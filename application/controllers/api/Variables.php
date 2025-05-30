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
		$this->api_user=$this->api_user();
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
			$this->editor_acl->user_has_project_access($sid,$permission='view', $this->api_user);
			$user_id=$this->get_api_user_id();        			
			$variable_detailed=(int)$this->input->get("detailed");

			$survey_variables=$this->Editor_variable_model->select_all($sid,$file_id,$variable_detailed);
			$this->update_variable_weight_info($sid,$survey_variables);
			
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
	 * Get single variable by UID or variable name
	 * 
	 * @uid - variable UID
	 * @name - variable name
	 * @file_id - file ID [required for name]
	 * 
	 */
	function single_get($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);
			$user_id=$this->get_api_user_id();

			$uid=(int)$this->input->get("uid");
			$name=$this->input->get("name");
			$file_id=$this->input->get("file_id");

			if (!$uid && !$name){
				throw new Exception("Invalid `uid` or `name` parameter");
			}

			if ($uid){
				$variable=$this->Editor_variable_model->variable($sid,$uid, $variable_detailed=true);
			}
			else{
				if (!$file_id){
					throw new Exception("Invalid `file_id` parameter");
				}

				$variable=$this->Editor_variable_model->variable_by_name($sid,$file_id,$name, $variable_detailed=true);
			}
						
			$response=array(
				'variable'=>$variable
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
	 * Get all key variables by project or data file
	 * 
	 * @sid - project ID
	 * @file_id - (optional) file ID
	 * 
	 * 
	 * 
	 */
	function key_get($sid=null,$file_id=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);			

			$survey_variables=$this->Editor_variable_model->key_variables($sid,$file_id);
			$this->update_variable_weight_info($sid,$survey_variables);
			
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

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);
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
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user);
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

				if (!isset($variable['name']) || empty($variable['name'])){
					throw new Exception("`name` is required");
				}

				$variable['file_id']=$variable['fid'];
				$variable_info=$this->Editor_variable_model->variable_by_name($sid,$variable['file_id'],$variable['name'],false);
				$this->Editor_model->validate_variable($variable);
				$variable['metadata']=$variable;
		
				if($variable_info){	
					$this->Editor_variable_model->update($sid,$variable_info['uid'],$variable);
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
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user);
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


	//fix variable-weight from VID to UID
	private function update_variable_weight_info($sid,&$variables)
    {
        $variable_vid_uids=$this->Editor_variable_model->vid_uid_list($sid);

        foreach($variables as $idx=>$variable)
        {
            //if variable var_wgt_id is set
            if (isset($variable['var_wgt_id']))
            {
                if (isset($variable_vid_uids[$variable['var_wgt_id']])){
                    $variables[$idx]['var_wgt_id']=$variable_vid_uids[$variable['var_wgt_id']];
                }
            }
        }
    }


	/**
	 * 
	 * Export variables as CSV
	 * 
	 * @sid - project ID
	 * @fid - file ID
	 * 
	 * Note: Exports only a selective list of variable fields
	 * 
	 */
	function export_csv_get($sid=null, $fid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view', $this->api_user);
			$user_id=$this->get_api_user_id();        						

			//$survey_variables=$this->Editor_variable_model->select_all($sid,$file_id,$variable_detailed=true);
			//$this->update_variable_weight_info($sid,$survey_variables);

			$this->load->library("Variables_transform");
			$response=$this->variables_transform->variables_to_csv($sid,$fid);
			
			/*$response=array(
				'variables'=>$survey_variables
			);*/

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

	
}
