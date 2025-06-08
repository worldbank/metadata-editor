<?php

class Audit_log_model extends CI_Model {

	private $fields=array(
		"obj_type",
		"obj_id",
		"user_id",
		"action_type", //create, update, delete, patch
		"created",
		"metadata",
		"obj_ref_id"
	);
 
    public function __construct()
    {
        parent::__construct();
		//$this->output->enable_profiler(TRUE);
    }

	function insert($data)
	{
		//keep only the fields that we need
		$data=array_intersect_key($data,array_flip($this->fields));

		if (!isset($data['created'])){
			$data['created']=date("Y-m-d H:i:s");
		}

		if (isset($data['metadata']) && is_array($data['metadata'])){
			$data['metadata']=json_encode($data['metadata']);			
		}

		$this->db->insert('audit_logs', $data); 
		return $this->db->insert_id();
	}

	function get_recent_entries_by_object_type($obj_type,$limit=10)
	{
		$this->db->select('*');
		$this->db->from('audit_logs');
		$this->db->where('obj_type',$obj_type);
		$this->db->order_by('created','desc');
		$this->db->limit($limit);
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_recent_entries_by_user_id($user_id,$limit=10)
	{
		$this->db->select('*');
		$this->db->from('audit_logs');
		$this->db->where('user_id',$user_id);
		$this->db->order_by('created','desc');
		$this->db->limit($limit);
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_recent_entries($limit=10)
	{
		$this->db->select('*');
		$this->db->from('audit_logs');
		$this->db->order_by('created','desc');
		$this->db->limit($limit);
		$query = $this->db->get();
		return $query->result_array();
	}


	/**
	 * 
	 * Search for audit logs based on various options
	 * 
	 */
	function get_history($options=array(),$limit=10, $offset=0)
	{
		$obj_type = isset($options['obj_type']) ? $options['obj_type'] : '';
		$obj_id = isset($options['obj_id']) ? $options['obj_id'] : '';
		$user_id = isset($options['user_id']) ? $options['user_id'] : '';
		$obj_ref_id = isset($options['obj_ref_id']) ? $options['obj_ref_id'] : '';		

		$this->db->select('audit_logs.*, users.username, users.email');
		
		if (!empty($user_id)){
			$this->db->where('audit_logs.user_id', $user_id);
		}

		if (!empty($obj_type)){
			$this->db->where('audit_logs.obj_type', $obj_type);
		}

		if (!empty($obj_id)){
			$this->db->where('audit_logs.obj_id', $obj_id);
		}

		if (!empty($obj_ref_id)){
			$this->db->where('audit_logs.obj_ref_id', $obj_ref_id);
		}
		
		$this->db->join('users', 'users.id = audit_logs.user_id', 'left');
		$this->db->order_by('created','desc');
		$this->db->limit($limit);

		if ($offset>0){
			$this->db->offset($offset);
		}

		$result = $this->db->get("audit_logs")->result_array();		

		foreach($result as $idx=>$row){
			$result[$idx]['metadata']=json_decode($row['metadata'],true);
		}
		
		return $result;
	}

}