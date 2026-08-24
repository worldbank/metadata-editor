<?php
defined('BASEPATH') OR exit('No direct script access allowed');


require(APPPATH.'/libraries/MY_REST_Controller.php');

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

class Validation extends MY_REST_Controller
{
    private $api_user;

    function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
        $this->load->model('Metadata_schemas_model');
        $this->load->library('editor_acl');
        $this->is_authenticated_or_die();
        $this->api_user = $this->api_user();
    }
    
    function _auth_override_check()
    {
        if ($this->session->userdata('user_id')){
            return true;
        }
        return parent::_auth_override_check();
    }

    /**
     * Get schema validation report for a project
     * 
     * @param int $sid Project ID
     */
    function schema_get($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='view');

            $metadata = $project['metadata'];
            $type = $project['type'];
            
            // Resolve canonical type
            $canonical_type = $type;
            try {
                $schema_row = $this->Metadata_schemas_model->get_by_uid($type);
                if ($schema_row && isset($schema_row['uid']) && $schema_row['uid']) {
                    $canonical_type = $schema_row['uid'];
                }
            } catch(Exception $e) {
                // If schema registry lookup fails, use original type
            }

            $result = array(
                'schema_uid' => $canonical_type,
                'valid' => false,
                'type' => $type,
                'issues' => array()
            );

            // Get schema file path using schema registry (handles aliases and custom schemas)
            try {
                $schema_file = $this->Metadata_schemas_model->get_schema_file_path($type);
            } catch(Exception $e) {
                $result['error'] = "Schema file not found for type: $type - " . $e->getMessage();
                $response = array(
                    'status' => 'success',
                    'result' => $result
                );
                $this->set_response($response, REST_Controller::HTTP_OK);
                return;
            }

            // Use validation library
            $this->load->library('Project_validation');
            
            // Load compiled schema for PHP-specific checks
            $schema = $this->Metadata_schemas_model->get_by_uid($canonical_type);
            $compiled_schema = null;
            
            if ($schema) {
                $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
                if (is_dir($schema_dir)) {
                    $this->load->library('Schema_registry');
                    $actual_filename = basename($schema_file);
                    $schema_for_loading = $schema;
                    $schema_for_loading['filename'] = $actual_filename;
                    $documents = $this->schema_registry->load_schema_documents($schema_for_loading, $schema_dir);
                    $compiled_schema = $this->schema_registry->inline_schema($actual_filename, $documents, $schema_dir);
                }
            }
            
            // Validate using library
            $validation_result = $this->project_validation->validate_schema($metadata, $type, $schema_file, $compiled_schema);
            
            $result = array_merge($result, $validation_result);

            $response = array(
                'status' => 'success',
                'validation' => $result
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
     * Get variables validation report for a project
     * 
     * @param int $sid Project ID
     */
    function variables_get($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='view');

            $type = $project['type'];
            
            // Only validate variables for microdata/survey projects
            if ($type !== 'microdata' && $type !== 'survey') {
                throw new Exception("Variables validation is only available for microdata projects");
            }

            // Get limit parameter (default to 50, max 1000)
            $limit = $this->get('limit');
            $limit = $limit ? min((int)$limit, 1000) : 50;

            $mode = $this->get('mode');
            if ($mode === 'light') {
                // Fast SQL-only check: empty variable labels (no schema validation, no metadata decode)
                $result = $this->_variables_validation_light($sid, $limit);
                $response = array(
                    'status' => 'success',
                    'validation' => $result
                );
                $this->set_response($response, REST_Controller::HTTP_OK);
                return;
            }

            $result = array(
                'valid' => true,
                'issues' => array()
            );

            // Load variable model
            $this->load->model('Editor_variable_model');
            
            // Validate all variables (full schema validation)
            $issues = array();
            $validated_count = 0;
            
            foreach($this->Editor_variable_model->chunk_reader_generator($sid) as $variable) {
                // Stop if we've reached the limit
                if (count($issues) >= $limit) {
                    break;
                }
                
                // Get variable metadata (needed in catch blocks)
                $var_metadata = isset($variable['metadata']) ? $variable['metadata'] : array();
                $variable_fid = isset($variable['fid']) ? $variable['fid'] : 'unknown';
                $variable_name = isset($var_metadata['name']) ? $var_metadata['name'] : 'unknown';
                
                try {
                    // Remove empty values for validation
                    $var_metadata_clean = array_remove_empty($var_metadata);
                    
                    // Validate against variable schema
                    $this->Editor_variable_model->validate_schema($var_metadata_clean);
                    $validated_count++;
                }
                catch(ValidationException $e) {
                    $validation_errors = $e->GetValidationErrors();
                    
                    // Format errors for frontend
                    foreach($validation_errors as $error) {
                        $property = isset($error['property']) ? $error['property'] : '';
                        $path = 'variables/' . $variable_fid . ($property ? '/' . $property : '');
                        
                        $issues[] = array(
                            'type' => 'variable_validation_error',
                            'property' => $property,
                            'path' => $path,
                            'message' => isset($error['message']) ? $error['message'] : 'Validation error',
                            'variable_fid' => $variable_fid,
                            'variable_name' => $variable_name,
                            'variable_uid' => isset($variable['uid']) ? $variable['uid'] : null
                        );
                        
                        // Stop if we've reached the limit
                        if (count($issues) >= $limit) {
                            break 2; // Break out of both loops
                        }
                    }
                }
                catch(Exception $e) {
                    // Handle other exceptions
                    $issues[] = array(
                        'type' => 'variable_validation_error',
                        'property' => '',
                        'path' => 'variables/' . $variable_fid,
                        'message' => $e->getMessage(),
                        'variable_fid' => $variable_fid,
                        'variable_name' => $variable_name,
                        'variable_uid' => isset($variable['uid']) ? $variable['uid'] : null
                    );
                    
                    // Stop if we've reached the limit
                    if (count($issues) >= $limit) {
                        break;
                    }
                }
            }

            $result['valid'] = empty($issues);
            $result['issues'] = $issues;
            $result['validated_count'] = $validated_count;
            $result['total_issues'] = count($issues);
            if (count($issues) >= $limit) {
                $result['limit_reached'] = true;
            }

            $response = array(
                'status' => 'success',
                'validation' => $result
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
     * Light variables validation: SQL-only check for empty labels (no schema, no metadata decode).
     * Used when mode=light on the variables validation endpoint.
     *
     * @param int $sid Project ID
     * @param int $limit Max number of issues to return
     * @return array Same shape as variables_get result: valid, issues, validated_count, total_issues, limit_reached?
     */
    private function _variables_validation_light($sid, $limit)
    {
        $sid = (int) $sid;
        $this->db->select('uid, sid, fid, vid, name, labl');
        $this->db->from('editor_variables');
        $this->db->where('sid', $sid);
        $this->db->where('(labl IS NULL OR TRIM(COALESCE(labl,\'\')) = \'\')', null, false);
        $this->db->limit($limit);
        $this->db->order_by('uid', 'asc');
        $rows = $this->db->get()->result_array();

        $issues = array();
        foreach ($rows as $row) {
            $variable_fid = isset($row['fid']) ? $row['fid'] : 'unknown';
            $variable_name = isset($row['name']) ? $row['name'] : 'unknown';
            $issues[] = array(
                'type' => 'variable_validation_error',
                'property' => 'labl',
                'path' => 'variables/' . $variable_fid,
                'message' => 'The property labl is required',
                'variable_fid' => $variable_fid,
                'variable_name' => $variable_name,
                'variable_uid' => isset($row['uid']) ? $row['uid'] : null
            );
        }

        $result = array(
            'valid' => empty($issues),
            'issues' => $issues,
            'validated_count' => count($issues),
            'total_issues' => count($issues)
        );
        if (count($issues) >= $limit) {
            $result['limit_reached'] = true;
        }
        return $result;
    }

    /**
     * Get template validation report for a project.
     * Optional query param template_uid validates against that template instead of the assigned one.
     *
     * @param int $sid Project ID
     */
    function template_get($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='view');

            $resolved = $this->_resolve_validation_template($project);
            $result = array(
                'template_uid' => $resolved['template_uid'],
                'template_uid_source' => $resolved['template_uid_source'],
                'valid' => null,
                'issues' => array(),
                'validation_report' => array()
            );

            if (!empty($resolved['error'])) {
                $result['error'] = $resolved['error'];
            } else {
                $this->load->library('Project_validation');
                $validation_result = $this->project_validation->validate_template($project['metadata'], $resolved['template_data']);
                $result = array_merge($result, $validation_result);
                $result['template_uid'] = $resolved['template_uid'];
                $result['template_uid_source'] = $resolved['template_uid_source'];
            }

            $response = array(
                'status' => 'success',
                'validation' => $result
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
     * Resolve which template to use for validation/extra-field reports.
     * Query param template_uid overrides (dry-run; does not save).
     * Otherwise: assigned project template, then the type default, then the first core template
     * (same order as Editor_template_model::resolve_template_for_project).
     * Override not-found or type mismatch throw (HTTP 400). Missing templates stay as result.error (HTTP 200).
     *
     * @param array $project
     * @return array{template_uid: ?string, template_uid_source: string, template_data: array, error: ?string}
     */
    private function _resolve_validation_template($project)
    {
        $assigned_uid = isset($project['template_uid']) ? trim((string) $project['template_uid']) : '';
        $override_uid = $this->get('template_uid');
        if ($override_uid === null || $override_uid === false || $override_uid === '') {
            $override_uid = $this->input->get('template_uid');
        }
        if (is_array($override_uid)) {
            $override_uid = '';
        }
        $override_uid = is_string($override_uid) ? trim($override_uid) : '';
        $is_override = ($override_uid !== '');

        $this->load->model('Editor_template_model');

        $resolved = array(
            'template_uid' => null,
            'template_uid_source' => $is_override ? 'override' : 'assigned',
            'template_data' => array(),
            'error' => null
        );

        if ($is_override) {
            $template = $this->Editor_template_model->get_template_by_uid($override_uid);
            if (!$template) {
                throw new Exception("Template not found: $override_uid");
            }
            $source = 'override';
        } else {
            try {
                $template = $this->Editor_template_model->resolve_template_for_project($project);
            } catch (Exception $e) {
                $resolved['error'] = $e->getMessage();
                return $resolved;
            }
            $source = $this->_validation_template_source($project, $assigned_uid, $template['uid']);
        }

        $resolved['template_uid'] = $template['uid'];
        $resolved['template_uid_source'] = $source;

        $project_type = $this->Editor_model->resolve_canonical_type($project['type']);
        if ($project_type === false) {
            $project_type = $project['type'];
        }
        $template_type = $this->Editor_model->resolve_canonical_type($template['data_type']);
        if ($template_type === false) {
            $template_type = $template['data_type'];
        }

        if ($project_type != $template_type) {
            $message = "TEMPLATE_TYPE_MISMATCHED: ".$template['data_type'].'!='.$project['type'];
            if ($is_override) {
                throw new Exception($message);
            }
            $resolved['error'] = $message;
            return $resolved;
        }

        $template_data = (isset($template['template']) && is_array($template['template'])) ? $template['template'] : array();
        if (empty($template_data) || !isset($template_data['items']) || !is_array($template_data['items']) || empty($template_data['items'])) {
            $resolved['error'] = "Template has no items defined";
            return $resolved;
        }

        $resolved['template_data'] = $template_data;
        return $resolved;
    }

    /**
     * @param array $project
     * @param string $assigned_uid
     * @param string $resolved_uid
     * @return string assigned|default|core
     */
    private function _validation_template_source($project, $assigned_uid, $resolved_uid)
    {
        if ($assigned_uid !== '' && $assigned_uid === $resolved_uid) {
            return 'assigned';
        }

        $default = $this->Editor_template_model->get_default_template($project['type']);
        if (!empty($default['template_uid']) && $default['template_uid'] === $resolved_uid) {
            return 'default';
        }

        return 'core';
    }

    // All validation logic has been moved to Project_validation library
    // The following private methods have been removed as they are now in the library:
    // - map_frontend_to_backend_rules
    // - validate_template_items
    // - collect_template_keys
    // - find_template_extra_fields
    // - has_template_children
    // - find_extra_fields
    // - check_php_specific_issues
    // - validate_field_types (deprecated)
    // - get_schema_type
    // - is_type_allowed
    // - get_allowed_types_string
    // - is_object_with_numeric_keys
    // - convert_object_to_array
    // - is_type_mismatch_fixable
    // - convert_value_to_type
    // - map_php_to_schema_type
    // - resolve_schema_ref
    // - get_value_preview
    // - get_php_type
    // - get_value_by_path
    // - create_additional_key
    // - get_field_definition_for_issue
    //
    // Use $this->project_validation->method_name() to access these methods

    /**
     * Get extra fields detection report for a project
     * Finds fields in metadata that are not defined in the schema
     * 
     * @param int $sid Project ID
     */
    function extra_fields_get($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='view');

            $metadata = $project['metadata'];
            $type = $project['type'];
            
            // Resolve canonical type
            $canonical_type = $type;
            try {
                $this->load->model('Metadata_schemas_model');
                $schema_row = $this->Metadata_schemas_model->get_by_uid($type);
                if ($schema_row && isset($schema_row['uid']) && $schema_row['uid']) {
                    $canonical_type = $schema_row['uid'];
                }
            } catch(Exception $e) {
                // If schema registry lookup fails, use original type
            }

            $result = array(
                'schema_uid' => $canonical_type,
                'extra_fields' => array()
            );

            // Load compiled schema
            $this->load->library('Schema_registry');
            $schema = $this->Metadata_schemas_model->get_by_uid($canonical_type);

            if (!$schema){
                $result['error'] = "Schema not found: $canonical_type";
            } else {
                // Get schema file path using schema registry (handles aliases and custom schemas)
                try {
                    $schema_file = $this->Metadata_schemas_model->get_schema_file_path($type);
                } catch(Exception $e) {
                    $result['error'] = "Schema file not found for type: $type - " . $e->getMessage();
                    $response = array(
                        'status' => 'success',
                        'result' => $result
                    );
                    $this->set_response($response, REST_Controller::HTTP_OK);
                    return;
                }
                
                $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
                if (!is_dir($schema_dir)) {
                    $result['error'] = "Schema directory not found: $schema_dir";
                } else {
                    // Get the actual filename from the resolved schema file path
                    // This ensures we use the correct filename even if it differs from $schema['filename']
                    $actual_filename = basename($schema_file);
                    
                    // Create a schema object with the correct filename for load_schema_documents
                    $schema_for_loading = $schema;
                    $schema_for_loading['filename'] = $actual_filename;
                    
                    // Get compiled schema
                    $documents = $this->schema_registry->load_schema_documents($schema_for_loading, $schema_dir);
                    $compiled_schema = $this->schema_registry->inline_schema($actual_filename, $documents, $schema_dir);
                    
                    // Use validation library
                    $this->load->library('Project_validation');
                    $result['extra_fields'] = $this->project_validation->find_extra_fields($metadata, $compiled_schema);
                }
            }

            $response = array(
                'status' => 'success',
                'result' => $result
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
     * Get template extra fields detection report for a project
     * Finds fields in metadata that are not defined in the current template and not displayed
     * 
     * @param int $sid Project ID
     */
    function template_extra_fields_get($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='view');

            $resolved = $this->_resolve_validation_template($project);

            $result = array(
                'template_uid' => $resolved['template_uid'],
                'template_uid_source' => $resolved['template_uid_source'],
                'extra_fields' => array()
            );

            if (!empty($resolved['error'])) {
                $result['error'] = $resolved['error'];
            } else {
                $this->load->library('Project_validation');
                $result['extra_fields'] = $this->project_validation->find_template_extra_fields($project['metadata'], $resolved['template_data']);
            }

            $response = array(
                'status' => 'success',
                'result' => $result
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
     * Move extra fields to additional section
     * 
     * @param int $sid Project ID
     */
    function move_to_additional_post($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='edit');

            $this->Editor_model->check_project_editable($sid);

            $paths = $this->post('paths');
            if (!is_array($paths) || empty($paths)){
                throw new Exception("paths parameter is required and must be an array");
            }

            $metadata = $project['metadata'];

            // Build JSON Patch operations
            $patches = array();
            $moved_fields = array();
            $errors = array();

            $this->load->library('Project_validation');
            
            // Move each field to additional
            foreach ($paths as $path) {
                try {
                    // Get value from path
                    $value = $this->project_validation->get_value_by_path($metadata, $path);
                    
                    if ($value !== null) {
                        // Create field key from path (use last segment)
                        $path_parts = explode('/', trim($path, '/'));
                        $field_key = end($path_parts);
                        
                        // Handle nested paths - preserve structure in additional
                        // Convert path to additional path format
                        $additional_key = $this->project_validation->create_additional_key($path);
                        
                        // Create additional path in JSON Pointer format
                        $additional_path = '/additional';
                        if (!empty($additional_key)) {
                            $additional_path_parts = explode('.', $additional_key);
                            foreach ($additional_path_parts as $part) {
                                $additional_path .= '/' . $part;
                            }
                        }
                        
                        // Important: Add to additional FIRST, then remove from original
                        // This ensures data is preserved even if remove fails
                        
                        // Add operation: add to additional (JsonPatch creates intermediate paths if needed)
                        $patches[] = array(
                            'op' => 'add',
                            'path' => $additional_path,
                            'value' => $value
                        );
                        
                        // Remove operation: remove from original location
                        $patches[] = array(
                            'op' => 'remove',
                            'path' => $path
                        );
                        
                        $moved_fields[] = array(
                            'path' => $path,
                            'key' => $field_key,
                            'additional_key' => $additional_key,
                            'additional_path' => $additional_path
                        );
                    }
                } catch(Exception $e) {
                    $errors[] = array(
                        'path' => $path,
                        'error' => $e->getMessage()
                    );
                }
            }

            // Apply patches if any using patch_project method
            if (!empty($patches)) {
                $type = $project['type'];
                $this->Editor_model->patch_project($type, $sid, array('patches' => $patches), $validate=false);
            }

            $response = array(
                'status' => 'success',
                'moved_fields' => $moved_fields,
                'errors' => $errors
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
     * Fix array-as-object issues by converting objects with numeric keys to arrays
     * 
     * @param int $sid Project ID
     */
    function fix_array_as_object_post($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='edit');
            $this->Editor_model->check_project_editable($sid);

            $paths = $this->post('paths');
            if (!is_array($paths) || empty($paths)){
                throw new Exception("paths parameter is required and must be an array");
            }

            $metadata = $project['metadata'];
            $type = $project['type'];
            
            // Resolve canonical type
            $canonical_type = $type;
            try {
                $schema_row = $this->Metadata_schemas_model->get_by_uid($type);
                if ($schema_row && isset($schema_row['uid']) && $schema_row['uid']) {
                    $canonical_type = $schema_row['uid'];
                }
            } catch(Exception $e) {
                // If schema registry lookup fails, use original type
            }

            // Load compiled schema
            $this->load->library('Schema_registry');
            $schema = $this->Metadata_schemas_model->get_by_uid($canonical_type);

            if (!$schema){
                throw new Exception("Schema not found: $canonical_type");
            }

            // Get schema file path using schema registry (handles aliases and custom schemas)
            try {
                $schema_file = $this->Metadata_schemas_model->get_schema_file_path($type);
            } catch(Exception $e) {
                throw new Exception("Schema file not found for type: $type - " . $e->getMessage());
            }

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: $schema_dir");
            }

            // Get the actual filename from the resolved schema file path
            // This ensures we use the correct filename even if it differs from $schema['filename']
            $actual_filename = basename($schema_file);
            
            // Create a schema object with the correct filename for load_schema_documents
            $schema_for_loading = $schema;
            $schema_for_loading['filename'] = $actual_filename;

            // Get compiled schema
            $documents = $this->schema_registry->load_schema_documents($schema_for_loading, $schema_dir);
            $compiled_schema = $this->schema_registry->inline_schema($actual_filename, $documents, $schema_dir);

            // Build JSON Patch operations for fixing arrays
            $patches = array();
            $fixed_fields = array();
            $errors = array();

            $this->load->library('Project_validation');
            
            foreach ($paths as $path) {
                try {
                    // Get value from path
                    $value = $this->project_validation->get_value_by_path($metadata, $path);
                    
                    if ($value !== null) {
                        // Check if it's an object with numeric keys
                        if ($this->project_validation->is_object_with_numeric_keys($value)) {
                            // Convert to array
                            $converted_value = $this->project_validation->convert_object_to_array($value);
                            
                            // Add replace operation to patches
                            $patches[] = array(
                                'op' => 'replace',
                                'path' => $path,
                                'value' => $converted_value
                            );
                            
                            $fixed_fields[] = array(
                                'path' => $path,
                                'fixed' => true,
                                'before' => $this->project_validation->get_value_preview($value),
                                'after' => $this->project_validation->get_value_preview($converted_value)
                            );
                        } else {
                            $fixed_fields[] = array(
                                'path' => $path,
                                'fixed' => false,
                                'message' => 'Field is not an object with numeric keys'
                            );
                        }
                    } else {
                        $fixed_fields[] = array(
                            'path' => $path,
                            'fixed' => false,
                            'message' => 'Field not found'
                        );
                    }
                } catch(Exception $e) {
                    $errors[] = array(
                        'path' => $path,
                        'error' => $e->getMessage()
                    );
                }
            }

            // Apply patches if any using patch_project method
            if (!empty($patches)) {
                $this->Editor_model->patch_project($type, $sid, array('patches' => $patches), $validate=false);
            }

            $response = array(
                'status' => 'success',
                'fixed_fields' => $fixed_fields,
                'errors' => $errors
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
     * Remove extra fields from metadata
     * 
     * @param int $sid Project ID
     */
    function remove_fields_post($sid=null)
    {
        try{
            $sid = $this->get_sid($sid);
            $project = $this->Editor_model->get_row($sid);

            if (!$project){
                throw new Exception("project not found");
            }

            $this->editor_acl->user_has_project_access($sid, $permission='edit');

            $this->Editor_model->check_project_editable($sid);

            $paths = $this->post('paths');
            if (!is_array($paths) || empty($paths)){
                throw new Exception("paths parameter is required and must be an array");
            }

            $metadata = $project['metadata'];

            // Build JSON Patch operations for removing fields
            $patches = array();
            $removed_fields = array();
            $errors = array();

            $this->load->library('Project_validation');

            foreach ($paths as $path) {
                try {
                    // Check if field exists
                    $value = $this->project_validation->get_value_by_path($metadata, $path);
                    
                    if ($value !== null) {
                        // Add remove operation to patches
                        $patches[] = array(
                            'op' => 'remove',
                            'path' => $path
                        );
                        
                        $removed_fields[] = array(
                            'path' => $path,
                            'removed' => true
                        );
                    } else {
                        $removed_fields[] = array(
                            'path' => $path,
                            'removed' => false,
                            'message' => 'Field not found'
                        );
                    }
                } catch(Exception $e) {
                    $errors[] = array(
                        'path' => $path,
                        'error' => $e->getMessage()
                    );
                }
            }

            // Apply patches if any using patch_project method
            if (!empty($patches)) {
                $type = $project['type'];
                $this->Editor_model->patch_project($type, $sid, array('patches' => $patches), $validate=false);
            }

            $response = array(
                'status' => 'success',
                'removed_fields' => $removed_fields,
                'errors' => $errors
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
}
