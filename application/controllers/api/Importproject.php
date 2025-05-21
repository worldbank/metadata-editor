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
			
			if (!$type){
				throw new Exception("TYPE not specified");
			}

			if(empty($_FILES['file'])){
				throw new Exception("File not uploaded");
			}

			//temporary uuid
			$idno=(string)$this->Editor_model->generate_uuid();			
			
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");
			$options['title']='untitled';
			$options['type']=$type;
			//$options['idno']=$idno;	
			
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


	private function import_zip_package($sid,$zip_path)
	{
		$this->load->library('ImportJsonMetadata');

		//extract zip
		$project_path=$this->extract_zip_package($sid,$zip_path);

		//read project info.json
		$project_info=$project_path.'/info.json';

		if (!file_exists($project_info)){
			throw new Exception("Project info.json not found: " .$project_info);
		}

		$project_info=json_decode(file_get_contents($project_info),true);

		$metadata_json_path=$project_path.'/'.$project_info['json_file'];

		if (!file_exists($metadata_json_path)){
			throw new Exception("Metadata json file not found: ". $metadata_json_path);
		}

		$options=array();

		//import project metadata
		$result=$this->importjsonmetadata->import($sid,$metadata_json_path,$validate=false,$options);

		//import external resources
		$rdf_json=$project_path.'/'.$project_info['rdf_json_file'];

		$resources_imported=0;
		if (file_exists($rdf_json)){			
			$resources_imported=$this->Editor_resource_model->import_json($sid,$rdf_json);
		}

		//set thumbnail
		$thumbnail=$project_info['thumbnail'];

		if ($thumbnail){
			$this->Editor_model->set_project_options($sid,$options=array(
				'thumbnail'=>$thumbnail
			));
		}

		return array(
			'project_imported'=>$result,
			'resources_imported'=>$resources_imported,
			'thumbnail'=>$thumbnail,
			'project_info'=>$project_info
		);
	}

	private function extract_zip_package($sid,$zip_path)
	{
		$project_folder_path=$this->Editor_model->get_project_folder($sid);

		if (!file_exists($project_folder_path)){
			throw new Exception("Project folder not found");
		}

		//extract zip
		$zipFile = new \PhpZip\ZipFile();
		try{
			$zipFile
				->openFile($zip_path)
				->extractTo($project_folder_path);
		}
		catch(\PhpZip\Exception\ZipException $e){
			throw new Exception("Failed to extract zip file");
		}
		finally{
			$zipFile->close();
		}

		return $project_folder_path;
	}



	
	
}
