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
	function log_event($obj_type,$obj_id,$action, $metadata=NULL, $user_id=NULL)
	{
		//validate metadata is json
		if($metadata != NULL){
			$metadata=json_encode($metadata);
		}

		if ($user_id == NULL){
			$user_id = $this->ci->session->userdata('user_id');
		}

		$data=array(
			"obj_type"=>$obj_type,
			"obj_id"=>$obj_id,
			"user_id"=>$user_id,
			"action_type"=>$action,
			"metadata"=>$metadata
		);

		try{
			$this->ci->Audit_log_model->insert($data);
		}
		catch(Exception $e){
			log_message('error', 'audit_log:: failed to log_event '.$e->getMessage());
		}		
	}

}

