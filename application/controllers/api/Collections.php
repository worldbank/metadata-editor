<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Collections extends MY_REST_Controller
{
	private $api_user;
	private $user_id;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Collection_model");
		$this->load->model("Editor_model");
		$this->load->model("Collection_project_acl_model");
		$this->load->model("Collection_acl_model");
		$this->load->library("Form_validation");
		$this->load->library("Editor_acl");
		$this->load->library("Audit_log");
		
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
		$this->user_id=$this->get_api_user_id();
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
	 * 
	 * Return all Collections
	 * 
	 */
	function index_get($uid=null)
	{
		try{
			if($uid){
				return $this->single_get($uid);
			}

			$this->has_access($resource_='collection',$privilege='view');
			$result=$this->Collection_model->select_all();
			array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
			$response=array(
				'status'=>'success',
				'total'=>count($result),
				'found'=>count($result),
				'collections'=>$result
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

	function single_get($id=null)
	{
		try{
			// Check user has view access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($id, 'view', $this->api_user);
			$result=$this->Collection_model->select_single($id);

			if(!$result){
				throw new Exception("COLLECTION_NOT_FOUND");
			}

			array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
			$response=array(
				'status'=>'success',
				'collection'=>$result
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
	 * Create new collection
	 * 
	 */	
	function index_post()
	{		
		try{			
			// Check if user can create collections (only global admin or global collection admin)
			if (!$this->editor_acl->user_is_admin($this->api_user) && 
				!$this->has_access('collection', 'admin')) {
				throw new Exception("You don't have permission to create collections");
			}

			$options=$this->raw_json_input();
			$options['created_by']=$this->user_id;
			$options['changed_by']=$this->user_id;
			$options['created']=time();
			$options['changed']=time();
			$new_collection_id=$this->Collection_model->insert($options);

			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$new_collection_id,
				$action='create', 
				$metadata=array(
					'collection'=>$options['title']
				),
				$this->user_id);

			//Add user as owner with collection ACL
			if ($new_collection_id){
				$options=array(
					'collection_id'=>$new_collection_id,
					'user_id'=>$this->user_id,
					'permissions'=>'admin'
				);				
				$this->Collection_acl_model->add_user($new_collection_id, $this->user_id, 'admin');
			}

			$output=array(
				'status'=>'success',
				'collection'=>$new_collection_id
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


	function update_post($id=null)
	{		
		try{
			if (!$id){
				throw new Exception("Missing parameter: ID");
			}

			// Check user has edit access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($id, 'edit', $this->api_user);
			$options=$this->raw_json_input();			
			$options['changed_by']=$this->user_id;
			$options['changed']=time();
			$result=$this->Collection_model->update($id,$options);

			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$id,
				$action='update', 
				$metadata=array(
					'collection'=> isset($options['title']) ? $options['title'] : ''
					),
				$this->user_id);

			$output=array(
				'status'=>'success',
				'Collection'=>$result
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


	function index_delete($id=null)
	{
		return $this->delete_post($id);
	}

	function delete_post($id=null)
	{		
		try{
			if (!$id){
				throw new Exception("Missing parameter: ID");
			}
			
			// Check user has admin access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($id, 'admin', $this->api_user);
			$result=$this->Collection_model->delete_nested($id);

			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$id,
				$action='delete', 
				$metadata=null,
				$this->user_id);

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
	 * Return projects in a collection
	 * 
	 */
	function projects_get($collection_id=null)
	{
		try{
			if (!$collection_id){
				throw new Exception("Missing parameter: collection ID");
			}

			// Check user has edit access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($collection_id, 'edit', $this->api_user);

			$result=$this->Collection_model->get_projects_list($collection_id);

			if ($result){
				array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			}

			$response=array(
				'status'=>'success',
				'found'=>count($result),
				'projects'=>$result
			);
						
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}	
	}



	function add_projects_post()
	{		
		try{
			$options=$this->raw_json_input();

			if (!isset($options['collections'])){
				throw new Exception("Missing parameter: collections");
			}

			if (!isset($options['projects'])){
				throw new Exception("Missing parameter: projects");
			}

			// Check user has edit access to all collections being modified
			foreach($options['collections'] as $collection_id){
				$this->editor_acl->user_has_collection_acl_access($collection_id, 'edit', $this->api_user);
			}

			if (isset($options['id_format']) && $options['id_format']=='idno'){
				
				$sid_arr=array();
				foreach((array)$options['projects'] as $idno){
					$sid=$this->get_sid($idno);
					$sid_arr[]=$sid;
				}
				$options['projects']=$sid_arr;
			}

			//check permissions for each project
			$access_errors=array();			
			foreach($options['projects'] as $sid){
				try{
					$this->editor_acl->user_has_project_access($sid,$permission='admin',$this->api_user);
				}
				catch(Exception $e){
					$access_errors[]=array(
						'sid'=>$sid,
						'error'=>$e->getMessage()
					);
				}
			}

			if (count($access_errors)>0){
				$response=array(
					'status'=>'failed',
					'errors'=>$access_errors
				);

				$this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}
			
			
			$result=$this->Collection_model->add_batch_projects($options['collections'], $options['projects']);

			foreach($options['collections'] as $collection_id){			
				foreach($options['projects'] as $sid){
					$this->audit_log->log_event(
						$obj_type='collection',
						$obj_id=$collection_id,
						$action='add-project', 
						$metadata=array(
							'project'=>$sid
						),
						$this->user_id);
				}
			}

			$output=array(
				'status'=>'success'
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

	function remove_projects_post()
	{		
		try{
			$options=$this->raw_json_input();

			if (!isset($options['collections'])){
				throw new Exception("Missing parameter: collections");
			}

			if (!isset($options['projects'])){
				throw new Exception("Missing parameter: projects");
			}

			// Check user has edit access to all collections being modified
			foreach($options['collections'] as $collection_id){
				$this->editor_acl->user_has_collection_acl_access($collection_id, 'edit', $this->api_user);
			}

			$sid_arr=array();
			if (isset($options['id_format']) && $options['id_format']=='idno'){
				foreach((array)$options['projects'] as $idno){
					$sid=$this->Collection_model->get_project_id_by_idno($idno);
					$sid_arr[]=$sid;
				}				
			}else{
				$sid_arr=(array)$options['projects'];
			}

			if (count($sid_arr)==0){
				throw new Exception("project was not found");
			}

			$this->Collection_model->remove_batch_projects($options['collections'], $sid_arr);

			foreach((array)$options['collections'] as $collection_id){			
				foreach($sid_arr as $sid){
					$this->audit_log->log_event(
						$obj_type='collection',
						$obj_id=$collection_id,
						$action='remove-project', 
						$metadata=array(
							'project'=>$sid
						),
						$this->user_id);
				}
			}

			$output=array(
				'status'=>'success'
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
	 * Return all users with access to projects in a collection
	 * 
	 */
	function user_project_access_get($collection_id=null)
	{
		try{
			if (!$collection_id){
				throw new Exception("Missing parameter: collection ID");
			}

			// Check user has access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($collection_id, 'view', $this->api_user);

			$result=$this->Collection_project_acl_model->select_all($collection_id);

			$output=array(
				'status'=>'success',
				'users'=>$result
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
	 * Add user access to projects in a collection
	 * 
	 */
	function user_project_access_post()
	{
		try{
			$options=$this->raw_json_input();

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

			if (!isset($options['permissions'])){
				throw new Exception("Missing parameter: permissions");
			}

			// Check user has access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($options['collection_id'], 'edit', $this->api_user);

			$result=$this->Collection_project_acl_model->upsert($options);

			//audit log
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$options['collection_id'],
				$action='user-access', 
				$metadata=array(
					'user'=>$options['user_id'],
					'permissions'=>$options['permissions']
				),
				$this->user_id);


			$output=array(
				'status'=>'success'
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

	function remove_user_project_access_post()
	{
		try{
			$options=$this->raw_json_input();

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

			// Check user has access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($options['collection_id'], 'edit', $this->api_user);

			$result=$this->Collection_project_acl_model->delete_user($options['collection_id'],$options['user_id']);

			//audit log
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$options['collection_id'],
				$action='remove-user', 
				$metadata=array(
					'user'=>$options['user_id']
				),
				$this->user_id);

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
	 * 
	 * Return all users with ACL access to a collection (collection-level permissions)
	 * 
	 */
	function user_acl_get($collection_id=null)
	{
		try{
			if (!$collection_id){
				throw new Exception("Missing parameter: collection ID");
			}

			// Check user has access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($collection_id, 'view', $this->api_user);
			
			$result=$this->Collection_acl_model->list_users($collection_id);

			$output=array(
				'status'=>'success',
				'users'=>$result
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
	 * Add user ACL access to collection (collection-level permissions)
	 * 
	 */
	function user_acl_post()
	{
		try{
			$options=$this->raw_json_input();

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

		if (!isset($options['permissions'])){
			throw new Exception("Missing parameter: permissions");
		}

		// Check user can manage ACL for this specific collection
		if (!$this->editor_acl->user_can_manage_collection_acl($options['collection_id'], $this->api_user)) {
			throw new Exception("You don't have permission to manage ACL for this collection");
		}

		$result=$this->Collection_acl_model->add_user($options['collection_id'], $options['user_id'], $options['permissions']);

			//audit log
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$options['collection_id'],
				$action='user-acl', 
				$metadata=array(
					'user'=>$options['user_id'],
					'permissions'=>$options['permissions']
				),
				$this->user_id);

			$output=array(
				'status'=>'success'
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
	 * 
	 * Update user ACL permissions for a collection
	 * 
	 */
	function user_acl_update_post()
	{
		try{
			$options=$this->raw_json_input();

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

		if (!isset($options['permissions'])){
			throw new Exception("Missing parameter: permissions");
		}

		// Check user can manage ACL for this specific collection
		if (!$this->editor_acl->user_can_manage_collection_acl($options['collection_id'], $this->api_user)) {
			throw new Exception("You don't have permission to manage ACL for this collection");
		}

		$result=$this->Collection_acl_model->update_user($options['collection_id'], $options['user_id'], $options['permissions']);

			//audit log
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$options['collection_id'],
				$action='user-acl-update', 
				$metadata=array(
					'user'=>$options['user_id'],
					'permissions'=>$options['permissions']
				),
				$this->user_id);

			$output=array(
				'status'=>'success'
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
	 * 
	 * Remove user ACL access from collection (collection-level permissions)
	 * 
	 */
	function user_acl_remove_post()
	{
		try{
			$options=$this->raw_json_input();

			if (!isset($options['collection_id'])){
				throw new Exception("Missing parameter: collection_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

					// Check user can manage ACL for this specific collection
		if (!$this->editor_acl->user_can_manage_collection_acl($options['collection_id'], $this->api_user)) {
			throw new Exception("You don't have permission to manage ACL for this collection");
		}

			// Prevent removing your own admin access (unless you're a global admin)
			if ($options['user_id'] == $this->user_id && !$this->editor_acl->user_is_admin($this->api_user)) {
				throw new Exception("Cannot remove your own admin access");
			}

			$result=$this->Collection_acl_model->remove_user($options['collection_id'], $options['user_id']);

			//audit log
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$options['collection_id'],
				$action='remove-user-acl', 
				$metadata=array(
					'user'=>$options['user_id']
				),
				$this->user_id);

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
	 * 
	 * Check if user has ACL access to a collection
	 * 
	 */
	function user_acl_check_get($collection_id=null, $user_id=null)
	{
		try{
			if (!$collection_id){
				throw new Exception("Missing parameter: collection ID");
			}

			if (!$user_id){
				$user_id = $this->user_id;
			}

			// Check user has access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($collection_id, 'view', $this->api_user);

			$has_access = $this->Collection_acl_model->user_has_access($collection_id, $user_id);
			$permissions = $this->Collection_acl_model->get_user_permissions($collection_id, $user_id);

			$output=array(
				'status'=>'success',
				'has_access'=>$has_access,
				'permissions'=>$permissions
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
	 * Return all Collections as Tree
	 * 
	 */
	function tree_get($id=null)
	{
		try{
			$this->has_access($resource_='collection',$privilege='view');
			$result=$this->Collection_model->get_collection_tree($id);
			
			$response=array(
				'status'=>'success',
				'collections'=>$result
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


	function tree_flatten_get($id=null)
	{
		try{
			$this->has_access($resource_='collection',$privilege='view');
			$result=$this->Collection_model->get_collection_flatten_tree($id);
			
			$response=array(
				'status'=>'success',
				'collections'=>$result
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
	 * Re-build collection tree (clousure table)
	 * 
	 */
	function tree_refresh_get()
	{
		try{
			$this->has_access($resource_='collection',$privilege='admin');
			$this->load->model("Collection_tree_model");			
			$this->Collection_tree_model->rebuild_tree();

			$response=array(
				'status'=>'success',
				//'collections'=>$result
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


	function tree_list_get($parent_id=null)
	{
		try{
			$this->has_access($resource_='collection',$privilege='view');
			$this->load->model("Collection_tree_model");
			$result=$this->Collection_tree_model->get_tree_flat();
			
			$response=array(
				'status'=>'success',
				'collections'=>$result
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
	 * Set project template by collection and project type
	 * 
	 * post @options {
	 * 	collection_id: int,
	 * 	project_type: string,  //project types: survey, timeseries, geospatial, document, table
	 * 	template_uid: string
	 * }
	 * 
	 */
	function template_post()
	{
		try{
			$this->has_dataset_access('edit');

			$options=$this->raw_json_input();

			$required_fields=array('collection_id','project_type','template_uid');

			foreach($required_fields as $field){
				if (!isset($options[$field])){
					throw new Exception("Missing parameter: $field");
				}
			}

			$collection_id=$options['collection_id'];
			$project_type=$options['project_type'];
			$template_uid=$options['template_uid'];


			$user=$this->api_user();
			$user_id=$this->get_api_user_id();

			// Check user has edit access to this specific collection
			$this->editor_acl->user_has_collection_acl_access($collection_id, 'edit', $this->api_user);

			//get collection by id
			$collection=$this->Collection_model->select_single($collection_id);

			if (!$collection){
				throw new Exception("Collection not found");
			}

			//get all projects in collection
			$projects=$this->Collection_model->get_projects($collection_id,$project_type);

			if (!$projects){
				throw new Exception("No projects found in collection matching the type: $project_type");
			}

			$result=array();

			foreach($projects as $project)
			{
				if (!isset($project['sid'])){
					$result['skipped'][]=array(
						'id'=>$project['sid'],
						'type'=>$project['type']
					);
					continue;
				}

				$sid=$project['sid'];				
				$this->Editor_model->set_project_template($sid,$template_uid);

				$result['updated'][]=array(
					'id'=>$sid,
					'type'=>$project['type']
				);				
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


	/**
	 * 
	 * Copy collection 
	 * 
	 * 	- projects
	 *  - users + permissions
	 * 
	 * 
	 */
	function copy_post()
	{
		try{
			$this->has_access($resource_='collection',$privilege='admin');
			$options=$this->raw_json_input();

			if (!isset($options['source_id'])){
				throw new Exception("Missing parameter: source_id");
			}

			if (!isset($options['target_id'])){
				throw new Exception("Missing parameter: target_id");
			}

			$source_id=(int)$options['source_id'];
			$target_id=(int)$options['target_id'];

			$result=$this->Collection_model->copy($source_id,$target_id);

			//audit log
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$source_id,
				$action='copy', 
				$metadata=array(
					'target'=>$target_id
				),
				$this->user_id);				

			$response=array(
				'status'=>'success',
				'collection_id'=>$result
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
	 * Copy collection 
	 * 
	 * 	- projects
	 *  - users + permissions
	 * 
	 * 
	 */
	function move_post()
	{
		try{
			$this->has_access($resource_='collection',$privilege='admin');
			$options=$this->raw_json_input();

			if (!isset($options['source_id'])){
				throw new Exception("Missing parameter: source_id");
			}

			if (!isset($options['target_id'])){
				throw new Exception("Missing parameter: target_id");
			}

			$source_id=(int)$options['source_id'];
			$target_id=(int)$options['target_id'];

			$result=$this->Collection_model->move($source_id,$target_id);

			//audit log
			$this->audit_log->log_event(
				$obj_type='collection',
				$obj_id=$source_id,
				$action='move', 
				$metadata=array(
					'target'=>$target_id
				),
				$this->user_id);

			$response=array(
				'status'=>'success',
				'collection_id'=>$result
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
	 * Get user's collection permissions for all collections
	 * Returns permission levels for collections user can access
	 * 
	 */
	function permissions_get()
	{
		try{
			// Check if user is global admin
			if ($this->editor_acl->user_is_admin($this->api_user)) {
				$output = array(
					'status' => 'success',
					'user_id' => $this->user_id,
					'is_admin' => true,
					'admin_type' => 'global',
					'collections' => array()
				);
				
				$this->set_response($output, REST_Controller::HTTP_OK);
				return;
			}

			// Check if user has global collection admin or edit roles
			try {
				$user_roles = $this->editor_acl->get_user_roles($this->api_user->id);
				$permissions = $this->editor_acl->get_roles_permissions(array_keys($user_roles));
				
				$has_global_collection_admin = false;
				$has_global_collection_edit = false;
				
				foreach($permissions as $perm) {
                    if ($perm['resource'] == 'collection') {
                        if (in_array('admin', $perm['permissions'])) {
                            $has_global_collection_admin = true;
                        }
                        if (in_array('edit', $perm['permissions'])) {
                            $has_global_collection_edit = true;
                        }
                    }
                }
                
                // If user has global collection admin role
                if ($has_global_collection_admin) {
                    $output = array(
                        'status' => 'success',
                        'user_id' => $this->user_id,
                        'is_admin' => true,
                        'admin_type' => 'collection',
                        'global_permission' => 'admin',
                        'collections' => array()
                    );
                    
                    $this->set_response($output, REST_Controller::HTTP_OK);
                    return;
                }
                
                // If user has global collection edit role
                if ($has_global_collection_edit) {
                    $output = array(
                        'status' => 'success',
                        'user_id' => $this->user_id,
                        'is_admin' => false,
                        'admin_type' => 'collection',
                        'global_permission' => 'edit',
                        'collections' => array()
                    );
                    
                    $this->set_response($output, REST_Controller::HTTP_OK);
                    return;
                }
            } catch(Exception $e) {
                log_message('error', 'Error checking global collection roles: ' . $e->getMessage());
            }

			// Get all collections with user's permission levels
			$result = $this->Collection_model->get_collections_with_user_permissions($this->user_id);
			
			// Check if user has any collection admin permissions
			$has_collection_admin = false;
			foreach ($result as $collection) {
				if ($collection['permissions'] === 'admin') {
					$has_collection_admin = true;
					break;
				}
			}

			// Simplify collection data to only include permissions
			$simplified_collections = array();
			foreach ($result as $collection) {
				$simplified_collections[] = array(
					'id' => $collection['id'],
					'permissions' => $collection['permissions']
				);
			}

			$output = array(
				'status' => 'success',
				'user_id' => $this->user_id,
				'is_admin' => $has_collection_admin,
				'admin_type' => $has_collection_admin ? 'collection' : 'none',
				'collections' => $simplified_collections
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	
	
}
