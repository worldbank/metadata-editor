<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Resources extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_datafile_model");		
		
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

	
	/**
	 * 
	 * Generate resources JSON and save to project folder
	 * 
	 */
	function write_json_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->Editor_resource_model->write_json($sid);
			
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
	 * Generate resources RDF and save to project folder
	 * 
	 */
	function write_rdf_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->Editor_resource_model->write_rdf($sid);
			
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
	 * import RDF or JSON
	 * 
	 */
	public function import_post($sid=NULL)
	{
		try {
			$sid=$this->get_sid($sid);
			$user=$this->api_user();
			$this->editor_acl->user_has_project_access($sid,$permission='edit', $user);

			$uploaded_filepath=$this->Editor_resource_model->upload_temporary_file($allowed_file_type="rdf|xml|json",$file_field_name='file',$temp_upload_folder=null);

			if (!file_exists($uploaded_filepath)){
				throw new Exception("File upload failed");
			}

			$file_info=pathinfo($uploaded_filepath);

			if (strtolower($file_info['extension'])=='rdf'){
				$imported_count=$this->Editor_resource_model->import_rdf($sid,$uploaded_filepath);
			}else if (strtolower($file_info['extension'])=='json'){
				$imported_count=$this->Editor_resource_model->import_json($sid,$uploaded_filepath);
			}
			else{
				throw new Exception("File type is not supported: ".$file_info['extension']);
			}
			
			@unlink($uploaded_filepath);

			$output=array(
				'status'=>'success',
				'entries_imported'=>$imported_count
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$output=array(
				'status'=>'error',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}		
	}


	/**
	 * 
	 * list external resources
	 * 
	 */
	function index_get($sid=null)
	{
		try{
			$user=$this->api_user();
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view', $user);
			
			$user_id=$this->get_api_user_id();
			$resources=$this->Editor_resource_model->select_all($sid,$fields=null);
			
			$response=array(
				'resources'=>$resources
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
	 * Download external resources as RDF/XML
	 * 
	 */
	function rdf_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');

			header('Content-type: application/xml');
			echo $this->Editor_resource_model->generate_rdf($sid);
			die();
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * create or update external resource
	 * 
	 **/ 
	function index_post($sid=null,$resource_id=null)
	{
		//multipart/form-data
		$options=$this->input->post(null, true);
		$user=$this->api_user();

		//raw json input
		if (empty($options)){
			$options=$this->raw_json_input();
		}
				
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

			$options['sid']=$sid;

			//get dctype by code
			if(isset($options['dctype'])){ 
				$options['dctype']=$this->Editor_resource_model->get_dctype_label_by_code($options['dctype']);
			}

			if(isset($options['dcformat'])){ 
				$options['dcformat']=$this->Editor_resource_model->get_dcformat_label_by_code($options['dcformat']);
			}

			//validate resource
			if ($this->Editor_resource_model->validate_resource($options)){

				$upload_result=null;

				if(!empty($_FILES)){
					//upload file?					
					$upload_result=$this->Editor_resource_model->upload_file($sid,$file_type='documentation', $file_field_name='file', $remove_spaces=false);
					$uploaded_file_name=$upload_result['file_name'];
				
					//set filename to uploaded file
					$options['filename']=$uploaded_file_name;
				}

				if(!isset($options['filename'])){
					$options['filename']=null;
				}				

				if($resource_id){
					$resource=$this->Editor_resource_model->select_single($sid,$resource_id);
					if (!$resource){
						throw new Exception("Resource not found");
					}

					/*if($resource['filename'] && $options['filename']){						
						//delete old file
						$this->Editor_resource_model->delete_file_by_resource($sid,$resource_id);
					}*/
					
					$resource_id=$this->Editor_resource_model->update($resource_id,$options);
				}				
				else{
					//insert new resource
					$resource_id=$this->Editor_resource_model->insert($options);
				}

				$resource=$this->Editor_resource_model->select_single($sid,$resource_id);
				
				$response=array(
					'status'=>'success',
					'resource'=>$resource,
					'uploaded_file'=>$upload_result
				);

				$this->set_response($response, REST_Controller::HTTP_OK);
			}
		}
		catch(ValidationException $e){
			$error_output=array(
				'message'=>'VALIDATION_ERROR',
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}		
	}


	/**
	 * 
	 * Delete external resource
	 * 
	 */
	function delete_post($sid=null,$resource_id=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$user=$this->api_user();

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);
			$resources=$this->Editor_resource_model->delete($sid,$resource_id);
			
			$response=array(
				'resources'=>$resources
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


	function download_get($sid, $resource_id)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->Editor_resource_model->download_resource($sid,$resource_id,$resource_type='documentation');
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}
}
