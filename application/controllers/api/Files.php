<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Files extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model('Dataset_model');
		$this->load->model("Survey_resource_model");
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
	 * list files attached to a dataset
	 * 
	 **/
	function index_get($dataset_idno=null)
	{
		try{
			$sid=$this->get_sid_from_idno($dataset_idno);

			$this->load->model('Survey_resource_model');
			$files=$this->Survey_resource_model->get_files_array($sid);
			array_walk($files, 'unix_date_to_gmt',array('date'));

			$response=array(
				'status'=>'success',
				'total'=>count($files),
				'files'=>$files
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}	
	}



	/**
	 * 
	 * 
	 * Download a file
	 * 
	 */
	function download_get($dataset_idno=null,$base64_filename=null)
	{

		try{
			$sid=$this->get_sid_from_idno($dataset_idno);

			if(!$base64_filename || trim($base64_filename)==""){
				throw new Exception("PARAM_NOT_SET: base64_name");
			}

			return $this->Survey_resource_model->download_file($sid, $base64_filename);
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
	 * upload file
	 * @resource_id (optional) if provided, file is attached to the resource
	 * 
	 **/ 
	function index_post($dataset_idno=null,$resource_id=null)
	{		
		try{
			$sid=$this->get_sid_from_idno($dataset_idno);

			$result=$this->Survey_resource_model->upload_file($sid,$file_field_name='file', $remove_spaces=false);

			$uploaded_file_name=$result['file_name'];
			$uploaded_path=$result['full_path'];
			
			//attach to resource if provided
			if(is_numeric($resource_id)){
				$options=array(
					'filename'=>$uploaded_file_name
				);
				$this->Survey_resource_model->update($resource_id,$options);
			}

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
	 * delete a file
	 * 
	 **/ 
	function index_delete($dataset_idno=null,$filename=null)
	{
		$base64_filename=$filename;

		try{
			$sid=$this->get_sid_from_idno($dataset_idno);

			if(!$base64_filename || trim($base64_filename)==""){
				throw new Exception("PARAM_NOT_SET: base64_name");
			}

			$this->Survey_resource_model->delete_file($sid, $base64_filename);		

			$output=array(
				'status'=>'success'
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

	
}
