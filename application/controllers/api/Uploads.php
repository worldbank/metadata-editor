<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

/**
 * Resumable File Uploads
 * 
 */
class Uploads extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('Resumable_upload', null, 'uploader');		
		$this->is_authenticated_or_die();
	}
	
	/**
	 * Initialize a new resumable upload
	 * 
	 * POST /api/uploads/init
	 * 
	 * Request body:
	 * {
	 *   "filename": "large-file.zip",
	 *   "total_size": 10737418240,
	 *   "total_chunks": 1024,
	 *   "chunk_size": 10485760,
	 *   "metadata": {} // optional
	 * }
	 */
	function init_post()
	{
		try {
			$input = $this->raw_json_input();
			
			if (empty($input)) {
				$input = $this->input->post(null, true);
			}
			
			// Validate required fields
			if (empty($input['filename'])) {
				throw new Exception("INVALID_INPUT: filename is required");
			}
			
			if (empty($input['total_size']) || !is_numeric($input['total_size'])) {
				throw new Exception("INVALID_INPUT: total_size is required and must be numeric");
			}
			
			if (empty($input['total_chunks']) || !is_numeric($input['total_chunks'])) {
				throw new Exception("INVALID_INPUT: total_chunks is required and must be numeric");
			}
			
			if (empty($input['chunk_size']) || !is_numeric($input['chunk_size'])) {
				throw new Exception("INVALID_INPUT: chunk_size is required and must be numeric");
			}
			
			// Validate consistency: total_chunks should match calculated value
			$total_size = (int)$input['total_size'];
			$chunk_size = (int)$input['chunk_size'];
			$provided_total_chunks = (int)$input['total_chunks'];
			$calculated_total_chunks = (int)ceil($total_size / $chunk_size);
			
			if ($provided_total_chunks != $calculated_total_chunks) {
				throw new Exception("INVALID_INPUT: total_chunks ($provided_total_chunks) doesn't match calculated value ($calculated_total_chunks) based on total_size ($total_size) and chunk_size ($chunk_size). Expected: ceil(total_size / chunk_size) = ceil($total_size / $chunk_size) = $calculated_total_chunks");
			}
			
			$metadata = isset($input['metadata']) ? $input['metadata'] : array();
			
			// Initialize upload
			$upload_id = $this->uploader->init_upload(
				$input['filename'],
				$total_size,
				$provided_total_chunks,
				$chunk_size,
				$metadata,
				$this->get_api_user_id()
			);
			
			$response = array(
				'status' => 'success',
				'upload_id' => $upload_id,
				'upload_url' => site_url('api/uploads/chunk/' . $upload_id),
				'status_url' => site_url('api/uploads/status/' . $upload_id),
				'max_chunk_size' => $this->uploader->get_max_chunk_size()
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * Upload a chunk
	 * 
	 * POST /api/uploads/chunk/{upload_id}
	 * POST /api/uploads/chunk (for auto-init with first chunk)
	 * 
	 * Parameters can be provided via (priority order):
	 * 1. HTTP Headers: X-Upload-Chunk-Number, X-Upload-Filename, X-Upload-Total-Size, etc.
	 * 2. JSON Body: {"chunk_number": 0, "filename": "...", ...} (if Content-Type: application/json)
	 * 3. POST Data: multipart/form-data or application/x-www-form-urlencoded
	 * 
	 * Chunk data can be sent as:
	 * - Raw binary: Content-Type: application/octet-stream (body contains binary data)
	 * - Multipart: Content-Type: multipart/form-data (chunk in 'chunk' field)
	 * 
	 * If upload_id is not provided and chunk_number is 0, will auto-initialize the upload
	 */
	function chunk_post($upload_id = null)
	{
		try {
			$chunk_number = $this->get_parameter('chunk_number', 'X-Upload-Chunk-Number');
			
			if ($chunk_number === null || $chunk_number === '' || !is_numeric($chunk_number)) {
				throw new Exception("INVALID_INPUT: chunk_number is required");
			}
			
			$chunk_number = (int)$chunk_number;
			
			// Get chunk_size from client (required for validation)
			$chunk_size = $this->get_parameter('chunk_size', 'X-Upload-Chunk-Size');
			
			if ($chunk_size === null || $chunk_size === '' || !is_numeric($chunk_size)) {
				throw new Exception("INVALID_INPUT: chunk_size is required");
			}
			
			$chunk_size = (int)$chunk_size;
			
			// Auto-initialize if upload_id not provided and this is the first chunk
			if (empty($upload_id) && $chunk_number == 0) {
				$upload_id = $this->auto_init_upload();
			}
			
			if (empty($upload_id)) {
				throw new Exception("INVALID_INPUT: upload_id is required (or provide init parameters for first chunk)");
			}

			$this->uploader->assert_upload_access($upload_id, $this->get_api_user_id());
			
			// Read chunk data from request body
			// Check Content-Type to determine how to read chunk data
			$content_type = $this->input->server('CONTENT_TYPE');
			$is_multipart = $content_type && strpos(strtolower($content_type), 'multipart/form-data') !== false;
			$is_json = $content_type && strpos(strtolower($content_type), 'application/json') !== false;
			
			$chunk_data = null;
			
			if ($is_multipart) {
				// Multipart: chunk is in $_FILES
				if (isset($_FILES['chunk']) && is_uploaded_file($_FILES['chunk']['tmp_name'])) {
					$chunk_data = file_get_contents($_FILES['chunk']['tmp_name']);
				}
			} elseif (!$is_json) {
				// Raw binary: read from raw_input_stream (application/octet-stream)
				$chunk_data = $this->input->raw_input_stream;
			}
			
			if (empty($chunk_data)) {
				throw new Exception("INVALID_INPUT: chunk data is required");
			}
			
			// Upload chunk
			$result = $this->uploader->upload_chunk($upload_id, $chunk_number, $chunk_data, $chunk_size);
			
			$response = array(
				'status' => 'success',
				'upload_status' => $result['status'],
				'uploaded_chunks' => $result['uploaded_chunks'],
				'total_chunks' => $result['total_chunks'],
				'progress' => round($result['progress'] * 100, 2)
			);
			
			// If complete, add file info
			if ($result['status'] == 'complete') {
				$metadata = $this->uploader->get_upload_metadata($upload_id);
				$final_file = $this->uploader->get_final_file_path($upload_id);
				
				$response['file_path'] = $final_file;
				$response['filename'] = $metadata['filename'];
				$response['file_size'] = filesize($final_file);
				$response['download_url'] = site_url('api/uploads/download/' . $upload_id);
			}
			
			// If this was auto-initialized, include upload_id in response
			if ($chunk_number == 0) {
				$response['upload_id'] = $upload_id;
				$response['status_url'] = site_url('api/uploads/status/' . $upload_id);
			}
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * Get upload status
	 * 
	 * GET /api/uploads/status/{upload_id}
	 */
	function status_get($upload_id = null)
	{
		try {
			if (empty($upload_id)) {
				throw new Exception("INVALID_INPUT: upload_id is required");
			}

			$metadata = $this->uploader->assert_upload_access($upload_id, $this->get_api_user_id());
			
			$uploaded_chunks = $this->uploader->get_uploaded_chunks($upload_id);
			$progress = count($uploaded_chunks) / $metadata['total_chunks'];
			
			$response = array(
				'status' => 'success',
				'upload_id' => $upload_id,
				'filename' => $metadata['filename'],
				'total_size' => $metadata['total_size'],
				'total_chunks' => $metadata['total_chunks'],
				'uploaded_chunks' => $uploaded_chunks,
				'progress' => round($progress * 100, 2),
				'upload_status' => $metadata['status'],
				'created_at' => $metadata['created_at'],
				'updated_at' => $metadata['updated_at']
			);
			
			if ($metadata['status'] == 'completed') {
				$final_file = $this->uploader->get_final_file_path($upload_id);
				
				$response['completed_at'] = $metadata['completed_at'];
				$response['file_path'] = $final_file;
				$response['file_size'] = filesize($final_file);
				$response['download_url'] = site_url('api/uploads/download/' . $upload_id);
			}
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * Download completed file
	 * 
	 * GET /api/uploads/download/{upload_id}
	 */
	function download_get($upload_id = null)
	{
		try {
			if (empty($upload_id)) {
				throw new Exception("INVALID_INPUT: upload_id is required");
			}

			$metadata = $this->uploader->assert_upload_access($upload_id, $this->get_api_user_id());
			
			if ($metadata['status'] != 'completed') {
				throw new Exception("UPLOAD_NOT_COMPLETED");
			}
			
			$final_file = $this->uploader->get_final_file_path($upload_id);
			
			if (!file_exists($final_file)) {
				throw new Exception("FILE_NOT_FOUND");
			}
			
			// Set headers for file download
			$this->load->helper('download');
			force_download2($final_file);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * Cancel/Delete upload
	 * 
	 * DELETE /api/uploads/{upload_id}
	 */
	function index_delete($upload_id = null)
	{
		try {
			if (empty($upload_id)) {
				throw new Exception("INVALID_INPUT: upload_id is required");
			}

			$this->uploader->assert_upload_access($upload_id, $this->get_api_user_id());
			
			$result = $this->uploader->delete_upload($upload_id);
			
			if (!$result) {
				throw new Exception("FAILED_TO_DELETE_UPLOAD");
			}
			
			$response = array(
				'status' => 'success',
				'message' => 'Upload deleted successfully'
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

    //alias for index_delete
    function delete_delete($upload_id = null){
        return $this->index_delete($upload_id);
    }

	/**
	 * Cleanup expired uploads
	 * 
	 * POST /api/uploads/cleanup
	 * 
	 * Removes expired incomplete uploads based on expiry_hours configuration
	 */
	function cleanup_post()
	{
		try {
			$stats = $this->uploader->cleanup_expired_uploads();
			
			$response = array(
				'status' => 'success',
				'checked' => $stats['checked'],
				'deleted' => $stats['deleted'],
				'errors' => $stats['errors'],
				'message' => 'Cleanup completed successfully'
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * List active uploads (optional)
	 * 
	 * GET /api/uploads
	 */
	function index_get()
	{
		try {
			$this->is_admin_or_die();
			$uploads = $this->uploader->list_uploads();
			
			$response = array(
				'status' => 'success',
				'total' => count($uploads),
				'uploads' => $uploads
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * Get upload limits and configuration
	 * 
	 * GET /api/uploads/limits
	 * 
	 * Returns maximum file size, chunk size limits, and recommended settings
	 */
	function limits_get()
	{
		try {
			$this->config->load('uploads');
			
			$max_chunk_size = $this->uploader->get_max_chunk_size();
			$recommended_chunk_size = $this->config->item('resumable_upload_chunk_size');
			$max_file_size = $this->config->item('resumable_upload_max_size');
			
			$response = array(
				'status' => 'success',
				'max_chunk_size' => $max_chunk_size,
				'recommended_chunk_size' => $recommended_chunk_size ?: $max_chunk_size,
				'max_file_size' => $max_file_size ?: 0, // 0 means unlimited
				'php_upload_max_filesize' => ini_get('upload_max_filesize'),
				'php_post_max_size' => ini_get('post_max_size')
			);
			
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$output = array(
				'status' => 'error',
				'error_code' => $this->get_error_code($e->getMessage()),
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * Extract error code from exception message
	 * 
	 * @param string $message
	 * @return string
	 */
	private function get_error_code($message)
	{
		if (strpos($message, ':') !== false) {
			return trim(explode(':', $message)[0]);
		}
		
		// Map common messages to error codes
		$error_map = array(
			'UPLOAD_NOT_FOUND' => 'UPLOAD_NOT_FOUND',
			'UPLOAD_COMPLETED' => 'UPLOAD_COMPLETED',
			'UPLOAD_CANCELLED' => 'UPLOAD_CANCELLED',
			'CHUNK_OUT_OF_RANGE' => 'CHUNK_OUT_OF_RANGE',
			'CHUNK_SIZE_MISMATCH' => 'CHUNK_SIZE_MISMATCH',
			'FILE_TYPE_NOT_ALLOWED' => 'FILE_TYPE_NOT_ALLOWED',
			'FILE_TOO_LARGE' => 'FILE_TOO_LARGE',
			'INVALID_INPUT' => 'INVALID_INPUT'
		);
		
		foreach ($error_map as $code => $pattern) {
			if (stripos($message, $code) !== false) {
				return $code;
			}
		}
		
		return 'UNKNOWN_ERROR';
	}
	
	/**
	 * Get parameter value with priority: Headers → Raw JSON → POST
	 * 
	 * @param string $param_name Parameter name (for JSON/POST)
	 * @param string $header_name Header name (e.g., 'X-Upload-Filename')
	 * @return mixed Parameter value or null if not found
	 */
	private function get_parameter($param_name, $header_name = null)
	{
		$value = null;
		
		// Priority 1: HTTP Headers
		if ($header_name) {
			$header_value = $this->input->server('HTTP_' . str_replace('-', '_', strtoupper($header_name)));
			if ($header_value !== null && $header_value !== '') {
				$value = $header_value;
			}
		}
		
		// Priority 2: Raw JSON body (if Content-Type is application/json)
		// Note: Only parse JSON if Content-Type indicates JSON, to avoid interfering with binary data
		if ($value === null) {
			$content_type = $this->input->server('CONTENT_TYPE');
			if ($content_type && strpos(strtolower($content_type), 'application/json') !== false) {
				try {
					$json_input = $this->raw_json_input();
					if ($json_input && isset($json_input[$param_name])) {
						$value = $json_input[$param_name];
					}
				} catch (Exception $e) {
					// If JSON parsing fails, continue to next priority
				}
			}
		}
		
		// Priority 3: POST data (works with multipart/form-data and application/x-www-form-urlencoded)
		if ($value === null) {
			$post_value = $this->input->post($param_name);
			if ($post_value !== null && $post_value !== '') {
				$value = $post_value;
			}
		}
		
		return $value;
	}
	
	/**
	 * Auto-initialize upload from chunk request parameters
	 * 
	 * Parameters read from: Headers → Raw JSON → POST
	 * 
	 * @return string upload_id
	 */
	private function auto_init_upload()
	{
		$filename = $this->get_parameter('filename', 'X-Upload-Filename');
		$total_size = $this->get_parameter('total_size', 'X-Upload-Total-Size');
		$total_chunks = $this->get_parameter('total_chunks', 'X-Upload-Total-Chunks');
		$chunk_size = $this->get_parameter('chunk_size', 'X-Upload-Chunk-Size');
		
		// Validate required fields
		if (empty($filename)) {
			throw new Exception("INVALID_INPUT: filename is required for auto-initialization");
		}
		
		if (empty($total_size) || !is_numeric($total_size)) {
			throw new Exception("INVALID_INPUT: total_size is required and must be numeric for auto-initialization");
		}
		
		if (empty($total_chunks) || !is_numeric($total_chunks)) {
			throw new Exception("INVALID_INPUT: total_chunks is required and must be numeric for auto-initialization");
		}
		
		if (empty($chunk_size) || !is_numeric($chunk_size)) {
			throw new Exception("INVALID_INPUT: chunk_size is required and must be numeric for auto-initialization");
		}
		
		// Validate consistency: total_chunks should match calculated value
		$total_size_int = (int)$total_size;
		$chunk_size_int = (int)$chunk_size;
		$provided_total_chunks = (int)$total_chunks;
		$calculated_total_chunks = (int)ceil($total_size_int / $chunk_size_int);
		
		if ($provided_total_chunks != $calculated_total_chunks) {
			throw new Exception("INVALID_INPUT: total_chunks ($provided_total_chunks) doesn't match calculated value ($calculated_total_chunks) based on total_size ($total_size_int) and chunk_size ($chunk_size_int). Expected: ceil(total_size / chunk_size) = ceil($total_size_int / $chunk_size_int) = $calculated_total_chunks");
		}
		
		$metadata = array();
		$metadata_input = $this->get_parameter('metadata', 'X-Upload-Metadata');
		
		if (!empty($metadata_input)) {
			if (is_string($metadata_input)) {
				$metadata = json_decode($metadata_input, true);
				if ($metadata === null) {
					$metadata = array();
				}
			} elseif (is_array($metadata_input)) {
				$metadata = $metadata_input;
			}
		}
		
		// Initialize upload
		$upload_id = $this->uploader->init_upload(
			$filename,
			$total_size_int,
			$provided_total_chunks,
			$chunk_size_int,
			$metadata,
			$this->get_api_user_id()
		);
		
		return $upload_id;
	}

    function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		return parent::_auth_override_check();
	}
}

