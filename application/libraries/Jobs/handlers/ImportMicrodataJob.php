<?php

require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';

/**
 * Microdata Import Job Handler
 * 
 * Handles microdata file import jobs that process data files,
 * generate data dictionaries, and export to CSV via FastAPI
 */
class ImportMicrodataJob implements JobHandlerInterface
{
    private $ci;
    
    public function __construct()
    {
        // Get CodeIgniter instance
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_model');
        $this->ci->load->model('Editor_datafile_model');
        $this->ci->load->library('Editor_acl');
    }
    
    /**
     * Get the job type this handler processes
     * 
     * @return string
     */
    public function getJobType()
    {
        return 'import_microdata';
    }
    
    /**
     * Validate the job payload
     * 
     * @param array $payload Job payload data
     * @throws Exception If validation fails
     * @return bool True if valid
     */
    public function validatePayload($payload)
    {
        if (empty($payload['project_id'])) {
            throw new Exception("Missing required parameter: project_id");
        }
        
        if (empty($payload['file_id'])) {
            throw new Exception("Missing required parameter: file_id");
        }
        
        // Validate project exists
        $project = $this->ci->Editor_model->get_row($payload['project_id']);
        if (!$project) {
            throw new Exception("Project not found: {$payload['project_id']}");
        }
        
        // Validate file_id exists for this project
        $datafile = $this->ci->Editor_datafile_model->data_file_by_id($payload['project_id'], $payload['file_id']);
        if (!$datafile) {
            throw new Exception("Data file not found: {$payload['file_id']} for project {$payload['project_id']}");
        }
        
        return true;
    }
    
    /**
     * Generate a unique hash for the job based on payload
     * Hash includes project_id and file source for idempotency
     * 
     * @param array $payload Job payload data
     * @return string Hash string (SHA256 hex)
     */
    public function generateJobHash($payload)
    {
        // Hash is based on project_id and file_id for idempotency
        // This ensures duplicate import jobs for the same file are detected
        $hash_data = array(
            'job_type' => $this->getJobType(),
            'project_id' => isset($payload['project_id']) ? $payload['project_id'] : null,
            'file_id' => isset($payload['file_id']) ? $payload['file_id'] : null
        );
        
        // Sort array to ensure consistent hash generation
        ksort($hash_data);
        
        // Generate SHA256 hash
        return hash('sha256', json_encode($hash_data));
    }
    
    /**
     * Process the microdata import job
     * 
     * @param array $job Full job data from database
     * @param array $payload Decoded payload data
     * @return array Result data
     * @throws Exception If processing fails
     */
    public function process($job, $payload)
    {
        // Validate payload first
        $this->validatePayload($payload);
        
        // Get user_id from job (set when job was enqueued)
        if (empty($job['user_id'])) {
            throw new Exception("User ID is required for microdata import job");
        }
        
        $user_id = $job['user_id'];
        $project_id = $payload['project_id'];
        
        // Get user object from user_id
        $user = $this->ci->ion_auth->get_user($user_id);
        if (!$user) {
            throw new Exception("User not found: {$user_id}");
        }
        
        // Validate user has access to the project (edit permission required)
        $permission = 'edit';
        $this->ci->editor_acl->user_has_project_access(
            $project_id,
            $permission,
            $user
        );
        
        $file_id = $payload['file_id'];
        
        // Get datafile record
        $datafile = $this->ci->Editor_datafile_model->data_file_by_id($project_id, $file_id);
        if (!$datafile) {
            throw new Exception("Data file not found: {$file_id}");
        }
        
        // Get the file path (check for CSV first if variables already imported)
        $data_file_var_count = $this->ci->Editor_datafile_model->get_file_varcount($project_id, $file_id);
        
        if ($data_file_var_count == 0) {
            $datafile_path = $this->ci->Editor_datafile_model->get_file_path($project_id, $file_id);
        } else {
            $datafile_path = $this->ci->Editor_datafile_model->get_file_csv_path($project_id, $file_id);
            
            if (!file_exists($datafile_path)) {
                $datafile_path = $this->ci->Editor_datafile_model->get_file_path($project_id, $file_id);
            }
        }
        
        if (!$datafile_path || !file_exists($datafile_path)) {
            throw new Exception("Data file not found: {$file_id} at path {$datafile_path}");
        }
        
        // Load DataUtils library
        $this->ci->load->library('DataUtils');
        $this->ci->load->model('Editor_variable_model');
        
        // Prepare data dictionary parameters
        $dict_params = $this->ci->datautils->prepare_data_dictionary_params($project_id, $file_id, $datafile_path);
        
        // Step 1: Call FastAPI process-microdata-queue endpoint
        $api_response = $this->ci->datautils->process_microdata_queue($datafile_path, $dict_params);
        
        $status_code = isset($api_response['status_code']) ? $api_response['status_code'] : 500;
        
        if ($status_code < 200 || $status_code >= 300) {
            throw new Exception("FastAPI request failed with status {$status_code}: " . json_encode($api_response['response']));
        }
        
        // Extract FastAPI job_id from response
        $fastapi_job_id = null;
        if (isset($api_response['response']['job_id'])) {
            $fastapi_job_id = $api_response['response']['job_id'];
        } elseif (isset($api_response['response']['id'])) {
            $fastapi_job_id = $api_response['response']['id'];
        } else {
            throw new Exception("FastAPI response missing job_id: " . json_encode($api_response['response']));
        }
        
        // Step 2: Poll FastAPI job until completion
        $poll_interval = 3; // seconds between checks
        $max_wait_time = 1800; // 30 minutes maximum wait time
        $start_time = time();
        $last_db_ping = null;

        $this->ci->load->library('db_keepalive');
        
        $fastapi_completed = false;
        $fastapi_result = null;
        
        while ((time() - $start_time) < $max_wait_time) {
            $this->ci->db_keepalive->ping_if_due($last_db_ping);

            // Check FastAPI job status
            $status_response = $this->ci->datautils->get_job_status($fastapi_job_id);
            
            $status_http_code = isset($status_response['status_code']) ? $status_response['status_code'] : 500;
            
            if ($status_http_code != 200) {
                throw new Exception("Failed to get FastAPI job status: HTTP {$status_http_code}");
            }
            
            $fastapi_status = isset($status_response['response']['status']) ? $status_response['response']['status'] : '';
            
            if ($fastapi_status === 'done' || $fastapi_status === 'completed') {
                $fastapi_completed = true;
                $fastapi_result = $status_response['response'];
                break;
            }
            
            if ($fastapi_status === 'failed' || $fastapi_status === 'error') {
                $body = isset($status_response['response']) && is_array($status_response['response'])
                    ? $status_response['response'] : array();
                $error_msg = isset($body['message']) && $body['message'] !== ''
                    ? $body['message']
                    : (isset($body['detail']) && is_string($body['detail']) ? $body['detail'] : 'FastAPI job failed');
                throw new Exception("FastAPI job failed: {$error_msg}");
            }
            
            // Still processing, wait before next check
            sleep($poll_interval);
        }
        
        if (!$fastapi_completed) {
            throw new Exception("FastAPI job did not complete within {$max_wait_time} seconds (timeout)");
        }

        // Refresh DB connection after long idle poll (avoids "MySQL server has gone away")
        $this->ci->db_keepalive->ping();
        
        // Step 3: Process FastAPI results - import variables and update case_count
        $variable_import_result = array();
        
        // Update case_count if available (from data_dictionary.rows)
        if (isset($fastapi_result['data']['data_dictionary']['rows'])) {
            $datafile = $this->ci->Editor_datafile_model->data_file_by_id($project_id, $file_id);
            if ($datafile) {
                $this->ci->Editor_datafile_model->update($datafile['id'], array('case_count' => $fastapi_result['data']['data_dictionary']['rows']));
            }
        }
        
        // Import variables from data dictionary
        if (isset($fastapi_result['data']['data_dictionary']['variables'])) {
            $variable_import_result = $this->ci->Editor_variable_model->bulk_upsert_dictionary(
                $project_id,
                $file_id,
                $fastapi_result['data']['data_dictionary']['variables']
            );
        }
        
        // Step 4: Confirm working CSV exists; keep source file (do not delete or repoint file_physical_name)
        $csv_file_path = $this->ci->Editor_datafile_model->check_csv_exists($project_id, $file_id);
        $csv_basename = $csv_file_path ? basename($csv_file_path) : null;

        // Refresh source_* columns (format + version) via FastAPI name-labels on the source file
        $datafile = $this->ci->Editor_datafile_model->data_file_by_id($project_id, $file_id);
        $source_path = null;
        if ($datafile && !empty($datafile['file_physical_name'])) {
            $project_folder = $this->ci->Editor_model->get_project_folder($project_id);
            $source_path = $project_folder . '/data/' . $datafile['file_physical_name'];
            if (!file_exists($source_path)) {
                $source_path = null;
            }
        }
        if (!$source_path) {
            $source_path = $datafile_path;
        }

        $source_update = $this->ci->Editor_datafile_model->build_source_fields_from_path($source_path);
        try {
            $name_labels = $this->ci->datautils->get_file_name_labels($source_path, array(
                'include_file_info' => true,
                'columns_only' => true,
            ));
            if (isset($name_labels['file_info']) && is_array($name_labels['file_info'])) {
                $source_update = array_merge(
                    $source_update,
                    $this->ci->Editor_datafile_model->source_fields_from_file_info($name_labels['file_info'])
                );
            }
        } catch (Exception $e) {
            // Extension-based source_update still applied; version may remain null
            log_message('error', 'ImportMicrodataJob: name-labels file_info failed: ' . $e->getMessage());
        }
        if (!empty($source_update)) {
            $datafile = $this->ci->Editor_datafile_model->data_file_by_id($project_id, $file_id);
            if ($datafile) {
                $this->ci->Editor_datafile_model->update($datafile['id'], $source_update);
            }
        }

        // store_data=0: remove physical data after metadata import (source + CSV)
        $datafile = $this->ci->Editor_datafile_model->data_file_by_id($project_id, $file_id);
        if ($datafile && isset($datafile['store_data']) && (int) $datafile['store_data'] === 0) {
            $this->ci->Editor_datafile_model->cleanup($project_id, $file_id);
        }

        // Return final result
        return array(
            'fastapi_job_id' => $fastapi_job_id,
            'file_id' => $file_id,
            'project_id' => $project_id,
            'datafile_path' => $datafile_path,
            'variables_imported' => count($variable_import_result),
            'case_count' => isset($fastapi_result['data']['data_dictionary']['rows']) ? $fastapi_result['data']['data_dictionary']['rows'] : null,
            'csv_file' => $csv_basename,
            'fastapi_status' => $fastapi_status,
            'message' => 'Microdata import completed successfully'
        );
    }
}
