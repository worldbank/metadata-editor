<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Import DSD components (and codelists from CSV columns) into an existing data structure.
 */
class Data_structure_csv_import {

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('Codelists_model');
		$this->CI->load->model('Data_structure_model');
		$this->CI->load->model('Data_structure_component_model');
	}

	/**
	 * @param int    $structure_id
	 * @param string $csv_path Absolute path to CSV file
	 * @param array  $payload delimiter, components[]
	 * @param array  $options dry_run, overwrite, user_id
	 * @return array
	 * @throws Exception
	 */
	public function import_from_csv($structure_id, $csv_path, array $payload, array $options = array())
	{
		$structure_id = (int) $structure_id;
		if ($structure_id <= 0) {
			throw new Exception('Data structure id is required.');
		}
		if (!is_string($csv_path) || $csv_path === '' || !is_readable($csv_path)) {
			throw new Exception('CSV file is not readable.');
		}

		$structure = $this->CI->Data_structure_model->get_structure_by_id($structure_id, true);
		if (!$structure) {
			throw new Exception('Data structure not found.');
		}
		if (Data_structure_model::is_locked_status((int) $structure['status'])) {
			throw new Exception('Cannot add components to a locked data structure (published/archived).');
		}

		$delimiter = isset($payload['delimiter']) ? (string) $payload['delimiter'] : ',';
		if ($delimiter === '') {
			$delimiter = ',';
		}
		$components = isset($payload['components']) && is_array($payload['components'])
			? $payload['components'] : array();
		if (empty($components)) {
			throw new Exception('At least one component mapping is required.');
		}

		$dry_run = !empty($options['dry_run']);
		$overwrite = !empty($options['overwrite']);
		$user_id = isset($options['user_id']) ? $options['user_id'] : null;

		$errors = $this->_validate_components($structure_id, $components);
		if (!empty($errors)) {
			throw new Exception('VALIDATION_FAILED: ' . json_encode($errors));
		}

		$parsed = $this->_parse_csv_headers($csv_path, $delimiter);
		$header_errors = $this->_validate_csv_columns($parsed['headers'], $components);
		if (!empty($header_errors)) {
			throw new Exception('VALIDATION_FAILED: ' . json_encode($header_errors));
		}

		$agency = isset($structure['agency']) && trim((string) $structure['agency']) !== ''
			? trim((string) $structure['agency'])
			: Data_structure_model::DEFAULT_AGENCY;
		$codelist_version = Codelists_model::NADA_DEFAULT_VERSION;

		$summary = array(
			'dry_run' => $dry_run,
			'structure_id' => $structure_id,
			'components_created' => array(),
			'components_preview' => array(),
			'codelists_created' => array(),
			'codelists_reused' => array(),
			'codelists_updated' => array(),
			'warnings' => array(),
		);

		$resolved_codelists = array();
		foreach ($components as $idx => $comp) {
			$resolved_codelists[$idx] = $this->_resolve_codelist_for_mapping(
				$comp,
				$csv_path,
				$delimiter,
				$parsed['headers'],
				$agency,
				$codelist_version,
				$overwrite,
				$dry_run,
				$summary
			);
		}

		if ($dry_run) {
			foreach ($components as $idx => $comp) {
				$summary['components_preview'][] = array(
					'name' => trim((string) $comp['name']),
					'label' => isset($comp['label']) ? trim((string) $comp['label']) : null,
					'column_type' => trim((string) $comp['column_type']),
					'csv_column' => trim((string) $comp['csv_column']),
					'codelist_id' => $resolved_codelists[$idx],
				);
			}
			return $summary;
		}

		$this->CI->db->trans_begin();
		try {
			foreach ($components as $idx => $comp) {
				$codelist_id = $resolved_codelists[$idx];
				$row = array(
					'name' => trim((string) $comp['name']),
					'label' => isset($comp['label']) ? trim((string) $comp['label']) : null,
					'column_type' => trim((string) $comp['column_type']),
					'codelist_id' => $codelist_id !== null && $codelist_id > 0 ? (int) $codelist_id : null,
					'sort_order' => $idx,
				);
				if ($row['label'] === '') {
					$row['label'] = null;
				}
				if ($user_id) {
					$row['created_by'] = (int) $user_id;
					$row['updated_by'] = (int) $user_id;
				}
				$new_id = $this->CI->Data_structure_component_model->create_component($structure_id, $row);
				$summary['components_created'][] = array(
					'id' => $new_id,
					'name' => $row['name'],
					'csv_column' => trim((string) $comp['csv_column']),
				);
			}

			if ($this->CI->db->trans_status() === false) {
				throw new Exception('Database transaction failed.');
			}
			$this->CI->db->trans_commit();
			return $summary;
		} catch (Exception $e) {
			$this->CI->db->trans_rollback();
			throw $e;
		}
	}

	/**
	 * True when import would create or update global codelists from CSV columns.
	 *
	 * @param array $payload
	 * @param bool  $overwrite
	 * @return bool
	 */
	public static function payload_mutates_codelists(array $payload, $overwrite = false)
	{
		$overwrite = !empty($overwrite);
		if (empty($payload['components']) || !is_array($payload['components'])) {
			return false;
		}
		foreach ($payload['components'] as $comp) {
			if (!is_array($comp) || empty($comp['codelist']) || !is_array($comp['codelist'])) {
				continue;
			}
			$cl = $comp['codelist'];
			if (isset($cl['mode']) && $cl['mode'] === 'from_csv') {
				return true;
			}
			if ($overwrite && isset($cl['mode']) && $cl['mode'] === 'global') {
				continue;
			}
		}
		return false;
	}

	/**
	 * SDMX codelist maintainable id: CL_{COMPONENT_NAME} (max 64 chars, no double prefix).
	 *
	 * @param string $component_name
	 * @return string
	 */
	public static function codelist_maintainable_name($component_name)
	{
		$name = strtoupper(trim((string) $component_name));
		if ($name === '') {
			return 'CL_COLUMN';
		}
		if (preg_match('/^CL_/i', $name)) {
			return substr($name, 0, 64);
		}
		$out = 'CL_' . $name;
		if (strlen($out) > 64) {
			$out = substr($out, 0, 64);
			$out = rtrim($out, '_');
		}
		return $out !== '' ? $out : 'CL_COLUMN';
	}

	/**
	 * @param string $csv_path
	 * @param string $delimiter
	 * @param string $code_column
	 * @param string|null $label_column
	 * @param array $headers Pre-parsed header list (optional; parsed when empty)
	 * @return array[] { code, label }
	 */
	public static function extract_distinct_code_label_pairs($csv_path, $delimiter, $code_column, $label_column = null, array $headers = array())
	{
		if (empty($headers)) {
			$headers = self::_read_csv_header_row($csv_path, $delimiter);
		}
		$code_idx = array_search($code_column, $headers, true);
		if ($code_idx === false) {
			return array();
		}
		$label_idx = false;
		if ($label_column !== null && $label_column !== '') {
			$label_idx = array_search($label_column, $headers, true);
		}

		$map = array();
		$fh = fopen($csv_path, 'rb');
		if (!$fh) {
			return array();
		}
		// Skip header row.
		fgetcsv($fh, 0, $delimiter);
		while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
			$code = isset($row[$code_idx]) ? trim((string) $row[$code_idx]) : '';
			if ($code === '') {
				continue;
			}
			$label = $code;
			if ($label_idx !== false && isset($row[$label_idx])) {
				$label = trim((string) $row[$label_idx]);
				if ($label === '') {
					$label = $code;
				}
			}
			if (!isset($map[$code])) {
				$map[$code] = $label;
			} elseif ($label !== '' && $map[$code] !== $label) {
				$map[$code] = $label;
			}
		}
		fclose($fh);

		$items = array();
		foreach ($map as $code => $label) {
			$items[] = array('code' => $code, 'label' => $label);
		}
		return $items;
	}

	/**
	 * @param int $structure_id
	 * @param array $components
	 * @return array[] error objects
	 */
	protected function _validate_components($structure_id, array $components)
	{
		$errors = array();
		$names = array();
		$existing_by_name = array();
		foreach ($this->CI->Data_structure_component_model->get_components_by_structure_id($structure_id) as $row) {
			$n = isset($row['name']) ? strtolower(trim((string) $row['name'])) : '';
			if ($n !== '') {
				$existing_by_name[$n] = $row['name'];
			}
		}

		foreach ($components as $idx => $comp) {
			if (!is_array($comp)) {
				$errors[] = array('path' => "components[{$idx}]", 'message' => 'Must be an object.');
				continue;
			}
			$name = isset($comp['name']) ? trim((string) $comp['name']) : '';
			if ($name === '') {
				$errors[] = array('path' => "components[{$idx}].name", 'message' => 'Required.');
			} elseif (!preg_match('/^(?!_)[A-Za-z0-9_]{1,100}$/', $name)) {
				$errors[] = array('path' => "components[{$idx}].name", 'message' => 'Invalid SDMX component name.');
			} else {
				$key = strtolower($name);
				if (isset($names[$key])) {
					$errors[] = array(
						'path' => "components[{$idx}].name",
						'message' => "Duplicate component name '{$name}' (case-insensitive match with '{$names[$key]}').",
					);
				} elseif (isset($existing_by_name[$key])) {
					$errors[] = array(
						'path' => "components[{$idx}].name",
						'message' => "Component name '{$name}' already exists on this data structure.",
					);
				} else {
					$names[$key] = $name;
				}
			}

			$csv_col = isset($comp['csv_column']) ? trim((string) $comp['csv_column']) : '';
			if ($csv_col === '') {
				$errors[] = array('path' => "components[{$idx}].csv_column", 'message' => 'Required.');
			}

			$column_type = isset($comp['column_type']) ? trim((string) $comp['column_type']) : '';
			if ($column_type === '') {
				$errors[] = array('path' => "components[{$idx}].column_type", 'message' => 'Required.');
			}

			$cl = isset($comp['codelist']) && is_array($comp['codelist']) ? $comp['codelist'] : null;
			if ($cl !== null) {
				$mode = isset($cl['mode']) ? trim((string) $cl['mode']) : '';
				if ($mode === 'global') {
					$cid = isset($cl['codelist_id']) ? (int) $cl['codelist_id'] : 0;
					if ($cid <= 0) {
						$errors[] = array('path' => "components[{$idx}].codelist.codelist_id", 'message' => 'Required for global codelist.');
					} elseif (!$this->CI->Codelists_model->get_by_id($cid)) {
						$errors[] = array('path' => "components[{$idx}].codelist.codelist_id", 'message' => 'Codelist not found.');
					}
				} elseif ($mode === 'from_csv') {
					$code_col = isset($cl['code_column']) ? trim((string) $cl['code_column']) : $csv_col;
					if ($code_col === '') {
						$errors[] = array('path' => "components[{$idx}].codelist.code_column", 'message' => 'Required.');
					}
				} elseif ($mode !== '') {
					$errors[] = array('path' => "components[{$idx}].codelist.mode", 'message' => 'Invalid codelist mode.');
				}
			}
		}

		if (!empty($errors)) {
			return $errors;
		}

		$columns = array();
		foreach ($components as $comp) {
			$columns[] = array(
				'name' => trim((string) $comp['name']),
				'column_type' => trim((string) $comp['column_type']),
			);
		}
		$this->CI->load->library('Indicator_dsd_structure_validate');
		$result = $this->CI->indicator_dsd_structure_validate->validate_columns($columns);
		if (empty($result['valid'])) {
			foreach ($result['errors'] as $msg) {
				$errors[] = array('path' => 'components', 'message' => (string) $msg);
			}
		}

		return $errors;
	}

	/**
	 * @param array $headers
	 * @param array $components
	 * @return array[]
	 */
	protected function _validate_csv_columns(array $headers, array $components)
	{
		$errors = array();
		foreach ($components as $idx => $comp) {
			$csv_col = isset($comp['csv_column']) ? trim((string) $comp['csv_column']) : '';
			if ($csv_col !== '' && array_search($csv_col, $headers, true) === false) {
				$errors[] = array(
					'path' => "components[{$idx}].csv_column",
					'message' => "CSV column '{$csv_col}' not found in file header.",
				);
			}
			$cl = isset($comp['codelist']) && is_array($comp['codelist']) ? $comp['codelist'] : null;
			if ($cl && isset($cl['mode']) && $cl['mode'] === 'from_csv') {
				$code_col = isset($cl['code_column']) ? trim((string) $cl['code_column']) : $csv_col;
				if ($code_col !== '' && array_search($code_col, $headers, true) === false) {
					$errors[] = array(
						'path' => "components[{$idx}].codelist.code_column",
						'message' => "CSV code column '{$code_col}' not found in file header.",
					);
				}
				$label_col = isset($cl['label_column']) && $cl['label_column'] !== null
					? trim((string) $cl['label_column']) : '';
				if ($label_col !== '' && array_search($label_col, $headers, true) === false) {
					$errors[] = array(
						'path' => "components[{$idx}].codelist.label_column",
						'message' => "CSV label column '{$label_col}' not found in file header.",
					);
				}
			}
		}
		return $errors;
	}

	/**
	 * @param string $csv_path
	 * @param string $delimiter
	 * @return array{ headers: string[] }
	 * @throws Exception
	 */
	protected function _parse_csv_headers($csv_path, $delimiter)
	{
		return array('headers' => self::_read_csv_header_row($csv_path, $delimiter));
	}

	/**
	 * @param string $csv_path
	 * @param string $delimiter
	 * @return string[]
	 * @throws Exception
	 */
	protected static function _read_csv_header_row($csv_path, $delimiter)
	{
		$fh = fopen($csv_path, 'rb');
		if (!$fh) {
			throw new Exception('Could not open CSV file.');
		}
		$first = fgetcsv($fh, 0, $delimiter);
		fclose($fh);
		if (!is_array($first) || empty($first)) {
			throw new Exception('CSV file has no header row.');
		}
		if (isset($first[0])) {
			$first[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first[0]);
		}
		$headers = array();
		foreach ($first as $h) {
			$headers[] = trim((string) $h);
		}
		return $headers;
	}

	/**
	 * @param array $comp
	 * @param string $csv_path
	 * @param string $delimiter
	 * @param array $headers
	 * @param string $agency
	 * @param string $codelist_version
	 * @param bool $overwrite
	 * @param bool $dry_run
	 * @param array $summary
	 * @return int|null
	 * @throws Exception
	 */
	protected function _resolve_codelist_for_mapping(
		array $comp,
		$csv_path,
		$delimiter,
		array $headers,
		$agency,
		$codelist_version,
		$overwrite,
		$dry_run,
		array &$summary
	) {
		$cl = isset($comp['codelist']) && is_array($comp['codelist']) ? $comp['codelist'] : null;
		if (!$cl) {
			return null;
		}
		$mode = isset($cl['mode']) ? trim((string) $cl['mode']) : '';
		if ($mode === 'global') {
			$cid = isset($cl['codelist_id']) ? (int) $cl['codelist_id'] : 0;
			if ($cid <= 0) {
				throw new Exception('Global codelist id is required.');
			}
			$row = $this->CI->Codelists_model->get_by_id($cid);
			if (!$row) {
				throw new Exception('Codelist not found.');
			}
			$summary['codelists_reused'][] = array('id' => $cid, 'idno' => $row['idno']);
			return $cid;
		}
		if ($mode !== 'from_csv') {
			return null;
		}

		$component_name = trim((string) $comp['name']);
		$cl_name = self::codelist_maintainable_name($component_name);
		$cl_title = isset($comp['label']) && trim((string) $comp['label']) !== ''
			? trim((string) $comp['label'])
			: $cl_name;
		$code_col = isset($cl['code_column']) ? trim((string) $cl['code_column']) : trim((string) $comp['csv_column']);
		$label_col = isset($cl['label_column']) && $cl['label_column'] !== null && $cl['label_column'] !== ''
			? trim((string) $cl['label_column']) : null;

		$items = self::extract_distinct_code_label_pairs($csv_path, $delimiter, $code_col, $label_col, $headers);
		if (empty($items)) {
			throw new Exception("No codes found in CSV column '{$code_col}' for codelist '{$cl_name}'.");
		}

		$existing = $this->CI->Codelists_model->get_by_identity($agency, $cl_name, $codelist_version);
		if ($existing) {
			$cid = (int) $existing['id'];
			if ($overwrite) {
				if (!$dry_run) {
					$this->CI->Codelists_model->delete_all_items_for_codelist($cid);
					$this->_insert_codelist_items($cid, $items);
				}
				$summary['codelists_updated'][] = array(
					'id' => $cid,
					'idno' => $existing['idno'],
					'name' => $cl_name,
					'item_count' => count($items),
				);
			} else {
				if (count($items) > 0) {
					$summary['warnings'][] = array(
						'message' => "Codelist '{$cl_name}' already exists; linked without updating items (set overwrite=true to replace codes).",
						'codelist_id' => $cid,
					);
				}
				$summary['codelists_reused'][] = array('id' => $cid, 'idno' => $existing['idno'], 'name' => $cl_name);
			}
			return $cid;
		}

		if ($dry_run) {
			$summary['codelists_created'][] = array(
				'id' => null,
				'idno' => Codelists_model::make_idno($agency, $cl_name, $codelist_version),
				'name' => $cl_name,
				'title' => $cl_title,
				'item_count' => count($items),
			);
			return null;
		}

		$payload = array(
			'name' => $cl_name,
			'title' => $cl_title,
			'agency' => $agency,
			'version' => $codelist_version,
			'items' => $items,
		);
		$r = $this->CI->Codelists_model->import_json_codelist($payload, array(
			'replace_existing' => false,
		));
		if (empty($r['ok']) || empty($r['id'])) {
			throw new Exception(isset($r['message']) ? $r['message'] : 'Failed to create codelist.');
		}
		$new_id = (int) $r['id'];
		$row = $this->CI->Codelists_model->get_by_id($new_id);
		$summary['codelists_created'][] = array(
			'id' => $new_id,
			'idno' => $row ? $row['idno'] : null,
			'name' => $cl_name,
			'title' => $cl_title,
			'item_count' => count($items),
		);
		return $new_id;
	}

	/**
	 * @param int $codelist_id
	 * @param array $items
	 */
	protected function _insert_codelist_items($codelist_id, array $items)
	{
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$code = isset($item['code']) ? trim((string) $item['code']) : '';
			if ($code === '') {
				continue;
			}
			$label = '';
			if (isset($item['label']) && $item['label'] !== '' && $item['label'] !== null) {
				$label = is_string($item['label']) ? trim((string) $item['label']) : (string) $item['label'];
			} elseif (isset($item['title']) && $item['title'] !== '') {
				$label = trim((string) $item['title']);
			}
			$item_id = $this->CI->Codelists_model->add_code((int) $codelist_id, array(
				'code' => $code,
				'sort_order' => 0,
				'parent_id' => null,
			));
			if ($item_id && $label !== '') {
				$this->CI->Codelists_model->set_code_label((int) $item_id, 'en', $label, null);
			}
		}
	}
}
