<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Variables extends MY_REST_Controller
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
	 * List dataset variables
	 * 
	 */
	function index_get($sid=null,$file_id=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$user_id=$this->get_api_user_id();        			
			$variable_detailed=(int)$this->input->get("detailed");
			$survey_variables=$this->Editor_variable_model->select_all($sid,$file_id,$variable_detailed);
			
			$response=array(
				'variables'=>$survey_variables
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

	function by_name_post($sid=null,$file_id=null)
	{
		try{

			$options=(array)$this->raw_json_input();

			if (!isset($options['var_names']) || !is_array($options['var_names'])){
				throw new Exception("Invalid var_names parameter");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$user_id=$this->get_api_user_id();
			
			$variable_detailed=1;//(int)$this->input->get("detailed");
			$survey_variables=$this->Editor_variable_model->variables_by_name($sid,$file_id,$options['var_names'],$variable_detailed);
			
			$response=array(
				'variables'=>$survey_variables
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
	 * Create variables for Datasets
	 * @idno - dataset IDNo
	 * @merge_metadata - true|false 
	 * 	- true = partial update metadata 
	 *  - false = replace all metadata with new
	 */
	function index_post($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$options=(array)$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			//check if a single variable input is provided or a list of variables
			$key=key($options);

			//convert to list of a list
			if(!is_numeric($key)){
				$tmp_options=array();
				$tmp_options[]=$options;
				$options=null;
				$options=$tmp_options;
			}

			$valid_data_files=$this->Editor_datafile_model->list($sid);
			
			//validate all variables
			foreach($options as $key=>$variable){

				if (!isset($variable['fid'])){
					throw new Exception("`fid` is required");
				}

				if (!in_array($variable['fid'],$valid_data_files)){
					throw new Exception("Invalid `fid`: valid values are: ". implode(", ", $valid_data_files ));
				}

				if (!isset($variable['vid'])){
					throw new Exception("`vid` is required");
				}

				$variable['file_id']=$variable['fid'];

				if (isset($variable['uid'])){					
					//check if variable already exists
					$variable_info=$this->Editor_variable_model->variable($sid,$variable['uid']);
				}

				//$this->Editor_model->validate_variable($variable);
				$variable['metadata']=$variable;
		
				if($variable_info){	
					$this->Editor_variable_model->update($sid,$variable['uid'],$variable);
				}
				else{						
					$this->Editor_variable_model->insert($sid,$variable);
				}

				$result[]=$variable['vid'];
			}

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



	function create_post($sid=null)
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
			$this->Editor_variable_model->validate_variable($variable);
			
			$variable['metadata']=$variable;
			$uid=$this->Editor_variable_model->insert($sid,$variable);

			if(!$uid){
				throw new Exception("Failed to create variable");
			}

			$variable=$this->Editor_variable_model->variable($sid,$uid);
			
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
	function index_delete($sid=null)
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

	function delete_post($sid=null)
	{
		return $this->index_delete($sid);
	}



	function order_post($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['sorted_uid']) || !is_array($options['sorted_uid'])){
				throw new Exception("`sorted_uid` is required");
			}

			$result=$this->Editor_variable_model->set_sort_order($sid,$options['sorted_uid']);
			
			$response=array(
				'status'=>'success',
				'result'=>$result
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
	
}
