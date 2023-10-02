<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Templates extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_template_model");
		
		$this->load->library("Form_validation");
		//$this->is_admin_or_die();
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
			array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
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

			$result=$this->Editor_template_model->duplicate_template($uid);

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
			$this->has_access($resource_='template_manager',$privilege='edit');

			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$options=$this->raw_json_input();
			$result=$this->Editor_template_model->update($uid,$options);

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


	function delete_post($uid=null)
	{		
		try{
			
			$this->has_access($resource_='template_manager',$privilege='delete');

			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$result=$this->Editor_template_model->delete($uid);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
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


	
}
