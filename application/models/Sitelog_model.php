<?php
class Sitelog_model extends CI_Model {
 
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
		$db_fields=array('ip','url','logtype','section','keyword','username');
		
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

		//set order by
		if ($sort_by!='' && $sort_order!='')
		{
			$this->db->order_by($sort_by, $sort_order); 
		}
		
		//set Limit clause
	  	$this->db->limit($limit, $offset);
		$this->db->from('sitelogs');		
        $query= $this->db->get();
		
		if (!$query)
		{	
			echo $this->db->last_query();	
			return FALSE;
		}
		$result=$query->result_array();
		return $result;
    }
  	
    function search_count()
    {
          return $this->db->count_all_results('sitelogs');
    }
	
	/**
	* Select a single item
	*
	**/
	function select_single($id){
		$this->db->where('id', $id); 
		return $this->db->get('sitelogs')->row_array();
	}
	

}
?>