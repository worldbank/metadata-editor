<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Resources extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_datafile_model");		
		
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
	 * 
	 * Generate resources JSON and save to project folder
	 * 
	 */
	function write_json_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->load->library('ProjectPackage');
			$this->projectpackage->run_stage($sid, 'resources_json');
			
			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Generate resources RDF and save to project folder
	 * 
	 */
	function write_rdf_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->load->library('ProjectPackage');
			$this->projectpackage->run_stage($sid, 'resources_rdf');
			
			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * import RDF or JSON
	 * 
	 */
	public function import_post($sid=NULL)
	{
		try {
			$sid=$this->get_sid($sid);
			$user=$this->api_user();
			$this->editor_acl->user_has_project_access($sid,$permission='edit', $user);

			$uploaded_filepath=$this->Editor_resource_model->upload_temporary_file($allowed_file_type="rdf|xml|json",$file_field_name='file',$temp_upload_folder=null);

			if (!file_exists($uploaded_filepath)){
				throw new Exception("File upload failed");
			}

			$file_info=pathinfo($uploaded_filepath);

			if (strtolower($file_info['extension'])=='rdf'){
				$imported_count=$this->Editor_resource_model->import_rdf($sid,$uploaded_filepath);
			}else if (strtolower($file_info['extension'])=='json'){
				$imported_count=$this->Editor_resource_model->import_json($sid,$uploaded_filepath);
			}
			else{
				throw new Exception("File type is not supported: ".$file_info['extension']);
			}
			
			@unlink($uploaded_filepath);

			$output=array(
				'status'=>'success',
				'entries_imported'=>$imported_count
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$output=array(
				'status'=>'error',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}		
	}


	/**
	 * 
	 * list external resources
	 * 
	 */
	function index_get($sid=null)
	{
		try{
			$user=$this->api_user();
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view', $user);
			
			$user_id=$this->get_api_user_id();
			$resources=$this->Editor_resource_model->select_all($sid,$fields=null);
			
			$response=array(
				'resources'=>$resources
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Download external resources as RDF/XML
	 * 
	 */
	function rdf_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');

			header('Content-type: application/xml');
			echo $this->Editor_resource_model->generate_rdf($sid);
			die();
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * create or update external resource
	 * 
	 **/ 
	function index_post($sid=null,$resource_id=null)
	{
		//multipart/form-data
		$options=$this->input->post(null, true);
		$user=$this->api_user();

		//raw json input
		if (empty($options)){
			$options=$this->raw_json_input();
		}
				
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

			$options['sid']=$sid;

			//get dctype by code
			if(isset($options['dctype'])){ 
				$options['dctype']=$this->Editor_resource_model->get_dctype_label_by_code($options['dctype']);
			}

			if(isset($options['dcformat'])){ 
				$options['dcformat']=$this->Editor_resource_model->get_dcformat_label_by_code($options['dcformat']);
			}

			//validate resource
			if ($this->Editor_resource_model->validate_resource($options, !$resource_id, $resource_id)){

				$upload_result=null;

				if(!empty($_FILES)){
					//upload file?					
					$upload_result=$this->Editor_resource_model->upload_file($sid,$file_type='documentation', $file_field_name='file', $remove_spaces=false);
					$uploaded_file_name=$upload_result['file_name'];
				
					//set filename to uploaded file
					$options['filename']=$uploaded_file_name;
				}

			if(!isset($options['filename'])){
				$options['filename']=null;
			}				

			if($resource_id){
					$resource=$this->Editor_resource_model->select_single($sid,$resource_id);
					if (!$resource){
						throw new Exception("Resource not found");
					}

					/*if($resource['filename'] && $options['filename']){						
						//delete old file
						$this->Editor_resource_model->delete_file_by_resource($sid,$resource_id);
					}*/
					
					$resource_id=$this->Editor_resource_model->update($resource_id,$options);
				}				
				else{
					// Insert new resource, or replace existing row when overwrite=true and filename matches
					$existing_by_filename = null;
					if (!empty($options['filename']) && !empty($options['sid'])) {
						$existing_by_filename = $this->Editor_resource_model->check_filename_in_use($options['sid'], $options['filename'], null);
					}
					if ($existing_by_filename && $this->Editor_resource_model->overwrite_flag_is_true($options)) {
						$resource_id = $this->Editor_resource_model->update($existing_by_filename['id'], $options);
					} else {
						$resource_id = $this->Editor_resource_model->insert($options);
					}
				}

				$resource=$this->Editor_resource_model->select_single($sid,$resource_id);
				
				$response=array(
					'status'=>'success',
					'resource'=>$resource,
					'uploaded_file'=>$upload_result
				);

				$this->set_response($response, REST_Controller::HTTP_OK);
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
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}		
	}


	/**
	 * 
	 * Delete external resource
	 * 
	 */
	function delete_post($sid=null,$resource_id=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$user=$this->api_user();

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);
			$resources = $this->Editor_resource_model->delete($sid, $resource_id);

			$this->set_response(array(
				'status' => 'success',
				'resources' => $resources,
			), REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function download_get($sid, $resource_id)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->Editor_resource_model->download_resource($sid,$resource_id,$resource_type='documentation');
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * Get resource file information
	 * 
	 */
	function file_get($sid=null,$resource_id=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$user=$this->api_user();

			if (!$sid){
				throw new Exception("Missing parameter: sid");
			}

			if (!$resource_id){
				throw new Exception("Missing parameter: resource_id");
			}
			
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$result=$this->Editor_resource_model->get_file_info($sid,$resource_id);

			$output=array(
				'status'=>'success',
				'file_info'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * POST /api/resources/generate_microdata/{projectId}
	 *
	 * Export data files and create a dat/micro external resource in documentation/.
	 */
	function generate_microdata_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$user = $this->api_user();
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $user);

			$input = $this->raw_json_input();
			if (!is_array($input)) {
				$input = array();
			}

			$user_id = $this->get_api_user_id();
			$options = $this->build_microdata_generate_options($sid, $input, $user_id);

			$this->load->library('Microdata_resource_generator');
			$result = $this->microdata_resource_generator->generate($sid, $options);

			$this->set_response($result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * POST /api/resources/regenerate/{projectId}/{resourceId}
	 *
	 * Re-export and update an existing generated microdata resource.
	 */
	function regenerate_post($sid = null, $resource_id = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$user = $this->api_user();
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $user);

			if (!$resource_id) {
				throw new Exception('Missing parameter: resource_id');
			}

			$input = $this->raw_json_input();
			if (!is_array($input)) {
				$input = array();
			}

			$user_id = $this->get_api_user_id();
			$options = $this->build_microdata_generate_options($sid, $input, $user_id, (int) $resource_id);

			$this->load->library('Microdata_resource_generator');
			$result = $this->microdata_resource_generator->generate($sid, $options);

			$this->set_response($result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * GET /api/resources/microdata_status/{projectId}
	 */
	function microdata_status_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$user = $this->api_user();
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $user);

			$this->load->model('Editor_resource_datafile_model');
			$this->load->library('Microdata_resource_generator');
			$publish_status = $this->Editor_resource_datafile_model->get_microdata_publish_status($sid);

			$resources = $this->Editor_resource_model->select_all($sid);
			$microdata_resources = array();

			foreach ($resources as $resource) {
				if (!$this->Editor_resource_datafile_model->is_microdata_dctype($resource['dctype'])) {
					continue;
				}

				$resource_id = (int) $resource['id'];
				$links = $this->Editor_resource_datafile_model->get_by_resource($sid, $resource_id);
				$staleness = $this->Editor_resource_datafile_model->get_resource_staleness($sid, $resource_id);

				$export_format = null;
				$export_version = null;
				foreach ($links as $link) {
					if ($link['link_type'] === Editor_resource_datafile_model::LINK_TYPE_GENERATED
						&& !empty($link['export_format'])) {
						$export_format = $link['export_format'];
						$export_version = $link['export_version'];
						break;
					}
				}

				$microdata_resources[] = array(
					'resource' => $resource,
					'links' => $links,
					'export_format' => $export_format,
					'export_version' => $export_version,
					'staleness' => $staleness,
				);
			}

			$this->set_response(array(
				'status' => 'success',
				'publish_status' => $publish_status,
				'microdata_resources' => $microdata_resources,
				'supported_formats' => Microdata_resource_generator::SUPPORTED_FORMATS,
			), REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * GET /api/resources/datafile_links/{projectId}/{resourceId}
	 */
	function datafile_links_get($sid = null, $resource_id = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$user = $this->api_user();
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $user);

			if (!$resource_id) {
				throw new Exception('Missing parameter: resource_id');
			}

			$this->load->model('Editor_resource_datafile_model');
			$resource = $this->Editor_resource_model->select_single($sid, $resource_id);
			if (!$resource) {
				throw new Exception('Resource not found');
			}
			if (!$this->Editor_resource_datafile_model->is_microdata_dctype($resource['dctype'])) {
				throw new Exception('Data file links are only available for microdata (dat/micro) resources');
			}

			$resource_id = (int) $resource_id;
			$links = $this->Editor_resource_datafile_model->get_by_resource($sid, $resource_id);

			$this->load->model('Editor_datafile_model');
			$export_format = null;
			$export_version = null;
			$generated_at = null;

			foreach ($links as $idx => $link) {
				$datafile = $this->Editor_datafile_model->data_file_by_id($sid, $link['file_id']);
				$links[$idx]['file_name'] = $datafile && isset($datafile['file_name'])
					? $datafile['file_name']
					: null;

				if ($link['link_type'] === Editor_resource_datafile_model::LINK_TYPE_GENERATED
					&& !empty($link['export_format'])) {
					if ($export_format === null) {
						$export_format = $link['export_format'];
						$export_version = isset($link['export_version']) ? $link['export_version'] : null;
					}
					if (!empty($link['generated_at'])
						&& ($generated_at === null || (int) $link['generated_at'] > (int) $generated_at)) {
						$generated_at = (int) $link['generated_at'];
					}
				}
			}

			$staleness = $this->Editor_resource_datafile_model->get_resource_staleness($sid, $resource_id);

			$this->set_response(array(
				'status' => 'success',
				'resource_id' => $resource_id,
				'source_type' => isset($resource['source_type']) ? $resource['source_type'] : null,
				'bundle_type' => isset($resource['bundle_type']) ? $resource['bundle_type'] : null,
				'export_format' => $export_format,
				'export_version' => $export_version,
				'generated_at' => $generated_at,
				'staleness' => $staleness,
				'links' => $links,
			), REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * PUT /api/resources/datafile_links/{projectId}/{resourceId}
	 *
	 * Replace optional manual/associated links for a microdata resource.
	 */
	function datafile_links_put($sid = null, $resource_id = null)
	{
		$this->datafile_links_save($sid, $resource_id);
	}


	/**
	 * POST /api/resources/datafile_links/{projectId}/{resourceId}
	 */
	function datafile_links_post($sid = null, $resource_id = null)
	{
		$this->datafile_links_save($sid, $resource_id);
	}


	/**
	 * @param int|null $sid
	 * @param int|null $resource_id
	 */
	private function datafile_links_save($sid = null, $resource_id = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$user = $this->api_user();
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $user);

			if (!$resource_id) {
				throw new Exception('Missing parameter: resource_id');
			}

			$resource = $this->Editor_resource_model->select_single($sid, $resource_id);
			if (!$resource) {
				throw new Exception('Resource not found');
			}

			$this->load->model('Editor_resource_datafile_model');
			if (!$this->Editor_resource_datafile_model->is_microdata_dctype($resource['dctype'])) {
				throw new Exception('Data file links are only allowed for microdata (dat/micro) resources');
			}

			if (isset($resource['source_type']) && $resource['source_type'] === 'generated') {
				throw new Exception('Generated resource links are managed via regenerate');
			}

			$input = $this->raw_json_input();
			if (!is_array($input) || !isset($input['links']) || !is_array($input['links'])) {
				throw new Exception('Request body must include a links array');
			}

			$links = array();
			foreach ($input['links'] as $link) {
				if (!is_array($link) || empty($link['file_id'])) {
					throw new Exception('Each link must include file_id');
				}

				$link_type = isset($link['link_type']) ? trim((string) $link['link_type']) : Editor_resource_datafile_model::LINK_TYPE_ASSOCIATED;
				if ($link_type === Editor_resource_datafile_model::LINK_TYPE_GENERATED) {
					throw new Exception('link_type generated is not allowed on this endpoint');
				}

				$links[] = array(
					'file_id' => (string) $link['file_id'],
					'link_type' => $link_type,
				);
			}

			$user_id = $this->get_api_user_id();
			$count = $this->Editor_resource_datafile_model->replace_links_for_resource($sid, (int) $resource_id, $links, $user_id);

			$this->set_response(array(
				'status' => 'success',
				'resource_id' => (int) $resource_id,
				'links_count' => $count,
				'links' => $this->Editor_resource_datafile_model->get_by_resource($sid, (int) $resource_id),
			), REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * @param int $sid
	 * @param array $input
	 * @param int|false $user_id
	 * @param int|null $resource_id
	 * @return array
	 */
	private function build_microdata_generate_options($sid, array $input, $user_id, $resource_id = null)
	{
		$this->load->model('Editor_resource_datafile_model');

		$options = array();
		if ($user_id) {
			$options['user_id'] = (int) $user_id;
		}

		if ($resource_id !== null && $resource_id > 0) {
			$options['resource_id'] = (int) $resource_id;
			$options['overwrite'] = true;

			$existing_links = $this->Editor_resource_datafile_model->get_by_resource($sid, (int) $resource_id);
			foreach ($existing_links as $link) {
				if ($link['link_type'] === Editor_resource_datafile_model::LINK_TYPE_GENERATED
					&& !empty($link['export_format'])) {
					if (empty($input['export_format'])) {
						$input['export_format'] = $link['export_format'];
					}
					if (empty($input['export_version']) && $link['export_version'] !== null && $link['export_version'] !== '') {
						$input['export_version'] = $link['export_version'];
					}
					break;
				}
			}

			if (empty($input['file_ids'])) {
				$file_ids = array();
				foreach ($existing_links as $link) {
					if ($link['link_type'] === Editor_resource_datafile_model::LINK_TYPE_GENERATED && !empty($link['file_id'])) {
						$file_ids[] = $link['file_id'];
					}
				}
				if (!empty($file_ids)) {
					$input['file_ids'] = $file_ids;
				}
			}

			if (!empty($input['refresh_description'])) {
				$options['refresh_description'] = true;
			}
		}

		if (!empty($input['export_format'])) {
			$options['export_format'] = $input['export_format'];
		}
		if (array_key_exists('export_version', $input)) {
			$options['export_version'] = $input['export_version'];
		}
		if (!empty($input['file_ids']) && is_array($input['file_ids'])) {
			$options['file_ids'] = $input['file_ids'];
		}
		if (array_key_exists('zip', $input)) {
			$options['zip'] = $input['zip'];
		}
		if (!empty($input['overwrite'])) {
			$options['overwrite'] = true;
		}
		if (!empty($input['max_wait_seconds'])) {
			$options['max_wait_seconds'] = (int) $input['max_wait_seconds'];
		}
		if (!empty($input['file_modes']) && is_array($input['file_modes'])) {
			$normalized = array();
			foreach ($input['file_modes'] as $fid => $mode) {
				$mode = strtolower(trim((string) $mode));
				if (in_array($mode, array('original', 'generate'), true)) {
					$normalized[(string) $fid] = $mode;
				}
			}
			$options['file_modes'] = $normalized;
		}

		return $options;
	}

}
