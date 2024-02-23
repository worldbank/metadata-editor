<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Audit_log
{
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Audit_log_model');
		//$this->ci->load->model('repository_model');

	}


	/**
	 * 
	 * Log an event
	 * 
	 * 
	 */
	function log_event($obj_type,$obj_id,$description)
	{
		$data=array(
			"obj_type"=>$obj_type,
			"obj_id"=>$obj_id,
			"user_id"=>$this->ci->session->userdata('user_id'),
			"description"=>$description
		);
		$this->ci->Audit_log_model->insert($data);
	}

}

