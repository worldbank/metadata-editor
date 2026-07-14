<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Export (and future import) of per-datafile CSV data dictionaries.
 *
 * Format v1: UTF-8 CSV with BOM; Block 1 = all info rows; Block 2 = all category rows.
 */
class Data_dictionary_csv
{
	const FORMAT_VERSION = '1.0';

	const ROW_TYPE_INFO = 'info';
	const ROW_TYPE_CATEGORY = 'category';

	const CSV_COLUMNS = array(
		'row_type',
		'sequence',
		'name',
		'label',
		'type',
		'missing_values',
		'value',
		'value_label',
		'is_missing',
	);

	/** @var CI_Controller */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_datafile_model');
		$this->ci->load->model('Editor_variable_model');
	}

	/**
	 * @param int $sid
	 * @param string $fid
	 * @param array $options
	 * @return string UTF-8 CSV with BOM
	 * @throws Exception
	 */
	public function export_csv($sid, $fid, array $options = array())
	{
		return $this->_render_csv($this->_build_export_rows($sid, $fid), $options);
	}

	/**
	 * @param int $sid
	 * @param string $fid
	 * @param string $path
	 * @param array $options
	 * @return array{ path: string, rows_info: int, rows_category: int, bytes: int }
	 * @throws Exception
	 */
	public function export_csv_to_path($sid, $fid, $path, array $options = array())
	{
		$rows = $this->_build_export_rows($sid, $fid);
		$csv = $this->_render_csv($rows, $options);
		$dir = dirname($path);
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0755, true)) {
				throw new Exception('Failed to create directory for dictionary export');
			}
		}
		if (@file_put_contents($path, $csv) === false) {
			throw new Exception('Failed to write dictionary CSV');
		}

		$info = 0;
		$category = 0;
		foreach ($rows as $row) {
			if ($row['row_type'] === self::ROW_TYPE_INFO) {
				$info++;
			} elseif ($row['row_type'] === self::ROW_TYPE_CATEGORY) {
				$category++;
			}
		}

		return array(
			'path' => $path,
			'rows_info' => $info,
			'rows_category' => $category,
			'bytes' => strlen($csv),
		);
	}

	/**
	 * Basename for dictionary file bundled with a data CSV (e.g. household_dictionary.csv).
	 *
	 * @param array $datafile
	 * @return string
	 */
	public function dictionary_filename_for_datafile(array $datafile)
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

		return $base . '_dictionary.csv';
	}

	/**
	 * Variables with merged metadata plus DB columns needed for export.
	 *
	 * @param int $sid
	 * @param string $fid
	 * @return array
	 */
	private function _load_variables($sid, $fid)
	{
		$variables = $this->ci->Editor_variable_model->select_all($sid, $fid, true);
		if (!is_array($variables) || empty($variables)) {
			return is_array($variables) ? $variables : array();
		}

		$this->ci->db->select('name, field_dtype, user_missings');
		$this->ci->db->where('sid', (int) $sid);
		$this->ci->db->where('fid', (string) $fid);
		$db_rows = $this->ci->db->get('editor_variables')->result_array();
		$db_by_name = array();
		foreach ($db_rows as $row) {
			$db_by_name[(string) $row['name']] = $row;
		}

		foreach ($variables as $key => $variable) {
			$name = isset($variable['name']) ? (string) $variable['name'] : '';
			if ($name === '' || !isset($db_by_name[$name])) {
				continue;
			}
			$db_row = $db_by_name[$name];
			if (isset($db_row['field_dtype']) && trim((string) $db_row['field_dtype']) !== '') {
				$variables[$key]['field_dtype'] = (string) $db_row['field_dtype'];
			}
			if (isset($db_row['user_missings'])) {
				$variables[$key]['user_missings'] = (string) $db_row['user_missings'];
			}
		}

		return $variables;
	}

	/**
	 * @param int $sid
	 * @param string $fid
	 * @return array
	 * @throws Exception
	 */
	private function _build_export_rows($sid, $fid)
	{
		$sid = (int) $sid;
		$fid = (string) $fid;

		$datafile = $this->ci->Editor_datafile_model->data_file_by_id($sid, $fid);
		if (!$datafile) {
			throw new Exception('Data file not found: ' . $fid);
		}

		$variables = $this->_load_variables($sid, $fid);
		if (!is_array($variables)) {
			$variables = array();
		}

		$rows = array();
		$index = 0;
		foreach ($variables as $variable) {
			$index++;
			$sort_order = isset($variable['sort_order']) ? (int) $variable['sort_order'] : 0;
			$sequence = $sort_order > 0 ? $sort_order : $index;
			$rows[] = $this->_info_row($variable, $sequence);
		}

		foreach ($variables as $variable) {
			$categories = $this->_category_entries_for_variable($variable);
			if (empty($categories)) {
				continue;
			}
			$cat_seq = 0;
			foreach ($categories as $cat) {
				$cat_seq++;
				$rows[] = $this->_category_row($variable['name'], $cat, $cat_seq);
			}
		}

		return $rows;
	}

	/**
	 * @param array $variable
	 * @param int $sequence
	 * @return array
	 */
	private function _info_row(array $variable, $sequence)
	{
		return array(
			'row_type' => self::ROW_TYPE_INFO,
			'sequence' => (string) (int) $sequence,
			'name' => isset($variable['name']) ? (string) $variable['name'] : '',
			'label' => isset($variable['labl']) ? (string) $variable['labl'] : '',
			'type' => $this->_normalize_export_type(
				$this->_variable_dtype($variable)
			),
			'missing_values' => $this->_missing_values_string($variable),
			'value' => '',
			'value_label' => '',
			'is_missing' => '',
		);
	}

	/**
	 * @param string $name
	 * @param array $cat
	 * @param int $sequence
	 * @return array
	 */
	private function _category_row($name, array $cat, $sequence)
	{
		return array(
			'row_type' => self::ROW_TYPE_CATEGORY,
			'sequence' => (string) (int) $sequence,
			'name' => (string) $name,
			'label' => '',
			'type' => '',
			'missing_values' => '',
			'value' => isset($cat['value']) ? (string) $cat['value'] : '',
			'value_label' => isset($cat['labl']) ? (string) $cat['labl'] : '',
			'is_missing' => (isset($cat['is_missing']) && (string) $cat['is_missing'] === '1') ? '1' : '',
		);
	}

	/**
	 * Value/label rows for Block 2 (var_catgry_labels primary; extras from var_catgry).
	 *
	 * @param array $variable
	 * @return array<int, array{value: string, labl: string, is_missing: string}>
	 */
	private function _category_entries_for_variable(array $variable)
	{
		$raw_labels = isset($variable['var_catgry_labels'])
			? $variable['var_catgry_labels']
			: array();
		$var_catgry_labels = is_array($raw_labels) ? array_values($raw_labels) : array();

		$catgry_by_value = array();
		if (isset($variable['var_catgry']) && is_array($variable['var_catgry'])) {
			foreach ($variable['var_catgry'] as $cat) {
				$cat = is_array($cat) ? $cat : (array) $cat;
				if (isset($cat['value'])) {
					$catgry_by_value[(string) $cat['value']] = $cat;
				}
			}
		}

		$missing_values = $this->_missing_value_codes($variable, $catgry_by_value);
		$entries = array();
		$seen = array();

		foreach ($var_catgry_labels as $label_item) {
			$label_item = is_array($label_item) ? $label_item : (array) $label_item;
			if (!array_key_exists('value', $label_item)) {
				continue;
			}
			$value_str = (string) $label_item['value'];
			$seen[$value_str] = true;
			$entries[] = array(
				'value' => $value_str,
				'labl' => isset($label_item['labl']) ? (string) $label_item['labl'] : '',
				'is_missing' => in_array($value_str, $missing_values, true) ? '1' : '',
			);
		}

		foreach ($catgry_by_value as $value_key => $cat) {
			if (isset($seen[$value_key])) {
				continue;
			}
			$label_extra = isset($cat['labl']) ? (string) $cat['labl'] : '';
			if ($label_extra === '' && !isset($cat['is_missing'])) {
				continue;
			}
			$entries[] = array(
				'value' => $value_key,
				'labl' => $label_extra,
				'is_missing' => in_array($value_key, $missing_values, true) ? '1' : '',
			);
		}

		return $entries;
	}

	/**
	 * @param array $variable
	 * @param array $catgry_by_value
	 * @return array<int, string>
	 */
	private function _missing_value_codes(array $variable, array $catgry_by_value)
	{
		$missing_values = array();
		if (isset($variable['var_invalrng']['values']) && is_array($variable['var_invalrng']['values'])) {
			$missing_values = array_map('strval', $variable['var_invalrng']['values']);
		}

		foreach ($catgry_by_value as $value_key => $cat) {
			if (!isset($cat['is_missing'])) {
				continue;
			}
			$im = $cat['is_missing'];
			if ($im === '1' || $im === 1 || $im === 'Y' || $im === true) {
				if (!in_array((string) $value_key, $missing_values, true)) {
					$missing_values[] = (string) $value_key;
				}
			}
		}

		return $missing_values;
	}

	/**
	 * @param array $variable
	 * @return string
	 */
	private function _missing_values_string(array $variable)
	{
		if (isset($variable['user_missings']) && trim((string) $variable['user_missings']) !== '') {
			return trim((string) $variable['user_missings']);
		}

		if (isset($variable['var_invalrng']['values']) && is_array($variable['var_invalrng']['values'])) {
			$vals = array();
			foreach ($variable['var_invalrng']['values'] as $v) {
				$v = trim((string) $v);
				if ($v !== '') {
					$vals[] = $v;
				}
			}
			if (!empty($vals)) {
				return implode(',', $vals);
			}
		}

		return '';
	}

	/**
	 * @param array $variable
	 * @return string
	 */
	private function _variable_dtype(array $variable)
	{
		if (isset($variable['field_dtype']) && trim((string) $variable['field_dtype']) !== '') {
			return (string) $variable['field_dtype'];
		}
		if (isset($variable['var_format']['type']) && trim((string) $variable['var_format']['type']) !== '') {
			return (string) $variable['var_format']['type'];
		}
		return '';
	}

	/**
	 * @param string $field_dtype
	 * @return string numeric|string|date
	 */
	private function _normalize_export_type($field_dtype)
	{
		$field_dtype = strtolower(trim((string) $field_dtype));
		if ($field_dtype === 'numeric') {
			return 'numeric';
		}
		if ($field_dtype === 'date') {
			return 'date';
		}
		if (in_array($field_dtype, array('character', 'string', 'fixed'), true)) {
			return 'string';
		}
		return 'string';
	}

	/**
	 * @param array $rows
	 * @param array $options
	 * @return string
	 */
	private function _render_csv(array $rows, array $options = array())
	{
		$eol = isset($options['line_ending']) ? (string) $options['line_ending'] : "\r\n";
		$lines = array();
		$lines[] = $this->_format_row(self::CSV_COLUMNS);

		foreach ($rows as $row) {
			$cells = array();
			foreach (self::CSV_COLUMNS as $col) {
				$cells[] = isset($row[$col]) ? (string) $row[$col] : '';
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
}
