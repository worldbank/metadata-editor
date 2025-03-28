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
			
			$result=$this->project_search->search($limit,$offset,null,$search_options, $this->api_user);
			array_walk($result['result'], 'unix_date_to_gmt',array('created','changed'));

			//add collections and tags to each study
			$project_id_list=array();
			foreach($result['result'] as $row){
				$project_id_list[]=$row['id'];
			}

			if (count($project_id_list)>0){						
				//get collections
				$collections=$this->Collection_model->collections_by_projects($project_id_list);

				//add collections and tags to each study
				foreach($result['result'] as $key=>$row){
					$result['result'][$key]['collections']=isset($collections[$row['id']]) ? $collections[$row['id']] : array();
				}
			}

			
			$response=array(
				'status'=>'success',
				'total'=>$this->project_search->get_total_count($search_options, $this->api_user),
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
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$result=$this->Editor_model->get_row($sid);
			array_walk($result, 'unix_date_to_gmt_row',array('created','changed'));
				
			if(!$result){
				throw new Exception("DATASET_NOT_FOUND");
			}

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





	private function get_collection_options(&$options)
	{
		$collections=array();
		if (isset($options['collection_ids'])){
			//$collections['id_list']=$options['collection_ids'];
			$id_list=$options['collection_ids'];
			unset($options['collection_ids']);
			return $id_list;			
		}
		if (isset($options['collection_names'])){
			//$collections['names']=$options['collection_names'];
			$names=$options['collection_names'];
			unset($options['collection_names']);

			foreach($names as $collection_name){
				$collection_id=$this->Collection_model->get_collection_id_by_name($collection_name);
				if ($collection_id){
					$collections[]=$collection_id;
				}
			}
			
		}
		return $collections;
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

			//overwrite
			if (isset($project_options['overwrite']) 
				&& ($project_options['overwrite']==1 
				|| strtolower($project_options['overwrite'])=='true')){

				$sid=$this->Editor_model->get_project_id_by_idno($idno);				

				if ($sid){
					return $this->update_post($type,$sid);
				}
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

			$this->audit_log->log_event($obj_type='project',$obj_id=$dataset_id,$action='create', $user_id);			

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
			$collections=$this->get_collection_options($options);
						
			//check project exists and is of correct type
			$exists=$this->Editor_model->check_id_exists($id,$type);

			if(!$exists){
				throw new Exception("Project with the type [".$type ."] not found");
			}

			$this->editor_acl->user_has_project_access($id,$permission='edit',$user);			
			
			$options['changed_by']=$user_id;
			$options['changed']=date("U");

			//get project metadata
			//$project_metadata=$this->Editor_model->get_metadata($id);
			
			//validate & update project
			$this->Editor_model->update_project($type,$id,$options,$validate);
			$this->Editor_model->create_project_folder($id);

			//generate diff
			//$diff=$this->Editor_model->get_metadata_diff($project_metadata,$options);
			//$this->audit_log->log_event($obj_type='project',$obj_id=$id,$action='update', $metadata=$diff);

			//add to collections
			if (is_array($collections) && count($collections)>0){
				$this->Collection_model->add_batch_projects($collections, array($id));
			}

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
	 * set:
	 * 	- template
	 * 	- thumbnail
	 * 	- created_by
	 * 	- changed_by
	 * 	- created
	 * 	- changed
	 * 	- idno
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

			$this->editor_acl->user_has_project_access($sid,$permission='edit', $this->api_user());
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
			$this->has_dataset_access('edit');

			$options=$this->raw_json_input();
			$user=$this->api_user();
			$user_id=$this->get_api_user_id();
			$sid=$this->get_sid($sid);

			if (!$template_uid){
				throw new Exception("Template UID is required");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);			
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


	/**
	 * 
	 * Download project metadata as JSON
	 * 
	 * @exclude_private_fields - exclude private fields - 0 = include, 1 = exclude
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

			$options=array(
				'exclude_private_fields'=>$exclude_private_fields,
				'external_resources'=>$inc_ext_resources,
				'admin_metadata'=>$inc_adm_meta,
				'user_id'=>$this->get_api_user_id()
			);

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);			
			$this->project_json_writer->download_project_json($sid,$options);
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

			if(!$exists){
				throw new Exception("Project not found");
			}

			if ((int)$this->input->get("exclude_private_fields")===1){
				$exclude_private_fields=1;
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view', $user=$this->api_user());
			$result=$this->Editor_model->generate_project_pdf($sid, $pdf_options=array(
				'exclude_private_fields'=>$exclude_private_fields
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
			$user_id=$this->get_api_user_id();
			$this->has_access($resource_='editor',$privilege='view');

			$result=$this->project_search->get_facets($user_id);

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

			$result=$this->Audit_log_model->get_history($obj_type='project',$obj_id=$sid,$limit=10, $offset=0);
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

}
