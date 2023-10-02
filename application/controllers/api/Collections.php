<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Collections extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Collection_model");
		$this->load->model("Collection_access_model");
		$this->load->library("Form_validation");
		
		$this->is_authenticated_or_die();
	}

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
	 * Return all Collections
	 * 
	 */
	function index_get($uid=null)
	{
		try{
			if($uid){
				return $this->single_get($uid);
			}

			$this->has_access($resource_='collection',$privilege='view');
			$result=$this->Collection_model->select_all();
			array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
			$response=array(
				'status'=>'success',
				'total'=>count($result),
				'found'=>count($result),
				'collections'=>$result
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

	function single_get($id=null)
	{
		try{
			$this->has_access($resource_='collection',$privilege='view');
			$result=$this->Collection_model->select_single($id);

			if(!$result){
				throw new Exception("COLLECTION_NOT_FOUND");
			}

			array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
			$response=array(
				'status'=>'success',
				'collection'=>$result
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
	 * Create new collection
	 * 
	 */	
	function index_post()
	{		
		try{
			$this->has_access($resource_='collection',$privilege='edit');
			$options=$this->raw_json_input();
			$options['created_by']=$this->session->userdata('user_id');
			$options['changed_by']=$this->session->userdata('user_id');
			$options['created']=time();
			$options['changed']=time();
			$result=$this->Collection_model->insert($options);

			$output=array(
				'status'=>'success',
				'collection'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function update_post($id=null)
	{		
		try{
			if (!$id){
				throw new Exception("Missing parameter: ID");
			}

			$this->has_access($resource_='collection',$privilege='edit');
			$options=$this->raw_json_input();			
			$options['changed_by']=$this->session->userdata('user_id');
			$options['changed']=time();
			$result=$this->Collection_model->update($id,$options);

			$output=array(
				'status'=>'success',
				'Collection'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function delete_post($id=null)
	{		
		try{
			if (!$id){
				throw new Exception("Missing parameter: ID");
			}
			
			$this->has_access($resource_='collection',$privilege='delete');
			$result=$this->Collection_model->delete($id);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function add_projects_post()
	{		
		try{
			$this->has_access($resource_='collection',$privilege='edit');
			$options=$this->raw_json_input();			

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['projects'])){
				throw new Exception("Missing parameter: projects");
			}
			
			$result=$this->Collection_model->add_projects($options['collection_id'], $options['projects']);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function remove_project_post($collection_id=null,$project_id=null)
	{		
		try{
			$this->has_access($resource_='collection',$privilege='edit');

			if (!$collection_id){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!$project_id){
				throw new Exception("Missing parameter: project_id");
			}

			$result=$this->Collection_model->remove_project($collection_id, $project_id);

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
	 * 
	 * Return all users with access to a collection
	 * 
	 */
	function user_access_get($collection_id=null)
	{
		try{
			$this->has_access($resource_='collection',$privilege='view');

			if (!$collection_id){
				throw new Exception("Missing parameter: collection ID");
			}

			$result=$this->Collection_access_model->select_all($collection_id);

			$output=array(
				'status'=>'success',
				'users'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * Add user access to collection
	 * 
	 */
	function user_access_post()
	{
		try{
			$options=$this->raw_json_input();
			$this->has_access($resource_='collection',$privilege='edit');

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

			if (!isset($options['permissions'])){
				throw new Exception("Missing parameter: permissions");
			}

			$result=$this->Collection_access_model->insert($options);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){

			$output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function remove_user_access_post()
	{
		try{
			$this->has_access($resource_='collection',$privilege='edit');

			$options=$this->raw_json_input();

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

			$result=$this->Collection_access_model->delete_user($options['collection_id'],$options['user_id']);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	
}
