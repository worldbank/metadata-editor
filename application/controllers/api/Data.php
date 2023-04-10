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
	private $DataApiUrl; //'http://localhost:2121';

	//temporary storage for creating files via data api
	private $DataStoragePath;	

	public function __construct()
	{
		parent::__construct();
		require_once 'modules/guzzle/vendor/autoload.php';

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
		$this->DataStoragePath=$this->config->item('data_storage_path', 'editor');
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
	public function status_get($type=NULL)
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

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$result=$this->Editor_datafile_model->upload_create($sid,$overwrite);

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
	function import_file_meta_get($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);

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
	}

	/**
	 * 
	 * Generate summary statistics for a data file and import into database
	 * 
	 */
	function generate_summary_stats_get($sid,$file_id)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
			$datafile_path=$this->Editor_datafile_model->get_file_path($sid,$file_id);

			//get file basic metadata [rows, columns, variable name and label]
			$response=$this->datautils->generate_summary_stats($datafile_path);

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
				'variables_imported'=>$variable_import_result
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

			//get file basic metadata [rows, columns, variable name and label]
			$response=$this->datautils->generate_summary_stats_variable($datafile_path,$options['var_names']);

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
				'variables_imported'=>$variable_import_result
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


}
