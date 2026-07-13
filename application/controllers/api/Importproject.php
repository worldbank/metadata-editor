<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class ImportProject extends MY_REST_Controller
{
	const ALLOWED_FILE_TYPES = 'json|jsonl|xml|zip';
	const RESUMABLE_ALLOWED_TYPES = 'json,jsonl,xml,zip';
	const IMPORT_MIN_MEMORY_BYTES = 1073741824; // 1G

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_resource_model");
		$this->load->model("Editor_datafile_model");
		$this->load->model("Editor_publish_model");
		$this->load->model("Collection_model");
		
		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();
	}

	//override authentication to support both session authentication + api keys
	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}

	/**
	 * Preview an uploaded import file before creating a project.
	 *
	 * POST /api/importproject/preview
	 *   upload_id (required) — completed resumable upload
	 *   type (required)
	 */
	function preview_post()
	{
		try {
			$user_id = $this->get_api_user_id();
			$type = $this->input->post('type');
			$upload_id = $this->normalize_upload_id($this->input->post('upload_id'));

			if (!$type) {
				throw new Exception("TYPE not specified");
			}

			if ($upload_id === '') {
				throw new Exception("upload_id is required");
			}

			$file_ctx = $this->resolve_uploaded_file($upload_id, null);
			$preview = $this->build_import_preview($type, $file_ctx, $user_id);

			if (!empty($preview['conflict'])) {
				$this->set_response($preview, REST_Controller::HTTP_CONFLICT);
				return;
			}

			$this->set_response($preview, REST_Controller::HTTP_OK);
		}
		catch (ValidationException $e) {
			$this->set_response(array(
				'message' => 'VALIDATION_ERROR',
				'errors' => $e->GetValidationErrors()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'message' => 'ERROR',
				'errors' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function index_post()
	{		
		try{
			set_time_limit(0);
			$this->ensure_project_import_memory_limit();

			$user_id=$this->get_api_user_id();
			$type=$this->input->post('type');
			$idno=$this->input->post('idno');
			$upload_id = $this->normalize_upload_id($this->input->post('upload_id'));
			$on_idno_conflict = $this->normalize_on_idno_conflict($this->input->post('on_idno_conflict'));
			$has_file = isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']);

			if (!$type){
				throw new Exception("TYPE not specified");
			}

			if ($upload_id !== '' && $has_file) {
				throw new Exception("Provide either a file upload or upload_id, not both.");
			}

			if ($upload_id === '' && !$has_file) {
				throw new Exception("File not uploaded");
			}

			$file_ctx = $this->resolve_uploaded_file($upload_id, $has_file ? 'file' : null);
			$file_ext = strtolower($file_ctx['extension']);

			$package_idno = '';
			$idno_conflict = false;
			if ($file_ext === 'zip') {
				$package_preview = $this->inspect_zip_package($file_ctx['path'], $type);
				$package_idno = $package_preview['package_idno'];
				$idno_conflict = $package_preview['idno_conflict'];
			}

			if ($idno_conflict && $on_idno_conflict === 'fail') {
				$this->set_response($this->build_idno_conflict_response(
					$package_preview['package_summary'],
					$package_preview['existing_project'],
					$user_id
				), REST_Controller::HTTP_CONFLICT);
				return;
			}

			if ($on_idno_conflict === 'assign_new_idno') {
				if (!$idno_conflict) {
					throw new Exception("assign_new_idno is only allowed when the package IDNO already exists.");
				}
				$idno = $this->generate_import_idno();
			}
			else if (!$idno) {
				if ($file_ext === 'zip' && $package_idno !== '') {
					$this->Editor_model->validate_idno_format($package_idno);
					if ($this->Editor_model->idno_exists($package_idno, null)) {
						$this->set_response($this->build_idno_conflict_response(
							$package_preview['package_summary'],
							$package_preview['existing_project'],
							$user_id
						), REST_Controller::HTTP_CONFLICT);
						return;
					}
					$idno = $package_idno;
				}
				else {
					$idno = (string) $this->Editor_model->generate_uuid();
				}
			}
			else {
				$this->Editor_model->validate_idno_format($idno);
				if ($this->Editor_model->idno_exists($idno, null)){
					throw new Exception("Project IDNO already exists: " . $idno);
				}
			}

			$idno_reassigned = ($on_idno_conflict === 'assign_new_idno');

			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");
			$options['title']='untitled';
			$options['type']=$type;
			$options['idno']=$idno;

			$uploaded_filepath = $file_ctx['path'];
			$file_info = $file_ctx['file_info'];

			$sid=$this->Editor_model->create_project($type,$options);

			if(!$sid){
				throw new Exception("FAILED_TO_CREATE_PROJECT");
			}

			$this->Editor_model->create_project_folder($sid);
			
			try{
				$import_package_options = array(
					'metadata_import_options' => array(
						'type' => $type,
						'created_by' => $user_id,
						'changed_by' => $user_id,
						'created' => $options['created'],
						'changed' => $options['changed'],
					),
				);
				if ($idno_reassigned) {
					$import_package_options['skip_idno_validation'] = true;
					$import_package_options['preserve_project_idno'] = true;
				}

				if ($file_ext=='xml'){
					if (in_array($options['type'],array('survey','microdata'))){
						$result=$this->Editor_model->importDDI($sid, $parseOnly=false,$options);
						$this->link_data_files($sid);
					}
					else if ($options['type']=='geospatial'){
						$this->load->library('Geospatial_import');
						$result=$this->geospatial_import->import($sid,$uploaded_filepath);
					}
					else{
						throw new Exception("Unsupported file type");
					}
				}else if ($file_ext=='json' || $file_ext=='jsonl'){
					$this->load->library('ImportJsonMetadata');
					$result=$this->importjsonmetadata->import($sid,$uploaded_filepath,$validate=true,$options);
					$this->link_data_files($sid);
				}
				else if ($file_ext=='zip')
				{
					$result=$this->import_zip_package($sid, $uploaded_filepath, $import_package_options);

					if (!$idno_reassigned && isset($result['project_info']['idno'])){
						$idno=$result['project_info']['idno'];
					}
				}

				$set_options = array(
					'created_by'=>$user_id,
					'changed_by'=>$user_id,
					'created'=>date("U"),
					'changed'=>date("U"),
				);
				if (!$idno_reassigned) {
					$set_options['idno'] = $idno;
				}
				$this->Editor_model->set_project_options($sid, $set_options);

				$project_row = $this->Editor_model->get_basic_info($sid);
				$study_idno = isset($project_row['study_idno']) ? trim((string) $project_row['study_idno']) : '';

				$output=array(
					'status'=>'success',
					'file_info'=>$file_info,
					'sid'=>$sid,
					'idno'=>$idno,
					'study_idno'=>$study_idno,
					'idno_reassigned'=>$idno_reassigned,
				);
				if ($package_idno !== '') {
					$output['package_idno'] = $package_idno;
				}

				if ($file_ctx['cleanup_upload']) {
					$this->cleanup_resumable_upload($file_ctx['upload_id']);
				}

				$this->set_response($output, REST_Controller::HTTP_OK);
			}
			catch(Exception $e){
				$this->Editor_model->delete_project($sid);
				throw $e;
			}
		}
		catch(ValidationException $e){
			$error_output=array(
				'message'=>'VALIDATION_ERROR',
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'message'=>'ERROR',
				'errors'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function import_ddi_post($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}
			$this->editor_acl->user_has_project_access($sid,$permission='edit');

			$result=$this->Editor_model->importDDI($sid);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	private function import_zip_package($sid, $zip_path, $options = array())
	{
		$this->load->library('ImportPackage');
		return $this->importpackage->import($sid, $zip_path, $options);
	}

	private function link_data_files($sid)
	{
		$this->load->library('ImportPackage');
		$project_path = $this->Editor_model->get_project_folder($sid);
		return $this->importpackage->link_data_files($sid, $project_path);
	}

	private function build_import_preview($type, $file_ctx, $user_id)
	{
		$file_ext = strtolower($file_ctx['extension']);
		$response = array(
			'status' => 'ready',
			'file_ext' => $file_ext,
			'package' => null,
			'conflict' => null,
		);

		if ($file_ext !== 'zip') {
			return $response;
		}

		$zip_preview = $this->inspect_zip_package($file_ctx['path'], $type);
		$response['package'] = $zip_preview['package_summary'];

		if ($zip_preview['idno_conflict']) {
			return $this->build_idno_conflict_response(
				$zip_preview['package_summary'],
				$zip_preview['existing_project'],
				$user_id
			);
		}

		return $response;
	}

	private function inspect_zip_package($zip_path, $type)
	{
		$this->load->library('ImportPackage');
		$project_info = $this->importpackage->peek_info_json($zip_path);
		$package_summary = $this->importpackage->summarize_package_info($project_info);

		$this->validate_package_type($type, $package_summary['type']);

		$package_idno = $package_summary['idno'];
		$idno_conflict = false;
		$existing_project = null;

		if ($package_idno !== '') {
			$this->Editor_model->validate_idno_format($package_idno);
			$existing_sid = $this->Editor_model->get_project_id_by_idno($package_idno);
			if ($existing_sid) {
				$idno_conflict = true;
				$existing_project = $this->summarize_existing_project($existing_sid);
			}
		}

		return array(
			'package_summary' => $package_summary,
			'package_idno' => $package_idno,
			'idno_conflict' => $idno_conflict,
			'existing_project' => $existing_project,
		);
	}

	private function validate_package_type($requested_type, $package_type)
	{
		if ($package_type === '') {
			return;
		}

		$canonical_requested = $this->Editor_model->resolve_canonical_type($requested_type);
		$canonical_package = $this->Editor_model->resolve_canonical_type($package_type);

		if ($canonical_requested === false) {
			throw new Exception("INVALID_TYPE: " . $requested_type);
		}
		if ($canonical_package === false) {
			throw new Exception("INVALID_PACKAGE_TYPE: " . $package_type);
		}
		if ($canonical_requested !== $canonical_package) {
			throw new Exception(
				"Project type mismatch. Package type is '" . $package_type
				. "', but the selected project type is '" . $requested_type . "'."
			);
		}
	}

	private function build_idno_conflict_response($package_summary, $existing_project, $user_id)
	{
		$existing = null;
		if (is_array($existing_project)) {
			$existing = array(
				'sid' => (int) $existing_project['sid'],
				'title' => $existing_project['title'],
				'type' => $existing_project['type'],
			);
			try {
				$this->editor_acl->user_has_project_access($existing_project['sid'], 'view', $this->api_user());
			}
			catch (Exception $e) {
				unset($existing['sid'], $existing['title']);
				$existing['type'] = $existing_project['type'];
			}
		}

		return array(
			'status' => 'conflict',
			'conflict' => 'idno_exists',
			'package_idno' => $package_summary['idno'],
			'package_type' => $package_summary['type'],
			'package_title' => $package_summary['title'],
			'package' => $package_summary,
			'existing_project' => $existing,
		);
	}

	private function summarize_existing_project($sid)
	{
		$project = $this->Editor_model->get_basic_info($sid);
		if (!$project) {
			return null;
		}

		return array(
			'sid' => (int) $sid,
			'title' => isset($project['title']) ? $project['title'] : '',
			'type' => isset($project['type']) ? $project['type'] : '',
			'idno' => isset($project['idno']) ? $project['idno'] : '',
		);
	}

	private function resolve_uploaded_file($upload_id, $file_field_name = null)
	{
		if ($upload_id !== '') {
			$this->load->library('Resumable_upload', null, 'resumable_upload');
			$completed = $this->resumable_upload->get_completed_upload($upload_id);
			if (!$completed) {
				throw new Exception("Resumable upload not found or not yet complete.");
			}

			$extension = strtolower((string) $completed['file_extension']);
			$this->assert_allowed_import_extension($extension);

			return array(
				'path' => $completed['file_path'],
				'extension' => $extension,
				'file_info' => pathinfo($completed['file_path']),
				'upload_id' => $upload_id,
				'cleanup_upload' => true,
			);
		}

		$uploaded_filepath = $this->Editor_resource_model->upload_temporary_file(
			self::ALLOWED_FILE_TYPES,
			$file_field_name,
			null
		);

		if (!file_exists($uploaded_filepath)) {
			throw new Exception("Failed to upload file");
		}

		$file_info = pathinfo($uploaded_filepath);
		$extension = strtolower($file_info['extension']);
		$this->assert_allowed_import_extension($extension);

		return array(
			'path' => $uploaded_filepath,
			'extension' => $extension,
			'file_info' => $file_info,
			'upload_id' => null,
			'cleanup_upload' => false,
		);
	}

	private function assert_allowed_import_extension($extension)
	{
		$allowed = array_map('trim', explode('|', self::ALLOWED_FILE_TYPES));
		if (!in_array($extension, $allowed, true)) {
			throw new Exception("Unsupported file type: " . $extension);
		}
	}

	private function normalize_upload_id($upload_id)
	{
		return is_string($upload_id) ? trim($upload_id) : '';
	}

	private function normalize_on_idno_conflict($value)
	{
		$value = is_string($value) ? strtolower(trim($value)) : 'fail';
		if (!in_array($value, array('fail', 'assign_new_idno'), true)) {
			throw new Exception("Invalid on_idno_conflict value. Allowed: fail, assign_new_idno");
		}
		return $value;
	}

	private function generate_import_idno()
	{
		do {
			$idno = (string) $this->Editor_model->generate_uuid();
		} while ($this->Editor_model->idno_exists($idno, null));

		return $idno;
	}

	private function cleanup_resumable_upload($upload_id)
	{
		if ($upload_id === null || $upload_id === '') {
			return;
		}

		$this->load->library('Resumable_upload', null, 'resumable_upload');
		try {
			$this->resumable_upload->delete_upload($upload_id);
		}
		catch (Exception $e) {
			log_message('error', 'Failed to cleanup resumable upload ' . $upload_id . ': ' . $e->getMessage());
		}
	}

	private function ensure_project_import_memory_limit()
	{
		$target_bytes = self::IMPORT_MIN_MEMORY_BYTES;
		$current = ini_get('memory_limit');
		if ($current === '-1') {
			return;
		}

		$bytes = $this->parse_memory_limit_bytes($current);
		if ($bytes > 0 && $bytes < $target_bytes) {
			ini_set('memory_limit', '1024M');
		}
	}

	private function parse_memory_limit_bytes($limit)
	{
		$limit = trim((string) $limit);
		if ($limit === '' || $limit === '-1') {
			return 0;
		}

		if (preg_match('/^(\d+)([KMG])?$/i', $limit, $m)) {
			$bytes = (int) $m[1];
			$unit = isset($m[2]) ? strtoupper($m[2]) : '';
			if ($unit === 'K') {
				$bytes *= 1024;
			} elseif ($unit === 'M') {
				$bytes *= 1024 * 1024;
			} elseif ($unit === 'G') {
				$bytes *= 1024 * 1024 * 1024;
			}
			return $bytes;
		}

		return 0;
	}
}
