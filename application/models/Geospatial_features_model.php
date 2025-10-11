<?php

/**
 * Geospatial Features Model
 * 
 * Handles operations for geospatial_features table
 */
class Geospatial_features_model extends CI_Model {

    private $table = 'geospatial_features';
    
    
    private $fields = array(
        'sid',
        'code',
        'name',
        'metadata',
        'file_name',
        'file_type',
        'file_size',
        'upload_status',
        'processing_notes',
        'layer_name',
        'layer_type',
        'feature_count',
        'geometry_type',
        'bounds',
        'data_file',
        'created',
        'changed',
        'created_by',
        'changed_by'
    );

    private $required = array(
        'sid',
        'name'
    );

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all geospatial features
     */
    function select_all()
    {
        $this->db->select('*');
        $this->db->order_by('name', 'ASC');
        $result = $this->db->get($this->table)->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
            if (isset($row['bounds']) && !empty($row['bounds'])) {
                $row['bounds'] = json_decode($row['bounds'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get single geospatial feature by ID
     */
    function select_single($id)
    {
        $this->db->select('*');
        $this->db->where('id', $id);
        $result = $this->db->get($this->table)->row_array();
        
        // Decode JSON fields
        if ($result) {
            if (isset($result['metadata']) && !empty($result['metadata'])) {
                $result['metadata'] = json_decode($result['metadata'], true);
            }
            if (isset($result['bounds']) && !empty($result['bounds'])) {
                $result['bounds'] = json_decode($result['bounds'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get geospatial feature by code
     */
    function select_by_code($code)
    {
        $this->db->select('*');
        $this->db->where('code', $code);
        $result = $this->db->get($this->table)->row_array();
        
        // Decode JSON fields
        if ($result) {
            if (isset($result['metadata']) && !empty($result['metadata'])) {
                $result['metadata'] = json_decode($result['metadata'], true);
            }
            if (isset($result['bounds']) && !empty($result['bounds'])) {
                $result['bounds'] = json_decode($result['bounds'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get geospatial feature by name
     */
    function select_by_name($name)
    {
        $this->db->select('*');
        $this->db->where('name', $name);
        $result = $this->db->get($this->table)->row_array();
        
        // Decode JSON fields
        if ($result) {
            if (isset($result['metadata']) && !empty($result['metadata'])) {
                $result['metadata'] = json_decode($result['metadata'], true);
            }
            if (isset($result['bounds']) && !empty($result['bounds'])) {
                $result['bounds'] = json_decode($result['bounds'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get geospatial features by project ID
     */
    function select_by_project($sid)
    {
        $this->db->select('*');
        $this->db->where('sid', $sid);
        $this->db->order_by('name', 'ASC');
        $result = $this->db->get($this->table)->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
            if (isset($row['bounds']) && !empty($row['bounds'])) {
                $row['bounds'] = json_decode($row['bounds'], true);
            }
        }
        
        return $result;
    }

    /**
     * Check if project ID exists
     */
    function project_exists($sid)
    {
        $this->load->model('Editor_model');
        return $this->Editor_model->check_id_exists($sid);
    }

    /**
     * Check if feature ID exists
     */
    function feature_id_exists($id)
    {
        $this->db->select('id');
        $this->db->where('id', $id);
        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Check if feature code exists
     */
    function feature_code_exists($code, $exclude_id = null)
    {
        $this->db->select('id');
        $this->db->where('code', $code);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Check if feature name exists within a project
     */
    function feature_name_exists($name, $sid, $exclude_id = null)
    {
        $this->db->select('id');
        $this->db->where('name', $name);
        $this->db->where('sid', $sid);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Insert new geospatial feature
     */
    function insert($data)
    {
        $data = array_intersect_key($data, array_flip($this->fields));

        // Validate required fields
        foreach ($this->required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception('Missing required field: ' . $field);
            }
        }

        // Check if code is unique (if provided)
        if (isset($data['code']) && !empty($data['code'])) {
            if ($this->feature_code_exists($data['code'])) {
                throw new Exception('Feature code already exists');
            }
        }

        // Check if project exists
        if (!$this->project_exists($data['sid'])) {
            throw new Exception('Project not found');
        }

        // Check if name is unique within the project
        if ($this->feature_name_exists($data['name'], $data['sid'])) {
            throw new Exception('Feature name already exists in this project');
        }

        // Handle JSON encoding for metadata and bounds fields
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        if (isset($data['bounds']) && is_array($data['bounds'])) {
            $data['bounds'] = json_encode($data['bounds']);
        }

        // Set timestamps
        $data['created'] = date('Y-m-d H:i:s');
        $data['changed'] = date('Y-m-d H:i:s');

        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Update geospatial feature
     */
    function update($id, $data)
    {
        if (!$this->feature_id_exists($id)) {
            throw new Exception('Feature not found');
        }

        $data = array_intersect_key($data, array_flip($this->fields));
        
        // Remove ID from update data
        if (isset($data['id'])) {
            unset($data['id']);
        }

        // Get current project ID if not being updated
        if (!isset($data['sid'])) {
            $current = $this->select_single($id);
            $data['sid'] = $current['sid'];
        }

        // Check if project exists (if sid is being updated)
        if (isset($data['sid'])) {
            if (!$this->project_exists($data['sid'])) {
                throw new Exception('Project not found');
            }
        }

        // Validate required fields
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            if (empty($data['name'])) {
                throw new Exception('Name cannot be empty');
            }
            if ($this->feature_name_exists($data['name'], $data['sid'], $id)) {
                throw new Exception('Feature name already exists in this project');
            }
        }

        // Check if code is unique (if provided)
        if (isset($data['code']) && !empty($data['code'])) {
            if ($this->feature_code_exists($data['code'], $id)) {
                throw new Exception('Feature code already exists');
            }
        }

        // Filter allowed fields for updates (only allow certain fields to be updated)
        $allowed_update_fields = ['name', 'code', 'metadata', 'data_file'];
        $filtered_data = array();
        
        foreach ($allowed_update_fields as $field) {
            if (isset($data[$field])) {
                $filtered_data[$field] = $data[$field];
            }
        }
        
        // Use filtered data for the rest of the method
        $data = $filtered_data;

        // Handle JSON encoding for metadata and bounds fields
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        if (isset($data['bounds']) && is_array($data['bounds'])) {
            $data['bounds'] = json_encode($data['bounds']);
        }

        // Set timestamp
        $data['changed'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        $this->db->update($this->table, $data);
    }

    /**
     * Delete geospatial feature
     */
    function delete($id)
    {
        if (!$this->feature_id_exists($id)) {
            throw new Exception('Feature not found');
        }

        $this->db->where('id', $id);
        $this->db->delete($this->table);
    }
    

    /**
     * Get features with their characteristics count
     */
    function select_with_characteristics_count()
    {
        $this->db->select('gf.*, COUNT(gfc.id) as characteristics_count');
        $this->db->from($this->table . ' gf');
        $this->db->join('geospatial_feature_chars gfc', 'gf.id = gfc.feature_id', 'left');
        $this->db->group_by('gf.id');
        $this->db->order_by('gf.name', 'ASC');
        $result = $this->db->get()->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
            if (isset($row['bounds']) && !empty($row['bounds'])) {
                $row['bounds'] = json_decode($row['bounds'], true);
            }
        }
        
        return $result;
    }

    /**
     * Delete associated file from file system if no other features use it
     * 
     * @param int $sid Project ID
     * @param string $file_name File name to check
     * @param int $exclude_id Feature ID to exclude from check
     */
    public function delete_associated_file_if_unused($sid, $file_name, $exclude_id)
    {
        try {
            // Check if other features use this file
            $other_features = $this->select_by_project($sid);
            $file_in_use = false;
            
            foreach ($other_features as $feature) {
                // Skip the feature being deleted
                if ($feature['id'] == $exclude_id) {
                    continue;
                }
                
                // Check if this feature uses the same file
                if ($feature['file_name'] === $file_name) {
                    $file_in_use = true;
                    break;
                }
            }
            
            // Only delete the file if no other features use it
            if (!$file_in_use) {
                $this->delete_associated_file($sid, $file_name);
            } else {
                log_message('info', "File {$file_name} still in use by other features, not deleted");
            }
            
        } catch (Exception $e) {
            log_message('error', "Error checking file usage for {$file_name}: " . $e->getMessage());
        }
    }

    /**
     * Delete associated file from file system
     * 
     * @param int $sid Project ID
     * @param string $file_name File name to delete
     */
    private function delete_associated_file($sid, $file_name)
    {
        try {
            // Load Editor_model to get project folder
            $this->load->model('Editor_model');
            $project_folder = $this->Editor_model->get_project_folder($sid);
            $geospatial_folder = $project_folder . '/geospatial';
            
            // Check if geospatial folder exists
            if (!file_exists($geospatial_folder)) {
                return; // No files to delete
            }
            
            $file_path = $geospatial_folder . '/' . $file_name;
            
            // Check if file exists and delete it
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    // File deleted successfully
                    log_message('info', "Deleted geospatial file: {$file_path}");
                } else {
                    log_message('error', "Failed to delete geospatial file: {$file_path}");
                }
            }
            
            // Check for related files (for shapefiles)
            $this->delete_related_files($geospatial_folder, $file_name);
            
        } catch (Exception $e) {
            log_message('error', "Error deleting geospatial file {$file_name}: " . $e->getMessage());
        }
    }

    /**
     * Delete related files (e.g., shapefile components)
     * 
     * @param string $folder_path Folder containing the files
     * @param string $file_name Base file name without extension
     */
    private function delete_related_files($folder_path, $file_name)
    {
        try {
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $base_name = pathinfo($file_name, PATHINFO_FILENAME);
            
            // For shapefiles, delete related components
            if ($file_extension === 'shp') {
                $related_extensions = array('shx', 'dbf', 'prj', 'cpg', 'sbn', 'sbx', 'shp.xml');
                
                foreach ($related_extensions as $ext) {
                    $related_file = $folder_path . '/' . $base_name . '.' . $ext;
                    if (file_exists($related_file)) {
                        unlink($related_file);
                        log_message('info', "Deleted related file: {$related_file}");
                    }
                }
            }
            
            // For other file types, check if there are any related files
            // (This can be extended for other formats as needed)
            
        } catch (Exception $e) {
            log_message('error', "Error deleting related files for {$file_name}: " . $e->getMessage());
        }
    }

    /**
     * Import geospatial feature metadata
     * 
     * @param int $sid Project ID
     * @param array $data Job data from FastAPI
     * @param array $info Job info from FastAPI
     * @param int $user_id User ID for audit trail
     * @return array Result with feature_id and characteristics_created
     */
    public function import_feature_metadata($sid, $data, $info, $user_id)
    {
        try {
            // Validate data structure
            if (!is_array($data) || count($data) < 2) {
                throw new Exception("Invalid data structure returned from job");
            }

            // Extract layer information from analytics
            $metadata_obj = $data[1];
            if (!$metadata_obj || !isset($metadata_obj['analytics'])) {
                throw new Exception("No analytics data found in job result");
            }

            // Get analytics data
            $analytics = $metadata_obj['analytics'];
            $layer_info = $analytics['layer'] ?? [];

            // Prepare feature metadata
            $metadata_field = array();
            $metadata_field['crs'] = isset($metadata_obj['crs']) ? $metadata_obj['crs'] : null;
            $metadata_field['layer_info'] = isset($metadata_obj['analytics']['layer']) ? $metadata_obj['analytics']['layer'] : null;

            // Create feature data
            $file_path = $info['file_path'] ?? '';
            $file_name = basename($file_path);
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Generate safe CSV filename based on layer name
            $layer_name = $info['layer_name_or_band_index'] ?? $file_name;
            $csv_filename = $this->generate_csv_filename($layer_name, $sid);

            $feature_data = array(
                'sid' => $sid,
                'name' => $layer_name,
                'code' => $this->generate_feature_code($layer_name),
                'file_name' => $file_name,
                'file_type' => $file_extension,
                'file_size' => filesize($file_path) ?: 0,
                'upload_status' => 'completed',
                'layer_name' => $layer_name,
                'layer_type' => $info['file_type'] ?? 'vector',
                'feature_count' => $layer_info['rows'] ?? 0,
                'geometry_type' => isset($layer_info['geographicBoundingBox']) ? 'vector' : 'raster',
                'bounds' => isset($layer_info['geographicBoundingBox']) ? json_encode($layer_info['geographicBoundingBox']) : null,
                'data_file' => $csv_filename,
                'metadata' => json_encode($metadata_field),
                'created_by' => $user_id,
                'changed_by' => $user_id
            );

            // Create the feature
            $feature_id = $this->insert($feature_data);

            if (!$feature_id) {
                throw new Exception("Failed to create geospatial feature in database");
            }

            // Process feature characteristics
            $characteristics_created = $this->create_feature_characteristics($sid, $feature_id, $analytics, $user_id);

            return array(
                'feature_id' => $feature_id,
                'characteristics_created' => $characteristics_created,
                'feature_data' => $feature_data
            );

        } catch (Exception $e) {
            log_message('error', "Error importing feature metadata: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create feature characteristics from analytics data
     * 
     * @param int $sid Project ID
     * @param int $feature_id Feature ID
     * @param array $analytics Analytics data from job
     * @param int $user_id User ID for audit trail
     * @return int Number of characteristics created
     */
    private function create_feature_characteristics($sid, $feature_id, $analytics, $user_id)
    {
        $characteristics_created = 0;

        if (!isset($analytics['feature_types']) || !is_array($analytics['feature_types'])) {
            return $characteristics_created;
        }

        // Load the geospatial feature characteristics model
        $this->load->model('Geospatial_feature_chars_model');

        foreach ($analytics['feature_types'] as $field_name => $data_type) {
            try {
                // Check if characteristic already exists
                if ($this->Geospatial_feature_chars_model->characteristic_name_exists($field_name, $sid, $feature_id)) {
                    log_message('info', 'Characteristic ' . $field_name . ' already exists for feature ' . $feature_id . ', skipping');
                    continue;
                }

                // Get statistics for this field if available
                $field_statistics = null;
                if (isset($analytics['feature_statistics'][$field_name])) {
                    $field_statistics = $analytics['feature_statistics'][$field_name];
                }

                // Prepare characteristic data
                $char_data = array(
                    'sid' => $sid,
                    'feature_id' => $feature_id,
                    'name' => $field_name,
                    'label' => null, // Will be populated by user
                    'data_type' => $data_type,
                    'metadata' => $field_statistics,
                    'created_by' => $user_id,
                    'changed_by' => $user_id
                );

                $this->Geospatial_feature_chars_model->insert($char_data);
                $characteristics_created++;

            } catch (Exception $e) {
                // Log the error but continue processing other characteristics
                log_message('error', 'Failed to create characteristic ' . $field_name . ': ' . $e->getMessage());
            }
        }

        return $characteristics_created;
    }

    /**
     * Generate a unique feature code
     */
    private function generate_feature_code($name)
    {
        // Convert name to uppercase and replace spaces/special chars with underscores
        $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $name));

        // Limit to 20 characters
        $code = substr($code, 0, 20);

        // Remove trailing underscores
        $code = rtrim($code, '_');

        // If empty, use default
        if (empty($code)) {
            $code = 'FEATURE';
        }

        // Add timestamp to ensure uniqueness
        $code .= '_' . time();

        return $code;
    }

    /**
     * Generate a safe CSV filename based on layer name
     */
    private function generate_csv_filename($layer_name, $sid)
    {
        $file_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $layer_name);
        $file_name = preg_replace('/_+/', '_', $file_name);
        $file_name = trim($file_name, '_');

        if (empty($file_name)) {
            $file_name = 'layer_' . substr(md5(uniqid(rand(), true)), 0, 8);
        }

        $filename = "layer_{$file_name}.csv";

        return $filename;
    }

    /**
     * Read CSV data for a geospatial feature
     * 
     * @param int $feature_id Feature ID
     * @param int $offset Pagination offset
     * @param int $limit Pagination limit
     * @return array CSV data with headers, records, and total count
     * @throws Exception If feature not found or file doesn't exist
     */
    public function read_feature_csv($feature_id, $offset = 0, $limit = 50)
    {
        // Get feature details
        $feature = $this->select_single($feature_id);
        if (!$feature) {
            throw new Exception('Feature not found');
        }
        
        if (!$feature['data_file']) {
            throw new Exception('No data file available for this feature');
        }
        
        // Load Editor_model to get project folder
        $this->load->model('Editor_model');
        $project_dir = $this->Editor_model->get_project_folder($feature['sid']);
        $csv_path = $project_dir . '/geospatial/' . $feature['data_file'];
        
        if (!file_exists($csv_path)) {
            throw new Exception('Data file not found: ' . $csv_path);
        }
        
        return $this->read_csv_file($csv_path, $offset, $limit);
    }

    /**
     * Read CSV file with pagination
     * 
     * @param string $file_path Path to CSV file
     * @param int $offset Pagination offset
     * @param int $limit Pagination limit
     * @return array CSV data with headers, records, and total count
     */
    private function read_csv_file($file_path, $offset = 0, $limit = 50)
    {
        $headers = [];
        $records = [];
        $total = 0;
        
        // Check file size - if larger than 300MB, skip row counting
        $file_size = filesize($file_path);
        $file_size_mb = $file_size / (1024 * 1024); // Convert to MB
        $skip_row_counting = $file_size_mb > 300;
        
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            // Read headers
            if (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $headers = $data;
            }
            
            // Count total rows only if file is not too large
            if (!$skip_row_counting) {
                $total = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $total++;
                }
            } else {
                // For large files, set total to -1 to indicate unknown count
                $total = -1;
            }
            
            // Reset file pointer
            rewind($handle);
            
            // Skip header
            fgetcsv($handle, 1000, ',');
            
            // Skip to offset
            for ($i = 0; $i < $offset; $i++) {
                if (fgetcsv($handle, 1000, ',') === FALSE) {
                    break;
                }
            }
            
            // Read records up to limit
            $count = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE && $count < $limit) {
                $record = [];
                for ($i = 0; $i < count($headers); $i++) {
                    $record[$headers[$i]] = isset($data[$i]) ? $data[$i] : '';
                }
                $records[] = $record;
                $count++;
            }
            
            fclose($handle);
        }
        
        return [
            'headers' => $headers,
            'records' => $records,
            'total' => $total,
            'file_size' => $file_size,
            'file_size_mb' => round($file_size_mb, 2),
            'skip_row_counting' => $skip_row_counting
        ];
    }
}
