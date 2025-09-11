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
	 * 
	 * upload data file
	 * @file_type data
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

			if ($store_data !== "store" && $store_data !== "remove") {
				throw new Exception("Invalid value for store_data. Valid values are 'store', 'remove'");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$result=$this->Editor_datafile_model->upload_create(
				$sid,
				$overwrite,
				$store_data,
				$this->get_api_user_id()
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

			//get the output of the job
			$api_response=$this->datautils->get_job_status($job_id);

			$api_http_status=isset($api_response['status_code']) ? $api_response['status_code'] : REST_Controller::HTTP_BAD_REQUEST;
			$job_status=isset($api_response['response']['status']) ? $api_response['response']['status'] : '';

			if (!$api_http_status==REST_Controller::HTTP_OK){
				throw new Exception("Job failed");
			}

			$variable_import_result=[];

			if (isset($api_response['response']['data']['rows'])){
				$datafile=$this->Editor_datafile_model->data_file_by_id($sid,$file_id);
				$this->Editor_datafile_model->update($datafile['id'],array('case_count'=>$api_response['response']['data']['rows']));
			}

			if (isset($api_response['response']['data']['variables'])){
				$variable_import_result=$this->Editor_variable_model->bulk_upsert_dictionary($sid,$file_id,$api_response['response']['data']['variables']);
			}

			$output=array(
				'status'=>'success',
				//'result'=>realpath($datafile_path),
				'variables_imported'=>count($variable_import_result),
				'job_status'=>$job_status				
				#'variables'=>$response['variables']
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

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());

			$api_response=$this->datautils->export_datafile_queue($sid,$file_id,$format);
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
			$job_status=isset($api_response['response']['status']) ? $api_response['response']['status'] : '';

			if (!$api_http_status==REST_Controller::HTTP_OK){
				throw new Exception("Job failed");
			}

			$csv_file_path=$this->Editor_datafile_model->check_csv_exists($sid, $file_id);

			if ($csv_file_path){
				$cleanup_result = $this->Editor_datafile_model->cleanup($sid, $file_id);
				
				$datafile=$this->Editor_datafile_model->data_file_by_id($sid,$file_id);
				if ($datafile){
					$this->Editor_datafile_model->update($datafile['id'],array('file_physical_name'=>basename($csv_file_path)));
				}
			}

			$output=array(
				'status'=>'success',
				'api_response'=>$api_response,
				'job_status'=>$job_status,
				'csv_file'=>basename($csv_file_path)
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
	 * Return Job status for a given job id
	 */
	function job_status_get($job_id)
	{
		try{
			$api_response=$this->datautils->get_job_status($job_id);

			$api_http_status=isset($api_response['status_code']) ? $api_response['status_code'] : REST_Controller::HTTP_BAD_REQUEST;
			$job_status=isset($api_response['response']['status']) ? $api_response['response']['status'] : '';

			if (!$api_http_status==REST_Controller::HTTP_OK){
				throw new Exception("Job failed");
			}

			$output=array(
				'status'=>'success',
				'api_response'=>$api_response,
				'job_status'=>$job_status				
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

			$result=$this->datafile_update->update($sid, $file_id,$result['uploaded_path']);
			
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
	 * Validate value labels for export formats
	 * 
	 * GET /api/data/validate_value_labels/{sid}/{file_id}?format=dta&show_all_errors=true
	 * 
	 * Validates that value labels are compatible with the specified export format
	 * 
	 * Query Parameters:
	 * - format: Export format (dta, sav)
	 * - show_all_errors: Show all errors instead of stopping on first (true/false, default: false)
	 * 
	 */
	function validate_value_labels_get($sid, $file_id)
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
			$validation_result = $this->datafile_export->validate_datafile_value_labels($sid, $file_id, $format, $stop_on_first_error);
			
			if ($validation_result['valid']) {
				$response = array(
					'status' => 'success',
					'message' => "Value labels are valid for {$format} export",
					'data' => array(
						'file_id' => $file_id,
						'project_id' => $sid,
						'format' => $format,
						'validation_passed' => true,
						'variables_checked' => $validation_result['variables_checked'],
						'variables_with_labels' => $validation_result['variables_with_labels']
					)
				);
			} else {
				$error_messages = array();
				foreach ($validation_result['errors'] as $error) {
					$error_messages[] = "Variable '{$error['variable_name']}': {$error['error']}";
				}
				$combined_error = implode('; ', $error_messages);
				
				$response = array(
					'status' => 'failed',
					'message' => $combined_error,
					'data' => array(
						'file_id' => $file_id,
						'project_id' => $sid,
						'format' => $format,
						'validation_passed' => false,
						'variables_checked' => $validation_result['variables_checked'],
						'variables_with_labels' => $validation_result['variables_with_labels'],
						'errors' => $validation_result['errors']
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
					'validation_passed' => false,
					'variables_checked' => 0,
					'variables_with_labels' => 0
				)
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

}
