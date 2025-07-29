<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * Project versions
 * 
 * 
 * 
 */


class Project_versions
{
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Editor_files_model');
		$this->ci->load->model('Editor_resource_model');
		
		// Define field arrays for copying data
		$this->data_file_fields = array(
			'file_id',
			'file_physical_name',
			'file_name',
			'description', 
			'case_count',
			'var_count',
			'producer',
			'data_checks',
			'missing_data',
			'version',
			'notes',
			'metadata',
			'wght',
			'created',
			'changed',
			'created_by',
			'changed_by',
			'store_data'
		);
		
		$this->variable_fields = array(
			'name',
			'labl',
			'fid',
			'vid',
			'sort_order',
			'is_weight',
			'is_key',
			'user_missings',
			'field_dtype',
			'var_wgt_id',
			'metadata'
		);
	}
	
	
	/**
	 * 
	 * Create project version
	 * 
	 * @param int $sid project id
	 * @param int $user_id user id
	 * @param string $version_type major|minor|patch
	 * @param string $version_notes version notes
	 * @return array
	 * 
	 */
	function create_project_version($sid, $user_id, $version_type, $version_notes)
	{
		if (!$this->is_main_project($sid)){
			throw new Exception("ONLY_MAIN_VERSION_CAN_HAVE_VERSIONS");
		}

		try {
			$new_project = $this->create_project_copy($sid, $user_id, $version_type, $version_notes);
			return $new_project;
		} catch (Exception $e) {
			if (isset($new_project['sid'])) {
				//cleanup failed version
				$this->cleanup_failed_version($new_project['sid']);
			}
			throw $e;
		}
	}

	/**
	 * 
	 * Apply version notes to metadata
	 * 
	 * @param string $type project type
	 * @param array $metadata project metadata
	 * @param array $version_info version info
	 * @return array metadata with version notes applied
	 * 
	 */
	/*function apply_metadata_version_notes($type, $metadata, $version_info=[])
	{
		$mapping=[
			'survey'=>[
				'version_notes'=>'study_desc/version_statement/version_notes',
				'version_number'=>'study_desc/version_statement/version',
				'version_date'=>'study_desc/version_statement/version_date',
			],
			'timeseries'=>[
				'version_notes'=>'series_description/version_statement/version_notes',
				'version_number'=>'series_description/version_statement/version',
				'version_date'=>'series_description/version_statement/version_date',
			],
			'timeseries-db'=>[
				'version_notes'=>'database_description/version/notes',
				'version_number'=>'database_description/version/version',
				'version_date'=>'database_description/version/version_date',
			],
			'script'=>[
				'version_notes'=>'project_desc/version_statement/version_notes',
				'version_number'=>'project_desc/version_statement/version',
				'version_date'=>'project_desc/version_statement/version_date',
			]
			//geospatial - no version notes fields available
		];

		if (isset($mapping[$type])){
			$field_path=$mapping[$type];
			set_array_nested_value($metadata, $field_path['version_notes'], $version_info['version_notes'],"/");
			set_array_nested_value($metadata, $field_path['version_number'], $version_info['version_number'],"/");
			set_array_nested_value($metadata, $field_path['version_date'], $version_info['version_date'],"/");
		}

		return $metadata;
	}


	/**
	 * 
	 * Generate a unique idno for project versions
	 * 
	 * @param string $base_idno original project idno
	 * @return string unique idno for version
	 * 
	 */
	function generate_version_idno($base_idno)
	{
		// Create version-specific idno by appending a unique suffix
		$version_idno = $base_idno . '_version';
		
		// Check if this idno already exists
		$counter = 1;
		$original_version_idno = $version_idno;
		
		while ($this->ci->Editor_model->idno_exists($version_idno)) {
			$version_idno = $original_version_idno . '_' . $counter;
			$counter++;
		}
		
		return $version_idno;
	}

	/**
	 * 
	 * Create a project copy
	 * 
	 * @param int $source_sid source project id
	 * @param int $user_id user id
	 * @param string $version_type major|minor|patch
	 * @param string $version_notes version notes
	 * @return array 
	 * 
	 */
	function create_project_copy($source_sid, $user_id, $version_type, $version_notes)
	{
		$project_info = $this->ci->Editor_model->get_row($source_sid);
		if (!$project_info) {
			throw new Exception("SOURCE_PROJECT_NOT_FOUND");
		}
		
		$latest_version = $this->get_latest_version($source_sid);
		$version_number = $this->generate_version_number($latest_version, $version_type);

		$version_idno = $this->generate_version_idno($project_info['idno']);

		$options = [			
			'idno' => $version_idno,
			'version_number' => $version_number,
			'study_idno' => $project_info['study_idno'],
			'type' => $project_info['type'],
			'title' => $project_info['title'],
			'abbreviation' => $project_info['abbreviation'],
			'nation' => $project_info['nation'],
			'year_start' => $project_info['year_start'],
			'year_end' => $project_info['year_end'],
			'published' => $project_info['published'],
			'created' => $project_info['created'],
			'changed' => $project_info['changed'],
			'varcount' => $project_info['varcount'],
			'created_by' => $project_info['created_by'],
			'changed_by' => $project_info['changed_by'],
			'is_shared' => $project_info['is_shared'],
			"thumbnail" => $project_info['thumbnail'],
			'template_uid' => $project_info['template_uid'],
			'is_locked' => 0,
			'pid' => $source_sid,
			'version_created' => date("U"),			
			'version_created_by' => $user_id,
			'version_notes' => $version_notes,
			'metadata' => $project_info['metadata']
		];

		//create target project
		$new_sid = $this->ci->Editor_model->create_project($project_info['type'], $options);

		if(!$new_sid){
			$db_error = $this->ci->db->error();		
			throw new Exception("FAILED_TO_COPY_PROJECT_METADATA:" . $db_error['message']);
		}

		$output = array(
			'pid' => $source_sid,
			'id' => $new_sid,			
			'idno' => $version_idno,
			'version_number' => $version_number,
			'template_uid' => $project_info['template_uid']
		);
		
		try {
			
			//create project folder and update project table
			$output['project_folder'] = $this->ci->Editor_model->create_project_folder($new_sid);

			//copy data files
			$output['data_files'] = $this->copy_project_data_files($source_sid, $new_sid);

			//copy variables
			$output['variables'] = $this->copy_project_variables($source_sid, $new_sid);

			//copy variable groups
			$output['variable_groups'] = $this->copy_project_variable_groups($source_sid, $new_sid);

			//copy external resources (database records)
			$output['external_resources'] = $this->copy_external_resources($source_sid, $new_sid);

			//copy project files (thumbnails, data files, external resource files)
			$output['files'] = $this->copy_project_files($source_sid, $new_sid);

			//lock project
			$this->ci->Editor_model->lock_project($new_sid);

		} catch (Exception $e) {
			$this->cleanup_failed_version($new_sid);
			throw new Exception("FAILED_TO_COPY_PROJECT_DATA: " . $e->getMessage());
		}

		return $output;
	}

	/**
	 * 
	 * Cleanup failed version by removing project and associated data
	 * 
	 * @param int $sid project id
	 * 
	 */
	function cleanup_failed_version($sid)
	{
		try {
			// Delete project folder if it exists
			$project_folder = $this->ci->Editor_model->get_project_folder($sid);
			if ($project_folder && file_exists($project_folder)) {
				$this->recursive_delete_directory($project_folder);
			}
			
			// Delete from database tables
			$this->ci->db->where('sid', $sid);
			$this->ci->db->delete('editor_data_files');
			
			$this->ci->db->where('sid', $sid);
			$this->ci->db->delete('editor_variables');
			
			$this->ci->db->where('sid', $sid);
			$this->ci->db->delete('editor_variable_groups');
			
			$this->ci->db->where('sid', $sid);
			$this->ci->db->delete('editor_resources');
			
			$this->ci->db->where('id', $sid);
			$this->ci->db->delete('editor_projects');
			
		} catch (Exception $e) {
			log_message('error', 'Failed to cleanup failed version ' . $sid . ': ' . $e->getMessage());
		}
	}

	/**
	 * 
	 * Recursively delete directory and contents
	 * 
	 * @param string $dir directory path
	 * @return bool
	 * 
	 */
	function recursive_delete_directory($dir)
	{
		if (!is_dir($dir)) {
			return false;
		}
		
		$files = array_diff(scandir($dir), array('.', '..'));
		foreach ($files as $file) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_dir($path)) {
				$this->recursive_delete_directory($path);
			} else {
				unlink($path);
			}
		}
		return rmdir($dir);
	}

	/**
	 * 
	 * Copy file with directory creation
	 * 
	 * @param string $source source file path
	 * @param string $target target file path
	 * @return bool
	 * 
	 */
	function copy_file_with_dir_creation($source, $target)
	{
		if (!file_exists($source)) {
			throw new Exception("SOURCE_FILE_NOT_FOUND: " . $source);
		}

		$target_dir = dirname($target);
		if (!file_exists($target_dir)) {
			if (!mkdir($target_dir, 0777, true)) {
				throw new Exception("FAILED_TO_CREATE_TARGET_DIRECTORY: " . $target_dir);
			}
		}

		if (!copy($source, $target)) {
			throw new Exception("FAILED_TO_COPY_FILE: " . $source . " to " . $target);
		}

		if (!file_exists($target)) {
			throw new Exception("TARGET_FILE_NOT_CREATED: " . $target);
		}

		return true;
	}



	/**
	 * 
	 * Copy project data files
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 * 
	 */
	function copy_project_data_files($source_sid, $target_sid)
	{		
		$sql = 'INSERT INTO editor_data_files (sid,' . implode(",", $this->data_file_fields) 
				. ') SELECT ' . $target_sid . ',' . implode(",", $this->data_file_fields) . ' FROM editor_data_files WHERE sid=?';

		$result = $this->ci->db->query($sql, array($source_sid));
		
		if (!$result) {
			$db_error = $this->ci->db->error();
			throw new Exception("FAILED_TO_COPY_DATA_FILES: " . $db_error['message']);
		}
		
		return $result;
	}


	/**
	 * 
	 * Copy project variables
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 * 
	 */
	function copy_project_variables($source_sid, $target_sid)
	{
		$sql = 'INSERT INTO editor_variables (sid,' . implode(",", $this->variable_fields) 
				. ') SELECT ' . $target_sid . ',' . implode(",", $this->variable_fields) . ' FROM editor_variables WHERE sid=?';

		$result = $this->ci->db->query($sql, array($source_sid));
		
		if (!$result) {
			$db_error = $this->ci->db->error();
			throw new Exception("FAILED_TO_COPY_VARIABLES: " . $db_error['message']);
		}
		
		return $result;
	}

	/**
	 * 
	 * Copy project variable groups
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 * 
	 */
	function copy_project_variable_groups($source_sid, $target_sid)
	{
		$sql = 'INSERT INTO editor_variable_groups (sid,metadata) SELECT ' . $target_sid . ',metadata FROM editor_variable_groups WHERE sid=?';
		$result = $this->ci->db->query($sql, array($source_sid));

		if (!$result) {
			$db_error = $this->ci->db->error();
			throw new Exception("FAILED_TO_COPY_VARIABLE_GROUPS: " . $db_error['message']);
		}

		return $result;
	}

	/**
	 * 
	 * Copy project external resources
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 * 
	 */
	function copy_external_resources($source_sid, $target_sid)
	{
		$columns = $this->get_table_columns('editor_resources');

		//remove sid and pk columns
		$columns = array_diff($columns, array('sid', 'id'));

		//create sql
		$sql = 'INSERT INTO editor_resources (sid,' . implode(",", $columns) 
				. ') SELECT ' . $target_sid . ',' . implode(",", $columns) . ' FROM editor_resources WHERE sid=?';
		
		$result = $this->ci->db->query($sql, array($source_sid));
		
		if (!$result) {
			$db_error = $this->ci->db->error();
			throw new Exception("FAILED_TO_COPY_EXTERNAL_RESOURCES: " . $db_error['message']);
		}
		
		return $result;
	}

	/**
	 * 
	 * Copy project files
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 *
	 */
	function copy_project_files($source_sid, $target_sid)
	{
		$source_folder = $this->ci->Editor_model->get_project_folder($source_sid);
		$target_folder = $this->ci->Editor_model->get_project_folder($target_sid);

		if (!$source_folder || !file_exists($source_folder)) {
			throw new Exception("SOURCE_PROJECT_FOLDER_NOT_FOUND: " . $source_folder);
		}

		if (!$target_folder || !file_exists($target_folder)) {
			throw new Exception("TARGET_PROJECT_FOLDER_NOT_FOUND: " . $target_folder);
		}

		$output = array();
		$errors = array();

		// Copy thumbnail file
		try {
			$thumbnail_result = $this->copy_thumbnail($source_sid, $target_sid);
			if ($thumbnail_result) {
				$output['thumbnail'] = 'copied';
			}
		} catch (Exception $e) {
			$errors[] = 'Thumbnail: ' . $e->getMessage();
		}

		// Copy data files
		try {
			$data_files_result = $this->copy_data_file_physical_files($source_sid, $target_sid);
			$output['data_files'] = $data_files_result;
		} catch (Exception $e) {
			$errors[] = 'Data files: ' . $e->getMessage();
		}

		// Copy external resource files
		try {
			$external_resources_result = $this->copy_external_resource_files($source_sid, $target_sid);
			$output['external_resources'] = $external_resources_result;
		} catch (Exception $e) {
			$errors[] = 'External resources: ' . $e->getMessage();
		}

		if (!empty($errors)) {
			throw new Exception("ESSENTIAL_FILE_COPY_ERRORS: " . implode("; ", $errors));
		}

		return $output;
	}

	/**
	 * 
	 * Copy data file physical files
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 * 
	/**
	 * 
	 * Copy physical files associated with data files
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 * 
	 */
	function copy_data_file_physical_files($source_sid, $target_sid)
	{
		// Get all data files for the source project
		$this->ci->db->select('file_physical_name');
		$this->ci->db->where('sid', $source_sid);
		$this->ci->db->where('file_physical_name IS NOT NULL');
		$this->ci->db->where('file_physical_name !=', '');
		$data_files = $this->ci->db->get('editor_data_files')->result_array();

		$output = array();
		$errors = array();

		foreach ($data_files as $data_file) {
			try {
				
				// csv file
				$file_parts = pathinfo($data_file['file_physical_name']);
				$source_file = $this->ci->Editor_model->get_project_folder($source_sid) . '/data/' . $file_parts['filename'] . '.csv';

				// Skip if file doesn't exist
				if (!file_exists($source_file)) {
					$output[] = [
						'file' => $source_file,
						'copied' => false,
						'type' => 'data_file'
					];
					continue;
				}

				// Determine target path - keep same filename in target project folder
				$filename = basename($source_file);
				$target_file = $this->ci->Editor_model->get_project_folder($target_sid) . '/data/' . $filename;

				// Copy file
				$copied = $this->copy_file_with_dir_creation($source_file, $target_file);
				
				if ($copied) {
					$output[] = [
						'file' => $filename,
						'copied' => true,
						'type' => 'data_file'
					];
				}
				
			} catch (Exception $e) {
				$errors[] = $e->getMessage();
			}
		}

		if (!empty($errors)) {
			throw new Exception("DATA_FILE_COPY_ERRORS: " . implode("; ", $errors));
		}

		return $output;
	}

	/**
	 * 
	 * Copy physical files associated with external resources
	 * 
	 * @param int $source_sid source project id
	 * @param int $target_sid target project id
	 * @return bool
	 * 
	 */
	function copy_external_resource_files($source_sid, $target_sid)
	{
		$this->ci->db->select('*');
		$this->ci->db->where('sid', $source_sid);
		$this->ci->db->where('filename IS NOT NULL');
		$this->ci->db->where('filename !=', '');
		$external_resources = $this->ci->db->get('editor_resources')->result_array();

		$output = array();
		$errors = array();

		foreach ($external_resources as $resource) {
			try {				
				$source_file = $this->ci->Editor_model->get_project_folder($source_sid) . '/documentation/' . $resource['filename'];
				
				// Skip if file doesn't exist
				if (!file_exists($source_file)) {
					$output[] = [
						'file' => $source_file,
						'copied' => false,
						'type' => 'external_resource'
					];
					continue;
				}

				// Determine target path - keep same filename in target project folder
				$filename = basename($source_file);
				$target_file = $this->ci->Editor_model->get_project_folder($target_sid) . '/documentation/' . $filename;

				// Copy file
				$copied = $this->copy_file_with_dir_creation($source_file, $target_file);
				
				if ($copied) {
					$output[] = [
						'file' => $filename,
						'copied' => true,
						'type' => 'external_resource'
					];
				}
				
			} catch (Exception $e) {
				$errors[] = $e->getMessage();
			}
		}

		if (!empty($errors)) {
			throw new Exception("EXTERNAL_RESOURCE_FILE_COPY_ERRORS: " . implode("; ", $errors));
		}

		return $output;
	}

	function copy_thumbnail($source_sid, $target_sid)
	{
		$source_folder = $this->ci->Editor_model->get_project_folder($source_sid);
		$target_folder = $this->ci->Editor_model->get_project_folder($target_sid);

		if (!$source_folder || !$target_folder) {
			return false;
		}

		$source_thumbnail = $this->ci->Editor_model->get_thumbnail($source_sid);

		if (!$source_thumbnail) {
			return false;
		}

		$source_thumbnail_path = $source_folder . '/' . $source_thumbnail;
		$target_thumbnail_path = $target_folder . '/' . $source_thumbnail;

		//copy file
		if (file_exists($target_thumbnail_path)) {
			return true;
		}

		if (!file_exists($source_thumbnail_path)) {
			return false;
		}

		$copied = $this->copy_file_with_dir_creation($source_thumbnail_path, $target_thumbnail_path);

		return $copied;
	}

	

	/**
	 * 
	 * Check if project is the main branch
	 * Only the main branch can have versions
	 * 
	 * @param int $sid project id
	 * @return bool
	 * 
	 */
	function is_main_project($sid)
	{
		$project = $this->ci->Editor_model->get_basic_info($sid);

		if (!$project) {
			return false;
		}

		if ($project['pid'] == 0 || !$project['pid'] || $project['pid'] == $sid) {
			return true;
		}

		return false;
	}


	/**
	 * 
	 * Get table columns
	 * 
	 * @param string $table table name
	 * @return array
	 * 
	 */
	function get_table_columns($table)
	{
		$fields = $this->ci->db->list_fields($table);
		return $fields;
	}


	/**
	 * 
	 * Get the latest version number for a project
	 * 
	 * @param int $sid project id
	 * @return string
	 * 
	 */
	function get_latest_version($sid)
	{
		$this->ci->db->select('version_number');
		$this->ci->db->where('pid', $sid);
		$this->ci->db->order_by('version_number', 'desc');
		$result = $this->ci->db->get('editor_projects')->result_array();

		if (!$result) {
			return null;
		}

		$version_numbers = array();
		foreach($result as $row) {
			$version_numbers[] = $row['version_number'];
		}

		//natural sort
		natsort($version_numbers);
		return end($version_numbers);
	}

	/**
	 * 
	 * @param string $old_version
	 * @param string $version_type
	 * 
	 * @version_type major|minor|patch
	 * 
	 * 
	 */
	function generate_version_number($old_version = null, $version_type = 'minor')
	{
		if (!$old_version) {
			return "1.0.0";
		}

		$version_parts = explode(".", $old_version);

		if (count($version_parts) < 3) {
			throw new Exception("INVALID_VERSION_FORMAT: " . $old_version);
		}

		$major = $version_parts[0];
		$minor = $version_parts[1];
		$patch = $version_parts[2];

		switch($version_type) {
			case 'major':
				$major++;
				$minor = 0;
				$patch = 0;
				break;
			case 'minor':
				$minor++;
				$patch = 0;
				break;
			case 'patch':
				$patch++;
				break;
			default:
				$patch++;
		}

		return $major . "." . $minor . "." . $patch;
	}

	/**
	 * Get the main project ID for a given project ID (which could be a version)
	 * 
	 * @param int $project_id - Project ID (can be main project or version)
	 * @return int - Main project ID
	 * @throws Exception - If project not found or invalid structure
	 */
	private function get_main_project_id($project_id)
	{
		$project = $this->ci->Editor_model->get_basic_info($project_id);
		if (!$project) {
			throw new Exception("Project not found");
		}

		if ($this->is_main_project($project_id)) {
			return $project_id;
		}

		$main_project_id = $project['pid'];
		if (!$main_project_id) {
			throw new Exception("Invalid project structure: version has no parent");
		}

		return $main_project_id;
	}

	/**
	 * 
	 * Get versions for a project
	 * 
	 * @param int $sid - Project ID (can be main project or version)
	 * @return array - Array of versions for the main project
	 */
	function get_versions($sid)
	{		
		$main_project_id = $this->get_main_project_id($sid);

		$this->ci->db->select('editor_projects.id, type, idno, version_number, pid,title, created, changed, created_by, changed_by, thumbnail, is_locked, version_notes, version_created, users.username as version_created_by_name, version_created_by');
		$this->ci->db->join("users", "users.id=editor_projects.version_created_by");
		$this->ci->db->where('pid',$main_project_id);
		$this->ci->db->order_by('version_created','desc');
		$result= $this->ci->db->get("editor_projects");
		
		if ($result){
			$result=$result->result_array();
			array_walk($result, 'unix_date_to_gmt',array('version_created'));
		}else{
			$error=$this->ci->db->error();
			throw  new Exception(implode(", ", $error));
		}

		return $result;
	}

	/**
	 * 
	 * Get a specific version by version number
	 * 
	 * @param int $project_id - Project ID (can be main project or version)
	 * @param string $version_number - Version number to retrieve
	 * @return array - Version data
	 */
	function get_version_by_version($project_id, $version_number)
	{
		$main_project_id = $this->get_main_project_id($project_id);

		$this->ci->db->select('editor_projects.id, type, idno, version_number, pid, title, created, changed, created_by, changed_by, thumbnail, is_locked, version_notes, version_created, users.username as version_created_by_name, version_created_by');
		$this->ci->db->join("users", "users.id=editor_projects.version_created_by");
		$this->ci->db->where('pid', $main_project_id);
		$this->ci->db->where('version_number', $version_number);
		$result = $this->ci->db->get("editor_projects")->row_array();
		
		if (!$result) {
			throw new Exception("Version not found");
		}

		unix_date_to_gmt($result, array('version_created'));
		return $result;
	}


	/**
	 * 
	 * Get version statistics for a project
	 * 
	 * @param int $project_id - Project ID (can be main project or version)
	 * @return array - Version statistics
	 */
	function get_version_stats($project_id)
	{
		$main_project_id = $this->get_main_project_id($project_id);

		$versions = $this->get_versions($main_project_id);
		
		if (empty($versions)) {
			return array(
				'total_versions' => 0,
				'latest_version' => null,
				'earliest_version' => null,
				'most_active_user' => null,
				'average_notes_length' => 0
			);
		}

		// Get version numbers for analysis
		$version_numbers = array_column($versions, 'version_number');
		$users = array_column($versions, 'version_created_by_name');
		
		// Count user activity
		$user_counts = array_count_values($users);
		arsort($user_counts);
		$most_active_user = key($user_counts);

		// Sort versions naturally
		natsort($version_numbers);
		$version_numbers = array_values($version_numbers);

		return array(
			'total_versions' => count($versions),
			'latest_version' => end($version_numbers),
			'earliest_version' => reset($version_numbers),
			'most_active_user' => $most_active_user,
			'average_notes_length' => $this->calculate_average_notes_length($versions)
		);
	}
	
}

