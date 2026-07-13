<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\JsonDecoder\DecodingError;
use JsonMachine\JsonDecoder\PassThruDecoder;


/**
 * 
 * Import project metadata from JSON, JSONL or XML files
 * 
 * Supports:
 * - JSON metadata files (all project types)
 * - JSONL (JSON Lines) metadata files (all project types)
 *   - For survey/microdata: first line contains project metadata, subsequent lines contain variables
 *   - For other types: first line contains project metadata (same as JSON)
 * - XML/DDI files (survey projects)
 * - XML metadata files (geospatial projects)
 * 
 * The class automatically detects the file type and routes to the appropriate importer.
 * For survey projects, it handles complete microdata import including datafiles and variables.
 * 
 */
class ImportJsonMetadata
{

	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "ImportJsonMetadata Class Initialized.");
		$this->ci =& get_instance();

		$this->ci->load->model("Editor_model");
        $this->ci->load->model("Editor_datafile_model");
        $this->ci->load->model("Editor_variable_model");
        $this->ci->load->model("Editor_variable_groups_model");
        $this->ci->load->model("Geospatial_features_model");
        $this->ci->load->model("Geospatial_feature_chars_model");
        
        // Threshold for using streaming parser (10MB)
        $this->streaming_threshold = 5 * 1024 * 1024;
        $this->import_min_memory_bytes = 1024 * 1024 * 1024;
	}
    
    /**
     * 
     * Import metadata from JSON or XML file
     * 
     * Tries JSON first, falls back to XML if JSON is invalid or type is survey
     * 
     * @param int $sid - Project ID
     * @param string $file_path - Path to JSON or XML file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options (type, created_by, etc.)
     * @return bool|array - Returns true or import result array
     * 
     */
    function import($sid,$file_path,$validate=true,$options=array())
    {
        $this->ensure_import_php_limits();

        // Validate file exists
        if (!file_exists($file_path)){
            throw new Exception("File not found: " . $file_path);
        }

        // Detect file type
        $file_info = pathinfo($file_path);
        $file_ext = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';

        // Check if file has an extension
        if (empty($file_ext)){
            throw new Exception("File has no extension. File: " . $file_path . ". Only JSON, JSONL and XML files are supported.");
        }

        // Route to appropriate importer based on file extension (try JSON first, then XML)
        if ($file_ext == 'json'){
            return $this->import_from_json($sid, $file_path, $validate, $options);
        }
        else if ($file_ext == 'jsonl'){
            return $this->import_from_jsonl($sid, $file_path, $validate, $options);
        }
        else if ($file_ext == 'xml'){
            return $this->import_from_xml($sid, $file_path, $validate, $options);
        }
        else{
            throw new Exception("Unsupported file type: " . $file_ext . ". File: " . $file_path . ". Only JSON, JSONL and XML files are supported.");
        }
    }
    

    /**
     * 
     * Import metadata from JSON file
     * 
     * Uses streaming parser (json-machine) for large files to avoid memory issues
     * 
     */
    private function import_from_json($sid,$json_file_path,$validate=true,$options=array())
    {
        $type = $this->detect_project_type($json_file_path, $options);
        $canonical_type = $this->ci->Editor_model->resolve_canonical_type($type) ?: $type;

        if (isset($options['type']) && !empty($options['type'])) {
            $canonical_options_type = $this->ci->Editor_model->resolve_canonical_type($options['type']) ?: $options['type'];
            if ($canonical_options_type) {
                $canonical_type = $canonical_options_type;
                $type = $options['type'];
            }
        }
        
        // microdata and geospatial always use streaming
        $is_microdata = ($canonical_type === 'microdata' || $canonical_type === 'survey');
        $is_geospatial = ($canonical_type === 'geospatial');
        
        if ($is_microdata) {
            log_message('info', sprintf(
                'Using streaming parser for microdata project: %s',
                basename($json_file_path)
            ));
            return $this->import_microdata_streaming($sid, $json_file_path, $validate, $options);
        }
        else if ($is_geospatial) {
            log_message('info', sprintf(
                'Using streaming parser for geospatial project: %s',
                basename($json_file_path)
            ));
            return $this->import_geospatial_streaming($sid, $json_file_path, $validate, $options);
        }
        else {
            // Other types: use traditional approach
            return $this->import_from_json_traditional($sid, $json_file_path, $validate, $options);
        }
    }
    
    /**
     * 
     * Detect project type from JSON file or options
     * 
     * Priority:
     * 1. Type from options (if provided)
     * 2. Extract type from JSON using json-machine JSON Pointer
     * 3. Fallback to reading first chunk of file
     * 
     * @param string $json_file_path - Path to JSON file
     * @param array $options - Options array that may contain 'type'
     * @return string - Project type
     * 
     */
    private function detect_project_type($json_file_path, $options=array())
    {
        // Use type from options if provided
        if (isset($options['type']) && !empty($options['type'])) {
            return $options['type'];
        }
        
        try {
            $items = Items::fromFile($json_file_path, [
                'decoder' => new ExtJsonDecoder(true),
                'pointer' => ['/type', '/schematype']
            ]);
            
            foreach ($items as $key => $value) {
                if (($key === 'type' || $key === 'schematype') && !empty($value)) {
                    return $value;
                }
            }
        } catch (Exception $e) {
            log_message('error', 'Failed to extract type using json-machine: ' . $e->getMessage());
        }
        
        throw new Exception("Could not detect project type from JSON file: " . $json_file_path);
    }


    /**
     * Read top-level JSON object fields while skipping large subtrees without decoding them.
     *
     * ExtJsonDecoder decodes each property value in full; skipping assignment after decode
     * still exhausts memory on keys such as "variables". PassThruDecoder yields raw JSON
     * fragments so excluded keys are never decoded into PHP structures.
     *
     * @param string $json_file_path
     * @param string[] $exclude_keys Top-level keys to skip (e.g. variables, data_files)
     * @return array
     */
    private function extract_root_metadata_excluding_keys($json_file_path, array $exclude_keys)
    {
        if (!file_exists($json_file_path)) {
            throw new Exception("File not found: " . $json_file_path);
        }

        $exclude_lookup = array_flip($exclude_keys);
        $json_data = array();

        $items = Items::fromFile($json_file_path, array(
            'decoder' => new PassThruDecoder(),
        ));

        foreach ($items as $raw_key => $raw_value) {
            $key = json_decode($raw_key, true);
            if (!is_string($key) || $key === '' || isset($exclude_lookup[$key])) {
                continue;
            }

            $decoded = json_decode($raw_value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON for key '{$key}': " . json_last_error_msg());
            }

            $json_data[$key] = $decoded;
        }

        return $json_data;
    }


    /**
     * Raise PHP limits for large metadata imports (matches Data API import limits).
     */
    private function ensure_import_php_limits()
    {
        set_time_limit(0);

        $current = ini_get('memory_limit');
        if ($current === '-1') {
            return;
        }

        $bytes = 0;
        if (preg_match('/^(\d+)([KMG])?$/i', trim((string) $current), $m)) {
            $bytes = (int) $m[1];
            $unit = isset($m[2]) ? strtoupper($m[2]) : '';
            if ($unit === 'K') {
                $bytes *= 1024;
            } elseif ($unit === 'M') {
                $bytes *= 1024 * 1024;
            } elseif ($unit === 'G') {
                $bytes *= 1024 * 1024 * 1024;
            }
        }

        if ($bytes > 0 && $bytes < $this->import_min_memory_bytes) {
            ini_set('memory_limit', '1024M');
        }
    }


    
    /**
     * 
     * Import microdata project using streaming parser (json-machine)
     * 
     * This method uses JSON Pointers to stream process large arrays (variables, data_files)
     * without loading the entire file into memory.
     * 
     * Strategy:
     * 1. Extract metadata using JSON Pointers (doc_desc, study_desc, etc.)
     * 2. Stream process data_files array
     * 3. Stream process variables array in batches
     * 
     * @param int $sid - Project ID
     * @param string $json_file_path - Path to JSON file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options
     * @return bool - Returns true on success
     * 
     */
    private function import_microdata_streaming($sid,$json_file_path,$validate=true,$options=array())
    {
        try {
            $json_data = $this->extract_root_metadata_excluding_keys($json_file_path, array(
                'variables',
                'data_files',
                'variable_groups',
            ));
        } catch (Exception $e) {
            log_message('error', 'Failed to extract metadata using json-machine: ' . $e->getMessage());
            throw new Exception("Failed to extract metadata from JSON file: " . $e->getMessage());
        }
        
        // Get type
        $type = isset($json_data['type']) ? $json_data['type'] : 
                (isset($json_data['schematype']) ? $json_data['schematype'] : null);
        
        if (!$type) {
            throw new Exception("Invalid JSON file. Missing 'type' field.");
        }
        
        $json_data['type'] = $type;
        
        // Check type mismatch - use canonical type resolution
        if (isset($options['type'])){
            $canonical_options_type = $this->ci->Editor_model->resolve_canonical_type($options['type']) ?: $options['type'];
            $canonical_metadata_type = $this->ci->Editor_model->resolve_canonical_type($type) ?: $type;
            
            if ($canonical_options_type !== $canonical_metadata_type){
                throw new Exception("Project type mismatched. The metadata 'type' is set to '".$type."', but the project type is set to '".$options['type']."'.");
            }
        }
        
        // Merge options into JSON data
        $json_data = array_merge($json_data, $options);
        
        // Use template if set [template_uid]
        if (isset($json_data['template_uid'])){
            $template = $this->ci->Editor_template_model->get_template_by_uid($json_data['template_uid']);
            if (!$template){
                $json_data['template_uid'] = null;
            }
        }
        
        // Import project metadata first
        $this->import_project_metadata($type, $sid, $json_data, $validate);
        unset($json_data);

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Stream process data_files array
        $file_id_mappings = $this->stream_process_datafiles($sid, $json_file_path, $validate);
        
        // Stream process variables array in batches
        $this->stream_process_variables($sid, $json_file_path, $file_id_mappings, $validate);
        
        return true;
    }
    
    /**
     * 
     * Import geospatial project using streaming parser (json-machine)
     * 
     * This method uses JSON Pointers to stream process large feature catalogues
     * without loading the entire file into memory.
     * 
     * @param int $sid - Project ID
     * @param string $json_file_path - Path to JSON file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options
     * @return bool - Returns true on success
     * 
     */
    private function import_geospatial_streaming($sid,$json_file_path,$validate=true,$options=array())
    {
        try {
            $json_data = $this->extract_root_metadata_excluding_keys($json_file_path, array(
                'feature_catalogue',
            ));
        } catch (Exception $e) {
            log_message('error', 'Failed to extract metadata using json-machine: ' . $e->getMessage());
            throw new Exception("Failed to extract metadata from JSON file: " . $e->getMessage());
        }
        
        // Get type
        $type = isset($json_data['type']) ? $json_data['type'] : 
                (isset($json_data['schematype']) ? $json_data['schematype'] : null);
        
        if (!$type) {
            throw new Exception("Invalid JSON file. Missing 'type' field.");
        }
        
        $json_data['type'] = $type;
        
        // Check type mismatch - use canonical type resolution
        if (isset($options['type'])){
            $canonical_options_type = $this->ci->Editor_model->resolve_canonical_type($options['type']) ?: $options['type'];
            $canonical_metadata_type = $this->ci->Editor_model->resolve_canonical_type($type) ?: $type;
            
            if ($canonical_options_type !== $canonical_metadata_type){
                throw new Exception("Project type mismatched. The metadata 'type' is set to '".$type."', but the project type is set to '".$options['type']."'.");
            }
        }
        
        // Merge options into JSON data
        $json_data = array_merge($json_data, $options);
        
        // Use template if set [template_uid]
        if (isset($json_data['template_uid'])){
            $template = $this->ci->Editor_template_model->get_template_by_uid($json_data['template_uid']);
            if (!$template){
                $json_data['template_uid'] = null;
            }
        }
        
        // Fix for geospatial - flatten identificationInfo array
        if (isset($json_data['description']['identificationInfo']) && 
            is_array($json_data['description']['identificationInfo']) &&
            isset($json_data['description']['identificationInfo'][0])
        ){
            $json_data['description']['identificationInfo'] = $json_data['description']['identificationInfo'][0];
        }
        
        // Import project metadata first
        $this->import_project_metadata($type, $sid, $json_data, $validate);
        
        // Stream process feature catalogue if present
        $this->stream_process_feature_catalogue($sid, $json_file_path, $options, $validate);
        
        return true;
    }
    
    
    /**
     * 
     * Traditional JSON import method (extracted for fallback)
     * 
     */
    private function import_from_json_traditional($sid,$json_file_path,$validate=true,$options=array())
    {
        // Read file
        $json = file_get_contents($json_file_path);
        
        if ($json === false){
            throw new Exception("Failed to read JSON file: " . $json_file_path);
        }

        // Decode JSON
        $json_data = json_decode($json, true);

        // Validate JSON parsing
        if ($json_data === null){
            $json_error = json_last_error();
            if ($json_error !== JSON_ERROR_NONE){
                throw new Exception("Invalid JSON format: " . json_last_error_msg());
            }
        }

        //type- check json_data['type'] or json_data['schematype']
        if (!isset($json_data['type']) && !isset($json_data['schematype'])){
            throw new Exception("Invalid JSON file. Missing 'type' field.");
        }

        $type=isset($json_data['type']) ? $json_data['type'] : $json_data['schematype'];
        $json_data['type']=$type;

        // Check type mismatch - use canonical type resolution
        if (isset($options['type'])){
            $canonical_options_type = $this->ci->Editor_model->resolve_canonical_type($options['type']) ?: $options['type'];
            $canonical_metadata_type = $this->ci->Editor_model->resolve_canonical_type($type) ?: $type;
            
            if ($canonical_options_type !== $canonical_metadata_type){
                throw new Exception("Project type mismatched. The metadata 'type' is set to '".$type."', but the project type is set to '".$options['type']."'.");
            }
        }

        // Merge options into JSON data
        $json_data=array_merge($json_data, $options);

        // Use template if set [template_uid]
        if (isset($json_data['template_uid'])){
            //get template by uid
            $template=$this->ci->Editor_template_model->get_template_by_uid($json_data['template_uid']);
            
            if (!$template){
                $json_data['template_uid']=null;
            }
        }        

        //fix for geospatial - flatten identificationInfo array
        if ($type=='geospatial'){
			if (isset($json_data['description']['identificationInfo']) && 
                    is_array($json_data['description']['identificationInfo']) &&
                    isset($json_data['description']['identificationInfo'][0])
                ){
				$json_data['description']['identificationInfo']=$json_data['description']['identificationInfo'][0];
			}
		}

        // Import based on project type
        if ($type=='survey' || $type=='microdata'){
            $this->import_microdata_project($type, $sid,$json_data,$validate);
        }
        else if ($type=='geospatial'){
            $this->import_geospatial_project($type, $sid,$json_data,$validate,$options);
        }
        else{
            //import project metadata
            $this->import_project_metadata($type, $sid,$json_data,$validate);
        }

        return true;
    }
    
    /**
     * Format bytes to human-readable format
     * 
     * @param int $bytes - Number of bytes
     * @param int $precision - Decimal precision
     * @return string - Formatted string (e.g., "1.5 MB")
     */
    private function format_bytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        if ($bytes < 0) {
            return '-' . $this->format_bytes(abs($bytes), $precision);
        }
        
        if ($bytes == 0) {
            return '0 B';
        }
        
        $base = log($bytes, 1024);
        $unit = $units[floor($base)];
        
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $unit;
    }


    /**
     * 
     * Import metadata from JSONL (JSON Lines) file
     * 
     * For survey/microdata projects:
     * - First line contains project metadata (schema_type='survey' or 'microdata')
     * - Subsequent lines contain variables (schema_type='variable')
     * 
     * For other project types:
     * - First line contains project metadata (same as JSON)
     * 
     * @param int $sid - Project ID
     * @param string $jsonl_file_path - Path to JSONL file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options (type, created_by, etc.)
     * @return bool|array - Returns true or import result array
     * 
     */
    private function import_from_jsonl($sid,$jsonl_file_path,$validate=true,$options=array())
    {
        // Resolve canonical type for options['type'] once at the start (if set)
        $canonical_options_type = null;
        if (isset($options['type'])) {
            $canonical_options_type = $this->ci->Editor_model->resolve_canonical_type($options['type']) ?: $options['type'];
        }
        
        // Read file line by line
        $handle = fopen($jsonl_file_path, 'r');
        if ($handle === false){
            throw new Exception("Failed to open JSONL file: " . $jsonl_file_path);
        }

        $line_number = 0;
        $project_data = null;
        $first_line = true;
        
        // Batch processing for variables
        $batch_size = 200;
        $variable_batch = array();
        $batch_count = 0;
        $file_id_mappings = null;
        $project_type = null;
        $canonical_project_type = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $line_number++;
                $line = trim($line);
                
                // Skip empty lines
                if (empty($line)){
                    continue;
                }

                // Parse JSON line
                $json_data = json_decode($line, true);
                
                if ($json_data === null){
                    $json_error = json_last_error();
                    if ($json_error !== JSON_ERROR_NONE){
                        throw new Exception("Invalid JSON format on line " . $line_number . ": " . json_last_error_msg());
                    }
                }

                // Get schema_type from the line
                $schema_type = isset($json_data['schema_type']) ? $json_data['schema_type'] : 
                              (isset($json_data['type']) ? $json_data['type'] : null);

                // First line should contain project metadata
                if ($first_line) {
                    if (!$schema_type){
                        throw new Exception("Invalid JSONL file. First line missing 'schema_type' or 'type' field.");
                    }

                    // Resolve canonical type for schema_type once
                    $canonical_schema_type = $this->ci->Editor_model->resolve_canonical_type($schema_type) ?: $schema_type;
                    
                    // Check type mismatch using canonical types
                    if ($canonical_options_type !== null && $canonical_options_type !== $canonical_schema_type){
                        throw new Exception("Project type mismatched. The metadata 'type' is set to '".$schema_type."', but the project type is set to '".$options['type']."'.");
                    }

                    // Check if first line is project metadata (survey, microdata, or other types)
                    // Use canonical type for comparison
                    $is_microdata_type = ($canonical_schema_type === 'survey' || $canonical_schema_type === 'microdata');
                    if ($is_microdata_type || !in_array($schema_type, array('variable'))) {
                        $project_data = $json_data;
                        $project_data['type'] = $schema_type;
                        $project_type = $schema_type;
                        $canonical_project_type = $canonical_schema_type;
                        $first_line = false;
                        
                        // For survey/microdata, we need to process project metadata and datafiles first
                        // to get file_id_mappings before processing variables
                        if ($is_microdata_type) {
                            // Merge options into project data
                            $project_data_merged = array_merge($project_data, $options);
                            
                            // Use template if set [template_uid]
                            if (isset($project_data_merged['template_uid'])){
                                //get template by uid
                                $template=$this->ci->Editor_template_model->get_template_by_uid($project_data_merged['template_uid']);
                                
                                if (!$template){
                                    $project_data_merged['template_uid']=null;
                                }
                            }
                            
                            // Extract data_files from project_data
                            $datafiles = isset($project_data_merged['data_files']) ? $project_data_merged['data_files'] : array();
                            
                            // Import project metadata first
                            $project_data_for_import = $project_data_merged;
                            unset($project_data_for_import['data_files']);
                            unset($project_data_for_import['variables']);
                            unset($project_data_for_import['variable_groups']);
                            
                            $this->import_project_metadata($schema_type, $sid, $project_data_for_import, $validate);
                            
                            // Import data file metadata to get file_id_mappings
                            $file_id_mappings = $this->import_datafile_metadata($sid, $datafiles, $validate);
                        }
                    } else {
                        throw new Exception("Invalid JSONL file. First line must contain project metadata (schema_type should be 'survey', 'microdata', or other project type), but found: " . $schema_type);
                    }
                } else {
                    // Subsequent lines for survey/microdata projects
                    if ($schema_type == 'variable') {
                        // This is a variable line - add to batch
                        $variable_batch[] = $json_data;
                        $batch_count++;
                        
                        // Process batch when it reaches batch_size
                        if ($batch_count >= $batch_size) {
                            if ($file_id_mappings !== null) {
                                $this->import_variable_metadata($sid, $variable_batch, $file_id_mappings, $validate);
                            } else {
                                throw new Exception("File ID mappings not available when processing variable batch. This should not happen.");
                            }
                            
                            // Clear batch and reset counter
                            $variable_batch = array();
                            $batch_count = 0;
                            
                            // Free memory
                            unset($json_data);
                        }
                    } else {
                        // For non-survey/microdata projects, only process the first line
                        // If encounter more lines, log a warning but continue
                        log_message('info', "JSONL file contains additional lines after first line for non-survey/microdata project. Line " . $line_number . " will be ignored.");
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        if ($project_data === null){
            throw new Exception("Invalid JSONL file. No project metadata found in first line.");
        }

        // Process any remaining variables in the batch (for survey/microdata projects)
        // Use canonical type for comparison (fallback to original type if canonical not resolved)
        $canonical_type_for_check = $canonical_project_type ?: $project_type;
        $is_microdata_project = ($canonical_type_for_check === 'survey' || $canonical_type_for_check === 'microdata');
        if (!empty($variable_batch) && $is_microdata_project) {
            if ($file_id_mappings !== null) {
                $this->import_variable_metadata($sid, $variable_batch, $file_id_mappings, $validate);
            } else {
                throw new Exception("File ID mappings not available when processing remaining variable batch. This should not happen.");
            }
            // Clear batch
            $variable_batch = array();
        }

        // For non-survey/microdata projects, process project metadata
        // Use canonical type for comparison
        if (!$is_microdata_project) {
            // Merge options into project data
            $project_data = array_merge($project_data, $options);

            // Use template if set [template_uid]
            if (isset($project_data['template_uid'])){
                //get template by uid
                $template=$this->ci->Editor_template_model->get_template_by_uid($project_data['template_uid']);
                
                if (!$template){
                    $project_data['template_uid']=null;
                }
            }        

            //fix for geospatial - flatten identificationInfo array
            $type = $project_data['type'];
            if ($type=='geospatial'){
                if (isset($project_data['description']['identificationInfo']) && 
                        is_array($project_data['description']['identificationInfo']) &&
                        isset($project_data['description']['identificationInfo'][0])
                    ){
                    $project_data['description']['identificationInfo']=$project_data['description']['identificationInfo'][0];
                }
            }

            // Import based on project type
            if ($type=='geospatial'){
                $this->import_geospatial_project($type, $sid, $project_data, $validate, $options);
            }
            else{
                //import project metadata (other types - same as JSON)
                $this->import_project_metadata($type, $sid, $project_data, $validate);
            }
        }

        return true;
    }


    /**
     * 
     * Import metadata from XML file (DDI for surveys)
     * 
     * @param int $sid - Project ID
     * @param string $xml_file_path - Path to XML file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options
     * @return array - Import result with details
     * 
     */
    private function import_from_xml($sid,$xml_file_path,$validate=true,$options=array())
    {
        // Determine project type
        $type = isset($options['type']) ? $options['type'] : null;

        // Check if we need to get type from existing project
        if (!$type){
            $project = $this->ci->Editor_model->get_basic_info($sid);
            if ($project){
                $type = $project['type'];
            }
        }

        // Only survey/microdata types support XML import via DDI
        if ($type == 'survey' || $type == 'microdata'){
            return $this->import_ddi_from_file($sid, $xml_file_path, $validate, $options);
        }
        else if ($type == 'geospatial'){
            // Geospatial XML import
            $this->ci->load->library('Geospatial_import');
            $result = $this->ci->geospatial_import->import($sid, $xml_file_path);
            return $result;
        }
        else{
            throw new Exception("XML import is only supported for 'microdata' and 'geospatial' project types. Current type: " . ($type ? $type : 'unknown'));
        }
    }


    /**
     * 
     * Import DDI/XML from file path (not file upload)
     * 
     * Uses the existing Editor_model::import_ddi_from_path() method to avoid code duplication
     * 
     * @param int $sid - Project ID
     * @param string $ddi_file_path - Path to DDI XML file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options
     * @return array - Import result
     * 
     */
    private function import_ddi_from_file($sid, $ddi_file_path, $validate, $options)
    {
        // Use the centralized DDI import method
        return $this->ci->Editor_model->import_ddi_from_path($sid, $ddi_file_path, $parseOnly=false, $options);
    }


    function import_microdata_project($type, $sid,$json_data,$validate=true)
    {
        $datafiles=[];
        $variables=[];
        $variable_groups=[];

        //get data files, variables and variable groups
        if (isset($json_data['data_files'])){
            $datafiles=$json_data['data_files'];
            unset($json_data['data_files']);
        }

        if (isset($json_data['variables'])){
            $variables=$json_data['variables'];
            unset($json_data['variables']);
        }

        if (isset($json_data['variable_groups'])){
            $variable_groups=$json_data['variable_groups'];
            unset($json_data['variable_groups']);
        }

        $this->import_project_metadata($type, $sid,$json_data, $validate);

        //import data file metadata
        $file_id_mappings= $this->import_datafile_metadata($sid,$datafiles, $validate);

        //import variable metadata
        $this->import_variable_metadata($sid,$variables, $file_id_mappings, $validate);

        //import variable groups
        //$this->import_variable_groups($sid,$variable_groups, $validate);
    }
    
    /**
     * 
     * Import microdata project with streaming support for large arrays
     * 
     * This method processes variables and data_files in batches to reduce memory usage
     * 
     * @param string $type - Project type
     * @param int $sid - Project ID
     * @param array $json_data - Project metadata (without variables/data_files)
     * @param array $variables - Variables array (can be large)
     * @param array $datafiles - Data files array
     * @param bool $validate - Whether to validate
     * 
     */
    private function import_microdata_project_streaming($type, $sid, $json_data, $variables, $datafiles, $validate=true)
    {
        // Import project metadata first
        $this->import_project_metadata($type, $sid, $json_data, $validate);
        
        // Import data files
        $file_id_mappings = $this->import_datafile_metadata($sid, $datafiles, $validate);
        
        // Process variables in batches to reduce memory usage
        $batch_size = 500;
        $variable_batch = [];
        $batch_count = 0;
        
        foreach ($variables as $variable) {
            $variable_batch[] = $variable;
            $batch_count++;
            
            if ($batch_count >= $batch_size) {
                $this->import_variable_metadata($sid, $variable_batch, $file_id_mappings, $validate);
                $variable_batch = [];
                $batch_count = 0;
                
                // Free memory
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }
        
        // Process remaining variables
        if (!empty($variable_batch)) {
            $this->import_variable_metadata($sid, $variable_batch, $file_id_mappings, $validate);
        }
    }

    /**
     * 
     * Stream process data_files array from JSON file
     * 
     * @param int $sid - Project ID
     * @param string $json_file_path - Path to JSON file
     * @param bool $validate - Whether to validate
     * @return array - File ID mappings
     * 
     */
    private function stream_process_datafiles($sid, $json_file_path, $validate=true)
    {
        require_once(APPPATH.'../vendor/autoload.php');
        
        $datafiles = array();
        
        try {
            // Stream process data_files array using JSON Pointer
            $items = Items::fromFile($json_file_path, [
                'decoder' => new ExtJsonDecoder(true),
                'pointer' => '/data_files'
            ]);
            
            foreach ($items as $datafile) {
                $datafiles[] = $datafile;
            }
        } catch (Exception $e) {
            // If data_files doesn't exist or is empty, that's okay
            log_message('debug', 'No data_files array found or error reading: ' . $e->getMessage());
        }
        
        // Import data files (usually small, can process in memory)
        return $this->import_datafile_metadata($sid, $datafiles, $validate);
    }
    
    /**
     * 
     * Stream process variables array from JSON file in batches
     * 
     * This method uses JSON Pointer to stream the variables array and processes
     * them in batches to avoid loading all variables into memory at once.
     * 
     * @param int $sid - Project ID
     * @param string $json_file_path - Path to JSON file
     * @param array $file_id_mappings - File ID mappings from datafiles
     * @param bool $validate - Whether to validate
     * 
     */
    private function stream_process_variables($sid, $json_file_path, $file_id_mappings, $validate=true)
    {
        require_once(APPPATH.'../vendor/autoload.php');
        
        $batch_size = 200;
        $variable_batch = array();
        $batch_count = 0;
        $total_variables = 0;
        
        try {
            // Stream process variables array using JSON Pointer
            $items = Items::fromFile($json_file_path, array(
                'decoder' => new ExtJsonDecoder(true),
                'pointer' => '/variables',
            ));
            
            foreach ($items as $variable) {
                $variable_batch[] = $variable;
                $batch_count++;
                $total_variables++;
                
                // Process batch when it reaches batch_size
                if ($batch_count >= $batch_size) {
                    $this->import_variable_metadata($sid, $variable_batch, $file_id_mappings, $validate);
                    $variable_batch = array();
                    $batch_count = 0;
                    
                    // Free memory
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                    
                    log_message('debug', sprintf(
                        'Processed %d variables (batch) - Total: %d',
                        $batch_size,
                        $total_variables
                    ));
                }
            }
            
            // Process remaining variables
            if (!empty($variable_batch)) {
                $this->import_variable_metadata($sid, $variable_batch, $file_id_mappings, $validate);
                log_message('debug', sprintf(
                    'Processed final batch of %d variables - Total: %d',
                    count($variable_batch),
                    $total_variables
                ));
            }
            
            log_message('info', sprintf(
                'Completed streaming import of %d variables for project %d',
                $total_variables,
                $sid
            ));
            
        } catch (Exception $e) {
            // If variables array doesn't exist, that's okay (some projects may not have variables)
            log_message('debug', 'No variables array found or error reading: ' . $e->getMessage());
        }
    }
    
    /**
     * 
     * Stream process feature catalogue from JSON file
     * 
     * @param int $sid - Project ID
     * @param string $json_file_path - Path to JSON file
     * @param array $options - Additional options
     * @param bool $validate - Whether to validate
     * 
     */
    private function stream_process_feature_catalogue($sid, $json_file_path, $options=array(), $validate=true)
    {
        require_once(APPPATH.'../vendor/autoload.php');
        
        $feature_types = array();
        
        try {
            // Try to get feature_catalogue from description.feature_catalogue.featureType
            $items = Items::fromFile($json_file_path, [
                'decoder' => new ExtJsonDecoder(true),
                'pointer' => '/description/feature_catalogue/featureType'
            ]);
            
            foreach ($items as $feature_type) {
                $feature_types[] = $feature_type;
            }
        } catch (Exception $e) {
            // Try root level feature_catalogue.featureType
            try {
                $items = Items::fromFile($json_file_path, [
                    'decoder' => new ExtJsonDecoder(true),
                    'pointer' => '/feature_catalogue/featureType'
                ]);
                
                foreach ($items as $feature_type) {
                    $feature_types[] = $feature_type;
                }
            } catch (Exception $e2) {
                // No feature catalogue found, that's okay
                log_message('debug', 'No feature catalogue found: ' . $e2->getMessage());
                return;
            }
        }
        
        // Import feature types if found
        if (!empty($feature_types)) {
            $user_id = isset($options['user_id']) ? $options['user_id'] : 
                      (isset($options['created_by']) ? $options['created_by'] : null);
            
            try {
                $result = $this->import_feature_catalogue($sid, $feature_types, $user_id, $validate);
                log_message('info', "Geospatial feature catalogue import completed: " . json_encode($result));
            } catch (Exception $e) {
                log_message('error', "Error importing feature catalogue: " . $e->getMessage());
                throw $e;
            }
        }
    }
    
    /**
     * 
     * Import project metadata
     * 
     */
    function import_project_metadata($type,$sid,$json_data,$validate=true)
    {
        $this->ci->Editor_model->update_project($type,$sid,$json_data,$validate);
    }
    
    /**
     * 
     * Process project metadata with type-specific handling
     * 
     * Extracts and processes special fields (data_files, variables, featureType) based on project type.
     * This method routes to the appropriate handler:
     * - Microdata/Survey: Extracts data_files, variables, variable_groups and processes them
     * - Geospatial: Extracts featureType from feature_catalogue and processes it
     * - Other types: Updates metadata normally
     * 
     * @param string $type - Project type (will be resolved to canonical type)
     * @param int $sid - Project ID
     * @param array $options - Project metadata (may contain special fields)
     * @param bool $validate - Whether to validate schema
     * @param array $import_options - Additional options (user_id, created_by, etc.)
     * @return void
     * 
     */
    function process_project_metadata($type, $sid, $options, $validate=true, $import_options=array())
    {
        // Resolve canonical type (e.g., "timeseries" -> "indicator")
        $resolved_type = $this->ci->Editor_model->resolve_canonical_type($type);
        if ($resolved_type === false) {
            throw new Exception("INVALID_TYPE: ".$type);
        }
        $type = $resolved_type;
        
        // Merge import_options into options for geospatial (needs user_id)
        if (!empty($import_options)) {
            $options = array_merge($options, $import_options);
        }
        
        // Route to type-specific handler
        if ($type === 'microdata' || $type === 'survey') {
            // For microdata/survey: extract and process data_files, variables, variable_groups
            $this->import_microdata_project($type, $sid, $options, $validate);
        }
        else if ($type === 'geospatial') {
            // For geospatial: extract and process featureType
            $this->import_geospatial_project($type, $sid, $options, $validate, $import_options);
        }
        else {
            // For other types: update metadata normally
            $this->import_project_metadata($type, $sid, $options, $validate);
        }
    }

    /**
     * 
     * Import data file metadata
     * 
     */
    function import_datafile_metadata($sid,$datafiles,$validate=true)
    {
        $file_id_mapping=[];//need these to map file_id in variables

        foreach($datafiles as $df_idx => $datafile)
        {
            // Validate required fields
            if (!isset($datafile['file_name'])){
                log_message('error', "Datafile missing 'file_name' field. Skipping datafile.");
                continue;
            }

            if (!isset($datafile['file_id'])){
                log_message('error', "Datafile missing 'file_id' field. Skipping datafile: " . $datafile['file_name']);
                continue;
            }

            //check if file exists
            $file_info=$this->ci->Editor_datafile_model->data_file_by_name($sid,$datafile['file_name']);

            if ($file_info){
                $file_id_mapping[$datafile['file_id']]=$file_info['file_id'];
                $datafile['file_id']=$file_info['file_id'];

                if ($validate){
                    $this->ci->Editor_datafile_model->validate($datafile);
                }            
    
                $this->ci->Editor_datafile_model->update($file_info['id'],$datafile);
            }
            else{
                $file_id=$this->ci->Editor_datafile_model->generate_fileid($sid);
                $file_id_mapping[$datafile['file_id']]=$file_id;
                $datafile['file_id']=$file_id;

                if ($validate){
                    $this->ci->Editor_datafile_model->validate($datafile);
                }
                
                $this->ci->Editor_datafile_model->insert($sid,$datafile);
            }
        }
        
        return $file_id_mapping;
    }


    /**
     * 
     * Import variable metadata
     * 
     */
    function import_variable_metadata($sid,$variables, $file_id_mappings, $validate=true)
    {
        // Batch load existing variables to avoid individual lookups
        $existing_vars_lookup = array();
        
        // Get unique fids from this batch
        $batch_fids = array();
        $batch_var_map = array(); // fid|name => true
        foreach($variables as $variable) {
            if (isset($variable['fid']) && isset($file_id_mappings[$variable['fid']])) {
                $fid = $file_id_mappings[$variable['fid']];
                $name = isset($variable['name']) ? $variable['name'] : null;
                if ($name) {
                    $batch_fids[$fid] = true;
                    $batch_var_map[$fid . '|' . $name] = true;
                }
            }
        }
        
        // Batch load existing variables for all fids in this batch
        if (!empty($batch_fids)) {
            $fids_list = array_keys($batch_fids);
            $this->ci->db->select('fid, name, uid');
            $this->ci->db->where('sid', $sid);
            $this->ci->db->where_in('fid', $fids_list);
            
            $existing_vars = $this->ci->db->get('editor_variables')->result_array();
            
            // Build lookup map: fid|name => uid (only for variables in this batch)
            foreach($existing_vars as $var) {
                $var_key = $var['fid'] . '|' . $var['name'];
                if (isset($batch_var_map[$var_key])) {
                    $existing_vars_lookup[$var_key] = $var['uid'];
                }
            }
        }
        
        foreach($variables as $var_idx => $variable){
            // Validate variable has required fid field
            if (!isset($variable['fid'])){
                log_message('error', "Variable missing 'fid' field. Skipping variable: " . (isset($variable['name']) ? $variable['name'] : 'unknown'));
                continue;
            }

            // Check if file_id mapping exists
            if (!isset($file_id_mappings[$variable['fid']])){
                log_message('error', "File ID mapping not found for fid: " . $variable['fid'] . ". Skipping variable: " . (isset($variable['name']) ? $variable['name'] : 'unknown'));
                continue;
            }

            if ($validate){
                $this->ci->Editor_variable_model->validate($variable);
            }

            $fid=$file_id_mappings[$variable['fid']];
            $variable['fid']=$fid;

            // Drop missing-flagged Sysmiss categories (use invd for system missing); then drop Sysmiss when invd present
            $this->sanitize_variable_categories_for_import($variable);

            //check if variable exists
            $var_key = $fid . '|' . (isset($variable['name']) ? $variable['name'] : '');
            $variable_info = null;
            
            if (isset($existing_vars_lookup[$var_key])) {
                // Variable exists - get full info
                $existing_uid = $existing_vars_lookup[$var_key];
                $variable_info = $this->ci->Editor_variable_model->variable($sid, $existing_uid, false);
            }
            
            if (!isset($variable['var_catgry_labels'])){
                $variable['var_catgry_labels']=$this->get_variable_category_value_labels($variable);
            }

            // Populate var_invalrng.values from categories with is_missing=1
            // Extract is_missing information before removing it
            if (isset($variable['var_catgry']) && is_array($variable['var_catgry'])) {
                $missing_values = array();
                foreach($variable['var_catgry'] as $cat) {
                    if ($this->category_marked_missing($cat)) {
                        if (isset($cat['value']) && $cat['value'] !== null && $cat['value'] !== '') {
                            $missing_values[] = (string)$cat['value'];
                        }
                    }
                }
                if (!empty($missing_values)) {
                    $variable['var_invalrng'] = array('values' => array_values(array_unique($missing_values)));
                } else if (!isset($variable['var_invalrng'])) {
                    $variable['var_invalrng'] = array('values' => array());
                }

                // Remove is_missing from categories (single source of truth is var_invalrng.values)
                foreach($variable['var_catgry'] as &$cat) {
                    if (isset($cat['is_missing'])) {
                        unset($cat['is_missing']);
                    }
                }
                unset($cat);
            }

            //remove fields
            $exclude=array("uid","sid");
            foreach($exclude as $field)
            {
                if (isset($variable[$field])){
                    unset($variable[$field]);
                }
            }            
            
            $variable['metadata']=$variable;

            if ($variable_info){
                $this->ci->Editor_variable_model->update($sid, $variable_info['uid'],$variable);
            }
            else{
                //if not exists, insert
                $this->ci->Editor_variable_model->insert($sid,$variable);
            }
        }
    }

    /**
     * Replace metadata for an existing data file and its variables.
     * Updates the data file row from payload, deletes existing variables, then imports variables from payload.
     * Payload format (same as export): { "datafile": {...}, "variables": [...] }
     *
     * @param int $sid Project ID
     * @param string $file_id Target data file ID to replace (e.g. F1)
     * @param array $payload Must have keys 'datafile' (object) and 'variables' (array)
     * @param bool $validate Whether to validate datafile and variables
     * @param int|null $user_id User ID for changed_by
     * @return array ['datafile' => updated row, 'variables_count' => N]
     */
    public function replace_datafile_metadata($sid, $file_id, $payload, $validate = true, $user_id = null)
    {
        if (!isset($payload['datafile']) || !is_array($payload['datafile'])) {
            throw new Exception("Payload must contain 'datafile' object.");
        }
        if (!isset($payload['variables']) || !is_array($payload['variables'])) {
            throw new Exception("Payload must contain 'variables' array.");
        }

        $existing = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);
        if (!$existing) {
            throw new Exception("Data file not found: " . $file_id);
        }

        $datafile = $payload['datafile'];
        $variables = $payload['variables'];

        // Allowed fields to update
        $allowed = array('description', 'case_count', 'var_count', 'producer', 'data_checks', 'missing_data', 'version', 'notes', 'metadata', 'wght');
        $options = array();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $datafile)) {
                $options[$key] = $datafile[$key];
            }
        }
        $options['changed'] = date("U");
        if ($user_id !== null) {
            $options['changed_by'] = $user_id;
        }

        // Skip validate_data_file for replace
        $this->ci->Editor_datafile_model->data_file_update($existing['id'], $options);

        // Delete existing variables
        $this->ci->Editor_datafile_model->delete_variables($sid, $file_id);

        // Import new variables
        $source_file_id = isset($datafile['file_id']) ? $datafile['file_id'] : $file_id;
        $file_id_mapping = array($source_file_id => $file_id);
        $this->import_variable_metadata($sid, $variables, $file_id_mapping, $validate);

        $updated = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);
        return array(
            'datafile' => $updated,
            'variables_count' => count($variables)
        );
    }

    /**
     * JSON variable import: omit missing-flagged Sysmiss from categories/labels/invalrng, then apply invd-based strip.
     */
    public function sanitize_variable_categories_for_import(&$variable)
    {
        $this->strip_sysmiss_category_when_marked_missing($variable);
        $this->strip_sysmiss_category_when_invd_present($variable);
    }

    /**
     * When a category has missing set and value Sysmiss, do not import it — system missing is carried by sumStat invd.
     */
    private function strip_sysmiss_category_when_marked_missing(&$variable)
    {
        $stripped_missing_sysmiss = false;
        if (isset($variable['var_catgry']) && is_array($variable['var_catgry'])) {
            $filtered = array();
            foreach ($variable['var_catgry'] as $cat) {
                if (isset($cat['value']) && $this->is_sysmiss_category_value($cat['value']) && $this->category_marked_missing($cat)) {
                    $stripped_missing_sysmiss = true;
                    continue;
                }
                $filtered[] = $cat;
            }
            if (count($filtered) > 0) {
                $variable['var_catgry'] = $filtered;
            } else {
                unset($variable['var_catgry']);
            }
        }
        if ($stripped_missing_sysmiss && isset($variable['var_catgry_labels']) && is_array($variable['var_catgry_labels'])) {
            $labs = array();
            foreach ($variable['var_catgry_labels'] as $row) {
                if (isset($row['value']) && $this->is_sysmiss_category_value($row['value'])) {
                    continue;
                }
                $labs[] = $row;
            }
            if (count($labs) > 0) {
                $variable['var_catgry_labels'] = $labs;
            } else {
                unset($variable['var_catgry_labels']);
            }
        }
        if (isset($variable['var_invalrng']['values']) && is_array($variable['var_invalrng']['values'])) {
            $vals = array();
            foreach ($variable['var_invalrng']['values'] as $v) {
                if ($this->is_sysmiss_category_value($v)) {
                    continue;
                }
                $vals[] = $v;
            }
            $variable['var_invalrng']['values'] = array_values($vals);
        }
    }

    private function category_marked_missing($cat)
    {
        if (!isset($cat['is_missing'])) {
            return false;
        }
        $im = $cat['is_missing'];
        return $im === '1' || $im === 1 || $im === 'Y' || $im === true;
    }

    /**
     * When var_sumstat has a positive invd, system missing is shown from that stat — remove Sysmiss rows
     * from var_catgry / var_catgry_labels / var_invalrng so import does not duplicate the UI row.
     */
    private function strip_sysmiss_category_when_invd_present(&$variable)
    {
        if (!$this->variable_has_positive_invd($variable)) {
            return;
        }
        if (isset($variable['var_catgry']) && is_array($variable['var_catgry'])) {
            $filtered = array();
            foreach ($variable['var_catgry'] as $cat) {
                if (isset($cat['value']) && $this->is_sysmiss_category_value($cat['value'])) {
                    continue;
                }
                $filtered[] = $cat;
            }
            if (count($filtered) > 0) {
                $variable['var_catgry'] = $filtered;
            } else {
                unset($variable['var_catgry']);
            }
        }
        if (isset($variable['var_catgry_labels']) && is_array($variable['var_catgry_labels'])) {
            $labs = array();
            foreach ($variable['var_catgry_labels'] as $row) {
                if (isset($row['value']) && $this->is_sysmiss_category_value($row['value'])) {
                    continue;
                }
                $labs[] = $row;
            }
            if (count($labs) > 0) {
                $variable['var_catgry_labels'] = $labs;
            } else {
                unset($variable['var_catgry_labels']);
            }
        }
        if (isset($variable['var_invalrng']['values']) && is_array($variable['var_invalrng']['values'])) {
            $vals = array();
            foreach ($variable['var_invalrng']['values'] as $v) {
                if ($this->is_sysmiss_category_value($v)) {
                    continue;
                }
                $vals[] = $v;
            }
            $variable['var_invalrng']['values'] = array_values($vals);
        }
    }

    private function variable_has_positive_invd($variable)
    {
        if (!isset($variable['var_sumstat']) || !is_array($variable['var_sumstat'])) {
            return false;
        }
        foreach ($variable['var_sumstat'] as $ss) {
            if (!isset($ss['type']) || $ss['type'] !== 'invd' || !isset($ss['value'])) {
                continue;
            }
            $v = $ss['value'];
            if ($v === '' || $v === null) {
                continue;
            }
            if (is_numeric($v) && (float)$v > 0) {
                return true;
            }
        }
        return false;
    }

    private function is_sysmiss_category_value($value)
    {
        return strcasecmp(trim((string)$value), 'Sysmiss') === 0;
    }

    function get_variable_category_value_labels($variable)
    {
        $labels=[];

        if (isset($variable['var_catgry'])){
            foreach($variable['var_catgry'] as $category){
                if (isset($category['value'])){                    
                    $labels[]=array(
                        'value'=>$category['value'],
                        'labl'=>isset($category['labl']) ? $category['labl'] : ''
                    );
                }
            }
        }

        return $labels;
    }    

    /**
     * 
     * Import geospatial project metadata
     * Extracts feature catalogue featureType and imports into database tables
     * 
     */
    function import_geospatial_project($type, $sid, $json_data, $validate=true, $options=array())
    {
        $feature_types = array();
        
        // Extract featureType from feature_catalogue
        // Check both locations: description.feature_catalogue.featureType (new structure) and feature_catalogue.featureType (root level)
        if (isset($json_data['description']['feature_catalogue']['featureType']) && 
            is_array($json_data['description']['feature_catalogue']['featureType'])) {
            $feature_types = $json_data['description']['feature_catalogue']['featureType'];
            // Remove from json_data before storing in project metadata
            unset($json_data['description']['feature_catalogue']['featureType']);
        }
        else if (isset($json_data['feature_catalogue']) && 
                 isset($json_data['feature_catalogue']['featureType']) && 
                 is_array($json_data['feature_catalogue']['featureType'])) {
            $feature_types = $json_data['feature_catalogue']['featureType'];
            
            // Move feature_catalogue to description.feature_catalogue if it doesn't exist there
            if (!isset($json_data['description']['feature_catalogue'])) {
                if (!isset($json_data['description'])) {
                    $json_data['description'] = array();
                }
                // Copy all feature_catalogue data except featureType
                $feature_catalogue_data = $json_data['feature_catalogue'];
                unset($feature_catalogue_data['featureType']);
                $json_data['description']['feature_catalogue'] = $feature_catalogue_data;
            } else {
                // If description.feature_catalogue already exists, just remove featureType from root level
                unset($json_data['feature_catalogue']['featureType']);
            }
            // Remove root level feature_catalogue (it's been moved or featureType removed)
            unset($json_data['feature_catalogue']);
        }
        
        // Import project metadata (without featureType)
        $this->import_project_metadata($type, $sid, $json_data, $validate);
        
        // Import feature types and characteristics
        if (!empty($feature_types)) {
            $user_id = isset($options['user_id']) ? $options['user_id'] : (isset($options['created_by']) ? $options['created_by'] : null);
            try {
                $result = $this->import_feature_catalogue($sid, $feature_types, $user_id, $validate);
                log_message('info', "Geospatial feature catalogue import completed: " . json_encode($result));
            } catch (Exception $e) {
                log_message('error', "Error importing feature catalogue: " . $e->getMessage());
                log_message('error', "Stack trace: " . $e->getTraceAsString());
                throw $e;
            }
        } else {
            log_message('info', "No feature types found in feature_catalogue for project {$sid}");
        }
    }

    /**
     * 
     * Import feature catalogue (feature types and characteristics) into database
     * 
     * @param int $sid - Project ID
     * @param array $feature_types - Array of featureType objects from JSON
     * @param int $user_id - User ID for created_by/changed_by
     * @param bool $validate - Whether to validate data
     * 
     */
    function import_feature_catalogue($sid, $feature_types, $user_id=null, $validate=true)
    {
        $features_imported = 0;
        $characteristics_imported = 0;
        
        foreach ($feature_types as $idx => $feature_type) {
            try {
                // Extract feature type data
                $type_name = isset($feature_type['typeName']) ? $feature_type['typeName'] : '';
                $code = isset($feature_type['code']) ? $feature_type['code'] : '';
                $definition = isset($feature_type['definition']) ? $feature_type['definition'] : '';
                $is_abstract = isset($feature_type['isAbstract']) ? $feature_type['isAbstract'] : false;
                $carrier_of_characteristics = isset($feature_type['carrierOfCharacteristics']) && is_array($feature_type['carrierOfCharacteristics']) 
                    ? $feature_type['carrierOfCharacteristics'] 
                    : array();
                
                if (empty($type_name)) {
                    log_message('info', 'Skipping feature type with empty typeName');
                    continue;
                }
                
                // Prepare feature metadata
                $feature_metadata = array();
                if (!empty($definition)) {
                    $feature_metadata['definition'] = $definition;
                }
                if ($is_abstract !== false) {
                    $feature_metadata['isAbstract'] = $is_abstract;
                }
                
                // Check if feature exists by code or name within the project
                $existing_feature = null;
                
                // Check by code within project first (if code is provided)
                if (!empty($code)) {
                    $existing_feature = $this->ci->Geospatial_features_model->select_by_code_and_project($code, $sid);
                }
                
                // If not found by code, check by name within project
                if (!$existing_feature) {
                    $project_features = $this->ci->Geospatial_features_model->select_by_project($sid);
                    foreach ($project_features as $proj_feature) {
                        if (isset($proj_feature['name']) && $proj_feature['name'] == $type_name) {
                            $existing_feature = $proj_feature;
                            break;
                        }
                    }
                }
                
                // Prepare feature data
                $feature_data = array(
                    'sid' => $sid,
                    'name' => $type_name,
                    'code' => !empty($code) ? $code : null,
                    'metadata' => !empty($feature_metadata) ? $feature_metadata : null
                );
                
                if ($user_id) {
                    $feature_data['created_by'] = $user_id;
                    $feature_data['changed_by'] = $user_id;
                }
                
                // Insert or update feature
                if ($existing_feature) {
                    // Update existing feature
                    $this->ci->Geospatial_features_model->update($existing_feature['id'], $feature_data);
                    $feature_id = $existing_feature['id'];
                    log_message('info', "Updated existing feature: {$type_name} (ID: {$feature_id})");
                } else {
                    // Insert new feature
                    try {
                        $feature_id = $this->ci->Geospatial_features_model->insert($feature_data);
                        if (!$feature_id) {
                            throw new Exception("Insert returned no ID");
                        }
                        log_message('info', "Inserted new feature: {$type_name} (ID: {$feature_id})");
                        $features_imported++;
                    } catch (Exception $e) {
                        log_message('error', "Failed to insert feature {$type_name}: " . $e->getMessage());
                        log_message('error', "Feature data: " . json_encode($feature_data));
                        throw $e;
                    }
                }
                
                // Import characteristics
                if (!empty($carrier_of_characteristics) && $feature_id) {
                    foreach ($carrier_of_characteristics as $characteristic) {
                        try {
                            $char_member_name = isset($characteristic['memberName']) ? $characteristic['memberName'] : '';
                            if (empty($char_member_name)) {
                                log_message('info', "Skipping characteristic with empty memberName for feature: {$type_name}");
                                continue;
                            }
                            
                            // Prepare characteristic metadata
                            $char_metadata = array();
                            if (isset($characteristic['definition']) && !empty($characteristic['definition'])) {
                                $char_metadata['definition'] = $characteristic['definition'];
                            }
                            if (isset($characteristic['code']) && !empty($characteristic['code'])) {
                                $char_metadata['code'] = $characteristic['code'];
                            }
                            if (isset($characteristic['cardinality']) && is_array($characteristic['cardinality'])) {
                                $char_metadata['cardinality'] = $characteristic['cardinality'];
                            }
                            if (isset($characteristic['valueMeasurementUnit']) && !empty($characteristic['valueMeasurementUnit'])) {
                                $char_metadata['valueMeasurementUnit'] = $characteristic['valueMeasurementUnit'];
                            }
                            if (isset($characteristic['listedValue']) && is_array($characteristic['listedValue']) && !empty($characteristic['listedValue'])) {
                                $char_metadata['listedValue'] = $characteristic['listedValue'];
                            }
                            
                            // Check if characteristic exists
                            $existing_char = $this->ci->Geospatial_feature_chars_model->select_by_name_and_feature($char_member_name, $feature_id);
                            
                            // Prepare characteristic data
                            $char_data = array(
                                'sid' => $sid,
                                'feature_id' => $feature_id,
                                'name' => $char_member_name,
                                'label' => isset($characteristic['definition']) ? $characteristic['definition'] : null,
                                'data_type' => isset($characteristic['valueType']) ? $characteristic['valueType'] : 'string',
                                'metadata' => !empty($char_metadata) ? $char_metadata : null
                            );
                            
                            if ($user_id) {
                                $char_data['created_by'] = $user_id;
                                $char_data['changed_by'] = $user_id;
                            }
                            
                            // Insert or update characteristic
                            if ($existing_char) {
                                // Update existing characteristic
                                $this->ci->Geospatial_feature_chars_model->update($existing_char['id'], $char_data);
                                log_message('info', "Updated existing characteristic: {$char_member_name} for feature: {$type_name}");
                            } else {
                                // Insert new characteristic
                                $this->ci->Geospatial_feature_chars_model->insert($char_data);
                                log_message('info', "Inserted new characteristic: {$char_member_name} for feature: {$type_name}");
                                $characteristics_imported++;
                            }
                        } catch (Exception $e) {
                            // Log error but continue with other characteristics
                            log_message('error', "Failed to import characteristic {$char_member_name} for feature {$type_name}: " . $e->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but continue with other features
                log_message('error', "Failed to import feature type {$type_name}: " . $e->getMessage());
            }
        }
        
        log_message('info', "Feature catalogue import completed. Features: {$features_imported} new, Characteristics: {$characteristics_imported} new");
        
        return array(
            'features_imported' => $features_imported,
            'characteristics_imported' => $characteristics_imported
        );
    }

}


