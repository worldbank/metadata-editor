<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

use Swaggest\JsonDiff\JsonPointer;
use Swaggest\JsonDiff\JsonPointerException;

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
		$this->load->model("Tags_model");
		$this->load->library("Editor_acl");
		$this->load->model("Audit_log_model");
		$this->load->library("Audit_log");
		$this->load->library("Project_search");
		$this->load->library('Project_json_writer');
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
	 * Is user logged in?
	 * 
	 * 
	 */
	function is_connected_get()
	{
		$response=array(
			'status'=>'success'			
		);
		$this->set_response($response, REST_Controller::HTTP_OK);
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
			
			$result=$this->project_search->search($limit,$offset,null,$search_options);
			array_walk($result['result'], 'unix_date_to_gmt',array('created','changed'));

			//add collections and tags to each study
			$project_id_list=array();
			foreach($result['result'] as $row){
				$project_id_list[]=$row['id'];
			}

			if (count($project_id_list)>0){
				//get collections and tags
				$collections=$this->Collection_model->collections_by_projects($project_id_list);
				$tags=$this->Tags_model->get_tags_by_projects($project_id_list);

				//add collections and tags to each study
				foreach($result['result'] as $key=>$row){
					$result['result'][$key]['collections']=isset($collections[$row['id']]) ? $collections[$row['id']] : array();
					$result['result'][$key]['tags']=isset($tags[$row['id']]) ? $tags[$row['id']] : array();
				}
			}

			
			$response=array(
				'status'=>'success',
				'total'=>$this->project_search->get_total_count($search_options),
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
	 * @params - querystring: version
	 * 
	 * 
	 */
	function single_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$version_id=$this->input->get("version");
			if ($version_id){
				$version_sid=$this->Editor_model->find_version_by_number($sid, $version_id);
				if ($version_sid){
					$sid=$version_sid;
				}
				else{
					throw new Exception("VERSION_NOT_FOUND");
				}
			}

			$result=$this->Editor_model->get_row($sid);			
				
			if(!$result){
				throw new Exception("PROJECT_NOT_FOUND");
			}

			array_walk($result, 'unix_date_to_gmt_row',array('created','changed'));

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
	 * Get basic project info
	 * 
	 */
	function basic_info_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$result=$this->Editor_model->get_basic_info($sid);
			array_walk($result, 'unix_date_to_gmt_row',array('created','changed'));
				
			if(!$result){
				throw new Exception("DATASET_NOT_FOUND");
			}

			$result['has_thumbnail'] = (bool) $this->Editor_model->get_thumbnail_file($sid);

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
	 * Update project metadata
	 * 
	 * @param string $type - Project type (survey, timeseries, geospatial)
	 * @param int $id - Project ID
	 * @param array $options - Project options/metadata
	 * @param bool $validate - Whether to validate schema
	 * @param object $user - User object for ACL checks
	 * @param int $user_id - User ID
	 * 
	 */
	private function _update_project_metadata($type, $id, $options, $validate=false, $user=null, $user_id=null)
	{
		if ($user === null) {
			$user = $this->api_user();
		}
		if ($user_id === null) {
			$user_id = $this->get_api_user_id();
		}
		
		$resolved_type = $this->Editor_model->resolve_canonical_type($type);
		if ($resolved_type === false) {
			throw new Exception("INVALID_TYPE: ".$type);
		}
		$type = $resolved_type;
		
		$id = $this->get_sid($id);		
		$exists = $this->Editor_model->check_id_exists($id, $type);
		
		if (!$exists) {
			throw new Exception("Project with the type [".$type ."] not found");
		}
		
		$this->editor_acl->user_has_project_access($id, $permission='edit', $user);
		
		$options['changed_by'] = $user_id;
		$options['changed'] = date("U");
		
		// Indicator/timeseries: bind global DSD reference only (no inline data_structure).
		if (in_array($type, array('indicator', 'timeseries'))) {
			if (isset($options['data_structure']) && is_array($options['data_structure']) && count($options['data_structure']) > 0) {
				throw new Exception(
					'Inline data_structure is no longer supported. Attach a global data structure via data_structure_reference or the project DSD UI.'
				);
			}
			unset($options['data_structure']);

			if (isset($options['data_structure_reference']) && is_array($options['data_structure_reference'])) {
				$this->load->library('Data_structure_util');
				$this->data_structure_util->bind_project_by_reference(
					$id,
					$options['data_structure_reference'],
					$user_id
				);
			}
		}
		
		$this->load->library('ImportJsonMetadata');
		
		$import_options = array(
			'user_id' => $user_id,
			'created_by' => $user_id
		);
		
		// Process metadata import
		$this->importjsonmetadata->process_project_metadata($type, $id, $options, $validate, $import_options);		
		$this->Editor_model->create_project_folder($id);
	}

	/**
	 * 
	 * 
	 * Create new project
	 * 
	 * @type - microdata, indicator, geospatial
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

			$overwrite_raw = $project_options['overwrite'] ?? false;
			$overwrite = is_string($overwrite_raw)
				? in_array(strtolower(trim($overwrite_raw)), ['true', 'yes'], true)
				: (bool) $overwrite_raw;
			unset($project_options['overwrite']);

			//collection IDs
			$collection_ids = null;
			if (isset($project_options['collection_ids'])) {
				$collection_ids = $project_options['collection_ids'];
				unset($project_options['collection_ids']);
			}

			$this->_batch_validate_collection_access($collection_ids);

			// check if project already exists
			$sid=$this->Editor_model->get_project_id_by_idno($idno);

			if ($sid && $overwrite) {
				// Verify the existing project is the same type before overwriting
				if (!$this->Editor_model->check_id_exists($sid, $type)) {
					throw new Exception("Cannot overwrite: existing project with IDNO [".$idno."] is a different type");
				}

				if (!empty($project_options)) {
					$this->_update_project_metadata($type, $sid, $project_options, false, $this->api_user(), $user_id);
				}

				$this->_add_project_to_collections($sid, $collection_ids, $user_id);
				$this->audit_log->log_event('project', $sid, 'update', null, $user_id, null);

				$this->set_response(array('status' => 'success', 'id' => $sid), REST_Controller::HTTP_OK);
				return;
			}

			if ($sid){
				throw new Exception("Project with this IDNO already exists: ".$idno);
			}

			$this->validate_project_idno($idno);
						
			$options=array(
				'title'=> 'untitled',
				'type'=> $type,
				'idno'=> $idno,
				'created_by'=> $user_id,
				'changed_by'=> $user_id,
				'created'=> date("U"),
				'changed'=> date("U"),
				'template_uid' => isset($project_options['template_uid']) ? $project_options['template_uid'] : null
			);
 
			//validate & create project
			$dataset_id=$this->Editor_model->create_project($type,$options);

			if(!$dataset_id){
				throw new Exception("FAILED_TO_CREATE_DATASET");
			}

			$this->Editor_model->create_project_folder($dataset_id);
			$this->audit_log->log_event($obj_type='project',
				$obj_id=$dataset_id,
				$action='create',
				$metadata=null, 
				$user_id=$user_id, 
				$obj_ref_id=null);						
			
			if (!empty($project_options)){
				$this->_update_project_metadata($type, $dataset_id, $project_options, false, $this->api_user(), $user_id);
			}
						
			$this->_add_project_to_collections($dataset_id, $collection_ids, $user_id);
			
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
			$user_id=$this->get_api_user_id();
			$sid=$this->get_sid($id);
			
			$collection_ids = null;
			if (isset($options['collection_ids'])) {
				$collection_ids = $options['collection_ids'];
				unset($options['collection_ids']);
			}
			
			$this->_batch_validate_collection_access($collection_ids);
			$this->_update_project_metadata($type, $sid, $options, $validate);
			$this->_add_project_to_collections($sid, $collection_ids, $user_id);

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
	 * Patch project - add, remove, update fields
	 * 
	 * @type - survey, timeseries, geospatial
	 * 
	 * 
	 */
	function patch_post($type=null,$id=null)
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

			if (!isset($options['patches'])){
				throw new Exception("`Patches` parameter is required");
			}

			$this->editor_acl->user_has_project_access($id,$permission='edit',$user);
			$this->audit_log->log_event($obj_type='project',$obj_id=$id,$action='patch', $metadata=$options['patches'], $user_id);
			
			$options['changed_by']=$user_id;
			$options['changed']=date("U");

			$validate=true;
			if (isset($options['validate']) && $options['validate']==false){
				$validate=false;
			}

			//patch project
			$this->Editor_model->patch_project($type,$id,$options, $validate);

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
	 *
	 * set:
	 * 	- template
	 * 	- thumbnail
	 * 	- idno
	 *
	 * 
	 */
	function options_post($sid=null)
	{
		try{
			$options=$this->raw_json_input();
			if (!is_array($options)){
				$options=array();
			}
			$user_id=$this->get_api_user_id();
			$sid=$this->get_sid($sid);

			if (isset($options['created_by'])){
				unset($options['created_by']);
			}
			if (isset($options['created'])){
				unset($options['created']);
			}

			$options['changed_by']=$user_id;
			$options['changed']=date("U");
			$options['sid']=$sid;

			// ADMIN access is required to set project template, other options require edit access
			$required_permission = (isset($options['template_uid'])) ? 'admin' : 'edit';
			$this->editor_acl->user_has_project_access($sid,$permission=$required_permission, $this->api_user());
			$this->Editor_model->set_project_options($sid,$options);
			$this->audit_log->log_event(
				$obj_type='project',
				$obj_id=$sid,
				$action='options', 
				$metadata=array($options),
				$user_id
			);

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
	 * Set project template
	 * 
	 */
	function template_post($sid=null,$template_uid=null)
	{
		try{
			$options=$this->raw_json_input();
			$user=$this->api_user();
			$user_id=$this->get_api_user_id();
			$sid=$this->get_sid($sid);

			if (!$template_uid){
				throw new Exception("Template UID is required");
			}

			// Template changes require ADMIN access
			$this->editor_acl->user_has_project_access($sid,$permission='admin',$user);			
			$this->Editor_model->set_project_template($sid,$template_uid);

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

			//validate variables
			$this->Editor_variable_model->validate_variables($sid);

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
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user());
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

			//Surface any non-fatal warnings raised during DDI parsing/import
			//(e.g. <var @files="H P"> fan-out across hierarchical files).
			if (is_array($result) && !empty($result['variable_warnings'])){
				$output['variable_warnings']=$result['variable_warnings'];
			}

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
				if ($project['type']=='survey' || $project['type']=='microdata'){
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


	/**
	 * 
	 * Download project metadata as JSON
	 * 
	 * @exclude_private_fields - exclude private fields - 0 = include, 1 = exclude
	 * @querystring params - version
	 * 
	 */
	function json_get($sid=null,$exclude_private_fields=0)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$exclude_private_fields=0;
			$inc_ext_resources=0;
			$inc_adm_meta=0;

			$version_id=$this->input->get("version");
			if ($version_id){
				$version_sid=$this->Editor_model->find_version_by_number($sid, $version_id);
				if ($version_sid){
					$sid=$version_sid;
				}
				else{
					throw new Exception("VERSION_NOT_FOUND");
				}
			}

			if ((int)$this->input->get("exclude_private_fields")===1){
				$exclude_private_fields=1;
			}
			else{
				if ((int)$this->input->get("exc_private")===1){
					$exclude_private_fields=1;
				}
			}

			if ((int)$this->input->get("external_resources")===1){
				$inc_ext_resources=1;
			}

			if ((int)$this->input->get("admin_metadata")===1){
				$inc_adm_meta=1;
			}

			$exclude_variables=0;
			if ((int)$this->input->get("exclude_variables")===1){
				$exclude_variables=1;
			}

			$options=array(
				'exclude_private_fields'=>$exclude_private_fields,
				'external_resources'=>$inc_ext_resources,
				'admin_metadata'=>$inc_adm_meta,
				'exclude_variables'=>$exclude_variables,
				'user_id'=>$this->get_api_user_id()
			);

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);			
			$this->project_json_writer->download_project_json($sid,$options);
			die();
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
	 * Get a single value from project export JSON by JSON Pointer (RFC 6901), same document shape as GET /editor/json/{id}.
	 *
	 * Query: path (required) — e.g. /idno or /identification/title
	 */
	function json_field_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$path=$this->input->get('path');
			if ($path===null || $path===''){
				throw new Exception("Query parameter `path` is required (JSON Pointer, e.g. /identification/title)");
			}

			$version_id=$this->input->get("version");
			if ($version_id){
				$version_sid=$this->Editor_model->find_version_by_number($sid, $version_id);
				if ($version_sid){
					$sid=$version_sid;
				}
				else{
					throw new Exception("VERSION_NOT_FOUND");
				}
			}

			$exclude_private_fields=0;
			if ((int)$this->input->get("exclude_private_fields")===1){
				$exclude_private_fields=1;
			}
			elseif ((int)$this->input->get("exc_private")===1){
				$exclude_private_fields=1;
			}

			$inc_ext_resources=0;
			if ((int)$this->input->get("external_resources")===1){
				$inc_ext_resources=1;
			}

			$inc_adm_meta=0;
			if ((int)$this->input->get("admin_metadata")===1){
				$inc_adm_meta=1;
			}

			$exclude_variables=0;
			if ((int)$this->input->get("exclude_variables")===1){
				$exclude_variables=1;
			}

			$options=array(
				'exclude_private_fields'=>$exclude_private_fields,
				'external_resources'=>$inc_ext_resources,
				'admin_metadata'=>$inc_adm_meta,
				'exclude_variables'=>$exclude_variables,
				'user_id'=>$this->get_api_user_id()
			);

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$json_file=$this->project_json_writer->generate_project_json($sid,$options);
			if (!is_readable($json_file)){
				throw new Exception("Failed to read project JSON export");
			}

			$json_raw=file_get_contents($json_file);
			$doc=json_decode($json_raw,true);
			if ($doc===null && json_last_error()!==JSON_ERROR_NONE){
				throw new Exception("Invalid JSON export for project");
			}

			try{
				$value=JsonPointer::getByPointer($doc,$path);
				$response=array(
					'status'=>'success',
					'path'=>$path,
					'found'=>true,
					'value'=>$value
				);
			}
			catch(JsonPointerException $e){
				$msg=$e->getMessage();
				if (strpos($msg,'Key not found')!==false){
					$response=array(
						'status'=>'success',
						'path'=>$path,
						'found'=>false,
						'value'=>null
					);
				}
				else{
					throw new Exception("INVALID_JSON_POINTER: ".$msg);
				}
			}

			$this->set_response($response, REST_Controller::HTTP_OK);
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
			$this->project_json_writer->generate_project_json($sid);

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


	function html_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);
			$exclude_private_fields=0;

			$download=false;
			if ($this->input->get("download")==1 || $this->input->get("download")=='true'){
				$download=true;
			}

			if ((int)$this->input->get("exclude_private_fields")===1){
				$exclude_private_fields=1;
			}

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);
			$this->load->library("html_report");
			$html=$this->html_report->generate($sid, $html_options=array(
				'exclude_private_fields'=>$exclude_private_fields
			));
			
			if ($download){
				$this->load->helper('download');
				$filename='project_metadata-'.$sid.'.html';
				force_download($filename, $html);
			}else{
				echo $html;
			}
			die();
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

			$this->editor_acl->user_has_project_access($sid,$permission='view', $user=$this->api_user());
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
			$include_private_fields=0;
			$template_uid=null;
			$include_external_resources=0;
			$external_resource_ids=array();

			if(!$exists){
				throw new Exception("Project not found");
			}

			if ((int)$this->input->get("include_private_fields")===1){
				$include_private_fields=1;
			}

			if ($this->input->get("template_uid")){
				$template_uid=$this->input->get("template_uid");
			}

			if ((int)$this->input->get("include_external_resources")===1){
				$include_external_resources=1;
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view', $user=$this->api_user());
			$result=$this->Editor_model->generate_project_pdf($sid, $pdf_options=array(
				'include_private_fields'=>$include_private_fields,
				'template_uid'=>$template_uid,
				'include_external_resources'=>$include_external_resources
			));

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
			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->Editor_model->download_project_thumbnail($sid);
			die();
		}
		catch(Exception $e){
			$output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
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
		catch(ApiRequestException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'response'=>$e->getDetails()
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
			$user_id=$this->get_api_user_id();
			$options = $this->input->get();
			$result=$this->project_search->get_facets($user_id, $options);

			$response=array(
				'status'=>'success',
				'facets'=>$result,
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
		$this->Editor_model->validate_idno_format($idno);

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


	/**
	 * 
	 * 
	 * Return collections for a project
	 * 
	 */
	function collections_get($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view', $this->api_user);
			$collections=$this->Collection_model->get_collection_by_project($sid);
			
			$response=array(
				'collections'=>$collections
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
	 * Get edit history for a project
	 * 
	 */
	function history_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$result=$this->Audit_log_model->get_history(
				array(
					'obj_type'=>'project',
					'obj_id'=>$sid
				)
				,$limit=10, $offset=0);
			//array_walk($result, 'unix_date_to_gmt_row',array('created','changed'));
				
			$response=array(
				'status'=>'success',
				'history'=>$result
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
	 * Transfer project ownership
	 * 
	 */
	function transfer_ownership_post()
	{
		try{
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['owner_id'])){
				throw new Exception("Parameter `owner_id` is required");
			}

			if (!isset($options['projects'])
				|| !is_array($options['projects'])
				|| count($options['projects'])==0){
				throw new Exception("Parameter `projects` is required");
			}

			$new_user_id=$options['owner_id'];
			
			foreach($options['projects'] as $project_id){

				$sid=$this->get_sid($project_id);
				$this->editor_acl->user_has_project_access($sid,$permission='admin',$this->api_user);

				$result=$this->Editor_model->transfer_ownership($project_id,$new_user_id);
				$this->audit_log->log_event(
					$obj_type='project',
					$obj_id=$project_id,
					$action='ownership', 
					$metadata=array('new_owner_id'=>$new_user_id),
					$user_id
				);
			}

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
	 * Export project metadata to ISO19139/XML
	 * 
	 */
	function iso19139_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid, $type='geospatial');
			$download=$this->input->get("download");

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->load->library('ISO19139Writer');
			$project=$this->Editor_model->get_row($sid);
			
			// Use project's idno if description.idno is missing
			$description_metadata = isset($project['metadata']['description']) ? $project['metadata']['description'] : array();
			if (!isset($description_metadata['idno']) && isset($project['idno'])) {
				$description_metadata['idno'] = $project['idno'];
			}

			$xml=$this->iso19139writer->generate($description_metadata);

			if ($download=='true' || $download==1){
				$this->load->helper('download');
				$filename=$project['idno'].'.xml';
				force_download($filename, $xml);
				die();
			}

			echo $xml;
			die();
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Export feature catalogue to ISO19110/XML
	 * 
	 */
	function iso19110_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid, $type='geospatial');
			$download=$this->input->get("download");

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$this->load->library('ISO19110Writer');
			$this->load->library('Geospatial_metadata_writer');
			
			// Get project info for filename
			$project_info = $this->Editor_model->get_basic_info($sid);
			
			// Get merged feature catalogue metadata
			$feature_catalogue = $this->geospatial_metadata_writer->get_merged_feature_catalogue($sid);
			
			// If feature catalogue is empty, throw error
			if (empty($feature_catalogue)) {
				throw new Exception("Feature catalogue not found and no features available in database");
			}

			// Generate XML
			$xml=$this->iso19110writer->generate($feature_catalogue);

			if ($download=='true' || $download==1){
				$this->load->helper('download');
				$filename = isset($project_info['idno']) && !empty($project_info['idno']) 
					? $project_info['idno'].'_feature_catalogue.xml' 
					: 'feature_catalogue_'.$sid.'.xml';
				force_download($filename, $xml);
				die();
			}

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

	/**
	 * 
	 * Get project lock status
	 * 
	 */
	function lock_status_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$is_locked = $this->Editor_model->is_project_locked($sid);
			$project_info = $this->Editor_model->get_basic_info($sid);
				
			if(!$project_info){
				throw new Exception("DATASET_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'is_locked'=>$is_locked,
				'project_id'=>$sid,
				'project_title'=>$project_info['title'],
				'project_idno'=>$project_info['idno']
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


	// get project version notes from metadata field
	function metadata_version_notes_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$version_notes=$this->Editor_model->get_metadata_version_notes($sid);

			$response=array(
				'status'=>'success',
				'version_notes'=>$version_notes
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
	 * Refresh core metadata fields from metadata field
	 * 
	 * Refreshes extracted fields (title, nation, year_start, year_end, attributes, study_idno)
	 * from the metadata JSON without modifying the metadata field
	 * 
	 * Body parameters:
	 * - projects: array of project IDs (required)
	 * - fields: array of field names to refresh (optional, defaults to all)
	 *   Valid fields: 'title', 'nation', 'year_start', 'year_end', 'attributes', 'study_idno'
	 * 
	 * Example:
	 * POST /api/editor/refresh_metadata
	 * Body: {
	 *   "projects": [24406, 24407],
	 *   "fields": ["attributes", "title"]
	 * }
	 * 
	 */
	function refresh_metadata_post()
	{
		try{
			$user_id=$this->get_api_user_id();
			$user=$this->api_user();
			$options=$this->raw_json_input();
			
			if (!isset($options['projects']) || !is_array($options['projects']) || count($options['projects'])==0){
				throw new Exception("Parameter `projects` is required");
			}
			
			$fields=isset($options['fields']) ? $options['fields'] : null;
			
			$result=array(
				'updated'=>array(),
				'skipped'=>array(),
				'errors'=>array()
			);
			
			foreach($options['projects'] as $project_id){
				$sid=$this->get_sid($project_id);
				
				try{
					$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);
					
					$refresh_result=$this->Editor_model->refresh_core_metadata_fields($sid, $fields);
					
					if ($refresh_result){
						$result['updated'][]=array(
							'id'=>$sid,
							'fields'=>array_keys($refresh_result)
						);
					} else {
						$result['skipped'][]=array(
							'id'=>$sid,
							'reason'=>'NO_CHANGES'
						);
					}
				}
				catch(Exception $e){
					$result['errors'][]=array(
						'id'=>$sid,
						'error'=>$e->getMessage()
					);
				}
			}

			$response=array(
				'status'=>'success',
				'updated'=>count($result['updated']),
				'skipped'=>count($result['skipped']),
				'errors'=>count($result['errors']),
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


	/**
	 * 
	 * Validate user has edit access to all collections
	 * Throws exception if validation fails
	 * 
	 * @param array $collection_ids - Array of collection IDs to validate
	 * @param object $user - User object (optional, defaults to current API user)
	 * @throws Exception - If user doesn't have edit access to any collection
	 * 
	 */
	private function _batch_validate_collection_access($collection_ids, $user=null)
	{		
		if (!$collection_ids || !is_array($collection_ids) || empty($collection_ids)) {
			return;
		}

		if ($user === null) {
			$user = $this->api_user();
		}

		foreach($collection_ids as $collection_id){
			//throw exception if user doesn't have edit access to collection
			$this->editor_acl->user_has_collection_acl_access($collection_id, 'edit', $user);
		}
	}

	/**
	 * 
	 * Add project to collections and log audit events
	 * 
	 * @param int $project_id - Project ID to add
	 * @param array $collection_ids - Array of collection IDs
	 * @param int $user_id - User ID for audit logging
	 * 
	 */
	private function _add_project_to_collections($project_id, $collection_ids, $user_id)
	{
		if (!$collection_ids || !is_array($collection_ids) || empty($collection_ids)) {
			return;
		}

		$this->Collection_model->add_batch_projects($collection_ids, array($project_id));
		
		foreach($collection_ids as $collection_id){
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$collection_id,
				$action='add-project', 
				$metadata=array(
					'project'=>$project_id
				),
				$user_id);
		}
	}
}
