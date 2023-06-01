<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Datafiles extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_datafile_model");
		$this->load->model("Editor_variable_model");
		
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
	 * list study data files
	 * 
	 */
	function index_get($id=null)
	{
		try{
			$this->editor_acl->user_has_project_access($id,$permission='view');
			
			$user_id=$this->get_api_user_id();
			$survey_datafiles=$this->Editor_datafile_model->select_all($id,true);
			
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
	 * 
	 * Create or update a data file
	 * 
	 */
	function index_post($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='edit');

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

				if (isset($options['id'])){
					$data_file=$this->Editor_model->data_file_by_pk_id($sid,$options['id']);

					if (!$data_file){
						throw new Exception("Data file not found");
					}

					$data_file_by_name=$this->Editor_model->data_file_by_name($sid,$options['file_name']);

					if($data_file_by_name && $data_file_by_name['id']!=$options['id']){
						throw new Exception("Data file name already exists");
					}

					$this->Editor_model->data_file_update($data_file["id"],$options);
				}else{

					//check if file name exists
					$data_file=$this->Editor_model->data_file_by_name($sid,$options['file_name']);

					if ($data_file){
						throw new Exception("Data file name already exists");
					}

					$this->Editor_model->data_file_insert($sid,$options);					
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
	 * Update data files sequence
	 * 
	 */
	function sequence_post($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='edit');

			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();			
			$options['sid']=$sid;

			$required_fields=array("wght","id");

			if (!isset($options['options'])){
				throw new Exception("Required field is missing: options");
			}

			$options=$options['options'];

			for($i=0;$i<count($options);$i++){			
				$row=$options[$i];

				//var_dump($row);
				
				if (!isset($row['id'])){
					throw new Exception("Required field is missing: id");
				}

				if (!isset($row['wght'])){
					throw new Exception("Required field is missing: wght");
				}

				$update_options=array(
					'wght'=>$row['wght']
				);

				$this->Editor_model->data_file_update($row['id'],$update_options);
			}
			
				
			$response=array(
				'status'=>'success',
				'datafile'=>$options
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
	 * Delete a data file
	 * 
	 */
	function delete_post($sid=null,$file_id=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
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
	


	function download_get($sid=null,$fid=null,$type=null)
	{
		try{			
			if (!$sid || !$fid){
				throw new Exception("Missing required parameters");
			}

			$this->load->helper("download");
			$valid_types=array('original','csv');

			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$files=$this->Editor_datafile_model->get_files_info($sid,$fid);

			if (!$type || !in_array($type,$valid_types)){
				$type='original';
			}

			if (!isset($files[$type]['filepath'])){
				throw new Exception("File not found");
			}

			$file_path=$files[$type]['filepath'];

			if (file_exists($file_path)){				
				force_download2($file_path);
			}
			else{
				throw new Exception("File not found");
			}
			
		}
		catch(Exception $e){
			$error=array(
				'error'=>$e->getMessage()
			);
			$this->set_response($error, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/**
	 * 
	 * Exported temporary files
	 * 
	 */
	function download_tmp_file_get($sid=null,$fid=null,$type=null)
	{
		try{			
			if (!$sid || !$fid || !$type){
				throw new Exception("Missing required parameters");
			}

			$this->load->helper("download");
			$valid_types=array('dta','csv','sav','json', 'sas','xpt');

			if (!in_array($type,$valid_types)){
				throw new Exception("Invalid file type");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$tmp_file_info=$this->Editor_datafile_model->get_tmp_file_info($sid,$fid,$type);

			if (file_exists($tmp_file_info['filepath'])){
				force_download2($tmp_file_info['filepath']);
				#unlink($tmp_file_info['filepath']);
			}
			else{
				throw new Exception("File not found");
			}
			
		}
		catch(Exception $e){
			$error=array(
				'error'=>$e->getMessage()
			);
			$this->set_response($error, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get data file by name
	 * 
	 */
	function by_name_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view');
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
	function generate_fid_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view');
			
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


}
