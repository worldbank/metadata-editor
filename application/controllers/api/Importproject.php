<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class ImportProject extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_resource_model");
		$this->load->model("Editor_datafile_model");
		$this->load->model("Editor_publish_model");
		$this->load->model("Collection_model");
		
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

	
	
	function index_post()
	{		
		try{			
			$user_id=$this->get_api_user_id();
			$type=$this->input->post('type');
			$idno=$this->input->post('idno'); // Accept idno from POST data
			
			if (!$type){
				throw new Exception("TYPE not specified");
			}

			if(empty($_FILES['file'])){
				throw new Exception("File not uploaded");
			}

			// Use provided idno or generate a temporary uuid if not provided
			if (!$idno){
				$idno=(string)$this->Editor_model->generate_uuid();
			} else {
				// Validate idno format
				$this->Editor_model->validate_idno_format($idno);
				
				// Check if idno already exists (pass null for new project)
				if ($this->Editor_model->idno_exists($idno, null)){
					throw new Exception("Project IDNO already exists: " . $idno);
				}
			}			
			
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");
			$options['title']='untitled';
			$options['type']=$type;
			$options['idno']=$idno;	
			
			//upload file and import metadata
			$allowed_file_types="json|xml|zip";
			$uploaded_filepath=$this->Editor_resource_model->upload_temporary_file($allowed_file_types,$file_field_name='file',$temp_upload_folder=null);

			if (!file_exists($uploaded_filepath)){
				throw new Exception("Failed to upload file");
			}

			$file_info=pathinfo($uploaded_filepath);
			$file_ext=strtolower($file_info['extension']);

			$result=$file_info;

			//validate & create dataset
			$sid=$this->Editor_model->create_project($type,$options);

			if(!$sid){
				throw new Exception("FAILED_TO_CREATE_PROJECT");
			}

			$this->Editor_model->create_project_folder($sid);
			
			try{
				if ($file_ext=='xml'){
					if ($options['type']=='survey'){
						$result=$this->Editor_model->importDDI($sid, $parseOnly=false,$options);
					}
					else if ($options['type']=='geospatial'){
						$this->load->library('Geospatial_import');
						$result=$this->geospatial_import->import($sid,$uploaded_filepath);
					}
					else{
						throw new Exception("Unsupported file type");
					}
				}else if ($file_ext=='json'){
					$this->load->library('ImportJsonMetadata');
					$result=$this->importjsonmetadata->import($sid,$uploaded_filepath,$validate=true,$options);
				}
				else if ($file_ext=='zip')
				{
					set_time_limit(0);
					$result=$this->import_zip_package($sid,$zip_path=$uploaded_filepath);

					if (isset($result['project_info']['idno'])){
						$idno=$result['project_info']['idno'];
					}
				}			

				$this->Editor_model->set_project_options($sid,$options=array(
					'created_by'=>$user_id,
					'changed_by'=>$user_id,
					'created'=>date("U"),
					'changed'=>date("U"),
					'idno'=>$idno
				));

				$output=array(
					'status'=>'success',
					'file_info'=>$file_info,
					'sid'=>$sid,
					'idno'=>$idno
				);

				$this->set_response($output, REST_Controller::HTTP_OK);			
			}
			catch(Exception $e){
				$this->Editor_model->delete_project($sid);
				throw $e;
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
	 * upload file
	 * @resource_id (optional) if provided, file is attached to the resource
	 * 
	 **/ 
	function import_ddi_post($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}
			$this->editor_acl->user_has_project_access($sid,$permission='edit');

			$result=$this->Editor_model->importDDI($sid);

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
	 * Import ZIP package containing project metadata and resources
	 * 
	 * @param int $sid - Project ID
	 * @param string $zip_path - Path to ZIP file
	 * @return array - Import results
	 * 
	 */
	private function import_zip_package($sid,$zip_path)
	{
		$this->load->library('ImportPackage');
		return $this->importpackage->import($sid,$zip_path);
	}



	
	
}
