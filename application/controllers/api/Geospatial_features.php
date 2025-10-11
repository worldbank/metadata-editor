<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Geospatial_features extends MY_REST_Controller
{
    private $api_user;
    private $user_id;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper("date");
        $this->load->model("Geospatial_features_model");
        $this->load->model("Geospatial_feature_chars_model");
        $this->load->model("Editor_model");
        $this->load->library("Form_validation");
        $this->load->library("Audit_log");
        $this->load->library("Editor_acl");
        $this->load->library("Geospatial_api_client");
        
        $this->is_authenticated_or_die();
        $this->api_user = $this->api_user();
        $this->user_id = $this->get_api_user_id();
    }

    function _auth_override_check()
    {
        if ($this->session->userdata('user_id')){
            return true;
        }
        parent::_auth_override_check();
    }

    /**
     * Get geospatial features for a specific project or single feature by ID
     */
    function index_get($sid = null, $id = null)
    {
        try{
            if($id){
                return $this->single_get($id);
            }

            if(!$sid){
                throw new Exception("Missing parameter: project ID (sid)");
            }

            $this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
            $result = $this->Geospatial_features_model->select_by_project($sid);
            
            $response = array(
                'status' => 'success',
                'project_id' => $sid,
                'total' => count($result),
                'found' => count($result),
                'features' => $result
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
     * Get single geospatial feature by ID
     */
    function single_get($id = null)
    {
        try{
            if(!$id){
                throw new Exception("Missing parameter: ID");
            }

            $result = $this->Geospatial_features_model->select_single($id);

            if(!$result){
                throw new Exception("FEATURE_NOT_FOUND");
            }

            $this->editor_acl->user_has_project_access($result['sid'], 'view', $this->api_user);
            array_walk($result, 'unix_date_to_gmt', array('created','changed'));
            
            $response = array(
                'status' => 'success',
                'feature' => $result
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
     * Get geospatial feature by code
     */
    function code_get($code = null)
    {
        try{
            if(!$code){
                throw new Exception("Missing parameter: code");
            }

            $result = $this->Geospatial_features_model->select_by_code($code);

            if(!$result){
                throw new Exception("FEATURE_NOT_FOUND");
            }

            $this->editor_acl->user_has_project_access($result['sid'], 'view', $this->api_user);
            array_walk($result, 'unix_date_to_gmt', array('created','changed'));
            
            $response = array(
                'status' => 'success',
                'feature' => $result
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
     * Create new geospatial feature
     */
    function index_post()
    {		
        try{			
            $options = $this->raw_json_input();
            
            if (!isset($options['sid'])) {
                throw new Exception("Missing parameter: project ID (sid)");
            }

            $this->editor_acl->user_has_project_access($options['sid'], 'edit', $this->api_user);
            
            $options['created_by'] = $this->user_id;
            $options['changed_by'] = $this->user_id;
            
            $new_feature_id = $this->Geospatial_features_model->insert($options);

            $this->Editor_model->set_project_options($options['sid'],$options=array(
                'changed_by'=>$this->user_id,
                'changed'=>date("U")
            ));

            $this->audit_log->log_event(
                $obj_type = 'geospatial_feature',
                $obj_id = $new_feature_id,
                $action = 'create', 
                $metadata = array(
                    'feature_name' => isset($options['name']) ? $options['name'] : '',
                    'feature_code' => isset($options['code']) ? $options['code'] : ''
                ),
                $this->user_id);

            $output = array(
                'status' => 'success',
                'feature_id' => $new_feature_id
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Update geospatial feature
     */
    function update_post($id = null)
    {		
        try{
            if (!$id){
                throw new Exception("Missing parameter: ID");
            }

            $result = $this->Geospatial_features_model->select_single($id);

            if (!$result) {
                throw new Exception("Feature not found");
            }

            $this->editor_acl->user_has_project_access($result['sid'], 'edit', $this->api_user);
            
            $options = $this->raw_json_input();			
            $options['changed_by'] = $this->user_id;
            
            $this->Geospatial_features_model->update($id, $options);

            $this->Editor_model->set_project_options($options['sid'],$options=array(
                'changed_by'=>$this->user_id,
                'changed'=>date("U")
            ));

            $this->audit_log->log_event(
                $obj_type = 'geospatial_feature',
                $obj_id = $id,
                $action = 'update', 
                $metadata = array(
                    'feature_name' => isset($options['name']) ? $options['name'] : '',
                    'feature_code' => isset($options['code']) ? $options['code'] : ''
                ),
                $this->user_id);

            $output = array(
                'status' => 'success',
                'message' => 'Feature updated successfully'
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Delete geospatial feature
     */
    function index_delete($id = null)
    {
        return $this->delete_post($id);
    }

    function delete_post($id = null)
    {		
        try{
            if (!$id){
                throw new Exception("Missing parameter: ID");
            }

            $result = $this->Geospatial_features_model->select_single($id);
            if (!$result) {
                throw new Exception("Feature not found");
            }

            $this->editor_acl->user_has_project_access($result['sid'], 'delete', $this->api_user);
            
            if (!empty($result['file_name'])) {
                $this->Geospatial_features_model->delete_associated_file_if_unused($result['sid'], $result['file_name'], $id);
            }
            
            $this->Geospatial_features_model->delete($id);

            $this->Editor_model->set_project_options($result['sid'],$options=array(
                'changed_by'=>$this->user_id,
                'changed'=>date("U")
            ));

            $this->audit_log->log_event(
                $obj_type = 'geospatial_feature',
                $obj_id = $id,
                $action = 'delete', 
                $metadata = null,
                $this->user_id);

            $output = array(
                'status' => 'success',
                'message' => 'Feature deleted successfully'
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Get characteristics for a specific feature
     */
    function characteristics_get($feature_id = null)
    {
        try{
            if (!$feature_id){
                throw new Exception("Missing parameter: feature ID");
            }

            $feature = $this->Geospatial_features_model->select_single($feature_id);

            if (!$feature) {
                throw new Exception("Feature not found");
            }

            $this->editor_acl->user_has_project_access($feature['sid'], 'view', $this->api_user);
            $result = $this->Geospatial_feature_chars_model->select_by_feature_id($feature_id);

            if ($result){
                array_walk($result, 'unix_date_to_gmt', array('created','changed'));
            }

            $response = array(
                'status' => 'success',
                'feature_id' => $feature_id,
                'found' => count($result),
                'characteristics' => $result
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
     * Add characteristic to a feature
     */
    function add_characteristic_post($feature_id = null)
    {		
        try{
            if (!$feature_id){
                throw new Exception("Missing parameter: feature ID");
            }

            $feature = $this->Geospatial_features_model->select_single($feature_id);
            if (!$feature) {
                throw new Exception("Feature not found");
            }

            $this->editor_acl->user_has_project_access($feature['sid'], 'edit', $this->api_user);

            $options = $this->raw_json_input();
            $options['feature_id'] = $feature_id;
            $options['created_by'] = $this->user_id;
            $options['changed_by'] = $this->user_id;
            
            $new_char_id = $this->Geospatial_feature_chars_model->insert($options);

            $this->Editor_model->set_project_options($feature['sid'],$options=array(
                'changed_by'=>$this->user_id,
                'changed'=>date("U")
            ));

            $this->audit_log->log_event(
                $obj_type = 'geospatial_feature_characteristic',
                $obj_id = $new_char_id,
                $action = 'create', 
                $metadata = array(
                    'feature_id' => $feature_id,
                    'characteristic_name' => isset($options['name']) ? $options['name'] : ''
                ),
                $this->user_id);

            $output = array(
                'status' => 'success',
                'characteristic_id' => $new_char_id
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Update characteristic
     */
    function update_characteristic_post($char_id = null)
    {		
        try{
            if (!$char_id){
                throw new Exception("Missing parameter: characteristic ID");
            }

            $characteristic = $this->Geospatial_feature_chars_model->select_single($char_id);
            if (!$characteristic) {
                throw new Exception("Characteristic not found");
            }

            $feature = $this->Geospatial_features_model->select_single($characteristic['feature_id']);
            if (!$feature) {
                throw new Exception("Feature not found");
            }

            $this->editor_acl->user_has_project_access($feature['sid'], 'edit', $this->api_user);
            
            $options = $this->raw_json_input();			
            $options['changed_by'] = $this->user_id;
            
            $this->Geospatial_feature_chars_model->update($char_id, $options);

            $this->Editor_model->set_project_options($feature['sid'],$options=array(
                'changed_by'=>$this->user_id,
                'changed'=>date("U")
            ));

            $this->audit_log->log_event(
                $obj_type = 'geospatial_feature_characteristic',
                $obj_id = $char_id,
                $action = 'update', 
                $metadata = array(
                    'characteristic_name' => isset($options['name']) ? $options['name'] : ''
                ),
                $this->user_id);

            $output = array(
                'status' => 'success',
                'message' => 'Characteristic updated successfully'
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Delete characteristic
     */
    function delete_characteristic_post($char_id = null)
    {		
        try{
            if (!$char_id){
                throw new Exception("Missing parameter: characteristic ID");
            }

            $characteristic = $this->Geospatial_feature_chars_model->select_single($char_id);
            if (!$characteristic) {
                throw new Exception("Characteristic not found");
            }

            $feature = $this->Geospatial_features_model->select_single($characteristic['feature_id']);
            if (!$feature) {
                throw new Exception("Feature not found");
            }

            $this->editor_acl->user_has_project_access($feature['sid'], 'delete', $this->api_user);
            $this->Geospatial_feature_chars_model->delete($char_id);

            $this->Editor_model->set_project_options($feature['sid'],$options=array(
                'changed_by'=>$this->user_id,
                'changed'=>date("U")
            ));

            $this->audit_log->log_event(
                $obj_type = 'geospatial_feature_characteristic',
                $obj_id = $char_id,
                $action = 'delete', 
                $metadata = null,
                $this->user_id);

            $output = array(
                'status' => 'success',
                'message' => 'Characteristic deleted successfully'
            );

            $this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Extract layers information from a geospatial file using FastAPI
     */
    function extract_layers_post()
    {
        try{
            $options = $this->raw_json_input();
            $file_path = isset($options['file_path']) ? $options['file_path'] : null;
            
            if (!$file_path) {
                throw new Exception("Missing parameter: file_path");
            }

            if (!file_exists($file_path)) {
                throw new Exception("File not found: {$file_path}");
            }

            $absolute_path = realpath($file_path);
            if (!$absolute_path) {
                throw new Exception("Could not resolve file path: {$file_path}");
            }

            $api_client = $this->geospatial_api_client;

            $result = $api_client->get_file_layers($absolute_path);
            $this->set_response($result, REST_Controller::HTTP_OK);
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
	 * Get job status from FastAPI
	 */
	function job_status_get()
	{
		try{
			$job_id = $this->get('job_id');
			
			if (!$job_id) {
				throw new Exception("Missing parameter: job_id");
			}

			$api_client = $this->geospatial_api_client;

			$result = $api_client->get_job_status($job_id);

			$this->set_response($result, REST_Controller::HTTP_OK);
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
	 * Get metadata for specific layers using metadata-queue endpoint
	 */
	function metadata_queue_post($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);
			$user_id=$this->get_api_user_id();
			$user=$this->api_user();

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

			$options = $this->raw_json_input();
			$file_path = isset($options['file_path']) ? $options['file_path'] : null;
			$layer_name = isset($options['layer_name']) ? $options['layer_name'] : null;
			
			if (!$file_path) {
				throw new Exception("Missing parameter: file_path");
			}
			
			if (!$layer_name) {
				throw new Exception("Missing parameter: layer_name");
			}

			$api_client = $this->geospatial_api_client;

			$result = $api_client->start_metadata_job($file_path, $layer_name);
			$this->set_response($result, REST_Controller::HTTP_OK);
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
     * Start geospatial layer analysis jobs
     * 
     * 
     * 
	 */
	function geospatial_analyze_post($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$user_id=$this->get_api_user_id();
			$user=$this->api_user();

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

			$options = $this->raw_json_input();
			$file_paths = isset($options['file_paths']) ? $options['file_paths'] : null;
			
			if (!$file_paths || !is_array($file_paths)) {
				throw new Exception("Missing parameter: file_paths array");
			}

			if (empty($file_paths)) {
				throw new Exception("No files provided for analysis");
			}

			$api_client = $this->geospatial_api_client;

			// Start analysis jobs for each file
			$job_results = array();
			foreach ($file_paths as $file_path) {
				try {
					$job_result = $api_client->start_layer_analysis_job($file_path);
					$job_results[] = array(
						'file_path' => $file_path,
						'job_id' => $job_result['job_id'],
						'status' => $job_result['status'],
						'message' => $job_result['message']
					);
				} catch (Exception $e) {
					$job_results[] = array(
						'file_path' => $file_path,
						'job_id' => null,
						'status' => 'error',
						'message' => $e->getMessage()
					);
				}
			}
			
			$output = array(
				'status' => 'success',
				'jobs' => $job_results,
				'message' => 'Layer analysis jobs started'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
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
     * Check job status and create database feature entries when job is completed
     * 
     * 
	 */
	function metadata_import_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);
			$user_id=$this->get_api_user_id();
			$user=$this->api_user();

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

			$job_id = $this->input->get('job_id');
			if (!$job_id) {
				throw new Exception("Missing parameter: job_id");
			}

			$api_client = $this->geospatial_api_client;
			$job_status = $api_client->get_job_status($job_id);
			
			if (!$job_status['success']) {
				throw new Exception("Failed to get job status: " . ($job_status['message'] ?? 'Unknown error'));
			}

			$status_data = $job_status['data'];
			
			if ($status_data['status'] !== 'done') {
				// Job not completed yet
				$output = array(
					'status' => 'processing',
					'job_status' => $status_data['status'],
					'message' => 'Job is still processing'
				);
				$this->set_response($output, REST_Controller::HTTP_OK);
				return;
			}

			// Job is completed, create feature from job data
			$data = $status_data['data'];
			$info = $status_data['info'];
			
			// import feature metadata
			$result = $this->Geospatial_features_model->import_feature_metadata($sid, $data, $info, $user_id);

			$output = array(
				'status' => 'success',
				'message' => 'Feature created successfully',
				'feature_id' => $result['feature_id'],
				'characteristics_created' => $result['characteristics_created'],
				'feature_data' => $result['feature_data']
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
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
	 * Start CSV generation job for a geospatial feature
	 */
	function csv_generate_post($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);
			$user_id=$this->get_api_user_id();
			$user=$this->api_user();

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

			$options = $this->raw_json_input();
			
			if (!isset($options['feature_id'])) {
				throw new Exception("Missing parameter: feature_id");
			}
			
			// Get feature details
			$feature = $this->Geospatial_features_model->select_single($options['feature_id']);
			if (!$feature) {
				throw new Exception("Geospatial feature not found");
			}

			$this->editor_acl->user_has_project_access($feature['sid'], 'edit', $user);

			$api_client = $this->geospatial_api_client;

			// Get the full file path for the feature
			$project_dir = $this->Editor_model->get_project_folder($sid);
			$file_path = $project_dir . '/geospatial/' . $feature['file_name'];
			
			// Get the output CSV path from the feature's data_file field
			$output_csv_path = $project_dir . '/geospatial/' . $feature['data_file'];
			
			// Start CSV generation job
			$csv_result = $api_client->start_csv_job($file_path, $feature['layer_name'], $output_csv_path);
			
			if (!$csv_result['success']) {
				throw new Exception("Failed to start CSV generation: " . ($csv_result['message'] ?? 'Unknown error'));
			}

			$output = array(
				'status' => 'success',
				'job_id' => $csv_result['job_id'],
				'message' => 'CSV generation job started successfully'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Check CSV generation job status and download CSV when completed
	 */
	function csv_download_get($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);
			$user_id=$this->get_api_user_id();
			$user=$this->api_user();

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$user);

			$job_id = $this->input->get('job_id');
			if (!$job_id) {
				throw new Exception("Missing parameter: job_id");
			}

			$api_client = $this->geospatial_api_client;
			$job_status = $api_client->get_job_status($job_id);
			
			if (!$job_status['success']) {
				throw new Exception("Failed to get job status: " . ($job_status['message'] ?? 'Unknown error'));
			}

			$status_data = $job_status['data'];
			
			if ($status_data['status'] !== 'done') {
				// Job not completed yet
				$output = array(
					'status' => 'processing',
					'job_status' => $status_data['status'],
					'message' => 'CSV generation is still in progress'
				);
				$this->set_response($output, REST_Controller::HTTP_OK);
				return;
			}

			// Job is completed, CSV file should already be saved by FastAPI
			$info = $status_data['info'];
			$layer_name = $info['layer_name_or_band_index'] ?? 'layer';
			
			// Get the feature to get the data_file path
			$feature_id = $this->input->get('feature_id');
			if (!$feature_id) {
				throw new Exception("Missing parameter: feature_id");
			}
			
			$feature = $this->Geospatial_features_model->select_single($feature_id);
			if (!$feature) {
				throw new Exception("Geospatial feature not found");
			}
			
			// Check if the CSV file exists
			$project_dir = $this->Editor_model->get_project_folder($sid);
			$csv_file_path = $project_dir . '/geospatial/' . $feature['data_file'];
			
			if (!file_exists($csv_file_path)) {
				throw new Exception("CSV file not found: " . $csv_file_path);
			}

			$output = array(
				'status' => 'success',
				'csv_file' => $feature['data_file'],
				'file_path' => $csv_file_path,
				'message' => 'CSV file generated successfully'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
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
	 * Update a geospatial feature
	 */
	function geospatial_features_put($id)
	{
		try{
			$feature = $this->Geospatial_features_model->select_single($id);
			if (!$feature) {
				throw new Exception("Geospatial feature not found");
			}

			$this->editor_acl->user_has_project_access($feature['sid'], $permission='edit', $this->api_user);

			$update_data = $this->raw_json_input();			
			$result = $this->Geospatial_features_model->update($id, $update_data);
			
			if ($result) {
				$output = array(
					'status' => 'success',
					'message' => 'Geospatial feature updated successfully',
					'feature_id' => $id
				);
			} else {
				throw new Exception("Failed to update geospatial feature");
			}

			$this->set_response($output, REST_Controller::HTTP_OK);
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
	 * Get characteristics for a specific feature
	 */
	function chars_get($feature_id)
	{
		try{
			$feature = $this->Geospatial_features_model->select_single($feature_id);
			
			if (!$feature) {
				throw new Exception("Feature not found");
			}

			$this->editor_acl->user_has_project_access($feature['sid'], $permission='view', $this->api_user);

			$characteristics = $this->Geospatial_feature_chars_model->select_by_feature_id($feature_id);

			$output = array(
				'status' => 'success',
				'characteristics' => $characteristics,
				'total' => count($characteristics)
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Update a characteristic
	 */
	function chars_put($id)
	{
		try{
			$characteristic = $this->Geospatial_feature_chars_model->select_single($id);
			
			if (!$characteristic) {
				throw new Exception("Characteristic not found");
			}

			$feature = $this->Geospatial_features_model->select_single($characteristic['feature_id']);
			
			if (!$feature) {
				throw new Exception("Feature not found");
			}

			$this->editor_acl->user_has_project_access($feature['sid'], $permission='edit', $this->api_user);

			$input_data = $this->raw_json_input();			
			$this->Geospatial_feature_chars_model->update($id, $input_data);

			$output = array(
				'status' => 'success',
				'message' => 'Characteristic updated successfully'
			);

			$this->set_response($output, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	
	/**
	 * 
     * Read CSV data for a geospatial feature
	 * 
	 */
	public function read_csv_get($sid=null, $feature_id=null) 
    {
		$sid = $this->get_sid($sid);
		
		if (!$feature_id) {
			$this->response(['status' => 'failed', 'message' => 'Feature ID is required'], 400);
			return;
		}
		
		$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
		
		// Get query parameters
		$offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;
		$limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 50;
		
		try {
			$csv_data = $this->Geospatial_features_model->read_feature_csv($feature_id, $offset, $limit);
			
			$this->response([
				'status' => 'success',
				'headers' => $csv_data['headers'],
				'records' => $csv_data['records'],
				'total' => $csv_data['total'],
				'file_size' => $csv_data['file_size'],
				'file_size_mb' => $csv_data['file_size_mb'],
				'skip_row_counting' => $csv_data['skip_row_counting'],
				'offset' => $offset,
				'limit' => $limit
			], 200);
			
		} catch (Exception $e) {
			$this->response(['status' => 'failed', 'message' => $e->getMessage()], 500);
		}
	}

	/**
	 * Upload geospatial files
	 * POST /api/geospatial_features/upload/{project_id}
	 */
	function upload_post($sid=null)
	{		
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user);

			$this->load->model('Editor_resource_model');
			$this->load->library('Geospatial_processor');
			$processor = $this->geospatial_processor;

			// upload geospatial files
			$result=$this->Editor_resource_model->upload_file($sid,'data',$file_field_name='file', $remove_spaces=false);
			$uploaded_file_name=$result['file_name'];
			$uploaded_path=$result['full_path'];
			
			$project_folder = $this->Editor_model->get_project_folder($sid);
			$geospatial_folder = $project_folder . '/geospatial';
			
			if (!file_exists($geospatial_folder)) {
				@mkdir($geospatial_folder, 0777, true);
			}

			// prepare file info for processing
			$uploaded_files = array(
				array(
					'name' => $uploaded_file_name,
					'path' => $uploaded_path
				)
			);

			// Process uploaded files (validate ZIP, extract if needed)
			try {
				$processing_result = $processor->process_uploaded_files($uploaded_files, $project_folder);

				if (!$processing_result['success']) {
					throw new Exception("File processing failed: " . implode(', ', $processing_result['errors']));
				}
			} catch (Exception $e) {
				log_message('error', 'Geospatial processing error: ' . $e->getMessage());
				throw new Exception("File processing error: " . $e->getMessage());
			}

			$output = array(
				'status' => 'success',
				'uploaded_file_name' => $uploaded_file_name,
				'processed_files' => $processing_result['processed_files'],
				'extracted_files' => $processing_result['extracted_files'],
				'message' => 'Files processed successfully'
			);

			$file_info = array();
			$all_files_for_analysis = array();
			
			foreach ($processing_result['processed_files'] as $file) {
				$file_info[] = array(
					'name' => $file['original_name'],
					'path' => $file['path'],
					'type' => 'direct'
				);
				$all_files_for_analysis[] = $file['path'];
			}
			
			// add extracted files
			foreach ($processing_result['extracted_files'] as $file) {
				$file_info[] = array(
					'name' => $file['extracted_file'],
					'path' => $file['path'],
					'type' => 'extracted',
					'original_zip' => $file['original_zip']
				);
				$all_files_for_analysis[] = $file['path'];
			}
			
			$output['files'] = $file_info;			
			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Check geospatial layer analysis status
	 * POST /api/geospatial_features/analysis_status/{project_id}
	 */
	function analysis_status_post($sid=null)
	{
		try{
			$sid=$this->get_sid($sid);
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);

			$job_id = $this->input->post('job_id');
			if (!$job_id) {
				throw new Exception("Missing parameter: job_id");
			}

			$api_client = $this->geospatial_api_client;
			$status_result = $api_client->get_processing_status($job_id);
			
			$output = array(
				'status' => 'success',
				'job_status' => $status_result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

}
