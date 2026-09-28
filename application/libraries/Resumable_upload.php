<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Resumable Upload Library
 * 
 * Handles chunked file uploads with resume capability
 * Uses file-based metadata storage
 */
class Resumable_upload {
	
	private $temp_path;
	private $max_size;
	private $chunk_size;
	private $expiry_hours;
	private $allowed_types;
	private $max_chunk_size;
	
	public function __construct()
	{
		$CI =& get_instance();
		$CI->config->load('uploads');
		
		$this->temp_path = $CI->config->item('resumable_upload_temp_path');
		$this->max_size = $CI->config->item('resumable_upload_max_size');
		$this->chunk_size = $CI->config->item('resumable_upload_chunk_size');
		$this->expiry_hours = $CI->config->item('resumable_upload_expiry_hours');
		$this->allowed_types = $CI->config->item('allowed_resource_types');
		
		// Calculate maximum chunk size based on PHP limits
		$this->max_chunk_size = $this->get_php_max_chunk_size();
		
		if (!$this->temp_path || trim($this->temp_path) == '') {
			throw new Exception("RESUMABLE_UPLOAD_TEMP_PATH_NOT_SET");
		}
		
		// If not absolute path, use relative to FCPATH
		if (!file_exists($this->temp_path)) {
			$this->temp_path = FCPATH . $this->temp_path;
		}
		
		$this->temp_path = unix_path($this->temp_path);
		
		// Ensure temp directory exists
		if (!file_exists($this->temp_path)) {
			if (!@mkdir($this->temp_path, 0755, true)) {
				throw new Exception("FAILED_TO_CREATE_TEMP_DIRECTORY: " . $this->temp_path);
			}
		}
	}
	
	/**
	 * Initialize a new upload session
	 * 
	 * @param string $filename Original filename
	 * @param int $total_size Total file size in bytes
	 * @param int $total_chunks Total number of chunks
	 * @param int $chunk_size Size of each chunk in bytes
	 * @param array $metadata Optional metadata
	 * @return string upload_id
	 */
	public function init_upload($filename, $total_size, $total_chunks, $chunk_size, $metadata = array())
	{
		// Validate inputs
		if (empty($filename)) {
			throw new Exception("INVALID_INPUT: filename is required");
		}
		
		if ($total_size <= 0) {
			throw new Exception("INVALID_INPUT: total_size must be positive");
		}
		
		if ($total_chunks <= 0) {
			throw new Exception("INVALID_INPUT: total_chunks must be positive");
		}
		
		if ($chunk_size <= 0) {
			throw new Exception("INVALID_INPUT: chunk_size must be positive");
		}

		$total_size = (int)$total_size;
		$total_chunks = (int)$total_chunks;
		$chunk_size = (int)$chunk_size;
		$calculated_total_chunks = (int)ceil($total_size / $chunk_size);
		if ($total_chunks !== $calculated_total_chunks) {
			throw new Exception("INVALID_INPUT: total_chunks ($total_chunks) doesn't match calculated value ($calculated_total_chunks) based on total_size ($total_size) and chunk_size ($chunk_size)");
		}
		
		// Validate chunk size against PHP limits
		if ($chunk_size > $this->max_chunk_size) {
			throw new Exception("CHUNK_SIZE_EXCEEDS_PHP_LIMIT: Maximum chunk size is " . $this->format_bytes($this->max_chunk_size) . " (PHP post_max_size: " . ini_get('post_max_size') . ", upload_max_filesize: " . ini_get('upload_max_filesize') . ")");
		}
		
		// Validate file size limit
		if ($this->max_size > 0 && $total_size > $this->max_size) {
			throw new Exception("FILE_TOO_LARGE: Maximum size is " . $this->max_size . " bytes");
		}
		
		// Store original filename before sanitization
		$original_filename = basename($filename);
		
		// Sanitize filename for safe storage
		$filename = $this->sanitize_filename($filename);
		
		// Generate upload ID (UUID v4)
		$upload_id = $this->generate_upload_id();
		
		// Create upload directory structure
		$upload_path = $this->get_upload_path($upload_id);
		
		// Ensure filename is unique (handle collisions)
		$filename = $this->ensure_unique_filename($filename, $upload_path);
		
		// Validate file type after final filename is determined
		if (!$this->validate_file_type($filename, $metadata)) {
			throw new Exception("FILE_TYPE_NOT_ALLOWED");
		}
		
		$chunks_dir = unix_path($upload_path . '/chunks');
		
		if (!@mkdir($chunks_dir, 0755, true)) {
			throw new Exception("FAILED_TO_CREATE_UPLOAD_DIRECTORY");
		}
		
		// Create metadata
		$metadata_data = array(
			'upload_id' => $upload_id,
			'filename' => $filename,
			'original_filename' => $original_filename,
			'total_size' => (int)$total_size,
			'total_chunks' => (int)$total_chunks,
			'chunk_size' => (int)$chunk_size,
			'created_at' => time(),
			'updated_at' => time(),
			'completed_at' => null,
			'status' => 'in_progress',
			'uploaded_chunks' => array(),
			'uploaded_chunk_sizes' => array(),
			'metadata' => $metadata
		);
		
		// Save metadata
		$this->save_upload_metadata($upload_id, $metadata_data);
		
		// Automatic cleanup of expired uploads (incremental)
		$this->cleanup_expired_uploads_auto(5);
		
		return $upload_id;
	}
	
	/**
	 * Get upload metadata
	 * 
	 * @param string $upload_id
	 * @return array|false Metadata array or false if not found
	 */
	public function get_upload_metadata($upload_id)
	{
		$metadata_path = $this->get_metadata_path($upload_id);
		
		if (!file_exists($metadata_path)) {
			return false;
		}
		
		$metadata_json = @file_get_contents($metadata_path);
		if ($metadata_json === false) {
			return false;
		}
		
		$metadata = @json_decode($metadata_json, true);
		if ($metadata === null) {
			return false;
		}
		
		return $metadata;
	}
	
	/**
	 * Save upload metadata
	 * 
	 * @param string $upload_id
	 * @param array $metadata
	 * @return bool
	 */
	public function save_upload_metadata($upload_id, $metadata)
	{
		$metadata_path = $this->get_metadata_path($upload_id);
		$upload_path = dirname($metadata_path);
		
		// Ensure directory exists
		if (!file_exists($upload_path)) {
			if (!@mkdir($upload_path, 0755, true)) {
				throw new Exception("FAILED_TO_CREATE_UPLOAD_DIRECTORY");
			}
		}
		
		$metadata_json = json_encode($metadata, JSON_PRETTY_PRINT);
		
		// Atomic write: write to temp file, then rename
		$temp_file = $metadata_path . '.tmp';
		if (@file_put_contents($temp_file, $metadata_json) === false) {
			throw new Exception("FAILED_TO_WRITE_METADATA");
		}
		
		if (!@rename($temp_file, $metadata_path)) {
			@unlink($temp_file);
			throw new Exception("FAILED_TO_SAVE_METADATA");
		}
		
		return true;
	}
	
	/**
	 * Upload a chunk
	 * 
	 * @param string $upload_id
	 * @param int $chunk_number
	 * @param string $chunk_data Binary chunk data
	 * @param int $client_chunk_size
	 * @return array Status information
	 */
	public function upload_chunk($upload_id, $chunk_number, $chunk_data, $client_chunk_size = null)
	{
		// Load metadata
		$metadata = $this->get_upload_metadata($upload_id);
		if (!$metadata) {
			throw new Exception("UPLOAD_NOT_FOUND");
		}
		
		// Check status
		if ($metadata['status'] == 'completed') {
			throw new Exception("UPLOAD_COMPLETED");
		}
		
		if ($metadata['status'] == 'cancelled') {
			throw new Exception("UPLOAD_CANCELLED");
		}
		
		// Validate chunk number
		if ($chunk_number < 0 || $chunk_number >= $metadata['total_chunks']) {
			throw new Exception("CHUNK_OUT_OF_RANGE");
		}
		
		// Get actual size from binary data
		$actual_size = strlen($chunk_data);
		$expected_size = $this->expected_chunk_byte_size($metadata, $chunk_number);

		if ($expected_size <= 0) {
			throw new Exception("CHUNK_SIZE_MISMATCH: Invalid expected size " . $expected_size . " for chunk " . $chunk_number);
		}
		
		// Validate client-reported chunk size matches actual data size
		if ($client_chunk_size !== null) {
			if ((int)$client_chunk_size != $actual_size) {
				throw new Exception("CHUNK_SIZE_MISMATCH: Client reported " . $client_chunk_size . " bytes, but actual data is " . $actual_size . " bytes");
			}
		}

		// Non-final chunks must be exactly chunk_size; the last chunk must be the exact remainder.
		if ($actual_size !== $expected_size) {
			throw new Exception("CHUNK_SIZE_MISMATCH: Expected " . $expected_size . " bytes for chunk " . $chunk_number . ", got " . $actual_size);
		}
		
		// Append chunk into the assembly file at the correct byte offset (no end-of-upload combine pass).
		$this->append_chunk_to_assembly($upload_id, $metadata, $chunk_number, $chunk_data);
		
		// Update metadata
		if (!in_array($chunk_number, $metadata['uploaded_chunks'])) {
			$metadata['uploaded_chunks'][] = $chunk_number;
			sort($metadata['uploaded_chunks']);
		}

		if (!isset($metadata['uploaded_chunk_sizes']) || !is_array($metadata['uploaded_chunk_sizes'])) {
			$metadata['uploaded_chunk_sizes'] = array();
		}
		$metadata['uploaded_chunk_sizes'][(int)$chunk_number] = $actual_size;
		
		$metadata['updated_at'] = time();
		
		// Automatic cleanup of expired uploads (incremental)
		// Run cleanup on first chunk (chunk 0) to handle auto-init case
		// Also run periodically (every 10th chunk) to keep cleaning up
		if ($chunk_number == 0 || ($chunk_number % 10 == 0)) {
			$this->cleanup_expired_uploads_auto(5);
		}
		
		// Check if upload is complete
		$is_complete = count($metadata['uploaded_chunks']) == $metadata['total_chunks'];
		if ($is_complete) {
			$metadata['status'] = 'completed';
			$metadata['completed_at'] = time();
			$this->finalize_assembled_upload($upload_id, $metadata);
		}
		
		$this->save_upload_metadata($upload_id, $metadata);
		
		return array(
			'status' => $is_complete ? 'complete' : 'partial',
			'uploaded_chunks' => $metadata['uploaded_chunks'],
			'total_chunks' => $metadata['total_chunks'],
			'progress' => count($metadata['uploaded_chunks']) / $metadata['total_chunks']
		);
	}
	
	/**
	 * Get list of uploaded chunks
	 * 
	 * @param string $upload_id
	 * @return array Array of chunk numbers
	 */
	public function get_uploaded_chunks($upload_id)
	{
		$metadata = $this->get_upload_metadata($upload_id);
		if ($metadata && !empty($metadata['uploaded_chunks']) && is_array($metadata['uploaded_chunks'])) {
			$chunks = array_values(array_unique(array_map('intval', $metadata['uploaded_chunks'])));
			sort($chunks);
			return $chunks;
		}
		
		// Legacy uploads may only have on-disk chunk parts.
		$upload_path = $this->get_upload_path($upload_id);
		$chunks_dir = unix_path($upload_path . '/chunks');
		
		if (!file_exists($chunks_dir)) {
			return array();
		}
		
		$chunks = array();
		$files = @scandir($chunks_dir);
		
		if ($files === false) {
			return array();
		}
		
		foreach ($files as $file) {
			if (preg_match('/^chunk_(\d+)\.part$/', $file, $matches)) {
				$chunks[] = (int)$matches[1];
			}
		}
		
		sort($chunks);
		return $chunks;
	}
	
	/**
	 * Check if upload is complete
	 * 
	 * @param string $upload_id
	 * @return bool
	 */
	public function is_upload_complete($upload_id)
	{
		$metadata = $this->get_upload_metadata($upload_id);
		if (!$metadata) {
			return false;
		}
		
		if ($metadata['status'] === 'completed') {
			return true;
		}
		
		$uploaded_chunks = $this->get_uploaded_chunks($upload_id);
		
		return count($uploaded_chunks) == $metadata['total_chunks'];
	}
	
	/**
	 * Expected payload size for one chunk. Non-final chunks are exactly chunk_size;
	 * the last chunk is the remainder of total_size.
	 *
	 * @param array $metadata
	 * @param int $chunk_number
	 * @return int
	 */
	private function expected_chunk_byte_size($metadata, $chunk_number)
	{
		$chunk_size = (int)$metadata['chunk_size'];
		$total_chunks = (int)$metadata['total_chunks'];
		$total_size = (int)$metadata['total_size'];

		if ((int)$chunk_number === $total_chunks - 1) {
			return $total_size - ((int)$chunk_number * $chunk_size);
		}

		return $chunk_size;
	}

	/**
	 * Require the sum of accepted chunk payloads to match total_size.
	 * Skips uploads that predate uploaded_chunk_sizes.
	 *
	 * @param array $metadata
	 * @return void
	 */
	private function assert_recorded_chunk_bytes($metadata)
	{
		if (!isset($metadata['uploaded_chunk_sizes']) || !is_array($metadata['uploaded_chunk_sizes'])) {
			return;
		}

		$written = 0;
		$total_chunks = (int)$metadata['total_chunks'];
		$sizes = $metadata['uploaded_chunk_sizes'];

		for ($i = 0; $i < $total_chunks; $i++) {
			if (array_key_exists($i, $sizes)) {
				$written += (int)$sizes[$i];
			} elseif (array_key_exists((string)$i, $sizes)) {
				$written += (int)$sizes[(string)$i];
			} else {
				throw new Exception("FILE_SIZE_MISMATCH: Missing recorded size for chunk " . $i);
			}
		}

		if ($written !== (int)$metadata['total_size']) {
			throw new Exception("FILE_SIZE_MISMATCH: Expected " . $metadata['total_size'] . " bytes of chunk data, got " . $written);
		}
	}

	/**
	 * Append one chunk into the in-progress assembly file at the correct offset.
	 *
	 * @param string $upload_id
	 * @param array $metadata
	 * @param int $chunk_number
	 * @param string $chunk_data
	 * @return void
	 */
	private function append_chunk_to_assembly($upload_id, $metadata, $chunk_number, $chunk_data)
	{
		$assembly_path = $this->get_assembly_temp_path($upload_id, $metadata['filename']);
		$upload_path = $this->get_upload_path($upload_id);
		
		if (!file_exists($upload_path)) {
			if (!@mkdir($upload_path, 0755, true)) {
				throw new Exception("FAILED_TO_CREATE_UPLOAD_DIRECTORY");
			}
		}
		
		$offset = (int)$chunk_number * (int)$metadata['chunk_size'];
		$chunk_length = strlen($chunk_data);
		
		$fp = @fopen($assembly_path, 'c+b');
		if (!$fp) {
			throw new Exception("FAILED_TO_OPEN_ASSEMBLY_FILE");
		}
		
		if (!@flock($fp, LOCK_EX)) {
			fclose($fp);
			throw new Exception("FAILED_TO_LOCK_ASSEMBLY_FILE");
		}
		
		if (@fseek($fp, $offset, SEEK_SET) !== 0) {
			@flock($fp, LOCK_UN);
			fclose($fp);
			throw new Exception("FAILED_TO_SEEK_ASSEMBLY_FILE: chunk_" . $chunk_number);
		}
		
		$written = @fwrite($fp, $chunk_data);
		if ($written === false || $written !== $chunk_length) {
			@flock($fp, LOCK_UN);
			fclose($fp);
			throw new Exception("FAILED_TO_WRITE_ASSEMBLY_CHUNK: chunk_" . $chunk_number);
		}
		
		@fflush($fp);
		@flock($fp, LOCK_UN);
		fclose($fp);
	}
	
	/**
	 * Validate the assembled file and rename it into place.
	 *
	 * @param string $upload_id
	 * @param array|null $metadata
	 * @return string Path to final file
	 */
	private function finalize_assembled_upload($upload_id, $metadata = null)
	{
		@set_time_limit(0);
		@ignore_user_abort(true);
		
		if ($metadata === null) {
			$metadata = $this->get_upload_metadata($upload_id);
		}
		
		if (!$metadata) {
			throw new Exception("UPLOAD_NOT_FOUND");
		}
		
		$upload_path = $this->get_upload_path($upload_id);
		$assembly_path = $this->get_assembly_temp_path($upload_id, $metadata['filename']);
		$final_file = unix_path($upload_path . '/' . $metadata['filename']);
		
		if (!file_exists($assembly_path)) {
			// Legacy uploads that still have chunk parts but no assembly file.
			return $this->combine_chunks($upload_id);
		}
		
		$this->assert_recorded_chunk_bytes($metadata);

		$actual_size = @filesize($assembly_path);
		if ($actual_size === false) {
			throw new Exception("FAILED_TO_READ_ASSEMBLY_FILE");
		}
		
		if ((int)$actual_size !== (int)$metadata['total_size']) {
			throw new Exception("FILE_SIZE_MISMATCH: Expected " . $metadata['total_size'] . ", got " . $actual_size);
		}
		
		if (file_exists($final_file)) {
			@unlink($final_file);
		}
		
		if (!@rename($assembly_path, $final_file)) {
			throw new Exception("FAILED_TO_FINALIZE_FILE");
		}
		
		$this->delete_legacy_chunk_parts($upload_id);
		$this->cleanup_expired_uploads_auto(50);
		
		return $final_file;
	}
	
	/**
	 * Combine chunks into final file
	 * 
	 * @param string $upload_id
	 * @return string Path to final file
	 */
	public function combine_chunks($upload_id)
	{
		@set_time_limit(0);
		@ignore_user_abort(true);
		
		$metadata = $this->get_upload_metadata($upload_id);
		if (!$metadata) {
			throw new Exception("UPLOAD_NOT_FOUND");
		}
		
		$upload_path = $this->get_upload_path($upload_id);
		$final_file = unix_path($upload_path . '/' . $metadata['filename']);
		$temp_file = $final_file . '.tmp';
		
		// Open output file
		$out = @fopen($temp_file, 'wb');
		if (!$out) {
			throw new Exception("FAILED_TO_CREATE_FINAL_FILE");
		}
		
		// Combine chunks in order (legacy uploads that stored separate .part files).
		$total_size = 0;
		for ($i = 0; $i < $metadata['total_chunks']; $i++) {
			$chunk_path = $this->get_chunk_path($upload_id, $i);
			
			if (!file_exists($chunk_path)) {
				fclose($out);
				@unlink($temp_file);
				throw new Exception("CHUNK_NOT_FOUND: chunk_" . $i);
			}
			
			$in = @fopen($chunk_path, 'rb');
			if (!$in) {
				fclose($out);
				@unlink($temp_file);
				throw new Exception("FAILED_TO_READ_CHUNK: chunk_" . $i);
			}
			
			$copied = @stream_copy_to_stream($in, $out);
			fclose($in);
			
			if ($copied === false) {
				fclose($out);
				@unlink($temp_file);
				throw new Exception("FAILED_TO_WRITE_CHUNK_DATA: chunk_" . $i);
			}
			
			$total_size += (int)$copied;
		}
		
		fclose($out);
		
		// Validate final file size
		if ($total_size != $metadata['total_size']) {
			@unlink($temp_file);
			throw new Exception("FILE_SIZE_MISMATCH: Expected " . $metadata['total_size'] . ", got " . $total_size);
		}
		
		// Atomic rename
		if (file_exists($final_file)) {
			@unlink($final_file);
		}
		
		if (!@rename($temp_file, $final_file)) {
			@unlink($temp_file);
			throw new Exception("FAILED_TO_FINALIZE_FILE");
		}
		
		$this->delete_legacy_chunk_parts($upload_id);
		$this->cleanup_expired_uploads_auto(50);
		
		return $final_file;
	}
	
	/**
	 * Delete upload (cleanup)
	 * 
	 * @param string $upload_id
	 * @return bool
	 */
	public function delete_upload($upload_id)
	{
		$upload_path = $this->get_upload_path($upload_id);
		
		if (!file_exists($upload_path)) {
			return true;
		}
		
		return $this->delete_directory($upload_path);
	}
	
	/**
	 * List all active uploads
	 * 
	 * @return array Array of upload information
	 */
	public function list_uploads()
	{
		$uploads = array();
		
		if (!file_exists($this->temp_path)) {
			return $uploads;
		}
		
		$dirs = @scandir($this->temp_path);
		
		if ($dirs === false) {
			return $uploads;
		}
		
		foreach ($dirs as $dir) {
			if ($dir == '.' || $dir == '..') {
				continue;
			}
			
			$upload_path = unix_path($this->temp_path . '/' . $dir);
			if (!is_dir($upload_path)) {
				continue;
			}
			
			$metadata = $this->get_upload_metadata($dir);
			if ($metadata) {
				$uploaded_chunks = $this->get_uploaded_chunks($dir);
				$progress = count($uploaded_chunks) / $metadata['total_chunks'];
				
				// Calculate expiry time: for completed uploads use completed_at, otherwise updated_at
				$check_time = ($metadata['status'] == 'completed' && isset($metadata['completed_at'])) 
					? $metadata['completed_at'] 
					: $metadata['updated_at'];
				$expires_at = $check_time + ($this->expiry_hours * 3600);
				
				$uploads[] = array(
					'upload_id' => $dir,
					'filename' => $metadata['filename'],
					'original_filename' => $metadata['original_filename'],
					'total_size' => $metadata['total_size'],
					'total_size_formatted' => $this->format_bytes($metadata['total_size']),
					'progress' => round($progress * 100, 2),
					'status' => $metadata['status'],
					'created_at' => date('Y-m-d H:i:s', $metadata['created_at']),
					'updated_at' => date('Y-m-d H:i:s', $metadata['updated_at']),
					'expires_at' => date('Y-m-d H:i:s', $expires_at)
				);
			}
		}
		
		return $uploads;
	}
	
	/**
	 * Automatic cleanup of expired uploads (incremental)
	 * 
	 * Uses opendir/readdir to process a limited number of expired uploads
	 * without loading all directory entries into memory. Designed to be called
	 * automatically during normal operations (e.g., when upload starts/completes).
	 * 
	 * @param int $max_deletions Maximum number of expired uploads to delete (default: 5)
	 * @return array Statistics
	 */
	public function cleanup_expired_uploads_auto($max_deletions = 5)
	{
		$stats = array(
			'checked' => 0,
			'deleted' => 0,
			'errors' => 0
		);
		
		if (!file_exists($this->temp_path)) {
			return $stats;
		}
		
		$expiry_time = time() - ($this->expiry_hours * 3600);
		$handle = @opendir($this->temp_path);
		
		if ($handle === false) {
			return $stats;
		}
		
		// Read entries one at a time using stream-based approach
		while (($dir = readdir($handle)) !== false) {
			// Stop if we've deleted enough
			if ($stats['deleted'] >= $max_deletions) {
				break;
			}
			
			if ($dir == '.' || $dir == '..') {
				continue;
			}
			
			$upload_path = unix_path($this->temp_path . '/' . $dir);
			
			if (!is_dir($upload_path)) {
				continue;
			}
			
			$stats['checked']++;
			
			// Check metadata
			$metadata_path = unix_path($upload_path . '/metadata.json');
			if (!file_exists($metadata_path)) {
				// No metadata, delete if old enough
				if (@filemtime($upload_path) < $expiry_time) {
					if ($this->delete_directory($upload_path)) {
						$stats['deleted']++;
					} else {
						$stats['errors']++;
					}
				}
				continue;
			}
			
			$metadata = $this->get_upload_metadata($dir);
			if (!$metadata) {
				continue;
			}
			
			// Delete expired uploads (complete or incomplete)
			// For completed uploads, use completed_at if available, otherwise updated_at
			$check_time = ($metadata['status'] == 'completed' && isset($metadata['completed_at'])) 
				? $metadata['completed_at'] 
				: $metadata['updated_at'];
			
			if ($check_time < $expiry_time) {
				if ($this->delete_upload($dir)) {
					$stats['deleted']++;
				} else {
					$stats['errors']++;
				}
			}
		}
		
		closedir($handle);
		return $stats;
	}
	
	/**
	 * Cleanup expired uploads
	 * 
	 * @return array Statistics
	 */
	public function cleanup_expired_uploads()
	{
		$stats = array(
			'checked' => 0,
			'deleted' => 0,
			'errors' => 0
		);
		
		if (!file_exists($this->temp_path)) {
			return $stats;
		}
		
		$expiry_time = time() - ($this->expiry_hours * 3600);
		$dirs = @scandir($this->temp_path);
		
		if ($dirs === false) {
			return $stats;
		}
		
		foreach ($dirs as $dir) {
			if ($dir == '.' || $dir == '..') {
				continue;
			}
			
			$upload_path = unix_path($this->temp_path . '/' . $dir);
			
			if (!is_dir($upload_path)) {
				continue;
			}
			
			$stats['checked']++;
			
			// Check metadata
			$metadata_path = unix_path($upload_path . '/metadata.json');
			if (!file_exists($metadata_path)) {
				// No metadata, delete if old enough
				if (@filemtime($upload_path) < $expiry_time) {
					if ($this->delete_directory($upload_path)) {
						$stats['deleted']++;
					} else {
						$stats['errors']++;
					}
				}
				continue;
			}
			
			$metadata = $this->get_upload_metadata($dir);
			if (!$metadata) {
				continue;
			}
			
			// Delete expired uploads (complete or incomplete)
			// For completed uploads, use completed_at if available, otherwise updated_at
			$check_time = ($metadata['status'] == 'completed' && isset($metadata['completed_at'])) 
				? $metadata['completed_at'] 
				: $metadata['updated_at'];
			
			if ($check_time < $expiry_time) {
				if ($this->delete_upload($dir)) {
					$stats['deleted']++;
				} else {
					$stats['errors']++;
				}
			}
		}
		
		return $stats;
	}
	
	/**
	 * Generate UUID v4
	 * 
	 * @return string
	 */
	private function generate_upload_id()
	{
		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
		
		return sprintf('%08s-%04s-%04s-%04s-%12s',
			bin2hex(substr($data, 0, 4)),
			bin2hex(substr($data, 4, 2)),
			bin2hex(substr($data, 6, 2)),
			bin2hex(substr($data, 8, 2)),
			bin2hex(substr($data, 10, 6))
		);
	}
	
	/**
	 * Recursively delete directory
	 * 
	 * @param string $dir
	 * @return bool
	 */
	private function delete_directory($dir)
	{
		if (!file_exists($dir)) {
			return true;
		}
		
		if (!is_dir($dir)) {
			return @unlink($dir);
		}
		
		$files = @scandir($dir);
		if ($files === false) {
			return false;
		}
		
		foreach ($files as $file) {
			if ($file == '.' || $file == '..') {
				continue;
			}
			
			$file_path = unix_path($dir . '/' . $file);
			
			if (is_dir($file_path)) {
				if (!$this->delete_directory($file_path)) {
					return false;
				}
			} else {
				if (!@unlink($file_path)) {
					return false;
				}
			}
		}
		
		return @rmdir($dir);
	}
	
	/**
	 * Get upload directory path
	 * 
	 * @param string $upload_id
	 * @return string
	 */
	private function get_upload_path($upload_id)
	{
		return unix_path($this->temp_path . '/' . $upload_id);
	}
	
	/**
	 * Get metadata file path
	 * 
	 * @param string $upload_id
	 * @return string
	 */
	private function get_metadata_path($upload_id)
	{
		$upload_path = $this->get_upload_path($upload_id);
		return unix_path($upload_path . '/metadata.json');
	}
	
	/**
	 * Get chunk file path
	 * 
	 * @param string $upload_id
	 * @param int $chunk_number
	 * @return string
	 */
	private function get_chunk_path($upload_id, $chunk_number)
	{
		$upload_path = $this->get_upload_path($upload_id);
		$chunks_dir = unix_path($upload_path . '/chunks');
		return unix_path($chunks_dir . '/chunk_' . $chunk_number . '.part');
	}
	
	/**
	 * Path to the in-progress assembly file for incremental chunk writes.
	 *
	 * @param string $upload_id
	 * @param string $filename
	 * @return string
	 */
	private function get_assembly_temp_path($upload_id, $filename)
	{
		$upload_path = $this->get_upload_path($upload_id);
		return unix_path($upload_path . '/' . $filename . '.tmp');
	}
	
	/**
	 * Remove legacy per-chunk part files if present.
	 *
	 * @param string $upload_id
	 * @return void
	 */
	private function delete_legacy_chunk_parts($upload_id)
	{
		$chunks_dir = unix_path($this->get_upload_path($upload_id) . '/chunks');
		if (file_exists($chunks_dir)) {
			$this->delete_directory($chunks_dir);
		}
	}
	
	/**
	 * Get final file path
	 * 
	 * @param string $upload_id
	 * @param string $filename
	 * @return string
	 */
	public function get_final_file_path($upload_id, $filename = null)
	{
		$upload_path = $this->get_upload_path($upload_id);
		
		if ($filename === null) {
			$metadata = $this->get_upload_metadata($upload_id);
			if (!$metadata) {
				return false;
			}
			$filename = $metadata['filename'];
		}
		
		return unix_path($upload_path . '/' . $filename);
	}
	
	/**
	 * Get completed upload file information (for internal use)
	 * 
	 * Validates that the upload is completed before returning file information.
	 * Use this function when other parts of the application need to access
	 * the completed file.
	 * 
	 * @param string $upload_id
	 * @return array|false Array with full file information, or false if not found/not completed
	 */
	public function get_completed_upload($upload_id)
	{
		$metadata = $this->get_upload_metadata($upload_id);
		
		if (!$metadata) {
			return false;
		}
		
		if ($metadata['status'] != 'completed') {
			return false;
		}
		
		$final_file = $this->get_final_file_path($upload_id);
		
		// Verify file actually exists
		if (!file_exists($final_file)) {
			return false;
		}
		
		$file_extension = strtolower(pathinfo($metadata['filename'], PATHINFO_EXTENSION));
		
		$file_info = array(
			'upload_id' => $upload_id,
			'file_path' => $final_file,
			'filename' => $metadata['filename'],
			'original_filename' => isset($metadata['original_filename']) ? $metadata['original_filename'] : $metadata['filename'],
			'file_extension' => $file_extension,
			'file_type' => $file_extension,
			'file_size' => filesize($final_file),
			'total_size' => $metadata['total_size'],
			'created_at' => $metadata['created_at'],
			'updated_at' => $metadata['updated_at'],
			'completed_at' => isset($metadata['completed_at']) ? $metadata['completed_at'] : null,
			'status' => $metadata['status'],
			'metadata' => isset($metadata['metadata']) ? $metadata['metadata'] : array()
		);
		
		return $file_info;
	}
	
	/**
	 * Sanitize filename for safe storage
	 * 
	 * @param string $filename
	 * @return string
	 */
	private function sanitize_filename($filename)
	{
		$CI =& get_instance();
		
		$filename = basename($filename);
		
		// Remove any null bytes
		$filename = str_replace("\0", '', $filename);
		
		// Sanitize filename
		$filename = $CI->security->sanitize_filename($filename);
		
		// Replace spaces and special characters with underscores
		// Allow only alphanumeric, dots, hyphens, and underscores
		$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
		
		// Clean up multiple consecutive underscores
		$filename = preg_replace('/_+/', '_', $filename);
		
		// Remove leading/trailing underscores
		$filename = trim($filename, '_');
		
		// Apply additional restrictions (dots to underscores for filename part)
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$name = pathinfo($filename, PATHINFO_FILENAME);
		
		// Replace dots with underscores in filename part only (consistent with resources model)
		$name = str_replace('.', '_', $name);
		
		// Clean up multiple consecutive underscores again after dot replacement
		$name = preg_replace('/_+/', '_', $name);
		$name = trim($name, '_');
		
		// Rebuild filename
		$filename = empty($extension) ? $name : $name . '.' . $extension;
		
		// Limit length
		if (strlen($filename) > 255) {
			$filename = substr($name, 0, 255 - strlen($extension) - 1) . '.' . $extension;
		}
		
		return $filename;
	}
	
	/**
	 * Ensure filename is unique in upload directory
	 * 
	 * @param string $base_filename
	 * @param string $upload_path
	 * @return string
	 */
	private function ensure_unique_filename($base_filename, $upload_path)
	{
		$filename = $base_filename;
		$counter = 1;
		
		while (file_exists($upload_path . '/' . $filename)) {
			$extension = pathinfo($base_filename, PATHINFO_EXTENSION);
			$name = pathinfo($base_filename, PATHINFO_FILENAME);
			
			$filename = $name . '_' . $counter . ($extension ? '.' . $extension : '');
			$counter++;
			
			// Prevent infinite loop
			if ($counter > 9999) {
				throw new Exception("FILENAME_COLLISION_LIMIT_EXCEEDED");
			}
		}
		
		return $filename;
	}
	
	/**
	 * Validate file extension against allowed types
	 * 
	 * Checks per-upload restriction (metadata['allowed_types']) first,
	 * then falls back to global allowed_resource_types setting.
	 * 
	 * @param string $filename
	 * @param array $metadata Optional metadata containing per-upload restrictions
	 * @return bool
	 */
	private function validate_file_type($filename, $metadata = array())
	{
		// Check for per-upload file type restriction in metadata
		$allowed_types = null;
		if (isset($metadata['allowed_types']) && !empty($metadata['allowed_types'])) {
			$allowed_types = $metadata['allowed_types'];
		}
		
		// Fall back to global setting if no per-upload restriction
		if ($allowed_types === null) {
			$allowed_types = $this->allowed_types;
		}
		
		// If still empty, all types allowed
		if (empty($allowed_types) || trim($allowed_types) == '') {
			return true;
		}
		
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$allowed = array_map('trim', explode(',', strtolower($allowed_types)));
		
		return in_array($extension, $allowed);
	}
	
	/**
	 * Get maximum chunk size based on PHP limits
	 * 
	 * Returns the smaller of post_max_size and upload_max_filesize
	 * 
	 * @return int Maximum chunk size in bytes
	 */
	public function get_max_chunk_size()
	{
		return $this->max_chunk_size;
	}
	
	/**
	 * Get maximum chunk size based on PHP limits
	 * 
	 * Returns the smaller of post_max_size and upload_max_filesize
	 * 
	 * @return int Maximum chunk size in bytes
	 */
	private function get_php_max_chunk_size()
	{
		$post_max_size = $this->convert_ini_size_to_bytes(ini_get('post_max_size'));
		$upload_max_filesize = $this->convert_ini_size_to_bytes(ini_get('upload_max_filesize'));
		
		// Use the smaller of the two limits
		// post_max_size must be >= upload_max_filesize, but we'll be safe
		return min($post_max_size, $upload_max_filesize);
	}
	
	/**
	 * Convert PHP ini size string to bytes
	 * 
	 * Handles formats like: "10M", "100M", "1G", "10485760" (bytes)
	 * 
	 * @param string $size_str
	 * @return int Size in bytes
	 */
	private function convert_ini_size_to_bytes($size_str)
	{
		if (empty($size_str) || $size_str == '0') {
			return 0;
		}
		
		$size_str = trim($size_str);
		$last_char = strtolower(substr($size_str, -1));
		
		// Extract numeric value
		$value = (float)$size_str;
		
		// Apply multiplier based on suffix
		switch ($last_char) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
			default:
				// No suffix, already in bytes
				break;
		}
		
		return (int)$value;
	}
	
	/**
	 * Format bytes to human-readable string
	 * 
	 * @param int $bytes
	 * @return string
	 */
	private function format_bytes($bytes)
	{
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		return round($bytes, 2) . ' ' . $units[$pow];
	}
}

