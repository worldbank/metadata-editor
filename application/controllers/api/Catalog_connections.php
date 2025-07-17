<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Catalog_connections extends MY_REST_Controller
{
	private $api_user;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Catalog_connections_model");
		$this->load->library("Editor_acl");
		$this->load->model("Audit_log_model");
		$this->load->library("Audit_log");
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
		$this->api_user_id=$this->get_api_user_id();
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
	 * List catalog connections by current logged-in user
	 * 
	 */
	function index_get()
	{
		try{
			$connections=$this->Catalog_connections_model->get_connections($this->api_user_id);
			
			$response=array(
				'status'=>'success',
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
	function index_post()
	{
		try{
			$options=$this->raw_json_input();
			$options['user_id']=$this->api_user_id;

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
	 * Update catalog connection
	 * 
	 */
	function update_post()
	{
		try{
			$options=$this->raw_json_input();
			$options['user_id']=$this->api_user_id;

			// Validate that catalog connection exists and belongs to user
			if (!isset($options['id'])){
				throw new Exception("Catalog ID is required");
			}

			$result=$this->Catalog_connections_model->update($options['id'],$options);
			
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
	 * Delete catalog connection
	 * 
	 */
	function delete_post()
	{
		try{
			$catalog_id=$this->input->post('catalog_id');

			if (!isset($catalog_id)){
				throw new Exception("Catalog ID is required");
			}

			$result=$this->Catalog_connections_model->delete($catalog_id, $this->api_user_id);
			
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
	 * Get single catalog connection by ID
	 * 
	 */
	function single_get($id=null)
	{
		try{
			if (!$id){
				throw new Exception("Catalog ID is required");
			}

			$connection=$this->Catalog_connections_model->get_connection($this->api_user_id, $id);
			
			if (!$connection){
				throw new Exception("Catalog connection not found");
			}

			//remove api_key from response
			unset($connection['api_key']);

			$response=array(
				'status'=>'success',
				'connection'=>$connection
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