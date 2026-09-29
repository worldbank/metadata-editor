<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Export and import per-datafile variable documentation as CSV (wide format, one row per variable).
 *
 * Import rules:
 * - empty cell: leave existing value unchanged
 * - single dash (-): clear the field (name cannot be cleared)
 */
class Variable_documentation_csv
{
	const FORMAT_VERSION = '1.0';

	const CLEAR_SENTINEL = '-';

	const PROFILE_DOCUMENTATION = 'documentation';
	const PROFILE_FULL = 'full';

	const DOCUMENTATION_COLUMNS = array(
		'uid',
		'vid',
		'name',
		'labl',
		'var_txt',
		'var_universe',
		'var_respunit',
		'var_qstn_preqtxt',
		'var_qstn_qstnlit',
		'var_qstn_postqtxt',
		'var_qstn_ivulnstr',
		'var_forward',
		'var_backward',
	);

	// Scalar fields only. Repeatable / structured fields (e.g. var_concept, var_catgry,
	// var_sumstat, var_format) are excluded from CSV round-trip.
	const EXTENDED_COLUMNS = array(
		'var_intrvl',
		'var_dcml',
		'var_wgt',
		'is_key',
		'var_imputation',
		'var_derivation',
		'var_security',
		'var_codinstr',
		'var_notes',
	);

	const READ_ONLY_IMPORT_COLUMNS = array('uid', 'vid');

	/** @var CI_Controller */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_datafile_model');
		$this->ci->load->model('Editor_variable_model');
		$this->ci->load->model('Editor_model');
	}

	/**
	 * @param string $profile
	 * @return array<int, string>
	 */
	public function columns_for_profile($profile)
	{
		$profile = $this->normalize_profile($profile);
		if ($profile === self::PROFILE_FULL) {
			return array_merge(self::DOCUMENTATION_COLUMNS, self::EXTENDED_COLUMNS);
		}

		return self::DOCUMENTATION_COLUMNS;
	}

	/**
	 * @param string $profile
	 * @return array<int, string> columns that may be updated on import
	 */
	public function importable_columns_for_profile($profile)
	{
		return array_values(array_diff(
			$this->columns_for_profile($profile),
			self::READ_ONLY_IMPORT_COLUMNS
		));
	}

	/**
	 * @param int $sid
	 * @param string $fid
	 * @param array $options profile, line_ending
	 * @return string UTF-8 CSV with BOM
	 * @throws Exception
	 */
	public function export_csv($sid, $fid, array $options = array())
	{
		$profile = $this->normalize_profile(isset($options['profile']) ? $options['profile'] : self::PROFILE_DOCUMENTATION);
		$columns = $this->columns_for_profile($profile);
		$variables = $this->_load_variables($sid, $fid);
		$rows = array();

		foreach ($variables as $variable) {
			$rows[] = $this->_export_row($variable, $columns);
		}

		return $this->_render_csv($columns, $rows, $options);
	}

	/**
	 * @param array $datafile
	 * @return string
	 */
	public function documentation_filename_for_datafile(array $datafile)
	{
		$base = '';
		if (!empty($datafile['file_name'])) {
			$base = (string) $datafile['file_name'];
		} elseif (!empty($datafile['file_physical_name'])) {
			$base = pathinfo($datafile['file_physical_name'], PATHINFO_FILENAME);
		} else {
			$base = (string) (isset($datafile['file_id']) ? $datafile['file_id'] : 'data');
		}

		$base = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $base);
		if ($base === '') {
			$base = 'data';
		}

		$base = preg_replace('/\.csv$/i', '', $base);

		return $base . '_variable_documentation.csv';
	}

	/**
	 * @param int $sid
	 * @param string $fid
	 * @param string $csv_content
	 * @param array $options dry_run, profile
	 * @return array
	 * @throws Exception
	 */
	public function import_csv($sid, $fid, $csv_content, array $options = array())
	{
		$sid = (int) $sid;
		$fid = (string) $fid;
		$dry_run = !empty($options['dry_run']);
		$profile = $this->normalize_profile(isset($options['profile']) ? $options['profile'] : self::PROFILE_DOCUMENTATION);

		$datafile = $this->ci->Editor_datafile_model->data_file_by_id($sid, $fid);
		if (!$datafile) {
			throw new Exception('Data file not found: ' . $fid);
		}

		$parsed = $this->_parse_csv($csv_content);
		if (!empty($parsed['errors'])) {
			return array(
				'ok' => false,
				'message' => $parsed['errors'][0],
				'errors' => $parsed['errors'],
			);
		}

		if (!in_array('uid', $parsed['headers'], true)) {
			return array(
				'ok' => false,
				'message' => 'CSV header must include uid',
				'errors' => array('Missing required column: uid'),
			);
		}

		$allowed_fields = array_flip($this->importable_columns_for_profile($profile));
		$updated = 0;
		$skipped = 0;
		$errors = array();
		$rename_map = array();

		foreach ($parsed['rows'] as $row_index => $row) {
			$row_number = $row_index + 2;
			$uid_raw = isset($row['uid']) ? trim((string) $row['uid']) : '';
			if ($uid_raw === '' || !ctype_digit($uid_raw)) {
				$errors[] = array(
					'row' => $row_number,
					'uid' => $uid_raw,
					'message' => 'uid is required and must be numeric',
				);
				continue;
			}

			$uid = (int) $uid_raw;
			$existing = $this->ci->Editor_variable_model->variable($sid, $uid, true);
			if (empty($existing)) {
				$errors[] = array(
					'row' => $row_number,
					'uid' => $uid,
					'message' => 'Variable not found',
				);
				continue;
			}

			if ((string) $existing['fid'] !== $fid) {
				$errors[] = array(
					'row' => $row_number,
					'uid' => $uid,
					'message' => 'Variable does not belong to this data file',
				);
				continue;
			}

			$apply = $this->_apply_import_row($existing, $row, $allowed_fields);
			if (!empty($apply['errors'])) {
				foreach ($apply['errors'] as $err) {
					$errors[] = array(
						'row' => $row_number,
						'uid' => $uid,
						'field' => isset($err['field']) ? $err['field'] : null,
						'message' => $err['message'],
					);
				}
				continue;
			}

			if (empty($apply['changed'])) {
				$skipped++;
				continue;
			}

			$variable = $apply['variable'];
			$old_name = isset($existing['name']) ? trim((string) $existing['name']) : '';
			$new_name = isset($variable['name']) ? trim((string) $variable['name']) : $old_name;

			try {
				$this->ci->Editor_model->validate_variable($variable);
			} catch (Exception $e) {
				$errors[] = array(
					'row' => $row_number,
					'uid' => $uid,
					'message' => $e->getMessage(),
				);
				continue;
			}

			if (!$dry_run) {
				$variable['metadata'] = $variable;
				$this->ci->Editor_variable_model->update($sid, $uid, $variable);
				if ($new_name !== $old_name && $old_name !== '') {
					$rename_map[$old_name] = $new_name;
				}
			}

			$updated++;
		}

		if (!$dry_run && !empty($rename_map)) {
			$this->ci->Editor_datafile_model->rewrite_csv_header($sid, $fid, $rename_map);
		}

		return array(
			'ok' => empty($errors),
			'dry_run' => $dry_run,
			'profile' => $profile,
			'rows_parsed' => count($parsed['rows']),
			'updated' => $updated,
			'skipped' => $skipped,
			'errors' => $errors,
		);
	}

	/**
	 * @param array $existing DB row with decoded metadata
	 * @param array $row CSV row keyed by column
	 * @param array $allowed_fields map field => true
	 * @return array{ changed: bool, variable: array, errors: array }
	 */
	public function _apply_import_row(array $existing, array $row, array $allowed_fields)
	{
		$variable = $this->_flatten_variable($existing);
		$changed = false;
		$errors = array();
		$old_name = isset($variable['name']) ? trim((string) $variable['name']) : '';

		foreach ($allowed_fields as $field => $_true) {
			if (!array_key_exists($field, $row)) {
				continue;
			}

			$raw = $row[$field];
			if ($raw === null || trim((string) $raw) === '') {
				continue;
			}

			if ($this->_is_clear_sentinel($raw)) {
				$cleared = $this->_clear_import_field_value($field, $errors);
				if ($cleared === null) {
					continue;
				}
				$variable[$field] = $cleared;
				$changed = true;
				continue;
			}

			if ($field === 'name') {
				$new_name = trim((string) $raw);
				if ($new_name === $old_name) {
					continue;
				}
				$valid = $this->ci->Editor_variable_model->validate_variable_name_for_rename($new_name);
				if (!$valid['valid']) {
					$errors[] = array('field' => 'name', 'message' => $valid['message']);
					continue;
				}
				$other = $this->ci->Editor_variable_model->variable_by_name($existing['sid'], $existing['fid'], $new_name, false);
				if (!empty($other) && (int) $other['uid'] !== (int) $existing['uid']) {
					$errors[] = array('field' => 'name', 'message' => 'Another variable already has this name.');
					continue;
				}
				$variable['name'] = $new_name;
				$changed = true;
				continue;
			}

			$value = $this->_parse_import_field_value($field, $raw, $errors);

			$variable[$field] = $value;
			$changed = true;
		}

		return array(
			'changed' => $changed && empty($errors),
			'variable' => $variable,
			'errors' => $errors,
		);
	}

	/**
	 * @param mixed $raw
	 * @return bool
	 */
	public function _is_clear_sentinel($raw)
	{
		return trim((string) $raw) === self::CLEAR_SENTINEL;
	}

	/**
	 * @param string $field
	 * @param array $errors
	 * @return mixed|null null when field cannot be cleared (errors appended)
	 */
	public function _clear_import_field_value($field, array &$errors)
	{
		if ($field === 'name') {
			$errors[] = array('field' => 'name', 'message' => 'Variable name cannot be cleared.');
			return null;
		}

		if ($field === 'var_wgt' || $field === 'is_key') {
			return 0;
		}

		return '';
	}

	/**
	 * @param string $profile
	 * @return string
	 */
	public function normalize_profile($profile)
	{
		$profile = strtolower(trim((string) $profile));
		if ($profile === self::PROFILE_FULL) {
			return self::PROFILE_FULL;
		}

		return self::PROFILE_DOCUMENTATION;
	}

	/**
	 * @param int $sid
	 * @param string $fid
	 * @return array
	 */
	private function _load_variables($sid, $fid)
	{
		$variables = $this->ci->Editor_variable_model->select_all($sid, $fid, true);
		return is_array($variables) ? $variables : array();
	}

	/**
	 * @param array $row
	 * @return array
	 */
	private function _flatten_variable(array $row)
	{
		$db_name = isset($row['name']) ? $row['name'] : '';
		$db_labl = isset($row['labl']) ? $row['labl'] : '';
		$metadata = array();
		if (isset($row['metadata']) && is_array($row['metadata'])) {
			$metadata = $row['metadata'];
		}
		unset($row['metadata']);
		$variable = array_merge($row, $metadata);
		$variable['name'] = $db_name;
		$variable['labl'] = $db_labl;

		return $variable;
	}

	/**
	 * @param array $variable
	 * @param array $columns
	 * @return array
	 */
	private function _export_row(array $variable, array $columns)
	{
		$row = array();
		foreach ($columns as $column) {
			$row[$column] = $this->_export_field_value($column, $variable);
		}

		return $row;
	}

	/**
	 * @param string $field
	 * @param array $variable
	 * @return string
	 */
	private function _export_field_value($field, array $variable)
	{
		if ($field === 'uid') {
			return isset($variable['uid']) ? (string) (int) $variable['uid'] : '';
		}
		if ($field === 'vid') {
			return isset($variable['vid']) ? (string) $variable['vid'] : '';
		}
		if ($field === 'name') {
			return isset($variable['name']) ? (string) $variable['name'] : '';
		}
		if ($field === 'labl') {
			return isset($variable['labl']) ? (string) $variable['labl'] : '';
		}

		if (!array_key_exists($field, $variable) || $variable[$field] === null) {
			return '';
		}

		$value = $variable[$field];

		if ($field === 'var_wgt' || $field === 'is_key') {
			if ($value === true || $value === '1' || $value === 1) {
				return '1';
			}
			return '0';
		}

		if (is_scalar($value)) {
			return (string) $value;
		}

		if (is_array($value)) {
			$encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
			return $encoded === false ? '' : $encoded;
		}

		return '';
	}

	/**
	 * @param string $field
	 * @param mixed $raw
	 * @param array $errors
	 * @return mixed|null
	 */
	private function _parse_import_field_value($field, $raw, array &$errors)
	{
		$text = trim((string) $raw);

		if ($field === 'var_wgt' || $field === 'is_key') {
			if ($text === '1' || strtolower($text) === 'true' || strtolower($text) === 'yes') {
				return $field === 'var_wgt' ? 1 : 1;
			}
			return 0;
		}

		return $text;
	}

	/**
	 * @param string $csv_content
	 * @return array{ headers: array, rows: array, errors: array }
	 */
	public function _parse_csv($csv_content)
	{
		$csv_content = $this->_strip_bom((string) $csv_content);
		if (trim($csv_content) === '') {
			return array(
				'headers' => array(),
				'rows' => array(),
				'errors' => array('CSV is empty'),
			);
		}

		$handle = fopen('php://memory', 'r+');
		if ($handle === false) {
			return array(
				'headers' => array(),
				'rows' => array(),
				'errors' => array('Failed to open CSV stream'),
			);
		}

		fwrite($handle, $csv_content);
		rewind($handle);

		$header_row = fgetcsv($handle, 0, ',', '"', '\\');
		if ($header_row === false || empty($header_row)) {
			fclose($handle);
			return array(
				'headers' => array(),
				'rows' => array(),
				'errors' => array('CSV header row is missing'),
			);
		}

		$headers = array();
		foreach ($header_row as $header) {
			$header = strtolower(trim((string) $header));
			if ($header !== '') {
				$headers[] = $header;
			}
		}

		if (empty($headers)) {
			fclose($handle);
			return array(
				'headers' => array(),
				'rows' => array(),
				'errors' => array('CSV header row is missing'),
			);
		}

		$rows = array();
		while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			if ($this->_is_empty_csv_row($data)) {
				continue;
			}
			$row = array();
			foreach ($headers as $index => $header) {
				$row[$header] = isset($data[$index]) ? $data[$index] : '';
			}
			$rows[] = $row;
		}

		fclose($handle);

		return array(
			'headers' => $headers,
			'rows' => $rows,
			'errors' => array(),
		);
	}

	/**
	 * @param array $columns
	 * @param array $rows
	 * @param array $options
	 * @return string
	 */
	private function _render_csv(array $columns, array $rows, array $options = array())
	{
		$eol = isset($options['line_ending']) ? (string) $options['line_ending'] : "\r\n";
		$lines = array();
		$lines[] = $this->_format_row($columns);

		foreach ($rows as $row) {
			$cells = array();
			foreach ($columns as $column) {
				$cells[] = isset($row[$column]) ? (string) $row[$column] : '';
			}
			$lines[] = $this->_format_row($cells);
		}

		return "\xEF\xBB\xBF" . implode($eol, $lines) . $eol;
	}

	/**
	 * @param array $fields
	 * @return string
	 */
	private function _format_row(array $fields)
	{
		$out = array();
		foreach ($fields as $field) {
			$value = (string) $field;
			if (strpos($value, '"') !== false || strpos($value, ',') !== false
				|| strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
				$out[] = '"' . str_replace('"', '""', $value) . '"';
			} else {
				$out[] = $value;
			}
		}

		return implode(',', $out);
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function _strip_bom($text)
	{
		if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
			return substr($text, 3);
		}

		return $text;
	}

	/**
	 * @param array $data
	 * @return bool
	 */
	private function _is_empty_csv_row(array $data)
	{
		foreach ($data as $cell) {
			if (trim((string) $cell) !== '') {
				return false;
			}
		}

		return true;
	}
}
