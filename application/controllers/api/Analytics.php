<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Analytics extends MY_REST_Controller
{
	private $api_user;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Analytics_model');
		$this->load->library('Editor_acl');
		$this->is_authenticated_or_die();
		$this->api_user = $this->api_user();
	}

	/**
	 * Override authentication to support both session authentication + api keys
	 */
	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}

	/**
	 * Track analytics event
	 * 
	 * 
	 * Request body (JSON):
	 * {
	 *   "session_id": "1234567890_abc123_xyz",
	 *   "browser_id": "hash123_timestamp",
	 *   "event_type": "page_view",
	 *   "page": "/admin/dashboard",
	 *   "data": { ... }
	 * }
	 */
	function track_post()
	{
		try {
			$input = $this->raw_json_input();
			
			if (empty($input)) {
				throw new Exception("Invalid request body");
			}
			
			// Validate required fields
			if (empty($input['session_id']) || empty($input['event_type'])) {
				throw new Exception("Missing required fields: session_id, event_type");
			}
			
			$user_id = $this->get_api_user_id();
			
			$obj_type = null;
			$obj_value = null;
			
			if (isset($input['data']) && is_array($input['data'])) {
				if (isset($input['data']['project_id'])) {
					$obj_type = 'project';
					$obj_value = (string)$input['data']['project_id'];
				} elseif (isset($input['data']['collection_id'])) {
					$obj_type = 'collection';
					$obj_value = (string)$input['data']['collection_id'];
				} elseif (isset($input['data']['template_uid'])) {
					$obj_type = 'template';
					$obj_value = $input['data']['template_uid'];
				}
			}
			
			$event_data = array(
				'user_id' => $user_id, 
				'session_id' => $input['session_id'],
				'browser_id' => isset($input['browser_id']) ? $input['browser_id'] : null,
				'event_type' => $input['event_type'],
				'page' => isset($input['page']) ? $input['page'] : '',
				'ip_address' => $this->input->ip_address(),
				'user_agent' => isset($input['user_agent']) ? $input['user_agent'] : null,
				'obj_type' => $obj_type,
				'obj_value' => $obj_value,
				'data' => isset($input['data']) ? json_encode($input['data']) : null
			);
			
			$result = $this->Analytics_model->track_event($event_data);
			
			if (!$result) {
				throw new Exception("Failed to store event");
			}
			
			$response = array(
				'status' => 'success'
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
			
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get analytics statistics
	 * 
	 * Query params:
	 *   days=30 - Number of days to fetch stats for (default: 30)
	 *   refresh=1 - Force refresh by clearing cache
	 */
	function stats_get()
	{
		try {			
			$this->has_access('admin_dashboard', 'view');
			
			$days = $this->input->get('days');
			$days = $days ? (int)$days : 30;
			$refresh = $this->input->get('refresh');
			
			if ($refresh) {
				$this->Analytics_model->clear_analytics_cache();
			}
			
			$stats = $this->Analytics_model->get_analytics_stats($days);
			
			$response = array(
				'status' => 'success',
				'data' => $stats
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
				
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get session information
	 * 
	 */
	function session_get($session_id = null)
	{
		try {
			// Require admin access
			$this->load->library('Editor_acl');
			$this->has_access('admin_dashboard', 'view');
			
			if (empty($session_id)) {
				throw new Exception("Session ID required");
			}
			
			$session_info = $this->Analytics_model->get_session_info($session_id);
			
			$response = array(
				'status' => 'success',
				'data' => $session_info
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
				
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get chart data for visualizations
	 * 
	 * Query params:
	 *   type=calls|success_rate - Chart type (default: calls)
	 *   days=30 - Number of days (default: 30)
	 */
	function chart_get()
	{
		try {
			$this->has_access('admin_dashboard', 'view');
			
			$type = $this->input->get('type');
			$type = $type ? $type : 'calls';
			$days = $this->input->get('days');
			$days = $days ? (int)$days : 30;
			
			$chart_data = $this->Analytics_model->get_chart_data($type, $days);
			
			$response = array(
				'status' => 'success',
				'data' => $chart_data
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
				
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get top pages by views
	 * 
	 * Query params:
	 *   days=30 - Number of days (default: 30)
	 *   limit=10 - Number of results (default: 10)
	 */
	function pages_get()
	{
		try {
			$this->has_access('admin_dashboard', 'view');
			
			$days = $this->input->get('days');
			$days = $days ? (int)$days : 30;
			$limit = $this->input->get('limit');
			$limit = $limit ? (int)$limit : 10;
			
			$pages = $this->Analytics_model->get_top_pages($days, $limit);
			
			$response = array(
				'status' => 'success',
				'data' => $pages
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
				
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Run aggregation and cleanup
	 * 
	 * Query params:
	 *   cleanup=1 - Also run cleanup (archive and delete old logs)
	 */
	function aggregate_post()
	{
		try {
			$this->has_access('admin_dashboard', 'edit');
			
			$cleanup = $this->input->get('cleanup');
			
			// Run aggregation
			$result = $this->Analytics_model->aggregate_analytics();
			$api_logs_result = $this->Analytics_model->aggregate_api_logs();
			$result['api_logs'] = $api_logs_result;

			// Optionally run cleanup
			if ($cleanup) {
				$cleanup_result = $this->Analytics_model->archive_and_cleanup();
				$result['cleanup'] = $cleanup_result;
			}
			
			$response = array(
				'status' => 'success',
				'data' => $result
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
				
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function api_logs_aggregate_post()
	{
		try {
			$this->has_access('admin_dashboard', 'edit');

			$result = $this->Analytics_model->aggregate_api_logs();

			$response = array(
				'status' => 'success',
				'data' => $result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Get aggregation status
	 * GET /api/analytics/status
	 */
	function status_get()
	{
		try {
			$this->has_access('admin_dashboard', 'view');
			
			$status = $this->Analytics_model->get_aggregation_status();
			
			$response = array(
				'status' => 'success',
				'data' => $status
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
				
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get API logs aggregation status
	 * 
	 */
	function api_logs_status_get()
	{
		try {
			$this->has_access('admin_dashboard', 'view');

			$status = $this->Analytics_model->get_api_logs_aggregation_status();

			$response = array(
				'status' => 'success',
				'data' => $status
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
}
