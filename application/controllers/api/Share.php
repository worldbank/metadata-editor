<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Share extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model("editor_owners_model");
		
		//$this->load->library("Dataset_manager");
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
	 * Return all users
	 * 
	 */
	function users_get($userId=null)
	{
		try{
			if($userId){
				return $this->user_get($userId);
			}

			//$this->has_dataset_access('view');
			$result=$this->editor_owners_model->list_users();
			
			$response=array(
				'status'=>'success',
				'total'=>count($result),
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


	function user_get($userId=null)
	{
		try{
			//$this->has_dataset_access('view',$sid);

			if(!$userId){
				throw new Exception("Missing parameter for `userID`");
			}

			$result=$this->editor_owners_model->user_by_id($userId);
				
			if(!$result){
				throw new Exception("USER_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'user'=>$result
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
	 * Get project owner
	 * 
	 */
	function project_owner_get($sid=null)
	{
		try{
			//$this->has_dataset_access('view',$sid);

			if(!$sid){
				throw new Exception("Missing parameter `sid`");
			}

			$result=$this->editor_owners_model->get_project_owner($sid);			
				
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
	 * Get all owners of a project
	 * 
	 */
	function list_get($sid=null)
	{
		try{
			//$this->has_dataset_access('view',$sid);

			if(!$sid){
				throw new Exception("Missing parameter  `sid`");
			}

			$result=$this->editor_owners_model->select_all($sid);
				
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


	/**
	 * 
	 * Add a new owner to a project
	 * 
	 */
	function index_post($sid=null, $userId=null)
	{
		try{
			//$this->has_dataset_access('edit',$sid);

			if(!$sid){
				throw new Exception("Missing parameter `sid`");
			}

			if(!$userId){
				throw new Exception("Missing parameter `userId`");
			}

			$options=$this->raw_json_input();
			$permissions='view';

			if (isset($options['permissions'])){
				$permissions=$options['permissions'];
			}

			$result=$this->editor_owners_model->add($sid,$userId,$permissions);
			
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
	 * Remove a member from a project
	 * 
	 */
	function index_delete($sid=null, $userId=null)
	{
		try{
			//$this->has_dataset_access('edit',$sid);

			if(!$sid){
				throw new Exception("Missing parameter `sid`");
			}

			if(!$userId){
				throw new Exception("Missing parameter `userId`");
			}

			$result=$this->editor_owners_model->delete($sid,$userId);
			
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

	function delete_post($sid=null, $userId=null)
	{
		return $this->index_delete($sid,$userId);
	}
	
}
