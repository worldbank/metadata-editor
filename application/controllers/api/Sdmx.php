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
		$this->load->library("SDMX/MsdWriter21");
		$this->load->library("SDMX/CsvWriter");
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
			$sdmx_version=$this->input->get('version');

			if (!in_array($sdmx_version, array('3.0','2.1'))){
				$sdmx_version='3.0';
			}

			if ($sdmx_version=='2.1'){
				$this->load->library("SDMX/MsdWriter21");
				$data = $this->msdwriter21->template_to_array($template_uid);
				$xml = $this->msdwriter21->build_msd($data);
			}
			else{
				$data = $this->msdwriter->template_to_array($template_uid);			
				$xml = $this->msdwriter->build_msd($data, 'test.xml');
			}

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

	/**
	 * 
	 * Export project as SDMX CSV
	 * 
	 *  - inline: boolean (default: false)
	 *  - structure_type: string
	 *  - structure_id: string 
	 *  - action: string (default: "I")
	 *  - dimensions: array of key-value pairs (example: ['INDICATOR' => "INDICATOR-ID-NO"])
	 * 
	 * 
	 */
	function csv_post($sid=null)
	{
		try{
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission='view', $this->api_user);

			$project_data = $this->Editor_model->get_row($sid);

			if(!$project_data){
				throw new Exception("PROJECT_NOT_FOUND");
			}

			$options = $this->raw_json_input();
			$inline = isset($options['inline']) ? (bool)$options['inline'] : false;
			$dimensions = isset($options['dimensions']) ? $options['dimensions'] : array();

			$required_params=["structure_type","structure_id","action"];

			foreach($required_params as $param){
				if (!isset($options[$param])){
					throw new Exception("Parameter [$param] is required");
				}
				if (empty($options[$param])){
					throw new Exception("Parameter [$param] is empty");
				}
			}
						
			$metadata = $project_data['metadata'];

			if(empty($metadata)){
				throw new Exception("NO_METADATA_FOUND");
			}

			$csv=$this->csvwriter->generate_csv(
				$options['structure_type'],
				$options['structure_id'],
				$options['action'],
				$dimensions,
				$metadata);

		
			if($inline){				
				header('Content-Type: text/plain');
				header('Cache-Control: no-cache, must-revalidate');
				header('Pragma: no-cache');
				echo $csv;
				die();
			} else {
				//download csv file
				$filename = 'sdmx_export_' . $sid . '_'. $project_data['idno'] . '_' . date('Y-m-d_H-i-s') . '.csv';
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				
				echo $csv;
				die();
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
	
	
}
