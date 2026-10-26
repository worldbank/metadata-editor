<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cell-level data notes for indicator projects.
 *
 * - Data: footnote columns (DSD component metadata.display_role = footnote) hold note IDs, several separated by ';'.
 * - Metadata: project `data_notes` holds the text once per note: { id, note, column? }.
 *
 * Notes can be loaded from a notes CSV (note_id, note [, indicator_id, scope, column]) or from the data CSV,
 * where a text column sits next to a footnote column (NOTE_ID -> NOTE_ID_LABEL or NOTE_ID_TEXT).
 */
class Indicator_data_notes {

	const ID_SEPARATOR = ';';

	/** Separates several note texts in one text column (note text itself may contain ';'). */
	const TEXT_SEPARATOR = '||';

	/** Prefix and minimum digits of generated note IDs (N01, N02, …). */
	const GENERATED_ID_PREFIX = 'N';
	const GENERATED_ID_DIGITS = 2;

	/**
	 * Suffixes of the optional text columns paired with a footnote column in the data CSV:
	 * <COL>_LABEL, <COL>_LABEL2, <COL>_LABEL3 … (or _TEXT, _TEXT2 …). The k-th text column holds the
	 * text of the k-th ID in the cell. The first family found (LABEL before TEXT) is used.
	 */
	public static $text_column_suffixes = array('_LABEL', '_TEXT');

	/** data_notes scopes: cell (default, referenced from footnote columns), table (whole indicator). */
	public static $scopes = array('cell', 'table');

	/** Distinct footnote cell values read from DuckDB per column for reference checks. */
	const CHECK_DISTINCT_LIMIT = 20000;

	/** @var CI_Controller */
	protected $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
	}

	/**
	 * Split a footnote cell into unique, trimmed note IDs (order kept).
	 *
	 * @param mixed $value
	 * @return string[]
	 */
	public static function split_ids($value)
	{
		if ($value === null) {
			return array();
		}
		$out = array();
		foreach (explode(self::ID_SEPARATOR, (string) $value) as $part) {
			$id = trim($part);
			if ($id !== '' && !in_array($id, $out, true)) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Names of DSD columns marked as footnote columns.
	 *
	 * @param array $columns DSD rows with decoded metadata
	 * @return string[]
	 */
	public static function footnote_column_names(array $columns)
	{
		$names = array();
		foreach ($columns as $col) {
			$meta = isset($col['metadata']) && is_array($col['metadata']) ? $col['metadata'] : array();
			$role = isset($meta['display_role']) ? strtolower(trim((string) $meta['display_role'])) : '';
			if ($role === 'footnote' && !empty($col['name'])) {
				$names[] = (string) $col['name'];
			}
		}
		return $names;
	}

	/**
	 * Footnote column names for a bound project.
	 *
	 * @param int $sid
	 * @return string[]
	 */
	public function get_footnote_columns($sid)
	{
		$this->ci->load->model('Indicator_dsd_model');
		return self::footnote_column_names($this->ci->Indicator_dsd_model->select_all($sid, true));
	}

	/**
	 * @param int $sid
	 * @return array list of { id, note, column? }
	 */
	public function get_notes($sid)
	{
		$this->ci->load->model('Editor_model');
		$project = $this->ci->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}
		$metadata = isset($project['metadata']) ? $project['metadata'] : array();
		if (!is_array($metadata)) {
			$metadata = json_decode(json_encode($metadata), true);
		}
		$notes = isset($metadata['data_notes']) && is_array($metadata['data_notes']) ? $metadata['data_notes'] : array();
		return array_values(array_filter($notes, 'is_array'));
	}

	/**
	 * Write project data_notes.
	 *
	 * @param int   $sid
	 * @param array $notes
	 * @param int|null $user_id
	 */
	public function save_notes($sid, array $notes, $user_id = null)
	{
		$this->ci->load->model('Editor_model');
		$project = $this->ci->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}
		$this->ci->Editor_model->patch_project($project['type'], $sid, array(
			'patches' => array(
				array('op' => 'add', 'path' => '/data_notes', 'value' => array_values($notes)),
			),
			'changed_by' => $user_id,
			'changed' => date('U'),
		), false);
	}

	/**
	 * Merge incoming notes into existing notes by id. Incoming text wins.
	 *
	 * @param array $existing
	 * @param array $incoming list of { id, note, column? }
	 * @param bool  $replace  drop existing notes not present in $incoming
	 * @return array { notes, added[], updated[ {id, before, after} ], unchanged[], removed[] }
	 */
	public static function merge_notes(array $existing, array $incoming, $replace = false)
	{
		$by_id = array();
		$order = array();
		foreach ($existing as $n) {
			$id = isset($n['id']) ? trim((string) $n['id']) : '';
			if ($id === '') {
				continue;
			}
			$by_id[$id] = $n;
			$order[] = $id;
		}

		$result = array('added' => array(), 'updated' => array(), 'unchanged' => array(), 'removed' => array());
		$seen = array();
		foreach ($incoming as $n) {
			$id = trim((string) $n['id']);
			$seen[$id] = true;
			if (!isset($by_id[$id])) {
				$by_id[$id] = $n;
				$order[] = $id;
				$result['added'][] = $id;
				continue;
			}
			$before = isset($by_id[$id]['note']) ? (string) $by_id[$id]['note'] : '';
			$merged = array_merge($by_id[$id], $n);
			if (self::scope_of($n) === 'cell') {
				unset($merged['scope']);
			}
			if ($before !== (string) $n['note']
				|| self::scope_of($by_id[$id]) !== self::scope_of($n)
				|| (isset($n['column']) && (!isset($by_id[$id]['column']) || $by_id[$id]['column'] !== $n['column']))) {
				$result['updated'][] = array('id' => $id, 'before' => $before, 'after' => (string) $n['note']);
			} else {
				$result['unchanged'][] = $id;
			}
			$by_id[$id] = $merged;
		}

		$notes = array();
		foreach ($order as $id) {
			if ($replace && !isset($seen[$id])) {
				$result['removed'][] = $id;
				continue;
			}
			$notes[] = $by_id[$id];
		}
		$result['notes'] = $notes;
		return $result;
	}

	/**
	 * Read a notes CSV. Columns: note_id (or id), note (or text/label); optional indicator_id, scope, column.
	 * Rows for another indicator and rows with scope other than cell are skipped.
	 *
	 * @param string      $csv_path
	 * @param string|null $indicator_id_value keep rows with empty or matching indicator_id
	 * @param string      $delimiter
	 * @return array { notes[], skipped_other_indicator, skipped_scope[ids], conflicts[] , errors[] }
	 */
	public static function parse_notes_csv($csv_path, $indicator_id_value = null, $delimiter = ',')
	{
		$fh = @fopen($csv_path, 'r');
		if (!$fh) {
			throw new Exception('Could not read notes CSV');
		}
		$header = fgetcsv($fh, 0, $delimiter, '"', '\\');
		if (!$header) {
			fclose($fh);
			throw new Exception('Notes CSV is empty');
		}
		$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
		$idx = array();
		foreach ($header as $i => $h) {
			$idx[strtolower(trim((string) $h))] = $i;
		}
		$col_id = self::first_index($idx, array('note_id', 'id'));
		$col_note = self::first_index($idx, array('note', 'text', 'label'));
		if ($col_id === null || $col_note === null) {
			fclose($fh);
			throw new Exception('Notes CSV must have columns note_id and note');
		}
		$col_ind = self::first_index($idx, array('indicator_id'));
		$col_scope = self::first_index($idx, array('scope'));
		$col_column = self::first_index($idx, array('column'));

		$out = array('notes' => array(), 'skipped_other_indicator' => 0, 'skipped_scope' => array(), 'conflicts' => array(), 'errors' => array());
		$by_id = array();
		$line = 1;
		while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
			$line++;
			if ($row === array(null)) {
				continue;
			}
			$id = isset($row[$col_id]) ? trim((string) $row[$col_id]) : '';
			$text = isset($row[$col_note]) ? trim((string) $row[$col_note]) : '';
			if ($id === '' && $text === '') {
				continue;
			}
			if ($col_ind !== null && $indicator_id_value !== null) {
				$ind = isset($row[$col_ind]) ? trim((string) $row[$col_ind]) : '';
				if ($ind !== '' && $ind !== (string) $indicator_id_value) {
					$out['skipped_other_indicator']++;
					continue;
				}
			}
			$scope = '';
			if ($col_scope !== null) {
				$scope = isset($row[$col_scope]) ? strtolower(trim((string) $row[$col_scope])) : '';
				if ($scope !== '' && !in_array($scope, self::$scopes, true)) {
					$out['skipped_scope'][] = $id;
					continue;
				}
			}
			if ($id === '' || $text === '') {
				$out['errors'][] = "Line {$line}: note_id and note are both required.";
				continue;
			}
			if (strpos($id, self::ID_SEPARATOR) !== false) {
				$out['errors'][] = "Line {$line}: note_id '{$id}' must not contain '" . self::ID_SEPARATOR . "'.";
				continue;
			}
			$entry = array('id' => $id, 'note' => $text);
			if ($scope === 'table') {
				$entry['scope'] = 'table';
			}
			if ($col_column !== null && isset($row[$col_column]) && trim((string) $row[$col_column]) !== '') {
				$entry['column'] = trim((string) $row[$col_column]);
			}
			self::collect_note($by_id, $out['conflicts'], $entry);
		}
		fclose($fh);
		$out['notes'] = array_values($by_id);
		return $out;
	}

	/**
	 * Text columns for one footnote column: position (1-based) => header index.
	 *
	 * @param array  $idx    upper-cased header => index
	 * @param string $fc_key upper-cased footnote column name
	 * @return array { suffix, columns{position=>index} } or null when there is no text column
	 */
	public static function text_columns_for(array $idx, $fc_key)
	{
		foreach (self::$text_column_suffixes as $suffix) {
			$base = $fc_key . $suffix;
			if (!isset($idx[$base])) {
				continue;
			}
			$cols = array(1 => $idx[$base]);
			foreach ($idx as $name => $i) {
				if (strpos($name, $base) === 0 && preg_match('/^\d+$/', substr($name, strlen($base)))) {
					$pos = (int) substr($name, strlen($base));
					if ($pos >= 2) {
						$cols[$pos] = $i;
					}
				}
			}
			ksort($cols);
			return array('suffix' => $suffix, 'columns' => $cols);
		}
		return null;
	}

	/**
	 * IDs as written in a cell, trimmed, empty parts dropped, duplicates kept (used for positional pairing).
	 *
	 * @param mixed $value
	 * @return string[]
	 */
	public static function split_ids_positional($value)
	{
		$out = array();
		foreach (explode(self::ID_SEPARATOR, (string) $value) as $part) {
			$id = trim($part);
			if ($id !== '') {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Pair the IDs of one footnote cell with its texts.
	 *
	 * - Several text columns (<COL>_LABEL, <COL>_LABEL2, …): the k-th column is the text of the k-th ID.
	 * - One text column: one ID (or the same ID repeated) takes the whole text; several IDs take the text
	 *   split on '||' when the number of parts matches the number of IDs; no IDs take one part each.
	 * Positions with text but no ID come back with id = null (to be generated).
	 *
	 * @param string[] $ids   positional IDs (split_ids_positional)
	 * @param array    $texts position (1-based) => raw text, from the text columns of the row
	 * @return array { pairs: [ {position, id|null, note} ], skipped: bool }  skipped = texts could not be matched to IDs
	 */
	public static function pair_cell(array $ids, array $texts)
	{
		$clean = array();
		foreach ($texts as $pos => $t) {
			$t = trim((string) $t);
			if ($t !== '') {
				$clean[(int) $pos] = $t;
			}
		}
		$result = array('pairs' => array(), 'skipped' => false);
		if (empty($clean)) {
			return $result;
		}

		if (count($texts) === 1) {
			$text = reset($clean);
			$distinct = array_values(array_unique($ids));
			if (count($distinct) === 1) {
				$result['pairs'][] = array('position' => 1, 'id' => $distinct[0], 'note' => $text);
				return $result;
			}
			$parts = array_values(array_filter(array_map('trim', explode(self::TEXT_SEPARATOR, $text)), 'strlen'));
			if (empty($ids)) {
				foreach ($parts as $k => $part) {
					$result['pairs'][] = array('position' => $k + 1, 'id' => null, 'note' => $part);
				}
				return $result;
			}
			if (count($parts) !== count($ids)) {
				$result['skipped'] = true;
				return $result;
			}
			foreach ($ids as $k => $id) {
				$result['pairs'][] = array('position' => $k + 1, 'id' => $id, 'note' => $parts[$k]);
			}
			return $result;
		}

		foreach ($clean as $pos => $text) {
			$result['pairs'][] = array('position' => $pos, 'id' => isset($ids[$pos - 1]) ? $ids[$pos - 1] : null, 'note' => $text);
		}
		return $result;
	}

	/**
	 * Collect note ID/text pairs from the data CSV for footnote columns that have paired text columns.
	 * Pairing rules: pair_cell(). Positions with text but no ID are ignored here (see generate_ids_in_csv).
	 *
	 * @param string   $csv_path
	 * @param string[] $footnote_columns
	 * @param string   $indicator_column
	 * @param string   $indicator_id_value
	 * @param string   $delimiter
	 * @return array { notes[], text_columns{footnote=>"text1, text2"}, skipped_multi_id, conflicts[] }
	 */
	public static function extract_notes_from_data_csv($csv_path, array $footnote_columns, $indicator_column, $indicator_id_value, $delimiter = ',')
	{
		$out = array('notes' => array(), 'text_columns' => array(), 'skipped_multi_id' => 0, 'conflicts' => array());
		if (empty($footnote_columns)) {
			return $out;
		}
		$fh = @fopen($csv_path, 'r');
		if (!$fh) {
			throw new Exception('Could not read data CSV');
		}
		$header = fgetcsv($fh, 0, $delimiter, '"', '\\');
		if (!$header) {
			fclose($fh);
			return $out;
		}
		$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
		$idx = array();
		foreach ($header as $i => $h) {
			$idx[strtoupper(trim((string) $h))] = $i;
		}

		$pairs = array();
		foreach ($footnote_columns as $fc) {
			$fc_key = strtoupper($fc);
			if (!isset($idx[$fc_key])) {
				continue;
			}
			$tc = self::text_columns_for($idx, $fc_key);
			if ($tc === null) {
				continue;
			}
			$pairs[] = array('column' => $fc, 'id_idx' => $idx[$fc_key], 'text_cols' => $tc['columns']);
			$out['text_columns'][$fc] = implode(', ', array_map(function ($i) use ($header) {
				return $header[$i];
			}, $tc['columns']));
		}
		$ind_idx = isset($idx[strtoupper((string) $indicator_column)]) ? $idx[strtoupper((string) $indicator_column)] : null;
		if (empty($pairs)) {
			fclose($fh);
			return $out;
		}

		$by_id = array();
		while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
			if ($ind_idx !== null && $indicator_id_value !== null && $indicator_id_value !== '') {
				$ind = isset($row[$ind_idx]) ? trim((string) $row[$ind_idx]) : '';
				if ($ind !== (string) $indicator_id_value) {
					continue;
				}
			}
			foreach ($pairs as $p) {
				$ids = self::split_ids_positional(isset($row[$p['id_idx']]) ? $row[$p['id_idx']] : '');
				if (empty($ids)) {
					continue;
				}
				$paired = self::pair_cell($ids, self::row_texts($row, $p['text_cols']));
				if ($paired['skipped']) {
					$out['skipped_multi_id']++;
					continue;
				}
				foreach ($paired['pairs'] as $pair) {
					if ($pair['id'] !== null) {
						self::collect_note($by_id, $out['conflicts'], array('id' => $pair['id'], 'note' => $pair['note'], 'column' => $p['column']));
					}
				}
			}
		}
		fclose($fh);
		$out['notes'] = array_values($by_id);
		return $out;
	}

	/**
	 * Fill missing note IDs in the data CSV (in place) for footnote columns with text columns.
	 * A text that matches an existing note (trimmed, spaces collapsed) reuses its ID; new texts get the next
	 * free ID (N01, N02, …). A footnote column missing from the CSV is added. Only rows of the indicator.
	 *
	 * @param string   $csv_path
	 * @param string[] $footnote_columns
	 * @param string   $indicator_column
	 * @param string   $indicator_id_value
	 * @param array    $existing_notes project data_notes
	 * @param string   $delimiter
	 * @return array { generated: [ {id, note, column} ], reused: int, added_columns: [] }
	 */
	public static function generate_ids_in_csv($csv_path, array $footnote_columns, $indicator_column, $indicator_id_value, array $existing_notes, $delimiter = ',')
	{
		$out = array('generated' => array(), 'reused' => 0, 'added_columns' => array());
		$rows = self::read_csv_rows($csv_path, $delimiter);
		if (empty($rows)) {
			return $out;
		}
		$header = array_shift($rows);
		$idx = array();
		foreach ($header as $i => $h) {
			$idx[strtoupper(trim((string) $h))] = $i;
		}

		$targets = array();
		foreach ($footnote_columns as $fc) {
			$tc = self::text_columns_for($idx, strtoupper($fc));
			if ($tc === null) {
				continue;
			}
			if (!isset($idx[strtoupper($fc)])) {
				$header[] = $fc;
				$idx[strtoupper($fc)] = count($header) - 1;
				$out['added_columns'][] = $fc;
			}
			$targets[] = array('column' => $fc, 'id_idx' => $idx[strtoupper($fc)], 'text_cols' => $tc['columns']);
		}
		if (empty($targets)) {
			return $out;
		}
		$ind_idx = isset($idx[strtoupper((string) $indicator_column)]) ? $idx[strtoupper((string) $indicator_column)] : null;
		$in_scope = function ($row) use ($ind_idx, $indicator_id_value) {
			if ($ind_idx === null || $indicator_id_value === null || $indicator_id_value === '') {
				return true;
			}
			return (isset($row[$ind_idx]) ? trim((string) $row[$ind_idx]) : '') === (string) $indicator_id_value;
		};

		// Known texts and highest numeric ID: existing notes, then explicit ID/text pairs in the file.
		$id_by_text = array();
		$max = 0;
		$track = function ($id) use (&$max) {
			$p = preg_quote(self::GENERATED_ID_PREFIX, '/');
			if (preg_match('/^' . $p . '(\d+)$/', (string) $id, $m)) {
				$max = max($max, (int) $m[1]);
			}
		};
		foreach ($existing_notes as $n) {
			if (!empty($n['id'])) {
				$track($n['id']);
				if (self::scope_of($n) === 'table') {
					continue;
				}
				$key = self::normalize_text(isset($n['note']) ? $n['note'] : '');
				if ($key !== '' && !isset($id_by_text[$key])) {
					$id_by_text[$key] = (string) $n['id'];
				}
			}
		}
		foreach ($rows as $row) {
			if (!$in_scope($row)) {
				continue;
			}
			foreach ($targets as $t) {
				$ids = self::split_ids_positional(isset($row[$t['id_idx']]) ? $row[$t['id_idx']] : '');
				foreach ($ids as $id) {
					$track($id);
				}
				$paired = self::pair_cell($ids, self::row_texts($row, $t['text_cols']));
				foreach ($paired['pairs'] as $pair) {
					$key = self::normalize_text($pair['note']);
					if ($pair['id'] !== null && !isset($id_by_text[$key])) {
						$id_by_text[$key] = $pair['id'];
					}
				}
			}
		}

		$changed = !empty($out['added_columns']);
		$width = count($header);
		foreach ($rows as $r => $row) {
			$row = array_pad($row, $width, '');
			if ($in_scope($row)) {
				foreach ($targets as $t) {
					$ids = self::split_ids_positional($row[$t['id_idx']]);
					$paired = self::pair_cell($ids, self::row_texts($row, $t['text_cols']));
					$final = $ids;
					$filled = false;
					foreach ($paired['pairs'] as $pair) {
						if ($pair['id'] !== null) {
							continue;
						}
						$key = self::normalize_text($pair['note']);
						if (isset($id_by_text[$key])) {
							$id = $id_by_text[$key];
							$out['reused']++;
						} else {
							$max++;
							$id = self::GENERATED_ID_PREFIX . str_pad((string) $max, self::GENERATED_ID_DIGITS, '0', STR_PAD_LEFT);
							$id_by_text[$key] = $id;
							$out['generated'][] = array('id' => $id, 'note' => $pair['note'], 'column' => $t['column']);
						}
						$final[$pair['position'] - 1] = $id;
						$filled = true;
					}
					if ($filled) {
						ksort($final);
						$row[$t['id_idx']] = implode(self::ID_SEPARATOR, array_values(array_filter($final, 'strlen')));
						$changed = true;
					}
				}
			}
			$rows[$r] = $row;
		}

		if ($changed) {
			self::write_csv_rows($csv_path, $header, $rows, $delimiter);
		}
		return $out;
	}

	/**
	 * Footnote columns that may be absent from a data CSV because their IDs will be generated from a text column.
	 *
	 * @param string[] $footnote_columns
	 * @param string[] $csv_headers
	 * @return string[]
	 */
	public static function generatable_footnote_columns(array $footnote_columns, array $csv_headers)
	{
		$idx = array();
		foreach ($csv_headers as $i => $h) {
			$idx[strtoupper(trim((string) $h))] = $i;
		}
		$out = array();
		foreach ($footnote_columns as $fc) {
			if (self::text_columns_for($idx, strtoupper($fc)) !== null) {
				$out[] = $fc;
			}
		}
		return $out;
	}

	/**
	 * Instance wrapper: generate missing IDs in a staging CSV for a bound project. Never throws.
	 *
	 * @return array|null report, or null when the DSD has no footnote columns with text columns in the file
	 */
	public function generate_missing_ids($sid, $csv_path, $indicator_column, $indicator_id_value)
	{
		try {
			$columns = $this->get_footnote_columns($sid);
			if (empty($columns)) {
				return null;
			}
			$report = self::generate_ids_in_csv($csv_path, $columns, $indicator_column, $indicator_id_value, $this->get_notes($sid));
			if (empty($report['generated']) && empty($report['reused']) && empty($report['added_columns'])) {
				return null;
			}
			return $report;
		} catch (Throwable $e) {
			log_message('error', 'Note ID generation failed sid=' . (int) $sid . ': ' . $e->getMessage());
			return array('warning' => 'Note IDs could not be generated: ' . $e->getMessage());
		}
	}

	/**
	 * Check note references in the imported data against project data_notes.
	 *
	 * @param int        $sid
	 * @param array|null $notes defaults to the project's data_notes
	 * @return array { footnote_columns[], errors[], warnings[], used{column=>ids[]}, unknown[], unused[], wrong_column[] }
	 */
	public function check_references($sid, $notes = null)
	{
		$columns = $this->get_footnote_columns($sid);
		if ($notes === null) {
			$notes = $this->get_notes($sid);
		}
		$result = array('footnote_columns' => $columns, 'errors' => array(), 'warnings' => array(), 'used' => array());
		if (empty($columns)) {
			$cell_notes = array_filter($notes, function ($n) {
				return is_array($n) && self::scope_of($n) === 'cell';
			});
			if (!empty($cell_notes)) {
				$result['warnings'][] = 'The project has cell notes but no footnote column in its data structure (set Display as: Footnote on the note column).';
			}
			return $result + array('unknown' => array(), 'unused' => array(), 'wrong_column' => array());
		}

		$this->ci->load->model('Indicator_dsd_model');
		$this->ci->load->library('indicator_duckdb_service');
		$physical = array_combine($columns, $this->ci->Indicator_dsd_model->normalize_duckdb_staging_column_names($columns));

		foreach ($columns as $col) {
			$res = $this->ci->indicator_duckdb_service->timeseries_distinct_pairs($sid, $physical[$col], null, self::CHECK_DISTINCT_LIMIT);
			if (is_array($res) && !empty($res['error'])) {
				$result['warnings'][] = "Could not read values of footnote column '{$col}': " . (isset($res['message']) ? $res['message'] : 'unknown error');
				$result['used'][$col] = array();
				continue;
			}
			if (!empty($res['truncated'])) {
				$result['warnings'][] = "Footnote column '{$col}' has more than " . self::CHECK_DISTINCT_LIMIT . ' distinct values; reference checks are incomplete.';
			}
			$ids = array();
			foreach ((isset($res['items']) ? $res['items'] : array()) as $item) {
				foreach (self::split_ids(isset($item['code']) ? $item['code'] : '') as $id) {
					$ids[$id] = true;
				}
			}
			$result['used'][$col] = array_keys($ids);
		}

		return array_merge($result, self::compare_references($result['used'], $notes, $result['errors'], $result['warnings']));
	}

	/**
	 * Compare IDs used per footnote column with defined notes.
	 *
	 * @param array $used  column => ids[]
	 * @param array $notes data_notes
	 * @param array $errors   appended
	 * @param array $warnings appended
	 * @return array { errors, warnings, unknown[], unused[], wrong_column[] }
	 */
	public static function compare_references(array $used, array $notes, array $errors = array(), array $warnings = array())
	{
		$defined = array();
		$table_ids = array();
		foreach ($notes as $n) {
			if (!empty($n['id'])) {
				$defined[trim((string) $n['id'])] = isset($n['column']) ? trim((string) $n['column']) : '';
				if (self::scope_of($n) === 'table') {
					$table_ids[trim((string) $n['id'])] = true;
				}
			}
		}

		$unknown = array();
		$wrong = array();
		$used_ids = array();
		foreach ($used as $col => $ids) {
			foreach ($ids as $id) {
				$used_ids[$id] = true;
				if (!array_key_exists($id, $defined)) {
					$unknown[] = array('column' => $col, 'id' => $id);
					$errors[] = "Note ID '{$id}' used in column '{$col}' is not defined in this project's data notes.";
				} elseif (isset($table_ids[$id])) {
					$warnings[] = "Note ID '{$id}' is a table note but is used in cells of column '{$col}'.";
				} elseif ($defined[$id] !== '' && strcasecmp($defined[$id], $col) !== 0) {
					$wrong[] = array('column' => $col, 'id' => $id, 'expected_column' => $defined[$id]);
					$warnings[] = "Note ID '{$id}' is used in column '{$col}' but is declared for column '{$defined[$id]}'.";
				}
			}
		}

		$unused = array();
		foreach (array_keys($defined) as $id) {
			if (!isset($used_ids[$id]) && !isset($table_ids[$id])) {
				$unused[] = $id;
				$warnings[] = "Data note '{$id}' is not used by any cell.";
			}
		}

		return array('errors' => $errors, 'warnings' => $warnings, 'unknown' => $unknown, 'unused' => $unused, 'wrong_column' => $wrong);
	}

	/**
	 * Load notes from the data CSV after a data import, save new/changed notes and check references.
	 * Never throws: problems are returned as warnings so the data import result is not affected.
	 *
	 * @param int    $sid
	 * @param string $csv_path staging CSV (all columns)
	 * @param string $indicator_column
	 * @param string $indicator_id_value
	 * @param int|null $user_id
	 * @return array|null null when the DSD has no footnote columns and the project has no notes
	 */
	public function process_after_data_import($sid, $csv_path, $indicator_column, $indicator_id_value, $user_id = null, $generated = null)
	{
		try {
			$columns = $this->get_footnote_columns($sid);
			$existing = $this->get_notes($sid);
			if (empty($columns) && empty($existing)) {
				return null;
			}

			$report = array('from_data_csv' => null);
			$notes = $existing;
			if (!empty($columns) && $csv_path) {
				$extracted = self::extract_notes_from_data_csv($csv_path, $columns, $indicator_column, $indicator_id_value);
				if (!empty($extracted['notes'])) {
					$merge = self::merge_notes($existing, $extracted['notes'], false);
					if (!empty($merge['added']) || !empty($merge['updated'])) {
						$this->save_notes($sid, $merge['notes'], $user_id);
					}
					$notes = $merge['notes'];
					$report['from_data_csv'] = array(
						'text_columns' => $extracted['text_columns'],
						'added' => $merge['added'],
						'updated' => $merge['updated'],
						'unchanged' => $merge['unchanged'],
						'skipped_multi_id' => $extracted['skipped_multi_id'],
						'conflicts' => $extracted['conflicts'],
					);
				} elseif (!empty($extracted['text_columns'])) {
					$report['from_data_csv'] = array('text_columns' => $extracted['text_columns'], 'added' => array(), 'updated' => array(),
						'unchanged' => array(), 'skipped_multi_id' => $extracted['skipped_multi_id'], 'conflicts' => array());
				}
			}

			if ($generated !== null) {
				$report['generated_ids'] = $generated;
			}
			$report['checks'] = $this->check_references($sid, $notes);
			$report['notes_count'] = count($notes);
			return $report;
		} catch (Throwable $e) {
			log_message('error', 'Data notes processing failed sid=' . (int) $sid . ': ' . $e->getMessage());
			return array('warning' => 'Data notes could not be processed: ' . $e->getMessage());
		}
	}

	/** 'table' or 'cell' (default) for a data_notes entry. */
	public static function scope_of(array $note)
	{
		$s = isset($note['scope']) ? strtolower(trim((string) $note['scope'])) : '';
		return $s === 'table' ? 'table' : 'cell';
	}

	/**
	 * Table notes of a project in data_notes order: [ { id, note } ].
	 *
	 * @param int $sid
	 * @return array
	 */
	public function get_table_notes($sid)
	{
		$out = array();
		foreach ($this->get_notes($sid) as $n) {
			if (self::scope_of($n) === 'table' && isset($n['note']) && trim((string) $n['note']) !== '') {
				$out[] = array('id' => isset($n['id']) ? (string) $n['id'] : '', 'note' => (string) $n['note']);
			}
		}
		return $out;
	}

	/**
	 * @param array $row
	 * @param array $text_cols position => header index
	 * @return array position => text
	 */
	protected static function row_texts(array $row, array $text_cols)
	{
		$texts = array();
		foreach ($text_cols as $pos => $i) {
			$texts[$pos] = isset($row[$i]) ? (string) $row[$i] : '';
		}
		return $texts;
	}

	/** Text key for matching notes: trimmed, internal whitespace collapsed. */
	public static function normalize_text($text)
	{
		return trim(preg_replace('/\s+/u', ' ', (string) $text));
	}

	protected static function read_csv_rows($csv_path, $delimiter)
	{
		$fh = @fopen($csv_path, 'r');
		if (!$fh) {
			throw new Exception('Could not read data CSV');
		}
		$rows = array();
		while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
			if ($row === array(null)) {
				continue;
			}
			$rows[] = $row;
		}
		fclose($fh);
		if (!empty($rows)) {
			$rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rows[0][0]);
		}
		return $rows;
	}

	protected static function write_csv_rows($csv_path, array $header, array $rows, $delimiter)
	{
		$tmp = $csv_path . '.notes.tmp';
		$fh = @fopen($tmp, 'w');
		if (!$fh) {
			throw new Exception('Could not write data CSV');
		}
		fputcsv($fh, $header, $delimiter, '"', '\\');
		foreach ($rows as $row) {
			fputcsv($fh, $row, $delimiter, '"', '\\');
		}
		fclose($fh);
		if (!@rename($tmp, $csv_path)) {
			@unlink($tmp);
			throw new Exception('Could not replace data CSV');
		}
	}

	/**
	 * @param array $by_id     id => entry (updated)
	 * @param array $conflicts appended with { id, previous, used }
	 * @param array $entry
	 */
	protected static function collect_note(array &$by_id, array &$conflicts, array $entry)
	{
		$id = $entry['id'];
		if (isset($by_id[$id]) && $by_id[$id]['note'] !== $entry['note']) {
			$conflicts[] = array('id' => $id, 'previous' => $by_id[$id]['note'], 'used' => $entry['note']);
		}
		$by_id[$id] = $entry;
	}

	/**
	 * @param array    $idx lower/upper-cased header => index
	 * @param string[] $names
	 * @return int|null
	 */
	protected static function first_index(array $idx, array $names)
	{
		foreach ($names as $n) {
			if (isset($idx[$n])) {
				return $idx[$n];
			}
		}
		return null;
	}
}
