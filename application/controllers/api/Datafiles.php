<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Datafiles extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_datafile_model");
		$this->load->model("Editor_variable_model");
		
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
	 * list study data files
	 * 
	 */
	function index_get($id=null)
	{
		try{
			$this->editor_acl->user_has_project_access($id,$permission='view');
			
			$user_id=$this->get_api_user_id();
			$survey_datafiles=$this->Editor_datafile_model->select_all($id,true);
			
			$response=array(
				'datafiles'=>$survey_datafiles
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
	


	function download_get($sid=null,$fid=null,$type=null)
	{
		try{			
			if (!$sid || !$fid){
				throw new Exception("Missing required parameters");
			}

			$this->load->helper("download");
			$valid_types=array('original','csv');

			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$files=$this->Editor_datafile_model->get_files_info($sid,$fid);

			if (!$type || !in_array($type,$valid_types)){
				$type='original';
			}

			if (!isset($files[$type]['filepath'])){
				throw new Exception("File not found");
			}

			$file_path=$files[$type]['filepath'];

			if (file_exists($file_path)){				
				force_download2($file_path);
			}
			else{
				throw new Exception("File not found");
			}
			
		}
		catch(Exception $e){
			$error=array(
				'error'=>$e->getMessage()
			);
			$this->set_response($error, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
}
