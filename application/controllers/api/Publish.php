<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Publish extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Catalog_connections_model");
		$this->load->model("Editor_resource_model");
		$this->load->model("Editor_publish_model");
		$this->load->model("Collection_model");
		
		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();
	}

	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}

	
	/**
	 * 
	 * list catalog connections by current logged-in user
	 * 
	 */
	function catalog_connections_get()
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();

			if (!$user_id)
			{
				throw new Exception("User-login-required");
			}

			$connections=$this->Catalog_connections_model->get_connections($user_id);
			
			$response=array(
				'connections'=>$connections
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
	 * Create new catalog connection
	 * 
	 */
	function catalog_connections_post()
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$options=$this->raw_json_input();
			$options['user_id']=$user_id;

			$result=$this->Catalog_connections_model->create($options);
			
			$response=array(
				'status'=>$result
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
	 * Get catalog info from NADA (collections, data_access_codes) for the publish form.
	 * GET /api/publish/catalog_info/{sid}/{catalog_connection_id}
	 */
	function catalog_info_get($sid = null, $catalog_connection_id = null)
	{
		try {
			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception("User-login-required");
			}
			if ($sid === null || $sid === '') {
				throw new Exception("Project ID is required");
			}
			if ($catalog_connection_id === null || $catalog_connection_id === '') {
				throw new Exception("Catalog connection ID is required");
			}

			$this->editor_acl->user_has_project_access($sid, $permission = 'view');

			$response = $this->Editor_publish_model->get_catalog_info($user_id, $catalog_connection_id, $sid);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Publish to catalog
	 * 
	 */
	function index_post($sid=null,$catalog_connection_id=null,$options=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=$this->Editor_publish_model->publish_to_catalog($sid,$user_id,$catalog_connection_id,$options);			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ApiRequestException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'response'=>$e->getDetails()
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
	 * Publish indicator DSD and/or DuckDB timeseries data to NADA.
	 * POST /api/publish/indicator/{sid}/{catalog_connection_id}
	 */
	function indicator_post($sid = null, $catalog_connection_id = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'view');

			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception("User-login-required");
			}

			$input = $this->raw_json_input();
			if (!is_array($input)) {
				$input = array();
			}

			$response = $this->Editor_publish_model->publish_indicator_extras(
				$sid,
				$user_id,
				$catalog_connection_id,
				$input
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (ApiRequestException $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'response' => $e->getDetails(),
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * NADA resumable upload limits (proxy).
	 * GET /api/publish/nada_upload_limits/{catalog_connection_id}?project_id=
	 */
	function nada_upload_limits_get($catalog_connection_id = null)
	{
		try {
			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception('User-login-required');
			}
			$project_id = $this->input->get('project_id');
			if ($project_id === null || $project_id === '') {
				throw new Exception('project_id is required');
			}
			$this->editor_acl->user_has_project_access($project_id, $permission = 'view');

			$response = $this->Editor_publish_model->nada_upload_limits($user_id, $catalog_connection_id);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (ApiRequestException $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'response' => $e->getDetails(),
			), REST_Controller::HTTP_BAD_REQUEST);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Initialize NADA resumable upload (proxy).
	 * POST /api/publish/nada_upload_init/{catalog_connection_id}
	 */
	function nada_upload_init_post($catalog_connection_id = null)
	{
		try {
			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception('User-login-required');
			}

			$input = $this->raw_json_input();
			if (!is_array($input)) {
				$input = array();
			}
			if (empty($input['project_id'])) {
				throw new Exception('project_id is required');
			}

			$this->editor_acl->user_has_project_access($input['project_id'], $permission = 'view');

			$response = $this->Editor_publish_model->nada_upload_init(
				$user_id,
				$catalog_connection_id,
				$input['project_id'],
				$input
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (ApiRequestException $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'response' => $e->getDetails(),
			), REST_Controller::HTTP_BAD_REQUEST);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Upload one chunk to NADA (proxy).
	 * POST /api/publish/nada_upload_chunk/{catalog_connection_id}/{upload_id}
	 */
	function nada_upload_chunk_post($catalog_connection_id = null, $upload_id = null)
	{
		try {
			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception('User-login-required');
			}
			if ($upload_id === null || $upload_id === '') {
				throw new Exception('upload_id is required');
			}

			$project_id = $this->input->get_post('project_id');
			if ($project_id === null || $project_id === '') {
				$project_id = $this->input->get('project_id');
			}
			if ($project_id === null || $project_id === '') {
				throw new Exception('project_id is required');
			}
			$this->editor_acl->user_has_project_access($project_id, $permission = 'view');

			$chunk_number = $this->input->get_request_header('X-Upload-Chunk-Number', true);
			if ($chunk_number === null || $chunk_number === '') {
				throw new Exception('X-Upload-Chunk-Number header is required');
			}
			$chunk_size = $this->input->get_request_header('X-Upload-Chunk-Size', true);
			if ($chunk_size === null || $chunk_size === '') {
				throw new Exception('X-Upload-Chunk-Size header is required');
			}

			$source = $this->input->get('source');
			$resource_id = $this->input->get('resource_id');
			$server_file_key = $this->input->get('server_file_key');
			$total_chunks = $this->input->get('total_chunks');

			if ($source !== null && $source !== '') {
				$chunk_data = $this->Editor_publish_model->read_nada_upload_server_chunk(
					$project_id,
					$source,
					$resource_id,
					$server_file_key,
					(int) $chunk_number,
					(int) $chunk_size
				);
			} else {
				$chunk_data = $this->input->raw_input_stream;
				if ($chunk_data === null || $chunk_data === '') {
					throw new Exception('Chunk data is required');
				}
			}

			$actual_chunk_size = strlen($chunk_data);
			if ($actual_chunk_size < 1) {
				throw new Exception('Chunk data is empty');
			}

			$response = $this->Editor_publish_model->nada_upload_chunk(
				$user_id,
				$catalog_connection_id,
				$upload_id,
				(int) $chunk_number,
				$chunk_data,
				$actual_chunk_size
			);

			if ($total_chunks !== null && $total_chunks !== '' && (int) $total_chunks > 0) {
				$uploadedChunks = (int) $chunk_number + 1;
				$response['uploaded_chunks'] = $uploadedChunks;
				$response['total_chunks'] = (int) $total_chunks;
				$response['progress'] = (int) round(($uploadedChunks / (int) $total_chunks) * 100);
			}

			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (ApiRequestException $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'response' => $e->getDetails(),
			), REST_Controller::HTTP_BAD_REQUEST);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * NADA resumable upload status (proxy).
	 * GET /api/publish/nada_upload_status/{catalog_connection_id}/{upload_id}?project_id=
	 */
	function nada_upload_status_get($catalog_connection_id = null, $upload_id = null)
	{
		try {
			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception('User-login-required');
			}
			$project_id = $this->input->get('project_id');
			if ($project_id === null || $project_id === '') {
				throw new Exception('project_id is required');
			}
			if ($upload_id === null || $upload_id === '') {
				throw new Exception('upload_id is required');
			}
			$this->editor_acl->user_has_project_access($project_id, $permission = 'view');

			$response = $this->Editor_publish_model->nada_upload_status(
				$user_id,
				$catalog_connection_id,
				$upload_id
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (ApiRequestException $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'response' => $e->getDetails(),
			), REST_Controller::HTTP_BAD_REQUEST);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function thumbnail_post($sid=null,$catalog_connection_id=null,$options=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=array(
				'status'=>'success',
				'result'=>$this->Editor_publish_model->publish_thumbnail($sid,$user_id,$catalog_connection_id,$options)
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ApiRequestException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'response'=>$e->getDetails()
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
	 * POST /api/publish/nada_resources_delete_all/{sid}/{catalog_connection_id}
	 */
	function nada_resources_delete_all_post($sid = null, $catalog_connection_id = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'view');

			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception("User-login-required");
			}

			$response = $this->Editor_publish_model->delete_all_nada_study_resources(
				$sid,
				$user_id,
				$catalog_connection_id
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (ApiRequestException $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'response' => $e->getDetails()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function external_resources_post($sid,$connection_id=null, $options=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=$this->Editor_publish_model->publish_external_resources($sid,$user_id,$connection_id,$options);			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ApiRequestException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'response'=>$e->getDetails()
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

	function external_resource_post($sid = null, $catalog_connection_id = null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			if (!is_array($options)) {
				$options = array();
			}
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			if (!isset($options['resource_id'])){
				throw new Exception("Missing resource_id");
			}

			if (!isset($options['catalog_id'])){
				if ($catalog_connection_id !== null && $catalog_connection_id !== '') {
					$options['catalog_id'] = $catalog_connection_id;
				} else {
					throw new Exception("Missing catalog_id");
				}
			}

			if (!isset($options['overwrite'])){
				$options['overwrite']='no';
			}

			$nada_upload_id = isset($options['nada_upload_id']) ? $options['nada_upload_id'] : null;

			$response=$this->Editor_publish_model->publish_external_resource(
				$sid,
				$user_id,
				$options['catalog_id'],
				$options['resource_id'],
				$options['overwrite'],
				$nada_upload_id
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ApiRequestException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'response'=>$e->getDetails()
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
		catch(Throwable $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * Release a temporary server-side upload file registration.
	 * POST /api/publish/nada_upload_release
	 */
	function nada_upload_release_post()
	{
		try {
			$user_id = $this->get_api_user_id();
			if (!$user_id) {
				throw new Exception('User-login-required');
			}

			$input = $this->raw_json_input();
			if (!is_array($input)) {
				$input = array();
			}
			if (empty($input['project_id'])) {
				throw new Exception('project_id is required');
			}
			if (empty($input['server_file_key'])) {
				throw new Exception('server_file_key is required');
			}

			$this->editor_acl->user_has_project_access($input['project_id'], $permission = 'view');
			$this->Editor_publish_model->release_nada_upload_server_file(
				$input['project_id'],
				$input['server_file_key']
			);

			$this->set_response(array('status' => 'success'), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function files_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			//$this->editor_acl->user_has_project_access($sid,$permission='view');

			$result=$this->Editor_resource_model->get_resources_uploaded_files($sid);

			$output=array(
				'files'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


}
