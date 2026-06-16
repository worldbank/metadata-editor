<?php

require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';

/**
 * Generate a dat/micro external resource from project data files (background job).
 */
class GenerateMicrodataResourceJob implements JobHandlerInterface
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_model');
        $this->ci->load->model('Editor_datafile_model');
        $this->ci->load->library('Editor_acl');
        $this->ci->load->library('Microdata_resource_generator');
    }

    public function getJobType()
    {
        return 'generate_microdata_resource';
    }

    public function validatePayload($payload)
    {
        if (empty($payload['project_id'])) {
            throw new Exception('Missing required parameter: project_id');
        }

        if (!is_numeric($payload['project_id'])) {
            throw new Exception('Invalid project_id: must be numeric');
        }

        $project_id = (int) $payload['project_id'];
        $project = $this->ci->Editor_model->get_basic_info($project_id);
        if (!$project) {
            throw new Exception('Project not found: ' . $project_id);
        }

        $type = isset($project['type']) ? strtolower((string) $project['type']) : '';
        if ($type !== 'survey' && $type !== 'microdata') {
            throw new Exception('Microdata resource generation is only supported for survey/microdata projects');
        }

        $export_format = isset($payload['export_format']) ? strtolower(trim((string) $payload['export_format'])) : '';
        if ($export_format === '') {
            throw new Exception('Missing required parameter: export_format');
        }

        if (!in_array($export_format, Microdata_resource_generator::SUPPORTED_FORMATS, true)) {
            throw new Exception('Unsupported export format: ' . $export_format);
        }

        $datafiles = $this->ci->Editor_datafile_model->select_all($project_id);
        if (empty($datafiles)) {
            throw new Exception('Project has no data files');
        }

        if (!empty($payload['file_ids'])) {
            if (!is_array($payload['file_ids'])) {
                throw new Exception('file_ids must be an array');
            }
            foreach ($payload['file_ids'] as $file_id) {
                if (!isset($datafiles[(string) $file_id])) {
                    throw new Exception('Data file not found: ' . $file_id);
                }
            }
        }

        return true;
    }

    public function generateJobHash($payload)
    {
        $file_ids = array();
        if (!empty($payload['file_ids']) && is_array($payload['file_ids'])) {
            $file_ids = array_map('strval', $payload['file_ids']);
            sort($file_ids);
        }

        $hash_data = array(
            'job_type' => $this->getJobType(),
            'project_id' => (int) $payload['project_id'],
            'export_format' => strtolower(trim((string) $payload['export_format'])),
            'export_version' => isset($payload['export_version']) ? (string) $payload['export_version'] : null,
            'file_ids' => $file_ids,
            'overwrite' => !empty($payload['overwrite']) ? 1 : 0,
            'resource_id' => isset($payload['resource_id']) ? (int) $payload['resource_id'] : null,
        );

        ksort($hash_data);
        return hash('sha256', json_encode($hash_data));
    }

    public function process($job, $payload)
    {
        $this->validatePayload($payload);

        if (empty($job['user_id'])) {
            throw new Exception('User ID is required for generate_microdata_resource job');
        }

        $user_id = (int) $job['user_id'];
        $user = $this->ci->ion_auth->get_user($user_id);
        if (!$user) {
            throw new Exception('User not found: ' . $user_id);
        }

        $project_id = (int) $payload['project_id'];
        $this->ci->editor_acl->user_has_project_access($project_id, 'edit', $user);

        $options = array(
            'export_format' => strtolower(trim((string) $payload['export_format'])),
            'user_id' => $user_id,
        );

        if (isset($payload['export_version'])) {
            $options['export_version'] = $payload['export_version'];
        }
        if (!empty($payload['file_ids']) && is_array($payload['file_ids'])) {
            $options['file_ids'] = $payload['file_ids'];
        }
        if (array_key_exists('zip', $payload)) {
            $options['zip'] = $payload['zip'];
        }
        if (!empty($payload['overwrite'])) {
            $options['overwrite'] = true;
        }
        if (!empty($payload['resource_id'])) {
            $options['resource_id'] = (int) $payload['resource_id'];
        }
        if (!empty($payload['refresh_description'])) {
            $options['refresh_description'] = true;
        }
        if (!empty($payload['max_wait_seconds'])) {
            $options['max_wait_seconds'] = (int) $payload['max_wait_seconds'];
        }

        $result = $this->ci->microdata_resource_generator->generate($project_id, $options);

        if (isset($result['status']) && $result['status'] === 'exists') {
            return array(
                'status' => 'skipped',
                'message' => isset($result['message']) ? $result['message'] : 'Resource already exists for this format',
                'project_id' => $project_id,
                'export_format' => $options['export_format'],
                'resource' => isset($result['resource']) ? $result['resource'] : null,
                'resource_id' => isset($result['resource_id']) ? $result['resource_id'] : null,
            );
        }

        if (isset($result['status']) && $result['status'] !== 'success') {
            throw new Exception(isset($result['message']) ? $result['message'] : 'Microdata resource generation failed');
        }

        return array(
            'status' => 'success',
            'project_id' => $project_id,
            'export_format' => $options['export_format'],
            'resource_id' => isset($result['resource_id']) ? $result['resource_id'] : null,
            'resource' => isset($result['resource']) ? $result['resource'] : null,
            'filename' => isset($result['filename']) ? $result['filename'] : null,
            'links_count' => isset($result['links']) && is_array($result['links']) ? count($result['links']) : 0,
        );
    }
}
