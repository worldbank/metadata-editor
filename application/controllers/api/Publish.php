<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Publish extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Catalog_connections_model");
		$this->load->model("Editor_resource_model");
		$this->load->model("Editor_publish_model");
		$this->load->model("Collection_model");
		
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

	
	


	//catalogs connections for direct publishing from the editor

	/**
	 * 
	 * list catalog connections by current logged-in user
	 * 
	 */
	function catalog_connections_get()
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();

			if (!$user_id)
			{
				throw new Exception("User-login-required");
			}

			$connections=$this->Catalog_connections_model->get_connections($user_id);
			
			$response=array(
				'connections'=>$connections
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
	 * Create new catalog connection
	 * 
	 */
	function catalog_connections_post()
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$options=$this->raw_json_input();
			$options['user_id']=$user_id;

			$result=$this->Catalog_connections_model->create($options);
			
			$response=array(
				'status'=>$result
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
	 * Publish to catalog
	 * 
	 */
	function index_post($sid=null,$catalog_connection_id=null,$options=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=$this->Editor_publish_model->publish_to_catalog($sid,$user_id,$catalog_connection_id,$options);			
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

	function thumbnail_post($sid=null,$catalog_connection_id=null,$options=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=array(
				'status'=>'success',
				'result'=>$this->Editor_publish_model->publish_thumbnail($sid,$user_id,$catalog_connection_id,$options)
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

	function external_resources_post($sid,$connection_id=null, $options=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=$this->Editor_publish_model->publish_external_resources($sid,$user_id,$connection_id,$options);			
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

	function external_resource_post($sid)
	{
		/*let formData={
			"overwrite": "no",
			"resource_id": resource.id,
			"sid": this.ProjectID,
			"catalog_id": this.catalog
		}*/

		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			if (!isset($options['resource_id'])){
				throw new Exception("Missing resource_id");
			}

			if (!isset($options['catalog_id'])){
				throw new Exception("Missing catalog_id");
			}

			if (!isset($options['overwrite'])){
				$options['overwrite']='no';
			}

			$response=$this->Editor_publish_model->publish_external_resource($sid,$user_id,$options['catalog_id'],$options['resource_id'],$options['overwrite']);
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

	function external_resources_files_post($sid,$connection_id=null, $options=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=$this->Editor_publish_model->publish_external_resources_files($sid,$user_id,$connection_id,$options);			
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

	function files_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			//$this->editor_acl->user_has_project_access($sid,$permission='view');

			$result=$this->Editor_resource_model->get_resources_uploaded_files($sid);

			$output=array(
				'files'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


}
