<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Import global DSD + codelists from JSON (data-structure-schema.json shape).
 */
class Data_structure_json_import {

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
	 * @param array $payload { data_structure: array, overwrite?: bool, dry_run?: bool }
	 * @param array $options overwrite (bool), dry_run (bool), user_id (int|null) for component audit fields
	 * @return array
	 * @throws Exception
	 */
	public function import_from_array(array $payload, array $options = [])
	{
		$overwrite = !empty($options['overwrite']);
		$dryRun    = !empty($options['dry_run']);

		$errors = $this->_validate_payload($payload, $overwrite);
		if (!empty($errors)) {
			throw new Exception('VALIDATION_FAILED: ' . json_encode($errors));
		}

		$dsFull = $payload['data_structure'];
		$components = isset($dsFull['components']) && is_array($dsFull['components']) ? $dsFull['components'] : [];
		$structure = $dsFull;
		unset($structure['components'], $structure['metadata']);

		$summary = [
			'dry_run'            => $dryRun,
			'data_structure'     => null,
			'components_created' => [],
			'codelists_created'  => [],
			'codelists_reused'   => [],
			'codelists_updated'  => [],
			'warnings'           => [],
		];

		if ($dryRun) {
			$summary['data_structure'] = $this->_preview_structure_row($structure);
			$summary['components_preview'] = count($components);
			return $summary;
		}

		$this->CI->db->trans_begin();
		try {
			$dsId = $this->CI->Data_structure_model->create_structure($structure);

			foreach ($components as $idx => $comp) {
				$codelistId = $this->_resolve_codelist_for_component($comp, $overwrite, $summary);

				$insert = $this->_component_to_create_row($comp, $codelistId);
				if (!empty($options['user_id'])) {
					$insert['created_by'] = (int) $options['user_id'];
					$insert['updated_by'] = (int) $options['user_id'];
				}
				$newCompId = $this->CI->Data_structure_component_model->create_component($dsId, $insert);
				$summary['components_created'][] = [
					'id'   => $newCompId,
					'name' => $insert['name'],
				];
			}

			if ($this->CI->db->trans_status() === false) {
				throw new Exception('Database transaction failed.');
			}
			$this->CI->db->trans_commit();

			$summary['data_structure'] = $this->CI->Data_structure_model->get_structure_by_id($dsId, true);
			return $summary;
		} catch (Exception $e) {
			$this->CI->db->trans_rollback();
			throw $e;
		}
	}

	/**
	 * @return array[] error objects
	 */
	protected function _validate_payload(array $payload, $overwrite)
	{
		$errors = [];
		if (empty($payload['data_structure']) || !is_array($payload['data_structure'])) {
			$errors[] = ['path' => 'data_structure', 'message' => 'Required object.'];
			return $errors;
		}
		$ds = $payload['data_structure'];
		if (!isset($ds['components']) || !is_array($ds['components'])) {
			$errors[] = ['path' => 'data_structure.components', 'message' => 'Required array.'];
			return $errors;
		}

		$st = $ds;
		$name = isset($st['name']) ? trim((string) $st['name']) : '';
		if ($name === '') {
			$errors[] = ['path' => 'data_structure.name', 'message' => 'Required.'];
		}
		$idno = isset($st['idno']) ? trim((string) $st['idno']) : '';
		if ($idno === '') {
			$errors[] = ['path' => 'data_structure.idno', 'message' => 'Required for JSON import.'];
		}
		if (array_key_exists('status', $ds) && $ds['status'] !== null && $ds['status'] !== '') {
			if (!Data_structure_model::is_valid_status_slug($ds['status'])) {
				$errors[] = ['path' => 'data_structure.status', 'message' => 'Invalid status; use draft, review, published, deprecated, or archived.'];
			}
		}

		$agency  = isset($st['agency']) && trim((string) $st['agency']) !== '' ? trim((string) $st['agency']) : Data_structure_model::DEFAULT_AGENCY;
		$version = isset($st['version']) && trim((string) $st['version']) !== '' ? trim((string) $st['version']) : Data_structure_model::DEFAULT_VERSION;

		if ($name !== '' && $this->CI->Data_structure_model->get_structure_by_identity($name, $agency, $version)) {
			$errors[] = ['path' => 'data_structure', 'message' => "Data structure already exists for agency '{$agency}', name '{$name}', version '{$version}'."];
		}
		if ($idno !== '' && $this->CI->Data_structure_model->get_structure_by_idno($idno)) {
			$errors[] = ['path' => 'data_structure.idno', 'message' => "idno '{$idno}' already exists."];
		}

		$names = [];
		foreach ($ds['components'] as $idx => $row) {
			if (!is_array($row)) {
				$errors[] = ['path' => "data_structure.components[{$idx}]", 'message' => 'Must be an object.'];
				continue;
			}
			$cname = isset($row['name']) ? trim((string) $row['name']) : '';
			if ($cname === '') {
				$errors[] = ['path' => "data_structure.components[{$idx}].name", 'message' => 'Required.'];
			} elseif (isset($names[$cname])) {
				$errors[] = ['path' => "data_structure.components[{$idx}].name", 'message' => 'Duplicate component name in payload.'];
			} else {
				$names[$cname] = true;
			}
			$ct = isset($row['column_type']) ? trim((string) $row['column_type']) : '';
			if ($ct === '' || !in_array($ct, Data_structure_component_model::$allowed_column_types, true)) {
				$errors[] = ['path' => "data_structure.components[{$idx}].column_type", 'message' => 'Invalid or missing column_type.'];
			}
			if (isset($row['data_type']) && $row['data_type'] !== null && trim((string) $row['data_type']) !== '') {
				$dt = trim((string) $row['data_type']);
				if (!in_array($dt, Data_structure_component_model::$allowed_data_types, true)) {
					$errors[] = ['path' => "data_structure.components[{$idx}].data_type", 'message' => 'Invalid data_type.'];
				}
			}

			$hasRef = !empty($row['codelist_reference']) && is_array($row['codelist_reference']);
			$refIdno = $hasRef && isset($row['codelist_reference']['idno']) ? trim((string) $row['codelist_reference']['idno']) : '';
			$cl = (!$hasRef || $refIdno === '') && isset($row['codelist']) && is_array($row['codelist']) ? $row['codelist'] : null;
			if ($hasRef && $refIdno !== '') {
				$cl = null;
			}
			$clIdno = $cl !== null && isset($cl['idno']) ? trim((string) $cl['idno']) : '';
			$clName = $cl !== null && isset($cl['name']) ? trim((string) $cl['name']) : '';
			$clItems = $cl !== null && isset($cl['items']) && is_array($cl['items']) ? $cl['items'] : [];

			$needs = in_array($ct, ['dimension', 'geography'], true);
			if ($needs) {
				if ($hasRef) {
					if ($refIdno === '') {
						$errors[] = ['path' => "data_structure.components[{$idx}].codelist_reference.idno", 'message' => 'Required when codelist_reference is set.'];
					} else {
						$existingRef = $this->CI->Codelists_model->get_by_idno($refIdno);
						if (!$existingRef) {
							$errors[] = ['path' => "data_structure.components[{$idx}].codelist_reference.idno", 'message' => "No catalogue codelist found for idno '{$refIdno}'."];
						}
					}
				} elseif ($cl === null) {
					$errors[] = ['path' => "data_structure.components[{$idx}]", 'message' => 'dimension/geography requires codelist_reference or codelist.'];
				} else {
					$existingByIdno = $clIdno !== '' ? $this->CI->Codelists_model->get_by_idno($clIdno) : null;
					if ($existingByIdno) {
						if (!$overwrite && count($clItems) > 0) {
							$errors[] = ['path' => "data_structure.components[{$idx}].codelist.items", 'message' => 'Cannot supply codelist.items for an existing codelist unless overwrite is true.'];
						}
					} else {
						if ($clName === '') {
							$errors[] = ['path' => "data_structure.components[{$idx}].codelist.name", 'message' => 'Required to create a new codelist.'];
						}
					}

					foreach ($clItems as $j => $item) {
						if (!is_array($item)) {
							$errors[] = ['path' => "data_structure.components[{$idx}].codelist.items[{$j}]", 'message' => 'Must be an object.'];
							continue;
						}
						$code = isset($item['code']) ? trim((string) $item['code']) : '';
						if ($code === '') {
							$errors[] = ['path' => "data_structure.components[{$idx}].codelist.items[{$j}].code", 'message' => 'Required.'];
						} elseif (strlen($code) > 64) {
							$errors[] = ['path' => "data_structure.components[{$idx}].codelist.items[{$j}].code", 'message' => 'Code exceeds 64 characters.'];
						}
					}
				}
			}
		}

		return $errors;
	}

	protected function _preview_structure_row(array $structure)
	{
		$agency  = isset($structure['agency']) && trim((string) $structure['agency']) !== '' ? trim((string) $structure['agency']) : Data_structure_model::DEFAULT_AGENCY;
		$version = isset($structure['version']) && trim((string) $structure['version']) !== '' ? trim((string) $structure['version']) : Data_structure_model::DEFAULT_VERSION;
		$name    = isset($structure['name']) ? trim((string) $structure['name']) : '';
		$idno    = isset($structure['idno']) ? trim((string) $structure['idno']) : '';
		if ($idno === '') {
			$idno = Data_structure_model::make_idno($agency, $name, $version);
		}
		return [
			'name'    => $name,
			'agency'  => $agency,
			'version' => $version,
			'idno'    => $idno,
		];
	}

	/**
	 * @param array $comp payload component
	 * @param bool  $overwrite
	 * @param array $summary mutated
	 * @return int|null codelist_id
	 * @throws Exception
	 */
	protected function _resolve_codelist_for_component(array $comp, $overwrite, array &$summary)
	{
		$ct = isset($comp['column_type']) ? trim((string) $comp['column_type']) : '';
		$needs = in_array($ct, ['dimension', 'geography'], true);
		if (!$needs) {
			return null;
		}

		$hasRef = !empty($comp['codelist_reference']) && is_array($comp['codelist_reference']);
		$refIdno = $hasRef && isset($comp['codelist_reference']['idno']) ? trim((string) $comp['codelist_reference']['idno']) : '';

		if ($hasRef && $refIdno !== '') {
			$existing = $this->CI->Codelists_model->get_by_idno($refIdno);
			if (!$existing) {
				throw new Exception("codelist_reference.idno '{$refIdno}' does not match any catalogue codelist.");
			}
			$cid = (int) $existing['id'];
			$summary['codelists_reused'][] = ['id' => $cid, 'idno' => $existing['idno']];
			return $cid;
		}

		$cl = isset($comp['codelist']) && is_array($comp['codelist']) ? $comp['codelist'] : [];
		$clIdno  = isset($cl['idno']) ? trim((string) $cl['idno']) : '';
		$clName  = isset($cl['name']) ? trim((string) $cl['name']) : '';
		$items   = isset($cl['items']) && is_array($cl['items']) ? $cl['items'] : [];
		$agency  = isset($cl['agency']) && trim((string) $cl['agency']) !== '' ? trim((string) $cl['agency']) : Codelists_model::NADA_DEFAULT_AGENCY;
		$version = isset($cl['version']) && trim((string) $cl['version']) !== '' ? trim((string) $cl['version']) : Codelists_model::NADA_DEFAULT_VERSION;

		$existing = null;
		if ($clIdno !== '') {
			$existing = $this->CI->Codelists_model->get_by_idno($clIdno);
		}
		if (!$existing && $clName !== '') {
			$existing = $this->CI->Codelists_model->get_by_identity($agency, $clName, $version);
		}

		if ($existing) {
			$cid = (int) $existing['id'];
			if ($overwrite && count($items) > 0) {
				$this->CI->Codelists_model->delete_all_items_for_codelist($cid);
				$this->_insert_code_list_items($cid, $items);
				$summary['codelists_updated'][] = ['id' => $cid, 'idno' => $existing['idno']];
			} else {
				if (count($items) > 0 && !$overwrite) {
					$summary['warnings'][] = [
						'message'     => 'Codelist already exists; items not applied (set overwrite=true or omit items).',
						'codelist_id' => $cid,
					];
				}
				$summary['codelists_reused'][] = ['id' => $cid, 'idno' => $existing['idno']];
			}
			return $cid;
		}

		if ($clName === '') {
			throw new Exception('codelist.name is required to create a new codelist.');
		}

		$title = isset($cl['title']) && trim((string) $cl['title']) !== ''
			? trim((string) $cl['title'])
			: (isset($cl['label']) && trim((string) $cl['label']) !== '' ? trim((string) $cl['label']) : $clName);

		$payload = array(
			'name'        => $clName,
			'title'       => $title,
			'agency'      => $agency,
			'version'     => $version,
			'description' => isset($cl['description']) ? trim((string) $cl['description']) : null,
			'items'       => $items,
		);
		if ($clIdno !== '') {
			$payload['idno'] = $clIdno;
		}

		$r = $this->CI->Codelists_model->import_json_codelist($payload, array(
			'replace_existing' => $overwrite && count($items) > 0,
		));
		if (empty($r['ok']) || empty($r['id'])) {
			throw new Exception(isset($r['message']) ? $r['message'] : 'Failed to create codelist');
		}
		$newId = (int) $r['id'];
		$row = $this->CI->Codelists_model->get_by_id($newId);
		$summary['codelists_created'][] = ['id' => $newId, 'idno' => $row ? $row['idno'] : null];
		return $newId;
	}

	protected function _insert_code_list_items($codelistId, array $items)
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
				$label = is_string($item['label']) ? trim($item['label']) : (string) $item['label'];
			} elseif (isset($item['title']) && $item['title'] !== '') {
				$label = trim((string) $item['title']);
			}
			$sortOrder = isset($item['sort_order']) ? (int) $item['sort_order'] : 0;
			$parentId  = null;
			if (isset($item['parent_id']) && $item['parent_id'] !== '' && $item['parent_id'] !== null && (int) $item['parent_id'] > 0) {
				$parentId = (int) $item['parent_id'];
			}
			$itemId = $this->CI->Codelists_model->add_code((int) $codelistId, array(
				'code'       => $code,
				'sort_order' => $sortOrder,
				'parent_id'  => $parentId,
			));
			if ($itemId && $label !== '') {
				$this->CI->Codelists_model->set_code_label((int) $itemId, 'en', $label, null);
			}
		}
	}

	/**
	 * Strip import-only keys; fold SDMX-style hints into the component metadata column.
	 *
	 * @param int|null $codelistId
	 * @return array
	 */
	protected function _component_to_create_row(array $comp, $codelistId)
	{
		$meta = [];
		if (!empty($comp['paired_time_column'])) {
			$meta['paired_time_column'] = trim((string) $comp['paired_time_column']);
		}
		if (!empty($comp['value_label_column'])) {
			$meta['value_label_column'] = trim((string) $comp['value_label_column']);
		}
		if (!empty($comp['attachment_level'])) {
			$meta['attachment_level'] = trim((string) $comp['attachment_level']);
		}
		if (!empty($comp['assignment_status'])) {
			$meta['assignment_status'] = trim((string) $comp['assignment_status']);
		}

		$row = [
			'name'               => trim((string) $comp['name']),
			'label'               => isset($comp['label']) ? trim((string) $comp['label']) : null,
			'description'         => isset($comp['description']) ? trim((string) $comp['description']) : null,
			'data_type'           => isset($comp['data_type']) && trim((string) $comp['data_type']) !== '' ? trim((string) $comp['data_type']) : null,
			'column_type'         => trim((string) $comp['column_type']),
			'sort_order'          => isset($comp['sort_order']) ? (int) $comp['sort_order'] : 0,
			'codelist_id'         => $codelistId !== null && $codelistId > 0 ? (int) $codelistId : null,
			'metadata'            => !empty($meta) ? $meta : null,
		];
		if ($row['label'] === '') {
			$row['label'] = null;
		}
		if ($row['description'] === '') {
			$row['description'] = null;
		}

		return $row;
	}

	/**
	 * True when import would create or update global codelists (inline binding), not reference-only.
	 *
	 * @param array $payload import_json body
	 * @param bool  $overwrite
	 * @return bool
	 */
	public static function payload_mutates_codelists(array $payload, $overwrite = false)
	{
		$overwrite = !empty($overwrite);
		if (empty($payload['data_structure']['components']) || !is_array($payload['data_structure']['components'])) {
			return false;
		}

		$ci =& get_instance();
		$ci->load->model('Codelists_model');

		foreach ($payload['data_structure']['components'] as $comp) {
			if (!is_array($comp)) {
				continue;
			}
			$hasRef = !empty($comp['codelist_reference']) && is_array($comp['codelist_reference']);
			$refIdno = $hasRef && isset($comp['codelist_reference']['idno'])
				? trim((string) $comp['codelist_reference']['idno'])
				: '';
			if ($hasRef && $refIdno !== '') {
				continue;
			}
			if (empty($comp['codelist']) || !is_array($comp['codelist'])) {
				continue;
			}
			$cl = $comp['codelist'];
			$clIdno = isset($cl['idno']) ? trim((string) $cl['idno']) : '';
			$clName = isset($cl['name']) ? trim((string) $cl['name']) : '';
			$clItems = isset($cl['items']) && is_array($cl['items']) ? $cl['items'] : array();
			if ($clIdno === '' && $clName === '' && count($clItems) === 0) {
				continue;
			}
			$existing = $clIdno !== '' ? $ci->Codelists_model->get_by_idno($clIdno) : null;
			if (!$existing) {
				return true;
			}
			if ($overwrite && count($clItems) > 0) {
				return true;
			}
		}

		return false;
	}
}
