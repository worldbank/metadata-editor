<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

/**
 * 
 * Admin Metadata
 * 
 */
class Admin_metadata extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		
		$this->load->model("Editor_model");
        $this->load->model("Editor_template_model");
        $this->load->model('Admin_metadata_acl_model');
        $this->load->model('Admin_metadata_model');
        $this->load->model('Admin_metadata_projects_model');

		$this->load->library("Editor_acl");		
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
	}

    /**
     * 
     * Get all admin metadata templates logged in user has access to
     * 
     */
    function templates_get($template_uid=null)
    {
        try{

            if ($template_uid){
                return $this->template_get($template_uid);
            }

            $this->has_access($resource_='template_manager',$privilege='view');
            $result= $this->Admin_metadata_model->get_admin_metadata_templates_by_acl($this->api_user->id);
            array_walk($result, 'unix_date_to_gmt',array('created','changed'));

            foreach($result as $key=>$row){
                $result[$key]['permissions']=$this->Admin_metadata_acl_model->get_user_permissions($row['id'],$this->api_user->id);
            }

            $response=array(
				'status'=>'success',
				'result'=>$result
			);	

            $this->set_response($response, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 
     * 
     * Return admin template by UID
     * 
     * 
     */
    function template_get($uid=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$result=$this->Admin_metadata_model->get_admin_metadata_template_by_acl($this->api_user->id,$uid);
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

            //template acl
            $result['permissions']=$this->Admin_metadata_acl_model->list_users($result['id'],$this->api_user->id);

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

    function templates_by_user_get()
    {
        return $this->templates_get();
    }

    /**
     * 
     * Get all admin metadata templates logged in user has access to
     * 
     */
    function templates_by_project_get($project_id=null)
    {
        try{

            if (!$project_id){
                throw new Exception("Missing parameter: project_id");
            }

            $this->has_access($resource_='template_manager',$privilege='view');
            $result= $this->Admin_metadata_model->get_admin_metadata_templates_by_acl($this->api_user->id);
            array_walk($result, 'unix_date_to_gmt',array('created','changed'));

            foreach($result as $key=>$row){
                $result[$key]['permissions']=$this->Admin_metadata_acl_model->get_user_permissions($row['id'],$this->api_user->id);
                $result[$key]['is_enabled']=$this->Admin_metadata_projects_model->is_attached($project_id,$row['id']);
                $result[$key]['has_data']=$this->Admin_metadata_model->exists($row['id'],$project_id);

                if ($result[$key]['is_enabled'] || $result[$key]['has_data']){
                    $result[$key]['is_active']=true;    
                }
            }

            $response=array(
				'status'=>'success',
				'result'=>$result
			);	

            $this->set_response($response, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    
    
    /**
     * 
     * 
     * Get metadata data
     * 
     * @querystring params (optional):
     *  - offset
     *  - limit
     *  - template - comma separated list of template uids
     *  - project_id
     * 
     * - returns metadata from multiple projects
     * 
     */
    function data_query_get()
    {
        try{
            $template_id=null;

            $offset=(int)$this->input->get('offset') ? (int)$this->input->get('offset') : 0;
            $limit=(int)$this->input->get('limit') ? (int)$this->input->get('limit') : 50;
            $template=$this->input->get('template');
            $project_id=$this->input->get('project_id');

            $template_uid_list=array();
            if (!empty($template)){
                $template_uid_list=explode(',',$template);
            }

            if (!empty($project_id)){
                $project_id=$this->get_sid($project_id);
            }

            //get templates for active user
            $templates_id_list=$this->Admin_metadata_acl_model->get_templates_id_by_user($this->api_user->id, $template_uid_list);

            if (empty($templates_id_list)){
                throw new Exception("One or more templates not found");
            }

            $search_options=array(
                'offset'=>$offset,
                'limit'=>$limit,
                'templates'=>$templates_id_list,
                'project_id'=>$project_id
            );
            
            $result=$this->Admin_metadata_model->search($search_options);

            $response=array(
                'status'=>'success',
                //'total'=>$result['total'],
                'found'=>count($result['data']),
                'offset'=>$offset,
                'limit'=>$limit,
                'data'=>$result['data']
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }

    }

    /**
     * 
     * 
     * Get metadata (data) by project id and/or admin metadata template uid
     * 
     * 
     */
    function data_get($project_id, $admin_template_uid=null)
    {
        try{
            $project_id=$this->get_sid($project_id);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $output_format=$this->input->get('output_format');

            if (!in_array($output_format, array('raw','metadata'))){
                $output_format='raw';
            }

            $template_id=null;

            if ($admin_template_uid){
                $template_id=$this->Editor_template_model->get_id_by_uid($admin_template_uid);

                if (!$template_id){
                    throw new Exception("Template not found: " . $template_uid);
                }
                
                $this->editor_acl->user_has_admin_metadata_access($template_id,$permission='view',$this->api_user);
                $result=$this->Admin_metadata_model->select_single($template_id,$project_id);
                
                if ($output_format=='metadata'){
                    if (isset($result['metadata'])){
                        $result=$result['metadata'];
                    }
                }

            }else{
                $result=$this->Admin_metadata_model->get_project_metadata($project_id, $template_id, $output_format, $this->api_user->id);
            }
            
            if (!$result){
                throw new Exception("NO_METADATA_FOUND");
            }

            $response=array(
                'status'=>'success',
                'data'=>$result
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }

    }


    function data_post()
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

            if (!isset($options['project_id'])){
                throw new Exception("Project ID is required");
            }

            if (!isset($options['template_uid'])){
                throw new Exception("Template UID is required");
            }

            $project_id=$this->get_sid($options['project_id']);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $template_id=$this->Editor_template_model->get_id_by_uid($options['template_uid']);

            if (!$template_id){
                throw new Exception("Template not found: " . $options['template_uid']);
            }

            //$this->editor_acl->user_has_metadata_type_access($metadata_type_id,$permission='edit',$this->api_user);

            //metadata
            if (!isset($options['metadata'])){
                throw new Exception("Metadata is missing!");
            }

			$result=$this->Admin_metadata_model->upsert($template_id, $project_id,$options);

			$output=array(
				'status'=>'success',
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}      
    }


    function data_remove_post()
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

            if (!isset($options['project_id'])){
                throw new Exception("Project ID is required");
            }

            if (!isset($options['template_uid'])){
                throw new Exception("Template UID is required");
            }

            $project_id=$this->get_sid($options['project_id']);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $template_id=$this->Editor_template_model->get_id_by_uid($options['template_uid']);

            if (!$template_id){
                throw new Exception("Template not found: " . $template_uid);
            }

			$result=$this->Admin_metadata_model->delete($template_id, $project_id);            

			$output=array(
				'status'=>'success',
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}      
    }


    //Metadata types ACL

    /**
	 * 
	 * Share Admin Metadata with user
	 * 
	 * @options JSON array
	 * [
	 * 	{
	 * 		"template_uid": "template uid",
	 * 		"user_id": "user id",
	 * 		"permissions": "view|edit|admin"
	 * 	}
	 * ]
	 * 
	 */
	function acl_post()
	{		
		try{
			$this->has_access($resource_='templates',$privilege='admin');
			$options=$this->raw_json_input();

			if (!is_array($options)){
				throw new Exception("Invalid input: must be an array");
			}

			foreach($options as $option){
				if (!isset($option['template_uid'])){
					throw new Exception("Missing parameter: template_uid");
				}

                $template_id=$this->Editor_template_model->get_id_by_uid($option['template_uid']);

                if (!$template_id){
                    throw new Exception("Template not found: " . $option['template_uid']);
                }

				//$this->editor_acl->user_has_metadata_type_access($option['metadata_type_id'],$permission='admin',$this->api_user);
                $this->Admin_metadata_acl_model->add_user($template_id,$option['user_id'],$option['permissions']);
			}

			$output=array(
				'status'=>'success',
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Return ACL for an admin template
     * 
     */
	function acl_get($template_uid=null)
	{
		try{
			$this->has_access($resource_='templates',$privilege='view');

            if (!$template_uid){
                throw new Exception("Missing parameter: template_uid");
            }

            $template_id=$this->Editor_template_model->get_id_by_uid($template_uid);

            if (!$template_id){
                throw new Exception("Template not found: " . $template_uid);
            }

			$result=$this->Admin_metadata_acl_model->list_users($template_id);
				
			$response=array(
				'status'=>'success',
				'users'=>$result
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
     * Remove user from ACL
     * 
     * 
     */
	function acl_remove_post()
	{
		try{
            $this->has_access($resource_='templates',$privilege='admin');
			$options=$this->raw_json_input();

			if (!isset($options['template_uid'])){
				throw new Exception("Missing parameter: template_uid");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

            $template_id=$this->Editor_template_model->get_id_by_uid($options['template_uid']);

            if (!$template_id){
                throw new Exception("Template not found: " . $template_uid);
            }

            $result=$this->Admin_metadata_acl_model->remove_user($template_id,$options['user_id']);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Enable/attach metadata to a project
     * 
     * post params: {project_id, template_uid}
     * 
     */
    function attach_post()
    {
        try{
            $this->has_access($resource_='templates',$privilege='admin');
            $options=$this->raw_json_input();

            if (!isset($options['project_id'])){
                throw new Exception("Missing parameter: project_id");
            }

            if (!isset($options['template_uid'])){
                throw new Exception("Missing parameter: template_uid");
            }

            $project_id=$this->get_sid($options['project_id']);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $template_id=$this->Editor_template_model->get_id_by_uid($options['template_uid']);

            if (!$template_id){
                throw new Exception("Template not found: " . $template_uid);
            }

            $result=$this->Admin_metadata_projects_model->attach($project_id, $template_id);

            $output=array(
                'status'=>'success',
                'result'=>$result
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Remove metadata template from a project
     * 
     * post params: {project_id, template_uid}
     * 
     */
    function detach_post()
    {
        try{
            $this->has_access($resource_='templates',$privilege='admin');
            $options=$this->raw_json_input();

            if (!isset($options['project_id'])){
                throw new Exception("Missing parameter: project_id");
            }

            if (!isset($options['template_uid'])){
                throw new Exception("Missing parameter: template_uid");
            }

            $project_id=$this->get_sid($options['project_id']);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $template_id=$this->Editor_template_model->get_id_by_uid($options['template_uid']);

            if (!$template_id){
                throw new Exception("Template not found: " . $template_uid);
            }

            $result=$this->Admin_metadata_projects_model->delete($project_id, $template_id);

            $output=array(
                'status'=>'success',
                'result'=>$result
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Get metadata edit history
     * 
     * 
     */
    function edit_history_get($project_id, $admin_template_uid=null)
    {
        try{
            $project_id=$this->get_sid($project_id);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            if (!$admin_template_uid){
                throw new Exception("Missing parameter: admin_template_uid");
            }
            
            $template_id=$this->Editor_template_model->get_id_by_uid($admin_template_uid);

            if (!$template_id){
                throw new Exception("Template not found: " . $admin_template_uid);
            }

            $offset=(int)$this->input->get('offset') ? (int)$this->input->get('offset') : 0;
            $limit=(int)$this->input->get('limit') ? (int)$this->input->get('limit') : 50;

            $this->editor_acl->user_has_admin_metadata_access($template_id,$permission='view',$this->api_user);
            $result=$this->Admin_metadata_model->history($template_id,$project_id, $limit, $offset); 

            $response=array(
                'status'=>'success',
                'offset'=>$offset,
                'limit'=>$limit,                
                'data'=>$result
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

	
    
    function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}
	
}
