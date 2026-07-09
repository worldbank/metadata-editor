<?php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

use League\Csv\Reader;
use League\Csv\Statement;

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Data extends MY_REST_Controller
{

	//Data API base url
	private $DataApiUrl; //'http://localhost:8000';

	/** Internal chunk size when streaming dictionary import from cache file. */
	private const SUMMARY_STATS_IMPORT_CHUNK_SIZE = 500;

	public function __construct()
	{
		parent::__construct();

		$this->load->model("Editor_model");
		$this->load->model("Editor_resource_model");
		$this->load->model("Editor_datafile_model");
		$this->load->model("Editor_variable_model");
		$this->load->library("DataUtils");
		//$this->is_admin_or_die();

		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();

		$this->load->config("editor");
		$this->DataApiUrl = $this->config->item('data_api_url', 'editor');
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
	 * Optional multipart fields for datafile uploads (see datafile-schema.json).
	 *
	 * @return array Only keys present in the request body are included.
	 */
	private function collect_datafile_upload_metadata()
	{
		$fields = array('description', 'producer', 'data_checks', 'missing_data', 'version', 'notes');
		$out = array();
		foreach ($fields as $f) {
			if (array_key_exists($f, $_POST)) {
				$out[$f] = $this->input->post($f, true);
			}
		}
		return $out;
	}
	
	/**
	 * 
	 *  Get status of the Data api service
	 * 
	 */
	public function status_get()
	{
		try{
			$response=$this->datautils->status();
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
	 * Upload data file (microdata).
	 *
	 * multipart/form-data:
	 * - file: single-file upload (legacy), or
	 * - upload_id: completed resumable upload from /api/uploads/* (with store_data, optional overwrite)
	 * Do not send both file and upload_id.
	 * Optional metadata (same as datafile-schema.json): description, producer, data_checks,
	 * missing_data, version, notes.
	 *
	 **/ 
	function datafile_post($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$overwrite=$this->input->post("overwrite") ? (int)$this->input->post("overwrite") : 0;
			$store_data=$this->input->post("store_data");
			$upload_id_raw = $this->input->post("upload_id");
			$upload_id = is_string($upload_id_raw) ? trim($upload_id_raw) : '';

			if ($store_data !== "store" && $store_data !== "remove") {
				throw new Exception("Invalid value for store_data. Valid values are 'store', 'remove'");
			}

			$has_file = isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']);
			if ($upload_id !== '' && $has_file) {
				throw new Exception("Provide either a file upload or upload_id, not both.");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$result=$this->Editor_datafile_model->upload_create(
				$sid,
				$overwrite,
				$store_data,
				$this->get_api_user_id(),
				$upload_id === '' ? null : $upload_id,
				$this->collect_datafile_upload_metadata()
			);

			$output=array(
				'status'=>'success',
				'result'=>$result
				//'uploaded_file_name'=>$uploaded_file_name,
				//'base64'=>base64_encode($uploaded_file_name)				
			);
						
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Upload data file and create import job in one step
	 * 
	 * POST /api/data/import_microdata/{sid}
	 * 
	 * Accepts multipart/form-data with:
	 *   - file: The data file to upload, or
	 *   - upload_id: completed resumable upload (do not send both)
	 *   - overwrite: (optional) 0 or 1, default 0
	 *   - store_data: (optional) "store" or "remove", default "store"
	 *   - priority: (optional) Job queue priority; higher values run before lower (default: 0)
	 *   - description, producer, data_checks, missing_data, version, notes: (optional) datafile metadata
	 * 
	 * Returns:
	 *   - file_id: The uploaded file ID
	 *   - job_id: The created job ID
	 *   - job: Full job information
	 * 
	 **/ 
	function import_microdata_post($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$upload_id_raw = $this->input->post("upload_id");
			$upload_id = is_string($upload_id_raw) ? trim($upload_id_raw) : '';
			$has_file = isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']);
			if ($upload_id !== '' && $has_file) {
				throw new Exception("Provide either a file upload or upload_id, not both.");
			}
			if ($upload_id === '' && !$has_file) {
				throw new Exception("File upload is required, or provide upload_id after completing a chunked upload.");
			}

			$overwrite=$this->input->post("overwrite") ? (int)$this->input->post("overwrite") : 0;
			$priority_post = $this->input->post('priority');
			$priority = ($priority_post !== false && $priority_post !== null && $priority_post !== '')
				? (int) $priority_post
				: 0;
			$store_data=$this->input->post("store_data");

			if (empty($store_data)) {
				$store_data = 'store';
			}

			if ($store_data !== "store" && $store_data !== "remove") {
				throw new Exception("Invalid value for store_data. Valid values are 'store', 'remove'");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			
			// Step 1: Upload file (multipart or completed resumable upload)
			$upload_result=$this->Editor_datafile_model->upload_create(
				$sid,
				$overwrite,
				$store_data,
				$this->get_api_user_id(),
				$upload_id === '' ? null : $upload_id,
				$this->collect_datafile_upload_metadata()
			);

			if (empty($upload_result['file_id'])) {
				throw new Exception("File upload succeeded but file_id was not returned");
			}

			$file_id = $upload_result['file_id'];

			// Step 2: Create import job
			$this->load->model('Job_queue_model');
			
			// Load job registry for validation
			require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';
			require_once APPPATH . 'libraries/Jobs/JobRegistry.php';
			
			$job_type = 'import_microdata';
			$payload = array(
				'project_id' => $sid,
				'file_id' => $file_id
			);
			
			// Validate job type exists
			if (!$this->Job_queue_model->is_valid_job_type($job_type)) {
				throw new Exception("Job type '{$job_type}' is not available");
			}
			
			// Validate payload using the job handler
			$handler = JobRegistry::getHandler($job_type);
			if ($handler) {
				$handler->validatePayload($payload);
			}
			
			// Enqueue the job
			$job_id = $this->Job_queue_model->enqueue(
				$job_type,
				$payload,
				$this->get_api_user_id(),
				$priority,
				3  // max_attempts
			);
			
			// Get the created job
			$job = $this->Job_queue_model->get($job_id);
			
			// Remove numeric ID from job object for API response
			$job_uuid = isset($job['uuid']) ? $job['uuid'] : null;
			$job_response = $job;
			unset($job_response['id']);

			$output=array(
				'status'=>'success',
				'message'=>'File uploaded and import job created successfully',
				'file_id'=>$file_id,
				'uuid'=>$job_uuid,
				'job'=>$job_response,
				'upload_result'=>$upload_result
			);
						
			$this->set_response($output, REST_Controller::HTTP_CREATED);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	
	/**
	 * 
	 *  Import basic metadata info for a data file (stata, csv, sav)
	 * 
	 * 
	 */
	/*function import_file_meta_get($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);

			if (!$datafile_path){
				throw new Exception("Data file not found");
			}

			//get file basic metadata [rows, columns, variable name and label]
			$response=$this->datautils->import_file_meta($datafile_path);

			$variable_import_result=null;
			if (isset($response['variables'])){
				//$variable_import_result=$this->Editor_variable_model->bulk_upsert($sid,$file_id,$response['variables']);
			}

			$output=array(
				'status'=>'success',
				'result'=>realpath($datafile_path),
				'variables_imported'=>$variable_import_result
			);
						
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'result'=>realpath($datafile_path),
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}*/

	

	function generate_summary_stats_queue_post($sid, $file_id)
	{
		return $this->generate_summary_stats_queue_get($sid,$file_id);
	}

	/**
	 * 
	 * Generate summary statistics for a data file and import into database
	 * 
	 * TODO: replace with generate_summary_stats_queue_post
	 * 
	 */
	function generate_summary_stats_queue_get($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
		
			$data_file_var_count=$this->Editor_datafile_model->get_file_varcount($sid,$file_id);//if not 0 then use CSV

			if ($data_file_var_count==0){
				$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);
			}else{
				$datafile_path=$this->Editor_datafile_model->get_file_csv_path($sid,$file_id);

				if (!file_exists($datafile_path)){
					$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);
				}
			}

			if (!$datafile_path){
				throw new Exception("Data file not found: ". basename($datafile_path));
			}

			$dict_params=$this->datautils->prepare_data_dictionary_params($sid,$file_id,$datafile_path);

			//queue job
			$api_response=$this->datautils->generate_summary_stats_queue($datafile_path,$dict_params);
			$status_code=isset($api_response['status_code']) ? $api_response['status_code'] : REST_Controller::HTTP_BAD_REQUEST;

			$output=array(
				'status'=>'success',
				'params'=>$dict_params,
				'file'=>realpath($datafile_path),
				'request'=> isset($api_response['request']) ? $api_response['request'] :'',
				'request_url'=>$api_response['request_url']
				//'job_id'=>$api_response['job_id']
			);
			$output=array_merge($output,$api_response['response']);
						
			$this->set_response($output, $status_code);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Generate summary statistics for a data file and import into database
	 * 
	 */
	function summary_stats_queue_status_get($sid,$file_id,$job_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());

			$api_response=$this->datautils->get_job_status($job_id);

			$api_http_status=isset($api_response['status_code']) ? $api_response['status_code'] : REST_Controller::HTTP_BAD_REQUEST;
			$upstream = isset($api_response['response']) && is_array($api_response['response']) ? $api_response['response'] : array();
			$job_status = isset($upstream['status']) ? $upstream['status'] : '';

			if ($api_http_status !== REST_Controller::HTTP_OK) {
				$msg = $this->_fastapi_job_error_message($upstream);
				$this->set_response(array(
					'status' => 'failed',
					'job_status' => 'failed',
					'message' => $msg,
					'variables_imported' => 0,
					'api_response' => $api_response,
				), $api_http_status >= 400 ? $api_http_status : REST_Controller::HTTP_BAD_GATEWAY);
				return;
			}

			if ($job_status === 'failed' || $job_status === 'error') {
				$msg = $this->_fastapi_job_error_message($upstream);
				$out = array(
					'status' => 'failed',
					'job_status' => $job_status,
					'message' => $msg,
					'variables_imported' => 0,
					'api_response' => $api_response,
				);
				if (isset($upstream['detail'])) {
					$out['detail'] = $upstream['detail'];
				}
				$this->set_response($out, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			if ($job_status !== 'done' && $job_status !== 'completed') {
				$this->set_response(array(
					'status' => 'success',
					'variables_imported' => 0,
					'job_status' => $job_status,
				), REST_Controller::HTTP_OK);
				return;
			}

			$variables_imported = $this->_import_summary_stats_dictionary($sid, $file_id, $job_id, $upstream);

			$this->set_response(array(
				'status' => 'success',
				'variables_imported' => $variables_imported,
				'job_status' => 'done',
			), REST_Controller::HTTP_OK);
		}
		catch(Throwable $e){
			$this->_summary_stats_import_cache_delete($job_id);
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}




	/**
	 * 
	 * Generate summary statistics for selected variables
	 * 
	 */
	function generate_summary_stats_variable_post($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$options=(array)$this->raw_json_input();

			if (!isset($options['var_names']) || !is_array($options['var_names'])){
				throw new Exception("Invalid var_names parameter");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);

			if (!$datafile_path){
				throw new Exception("Data file not found");
			}

			//get file basic metadata [rows, columns, variable name and label]
			$response=$this->datautils->generate_summary_stats_variable($datafile_path,$options);

			$variable_import_result=null;

			if (isset($response['rows'])){
				$datafile=$this->Editor_datafile_model->data_file_by_id($sid,$file_id);
				$this->Editor_datafile_model->update($datafile['id'],array('case_count'=>$response['rows']));
			}

			if (isset($response['variables'])){
				$variable_import_result=$this->Editor_variable_model->bulk_upsert($sid,$file_id,$response['variables']);
			}

			$output=array(
				'status'=>'success',
				'result'=>realpath($datafile_path),
				'variables_imported'=>$variable_import_result,
				'response'=>$response
			);
						
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 *  Import basic metadata info for a data file (stata, csv, sav)
	 * 
	 * 
	 */
	function generate_csv_get($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);

			if (!$datafile_path){
				throw new Exception("Data file not found");
			}

			//get file basic metadata [rows, columns, variable name and label]
			$response=$this->datautils->generate_csv($datafile_path);

			$output=array(
				'status'=>'success',
				'result'=>$response
			);
						
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * 
	 * Export data files
	 * 	- format:
	 * 		- csv
	 * 		- stata
	 * 		- spss
	 * 		- json
	 * 
	 */
	function export_datafile_queue_post($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$options=(array)$this->raw_json_input();

			if(!isset($options['format'])){
				throw new Exception("No value provided for `format` parameter");
			}

			$format=$options['format'];
			$export_options=isset($options['export_options']) && is_array($options['export_options']) ? $options['export_options'] : null;

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());

			$api_response=$this->datautils->export_datafile_queue($sid,$file_id,$format,$export_options);
			$status_code=$api_response['status_code'];

			$output=$api_response['response'];
			$output['request']=$api_response['request'];
			$output['status_code']=$api_response['status_code'];
			if (!empty($api_response['request']['output_filename'])) {
				$output['output_filename'] = $api_response['request']['output_filename'] . '.' . $format;
			}

			$this->set_response($output, $status_code);
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function generate_csv_queue_get($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);

			if (!$datafile_path){
				throw new Exception("Data file not found");
			}

			//get file basic metadata [rows, columns, variable name and label]
			$api_response=$this->datautils->generate_csv_queue($datafile_path);
			$status_code=$api_response['status_code'];
			$this->set_response($api_response['response'], $status_code);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function generate_csv_job_status_get($sid,$file_id,$job_id)
	{
		try{

			//get the output of the job
			$api_response=$this->datautils->get_job_status($job_id);

			$api_http_status=isset($api_response['status_code']) ? $api_response['status_code'] : REST_Controller::HTTP_BAD_REQUEST;
			$upstream = isset($api_response['response']) && is_array($api_response['response']) ? $api_response['response'] : array();
			$job_status = isset($upstream['status']) ? $upstream['status'] : '';

			if ($api_http_status !== REST_Controller::HTTP_OK) {
				$msg = $this->_fastapi_job_error_message($upstream);
				$this->set_response(array(
					'status' => 'failed',
					'job_status' => 'failed',
					'message' => $msg,
					'api_response' => $api_response,
				), $api_http_status >= 400 ? $api_http_status : REST_Controller::HTTP_BAD_GATEWAY);
				return;
			}

			if ($job_status === 'failed' || $job_status === 'error') {
				$msg = $this->_fastapi_job_error_message($upstream);
				$out = array(
					'status' => 'failed',
					'job_status' => $job_status,
					'message' => $msg,
					'api_response' => $api_response,
				);
				if (isset($upstream['detail'])) {
					$out['detail'] = $upstream['detail'];
				}
				$this->set_response($out, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$csv_file_path=$this->Editor_datafile_model->check_csv_exists($sid, $file_id);

			// Keep source file; only purge when store_data=0 (metadata-only)
			$datafile=$this->Editor_datafile_model->data_file_by_id($sid,$file_id);
			if ($datafile && isset($datafile['store_data']) && (int) $datafile['store_data'] === 0) {
				$this->Editor_datafile_model->cleanup($sid, $file_id);
			}

			$output=array(
				'status'=>'success',
				'api_response'=>$api_response,
				'job_status'=>$job_status,
				'csv_file'=>$csv_file_path ? basename($csv_file_path) : null
			);
						
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Sync CSV columns with metadata: remove from CSV any columns not in DB (in-place).
	 * 
	 * POST api/data/sync_csv_columns_job/{sid}/{file_id}
	 * Calls FastAPI remove-csv-columns-queue with same path for source and target; polls until done.
	 */
	function sync_csv_columns_job_post($sid = null, $file_id = null)
	{
		try {
			$exists = $this->Editor_model->check_id_exists($sid);
			if (!$exists) {
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid, 'edit', $this->api_user());

			if (!$file_id) {
				throw new Exception("Missing required parameter: file_id");
			}

			$datafile = $this->Editor_datafile_model->data_file_by_id($sid, $file_id);
			if (!$datafile) {
				throw new Exception("Data file not found");
			}

			$csv_path = $this->Editor_datafile_model->get_file_csv_path($sid, $file_id);
			if (!$csv_path || !file_exists($csv_path)) {
				throw new Exception("CSV file not found for this data file");
			}

			$diff = $this->Editor_datafile_model->get_columns_out_of_sync($sid, $file_id);
			$columns_to_remove = isset($diff['columns_to_remove_from_csv']) ? $diff['columns_to_remove_from_csv'] : array();

			if (empty($columns_to_remove)) {
				$this->set_response(array(
					'status' => 'success',
					'message' => 'Already in sync',
					'in_sync' => true
				), REST_Controller::HTTP_OK);
				return;
			}

			$api_response = $this->datautils->remove_csv_columns_queue($csv_path, $columns_to_remove, $csv_path);

			$status_code = isset($api_response['status_code']) ? $api_response['status_code'] : 500;
			$response = isset($api_response['response']) ? $api_response['response'] : array();

			if ($status_code !== 202) {
				$message = isset($response['detail']) ? $response['detail'] : (isset($response['message']) ? $response['message'] : 'FastAPI request failed');
				if (is_array($message)) {
					$message = json_encode($message);
				}
				$this->set_response(array(
					'status' => 'failed',
					'message' => $message
				), $status_code >= 400 ? $status_code : REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$job_id = isset($response['job_id']) ? $response['job_id'] : null;
			if (!$job_id) {
				$this->set_response(array(
					'status' => 'failed',
					'message' => 'FastAPI did not return job_id'
				), REST_Controller::HTTP_BAD_GATEWAY);
				return;
			}

			$poll_interval = 3;
			$max_wait_time = 30;
			$start_time = time();
			$completed = false;
			$result = null;
			$job_failed = false;
			$timed_out = false;
			$last_status_response = null;

			while ((time() - $start_time) < $max_wait_time) {
				$status_response = $this->datautils->get_job_status($job_id);
				$last_status_response = $status_response;
				$status_http = isset($status_response['status_code']) ? $status_response['status_code'] : 500;
				$job_body = isset($status_response['response']) ? $status_response['response'] : array();
				$job_status = isset($job_body['status']) ? $job_body['status'] : '';

				if ($status_http !== 200) {
					$this->set_response(array(
						'status' => 'failed',
						'message' => 'Failed to get job status'
					), REST_Controller::HTTP_BAD_GATEWAY);
					return;
				}

				if ($job_status === 'done' || $job_status === 'completed') {
					$completed = true;
					$result = $job_body;
					break;
				}

				if ($job_status === 'failed' || $job_status === 'error') {
					$job_failed = true;
					$this->set_response(array(
						'status' => 'failed',
						'job_failed' => true,
						'message' => isset($job_body['message']) ? $job_body['message'] : 'Job failed',
						'job_id' => $job_id,
						'api_response' => $last_status_response
					), REST_Controller::HTTP_OK);
					return;
				}

				sleep($poll_interval);
			}

			if (!$completed) {
				$timed_out = true;
				$out = array(
					'status' => 'pending',
					'timed_out' => true,
					'job_id' => $job_id,
					'message' => 'Job did not finish within ' . $max_wait_time . ' seconds'
				);
				if ($last_status_response !== null) {
					$out['api_response'] = $last_status_response;
				}
				$this->set_response($out, REST_Controller::HTTP_OK);
				return;
			}

			$output = array(
				'status' => 'success',
				'job_failed' => false,
				'timed_out' => false,
				'message' => 'CSV updated',
				'in_sync' => true
			);
			if (isset($result['data']['columns_removed'])) {
				$output['columns_removed'] = $result['data']['columns_removed'];
			}
			if (isset($result['data']['columns_requested_not_found'])) {
				$output['columns_requested_not_found'] = $result['data']['columns_requested_not_found'];
			}

			$this->set_response($output, REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Return job status for a given job id.
	 * On upstream errors (e.g. 400), returns structured response with details
	 */
	function job_status_get($job_id)
	{
		try {
			$api_response = $this->datautils->get_job_status($job_id);
		} catch (Exception $e) {
			$this->set_response([
				'status' => 'failed',
				'job_status' => 'failed',
				'message' => 'Could not reach job service.',
				'detail' => $e->getMessage()
			], REST_Controller::HTTP_BAD_REQUEST);
			return;
		}

		$api_http_status = isset($api_response['status_code']) ? $api_response['status_code'] : REST_Controller::HTTP_BAD_REQUEST;
		$upstream = isset($api_response['response']) ? $api_response['response'] : [];
		$job_status = isset($upstream['status']) ? $upstream['status'] : '';

		if ($api_http_status === REST_Controller::HTTP_OK) {
			if ($job_status === 'failed' || $job_status === 'error') {
				$msg = $this->_fastapi_job_error_message($upstream);
				$out = [
					'status' => 'failed',
					'job_status' => $job_status,
					'message' => $msg,
					'api_response' => $api_response,
				];
				if (isset($upstream['detail'])) {
					$out['detail'] = $upstream['detail'];
				}
				$this->set_response($out, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}
			$this->set_response([
				'status' => 'success',
				'api_response' => $api_response,
				'job_status' => $job_status
			], REST_Controller::HTTP_OK);
			return;
		}

		// Upstream returned 4xx/5xx: return formatted error with details
		$detail = isset($upstream['detail']) ? $upstream['detail'] : (isset($upstream['message']) ? $upstream['message'] : json_encode($upstream));
		if (is_array($detail)) {
			$detail = json_encode($detail);
		}
		$this->set_response([
			'status' => 'failed',
			'job_status' => $job_status ?: 'failed',
			'message' => 'Export job failed.',
			'detail' => $detail
		], $api_http_status);
	}


	/**
	 * 
	 * Replace/append data file
	 * 
	 * 
	 * 
	 * @file_type data
	 * 
	 **/ 
	function replace_datafile_post($sid=null, $file_id=null)
	{		
		$this->load->library("Datafile_update");

		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$datafile=$this->Editor_datafile_model->data_file_by_id($sid,$file_id);

			if (!$datafile){
				throw new Exception("Data file not found: ". $file_id);
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());

			//upload file to a temporary location
			$result=$this->Editor_datafile_model->temp_upload_file($sid);

			if (!isset($result['uploaded_path'])){
				throw new Exception("File upload failed");
			}

			$result=$this->datafile_update->update($sid, $file_id,$result['uploaded_path'], $this->get_api_user_id());
			
			$output=array(
				'status'=>'success',
				'result'=>$result
			);
						
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * Attach (or re-attach) an original source file (.dta/.sav) for a datafile.
	 * Validates columns against DB variables via FastAPI; does not regenerate CSV.
	 *
	 * POST /api/data/attach_source/{sid}/{file_id}
	 */
	function attach_source_post($sid=null, $file_id=null)
	{
		$this->load->library("Datafile_attach_source");

		try{
			$exists=$this->Editor_model->check_id_exists($sid);
			if(!$exists){
				throw new Exception("Project not found");
			}

			$datafile=$this->Editor_datafile_model->data_file_by_id($sid,$file_id);
			if (!$datafile){
				throw new Exception("Data file not found: ". $file_id);
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());

			$result=$this->Editor_datafile_model->temp_upload_file($sid);
			if (!isset($result['uploaded_path'])){
				throw new Exception("File upload failed");
			}

			$original_client_name = null;
			if (isset($_FILES['file']['name']) && $_FILES['file']['name'] !== '') {
				$original_client_name = $_FILES['file']['name'];
			} elseif (!empty($result['uploaded_file_name'])) {
				$original_client_name = $result['uploaded_file_name'];
			}

			$result=$this->datafile_attach_source->attach(
				$sid,
				$file_id,
				$result['uploaded_path'],
				$this->get_api_user_id(),
				false,
				$original_client_name
			);

			$output=array(
				'status'=>'success',
				'result'=>$result
			);
			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$response=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * Read CSV
	 * 
	 */
	public function read_csv_get($sid=null,$fileid=null)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$project_folder=$this->Editor_model->get_project_folder($sid);			
		
			if (!file_exists($project_folder)){
				throw new Exception('PROJECT_FOLDER_NOT_FOUND');
			}

			$project_folder=realpath($project_folder);

			//get filename by FID
			$datafile=$this->Editor_datafile_model->data_file_by_id($sid,$fileid);
			
			if (!$datafile){
				throw new Exception("DATAFILE_NOT_FOUND");
			}

			$filename=$datafile['file_name'];
			$csv_file_path=$project_folder.'/data/'.$filename.'.csv';

			/*if (!file_exists($data_file_path)){
				throw new Exception("DATA_FILE_NOT_FOUND: ".$data_file_path);
			}*/

			if (!file_exists($csv_file_path)){
				throw new Exception("CSV_FILE_NOT_FOUND: ".$csv_file_path);
			}

			$csv = Reader::createFromPath($csv_file_path, 'r');
			$csv->setHeaderOffset(0); //set the CSV header offset
			$offset=(int)$this->input->get("offset");
			$limit=(int)$this->input->get("limit");

			if ($limit <1 || $limit>100){
				$limit=100;
			}

			$stmt = Statement::create()
				->offset($offset)
				->limit($limit)
			;

			$records = $stmt->process($csv);
			/*foreach ($records as $record) {
				//do something here
			}*/

			$response=array(
				'csv'=>basename($csv_file_path),
				'total'=>$this->getCsvLinesCount($csv_file_path),
				//'total'=>'?',
				'offset'=>$offset,
				'limit'=>$limit,
				'records'=>$records
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

	private function getCsvLinesCount($file_path)
	{
		$file = new \SplFileObject($file_path, 'r');
		$file->seek(PHP_INT_MAX);

		return $file->key();
	}

	/**
	 * Comprehensive validation for export formats - checks both user_missings and value_labels
	 * 
	 * GET /api/data/validate_export/{sid}/{file_id}?format=dta&show_all_errors=true
	 * 
	 * Validates that both user_missings and value_labels are compatible with the specified export format
	 * 
	 * Query Parameters:
	 * - format: Export format (dta, sav)
	 * - show_all_errors: Show all errors instead of stopping on first (true/false, default: false)
	 * 
	 */
	function validate_export_get($sid, $file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$format = $this->input->get('format');						
			$show_all_errors = $this->input->get('show_all_errors') === 'true' || $this->input->get('show_all_errors') === '1';
			$stop_on_first_error = !$show_all_errors;

			$supported_formats = ['dta', 'sav'];
			if (!in_array($format, $supported_formats)) {
				throw new Exception("Unsupported format '{$format}'. Supported formats: " . implode(', ', $supported_formats));
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());

			$this->load->library("Datafile_export");
			$validation_result = $this->datafile_export->validate_datafile_export($sid, $file_id, $format, $stop_on_first_error);
			
			if ($validation_result['valid']) {
				$response = array(
					'status' => 'success',
					'message' => "Data file is valid for {$format} export",
					'data' => array(
						'file_id' => $file_id,
						'project_id' => $sid,
						'format' => $format,
						'validation_passed' => true,
						'variables_checked' => $validation_result['variables_checked'],
						'variables_with_missings' => $validation_result['variables_with_missings'],
						'variables_with_labels' => $validation_result['variables_with_labels'],
						'missing_value_errors' => array(),
						'value_label_errors' => array()
					)
				);
			} else {
				// Create summary error message
				$missing_value_count = count($validation_result['missing_value_errors']);
				$value_label_count = count($validation_result['value_label_errors']);
				$total_errors = $missing_value_count + $value_label_count;
				
				$summary_parts = array();
				if ($missing_value_count > 0) {
					$summary_parts[] = "{$missing_value_count} variable(s) with invalid missing values";
				}
				if ($value_label_count > 0) {
					$summary_parts[] = "{$value_label_count} variable(s) with invalid value labels";
				}
				
				$summary_message = "Export validation failed: " . implode(' and ', $summary_parts) . " for {$format} format.";
				
				$response = array(
					'status' => 'failed',
					'message' => $summary_message,
					'data' => array(
						'file_id' => $file_id,
						'project_id' => $sid,
						'format' => $format,
						'validation_passed' => false,
						'variables_checked' => $validation_result['variables_checked'],
						'variables_with_missings' => $validation_result['variables_with_missings'],
						'variables_with_labels' => $validation_result['variables_with_labels'],
						'error_summary' => array(
							'total_errors' => $total_errors,
							'missing_value_errors' => $missing_value_count,
							'value_label_errors' => $value_label_count
						),
						'missing_value_errors' => $validation_result['missing_value_errors'],
						'value_label_errors' => $validation_result['value_label_errors'],
						'all_errors' => $validation_result['errors']
					)
				);
			}
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$format = $this->input->get('format') ?: 'unknown';
			$response = array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'data' => array(
					'file_id' => $file_id,
					'project_id' => $sid,
					'format' => $format,
					'validation_passed' => false
				)
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Import dictionary variables in one request (internal chunked reads + batch DB writes).
	 */
	private function _import_summary_stats_dictionary($sid, $file_id, $job_id, array $upstream)
	{
		$this->_begin_summary_stats_import_limits();

		if (isset($upstream['data']['rows'])) {
			$datafile = $this->Editor_datafile_model->data_file_by_id($sid, $file_id);
			if ($datafile) {
				$this->Editor_datafile_model->update($datafile['id'], array('case_count' => $upstream['data']['rows']));
			}
		}

		$cache_path = $this->_summary_stats_import_cache_path($job_id);

		if (isset($upstream['data']['variables']) && is_array($upstream['data']['variables']) && !empty($upstream['data']['variables'])) {
			$this->_summary_stats_import_cache_write($job_id, $upstream['data']['variables']);
			unset($upstream['data']['variables']);
		}

		if (!is_file($cache_path)) {
			$this->_summary_stats_import_cache_delete($job_id);
			return 0;
		}

		try {
			$imported = $this->Editor_variable_model->import_dictionary_from_ndjson(
				$sid,
				$file_id,
				$cache_path,
				self::SUMMARY_STATS_IMPORT_CHUNK_SIZE
			);
		} finally {
			$this->_summary_stats_import_cache_delete($job_id);
		}

		return $imported;
	}

	/**
	 * Raise PHP limits for large summary-stats dictionary imports.
	 */
	private function _begin_summary_stats_import_limits()
	{
		ini_set('max_execution_time', '0');

		$current = ini_get('memory_limit');
		if ($current === '-1') {
			return;
		}

		$bytes = 0;
		if (preg_match('/^(\d+)([KMG])?$/i', trim((string)$current), $m)) {
			$bytes = (int)$m[1];
			$unit = isset($m[2]) ? strtoupper($m[2]) : '';
			if ($unit === 'K') {
				$bytes *= 1024;
			} elseif ($unit === 'M') {
				$bytes *= 1024 * 1024;
			} elseif ($unit === 'G') {
				$bytes *= 1024 * 1024 * 1024;
			}
		}

		if ($bytes > 0 && $bytes < 512 * 1024 * 1024) {
			ini_set('memory_limit', '512M');
		}
	}

	private function _summary_stats_import_cache_dir()
	{
		$dir = rtrim($this->Editor_model->get_temp_storage_path(), '/') . '/summary_stats_import';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		return $dir;
	}

	private function _summary_stats_import_cache_path($job_id)
	{
		$safe_id = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$job_id);
		return $this->_summary_stats_import_cache_dir() . '/' . $safe_id . '.ndjson';
	}

	private function _summary_stats_import_cache_meta_path($job_id)
	{
		return $this->_summary_stats_import_cache_path($job_id) . '.meta';
	}

	private function _summary_stats_import_cache_write($job_id, array $variables)
	{
		$path = $this->_summary_stats_import_cache_path($job_id);
		$fp = fopen($path, 'w');
		if ($fp === false) {
			throw new Exception('Failed to create summary stats import cache');
		}

		foreach ($variables as $variable) {
			$line = json_encode($variable);
			if ($line === false) {
				fclose($fp);
				throw new Exception('Failed to encode variable for import cache');
			}
			fwrite($fp, $line . "\n");
		}
		fclose($fp);

		file_put_contents(
			$this->_summary_stats_import_cache_meta_path($job_id),
			(string)count($variables)
		);
	}

	private function _summary_stats_import_cache_delete($job_id)
	{
		$path = $this->_summary_stats_import_cache_path($job_id);
		if (is_file($path)) {
			unlink($path);
		}
		$meta_path = $this->_summary_stats_import_cache_meta_path($job_id);
		if (is_file($meta_path)) {
			unlink($meta_path);
		}
	}

	/**
	 * User-facing message from FastAPI job body (after DataUtils normalization).
	 *
	 * @param array $body
	 * @return string
	 */
	private function _fastapi_job_error_message($body)
	{
		if (!is_array($body)) {
			return 'Job failed';
		}
		if (isset($body['message']) && $body['message'] !== '') {
			return $body['message'];
		}
		if (isset($body['detail'])) {
			if (is_string($body['detail'])) {
				return $body['detail'];
			}
			if (is_array($body['detail'])) {
				return json_encode($body['detail']);
			}
		}
		return 'Job failed';
	}


}
