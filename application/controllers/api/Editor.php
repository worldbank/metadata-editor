<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Editor extends MY_REST_Controller
{
	private $api_user;

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
		$this->load->library("Audit_log");
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();		
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

			$user_id=$this->get_api_user_id();
			$this->has_access($resource_='editor',$privilege='view');			
			
			$offset=(int)$this->input->get("offset");
			$limit=(int)$this->input->get("limit");

			$search_options=$this->input->get();
			$search_options['user_id']=$user_id;

			if (!$limit){
				$limit=100;
			}
			
			$result=$this->Editor_model->get_all($limit,$offset,null,$search_options);
			array_walk($result['result'], 'unix_date_to_gmt',array('created','changed'));

			//add collections and tags to each study
			$project_id_list=array();
			foreach($result['result'] as $row){
				$project_id_list[]=$row['id'];
			}

			if (count($project_id_list)>0){						
				//get collections
				$collections=$this->Collection_model->collections_by_projects($project_id_list);

				//get tags
				//$tags=$this->Editor_model->get_tags($project_id_list);

				//add collections and tags to each study
				foreach($result['result'] as $key=>$row){
					$result['result'][$key]['collections']=isset($collections[$row['id']]) ? $collections[$row['id']] : array();
					//$result['result'][$key]['tags']=$tags[$row['id']];
				}
			}

			
			$response=array(
				'status'=>'success',
				'total'=>$this->Editor_model->get_total_count($search_options),
				'found'=>is_array($result['result']) ? count($result['result']) : 0,
				'offset'=>$offset,
				'limit'=>$limit,
				'projects'=>$result['result'],
				'filters'=>$result['filters']				
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
			$sid=$this->get_sid($sid);
			$user=$this->api_user();
			$this->editor_acl->user_has_project_access($sid,$permission='view',$user);

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
		try{			
			$user_id=$this->get_api_user_id();
			$project_options=$this->raw_json_input();

			$idno='';
			if (isset($project_options['idno'])){
				$idno=$project_options['idno'];
			}else{
				$idno=$this->Editor_model->generate_uuid();
			}

			$this->validate_project_idno($idno);
			
			$options=array(
				'title'=> 'untitled',
				'type'=> $type,
				'idno'=> $idno
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
			
			
			if (!empty($project_options)){
				$this->update_post($type,$dataset_id);
			}

			$this->audit_log->log_event($obj_type='project',$obj_id=$dataset_id,$description='create');

			$response=array(
				'status'=>'success',
				'id'=>$dataset_id
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
		try{			
			$options=$this->raw_json_input();
			$user=$this->api_user();
			$user_id=$this->get_api_user_id();
			$id=$this->get_sid($id);
			
			//check project exists and is of correct type
			$exists=$this->Editor_model->check_id_exists($id,$type);

			if(!$exists){
				throw new Exception("Project with the type [".$type ."] not found");
			}

			$this->editor_acl->user_has_project_access($id,$permission='edit',$user);
			$this->audit_log->log_event($obj_type='project',$obj_id=$id,$description='update');			
			
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
			$sid=$this->get_sid($sid);

			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['sid']=$sid;

			$this->editor_acl->user_has_project_access($sid,$permission='edit');			
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
			$sid=$this->get_sid($sid);
			$project=$this->Editor_model->get_row($sid);

			if (!$project){
				throw new exception("project not found");
			}

			$user_id=$this->get_api_user_id();
			
			$this->editor_acl->user_has_project_access($sid,$permission='view');

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
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
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



	function import_metadata_post($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$project=$this->Editor_model->get_basic_info($sid);

			if(!$project){
				throw new Exception("Project not found");
			}

			$user_id=$this->get_api_user_id();
			
			$options=array();
			$options['created_by']=$user_id;
			$options['changed_by']=$user_id;
			$options['created']=date("U");
			$options['changed']=date("U");

			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			
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
					$result=$this->Editor_model->importDDI($sid, $parseOnly=false,$options);
				}
			}else{
				$this->load->library('ImportJsonMetadata');

				$result=$this->importjsonmetadata->import($sid,$uploaded_filepath);

				/*
				$json_data=json_decode(file_get_contents($uploaded_filepath),true);
				
				if (!$json_data){
					throw new Exception("Failed to read/decode JSON file");
				}

				$result=$this->Editor_model->importJSON($sid,$type=$project['type'],$json_data,$validate=true);
				*/
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


	/*function convert_ddi_post($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit');
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
	}*/


	/**
	 * 
	 * Download project metadata as JSON
	 * 
	 */
	function json_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);			

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);
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
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);
			$user=$this->api_user();

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
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
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
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
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
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
	 * Download project metadata as DDI (only for Microdata)
	 * 
	 */
	function pdf_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->Editor_model->download_project_pdf($sid);
			die();
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Generate project pdf documentation
	 * 
	 */
	function generate_pdf_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$result=$this->Editor_model->generate_project_pdf($sid);

			$output=array(
				'status'=>'success',
				'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function pdf_info_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$result=$this->Editor_model->get_pdf_info($sid);

			$output=array(
				'status'=>'success',
				'info'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
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
			$sid=$this->get_sid($sid);
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


	//remove catalog connection
	function catalog_connections_delete_post()
	{
		try{
			//$this->has_dataset_access('view');
			
			$user_id=$this->get_api_user_id();

			if (!$user_id){
				throw new Exception("User-login-required");
			}
			
			$catalog_id=$this->input->post('catalog_id');

			if (!isset($catalog_id)){
				throw new Exception("Catalog ID is required");
			}

			$result=$this->Editor_model->catalog_connection_delete($catalog_id, $user_id);
			
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
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view');

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
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit');

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


	/**
	 * 
	 * get all facets available
	 * 
	 */
	function facets_get()
	{
		try{

			$result=$this->Editor_model->get_facets();

			$response=array(
				'status'=>'success',
				'facets'=>$result
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
	 * Fix category labels
	 * 
	 */	
	function populate_category_labels_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$result=$this->Editor_variable_model->populate_categry_labels($sid);			
				
			if(!$result){
				throw new Exception("PROJECT_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'result'=>$result
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



	private function validate_project_idno($idno,$sid=null)
	{
		//validate idno format
		$this->Editor_model->validate_idno($idno);

		$idno_exists=$this->Editor_model->idno_exists($idno,$sid);
				
		if ($idno_exists){
			throw new Exception("Project IDNO already exists. IDNO must be a unique value.");
		}

		return true;
	}


	/**
	 * 
	 * Get info on all users/collections that have access to a project
	 * 
	 */
	function access_permissions_get($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');

			$result=$this->editor_acl->get_project_access_permissions($sid);
			
			$response=array(
				'status'=>'success',
				'access'=>$result
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
	 * Check if user has admin access on a project
	 * 
	 */
	function has_admin_access_get($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='admin');

			$response=array(
				'status'=>'success',
				'access'=>'admin'
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
	 * Return info on users who created, changed the project
	 * 
	 */
	function edit_stats_get($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view');			
			$info=$this->Editor_model->get_edits_info($sid);
			array_walk($info, 'unix_date_to_gmt_row',array('created','changed'));
			
			$response=array(
				'info'=>$info
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
