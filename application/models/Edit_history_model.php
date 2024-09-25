<?php

class Edit_history_model extends CI_Model {

	private $fields=array(
		"obj_type", //project, template
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

	function log($obj_type,$obj_id,$action,$metadata, $user_id=null)
	{

		if ($user_id==null){
			$user_id=$this->session->userdata('user_id');
		}

		$data=array(
			"obj_type"=>$obj_type,
			"obj_id"=>$obj_id,
			"user_id"=>$user_id,
			"action_type"=>$action,
			"metadata"=>$metadata,
			"created"=>date("U")
		);

		return $this->insert($data);
	}
	

	function insert($data)
	{
		$data=array_intersect_key($data,array_flip($this->fields));

		if (!isset($data['created'])){
			$data['created']=date("U");
		}

		$this->db->insert('edit_history', $data); 
		return $this->db->insert_id();
	}

	function get_recent_entries_by_object_type($obj_type,$limit=0)
	{
		$this->db->select('*');
		$this->db->from('edit_history');
		$this->db->where('obj_type',$obj_type);
		$this->db->order_by('created','desc');
		$this->db->limit($limit);
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_recent_entries_by_user_id($user_id,$limit=10)
	{
		$this->db->select('*');
		$this->db->from('edit_history');
		$this->db->where('user_id',$user_id);
		$this->db->order_by('created','desc');
		$this->db->limit($limit);
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_recent_entries($limit=10)
	{
		$this->db->select('*');
		$this->db->from('edit_history');
		$this->db->order_by('created','desc');
		$this->db->limit($limit);
		$query = $this->db->get();
		return $query->result_array();
	}

}