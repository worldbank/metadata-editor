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
        $this->load->model('Audit_log_model');

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
     *  - date_from - filter by date from (format: YYYY-MM-DD), filters on 'changed' field
     *  - date_to - filter by date to (format: YYYY-MM-DD), filters on 'changed' field
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
            $date_from=$this->input->get('date_from');
            $date_to=$this->input->get('date_to');

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

            // Add date range filters if provided
            if (!empty($date_from) || !empty($date_to)){
                if (!empty($date_from)){
                    $date_from_timestamp = $this->convert_date_to_unix($date_from);
                    if ($date_from_timestamp === false){
                        throw new Exception("Invalid date_from format. Use YYYY-MM-DD");
                    }
                    $search_options['date_from'] = $date_from_timestamp;
                }

                if (!empty($date_to)){
                    $date_to_timestamp = $this->convert_date_to_unix($date_to, true);
                    if ($date_to_timestamp === false){
                        throw new Exception("Invalid date_to format. Use YYYY-MM-DD");
                    }
                    $search_options['date_to'] = $date_to_timestamp;
                }
            }
            
            $result=$this->Admin_metadata_model->search($search_options);

            $response=array(
                'status'=>'success',
                'total'=>$result['total'],
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
                $result = array();
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

    /**
     * 
     * Patch admin metadata - apply JSON patch operations for partial updates
     * 
     * @options JSON object:
     *  {
     *    "project_id": "project id or idno",
     *    "template_uid": "template uid",
     *    "patches": [
     *      {
     *        "op": "add|remove|replace|move|copy|test",
     *        "path": "/path/to/element",
     *        "value": "value" (for add/replace operations)
     *      }
     *    ]
     *  }
     * 
     */
    function data_patch_post()
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

            if (!isset($options['patches'])){
                throw new Exception("`Patches` parameter is required");
            }

            $project_id=$this->get_sid($options['project_id']);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $template_id=$this->Editor_template_model->get_id_by_uid($options['template_uid']);

            if (!$template_id){
                throw new Exception("Template not found: " . $options['template_uid']);
            }

            $this->editor_acl->user_has_admin_metadata_access($template_id, $permission='edit', $this->api_user);

            $options['changed_by']=$this->api_user->id;
            $options['changed']=date("U");

            $result=$this->Admin_metadata_model->patch($template_id, $project_id, $options);

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
			$options=$this->raw_json_input();

			if (!is_array($options)){
				throw new Exception("Invalid input: must be an array");
			}

			foreach($options as $option){
				if (!isset($option['template_uid'])){
					throw new Exception("Missing parameter: template_uid");
				}

				// Allow system template_manager/admin role OR share-tab admin collaborators
				$this->editor_acl->user_has_template_access($option['template_uid'], $permission='admin', $this->api_user);

                $template_id=$this->Editor_template_model->get_id_by_uid($option['template_uid']);

                if (!$template_id){
                    throw new Exception("Template not found: " . $option['template_uid']);
                }

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
            if (!$template_uid){
                throw new Exception("Missing parameter: template_uid");
            }

			// Allow system template_manager/admin role OR share-tab admin collaborators
			$this->editor_acl->user_has_template_access($template_uid, $permission='admin', $this->api_user);

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
			$options=$this->raw_json_input();

			if (!isset($options['template_uid'])){
				throw new Exception("Missing parameter: template_uid");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

			// Allow system template_manager/admin role OR share-tab admin collaborators
			$this->editor_acl->user_has_template_access($options['template_uid'], $permission='admin', $this->api_user);

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
                throw new Exception("Template not found: " . $options['template_uid']);
            }

            // Allow system template_manager role OR users with explicit ACL access to this template
            try {
                $this->has_access($resource_='template_manager', $privilege='view');
            } catch (Exception $e) {
                $this->editor_acl->user_has_admin_metadata_access($template_id, $permission='view', $this->api_user);
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
                throw new Exception("Template not found: " . $options['template_uid']);
            }

            // Allow system template_manager role OR users with explicit ACL access to this template
            try {
                $this->has_access($resource_='template_manager', $privilege='view');
            } catch (Exception $e) {
                $this->editor_acl->user_has_admin_metadata_access($template_id, $permission='view', $this->api_user);
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

            $pagination = $this->get_pagination_params(15, 100);
            $offset = $pagination['offset'];
            $limit = $pagination['limit'];

            $this->editor_acl->user_has_admin_metadata_access($template_id,$permission='view',$this->api_user);

            $filter = array(
                'obj_type' => 'admin-metadata',
                'obj_id' => $this->Admin_metadata_model->get_project_id_by_template($template_id, $project_id),
                'obj_ref_id' => $project_id,
            );

            if (empty($filter['obj_id'])) {
                $response = array(
                    'status' => 'success',
                    'offset' => $offset,
                    'limit' => $limit,
                    'total' => 0,
                    'found' => 0,
                    'data' => array(),
                );
                $this->set_response($response, REST_Controller::HTTP_OK);
                return;
            }

            $result = $this->Audit_log_model->get_history($filter, $limit, $offset);
            $total = $this->Audit_log_model->get_total_count($filter);

            $response=array(
                'status'=>'success',
                'offset'=>$offset,
                'limit'=>$limit,
                'total'=>$total,
                'found'=>count($result),
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

    /**
     * 
     * Convert date string to Unix timestamp
     * 
     * @param string $date_iso - Date in YYYY-MM-DD format
     * @param bool $is_end - If true, adds 86400 seconds (1 day) to include entire end date
     * @return int|false - Unix timestamp or false on invalid format
     * 
     */
    private function convert_date_to_unix($date_iso, $is_end=false)
    {
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_iso)){
            return false;
        }

        $date = strtotime($date_iso);
        
        if ($date === false){
            return false;
        }

        // If this is an end date, add 86400 seconds (1 day) to include the entire day
        if ($is_end){
            $date += 86400;
        }

        return $date;
    }
	
}
