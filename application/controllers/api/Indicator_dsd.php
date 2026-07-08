<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Indicator_dsd extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Indicator_dsd_model");
		
		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();
		$this->api_user = $this->api_user();
	}

	//override authentication to support both session authentication + api keys
	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}

	/**
	 * 
	 * List all DSD columns for a project
	 * 
	 * GET /api/indicator_dsd/{sid}
	 * Query params: detailed (0|1), offset (default: 0), limit (default: null - all),
	 *   resolve_codelists (0|1) — when 1, expand global linked codelists into each column's code_list (chart filters)
	 * 
	 */
	function index_get($sid = null)
	{
		try{
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);
			
			$detailed = (int)$this->input->get("detailed");
			$offset = (int)$this->input->get("offset");
			$limit = $this->input->get("limit");

			if ($limit !== null) {
				$limit = (int)$limit;
			}

			$columns = $this->Indicator_dsd_model->select_all($sid, $detailed, $offset, $limit);

			if ((int) $this->input->get('resolve_codelists') === 1) {
				$columns = $this->Indicator_dsd_model->enrich_columns_resolved_code_lists($sid, $columns);
			}
			
			$response = array(
				'columns' => $columns
			);

			$this->config->load('indicator_dsd', true);
			$response['dictionaries'] = array(
				'freq_codes' => $this->config->item('dsd_freq_codes', 'indicator_dsd') ?: array(),
			);

			// Include total count if pagination is used
			if ($limit !== null && $limit > 0) {
				// Note: count method can be added to model later if needed
				$response['offset'] = $offset;
				$response['limit'] = $limit;
			}

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
	 * Get single DSD column by ID
	 * 
	 * GET /api/indicator_dsd/{sid}/{id}
	 * 
	 */
	function single_get($sid = null, $id = null)
	{
		try{
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			if (!$id) {
				throw new Exception("Column ID is required");
			}

			$column = $this->Indicator_dsd_model->get_row($sid, $id);

			if (!$column) {
				throw new Exception("Column not found");
			}

			$response = array(
				'column' => $column
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
	 * Validate DSD structure, then data (column presence vs DuckDB: published timeseries or staging) only if structure is valid.
	 * Observation-key uniqueness runs on published timeseries only via DuckDB aggregates (POST …/observation-key-validate); time × geography/dimensions/measure/periodicity; not attributes/annotations.
	 *
	 * GET /api/indicator_dsd/validate/{sid}
	 *
	 * Response includes `structure` and `data_validation` (with `skipped` / `reason` when data checks do not run;
	 * `data_validation.observation_key` describes key columns and unique observation counts when applicable).
	 */
	function validate_get($sid = null)
	{
		try{
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);
			
			$result = $this->Indicator_dsd_model->validate_dsd($sid);
			
			$response = array(
				'status' => $result['valid'] ? 'success' : 'failed',
				'valid' => $result['valid'],
				'errors' => $result['errors'],
				'warnings' => $result['warnings'],
				'summary' => $result['summary'],
			);
			if (!empty($result['structure'])) {
				$response['structure'] = $result['structure'];
			}
			if (!empty($result['data_validation'])) {
				$response['data_validation'] = $result['data_validation'];
			}

			// Always return 200 for validation report, regardless of validation result
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
	 * Full reset: drops the DuckDB timeseries table for the project.
	 * POST /api/indicator_dsd/reset/{sid}
	 * No body required. Returns { timeseries_dropped }.
	 */
	function reset_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$this->load->library('indicator_duckdb_service');
			$drop = $this->indicator_duckdb_service->timeseries_drop($sid);

			$ts_dropped = true;
			$warnings   = array();
			if (is_array($drop) && !empty($drop['error'])) {
				$hc = isset($drop['http_code']) ? (int) $drop['http_code'] : 0;
				if ($hc !== 404 && $hc !== 0) {
					$ts_dropped = false;
					$warnings[] = isset($drop['message']) ? $drop['message'] : 'Timeseries drop failed';
				} elseif ($hc === 0) {
					$ts_dropped = false;
					$warnings[] = 'Published data could not be dropped (data API unavailable).';
				}
			}

			$response = array(
				'status'             => 'success',
				'timeseries_dropped' => $ts_dropped,
			);
			if (!empty($warnings)) {
				$response['warnings'] = $warnings;
			}

			$this->Indicator_dsd_model->clear_published_data_tracking($sid);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status'  => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get chart data for visualization
	 * 
	 * GET /api/indicator_dsd/chart-data/{sid}
	 * Query params: geography (comma-separated), time_period_start, time_period_end
	 * 
	 */
	function chart_data_get($sid = null)
	{
		try{
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);
			
			// Get filter parameters
			$filters = array();
			
			$geography = $this->input->get('geography');
			if ($geography) {
				$filters['geography'] = is_array($geography) ? $geography : explode(',', $geography);
				// Trim whitespace
				$filters['geography'] = array_map('trim', $filters['geography']);
			}

			$dimensions_json = $this->input->get('dimensions');
			if ($dimensions_json !== null && $dimensions_json !== '') {
				$decoded = json_decode($dimensions_json, true);
				if (is_array($decoded)) {
					$filters['dimensions'] = $decoded;
				}
			}

			$time_period_start = $this->input->get('time_period_start');
			if ($time_period_start) {
				$filters['time_period_start'] = trim($time_period_start);
			}
			
			$time_period_end = $this->input->get('time_period_end');
			if ($time_period_end) {
				$filters['time_period_end'] = trim($time_period_end);
			}

			$chart_data = $this->Indicator_dsd_model->get_chart_data($sid, $filters);
			
			$response = array(
				'status' => 'success',
				'data' => $chart_data
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
	 * Chart data (POST JSON for multi-dimension filters).
	 * POST /api/indicator_dsd/chart_data/{sid}
	 * Body: geography[], dimensions{ "COL": ["code"] }, time_period_start/end
	 */
	function chart_data_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			$raw = $this->input->raw_input_stream;
			$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
			if (!is_array($data)) {
				$data = array();
			}

			$filters = array();
			if (!empty($data['geography']) && is_array($data['geography'])) {
				$filters['geography'] = array_map('trim', $data['geography']);
			}
			if (!empty($data['dimensions']) && is_array($data['dimensions'])) {
				$filters['dimensions'] = $data['dimensions'];
			}
			if (!empty($data['time_period_start'])) {
				$filters['time_period_start'] = trim((string) $data['time_period_start']);
			}
			if (!empty($data['time_period_end'])) {
				$filters['time_period_end'] = trim((string) $data['time_period_end']);
			}
			if (array_key_exists('use_ts_year_for_time_filter', $data)) {
				$filters['use_ts_year_for_time_filter'] = (bool) $data['use_ts_year_for_time_filter'];
			}

			$chart_data = $this->Indicator_dsd_model->get_chart_data($sid, $filters);

			$this->set_response(array(
				'status' => 'success',
				'data' => $chart_data,
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * GET /api/indicator_dsd/chart_facet_counts/{sid}
	 * Dataset-wide row counts per distinct value for chart slice columns (DuckDB); keys = DSD column names.
	 */
	function chart_facet_counts_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			$payload = $this->Indicator_dsd_model->get_chart_facet_value_counts($sid);

			$this->set_response(array(
				'status' => 'success',
				'data' => $payload,
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * GET /api/indicator_dsd/chart_filter_options/{sid}
	 * Observed-only filter options for chart (DuckDB facet counts + codelist labels).
	 */
	function chart_filter_options_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			$payload = $this->Indicator_dsd_model->get_chart_filter_options($sid);

			$this->set_response(array(
				'status' => 'success',
				'data' => $payload,
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Distinct code/label pairs from published timeseries.
	 * GET /api/indicator_dsd/data_values/{sid}?code_column=COL&label_column=COL2&limit=5000
	 */
	function data_values_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);
			$code_column = $this->input->get('code_column');
			if ($code_column === null || trim($code_column) === '') {
				throw new Exception('code_column query parameter is required');
			}
			$label_column = $this->input->get('label_column');
			if ($label_column !== null && trim($label_column) === '') {
				$label_column = null;
			}
			$limit = (int) $this->input->get('limit');
			if ($limit < 1) {
				$limit = 5000;
			}
			if ($limit > 20000) {
				$limit = 20000;
			}
			$this->load->library('indicator_duckdb_service');
			$data = $this->indicator_duckdb_service->timeseries_distinct_pairs($sid, $code_column, $label_column, $limit);
			if (is_array($data) && !empty($data['error'])) {
				throw new Exception(isset($data['message']) ? $data['message'] : 'distinct pairs failed');
			}
			$this->set_response(array(
				'status' => 'success',
				'data' => $data,
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Paginated rows from published timeseries (data explorer).
	 * GET /api/indicator_dsd/data_rows/{sid}?offset=0&limit=50&filters={"COL":["a","b"]} (limit max 200)
	 */
	function data_rows_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			$offset = (int) $this->input->get('offset');
			if ($offset < 0) {
				$offset = 0;
			}
			$limit = (int) $this->input->get('limit');
			if ($limit < 1) {
				$limit = 50;
			}
			if ($limit > 200) {
				$limit = 200;
			}

			$filters = null;
			$filters_raw = $this->input->get('filters');
			if ($filters_raw !== null && $filters_raw !== '') {
				$decoded = json_decode($filters_raw, true);
				if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
					throw new Exception('Invalid filters JSON');
				}
				if (! is_array($decoded)) {
					throw new Exception('filters must be a JSON object');
				}
				$sanitized = array();
				foreach ($decoded as $k => $v) {
					$col = trim((string) $k);
					if ($col === '') {
						continue;
					}
					if (! is_array($v)) {
						throw new Exception('Each filter value must be a JSON array');
					}
					$vals = array();
					foreach ($v as $item) {
						$vals[] = trim((string) $item);
					}
					if (count($vals) > 0) {
						$sanitized[$col] = $vals;
					}
				}
				if (count($sanitized) > 0) {
					$filters = $sanitized;
				}
			}

			$this->load->library('indicator_duckdb_service');
			$data = $this->indicator_duckdb_service->timeseries_page($sid, $offset, $limit, $filters);

			if (is_array($data) && ! empty($data['error'])) {
				$code = isset($data['http_code']) ? (int) $data['http_code'] : 0;
				$msg = isset($data['message']) ? $data['message'] : 'Timeseries page request failed';
				if ($code === 404) {
					throw new Exception($msg, REST_Controller::HTTP_NOT_FOUND);
				}
				throw new Exception($msg);
			}

			$this->set_response(array(
				'status' => 'success',
				'data' => $data,
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$msg = $e->getMessage();
			$code = $e->getCode();
			if ($code === REST_Controller::HTTP_NOT_FOUND) {
				$this->set_response(array(
					'status' => 'failed',
					'message' => $msg,
				), REST_Controller::HTTP_NOT_FOUND);
				return;
			}
			$this->set_response(array(
				'status' => 'failed',
				'message' => $msg,
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * Download full published timeseries as CSV.
	 * GET /api/indicator_dsd/data_export/{sid}
	 */
	function data_export_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			$this->load->library('indicator_duckdb_service');
			$body = $this->indicator_duckdb_service->timeseries_export_csv_body($sid);

			$this->load->helper('download');
			$name = 'indicator_timeseries_' . (int) $sid . '.csv';
			if (! force_download2($name, $body)) {
				throw new Exception('Could not start CSV download');
			}
			exit;
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Delete all published indicator data (drops project_{sid}.timeseries).
	 * POST /api/indicator_dsd/data_delete/{sid}
	 * Requires edit permission. Returns { status, dropped, row_count }.
	 */
	function data_delete_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$this->load->library('indicator_duckdb_service');
			$result = $this->indicator_duckdb_service->timeseries_drop($sid);

			if (is_array($result) && !empty($result['error'])) {
				throw new Exception(isset($result['message']) ? $result['message'] : 'Failed to drop timeseries table');
			}

			$this->Indicator_dsd_model->clear_published_data_tracking($sid);
			$this->Indicator_dsd_model->delete_indicator_csv($sid);

			$this->set_response(array(
				'status'    => 'success',
				'dropped'   => isset($result['dropped']) ? (bool) $result['dropped'] : true,
				'row_count' => isset($result['row_count']) ? (int) $result['row_count'] : 0,
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status'  => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Queue FastAPI job to recompute _ts_year / _ts_freq on published timeseries from current DSD.
	 * POST /api/indicator_dsd/data_recompute/{sid}
	 * No body; time_spec is built from the bound global data structure.
	 */
	function data_recompute_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$time_spec = $this->Indicator_dsd_model->build_duckdb_promote_time_spec($sid);
			if (empty($time_spec['time_column'])) {
				throw new Exception('No time_period column in the data structure; nothing to recompute.');
			}

			$this->load->library('indicator_duckdb_service');
			$queue = $this->indicator_duckdb_service->recompute_queue($sid, $time_spec);

			if (is_array($queue) && !empty($queue['error'])) {
				$msg = isset($queue['message']) ? $queue['message'] : 'FastAPI recompute request failed';
				$hc = isset($queue['http_code']) ? (int) $queue['http_code'] : 0;
				if ($hc === 404) {
					$this->set_response(array(
						'status' => 'failed',
						'message' => $msg,
					), REST_Controller::HTTP_NOT_FOUND);
					return;
				}
				throw new Exception($msg);
			}

			if (empty($queue['job_id'])) {
				throw new Exception('FastAPI did not return job_id');
			}

			$this->set_response(array(
				'status' => 'success',
				'job_id' => $queue['job_id'],
				'message' => isset($queue['message']) ? $queue['message'] : 'Time-derived columns recompute queued',
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Poll async job status (import, recompute, etc.).
	 * GET /api/indicator_dsd/job/{sid}?job_id=...
	 */
	function job_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			$job_id = $this->input->get('job_id');
			if ($job_id === null || $job_id === '') {
				throw new Exception('Query parameter job_id is required');
			}

			$this->load->library('indicator_duckdb_service');
			$res = $this->indicator_duckdb_service->get_job($job_id);

			if ($res['http_code'] === 200 && is_array($res['body']) && isset($res['body']['info']['project_id'])) {
				if ((string) (int) $res['body']['info']['project_id'] !== (string) (int) $sid) {
					throw new Exception('Job does not belong to this project');
				}
			}

			$out_status = ($res['http_code'] === 200) ? 'success' : 'failed';
			$this->set_response(array(
				'status' => $out_status,
				'http_code' => $res['http_code'],
				'job' => $res['body'],
				'message' => $res['message'],
			), REST_Controller::HTTP_OK);
		}
		catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Global DSD binding for this project (read-only structure when bound).
	 *
	 * GET /api/indicator_dsd/binding/{sid}
	 */
	function binding_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'view', $this->api_user);

			$this->load->model('Editor_project_dsd_model');
			$this->load->library('Data_structure_util');
			$this->load->model('Data_structure_model');

			$binding = $this->Editor_project_dsd_model->get_by_sid($sid);
			$reference = $this->data_structure_util->resolve_project_reference($sid);
			$columns = $this->Indicator_dsd_model->select_all($sid, false);
			$structure_validation = $this->Indicator_dsd_model->validate_dsd_structure($sid);

			$structure = null;
			if ($binding && !empty($binding['data_structure_id'])) {
				$structure = $this->Data_structure_model->get_structure_by_id((int) $binding['data_structure_id'], false);
			}

			$indicator_id_value = ($binding && isset($binding['indicator_id_value']))
				? trim((string) $binding['indicator_id_value'])
				: '';
			$default_indicator_id_value = $this->data_structure_util->resolve_default_indicator_id_value($sid);
			$series_idno = $this->data_structure_util->resolve_series_idno($sid);
			$indicator_id_column = $this->Indicator_dsd_model->get_column_name_by_type($sid, 'indicator_id');

			if ($binding && empty($binding['has_published_data'])) {
				$this->Indicator_dsd_model->sync_published_data_tracking_from_duckdb($sid);
				$binding = $this->Editor_project_dsd_model->get_by_sid($sid);
			}

			$has_published_data = $binding && !empty($binding['has_published_data']);
			$published_row_count = ($binding && isset($binding['published_row_count']))
				? $binding['published_row_count']
				: null;
			$data_imported_at = ($binding && isset($binding['data_imported_at']))
				? $binding['data_imported_at']
				: null;

			$import_blocked_reasons = array();
			if (!$binding) {
				$import_blocked_reasons[] = 'No data structure attached';
			}
			if (empty($structure_validation['valid'])) {
				if (!empty($structure_validation['errors'])) {
					$import_blocked_reasons[] = $structure_validation['errors'][0];
				} else {
					$import_blocked_reasons[] = 'Structure has validation errors';
				}
			}
			if ($indicator_id_value === '') {
				$import_blocked_reasons[] = 'Indicator ID value is not set';
			}

			$has_periodicity = $this->Indicator_dsd_model->project_has_periodicity_column($sid);
			$implied_freq = ($binding && isset($binding['implied_freq_code']))
				? trim((string) $binding['implied_freq_code'])
				: '';
			if (!$has_periodicity && $implied_freq === '') {
				$import_blocked_reasons[] = 'Series FREQ (SDMX) is not set — required when the structure has no FREQ column';
			}

			$this->config->load('indicator_dsd', true);

			$this->set_response(array(
				'status' => 'success',
				'bound' => $binding !== null,
				'read_only' => $binding !== null,
				'binding' => $binding,
				'data_structure_reference' => $reference,
				'global_structure' => $structure,
				'column_count' => count($columns),
				'indicator_id_value' => $indicator_id_value !== '' ? $indicator_id_value : null,
				'series_idno' => $series_idno !== '' ? $series_idno : null,
				'default_indicator_id_value' => $default_indicator_id_value !== '' ? $default_indicator_id_value : null,
				'indicator_id_column' => $indicator_id_column,
				'has_periodicity_column' => $has_periodicity,
				'needs_implied_freq_code' => !$has_periodicity,
				'implied_freq_code' => $implied_freq !== '' ? $implied_freq : null,
				'has_published_data' => $has_published_data,
				'published_row_count' => $published_row_count !== null ? (int) $published_row_count : null,
				'data_imported_at' => $data_imported_at !== null ? (int) $data_imported_at : null,
				'import_ready' => count($import_blocked_reasons) === 0,
				'import_blocked_reasons' => $import_blocked_reasons,
				'structure_validation' => array(
					'valid' => !empty($structure_validation['valid']),
					'errors' => isset($structure_validation['errors']) ? $structure_validation['errors'] : array(),
					'warnings' => isset($structure_validation['warnings']) ? $structure_validation['warnings'] : array(),
					'roles' => isset($structure_validation['roles']) ? $structure_validation['roles'] : array(),
				),
				'freq_codes' => $this->config->item('dsd_freq_codes', 'indicator_dsd') ?: array(),
			), REST_Controller::HTTP_OK);
		} catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Upload CSV, validate required DSD headers (extra CSV columns allowed), return distinct indicator_id values.
	 *
	 * POST /api/indicator_dsd/data_upload_prepare/{sid}
	 * multipart: file or upload_id; optional delimiter.
	 * Does not require indicator_id_value or implied_freq_code (those are enforced on import).
	 */
	function data_upload_prepare_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$ready = $this->Indicator_dsd_model->assert_ready_for_data_upload($sid, false, false);
			$real_path = $this->save_indicator_csv_upload($sid);

			$delimiter = $this->input->post('delimiter');
			if ($delimiter === null || $delimiter === '' || strlen($delimiter) !== 1) {
				$delimiter = ',';
			}

			$keep_extra = $this->parse_bool_request_flag($this->input->post('keep_extra_csv_columns'), false);

			$expected = $this->Indicator_dsd_model->get_dsd_column_names_for_csv($sid);
			$validation = $this->Indicator_dsd_model->validate_csv_headers_for_dsd($real_path, $expected);

			if (empty($validation['valid'])) {
				$this->set_response(array(
					'status' => 'failed',
					'headers_valid' => false,
					'message' => isset($validation['message']) ? $validation['message'] : 'CSV is missing required data structure columns',
					'missing_in_csv' => isset($validation['missing_in_csv']) ? $validation['missing_in_csv'] : array(),
					'ignored_columns' => isset($validation['ignored_columns']) ? $validation['ignored_columns'] : array(),
					'extra_in_csv' => isset($validation['ignored_columns']) ? $validation['ignored_columns'] : array(),
					'expected_columns' => $expected,
				), REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$this->load->library('indicator_duckdb_service');
			$distinct_limit = 2000;
			$distinct = $this->indicator_duckdb_service->csv_distinct(
				$sid,
				$real_path,
				$ready['indicator_column'],
				$delimiter,
				$distinct_limit
			);

			if (is_array($distinct) && !empty($distinct['error'])) {
				throw new Exception(isset($distinct['message']) ? $distinct['message'] : 'Could not read indicator values from CSV');
			}

			$indicator_values = array();
			if (!empty($distinct['items']) && is_array($distinct['items'])) {
				foreach ($distinct['items'] as $item) {
					if (!is_array($item) || !isset($item['value'])) {
						continue;
					}
					$value = trim((string) $item['value']);
					if ($value === '') {
						continue;
					}
					$indicator_values[] = array(
						'value' => $value,
						'count' => isset($item['count']) ? (int) $item['count'] : null,
					);
				}
			} elseif (!empty($distinct['values']) && is_array($distinct['values'])) {
				foreach ($distinct['values'] as $value) {
					$value = trim((string) $value);
					if ($value === '') {
						continue;
					}
					$indicator_values[] = array(
						'value' => $value,
						'count' => null,
					);
				}
			}

			$this->load->library('Data_structure_util');
			$series_idno = $this->data_structure_util->resolve_series_idno($sid);
			$series_idno_in_csv = false;
			if ($series_idno !== '') {
				foreach ($indicator_values as $item) {
					if ($item['value'] === $series_idno) {
						$series_idno_in_csv = true;
						break;
					}
				}
			}

			$import_columns = $this->Indicator_dsd_model->build_csv_import_column_names($expected, $validation, $keep_extra);

			$this->set_response(array(
				'status' => 'success',
				'headers_valid' => true,
				'expected_columns' => $expected,
				'import_columns' => $import_columns,
				'keep_extra_csv_columns' => $keep_extra,
				'indicator_column' => $ready['indicator_column'],
				'indicator_id_value' => $ready['indicator_id_value'] !== '' ? $ready['indicator_id_value'] : null,
				'indicator_values' => $indicator_values,
				'indicator_values_truncated' => !empty($distinct['truncated']),
				'indicator_values_limit' => $distinct_limit,
				'series_idno' => $series_idno !== '' ? $series_idno : null,
				'series_idno_in_csv' => $series_idno_in_csv,
				'ignored_columns' => isset($validation['ignored_columns']) ? $validation['ignored_columns'] : array(),
				'extra_in_csv' => isset($validation['ignored_columns']) ? $validation['ignored_columns'] : array(),
				'csv_row_count' => isset($distinct['row_count']) ? (int) $distinct['row_count'] : null,
			), REST_Controller::HTTP_OK);
		} catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Replace project timeseries from prepared CSV for one indicator_id value.
	 *
	 * POST /api/indicator_dsd/data_upload_import/{sid}
	 * JSON: optional { "wait": true|false (default true) } — uses bound indicator_id_value
	 */
	function data_upload_import_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$body = (array) $this->raw_json_input();
			if (array_key_exists('implied_freq_code', $body)) {
				$this->load->library('Data_structure_util');
				$this->data_structure_util->update_project_implied_freq_code(
					$sid,
					$body['implied_freq_code'],
					$this->get_api_user_id()
				);
			}
			$ready = $this->Indicator_dsd_model->assert_ready_for_data_upload($sid);
			$indicator_value = $ready['indicator_id_value'];

			$wait = !isset($body['wait']) || filter_var($body['wait'], FILTER_VALIDATE_BOOLEAN);

			$real_path = $this->indicator_staging_upload_realpath($sid);
			if (!$real_path) {
				throw new Exception('No prepared CSV found. Upload and validate a CSV first.');
			}

			$keep_extra = $this->parse_bool_request_flag(
				isset($body['keep_extra_csv_columns']) ? $body['keep_extra_csv_columns'] : null,
				false
			);

			$expected = $this->Indicator_dsd_model->get_dsd_column_names_for_csv($sid);
			$validation = $this->Indicator_dsd_model->validate_csv_headers_for_dsd($real_path, $expected);
			if (empty($validation['valid'])) {
				$msg = isset($validation['message']) ? $validation['message'] : 'CSV is missing required data structure columns';
				if (!empty($validation['missing_in_csv'])) {
					$msg .= ' Missing: ' . implode(', ', $validation['missing_in_csv']);
				}
				throw new Exception($msg);
			}

			$import_columns = $this->Indicator_dsd_model->build_csv_import_column_names($expected, $validation, $keep_extra);

			$csv_for_import = $this->Indicator_dsd_model->resolve_csv_path_for_fastapi_import(
				$real_path,
				$expected,
				$keep_extra
			);
			$import_csv_path = $csv_for_import['path'];

			$overrides = array();
			if (!empty($body['implied_freq_code'])) {
				$overrides['implied_freq_code'] = trim((string) $body['implied_freq_code']);
			}
			$time_spec = $this->Indicator_dsd_model->build_duckdb_promote_time_spec($sid, $overrides);

			$this->load->library('indicator_duckdb_service');
			$queue = $this->indicator_duckdb_service->timeseries_replace_from_csv_queue(
				$sid,
				$import_csv_path,
				$import_columns,
				$ready['indicator_column'],
				$indicator_value,
				$time_spec,
				','
			);

			if (is_array($queue) && !empty($queue['error'])) {
				throw new Exception($this->format_fastapi_error_message(
					$queue,
					'FastAPI replace-from-csv request failed'
				));
			}
			if (empty($queue['job_id'])) {
				throw new Exception('FastAPI did not return job_id');
			}

			$job_id = $queue['job_id'];
			$result = array(
				'status' => 'success',
				'job_id' => $job_id,
				'indicator_value' => $indicator_value,
			);

			if ($wait) {
				$poll = $this->indicator_duckdb_service->poll_job($job_id, 1800, 3);
				$result['job'] = $poll;
				if (!is_array($poll) || ($poll['status'] ?? '') !== 'done') {
					$err = isset($poll['error']) ? $poll['error'] : 'Import did not complete';
					throw new Exception($err);
				}

				$user_id = $this->get_api_user_id();
				$row_count = $this->Indicator_dsd_model->extract_row_count_from_import_job($poll);
				$finalize = $this->Indicator_dsd_model->finalize_indicator_data_import($sid, $user_id, $row_count);
				if (!empty($finalize['export_warning'])) {
					$result['export_warning'] = $finalize['export_warning'];
				}
				$result['message'] = 'Timeseries data imported successfully';
			} else {
				$result['message'] = 'Replace-from-csv queued; poll GET /api/indicator_dsd/job/{sid}?job_id=';
			}

			$this->set_response($result, REST_Controller::HTTP_OK);
		} catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Bind a global registry DSD to this project (structure read from registry; no per-project column table).
	 *
	 * POST /api/indicator_dsd/bind_global/{sid}
	 * Body JSON: { "data_structure_id": int } OR { "data_structure_reference": { idno|agency,name,version } }
	 */
	function bind_global_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$input = (array) $this->raw_json_input();
			$this->load->library('Data_structure_util');
			$user_id = $this->get_api_user_id();

			$indicator_id_value = null;
			if (isset($input['indicator_id_value'])) {
				$indicator_id_value = trim((string) $input['indicator_id_value']);
			}

			if (!empty($input['data_structure_id'])) {
				$summary = $this->data_structure_util->bind_project(
					$sid,
					(int) $input['data_structure_id'],
					$user_id,
					$indicator_id_value
				);
			} elseif (!empty($input['data_structure_reference']) && is_array($input['data_structure_reference'])) {
				$summary = $this->data_structure_util->bind_project_by_reference(
					$sid,
					$input['data_structure_reference'],
					$user_id,
					$indicator_id_value
				);
			} else {
				throw new Exception('data_structure_id or data_structure_reference is required');
			}

			$this->set_response(array(
				'status' => 'success',
				'result' => $summary,
			), REST_Controller::HTTP_OK);
		} catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Update project binding fields (indicator ID value).
	 *
	 * POST /api/indicator_dsd/update_binding/{sid}
	 * Body JSON: { "indicator_id_value": "..." } and/or { "implied_freq_code": "A" }
	 */
	function update_binding_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$input = (array) $this->raw_json_input();
			if (!isset($input['indicator_id_value']) && !array_key_exists('implied_freq_code', $input)) {
				throw new Exception('indicator_id_value and/or implied_freq_code is required');
			}

			$this->load->library('Data_structure_util');
			$user_id = $this->get_api_user_id();
			$result = array('sid' => (int) $sid);
			if (isset($input['indicator_id_value'])) {
				$result = array_merge($result, $this->data_structure_util->update_project_indicator_id_value(
					$sid,
					$input['indicator_id_value'],
					$user_id
				));
			}
			if (array_key_exists('implied_freq_code', $input)) {
				$freqResult = $this->data_structure_util->update_project_implied_freq_code(
					$sid,
					$input['implied_freq_code'],
					$user_id
				);
				$result = array_merge($result, $freqResult);
			}

			$this->set_response(array(
				'status' => 'success',
				'result' => $result,
			), REST_Controller::HTTP_OK);
		} catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Detach global data structure from project: remove binding, clear metadata reference,
	 * and drop published timeseries data.
	 *
	 * POST /api/indicator_dsd/unbind/{sid}
	 */
	function unbind_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$this->load->library('Data_structure_util');
			$user_id = $this->get_api_user_id();
			$result = $this->data_structure_util->unbind_project($sid, $user_id);

			$response = array(
				'status' => 'success',
				'unbound' => !empty($result['unbound']),
				'dsd_columns_deleted' => isset($result['dsd_columns_deleted']) ? (int) $result['dsd_columns_deleted'] : 0,
				'timeseries_dropped' => !empty($result['timeseries_dropped']),
			);
			if (!empty($result['warnings'])) {
				$response['warnings'] = $result['warnings'];
			}

			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (Throwable $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * @param array  $queue Result from indicator_duckdb_service (error branch)
	 * @param string $fallback
	 * @return string
	 */
	protected function format_fastapi_error_message(array $queue, $fallback = 'FastAPI request failed')
	{
		$raw = isset($queue['message']) ? trim((string) $queue['message']) : '';
		if ($raw !== '' && $raw[0] === '{') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded) && isset($decoded['detail'])) {
				$detail = $decoded['detail'];
				return is_string($detail) ? $detail : json_encode($detail);
			}
		}
		if ($raw !== '') {
			return $raw;
		}

		return $fallback;
	}

	/**
	 * Parse boolean request flag (POST field or JSON body).
	 *
	 * @param mixed $value
	 * @param bool  $default
	 * @return bool
	 */
	protected function parse_bool_request_flag($value, $default = false)
	{
		if ($value === null || $value === '') {
			return $default;
		}

		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Save uploaded CSV to project data/indicator_staging_upload.csv (rewrite headers for DuckDB).
	 *
	 * @param int $sid
	 * @return string Absolute path
	 * @throws Exception
	 */
	protected function save_indicator_csv_upload($sid)
	{
		$upload_id_raw = $this->input->post('upload_id');
		$upload_id = is_string($upload_id_raw) ? trim($upload_id_raw) : '';
		$has_file = isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name']);

		if ($upload_id !== '' && $has_file) {
			throw new Exception('Provide either a file upload or upload_id, not both');
		}

		$this->Editor_model->create_project_folder($sid);
		$dest = $this->Editor_model->resolve_project_file_path($sid, 'data/indicator_staging_upload.csv');
		if (!$dest) {
			throw new Exception('Project folder not available');
		}

		$data_dir = dirname($dest);
		if (!is_dir($data_dir)) {
			@mkdir($data_dir, 0777, true);
		}

		if ($upload_id !== '') {
			$this->load->library('Resumable_upload', null, 'uploader');
			$completed = $this->uploader->get_completed_upload($upload_id);
			if (!$completed) {
				throw new Exception('Resumable upload not found or not yet complete');
			}
			$ext = strtolower(pathinfo($completed['filename'], PATHINFO_EXTENSION));
			if ($ext !== 'csv') {
				throw new Exception('Only CSV files are supported');
			}
			if (!@copy($completed['file_path'], $dest)) {
				throw new Exception('Failed to save uploaded CSV');
			}
			$this->uploader->delete_upload($upload_id);
		} elseif ($has_file) {
			$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
			if ($ext !== 'csv') {
				throw new Exception('Only CSV files are supported');
			}
			if (!@move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
				throw new Exception('Failed to save uploaded CSV');
			}
		} else {
			throw new Exception('CSV file is required');
		}

		$real_path = $this->Editor_model->resolve_absolute_file_path($dest);
		if ($real_path === null || $real_path === '') {
			throw new Exception('Could not resolve CSV path');
		}

		$this->Indicator_dsd_model->rewrite_indicator_csv_headers_for_duckdb($real_path);

		return $real_path;
	}

	/**
	 * @param int $sid
	 * @return string|null Absolute path to data/indicator_staging_upload.csv when readable
	 */
	protected function indicator_staging_upload_realpath($sid)
	{
		$this->Editor_model->create_project_folder($sid);
		$dest = $this->Editor_model->resolve_project_file_path($sid, 'data/indicator_staging_upload.csv');
		if (!$dest || !is_file($dest) || !is_readable($dest)) {
			return null;
		}

		return $this->Editor_model->resolve_absolute_file_path($dest);
	}
}
