<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Geospatial API Client Library
 * 
 * Handles communication with external geospatial processing API
 */
class Geospatial_api_client {

    private $api_base_url;
    private $timeout;
    private $max_retries;

    function __construct()
    {
        // Load configuration
        $this->CI =& get_instance();
        $this->CI->load->config('editor');
        
        // Use the same FastAPI service as data processing
        $this->api_base_url = rtrim($this->CI->config->item('editor')['data_api_url'], '/');
        $this->timeout = 30;
        $this->max_retries = 3;
    }

    /**
     * Start layer analysis job for a single file
     * 
     * @param string $file_path File path to analyze
     * @return array API response with job ID and status
     */
    public function start_layer_analysis_job($file_path)
    {
        $result = array(
            'success' => false,
            'job_id' => null,
            'message' => '',
            'errors' => array()
        );

        try {
            $absolute_path = realpath($file_path);
            if (!$absolute_path) {
                throw new Exception("File path not found: {$file_path}");
            }
            
            $payload = array(
                'file_path' => $absolute_path,
                'return_object' => true
            );

            $response = $this->make_api_request('POST', '/geospatial/layers-queue', $payload);
            
            if ($response['success']) {
                $result['success'] = true;
                // The API should return a job_id for async processing
                $result['job_id'] = $response['data']['job_id'] ?? 'unknown';
                $result['status'] = 'processing';
                $result['message'] = 'Layer analysis job started successfully';
            } else {
                $result['errors'] = $response['errors'];
                $result['status'] = 'failed';
                $result['message'] = 'Failed to start layer analysis job';
            }
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['status'] = 'error';
            $result['message'] = 'API request failed';
        }

        return $result;
    }

    /**
     * Start metadata extraction job for a single layer using metadata-queue endpoint
     * 
     * @param string $file_path Path to the geospatial file
     * @param string $layer_name Single layer name to get metadata for
     * @return array API response with job ID
     */
    public function start_metadata_job($file_path, $layer_name)
    {
        $result = array(
            'success' => false,
            'job_id' => null,
            'status' => 'unknown',
            'errors' => array(),
            'message' => ''
        );

        try {
            $absolute_path = realpath($file_path);
            if (!$absolute_path) {
                throw new Exception("File path not found: {$file_path}");
            }
            
            $payload = array(
                'file_path' => $absolute_path,
                'layer_name_or_band_index' => $layer_name,
                'return_object' => true
            );

            $response = $this->make_api_request('POST', '/geospatial/metadata-queue', $payload);
            
            // Debug: Log the request and response
            log_message('debug', 'Metadata job request payload: ' . json_encode($payload));
            log_message('debug', 'Metadata job response: ' . json_encode($response));
            
            if ($response['success']) {
                $result['success'] = true;
                $result['job_id'] = $response['data']['job_id'] ?? 'unknown';
                $result['status'] = 'processing';
                $result['message'] = 'Metadata extraction job started successfully';
                $result['response'] = $response;
                $result['payload'] = $payload;
            } else {
                $result['errors'] = $response['errors'];
                $result['status'] = 'failed';
                $result['message'] = 'Failed to start metadata extraction job';
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['status'] = 'error';
            $result['message'] = 'API request failed';
        }

        return $result;
    }

    /**
     * Send files for analysis to external API
     * 
     * @param array $file_paths Array of file paths to analyze
     * @return array API response with job ID and status
     */
    public function analyze_files($file_paths)
    {
        $result = array(
            'success' => false,
            'layers' => array(),
            'message' => '',
            'errors' => array()
        );

        try {
            // Convert all file paths to absolute paths
            $absolute_paths = array();
            foreach ($file_paths as $path) {
                $absolute_paths[] = realpath($path);
            }
            
            // For now, we'll analyze files individually since the API expects single file requests
            // In the future, this could be optimized to handle multiple files in one request
            $all_layers = array();
            $has_errors = false;
            
            foreach ($absolute_paths as $file_path) {
                try {
                    $layer_result = $this->get_file_layers($file_path);
                    if ($layer_result['success'] && !empty($layer_result['layers'])) {
                        // Add file path to each layer for reference
                        foreach ($layer_result['layers'] as &$layer) {
                            $layer['file_path'] = $file_path;
                        }
                        $all_layers = array_merge($all_layers, $layer_result['layers']);
                    } else {
                        $result['errors'] = array_merge($result['errors'], $layer_result['errors']);
                        $has_errors = true;
                    }
                } catch (Exception $e) {
                    $result['errors'][] = "Error analyzing {$file_path}: " . $e->getMessage();
                    $has_errors = true;
                }
            }
            
            if (!$has_errors || !empty($all_layers)) {
                $result['success'] = true;
                $result['layers'] = $all_layers;
                $result['message'] = 'Files analyzed successfully';
            } else {
                $result['message'] = 'Failed to analyze files';
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['message'] = 'API request failed';
        }

        return $result;
    }

    /**
     * Get job status for a job
     * 
     * @param string $job_id Job ID from analyze_files response
     * @return array Job status and results
     */
    public function get_job_status($job_id)
    {
        $result = array(
            'success' => false,
            'data' => null,
            'errors' => array(),
            'message' => ''
        );

        try {
            $response = $this->make_api_request('GET', "/jobs/{$job_id}");
            
            if ($response['success']) {
                $result['success'] = true;
                $result['data'] = $response['data'];
                $result['message'] = 'Job status retrieved successfully';
            } else {
                $result['errors'] = $response['errors'];
                $result['message'] = 'Failed to get job status';
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['message'] = 'API request failed';
        }

        return $result;
    }

    /**
     * Get layer information for a specific file
     * 
     * @param string $file_path Path to geospatial file
     * @return array Layer information
     */
    public function get_file_layers($file_path)
    {
        $result = array(
            'success' => false,
            'layers' => array(),
            'errors' => array(),
            'message' => ''
        );

        try {
            $payload = array(
                'file_path' => realpath($file_path),
                'return_object' => true
            );
            $response = $this->make_api_request('POST', '/geospatial/info-queue', $payload);
            
            if ($response['success']) {
                $result['success'] = true;               
                $result['layers'] = $response['data'];                                
                $result['message'] = 'Layer information retrieved successfully';
            } else {
                $result['errors'] = $response['errors'];
                $result['message'] = 'Failed to get layer information';
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['message'] = 'API request failed';
        }

        return $result;
    }

    /**
     * Make HTTP request to external API using Guzzle
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array API response
     */
    private function make_api_request($method, $endpoint, $data = null)
    {
        try {
            $client = new Client([
                'base_uri' => $this->api_base_url,
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Metadata-Editor/1.0'
                ]
            ]);

            $options = [];
            if ($method === 'POST' && $data) {
                $options['json'] = $data;
            }

            $response = $client->request($method, $endpoint, $options);
            
            $response_body = $response->getBody()->getContents();
            $decoded_response = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response: " . json_last_error_msg());
            }

            return array(
                'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
                'data' => $decoded_response,
                'errors' => isset($decoded_response['errors']) ? $decoded_response['errors'] : array()
            );

        } catch (RequestException $e) {
            $error_message = $e->getMessage();
            if ($e->hasResponse()) {
                $error_message .= " - Response: " . $e->getResponse()->getBody()->getContents();
            }
            throw new Exception("HTTP request failed: {$error_message}");
        } catch (Exception $e) {
            throw new Exception("API request failed: " . $e->getMessage());
        }
    }

    /**
     * Wait for job completion with polling
     * 
     * @param string $job_id Job ID to monitor
     * @param int $poll_interval Seconds between status checks
     * @param int $max_wait_time Maximum time to wait in seconds
     * @return array Final job status
     */
    public function wait_for_job_completion($job_id, $poll_interval = 5, $max_wait_time = 300)
    {
        $start_time = time();
        $result = array(
            'success' => false,
            'status' => 'timeout',
            'layers' => array(),
            'errors' => array(),
            'message' => 'Job monitoring timed out'
        );

        while ((time() - $start_time) < $max_wait_time) {
            $status = $this->get_processing_status($job_id);
            
            if (!$status['success']) {
                $result['errors'] = $status['errors'];
                $result['message'] = 'Failed to check job status';
                break;
            }

            if ($status['status'] === 'completed') {
                $result['success'] = true;
                $result['status'] = 'completed';
                $result['layers'] = $status['layers'];
                $result['message'] = 'Job completed successfully';
                break;
            }

            if ($status['status'] === 'failed') {
                $result['status'] = 'failed';
                $result['errors'] = $status['errors'];
                $result['message'] = 'Job failed: ' . implode(', ', $status['errors']);
                break;
            }

            // Still processing, wait before next check
            sleep($poll_interval);
        }

        return $result;
    }

    /**
     * Start CSV generation job for a single layer
     * 
     * @param string $file_path Path to the geospatial file
     * @param string $layer_name Single layer name to generate CSV for
     * @param string $output_csv_path Path where the CSV file should be saved
     * @return array API response with job ID
     */
    public function start_csv_job($file_path, $layer_name, $output_csv_path)
    {
        $result = array(
            'success' => false,
            'job_id' => null,
            'status' => 'unknown',
            'errors' => array(),
            'message' => ''
        );

        try {
            $absolute_path = realpath($file_path);
            if (!$absolute_path) {
                throw new Exception("File path not found: {$file_path}");
            }
            
            $payload = array(
                'file_path' => $absolute_path,
                'layer_name_or_band_index' => $layer_name,
                'csv_output_path' => $output_csv_path
            );

            $response = $this->make_api_request('POST', '/geospatial/data-queue', $payload);
            
            // Debug: Log the request and response
            log_message('debug', 'CSV job request payload: ' . json_encode($payload));
            log_message('debug', 'CSV job response: ' . json_encode($response));
            
            if ($response['success']) {
                $result['success'] = true;
                $result['job_id'] = $response['data']['job_id'] ?? 'unknown';
                $result['status'] = 'processing';
                $result['message'] = 'CSV generation job started successfully';
                $result['response'] = $response;
                $result['payload'] = $payload;
            } else {
                $result['errors'] = $response['errors'];
                $result['status'] = 'failed';
                $result['message'] = 'Failed to start CSV generation job';
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['status'] = 'error';
            $result['message'] = 'API request failed';
        }

        return $result;
    }

    /**
     * Test API connection using the info-queue endpoint
     * 
     * @return array Connection test result
     */
    public function test_connection()
    {
        $result = array(
            'success' => false,
            'message' => '',
            'errors' => array()
        );

        try {
            // Test with a dummy file path to see if the endpoint responds
            $payload = array(
                'file_path' => realpath('/test/path/file.geojson'),
                'return_object' => true
            );
            $response = $this->make_api_request('POST', '/geospatial/info-queue', $payload);
            
            // Even if the file doesn't exist, if we get a response, the API is working
            if ($response !== null) {
                $result['success'] = true;
                $result['message'] = 'API connection successful';
            } else {
                $result['errors'][] = 'No response from API';
                $result['message'] = 'API connection failed';
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['message'] = 'API connection test failed';
        }

        return $result;
    }
}
