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
					$obj_value = $this->sanitize_string((string)$input['data']['project_id'], 255);
				} elseif (isset($input['data']['collection_id'])) {
					$obj_type = 'collection';
					$obj_value = $this->sanitize_string((string)$input['data']['collection_id'], 255);
				} elseif (isset($input['data']['template_uid'])) {
					$obj_type = 'template';
					$obj_value = $this->sanitize_string((string)$input['data']['template_uid'], 255);
				}
			}
			
			// Sanitize page field
			$page = '';
			if (isset($input['page']) && is_string($input['page'])) {
				$page = $this->sanitize_page_path($input['page']);
			}
			
			// Sanitize session_id and browser_id
			$session_id = $this->sanitize_string($input['session_id'], 255);
			$browser_id = isset($input['browser_id']) && is_string($input['browser_id']) 
				? $this->sanitize_string($input['browser_id'], 255) 
				: null;
			
			// Sanitize user_agent
			$user_agent = isset($input['user_agent']) && is_string($input['user_agent']) 
				? $this->sanitize_string($input['user_agent'], 100) 
				: null;
			
			// Sanitize event_type
			$event_type = $this->sanitize_string($input['event_type'], 50);
			
			// Sanitize and validate JSON data field
			$data_json = null;
			if (isset($input['data'])) {
				if (is_array($input['data'])) {
					// Limit array depth
					$data_json = $this->sanitize_json_data($input['data']);
				} elseif (is_string($input['data'])) {
					$decoded = json_decode($input['data'], true);
					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$data_json = $this->sanitize_json_data($decoded);
					} else {
						$data_json = null; // Invalid JSON, ignore
					}
				}
			}
			
			$event_data = array(
				'user_id' => $user_id, 
				'session_id' => $session_id,
				'browser_id' => $browser_id,
				'event_type' => $event_type,
				'page' => $page,
				'ip_address' => $this->input->ip_address(),
				'user_agent' => $user_agent,
				'obj_type' => $obj_type,
				'obj_value' => $obj_value,
				'data' => $data_json
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
			$this->has_access('dashboard', 'view');
			
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
			$this->has_access('dashboard', 'view');
			
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
			$this->has_access('dashboard', 'view');
			
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
			$this->has_access('dashboard', 'view');
			
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
			$this->has_access('dashboard', 'edit');
			
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
			$this->has_access('dashboard', 'edit');

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
			$this->has_access('dashboard', 'view');
			
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
			$this->has_access('dashboard', 'view');

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

	/**
	 * Sanitize page path
	 * Ensures the page path is safe and valid
	 * 
	 * @param string $page Raw page path
	 * @return string Sanitized page path
	 */
	private function sanitize_page_path($page)
	{
		if (!is_string($page)) {
			return '';
		}
		
		// Remove null bytes and control characters
		$page = str_replace("\0", '', $page);
		$page = preg_replace('/[\x00-\x1F\x7F]/', '', $page);
		
		// Remove any protocol (http://, https://, etc.)
		$page = preg_replace('#^https?://#i', '', $page);
		
		// Remove domain/host if present
		if (preg_match('#^[^/]+(/.*)$#', $page, $matches)) {
			$page = $matches[1];
		}
		
		// Remove query parameters and fragments (keep hash for SPA routing)
		$page = strtok($page, '?');
		
		// Ensure it starts with /
		if (!empty($page) && $page[0] !== '/') {
			$page = '/' . $page;
		}
		
		// Limit length to database field size (255)
		if (strlen($page) > 255) {
			$page = substr($page, 0, 255);
		}
		
		// XSS clean
		$page = $this->security->xss_clean($page);
		
		// Remove any remaining dangerous characters but allow valid path characters
		// Allow: /, -, _, ., alphanumeric, and hash for SPA routing
		$page = preg_replace('/[^\/\w\-\.#]/', '', $page);
		
		// Normalize multiple slashes
		$page = preg_replace('#/+#', '/', $page);
		
		return $page ?: '/';
	}

	/**
	 * Sanitize string input
	 * 
	 * @param string $value Raw string value
	 * @param int $max_length Maximum length
	 * @return string Sanitized string
	 */
	private function sanitize_string($value, $max_length = 255)
	{
		if (!is_string($value)) {
			return '';
		}
		
		// Remove null bytes and control characters
		$value = str_replace("\0", '', $value);
		$value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
		
		// XSS clean
		$value = $this->security->xss_clean($value);
		
		// Limit length
		if (strlen($value) > $max_length) {
			$value = substr($value, 0, $max_length);
		}
		
		return trim($value);
	}

	/**
	 * Sanitize JSON data field
	 * Prevents large payloads and deep nesting attacks
	 * 
	 * @param array $data Raw data array
	 * @param int $max_depth Maximum nesting depth (default: 5)
	 * @return string|null JSON encoded string or null if invalid
	 */
	private function sanitize_json_data($data, $max_depth = 5)
	{
		if (!is_array($data)) {
			return null;
		}
		
		// Recursively sanitize array with depth limit
		$sanitized = $this->sanitize_array_recursive($data, $max_depth, 0);
		
		// Encode to JSON
		$json = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		
		// Limit total JSON size to 10KB to prevent large payload attacks
		if (strlen($json) > 10240) {
			return null; // Too large, reject
		}
		
		return $json;
	}

	/**
	 * Recursively sanitize array data
	 * 
	 * @param mixed $data Data to sanitize
	 * @param int $max_depth Maximum depth
	 * @param int $current_depth Current depth
	 * @return mixed Sanitized data
	 */
	private function sanitize_array_recursive($data, $max_depth, $current_depth)
	{
		// Prevent deep nesting
		if ($current_depth >= $max_depth) {
			return null;
		}
		
		if (is_array($data)) {
			$result = array();
			$count = 0;
			// Limit array size to prevent DoS
			foreach ($data as $key => $value) {
				if ($count++ >= 100) { // Max 100 items per array
					break;
				}
				
				// Sanitize key
				if (is_string($key)) {
					$key = $this->sanitize_string($key, 100);
				}
				
				// Recursively sanitize value
				$result[$key] = $this->sanitize_array_recursive($value, $max_depth, $current_depth + 1);
			}
			return $result;
		} elseif (is_string($data)) {
			// Sanitize string values (limit to reasonable size)
			return $this->sanitize_string($data, 1000);
		} elseif (is_numeric($data) || is_bool($data) || is_null($data)) {
			// Allow numeric, boolean, and null values
			return $data;
		} else {
			// Convert other types to string and sanitize
			return $this->sanitize_string((string)$data, 1000);
		}
	}
}
