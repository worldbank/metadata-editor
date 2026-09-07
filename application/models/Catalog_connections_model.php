<?php

/**
 * Shared and personal catalogs (publish destinations) and per-user API keys.
 */
class Catalog_connections_model extends CI_Model {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('catalog');
	}

	/**
	 * Catalogs this user may see: official, their private, or a row they have a key on.
	 *
	 * @param int $user_id
	 * @return array
	 */
	function get_connections($user_id)
	{
		$user_id = (int) $user_id;
		$this->db->select($this->catalog_select_fields());
		$this->db->order_by('title', 'ASC');
		$catalogs = $this->db->get('editor_catalogs')->result_array();

		$key_by_catalog = $this->credential_catalog_ids($user_id);

		$visible = array();
		foreach ($catalogs as $catalog) {
			if (!$this->user_can_see_catalog($catalog, $user_id, $key_by_catalog)) {
				continue;
			}
			$catalog = $this->normalize_catalog_row($catalog);
			$catalog['has_credential'] = !empty($key_by_catalog[(int) $catalog['id']]);
			$visible[] = $catalog;
		}

		return $visible;
	}

	/**
	 * Catalog plus this user's API key (null if none). False if missing or not visible.
	 *
	 * @param int $user_id
	 * @param int $id
	 * @return array|false
	 */
	function get_connection($user_id, $id)
	{
		$catalog = $this->get_catalog($id);
		if (!$catalog) {
			return false;
		}

		$user_id = (int) $user_id;
		$credential = $this->get_credential((int) $id, $user_id);
		$key_by_catalog = $credential ? array((int) $id => true) : $this->credential_catalog_ids($user_id);
		if (!$this->user_can_see_catalog($catalog, $user_id, $key_by_catalog)) {
			return false;
		}

		$catalog['api_key'] = $credential ? $credential['api_key'] : null;
		$catalog['has_credential'] = $credential ? true : false;
		$catalog['user_id'] = $user_id;

		return $catalog;
	}

	/**
	 * Catalog + API key, or throw.
	 *
	 * @param int $user_id
	 * @param int $id
	 * @return array
	 */
	function require_connection($user_id, $id)
	{
		$conn = $this->get_connection($user_id, $id);
		if (!$conn) {
			throw new Exception('Target catalog connection was not found');
		}
		if (empty($conn['api_key'])) {
			throw new Exception('API key not found for this catalog. Add your API key in catalog settings.');
		}
		return $conn;
	}

	/**
	 * Catalog identity (never includes api_key).
	 *
	 * @param int $id
	 * @return array|false
	 */
	function get_catalog($id)
	{
		$this->db->select($this->catalog_select_fields());
		$this->db->where('id', (int) $id);
		$row = $this->db->get('editor_catalogs')->row_array();
		return $row ? $this->normalize_catalog_row($row) : false;
	}

	/**
	 * @param int $catalog_id
	 * @param int $user_id
	 * @return array|false
	 */
	function get_credential($catalog_id, $user_id)
	{
		$row = $this->db
			->where('catalog_id', (int) $catalog_id)
			->where('user_id', (int) $user_id)
			->get('editor_catalog_credentials')
			->row_array();
		return $row ? $row : false;
	}

	/**
	 * Create a catalog and optionally store this user's API key.
	 * Personal rows may reuse a shared URL. Shared URLs must be unique among shared rows.
	 *
	 * @param array $options title, url, api_key, user_id, type, is_official
	 * @param bool $can_manage_official
	 * @return int catalog id
	 */
	function create($options = [], $can_manage_official = false)
	{
		$user_id = isset($options['user_id']) ? (int) $options['user_id'] : 0;
		if ($user_id < 1) {
			throw new Exception('Field is required: user_id');
		}

		$type = $this->normalize_type(isset($options['type']) ? $options['type'] : 'nada');
		$title = isset($options['title']) ? trim((string) $options['title']) : '';
		if ($title === '') {
			throw new Exception('Field is required: title');
		}

		$is_official = 0;
		if ($can_manage_official && array_key_exists('is_official', $options)) {
			$is_official = catalog_is_official($options['is_official']) ? 1 : 0;
		}

		$url = isset($options['url']) ? trim((string) $options['url']) : '';
		if ($url === 'https://' || $url === 'http://') {
			$url = '';
		}
		$url_normalized = null;

		if (catalog_type_is_nada($type)) {
			if ($url === '') {
				throw new Exception('Field is required: url');
			}
			$this->assert_nada_url($url);
			$url_normalized = normalize_catalog_url($url);
			$url = $url_normalized;
		} elseif ($url !== '') {
			$url_normalized = normalize_catalog_url($url);
			$url = $url_normalized;
		} else {
			$url = null;
		}

		if ($is_official && $url_normalized) {
			$existing = $this->get_official_by_normalized_url($url_normalized);
			if ($existing) {
				throw new Exception('A shared connection with this URL already exists');
			}
		}

		$now = time();
		$data = array(
			'uid' => $this->allocate_uid($title),
			'title' => $title,
			'type' => $type,
			'is_official' => $is_official,
			'url' => $url,
			'url_normalized' => $url_normalized,
			'created' => $now,
			'changed' => $now,
			'created_by' => $user_id,
		);

		$this->db->insert('editor_catalogs', $data);
		$catalog_id = (int) $this->db->insert_id();

		$this->upsert_credential($catalog_id, $user_id, isset($options['api_key']) ? $options['api_key'] : null);

		return $catalog_id;
	}

	/**
	 * Update identity (owner or catalog admin) and/or this user's API key.
	 *
	 * @param int $catalog_id
	 * @param array $options
	 * @param bool $can_manage_official
	 * @return bool
	 */
	function update($catalog_id, $options = [], $can_manage_official = false)
	{
		$catalog = $this->get_catalog($catalog_id);
		if (!$catalog) {
			throw new Exception('Catalog connection was not found');
		}

		$user_id = isset($options['user_id']) ? (int) $options['user_id'] : 0;
		if ($user_id < 1) {
			throw new Exception('Field is required: user_id');
		}

		if (!$this->user_can_see_catalog($catalog, $user_id)) {
			throw new Exception('Catalog connection was not found');
		}

		$is_official = catalog_is_official(isset($catalog['is_official']) ? $catalog['is_official'] : 0);
		if ($can_manage_official && array_key_exists('is_official', $options)) {
			$is_official = catalog_is_official($options['is_official']);
		}

		if ($this->user_can_edit_identity($catalog, $user_id, $can_manage_official)) {
			$update = array();
			if (isset($options['title']) && trim((string) $options['title']) !== '') {
				$update['title'] = trim((string) $options['title']);
			}
			if (isset($options['type']) && trim((string) $options['type']) !== '') {
				$update['type'] = $this->normalize_type($options['type']);
			}

			$type = isset($update['type']) ? $update['type'] : $catalog['type'];
			if (array_key_exists('url', $options)) {
				$url = trim((string) $options['url']);
				if ($url === 'https://' || $url === 'http://') {
					$url = '';
				}
				if (catalog_type_is_nada($type)) {
					if ($url === '') {
						throw new Exception('Field is required: url');
					}
					$this->assert_nada_url($url);
					$url_normalized = normalize_catalog_url($url);
					$update['url'] = $url_normalized;
					$update['url_normalized'] = $url_normalized;
				} elseif ($url === '') {
					$update['url'] = null;
					$update['url_normalized'] = null;
				} else {
					$url_normalized = normalize_catalog_url($url);
					$update['url'] = $url_normalized;
					$update['url_normalized'] = $url_normalized;
				}
			}

			$url_normalized = array_key_exists('url_normalized', $update)
				? $update['url_normalized']
				: $catalog['url_normalized'];

			if ($is_official && $url_normalized) {
				$other = $this->get_official_by_normalized_url($url_normalized);
				if ($other && (int) $other['id'] !== (int) $catalog_id) {
					throw new Exception('A shared connection with this URL already exists');
				}
			}

			if (!empty($update)) {
				$update['changed'] = time();
				$this->db->where('id', (int) $catalog_id)->update('editor_catalogs', $update);
			}
		}

		if ($can_manage_official && array_key_exists('is_official', $options)) {
			$was_official = catalog_is_official(isset($catalog['is_official']) ? $catalog['is_official'] : 0);
			if ($is_official && !$was_official) {
				$latest = $this->get_catalog($catalog_id);
				$url_normalized = $latest && !empty($latest['url_normalized'])
					? $latest['url_normalized']
					: (isset($catalog['url_normalized']) ? $catalog['url_normalized'] : null);
				if ($url_normalized) {
					$other = $this->get_official_by_normalized_url($url_normalized);
					if ($other && (int) $other['id'] !== (int) $catalog_id) {
						throw new Exception('A shared connection with this URL already exists');
					}
				}
			}
			if ($is_official !== $was_official) {
				$this->db->where('id', (int) $catalog_id)->update('editor_catalogs', array(
					'is_official' => $is_official ? 1 : 0,
					'changed' => time(),
				));
				if (!$is_official) {
					$this->clear_curators((int) $catalog_id);
				}
			}
		}

		if (array_key_exists('api_key', $options) && $options['api_key'] !== null && trim((string) $options['api_key']) !== '') {
			$this->upsert_credential((int) $catalog_id, $user_id, $options['api_key']);
		}

		return true;
	}

	/**
	 * Remove this user's API key. Owner or catalog admin may delete the catalog row.
	 *
	 * @param int $catalog_id
	 * @param int $user_id
	 * @param bool $delete_catalog
	 * @param bool $can_manage_official
	 * @return bool
	 */
	function delete($catalog_id, $user_id, $delete_catalog = false, $can_manage_official = false)
	{
		$catalog = $this->get_catalog($catalog_id);
		if (!$catalog) {
			throw new Exception('Catalog connection was not found');
		}

		if (!$this->user_can_see_catalog($catalog, $user_id)) {
			throw new Exception('Catalog connection was not found');
		}

		$this->db
			->where('catalog_id', (int) $catalog_id)
			->where('user_id', (int) $user_id)
			->delete('editor_catalog_credentials');

		if ($delete_catalog) {
			if (!$this->user_can_edit_identity($catalog, $user_id, $can_manage_official)) {
				throw new Exception('You cannot delete this catalog connection');
			}
			$this->db->where('id', (int) $catalog_id)->delete('editor_catalogs');
		}

		return true;
	}

	function exists($catalog_id, $user_id)
	{
		return $this->get_connection($user_id, $catalog_id) ? true : false;
	}

	function get_by_id($id, $user_id)
	{
		return $this->get_connection($user_id, $id);
	}

	function validate($data, $is_update = false)
	{
		if (empty($data['title'])) {
			throw new Exception('Field is required: title');
		}
		$type = $this->normalize_type(isset($data['type']) ? $data['type'] : 'nada');
		if (catalog_type_is_nada($type)) {
			if (empty($data['url'])) {
				throw new Exception('Field is required: url');
			}
			$this->assert_nada_url($data['url']);
			normalize_catalog_url($data['url']);
		} elseif (!empty($data['url'])) {
			normalize_catalog_url($data['url']);
		}
		if ($is_update && empty($data['id'])) {
			throw new Exception('Field is required: id');
		}
		return true;
	}

	function count_by_user($user_id)
	{
		$this->db->where('user_id', (int) $user_id);
		return $this->db->count_all_results('editor_catalog_credentials');
	}

	function get_all()
	{
		$this->db->select($this->catalog_select_fields());
		$this->db->order_by('title', 'ASC');
		return $this->db->get('editor_catalogs')->result_array();
	}

	/**
	 * Shared catalogs available for Submit for publishing.
	 *
	 * @return array<int, array>
	 */
	function get_official_catalogs()
	{
		$this->db->select($this->catalog_select_fields());
		$this->db->where('is_official', 1);
		$this->db->order_by('title', 'ASC');
		$rows = $this->db->get('editor_catalogs')->result_array();

		$out = array();
		foreach ($rows as $row) {
			$out[] = $this->normalize_catalog_row($row);
		}
		return $out;
	}

	function get_official_by_normalized_url($url_normalized)
	{
		if ($url_normalized === null || $url_normalized === '') {
			return false;
		}
		$row = $this->db
			->where('url_normalized', $url_normalized)
			->where('is_official', 1)
			->get('editor_catalogs')
			->row_array();
		return $row ? $row : false;
	}

	function get_catalog_by_normalized_url($url_normalized)
	{
		return $this->get_official_by_normalized_url($url_normalized);
	}

	/**
	 * @param array $catalog
	 * @param int $user_id
	 * @param bool $can_manage_official
	 * @return bool
	 */
	function user_can_edit_identity($catalog, $user_id, $can_manage_official)
	{
		if (catalog_is_official(isset($catalog['is_official']) ? $catalog['is_official'] : 0)) {
			return (bool) $can_manage_official;
		}
		if ((int) $catalog['created_by'] === (int) $user_id) {
			return true;
		}
		return $can_manage_official && empty($catalog['created_by']);
	}

	private function user_can_see_catalog($catalog, $user_id, $key_by_catalog = null)
	{
		if (catalog_is_official(isset($catalog['is_official']) ? $catalog['is_official'] : 0)) {
			return true;
		}
		if ((int) $catalog['created_by'] === (int) $user_id) {
			return true;
		}
		if ($key_by_catalog === null) {
			$key_by_catalog = $this->credential_catalog_ids($user_id);
		}
		return !empty($key_by_catalog[(int) $catalog['id']]);
	}

	private function credential_catalog_ids($user_id)
	{
		$key_by_catalog = array();
		if (!$user_id) {
			return $key_by_catalog;
		}
		$rows = $this->db
			->select('catalog_id')
			->where('user_id', (int) $user_id)
			->get('editor_catalog_credentials')
			->result_array();
		foreach ($rows as $row) {
			$key_by_catalog[(int) $row['catalog_id']] = true;
		}
		return $key_by_catalog;
	}

	private function catalog_select_fields()
	{
		return 'id, uid, title, type, is_official, url, url_normalized, created, changed, created_by';
	}

	private function normalize_catalog_row($catalog)
	{
		$catalog['is_official'] = catalog_is_official(isset($catalog['is_official']) ? $catalog['is_official'] : 0) ? 1 : 0;
		return $catalog;
	}

	private function upsert_credential($catalog_id, $user_id, $api_key)
	{
		$api_key = $api_key === null ? '' : trim((string) $api_key);
		if ($api_key === '') {
			return;
		}

		$now = time();
		$existing = $this->get_credential($catalog_id, $user_id);
		if ($existing) {
			$this->db
				->where('id', (int) $existing['id'])
				->update('editor_catalog_credentials', array(
					'api_key' => $api_key,
					'changed' => $now,
				));
			return;
		}

		$this->db->insert('editor_catalog_credentials', array(
			'catalog_id' => (int) $catalog_id,
			'user_id' => (int) $user_id,
			'api_key' => $api_key,
			'created' => $now,
			'changed' => $now,
		));
	}

	private function allocate_uid($title)
	{
		$base = catalog_uid_slug($title);
		$uid = $base;
		$i = 2;
		while ($this->uid_exists($uid)) {
			$uid = $base . '-' . $i;
			$i++;
		}
		return $uid;
	}

	private function uid_exists($uid)
	{
		$row = $this->db->select('id')->where('uid', $uid)->get('editor_catalogs')->row_array();
		return !empty($row);
	}

	private function normalize_type($type)
	{
		$type = strtolower(trim((string) $type));
		if ($type === '' || $type === 'nada') {
			return 'nada';
		}
		if ($type === 'other' || $type === 'external') {
			return 'other';
		}
		return $type;
	}

	/**
	 * Curators assigned to a shared catalog.
	 *
	 * @param int $catalog_id
	 * @return array<int, array>
	 */
	function get_curators($catalog_id)
	{
		if (!$this->db->table_exists('catalog_curators')) {
			return array();
		}

		$this->db->select('catalog_curators.user_id, users.username, users.email, meta.first_name, meta.last_name');
		$this->db->from('catalog_curators');
		$this->db->join('users', 'users.id = catalog_curators.user_id');
		$this->db->join('meta', 'meta.user_id = users.id', 'left');
		$this->db->where('catalog_curators.catalog_id', (int) $catalog_id);
		$this->db->order_by('users.username', 'ASC');
		return $this->db->get()->result_array();
	}

	/**
	 * @param int $catalog_id
	 * @param int $user_id
	 * @return bool
	 */
	function add_curator($catalog_id, $user_id)
	{
		$catalog = $this->get_catalog($catalog_id);
		if (!$catalog) {
			throw new Exception('Catalog connection was not found');
		}
		if (!catalog_is_official(isset($catalog['is_official']) ? $catalog['is_official'] : 0)) {
			throw new Exception('Curators can only be assigned to shared connections');
		}

		$user_id = (int) $user_id;
		if ($user_id < 1) {
			throw new Exception('User is required');
		}

		$user = $this->db->select('id')->where('id', $user_id)->get('users')->row_array();
		if (!$user) {
			throw new Exception('User was not found');
		}

		if (!$this->db->table_exists('catalog_curators')) {
			throw new Exception('Catalog curators table is missing');
		}

		$existing = $this->db
			->where('catalog_id', (int) $catalog_id)
			->where('user_id', $user_id)
			->get('catalog_curators')
			->row_array();
		if ($existing) {
			return true;
		}

		$this->db->insert('catalog_curators', array(
			'catalog_id' => (int) $catalog_id,
			'user_id' => $user_id,
			'created' => time(),
		));
		return true;
	}

	/**
	 * @param int $catalog_id
	 * @param int $user_id
	 * @return bool
	 */
	function remove_curator($catalog_id, $user_id)
	{
		if (!$this->db->table_exists('catalog_curators')) {
			return true;
		}

		$this->db
			->where('catalog_id', (int) $catalog_id)
			->where('user_id', (int) $user_id)
			->delete('catalog_curators');
		return true;
	}

	/**
	 * @param int $catalog_id
	 * @return bool
	 */
	function clear_curators($catalog_id)
	{
		if (!$this->db->table_exists('catalog_curators')) {
			return true;
		}

		$this->db->where('catalog_id', (int) $catalog_id)->delete('catalog_curators');
		return true;
	}

	private function assert_nada_url($url)
	{
		$lower = strtolower($url);
		if (strpos($lower, 'index.php') !== false || preg_match('#/api(/|$)#', $lower)) {
			throw new Exception('URL must point to the root of the catalog. Remove index.php or /api');
		}
	}
}
