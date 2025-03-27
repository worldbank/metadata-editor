<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use Laminas\Permissions\Acl\Acl as Acl;
use Laminas\Permissions\Acl\Role\GenericRole as Role;
use Laminas\Permissions\Acl\Resource\GenericResource as Resource;

class Editor_acl
{
	var $debug=false;

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->ci =& get_instance();
		//$this->ci->load->model('Permissions_model');
		//$this->ci->load->model('repository_model');

	}



	function user_has_project_access($project_id,$permission=null,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		//global admin role
		if ($this->user_is_admin($user)){
			return true;
		}

		//check if user is project owner
		if ($this->is_user_project_owner($project_id,$user)){
			return true;
		}

		//check if user is project collaborator
		if ($this->user_has_shared_project_access($project_id,$user,$permission)){
			return true;
		}

		//check if user has access to the collection
		if ($this->user_has_collection_access($permission,$project_id,$user)){

			return true;
		}
 
		throw new Exception("You don't have permissions to access this project");
	}

	/**
	 * 
	 * Check if user is project owner
	 * 
	 */
	function is_user_project_owner($project_id,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		$this->ci->db->select("created_by");
		$this->ci->db->where("id",$project_id);
		$project=$this->ci->db->get("editor_projects")->row_array();
		
		if (!$project){
			return false;
		}

		//check if user is project owner
		if ($project['created_by']==$user->id){
			return true;
		}

		return false;
	}


	/**
	 * 
	 * check if user has shared access to the project
	 * 
	 */
	function user_has_shared_project_access($project_id,$user=null,$permission='view')
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		if (!$user)
		{
			throw new Exception("User not set");
		}


		//check if project is shared with user
		$this->ci->db->select("user_id,permissions");
		$this->ci->db->where("sid",$project_id);
		$this->ci->db->where("user_id",$user->id);
		$project_owners=$this->ci->db->get("editor_project_owners")->result_array();

		if (!$project_owners){
			return false;
		}

		$user_permissions=[];
		foreach($project_owners as $row)
		{
			$user_permissions[]=$row['permissions'];
		}

		//test access
		$has_access=$this->user_has_shared_project_access_acl($user_permissions, $permission);

		if (!$has_access){
			//throw new AclAccessDeniedException("Access denied, you don't have permissions");
			throw new Exception("Access denied, you don't have permissions");
		}

		return true;
	}

	/**
	 * 
	 * Check if user has access to the project
	 * 
	 * @privilege - array - view, edit, admin
	 * @permission - permission - view, edit, admin
	 * 
	 */
	private function user_has_shared_project_access_acl($privileges,$permission)
	{

		$acl = new Acl();

		//base role/user
		$acl->addRole(new Role('user'));

		$permissions_list=array('view','edit','admin');

		//for each permission add a role
		$acl->addRole(new Role('user-view'), 'user');
		$acl->addRole(new Role('user-edit'), 'user-view');
		$acl->addRole(new Role('user-admin'), 'user-edit');

		//add resources
		$acl->addResource(new Resource('project'));

		//allow access
		$acl->allow('user-view','project',array('view'));
		$acl->allow('user-edit','project',array('edit'));
		$acl->allow('user-admin','project',array('admin'));
		
		//add access
		foreach($privileges as $priv)
		{
			$role='user-'.$priv;
			$acl->allow($role,'project',$privileges);
		}


		if ($acl->isAllowed($role,'project',$permission) ){
			return true;
		}

		return false;	
	}


	/*function user_has_collection_access($project_id,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		//check user has access to the collection		
		$this->ci->db->select("editor_collection_access.user_id");
		$this->ci->db->join("editor_collection_access","editor_collection_projects.collection_id=editor_collection_access.collection_id");
		$this->ci->db->where("editor_collection_projects.sid",$project_id);
		$this->ci->db->where("editor_collection_access.user_id",$user->id);				
		$collection_access=$this->ci->db->get("editor_collection_projects")->row_array();

		if (!$collection_access){
			return false;
		}

		if($collection_access['user_id']==$user->id){
			return true;
		}

		return false;
	}*/

	function user_collection_permissions($project_id,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		//check user has access to the collection		
		$this->ci->db->select("editor_collection_access.*,editor_collection_projects.sid");
		$this->ci->db->join("editor_collection_access","editor_collection_projects.collection_id=editor_collection_access.collection_id");
		$this->ci->db->where("editor_collection_projects.sid",$project_id);
		$this->ci->db->where("editor_collection_access.user_id",$user->id);				
		$collection_access=$this->ci->db->get("editor_collection_projects")->result_array();

		return $collection_access;
	}

	function user_has_collection_access($permission,$project_id,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		$acl = new Acl();

		//base role/user
		$acl->addRole(new Role('user'));

		$permissions_list=array('view','edit','admin');

		//for each permission add a role
		$acl->addRole(new Role('user-view'), 'user');
		$acl->addRole(new Role('user-edit'), 'user-view');
		$acl->addRole(new Role('user-admin'), 'user-edit');

		//add resources
		$acl->addResource(new Resource('project'));

		//allow access
		$acl->allow('user-view','project',array('view'));
		$acl->allow('user-edit','project',array('edit'));
		$acl->allow('user-admin','project',array('admin'));

		//get user permissions on collections by projectID
		$collection_permissions=$this->user_collection_permissions($project_id,$user);

		if (!$collection_permissions){
			return false;
		}
		
		//add access and test permissions
		foreach($collection_permissions as $collection_permission)
		{
			$role='user-'.$collection_permission['permissions'];
			$acl->allow($role,'project',array($collection_permission['permissions']));

			if ($acl->isAllowed($role,'project',$permission) ){
				return true;
			}
		}

		return false;		
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
			if (!$acl->hasResource($perm['resource'])){
				$acl->addResource(new Resource($perm['resource']));
			}
			//admin role has access to all permissions
			if (in_array('admin',$perm['permissions'])){
				$acl->allow($perm['role_id'],$perm['resource'], array('view','edit','admin'));				
			}else{
				$acl->allow($perm['role_id'],$perm['resource'], $perm['permissions']);
			}
		}

		//resources by repository
		if(!empty($repositoryid)){
			foreach($permissions as $perm){
				if (!$acl->hasResource($repositoryid.'-'.$perm['resource'])){
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
			throw new Exception('Access denied. You don\'t have permissions to access this resource');
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
	 * Return a list of all permissions
	 * 
	 * 
	 */
	function get_user_permissions_by_project($project_id,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		//TODO
	}

	/**
	 * 
	 * Return project owner, collaborators, and collection permissions
	 * 
	 * 
	 * 
	 */
	function get_project_access_permissions($project_id)
	{
		$output=array();

		$this->ci->load->model("Editor_owners_model");

		$output['owner']=$this->ci->Editor_owners_model->get_project_owner($project_id);
		$output['collaborators']=$this->ci->Editor_owners_model->select_all($project_id);
		$output['collections']=$this->get_project_access_permissions_by_collections($project_id);

		return $output;
	}


	/**
     * Get users with access to the project via collections
     * 
     */
    function get_project_access_permissions_by_collections($sid)
    {

	/*		SELECT editor_collections.id as collection_id,editor_collections.title, ca.user_id, ca.permissions, p.sid,
				users.username, users.email
				FROM editor_collections
				inner join editor_collection_access ca on ca.collection_id=editor_collections.id
				inner join editor_collection_projects p on p.collection_id= ca.collection_id 
				inner join users on users.id=ca.user_id
				LIMIT 0,100
	*/

		$this->ci->db->select("editor_collections.id as collection_id,editor_collections.title, ca.user_id, ca.permissions, p.sid,users.username, users.email");
		$this->ci->db->join("editor_collection_access ca","ca.collection_id=editor_collections.id");
		$this->ci->db->join("editor_collection_projects p","p.collection_id= ca.collection_id");
		$this->ci->db->join("users","users.id=ca.user_id");
		$this->ci->db->where("p.sid",$sid);
		$result=$this->ci->db->get("editor_collections")->result_array();

		return $result;
    }



	/**
	 * 
	 * Test user has access to a template
	 * 
	 */
	function user_has_template_access($template_uid,$permission=null,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		if (!$user){
			throw new Exception("User not set");
		}

		//check if user has global template admin access
		if ($this->has_access('template_manager','admin', $user)){
			return true;
		}

		//check if user is template owner
		if ($this->is_user_template_owner($template_uid,$user)){
			return true;
		}

		//check if user is template collaborator
		if ($this->user_has_shared_template_access($template_uid,$user,$permission)){
			return true;
		}

		throw new Exception("You don't have permissions to access this template");
	}

	/**
	 * 
	 * Check if user is template owner
	 * 
	 */
	function is_user_template_owner($template_uid,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		if (!$user){
			throw new Exception("User not set");
		}

		$this->ci->db->select("owner_id");
		$this->ci->db->where("uid",$template_uid);
		$template=$this->ci->db->get("editor_templates")->row_array();
		
		if (!$template){
			return false;
		}

		//check if user is template owner
		if ($template['owner_id']==$user->id){
			return true;
		}

		return false;
	}


	/**
	 * 
	 * check if user has shared access to the template
	 * 
	 */
	function user_has_shared_template_access($template_uid,$user=null,$permission='view')
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		if (!$user){
			throw new Exception("User not set");
		}

		//editor_template_acl -template_id, permissions, user_id
		//check if template is shared with user
		$this->ci->db->select("user_id,permissions");
		$this->ci->db->join("editor_templates","editor_templates.id=editor_template_acl.template_id");
		$this->ci->db->where("editor_templates.uid",$template_uid);
		$this->ci->db->where("editor_template_acl.user_id",$user->id);
		$template_users=$this->ci->db->get("editor_template_acl")->result_array();

		if (!$template_users){
			return false;
		}

		$user_permissions=[];
		foreach($template_users as $row)
		{
			$user_permissions[]=$row['permissions'];
		}

		//test access
		$has_access=$this->user_has_shared_template_access_acl($user_permissions, $permission);

		if (!$has_access){
			//throw new AclAccessDeniedException("Access denied, you don't have permissions");
			throw new Exception("Access denied, you don't have permissions");
		}

		return true;
	}


	/**
	 * 
	 * Check if user has access to the template
	 * 
	 * @privilege - array - view, edit, admin
	 * @permission - permission - view, edit, admin
	 * 
	 */
	private function user_has_shared_template_access_acl($privileges,$permission)
	{

		$acl = new Acl();

		//base role/user
		$acl->addRole(new Role('user'));

		$permissions_list=array('view','edit','admin');

		//for each permission add a role
		$acl->addRole(new Role('user-view'), 'user');
		$acl->addRole(new Role('user-edit'), 'user-view');
		$acl->addRole(new Role('user-admin'), 'user-edit');

		//add resources
		$acl->addResource(new Resource('template'));

		//allow access
		$acl->allow('user-view','template',array('view'));
		$acl->allow('user-edit','template',array('edit'));
		$acl->allow('user-admin','template',array('admin'));
		
		//add access
		foreach($privileges as $priv)
		{
			$role='user-'.$priv;
			$acl->allow($role,'template',$privileges);
		}

		if ($acl->isAllowed($role,'template',$permission) ){
			return true;
		}

		return false;	

	}


	/**
	 * 
	 * Test user has access to admin metadata
	 * 
	 */
	function user_has_admin_metadata_access($template_id,$permission=null,$user=null)
	{
		if (!$user){
			$user=(object)$this->current_user();
		}

		if (!$user){
			throw new Exception("User not set");
		}

		$this->ci->load->model("Admin_metadata_acl_model");

		//get permissions list [view, edit, admin]
		$permissions=$this->ci->Admin_metadata_acl_model->get_user_permissions($template_id,$user->id);

		if (!$permissions){
			throw new Exception("Access denied, you don't have permissions");
		}

		//test access
		$has_access=$this->user_has_admin_metadata_access_acl($permissions, $permission);

		if (!$has_access){
			//throw new AclAccessDeniedException("Access denied, you don't have permissions");
			throw new Exception("Access denied, you don't have permissions");
		}
	}


	/**
	 * 
	 * Check if user has access to the Metadata Type
	 * 
	 * @privilege - array - view, edit, admin
	 * @permission - permission - view, edit, admin
	 * 
	 */
	private function user_has_admin_metadata_access_acl($privileges,$permission)
	{
		$acl = new Acl();

		//base role/user
		$acl->addRole(new Role('user'));

		$permissions_list=array('view','edit','admin');

		//for each permission add a role
		$acl->addRole(new Role('user-view'), 'user');
		$acl->addRole(new Role('user-edit'), 'user-view');
		$acl->addRole(new Role('user-admin'), 'user-edit');

		//add resources
		$acl->addResource(new Resource('admin_metadata'));

		//allow access
		$acl->allow('user-view','admin_metadata',array('view'));
		$acl->allow('user-edit','admin_metadata',array('edit'));
		$acl->allow('user-admin','admin_metadata',array('admin'));
		
		//add access
		foreach($privileges as $priv)
		{
			$role='user-'.$priv;
			$acl->allow($role,'admin_metadata',$privileges);
		}

		if ($acl->isAllowed($role,'admin_metadata',$permission) ){
			return true;
		}

		return false;	

	}
}

