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

	private function is_admin_role($roles)
	{
		foreach($roles as $role){
			if ($role['is_admin']==1){
				return true;
			}
		}
		return false;
	}


	function has_site_admin_access($user=null)
	{
		if(empty($user)){
			$user=$this->current_user();
		}

		if(!$user){
			die("acl_manager::User not set");
		}

		//get user roles
		$user_roles=$this->get_user_roles($user->id);

		if(!$user_roles){
			return false;
		}

		foreach($user_roles as $role){
			if ($role['role_id']==2){ //user
				return false;
			}
		}

		return true;
	}

	function has_access_or_die($resource,$privilege, $user=null, $repositoryid=null)
	{
		try{
			$this->has_access($resource, $privilege,$user,$repositoryid);
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

	function has_access($resource,$privilege, $user=null, $repositoryid=null)
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

		//resources by repository
		if(!empty($repositoryid)){
			foreach($permissions as $perm){
				if (!$acl->hasResource($resource)){
					$acl->addResource(new Resource($repositoryid.'-'.$perm['resource']));
				}
				$acl->allow($perm['role_id'],$repositoryid.'-'.$perm['resource'], $perm['permissions']);
			}
		}

		try{
			//test role as permissions
			foreach($user_roles as $role_id=>$role){				
				if(!empty($repositoryid)){
					if ($acl->isAllowed($role_id, $repositoryid.'-'.$resource, $privilege)){
						return true;
					}
				}else{
					if ($acl->isAllowed($role_id, $resource,$privilege)){
						return true;
					}
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
	
}

