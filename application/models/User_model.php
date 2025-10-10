<?php
class User_model extends CI_Model {
 
    public function __construct()
    {
        // model constructor
        parent::__construct();
		
		$this->load->config('ion_auth');
		$this->tables  = $this->config->item('tables');		
    }
	
	//search users 
    function search($limit = NULL, $offset = NULL,$filter=NULL,$sort_by=NULL,$sort_order=NULL)
    {
		//$this->output->enable_profiler(TRUE);
		$this->db->flush_cache();
		//$this->db->start_cache();

		//columns
		$columns=sprintf('%s.id,username,email,active,created_on,last_login,country,company',
							$this->tables['users']);
		//$columns.=','.$this->tables['groups'].'.name as group_name';
		
		//select columns for output
		$this->db->select($columns);
		
		//allowed_fields for searching or sorting
		$db_fields=array(
					'username'=>'username',
					'first_name'=>'first_name',
					'last_name'=>'last_name',
					'email'=>'email',
					'country'=>'country',
					'company'=>'company',
					//'group_name'=>'user_groups.name',
					'active'=>'active',
					'created_on'=>'created_on',
					'last_login'=>'last_login'
					);
		
		//set where
		if ($filter)
		{			
			$has_keyword_search = false;
			$keyword_field = '';
			$keyword_value = '';
			
			// First pass: handle keyword searches
			foreach($filter as $f)
			{
				if (isset($f['keywords'])) {
					$has_keyword_search = true;
					$keyword_field = $f['field'];
					$keyword_value = $f['keywords'];
					break; // Only handle one keyword search
				}
			}
			
			// Handle keyword search with proper grouping
			if ($has_keyword_search) {
				if ($keyword_field == 'all') {
					$this->db->group_start();
					foreach($db_fields as $field)
					{
						$this->db->or_like($field, $keyword_value); 
					}
					$this->db->group_end();
				} else if (in_array($keyword_field, $db_fields)) {
					$this->db->like($keyword_field, $keyword_value);
				}
			}
			
			// Second pass: handle other filters
			foreach($filter as $f)
			{
				// Skip keyword searches (already handled above)
				if (isset($f['keywords'])) {
					continue;
				}
				
				// Handle filters with operators (including NEVER with null value)
				if (isset($f['operator'])) {
					if (in_array($f['field'],$db_fields))
					{
						if ($f['operator'] == 'NEVER') {
							$this->db->group_start();
							$this->db->where($f['field'], null);
							$this->db->or_where($f['field'] . ' = created_on');
							$this->db->group_end();
						} else {
							$this->db->where($f['field'] . ' ' . $f['operator'], $f['value']);
						}
					}
				}
				// Handle filters with values (but not null values)
				else if (isset($f['value']) && $f['value'] !== null) {
					if (in_array($f['field'],$db_fields))
					{
						// Default equals comparison
						$this->db->where($f['field'], $f['value']);
					}
				}
			}
		}
		
		$this->db->join($this->tables['meta'], sprintf('%s.user_id = %s.id',$this->tables['meta'],$this->tables['users']));
		//$this->db->join($this->tables['groups'], sprintf('%s.id = %s.group_id',$this->tables['groups'],$this->tables['users']),'left');
		//$this->db->stop_cache();

		//set order by
		if ($sort_by!='' && $sort_order!='')
		{
			if ( array_key_exists($sort_by,$db_fields))
			{
				$this->db->order_by($db_fields[$sort_by], $sort_order); 
			}	
		}
		
		//set Limit clause
	  	$this->db->limit($limit, $offset);
		$this->db->from($this->tables['users']);

        $result= $this->db->get()->result_array();
		return $result;
    }
	
  	
    function search_count($filter=NULL)
    {
          return $this->db->count_all_results($this->tables['users']);
    }
    
    /**
     * Get users by role with filters
     */
    function get_users_by_role($role_id, $limit = NULL, $offset = NULL, $filter=NULL, $sort_by=NULL, $sort_order=NULL)
    {
        $this->db->flush_cache();
        
        //columns
        $columns=sprintf('%s.id,username,email,active,created_on,last_login,country,company',
                        $this->tables['users']);
        
        //select columns for output
        $this->db->select($columns);
        
        //allowed_fields for searching or sorting
        $db_fields=array(
                    'username'=>'username',
                    'first_name'=>'first_name',
                    'last_name'=>'last_name',
                    'email'=>'email',
                    'country'=>'country',
                    'company'=>'company',
                    'active'=>'active',
                    'created_on'=>'created_on',
                    'last_login'=>'last_login'
                    );
        
        //set where
        if ($filter)
        {			
            $has_keyword_search = false;
            $keyword_field = '';
            $keyword_value = '';
            
            // First pass: handle keyword searches
            foreach($filter as $f)
            {
                if (isset($f['keywords'])) {
                    $has_keyword_search = true;
                    $keyword_field = $f['field'];
                    $keyword_value = $f['keywords'];
                    break; // Only handle one keyword search
                }
            }
            
            // Handle keyword search with proper grouping
            if ($has_keyword_search) {
                if ($keyword_field == 'all') {
                    $this->db->group_start();
                    foreach($db_fields as $field)
                    {
                        $this->db->or_like($field, $keyword_value); 
                    }
                    $this->db->group_end();
                } else if (in_array($keyword_field, $db_fields)) {
                    $this->db->like($keyword_field, $keyword_value);
                }
            }
            
            // Second pass: handle other filters
            foreach($filter as $f)
            {
                if (isset($f['value'])) {
                    // New filter with value (status, role, date filters)
                    if (in_array($f['field'],$db_fields))
                    {
                        if (isset($f['operator'])) {
                            // Handle operators like >=, IS NULL, etc.
                            if ($f['operator'] == 'IS NULL') {
                                $this->db->where($f['field'] . ' IS NULL');
                            } else {
                                $this->db->where($f['field'] . ' ' . $f['operator'], $f['value']);
                            }
                        } else {
                            // Default equals comparison
                            $this->db->where($f['field'], $f['value']);
                        }
                    }
                }
            }
        }
        
        // Join with user_roles table for role filtering
        $this->db->join('user_roles', sprintf('%s.id = user_roles.user_id',$this->tables['users']));
        $this->db->where('user_roles.role_id', $role_id);
        
        $this->db->join($this->tables['meta'], sprintf('%s.user_id = %s.id',$this->tables['meta'],$this->tables['users']));
        
        //set order by
        if ($sort_by!='' && $sort_order!='')
        {
            if ( array_key_exists($sort_by,$db_fields))
            {
                $this->db->order_by($db_fields[$sort_by], $sort_order); 
            }	
        }
        
        //set Limit clause
        $this->db->limit($limit, $offset);
        $this->db->from($this->tables['users']);

        $result= $this->db->get()->result_array();
        return $result;
    }
    
    /**
     * Get count of users by role with filters
     */
    function get_users_by_role_count($role_id, $filter=NULL)
    {
        $this->db->flush_cache();
        
        //allowed_fields for searching
        $db_fields=array(
                    'username'=>'username',
                    'first_name'=>'first_name',
                    'last_name'=>'last_name',
                    'email'=>'email',
                    'country'=>'country',
                    'company'=>'company',
                    'active'=>'active',
                    'created_on'=>'created_on',
                    'last_login'=>'last_login'
                    );
        
        //set where
        if ($filter)
        {			
            $has_keyword_search = false;
            $keyword_field = '';
            $keyword_value = '';
            
            // First pass: handle keyword searches
            foreach($filter as $f)
            {
                if (isset($f['keywords'])) {
                    $has_keyword_search = true;
                    $keyword_field = $f['field'];
                    $keyword_value = $f['keywords'];
                    break; // Only handle one keyword search
                }
            }
            
            // Handle keyword search with proper grouping
            if ($has_keyword_search) {
                if ($keyword_field == 'all') {
                    $this->db->group_start();
                    foreach($db_fields as $field)
                    {
                        $this->db->or_like($field, $keyword_value); 
                    }
                    $this->db->group_end();
                } else if (in_array($keyword_field, $db_fields)) {
                    $this->db->like($keyword_field, $keyword_value);
                }
            }
            
            // Second pass: handle other filters
            foreach($filter as $f)
            {
                if (isset($f['value'])) {
                    // New filter with value (status, role, date filters)
                    if (in_array($f['field'],$db_fields))
                    {
                        if (isset($f['operator'])) {
                            // Handle operators like >=, IS NULL, etc.
                            if ($f['operator'] == 'IS NULL') {
                                $this->db->where($f['field'] . ' IS NULL');
                            } else {
                                $this->db->where($f['field'] . ' ' . $f['operator'], $f['value']);
                            }
                        } else {
                            // Default equals comparison
                            $this->db->where($f['field'], $f['value']);
                        }
                    }
                }
            }
        }
        
        // Join with user_roles table for role filtering
        $this->db->join('user_roles', sprintf('%s.id = user_roles.user_id',$this->tables['users']));
        $this->db->where('user_roles.role_id', $role_id);
        
        $this->db->join($this->tables['meta'], sprintf('%s.user_id = %s.id',$this->tables['meta'],$this->tables['users']));
        $this->db->from($this->tables['users']);
        
        return $this->db->count_all_results();
    }
	
	function getSingle($userid)
	{
		$this->db->where('id', $userid); 
		return $this->db->get($this->tables['users']);
	}
	
	function delete($id)
	{
		$this->db->where('id', $id); 
		$deleted=$this->db->delete($this->tables['users']);
		
		if ($deleted)
		{
			//remove meta
			$this->db->where('user_id', $id); 
			$this->db->delete($this->tables['meta']);
		}
		
		return $deleted;
	}
	
	/**
	* Returns a list of all countries in the database
	*
	*/
	function get_all_countries()
	{
		$this->db->select('countryid,name');
		$query=$this->db->get('countries');
		
		$output=array('-'=>'-');
		
		if ($query)
		{
			$rows=$query->result_array();
			
			foreach($rows as $row)
			{
				$output[$row['countryid']]=$row['name'];
			}				
		}
		
		return $output;
	}
	
	function get_users_by_group($group_id, $limit = NULL, $offset = NULL,$filter=NULL,$sort_by=NULL,$sort_order=NULL)
    {
		//$this->output->enable_profiler(TRUE);

		$this->db->start_cache();

		//columns
		$columns=sprintf('%s.id,group_id,username,email,active,created_on,last_login,country,company',
							$this->tables['users']);
		$columns.=','.$this->tables['groups'].'.name as group_name';
		//select columns for output
		$this->db->select($columns);
		
		//allowed_fields for searching or sorting
		$db_fields=array(
					'username'=>'username',
					'first_name'=>'first_name',
					'last_name'=>'last_name',
					'email'=>'email',
					'country'=>'country',
					'company'=>'company',
					'group_name'=>'user_groups.name',
					'active'=>'active',
					'created_on'=>'created_on',
					'last_login'=>'last_login'
					);
		
		//set where
		if ($filter)
		{			
			foreach($filter as $f)
			{
				//search only in the allowed fields
				if (in_array($f['field'],$db_fields))
				{
					$this->db->like($f['field'], $f['keywords']); 
				}
				else if ($f['field']=='all')
				{
					foreach($db_fields as $field)
					{
						$this->db->or_like($field, $f['keywords']); 
					}
				}
			}
		}
		
		$this->db->join($this->tables['meta'], sprintf('%s.user_id = %s.id',$this->tables['meta'],$this->tables['users']));
		$this->db->join($this->tables['groups'], sprintf('%s.id = %s.group_id',$this->tables['groups'],$this->tables['users']),'left');
		$this->db->stop_cache();

		//set order by
		if ($sort_by!='' && $sort_order!='')
		{
			if ( array_key_exists($sort_by,$db_fields))
			{
				$this->db->order_by($db_fields[$sort_by], $sort_order); 
			}	
		}
		
		//set Limit clause
	  	$this->db->limit($limit, $offset);
		$this->db->from($this->tables['users']);
		
		$this->db->where('group_id', $group_id);
		
        $result= $this->db->get()->result_array();
		return $result;
    }
	
	
	/**
	 * Return user groups by user
	 *
	 **/
	public function get_user_roles($id_arr=array())
	{
		if (is_array($id_arr) && count($id_arr) ==0 )
		{
			return FALSE;
		}

	    $this->db->flush_cache();
		$this->db->select('role_id,user_id,name');
		$this->db->where_in('user_id', $id_arr);
		$this->db->join('roles', sprintf('%s.id= %s.role_id','roles','user_roles'));
		$query = $this->db->get('user_roles');

		//all user groups
		$rows = $query->result_array();
		$output=array();
		foreach($rows as $row)
		{
			$output[$row['user_id']][]=$row;
		}
		
		return $output;
	}	
}
?>