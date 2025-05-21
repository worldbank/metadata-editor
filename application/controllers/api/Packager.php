<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

/**
 * 
 * Package project
 * 
 */
class Packager extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		
		$this->load->model("Editor_model");
		$this->load->library("Editor_acl");		
		$this->is_authenticated_or_die();
	}


	/**
	 * 
	 * 
	 * Generate package for a project
	 */
	function generate_post($sid)
	{
		//json
		//export project level metadata as json
		//export external resources metadata as json
		//create project zip

		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$this->load->library("ProjectPackage");
			$zip_path=$this->projectpackage->prepare_package($sid);

			$response=array(
				'status'=>'success',
				'package'=>$zip_path
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
	

	function download_zip_get($sid,$generate=0)
	{
		set_time_limit(0);
		
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$this->load->library('zip');
			$this->load->library("ProjectPackage");

			$path = $this->Editor_model->get_project_folder($sid);
			$project=$this->Editor_model->get_basic_info($sid);
			$zip_path=$path.'/'.$project['idno'].'.zip';

			if ($generate==1){				
				$zip_path=$this->projectpackage->prepare_package($sid);
			}

			if (file_exists($zip_path)){
				$this->load->helper('download');
				force_download2($zip_path);
				die();
			}
			else {
				throw new Exception("Zip file not found");
			}
		}
		catch(Exception $e){
			show_error($e->getMessage(),500);
			die();
		}
	}

	function generate_zip_get($sid)
	{		
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$this->load->library("ProjectPackage");

			$zip_path=$this->projectpackage->generate_zip($sid);

			$response=array(
				'status'=>'success',
				'zip'=>$zip_path
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



	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}
	
}
