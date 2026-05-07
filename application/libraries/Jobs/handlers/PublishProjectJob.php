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
            'catalog_connection_id' => $payload['catalog_connection_id']
            // We omit timestamp so that duplicate requests for same project+catalog concurrently are prevented
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
            // Throw exception if all requested operations failed, or maybe just return errors in the result
            if (($publish_metadata && isset($errors['metadata'])) && 
                ($publish_resources && isset($errors['resources']))) {
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
