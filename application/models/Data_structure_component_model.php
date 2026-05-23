<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Data_structure_component_model
 *
 * DSD column definitions for data_structures rows. Aligns with metadata editor
 * indicator_dsd (minus sid, sum_stats, inline/local codelists). Codelist binding
 * is only via codelist_id -> codelists.id.
 */
class Data_structure_component_model extends CI_Model {

	/** Allowed data_type values (matches DB enum). */
	public static $allowed_data_types = ['string', 'integer', 'float', 'double', 'date', 'boolean'];

	/** Allowed column_type values (matches DB enum). */
	public static $allowed_column_types = [
		'dimension',
		'time_period',
		'measure',
		'attribute',
		'indicator_id',
		'indicator_name',
		'annotation',
		'geography',
		'observation_value',
		'periodicity',
	];

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @param int $structure_id
	 * @return array
	 */
	public function get_components_by_structure_id($structure_id)
	{
		$structure_id = (int) $structure_id;
		$this->db->order_by('sort_order', 'ASC');
		$this->db->order_by('id', 'ASC');
		$_r = $this->db->get_where('data_structure_components', ['data_structure_id' => $structure_id]);
		return $_r ? $_r->result_array() : [];
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public function get_component_by_id($id)
	{
		$result = $this->db->get_where('data_structure_components', ['id' => (int) $id]);
		if (!$result) {
			return null;
		}
		$row = $result->row_array();
		return $row ?: null;
	}

	/**
	 * Find a component in a structure by name (case-insensitive).
	 *
	 * @param int      $structure_id
	 * @param string   $name
	 * @param int|null $exclude_component_id
	 * @return array|null
	 */
	/**
	 * Paginated global component catalogue (joined with parent data structure).
	 *
	 * @param array $options {
	 *     @var int         $page
	 *     @var int         $per_page
	 *     @var string      $search         LIKE on component name/label/description and structure fields
	 *     @var string      $name           Prefix match on component name (case-insensitive)
	 *     @var string      $column_type
	 *     @var string      $agency         Parent structure agency
	 *     @var int|null    $structure_id
	 *     @var int|null    $exclude_structure_id
	 *     @var int|null    $status         Parent structure status code
	 *     @var bool        $has_codelist
	 *     @var string      $order_by       name|structure_title|updated
	 *     @var string      $order_dir      ASC|DESC
	 * }
	 * @return array{ rows: array, total: int, page: int, per_page: int }
	 */
	public function search_catalog_paged(array $options = [])
	{
		$page = isset($options['page']) ? max(1, (int) $options['page']) : 1;
		$perPage = isset($options['per_page']) ? (int) $options['per_page'] : 50;
		if ($perPage < 1) {
			$perPage = 50;
		}
		if ($perPage > 200) {
			$perPage = 200;
		}
		$offset = ($page - 1) * $perPage;

		$this->db->from('data_structure_components c');
		$this->db->join('data_structures ds', 'ds.id = c.data_structure_id', 'inner');
		$this->_apply_component_catalog_filters($options);
		$total = (int) $this->db->count_all_results();

		$this->db->select(
			'c.id, c.data_structure_id, c.sort_order, c.name, c.label, c.description, c.data_type, c.column_type, c.codelist_id, c.metadata, c.updated,'
			. ' ds.agency AS structure_agency, ds.name AS structure_name, ds.version AS structure_version,'
			. ' ds.title AS structure_title, ds.idno AS structure_idno, ds.status AS structure_status',
			false
		);
		$this->db->from('data_structure_components c');
		$this->db->join('data_structures ds', 'ds.id = c.data_structure_id', 'inner');
		$this->_apply_component_catalog_filters($options);
		$this->_apply_component_catalog_order($options);
		$this->db->limit($perPage, $offset);
		$_r = $this->db->get();
		$rows = $_r ? $_r->result_array() : [];

		return [
			'rows'     => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $perPage,
		];
	}

	/**
	 * @param array $options
	 */
	private function _apply_component_catalog_filters(array $options)
	{
		$search = isset($options['search']) ? trim((string) $options['search']) : '';
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('c.name', $search);
			$this->db->or_like('c.label', $search);
			$this->db->or_like('c.description', $search);
			$this->db->or_like('ds.title', $search);
			$this->db->or_like('ds.name', $search);
			$this->db->or_like('ds.agency', $search);
			$this->db->or_like('ds.idno', $search);
			$this->db->or_like('ds.version', $search);
			if (ctype_digit($search)) {
				$this->db->or_where('c.id', (int) $search);
				$this->db->or_where('c.data_structure_id', (int) $search);
			}
			$this->db->group_end();
		}

		$name = isset($options['name']) ? trim((string) $options['name']) : '';
		if ($name !== '') {
			$this->db->like('c.name', $name, 'after');
		}

		$columnType = isset($options['column_type']) ? trim((string) $options['column_type']) : '';
		if ($columnType !== '') {
			$this->_assert_column_type($columnType);
			$this->db->where('c.column_type', $columnType);
		}

		$agency = isset($options['agency']) ? trim((string) $options['agency']) : '';
		if ($agency !== '') {
			$this->db->where('ds.agency', $agency);
		}

		if (!empty($options['structure_id'])) {
			$this->db->where('c.data_structure_id', (int) $options['structure_id']);
		}

		if (!empty($options['exclude_structure_id'])) {
			$this->db->where('c.data_structure_id <>', (int) $options['exclude_structure_id']);
		}

		if (array_key_exists('status', $options) && $options['status'] !== null && $options['status'] !== '') {
			$this->db->where('ds.status', (int) $options['status']);
		}

		if (!empty($options['has_codelist'])) {
			$this->db->where('c.codelist_id IS NOT NULL', null, false);
			$this->db->where('c.codelist_id >', 0);
		}
	}

	/**
	 * @param array $options
	 */
	private function _apply_component_catalog_order(array $options)
	{
		$orderBy = isset($options['order_by']) ? strtolower(trim((string) $options['order_by'])) : 'name';
		$orderDir = isset($options['order_dir']) ? strtoupper(trim((string) $options['order_dir'])) : 'ASC';
		if ($orderDir !== 'DESC') {
			$orderDir = 'ASC';
		}

		switch ($orderBy) {
			case 'structure_title':
				$this->db->order_by('ds.title', $orderDir);
				$this->db->order_by('c.name', 'ASC');
				break;
			case 'updated':
				$this->db->order_by('c.updated', $orderDir);
				$this->db->order_by('c.name', 'ASC');
				break;
			case 'name':
			default:
				$this->db->order_by('c.name', $orderDir);
				$this->db->order_by('ds.agency', 'ASC');
				$this->db->order_by('ds.name', 'ASC');
				$this->db->order_by('ds.version_seq', 'ASC');
				break;
		}
	}

	public function find_by_name_in_structure($structure_id, $name, $exclude_component_id = null)
	{
		$name = trim((string) $name);
		if ($name === '') {
			return null;
		}
		$this->db->where('data_structure_id', (int) $structure_id);
		$this->db->where('LOWER(name)', strtolower($name));
		if ($exclude_component_id !== null && (int) $exclude_component_id > 0) {
			$this->db->where('id <>', (int) $exclude_component_id);
		}
		$result = $this->db->get('data_structure_components', 1);
		if (!$result) {
			return null;
		}
		$row = $result->row_array();
		return $row ?: null;
	}

	/**
	 * @param int   $structure_id
	 * @param array $data
	 * @return int New component id
	 * @throws Exception
	 */
	public function create_component($structure_id, $data)
	{
		$structure_id = (int) $structure_id;
		if ($structure_id <= 0) {
			throw new Exception('Data structure id is required.');
		}
		$this->load->model('Data_structure_model');
		$structure = $this->Data_structure_model->get_structure_by_id($structure_id, false);
		if (!$structure) {
			throw new Exception('Data structure not found.');
		}
		if (Data_structure_model::is_locked_status((int) $structure['status'])) {
			throw new Exception('Cannot add components to a locked data structure (published/archived).');
		}

		$name = isset($data['name']) ? trim((string) $data['name']) : '';
		if ($name === '') {
			throw new Exception('Component name is required.');
		}
		if ($this->find_by_name_in_structure($structure_id, $name)) {
			throw new Exception('Component name already exists in this data structure.');
		}

		$column_type = isset($data['column_type']) ? trim((string) $data['column_type']) : '';
		$this->_assert_column_type($column_type);

		$data_type = isset($data['data_type']) ? trim((string) $data['data_type']) : null;
		if ($data_type !== null && $data_type !== '') {
			$this->_assert_data_type($data_type);
		} else {
			$data_type = null;
		}

		$codelist_id = isset($data['codelist_id']) && $data['codelist_id'] !== '' && $data['codelist_id'] !== null
			? (int) $data['codelist_id'] : null;
		if ($codelist_id !== null && $codelist_id > 0) {
			$this->_assert_codelist_exists($codelist_id);
		} else {
			$codelist_id = null;
		}

		$now = time();
		$insert = [
			'data_structure_id'   => $structure_id,
			'sort_order'          => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
			'name'                => $name,
			'label'               => isset($data['label']) ? trim((string) $data['label']) : null,
			'description'       => isset($data['description']) ? trim((string) $data['description']) : null,
			'data_type'           => $data_type,
			'column_type'         => $column_type,
			'codelist_id'         => $codelist_id,
			'metadata'            => $this->_normalize_metadata_for_db(isset($data['metadata']) ? $data['metadata'] : null),
			'created'             => isset($data['created']) ? (int) $data['created'] : $now,
			'updated'             => isset($data['updated']) ? (int) $data['updated'] : $now,
			'created_by'          => $this->_optional_user_id(isset($data['created_by']) ? $data['created_by'] : null),
			'updated_by'          => $this->_optional_user_id(isset($data['updated_by']) ? $data['updated_by'] : null),
		];
		if ($insert['label'] === '') {
			$insert['label'] = null;
		}
		if ($insert['description'] === '') {
			$insert['description'] = null;
		}
		$this->db->insert('data_structure_components', $insert);
		$newId = (int) $this->db->insert_id();
		return $newId;
	}

	/**
	 * @param int   $component_id
	 * @param array $data
	 * @return bool
	 * @throws Exception
	 */
	public function update_component($component_id, $data)
	{
		$component_id = (int) $component_id;
		$existing = $this->get_component_by_id($component_id);
		if (!$existing) {
			throw new Exception('Component not found.');
		}
		$this->load->model('Data_structure_model');
		$structure = $this->Data_structure_model->get_structure_by_id((int) $existing['data_structure_id'], false);
		if ($structure && Data_structure_model::is_locked_status((int) $structure['status'])) {
			throw new Exception('Cannot edit components of a locked data structure (published/archived).');
		}

		$upd = [];
		if (array_key_exists('name', $data)) {
			$name = trim((string) $data['name']);
			if ($name === '') {
				throw new Exception('Component name cannot be empty.');
			}
			$other = $this->find_by_name_in_structure((int) $existing['data_structure_id'], $name, $component_id);
			if ($other) {
				throw new Exception('Component name already exists in this data structure.');
			}
			$upd['name'] = $name;
		}
		if (array_key_exists('sort_order', $data)) {
			$upd['sort_order'] = (int) $data['sort_order'];
		}
		if (array_key_exists('label', $data)) {
			$upd['label'] = trim((string) $data['label']) ?: null;
		}
		if (array_key_exists('description', $data)) {
			$upd['description'] = trim((string) $data['description']) ?: null;
		}
		if (array_key_exists('data_type', $data)) {
			$v = $data['data_type'];
			if ($v === null || $v === '') {
				$upd['data_type'] = null;
			} else {
				$this->_assert_data_type(trim((string) $v));
				$upd['data_type'] = trim((string) $v);
			}
		}
		if (array_key_exists('column_type', $data)) {
			$ct = trim((string) $data['column_type']);
			$this->_assert_column_type($ct);
			$upd['column_type'] = $ct;
		}
		if (array_key_exists('codelist_id', $data)) {
			$cid = $data['codelist_id'];
			if ($cid === null || $cid === '') {
				$upd['codelist_id'] = null;
			} else {
				$cid = (int) $cid;
				$this->_assert_codelist_exists($cid);
				$upd['codelist_id'] = $cid;
			}
		}
		if (array_key_exists('metadata', $data)) {
			$upd['metadata'] = $this->_normalize_metadata_for_db($data['metadata']);
		}
		if (array_key_exists('updated', $data)) {
			$upd['updated'] = (int) $data['updated'];
		} else {
			$upd['updated'] = time();
		}
		if (array_key_exists('updated_by', $data)) {
			$upd['updated_by'] = $data['updated_by'] === null || $data['updated_by'] === '' ? null : (int) $data['updated_by'];
		}

		if (empty($upd)) {
			return true;
		}
		$this->db->where('id', $component_id);
		$this->db->update('data_structure_components', $upd);
		return true;
	}

	/**
	 * @param int $component_id
	 * @return bool
	 * @throws Exception
	 */
	public function delete_component($component_id)
	{
		$component_id = (int) $component_id;
		$existing = $this->get_component_by_id($component_id);
		if (!$existing) {
			throw new Exception('Component not found.');
		}
		$this->load->model('Data_structure_model');
		$structure = $this->Data_structure_model->get_structure_by_id((int) $existing['data_structure_id'], false);
		if ($structure && Data_structure_model::is_locked_status((int) $structure['status'])) {
			throw new Exception('Cannot delete components of a locked data structure (published/archived).');
		}
		$this->db->where('id', $component_id);
		$this->db->delete('data_structure_components');
		return true;
	}

	/**
	 * @param string $column_type
	 * @throws Exception
	 */
	private function _assert_column_type($column_type)
	{
		if ($column_type === '' || !in_array($column_type, self::$allowed_column_types, true)) {
			throw new Exception('Invalid column_type.');
		}
	}

	/**
	 * @param string $data_type
	 * @throws Exception
	 */
	private function _assert_data_type($data_type)
	{
		if (!in_array($data_type, self::$allowed_data_types, true)) {
			throw new Exception('Invalid data_type.');
		}
	}

	/**
	 * @param int $codelist_id
	 * @throws Exception
	 */
	private function _assert_codelist_exists($codelist_id)
	{
		$this->load->model('Codelists_model');
		if (!$this->Codelists_model->get_by_id($codelist_id)) {
			throw new Exception('Codelist not found.');
		}
	}

	/**
	 * @param mixed $metadata
	 * @return string|null
	 */
	private function _normalize_metadata_for_db($metadata)
	{
		if ($metadata === null || $metadata === '') {
			return null;
		}
		if (is_array($metadata)) {
			return json_encode($metadata);
		}
		return (string) $metadata;
	}

	/**
	 * @param mixed $user_id
	 * @return int|null
	 */
	private function _optional_user_id($user_id)
	{
		if ($user_id === null || $user_id === '') {
			return null;
		}
		return (int) $user_id;
	}
}
