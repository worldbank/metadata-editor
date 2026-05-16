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
        'definition',
        'is_abstract',
        'aliases',
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
        'name',
        'definition',
        'is_abstract'
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
     * Get geospatial feature by code and project ID
     */
    function select_by_code_and_project($code, $sid)
    {
        $this->db->select('*');
        $this->db->where('code', $code);
        $this->db->where('sid', $sid);
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
    function feature_code_exists($code, $sid = null, $exclude_id = null)
    {
        $this->db->select('id');
        $this->db->where('code', $code);
        if ($sid) {
            $this->db->where('sid', $sid);
        }
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

        // Check if code is unique within the project (if provided)
        if (isset($data['code']) && !empty($data['code']) && isset($data['sid'])) {
            if ($this->feature_code_exists($data['code'], $data['sid'])) {
                throw new Exception('Feature code already exists in this project');
            }
        }

        // Check if project exists (if sid is provided)
        if (isset($data['sid']) && !$this->project_exists($data['sid'])) {
            throw new Exception('Project not found');
        }

        // Generate unique name if conflict exists (auto-rename instead of throwing exception)
        if (isset($data['name']) && isset($data['sid']) && $this->feature_name_exists($data['name'], $data['sid'])) {
            $original_name = $data['name'];
            $data['name'] = $this->generate_unique_feature_name($original_name, $data['sid']);
            log_message('info', "Feature name auto-renamed during insert: '{$original_name}' -> '{$data['name']}'");
        }

        // Handle JSON encoding for metadata, bounds, and aliases fields
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        if (isset($data['bounds']) && is_array($data['bounds'])) {
            $data['bounds'] = json_encode($data['bounds']);
        }
        if (isset($data['aliases']) && is_array($data['aliases'])) {
            $data['aliases'] = json_encode($data['aliases']);
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

        // Validate name field if being updated (data quality check, not required field validation)
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            if (empty($data['name'])) {
                throw new Exception('Name cannot be empty');
            }
            if ($this->feature_name_exists($data['name'], $data['sid'], $id)) {
                throw new Exception('Feature name already exists in this project');
            }
        }

        // Check if code is unique within the project (if provided)
        if (isset($data['code']) && !empty($data['code'])) {
            if ($this->feature_code_exists($data['code'], $data['sid'], $id)) {
                throw new Exception('Feature code already exists in this project');
            }
        }

        // Filter allowed fields for updates (only allow certain fields to be updated)
        $allowed_update_fields = ['name', 'code', 'definition', 'is_abstract', 'aliases', 'metadata', 'data_file'];
        $filtered_data = array();
        
        foreach ($allowed_update_fields as $field) {
            if (isset($data[$field])) {
                $filtered_data[$field] = $data[$field];
            }
        }
        
        // Use filtered data for the rest of the method
        $data = $filtered_data;

        // Handle JSON encoding for metadata, bounds, and aliases fields
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        if (isset($data['bounds']) && is_array($data['bounds'])) {
            $data['bounds'] = json_encode($data['bounds']);
        }
        if (isset($data['aliases']) && is_array($data['aliases'])) {
            $data['aliases'] = json_encode($data['aliases']);
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
     * Calculate global bounding box from all features in a project
     * 
     * @param int $sid Project ID
     * @return array|null Global bounding box or null if no valid bounds found
     */
    public function calculate_global_bounding_box($sid)
    {
        // Select only bounds column for efficiency
        $this->db->select('bounds');
        $this->db->where('sid', $sid);
        $this->db->where('bounds IS NOT NULL');
        $this->db->where('bounds !=', '');
        $result = $this->db->get($this->table)->result_array();
        
        if (empty($result)) {
            return null;
        }
        
        $westValues = array();
        $eastValues = array();
        $southValues = array();
        $northValues = array();
        
        foreach ($result as $row) {
            $bounds = json_decode($row['bounds'], true);
            
            if (!$bounds || !is_array($bounds)) {
                continue;
            }
            
            // Handle both direct bounds structure and nested geographicBoundingBox
            $bbox = isset($bounds['geographicBoundingBox']) 
                ? $bounds['geographicBoundingBox'] 
                : $bounds;
            
            if (isset($bbox['westBoundLongitude']) && 
                isset($bbox['eastBoundLongitude']) && 
                isset($bbox['southBoundLatitude']) && 
                isset($bbox['northBoundLatitude'])) {
                
                $west = floatval($bbox['westBoundLongitude']);
                $east = floatval($bbox['eastBoundLongitude']);
                $south = floatval($bbox['southBoundLatitude']);
                $north = floatval($bbox['northBoundLatitude']);
                
                // Validate ranges
                if ($west >= -180 && $west <= 180 && 
                    $east >= -180 && $east <= 180 &&
                    $south >= -90 && $south <= 90 &&
                    $north >= -90 && $north <= 90 &&
                    $west < $east &&
                    $south < $north) {
                    
                    $westValues[] = $west;
                    $eastValues[] = $east;
                    $southValues[] = $south;
                    $northValues[] = $north;
                }
            }
        }
        
        if (empty($westValues)) {
            return null;
        }
        
        // Calculate global bounds
        return array(
            'westBoundLongitude' => min($westValues),
            'eastBoundLongitude' => max($eastValues),
            'southBoundLatitude' => min($southValues),
            'northBoundLatitude' => max($northValues)
        );
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
     * Checks for related files for any file type, not just shapefiles
     * 
     * @param string $folder_path Folder containing the files
     * @param string $file_name Base file name without extension
     */
    private function delete_related_files($folder_path, $file_name)
    {
        try {
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $base_name = pathinfo($file_name, PATHINFO_FILENAME);
            
            // For shapefiles, delete known related components
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
            
            // For ANY file type, check for files with same base name
            // This handles cases where related files might exist for other formats
            $pattern = $folder_path . '/' . $base_name . '.*';
            $related_files = glob($pattern);
            
            foreach ($related_files as $related_file) {
                // Skip the main file itself (already deleted or being deleted)
                if (basename($related_file) === $file_name) {
                    continue;
                }
                
                // Skip if already deleted (shapefile case above)
                if (!file_exists($related_file)) {
                    continue;
                }
                
                // Delete related file
                if (unlink($related_file)) {
                    log_message('info', "Deleted related file: {$related_file}");
                }
            }
            
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

            // Extract metadata object
            $metadata_obj = $data[1];
            if (!$metadata_obj || !isset($metadata_obj['analytics'])) {
                throw new Exception("No analytics data found in job result");
            }

            // Determine file type (vector or raster)
            $file_type = $info['file_type'] ?? 'vector';
            $is_raster = ($file_type === 'raster');

            // Get analytics data
            $analytics = $metadata_obj['analytics'];

            // Process based on file type
            if ($is_raster) {
                // Raster structure: analytics.feature_statistics contains band data directly
                $layer_info = array();
                $feature_stats = $analytics['feature_statistics'] ?? array();
                
                // Extract raster-specific data
                if (isset($metadata_obj['raster_stats'])) {
                    $layer_info['rows'] = $metadata_obj['raster_stats']['rows'] ?? 0;
                    $layer_info['columns'] = $metadata_obj['raster_stats']['cols'] ?? 0;
                    $layer_info['bands'] = $metadata_obj['raster_stats']['bands'] ?? 1;
                }
                
                // Extract bounding box from raster structure
                if (isset($metadata_obj['bounding_box']['geographicBoundingBox'])) {
                    $layer_info['geographicBoundingBox'] = $metadata_obj['bounding_box']['geographicBoundingBox'];
                    $layer_info['geohash'] = $metadata_obj['bounding_box']['geohash'] ?? null;
                }
                
                // Store CRS - raster uses 'projection' (WKT string) instead of 'crs' (PROJ JSON)
                $metadata_field = array();
                if (isset($metadata_obj['projection'])) {
                    $metadata_field['projection'] = $metadata_obj['projection'];
                }
                if (isset($metadata_obj['crs'])) {
                    $metadata_field['crs'] = $metadata_obj['crs'];
                }
                $metadata_field['raster_stats'] = $metadata_obj['raster_stats'] ?? null;
                $metadata_field['layer_info'] = $layer_info;
                
            } else {
                // Vector structure: analytics.layer contains layer information
                $layer_info = $analytics['layer'] ?? array();
                
                // Prepare metadata field for vector
                $metadata_field = array();
                $metadata_field['crs'] = isset($metadata_obj['crs']) ? $metadata_obj['crs'] : null;
                $metadata_field['layer_info'] = $layer_info;
            }

            // Create feature data
            $file_path = $info['file_path'] ?? '';
            $file_name = basename($file_path);
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $file_name_without_ext = pathinfo($file_name, PATHINFO_FILENAME);

            // Determine feature name and layer name with uniqueness strategy
            if ($is_raster) {
                // For raster files: use filename + band index for uniqueness
                $layer_name = $info['layer_name_or_band_index'] ?? 1;
                $band_index = is_numeric($layer_name) ? $layer_name : 1;
                
                // Use filename as base, add band suffix if multiple bands or if name conflicts
                $preferred_name = $file_name_without_ext;
                // If band index > 1 or if we need to distinguish, add band suffix
                if ($band_index > 1 || $this->feature_name_exists($preferred_name, $sid)) {
                    $preferred_name = $file_name_without_ext . '_band_' . $band_index;
                }
                $feature_name = $this->generate_unique_feature_name($preferred_name, $sid);
            } else {
                // For vector files: prioritize layer name, fallback to filename
                $layer_name = $info['layer_name_or_band_index'] ?? $file_name_without_ext;
                
                // Strategy: Use layer name if available and different from filename
                // Otherwise use filename, or filename + layer name if layer name exists
                if (!empty($layer_name) && $layer_name !== $file_name_without_ext) {
                    // Layer name exists and is different from filename
                    // Check if layer name alone would be unique
                    if (!$this->feature_name_exists($layer_name, $sid)) {
                        $preferred_name = $layer_name;
                    } else {
                        // Layer name conflicts, use filename + layer name combination
                        $preferred_name = $file_name_without_ext . '_' . $layer_name;
                    }
                } else {
                    // No layer name or same as filename, use filename
                    $preferred_name = $file_name_without_ext;
                }
                
                $feature_name = $this->generate_unique_feature_name($preferred_name, $sid);
            }

            // Generate safe CSV filename based on feature name
            $csv_filename = $this->generate_csv_filename($feature_name, $sid);

            $feature_data = array(
                'sid' => $sid,
                'name' => $feature_name,
                'code' => $this->generate_feature_code($feature_name),
                'definition' => 'Geospatial feature: ' . $feature_name, // Required field - default definition, can be updated later by user
                'is_abstract' => 0, // Required field - default to false
                'file_name' => $file_name,
                'file_type' => $file_extension,
                'file_size' => filesize($file_path) ?: 0,
                'upload_status' => 'completed',
                'layer_name' => $layer_name,
                'layer_type' => $file_type,
                'feature_count' => $layer_info['rows'] ?? 0,
                'geometry_type' => $is_raster ? 'raster' : 'vector',
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

            // Process feature characteristics (only for vector files)
            $characteristics_created = $this->create_feature_characteristics($sid, $feature_id, $analytics, $file_type, $user_id);

            // Cleanup files after metadata extraction if keep_files is false (default behavior)
            // Check if keep_files preference is stored in metadata
            // Default to false (delete files) if not specified
            $metadata_array = json_decode($feature_data['metadata'], true);
            $keep_files = isset($metadata_array['keep_files']) && $metadata_array['keep_files'] === true;
            
            if (!$keep_files && !empty($file_path) && file_exists($file_path)) {
                // Delete file and related files after successful metadata extraction
                // Note: delete_associated_file_if_unused checks if other features use the file
                $this->delete_associated_file_if_unused($sid, $file_name, $feature_id);
                log_message('info', "Deleted file after metadata extraction: {$file_path} (keep_files=false, default)");
            } else if ($keep_files) {
                log_message('info', "Kept file after metadata extraction: {$file_path} (keep_files=true)");
            }

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
     * Map pandas data type to ISO 19110 data type
     * 
     * @param string $pandas_type Pandas data type (e.g., 'object', 'int32', 'float64')
     * @return string ISO data type (e.g., 'CharacterString', 'Integer', 'Real')
     */
    private function map_pandas_to_iso_type($pandas_type)
    {
        $type_mapping = array(
            'object' => 'string',
            'string' => 'string',
            'category' => 'string',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'int8' => 'integer',
            'int16' => 'integer',
            'int32' => 'integer',
            'int64' => 'integer',
            'uint8' => 'integer',
            'uint16' => 'integer',
            'uint32' => 'integer',
            'uint64' => 'integer',
            'float16' => 'real',
            'float32' => 'real',
            'float64' => 'real',
            'datetime64[ns]' => 'datetime',
            'datetime64[D]' => 'date',
            'timedelta64[ns]' => 'duration',
            'geometry' => 'string'
        );
        
        // Return mapped type or default to CharacterString if not found
        return isset($type_mapping[$pandas_type]) ? $type_mapping[$pandas_type] : 'string';
    }

    /**
     * Check if data type supports listed values (allowListed)
     * 
     * @param string $iso_type ISO data type
     * @return bool True if type supports listed values
     */
    private function is_allow_listed_type($iso_type)
    {
        $allow_listed_types = array(
            'string',
            'boolean',
            'integer',
        );
        
        return in_array($iso_type, $allow_listed_types);
    }

    /**
     * Create listedValue array from data_dictionary
     * 
     * @param array $data_dictionary Array of values from data_dictionary
     * @return array Array of listedValue objects with code, label, definition
     */
    private function create_listed_values_from_data_dictionary($data_dictionary)
    {
        $listed_values = array();
        
        if (is_array($data_dictionary)) {
            foreach ($data_dictionary as $value) {
                $listed_values[] = array(
                    'code' => (string)$value,
                    'label' => '',
                    'definition' => ''
                );
            }
        }
        
        return $listed_values;
    }

    /**
     * Create feature characteristics from analytics data
     * 
     * @param int $sid Project ID
     * @param int $feature_id Feature ID
     * @param array $analytics Analytics data from job
     * @param string $file_type File type (vector or raster)
     * @param int $user_id User ID for audit trail
     * @return int Number of characteristics created
     */
    private function create_feature_characteristics($sid, $feature_id, $analytics, $file_type, $user_id)
    {
        $characteristics_created = 0;

        // Load the geospatial feature characteristics model
        $this->load->model('Geospatial_feature_chars_model');

        if ($file_type === 'raster') {
            // Raster files: create a characteristic for the band statistics
            $feature_stats = $analytics['feature_statistics'] ?? null;
            
            if ($feature_stats && isset($feature_stats['band_index'])) {
                try {
                    $band_index = $feature_stats['band_index'];
                    $char_name = 'Band ' . $band_index;
                    
                    // Check if characteristic already exists
                    if (!$this->Geospatial_feature_chars_model->characteristic_name_exists($char_name, $sid, $feature_id)) {
                        // Prepare characteristic data for raster band
                        $char_data = array(
                            'sid' => $sid,
                            'feature_id' => $feature_id,
                            'name' => $char_name,
                            'label' => 'Band ' . $band_index . ' Statistics',
                            'data_type' => 'raster_band',
                            'metadata' => $feature_stats,
                            'created_by' => $user_id,
                            'changed_by' => $user_id
                        );

                        $this->Geospatial_feature_chars_model->insert($char_data);
                        $characteristics_created++;
                    }
                } catch (Exception $e) {
                    log_message('error', 'Failed to create raster band characteristic: ' . $e->getMessage());
                }
            }
            
        } else {
            // Vector files: create characteristics from feature_types
            if (!isset($analytics['feature_types']) || !is_array($analytics['feature_types'])) {
                return $characteristics_created;
            }

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

                    // Map pandas data type to ISO type
                    $mapped_data_type = $this->map_pandas_to_iso_type($data_type);

                    // Prepare metadata object
                    $metadata = $field_statistics ? $field_statistics : array();
                    
                    // Remove cardinality field from metadata
                    if (isset($metadata['cardinality'])) {
                        unset($metadata['cardinality']);
                    }
                    
                    // Create listedValue array from data_dictionary if available
                    if (isset($metadata['data_dictionary']) && is_array($metadata['data_dictionary'])) {
                        // Check if type supports listed values
                        if ($this->is_allow_listed_type($mapped_data_type)) {
                            $metadata['listedValue'] = $this->create_listed_values_from_data_dictionary($metadata['data_dictionary']);
                        }
                        // Remove data_dictionary after mapping to listedValue
                        unset($metadata['data_dictionary']);
                    }

                    // Prepare characteristic data
                    $char_data = array(
                        'sid' => $sid,
                        'feature_id' => $feature_id,
                        'name' => $field_name,
                        'label' => null, // Will be populated by user
                        'data_type' => $mapped_data_type, // Store mapped ISO type
                        'metadata' => $metadata,
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
        }

        return $characteristics_created;
    }

    /**
     * Generate a unique feature name with conflict resolution
     * 
     * @param string $preferred_name The preferred name for the feature
     * @param int $sid Project ID
     * @param int $max_attempts Maximum number of attempts to find unique name
     * @return string Unique feature name
     */
    private function generate_unique_feature_name($preferred_name, $sid, $max_attempts = 100)
    {
        // Clean the preferred name
        $base_name = trim($preferred_name);
        if (empty($base_name)) {
            $base_name = 'unnamed_feature';
        }
        
        // Check if base name is unique
        if (!$this->feature_name_exists($base_name, $sid)) {
            return $base_name;
        }
        
        // Try with numeric suffix
        $counter = 1;
        while ($counter <= $max_attempts) {
            $unique_name = $base_name . '_' . $counter;
            if (!$this->feature_name_exists($unique_name, $sid)) {
                log_message('info', "Feature name conflict resolved: '{$base_name}' -> '{$unique_name}'");
                return $unique_name;
            }
            $counter++;
        }
        
        // If we've exhausted all attempts, append timestamp as last resort
        $unique_name = $base_name . '_' . time();
        log_message('info', "Feature name conflict: using timestamp suffix for '{$base_name}' -> '{$unique_name}'");
        return $unique_name;
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

    /**
     * Decode JSON fields in a feature record
     * 
     * @param array &$row Feature record (passed by reference)
     */
    private function decode_json_fields(&$row)
    {
        if (isset($row['metadata']) && !empty($row['metadata'])) {
            $row['metadata'] = json_decode($row['metadata'], true);
        }
        if (isset($row['bounds']) && !empty($row['bounds'])) {
            $row['bounds'] = json_decode($row['bounds'], true);
        }
        if (isset($row['aliases']) && !empty($row['aliases'])) {
            $row['aliases'] = json_decode($row['aliases'], true);
        }
    }
}
