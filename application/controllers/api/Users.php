<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Users extends MY_REST_Controller
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
	function index_get($userId=null)
	{
		try{
			$this->is_project_sharing_enabled_or_die();

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
	 * 
	 * Search for suers
	 * 
	 */
	function search_get()
	{
		try{
			$this->is_project_sharing_enabled_or_die();
			$keywords=$this->get('keywords');

			if(!$keywords){
				throw new Exception("Missing parameter for `keywords`");
			}

			//$this->has_dataset_access('view');
			$result=$this->editor_owners_model->search_users($keywords);
			
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
	
}
