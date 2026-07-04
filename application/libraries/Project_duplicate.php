<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Duplicate a project as a new independent main project (no version linkage).
 */
class Project_duplicate
{
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->library('Project_versions');
	}

	/**
	 * @param int   $source_sid
	 * @param int   $user_id
	 * @return array
	 */
	function duplicate($source_sid, $user_id)
	{
		$source_sid = (int) $source_sid;
		$user_id = (int) $user_id;

		$project_info = $this->ci->Editor_model->get_row($source_sid);
		if (!$project_info) {
			throw new Exception('SOURCE_PROJECT_NOT_FOUND');
		}

		$idno = $this->generate_duplicate_idno();
		$this->ci->Editor_model->validate_idno_format($idno);

		$source_title = isset($project_info['title']) ? trim((string) $project_info['title']) : '';
		$title = $source_title !== '' ? $source_title . ' (copy)' : 'untitled (copy)';

		$now = date('U');
		$row = array(
			'idno' => $idno,
			'study_idno' => $project_info['study_idno'],
			'type' => $project_info['type'],
			'title' => $title,
			'abbreviation' => $project_info['abbreviation'],
			'nation' => $project_info['nation'],
			'year_start' => $project_info['year_start'],
			'year_end' => $project_info['year_end'],
			'published' => 0,
			'created' => $now,
			'changed' => $now,
			'varcount' => $project_info['varcount'],
			'created_by' => $user_id,
			'changed_by' => $user_id,
			'is_shared' => 0,
			'thumbnail' => $project_info['thumbnail'],
			'template_uid' => $project_info['template_uid'],
			'is_locked' => 0,
			'pid' => null,
			'version_number' => null,
			'version_created' => null,
			'version_created_by' => null,
			'version_notes' => null,
			'metadata' => $project_info['metadata'],
		);

		$new_sid = $this->ci->Editor_model->create_project($project_info['type'], $row);
		if (!$new_sid) {
			$db_error = $this->ci->db->error();
			throw new Exception('FAILED_TO_DUPLICATE_PROJECT_METADATA:' . $db_error['message']);
		}

		$output = array(
			'source_id' => $source_sid,
			'id' => $new_sid,
			'idno' => $idno,
			'title' => $title,
			'type' => $project_info['type'],
			'template_uid' => $project_info['template_uid'],
		);

		try {
			$output = array_merge($output, $this->ci->project_versions->copy_project_content($source_sid, $new_sid));
			$output['project_dsd'] = $this->ci->project_versions->copy_project_dsd_binding($source_sid, $new_sid);
			$this->ci->Editor_model->unlock_project($new_sid);
		} catch (Exception $e) {
			$this->ci->project_versions->cleanup_failed_copy($new_sid);
			throw new Exception('FAILED_TO_DUPLICATE_PROJECT_DATA: ' . $e->getMessage());
		}

		return $output;
	}

	/**
	 * @return string
	 */
	function generate_duplicate_idno()
	{
		do {
			$idno = (string) $this->ci->Editor_model->generate_uuid();
		} while ($this->ci->Editor_model->idno_exists($idno, null));

		return $idno;
	}
}
