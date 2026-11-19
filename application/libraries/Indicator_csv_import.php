<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Indicator data import from CSV via FastAPI/DuckDB.
 *
 * PHP never reads or rewrites the data rows:
 *   prepare  - move the upload to a per-upload staging file, read the header line, validate it against
 *              the DSD, get distinct indicator IDs (one DuckDB scan in FastAPI);
 *   queue    - send one FastAPI job: load (header renames + DSD column selection + indicator filter while
 *              reading), atomic replace of the timeseries table, archive CSV export, note tuples;
 *   finalize - after the job is done: record publish state, register the archive data file, save notes.
 *              Idempotent; also run lazily (finalize_pending) when the user left before it ran.
 *
 * State lives in data/indicator_import_state.json in the project folder.
 */
class Indicator_csv_import
{
	const STATE_FILE = 'data/indicator_import_state.json';
	const LOCK_FILE = 'data/indicator_import_state.lock';
	const STAGING_PREFIX = 'indicator_staging_';

	/** @var object CodeIgniter instance */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Indicator_dsd_model');
		$this->ci->load->library('indicator_duckdb_service');
		$this->ci->load->library('Indicator_data_notes');
	}

	/**
	 * Move a CSV into the project as the prepared staging file, validate headers, read distinct indicator IDs.
	 *
	 * @param int    $sid
	 * @param string $source_path completed resumable upload file or PHP upload tmp file
	 * @param bool   $is_php_upload true when $source_path is a $_FILES tmp file
	 * @param string $delimiter
	 * @param int    $distinct_limit
	 * @param int|null $user_id
	 * @return array valid, validation, expected_columns, column_names, distinct, ready
	 * @throws Exception
	 */
	public function prepare($sid, $source_path, $is_php_upload = false, $delimiter = ',', $distinct_limit = 2000, $user_id = null)
	{
		$sid = (int) $sid;
		$delimiter = (is_string($delimiter) && strlen($delimiter) === 1) ? $delimiter : ',';

		$lock = $this->lock($sid);
		try {
			$this->assert_no_running_import($sid, $user_id);
			$ready = $this->ci->Indicator_dsd_model->assert_ready_for_data_upload($sid, false, false);

			$this->remove_staging_files($sid);

			$relative = 'data/' . self::STAGING_PREFIX . bin2hex(random_bytes(8)) . '.csv';
			$dest = $this->ci->Editor_model->resolve_project_file_path($sid, $relative);
			if (!$dest) {
				throw new Exception('Project folder not available');
			}
			$this->move_file($source_path, $dest, $is_php_upload);

			$real_path = $this->ci->Editor_model->resolve_absolute_file_path($dest);
			if (!$real_path || !is_file($real_path)) {
				throw new Exception('Could not resolve CSV path');
			}

			$column_names = $this->ci->Indicator_dsd_model->normalize_csv_header_names(
				$this->read_raw_header($real_path, $delimiter)
			);
			$expected = $this->ci->Indicator_dsd_model->get_dsd_column_names_for_csv($sid);
			$validation = $this->validate_header($sid, $column_names, $expected);

			if (empty($validation['valid'])) {
				@unlink($real_path);
				return array(
					'valid' => false,
					'validation' => $validation,
					'expected_columns' => $expected,
					'column_names' => $column_names,
					'ready' => $ready,
				);
			}

			$distinct = $this->ci->indicator_duckdb_service->csv_distinct(
				$sid,
				$real_path,
				$ready['indicator_column'],
				$delimiter,
				$distinct_limit,
				$column_names
			);
			if (is_array($distinct) && !empty($distinct['error'])) {
				throw new Exception(
					$this->fastapi_message($distinct, 'Could not read indicator values from CSV')
				);
			}

			clearstatcache(true, $real_path);
			$state = $this->read_state($sid);
			$state['staging_file'] = $relative;
			$state['staging_size'] = (int) filesize($real_path);
			$state['staging_mtime'] = (int) filemtime($real_path);
			$state['delimiter'] = $delimiter;
			$state['column_names'] = $column_names;
			$state['prepared_at'] = time();
			$state['prepared_by'] = $user_id !== null ? (int) $user_id : null;
			$this->write_state($sid, $state);

			return array(
				'valid' => true,
				'validation' => $validation,
				'expected_columns' => $expected,
				'column_names' => $column_names,
				'distinct' => is_array($distinct) ? $distinct : array(),
				'ready' => $ready,
			);
		} finally {
			$this->unlock($lock);
		}
	}

	/**
	 * Queue the FastAPI import of the prepared CSV for one indicator ID. Returns immediately.
	 *
	 * @param int      $sid
	 * @param int|null $user_id
	 * @param bool     $keep_extra_columns
	 * @param array    $time_overrides build_duckdb_promote_time_spec() overrides (implied_freq_code)
	 * @param string|null $indicator_value import this indicator ID instead of the project's bound value
	 * @return array job_id, indicator_value
	 * @throws Exception
	 */
	public function queue_import($sid, $user_id = null, $keep_extra_columns = false, array $time_overrides = array(), $indicator_value = null)
	{
		$sid = (int) $sid;
		$lock = $this->lock($sid);
		try {
			$this->assert_no_running_import($sid, $user_id);

			$state = $this->read_state($sid);
			$real_path = $this->staging_realpath($sid, $state);
			if (!$real_path) {
				throw new Exception('No prepared CSV found. Upload and validate a CSV first.');
			}

			$ready = $this->ci->Indicator_dsd_model->assert_ready_for_data_upload($sid);
			if ($indicator_value === null || trim((string) $indicator_value) === '') {
				$indicator_value = $ready['indicator_id_value'];
			}
			$indicator_value = trim((string) $indicator_value);
			$column_names = isset($state['column_names']) && is_array($state['column_names']) ? $state['column_names'] : array();
			$delimiter = isset($state['delimiter']) ? (string) $state['delimiter'] : ',';

			// Validate again: the data structure may have changed since the CSV was prepared.
			$expected = $this->ci->Indicator_dsd_model->get_dsd_column_names_for_csv($sid);
			$validation = $this->validate_header($sid, $column_names, $expected);
			if (empty($validation['valid'])) {
				$msg = isset($validation['message']) ? $validation['message'] : 'CSV is missing required data structure columns';
				if (!empty($validation['missing_in_csv'])) {
					$msg .= ' Missing: ' . implode(', ', $validation['missing_in_csv']);
				}
				throw new Exception($msg);
			}

			$select_columns = $this->ci->Indicator_dsd_model->build_csv_select_columns($expected, $validation, $keep_extra_columns);
			$note_specs = Indicator_data_notes::note_column_specs(
				$this->ci->indicator_data_notes->get_footnote_columns($sid),
				$column_names
			);
			$time_spec = $this->ci->Indicator_dsd_model->build_duckdb_promote_time_spec($sid, $time_overrides);
			$export_path = $this->ci->Indicator_dsd_model->resolve_indicator_data_csv_absolute_path($sid);

			$body = array(
				'project_id' => (string) $sid,
				'csv_path' => $real_path,
				'delimiter' => $delimiter,
				'expected_columns' => array_map(function ($name) {
					return array('name' => (string) $name);
				}, $expected),
				'indicator_column' => $ready['indicator_column'],
				'indicator_value' => (string) $indicator_value,
				'column_names' => $column_names,
				'select_columns' => $select_columns,
				'expected_size' => isset($state['staging_size']) ? (int) $state['staging_size'] : null,
				'expected_mtime' => isset($state['staging_mtime']) ? (int) $state['staging_mtime'] : null,
			);
			if (!empty($note_specs)) {
				$body['note_columns'] = array_map(function ($spec) {
					return array('column' => $spec['column'], 'text_columns' => $spec['text_columns']);
				}, $note_specs);
			}
			if ($export_path) {
				$body['export_csv_path'] = $export_path;
			}
			if (is_array($time_spec) && !empty($time_spec['time_column'])) {
				$body['time_spec'] = $time_spec;
			}

			$queue = $this->ci->indicator_duckdb_service->timeseries_import_csv_queue($body);
			if (is_array($queue) && !empty($queue['error'])) {
				throw new Exception($this->fastapi_message($queue, 'FastAPI replace-from-csv request failed'));
			}
			if (empty($queue['job_id'])) {
				throw new Exception('FastAPI did not return job_id');
			}

			$state['pending'] = array(
				'job_id' => (string) $queue['job_id'],
				'indicator_value' => (string) $indicator_value,
				'note_specs' => $note_specs,
				'user_id' => $user_id !== null ? (int) $user_id : null,
				'queued_at' => time(),
			);
			$this->write_state($sid, $state);

			return array(
				'job_id' => (string) $queue['job_id'],
				'indicator_value' => (string) $indicator_value,
			);
		} finally {
			$this->unlock($lock);
		}
	}

	/**
	 * Finish an import job: record publish state, register the archive CSV, save notes. Safe to call repeatedly.
	 *
	 * @param int      $sid
	 * @param string   $job_id
	 * @param int|null $user_id
	 * @return array status: success | running | failed, plus result fields
	 * @throws Exception when the job does not belong to this project
	 */
	public function finalize($sid, $job_id, $user_id = null)
	{
		$sid = (int) $sid;
		$lock = $this->lock($sid);
		try {
			return $this->finalize_locked($sid, (string) $job_id, $user_id);
		} finally {
			$this->unlock($lock);
		}
	}

	/**
	 * Finalize a finished import the user did not wait for. Never throws.
	 *
	 * @param int      $sid
	 * @param int|null $user_id
	 * @return array|null finalize() result, or null when nothing was pending
	 */
	public function finalize_pending($sid, $user_id = null)
	{
		try {
			$state = $this->read_state($sid);
			if (empty($state['pending']['job_id'])) {
				return null;
			}
			return $this->finalize($sid, $state['pending']['job_id'], $user_id);
		} catch (Throwable $e) {
			log_message('error', 'Indicator import finalize_pending failed sid=' . (int) $sid . ': ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Poll the job and finalize (for API clients that ask to wait, and the background worker).
	 *
	 * @return array finalize() result
	 */
	public function wait_and_finalize($sid, $job_id, $user_id = null, $max_wait_seconds = 1800)
	{
		$this->ci->indicator_duckdb_service->poll_job($job_id, $max_wait_seconds, 3);
		return $this->finalize($sid, $job_id, $user_id);
	}

	/**
	 * Pending import job id, if any (for the UI to resume polling).
	 *
	 * @param int $sid
	 * @return string|null
	 */
	public function pending_job_id($sid)
	{
		$state = $this->read_state($sid);
		return !empty($state['pending']['job_id']) ? (string) $state['pending']['job_id'] : null;
	}

	private function finalize_locked($sid, $job_id, $user_id)
	{
		$state = $this->read_state($sid);
		if (!empty($state['last_finalized_job_id']) && $state['last_finalized_job_id'] === $job_id) {
			$result = isset($state['last_result']) && is_array($state['last_result']) ? $state['last_result'] : array();
			return array_merge(array('status' => 'success', 'job_id' => $job_id), $result, array('already_finalized' => true));
		}

		$pending = isset($state['pending']) && is_array($state['pending']) ? $state['pending'] : null;
		if (!$pending || $pending['job_id'] !== $job_id) {
			throw new Exception('Import job not found for this project');
		}

		$res = $this->ci->indicator_duckdb_service->get_job($job_id);
		$code = (int) $res['http_code'];
		$body = is_array($res['body']) ? $res['body'] : array();

		if ($code === 200 && isset($body['info']['project_id']) && (string) (int) $body['info']['project_id'] !== (string) $sid) {
			throw new Exception('Job does not belong to this project');
		}

		$status = $code === 200 && isset($body['status']) ? $body['status'] : null;
		if ($status === 'queued' || $status === 'processing') {
			return array('status' => 'running', 'job_id' => $job_id, 'job_status' => $status);
		}

		if ($status !== 'done') {
			$message = $code === 404
				? 'Import job not found (the data service may have restarted). Import the data again.'
				: $this->strip_error_details(!empty($res['message']) ? $res['message'] : 'Import failed');
			unset($state['pending']);
			$state['last_error'] = array('job_id' => $job_id, 'message' => $message, 'at' => time());
			$this->write_state($sid, $state);
			return array('status' => 'failed', 'job_id' => $job_id, 'message' => $message);
		}

		$data = isset($body['data']) && is_array($body['data']) ? $body['data'] : array();
		$row_count = isset($data['row_count']) ? (int) $data['row_count'] : null;
		$finalize_user = $user_id !== null ? $user_id : (isset($pending['user_id']) ? $pending['user_id'] : null);

		$this->ci->Indicator_dsd_model->record_published_data_import($sid, $row_count);

		$export_warning = null;
		if (!empty($data['export'])) {
			try {
				$this->ci->Indicator_dsd_model->register_indicator_datafile($sid, $finalize_user);
			} catch (Throwable $e) {
				$export_warning = $e->getMessage();
			}
		} else {
			$export_warning = !empty($data['export_error']) ? $data['export_error'] : 'Archive CSV was not exported';
		}

		$notes_report = $this->ci->indicator_data_notes->process_after_import_tuples(
			$sid,
			isset($data['note_tuples']) ? $data['note_tuples'] : null,
			isset($pending['note_specs']) && is_array($pending['note_specs']) ? $pending['note_specs'] : array(),
			$finalize_user
		);

		$result = array(
			'indicator_value' => isset($pending['indicator_value']) ? $pending['indicator_value'] : null,
			'row_count' => $row_count,
			'message' => 'Timeseries data imported successfully',
		);
		if ($export_warning !== null) {
			$result['export_warning'] = $export_warning;
			log_message('error', 'Indicator CSV archive export failed after DuckDB import sid=' . $sid . ': ' . $export_warning);
		}
		if ($notes_report !== null) {
			$result['data_notes'] = $notes_report;
		}

		$staging = $this->staging_realpath($sid, $state, false);
		if ($staging) {
			@unlink($staging);
		}
		unset($state['pending'], $state['staging_file'], $state['staging_size'], $state['staging_mtime'], $state['column_names'], $state['last_error']);
		$state['last_finalized_job_id'] = $job_id;
		$state['last_result'] = $result;
		$state['finalized_at'] = time();
		$this->write_state($sid, $state);

		return array_merge(array('status' => 'success', 'job_id' => $job_id), $result);
	}

	/**
	 * Refuse when an import is still running; finalize one that finished meanwhile.
	 */
	private function assert_no_running_import($sid, $user_id)
	{
		$state = $this->read_state($sid);
		if (empty($state['pending']['job_id'])) {
			return;
		}
		$result = $this->finalize_locked($sid, $state['pending']['job_id'], $user_id);
		if (isset($result['status']) && $result['status'] === 'running') {
			throw new Exception('A data import is already running for this project. Wait for it to finish.');
		}
	}

	/**
	 * Validate normalized header names against the DSD (footnote columns with note text columns are optional).
	 */
	private function validate_header($sid, array $column_names, array $expected)
	{
		$optional = Indicator_data_notes::generatable_footnote_columns(
			$this->ci->indicator_data_notes->get_footnote_columns($sid),
			$column_names
		);
		return $this->ci->Indicator_dsd_model->validate_headers_for_dsd($column_names, $expected, $optional);
	}

	/**
	 * First CSV row as raw cells (BOM removed). Reads one line only.
	 */
	private function read_raw_header($path, $delimiter)
	{
		$fh = @fopen($path, 'rb');
		if ($fh === false) {
			throw new Exception('Failed to read CSV file');
		}
		$row = fgetcsv($fh, 0, $delimiter, '"', '');
		fclose($fh);
		if ($row === false || $row === null || $row === array(null)) {
			throw new Exception('CSV file has no header row');
		}
		$row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
		return $row;
	}

	private function move_file($source, $dest, $is_php_upload)
	{
		$dir = dirname($dest);
		if (!is_dir($dir)) {
			@mkdir($dir, 0777, true);
		}
		if ($is_php_upload) {
			if (!@move_uploaded_file($source, $dest)) {
				throw new Exception('Failed to save uploaded CSV');
			}
			return;
		}
		if (@rename($source, $dest)) {
			return;
		}
		// Different filesystem: copy, then remove the source.
		if (!@copy($source, $dest)) {
			throw new Exception('Failed to save uploaded CSV');
		}
		@unlink($source);
	}

	/**
	 * Remove staging CSVs left by earlier uploads (per-upload names and the legacy fixed names).
	 */
	private function remove_staging_files($sid)
	{
		$folder = $this->ci->Editor_model->get_project_folder($sid);
		if (!$folder) {
			return;
		}
		foreach ((array) glob(rtrim($folder, '/') . '/data/' . self::STAGING_PREFIX . '*.csv') as $file) {
			// The legacy upload file is kept: project packages use it when there is no indicator_data.csv.
			if (basename($file) === 'indicator_staging_upload.csv') {
				continue;
			}
			if (is_file($file)) {
				@unlink($file);
			}
		}
	}

	/**
	 * Absolute path of the prepared staging file, or null.
	 */
	private function staging_realpath($sid, array $state, $require_exists = true)
	{
		if (empty($state['staging_file']) || strpos($state['staging_file'], 'data/' . self::STAGING_PREFIX) !== 0) {
			return null;
		}
		$path = $this->ci->Editor_model->resolve_project_file_path($sid, $state['staging_file']);
		if (!$path) {
			return null;
		}
		if ($require_exists && !is_file($path)) {
			return null;
		}
		return $path;
	}

	private function state_path($sid)
	{
		$this->ci->Editor_model->create_project_folder($sid);
		$path = $this->ci->Editor_model->resolve_project_file_path($sid, self::STATE_FILE);
		if (!$path) {
			throw new Exception('Project folder not available');
		}
		$dir = dirname($path);
		if (!is_dir($dir)) {
			@mkdir($dir, 0777, true);
		}
		return $path;
	}

	private function read_state($sid)
	{
		$path = $this->state_path($sid);
		if (!is_file($path)) {
			return array();
		}
		$data = json_decode((string) @file_get_contents($path), true);
		return is_array($data) ? $data : array();
	}

	private function write_state($sid, array $state)
	{
		$path = $this->state_path($sid);
		$tmp = $path . '.tmp_' . bin2hex(random_bytes(4));
		if (@file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false || !@rename($tmp, $path)) {
			@unlink($tmp);
			throw new Exception('Could not save import state');
		}
	}

	private function lock($sid)
	{
		$this->state_path($sid);
		$path = $this->ci->Editor_model->resolve_project_file_path($sid, self::LOCK_FILE);
		$fh = $path ? @fopen($path, 'c') : false;
		if ($fh === false) {
			throw new Exception('Could not lock import state');
		}
		flock($fh, LOCK_EX);
		return $fh;
	}

	private function unlock($fh)
	{
		if (is_resource($fh)) {
			flock($fh, LOCK_UN);
			fclose($fh);
		}
	}

	/**
	 * Message from a FastAPI error envelope (JSON detail when present).
	 */
	private function fastapi_message($envelope, $fallback)
	{
		$raw = isset($envelope['message']) ? (string) $envelope['message'] : '';
		$decoded = json_decode($raw, true);
		if (is_array($decoded) && isset($decoded['detail'])) {
			$raw = is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']);
		}
		$raw = $this->strip_error_details($raw);
		return $raw !== '' ? $raw : $fallback;
	}

	/**
	 * FastAPI job errors append a traceback after "Detailed Error Information"; keep the first part for users.
	 */
	private function strip_error_details($message)
	{
		$message = (string) $message;
		$pos = strpos($message, "\n\nDetailed Error Information:");
		return trim($pos !== false ? substr($message, 0, $pos) : $message);
	}
}
