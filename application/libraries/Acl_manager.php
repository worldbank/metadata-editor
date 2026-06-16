<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use Laminas\Permissions\Acl\Acl as Acl;
use Laminas\Permissions\Acl\Role\GenericRole as Role;
use Laminas\Permissions\Acl\Resource\GenericResource as Resource;

class Acl_manager
{
	var $debug=false;
	private $acl_permissions;

	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "Acl_manager Class Initialized.");
		$this->ci =& get_instance();
		$this->ci->load->library('Form_validation');
		//$this->ci->load->model('Permissions_model');
		//$this->ci->load->model('repository_model');

		if ($this->ci->config->item('acl_debug')==true){
			$this->debug=true;
		}

		$this->ci->load->config('acl_permissions');
		$this->acl_permissions=$this->ci->config->item("acl_permissions");
	}


	/**
	 * 
	 * Return a list of all permissions
	 * 
	 * 
	 */
	function get_all_permissions()
	{
		$acl_permissions=$this->ci->config->item("acl_permissions");
		$collection_rules=$this->ci->config->item("acl_permissions_collections");
		
		$repositories=[];
		$collection_permissions=[];
		/*$repositories=$this->ci->Repository_model->select_all();
		array_unshift($repositories, $this->ci->Repository_model->get_central_catalog_array());

		$collection_permissions=[];
		foreach($collection_rules as $resource_id){
			foreach($repositories as $repository){
				$repo_permissions=$acl_permissions[$resource_id];
				$repo_permissions['title']=$repository['title'] .' ['. $repository['repositoryid'].']'. ' -  '.$repo_permissions['title']  ;
				//$acl_permissions[$repository['repositoryid'].'.'.$resource_id]=$repo_permissions;
				$collection_permissions[$repository['repositoryid'].'-'.$resource_id]=$repo_permissions;
			}
		}*/

		return array(
			'permissions'=>$acl_permissions,
			'permissions_collections'=>$collection_permissions,
			'repositories'=>$repositories
		);
	}

	/**
	 * 
	 * Return a list of all roles
	 */
	function get_roles()
	{
		$this->ci->db->select("*");
		$this->ci->db->order_by("weight");
		$this->ci->db->order_by("name");
		return $this->ci->db->get("roles")->result_array();
	}

	function get_role_by_name($role_name)
	{
		$this->ci->db->select("*");
		$this->ci->db->where("name",$role_name);
		return $this->ci->db->get("roles")->row_array();
	}

	function get_role_by_id($role_id)
	{
		$this->ci->db->select("*");
		$this->ci->db->where("id",$role_id);
		return $this->ci->db->get("roles")->row_array();
	}

	function create_role($role, $description=null,$weight=0)
	{
		if ($this->get_role_by_name($role)){
			throw new Exception("Role already exists");
		}

		$options=array(
			'name'=>$role,
			'description'=>$description, 
			'weight'=>$weight
		);

		return $this->ci->db->insert("roles",$options);
	}

	function update_role($role_id, $role, $description=null,$weight=0)
	{
		$role_info=$this->get_role_by_name($role);

		if(!empty($role_info) && $role_info['id']!=$role_id){
			throw new Exception("Role already exists");
		}

		$options=array(
			'name'=>$role,
			'description'=>$description,
			'weight'=>$weight
		);

		$this->ci->db->where('id',$role_id);
		return $this->ci->db->update("roles",$options);
	}


	function delete_role($role_id)
	{
		$this->ci->db->where('id',$role_id);
		return $this->ci->db->delete("roles");
	}


	function remove_role_permissions($role_id)
	{
		$this->ci->db->where('role_id',$role_id);
		return $this->ci->db->delete("role_permissions");
	}

	function set_role_permissions($role_id,$resource, $permissions=array())
	{
		$options=array(
			'role_id'=>$role_id,
			'resource'=>$resource,
			'permissions'=>implode(",",$permissions)
		);

		return $this->ci->db->insert("role_permissions",$options);
	}

	function get_role_permissions($role_id)
	{
		$this->ci->db->where('role_id',$role_id);
		$result=$this->ci->db->get("role_permissions")->result_array();

		foreach($result as $idx=>$row){
			$result[$idx]['permissions']=explode(",",$row['permissions']);
		}

		return $result;
	}

	function get_roles_permissions($roles)
	{
		if (empty($roles)){
			return array();
		}

		$this->ci->db->where_in('role_id',$roles);
		$result=$this->ci->db->get("role_permissions")->result_array();

		foreach($result as $idx=>$row){
			$result[$idx]['permissions']=explode(",",$row['permissions']);
		}

		return $result;
	}

	/**
	 * 
	 * Return roles by user
	 * 
	 */
	function get_user_roles($user_id)
	{
		$this->ci->db->select("user_roles.user_id, user_roles.role_id, roles.name, roles.is_admin");
		$this->ci->db->where("user_id",$user_id);
		$this->ci->db->join('roles', 'roles.id = user_roles.role_id');		
		$result= $this->ci->db->get("user_roles")->result_array();

		$user_roles=array();
		foreach($result as $row){
			$user_roles[$row['role_id']]=$row;
		}

		return $user_roles;
	}


	/**
	 * 
	 * assign a role to a user
	 * 
	 */
	function set_user_role($user_id, $role_id)
	{
		$options=array(
			'role_id'=>$role_id,
			'user_id'=>$user_id
		);

		if (!$this->check_user_role_exists($user_id, $role_id)){
			return $this->ci->db->insert("user_roles",$options);
		}
	}


	function check_user_role_exists($user_id, $role_id)
	{
		$this->ci->db->select("*");
		$this->ci->db->where("user_id",$user_id);
		$this->ci->db->where("role_id",$role_id);		
		$result= $this->ci->db->get("user_roles")->result_array();

		if (count($result)>0){
			return true;
		}
		return false;
	}
	


	/**
	 * 
	 * delete all user roles
	 * 
	 */
	function remove_user_roles($user_id)
	{
		$this->ci->db->where("user_id",$user_id);		
		return $this->ci->db->delete("user_roles");
	}


	/**
	*
	* Returns the currently logged in user object
	**/
	function current_user()
	{
		return $this->ci->ion_auth->current_user();
	}

	function user_is_admin($user=null)
	{
		if(empty($user)){
			$user=$this->current_user();
		}

		if(!$user){
			throw new Exception("acl_manager::User not set");
		}

		//get user roles
		$user_roles=$this->get_user_roles($user->id);

		//user has admin access
		if($this->is_admin_role($user_roles)==true){
			return true;
		}

		return false;
	}

	/**
	 * Require the current user to have an admin role (is_admin=1).
	 */
	function require_admin_or_die($user=null)
	{
		if (!$this->user_is_admin($user)) {
			show_error('Access denied', 403);
		}
	}

	/**
	 * Role IDs flagged with is_admin=1 (e.g. site Admin).
	 *
	 * @return int[]
	 */
	function get_admin_role_ids()
	{
		$this->ci->db->select('id');
		$this->ci->db->where('is_admin', 1);
		$rows = $this->ci->db->get('roles')->result_array();

		return array_map('intval', array_column($rows, 'id'));
	}

	/**
	 * Roles that may be shown/assigned by the given user.
	 * Non-admins cannot assign admin roles.
	 */
	function roles_for_assigner($user=null)
	{
		$roles = $this->get_roles();

		if ($this->user_is_admin($user)) {
			return $roles;
		}

		$admin_role_ids = $this->get_admin_role_ids();
		if (empty($admin_role_ids)) {
			return $roles;
		}

		return array_values(array_filter($roles, function ($role) use ($admin_role_ids) {
			return !in_array((int)$role['id'], $admin_role_ids, true);
		}));
	}

	/**
	 * Sanitize submitted role IDs for create/replace assignment.
	 * Non-admins cannot grant admin roles; existing admin roles on the target are preserved.
	 *
	 * @param int[]|mixed $role_ids
	 * @param int|null $target_user_id
	 * @return int[]
	 */
	function sanitize_role_assignment($role_ids, $target_user_id=null, $user=null)
	{
		$role_ids = array_values(array_filter(array_map('intval', (array)$role_ids)));

		if ($this->user_is_admin($user)) {
			return $role_ids;
		}

		$admin_role_ids = $this->get_admin_role_ids();
		$sanitized = array_values(array_diff($role_ids, $admin_role_ids));

		if ($target_user_id !== null && !empty($admin_role_ids)) {
			$existing = $this->get_user_roles($target_user_id);
			foreach ($existing as $role) {
				$role_id = (int)$role['role_id'];
				if (in_array($role_id, $admin_role_ids, true) && !in_array($role_id, $sanitized, true)) {
					$sanitized[] = $role_id;
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Strip admin roles from bulk-add requests by non-admins.
	 *
	 * @param int[]|mixed $role_ids
	 * @return int[]
	 */
	function filter_assignable_role_ids($role_ids, $user=null)
	{
		$role_ids = array_values(array_filter(array_map('intval', (array)$role_ids)));

		if ($this->user_is_admin($user)) {
			return $role_ids;
		}

		return array_values(array_diff($role_ids, $this->get_admin_role_ids()));
	}

	private function is_admin_role($roles)
	{
		foreach($roles as $role){
			if ($role['is_admin']==1){
				return true;
			}
		}
		return false;
	}


	/**
	 * True when the user is a super admin or has any site-administration permission.
	 * Excludes the general "editor" resource (project access, not /admin UI).
	 */
	function has_site_admin_access($user=null)
	{
		if(empty($user)){
			$user=$this->current_user();
		}

		if(!$user){
			return false;
		}

		try{
			if($this->user_is_admin($user)){
				return true;
			}
		}
		catch(Exception $e){
			return false;
		}

		foreach($this->get_site_admin_resources() as $resource){
			if($this->user_has_resource_access($resource, $user)){
				return true;
			}
		}

		return false;
	}

	/**
	 * ACL resources that grant access to the site administration area.
	 *
	 * @return string[]
	 */
	private function get_site_admin_resources()
	{
		$resources = array_keys($this->acl_permissions);

		return array_values(array_diff($resources, array('editor')));
	}

	/**
	 * Check access without throwing (for composite checks).
	 */
	function check_access($resource, $privilege, $user=null)
	{
		try{
			$this->has_access($resource, $privilege, $user);
			return true;
		}
		catch(Exception $e){
			return false;
		}
	}

	/**
	 * True if the user has any configured privilege on the resource.
	 */
	function user_has_resource_access($resource, $user=null)
	{
		if(!isset($this->acl_permissions[$resource]['permissions'])){
			return false;
		}

		foreach($this->acl_permissions[$resource]['permissions'] as $perm){
			if($this->check_access($resource, $perm['permission'], $user)){
				return true;
			}
		}

		return false;
	}

	function has_access_or_die($resource,$privilege, $user=null)
	{
		try{
			$this->has_access($resource, $privilege,$user);
		}
		catch(Exception $e){
			if ($this->ci->input->is_ajax_request()) {
				$this->ci->output
					->set_status_header(403)
        			->set_content_type('application/json');
				die (json_encode($e->getMessage()));
			}

			show_error($e->getMessage());
		}	
	}

	function has_access($resource,$privilege, $user=null)
	{
		if(empty($user)){
			$user=$this->current_user();
		}

		if(!$user){
			throw new Exception("acl_manager::User not set");
		}

		//get user roles
		$user_roles=$this->get_user_roles($user->id);

		//user has admin access
		if($this->is_admin_role($user_roles)==true){
			return true;
		}

		//get role resources and permissions list
		$permissions=$this->get_roles_permissions(array_keys($user_roles));

		//load into zend acl
		$acl = new Acl();

		//add roles
		foreach($user_roles as $role_id=>$role){
			$acl->addRole(new Role($role_id));
		}

		//check roles has access to resource
		foreach($permissions as $perm){
			if ($perm['resource']==$resource){
				if (!$acl->hasResource($resource)){
					$acl->addResource(new Resource($resource));
				}
				$acl->allow($perm['role_id'],$perm['resource'], $this->get_resource_sub_priveleges($perm['resource'],$perm['permissions']));
			}
		}

		// Ensure the resource exists in the ACL even when the user has no permissions
		// for it, so isAllowed() returns false instead of throwing "Resource not found".
		if (!$acl->hasResource($resource)){
			$acl->addResource(new Resource($resource));
		}

		try{
			//test role as permissions
			foreach($user_roles as $role_id=>$role){
				if ($acl->isAllowed($role_id, $resource, $privilege)){
					return true;
				}
			}
		}
		catch(Exception $e){
			throw new Exception('Access denied:: '. $e->getMessage());
		}
		

		$debug_info=[];
		if ($this->debug==true){
			$debug_info[]='Access denied for resource:: '.$resource;
			$debug_info[]='<pre style="padding:20px;">';						
			$debug_info[]=print_r($user_roles,true);
			$debug_info[]=print_r($permissions, true);
			$debug_info[]='</pre>';
			
			throw new Exception(implode("\n", $debug_info));
		}else{
			throw new AclAccessDeniedException('Access denied for resource:: '.$resource);
		}
	}



	/**
	 * 
	 * get sub_priveleges associated to a resource's privilege
	 * 
	 * A higher privilege gets all child privileges e.g admin can view, edit, delete
	 * 
	 * 
	 * */
	function get_resource_sub_priveleges($resource,$privileges)
	{
		$resource_sub_perms=$privileges;
		if (isset($this->acl_permissions[$resource]['permissions'])){
			foreach($privileges as $privilege){
				foreach($this->acl_permissions[$resource]['permissions'] as $permission){				
					if ($permission['permission']==$privilege && isset($permission['sub_permissions']) ){					
						$resource_sub_perms=array_merge($resource_sub_perms,$permission['sub_permissions']);
					}
				}
			}
		}
		
		return $resource_sub_perms;
	}

	/**
	 * Registry ACL: codelist | data_structure.
	 *
	 * Actions: browse (catalogue + editor:view), edit, import (import or edit), delete, admin.
	 *
	 * @param string $resource codelist|data_structure
	 * @param string $action browse|edit|import|delete|admin
	 * @param object|null $user
	 */
	function registry_can($resource, $action, $user = null)
	{
		switch ($action) {
			case 'browse':
				return $this->check_access($resource, 'view', $user)
					|| $this->check_access('editor', 'view', $user);
			case 'edit':
				return $this->check_access($resource, 'edit', $user);
			case 'import':
				return $this->check_access($resource, 'import', $user)
					|| $this->check_access($resource, 'edit', $user);
			case 'delete':
				return $this->check_access($resource, 'delete', $user);
			case 'admin':
				return $this->user_is_admin($user)
					|| $this->check_access($resource, 'admin', $user);
			default:
				throw new Exception('Unknown registry action: ' . $action);
		}
	}

	/**
	 * @param string $resource codelist|data_structure
	 * @param string $action browse|edit|import|delete|admin
	 * @param object|null $user
	 */
	function registry_require($resource, $action, $user = null)
	{
		if ($this->registry_can($resource, $action, $user)) {
			return true;
		}
		$this->_registry_access_denied_die();
	}

	/**
	 * Flags for CI.user_info (Vue nav, registry pages, action buttons).
	 *
	 * @param object|null $user
	 * @return array<string, bool>
	 */
	function registry_user_info_flags($user = null)
	{
		return array(
			'has_codelist_permission' => $this->registry_can('codelist', 'browse', $user),
			'has_data_structure_permission' => $this->registry_can('data_structure', 'browse', $user),
			'can_edit_codelist' => $this->registry_can('codelist', 'edit', $user),
			'can_import_codelist' => $this->registry_can('codelist', 'import', $user),
			'can_delete_codelist' => $this->registry_can('codelist', 'delete', $user),
			'can_edit_data_structure' => $this->registry_can('data_structure', 'edit', $user),
			'can_import_data_structure' => $this->registry_can('data_structure', 'import', $user),
			'can_delete_data_structure' => $this->registry_can('data_structure', 'delete', $user),
		);
	}

	private function _registry_access_denied_die()
	{
		if ($this->ci->input->is_ajax_request()) {
			$this->ci->output
				->set_status_header(403)
				->set_content_type('application/json');
			die(json_encode('Access denied'));
		}
		show_error('Access denied', 403);
	}
	
}

