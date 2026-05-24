<?php

use League\Csv\Reader;

/**
 * 
 * Indicator Data Structure Definition (DSD) Model
 * 
 * Manages data structure columns for indicator/timeseries projects
 * Similar to Editor_variable_model but for SDMX-compatible data structures
 * 
 */
class Indicator_dsd_model extends CI_Model {

    /**
     * Standard filename for indicator CSV data files
     */
    private $INDICATOR_DATA_FILENAME = 'indicator_data.csv';
    private $INDICATOR_FILE_ID = 'INDICATOR_DATA'; // Fixed file_id

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
    }

    /**
     * 
     * Get all DSD columns for a project
     * 
     * @param int $sid - Project ID
     * @param bool $metadata_detailed - Include detailed metadata
     * @param int $offset - Offset for pagination (default: 0)
     * @param int $limit - Limit for pagination (default: null, returns all)
     * @return array List of DSD columns
     * 
     **/
    function select_all($sid, $metadata_detailed = false, $offset = 0, $limit = null)
    {
        $this->load->library('Project_dsd_resolver');
        if (!$this->project_dsd_resolver->is_bound($sid)) {
            return array();
        }

        return $this->project_dsd_resolver->get_columns($sid, $metadata_detailed, $offset, $limit);
    }

    /**
     * 
     * Get a single DSD column by ID
     * 
     * @param int $sid - Project ID
     * @param int $id - Column ID
     * @return array|false DSD column or false if not found
     * 
     **/
    function get_row($sid, $id)
    {
        $this->load->library('Project_dsd_resolver');
        if (!$this->project_dsd_resolver->is_bound($sid)) {
            return false;
        }

        return $this->project_dsd_resolver->get_column_by_component_id($sid, $id, true);
    }

    /**
     * Column names starting with "_" are reserved for system/DuckDB fields (e.g. _ts_freq).
     *
     * @param string $name
     * @return bool true if reserved / not allowed for user-defined DSD columns
     */
    public static function is_reserved_system_column_name($name)
    {
        $name = (string) $name;
        return $name !== '' && $name[0] === '_';
    }


    /**
     * Single CSV header cell → DuckDB/FastAPI-safe label: only letters, digits, underscore, hyphen.
     * Other characters (spaces, dots, slashes, parentheses, etc.) become underscores; runs collapse.
     *
     * @param string $name
     * @return string
     */
    private function normalize_csv_header_token($name)
    {
        $n = trim((string) $name);
        $n = preg_replace('/[^A-Za-z0-9_-]+/', '_', $n);
        $n = preg_replace('/_+/', '_', $n);
        $n = trim($n, '_-');

        return $n !== '' ? $n : 'column';
    }

    /**
     * Normalize a list of header tokens and ensure case-insensitive uniqueness (suffix _2, _3, …).
     *
     * @param array $raw_names Original header strings from CSV
     * @return array Normalized, unique column names
     */
    private function normalize_csv_column_names($raw_names)
    {
        $normalized = array();
        foreach ($raw_names as $name) {
            $normalized[] = $this->normalize_csv_header_token($name);
        }
        $seen = array();
        $out = array();
        foreach ($normalized as $name) {
            $key = strtoupper($name);
            if (isset($seen[$key])) {
                $suffix = 2;
                do {
                    $candidate = $name . '_' . $suffix;
                    $key = strtoupper($candidate);
                    $suffix++;
                } while (isset($seen[$key]));
                $name = $candidate;
            }
            $seen[$key] = true;
            $out[] = $name;
        }

        return $out;
    }

    /**
     * Whether a mapping csvColumn refers to a physical header after rewrite (exact or same sanitized token).
     *
     * @param string $mapping_csv_col Value from import UI
     * @param string $header_h Column name from file (already rewritten)
     * @return bool
     */
    private function mapping_csv_column_matches_header($mapping_csv_col, $header_h)
    {
        $a = trim((string) $mapping_csv_col);
        $b = trim((string) $header_h);
        if ($a !== '' && $b !== '' && strcasecmp($a, $b) === 0) {
            return true;
        }

        return strcasecmp(
            $this->normalize_csv_header_token($a),
            $this->normalize_csv_header_token($b)
        ) === 0;
    }

    /**
     * Rewrite first CSV line to FastAPI-safe headers (for staging upload / DuckDB import).
     *
     * @param string $csv_file_path Absolute path
     * @return string[] Normalized header names
     * @throws Exception
     */
    public function rewrite_indicator_csv_headers_for_duckdb($csv_file_path)
    {
        if (!is_readable($csv_file_path)) {
            throw new Exception('CSV file is not readable');
        }

        return $this->rewrite_csv_with_normalized_headers($csv_file_path);
    }

    /**
     * Read normalized header names from the first row of a CSV (after DuckDB rewrite).
     *
     * @param string $csv_file_path Absolute path
     * @return string[]
     * @throws Exception
     */
    public function read_csv_header_names($csv_file_path)
    {
        if (!is_readable($csv_file_path)) {
            throw new Exception('CSV file is not readable');
        }

        $in = @fopen($csv_file_path, 'rb');
        if ($in === false) {
            throw new Exception('Failed to read CSV file');
        }

        $raw_first_line = fgets($in);
        fclose($in);

        if ($raw_first_line === false) {
            throw new Exception('CSV file has no header row');
        }

        $first_line_stripped = rtrim($raw_first_line, "\r\n");
        $headers = str_getcsv($first_line_stripped);
        if (empty($headers)) {
            throw new Exception('CSV file has no header row');
        }

        $out = array();
        foreach ($headers as $header) {
            $name = trim((string) $header);
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Validate CSV contains all DSD columns. Extra CSV columns are allowed and reported as ignored.
     *
     * @param string   $csv_path Absolute path (headers should already be rewritten for DuckDB)
     * @param string[] $expected_column_names Normalized DSD column names
     * @return array valid, missing_in_csv, ignored_columns, extra_in_csv, csv_columns, expected_columns, message?
     */
    public function validate_csv_headers_for_dsd($csv_path, array $expected_column_names)
    {
        $csv_headers = $this->read_csv_header_names($csv_path);
        $expected = $this->normalize_duckdb_staging_column_names($expected_column_names);

        $missing = array();
        $matched_csv = array();

        foreach ($expected as $exp) {
            $found = false;
            foreach ($csv_headers as $idx => $hdr) {
                if ($this->mapping_csv_column_matches_header($exp, $hdr)) {
                    $found = true;
                    $matched_csv[$idx] = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $exp;
            }
        }

        $ignored = array();
        foreach ($csv_headers as $idx => $hdr) {
            if (empty($matched_csv[$idx])) {
                $ignored[] = $hdr;
            }
        }

        $valid = empty($missing);
        $result = array(
            'valid' => $valid,
            'missing_in_csv' => $missing,
            'ignored_columns' => $ignored,
            'extra_in_csv' => $ignored,
            'csv_columns' => $csv_headers,
            'expected_columns' => $expected,
        );

        if (!$valid) {
            $result['message'] = 'CSV is missing required data structure columns.';
        }

        return $result;
    }

    /**
     * Rewrite CSV in place keeping only columns defined in the DSD (streaming; safe for large files).
     *
     * @param string   $csv_file_path Absolute path (headers should already be rewritten for DuckDB)
     * @param string[] $expected_column_names Normalized DSD column names
     * @return array ignored_columns, columns_kept
     * @throws Exception
     */
    public function trim_csv_to_dsd_columns($csv_file_path, array $expected_column_names)
    {
        if (!is_readable($csv_file_path)) {
            throw new Exception('CSV file is not readable');
        }

        $expected = $this->normalize_duckdb_staging_column_names($expected_column_names);
        $csv_headers = $this->read_csv_header_names($csv_file_path);

        $column_indices = array();
        $output_headers = array();
        foreach ($expected as $exp) {
            $found_idx = null;
            foreach ($csv_headers as $idx => $hdr) {
                if ($this->mapping_csv_column_matches_header($exp, $hdr)) {
                    $found_idx = $idx;
                    break;
                }
            }
            if ($found_idx === null) {
                throw new Exception('CSV is missing required data structure column: ' . $exp);
            }
            $column_indices[] = $found_idx;
            $output_headers[] = $exp;
        }

        $matched = array();
        foreach ($column_indices as $idx) {
            $matched[$idx] = true;
        }
        $ignored = array();
        foreach ($csv_headers as $idx => $hdr) {
            if (empty($matched[$idx])) {
                $ignored[] = $hdr;
            }
        }

        $in = @fopen($csv_file_path, 'rb');
        if ($in === false) {
            throw new Exception('Failed to read CSV file');
        }

        $raw_first_line = fgets($in);
        if ($raw_first_line === false) {
            fclose($in);
            throw new Exception('CSV file has no header row');
        }

        $tmp_path = $csv_file_path . '.trim_' . uniqid('', true);
        $out = @fopen($tmp_path, 'wb');
        if ($out === false) {
            fclose($in);
            throw new Exception('Failed to write trimmed CSV file');
        }

        fwrite($out, $this->csv_line_from_values($output_headers) . "\n");

        while (($row = fgetcsv($in)) !== false) {
            if ($row === array(null) || ($row === false)) {
                continue;
            }
            $out_row = array();
            foreach ($column_indices as $idx) {
                $out_row[] = isset($row[$idx]) ? $row[$idx] : '';
            }
            if (count(array_filter($out_row, function ($v) {
                return trim((string) $v) !== '';
            })) === 0) {
                continue;
            }
            fwrite($out, $this->csv_line_from_values($out_row) . "\n");
        }

        fclose($in);
        fclose($out);

        if (!@rename($tmp_path, $csv_file_path)) {
            @unlink($tmp_path);
            throw new Exception('Failed to replace CSV with trimmed version');
        }

        return array(
            'ignored_columns' => $ignored,
            'columns_kept' => $output_headers,
        );
    }

    /**
     * Path for FastAPI replace-from-csv: full staging file, or a DSD-only copy when extras are omitted.
     * FastAPI rejects CSV files that contain columns outside expected_columns ("extra in CSV").
     * indicator_staging_upload.csv is never modified; a sibling indicator_staging_import.csv is trimmed instead
     * (kept on disk until the next import so async FastAPI jobs can read it).
     *
     * @param string   $staging_csv_path Absolute path to indicator_staging_upload.csv
     * @param string[] $expected_column_names Normalized DSD column names
     * @param bool     $keep_extra_columns
     * @return array path (string)
     * @throws Exception
     */
    public function resolve_csv_path_for_fastapi_import($staging_csv_path, array $expected_column_names, $keep_extra_columns = false)
    {
        if ($keep_extra_columns) {
            return array('path' => $staging_csv_path);
        }

        $dir = dirname($staging_csv_path);
        $import_csv = $dir . '/indicator_staging_import.csv';
        if (!@copy($staging_csv_path, $import_csv)) {
            throw new Exception('Could not copy staging CSV for import');
        }

        $this->trim_csv_to_dsd_columns($import_csv, $expected_column_names);

        return array('path' => $import_csv);
    }

    /**
     * Normalize header names for optional dsd_columns passed to draft-queue (same rules as file rewrite).
     *
     * @param string[] $names
     * @return string[]
     */
    public function normalize_duckdb_staging_column_names(array $names)
    {
        $out = array();
        foreach ($names as $n) {
            $out[] = $this->normalize_csv_header_token($n);
        }

        return $out;
    }

    /**
     * Find length of the first CSV line in content (respects quoted fields with commas/newlines).
     *
     * @param string $content Full file content
     * @return array [length of first line including line ending, line ending string]
     */
    private function find_first_csv_line_length($content)
    {
        $len = strlen($content);
        $in_quotes = false;
        $i = 0;
        while ($i < $len) {
            $c = $content[$i];
            if ($c === '"') {
                if ($in_quotes && $i + 1 < $len && $content[$i + 1] === '"') {
                    $i += 2;
                    continue;
                }
                $in_quotes = !$in_quotes;
                $i++;
                continue;
            }
            if (!$in_quotes && ($c === "\n" || $c === "\r")) {
                $line_end_len = ($c === "\r" && $i + 1 < $len && $content[$i + 1] === "\n") ? 2 : 1;
                return array($i + $line_end_len, substr($content, $i, $line_end_len));
            }
            $i++;
        }
        return array($len, "\n");
    }

    /**
     * Build a single CSV line from values (quotes values that contain comma, quote, or newline).
     *
     * @param array $values
     * @return string
     */
    private function csv_line_from_values($values)
    {
        $out = array();
        foreach ($values as $v) {
            $v = (string) $v;
            if (strpbrk($v, ",\"\r\n") !== false) {
                $v = '"' . str_replace('"', '""', $v) . '"';
            }
            $out[] = $v;
        }
        return implode(',', $out);
    }

    /**
     * Rewrite only the first line of the CSV with FastAPI-safe column names (invalid chars -> underscores).
     * Rest of the file is unchanged. Faster than re-writing the entire file.
     *
     * @param string $csv_file_path Path to the CSV file
     * @return array Normalized headers (same length as original)
     */
    private function rewrite_csv_with_normalized_headers($csv_file_path)
    {
        $in = @fopen($csv_file_path, 'rb');
        if ($in === false) {
            throw new Exception('Failed to read CSV file');
        }

        // Read the first line only — keep memory usage O(1) regardless of file size.
        $raw_first_line = fgets($in);
        if ($raw_first_line === false) {
            fclose($in);
            throw new Exception('CSV file has no header row');
        }

        // Detect and preserve the original line ending.
        if (substr($raw_first_line, -2) === "\r\n") {
            $line_ending = "\r\n";
        } elseif (substr($raw_first_line, -1) === "\r") {
            $line_ending = "\r";
        } else {
            $line_ending = "\n";
        }
        $first_line_stripped = rtrim($raw_first_line, "\r\n");

        $headers = str_getcsv($first_line_stripped);
        if (empty($headers)) {
            fclose($in);
            throw new Exception('CSV file has no header row');
        }

        $normalized_headers = $this->normalize_csv_column_names($headers);
        $new_first_line     = $this->csv_line_from_values($normalized_headers);

        // If nothing changed we can skip the rewrite entirely.
        if ($new_first_line === $first_line_stripped) {
            fclose($in);
            return $normalized_headers;
        }

        // Stream-write to a temp file next to the original, then atomically replace it.
        $tmp_path = $csv_file_path . '.rewrite_' . uniqid('', true);
        $out = @fopen($tmp_path, 'wb');
        if ($out === false) {
            fclose($in);
            throw new Exception('Failed to create temp file for CSV header rewrite');
        }

        fwrite($out, $new_first_line . $line_ending);

        while (!feof($in)) {
            $chunk = fread($in, 65536);
            if ($chunk !== false && $chunk !== '') {
                fwrite($out, $chunk);
            }
        }

        fclose($in);
        fclose($out);

        if (!rename($tmp_path, $csv_file_path)) {
            @unlink($tmp_path);
            throw new Exception('Failed to replace CSV with normalised header version');
        }

        return $normalized_headers;
    }

    /**
     * Filter CSV to only rows where indicator_id column matches project IDNO (case-insensitive).
     * Overwrites the file with header + matching rows only, so the stored CSV stays clean.
     *
     * @param string $csv_file_path Path to the CSV file (with normalized headers)
     * @param string $indicator_id_column CSV column name for indicator_id
     * @param string $project_idno Project indicator IDNO to keep
     * @return int Number of rows kept
     * @throws Exception if no rows match or file cannot be written
     */

    /**
     * Default FREQ code per time_period_format when no user FREQ column (from config indicator_dsd.php).
     *
     * @return array time_period_format => freq code
     */
    protected function get_dsd_default_freq_by_time_period_format_from_config()
    {
        $this->load->config('indicator_dsd', true);
        $map = $this->config->item('dsd_default_freq_by_time_period_format', 'indicator_dsd');

        return is_array($map) ? $map : array();
    }

    /**
     * Build optional time_spec for FastAPI promote (DuckDB _ts_year / _ts_freq).
     *
     * @param int   $sid
     * @param array $overrides optional: implied_freq_code (import-time constant FREQ when no periodicity column)
     * @return array
     */
    public function build_duckdb_promote_time_spec($sid, array $overrides = array())
    {
        $this->load->library('SDMX/Sdmx_time_period');
        $columns = $this->select_all($sid, true);
        $spec = array();

        foreach ($columns as $col) {
            if (empty($col['column_type']) || $col['column_type'] !== 'time_period') {
                continue;
            }
            $spec['time_column'] = $col['name'];
            break;
        }

        foreach ($columns as $col) {
            if (empty($col['column_type']) || $col['column_type'] !== 'periodicity') {
                continue;
            }
            $spec['freq_column'] = $col['name'];
            break;
        }

        if (empty($spec['time_column'])) {
            return $spec;
        }

        $implied = null;
        if (!empty($overrides['implied_freq_code'])) {
            $implied = trim((string) $overrides['implied_freq_code']);
        }
        if (($implied === null || $implied === '') && empty($spec['freq_column'])) {
            $this->load->model('Editor_project_dsd_model');
            $binding = $this->Editor_project_dsd_model->get_by_sid($sid);
            if ($binding && !empty($binding['implied_freq_code'])) {
                $implied = trim((string) $binding['implied_freq_code']);
            }
        }
        if ($implied !== null && $implied !== '') {
            $spec['implied_freq_code'] = $implied;
            $tf = $this->sdmx_time_period->format_for_freq($implied);
            if ($tf !== null) {
                $spec['time_period_format'] = $tf;
            }
        }

        $spec['default_freq_by_format'] = $this->get_dsd_default_freq_by_time_period_format_from_config();

        return $spec;
    }

    /**
     * Whether the bound structure has a periodicity (FREQ) column.
     *
     * @param int $sid
     * @return bool
     */
    public function project_has_periodicity_column($sid)
    {
        return $this->get_column_name_by_type($sid, 'periodicity') !== null;
    }

    /**
     * Normalize a physical column name for cross-checking DSD names with CSV/DuckDB headers
     * (spaces/dots → underscores, trim; same normalization as CSV import).
     *
     * @param string $name
     * @return string Uppercase key, or '' if unusable
     */
    private function dsd_physical_column_key($name)
    {
        $n = trim((string) $name);
        $n = preg_replace('/[\s.]+/', '_', $n);
        $n = trim($n, '_');
        if ($n === '') {
            return '';
        }

        return strtoupper($n);
    }

    /**
     * Resolve data columns for validation from published DuckDB timeseries.
     *
     * @param int $sid
     * @return array|null { source, column_keys: map upper=>name, row_count?, warning? }
     */
    private function resolve_indicator_data_validation_context($sid)
    {
        $this->load->model('Editor_project_dsd_model');
        $binding = $this->Editor_project_dsd_model->get_by_sid($sid);
        if (!$binding || empty($binding['has_published_data'])) {
            return null;
        }

        $this->load->library('indicator_duckdb_service');

        // Only validate against published timeseries data.
        // Staging (upload buffer) is provisional and must not be used here —
        // it would produce false validation errors before the import is complete.
        $page = $this->indicator_duckdb_service->timeseries_page($sid, 0, 1);
        if (is_array($page) && !empty($page['error'])) {
            $hc = isset($page['http_code']) ? (int) $page['http_code'] : 0;
            if ($hc === 404) {
                $this->Editor_project_dsd_model->clear_published_data($sid);
                return null;
            }

            return array(
                'skipped_api' => true,
                'warning' => 'Published data is recorded but the data API was unavailable; data checks were skipped.',
                'row_count' => isset($binding['published_row_count']) ? (int) $binding['published_row_count'] : null,
            );
        }

        if (is_array($page) && empty($page['error']) && !empty($page['columns']) && is_array($page['columns'])) {
            $map = array();
            foreach ($page['columns'] as $col) {
                $nm = '';
                if (is_array($col) && isset($col['name'])) {
                    $nm = trim((string) $col['name']);
                } elseif (is_string($col)) {
                    $nm = trim($col);
                }
                if ($nm === '') {
                    continue;
                }
                $k = $this->dsd_physical_column_key($nm);
                if ($k !== '') {
                    $map[$k] = $nm;
                }
            }
            if (count($map) > 0) {
                $row_count = isset($page['total_row_count']) ? (int) $page['total_row_count'] : null;
                if ($row_count !== null) {
                    $this->Editor_project_dsd_model->mark_published_data($sid, $row_count);
                }

                return array(
                    'source' => 'timeseries',
                    'column_keys' => $map,
                    'row_count' => $row_count,
                    'warning' => null,
                );
            }
        }

        $this->Editor_project_dsd_model->clear_published_data($sid);
        return null;
    }

    /**
     * Record successful publish of indicator timeseries data (MySQL tracking; avoids FastAPI probe when unset).
     *
     * @param int      $sid
     * @param int|null $row_count
     * @return bool
     */
    public function record_published_data_import($sid, $row_count = null)
    {
        $this->load->model('Editor_project_dsd_model');
        if (!$this->Editor_project_dsd_model->get_by_sid($sid)) {
            return false;
        }

        return $this->Editor_project_dsd_model->mark_published_data($sid, $row_count);
    }

    /**
     * Clear published-data tracking when timeseries is dropped or structure is replaced.
     *
     * @param int $sid
     * @return bool
     */
    public function clear_published_data_tracking($sid)
    {
        $this->load->model('Editor_project_dsd_model');
        if (!$this->Editor_project_dsd_model->get_by_sid($sid)) {
            return false;
        }

        return $this->Editor_project_dsd_model->clear_published_data($sid);
    }

    /**
     * @param array|null $job_body poll_job() result when status is done
     * @return int|null
     */
    public function extract_row_count_from_import_job($job_body)
    {
        if (!is_array($job_body)) {
            return null;
        }
        foreach (array('row_count', 'rows_imported', 'total_row_count') as $key) {
            if (isset($job_body[$key]) && $job_body[$key] !== '') {
                return (int) $job_body[$key];
            }
        }
        if (!empty($job_body['info']) && is_array($job_body['info'])) {
            foreach (array('row_count', 'rows_imported', 'total_row_count') as $key) {
                if (isset($job_body['info'][$key]) && $job_body['info'][$key] !== '') {
                    return (int) $job_body['info'][$key];
                }
            }
        }
        if (!empty($job_body['data']) && is_array($job_body['data'])) {
            foreach (array('row_count', 'rows_imported', 'total_row_count') as $key) {
                if (isset($job_body['data'][$key]) && $job_body['data'][$key] !== '') {
                    return (int) $job_body['data'][$key];
                }
            }
        }

        return null;
    }

    /**
     * @param array $dsd_columns rows like select_all($sid, true)
     * @param array $column_key_to_name map uppercase key => physical name in data
     * @param string $source timeseries
     * @return array [ list $errors, list $warnings ]
     */
    private function validate_dsd_columns_against_data_keys(array $dsd_columns, array $column_key_to_name, $source)
    {
        $errors = array();
        $warnings = array();
        $label = 'published data (timeseries)';

        foreach ($dsd_columns as $col) {
            $name = isset($col['name']) ? trim((string) $col['name']) : '';
            if ($name === '') {
                continue;
            }
            if (self::is_reserved_system_column_name($name)) {
                continue;
            }
            $key = $this->dsd_physical_column_key($name);
            if ($key === '') {
                continue;
            }
            if (!isset($column_key_to_name[$key])) {
                $errors[] = "DSD column '{$name}' is not present in {$label}.";
            }
        }

        return array($errors, $warnings);
    }

    /**
     * Structural errors: vocabulary is set (global or inline) but the list is missing or has no codes.
     *
     * @param int $sid
     * @param array $columns select_all(..., true)
     * @return string[] error messages
     */
    private function collect_dsd_codelist_definition_errors($sid, array $columns)
    {
        $errors = array();
        $this->load->model('Codelists_model');

        foreach ($columns as $column) {
            $label = isset($column['name']) && $column['name'] !== '' ? $column['name'] : ('#' . (isset($column['id']) ? $column['id'] : '?'));
            $ctype = isset($column['codelist_type']) ? $column['codelist_type'] : 'none';
            $col_type = isset($column['column_type']) ? $column['column_type'] : '';

            if ($ctype === 'global') {
                $pk = isset($column['global_codelist_id']) ? (int) $column['global_codelist_id'] : 0;
                if ($pk <= 0) {
                    $errors[] = "Column '{$label}': global vocabulary is selected but no registry codelist is linked (global_codelist_id).";
                } else {
                    $cl = $this->resolve_global_registry_codelist_row($column);
                    if (!$cl) {
                        $errors[] = "Column '{$label}': global vocabulary registry id {$pk} was not found.";
                    } else {
                        $codes = $this->Codelists_model->get_codes((int) $cl['id'], null, false);
                        if (!is_array($codes) || count($codes) === 0) {
                            $errors[] = "Column '{$label}': linked global codelist has no codes.";
                        }
                    }
                }
            }

            $meta = isset($column['metadata']) && is_array($column['metadata']) ? $column['metadata'] : array();

            if ($ctype === 'none' && !empty($column['code_list']) && is_array($column['code_list']) && count($column['code_list']) > 0) {
                $imap = $this->dsd_allow_map_from_code_rows($column['code_list']);
                if (count($imap) === 0) {
                    $errors[] = "Column '{$label}': inline code list has no valid codes (entries are missing or empty).";
                }
            }
        }

        return $errors;
    }

    /**
     * Resolve registry codelist row from global_codelist_id (codelists.id).
     *
     * @param array $column resolved DSD column row (decoded)
     * @return array|null codelists row or null
     */
    private function resolve_global_registry_codelist_row(array $column)
    {
        $this->load->model('Codelists_model');
        $pk = isset($column['global_codelist_id']) ? (int) $column['global_codelist_id'] : 0;
        if ($pk <= 0) {
            return null;
        }
        $row = $this->Codelists_model->get_by_id($pk);
        return is_array($row) ? $row : null;
    }

    /**
     * Build lookup map code => true from codelist_items rows or inline code_list entries.
     *
     * @param array $rows get_codes() rows or inline code_list entries
     * @return array
     */
    private function dsd_allow_map_from_code_rows(array $rows)
    {
        $m = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $c = isset($row['code']) ? trim((string) $row['code']) : '';
            if ($c === '') {
                continue;
            }
            $m[$c] = true;
        }

        return $m;
    }

    /**
     * Allowed codes for one DSD column (multiple constraints = value must match every map).
     *
     * @param int $sid
     * @param array $column resolved DSD column row (decoded)
     * @return array [ list of [ 'label' => string, 'map' => array code=>true ] ], empty = no codelist enforcement
     */
    private function dsd_column_codelist_constraints($sid, array $column)
    {
        $constraints = array();
        $this->load->model('Codelists_model');

        $ctype = isset($column['codelist_type']) ? $column['codelist_type'] : 'none';
        $col_type = isset($column['column_type']) ? $column['column_type'] : '';
        $meta = isset($column['metadata']) && is_array($column['metadata']) ? $column['metadata'] : array();

        if ($ctype === 'global') {
            $cl = $this->resolve_global_registry_codelist_row($column);
            if ($cl) {
                $codes = $this->Codelists_model->get_codes((int) $cl['id'], null, false);
                if (is_array($codes) && count($codes) > 0) {
                    $constraints[] = array(
                        'label' => 'global vocabulary',
                        'map' => $this->dsd_allow_map_from_code_rows($codes),
                    );
                }
            }
        } elseif ($ctype === 'none' && !empty($column['code_list']) && is_array($column['code_list'])) {
            $imap = $this->dsd_allow_map_from_code_rows($column['code_list']);
            if (count($imap) > 0) {
                $constraints[] = array(
                    'label' => 'inline code list',
                    'map' => $imap,
                );
            }
        }

        return $constraints;
    }

    /**
     * Distinct non-empty values for one physical column from published timeseries (DuckDB).
     *
     * @param int $sid
     * @param string $physical_name
     * @return array [ string[] distinct values, bool truncated ]
     */
    private function fetch_distinct_values_for_dsd_data_column($sid, $physical_name)
    {
        $this->load->library('indicator_duckdb_service');
        $physical_name = trim((string) $physical_name);
        $truncated = false;
        $values = array();

        if ($physical_name === '') {
            return array(array(), false);
        }

        $raw = $this->indicator_duckdb_service->timeseries_distinct_pairs($sid, $physical_name, null, 20000);
        if (is_array($raw) && empty($raw['error']) && !empty($raw['items']) && is_array($raw['items'])) {
            foreach ($raw['items'] as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $c = isset($it['code']) ? trim((string) $it['code']) : '';
                if ($c !== '') {
                    $values[$c] = true;
                }
            }
            if (!empty($raw['truncated'])) {
                $truncated = true;
            }
        }

        return array(array_keys($values), $truncated);
    }

    /**
     * Data-phase: every distinct value must appear in each applicable codelist (AND across constraints).
     *
     * @param int $sid
     * @param array $dsd_columns
     * @param array $ctx from resolve_indicator_data_validation_context
     * @return array [ errors[], warnings[] ]
     */
    private function validate_dsd_codelist_values_against_data($sid, array $dsd_columns, array $ctx)
    {
        $errors = array();
        $warnings = array();

        foreach ($dsd_columns as $col) {
            $name = isset($col['name']) ? trim((string) $col['name']) : '';
            if ($name === '') {
                continue;
            }
            if (self::is_reserved_system_column_name($name)) {
                continue;
            }

            $constraints = $this->dsd_column_codelist_constraints($sid, $col);
            if (empty($constraints)) {
                continue;
            }

            $key = $this->dsd_physical_column_key($name);
            if ($key === '' || !isset($ctx['column_keys'][$key])) {
                continue;
            }

            $physical = $ctx['column_keys'][$key];
            list($distinct_vals, $truncated) = $this->fetch_distinct_values_for_dsd_data_column(
                $sid,
                $physical
            );
            if ($truncated) {
                $warnings[] = "Column '{$name}': codelist validation may be incomplete (distinct value limit reached).";
            }

            foreach ($distinct_vals as $v) {
                if ($v === '') {
                    continue;
                }
                foreach ($constraints as $c) {
                    if (!isset($c['map'][$v])) {
                        $errors[] = "Column '{$name}': value '" . $v . "' is not allowed by " . $c['label'] . '.';
                        break;
                    }
                }
            }
        }

        return array($errors, $warnings);
    }

    /**
     * Minimal timeseries_page-shaped payload so chart_timeseries_slice_context can resolve physical columns.
     *
     * @param array $ctx resolve_indicator_data_validation_context result
     * @return array
     */
    private function validation_synthetic_page_from_column_keys(array $ctx)
    {
        $cols = array();
        foreach ($ctx['column_keys'] as $phys) {
            $cols[] = array('name' => $phys);
        }

        return array('columns' => $cols);
    }

    /**
     * SDMX-style observation key: time_period × slice facets (same as chart-aggregate: geography, dimension, measure, periodicity when codelist), excluding observation value and attributes/annotations.
     * Only rows with non-empty observation value are counted (matches chart-aggregate WHERE on value column).
     *
     * @param int $sid
     * @param array $ctx resolve_indicator_data_validation_context
     * @return array{0: string[], 1: string[], 2: array}
     */
    private function validate_dsd_observation_key_uniqueness($sid, array $ctx)
    {
        $errors = array();
        $warnings = array();
        $meta = array(
            'skipped' => true,
            'reason' => null,
            'semantics' => 'time_period_geography_dimensions_measure_periodicity',
            'key_columns' => array(),
            'value_column' => null,
            'table_rows_read' => null,
            'rows_with_observation_value' => null,
            'unique_observation_count' => null,
            'duplicate_row_count' => null,
            'scan_truncated' => false,
            'valid' => null,
        );

        if (!$ctx || empty($ctx['source']) || $ctx['source'] !== 'timeseries') {
            $meta['reason'] = 'Observation key uniqueness requires published timeseries data.';

            return array($errors, $warnings, $meta);
        }

        $page = $this->validation_synthetic_page_from_column_keys($ctx);
        try {
            $slice_ctx = $this->chart_timeseries_slice_context($sid, $page, self::chart_observation_key_slice_column_types());
        } catch (Throwable $e) {
            $meta['reason'] = 'Could not derive observation key from DSD and data columns: ' . $e->getMessage();

            return array($errors, $warnings, $meta);
        }

        $phys_time = $slice_ctx['phys_time'];
        $phys_val = $slice_ctx['phys_val'];
        $phys_slices = array();
        $meta['key_columns'][] = array(
            'dsd_name' => $slice_ctx['time_dsd_name'],
            'physical_name' => $phys_time,
            'column_type' => 'time_period',
        );
        foreach ($slice_ctx['slice_facets'] as $sf) {
            $phys_slices[] = $sf['physical'];
            $meta['key_columns'][] = array(
                'dsd_name' => $sf['name'],
                'physical_name' => $sf['physical'],
                'column_type' => isset($sf['column_type']) ? (string) $sf['column_type'] : 'dimension',
            );
        }
        $meta['value_column'] = array(
            'dsd_name' => $slice_ctx['value_dsd_name'],
            'physical_name' => $phys_val,
            'column_type' => 'observation_value',
        );
        $meta['skipped'] = false;

        if ($ctx['source'] !== 'timeseries') {
            $meta['skipped'] = true;
            $meta['reason'] = 'Observation key uniqueness is only checked on published timeseries.';

            return array($errors, $warnings, $meta);
        }

        $this->load->library('indicator_duckdb_service');
        $api = $this->indicator_duckdb_service->timeseries_observation_key_validate($sid, array(
            'time_column' => $phys_time,
            'value_column' => $phys_val,
            'slice_columns' => $phys_slices,
        ));

        if (!is_array($api) || !empty($api['error'])) {
            $msg = isset($api['message']) ? (string) $api['message'] : 'Observation key validation request failed.';
            $errors[] = 'Observation key uniqueness could not be checked via DuckDB: ' . $msg;
            $meta['valid'] = false;
            $meta['aggregate_source'] = null;

            return array($errors, $warnings, $meta);
        }

        $rows_with_value = isset($api['rows_with_observation_value']) ? (int) $api['rows_with_observation_value'] : 0;
        $unique = isset($api['unique_observation_key_count']) ? (int) $api['unique_observation_key_count'] : 0;
        $dup = isset($api['duplicate_row_count']) ? (int) $api['duplicate_row_count'] : 0;
        $table_total = isset($api['table_total_row_count']) ? (int) $api['table_total_row_count'] : null;

        $meta['table_rows_read'] = $rows_with_value;
        $meta['rows_with_observation_value'] = $rows_with_value;
        $meta['unique_observation_count'] = $unique;
        $meta['duplicate_row_count'] = $dup;
        $meta['scan_truncated'] = false;
        $meta['aggregate_source'] = isset($api['source']) ? (string) $api['source'] : 'duckdb';
        if ($table_total !== null) {
            $meta['table_total_row_count'] = $table_total;
        }
        if (isset($api['duplicate_key_group_count'])) {
            $meta['duplicate_key_group_count'] = (int) $api['duplicate_key_group_count'];
        }

        if ($rows_with_value === 0) {
            $meta['valid'] = true;
            $warnings[] = 'No rows with a non-empty observation value were found; uniqueness was not tested.';

            return array($errors, $warnings, $meta);
        }

        if ($dup > 0) {
            $meta['valid'] = false;
            $key_label = implode(', ', array_map(function ($c) {
                return isset($c['dsd_name']) ? (string) $c['dsd_name'] : '';
            }, $meta['key_columns']));
            $errors[] = "Duplicate observation keys: {$rows_with_value} rows with values map to {$unique} distinct keys (columns: {$key_label}). "
                . 'At most one row per key (time × geography, dimensions, measure, periodicity when in key; attributes and annotations excluded).';

            return array($errors, $warnings, $meta);
        }

        $meta['valid'] = true;

        return array($errors, $warnings, $meta);
    }

    /**
     * @param array $structure validate_dsd_structure result (includes 'columns' key)
     * @param array $data_validation
     * @return array API-shaped validation payload
     */
    private function merge_dsd_validation_response(array $structure, array $data_validation)
    {
        $errors = $structure['errors'];
        $warnings = array_merge($structure['warnings'], $data_validation['warnings']);
        if (!$data_validation['skipped'] && isset($data_validation['valid']) && $data_validation['valid'] === false) {
            $errors = array_merge($errors, $data_validation['errors']);
        }

        $overall = $structure['valid']
            && ($data_validation['skipped'] || (isset($data_validation['valid']) && $data_validation['valid'] === true));

        $structure_block = array(
            'valid' => $structure['valid'],
            'errors' => $structure['errors'],
            'warnings' => $structure['warnings'],
            'summary' => $structure['summary'],
        );
        if (!empty($structure['roles'])) {
            $structure_block['roles'] = $structure['roles'];
        }

        return array(
            'valid' => $overall,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => $structure['summary'],
            'structure' => $structure_block,
            'data_validation' => $data_validation,
        );
    }

    /**
     * DSD structure only (MySQL definitions): SDMX roles, types, time/FREQ metadata rules.
     * Does not read CSV or DuckDB.
     *
     * @param int $sid
     * @return array valid, errors, warnings, summary, columns (internal)
     */
    public function validate_dsd_structure($sid)
    {
        $columns = $this->select_all($sid, true);

        $this->load->library('Indicator_dsd_structure_validate');
        $result = $this->indicator_dsd_structure_validate->validate_columns(
            $columns,
            array('include_role_checklist' => true)
        );

        $errors = $result['errors'];
        $warnings = $result['warnings'];

        foreach ($this->collect_dsd_codelist_definition_errors($sid, $columns) as $msg) {
            $errors[] = $msg;
        }

        $valid_attachment_levels  = array('DataSet', 'Series', 'Observation');
        $valid_assignment_statuses = array('Mandatory', 'Conditional');
        foreach ($columns as $column) {
            $ct = $column['column_type'];
            if ($ct !== 'attribute' && $ct !== 'annotation') {
                continue;
            }
            $meta = isset($column['metadata']) && is_array($column['metadata']) ? $column['metadata'] : array();
            $al = isset($meta['attachment_level']) ? $meta['attachment_level'] : null;
            $as = isset($meta['assignment_status']) ? $meta['assignment_status'] : null;
            // Both fields are optional; only warn when a value is present but not valid.
            if ($al !== null && $al !== '' && !in_array($al, $valid_attachment_levels, true)) {
                $warnings[] = "Column '{$column['name']}' ({$ct}): attachment_level '{$al}' is not a recognised value (DataSet, Series, Observation).";
            }
            if ($as !== null && $as !== '' && !in_array($as, $valid_assignment_statuses, true)) {
                $warnings[] = "Column '{$column['name']}' ({$ct}): assignment_status '{$as}' is not a recognised value (Mandatory, Conditional).";
            }
        }

        $result['errors'] = $errors;
        $result['warnings'] = $warnings;
        $result['valid'] = count($errors) === 0;

        return $result;
    }

    /**
     * Validate DSD structure, then data presence vs structure when the structure is valid.
     * Data checks are skipped if structure validation fails.
     *
     * Validation rules (structure):
     * - Required (must have exactly 1): indicator_id, time_period, observation_value
     * - Recommended (0 or 1): geography (warning if missing)
     * - Optional single (0 or 1): periodicity, indicator_name
     * - Optional multiple (0 or more): dimension, measure (SDMX measure as a slice dimension), attribute, annotation
     *
     * Data (when structure valid and data present): column presence, codelist allow-lists, and (published timeseries only)
     * observation-key uniqueness: time_period plus geography, dimension, measure, and periodicity (when in data and periodicity has
     * a resolved codelist) — attributes and annotations are never part of the key; among rows with non-empty observation value.
     *
     * @param int $sid - Project ID
     * @return array valid, errors, warnings, summary, structure, data_validation
     */
    public function validate_dsd($sid)
    {
        $structure = $this->validate_dsd_structure($sid);

        $data_validation = array(
            'skipped' => true,
            'has_data' => false,
            'source' => null,
            'valid' => null,
            'errors' => array(),
            'warnings' => array(),
            'reason' => null,
            'row_count' => null,
            'observation_key' => null,
        );

        if (!$structure['valid']) {
            $data_validation['reason'] = 'DSD structure validation failed; data checks were not run.';

            return $this->merge_dsd_validation_response($structure, $data_validation);
        }

        $ctx = $this->resolve_indicator_data_validation_context($sid);
        if ($ctx === null) {
            $this->load->model('Editor_project_dsd_model');
            $binding = $this->Editor_project_dsd_model->get_by_sid($sid);
            if (!$binding || empty($binding['has_published_data'])) {
                $data_validation['reason'] = 'No published data attached; data checks were not run.';
            } else {
                $data_validation['reason'] = 'No published timeseries data found; data checks were not run.';
            }

            return $this->merge_dsd_validation_response($structure, $data_validation);
        }

        if (!empty($ctx['skipped_api'])) {
            $data_validation['reason'] = isset($ctx['warning']) ? $ctx['warning'] : 'Data API unavailable; data checks were skipped.';
            $data_validation['warnings'][] = $data_validation['reason'];
            if (!empty($ctx['row_count'])) {
                $data_validation['has_data'] = true;
                $data_validation['row_count'] = (int) $ctx['row_count'];
            }

            return $this->merge_dsd_validation_response($structure, $data_validation);
        }

        $data_validation['skipped'] = false;
        $data_validation['has_data'] = true;
        $data_validation['source'] = $ctx['source'];
        $data_validation['row_count'] = isset($ctx['row_count']) ? $ctx['row_count'] : null;
        if (!empty($ctx['warning'])) {
            $data_validation['warnings'][] = $ctx['warning'];
        }

        list($derr, $dwarn) = $this->validate_dsd_columns_against_data_keys(
            $structure['columns'],
            $ctx['column_keys'],
            $ctx['source']
        );
        list($cerr, $cwarn) = $this->validate_dsd_codelist_values_against_data(
            $sid,
            $structure['columns'],
            $ctx
        );
        list($oerr, $owarn, $obs_key_meta) = $this->validate_dsd_observation_key_uniqueness($sid, $ctx);
        $data_validation['observation_key'] = $obs_key_meta;
        $data_validation['errors'] = array_merge($derr, $cerr, $oerr);
        $data_validation['warnings'] = array_merge($data_validation['warnings'], $dwarn, $cwarn, $owarn);
        $data_validation['valid'] = count($data_validation['errors']) === 0;

        return $this->merge_dsd_validation_response($structure, $data_validation);
    }

    /**
     * Upload and store CSV file for indicator project (singleton pattern)
     * Always overwrites existing file if present
     * Renames file to standard name: indicator_data.csv
     * 
     * @param int $sid - Project ID
     * @param string $uploaded_file_path - Path to uploaded temporary file
     * @param int $user_id - User ID
     * @return array - File upload result with file_id
     */
    public function upload_indicator_csv($sid, $uploaded_file_path, $user_id = null)
    {
        $this->load->model('Editor_datafile_model');
        
        if (!file_exists($uploaded_file_path)) {
            throw new Exception("Uploaded file not found: " . $uploaded_file_path);
        }
        
        // Get or create the single datafile record
        $existing_file = $this->get_indicator_datafile($sid);
        
        // Ensure project folder exists
        $project_folder = $this->Editor_model->get_project_folder($sid);
        if (!$project_folder) {
            $this->Editor_model->create_project_folder($sid);
            $project_folder = $this->Editor_model->get_project_folder($sid);
        }
        
        $data_folder = $project_folder . '/data/';
        if (!file_exists($data_folder)) {
            @mkdir($data_folder, 0777, true);
        }
        
        // Target file path with standard name
        $target_file_path = $data_folder . $this->INDICATOR_DATA_FILENAME;
        
        // Move uploaded file to target location (overwrite if exists)
        if (!@copy($uploaded_file_path, $target_file_path)) {
            throw new Exception("Failed to save CSV file to: " . $target_file_path);
        }
        
        // Update or create database record
        if ($existing_file) {
            // Update existing record
            $update_data = array(
                'file_physical_name' => $this->INDICATOR_DATA_FILENAME,
                'file_name' => 'indicator_data', // Without extension
                'changed_by' => $user_id,
                'changed' => time(),
                'store_data' => 1
            );
            $this->Editor_datafile_model->update($existing_file['id'], $update_data);
            $file_id = $existing_file['file_id'];
        } else {
            // Create new record
            $insert_data = array(
                'sid' => $sid,
                'file_id' => $this->INDICATOR_FILE_ID,
                'file_physical_name' => $this->INDICATOR_DATA_FILENAME,
                'file_name' => 'indicator_data',
                'store_data' => 1,
                'created_by' => $user_id,
                'changed_by' => $user_id,
                'created' => time(),
                'changed' => time()
            );
            $this->Editor_datafile_model->insert($sid, $insert_data);
            $file_id = $this->INDICATOR_FILE_ID;
        }
        
        return array(
            'file_id' => $file_id,
            'file_name' => $this->INDICATOR_DATA_FILENAME,
            'file_path' => $target_file_path
        );
    }

    /**
     * Absolute path for the canonical indicator archive CSV (may not exist yet).
     *
     * @param int $sid
     * @return string|null
     */
    public function resolve_indicator_data_csv_absolute_path($sid)
    {
        $this->Editor_model->create_project_folder($sid);
        $project_folder = $this->Editor_model->get_project_folder($sid);
        if (!$project_folder) {
            return null;
        }

        return $project_folder . '/data/' . $this->INDICATOR_DATA_FILENAME;
    }

    /**
     * Update editor_datafiles for indicator_data.csv when the file already exists on disk.
     *
     * @param int $sid
     * @param int|null $user_id
     * @return array
     * @throws Exception
     */
    public function register_indicator_datafile($sid, $user_id = null)
    {
        $target_file_path = $this->resolve_indicator_data_csv_absolute_path($sid);
        if (!$target_file_path || !is_file($target_file_path)) {
            throw new Exception('Indicator data CSV not found: ' . ($target_file_path ?: 'unknown path'));
        }

        $this->load->model('Editor_datafile_model');
        $existing_file = $this->get_indicator_datafile($sid);

        if ($existing_file) {
            $update_data = array(
                'file_physical_name' => $this->INDICATOR_DATA_FILENAME,
                'file_name' => 'indicator_data',
                'changed_by' => $user_id,
                'changed' => time(),
                'store_data' => 1,
            );
            $this->Editor_datafile_model->update($existing_file['id'], $update_data);
            $file_id = $existing_file['file_id'];
        } else {
            $insert_data = array(
                'sid' => $sid,
                'file_id' => $this->INDICATOR_FILE_ID,
                'file_physical_name' => $this->INDICATOR_DATA_FILENAME,
                'file_name' => 'indicator_data',
                'store_data' => 1,
                'created_by' => $user_id,
                'changed_by' => $user_id,
                'created' => time(),
                'changed' => time(),
            );
            $this->Editor_datafile_model->insert($sid, $insert_data);
            $file_id = $this->INDICATOR_FILE_ID;
        }

        return array(
            'file_id' => $file_id,
            'file_name' => $this->INDICATOR_DATA_FILENAME,
            'file_path' => $target_file_path,
        );
    }

    /**
     * Delete indicator_data.csv and regenerate from DuckDB via FastAPI export-to-file job.
     *
     * @param int $sid
     * @param int|null $user_id
     * @param int $max_wait_seconds
     * @return array path, row_count, job_id, job
     * @throws Exception
     */
    public function regenerate_indicator_csv_from_duckdb($sid, $user_id = null, $max_wait_seconds = 1800)
    {
        $this->delete_indicator_csv($sid);

        $output_path = $this->resolve_indicator_data_csv_absolute_path($sid);
        if (!$output_path) {
            throw new Exception('Project folder not available');
        }

        $data_dir = dirname($output_path);
        if (!is_dir($data_dir)) {
            @mkdir($data_dir, 0777, true);
        }

        $this->load->library('indicator_duckdb_service');
        $queue = $this->indicator_duckdb_service->timeseries_export_to_file_queue($sid, $output_path);

        if (is_array($queue) && !empty($queue['error'])) {
            throw new Exception(isset($queue['message']) ? $queue['message'] : 'FastAPI export-to-file request failed');
        }
        if (empty($queue['job_id'])) {
            throw new Exception('FastAPI did not return job_id for export-to-file');
        }

        $poll = $this->indicator_duckdb_service->poll_job($queue['job_id'], $max_wait_seconds, 3);
        if (!is_array($poll) || ($poll['status'] ?? '') !== 'done') {
            $err = isset($poll['error']) ? $poll['error'] : 'Export-to-file did not complete';
            throw new Exception($err);
        }

        $registered = $this->register_indicator_datafile($sid, $user_id);
        $row_count = $this->extract_row_count_from_import_job($poll);

        return array(
            'path' => $registered['file_path'],
            'row_count' => $row_count,
            'job_id' => $queue['job_id'],
            'job' => $poll,
        );
    }

    /**
     * Ensure indicator_data.csv exists when DuckDB has published data (legacy backfill).
     * Skips when the archive file is already present.
     *
     * @param int $sid
     * @param int|null $user_id
     * @return array skipped?, path?, row_count?
     * @throws Exception when export is required and fails
     */
    public function ensure_indicator_data_csv($sid, $user_id = null)
    {
        $path = $this->resolve_indicator_data_csv_absolute_path($sid);
        if ($path && is_file($path) && is_readable($path)) {
            return array(
                'skipped' => true,
                'reason' => 'file_exists',
                'path' => $path,
            );
        }

        $this->load->model('Editor_project_dsd_model');
        if (!$this->Editor_project_dsd_model->has_published_data($sid)) {
            return array(
                'skipped' => true,
                'reason' => 'no_published_data',
            );
        }

        $result = $this->regenerate_indicator_csv_from_duckdb($sid, $user_id);
        $result['skipped'] = false;

        return $result;
    }

    /**
     * After DuckDB import: remove stale archive, export from DuckDB, register datafile.
     *
     * @param int $sid
     * @param int|null $user_id
     * @param int|null $row_count Optional row count from import job (fallback when export job omits it)
     * @return array
     * @throws Exception
     */
    public function finalize_indicator_data_import($sid, $user_id = null, $row_count = null)
    {
        $export = $this->regenerate_indicator_csv_from_duckdb($sid, $user_id);
        if ($row_count === null && isset($export['row_count'])) {
            $row_count = $export['row_count'];
        }
        $this->record_published_data_import($sid, $row_count);

        return $export;
    }

    /**
     * Get the single datafile for an indicator project
     * 
     * @param int $sid - Project ID
     * @return array|null - Datafile record or null if not found
     */
    public function get_indicator_datafile($sid)
    {
        $this->load->model('Editor_datafile_model');
        
        // Try to get by fixed file_id first
        $file = $this->Editor_datafile_model->data_file_by_id($sid, $this->INDICATOR_FILE_ID);
        if ($file) {
            return $file;
        }
        
        // Fallback: get first file if file_id doesn't match (for migration)
        $all_files = $this->Editor_datafile_model->select_all($sid);
        if (!empty($all_files)) {
            return reset($all_files); // Return first file
        }
        
        return null;
    }

    /**
     * Get file path for indicator CSV data
     * 
     * @param int $sid - Project ID
     * @return string|null - Full file path or null if not found
     */
    public function get_indicator_csv_path($sid)
    {
        $datafile = $this->get_indicator_datafile($sid);
        if (!$datafile) {
            return null;
        }
        
        $project_folder = $this->Editor_model->get_project_folder($sid);
        if (!$project_folder) {
            return null;
        }
        
        $file_path = $project_folder . '/data/' . $this->INDICATOR_DATA_FILENAME;
        
        // Check if file exists
        if (file_exists($file_path)) {
            return $file_path;
        }
        
        // Fallback: try to get path from datafile record
        if (isset($datafile['file_physical_name'])) {
            $fallback_path = $project_folder . '/data/' . $datafile['file_physical_name'];
            if (file_exists($fallback_path)) {
                return $fallback_path;
            }
        }
        
        return null;
    }

    /**
     * Delete indicator CSV data file
     * 
     * @param int $sid - Project ID
     * @return bool - True on success
     */
    public function delete_indicator_csv($sid)
    {
        $file_path = $this->resolve_indicator_data_csv_absolute_path($sid);
        if ($file_path && file_exists($file_path)) {
            @unlink($file_path);
        }

        $datafile = $this->get_indicator_datafile($sid);
        if (!$datafile) {
            return true;
        }
        
        $this->load->model('Editor_datafile_model');
        $this->Editor_datafile_model->delete($sid, $datafile['file_id']);
        
        return true;
    }

    /**
     * Valid code for inline CSV population: non-empty, DB-safe length, no control characters.
     * (Series / SDMX-style ids often use ".", "-", ":" etc.; strict alphanumeric was dropping them.)
     *
     * @param string $code
     * @return bool
     */
    private function is_valid_code_list_code($code)
    {
        $code = (string) $code;
        if ($code === '' || strlen($code) > 150) {
            return false;
        }

        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $code) !== 1;
    }

    /**
     * Resolve code_list items for UI: inline code_list on the column, else global registry codes.
     *
     * @param int $sid Project ID
     * @param array $column resolved DSD column row (decoded)
     * @return array[] { code, label }
     */
    public function resolve_column_code_list_items_for_ui($sid, array $column)
    {
        $out = array();
        $ctype = isset($column['codelist_type']) ? $column['codelist_type'] : 'none';

        if (!empty($column['code_list']) && is_array($column['code_list'])) {
            foreach ($column['code_list'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = isset($row['code']) ? trim((string) $row['code']) : '';
                if ($code === '') {
                    continue;
                }
                $lab = isset($row['label']) ? trim((string) $row['label']) : '';
                $out[] = array(
                    'code' => $code,
                    'label' => $lab !== '' ? $lab : $code,
                );
            }
            if (count($out) > 0) {
                return $out;
            }
        }

        if ($ctype === 'global') {
            $this->load->model('Codelists_model');
            $cl = $this->resolve_global_registry_codelist_row($column);
            if ($cl) {
                $codes = $this->Codelists_model->get_codes((int) $cl['id'], null, true);
                if (is_array($codes)) {
                    foreach ($codes as $cr) {
                        if (!is_array($cr)) {
                            continue;
                        }
                        $code = isset($cr['code']) ? trim((string) $cr['code']) : '';
                        if ($code === '') {
                            continue;
                        }
                        $label = $code;
                        if (!empty($cr['labels']) && is_array($cr['labels'])) {
                            foreach ($cr['labels'] as $lb) {
                                if (is_array($lb) && isset($lb['label']) && trim((string) $lb['label']) !== '') {
                                    $label = trim((string) $lb['label']);
                                    break;
                                }
                            }
                        }
                        $out[] = array('code' => $code, 'label' => $label);
                    }
                }
            }

            return $out;
        }

        return $out;
    }

    /**
     * Fill each column's code_list in-memory for API consumers (e.g. chart filters) when empty but linked to global registry.
     *
     * @param int $sid
     * @param array $columns from select_all
     * @return array
     */
    public function enrich_columns_resolved_code_lists($sid, array $columns)
    {
        foreach ($columns as $k => $col) {
            if (!is_array($col)) {
                continue;
            }
            $items = $this->resolve_column_code_list_items_for_ui($sid, $col);
            if (count($items) > 0) {
                $columns[$k]['code_list'] = $items;
            }
        }

        return $columns;
    }

    /**
     * Whether a column has at least one code after resolving inline / global codelists.
     *
     * @param int $sid
     * @param array $column resolved DSD column row (decoded)
     * @return bool
     */
    public function indicator_dsd_column_has_resolved_codelist($sid, array $column)
    {
        return count($this->resolve_column_code_list_items_for_ui($sid, $column)) > 0;
    }

    /**
     * DSD roles included in chart GROUP BY / series breakdown (SDMX-aligned: no indicator_id/name facets; measure as dimension).
     *
     * @return string[]
     */
    private static function chart_slice_dsd_column_types()
    {
        return array('geography', 'dimension', 'measure', 'attribute', 'periodicity', 'annotation');
    }

    /**
     * DSD roles included in observation-key uniqueness (with time_period): geography, dimensions, measure, periodicity — not attribute or annotation.
     *
     * @return string[]
     */
    private static function chart_observation_key_slice_column_types()
    {
        return array('geography', 'dimension', 'measure', 'periodicity');
    }

    /**
     * Uppercase key => physical column name from a timeseries/page response.
     *
     * @param array $page
     * @return array<string,string>
     */
    private function timeseries_page_column_upper_to_physical($page)
    {
        $map = array();
        if (empty($page['columns']) || !is_array($page['columns'])) {
            return $map;
        }
        foreach ($page['columns'] as $col) {
            $nm = '';
            if (is_array($col) && isset($col['name'])) {
                $nm = trim((string) $col['name']);
            } elseif (is_string($col)) {
                $nm = trim($col);
            }
            if ($nm === '') {
                continue;
            }
            $k = $this->dsd_physical_column_key($nm);
            if ($k !== '') {
                $map[$k] = $nm;
            }
        }

        return $map;
    }

    /**
     * Time/value physical columns and chart slice facets (DSD name + DuckDB physical), aligned with chart-aggregate.
     *
     * @param int $sid
     * @param array $page timeseries_page first page (columns)
     * @param string[]|null $slice_column_types If set, only these column_type values become slice facets (e.g. observation-key validation); null = full chart slice set.
     * @return array{key_map: array<string,string>, geography_upper: string|null, phys_time: string, phys_val: string, slice_facets: array<int,array{name:string,physical:string}>}
     * @throws Exception
     */
    private function chart_timeseries_slice_context($sid, $page, ?array $slice_column_types = null)
    {
        $key_map = $this->timeseries_page_column_upper_to_physical($page);
        if (empty($key_map)) {
            throw new Exception('Could not read timeseries columns from DuckDB.');
        }

        if ($slice_column_types === null) {
            $slice_column_types = self::chart_slice_dsd_column_types();
        }

        $columns = $this->enrich_columns_resolved_code_lists($sid, $this->select_all($sid, false));
        $time_dsd = null;
        $value_dsd = null;
        $geography_upper = null;
        $slice_dsds = array();

        foreach ($columns as $col) {
            $ct = isset($col['column_type']) ? $col['column_type'] : '';
            $uk = strtoupper(trim((string) $col['name']));
            if ($ct === 'time_period') {
                $time_dsd = $col;
            } elseif ($ct === 'observation_value') {
                $value_dsd = $col;
            } elseif ($ct === 'geography') {
                $geography_upper = $uk;
            }
            if (!in_array($ct, $slice_column_types, true)) {
                continue;
            }
            if (in_array($ct, array('periodicity', 'attribute', 'annotation'), true)) {
                if (!$this->indicator_dsd_column_has_resolved_codelist($sid, $col)) {
                    continue;
                }
            }
            $slice_dsds[] = $col;
        }

        if (!$time_dsd || !$value_dsd) {
            throw new Exception('DSD must include time_period and observation_value columns for chart data.');
        }

        $time_key = $this->dsd_physical_column_key($time_dsd['name']);
        $val_key = $this->dsd_physical_column_key($value_dsd['name']);
        if ($time_key === '' || $val_key === '' || !isset($key_map[$time_key]) || !isset($key_map[$val_key])) {
            throw new Exception('Time or observation column is missing from DuckDB timeseries table.');
        }

        $phys_time = $key_map[$time_key];
        $phys_val = $key_map[$val_key];

        $slice_facets = array();
        $seen_phys = array();
        foreach ($slice_dsds as $sc) {
            $sk = $this->dsd_physical_column_key($sc['name']);
            if ($sk === '' || !isset($key_map[$sk])) {
                continue;
            }
            $p = $key_map[$sk];
            if ($p === $phys_time || $p === $phys_val) {
                continue;
            }
            if (isset($seen_phys[$p])) {
                continue;
            }
            $seen_phys[$p] = true;
            $sc_ct = isset($sc['column_type']) ? (string) $sc['column_type'] : '';
            $slice_facets[] = array(
                'name' => (string) $sc['name'],
                'physical' => $p,
                'column_type' => $sc_ct,
            );
        }

        return array(
            'key_map' => $key_map,
            'geography_upper' => $geography_upper,
            'phys_time' => $phys_time,
            'phys_val' => $phys_val,
            'slice_facets' => $slice_facets,
            'time_dsd_name' => isset($time_dsd['name']) ? (string) $time_dsd['name'] : '',
            'value_dsd_name' => isset($value_dsd['name']) ? (string) $value_dsd['name'] : '',
        );
    }

    /**
     * Build FastAPI chart-aggregate body from DSD + filters. Validates slice filters.
     *
     * @param int $sid
     * @param array $filters geography, dimensions (upper DSD name => string[]), time_period_*, use_ts_year_for_time_filter
     * @param array $page timeseries_page first page (columns)
     * @return array
     * @throws Exception
     */
    private function build_chart_aggregate_spec($sid, $filters, $page)
    {
        $ctx = $this->chart_timeseries_slice_context($sid, $page);
        $key_map = $ctx['key_map'];
        $geography_upper = $ctx['geography_upper'];
        $phys_time = $ctx['phys_time'];
        $phys_val = $ctx['phys_val'];
        $slice_phys = array();
        foreach ($ctx['slice_facets'] as $sf) {
            $slice_phys[] = $sf['physical'];
        }

        $norm_dims = array();
        if (isset($filters['dimensions']) && is_array($filters['dimensions'])) {
            foreach ($filters['dimensions'] as $k => $v) {
                $norm_dims[strtoupper(trim((string) $k))] = is_array($v) ? $v : array();
            }
        }
        if (!empty($filters['geography']) && is_array($filters['geography']) && $geography_upper !== null) {
            if (!isset($norm_dims[$geography_upper]) || !is_array($norm_dims[$geography_upper])) {
                $norm_dims[$geography_upper] = array();
            }
            $norm_dims[$geography_upper] = array_values(array_unique(array_merge(
                $norm_dims[$geography_upper],
                $filters['geography']
            )));
        }

        $phys_filters = array();
        foreach ($norm_dims as $dsd_u => $vals) {
            if (!is_array($vals) || count($vals) === 0) {
                continue;
            }
            if (!isset($key_map[$dsd_u])) {
                throw new Exception("Unknown filter column: {$dsd_u}");
            }
            $phys = $key_map[$dsd_u];
            if (!in_array($phys, $slice_phys, true)) {
                throw new Exception("Column {$dsd_u} is not a chart dimension (slice) column.");
            }
            $phys_filters[$phys] = array_values(array_filter(array_map(function ($x) {
                return trim((string) $x);
            }, $vals), function ($x) {
                return $x !== '';
            }));
        }

        if (count($slice_phys) > 0 && empty($phys_filters)) {
            throw new Exception('Select at least one value in at least one dimension filter (e.g. geography).');
        }

        $body = array(
            'time_column' => $phys_time,
            'value_column' => $phys_val,
            'slice_columns' => $slice_phys,
            'filters' => $phys_filters,
            'time_period_start' => isset($filters['time_period_start']) ? $filters['time_period_start'] : null,
            'time_period_end' => isset($filters['time_period_end']) ? $filters['time_period_end'] : null,
        );
        if (array_key_exists('use_ts_year_for_time_filter', $filters)) {
            $body['use_ts_year_for_time_filter'] = $filters['use_ts_year_for_time_filter'] ? true : false;
        }

        return $body;
    }

    /**
     * Dataset-wide row counts per distinct value for each chart slice column (DuckDB). Keys = DSD column names.
     *
     * @param int $sid
     * @return array{column_counts: array<string,array<int,array{value:string,count:int}>>, metadata: array}
     */
    public function get_chart_facet_value_counts($sid)
    {
        $this->load->library('indicator_duckdb_service');
        $page = $this->indicator_duckdb_service->timeseries_page($sid, 0, 1);
        if (!is_array($page) || !empty($page['error']) || empty($page['columns']) || !is_array($page['columns'])) {
            return array(
                'column_counts' => array(),
                'metadata' => array('source' => 'none'),
            );
        }

        try {
            $ctx = $this->chart_timeseries_slice_context($sid, $page);
        } catch (Throwable $e) {
            return array(
                'column_counts' => array(),
                'metadata' => array('source' => 'none', 'message' => $e->getMessage()),
            );
        }

        $slice_facets = isset($ctx['slice_facets']) && is_array($ctx['slice_facets']) ? $ctx['slice_facets'] : array();
        if (count($slice_facets) === 0) {
            return array(
                'column_counts' => array(),
                'metadata' => array('source' => 'duckdb'),
            );
        }

        $columns = array();
        foreach ($slice_facets as $sf) {
            if (!empty($sf['physical'])) {
                $columns[] = $sf['physical'];
            }
        }
        $columns = array_values(array_unique($columns));
        if (count($columns) === 0) {
            return array(
                'column_counts' => array(),
                'metadata' => array('source' => 'duckdb'),
            );
        }

        $raw = $this->indicator_duckdb_service->timeseries_facet_value_counts($sid, array('columns' => $columns));
        if (!empty($raw['error'])) {
            return array(
                'column_counts' => array(),
                'metadata' => array(
                    'source' => 'error',
                    'message' => isset($raw['message']) ? (string) $raw['message'] : '',
                ),
            );
        }

        $phys_to_name = array();
        foreach ($slice_facets as $sf) {
            if (!empty($sf['physical']) && !empty($sf['name'])) {
                $phys_to_name[$sf['physical']] = $sf['name'];
            }
        }

        $column_counts_raw = isset($raw['column_counts']) && is_array($raw['column_counts']) ? $raw['column_counts'] : array();
        $out_counts = array();
        foreach ($column_counts_raw as $phys => $items) {
            if (!is_array($items)) {
                continue;
            }
            $dsd_name = isset($phys_to_name[$phys]) ? $phys_to_name[$phys] : (string) $phys;
            $norm = array();
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $v = isset($row['value']) ? (string) $row['value'] : '';
                $c = isset($row['count']) ? (int) $row['count'] : 0;
                if ($v === '') {
                    continue;
                }
                $norm[] = array('value' => $v, 'count' => $c);
            }
            $out_counts[$dsd_name] = $norm;
        }

        $trunc = array();
        if (isset($raw['columns_truncated']) && is_array($raw['columns_truncated'])) {
            $trunc = $raw['columns_truncated'];
        }

        return array(
            'column_counts' => $out_counts,
            'metadata' => array(
                'source' => 'duckdb',
                'columns_truncated' => $trunc,
            ),
        );
    }

    /**
     * Chart filter dropdowns: observed values only (DuckDB counts + codelist labels when available).
     *
     * @param int $sid
     * @return array geography_column, geography_options, periodicity_facet, dimension_facets, attribute_facets, annotation_facets, metadata
     */
    public function get_chart_filter_options($sid)
    {
        $counts_payload = $this->get_chart_facet_value_counts($sid);
        $column_counts = isset($counts_payload['column_counts']) && is_array($counts_payload['column_counts'])
            ? $counts_payload['column_counts']
            : array();
        $metadata = isset($counts_payload['metadata']) && is_array($counts_payload['metadata'])
            ? $counts_payload['metadata']
            : array('source' => 'none');

        $columns = $this->select_all($sid, false);

        $geography_column = null;
        $geography_options = array();
        $periodicity_facet = null;
        $dimension_facets = array();
        $attribute_facets = array();
        $annotation_facets = array();

        foreach ($columns as $col) {
            if (!is_array($col) || empty($col['name'])) {
                continue;
            }
            $ctype = isset($col['column_type']) ? (string) $col['column_type'] : '';
            $name = (string) $col['name'];
            $count_rows = isset($column_counts[$name]) && is_array($column_counts[$name])
                ? $column_counts[$name]
                : array();

            if ($ctype === 'geography') {
                $geography_column = $name;
                $geography_options = $this->build_observed_filter_options_from_counts($sid, $col, $count_rows);
                continue;
            }

            if ($ctype === 'periodicity') {
                $periodicity_facet = $this->build_chart_filter_facet($sid, $col, $count_rows);
                continue;
            }

            if ($ctype === 'dimension' || $ctype === 'measure') {
                $dimension_facets[] = $this->build_chart_filter_facet($sid, $col, $count_rows);
                continue;
            }

            if ($ctype === 'attribute') {
                if (!$this->indicator_dsd_column_has_resolved_codelist($sid, $col)) {
                    continue;
                }
                $attribute_facets[] = $this->build_chart_filter_facet($sid, $col, $count_rows);
                continue;
            }

            if ($ctype === 'annotation') {
                if (!$this->indicator_dsd_column_has_resolved_codelist($sid, $col)) {
                    continue;
                }
                $annotation_facets[] = $this->build_chart_filter_facet($sid, $col, $count_rows);
            }
        }

        return array(
            'geography_column' => $geography_column,
            'geography_options' => $geography_options,
            'periodicity_facet' => $periodicity_facet,
            'dimension_facets' => $dimension_facets,
            'attribute_facets' => $attribute_facets,
            'annotation_facets' => $annotation_facets,
            'metadata' => $metadata,
        );
    }

    /**
     * @param int   $sid
     * @param array $column DSD column row
     * @param array $count_rows [{ value, count }, ...]
     * @return array name, label, column_type, items
     */
    private function build_chart_filter_facet($sid, array $column, array $count_rows)
    {
        $label = isset($column['label']) && trim((string) $column['label']) !== ''
            ? trim((string) $column['label'])
            : (isset($column['name']) ? (string) $column['name'] : '');

        return array(
            'name' => isset($column['name']) ? (string) $column['name'] : '',
            'label' => $label,
            'column_type' => isset($column['column_type']) ? (string) $column['column_type'] : '',
            'items' => $this->build_observed_filter_options_from_counts($sid, $column, $count_rows),
        );
    }

    /**
     * Observed codes with labels (from codelist when available) and row counts from DuckDB.
     *
     * @param int   $sid
     * @param array $column
     * @param array $count_rows
     * @return array<int,array{code:string,label:string,count:int}>
     */
    private function build_observed_filter_options_from_counts($sid, array $column, array $count_rows)
    {
        if (!is_array($count_rows) || count($count_rows) === 0) {
            return array();
        }

        $label_map = array();
        foreach ($this->resolve_column_code_list_items_for_ui($sid, $column) as $it) {
            if (!is_array($it)) {
                continue;
            }
            $code = isset($it['code']) ? trim((string) $it['code']) : '';
            if ($code === '') {
                continue;
            }
            $lab = isset($it['label']) ? trim((string) $it['label']) : '';
            $label_map[strtolower($code)] = $lab !== '' ? $lab : $code;
        }

        $options = array();
        foreach ($count_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = isset($row['value']) ? trim((string) $row['value']) : '';
            if ($code === '') {
                continue;
            }
            $count = isset($row['count']) ? (int) $row['count'] : 0;
            $base_label = isset($label_map[strtolower($code)]) ? $label_map[strtolower($code)] : $code;
            $options[] = array(
                'code' => $code,
                'label' => $base_label . ' (' . number_format($count) . ')',
                'count' => $count,
            );
        }

        usort($options, function ($a, $b) {
            $ca = isset($a['count']) ? (int) $a['count'] : 0;
            $cb = isset($b['count']) ? (int) $b['count'] : 0;
            if ($ca !== $cb) {
                return $cb <=> $ca;
            }

            return strcmp((string) $a['code'], (string) $b['code']);
        });

        return $options;
    }

    /**
     * Add series_key_label and geography (geography column label only); strip slice_values.
     *
     * @param int $sid
     * @param array $page timeseries_page
     * @param array $records
     * @param array $metadata chart-aggregate metadata (slice_columns)
     */
    private function enrich_chart_aggregate_records_from_dsd($sid, $page, array &$records, array $metadata)
    {
        $slice_cols = isset($metadata['slice_columns']) && is_array($metadata['slice_columns'])
            ? $metadata['slice_columns']
            : array();

        if (count($records) === 0) {
            return;
        }

        $key_map = $this->timeseries_page_column_upper_to_physical($page);
        if (empty($key_map) || count($slice_cols) === 0) {
            foreach ($records as $i => $rec) {
                if (!is_array($rec)) {
                    continue;
                }
                if (array_key_exists('slice_values', $rec)) {
                    unset($records[$i]['slice_values']);
                }
                if (!isset($records[$i]['series_key_label']) && isset($records[$i]['series_key'])) {
                    $records[$i]['series_key_label'] = $records[$i]['series_key'];
                }
                if (!isset($records[$i]['geography']) && isset($records[$i]['series_key'])) {
                    $records[$i]['geography'] = $records[$i]['series_key'];
                }
            }

            return;
        }

        $columns = $this->enrich_columns_resolved_code_lists($sid, $this->select_all($sid, false));

        /** @var array<string,array<string,string>> upper physical -> lower(code) -> label */
        $labels_by_col_upper = array();
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            $ct = isset($col['column_type']) ? $col['column_type'] : '';
            if (!in_array($ct, self::chart_slice_dsd_column_types(), true)) {
                continue;
            }
            if (in_array($ct, array('periodicity', 'attribute', 'annotation'), true)) {
                if (!$this->indicator_dsd_column_has_resolved_codelist($sid, $col)) {
                    continue;
                }
            }
            $uk = $this->dsd_physical_column_key($col['name']);
            if ($uk === '' || !isset($key_map[$uk])) {
                continue;
            }
            $phys = $key_map[$uk];
            $pu = strtoupper($phys);
            $in_slice = false;
            foreach ($slice_cols as $sc) {
                if (strtoupper(trim((string) $sc)) === $pu) {
                    $in_slice = true;
                    break;
                }
            }
            if (!$in_slice) {
                continue;
            }

            $items = $this->resolve_column_code_list_items_for_ui($sid, $col);
            if (!isset($labels_by_col_upper[$pu])) {
                $labels_by_col_upper[$pu] = array();
            }
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $c = isset($it['code']) ? trim((string) $it['code']) : '';
                if ($c === '') {
                    continue;
                }
                $lab = isset($it['label']) ? trim((string) $it['label']) : '';
                if ($lab === '') {
                    $lab = $c;
                }
                $labels_by_col_upper[$pu][strtolower($c)] = $lab;
            }
        }

        $geo_phys_upper = null;
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            if (isset($col['column_type']) && $col['column_type'] === 'geography') {
                $uk = $this->dsd_physical_column_key($col['name']);
                if ($uk !== '' && isset($key_map[$uk])) {
                    $geo_phys_upper = strtoupper($key_map[$uk]);
                }
                break;
            }
        }

        foreach ($records as $i => $rec) {
            if (!is_array($rec)) {
                continue;
            }

            $vals = isset($rec['slice_values']) && is_array($rec['slice_values']) ? $rec['slice_values'] : array();
            if (count($vals) !== count($slice_cols)) {
                $vals = array();
                if (isset($rec['series_key']) && is_string($rec['series_key']) && count($slice_cols) > 0) {
                    $parts = explode(' | ', $rec['series_key'], count($slice_cols));
                    while (count($parts) < count($slice_cols)) {
                        $parts[] = '';
                    }
                    $vals = array_slice($parts, 0, count($slice_cols));
                }
            }

            $parts_label = array();
            $geo_label = null;
            foreach ($slice_cols as $idx => $phys) {
                $pu = strtoupper(trim((string) $phys));
                $code = isset($vals[$idx]) ? trim((string) $vals[$idx]) : '';
                $display = $code;
                if ($code !== '' && isset($labels_by_col_upper[$pu][strtolower($code)])) {
                    $display = $labels_by_col_upper[$pu][strtolower($code)];
                }
                $parts_label[] = ($display !== '' ? $display : $code);
                if ($geo_phys_upper !== null && $pu === $geo_phys_upper) {
                    $geo_label = ($display !== '' ? $display : $code);
                }
            }

            $records[$i]['series_key_label'] = implode(' | ', $parts_label);
            if ($geo_label !== null && $geo_label !== '') {
                $records[$i]['geography'] = $geo_label;
            } elseif (!isset($records[$i]['geography']) && isset($records[$i]['series_key'])) {
                $records[$i]['geography'] = $records[$i]['series_key'];
            }

            unset($records[$i]['slice_values']);
        }
    }

    /**
     * FastAPI chart-aggregate: time_period, observation_value, series_key, slice_values (stripped here).
     *
     * @param int $sid
     * @param array $page timeseries_page first row metadata
     * @param array $raw FastAPI JSON
     * @return array
     */
    private function normalize_chart_aggregate_response($sid, $page, array $raw)
    {
        $records = isset($raw['records']) && is_array($raw['records']) ? $raw['records'] : array();
        $metadata = isset($raw['metadata']) && is_array($raw['metadata']) ? $raw['metadata'] : array();

        $this->enrich_chart_aggregate_records_from_dsd($sid, $page, $records, $metadata);

        $out = array(
            'records' => $records,
            'filter_options' => isset($raw['filter_options']) && is_array($raw['filter_options'])
                ? $raw['filter_options']
                : array(),
            'metadata' => $metadata,
        );
        $out['metadata']['source'] = 'duckdb';

        return $out;
    }

    /**
     * @param int $sid
     * @param array $filters
     * @param array $page
     * @return array|null
     */
    private function try_get_chart_data_duckdb($sid, $filters, $page)
    {
        $spec = $this->build_chart_aggregate_spec($sid, $filters, $page);

        $this->load->library('indicator_duckdb_service');
        $raw = $this->indicator_duckdb_service->timeseries_chart_aggregate($sid, $spec);
        if (!empty($raw['error'])) {
            $hc = isset($raw['http_code']) ? (int) $raw['http_code'] : 0;
            if ($hc === 404 || $hc === 405 || $hc === 501) {
                return null;
            }
            if ($hc >= 400 && $hc < 500) {
                $msg = isset($raw['message']) ? (string) $raw['message'] : 'Chart aggregate request rejected';

                throw new Exception($msg);
            }

            return null;
        }

        return $this->normalize_chart_aggregate_response($sid, $page, $raw);
    }

    /**
     * Get chart data: DuckDB observation rows when timeseries + FastAPI chart-aggregate exist; else CSV.
     *
     * @param int $sid
     * @param array $filters geography, dimensions, time_period_start/end
     * @return array
     */
    public function get_chart_data($sid, $filters = array())
    {
        $this->load->library('indicator_duckdb_service');
        $page = $this->indicator_duckdb_service->timeseries_page($sid, 0, 1);
        $duck_ok = is_array($page) && empty($page['error']) && !empty($page['columns']) && is_array($page['columns']);

        if ($duck_ok) {
            $agg = $this->try_get_chart_data_duckdb($sid, $filters, $page);
            if ($agg !== null) {
                return $agg;
            }
        }

        if (!empty($filters['dimensions']) && is_array($filters['dimensions'])) {
            foreach ($filters['dimensions'] as $k => $v) {
                if (is_array($v) && count($v) > 0) {
                    throw new Exception('Multi-dimension charts require published DuckDB timeseries and the chart-aggregate API. See metadata-editor-fastapi/src/routers/timeseries.py (indicator_timeseries_chart_aggregate).');
                }
            }
        }

        return $this->get_chart_data_from_csv($sid, $filters);
    }

    /**
     * Legacy CSV path (small data): last row wins for duplicate keys; no SQL aggregation.
     *
     * @param int $sid
     * @param array $filters
     * @return array
     */
    private function get_chart_data_from_csv($sid, $filters = array())
    {
        $csv_path = $this->get_indicator_csv_path($sid);
        if (!$csv_path || !file_exists($csv_path)) {
            throw new Exception("CSV data file not found. Please import data first.");
        }

        // Get DSD columns to identify field names
        $columns = $this->select_all($sid, false);
        $column_map = array();
        $geography_column = null;
        $time_period_column = null;
        $observation_value_column = null;

        foreach ($columns as $col) {
            $column_map[strtoupper($col['name'])] = $col;
            if ($col['column_type'] === 'geography') {
                $geography_column = strtoupper($col['name']);
            } elseif ($col['column_type'] === 'time_period') {
                $time_period_column = strtoupper($col['name']);
            } elseif ($col['column_type'] === 'observation_value') {
                $observation_value_column = strtoupper($col['name']);
            }
        }

        if (!$geography_column || !$time_period_column || !$observation_value_column) {
            throw new Exception("Required columns (geography, time_period, observation_value) not found in DSD.");
        }

        // Read CSV
        $csv = Reader::createFromPath($csv_path, 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();

        // Create mapping of uppercase header names to original header names
        $header_map = array();
        foreach ($headers as $header) {
            $header_map[strtoupper($header)] = $header;
        }

        // Find column names (case-insensitive)
        $geography_col = isset($header_map[$geography_column]) ? $header_map[$geography_column] : null;
        $time_period_col = isset($header_map[$time_period_column]) ? $header_map[$time_period_column] : null;
        $observation_value_col = isset($header_map[$observation_value_column]) ? $header_map[$observation_value_column] : null;

        if (!$geography_col || !$time_period_col || !$observation_value_col) {
            throw new Exception("Required columns not found in CSV file. Looking for: " .
                $geography_column . ", " . $time_period_column . ", " . $observation_value_column);
        }

        // Apply filters
        $geography_filter = isset($filters['geography']) && is_array($filters['geography']) ? $filters['geography'] : null;
        $time_period_start = isset($filters['time_period_start']) ? $filters['time_period_start'] : null;
        $time_period_end = isset($filters['time_period_end']) ? $filters['time_period_end'] : null;

        // Process CSV records
        $records = array();
        $geography_values = array();
        $time_period_values = array();

        foreach ($csv->getRecords() as $record) {
            $geography = isset($record[$geography_col]) ? trim($record[$geography_col]) : '';
            $time_period = isset($record[$time_period_col]) ? trim($record[$time_period_col]) : '';
            $observation_value = isset($record[$observation_value_col]) ? trim($record[$observation_value_col]) : '';

            // Skip empty rows
            if (empty($geography) || empty($time_period) || empty($observation_value)) {
                continue;
            }

            // Apply geography filter
            if ($geography_filter !== null && !in_array($geography, $geography_filter)) {
                continue;
            }

            // Apply time period filter
            if ($time_period_start !== null && $time_period < $time_period_start) {
                continue;
            }
            if ($time_period_end !== null && $time_period > $time_period_end) {
                continue;
            }

            // Collect unique values for filter options
            if (!in_array($geography, $geography_values)) {
                $geography_values[] = $geography;
            }
            if (!in_array($time_period, $time_period_values)) {
                $time_period_values[] = $time_period;
            }

            $records[] = array(
                'geography' => $geography,
                'time_period' => $time_period,
                'observation_value' => is_numeric($observation_value) ? (float) $observation_value : null,
                'series_key' => $geography,
                'series_key_label' => $geography,
            );
        }

        // Sort by time_period
        usort($records, function ($a, $b) {
            return strcmp($a['time_period'], $b['time_period']);
        });

        // Sort filter values
        sort($geography_values);
        sort($time_period_values);

        // Return raw data - let client handle transformation for visualization
        return array(
            'records' => $records,
            'filter_options' => array(
                'geography' => $geography_values,
                'time_period' => array(
                    'min' => !empty($time_period_values) ? min($time_period_values) : null,
                    'max' => !empty($time_period_values) ? max($time_period_values) : null,
                    'values' => $time_period_values,
                ),
            ),
            'metadata' => array(
                'geography_column' => $geography_column,
                'time_period_column' => $time_period_column,
                'observation_value_column' => $observation_value_column,
                'total_records' => count($records),
                'source' => 'csv',
            ),
        );
    }

    public function is_project_dsd_read_only($sid)
    {
        $this->load->model('Editor_project_dsd_model');

        return $this->Editor_project_dsd_model->get_by_sid($sid) !== null;
    }

    /**
     * Require global DSD binding before CSV data upload.
     *
     * @param int  $sid
     * @param bool $require_indicator_id_value When false (prepare step), indicator_id_value may be empty.
     * @param bool $require_implied_freq_code When false (prepare step), implied_freq_code may be empty if structure has no periodicity column.
     * @return array binding row, indicator_column, column_count, indicator_id_value
     * @throws Exception
     */
    public function assert_ready_for_data_upload($sid, $require_indicator_id_value = true, $require_implied_freq_code = true)
    {
        $this->load->model('Editor_project_dsd_model');
        $binding = $this->Editor_project_dsd_model->get_by_sid($sid);
        if (!$binding) {
            throw new Exception('Attach a global data structure to this project before importing data.');
        }

        $structure = $this->validate_dsd_structure($sid);
        if (empty($structure['valid'])) {
            $msg = !empty($structure['errors'][0])
                ? $structure['errors'][0]
                : 'Data structure has validation errors. Fix them before importing data.';
            throw new Exception($msg);
        }

        $columns = $this->select_all($sid, false);
        if (empty($columns)) {
            throw new Exception('Data structure has no components. Check the global data structure definition.');
        }

        $indicator_col = $this->get_column_name_by_type($sid, 'indicator_id');
        if ($indicator_col === null || $indicator_col === '') {
            throw new Exception('Data structure must include an indicator_id column.');
        }

        $indicator_id_value = isset($binding['indicator_id_value'])
            ? trim((string) $binding['indicator_id_value'])
            : '';
        if ($require_indicator_id_value && $indicator_id_value === '') {
            throw new Exception('Set the indicator ID value for this project before importing data.');
        }

        if ($require_implied_freq_code && !$this->project_has_periodicity_column($sid)) {
            $implied = isset($binding['implied_freq_code']) ? trim((string) $binding['implied_freq_code']) : '';
            if ($implied === '') {
                throw new Exception(
                    'This data structure has no FREQ (periodicity) column. Set a series FREQ (SDMX code) on the project before importing data.'
                );
            }
            $this->load->library('SDMX/Sdmx_time_period');
            if (!$this->sdmx_time_period->is_allowed_freq_code($implied)) {
                throw new Exception("Series FREQ '{$implied}' is not a configured SDMX FREQ code.");
            }
        }

        return array(
            'binding' => $binding,
            'indicator_column' => $indicator_col,
            'indicator_id_value' => $indicator_id_value,
            'column_count' => count($columns),
        );
    }

    /**
     * DSD column names normalized for DuckDB / CSV header matching.
     *
     * @param int $sid
     * @return string[]
     */
    public function get_dsd_column_names_for_csv($sid)
    {
        $columns = $this->select_all($sid, false);
        $names = array();
        foreach ($columns as $col) {
            if (!empty($col['name'])) {
                $names[] = (string) $col['name'];
            }
        }

        return $this->normalize_duckdb_staging_column_names($names);
    }

    /**
     * Column names to pass to DuckDB replace-from-csv (staging CSV may still contain extra columns).
     * When $keep_extra_columns is false (default), returns DSD columns only — FastAPI should not load extras.
     * When true, appends extra CSV columns (normalized) after DSD columns in CSV header order.
     *
     * @param string[] $expected_column_names Normalized DSD column names
     * @param array    $validation Result from validate_csv_headers_for_dsd()
     * @param bool     $keep_extra_columns
     * @return string[]
     */
    public function build_csv_import_column_names(array $expected_column_names, array $validation, $keep_extra_columns = false)
    {
        if (!$keep_extra_columns) {
            return $expected_column_names;
        }

        $csv_headers = isset($validation['csv_columns']) && is_array($validation['csv_columns'])
            ? $validation['csv_columns']
            : array();
        $seen = array();
        $out = array();

        foreach ($expected_column_names as $exp) {
            $out[] = $exp;
            $seen[$exp] = true;
        }

        foreach ($csv_headers as $hdr) {
            $matched_dsd = false;
            foreach ($expected_column_names as $exp) {
                if ($this->mapping_csv_column_matches_header($exp, $hdr)) {
                    $matched_dsd = true;
                    break;
                }
            }
            if ($matched_dsd) {
                continue;
            }
            $norm = $this->normalize_csv_header_token($hdr);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $out[] = $norm;
            $seen[$norm] = true;
        }

        return $out;
    }

    /**
     * First column name for a given SDMX column_type (DuckDB-safe token).
     *
     * @param int    $sid
     * @param string $column_type e.g. indicator_id
     * @return string|null
     */
    public function get_column_name_by_type($sid, $column_type)
    {
        $columns = $this->select_all($sid, false);
        foreach ($columns as $col) {
            if (
                isset($col['column_type'])
                && $col['column_type'] === $column_type
                && !empty($col['name'])
            ) {
                $normalized = $this->normalize_duckdb_staging_column_names(array((string) $col['name']));

                return $normalized[0];
            }
        }

        return null;
    }
}

