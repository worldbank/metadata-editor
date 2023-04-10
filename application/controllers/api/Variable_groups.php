<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Variable_groups extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_variable_groups_model");
		
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
	 * 
	 * Return all variable groups by a project
	 * 
	 */
	/**
	 * 
	 * list study data files
	 * 
	 */
	function index_get($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');
			
			$user_id=$this->get_api_user_id();
			$result=$this->Editor_variable_groups_model->select_all($sid,true);
			
			$response=array(
				'variable_groups'=>$result
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
	 * 
	 * Create variable groups for Project
	 * 
	 * @sid - Project ID
	 * 
	 */
	function index_post($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');

			$options=(array)$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['variable_groups']) || !is_array($options['variable_groups'])){
				throw new Exception("`variable_groups` is required and must be an array");
			}

			$result=$this->Editor_variable_groups_model->upsert($sid,$options['variable_groups']);

			$response=array(
				'status'=>'success',
				'variables'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
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



	function variable_create_post($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['variable'])){
				throw new Exception("`variable` is required");
			}

			$valid_data_files=$this->Editor_datafile_model->list($sid);
			
			//validate all variables
			$variable=$options['variable'];

			if (!isset($variable['file_id'])){
				throw new Exception("`file_id` is required");
			}

			if (!in_array($variable['file_id'],$valid_data_files)){
				throw new Exception("Invalid `file_id`: valid values are: ". implode(", ", $valid_data_files ));
			}

			if (!isset($variable['vid']) ){
				throw new Exception("`vid` is required");
			}
				
			$variable['fid']=$variable['file_id'];
			$this->Editor_model->validate_variable($variable);
			
			$variable['metadata']=$variable;
			$uid=$this->Editor_model->variable_insert($sid,$variable);

			if(!$uid){
				throw new Exception("Failed to create variable");
			}

			$variable=$this->Editor_model->variable($sid,$uid);
			
			$response=array(
				'status'=>'success',
				'variable'=>$variable
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
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
	 * 
	 * Delete variables by UID
	 * 
	 *  
	 */
	function variables_delete($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$options=(array)$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['uid']) || !is_array($options['uid'])){
				throw new Exception("`uid` is required and must be an array");
			}

			$this->load->model("Editor_variable_model");

			$result=$this->Editor_variable_model->delete($sid,$options['uid']);
			
			$response=array(
				'status'=>'success',
				'variables'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
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

	function variables_delete_post($sid=null)
	{
		return $this->variables_delete($sid);
	}

}
