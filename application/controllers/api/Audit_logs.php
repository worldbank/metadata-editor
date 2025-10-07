<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Audit_logs extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model("Audit_log_model");
		$this->load->model("User_model");
		$this->is_admin_or_die();
	}

	function _auth_override_check()
	{
		if ($this->session->userdata('user_id'))
		{
			return true;
		}

		parent::_auth_override_check();
	}

	/**
	 * 
	 * Get audit logs with pagination and filtering
	 * GET /api/audit_logs/index
	 * 
	 * Query parameters:
	 * - limit: Number of records per page (default: 15)
	 * - offset: Starting record number (default: 0)
	 * - user_id: Filter by user ID
	 * - obj_type: Filter by object type
	 * - action_type: Filter by action type
	 * - obj_ref_id: Filter by object reference ID
	 * 
	 * 
	 */
	function index_get()
	{
		try{
			$limit = $this->get('limit') ? (int)$this->get('limit') : 15;
			$offset = $this->get('offset') ? (int)$this->get('offset') : 0;
			
			if ($limit > 100) {
				$limit = 100;
			}
			if ($limit < 1) {
				$limit = 15;
			}
			
			if ($offset < 0) {
				$offset = 0;
			}

			$options = array();
			
			if ($this->get('user_id')) {
				$options['user_id'] = $this->get('user_id');
			}
			
			if ($this->get('obj_type')) {
				$options['obj_type'] = $this->get('obj_type');
			}
			
			if ($this->get('action_type')) {
				$options['action_type'] = $this->get('action_type');
			}
			
			if ($this->get('obj_ref_id')) {
				$options['obj_ref_id'] = $this->get('obj_ref_id');
			}
			
			$options['exclude_metadata'] = true;
			$logs = $this->Audit_log_model->get_history($options, $limit, $offset);
			$total_count = $this->Audit_log_model->get_total_count($options);
			
			$response = array(
				'status' => 'success',
				'data' => $logs,
				'pagination' => array(
					'limit' => $limit,
					'offset' => $offset,
					'total' => $total_count,
					'current_page' => floor($offset / $limit) + 1,
					'total_pages' => ceil($total_count / $limit)
				)
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get detailed information for a specific audit log entry
	 * GET /api/audit_logs/info/{id}
	 * 
	 * @param int $id Audit log ID
	 * 
	 */
	function info_get($id = null)
	{
		try{
			if (!$id || !is_numeric($id)) {
				$error_output = array(
					'status' => 'failed',
					'message' => 'Invalid audit log ID'
				);
				$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$log_entry = $this->Audit_log_model->log_details($id);
			
			if (!$log_entry) {
				$error_output = array(
					'status' => 'failed',
					'message' => 'Audit log entry not found'
				);
				$this->set_response($error_output, REST_Controller::HTTP_NOT_FOUND);
				return;
			}
			
			$response = array(
				'status' => 'success',
				'data' => $log_entry
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

}
