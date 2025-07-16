<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Croissant extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->library("Croissant_Writer");
		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
	}

	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}
	
	function index_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$croissant_writer=$this->croissant_writer;
			$result=$croissant_writer->write_croissant($sid);
			$this->set_response($result, REST_Controller::HTTP_OK);
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
