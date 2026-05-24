<?php

require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';

/**
 * Import Indicator Data Job Handler
 *
 * Background job: copy uploaded CSV → validate headers against bound global DSD →
 * replace project timeseries for one indicator_id value (FastAPI replace-from-csv).
 *
 * Payload:
 *   project_id       (int)    required
 *   upload_id        (string) required — completed resumable upload
 *   indicator_value  (string) required
 *   delimiter        (string) optional (default ',')
 */
class ImportIndicatorDataJob implements JobHandlerInterface
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_model');
        $this->ci->load->model('Indicator_dsd_model');
    }

    public function getJobType()
    {
        return 'import_indicator_data';
    }

    public function validatePayload($payload)
    {
        if (empty($payload['project_id'])) {
            throw new Exception('Missing required parameter: project_id');
        }

        if (empty($payload['upload_id'])) {
            throw new Exception('Missing required parameter: upload_id');
        }

        if (!isset($payload['indicator_value']) || trim((string) $payload['indicator_value']) === '') {
            throw new Exception('Missing required parameter: indicator_value');
        }

        $sid = (int) $payload['project_id'];
        $project = $this->ci->Editor_model->get_row($sid);
        if (!$project) {
            throw new Exception('Project not found: ' . $sid);
        }

        $allowed_types = array('indicator', 'timeseries');
        if (!in_array($project['type'], $allowed_types, true)) {
            throw new Exception('Project must be of type indicator or timeseries');
        }

        $this->ci->Indicator_dsd_model->assert_ready_for_data_upload($sid);

        return true;
    }

    public function generateJobHash($payload)
    {
        $hash_data = array(
            'job_type'   => $this->getJobType(),
            'project_id' => isset($payload['project_id']) ? (int) $payload['project_id'] : null,
            'upload_id'  => isset($payload['upload_id']) ? (string) $payload['upload_id'] : null,
        );
        ksort($hash_data);

        return hash('sha256', json_encode($hash_data));
    }

    public function process($job, $payload)
    {
        $sid = (int) $payload['project_id'];
        $upload_id = (string) $payload['upload_id'];
        $delimiter = isset($payload['delimiter']) && strlen((string) $payload['delimiter']) === 1
            ? (string) $payload['delimiter']
            : ',';
        $indicator_value = trim((string) $payload['indicator_value']);

        $ready = $this->ci->Indicator_dsd_model->assert_ready_for_data_upload($sid);
        $this->ci->load->library('indicator_duckdb_service');

        $this->ci->Editor_model->create_project_folder($sid);
        $folder = $this->ci->Editor_model->get_project_folder($sid);
        if (!$folder) {
            throw new Exception('Project folder not available for project ' . $sid);
        }

        $data_dir = $folder . '/data';
        if (!is_dir($data_dir)) {
            @mkdir($data_dir, 0777, true);
        }

        $dest = $data_dir . '/indicator_staging_upload.csv';

        $this->ci->load->library('Resumable_upload', null, 'uploader');
        $completed = $this->ci->uploader->get_completed_upload($upload_id);
        if (!$completed) {
            throw new Exception('Resumable upload not found or not yet complete: ' . $upload_id);
        }

        $ext = strtolower(pathinfo($completed['filename'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new Exception('Only CSV files are supported, got: ' . $ext);
        }

        if (!@copy($completed['file_path'], $dest)) {
            throw new Exception('Failed to copy uploaded CSV to project folder');
        }

        $this->ci->uploader->delete_upload($upload_id);

        $real_path = realpath($dest);
        if ($real_path === false) {
            throw new Exception('Could not resolve CSV path after copy');
        }

        $this->ci->Indicator_dsd_model->rewrite_indicator_csv_headers_for_duckdb($real_path);

        $expected = $this->ci->Indicator_dsd_model->get_dsd_column_names_for_csv($sid);
        $validation = $this->ci->Indicator_dsd_model->validate_csv_headers_for_dsd($real_path, $expected);

        if (empty($validation['valid'])) {
            $msg = isset($validation['message']) ? $validation['message'] : 'CSV is missing required data structure columns';
            if (!empty($validation['missing_in_csv'])) {
                $msg .= ' Missing: ' . implode(', ', $validation['missing_in_csv']);
            }
            throw new Exception($msg);
        }

        $keep_extra = !empty($payload['keep_extra_csv_columns']);
        $import_columns = $this->ci->Indicator_dsd_model->build_csv_import_column_names($expected, $validation, $keep_extra);

        $csv_for_import = $this->ci->Indicator_dsd_model->resolve_csv_path_for_fastapi_import(
            $real_path,
            $expected,
            $keep_extra
        );
        $import_csv_path = $csv_for_import['path'];

        $distinct = $this->ci->indicator_duckdb_service->csv_distinct(
            $sid,
            $real_path,
            $ready['indicator_column'],
            $delimiter
        );

        if (is_array($distinct) && !empty($distinct['error'])) {
            throw new Exception(isset($distinct['message']) ? $distinct['message'] : 'Could not read indicator values from CSV');
        }

        $values = array();
        if (!empty($distinct['values']) && is_array($distinct['values'])) {
            $values = array_map('strval', $distinct['values']);
        }
        if (!in_array($indicator_value, $values, true)) {
            throw new Exception(
                'Indicator value "' . $indicator_value . '" not found in CSV column "' . $ready['indicator_column'] . '"'
            );
        }

        $time_spec = $this->ci->Indicator_dsd_model->build_duckdb_promote_time_spec($sid);
        $queue = $this->ci->indicator_duckdb_service->timeseries_replace_from_csv_queue(
            $sid,
            $import_csv_path,
            $import_columns,
            $ready['indicator_column'],
            $indicator_value,
            $time_spec,
            $delimiter
        );

        if (is_array($queue) && !empty($queue['error'])) {
            throw new Exception(isset($queue['message']) ? $queue['message'] : 'Replace-from-csv queue failed');
        }
        if (empty($queue['job_id'])) {
            throw new Exception('FastAPI did not return job_id');
        }

        $poll = $this->ci->indicator_duckdb_service->poll_job($queue['job_id'], 1800, 3);
        if (!is_array($poll) || ($poll['status'] ?? '') !== 'done') {
            $err = isset($poll['error']) ? $poll['error'] : 'Replace-from-csv did not complete';
            throw new Exception($err);
        }

        $user_id = isset($job['user_id']) ? (int) $job['user_id'] : null;
        $row_count = $this->ci->Indicator_dsd_model->extract_row_count_from_import_job($poll);
        $this->ci->Indicator_dsd_model->finalize_indicator_data_import($sid, $user_id, $row_count);

        return array(
            'status' => 'success',
            'indicator_value' => $indicator_value,
            'job_id' => $queue['job_id'],
        );
    }
}
