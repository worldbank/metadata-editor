<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Links editor templates to global codelists (one row per field binding).
 *
 * Rows are replaced wholesale from template JSON when a custom template is saved.
 */
class Editor_templates_codelists_model extends CI_Model {

	const MAX_FIELD_PATH_LENGTH = 500;

	private $table = 'editor_templates_codelists';

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @return bool
	 */
	public function table_exists()
	{
		return $this->db->table_exists($this->table);
	}

	/**
	 * @param int $template_id
	 * @return array<int, array>
	 */
	public function get_by_template_id($template_id)
	{
		if (!$this->table_exists()) {
			return array();
		}

		$template_id = (int) $template_id;
		if ($template_id <= 0) {
			return array();
		}

		$this->db->where('template_id', $template_id);
		$this->db->order_by('field_path', 'ASC');
		$query = $this->db->get($this->table);

		return $query ? $query->result_array() : array();
	}

	/**
	 * @param int $codelist_id
	 * @return int
	 */
	public function count_by_codelist_id($codelist_id)
	{
		if (!$this->table_exists()) {
			return 0;
		}

		$codelist_id = (int) $codelist_id;
		if ($codelist_id <= 0) {
			return 0;
		}

		$this->db->where('codelist_id', $codelist_id);

		return (int) $this->db->count_all_results($this->table);
	}

	/**
	 * @param int[] $codelist_ids
	 * @return array<int, int> codelist_id => ref count
	 */
	public function count_map_by_codelist_ids(array $codelist_ids)
	{
		if (!$this->table_exists() || empty($codelist_ids)) {
			return array();
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', $codelist_ids))));
		if (empty($ids)) {
			return array();
		}

		$this->db->select('codelist_id, COUNT(*) AS cnt', false);
		$this->db->from($this->table);
		$this->db->where_in('codelist_id', $ids);
		$this->db->group_by('codelist_id');
		$query = $this->db->get();
		if (!$query) {
			return array();
		}

		$map = array();
		foreach ($query->result_array() as $row) {
			$map[(int) $row['codelist_id']] = (int) $row['cnt'];
		}

		return $map;
	}

	/**
	 * Templates referencing a codelist (for delete / admin messages).
	 *
	 * @param int $codelist_id
	 * @param int $limit
	 * @return array<int, array{ template_id: int, uid: string, name: string, field_path: string }>
	 */
	public function list_usages_by_codelist_id($codelist_id, $limit = 50)
	{
		if (!$this->table_exists()) {
			return array();
		}

		$codelist_id = (int) $codelist_id;
		if ($codelist_id <= 0) {
			return array();
		}

		$limit = max(1, min(200, (int) $limit));

		$this->db->select('etc.template_id, etc.field_path, t.uid, t.name', false);
		$this->db->from($this->table . ' etc');
		$this->db->join('editor_templates t', 't.id = etc.template_id', 'inner');
		$this->db->where('etc.codelist_id', $codelist_id);
		$this->db->order_by('t.name', 'ASC');
		$this->db->order_by('etc.field_path', 'ASC');
		$this->db->limit($limit);
		$query = $this->db->get();

		return $query ? $query->result_array() : array();
	}

	/**
	 * Remove all bindings for a template.
	 *
	 * @param int $template_id
	 * @return bool
	 */
	public function delete_by_template_id($template_id)
	{
		if (!$this->table_exists()) {
			return true;
		}

		$template_id = (int) $template_id;
		if ($template_id <= 0) {
			return false;
		}

		$this->db->where('template_id', $template_id);

		return $this->db->delete($this->table);
	}

	/**
	 * Replace all codelist bindings for a template (transactional).
	 *
	 * Each ref: field_path (string), codelist_id (int). Duplicate field_path keeps last.
	 *
	 * @param int   $template_id
	 * @param array $refs
	 * @return bool
	 * @throws Exception
	 */
	public function replace_for_template($template_id, array $refs)
	{
		if (!$this->table_exists()) {
			throw new Exception('editor_templates_codelists table is not installed');
		}

		$template_id = (int) $template_id;
		if ($template_id <= 0) {
			throw new Exception('Invalid template_id');
		}

		$by_path = array();
		foreach ($refs as $ref) {
			if (!is_array($ref)) {
				continue;
			}
			$field_path = isset($ref['field_path']) ? trim((string) $ref['field_path']) : '';
			$codelist_id = isset($ref['codelist_id']) ? (int) $ref['codelist_id'] : 0;
			if ($field_path === '' || $codelist_id <= 0) {
				continue;
			}
			if (strlen($field_path) > self::MAX_FIELD_PATH_LENGTH) {
				throw new Exception(
					'field_path exceeds ' . self::MAX_FIELD_PATH_LENGTH . ' characters: ' . $field_path
				);
			}
			$by_path[$field_path] = $codelist_id;
		}

		$this->db->trans_begin();

		$this->db->where('template_id', $template_id);
		if (!$this->db->delete($this->table)) {
			$this->db->trans_rollback();
			throw new Exception('Failed to clear template codelist references');
		}

		foreach ($by_path as $field_path => $codelist_id) {
			$inserted = $this->db->insert($this->table, array(
				'template_id' => $template_id,
				'field_path' => $field_path,
				'codelist_id' => $codelist_id,
			));
			if (!$inserted) {
				$this->db->trans_rollback();
				throw new Exception('Failed to save template codelist reference');
			}
		}

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			throw new Exception('Failed to sync template codelist references');
		}

		$this->db->trans_commit();

		return true;
	}
}
