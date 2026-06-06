<?php

/**
 * Optional links between dat/micro external resources and editor data files.
 */
class Editor_resource_datafile_model extends CI_Model
{
	const LINK_TYPE_GENERATED = 'generated';
	const LINK_TYPE_MANUAL = 'manual';
	const LINK_TYPE_ASSOCIATED = 'associated';

	const DCTYPE_MICRO = 'dat/micro';

	private $allowed_link_types = array('generated', 'manual', 'associated');

	private $link_fields = array(
		'id',
		'sid',
		'resource_id',
		'file_id',
		'export_format',
		'export_version',
		'zip_entry_name',
		'link_type',
		'data_file_changed',
		'source_csv_mtime',
		'generated_at',
		'created',
		'created_by',
	);

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Editor_resource_model');
		$this->load->model('Editor_datafile_model');
	}

	/**
	 * @param int $sid
	 * @param int $resource_id
	 * @return array
	 */
	public function get_by_resource($sid, $resource_id)
	{
		$this->db->select('*');
		$this->db->where('sid', (int) $sid);
		$this->db->where('resource_id', (int) $resource_id);
		$this->db->order_by('file_id', 'ASC');
		return $this->db->get('editor_resource_data_files')->result_array();
	}

	/**
	 * @param int $sid
	 * @param string $file_id
	 * @return array
	 */
	public function get_by_data_file($sid, $file_id)
	{
		$this->db->select('editor_resource_data_files.*');
		$this->db->from('editor_resource_data_files');
		$this->db->where('editor_resource_data_files.sid', (int) $sid);
		$this->db->where('editor_resource_data_files.file_id', (string) $file_id);
		return $this->db->get()->result_array();
	}

	/**
	 * Find a generated microdata resource for an export format (first match).
	 *
	 * @param int $sid
	 * @param string $export_format
	 * @return array|null resource row with resource_id key from link
	 */
	public function find_generated_resource_by_format($sid, $export_format)
	{
		$export_format = strtolower(trim((string) $export_format));
		if ($export_format === '') {
			return null;
		}

		$this->db->select('editor_resources.*, editor_resource_data_files.resource_id AS link_resource_id');
		$this->db->from('editor_resource_data_files');
		$this->db->join('editor_resources', 'editor_resources.id = editor_resource_data_files.resource_id');
		$this->db->where('editor_resource_data_files.sid', (int) $sid);
		$this->db->where('editor_resource_data_files.export_format', $export_format);
		$this->db->where('editor_resource_data_files.link_type', self::LINK_TYPE_GENERATED);
		$this->db->like('editor_resources.dctype', 'dat/micro');
		$this->db->group_by('editor_resources.id');
		$this->db->limit(1);
		$row = $this->db->get()->row_array();

		return $row ? $row : null;
	}

	/**
	 * @param int $sid
	 * @return array microdata_resources_count, data_files_count, has_dat_micro_resources, data_files_without_microdata_link
	 */
	public function get_microdata_publish_status($sid)
	{
		$sid = (int) $sid;
		$data_files = $this->Editor_datafile_model->select_all($sid);
		$data_file_ids = array_keys($data_files);

		$resources = $this->Editor_resource_model->select_all($sid);
		$microdata_resources = array();
		foreach ($resources as $resource) {
			if ($this->is_microdata_dctype($resource['dctype'])) {
				$microdata_resources[] = $resource;
			}
		}

		$linked_file_ids = array();
		if (!empty($microdata_resources)) {
			$this->db->select('file_id');
			$this->db->where('sid', $sid);
			$this->db->where_in('resource_id', array_column($microdata_resources, 'id'));
			$rows = $this->db->get('editor_resource_data_files')->result_array();
			foreach ($rows as $row) {
				$linked_file_ids[$row['file_id']] = true;
			}
		}

		$unlinked = array();
		foreach ($data_file_ids as $fid) {
			if (!isset($linked_file_ids[$fid])) {
				$unlinked[] = $fid;
			}
		}

		return array(
			'data_files_count' => count($data_file_ids),
			'microdata_resources_count' => count($microdata_resources),
			'has_dat_micro_resources' => count($microdata_resources) > 0,
			'data_file_ids' => $data_file_ids,
			'unlinked_data_file_ids' => $unlinked,
		);
	}

	/**
	 * @param int $sid
	 * @param int $resource_id
	 * @return array status current|stale|missing_file, reasons[]
	 */
	public function get_resource_staleness($sid, $resource_id)
	{
		$resource = $this->Editor_resource_model->select_single($sid, $resource_id);
		if (!$resource) {
			throw new Exception('Resource not found');
		}

		$links = $this->get_by_resource($sid, $resource_id);
		$reasons = array();

		if (!empty($resource['filename']) && !is_url($resource['filename'])) {
			$path = $this->Editor_resource_model->get_resource_file_by_name($sid, $resource['filename']);
			if (!is_file($path)) {
				$reasons[] = array('code' => 'missing_file', 'file_id' => null);
			}
		}

		foreach ($links as $link) {
			if ($link['link_type'] !== self::LINK_TYPE_GENERATED) {
				continue;
			}

			$datafile = $this->Editor_datafile_model->data_file_by_id($sid, $link['file_id']);
			if (!$datafile) {
				$reasons[] = array('code' => 'data_file_removed', 'file_id' => $link['file_id']);
				continue;
			}

			if ($link['data_file_changed'] !== null && $link['data_file_changed'] !== ''
				&& (int) $datafile['changed'] > (int) $link['data_file_changed']) {
				$reasons[] = array('code' => 'data_file_changed', 'file_id' => $link['file_id']);
			}

			$csv_path = $this->Editor_datafile_model->get_file_csv_path($sid, $link['file_id']);
			if ($csv_path && is_file($csv_path) && $link['source_csv_mtime'] !== null && $link['source_csv_mtime'] !== '') {
				$mtime = (int) filemtime($csv_path);
				if ($mtime > (int) $link['source_csv_mtime']) {
					$reasons[] = array('code' => 'csv_modified', 'file_id' => $link['file_id']);
				}
			}

			$sync = $this->Editor_datafile_model->get_columns_out_of_sync($sid, $link['file_id']);
			if (empty($sync['in_sync'])) {
				$reasons[] = array('code' => 'columns_out_of_sync', 'file_id' => $link['file_id']);
			}
		}

		$status = 'current';
		if (!empty($reasons)) {
			$status = 'stale';
			foreach ($reasons as $r) {
				if ($r['code'] === 'missing_file') {
					$status = 'missing_file';
					break;
				}
			}
		}

		return array(
			'status' => $status,
			'reasons' => $reasons,
		);
	}

	/**
	 * Replace all link rows for a resource (delete then insert).
	 *
	 * @param int $sid
	 * @param int $resource_id
	 * @param array $links
	 * @param int|null $user_id
	 * @return int number of rows inserted
	 */
	public function replace_links_for_resource($sid, $resource_id, array $links, $user_id = null)
	{
		$this->assert_microdata_resource($sid, $resource_id);

		$this->db->where('sid', (int) $sid);
		$this->db->where('resource_id', (int) $resource_id);
		$this->db->delete('editor_resource_data_files');

		$count = 0;
		foreach ($links as $link) {
			$this->insert_link($sid, $resource_id, $link, $user_id);
			$count++;
		}

		return $count;
	}

	/**
	 * @param int $sid
	 * @param int $resource_id
	 * @param array $link
	 * @param int|null $user_id
	 * @return int insert id
	 */
	public function insert_link($sid, $resource_id, array $link, $user_id = null)
	{
		$this->assert_microdata_resource($sid, $resource_id);
		$this->assert_data_file_exists($sid, $link['file_id']);

		$link_type = isset($link['link_type']) ? trim((string) $link['link_type']) : self::LINK_TYPE_MANUAL;
		if (!in_array($link_type, $this->allowed_link_types, true)) {
			throw new Exception('Invalid link_type: ' . $link_type);
		}

		$row = array(
			'sid' => (int) $sid,
			'resource_id' => (int) $resource_id,
			'file_id' => (string) $link['file_id'],
			'export_format' => isset($link['export_format']) ? $link['export_format'] : null,
			'export_version' => isset($link['export_version']) ? $link['export_version'] : null,
			'zip_entry_name' => isset($link['zip_entry_name']) ? $link['zip_entry_name'] : null,
			'link_type' => $link_type,
			'data_file_changed' => isset($link['data_file_changed']) ? $link['data_file_changed'] : null,
			'source_csv_mtime' => isset($link['source_csv_mtime']) ? $link['source_csv_mtime'] : null,
			'generated_at' => isset($link['generated_at']) ? $link['generated_at'] : null,
			'created' => time(),
			'created_by' => $user_id !== null ? (int) $user_id : null,
		);

		$this->db->insert('editor_resource_data_files', $row);
		return (int) $this->db->insert_id();
	}

	/**
	 * @param int $sid
	 * @param int $resource_id
	 */
	public function delete_by_resource($sid, $resource_id)
	{
		$this->db->where('sid', (int) $sid);
		$this->db->where('resource_id', (int) $resource_id);
		$this->db->delete('editor_resource_data_files');
	}

	/**
	 * Build link row snapshots for generated exports.
	 *
	 * @param int $sid
	 * @param string $file_id
	 * @param string $export_format
	 * @param string|null $export_version
	 * @param string|null $zip_entry_name
	 * @return array
	 */
	public function build_generated_link_row($sid, $file_id, $export_format, $export_version = null, $zip_entry_name = null)
	{
		$datafile = $this->Editor_datafile_model->data_file_by_id($sid, $file_id);
		if (!$datafile) {
			throw new Exception('Data file not found: ' . $file_id);
		}

		$csv_mtime = null;
		$csv_path = $this->Editor_datafile_model->get_file_csv_path($sid, $file_id);
		if ($csv_path && is_file($csv_path)) {
			$csv_mtime = (int) filemtime($csv_path);
		}

		$now = time();

		return array(
			'file_id' => (string) $file_id,
			'export_format' => strtolower((string) $export_format),
			'export_version' => $export_version !== null && $export_version !== '' ? (string) $export_version : null,
			'zip_entry_name' => $zip_entry_name,
			'link_type' => self::LINK_TYPE_GENERATED,
			'data_file_changed' => isset($datafile['changed']) ? (int) $datafile['changed'] : null,
			'source_csv_mtime' => $csv_mtime,
			'generated_at' => $now,
		);
	}

	/**
	 * @param string|null $dctype
	 * @return bool
	 */
	public function is_microdata_dctype($dctype)
	{
		$code = $this->Editor_resource_model->get_dctype_code_from_string((string) $dctype);
		return $code === self::DCTYPE_MICRO;
	}

	/**
	 * @param int $sid
	 * @param int $resource_id
	 */
	private function assert_microdata_resource($sid, $resource_id)
	{
		$resource = $this->Editor_resource_model->select_single($sid, $resource_id);
		if (!$resource) {
			throw new Exception('Resource not found');
		}
		if (!$this->is_microdata_dctype($resource['dctype'])) {
			throw new Exception('Resource links are only allowed for microdata (dat/micro) resources');
		}
	}

	/**
	 * @param int $sid
	 * @param string $file_id
	 */
	private function assert_data_file_exists($sid, $file_id)
	{
		$datafile = $this->Editor_datafile_model->data_file_by_id($sid, $file_id);
		if (!$datafile) {
			throw new Exception('Data file not found: ' . $file_id);
		}
	}
}
