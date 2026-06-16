<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Jobs extends MY_REST_Controller
{
	private $api_user;
	private $user_id;

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Job_queue_model');
		
		// Load job registry for validation
		require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';
		require_once APPPATH . 'libraries/Jobs/JobRegistry.php';
		
		$this->is_authenticated_or_die();
		$this->api_user = $this->api_user();
		$this->user_id = $this->get_api_user_id();
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
	 * Get list of all jobs (basic information only)
	 * 
	 * Query parameters:
	 *   status - Filter by status (pending, processing, completed, failed)
	 *   job_type - Filter by job type
	 *   project_id - Filter by project ID (payload.project_id; use with job_type, e.g. metadata_assessment_result)
	 *   user_id - Filter by user ID (admin only, or own jobs)
	 *   limit - Number of jobs to return (default: 50)
	 *   offset - Offset for pagination (default: 0)
	 * 
	 * GET /api/jobs
	 * GET /api/jobs?status=pending
	 * GET /api/jobs?user_id=123
	 * 
	 * Returns basic job information (id, job_type, status, priority, user_id, created_at, started_at, completed_at, attempts)
	 * For full details including payload and result, use GET /api/jobs/{job_id}
	 */
	function index_get()
	{
		try {
			// Check if a job_id is provided in the URI (for /api/jobs/{job_uuid})
			$job_identifier = $this->uri->segment(3);
			if ($job_identifier) {
				// Route to job detail handler (accepts both UUID and numeric ID for backward compatibility)
				return $this->job_get($job_identifier);
			}
			
			// Get query parameters
			$status = $this->input->get('status');
			$job_type = $this->input->get('job_type');
			$user_id_filter = $this->input->get('user_id');
			$project_id_filter = $this->input->get('project_id');
			$limit = (int)($this->input->get('limit') ?: 50);
			$offset = (int)($this->input->get('offset') ?: 0);

			if ($project_id_filter !== null && $project_id_filter !== '') {
				$project_id_filter = (int) $project_id_filter;
			} else {
				$project_id_filter = null;
			}
			
			// Limit maximum results per request
			if ($limit > 100) {
				$limit = 100;
			}
			
			// Non-admin users can only see their own jobs
			$is_admin = $this->is_admin();
			
			if (!$is_admin) {
				// Regular users can only see their own jobs
				$user_id_filter = $this->user_id;
			}
			
			// Build query based on filters
			$jobs = array();

			// Filter by job_type and project_id (e.g. metadata_assessment_result for a project)
			if ($project_id_filter !== null && $job_type) {
				$filters = array('job_type' => $job_type, 'project_id' => $project_id_filter);
				if ($status !== null && $status !== '') {
					$filters['status'] = $status;
				}
				if ($user_id_filter !== null && $user_id_filter !== '') {
					$filters['user_id'] = $user_id_filter;
				}
				$jobs = $this->Job_queue_model->get_all($filters, $limit, $offset);
			} elseif ($user_id_filter) {
				// Get jobs by user
				$jobs = $this->Job_queue_model->get_by_user(
					$user_id_filter,
					$status,
					$limit,
					$offset
				);
			} elseif ($status) {
				// Get jobs by status
				$jobs = $this->Job_queue_model->get_by_status(
					$status,
					$limit,
					$offset
				);
			} elseif ($job_type) {
				// Get jobs by type
				$jobs = $this->Job_queue_model->get_by_job_type(
					$job_type,
					$status,
					$limit
				);
			} else {
				// Get all jobs (admin only, or fallback to user's jobs)
				if ($is_admin) {
					// Admin can see all jobs
					$filters = array();
					if ($job_type) {
						$filters['job_type'] = $job_type;
					}
					$jobs = $this->Job_queue_model->get_all($filters, $limit, $offset);
				} else {
					// Regular users see only their jobs
					$jobs = $this->Job_queue_model->get_by_user(
						$this->user_id,
						$status,
						$limit,
						$offset
					);
				}
			}
			
			// Return only basic information (exclude payload and result)
			$basic_jobs = array();
			foreach ($jobs as $job) {
				$job_uuid = isset($job['uuid']) ? $job['uuid'] : null;
				$basic_jobs[] = array(
					'uuid' => $job_uuid, // Public-facing UUID
					'job_type' => $job['job_type'],
					'status' => $job['status'],
					'priority' => $job['priority'],
					'user_id' => $job['user_id'],
					'attempts' => $job['attempts'],
					'max_attempts' => $job['max_attempts'],
					'created_at' => $job['created_at'],
					'started_at' => $job['started_at'],
					'completed_at' => $job['completed_at'],
					'worker_id' => isset($job['worker_id']) ? $job['worker_id'] : null,
                    'job_status_link' => $job_uuid ? site_url('api/jobs/' . $job_uuid) : null
				);
			}
			
			$response = array(
				'status' => 'success',
				'total' => count($basic_jobs),
				'found' => count($basic_jobs),
				'limit' => $limit,
				'offset' => $offset,
				'jobs' => $basic_jobs
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
	 * Get full details of a specific job
	 * 
	 * GET /api/jobs/{job_uuid}
	 * 
	 * Accepts either UUID or numeric ID (for backward compatibility)
	 * Returns complete job information including payload and result
	 */
	function job_get($job_identifier = null)
	{
		try {
			// Get job identifier from parameter or URI segment
			if (!$job_identifier) {
				$job_identifier = $this->uri->segment(3);
			}
			
			if (!$job_identifier) {
				throw new Exception("Invalid or missing job identifier");
			}
			
			// Get the job by UUID or ID (supports both for backward compatibility)
			$job = $this->Job_queue_model->get_by_uuid_or_id($job_identifier);
			
			if (!$job) {
				$error_output = array(
					'status' => 'failed',
					'message' => 'Job not found'
				);
				$this->set_response($error_output, REST_Controller::HTTP_NOT_FOUND);
				return;
			}
			
			// Check access permissions
			$is_admin = $this->is_admin();
			
			// Non-admin users can only see their own jobs
			if (!$is_admin && $job['user_id'] != $this->user_id) {
				$error_output = array(
					'status' => 'failed',
					'message' => 'Access denied'
				);
				$this->set_response($error_output, REST_Controller::HTTP_FORBIDDEN);
				return;
			}
			
			// Remove numeric ID from job object for API response
			$job_response = $this->sanitize_job_for_api($job);
			$worker_status_response = $this->_get_worker_status_response();
			
			// Return full job details
			$response = array(
				'status' => 'success',
				'job' => $job_response,
				'worker_status' => $worker_status_response
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
	 * Create a new job
	 * 
	 * POST /api/jobs
	 * 
	 * Request body:
	 *   {
	 *     "job_type": "pdf_generation",
	 *     "payload": {
	 *       "project_id": 123,
	 *       "options": {}
	 *     },
	 *     "priority": 0,
	 *     "max_attempts": 3
	 *   }
	 * 
	 * Required fields:
	 *   - job_type: Must be a valid registered job type
	 *   - payload: Job-specific parameters (validated by the job handler)
	 * 
	 * Optional fields:
	 *   - priority: Job priority (default: 0)
	 *   - max_attempts: Maximum retry attempts (default: 3)
	 */
	function index_post()
	{
		try {
			// Get request data
			$input = json_decode($this->input->raw_input_stream, true);
			
			if (!$input) {
				// Try form data as fallback
				$input = $this->input->post();
			}
			
			if (empty($input)) {
				throw new Exception("Request body is required");
			}
			
			// Validate required fields
			if (empty($input['job_type'])) {
				throw new Exception("job_type is required");
			}
			
			if (!isset($input['payload'])) {
				throw new Exception("payload is required");
			}
			
			$job_type = $input['job_type'];
			$payload = $input['payload'];
			$priority = isset($input['priority']) ? (int)$input['priority'] : 0;
			$max_attempts = isset($input['max_attempts']) ? (int)$input['max_attempts'] : 3;
			
			// Validate job type exists
			if (!$this->Job_queue_model->is_valid_job_type($job_type)) {
				$available_types = $this->Job_queue_model->get_job_types();
				throw new Exception("Invalid job_type: {$job_type}. Available types: " . implode(', ', $available_types));
			}
			
			// Validate payload using the job handler
			$handler = JobRegistry::getHandler($job_type);
			if ($handler) {
				$handler->validatePayload($payload);
			}
			
			// Enqueue the job
			$job_id = $this->Job_queue_model->enqueue(
				$job_type,
				$payload,
				$this->user_id, // Use authenticated user's ID
				$priority,
				$max_attempts
			);
			
			// Get the created job
			$job = $this->Job_queue_model->get($job_id);
			
			// Remove numeric ID from job object for API response
			$job_response = $this->sanitize_job_for_api($job);
			$job_uuid = isset($job['uuid']) ? $job['uuid'] : null;
			
			$response = array(
				'status' => 'success',
				'message' => 'Job created successfully',
				'uuid' => $job_uuid, // Public-facing UUID
				'job' => $job_response
			);
			
			$this->set_response($response, REST_Controller::HTTP_CREATED);
			
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Create a PDF generation job (convenience endpoint)
	 * 
	 * POST /api/jobs/generate_pdf
	 * 
	 * Convenience endpoint for creating PDF generation jobs. This endpoint automatically
	 * sets the job_type to 'generate_pdf' and delegates to the generic job creation handler.
	 * 
	 * Request body:
	 *   {
	 *     "project_id": 123,
	 *     "options": {},
	 *     "priority": 0,
	 *     "max_attempts": 3
	 *   }
	 * 
	 * Required fields:
	 *   - project_id: Project ID for which to generate the PDF
	 * 
	 * Optional fields:
	 *   - options: PDF generation options (object)
	 *   - priority: Job priority (default: 0)
	 *   - max_attempts: Maximum retry attempts (default: 3)
	 * 
	 * This is equivalent to POST /api/jobs with:
	 *   {
	 *     "job_type": "generate_pdf",
	 *     "payload": { "project_id": 123, "options": {} }
	 *   }
	 */
	function generate_pdf_post()
	{
		try {
			// Get request data
			$input = json_decode($this->input->raw_input_stream, true);
			
			if (!$input) {
				// Try form data as fallback
				$input = $this->input->post();
			}
			
			if (empty($input)) {
				throw new Exception("Request body is required");
			}
			
			// Validate required field
			if (empty($input['project_id'])) {
				throw new Exception("project_id is required");
			}
			
			// Build payload for generic handler
			$payload = array(
				'project_id' => $input['project_id']
			);
			
			// Add options if provided
			if (isset($input['options'])) {
				$payload['options'] = $input['options'];
			}
			
			// Set job type and other parameters
			$job_type = 'generate_pdf';
			$priority = isset($input['priority']) ? (int)$input['priority'] : 0;
			$max_attempts = isset($input['max_attempts']) ? (int)$input['max_attempts'] : 3;
			
			// Validate job type exists
			if (!$this->Job_queue_model->is_valid_job_type($job_type)) {
				$available_types = $this->Job_queue_model->get_job_types();
				throw new Exception("Invalid job_type: {$job_type}. Available types: " . implode(', ', $available_types));
			}
			
			// Validate payload using the job handler
			$handler = JobRegistry::getHandler($job_type);
			if ($handler) {
				$handler->validatePayload($payload);
			}
			
			// Enqueue the job
			$job_id = $this->Job_queue_model->enqueue(
				$job_type,
				$payload,
				$this->user_id, // Use authenticated user's ID
				$priority,
				$max_attempts
			);
			
			// Get the created job
			$job = $this->Job_queue_model->get($job_id);
			
			// Remove numeric ID from job object for API response
			$job_response = $this->sanitize_job_for_api($job);
			$job_uuid = isset($job['uuid']) ? $job['uuid'] : null;
			
			$response = array(
				'status' => 'success',
				'message' => 'Job created successfully',
				'uuid' => $job_uuid, // Public-facing UUID
				'job' => $job_response
			);
			
			$this->set_response($response, REST_Controller::HTTP_CREATED);
			
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Create a publish to NADA job (convenience endpoint)
	 * 
	 * POST /api/jobs/publish_to_nada
	 * 
	 * Convenience endpoint for creating publish project jobs. This endpoint automatically
	 * sets the job_type to 'publish_project' and delegates to the generic job creation handler.
	 * 
	 * Request body:
	 *   {
	 *     "project_id": 123,
	 *     "catalog_connection_id": 1,
	 *     "publish_metadata": true,
	 *     "publish_thumbnail": true,
	 *     "publish_resources": true,
	 *     "options": {},
	 *     "priority": 0,
	 *     "max_attempts": 3
	 *   }
	 */
	function publish_to_nada_post()
	{
		try {
			// Get request data
			$input = json_decode($this->input->raw_input_stream, true);
			
			if (!$input) {
				// Try form data as fallback
				$input = $this->input->post();
			}
			
			if (empty($input)) {
				throw new Exception("Request body is required");
			}
			
			// Validate required field
			if (empty($input['project_id'])) {
				throw new Exception("project_id is required");
			}

			if (empty($input['catalog_connection_id'])) {
				throw new Exception("catalog_connection_id is required");
			}
			
			// Build payload for generic handler
			$payload = array(
				'project_id' => $input['project_id'],
				'catalog_connection_id' => $input['catalog_connection_id'],
				'user_id' => $this->user_id,
				'publish_metadata' => isset($input['publish_metadata']) ? $input['publish_metadata'] : true,
				'publish_thumbnail' => isset($input['publish_thumbnail']) ? $input['publish_thumbnail'] : true,
				'publish_resources' => isset($input['publish_resources']) ? $input['publish_resources'] : true,
			);
			
			// Add options if provided
			if (isset($input['options'])) {
				$payload['options'] = $input['options'];
			}
			
			// Set job type and other parameters
			$job_type = 'publish_project';
			$priority = isset($input['priority']) ? (int)$input['priority'] : 0;
			$max_attempts = isset($input['max_attempts']) ? (int)$input['max_attempts'] : 3;
			
			// Validate job type exists
			if (!$this->Job_queue_model->is_valid_job_type($job_type)) {
				$available_types = $this->Job_queue_model->get_job_types();
				throw new Exception("Invalid job_type: {$job_type}. Available types: " . implode(', ', $available_types));
			}
			
			// Validate payload using the job handler
			$handler = JobRegistry::getHandler($job_type);
			if ($handler) {
				$handler->validatePayload($payload);
			}
			
			// Enqueue the job
			$job_id = $this->Job_queue_model->enqueue(
				$job_type,
				$payload,
				$this->user_id, // Use authenticated user's ID
				$priority,
				$max_attempts
			);
			
			// Get the created job
			$job = $this->Job_queue_model->get($job_id);
			
			// Remove numeric ID from job object for API response
			$job_response = $this->sanitize_job_for_api($job);
			$job_uuid = isset($job['uuid']) ? $job['uuid'] : null;
			
			$response = array(
				'status' => 'success',
				'message' => 'Job created successfully',
				'uuid' => $job_uuid, // Public-facing UUID
				'job' => $job_response
			);
			
			$this->set_response($response, REST_Controller::HTTP_CREATED);
			
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Submit project metadata for quality assessment and enqueue a job to fetch the result
	 *
	 * POST /api/jobs/metadata_assessment
	 *
	 * Request body:
	 *   { "project_id": 123 }
	 *
	 * Submits project metadata to the external assessment API, receives an event_id,
	 * then enqueues a metadata_assessment_result job. The worker will stream the result
	 * and store it in the job. Client should poll GET /api/jobs/{uuid} for status.
	 */
	function metadata_assessment_post()
	{
		if (!$this->is_admin()) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => 'Admin access required to run metadata assessment',
			), REST_Controller::HTTP_FORBIDDEN);
			return;
		}
		try {
			$input = json_decode($this->input->raw_input_stream, true);
			if (!$input) {
				$input = $this->input->post();
			}
			if (empty($input) || empty($input['project_id'])) {
				throw new Exception("project_id is required");
			}

			$project_id = (int) $input['project_id'];

			$this->load->model('Editor_model');
			$this->load->library('Editor_acl');
			$this->editor_acl->user_has_project_access($project_id, 'view', $this->api_user);

			$project = $this->Editor_model->get_row($project_id);
			if (!$project || !isset($project['metadata'])) {
				throw new Exception("Project not found or has no metadata");
			}

			$this->load->library('DataUtils');
			$review_options = array();
			if (!empty($input['manifest_file'])) {
				$review_options['manifest_file'] = $input['manifest_file'];
			}
			if (!empty($input['team_preset'])) {
				$review_options['team_preset'] = $input['team_preset'];
			}

			$submit_response = $this->datautils->submit_metadata_review($project['metadata'], $review_options);
			if (!isset($submit_response['status_code']) || (int)$submit_response['status_code'] !== 202) {
				$error_detail = isset($submit_response['response']) ? $submit_response['response'] : 'Unknown FastAPI error';
				if (is_array($error_detail)) {
					$error_detail = json_encode($error_detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}
				throw new Exception("FastAPI review submission failed: " . $error_detail);
			}

			$fastapi_job_id = isset($submit_response['response']['job_id'])
				? $submit_response['response']['job_id']
				: null;
			if (empty($fastapi_job_id)) {
				throw new Exception("FastAPI review submission did not return job_id");
			}

			$job_type = 'metadata_assessment_result';
			if (!$this->Job_queue_model->is_valid_job_type($job_type)) {
				throw new Exception("Job type metadata_assessment_result is not available");
			}

			$payload = array(
				'fastapi_job_id' => $fastapi_job_id,
				'project_id' => $project_id,
			);
			$handler = JobRegistry::getHandler($job_type);
			if ($handler) {
				$handler->validatePayload($payload);
			}

			$priority = isset($input['priority']) ? (int) $input['priority'] : 0;
			$max_attempts = isset($input['max_attempts']) ? (int) $input['max_attempts'] : 3;
			$job_id = $this->Job_queue_model->enqueue(
				$job_type,
				$payload,
				$this->user_id,
				$priority,
				$max_attempts
			);

			$job = $this->Job_queue_model->get($job_id);
			$job_response = $this->sanitize_job_for_api($job);
			$job_uuid = isset($job['uuid']) ? $job['uuid'] : null;

			$response = array(
				'status' => 'success',
				'message' => 'Metadata assessment submitted; poll job for result',
				'uuid' => $job_uuid,
				'fastapi_job_id' => $fastapi_job_id,
				'job' => $job_response,
			);
			$this->set_response($response, REST_Controller::HTTP_CREATED);

		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Cancel a job by UUID or numeric ID.
	 *
	 * POST /api/jobs/cancel/{job_uuid}
	 */
	function cancel_post($job_identifier = null)
	{
		try {
			if (!$job_identifier) {
				$job_identifier = $this->uri->segment(4);
			}
			if (!$job_identifier) {
				throw new Exception("Invalid or missing job identifier");
			}

			$job = $this->Job_queue_model->get_by_uuid_or_id($job_identifier);
			if (!$job) {
				$this->set_response(array(
					'status' => 'failed',
					'message' => 'Job not found'
				), REST_Controller::HTTP_NOT_FOUND);
				return;
			}

			$is_admin = $this->is_admin();
			if (!$is_admin && (int)$job['user_id'] !== (int)$this->user_id) {
				$this->set_response(array(
					'status' => 'failed',
					'message' => 'Access denied'
				), REST_Controller::HTTP_FORBIDDEN);
				return;
			}

			if (in_array($job['status'], array('completed', 'failed', 'cancelled'), true)) {
				throw new Exception("Cannot cancel job with status '{$job['status']}'");
			}

			$fastapi_cancel = null;
			if (
				$job['job_type'] === 'metadata_assessment_result'
				&& is_array($job['payload'])
				&& !empty($job['payload']['fastapi_job_id'])
			) {
				$this->load->library('DataUtils');
				$fastapi_cancel = $this->datautils->cancel_job($job['payload']['fastapi_job_id']);
			}

			$updated = $this->Job_queue_model->mark_cancelled($job['id'], 'Cancelled by user');
			if (!$updated) {
				throw new Exception("Job could not be cancelled (it may have already finished)");
			}

			$this->set_response(array(
				'status' => 'success',
				'message' => 'Job cancelled successfully',
				'uuid' => isset($job['uuid']) ? $job['uuid'] : null,
				'fastapi_cancel' => $fastapi_cancel
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * POST project metadata to the assessment API and return the event_id from the response
	 *
	 * @param string $base_url Assessment API base URL (no trailing slash)
	 * @param array $metadata Project metadata array
	 * @return string|null event_id or null
	 */
	private function submitToAssessmentApi($base_url, $metadata)
	{
		$this->load->config('metadata_assessment');
		$connect_timeout = (int) $this->config->item('submit_connect_timeout', 'metadata_assessment') ?: 30;
		$read_timeout = (int) $this->config->item('submit_read_timeout', 'metadata_assessment') ?: 30;
		$headers = array('Content-Type' => 'application/json');
		$api_key = $this->config->item('api_key', 'metadata_assessment');
		$api_key_header = $this->config->item('api_key_header', 'metadata_assessment');
		if (!empty($api_key) && !empty($api_key_header)) {
			$headers[$api_key_header] = $api_key;
		}

		$body = array(
			'data' => array(json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
		);

		try {
			$client = new \GuzzleHttp\Client(array(
				\GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => $connect_timeout,
				\GuzzleHttp\RequestOptions::TIMEOUT => $read_timeout,
			));
			$response = $client->post($base_url, array(
				\GuzzleHttp\RequestOptions::HEADERS => $headers,
				\GuzzleHttp\RequestOptions::JSON => $body,
			));
		} catch (\GuzzleHttp\Exception\GuzzleException $e) {
			throw new Exception("Assessment API request failed: " . $e->getMessage());
		}

		$raw = (string) $response->getBody();
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			if (!empty($decoded['event_id'])) {
				return $decoded['event_id'];
			}
			if (!empty($decoded['event-id'])) {
				return $decoded['event-id'];
			}
		}
		if (preg_match('/"([a-f0-9]{32})"/', $raw, $m)) {
			return $m[1];
		}
		return null;
	}

	/**
	 * Get available job types
	 * 
	 * Returns a list of all registered job types that can be created
	 * 
	 * GET /api/jobs/types
	 * 
	 * Returns array of job type strings and their basic information
	 */
	function types_get()
	{
		try {
			$job_types = $this->Job_queue_model->get_job_types();
			
			// Build detailed information for each job type
			$types_info = array();
			foreach ($job_types as $job_type) {
				$handler = JobRegistry::getHandler($job_type);
				
				$type_info = array(
					'job_type' => $job_type,
					'available' => true
				);
				
				// Add handler-specific information if available
				if ($handler && class_exists('ReflectionClass')) {
					try {
						// Get class name for description
						$reflection = new ReflectionClass($handler);
						$doc_comment = $reflection->getDocComment();
						
						// Extract description from docblock
						if ($doc_comment) {
							$lines = explode("\n", $doc_comment);
							foreach ($lines as $line) {
								$line = trim($line);
								if (strpos($line, '*') === 0 && strpos($line, '**') === false) {
									$desc = trim($line, '* /');
									if (!empty($desc) && strpos($desc, '@') !== 0 && strpos($desc, '/') !== 0) {
										$type_info['description'] = $desc;
										break;
									}
								}
							}
						}
					} catch (Exception $e) {
						// If reflection fails, just skip description
					}
				}
				
				$types_info[] = $type_info;
			}
			
			$response = array(
				'status' => 'success',
				'job_types' => $types_info,
				'total' => count($types_info)
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
	 * Get queue status and statistics
	 * 
	 * Returns overall queue statistics including counts by status
	 * 
	 * Query parameters:
	 *   user_id - Filter statistics by user ID (admin only, or own stats for regular users)
	 * 
	 * GET /api/jobs/status
	 * GET /api/jobs/status?user_id=123
	 */
	function status_get()
	{
		try {
			$user_id_filter = $this->input->get('user_id');
			
			// Non-admin users can only see their own stats
			$is_admin = $this->is_admin();
			
			if (!$is_admin) {
				// Regular users can only see their own stats
				$user_id_filter = $this->user_id;
			}
			
			// Get overall statistics
			$stats = $this->Job_queue_model->get_stats();
			
			// If user_id filter is provided, get user-specific stats
			$user_stats = null;
			if ($user_id_filter) {
				$user_stats = array(
					'pending' => count($this->Job_queue_model->get_by_user($user_id_filter, 'pending', 1000, 0)),
					'processing' => count($this->Job_queue_model->get_by_user($user_id_filter, 'processing', 1000, 0)),
					'completed' => count($this->Job_queue_model->get_by_user($user_id_filter, 'completed', 1000, 0)),
					'failed' => count($this->Job_queue_model->get_by_user($user_id_filter, 'failed', 1000, 0))
				);
				$user_stats['total'] = array_sum($user_stats);
			}
			
			$response = array(
				'status' => 'success',
				'queue' => $stats,
				//'user_id' => $user_id_filter
			);
			
			if ($user_stats !== null) {
				$response['user_stats'] = $user_stats;
			}
			
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
	 * Get worker daemon status
	 * 
	 * Checks if the worker daemon is running by checking PID and heartbeat files
	 * 
	 * GET /api/jobs/worker_status
	 */
	function worker_status_get()
	{
		try {
			$worker_status = $this->_get_worker_status();
			
			$response = array(
				'status' => 'success',
				'worker' => $worker_status
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


	private function _get_worker_status_response()
	{
		$worker_status = $this->_get_worker_status();
		$worker_status_response = array();

		// Process liveness is authoritative. Heartbeat can lag during long blocking jobs
		// because the React event loop does not run while a handler is processing.
		if (!empty($worker_status['is_running'])) {
			$worker_status_response['status'] = 'running';
		} else {
			$worker_status_response['status'] = 'stopped';
		}

		return $worker_status_response;
	}

	/**
	 * Get worker status data
	 * 
	 * @return array Worker status information
	 */
	private function _get_worker_status()
	{
		$this->load->config('editor');
		$storage_path = $this->config->item('storage_path', 'editor');
		$tmp_path = rtrim($storage_path, '/') . '/tmp';
		
		$pid_file = $tmp_path . '/worker.pid';
		$heartbeat_file = $tmp_path . '/worker.heartbeat';
		
		$is_running = false;
		$pid_data = null;
		$heartbeat_data = null;
		$heartbeat_age = null;
		$is_alive = false;
		
		// Check PID file
		if (file_exists($pid_file)) {
			$pid_content = @file_get_contents($pid_file);
			if ($pid_content) {
				$pid_data = json_decode($pid_content, true);
				
				// Verify process is still running
				if ($pid_data && isset($pid_data['pid'])) {
					$is_running = $this->is_process_running($pid_data['pid']);
				}
			}
		}
		
		// Check heartbeat file
		if (file_exists($heartbeat_file)) {
			$heartbeat_content = @file_get_contents($heartbeat_file);
			if ($heartbeat_content) {
				$heartbeat_data = json_decode($heartbeat_content, true);
				
				if ($heartbeat_data && isset($heartbeat_data['timestamp'])) {
					$heartbeat_age = time() - $heartbeat_data['timestamp'];
					
					// Worker is alive if heartbeat is less than 15 seconds old (3x the 5-second interval)
					$is_alive = ($heartbeat_age < 15);
				}
			}
		}
		
		// Also check for active workers in the database (jobs currently being processed)
		$active_workers = $this->Job_queue_model->get_by_status('processing', 100, 0);
		$active_worker_ids = array();
		foreach ($active_workers as $job) {
			if (!empty($job['worker_id'])) {
				$active_worker_ids[$job['worker_id']] = true;
			}
		}
		$active_worker_count = count($active_worker_ids);
		
		return array(
			'is_running' => $is_running,
			'is_alive' => $is_alive,
			'pid_file_exists' => file_exists($pid_file),
			'heartbeat_file_exists' => file_exists($heartbeat_file),
			'heartbeat_age_seconds' => $heartbeat_age,
			'pid_data' => $pid_data,
			'heartbeat_data' => $heartbeat_data,
			'active_worker_count' => $active_worker_count,
			'active_worker_ids' => array_keys($active_worker_ids)
		);
	}
	
	/**
	 * Remove numeric ID from job object for API responses
	 * 
	 * @param array $job Job data
	 * @return array Job data without numeric ID
	 */
	private function sanitize_job_for_api($job)
	{
		if (!$job || !is_array($job)) {
			return $job;
		}
		
		// Remove numeric ID, keep only UUID
		$sanitized = $job;
		unset($sanitized['id']);
		
		return $sanitized;
	}

	/**
	 * Check if a process is running by PID
	 * 
	 * @param int $pid Process ID
	 * @return bool True if process is running
	 */
	private function is_process_running($pid)
	{
		if (PHP_OS_FAMILY === 'Windows') {
			// Windows: use tasklist
			$command = "tasklist /FI \"PID eq {$pid}\" 2>nul";
			$output = @shell_exec($command);
			return $output && stripos($output, (string)$pid) !== false;
		} else {
			// Unix/Linux/macOS: use ps
			$command = "ps -p {$pid} 2>/dev/null";
			$output = @shell_exec($command);
			return $output && trim($output) !== '';
		}
	}
}

