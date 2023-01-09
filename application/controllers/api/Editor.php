<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Editor extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Catalog_model'); 	
		$this->load->helper("date");
		$this->load->model('Data_file_model');
		$this->load->model('Variable_model');	
		$this->load->model('Dataset_model');//remove with Datasets library
		$this->load->model("Editor_model");
		$this->load->model("Editor_resource_model");
		$this->load->model("Editor_publish_model");
		
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
	 * Return all datasets
	 * 
	 */
	function index_get($id=null)
	{
		try{
			if($id){
				return $this->single_get($id);
			}

			$this->has_dataset_access('view');
			
			$offset=(int)$this->input->get("offset");
			$limit=(int)$this->input->get("limit");

			if (!$limit){
				$limit=10;
			}
			
			$result=$this->Editor_model->get_all($limit,$offset);
			array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
			$response=array(
				'status'=>'success',
				'total'=>$this->Editor_model->get_total_count(),
				'found'=>is_array($result) ? count($result) : 0,
				'offset'=>$offset,
				'limit'=>$limit,
				'projects'=>$result
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
	 * Get a single dataset
	 * 
	 */
	function single_get($sid=null)
	{
		try{
			//$this->has_dataset_access('view',$sid);

			$result=$this->Editor_model->get_row($sid);
			array_walk($result, 'unix_date_to_gmt_row',array('created','changed'));
				
			if(!$result){
				throw new Exception("DATASET_NOT_FOUND");
			}

			//$result['metadata']=$this->dataset_manager->get_metadata($sid);
			
			$response=array(
				'status'=>'success',
				'project'=>$result
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
	 * Check if a study IDNO exists
	 * 
	 */
	function check_idno_get($idno=null)
	{
		try{
			$sid=$this->dataset_manager->find_by_idno($idno);
			$this->has_dataset_access('view',$sid);
			
			if ($sid){
				$response=array(
					'status'=>'success',
					'idno'=>$idno,
					'id'=>$sid
				);			
				$this->set_response($response, REST_Controller::HTTP_OK);
			}
			else{
				$response=array(
					'status'=>'not-found',
					'idno'=>$idno,
					'message'=>'IDNO NOT FOUND'
				);
				$this->set_response($response, REST_Controller::HTTP_NOT_FOUND);
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



	/**
	 * 
	 * 
	 * Create new study
	 * @type - survey, timesereis, geospatial
	 * 
	 */
	function create_post($type=null)
	{
		/*if($type=='timeseries-db' || $type=='timeseriesdb'){
			throw new Exception("NOT IMPLEMENTED YET");
			//return $this->create_timeseries_database($idno);
		}*/

		try{
			//$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			
			$options=array(
				'title'=>'untitled',
				'type'=>$type
			);

			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");
			

			//$this->has_dataset_access('edit',null,$options['repositoryid']);

			//validate & create dataset
			$dataset_id=$this->Editor_model->create_project($type,$options);			

			if(!$dataset_id){
				throw new Exception("FAILED_TO_CREATE_DATASET");
			}

			$this->Editor_model->create_project_folder($dataset_id);
			$project=$this->Editor_model->get_row($dataset_id);

			/*
			//create dataset project folder
			$dataset['dirpath']=$this->dataset_manager->setup_folder($repositoryid='central', $folder_name=md5($dataset['idno']));

			$update_options=array(
				'dirpath'=>$dataset['dirpath']
			);

			$this->dataset_manager->update_options($dataset_id,$update_options);
			*/

			$response=array(
				'status'=>'success',
				'id'=>$dataset_id,
				'project'=>$project
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>(array)$e->GetValidationErrors()
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




	/**
	 * 
	 * 
	 * Update project
	 * @type - survey, timeseries, geospatial
	 * 
	 */
	function update_post($type=null,$id=null,$validate=false)
	{
		/*if($type=='timeseries-db' || $type=='timeseriesdb'){
			//return $this->update_timeseries_database($idno);
			throw new Exception("NOT IMPLEMENTED YET");
		}*/

		try{			
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			
			//$this->has_dataset_access('edit',$sid);

			//check project exists and is of correct type
			$exists=$this->Editor_model->check_id_exists($id,$type);

			if(!$exists){
				throw new Exception("Project with the type [".$type ."] not found");
			}
			
			$options['changed_by']=$user_id;
			$options['changed']=date("U");

			
			//validate & update project
			$this->Editor_model->update_project($type,$id,$options,$validate);
			$this->Editor_model->create_project_folder($id);

			$response=array(
				'status'=>'success'				
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
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


	/**
	 * 
	 * 
	 * Update project options
	 * set:
	 * 	- template
	 * 
	 */
	function options_post($sid=null)
	{
		try{
			$this->has_dataset_access('edit');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['sid']=$sid;
			
			$this->Editor_model->set_project_options($sid,$options);

			$response=array(
					'status'=>'success'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);			
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



	function validate_get($sid=null)
	{
		try{
			$project=$this->Editor_model->get_row($sid);

			if (!$project){
				throw new exception("project not found");
			}

			$user_id=$this->get_api_user_id();
			
			//$this->has_dataset_access('edit',$sid);

			//validate & update project
			$this->Editor_model->validate_schema($project['type'],$project['metadata']);

			$response=array(
				'status'=>'success'				
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
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


	/**
	 * 
	 * 
	 * Delete project
	 * 
	 */
	function delete_post($sid=null)
	{
		try{
			//$this->has_dataset_access('edit');
			$this->Editor_model->delete_project($sid);
				
			$response=array(
				'status'=>'success'					
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


	//data files

	/**
	 * 
	 * list study data files
	 * 
	 */
	function datafiles_get($id=null)
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();
			$survey_datafiles=$this->Editor_model->data_files($id);
			
			$response=array(
				'datafiles'=>$survey_datafiles
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
	 * Get data file by name
	 * 
	 */
	function datafile_by_name_get($sid=null)
	{
		try{
			//$this->has_dataset_access('view');

			$filename=$this->input->get("filename");

			if(!$filename){
				throw new Exception("Missing required parameter: filename");
			}
			
			$user_id=$this->get_api_user_id();
			$survey_datafiles=$this->Editor_model->data_file_by_name($sid,$filename);

			if (!$survey_datafiles){
				throw new Exception("Data file not found");
			}
			
			$response=array(
				'datafile'=>$survey_datafiles
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
	 * Get a new file id 
	 * 
	 */
	function datafile_generate_fid_get($sid=null)
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();
			$file_id=$this->Editor_model->data_file_generate_fileid($sid);

			$response=array(
				'file_id'=>$file_id
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
	 * Create or update a data file
	 * 
	 */
	function datafiles_post($sid=null)
	{
		try{
			$this->has_dataset_access('edit');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['sid']=$sid;

			/*$required_fields=array("file_id","file_name");

			foreach($required_fields as $field_){
				if(!isset($options[$field_])){
					throw new Exception("Required field is missing: ".$field_);
				}
			}*/

			//validate 
			if ($this->Editor_model->validate_data_file($options)){
				$options['file_uri']=$options['file_name'];
				$options['file_name']=$this->Editor_model->data_file_filename_part($options['file_name']);

				$data_file=$this->Editor_model->data_file_by_name($sid,$options['file_name']);

				if (!$data_file){
					$this->Editor_model->data_file_insert($sid,$options);
				}else{
					$this->Editor_model->data_file_update($data_file["id"],$options);
				}
				
				$response=array(
					'status'=>'success',
					'datafile'=>$options
				);

				$this->set_response($response, REST_Controller::HTTP_OK);
			}
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

	/**
	 * 
	 * 
	 * Delete a data file
	 * 
	 */
	function datafiles_delete_post($sid=null,$file_id=null)
	{
		try{
			//$this->has_dataset_access('edit');

			//delete
			$this->Editor_model->data_file_delete($sid,$file_id);
				
			$response=array(
				'status'=>'success'					
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
	 * List dataset variables
	 * 
	 */
	function variables_get($id=null,$file_id=null)
	{
		try{
			//$this->has_dataset_access('view');			
			$user_id=$this->get_api_user_id();        			
			$variable_detailed=(int)$this->input->get("detailed");
			$survey_variables=$this->Editor_model->variables($id,$file_id,$variable_detailed);
			
			$response=array(
				'variables'=>$survey_variables
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
	 * Create variables for Datasets
	 * @idno - dataset IDNo
	 * @merge_metadata - true|false 
	 * 	- true = partial update metadata 
	 *  - false = replace all metadata with new
	 */
	function variables_post($sid=null)
	{
		try{
			//$this->has_dataset_access('edit');
			$options=(array)$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			//check if a single variable input is provided or a list of variables
			$key=key($options);

			//convert to list of a list
			if(!is_numeric($key)){
				$tmp_options=array();
				$tmp_options[]=$options;
				$options=null;
				$options=$tmp_options;
			}

			$valid_data_files=$this->Editor_model->data_files_list($sid);
			
			//validate all variables
			foreach($options as $key=>$variable){

				if (!isset($variable['file_id'])){
					throw new Exception("`file_id` is required");
				}

				if (!in_array($variable['file_id'],$valid_data_files)){
					throw new Exception("Invalid `file_id`: valid values are: ". implode(", ", $valid_data_files ));
				}

				if (isset($variable['vid']) && !empty($variable['vid'])){
					//check if variable already exists
					$uid=$this->Editor_model->variable_uid_by_name($sid,$variable['file_id'],$variable['name']);
					$variable['fid']=$variable['file_id'];

					$this->Editor_model->validate_variable($variable);
					$variable['metadata']=$variable;
		
					if($uid){						
						$this->Editor_model->variable_update($sid,$uid,$variable);
					}
					else{						
						$this->Editor_model->variable_insert($sid,$variable);
					}

					$result[]=$variable['vid'];
				}
			}

			$response=array(
				'status'=>'success',
				'variables'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
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


	/**
	 * 
	 * Project files
	 * 
	 * Return all files for a project
	 * 
	 **/ 
	function files_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$result=$this->Editor_resource_model->files_summary($sid);

			$output=array(
				'files'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * upload file
	 * @file_type data | documentation | thumbnail
	 * 
	 **/ 
	function files_post($sid=null,$file_type='documentation')
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			if ($file_type=='thumbnail'){
				$output=$this->Editor_resource_model->upload_thumbnail($sid,$file_field_name='file');
				$this->Editor_model->set_project_options($sid,$options=array('thumbnail'=>$output['thumbnail_filename']));
			}else{
				$result=$this->Editor_resource_model->upload_file($sid,$file_type,$file_field_name='file', $remove_spaces=false);
				$uploaded_file_name=$result['file_name'];
				$uploaded_path=$result['full_path'];
				
				$output=array(
					'status'=>'success',
					'uploaded_file_name'=>$uploaded_file_name,
					'base64'=>base64_encode($uploaded_file_name)				
				);
			}
						
			//attach to resource if provided
			/*if(is_numeric($resource_id)){
				$options=array(
					'filename'=>$uploaded_file_name
				);
				$this->Survey_resource_model->update($resource_id,$options);
			}*/

			$this->set_response($output, REST_Controller::HTTP_OK);			
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
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

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



	function import_metadata_post($sid=null)
	{		
		try{
			$project=$this->Editor_model->get_basic_info($sid);

			if(!$project){
				throw new Exception("Project not found");
			}
			
			$allowed_file_types="json|xml";
			$uploaded_filepath=$this->Editor_resource_model->upload_temporary_file($allowed_file_types,$file_field_name='file',$temp_upload_folder=null);

			if (!file_exists($uploaded_filepath)){
				throw new Exception("Failed to upload file");
			}

			$file_info=pathinfo($uploaded_filepath);
			$file_ext=strtolower($file_info['extension']);

			$result=$file_info;

			if ($file_ext=='xml'){
				if ($project['type']=='survey'){
					$result=$this->Editor_model->importDDI($sid);		
				}
			}else{
				$json_data=json_decode(file_get_contents($uploaded_filepath),true);
				
				if (!$json_data){
					throw new Exception("Failed to read/decode JSON file");
				}

				$result=$this->Editor_model->importJSON($sid,$type=$project['type'],$json_data,$validate=true);
			}

			$output=array(
				'status'=>'success',
				'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
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


	function convert_ddi_post($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$result=$this->Editor_model->importDDI($sid,$parseOnly=true);

			$output=array(
				'status'=>'success',
				'ddi'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Download project metadata as JSON
	 * 
	 */
	function json_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->Editor_model->download_project_json($sid);
			die();
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Generate project metadata as JSON
	 * 
	 */
	function generate_json_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->Editor_model->generate_project_json($sid);

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
	 * Download project metadata as DDI (only for Microdata)
	 * 
	 */
	function ddi_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->Editor_model->download_project_ddi($sid);
			die();
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Generate project metadata as DDI
	 * 
	 */
	function generate_ddi_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->Editor_model->generate_project_ddi($sid);

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
	 * Generate resources JSON and save to project folder
	 * 
	 */
	function write_resources_json_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$resources=$this->Editor_resource_model->select_all($sid,$fields=null);

			$remove_fields=array("sid","id");
			foreach($resources as $idx=>$resource){
				foreach($remove_fields as $f){
					if (isset($resources[$idx][$f])){
						unset($resources[$idx][$f]);
					}
				}
			}

			$path = $this->Editor_model->get_project_folder($sid);
			$resource_file=$path.'/resources.json';

			if (file_exists($resource_file)){
				unlink($resource_file);
				//$this->load->helper('download');
				//force_download2($path.'/project.zip');
				//die();

			}

			file_put_contents($resource_file,json_encode($resources,JSON_PRETTY_PRINT));

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
	function write_resources_rdf_get($sid=null)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

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
	public function resources_import_post($sid=NULL)
	{
		try {
			//$this->has_dataset_access('edit',$sid);

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
	function resources_get($sid=null)
	{
		try{
			//$this->has_dataset_access('view');
			
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
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

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
	function resources_post($sid=null,$resource_id=null)
	{
		//multipart/form-data
		$options=$this->input->post(null, true);

		//raw json input
		if (empty($options)){
			$options=$this->raw_json_input();
		}
				
		try{
			
			//$this->has_dataset_access('edit',$sid);

			$options['sid']=$sid;

			//get dctype by code
			if(isset($options['dctype'])){ 
				$options['dctype']=$this->Survey_resource_model->get_dctype_label_by_code($options['dctype']);
			}

			if(isset($options['dcformat'])){ 
				$options['dcformat']=$this->Survey_resource_model->get_dcformat_label_by_code($options['dcformat']);
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
	function resource_delete_post($sid=null,$resource_id=null)
	{
		try{
			//$this->has_dataset_access('view');

			$user_id=$this->get_api_user_id();
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


	function download_zip_get($sid,$generate=0)
	{
		$this->load->library('zip');
		$path = $this->Editor_model->get_project_folder($sid);

		if (file_exists($path.'/project.zip') && $generate==0){
			$this->load->helper('download');
			force_download2($path.'/project.zip');
			die();
		}

		$files=$this->Editor_resource_model->files($sid);
		
		foreach($files as $file){
			$this->zip->read_file($path.$file,$file);
		}

		$this->zip->download(md5($sid).'.zip',false);
		die();
	}

	function generate_zip_get($sid)
	{
		$this->load->library('zip');
		try{
			$path = $this->Editor_model->get_project_folder($sid);
			$files=$this->Editor_resource_model->files($sid);

			if (file_exists($path.'/project.zip')){
				unlink($path.'/project.zip');
			}
			
			foreach($files as $file){
				$this->zip->read_file($path.$file,$file);
			}

			$this->zip->archive($path.'/project.zip');

			$response=array(
				'status'=>'success',
				'zip'=>$path.'/project.zip'
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
	 * Project thumbnail
	 * 
	 */
	function thumbnail_get($sid=null)
	{		
		try{
			$this->Editor_model->download_project_thumbnail($sid);
			die();
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	//catalogs connections for direct publishing from the editor

	/**
	 * 
	 * list catalog connections by current logged-in user
	 * 
	 */
	function catalog_connections_get()
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();

			if (!$user_id)
			{
				throw new Exception("User-login-required");
			}

			$connections=$this->Editor_model->catalog_connections($user_id);
			
			$response=array(
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
	function catalog_connections_post()
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$options=$this->raw_json_input();
			$options['user_id']=$user_id;

			$result=$this->Editor_model->catalog_connection_create($options);
			
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

	function publish_to_catalog_post($sid=null,$catalog_connection_id=null)
	{
		try{
			//$this->has_dataset_access('view');
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}

			$response=$this->Editor_publish_model->publish_to_catalog($sid,$user_id,$catalog_connection_id,$options);			
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
	 * Import data file
	 * @fid - file id
	 * @append 0=false, 1=true - if false, overwrite existing data
	 * 
	 **/ 
	function import_data_post($sid=null, $fid=null, $append=0)
	{		
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$result=$this->Editor_resource_model->upload_data($sid,$fid,$file_field_name='file', $append);
			$uploaded_file_name=$result['file_name'];
			$uploaded_path=$result['full_path'];
			
			$output=array(
				'status'=>'success',
				'uploaded_file_name'=>$uploaded_file_name,
				'base64'=>base64_encode($uploaded_file_name)				
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


}
