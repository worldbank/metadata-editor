<?php

class Job_queue_model extends CI_Model {

	private $fields = array(
		'uuid',
		'job_type',
		'job_hash',
		'status',
		'priority',
		'user_id',
		'payload',
		'result',
		'error_message',
		'attempts',
		'max_attempts',
		'created_at',
		'started_at',
		'completed_at',
		'worker_id'
	);

    public function __construct()
    {
        parent::__construct();
        
        // Load job registry
        require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';
        require_once APPPATH . 'libraries/Jobs/JobRegistry.php';
    }

	/**
	 * Get available job types from the JobRegistry
	 * 
	 * @return array Array of job type strings
	 */
	public function get_job_types()
	{
		return JobRegistry::getJobTypes();
	}
	
	/**
	 * Validate that a job type is registered
	 * 
	 * @param string $job_type Job type to validate
	 * @return bool True if valid
	 */
	public function is_valid_job_type($job_type)
	{
		return JobRegistry::hasHandler($job_type);
	}

	/**
	 * Enqueue a new job
	 * 
	 * @param string $job_type Type of job (e.g., 'pdf_generation')
	 * @param array $payload Job parameters (will be JSON encoded)
	 * @param int|null $user_id User who initiated the job (NULL for system jobs)
	 * @param int $priority Job priority (higher = more priority, default: 0)
	 * @param int $max_attempts Maximum retry attempts (default: 3)
	 * @return int Job ID (existing job ID if duplicate found, new job ID otherwise)
	 * @throws Exception If job type is invalid or payload validation fails
	 */
	function enqueue($job_type, $payload, $user_id = null, $priority = 0, $max_attempts = 3)
	{
		// Validate job type
		if (!$this->is_valid_job_type($job_type)) {
			throw new Exception("Invalid job type: {$job_type}");
		}
		
		// Start transaction for atomic idempotency check and insert
		$this->db->trans_start();
		
		try {
			// Get handler and validate payload
			$handler = JobRegistry::getHandler($job_type);
			if ($handler) {
				$handler->validatePayload($payload);
				
				// Generate job hash for idempotency
				$job_hash = $handler->generateJobHash($payload);
			} else {
				// If no handler found, generate a basic hash from job_type and payload
				$hash_data = array(
					'job_type' => $job_type,
					'payload' => $payload
				);
				ksort($hash_data);
				$job_hash = hash('sha256', json_encode($hash_data));
			}
			
			// Check for existing job with the same hash (idempotency check)
			// Only enforce idempotency for pending/processing jobs
			// Allow new jobs for completed, failed, or cancelled jobs (enables regeneration/retries)
			
			// Only perform idempotency check if job_hash is not null
			if (!empty($job_hash)) {
				// Use row-level locking to prevent race conditions
				// Only check for pending or processing jobs (active jobs)
				$sql = "SELECT * FROM job_queue 
						WHERE job_hash = ? 
						AND status IN ('pending', 'processing')
						ORDER BY created_at DESC
						LIMIT 1
						FOR UPDATE";
				$query = $this->db->query($sql, array($job_hash));
				$existing_job = $query->row_array();
				
				if ($existing_job) {
					// Job with same hash already exists and is pending/processing
					// Return existing job ID for idempotency (prevents duplicate active jobs)
					$this->db->trans_complete();
					// Return the ID, but note that the UUID is already in the existing_job array
					return (int)$existing_job['id'];
				}
			}
			
			// No active job found - allow creation of new job
			// This allows:
			// - Retrying failed jobs
			// - Regenerating completed jobs (e.g., PDF with updated metadata)
			// - Creating new jobs after cancellation
			
			// No existing job found - create new one
			// Generate UUID for public-facing API access
			$uuid = $this->generate_uuid();
			
			$data = array(
				'uuid' => $uuid,
				'job_type' => $job_type,
				'job_hash' => $job_hash,
				'status' => 'pending',
				'priority' => $priority,
				'user_id' => $user_id,
				'payload' => is_array($payload) ? json_encode($payload) : $payload,
				'attempts' => 0,
				'max_attempts' => $max_attempts,
				'created_at' => date('Y-m-d H:i:s')
			);

			// Keep only valid fields
			$data = array_intersect_key($data, array_flip($this->fields));

			$this->db->insert('job_queue', $data);
			$job_id = $this->db->insert_id();
			
			$this->db->trans_complete();
			
			if ($this->db->trans_status() === FALSE) {
				// Transaction failed - check if it's a duplicate key error
				$error = $this->db->error();
				
				// Check for duplicate key error (1062) or unique constraint violation
				if (isset($error['code']) && ($error['code'] == 1062 || strpos($error['message'], 'Duplicate entry') !== false)) {
					// This means there's still a UNIQUE constraint on job_hash
					// Try to get the existing job that was inserted by another process
					$this->db->where('job_hash', $job_hash);
					$this->db->where_in('status', array('pending', 'processing'));
					$this->db->order_by('id', 'DESC');
					$race_job = $this->db->get('job_queue')->row_array();
					
					if ($race_job) {
						// Return the job that was created by the other process
						// Return the ID, UUID is already in the race_job array
						return (int)$race_job['id'];
					}
					
					// If no pending/processing job found, it might be a completed job
					// In this case, we should still allow the new job, but the UNIQUE constraint prevents it
					// This indicates the database schema needs to be updated to remove the UNIQUE constraint
					throw new Exception("Duplicate job detected. Please update database schema to remove UNIQUE constraint on job_hash: " . $error['message']);
				}
				
				throw new Exception("Failed to enqueue job: " . (isset($error['message']) ? $error['message'] : 'database error'));
			}
			
			return $job_id;
			
		} catch (Exception $e) {
			$this->db->trans_complete();
			throw $e;
		}
	}

	/**
	 * Get next pending job from queue and mark it as processing atomically
	 * Uses row locking to prevent multiple workers from processing the same job
	 * 
	 * @param string|null $worker_id Worker identifier
	 * @return array|null Job data or null if no jobs available
	 */
	function get_next_job($worker_id = null)
	{
		$this->db->trans_start();
		
		$sql = "SELECT * FROM job_queue 
				WHERE status = 'pending' 
				ORDER BY priority DESC, created_at ASC 
				LIMIT 1 
				FOR UPDATE";
		
		$query = $this->db->query($sql);
		if ($query === false) {
			$error = $this->db->error();
			$message = isset($error['message']) && $error['message'] !== ''
				? $error['message']
				: 'unknown database error';
			$this->db->trans_complete();
			throw new RuntimeException('Database query failed in get_next_job: ' . $message);
		}
		$job = $query->row_array();
		
		if ($job) {
			$update_data = array(
				'status' => 'processing',
				'started_at' => date('Y-m-d H:i:s'),
				'worker_id' => $worker_id
			);
			
			$this->db->where('id', $job['id']);
			$this->db->where('status', 'pending');
			$this->db->update('job_queue', $update_data);
			
			// Update failed?
			if ($this->db->affected_rows() == 0) {
				$job = null;
			} else {
				// Decode JSON payload
				if (!empty($job['payload'])) {
					$job['payload'] = json_decode($job['payload'], true);
				}
				
				// Decode JSON result if exists
				if (!empty($job['result'])) {
					$job['result'] = json_decode($job['result'], true);
				}
				
				// Update job array with new status
				$job['status'] = 'processing';
				$job['started_at'] = $update_data['started_at'];
				$job['worker_id'] = $worker_id;
			}
		}
		
		$this->db->trans_complete();
		
		return $job ? $job : null;
	}

	/**
	 * Mark job as processing
	 * 
	 * @param int $job_id Job ID
	 * @param string|null $worker_id Worker identifier
	 * @return bool Success
	 */
	function mark_processing($job_id, $worker_id = null)
	{
		$data = array(
			'status' => 'processing',
			'started_at' => date('Y-m-d H:i:s'),
			'worker_id' => $worker_id
		);

		$this->db->where('id', $job_id);
		$this->db->where('status', 'pending'); // Only update if still pending
		$this->db->update('job_queue', $data);
		
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Mark job as completed
	 * 
	 * @param int $job_id Job ID
	 * @param array|null $result Job result data (will be JSON encoded)
	 * @return bool Success
	 */
	function mark_completed($job_id, $result = null)
	{
		$data = array(
			'status' => 'completed',
			'completed_at' => date('Y-m-d H:i:s'),
			'error_message' => null
		);

		if ($result !== null) {
			$data['result'] = is_array($result) ? json_encode($result) : $result;
		}

		$this->db->where('id', $job_id);
		$this->db->update('job_queue', $data);
		
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Mark job as failed
	 * 
	 * @param int $job_id Job ID
	 * @param string $error_message Error message
	 * @return bool Success
	 */
	function mark_failed($job_id, $error_message)
	{
		$job = $this->get_by_id($job_id);
		
		if (!$job) {
			return false;
		}

		$attempts = $job['attempts'] + 1;
		$status = ($attempts >= $job['max_attempts']) ? 'failed' : 'pending';

		$data = array(
			'status' => $status,
			'attempts' => $attempts,
			'error_message' => $error_message,
			'worker_id' => null // Clear worker_id so job can be retried
		);

		// If max attempts reached, mark as completed_at
		if ($status === 'failed') {
			$data['completed_at'] = date('Y-m-d H:i:s');
		}

		$this->db->where('id', $job_id);
		$this->db->update('job_queue', $data);
		
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Get job by ID
	 * 
	 * @param int $job_id Job ID
	 * @return array|null Job data or null if not found
	 */
	function get_by_id($job_id)
	{
		$this->db->where('id', $job_id);
		$query = $this->db->get('job_queue');
		$job = $query->row_array();
		
		if ($job) {
			// Decode JSON fields
			if (!empty($job['payload'])) {
				$job['payload'] = json_decode($job['payload'], true);
			}
			if (!empty($job['result'])) {
				$job['result'] = json_decode($job['result'], true);
			}
		}
		
		return $job ? $job : null;
	}

	/**
	 * Get job by ID (alias for get_by_id)
	 * 
	 * @param int $job_id Job ID
	 * @return array|null Job data or null if not found
	 */
	function get($job_id)
	{
		return $this->get_by_id($job_id);
	}

	/**
	 * Get job by UUID
	 * 
	 * @param string $uuid Job UUID
	 * @return array|null Job data or null if not found
	 */
	function get_by_uuid($uuid)
	{
		if (empty($uuid)) {
			return null;
		}
		
		$this->db->where('uuid', $uuid);
		$query = $this->db->get('job_queue');
		$job = $query->row_array();
		
		if ($job) {
			// Decode JSON fields
			if (!empty($job['payload'])) {
				$job['payload'] = json_decode($job['payload'], true);
			}
			if (!empty($job['result'])) {
				$job['result'] = json_decode($job['result'], true);
			}
		}
		
		return $job ? $job : null;
	}

	/**
	 * Get job by UUID or ID (supports both for backward compatibility)
	 * 
	 * @param string|int $identifier Job UUID or ID
	 * @return array|null Job data or null if not found
	 */
	function get_by_uuid_or_id($identifier)
	{
		if (empty($identifier)) {
			return null;
		}
		
		// Check if it's a UUID (36 characters with hyphens) or numeric ID
		if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
			return $this->get_by_uuid($identifier);
		} elseif (is_numeric($identifier)) {
			return $this->get_by_id($identifier);
		}
		
		return null;
	}

	/**
	 * Generate a UUID v4
	 * 
	 * @return string UUID
	 */
	private function generate_uuid()
	{
		// Try to use PHP's built-in function first (PHP 7.0+)
		if (function_exists('random_bytes')) {
			$data = random_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		}
		
		// Fallback: use MySQL UUID() function if available
		$query = $this->db->query('SELECT UUID() as uuid');
		$row = $query->row();
		if ($row && !empty($row->uuid)) {
			return $row->uuid;
		}
		
		// Last resort: simple UUID generation
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	/**
	 * Get jobs by user ID
	 * 
	 * @param int $user_id User ID
	 * @param string|null $status Filter by status (optional)
	 * @param int $limit Number of jobs to return
	 * @param int $offset Offset for pagination
	 * @return array Array of jobs
	 */
	function get_by_user($user_id, $status = null, $limit = 50, $offset = 0)
	{
		$this->db->where('user_id', $user_id);
		
		if ($status !== null) {
			$this->db->where('status', $status);
		}
		
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit);
		
		if ($offset > 0) {
			$this->db->offset($offset);
		}
		
		$query = $this->db->get('job_queue');
		$jobs = $query->result_array();
		
		// Decode JSON fields
		foreach ($jobs as &$job) {
			if (!empty($job['payload'])) {
				$job['payload'] = json_decode($job['payload'], true);
			}
			if (!empty($job['result'])) {
				$job['result'] = json_decode($job['result'], true);
			}
		}
		
		return $jobs;
	}

	/**
	 * Get jobs by status
	 * 
	 * @param string $status Job status
	 * @param int $limit Number of jobs to return
	 * @param int $offset Offset for pagination
	 * @return array Array of jobs
	 */
	function get_by_status($status, $limit = 50, $offset = 0)
	{
		$this->db->where('status', $status);
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit);
		
		if ($offset > 0) {
			$this->db->offset($offset);
		}
		
		$query = $this->db->get('job_queue');
		$jobs = $query->result_array();
		
		// Decode JSON fields
		foreach ($jobs as &$job) {
			if (!empty($job['payload'])) {
				$job['payload'] = json_decode($job['payload'], true);
			}
			if (!empty($job['result'])) {
				$job['result'] = json_decode($job['result'], true);
			}
		}
		
		return $jobs;
	}

	/**
	 * Get jobs by job type
	 * 
	 * @param string $job_type Job type
	 * @param string|null $status Filter by status (optional)
	 * @param int $limit Number of jobs to return
	 * @return array Array of jobs
	 */
	function get_by_job_type($job_type, $status = null, $limit = 50)
	{
		$this->db->where('job_type', $job_type);
		
		if ($status !== null) {
			$this->db->where('status', $status);
		}
		
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit);
		
		$query = $this->db->get('job_queue');
		$jobs = $query->result_array();
		
		// Decode JSON fields
		foreach ($jobs as &$job) {
			if (!empty($job['payload'])) {
				$job['payload'] = json_decode($job['payload'], true);
			}
			if (!empty($job['result'])) {
				$job['result'] = json_decode($job['result'], true);
			}
		}
		
		return $jobs;
	}

	/**
	 * Get all jobs with optional filters
	 * 
	 * @param array $filters Filter options (status, job_type, user_id)
	 * @param int $limit Number of jobs to return
	 * @param int $offset Offset for pagination
	 * @return array Array of jobs
	 */
	function get_all($filters = array(), $limit = 50, $offset = 0)
	{
		$status = isset($filters['status']) ? $filters['status'] : null;
		$job_type = isset($filters['job_type']) ? $filters['job_type'] : null;
		$user_id = isset($filters['user_id']) ? $filters['user_id'] : null;
		$active_only = !empty($filters['active']);
		$stale_only = !empty($filters['stale']);
		$history_only = !empty($filters['history']);
		
		$this->db->select('*');
		$this->db->from('job_queue');
		
		if ($stale_only) {
			$this->apply_stale_filter_sql($this->get_stale_config());
		} elseif ($active_only) {
			$this->db->where_in('status', array('pending', 'held', 'processing'));
		} elseif ($history_only) {
			$this->db->where_in('status', array('completed', 'failed'));
		} elseif ($status !== null) {
			$this->db->where('status', $status);
		}
		
		if ($job_type !== null) {
			$this->db->where('job_type', $job_type);
		}
		
		if ($user_id !== null) {
			$this->db->where('user_id', $user_id);
		}
		
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit);
		
		if ($offset > 0) {
			$this->db->offset($offset);
		}
		
		$query = $this->db->get();
		$jobs = $query->result_array();
		
		// Decode JSON fields
		foreach ($jobs as &$job) {
			if (!empty($job['payload'])) {
				$job['payload'] = json_decode($job['payload'], true);
			}
			if (!empty($job['result'])) {
				$job['result'] = json_decode($job['result'], true);
			}
		}
		
		return $jobs;
	}

	/**
	 * Count jobs matching optional filters
	 *
	 * @param array $filters Filter options (status, job_type, user_id)
	 * @return int Total matching jobs
	 */
	function count_jobs($filters = array())
	{
		$status = isset($filters['status']) ? $filters['status'] : null;
		$job_type = isset($filters['job_type']) ? $filters['job_type'] : null;
		$user_id = isset($filters['user_id']) ? $filters['user_id'] : null;
		$active_only = !empty($filters['active']);
		$stale_only = !empty($filters['stale']);
		$history_only = !empty($filters['history']);

		$this->db->from('job_queue');

		if ($stale_only) {
			$this->apply_stale_filter_sql($this->get_stale_config());
		} elseif ($active_only) {
			$this->db->where_in('status', array('pending', 'held', 'processing'));
		} elseif ($history_only) {
			$this->db->where_in('status', array('completed', 'failed'));
		} elseif ($status !== null && $status !== '') {
			$this->db->where('status', $status);
		}

		if ($job_type !== null && $job_type !== '') {
			$this->db->where('job_type', $job_type);
		}

		if ($user_id !== null && $user_id !== '') {
			$this->db->where('user_id', $user_id);
		}

		return (int) $this->db->count_all_results();
	}

	/**
	 * Get queue statistics
	 * 
	 * @return array Statistics array
	 */
	function get_stats()
	{
		$stats = array();
		
		// Count by status
		$this->db->select('status, COUNT(*) as count');
		$this->db->group_by('status');
		$query = $this->db->get('job_queue');
		$status_counts = $query->result_array();
		
		foreach ($status_counts as $row) {
			$stats[$row['status']] = (int)$row['count'];
		}
		
		// Total jobs
		$stats['total'] = array_sum($stats);
		
		// Pending jobs count
		$stats['pending'] = isset($stats['pending']) ? $stats['pending'] : 0;
		
		// Processing jobs count
		$stats['processing'] = isset($stats['processing']) ? $stats['processing'] : 0;
		
		// Failed jobs count
		$stats['failed'] = isset($stats['failed']) ? $stats['failed'] : 0;
		
		// Completed jobs count
		$stats['completed'] = isset($stats['completed']) ? $stats['completed'] : 0;

		// Held jobs count
		$stats['held'] = isset($stats['held']) ? $stats['held'] : 0;
		
		return $stats;
	}

	/**
	 * Stale/expiry thresholds from editor config
	 *
	 * @return array
	 */
	function get_stale_config()
	{
		$CI =& get_instance();
		$CI->load->config('editor');

		return array(
			'stale_pending_hours' => max(1, (int) ($CI->config->item('jobs_stale_pending_hours', 'editor') ?: 48)),
			'expire_pending_hours' => max(1, (int) ($CI->config->item('jobs_expire_pending_hours', 'editor') ?: 168)),
			'stuck_processing_hours' => max(1, (int) ($CI->config->item('jobs_stuck_processing_hours', 'editor') ?: 2)),
		);
	}

	/**
	 * Determine whether an active job is stale (for UI badges and filters)
	 *
	 * @param array $job Job row
	 * @return array { is_stale, stale_reason, stale_level }
	 */
	function get_job_stale_info($job)
	{
		$config = $this->get_stale_config();
		$info = array(
			'is_stale' => false,
			'stale_reason' => null,
			'stale_level' => null,
		);

		if (!$job || empty($job['status'])) {
			return $info;
		}

		if ($job['status'] === 'pending' && !empty($job['created_at'])) {
			$age_hours = (time() - strtotime($job['created_at'])) / 3600;
			if ($age_hours >= $config['expire_pending_hours']) {
				$info['is_stale'] = true;
				$info['stale_reason'] = 'Pending beyond expiry threshold';
				$info['stale_level'] = 'critical';
				return $info;
			}
			if ($age_hours >= $config['stale_pending_hours']) {
				$info['is_stale'] = true;
				$info['stale_reason'] = 'Pending longer than expected';
				$info['stale_level'] = 'warning';
				return $info;
			}
		}

		if ($job['status'] === 'processing' && !empty($job['started_at'])) {
			$age_hours = (time() - strtotime($job['started_at'])) / 3600;
			if ($age_hours >= $config['stuck_processing_hours']) {
				$info['is_stale'] = true;
				$info['stale_reason'] = 'Processing longer than expected';
				$info['stale_level'] = 'warning';
			}
		}

		return $info;
	}

	/**
	 * Apply SQL filter for stale active jobs only
	 *
	 * @param array $config Stale config from get_stale_config()
	 */
	private function apply_stale_filter_sql($config)
	{
		$pending_cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) $config['stale_pending_hours'] . ' hours'));
		$processing_cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) $config['stuck_processing_hours'] . ' hours'));

		$this->db->group_start();
		$this->db->group_start();
		$this->db->where('status', 'pending');
		$this->db->where('created_at <', $pending_cutoff);
		$this->db->group_end();
		$this->db->or_group_start();
		$this->db->where('status', 'processing');
		$this->db->where('started_at IS NOT NULL', null, false);
		$this->db->where('started_at <', $processing_cutoff);
		$this->db->group_end();
		$this->db->group_end();
	}

	/**
	 * Count stale active jobs
	 *
	 * @param int|null $user_id Optional user filter
	 * @return int
	 */
	function count_stale_jobs($user_id = null)
	{
		$filters = array('stale' => true);
		if ($user_id !== null && $user_id !== '') {
			$filters['user_id'] = $user_id;
		}
		return $this->count_jobs($filters);
	}

	/**
	 * Mark a job as failed due to expiry (record retained)
	 *
	 * @param int $job_id Job ID
	 * @param string $error_message Error message
	 * @return bool Success
	 */
	function mark_expired($job_id, $error_message)
	{
		$data = array(
			'status' => 'failed',
			'completed_at' => date('Y-m-d H:i:s'),
			'error_message' => $error_message,
			'worker_id' => null,
			'started_at' => null,
		);

		$this->db->where('id', $job_id);
		$this->db->where('status', 'pending');
		$this->db->update('job_queue', $data);

		return $this->db->affected_rows() > 0;
	}

	/**
	 * Fail pending jobs that were never picked up within the expiry window
	 *
	 * @param int|null $hours Override expire threshold (default from config)
	 * @return int Number of jobs expired
	 */
	function expire_stale_pending_jobs($hours = null)
	{
		$config = $this->get_stale_config();
		if ($hours === null) {
			$hours = $config['expire_pending_hours'];
		}
		$hours = (int) $hours;
		if ($hours <= 0) {
			return 0;
		}

		$cutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
		$this->db->where('status', 'pending');
		$this->db->where('created_at <', $cutoff);
		$query = $this->db->get('job_queue');
		$jobs = $query->result_array();

		$expired = 0;
		foreach ($jobs as $job) {
			$message = "Job expired: not processed within {$hours} hours";
			if ($this->mark_expired($job['id'], $message)) {
				$expired++;
			}
		}

		return $expired;
	}

	/**
	 * Reset stuck processing jobs and expire ancient pending jobs
	 *
	 * @return array Maintenance counters
	 */
	function run_job_maintenance()
	{
		$config = $this->get_stale_config();

		return array(
			'reset_stuck' => $this->reset_stuck_jobs($config['stuck_processing_hours']),
			'expired_pending' => $this->expire_stale_pending_jobs($config['expire_pending_hours']),
		);
	}

	/**
	 * Clean up old completed jobs (not run automatically; available for manual maintenance)
	 * 
	 * @param int $days Number of days to keep completed jobs (default: 30)
	 * @return int Number of jobs deleted
	 */
	function cleanup_old_jobs($days = 30)
	{
		$cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
		
		$this->db->where('status', 'completed');
		$this->db->where('completed_at <', $cutoff_date);
		$this->db->delete('job_queue');
		
		return $this->db->affected_rows();
	}

	/**
	 * Clean up old jobs (completed and failed) older than specified hours
	 * (not run automatically; available for manual maintenance)
	 * 
	 * @param int $hours Number of hours to keep jobs (default: 12)
	 * @return int Number of jobs deleted
	 */
	function cleanup_old_jobs_by_hours($hours = 12)
	{
		$cutoff_date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
		
		// Clean up completed jobs older than the cutoff (based on completed_at)
		$this->db->where('status', 'completed');
		$this->db->where('completed_at <', $cutoff_date);
		$this->db->delete('job_queue');
		$deleted_completed = $this->db->affected_rows();
		
		// Clean up failed jobs older than the cutoff (based on completed_at)
		$this->db->where('status', 'failed');
		$this->db->where('completed_at <', $cutoff_date);
		$this->db->delete('job_queue');
		$deleted_failed = $this->db->affected_rows();
		
		return $deleted_completed + $deleted_failed;
	}

	/**
	 * Reset stuck jobs (jobs that have been processing for too long)
	 * 
	 * @param int $hours Number of hours before considering a job stuck (default: 2)
	 * @return int Number of jobs reset
	 */
	function reset_stuck_jobs($hours = 2)
	{
		$cutoff_time = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
		
		$this->db->where('status', 'processing');
		$this->db->where('started_at <', $cutoff_time);
		
		$data = array(
			'status' => 'pending',
			'started_at' => null,
			'worker_id' => null,
			'attempts' => $this->db->protect_identifiers('attempts') . ' + 1'
		);
		
		// Use raw query for incrementing attempts
		$this->db->set('attempts', 'attempts + 1', false);
		$this->db->set('status', 'pending');
		$this->db->set('started_at', null);
		$this->db->set('worker_id', null);
		$this->db->update('job_queue');
		
		return $this->db->affected_rows();
	}

	/**
	 * Hold a pending job (skip until released back to pending)
	 *
	 * @param int $job_id Job ID
	 * @return bool Success
	 */
	function hold_job($job_id)
	{
		$this->db->where('id', $job_id);
		$this->db->where('status', 'pending');
		$this->db->update('job_queue', array('status' => 'held'));

		return $this->db->affected_rows() > 0;
	}

	/**
	 * Release a held job back to the pending queue
	 *
	 * @param int $job_id Job ID
	 * @return bool Success
	 */
	function release_job($job_id)
	{
		$this->db->where('id', $job_id);
		$this->db->where('status', 'held');
		$this->db->update('job_queue', array('status' => 'pending'));

		return $this->db->affected_rows() > 0;
	}

	/**
	 * Hold all pending jobs
	 *
	 * @return int Number of jobs held
	 */
	function hold_all_pending()
	{
		$this->db->where('status', 'pending');
		$this->db->update('job_queue', array('status' => 'held'));

		return (int) $this->db->affected_rows();
	}

	/**
	 * Release all held jobs back to pending
	 *
	 * @return int Number of jobs released
	 */
	function release_all_held()
	{
		$this->db->where('status', 'held');
		$this->db->update('job_queue', array('status' => 'pending'));

		return (int) $this->db->affected_rows();
	}

	/**
	 * Cancel a pending job (record retained as failed)
	 *
	 * @param int $job_id Job ID
	 * @param string $message Cancellation reason
	 * @return bool Success
	 */
	function cancel_job($job_id, $message = 'Cancelled by user')
	{
		$data = array(
			'status' => 'failed',
			'completed_at' => date('Y-m-d H:i:s'),
			'error_message' => $message,
			'worker_id' => null,
			'started_at' => null,
		);

		$this->db->where('id', $job_id);
		$this->db->where('status', 'pending');
		$this->db->update('job_queue', $data);

		return $this->db->affected_rows() > 0;
	}

	/**
	 * Delete a terminal job record
	 *
	 * @param int $job_id Job ID
	 * @return bool Success
	 */
	function delete_job($job_id)
	{
		$this->db->where('id', $job_id);
		$this->db->where_in('status', array('completed', 'failed'));
		$this->db->delete('job_queue');

		return $this->db->affected_rows() > 0;
	}

	/**
	 * Enqueue a new job from a failed job's parameters
	 *
	 * @param array $job Source job row (payload decoded)
	 * @param int|null $user_id User initiating the retry
	 * @return int New job ID
	 * @throws Exception If job is not failed or validation fails
	 */
	function create_retry_from_job($job, $user_id = null)
	{
		if (!$job || !is_array($job)) {
			throw new Exception('Job not found');
		}

		if ($job['status'] !== 'failed') {
			throw new Exception('Only failed jobs can be retried');
		}

		$payload = $job['payload'];
		if (is_string($payload)) {
			$payload = json_decode($payload, true);
		}
		if (!is_array($payload)) {
			throw new Exception('Invalid job payload');
		}

		$job_type = $job['job_type'];
		if (JobRegistry::hasHandler($job_type) === false) {
			throw new Exception("Invalid job_type: {$job_type}");
		}

		return $this->enqueue(
			$job_type,
			$payload,
			$user_id !== null ? $user_id : $job['user_id'],
			isset($job['priority']) ? (int) $job['priority'] : 0,
			isset($job['max_attempts']) ? (int) $job['max_attempts'] : 3
		);
	}
}

