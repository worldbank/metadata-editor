<?php
class Menu_model extends CI_Model {
 
 	var $search_count=0;
	var $db_fields=array('url','title','body','published','target','changed','linktype','weight','pid','js_inline');
	
    public function __construct()
    {
        parent::__construct();
		//$this->output->enable_profiler(TRUE);
    }
	
	//search
    function search($limit = NULL, $offset = NULL,$filter=NULL,$sort_by=NULL,$sort_order=NULL)
    {	
		$this->db->start_cache();
		
		//select columns for output
		$this->db->select('*');
		
		//allowed_fields
		$db_fields=array('url','title','body');
		
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

		$this->db->stop_cache();
		
		//test if valid sort field
		if (!in_array($sort_by,$this->db_fields))
		{
			$sort_by='title';
			$sort_order='ASC';
		}
		
		//set order by
		if ($sort_by!='' && $sort_order!='')
		{
			$this->db->order_by($sort_by, $sort_order); 
		}
		
		//set Limit clause
	  	$this->db->limit($limit, $offset);
		$this->db->from('menus');
		
        $result= $this->db->get()->result_array();				
		
		//get count
		$this->search_count=$this->db->count_all_results('menus');
	
		return $result;
    }
  			
    function search_count()
    {
        return $this->db->count_all_results('menus');
    }
	
	/**
	* Select a single menu item
	*
	**/
	function select_single($id)
	{		
		$this->db->select("*");
		$this->db->where('id', (integer)$id); 
		return $this->db->get('menus')->row_array();
	}
	
	function select_all($sort_by='weight', $sort_order='ASC')
	{
		$this->db->select('id,url,title,target,linktype');	
		$this->db->order_by($sort_by, $sort_order);
		$this->db->where('published', 1); 
		$query=$this->db->get('menus');
		
		if (!$query)
		{
			return FALSE;
		}
		
		return $query->result_array();
	}

	/**
	* update menu weight to change their position on the left menu
	*
	*	id			array
	**/
	function update_weight($id_list)
	{
		
		if (!is_array($id_list))
		{
			return FALSE;
		}			
		
		//iterate each value and update WEIGHT
		for($i=0;$i<count($id_list);$i++)
		{		
			$update_data=array('weight'=>$i);
			$this->db->where('id', $id_list[$i]);
			$this->db->update('menus', $update_data);
		}
		return TRUE;
	}

	
	
	/**
	* update 
	*
	*	id			int
	* 	options		array
	**/
	function update($id,$options)
	{
		//allowed fields
		$valid_fields=array(
			'url',
			'title',
			'body',
			'published',
			'target',
			'changed',
			'linktype',
			'weight',
			'pid',
/*			'css_links',
			'css_inline',
			'js_links',
			'js_inline'*/
			);

		//add date modified
		$options['changed']=date("U");
		
		if (!is_numeric($options['pid']))
		{
			$options['pid']=0;
		}
	
		if (!is_numeric($options['weight']))
		{
			$options['weight']=0;
		}
		
		//pk field name
		$key_field='id';
		
		$data=array();
		
		//build update statement
		foreach($options as $key=>$value)
		{
			if (in_array($key,$valid_fields) )
			{
				$data[$key]=$value;
			}
		}
		
		//update db
		$this->db->where($key_field, $id);
		$result=$this->db->update('menus', $data); 

		return $result;		
	}


	/**
	* add 
	*
	* 	options			array
	**/
	function insert($options)
	{
		//allowed fields
		$valid_fields=array(
			'url',
			'title',
			'body',
			'published',
			'target',
			'changed',
			'linktype',
			'weight',
			'pid',
			/*'css_links',
			'css_inline',
			'js_links',
			'js_inline'*/
			);

		//add date modified
		$options['changed']=date("U");
							
		$data=array();
		
		if (!is_numeric($options['pid']))
		{
			$options['pid']=0;
		}

		if (!is_numeric($options['weight']))
		{
			$options['weight']=0;
		}
		
		//build update statement
		foreach($options as $key=>$value)
		{
			if (in_array($key,$valid_fields) )
			{
				$data[$key]=$value;
			}
		}		
		
		//insert record into db
		$result=$this->db->insert('menus', $data); 
		
		return $result;		
	}
	
	
	/**
	* checks if a URL exists
	*
	*/
	function url_exists($url,$id=NULL)
	{
		$this->db->select('id');		
		$this->db->from('menus');		
		$this->db->where('url',$url);		
		if ($id!=NULL)
		{
//			$this->db->where('id',$id);		
			$this->db->where('id !=', $id);
		}
        $result= $this->db->count_all_results();
		return $result;
	}
	
	
	/**
	* Returns the Menu by URL or ID
	*
	*/
	function get_page($page_url)
    {
		$this->db->select('*');		
		$this->db->where('url',$page_url );
		
		if (is_numeric($page_url)){
			$this->db->or_where('id',$page_url );
		}
			
		$result= $this->db->get('menus');
		
		if($result){
			return $result->row_array();
		}

		return false;
    }
	
	/**
	* 
	* Return a page item by minimum weight
	*/
	function get_page_by_min_weight()
    {
		$this->db->select('*,min(weight)');		
		$this->db->group_by('id');
        $query= $this->db->get('menus');
		
		if (!$query)
		{
			return FALSE;
		}
		
		$result=$query->row_array();
		
		return $result;
    }
	
	function delete($id)
	{
		$this->db->where('id', $id); 
		return $this->db->delete('menus');
	}
	
		
	function get_menu_tree($id=NULL)
	{
		//get parent items
		$this->db->select("*");
		if($id==NULL)
		{
			$this->db->where('pid', 0); 
		}
		else
		{
			$this->db->where('pid', $id); 
		}
		
		$this->db->order_by('weight', 'asc');
		$query= $this->db->get('menus');
		
		$parents=array();
		
		if ($query)
		{
			$parents=$query->result_array();
		}
		
		$result='<ul class="sf-menu">';
		foreach($parents as $item)
		{
			$item=(object)$item;
			$target='';
			if ($item->target==1)
			{
				$target=' target="_blank"';
			}

			$result.='<li>'.anchor($item->url,$item->title, $target);
			$sub=$this->get_children($item->id);

			if (is_array($sub))			
			{
				if (count($sub)>0)
				{
				$result.='<ul>';
				//merge array
				foreach($sub as $value)
				{
					$target='';
					if ($item->target==1)
					{
						$target=' target="_blank"';
					}
					$result.='<li>'.anchor($value['url'],$value['title'],$target).'</li>';
				}
				$result.='</ul>';
				}
			}
			$result.='</li>';	
		}
		$result.'<ul>';
		return $result;
	}
	
	//return children items by parentid
	function get_children($id)
	{
		$this->db->select("*");
		$this->db->where('pid', $id); 
		$this->db->where('published', 1); 
		$this->db->order_by('weight', 'ASC');		
		return $this->db->get('menus')->result_array();
	}
	
	function get_menu_by_url($url)
	{
		$this->db->select("*");
		$this->db->where('published', 1); 
		$this->db->where('url', $url); 
		$query=$this->db->get('menus');

		if ($query)
		{
			return $query->row_array();
		}
		
		return FALSE;	
	}
	
	function get_secondary_menu($pid)
	{
		$this->db->select("*");
		$this->db->where(' (pid ='.$pid.' OR id='.$pid.')', NULL,FALSE); 
		$this->db->where('published', 1); 
		$this->db->order_by('weight', 'ASC'); 
		$query=$this->db->get('menus');
		//echo $this->db->last_query();
		if($query)
		{
			return $query->result_array();
		}
		else
		{
			return FALSE;
		}
	}

	/**
	*
	* Returns an array of all active blocks for a given page
	*
	**/
	function get_blocks($url)
	{
		$this->db->select("*");
		$this->db->where('published', 1); 
		$this->db->order_by('region', 'ASC'); 
		$this->db->order_by('weight', 'ASC'); 		
		
		$query=$this->db->get('blocks');
		
		//echo $this->db->last_query();
		if($query)
		{
			$rows=$query->result_array();
			$result=array();	
			foreach($rows as $row)
			{
				//check if block to be displayed for the current url
				$show=$this->check_block_array($row['pages'],$url);
				
				if ($show==TRUE)
				{
					$result[$row['region']][]=$row;
				/*
					if (isset($result[$row['region']]))
					{
						$result[$row['region']].=$row['body'];
					}
					else
					{
						$result[$row['region']]=$row['body'];
					}	
				*/	
				}
			}
			return $result;
		}
		else
		{
			return FALSE;
		}
	
	}

	/**
	*
	* Test page url in the block pages field to check if the block is to be displayed or not
	*
	**/
	function check_block_array($block_data, $url)
	{
		$pages=explode("\r",$block_data);
		
		if (trim($block_data)=='')
		{
			return TRUE;
		}
		
		foreach($pages as $page)
		{
			if (trim($page)==trim($url))
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}

}
?>