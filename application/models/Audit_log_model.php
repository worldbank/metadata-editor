<?php

class Audit_log_model extends CI_Model {

	private $fields=array(
		"obj_type",
		"obj_id",
		"user_id",
		"action_type", //create, update, delete, patch
		"created",
		"metadata"
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

}