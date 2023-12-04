<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Files extends MY_REST_Controller
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
	 * Project files
	 * 
	 * Return all files for a project
	 * 
	 **/ 
	function index_get($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');

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
	function index_post($sid=null,$file_type='documentation')
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);
			$user_id=$this->get_api_user_id();
			$user=$this->api_user();

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

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
	 * 
	 * Return files and folders for a project with file size information
	 * 
	 */
	function size_get($sid=null,$details=0)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			if ($details==1){
				$details=true;
			}else{
				$details=false;
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view');
			$result=$this->Editor_resource_model->files_with_sizes($sid,$details);

			$output=array(
				'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * 
	 * Check if documentation file exists
	 * 
	 * @sid
	 * @filename
	 * @doc_type data | documentation | thumbnail
	 * 
	 */
	function exists_post($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$filename=basename($this->post('file_name'));
			$doc_type=$this->post('doc_type');
			$user=$this->api_user();

			if (!$sid){
				throw new Exception("Missing parameter: sid");
			}

			if (!$filename){
				throw new Exception("Missing parameter: file_name");
			}

			if (!$doc_type){
				throw new Exception("Missing parameter: doc_type");
			}
			
			$this->editor_acl->user_has_project_access($sid,$permission='view',$user);

			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$result=$this->Editor_resource_model->check_file_exists($sid,$doc_type,$filename);

			$output=array(
				'exists'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * Delete resource file
	 * 
	 */
	function delete_resource_file_post($sid=null,$resource_id=null)
	{

		try{
			$sid=$this->get_sid($sid);
			$user=$this->api_user();

			if (!$sid){
				throw new Exception("Missing parameter: sid");
			}

			if (!$resource_id){
				throw new Exception("Missing parameter: resource_id");
			}
			
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$result=$this->Editor_resource_model->delete_file_by_resource($sid,$resource_id);

			$output=array(
				'status'=>'success'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}


	}

}
