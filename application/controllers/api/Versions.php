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
	 * @param int $version_id (optional)
	 * 
     */
    public function index_get($sid, $version_id=null)
    {
		try{
			$sid=$this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->user);

			if ($version_id){
				return $this->get_single_version($sid, $version_id);
			}
			
			$versions = $this->project_versions->get_versions($sid);		
			$project = $this->Editor_model->get_basic_info($sid);			
			$is_main_project = $this->project_versions->is_main_project($sid);
			$main_project_id = $is_main_project ? $sid : $project['pid'];			
			$main_project = $this->Editor_model->get_basic_info($main_project_id);
			
			$response = array(
				'status' => 'success',
				'result' => array(
					'project_id' => $sid,
					'project_title' => $project['title'],
					'project_idno' => $project['idno'],
					'is_main_project' => $is_main_project,
					'main_project_id' => $main_project_id,
					'main_project_title' => $main_project['title'],
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
     * 
     * 
     * Create a new version for a project
     * 
     */
    function create_post()
    {
        try{			
			$options=$this->raw_json_input();
            $required_params=array('sid','version_type','version_notes');

            foreach($required_params as $param){
                if (!isset($options[$param])){
                    throw new Exception("Parameter [$param] is required");
                }
            }

            $sid=$options['sid'];
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
	 * Delete a version for a project
	 * 
	 */
	function delete_post($sid)
	{
		try{
			$sid=$this->get_sid($sid);
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

}