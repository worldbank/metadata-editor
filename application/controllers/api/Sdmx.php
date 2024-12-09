<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Sdmx extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->library("SDMX/MsdWriter");
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
	
	function msd_get()
	{
		try{
			$template_uid=$this->get('template_uid');
			$schema_name=$this->get('schema');

			if ($template_uid){
				return $this->template_msd_get($template_uid);
			}
			else if ($schema_name){
				return $this->schema_msd_get();
			}
			else{
				throw new Exception("MISSING_PARAMETERS `template_uid` or `schema`");
			}
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function schema_msd_get()
	{
		try{
			$json_schema_path = 'application/schemas/timeseries-schema.json';
			$data = $this->msdwriter->json_schema_to_array($json_schema_path);			
			$xml = $this->msdwriter->build_msd($data);

			header("Content-type: text/xml");
			echo $xml;
			die();}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function template_msd_get($template_uid=null)
	{
		try{
			$data = $this->msdwriter->template_to_array($template_uid);			
			$xml = $this->msdwriter->build_msd($data, 'test.xml');

			header("Content-type: text/xml");
			echo $xml;
			die();
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function metadatasetreport_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);
			$this->load->library("SDMX/MetadataSetReport");

			$result=$this->metadatasetreport->json($sid);
			$validated=$this->metadatasetreport->validate_schema($result);			
				
			if(!$result){
				throw new Exception("DATASET_NOT_FOUND");
			}
	
			$this->set_response($result, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function json_schema_to_array_get()
	{
		try{
			$json_schema_path = 'application/schemas/timeseries-schema.json';
			$array = $this->msdwriter->json_schema_to_array($json_schema_path);
			$response=array(
				'status'=>'success',
				'array'=>$array
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


	function template_to_array_get($template_uid=null)
	{
		try{			
			$array = $this->msdwriter->template_to_array($template_uid);
			$response=array(
				'status'=>'success',
				'array'=>$array
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
