<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Data_structure_model
 *
 * Catalogue-scoped global data structures (DSD). One row per version:
 * canonical identity (agency, name, version), optional idno alias.
 *
 * Components live in Data_structure_component_model.
 */
class Data_structure_model extends CI_Model {

	const DEFAULT_AGENCY  = 'NADA';
	const DEFAULT_VERSION = '1.0.0';
	const VERSION_REGEX   = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9]\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*))*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/';

	const STATUS_DRAFT      = 0;
	const STATUS_REVIEW     = 10;
	const STATUS_PUBLISHED  = 20;
	const STATUS_DEPRECATED = 30;
	const STATUS_ARCHIVED   = 40;

	/** API / JSON slug for each persisted status code (DB remains integer). */
	private static $STATUS_CODE_TO_SLUG = [
		self::STATUS_DRAFT      => 'draft',
		self::STATUS_REVIEW     => 'review',
		self::STATUS_PUBLISHED  => 'published',
		self::STATUS_DEPRECATED => 'deprecated',
		self::STATUS_ARCHIVED  => 'archived',
	];

	/** @var array<string,int> */
	private static $STATUS_SLUG_TO_CODE = [
		'draft'      => self::STATUS_DRAFT,
		'review'     => self::STATUS_REVIEW,
		'published'  => self::STATUS_PUBLISHED,
		'deprecated' => self::STATUS_DEPRECATED,
		'archived'   => self::STATUS_ARCHIVED,
	];

	public static $allowed_statuses = [
		self::STATUS_DRAFT,
		self::STATUS_REVIEW,
		self::STATUS_PUBLISHED,
		self::STATUS_DEPRECATED,
		self::STATUS_ARCHIVED,
	];

	/**
	 * Slugs accepted on API payloads and in JSON Schema (data_structure.status).
	 *
	 * @return string[]
	 */
	public static function allowed_status_slugs()
	{
		return array_keys(self::$STATUS_SLUG_TO_CODE);
	}

	/**
	 * Map DB status code to API slug.
	 *
	 * @param int|string $code
	 * @return string
	 */
	public static function status_code_to_slug($code)
	{
		$c = (int) $code;
		return isset(self::$STATUS_CODE_TO_SLUG[$c])
			? self::$STATUS_CODE_TO_SLUG[$c]
			: 'draft';
	}

	/**
	 * Copy a structure row for JSON responses: replace numeric status with slug.
	 *
	 * @param array $row
	 * @return array
	 */
	public static function encode_row_status_for_api(array $row)
	{
		$out = $row;
		if (array_key_exists('status', $out) && $out['status'] !== null && $out['status'] !== '') {
			$out['status'] = self::status_code_to_slug($out['status']);
		}
		return $out;
	}

	/**
	 * @param array[] $rows
	 * @return array[]
	 */
	public static function encode_rows_status_for_api(array $rows)
	{
		$out = [];
		foreach ($rows as $r) {
			$out[] = is_array($r) ? self::encode_row_status_for_api($r) : $r;
		}
		return $out;
	}

	/**
	 * Parse status query or filter value to a DB code. Accepts slug or legacy numeric string.
	 *
	 * @param mixed $value
	 * @return int|null null when value is empty; null when invalid
	 */
	/**
	 * True when value is a non-empty API status slug (case-insensitive). Used for JSON import / validate payloads.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function is_valid_status_slug($value)
	{
		if (!is_string($value)) {
			return false;
		}
		$s = strtolower(trim($value));
		return $s !== '' && isset(self::$STATUS_SLUG_TO_CODE[$s]);
	}

	public static function decode_status_filter_value($value)
	{
		if ($value === null || $value === false || $value === '') {
			return null;
		}
		if (is_int($value) || is_float($value)) {
			$c = (int) $value;
			return in_array($c, self::$allowed_statuses, true) ? $c : null;
		}
		if (is_string($value)) {
			$s = trim($value);
			if ($s === '') {
				return null;
			}
			$slug = strtolower($s);
			if (isset(self::$STATUS_SLUG_TO_CODE[$slug])) {
				return (int) self::$STATUS_SLUG_TO_CODE[$slug];
			}
			if (is_numeric($s)) {
				$c = (int) $s;
				return in_array($c, self::$allowed_statuses, true) ? $c : null;
			}
		}
		return null;
	}

	/**
	 * @param int $status
	 * @return bool
	 */
	public static function is_locked_status($status)
	{
		$code = (int) $status;
		return $code === self::STATUS_PUBLISHED || $code === self::STATUS_ARCHIVED;
	}

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Deterministic idno: '{agency}_{name}_{version}' (same convention as codelists).
	 */
	public static function make_idno($agency, $name, $version)
	{
		$agency  = trim((string) $agency)  !== '' ? trim((string) $agency)  : self::DEFAULT_AGENCY;
		$version = trim((string) $version) !== '' ? trim((string) $version) : self::DEFAULT_VERSION;
		$name    = trim((string) $name);
		return $agency . '_' . $name . '_' . $version;
	}

	/**
	 * Purely numeric idnos are disallowed so GET .../data_structures/{segment} can treat digit-only
	 * segments as primary keys without ambiguity.
	 *
	 * @param string $idno Trimmed or untrimmed idno
	 * @throws Exception
	 */
	public static function assert_idno_not_purely_numeric($idno)
	{
		$idno = trim((string) $idno);
		if ($idno !== '' && ctype_digit($idno)) {
			throw new Exception(
				'Data structure idno must not be a purely numeric string (reserved for id-based API paths).'
			);
		}
	}

	/**
	 * @param int  $id
	 * @param bool $with_components When true, adds `components` array from Data_structure_component_model.
	 * @return array|null
	 */
	public function get_structure_by_id($id, $with_components = false)
	{
		$result = $this->db->get_where('data_structures', ['id' => (int) $id]);
		if (!$result) {
			return null;
		}
		$row = $result->row_array();
		if (!$row) {
			return null;
		}
		if ($with_components) {
			$this->load->model('Data_structure_component_model');
			$row['components'] = $this->Data_structure_component_model->get_components_by_structure_id((int) $id);
		}
		return $row;
	}

	/**
	 * @param string      $name
	 * @param string|null $agency  Defaults to NADA.
	 * @param string|null $version Defaults to 1.0.
	 * @return array|null
	 */
	public function get_structure_by_identity($name, $agency = null, $version = null)
	{
		$agency = ($agency === null || $agency === '') ? self::DEFAULT_AGENCY : trim((string) $agency);
		$name   = trim((string) $name);
		if ($name === '') {
			return null;
		}
		$version = $version === null ? null : $this->_normalize_semver_input(trim((string) $version));
		if ($version === '') {
			$this->db->where(['name' => $name, 'agency' => $agency]);
			$this->db->order_by('version_seq', 'DESC');
			$this->db->order_by('id', 'DESC');
			$this->db->limit(1);
			$result = $this->db->get('data_structures');
		} else {
			$result = $this->db->get_where('data_structures', [
				'name'    => $name,
				'agency'  => $agency,
				'version' => $version,
			]);
		}
		if (!$result) {
			return null;
		}
		$row = $result->row_array();
		return $row ?: null;
	}

	/**
	 * @param string $idno
	 * @return array|null
	 */
	public function get_structure_by_idno($idno)
	{
		$idno = trim((string) $idno);
		if ($idno === '') {
			return null;
		}
		$result = $this->db->get_where('data_structures', ['idno' => $idno]);
		if (!$result) {
			return null;
		}
		$row = $result->row_array();
		return $row ?: null;
	}

	/**
	 * All version rows for one maintainable (agency + name).
	 *
	 * @param string      $name
	 * @param string|null $agency
	 * @return array
	 */
	public function get_structure_versions($name, $agency = null)
	{
		$agency = ($agency === null || $agency === '') ? self::DEFAULT_AGENCY : trim((string) $agency);
		$this->db->where(['name' => trim((string) $name), 'agency' => $agency]);
		$this->db->order_by('version_seq', 'ASC');
		$this->db->order_by('id', 'ASC');
		$_r = $this->db->get('data_structures');
		return $_r ? $_r->result_array() : [];
	}

	/**
	 * @return array
	 */
	public function get_all_structures()
	{
		$this->db->select(
			"data_structures.*, (SELECT COUNT(*) FROM editor_project_dsd ep WHERE ep.data_structure_id = data_structures.id) AS projects_count",
			false
		);
		$this->db->order_by('agency', 'ASC');
		$this->db->order_by('name', 'ASC');
		$this->db->order_by('version_seq', 'ASC');
		$this->db->order_by('id', 'ASC');
		$_r = $this->db->get('data_structures');
		return $_r ? $_r->result_array() : [];
	}

	/**
	 * One row per DSD family (latest row where id=pid), with versions_count.
	 *
	 * @return array
	 */
	public function get_all_structures_collapsed()
	{
		$sql = "
			SELECT ds.*,
				(SELECT COUNT(*) FROM data_structures v WHERE v.pid = ds.id) AS versions_count,
				(
					SELECT COUNT(*)
					FROM editor_project_dsd ep
					INNER JOIN data_structures v2 ON v2.id = ep.data_structure_id
					WHERE v2.pid = ds.id
				) AS projects_count
			FROM data_structures ds
			WHERE ds.id = ds.pid
			ORDER BY ds.agency ASC, ds.name ASC
		";
		$_r = $this->db->query($sql);
		return $_r ? $_r->result_array() : [];
	}

	/**
	 * Apply optional text search and status filter to the active query (data_structures or alias).
	 *
	 * @param string    $alias  Table alias with dot, e.g. 'ds.' or '' for no prefix
	 * @param string    $search Substring match across identity fields
	 * @param int|null  $status Exact status code, or null to ignore
	 */
	private function _apply_structure_catalog_filters($alias, $search, $status)
	{
		$prefix = $alias === '' ? '' : rtrim((string) $alias, '.') . '.';
		$search = trim((string) $search);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like($prefix . 'name', $search);
			$this->db->or_like($prefix . 'title', $search);
			$this->db->or_like($prefix . 'agency', $search);
			$this->db->or_like($prefix . 'idno', $search);
			$this->db->or_like($prefix . 'version', $search);
			$this->db->or_like($prefix . 'description', $search);
			$this->db->or_like($prefix . 'notes', $search);
			if (ctype_digit($search)) {
				$this->db->or_where($prefix . 'id', (int) $search);
			}
			$this->db->group_end();
		}
		if ($status !== null) {
			$this->db->where($prefix . 'status', (int) $status);
		}
	}

	/**
	 * Paginated catalogue list (family rows by default; flat = one row per version).
	 *
	 * @param array $options {
	 *     @var int         $page      1-based page
	 *     @var int         $per_page  clamped 1–200 (default 50)
	 *     @var string      $search    optional LIKE across name, title, agency, idno, version, description, notes; numeric = id
	 *     @var bool        $flat      when true, list all version rows instead of family heads
	 *     @var int|null    $status    optional exact status filter
	 * }
	 * @return array{ rows: array, total: int, page: int, per_page: int }
	 */
	public function get_structures_catalog_paged(array $options = [])
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
		$search = isset($options['search']) ? (string) $options['search'] : '';
		$flat = !empty($options['flat']);
		$status = null;
		if (array_key_exists('status', $options) && $options['status'] !== null && $options['status'] !== '' && $options['status'] !== false) {
			$status = (int) $options['status'];
		}

		if ($flat) {
			return $this->_get_structures_flat_paged($page, $perPage, $offset, $search, $status);
		}

		return $this->_get_structures_collapsed_paged($page, $perPage, $offset, $search, $status);
	}

	/**
	 * @return array{ rows: array, total: int, page: int, per_page: int }
	 */
	private function _get_structures_flat_paged($page, $perPage, $offset, $search, $status)
	{
		$this->db->from('data_structures');
		$this->_apply_structure_catalog_filters('', $search, $status);
		$total = (int) $this->db->count_all_results();

		$this->db->select(
			"data_structures.*, (SELECT COUNT(*) FROM editor_project_dsd ep WHERE ep.data_structure_id = data_structures.id) AS projects_count",
			false
		);
		$this->db->from('data_structures');
		$this->_apply_structure_catalog_filters('', $search, $status);
		$this->db->order_by('agency', 'ASC');
		$this->db->order_by('name', 'ASC');
		$this->db->order_by('version_seq', 'ASC');
		$this->db->order_by('id', 'ASC');
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
	 * @return array{ rows: array, total: int, page: int, per_page: int }
	 */
	private function _get_structures_collapsed_paged($page, $perPage, $offset, $search, $status)
	{
		$this->db->from('data_structures ds');
		$this->db->where('ds.id = ds.pid', null, false);
		$this->_apply_structure_catalog_filters('ds', $search, $status);
		$total = (int) $this->db->count_all_results();

		$this->db->select(
			"ds.*,
			(SELECT COUNT(*) FROM data_structures v WHERE v.pid = ds.id) AS versions_count,
			(
				SELECT COUNT(*)
				FROM editor_project_dsd ep
				INNER JOIN data_structures v2 ON v2.id = ep.data_structure_id
				WHERE v2.pid = ds.id
			) AS projects_count",
			false
		);
		$this->db->from('data_structures ds');
		$this->db->where('ds.id = ds.pid', null, false);
		$this->_apply_structure_catalog_filters('ds', $search, $status);
		$this->db->order_by('ds.agency', 'ASC');
		$this->db->order_by('ds.name', 'ASC');
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
	 * Create a data structure version row.
	 *
	 * @param array $data Keys: name (required), agency, version, idno, status, title, description,
	 *                    notes, content_hash, metadata, created, updated, created_by, updated_by
	 * @return int New id
	 * @throws Exception
	 */
	public function create_structure($data)
	{
		$name = isset($data['name']) ? trim((string) $data['name']) : '';
		if ($name === '') {
			throw new Exception('Data structure name is required.');
		}

		$agency  = isset($data['agency'])  && trim((string) $data['agency'])  !== '' ? trim((string) $data['agency'])  : self::DEFAULT_AGENCY;
		$version = isset($data['version']) && trim((string) $data['version']) !== '' ? trim((string) $data['version']) : self::DEFAULT_VERSION;
		$version = $this->_normalize_semver_input($version);
		$this->_assert_semver($version);

		if ($this->get_structure_by_identity($name, $agency, $version)) {
			throw new Exception("Data structure already exists for agency '{$agency}', name '{$name}', version '{$version}'.");
		}

		$idno = isset($data['idno']) ? trim((string) $data['idno']) : '';
		if ($idno === '') {
			$idno = self::make_idno($agency, $name, $version);
		}
		self::assert_idno_not_purely_numeric($idno);
		if ($this->get_structure_by_idno($idno)) {
			throw new Exception("Data structure idno '{$idno}' already exists.");
		}

		$status = $this->_normalize_status(isset($data['status']) ? $data['status'] : null);
		$version_seq = array_key_exists('version_seq', $data)
			? (int) $data['version_seq']
			: $this->get_next_version_seq($agency, $name);
		if ($version_seq <= 0) {
			throw new Exception('version_seq must be a positive integer.');
		}
		$_existing_seq = $this->db->get_where('data_structures', [
			'agency'      => $agency,
			'name'        => $name,
			'version_seq' => $version_seq,
		]);
		if ($_existing_seq && $_existing_seq->row_array()) {
			throw new Exception("Data structure already exists for agency '{$agency}', name '{$name}', version_seq '{$version_seq}'.");
		}

		$now = time();
		$insert = [
			'pid'           => null,
			'agency'        => $agency,
			'name'          => $name,
			'version'       => $version,
			'version_seq'   => $version_seq,
			'idno'          => $idno,
			'status'        => $status,
			'title'         => isset($data['title']) ? trim((string) $data['title']) : null,
			'description'   => isset($data['description']) ? trim((string) $data['description']) : null,
			'notes'         => isset($data['notes']) ? trim((string) $data['notes']) : null,
			'content_hash'  => isset($data['content_hash']) ? trim((string) $data['content_hash']) : null,
			'metadata'      => $this->_normalize_metadata_for_db(isset($data['metadata']) ? $data['metadata'] : null),
			'created'       => isset($data['created']) ? (int) $data['created'] : $now,
			'updated'       => isset($data['updated']) ? (int) $data['updated'] : $now,
			'created_by'    => $this->_optional_user_id(isset($data['created_by']) ? $data['created_by'] : null),
			'updated_by'    => $this->_optional_user_id(isset($data['updated_by']) ? $data['updated_by'] : null),
		];
		if ($insert['description'] === '') {
			$insert['description'] = null;
		}
		if ($insert['title'] === '') {
			$insert['title'] = null;
		}
		if ($insert['notes'] === '') {
			$insert['notes'] = null;
		}
		if ($insert['content_hash'] === '') {
			$insert['content_hash'] = null;
		}

		$this->db->trans_begin();
		$this->db->insert('data_structures', $insert);
		$new_id = (int) $this->db->insert_id();
		// PID strategy: newest version id becomes family pointer for all versions.
		$this->db->where(['agency' => $agency, 'name' => $name]);
		$this->db->update('data_structures', ['pid' => $new_id]);
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			throw new Exception('Failed to create data structure.');
		}
		$this->db->trans_commit();
		return $new_id;
	}

	/**
	 * Apply agency/name/version changes for draft rows (mutates $upd and may update family rows).
	 *
	 * @param int   $id
	 * @param array $existing
	 * @param array $data
	 * @param array $upd
	 * @return void
	 * @throws Exception
	 */
	private function _apply_draft_identity_update($id, array $existing, array $data, array &$upd)
	{
		$old_agency = (string) $existing['agency'];
		$old_name   = (string) $existing['name'];
		$agency = array_key_exists('agency', $data)
			? (trim((string) $data['agency']) !== '' ? trim((string) $data['agency']) : self::DEFAULT_AGENCY)
			: $old_agency;
		$name = array_key_exists('name', $data)
			? trim((string) $data['name'])
			: $old_name;
		if ($name === '') {
			throw new Exception('Data structure name is required.');
		}
		$version = array_key_exists('version', $data)
			? (trim((string) $data['version']) !== '' ? trim((string) $data['version']) : self::DEFAULT_VERSION)
			: (string) $existing['version'];
		$version = $this->_normalize_semver_input($version);
		$this->_assert_semver($version);

		$identity_changed = ($agency !== $old_agency || $name !== $old_name);
		$version_changed = ($version !== (string) $existing['version']);

		if (!$identity_changed && !$version_changed) {
			return;
		}

		if ($identity_changed) {
			$dup = $this->db->select('id')
				->from('data_structures')
				->where('agency', $agency)
				->where('name', $name)
				->where('version', $version)
				->where('id <>', (int) $id)
				->get();
			$dup = $this->_require_query_result($dup, '_apply_draft_identity_update');
			if ($dup->row_array()) {
				throw new Exception("Data structure already exists for agency '{$agency}', name '{$name}', version '{$version}'.");
			}
			$this->db->where('agency', $old_agency);
			$this->db->where('name', $old_name);
			$this->db->update('data_structures', [
				'agency' => $agency,
				'name'   => $name,
			]);
			$upd['agency'] = $agency;
			$upd['name'] = $name;
		}

		if ($version_changed) {
			$check_agency = $identity_changed ? $agency : $old_agency;
			$check_name   = $identity_changed ? $name : $old_name;
			$other = $this->get_structure_by_identity($check_name, $check_agency, $version);
			if ($other && (int) $other['id'] !== (int) $id) {
				throw new Exception("Data structure already exists for agency '{$check_agency}', name '{$check_name}', version '{$version}'.");
			}
			$upd['version'] = $version;
		}
	}

	/**
	 * Update mutable fields. Agency, name, and version may change only while status is draft.
	 *
	 * Supported keys: status, title, description, notes, content_hash, metadata, idno,
	 *                 agency, name, version (draft only), updated, updated_by
	 * Empty string for idno regenerates from agency/name/version.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 * @throws Exception
	 */
	public function update_structure($id, $data)
	{
		$id = (int) $id;
		$existing = $this->get_structure_by_id($id, false);
		if (!$existing) {
			throw new Exception('Data structure not found.');
		}
		if (self::is_locked_status((int) $existing['status'])) {
			throw new Exception('Locked data structures (published/archived) cannot be edited.');
		}

		$is_draft = (int) $existing['status'] === self::STATUS_DRAFT;
		$upd = [];
		if ($is_draft) {
			$this->_apply_draft_identity_update($id, $existing, $data, $upd);
		}
		if (array_key_exists('status', $data) || array_key_exists('status_code', $data)) {
			$new_status = $this->_normalize_status(array_key_exists('status_code', $data) ? $data['status_code'] : $data['status']);
			if ((int) $new_status === self::STATUS_PUBLISHED && (int) $existing['status'] !== self::STATUS_PUBLISHED) {
				$this->load->library('Indicator_dsd_structure_validate');
				$validation = $this->indicator_dsd_structure_validate->validate_structure_id($id);
				if (empty($validation['valid'])) {
					$first = !empty($validation['errors'][0]) ? $validation['errors'][0] : 'Structure has validation errors';
					throw new Exception('Cannot publish: ' . $first);
				}
			}
			$upd['status'] = $new_status;
		}
		if (array_key_exists('title', $data)) {
			$upd['title'] = trim((string) $data['title']) ?: null;
		}
		if (array_key_exists('description', $data)) {
			$upd['description'] = trim((string) $data['description']) ?: null;
		}
		if (array_key_exists('notes', $data)) {
			$upd['notes'] = trim((string) $data['notes']) ?: null;
		}
		if (array_key_exists('content_hash', $data)) {
			$upd['content_hash'] = trim((string) $data['content_hash']) ?: null;
		}
		if (array_key_exists('metadata', $data)) {
			$upd['metadata'] = $this->_normalize_metadata_for_db($data['metadata']);
		}
		if (array_key_exists('idno', $data)) {
			$agency_for_idno = isset($upd['agency']) ? $upd['agency'] : $existing['agency'];
			$name_for_idno   = isset($upd['name']) ? $upd['name'] : $existing['name'];
			$version_for_idno = isset($upd['version']) ? $upd['version'] : $existing['version'];
			$new_idno = trim((string) $data['idno']);
			if ($new_idno === '') {
				$new_idno = self::make_idno($agency_for_idno, $name_for_idno, $version_for_idno);
			}
			self::assert_idno_not_purely_numeric($new_idno);
			if ($new_idno !== $existing['idno']) {
				$other = $this->get_structure_by_idno($new_idno);
				if ($other && (int) $other['id'] !== $id) {
					throw new Exception("Data structure idno '{$new_idno}' already exists.");
				}
				$upd['idno'] = $new_idno;
			}
		} elseif ($is_draft && (isset($upd['agency']) || isset($upd['name']) || isset($upd['version']))) {
			$agency_for_idno = isset($upd['agency']) ? $upd['agency'] : $existing['agency'];
			$name_for_idno   = isset($upd['name']) ? $upd['name'] : $existing['name'];
			$version_for_idno = isset($upd['version']) ? $upd['version'] : $existing['version'];
			$auto_idno = self::make_idno($agency_for_idno, $name_for_idno, $version_for_idno);
			if ($existing['idno'] === self::make_idno($existing['agency'], $existing['name'], $existing['version'])) {
				$other = $this->get_structure_by_idno($auto_idno);
				if (!$other || (int) $other['id'] === $id) {
					$upd['idno'] = $auto_idno;
				}
			}
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
		$this->db->where('id', $id);
		$this->db->update('data_structures', $upd);
		return true;
	}

	/**
	 * Delete structure (components removed by FK CASCADE).
	 *
	 * @param int $id
	 * @return bool
	 * @throws Exception
	 */
	public function delete_structure($id)
	{
		$id = (int) $id;
		$existing = $this->get_structure_by_id($id, false);
		if (!$existing) {
			throw new Exception('Data structure not found.');
		}
		if (self::is_locked_status((int) $existing['status'])) {
			throw new Exception('Locked data structures (published/archived) cannot be deleted.');
		}
		$usage_count = (int) $this->db
			->from('editor_project_dsd')
			->where('data_structure_id', $id)
			->count_all_results();
		if ($usage_count > 0) {
			throw new Exception("Data structure is in use by {$usage_count} project(s) and cannot be deleted.");
		}

		$agency = (string) $existing['agency'];
		$name   = (string) $existing['name'];
		$family_result = $this->db->select('id, version_seq')
			->from('data_structures')
			->where('agency', $agency)
			->where('name', $name)
			->order_by('version_seq', 'DESC')
			->order_by('id', 'DESC')
			->get();
		$family_result = $this->_require_query_result($family_result, 'delete_structure');
		$family = $family_result->result_array();

		$this->db->trans_begin();
		if (count($family) > 1) {
			$new_pid = null;
			foreach ($family as $row) {
				if ((int) $row['id'] !== $id) {
					$new_pid = (int) $row['id'];
					break;
				}
			}
			if ($new_pid) {
				$this->db->where('agency', $agency)->where('name', $name)->where('id <>', $id);
				$this->db->update('data_structures', ['pid' => $new_pid]);
			}
		}
		// Break self-reference / family pointer before deleting this row.
		$this->db->where('id', $id);
		$this->db->update('data_structures', ['pid' => null]);

		$this->db->where('id', $id);
		$this->db->delete('data_structures');
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			throw new Exception('Failed to delete data structure.');
		}
		$this->db->trans_commit();
		return true;
	}

	/**
	 * Next sequence number for (agency,name) family.
	 *
	 * @param string $agency
	 * @param string $name
	 * @return int
	 */
	public function get_next_version_seq($agency, $name)
	{
		$agency = trim((string) $agency);
		$name   = trim((string) $name);
		$result = $this->db->select_max('version_seq')
			->from('data_structures')
			->where('agency', $agency)
			->where('name', $name)
			->get();
		$result = $this->_require_query_result($result, 'get_next_version_seq');
		$row = $result->row_array();
		$max = isset($row['version_seq']) && $row['version_seq'] !== null
			? (int) $row['version_seq']
			: 0;
		return $max + 1;
	}

	/**
	 * @param mixed $result CI_DB_result|false
	 * @param string $operation Context for error messages
	 * @return object CI_DB_result
	 * @throws Exception
	 */
	private function _require_query_result($result, $operation = 'database query')
	{
		if ($result !== false) {
			return $result;
		}
		$err = $this->db->error();
		$msg = isset($err['message']) ? trim((string) $err['message']) : '';
		$code = isset($err['code']) ? (int) $err['code'] : 0;
		if ($code === 1146 || stripos($msg, "doesn't exist") !== false) {
			throw new Exception(
				'Data structure tables are missing. Run migration 20260516000001_global_codelists_and_data_structures or apply install/schema-data-structures.sql.'
			);
		}
		$detail = $msg !== '' ? ': ' . $msg : '.';
		throw new Exception(trim($operation) . ' failed' . $detail);
	}

	/**
	 * @param mixed $metadata null, array (json_encode), or string
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

	/**
	 * @param mixed $status
	 * @return int
	 * @throws Exception
	 */
	private function _normalize_status($status)
	{
		if ($status === null || $status === '') {
			return self::STATUS_DRAFT;
		}
		if (is_string($status)) {
			$slug = strtolower(trim($status));
			if ($slug !== '' && isset(self::$STATUS_SLUG_TO_CODE[$slug])) {
				return (int) self::$STATUS_SLUG_TO_CODE[$slug];
			}
		}
		if (is_numeric($status)) {
			$code = (int) $status;
			if (in_array($code, self::$allowed_statuses, true)) {
				return $code;
			}
		}
		throw new Exception('Invalid status. Use one of: draft, review, published, deprecated, archived (or legacy codes 0, 10, 20, 30, 40).');
	}

	/**
	 * Update only status/audit fields. Intended for lifecycle transitions on locked rows.
	 *
	 * @param int         $id
	 * @param int|string  $status
	 * @param int|null    $updated_by
	 * @return bool
	 * @throws Exception
	 */
	public function update_structure_status($id, $status, $updated_by = null)
	{
		$id = (int) $id;
		$existing = $this->get_structure_by_id($id, false);
		if (!$existing) {
			throw new Exception('Data structure not found.');
		}
		$new_status = $this->_normalize_status($status);
		$upd = [
			'status'  => $new_status,
			'updated' => time(),
		];
		if ($updated_by !== null && $updated_by !== '') {
			$upd['updated_by'] = (int) $updated_by;
		}
		$this->db->where('id', $id);
		$this->db->update('data_structures', $upd);
		return true;
	}

	/**
	 * @param string $version
	 * @throws Exception
	 */
	private function _assert_semver($version)
	{
		$version = trim((string) $version);
		if (!preg_match(self::VERSION_REGEX, $version)) {
			throw new Exception("Invalid version '{$version}'. Expected semantic version (e.g. 1.2.3).");
		}
	}

	/**
	 * Normalize common legacy short form x.y => x.y.0.
	 *
	 * @param string $version
	 * @return string
	 */
	private function _normalize_semver_input($version)
	{
		$version = trim((string) $version);
		if (preg_match('/^\d+\.\d+$/', $version)) {
			return $version . '.0';
		}
		return $version;
	}

	/**
	 * Return editor projects bound to one data structure version row.
	 *
	 * @param int   $structure_id data_structures.id
	 * @param array $options { page?: int, per_page?: int }
	 * @return array{ rows: array, total: int, page: int, per_page: int }
	 */
	public function get_structure_projects_paged($structure_id, array $options = [])
	{
		$structure_id = (int) $structure_id;
		$page = isset($options['page']) ? max(1, (int) $options['page']) : 1;
		$perPage = isset($options['per_page']) ? (int) $options['per_page'] : 25;
		if ($perPage < 1) {
			$perPage = 25;
		}
		if ($perPage > 200) {
			$perPage = 200;
		}
		$offset = ($page - 1) * $perPage;

		$this->db->from('editor_project_dsd ep');
		$this->db->where('ep.data_structure_id', $structure_id);
		$total = (int) $this->db->count_all_results();

		$this->db->select('p.id, p.idno, p.title, p.type, p.published, p.created, p.changed, ep.data_structure_id');
		$this->db->from('editor_project_dsd ep');
		$this->db->join('editor_projects p', 'p.id = ep.sid');
		$this->db->where('ep.data_structure_id', $structure_id);
		$this->db->order_by('p.changed', 'DESC');
		$this->db->order_by('p.id', 'DESC');
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
}
