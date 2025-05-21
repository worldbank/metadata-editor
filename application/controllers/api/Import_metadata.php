<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

/**
 * 
 * Import metadata into existing projects
 * 
 */
class Import_metadata extends MY_REST_Controller
{
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
	
	/**
	 * 
	 * Import JSON, XML metadata into an existing project
	 * 
	 * 
	 */
	function index_post($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$project=$this->Editor_model->get_basic_info($sid);

			if(!$project){
				throw new Exception("Project not found");
			}

			$user_id=$this->get_api_user_id();
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			
			$options=array();
			$options['changed_by']=$user_id;
			$options['changed']=date("U");			
			
			$allowed_file_types="json|xml";
			$uploaded_filepath=$this->Editor_resource_model->upload_temporary_file($allowed_file_types,$file_field_name='file',$temp_upload_folder=null);

			if (!file_exists($uploaded_filepath)){
				throw new Exception("Failed to upload file");
			}

			$file_info=pathinfo($uploaded_filepath);
			$file_ext=strtolower($file_info['extension']);

			$result=$file_info;

			if ($file_ext=='xml'){
				if ($project['type']=='survey'){
					$this->load->library("Editor_partial_import");
					$import_options=$this->input->post("options");
					$import_options=explode(",",$import_options);
					$result=$this->editor_partial_import->import_ddi($sid, $uploaded_filepath,$options,$import_options);
				}
			}else{
				$this->load->library('ImportJsonMetadata');
				$result=$this->importjsonmetadata->import($sid,$uploaded_filepath);
			}

			$output=array(
				'status'=>'success',
				'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(ValidationException $e){
			$error_output=array(
				'message'=>'VALIDATION_ERROR',
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$output=array(
				'status'=>'error',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
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
