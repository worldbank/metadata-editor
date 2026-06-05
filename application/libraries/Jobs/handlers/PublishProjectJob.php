<?php

require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';

/**
 * Publish Project Job Handler
 * 
 * Handles background publishing of projects to NADA catalogs
 */
class PublishProjectJob implements JobHandlerInterface
{
    protected $ci;

    public function __construct()
    {
        // Get CodeIgniter instance
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_publish_model');
        $this->ci->load->model('Editor_model');
    }

    /**
     * Get the job type this handler processes
     * 
     * @return string
     */
    public function getJobType()
    {
        return 'publish_project';
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
        
        if (empty($payload['catalog_connection_id'])) {
            throw new Exception("Missing required parameter: catalog_connection_id");
        }
        
        if (empty($payload['user_id'])) {
            throw new Exception("Missing required parameter: user_id");
        }
        
        return true;
    }

    /**
     * Generate a unique hash for the job based on payload
     * 
     * @param array $payload Job payload data
     * @return string Hash string
     */
    public function generateJobHash($payload)
    {
        $hash_data = array(
            'job_type' => $this->getJobType(),
            'project_id' => $payload['project_id'],
            'catalog_connection_id' => $payload['catalog_connection_id'],
            'publish_metadata' => !empty($payload['publish_metadata']) ? 1 : 0,
            'publish_thumbnail' => !empty($payload['publish_thumbnail']) ? 1 : 0,
            'publish_resources' => !empty($payload['publish_resources']) ? 1 : 0,
            'publish_dsd' => !empty($payload['publish_dsd']) ? 1 : 0,
            'dsd_overwrite' => !empty($payload['dsd_overwrite']) ? 1 : 0,
            'publish_indicator_data' => !empty($payload['publish_indicator_data']) ? 1 : 0,
            'delete_nada_resources' => !empty($payload['delete_nada_resources']) ? 1 : 0,
        );
        
        ksort($hash_data);
        return hash('sha256', json_encode($hash_data));
    }
    
    /**
     * Process the publish project job
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
        
        $project_id = $payload['project_id'];
        $catalog_connection_id = $payload['catalog_connection_id'];
        $user_id = $payload['user_id'];
        
        $options = isset($payload['options']) ? $payload['options'] : array();
        
        $publish_metadata = isset($payload['publish_metadata']) ? $payload['publish_metadata'] : true;
        $publish_thumbnail = isset($payload['publish_thumbnail']) ? $payload['publish_thumbnail'] : true;
        $publish_resources = isset($payload['publish_resources']) ? $payload['publish_resources'] : true;
        $publish_dsd = !empty($payload['publish_dsd']);
        $dsd_overwrite = !empty($payload['dsd_overwrite']);
        $publish_indicator_data = !empty($payload['publish_indicator_data']);
        $delete_nada_resources = !empty($payload['delete_nada_resources']);

        $results = array();
        $errors = array();

        // 1. Prepare export by generating the project JSON metadata file.
        // NADA publishing reads directly from this JSON file.
        try {
            $this->ci->load->library('Project_json_writer');
            $this->ci->project_json_writer->generate_project_json($project_id);
            $results['export'] = 'Project JSON metadata generated successfully';
        } catch (Exception $e) {
            $errors['export'] = $e->getMessage();
            throw new Exception("Failed to prepare project for publishing (JSON generation): " . $e->getMessage());
        }

        // DDI used when JSON publish fails and NADA falls back to import_ddi (survey/microdata only).
        $proj_row = $this->ci->Editor_model->get_basic_info($project_id);
        if ($proj_row && ($proj_row['type'] === 'microdata' || $proj_row['type'] === 'survey')) {
            try {
                $this->ci->Editor_model->generate_project_ddi($project_id);
                $results['export_ddi'] = 'Project DDI generated for publish fallback';
            } catch (Exception $e) {
                $results['export_ddi_warning'] = $e->getMessage();
            }
        }

        $this->ci->load->library('Editor_nada_indicator_publish');
        $is_indicator = $proj_row && $this->ci->editor_nada_indicator_publish->is_indicator_project_type($proj_row['type']);

            if ($publish_metadata) {
            try {
                $results['metadata'] = $this->ci->Editor_publish_model->publish_to_catalog(
                    $project_id, 
                    $user_id, 
                    $catalog_connection_id, 
                    $options
                );
            } catch (Exception $e) {
                $errors['metadata'] = $e->getMessage();
            }
        }

        if ($is_indicator && $publish_dsd) {
            try {
                $results['dsd'] = $this->ci->Editor_publish_model->publish_indicator_extras(
                    $project_id,
                    $user_id,
                    $catalog_connection_id,
                    array(
                        'publish_dsd' => true,
                        'dsd_overwrite' => $dsd_overwrite,
                        'publish_indicator_data' => false,
                    )
                )['dsd'];
            } catch (Exception $e) {
                $errors['dsd'] = $e->getMessage();
            }
        }

        if ($is_indicator && $publish_indicator_data) {
            try {
                $dataResult = $this->ci->Editor_publish_model->publish_indicator_extras(
                    $project_id,
                    $user_id,
                    $catalog_connection_id,
                    array(
                        'publish_dsd' => false,
                        'publish_indicator_data' => true,
                    )
                );
                $results['indicator_data'] = $dataResult['data'];
            } catch (Exception $e) {
                $errors['indicator_data'] = $e->getMessage();
            }
        }

        if ($publish_thumbnail) {
            try {
                $results['thumbnail'] = $this->ci->Editor_publish_model->publish_thumbnail(
                    $project_id, 
                    $user_id, 
                    $catalog_connection_id, 
                    $options
                );
            } catch (Exception $e) {
                $errors['thumbnail'] = $e->getMessage();
            }
        }

        if ($delete_nada_resources) {
            try {
                $results['nada_resources_delete'] = $this->ci->Editor_publish_model->delete_all_nada_study_resources(
                    $project_id,
                    $user_id,
                    $catalog_connection_id
                );
            } catch (Exception $e) {
                $errors['nada_resources_delete'] = $e->getMessage();
            }
        }

        if ($publish_resources) {
            try {
                $results['resources'] = $this->ci->Editor_publish_model->publish_external_resources(
                    $project_id, 
                    $user_id, 
                    $catalog_connection_id, 
                    $options
                );
            } catch (Exception $e) {
                $errors['resources'] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $critical_failures = 0;
            $critical_attempts = 0;
            foreach (array('metadata', 'dsd', 'indicator_data') as $key) {
                $attempted = false;
                if ($key === 'metadata' && $publish_metadata) {
                    $attempted = true;
                } elseif ($key === 'dsd' && $publish_dsd) {
                    $attempted = true;
                } elseif ($key === 'indicator_data' && $publish_indicator_data) {
                    $attempted = true;
                }
                if ($attempted) {
                    $critical_attempts++;
                    if (isset($errors[$key])) {
                        $critical_failures++;
                    }
                }
            }
            if ($critical_attempts > 0 && $critical_failures === $critical_attempts) {
                throw new Exception("Publishing failed: " . json_encode($errors));
            }
        }

        return array(
            'results' => $results,
            'errors' => $errors,
            'project_id' => $project_id,
            'catalog_connection_id' => $catalog_connection_id,
            'published_at' => date('Y-m-d H:i:s')
        );
    }
}
