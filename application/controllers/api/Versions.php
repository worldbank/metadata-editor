<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Versions extends MY_REST_Controller
{
	private $user;
    private $user_id;

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
		$this->load->library("Project_versions");

		$this->is_authenticated_or_die();
		$this->user=$this->api_user();
        $this->user_id=$this->get_api_user_id();
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
	 * Get a list of versions for a project
	 * 
     * @param int $sid	 
	 * 
     */
    public function index_get($sid)
    {
		try{
			$sid=$this->get_sid($sid);
			$version_id = $this->get('version', true);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->user);

			if ($version_id){
				return $this->get_single_version($sid, $version_id);
			}

			$is_main_project = $this->project_versions->is_main_project($sid);
			$versions = $this->project_versions->get_versions($sid);		
			$project = $this->Editor_model->get_basic_info($sid);			
			
			$main_project_id = $is_main_project ? $sid : $project['pid'];			
			$main_project = $this->Editor_model->get_basic_info($main_project_id);
			
			$response = array(
				'status' => 'success',
				'result' => array(
					'id' => $sid,
					'idno' => $project['idno'],
					'title' => $project['title'],					
					'is_main_project' => $is_main_project,					
					'total_versions' => count($versions),
					'versions' => $versions
				)
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$response=array(
				'status'=>'error',
				'message'=>$e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
    }


	/**
	 * Get a single version of a project
	 * @param int $sid
	 * @param string $version_number
	 * @return array
	 */
	private function get_single_version($sid, $version_number)
	{
		$this->editor_acl->user_has_project_access($sid, $permission='view', $this->user);
		
		try {
			$version = $this->project_versions->get_version_by_version($sid, $version_number);
			
			$response = array(
				'status' => 'success',
				'result' => $version
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e) {
			$response = array(
				'status' => 'error',
				'message' => $e->getMessage()
			);
			$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


    /**
     * 
     * 
     * Create a new version for a project
     * 
     */
    function create_post()
    {
        try{			
			$options=$this->raw_json_input();
            $required_params=array('id','version_type','version_notes');

            foreach($required_params as $param){
                if (!isset($options[$param])){
                    throw new Exception("Parameter [$param] is required");
                }
            }

            $sid=$options['id'];
            $version_type=$options['version_type'];
            $version_notes=$options['version_notes'];

			$id=$this->get_sid($sid);

			$this->editor_acl->user_has_project_access($id,$permission='admin',$this->user);
			$this->audit_log->log_event($obj_type='project',$obj_id=$id,$action='version', $metadata='new versions created');
			
			$result=$this->project_versions->create_project_version($sid, $this->user_id, $version_type, $version_notes);

			$response=array(
				'status'=>'success',
                'result'=>$result
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
	 * Delete a version for a project using project ID of the version
	 * 
	 * @param int $id - Project ID of the version
	 * 
	 */
	function delete_by_id_post()
	{
		try{
			$options=$this->raw_json_input();
			$required_params=array('id');

			foreach($required_params as $param){
				if (!isset($options[$param])){
					throw new Exception("Parameter [$param] is required");
				}
			}

			$sid=$this->get_sid($options['id']);
			$this->editor_acl->user_has_project_access($sid,$permission='admin',$this->user);
			
			$is_main_project=$this->project_versions->is_main_project($sid);

			if ($is_main_project) {
				throw new Exception("Cannot delete the main project");
			}

			$this->Editor_model->unlock_project($sid);
			$this->Editor_model->delete_project($sid);
				
			$response=array(
				'status'=>'success',
				'message'=>'Project version was deleted successfully'
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
	 * Delete a version for a project by version number
	 * 
	 */
	function delete_post()
	{
		try{
			$options=$this->raw_json_input();
			$required_params=array('id','version');

			foreach($required_params as $param){
				if (!isset($options[$param])){
					throw new Exception("Parameter [$param] is required");
				}
			}

			$version = $this->project_versions->get_version_by_version($options['id'], $options['version']);

			if ($version['pid']!=$options['id']){
				throw new Exception("Provide ID for the main project");
			}

			$sid=$version['id'];		
			$is_main_project=$this->project_versions->is_main_project($sid);

			if ($is_main_project) {
				throw new Exception("Cannot delete the main project");
			}

			$this->Editor_model->unlock_project($sid);
			$this->Editor_model->delete_project($sid);
				
			$response=array(
				'status'=>'success',
				'message'=>'Project version was deleted successfully'
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