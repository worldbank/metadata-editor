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
		$exclude_metadata = isset($options['exclude_metadata']) ? $options['exclude_metadata'] : false;

		if ($exclude_metadata) {
			$this->db->select('audit_logs.id, audit_logs.obj_type, audit_logs.obj_id, audit_logs.user_id, audit_logs.action_type, audit_logs.created, audit_logs.obj_ref_id');
		} else {
			$this->db->select('audit_logs.*');
		}
		
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
		
		$this->db->order_by('audit_logs.created','desc');
		$this->db->limit($limit);

		if ($offset>0){
			$this->db->offset($offset);
		}

		$result = $this->db->get("audit_logs")->result_array();		

		if (!empty($result)) {
			$user_ids = array_unique(array_column($result, 'user_id'));
			$user_ids = array_filter($user_ids); // Remove null values
			
			$users = array();
			if (!empty($user_ids)) {
				$this->db->select('id, username, email');
				$this->db->where_in('id', $user_ids);
				$user_results = $this->db->get('users')->result_array();
				
				foreach($user_results as $user) {
					$users[$user['id']] = $user;
				}
			}
			
			// Merge user information with audit logs
			foreach($result as $idx => $row) {
				if (!$exclude_metadata && isset($row['metadata'])) {
					$result[$idx]['metadata'] = json_decode($row['metadata'], true);
				}
				
				if (isset($users[$row['user_id']])) {
					$result[$idx]['username'] = $users[$row['user_id']]['username'];
					$result[$idx]['email'] = $users[$row['user_id']]['email'];
				} else {
					$result[$idx]['username'] = null;
					$result[$idx]['email'] = null;
				}
			}
		}
		
		return $result;
	}

	/**
	 * 
	 * Get total count of audit logs based on filter options
	 * 
	 */
	function get_total_count($options=array())
	{
		$obj_type = isset($options['obj_type']) ? $options['obj_type'] : '';
		$obj_id = isset($options['obj_id']) ? $options['obj_id'] : '';
		$user_id = isset($options['user_id']) ? $options['user_id'] : '';
		$obj_ref_id = isset($options['obj_ref_id']) ? $options['obj_ref_id'] : '';		

		$this->db->from('audit_logs');
		
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

		return $this->db->count_all_results();
	}

	/**
	 * 
	 * Get full details of a specific audit log entry by ID
	 * 
	 * @param int $id Audit log ID
	 * @return array|null Audit log details or null if not found
	 * 
	 */
	function log_details($id)
	{
		if (!$id || !is_numeric($id)) {
			return null;
		}

		// Get the audit log entry
		$this->db->select('audit_logs.*');
		$this->db->where('audit_logs.id', $id);
		$query = $this->db->get('audit_logs');
		$result = $query->result_array();
		
		if (empty($result)) {
			return null;
		}
		
		$log_entry = $result[0];
		
		// Get user information if user_id exists
		if ($log_entry['user_id']) {
			$this->db->select('id, username, email');
			$this->db->where('id', $log_entry['user_id']);
			$user_query = $this->db->get('users');
			$user_result = $user_query->result_array();
			
			if (!empty($user_result)) {
				$log_entry['username'] = $user_result[0]['username'];
				$log_entry['email'] = $user_result[0]['email'];
			} else {
				$log_entry['username'] = null;
				$log_entry['email'] = null;
			}
		} else {
			$log_entry['username'] = null;
			$log_entry['email'] = null;
		}
		
		if (isset($log_entry['metadata'])) {
			$log_entry['metadata'] = json_decode($log_entry['metadata'], true);
		}
		
		// Add object-specific information based on obj_type
		if ($log_entry['obj_type'] == 'project') {
			$log_entry['project_info'] = $this->get_project_basic_info($log_entry['obj_id']);
		} elseif ($log_entry['obj_type'] == 'collection') {
			$log_entry['collection_info'] = $this->get_collection_basic_info($log_entry['obj_id']);
		}
		
		return $log_entry;
	}

	/**
	 * 
	 * Get basic project information by ID
	 * 
	 * @param int $project_id Project ID
	 * @return array|null Project info or null if not found
	 * 
	 */
	function get_project_basic_info($project_id)
	{
		if (!$project_id || !is_numeric($project_id)) {
			return null;
		}

		$this->db->select('id,idno, title, type, study_idno, nation');
		$this->db->where('id', $project_id);
		$query = $this->db->get('editor_projects');
		$result = $query->result_array();
		
		if (empty($result)) {
			return null;
		}
		
		return $result[0];
	}

	/**
	 * 
	 * Get basic collection information by ID
	 * 
	 * @param int $collection_id Collection ID
	 * @return array|null Collection info or null if not found
	 * 
	 */
	function get_collection_basic_info($collection_id)
	{
		if (!$collection_id || !is_numeric($collection_id)) {
			return null;
		}

		$this->db->select('id, title, description, created, created_by, changed, changed_by, pid, wgt');
		$this->db->where('id', $collection_id);
		$query = $this->db->get('editor_collections');
		$result = $query->result_array();
		
		if (empty($result)) {
			return null;
		}
		
		return $result[0];
	}

}