<?php

require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Metadata Assessment Result Job Handler
 *
 * Fetches the result of a metadata quality assessment from the external API.
 * The API returns a stream (SSE); this job reads the stream until the completion
 * event and returns the decoded result (e.g. issues by field).
 */
class MetadataAssessmentResultJob implements JobHandlerInterface
{
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
	}

	/**
	 * Get the job type this handler processes
	 *
	 * @return string
	 */
	public function getJobType()
	{
		return 'metadata_assessment_result';
	}

	/**
	 * Validate the job payload
	 *
	 * @param array $payload Job payload data (fastapi_job_id, project_id)
	 * @throws Exception If validation fails
	 * @return bool True if valid
	 */
	public function validatePayload($payload)
	{
		if (empty($payload['fastapi_job_id']) || !is_string($payload['fastapi_job_id'])) {
			throw new Exception("Missing or invalid required parameter: fastapi_job_id");
		}
		if (isset($payload['project_id']) && !is_numeric($payload['project_id'])) {
			throw new Exception("Invalid project_id: must be numeric");
		}
		return true;
	}

	/**
	 * Generate a unique hash for the job based on payload (idempotency by event_id)
	 *
	 * @param array $payload Job payload data
	 * @return string Hash string (SHA256 hex)
	 */
	public function generateJobHash($payload)
	{
		$hash_data = array(
			'job_type'   => $this->getJobType(),
			'fastapi_job_id'   => isset($payload['fastapi_job_id']) ? $payload['fastapi_job_id'] : '',
		);
		ksort($hash_data);
		return hash('sha256', json_encode($hash_data));
	}

	/**
	 * Process the job: fetch assessment result stream, parse SSE, return issues
	 *
	 * @param array $job Full job data from database
	 * @param array $payload Decoded payload data (fastapi_job_id, project_id)
	 * @return array Result data (fastapi_job_id, issues, raw_data)
	 * @throws Exception If processing fails
	 */
	public function process($job, $payload)
	{
		$this->logSyncToFile('process() started (handler with sync loaded)');
		$this->validatePayload($payload);

		$fastapi_job_id = $payload['fastapi_job_id'];
		$result_data = $this->pollFastApiReviewResult($job, $fastapi_job_id);

		$project_id = isset($payload['project_id']) ? (int) $payload['project_id'] : null;
		$issues_raw = isset($result_data['issues']) ? $result_data['issues'] : array();
		$this->logSyncToFile('about_to_sync project_id=' . (string) $project_id . ' issues_raw_count=' . (is_array($issues_raw) ? count($issues_raw) : 0) . ' log_file=' . $this->getSyncLogPath());
		$sync = $this->syncAssessmentIssuesToProject($project_id, $issues_raw, $job);

		$output = array(
			'fastapi_job_id' => $fastapi_job_id,
			'project_id'   => $project_id,
			'issues'       => $issues_raw,
			'raw_data'     => isset($result_data['raw_data']) ? $result_data['raw_data'] : null,
			'completed_at'  => date('Y-m-d H:i:s'),
			'sync_created' => $sync['created'],
			'sync_updated' => $sync['updated'],
		);

		$this->logResultToFile($output);

		return $output;
	}

	/**
	 * Poll FastAPI /jobs/{job_id} until completion/error/cancelled.
	 *
	 * @param array $job Full internal queue job row
	 * @param string $fastapi_job_id FastAPI job identifier
	 * @return array Normalized result [issues => [], raw_data => mixed]
	 * @throws Exception
	 */
	private function pollFastApiReviewResult($job, $fastapi_job_id)
	{
		$this->ci->load->library('DataUtils');
		$this->ci->load->model('Job_queue_model');

		$poll_interval_ms = 2500;
		$max_wait_ms = 10 * 60 * 1000; // 10 minutes
		$start_at = (int) round(microtime(true) * 1000);

		while (true) {
			// User cancelled from UI/internal API
			if ($this->isLocalJobCancelled($job['id'])) {
				$this->tryCancelFastApiJob($fastapi_job_id);
				throw new Exception('Assessment cancelled by user');
			}

			$status_response = $this->ci->datautils->get_job_status($fastapi_job_id);
			$status_code = isset($status_response['status_code']) ? (int)$status_response['status_code'] : 0;
			$body = isset($status_response['response']) ? $status_response['response'] : array();
			$status = strtolower((string)(is_array($body) && isset($body['status']) ? $body['status'] : ''));

			if ($status_code >= 400) {
				throw new Exception(
					"FastAPI review status failed ({$status_code}): " . $this->stringifyErrorDetail($body)
				);
			}

			if ($status === 'done' || $status === 'completed') {
				$issues = array();
				if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
					$issues = $body['data'];
				}
				return array(
					'issues' => $issues,
					'raw_data' => $body
				);
			}

			if ($status === 'error' || $status === 'failed') {
				$error_message = is_array($body) && isset($body['error'])
					? $body['error']
					: $this->stringifyErrorDetail($body);
				throw new Exception("FastAPI review job failed: " . $error_message);
			}

			if ($status === 'cancelled' || $status === 'canceled') {
				throw new Exception("FastAPI review job was cancelled");
			}

			$now = (int) round(microtime(true) * 1000);
			if (($now - $start_at) > $max_wait_ms) {
				throw new Exception("FastAPI review job timed out after " . (int)($max_wait_ms / 1000) . " seconds");
			}

			$this->ci->load->library('Worker_heartbeat');
			Worker_heartbeat::touch();

			usleep($poll_interval_ms * 1000);
		}
	}

	/**
	 * Map assessment API severity (numeric) to project_issues severity (string)
	 *
	 * @param int|null $severity
	 * @return string
	 */
	private function mapSeverity($severity)
	{
		$map = array(1 => 'low', 2 => 'medium', 3 => 'high', 4 => 'critical', 5 => 'critical');
		if ($severity !== null && isset($map[(int) $severity])) {
			return $map[(int) $severity];
		}
		return 'medium';
	}

	/**
	 * Flatten assessment issues (API may return array of arrays at any depth)
	 *
	 * @param array $issues
	 * @return array Flat list of issue objects (associative arrays with detected_issue)
	 */
	private function flattenAssessmentIssues($issues)
	{
		$flat = array();
		if (!is_array($issues)) {
			return $flat;
		}
		foreach ($issues as $item) {
			$item = is_object($item) ? (array) $item : $item;
			if (!is_array($item)) {
				continue;
			}
			if (isset($item['detected_issue']) && array_key_exists('detected_issue', $item)) {
				$flat[] = $item;
				continue;
			}
			// Nested array (e.g. [[ {...}, {...} ]]) – recurse
			$nested = $this->flattenAssessmentIssues($item);
			foreach ($nested as $sub) {
				$flat[] = $sub;
			}
		}
		return $flat;
	}

	/**
	 * Append a line to metadata_assessment.log with [sync] prefix for debugging
	 *
	 * @param string $message
	 */
	private function logSyncToFile($message)
	{
		$logs_dir = realpath(APPPATH . '../logs');
		if ($logs_dir === false || !is_dir($logs_dir)) {
			$logs_dir = APPPATH . '../logs';
			if (!is_dir($logs_dir)) {
				@mkdir($logs_dir, 0755, true);
			}
		}
		$log_file = rtrim($logs_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'metadata_assessment.log';
		$line = date('Y-m-d H:i:s') . ' [sync] ' . $message . "\n";
		@file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
	}

	/** Return log file path for debugging (same as logSyncToFile) */
	private function getSyncLogPath()
	{
		$logs_dir = realpath(APPPATH . '../logs');
		if ($logs_dir === false || !is_dir($logs_dir)) {
			$logs_dir = APPPATH . '../logs';
		}
		return rtrim($logs_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'metadata_assessment.log';
	}

	/**
	 * Sync assessment result issues to project_issues: create or update by title match
	 *
	 * @param int|null $project_id
	 * @param array $issues_raw Raw issues from assessment API
	 * @param array $job Full job row (for user_id)
	 * @return array ['created' => int, 'updated' => int]
	 */
	private function syncAssessmentIssuesToProject($project_id, $issues_raw, $job)
	{
		$created = 0;
		$updated = 0;
		if ($project_id === null || empty($project_id)) {
			$this->logSyncToFile('skip: project_id empty or null');
			return array('created' => $created, 'updated' => $updated);
		}

		$raw_count = is_array($issues_raw) ? count($issues_raw) : 0;
		$this->logSyncToFile("start project_id={$project_id} raw_issues_count={$raw_count}");

		$this->ci->load->model('Project_issues_model');
		$model = $this->ci->Project_issues_model;
		$user_id = isset($job['user_id']) ? (int) $job['user_id'] : null;
		$this->logSyncToFile('user_id=' . ($user_id ?: 'null'));

		$flat = $this->flattenAssessmentIssues($issues_raw);
		$flat_count = count($flat);
		$this->logSyncToFile("flattened issues count={$flat_count}");
		if ($flat_count === 0 && $raw_count > 0) {
			$this->logSyncToFile('raw_structure_sample=' . json_encode(
				is_array($issues_raw) && isset($issues_raw[0]) ? $issues_raw[0] : $issues_raw,
				JSON_UNESCAPED_UNICODE
			));
		}

		foreach ($flat as $index => $issue) {
			$title = isset($issue['detected_issue']) ? trim((string) $issue['detected_issue']) : '';
			if ($title === '') {
				$this->logSyncToFile("issue[{$index}] skip: no detected_issue keys=" . implode(',', array_keys($issue)));
				continue;
			}
			$current_meta = isset($issue['current_metadata']) && is_array($issue['current_metadata']) ? $issue['current_metadata'] : array();
			$suggested_meta = isset($issue['suggested_metadata']) && is_array($issue['suggested_metadata']) ? $issue['suggested_metadata'] : array();
			$field_path = '';
			if (!empty($current_meta)) {
				$keys = array_keys($current_meta);
				$field_path = (string) reset($keys);
			}
			$category = isset($issue['issue_category']) ? trim((string) $issue['issue_category']) : '';
			$severity = $this->mapSeverity(isset($issue['issue_severity']) ? $issue['issue_severity'] : null);

			$data = array(
				'project_id'        => $project_id,
				'title'             => $title,
				'description'       => $title,
				'category'          => $category !== '' ? $category : null,
				'field_path'        => $field_path !== '' ? $field_path : null,
				'severity'          => $severity,
				'status'            => 'open',
				'current_metadata'  => $current_meta,
				'suggested_metadata'=> $suggested_meta,
				'source'            => 'metadata_assessment',
			);
			if ($user_id) {
				$data['created_by'] = $user_id;
			}

			$existing = $model->get_by_project_and_title($project_id, $title);
			try {
				if ($existing) {
					$update_data = array(
						'category'           => $data['category'],
						'field_path'         => $data['field_path'],
						'severity'           => $data['severity'],
						'current_metadata'   => $data['current_metadata'],
						'suggested_metadata' => $data['suggested_metadata'],
					);
					$model->update($existing['id'], $update_data);
					$updated++;
					$this->logSyncToFile("issue[{$index}] updated id={$existing['id']} title=" . substr($title, 0, 60));
				} else {
					$model->create($data);
					$created++;
					$this->logSyncToFile("issue[{$index}] created title=" . substr($title, 0, 60));
				}
			} catch (Exception $e) {
				$this->logSyncToFile("issue[{$index}] error title=" . substr($title, 0, 60) . ' err=' . $e->getMessage());
				log_message('error', 'MetadataAssessmentResultJob sync issue: ' . $e->getMessage());
			}
		}
		$this->logSyncToFile("done created={$created} updated={$updated}");
		return array('created' => $created, 'updated' => $updated);
	}

	/**
	 * Append the assessment API output to a log file in the application root logs folder
	 *
	 * @param array $output The result array (event_id, project_id, issues, raw_data, completed_at)
	 */
	private function logResultToFile($output)
	{
		$logs_dir = realpath(APPPATH . '../logs');
		if ($logs_dir === false || !is_dir($logs_dir)) {
			$logs_dir = APPPATH . '../logs';
			if (!is_dir($logs_dir)) {
				@mkdir($logs_dir, 0755, true);
			}
		}
		$log_file = rtrim($logs_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'metadata_assessment.log';
		$line = date('Y-m-d H:i:s') . ' fastapi_job_id=' . (isset($output['fastapi_job_id']) ? $output['fastapi_job_id'] : '')
			. ' project_id=' . (isset($output['project_id']) ? $output['project_id'] : '')
			. "\n" . json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
			. str_repeat('-', 80) . "\n";
		@file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Check whether the local queue job has been cancelled by user.
	 *
	 * @param int $job_id
	 * @return bool
	 */
	private function isLocalJobCancelled($job_id)
	{
		$latest = $this->ci->Job_queue_model->get_by_id($job_id);
		return (is_array($latest) && isset($latest['status']) && $latest['status'] === 'cancelled');
	}

	/**
	 * Best-effort cancellation for FastAPI job.
	 *
	 * @param string $fastapi_job_id
	 */
	private function tryCancelFastApiJob($fastapi_job_id)
	{
		try {
			$this->ci->datautils->cancel_job($fastapi_job_id);
		} catch (Exception $e) {
			log_message('debug', 'MetadataAssessmentResultJob: FastAPI cancel failed: ' . $e->getMessage());
		}
	}

	/**
	 * Convert response body into a concise error string.
	 *
	 * @param mixed $body
	 * @return string
	 */
	private function stringifyErrorDetail($body)
	{
		if (is_array($body)) {
			if (isset($body['detail']) && is_string($body['detail'])) {
				return $body['detail'];
			}
			if (isset($body['error']) && is_string($body['error'])) {
				return $body['error'];
			}
			return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		if (is_string($body) && trim($body) !== '') {
			return $body;
		}
		return 'Unknown error';
	}

	/**
	 * Open the result URL as a stream, parse SSE events, return the first non-heartbeat result
	 *
	 * @param string $url Full URL (base_url/event_id)
	 * @param int $timeout_seconds Read timeout in seconds
	 * @return array Parsed result (e.g. ['issues' => [...], 'raw_data' => ...])
	 * @throws Exception If stream fails or no result event is received
	 */
	private function fetchResultStream($url, $timeout_seconds = 600)
	{
		$headers = array('Accept' => 'text/event-stream');
		$api_key = $this->ci->config->item('api_key', 'metadata_assessment');
		$api_key_header = $this->ci->config->item('api_key_header', 'metadata_assessment');
		if (!empty($api_key) && !empty($api_key_header)) {
			$headers[$api_key_header] = $api_key;
		}

		$client = new Client();

		try {
			$response = $client->get($url, array(
				RequestOptions::HEADERS => $headers,
				RequestOptions::CONNECT_TIMEOUT => 30,
				RequestOptions::TIMEOUT => $timeout_seconds,
				RequestOptions::STREAM => true,
			));
		} catch (GuzzleException $e) {
			throw new Exception("Assessment result stream failed: " . $e->getMessage());
		}

		$buffer = '';
		$result_data = null;
		$body = $response->getBody();

		while (!$body->eof()) {
			$chunk = $body->read(8192);
			if ($chunk === '') {
				break;
			}
			$buffer .= $chunk;
			// Parse complete SSE events (delimited by double newline)
			while (($pos = strpos($buffer, "\n\n")) !== false) {
				$event_block = substr($buffer, 0, $pos);
				$buffer = substr($buffer, $pos + 2);
				$parsed = $this->parseSseEvent($event_block);
				if ($parsed !== null) {
					$result_data = $parsed;
					break 2;
				}
			}
		}

		$body->close();

		if ($result_data === null) {
			throw new Exception("Assessment result stream ended without a result event");
		}

		return $result_data;
	}

	/**
	 * Parse one SSE event block (event type + data). Returns null for heartbeat or unknown; array for result.
	 *
	 * @param string $block One event block (lines of "event: x" and "data: y")
	 * @return array|null Decoded result or null if heartbeat / skip
	 */
	private function parseSseEvent($block)
	{
		$event_type = null;
		$data_line = null;

		foreach (explode("\n", $block) as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			if (stripos($line, 'event:') === 0) {
				$event_type = trim(substr($line, 6));
			}
			if (stripos($line, 'data:') === 0) {
				$data_line = trim(substr($line, 5));
			}
		}

		// Ignore heartbeat (event: heartbeat, data: null)
		if (strtolower($event_type) === 'heartbeat' && ($data_line === '' || strtolower($data_line) === 'null')) {
			return null;
		}

		// No data to parse
		if ($data_line === '' || strtolower($data_line) === 'null') {
			return null;
		}

		$decoded = json_decode($data_line, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return array('issues' => array(), 'raw_data' => $data_line);
		}

		// Normalize: API may return { "data": [ ... issues ... ] } or direct array
		if (isset($decoded['data']) && is_array($decoded['data'])) {
			return array('issues' => $decoded['data'], 'raw_data' => $decoded);
		}
		if (isset($decoded['issues']) && is_array($decoded['issues'])) {
			return array('issues' => $decoded['issues'], 'raw_data' => $decoded);
		}
		if (is_array($decoded)) {
			return array('issues' => $decoded, 'raw_data' => $decoded);
		}

		return array('issues' => array(), 'raw_data' => $decoded);
	}
}
