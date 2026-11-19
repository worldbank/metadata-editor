<?php

require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';

/**
 * Import Indicator Data Job Handler
 *
 * Background job: move uploaded CSV into the project → validate headers against bound global DSD →
 * replace project timeseries for one indicator_id value (Indicator_csv_import / FastAPI replace-from-csv).
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
        $keep_extra = !empty($payload['keep_extra_csv_columns']);
        $user_id = isset($job['user_id']) ? (int) $job['user_id'] : null;

        $this->ci->Indicator_dsd_model->assert_ready_for_data_upload($sid);

        $this->ci->load->library('Resumable_upload', null, 'uploader');
        $this->ci->uploader->assert_upload_access($upload_id, (int) $user_id);
        $completed = $this->ci->uploader->get_completed_upload($upload_id);
        if (!$completed) {
            throw new Exception('Resumable upload not found or not yet complete: ' . $upload_id);
        }

        $ext = strtolower(pathinfo($completed['filename'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new Exception('Only CSV files are supported, got: ' . $ext);
        }

        // Same flow as the editor UI: no PHP pass over the data rows; FastAPI loads, replaces and exports.
        $this->ci->load->library('Indicator_csv_import');
        $prep = $this->ci->indicator_csv_import->prepare($sid, $completed['file_path'], false, $delimiter, 3000, $user_id);
        $this->ci->uploader->delete_upload($upload_id);

        if (empty($prep['valid'])) {
            $validation = $prep['validation'];
            $msg = isset($validation['message']) ? $validation['message'] : 'CSV is missing required data structure columns';
            if (!empty($validation['missing_in_csv'])) {
                $msg .= ' Missing: ' . implode(', ', $validation['missing_in_csv']);
            }
            throw new Exception($msg);
        }

        // The distinct list is capped; only a complete list can prove the value is absent.
        $distinct = $prep['distinct'];
        $values = array();
        foreach ((isset($distinct['values']) && is_array($distinct['values']) ? $distinct['values'] : array()) as $v) {
            $values[] = (string) $v;
        }
        if (empty($distinct['truncated']) && !in_array($indicator_value, $values, true)) {
            throw new Exception(
                'Indicator value "' . $indicator_value . '" not found in CSV column "' . $prep['ready']['indicator_column'] . '"'
            );
        }

        $queued = $this->ci->indicator_csv_import->queue_import($sid, $user_id, $keep_extra, array(), $indicator_value);
        $result = $this->ci->indicator_csv_import->wait_and_finalize($sid, $queued['job_id'], $user_id);

        if (!isset($result['status']) || $result['status'] !== 'success') {
            throw new Exception(isset($result['message']) ? $result['message'] : 'Import did not complete');
        }

        $out = array(
            'status' => 'success',
            'indicator_value' => $indicator_value,
            'job_id' => $queued['job_id'],
            'row_count' => isset($result['row_count']) ? $result['row_count'] : null,
        );
        if (!empty($result['export_warning'])) {
            $out['export_warning'] = $result['export_warning'];
        }
        if (!empty($result['data_notes'])) {
            $out['data_notes'] = $result['data_notes'];
        }

        return $out;
    }
}
