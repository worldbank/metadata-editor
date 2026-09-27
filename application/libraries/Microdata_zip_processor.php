<?php

/**
 * Validate and extract microdata files (.dta, .sav, .csv) from ZIP uploads.
 *
 * Only whitelisted extensions are written to disk; other entries are ignored.
 * The ZIP archive is deleted after successful extraction.
 */
class Microdata_zip_processor
{
	private $allowed_extensions = array('dta', 'sav', 'csv');

	private $max_entries = 100;

	public function __construct()
	{
		if (function_exists('get_instance')) {
			$CI =& get_instance();
			$CI->config->load('uploads');
			$configured = $CI->config->item('microdata_zip_max_entries');
			if (is_numeric($configured) && (int) $configured > 0) {
				$this->max_entries = (int) $configured;
			}
		}
	}

	/**
	 * Scan a ZIP without writing files.
	 *
	 * @param string $zip_path
	 * @return array{valid:bool,errors:array,files:array,skipped_count:int}
	 */
	public function scan($zip_path)
	{
		$result = array(
			'valid' => false,
			'errors' => array(),
			'files' => array(),
			'skipped_count' => 0,
		);

		if (!file_exists($zip_path)) {
			$result['errors'][] = 'ZIP file does not exist.';
			return $result;
		}

		if (!class_exists('ZipArchive')) {
			$result['errors'][] = 'ZipArchive PHP extension is not available.';
			return $result;
		}

		$zip = new ZipArchive();
		$opened = $zip->open($zip_path);
		if ($opened !== true) {
			$result['errors'][] = 'Cannot open ZIP file.';
			return $result;
		}

		$entry_count = $zip->numFiles;
		if ($entry_count > $this->max_entries) {
			$zip->close();
			$result['errors'][] = 'ZIP contains too many entries (maximum ' . $this->max_entries . ').';
			return $result;
		}

		$seen_flat_names = array();

		for ($i = 0; $i < $entry_count; $i++) {
			$file_info = $zip->statIndex($i);
			if (!$file_info || empty($file_info['name'])) {
				continue;
			}

			$entry_name = str_replace('\\', '/', (string) $file_info['name']);
			$basename = basename($entry_name);

			if ($this->should_skip_entry($entry_name, $basename)) {
				$result['skipped_count']++;
				continue;
			}

			if ($this->is_unsafe_path($entry_name)) {
				$zip->close();
				$result['errors'][] = 'Unsafe path in ZIP archive: ' . $entry_name;
				return $result;
			}

			$extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
			if (!in_array($extension, $this->allowed_extensions, true)) {
				$result['skipped_count']++;
				continue;
			}

			$flat_name = $basename;
			try {
				validate_filename($flat_name, 200);
			} catch (Exception $e) {
				$zip->close();
				$result['errors'][] = 'Invalid file name in ZIP: ' . $flat_name;
				return $result;
			}

			if (isset($seen_flat_names[$flat_name])) {
				$zip->close();
				$result['errors'][] = "Duplicate file name after extraction: '{$flat_name}' (from '{$seen_flat_names[$flat_name]}' and '{$entry_name}').";
				return $result;
			}

			$uncompressed_size = isset($file_info['size']) ? (int) $file_info['size'] : 0;
			if ($uncompressed_size <= 0) {
				$zip->close();
				$result['errors'][] = "File '{$entry_name}' is empty.";
				return $result;
			}

			$seen_flat_names[$flat_name] = $entry_name;
			$result['files'][] = array(
				'name' => $entry_name,
				'flat_name' => $flat_name,
				'extension' => $extension,
				'size' => $uncompressed_size,
			);
		}

		$zip->close();

		if (empty($result['files'])) {
			$result['errors'][] = 'ZIP contains no supported data files (.dta, .sav, .csv).';
			return $result;
		}

		$result['valid'] = empty($result['errors']);
		return $result;
	}

	/**
	 * Extract only the planned supported files, then delete the ZIP on success.
	 *
	 * @param string $zip_path
	 * @param string $destination
	 * @param array $files Planned files from scan()
	 * @return array{success:bool,extracted_files:array,errors:array}
	 */
	public function extract($zip_path, $destination, array $files)
	{
		$result = array(
			'success' => false,
			'extracted_files' => array(),
			'errors' => array(),
		);

		if (empty($files)) {
			$result['errors'][] = 'No files to extract.';
			return $result;
		}

		if (!is_dir($destination) && !@mkdir($destination, 0777, true)) {
			$result['errors'][] = 'Cannot create destination directory.';
			return $result;
		}

		$zip = new ZipArchive();
		if ($zip->open($zip_path) !== true) {
			$result['errors'][] = 'Cannot open ZIP file.';
			return $result;
		}

		foreach ($files as $file) {
			$entry_name = $file['name'];
			$flat_name = $file['flat_name'];
			$target_path = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . $flat_name;
			$max_bytes = isset($file['size']) ? (int) $file['size'] : 0;

			$fp_in = $zip->getStream($entry_name);
			if ($fp_in === false) {
				$result['errors'][] = "Failed to open stream for file: {$entry_name}";
				continue;
			}

			$fp_out = fopen($target_path, 'wb');
			if ($fp_out === false) {
				fclose($fp_in);
				$result['errors'][] = "Failed to create file: {$flat_name}";
				continue;
			}

			$bytes_written = $this->stream_copy_with_limit($fp_in, $fp_out, $max_bytes);
			fclose($fp_in);
			fclose($fp_out);

			if ($bytes_written === false || $bytes_written <= 0) {
				$result['errors'][] = "Failed to write file: {$flat_name}";
				@unlink($target_path);
				continue;
			}

			if ($max_bytes > 0 && $bytes_written > $max_bytes) {
				$result['errors'][] = "File exceeded declared size: {$flat_name}";
				@unlink($target_path);
				continue;
			}

			$result['extracted_files'][] = $flat_name;
		}

		$zip->close();

		if (!empty($result['errors']) || empty($result['extracted_files'])) {
			$this->cleanup_files($destination, $result['extracted_files']);
			return $result;
		}

		if (!@unlink($zip_path)) {
			$this->cleanup_files($destination, $result['extracted_files']);
			$result['errors'][] = 'Failed to delete ZIP file after extraction.';
			$result['extracted_files'] = array();
			return $result;
		}

		$result['success'] = true;
		return $result;
	}

	/**
	 * Scan and extract in one step.
	 *
	 * @param string $zip_path
	 * @param string $destination
	 * @return array
	 */
	public function process($zip_path, $destination)
	{
		$scan = $this->scan($zip_path);
		if (!$scan['valid']) {
			return array(
				'success' => false,
				'errors' => $scan['errors'],
				'extracted_files' => array(),
				'skipped_count' => isset($scan['skipped_count']) ? $scan['skipped_count'] : 0,
			);
		}

		$extract = $this->extract($zip_path, $destination, $scan['files']);
		$extract['skipped_count'] = $scan['skipped_count'];
		return $extract;
	}

	/**
	 * @param string $destination
	 * @param array $filenames Basenames to delete from destination
	 */
	public function cleanup_files($destination, array $filenames)
	{
		foreach ($filenames as $filename) {
			$path = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR . basename($filename);
			if (is_file($path)) {
				@unlink($path);
			}
		}
	}

	private function should_skip_entry($entry_name, $basename)
	{
		if ($entry_name === '' || substr($entry_name, -1) === '/') {
			return true;
		}

		if (strpos($entry_name, '__MACOSX/') === 0 || strpos($basename, '._') === 0) {
			return true;
		}

		return false;
	}

	private function is_unsafe_path($entry_name)
	{
		if ($entry_name === '' || $entry_name[0] === '/' || preg_match('/^[A-Za-z]:\\//', $entry_name)) {
			return true;
		}

		if (strpos($entry_name, '../') !== false || substr($entry_name, -3) === '/..') {
			return true;
		}

		return false;
	}

	/**
	 * @param resource $in
	 * @param resource $out
	 * @param int $max_bytes 0 = no limit
	 * @return int|false
	 */
	private function stream_copy_with_limit($in, $out, $max_bytes = 0)
	{
		$total = 0;
		while (!feof($in)) {
			$chunk = fread($in, 8192);
			if ($chunk === false) {
				return false;
			}
			if ($chunk === '') {
				break;
			}

			$total += strlen($chunk);
			if ($max_bytes > 0 && $total > $max_bytes) {
				return false;
			}

			$written = fwrite($out, $chunk);
			if ($written === false) {
				return false;
			}
		}

		return $total;
	}
}
