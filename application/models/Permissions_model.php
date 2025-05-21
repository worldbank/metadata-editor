<?php
class Permissions_model extends CI_Model {
    
	public function __construct()
    {
        parent::__construct();
    }

	/**
	*
	* Returns an array of all available permissions
	**/
	public function get_available_permissions()
	{
		$query = $this->db->select('p.*,pu.url')
				->from('permissions p')
				->join('permission_urls pu', 'p.id = pu.permission_id', 'inner')
				->order_by('p.section', 'asc')
				->order_by('p.weight', 'asc');
					
		return $query->get()->result_array();		
	}
	
	/**
	*
	* Return permissions divided in sections
	**/
	public function get_grouped_permission_list()
	{
		//get all permissions
		$permissions=$this->get_available_permissions();
		
		//grouped by sections
		$output=array();
		
		foreach($permissions as $perm)
		{
			$output[$perm['section']][$perm['id']]=$perm;
		}
		
		return $output;
	}
	
	/**
	*
	* Get group basic info by group id
	**/
	public function get_group_info($group_id)
	{
		$query=$this->db->select('name, group_type, access_type')
					->from('groups')
					->where('id',$group_id)
					->get()->row_array();
		
		if ($query)
		{			
			return $query;	
		}
		
		return FALSE;	
	}
	
	/**
	*
	* Get permissions assigned to the group
	**/
	public function get_group_permissions($group_id)
	{
		$this->db->select('permission_id');
		$this->db->where('group_id',$group_id);
		$query=$this->db->get('group_permissions')->result_array();
		
		$output=array();
		foreach($query as $row)
		{
			$output[]=$row['permission_id'];
		}
		
		return $output;
	}
	
	
	/**
	*
	* Update group permissions
	**/
	public function update_perms($group_id,$perms_array)
	{
		//remove existing permissions for the group
		$this->db->where('group_id',$group_id);
		$this->db->delete('group_permissions');

		if (!is_array($perms_array))
		{
			return;
		}
		
		//assign new permissions
		foreach($perms_array as $perm)
		{
			if (is_numeric($perm))
			{
				$options=array(
							'group_id'		=>$group_id,
							'permission_id'	=>$perm
							);
				
				$this->db->insert('group_permissions',$options);			
			}
		}
	}
	
	/**
	*
	* Set group access for repositories
	**/
	public function update_repo_perms($group_id,$repo_array)
	{		
		//remove existing permissions for the group
		$this->db->where('group_id',$group_id);
		$this->db->delete('group_repo_access');
	
		if (!is_array($repo_array))
		{
			return;
		}

		//assign new permissions
		foreach($repo_array as $repo)
		{
			if (is_numeric($repo))
			{
				$options=array(
							'group_id'	=>$group_id,
							'repo_id'	=>$repo
							);
				
				$this->db->insert('group_repo_access',$options);			
			}
		}
		
	}
	
	
	/**
	*
	* Returns a list of all repositories
	**/
	public function get_repositories()
	{
		$this->db->select('*');
		return $this->db->get('repositories')->result_array();
	}
	
	/**
	*
	* Returns user group permissions for all repositories
	**/
	public function get_group_repositories($group_id)
	{
		$this->db->select('repo_id');
		$this->db->where('group_id',$group_id);
		$rows=$this->db->get('group_repo_access')->result_array();
		
		$repos=array();
		foreach($rows as $row)
		{
			$repos[]=$row['repo_id'];
		}
		
		return $repos;
	}
	
	
	public function get_permission_labels()
	{
		$this->db->order_by('section,weight','ASC');
		$permissions=$this->db->get('permissions')->result_array();
		
		//grouped by sections
		$output=array();
		
		foreach($permissions as $perm)
		{
			$output[$perm['section']][$perm['id']]=$perm;
		}
		
		return $output;
	}
	
	public function get_permission_urls()
	{
		$rows=$this->db->get('permission_urls')->result_array();	
		
		$output=array();
		foreach($rows as $row)
		{
			$output[$row['permission_id']][]=$row['url'];
		}

		return $output;
	}
	
	public function get_permission_by_id($perm_id)
	{
		$permissions=$this->db->from('permissions')
							->where('id',$perm_id)
							->get()->row_array();
		
		//get associated URLs
		$permissions['urls']=$this->get_permission_associated_urls($perm_id);
		return $permissions;
	}
	
	public function get_permission_associated_urls($perm_id)
	{
		$rows=$this->db->from('permission_urls')
						->where('permission_id',$perm_id)
						->get()->result_array();	
						
		$urls=array();
		foreach($rows as $row)
		{
			$urls[]=$row['url'];
		}			
		return $urls;	
	}

	/**
	*
	* Update permission description + urls
	**/
	public function update_permission_options($perm_id,$options)
	{		
		$valid_fields=array('label','section','description','weight');
		
		$data=array();
		//build update statement
		foreach($options as $key=>$value)
		{
			if (in_array($key,$valid_fields) )
			{
				$data[$key]=$value;
			}
		}
		
		//update permission description table
		$this->db->where('id', $perm_id);
		$result=$this->db->update('permissions', $data); 
		
		if (!$result)
		{
			return FALSE;
		}
		
		//update permission urls table
		
		//first remove any assigned urls
		$this->db->where('permission_id',$perm_id);
		$this->db->delete('permission_urls');
		
		//assign new urls
		foreach($options['url'] as $url)
		{
			if (trim($url)==''){continue;}
			
			$options=array(
							'url'=>$url,
							'permission_id'=>$perm_id
						);
			$result=$this->db->insert('permission_urls',$options);			
			
			if (!$result)
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
	
	
	/**
	*
	* Add new permission description + urls
	**/
	public function add_permission($options)
	{		
		$valid_fields=array('label','section','description','weight');
		
		$data=array();
		
		//build update statement
		foreach($options as $key=>$value)
		{
			if (in_array($key,$valid_fields) )
			{
				$data[$key]=$value;
			}
		}
				
		//add to permission description table
		$result=$this->db->insert('permissions', $data); 
		
		if (!$result)
		{
			return FALSE;
		}
		
		//id for newly added row
		$perm_id=$this->db->insert_id();
	
		//assign new urls
		foreach($options['url'] as $url)
		{
			if (trim($url)==''){continue;}
			
			$options=array(
							'url'=>$url,
							'permission_id'=>$perm_id
						);
			$result=$this->db->insert('permission_urls',$options);			
			
			if (!$result)
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
	
	public function delete($perm_id)
	{
		//remove from permissions
		$this->db->where('id',$perm_id);
		$this->db->delete('permissions');
		
		//remove permission urls
		$this->db->where('permission_id',$perm_id);
		$this->db->delete('permission_urls');
	}
	
	
	
	/**
	*
	* Set group access for repositories
	**/
	public function update_repo_permissions($repo_id,$groups_array)
	{	
		//remove existing permissions for the repo
		$this->db->where('repo_id',$repo_id);
		$this->db->delete('group_repo_access');

		if (!is_array($groups_array))
		{
			return;
		}
	
		//assign new permissions
		foreach($groups_array as $group_id)
		{
			if (is_numeric($group_id))
			{
				$options=array(
							'group_id'	=>$group_id,
							'repo_id'	=>$repo_id
							);
				
				$this->db->insert('group_repo_access',$options);			
			}
		}		
	}
		
}//end class