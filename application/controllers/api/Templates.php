<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Templates extends MY_REST_Controller
{

	private $user_id=null;
	private $user=null;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_template_model");
		$this->load->model("Edit_history_model");
		
		$this->load->library("Form_validation");
		//$this->is_admin_or_die();
		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();

		$this->user_id=$this->get_api_user_id();
		$this->user=$this->api_user();
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
	 * Return all templates
	 * 
	 */
	function index_get($uid=null)
	{
		try{
			if($uid){
				return $this->template_get($uid);
			}

			$this->has_access($resource_='template_manager',$privilege='view');
			
			$result=$this->Editor_template_model->select_all();
			//array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
			$response=array(
				'status'=>'success',
				'total'=>count($result),
				'found'=>count($result),
				'templates'=>$result
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


	function core_template_get($data_type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$data_type){
				throw new Exception("Missing parameter for `data_type`");
			}

			if ($data_type=='microdata'){
				$data_type='survey';
			}

			$result=$this->Editor_template_model->get_core_template_json($data_type);			
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'template'=>$result
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

	function template_get($uid=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$result=$this->Editor_template_model->get_template_by_uid($uid);			
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'result'=>$result
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


	function list_get($type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			$result=$this->Editor_template_model->get_templates_by_type($type);
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'result'=>$result
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


	function template_parts_get($uid=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$result=$this->Editor_template_model->get_template_parts_by_uid($uid);
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'result'=>$result
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
	 * Create new template by duplicating a template
	 * @template_uid
	 * 
	 **/ 
	function duplicate_post($uid=null)
	{		
		try{			
			$this->has_access($resource_='template_manager',$privilege='duplicate');
			$result=$this->Editor_template_model->duplicate_template($uid, $this->user_id);

			if (!$result){
				throw new Exception("Failed to duplicate template");
			}

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function create_post()
	{		
		try{
			$this->has_access($resource_='template_manager',$privilege='edit');

			$options=$this->raw_json_input();
			$options['created_by']=$this->user_id;
			$options['changed_by']=$this->user_id;
			$result=$this->Editor_template_model->create_template($options);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function update_post($uid=null)
	{		
		try{			
			//$this->has_access($resource_='template_manager',$privilege='edit');
			$this->editor_acl->user_has_template_access($uid,$permission='edit',$this->user);

			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$options=$this->raw_json_input(); 			
			$options['changed_by']=$this->user_id;

			$result=$this->Editor_template_model->update($uid,$options);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function delete_post($uid=null)
	{		
		try{
			$this->has_access($resource_='template_manager',$privilege='delete');

			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$result=$this->Editor_template_model->delete($uid, $this->user_id);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
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
	 * Set default template
	 * @template_uid
	 * 
	 **/ 
	function default_post($type=null,$uid=null)
	{		
		try{			
			$this->has_access($resource_='template_manager',$privilege='admin');
			$result=$this->Editor_template_model->set_default_template($type,$uid);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function default_get($type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');
			$result=$this->Editor_template_model->get_default_template($type);

			if (!isset($result['template_uid'])){
				throw new Exception("Default template not found");
			}

			$template=$this->Editor_template_model->get_template_by_uid($result['template_uid']);
			
				
			$response=array(
				'status'=>'success',
				'result'=>$template
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

	function defaults_get($type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');
			$result=$this->Editor_template_model->get_all_default_templates();
				
			$response=array(
				'status'=>'success',
				'result'=>$result
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
	 * Share template with user
	 * 
	 * @options JSON array
	 * [
	 * 	{
	 * 		"template_id": "template id",
	 * 		"user_id": "user id",
	 * 		"permissions": "view|edit|admin"
	 * 	}
	 * ]
	 * 
	 */
	function share_post()
	{		
		try{
			
			$options=$this->raw_json_input();

			if (!is_array($options)){
				throw new Exception("Invalid input: must be an array");
			}

			foreach($options as $option){
				if (!isset($option['template_uid'])){
					throw new Exception("Missing parameter: template_uid");
				}

				$this->editor_acl->user_has_template_access($option['template_uid'],$permission='admin',$this->user);	
			}

			$result=$this->Editor_template_model->share_template($options, $this->user_id);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function share_get($uid)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			$result=$this->Editor_template_model->template_users($uid);
				
			$response=array(
				'status'=>'success',
				'users'=>$result
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


	function remove_access_post()
	{
		try{
			$options=$this->raw_json_input();

			if (!isset($options['template_uid'])){
				throw new Exception("Missing parameter: UID");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

			$this->editor_acl->user_has_template_access($options['template_uid'],$permission='admin',$this->user);
			$result=$this->Editor_template_model->unshare_template($options['template_uid'], $options['user_id']);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
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
	 * Revision history for a template
	 * 
	 */
	function revisions_get($uid=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$result=$this->Editor_template_model->get_template_revision_history($uid);	
			array_walk($result['history'], 'unix_date_to_gmt',array('created'));
			
			$response=array(
				'status'=>'success',
				//'total'=>count($result),
				//'found'=>count($result),
				'data'=>$result
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
	
}
