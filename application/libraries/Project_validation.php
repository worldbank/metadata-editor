<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

/**
 * Project Validation Library
 * 
 * Handles validation of project metadata against schemas and templates.
 * Provides methods for schema validation, template validation, and extra field detection.
 */
class Project_validation
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        log_message('debug', 'Project_validation Class Initialized');
    }

    /**
     * Root-level metadata keys managed by the application (not part of type JSON Schema).
     *
     * @return array
     */
    public static function application_managed_metadata_keys()
    {
        return array(
            'schema',
            'schema_version',
            'type',
            'changed',
            'changed_utc',
            'created',
            'created_utc',
            'created_by',
            'changed_by',
            'user_id',
        );
    }

    /**
     * Copy of root metadata with application-managed keys removed (for JSON Schema validation).
     *
     * @param array|object|null $metadata
     * @return array|object|null
     */
    public static function strip_application_managed_metadata_for_schema($metadata)
    {
        if ($metadata === null) {
            return null;
        }
        if (is_object($metadata)) {
            $metadata = json_decode(json_encode($metadata), true);
        }
        if (!is_array($metadata)) {
            return $metadata;
        }
        $out = $metadata;
        foreach (self::application_managed_metadata_keys() as $key) {
            unset($out[$key]);
        }
        return $out;
    }

    /**
     * Validate project metadata against schema
     * 
     * @param array $metadata Project metadata
     * @param string $type Project type
     * @param string $schema_file Path to schema file
     * @param array|null $compiled_schema Compiled schema (optional, for PHP-specific checks)
     * @return array Validation result with 'valid' boolean and 'issues' array
     */
    public function validate_schema($metadata, $type, $schema_file, $compiled_schema = null)
    {
        $metadata = self::strip_application_managed_metadata_for_schema($metadata);

        $canonical_type = $type;
        
        // Get canonical type from schema registry
        try {
            $this->ci->load->model('Metadata_schemas_model');
            $schema_row = $this->ci->Metadata_schemas_model->get_by_uid($type);
            if ($schema_row && isset($schema_row['uid']) && $schema_row['uid']) {
                $canonical_type = $schema_row['uid'];
            }
        } catch (Exception $e) {
            // Use provided type if lookup fails
        }

        $validation_result = array(
            'valid' => false,
            'type' => $canonical_type,
            'issues' => array()
        );

        if (!$schema_file || !file_exists($schema_file)) {
            $validation_result['issues'][] = array(
                'type' => 'schema_not_found',
                'message' => "Schema file not found for type: $type",
                'path' => null
            );
            return $validation_result;
        }

        // Initialize PHP-specific issues array
        $php_specific_issues = array();
        
        // Check for PHP-specific issues if compiled schema is provided
        if ($compiled_schema !== null) {
            $this->check_php_specific_issues($metadata, $compiled_schema, '', $php_specific_issues);
        }
        
        // Validate using JSON Schema validator (handles union types, constraints, etc.)
        $validator = new Validator;
        $validator->validate(
            $metadata,
            (object)['$ref' => 'file://' . unix_path(realpath($schema_file))],
            Constraint::CHECK_MODE_TYPE_CAST
            + Constraint::CHECK_MODE_APPLY_DEFAULTS
        );

        // Combine JsonSchema validator results with PHP-specific issues
        $json_schema_valid = $validator->isValid();
        $has_php_issues = !empty($php_specific_issues);
        
        if ($json_schema_valid && !$has_php_issues) {
            $validation_result['valid'] = true;
        } else {
            $validation_result['valid'] = false;
            
            // Add PHP-specific issues first (they're more specific)
            foreach ($php_specific_issues as $issue) {
                $validation_result['issues'][] = $issue;
            }
            
            // Convert JsonSchema validator errors to structured issues
            foreach ($validator->getErrors() as $error) {
                $validation_result['issues'][] = array(
                    'type' => 'validation_error',
                    'property' => $error['property'],
                    'message' => $error['message'],
                    'constraint' => isset($error['constraint']) ? $error['constraint'] : null,
                    'path' => $error['property']
                );
            }
        }

        return $validation_result;
    }

    /**
     * Find extra fields in metadata that are not in schema
     * 
     * @param array $metadata Project metadata
     * @param array $compiled_schema Compiled schema
     * @return array Array of extra fields with paths
     */
    public function find_extra_fields($metadata, $compiled_schema)
    {
        $extra_fields = array();
        $this->find_extra_fields_recursive($metadata, $compiled_schema, '', $extra_fields);
        return $extra_fields;
    }

    /**
     * Find extra fields in metadata that are not in template
     * 
     * @param array $metadata Project metadata
     * @param array $template_data Template data
     * @return array Array of extra fields with paths
     */
    public function find_template_extra_fields($metadata, $template_data)
    {
        $extra_fields = array();
        $template_keys = array();
        
        if (isset($template_data['items']) && is_array($template_data['items'])) {
            $this->collect_template_keys($template_data['items'], '', $template_keys);
            $this->find_template_extra_fields_recursive($metadata, $template_keys, '', $extra_fields);
        }
        
        return $extra_fields;
    }

    /**
     * Validate template rules
     * 
     * @param array $metadata Project metadata
     * @param array $template_data Template data
     * @return array Validation result with 'valid' boolean and 'issues' array
     */
    public function validate_template($metadata, $template_data)
    {
        $validation_result = array(
            'valid' => true,
            'issues' => array(),
            'validation_report' => array()
        );

        if (!isset($template_data['items']) || !is_array($template_data['items'])) {
            return $validation_result;
        }

        $this->validate_template_items($template_data['items'], $metadata, '', $validation_result['issues'], $validation_result['validation_report']);

        if (!empty($validation_result['issues'])) {
            $validation_result['valid'] = false;
        }

        return $validation_result;
    }

    // ============================================================================
    // Private helper methods
    // ============================================================================

    /**
     * Check for PHP-specific issues that JsonSchema validator cannot detect
     */
    private function check_php_specific_issues($data, $schema, $base_path = '', &$issues = array())
    {
        if ($data === null) {
            return;
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        $schema_properties = array();
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema_properties = $schema['properties'];
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $sub_schema) {
                if (isset($sub_schema['properties']) && is_array($sub_schema['properties'])) {
                    $schema_properties = array_merge($schema_properties, $sub_schema['properties']);
                }
            }
        }

        foreach ($data as $key => $value) {
            $field_path = $base_path ? $base_path . '/' . $key : '/' . $key;

            if (in_array($key, array('$schema', '$id', '$ref'))) {
                continue;
            }

            if (isset($schema_properties[$key])) {
                $field_schema = $schema_properties[$key];
                
                if (isset($field_schema['$ref'])) {
                    $field_schema = $this->resolve_schema_ref($field_schema['$ref'], $schema);
                }
                
                $expected_type = $this->get_schema_type($field_schema);
                $actual_type = $this->get_php_type($value);
                $actual_schema_type = $this->map_php_to_schema_type($actual_type, $value);
                
                // Check if schema allows array type (handles union types)
                $allows_array = false;
                if (isset($field_schema['type'])) {
                    if (is_array($field_schema['type'])) {
                        $allows_array = in_array('array', $field_schema['type']);
                    } else {
                        $allows_array = ($field_schema['type'] === 'array');
                    }
                } elseif (isset($field_schema['items'])) {
                    $allows_array = true;
                }
                
                // PHP-specific check: array stored as object with numeric keys
                if ($allows_array && $actual_schema_type === 'object' && $this->is_object_with_numeric_keys($value)) {
                    $issues[] = array(
                        'type' => 'array_as_object',
                        'property' => $key,
                        'path' => $field_path,
                        'message' => "Array is incorrectly stored as object with numeric keys. Should be an array.",
                        'expected_type' => $expected_type,
                        'actual_type' => $actual_schema_type,
                        'value_preview' => $this->get_value_preview($value),
                        'fixable' => true
                    );
                }
                
                // Recurse into nested structures
                if (is_array($value) || is_object($value)) {
                    if (isset($field_schema['items'])) {
                        $items_schema = $field_schema['items'];
                        if (isset($items_schema['$ref'])) {
                            $items_schema = $this->resolve_schema_ref($items_schema['$ref'], $schema);
                        }
                        
                        if (is_array($value)) {
                            foreach ($value as $index => $item) {
                                $item_path = $field_path . '/' . $index;
                                if (is_array($item) || is_object($item)) {
                                    $this->check_php_specific_issues($item, $items_schema, $item_path, $issues);
                                }
                            }
                        }
                    } elseif (isset($field_schema['type']) && $field_schema['type'] === 'object') {
                        $this->check_php_specific_issues($value, $field_schema, $field_path, $issues);
                    }
                }
            }
        }
    }

    /**
     * Find extra fields recursively
     */
    private function find_extra_fields_recursive($data, $schema, $base_path = '', &$extra_fields = array())
    {
        if ($data === null || (!is_array($data) && !is_object($data))) {
            return;
        }

        if ($base_path === '/additional' || strpos($base_path, '/additional/') === 0) {
            return;
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        $schema_properties = array();
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema_properties = $schema['properties'];
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $sub_schema) {
                if (isset($sub_schema['properties']) && is_array($sub_schema['properties'])) {
                    $schema_properties = array_merge($schema_properties, $sub_schema['properties']);
                }
            }
        }

        foreach ($data as $key => $value) {
            $field_path = $base_path ? $base_path . '/' . $key : '/' . $key;

            if (in_array($key, array('$schema', '$id', '$ref'))) {
                continue;
            }

            // Extension bucket: do not list or traverse (user-defined / overflow metadata)
            if ($key === 'additional') {
                continue;
            }

            // Application-managed root keys (not schema content)
            if ($base_path === '' && in_array($key, self::application_managed_metadata_keys(), true)) {
                continue;
            }

            if (!isset($schema_properties[$key])) {
                $extra_fields[] = array(
                    'field' => $key,
                    'path' => $field_path,
                    'type' => $this->get_php_type($value),
                    'value_preview' => $this->get_value_preview($value)
                );
            } elseif (is_array($value) || is_object($value)) {
                if (isset($schema_properties[$key]['items'])) {
                    $items_schema = $schema_properties[$key]['items'];
                    if (isset($items_schema['$ref'])) {
                        $items_schema = $this->resolve_schema_ref($items_schema['$ref'], $schema);
                    }
                    
                    if (is_array($value)) {
                        foreach ($value as $index => $item) {
                            $item_path = $field_path . '/' . $index;
                            if (is_array($item) || is_object($item)) {
                                $this->find_extra_fields_recursive($item, $items_schema, $item_path, $extra_fields);
                            }
                        }
                    }
                } elseif (isset($schema_properties[$key]['type']) && $schema_properties[$key]['type'] === 'object') {
                    $field_schema = $schema_properties[$key];
                    if (isset($field_schema['$ref'])) {
                        $field_schema = $this->resolve_schema_ref($field_schema['$ref'], $schema);
                    }
                    $this->find_extra_fields_recursive($value, $field_schema, $field_path, $extra_fields);
                }
            }
        }
    }

    /**
     * Collect template keys recursively
     */
    private function collect_template_keys($items, $base_path = '', &$template_keys = array())
    {
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (!isset($item['key'])) {
                continue;
            }

            $field_key = $item['key'];
            $field_path = $base_path ? $base_path . '/' . $field_key : '/' . $field_key;
            $template_keys[] = $field_path;

            if (isset($item['items']) && is_array($item['items'])) {
                $this->collect_template_keys($item['items'], $field_path, $template_keys);
            }
        }
    }

    /**
     * Find template extra fields recursively
     */
    private function find_template_extra_fields_recursive($data, $template_keys, $base_path = '', &$extra_fields = array())
    {
        if ($data === null || (!is_array($data) && !is_object($data))) {
            return;
        }

        if ($base_path === '/additional' || strpos($base_path, '/additional/') === 0) {
            return;
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        foreach ($data as $key => $value) {
            $field_path = $base_path ? $base_path . '/' . $key : '/' . $key;

            if (in_array($key, array('$schema', '$id', '$ref'))) {
                continue;
            }

            if ($base_path === '' && in_array($key, self::application_managed_metadata_keys(), true)) {
                continue;
            }

            if (!in_array($field_path, $template_keys) && !$this->has_template_children($field_path, $template_keys)) {
                $extra_fields[] = array(
                    'field' => $key,
                    'path' => $field_path,
                    'value_preview' => $this->get_value_preview($value)
                );
            } elseif (is_array($value) || is_object($value)) {
                if (is_array($value)) {
                    foreach ($value as $index => $item) {
                        $item_path = $field_path . '/' . $index;
                        if (is_array($item) || is_object($item)) {
                            $this->find_template_extra_fields_recursive($item, $template_keys, $item_path, $extra_fields);
                        }
                    }
                } else {
                    $this->find_template_extra_fields_recursive($value, $template_keys, $field_path, $extra_fields);
                }
            }
        }
    }

    /**
     * Check if a field path has template children
     */
    private function has_template_children($field_path, $template_keys)
    {
        $prefix = $field_path . '/';
        foreach ($template_keys as $key) {
            if (strpos($key, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate template items recursively
     */
    private function validate_template_items($items, $metadata, $base_path = '', &$issues = array(), &$validation_report = array())
    {
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (!isset($item['key'])) {
                continue;
            }

            $field_key = $item['key'];
            $field_path = $base_path ? $base_path . '/' . $field_key : '/' . $field_key;

            // Get value from metadata
            $value = $this->get_value_by_path($metadata, $field_path);

            // Map frontend rules to backend rules
            $rules = isset($item['rules']) ? $this->map_frontend_to_backend_rules($item['rules']) : '';
            
            // Parse rules into array for frontend
            $rules_applied = array();
            if (!empty($rules)) {
                $rules_array = explode('|', $rules);
                foreach ($rules_array as $rule) {
                    $rule = trim($rule);
                    if (!empty($rule)) {
                        $rules_applied[] = $rule;
                    }
                }
            }

            $errors = array();
            $is_valid = true;
            
            if (!empty($rules)) {
                $this->ci->load->library('form_validation');
                $this->ci->form_validation->set_data(array($field_key => $value));
                $this->ci->form_validation->set_rules($field_key, $item['label'] ?? $field_key, $rules);

                $validation_passed = $this->ci->form_validation->run();
                if (!$validation_passed) {
                    $error_message = $this->ci->form_validation->error($field_key);
                    $errors[] = $error_message;
                    $is_valid = false;
                    $issues[] = array(
                        'type' => 'template_validation_error',
                        'property' => $field_key,
                        'path' => $field_path,
                        'message' => $error_message,
                        'label' => $item['label'] ?? $field_key
                    );
                } else {
                    $is_valid = true;
                }
            } else {
                // If no rules, skip this item (don't add to validation_report)
                // Frontend expects only items that were actually validated
                // Recurse into nested items even if this item has no rules
                if (isset($item['items']) && is_array($item['items'])) {
                    $this->validate_template_items($item['items'], $metadata, $field_path, $issues, $validation_report);
                }
                continue;
            }

            // Build validation report item matching frontend expectations
            // Only include items that have validation rules
            $validation_report[] = array(
                'field' => $field_key,
                'path' => $field_path,
                'title' => $item['label'] ?? $item['title'] ?? $field_key,
                'label' => $item['label'] ?? $field_key,
                'value' => $value,
                'rules' => $rules,
                'rules_applied' => $rules_applied,
                'valid' => $is_valid,
                'status' => $is_valid ? 'valid' : 'invalid',
                'errors' => $errors,
                'error_count' => count($errors)
            );

            // Recurse into nested items
            if (isset($item['items']) && is_array($item['items'])) {
                $this->validate_template_items($item['items'], $metadata, $field_path, $issues, $validation_report);
            }
        }
    }

    /**
     * Map frontend validation rules to backend rules
     */
    private function map_frontend_to_backend_rules($rules)
    {
        $rule_mapping = array(
            'required' => 'required',
            'email' => 'valid_email',
            'url' => 'valid_url',
            'numeric' => 'numeric',
            'integer' => 'integer',
            'alpha' => 'alpha',
            'alpha_numeric' => 'alpha_numeric',
            'min' => 'min_length',
            'max' => 'max_length'
        );

        $normalize_and_map_rule = function($rule) use ($rule_mapping) {
            $rule = trim($rule);
            if (empty($rule)) {
                return '';
            }

            // Handle rules with parameters (e.g., "min:5" -> "min_length[5]")
            if (preg_match('/^(\w+):(.+)$/', $rule, $matches)) {
                $rule_name = $matches[1];
                $rule_value = $matches[2];
                
                if (isset($rule_mapping[$rule_name])) {
                    $mapped_rule = $rule_mapping[$rule_name];
                    // For min/max, convert to CodeIgniter format
                    if ($rule_name === 'min' || $rule_name === 'max') {
                        return $mapped_rule . '[' . $rule_value . ']';
                    }
                    return $mapped_rule;
                }
            }

            // Handle simple rules without parameters
            if (isset($rule_mapping[$rule])) {
                return $rule_mapping[$rule];
            }

            return $rule;
        };

        if (is_string($rules)) {
            $rules_array = array_map('trim', explode('|', $rules));
        } elseif (is_array($rules)) {
            $rules_array = $rules;
        } else {
            return '';
        }

        $mapped_rules = array_map($normalize_and_map_rule, $rules_array);
        $mapped_rules = array_filter($mapped_rules, function($rule) {
            return !empty($rule);
        });

        return implode('|', $mapped_rules);
    }

    /**
     * Get value by path from nested array
     * Public utility method for accessing nested data structures
     */
    public function get_value_by_path($data, $path)
    {
        $path = ltrim($path, '/');
        if (empty($path)) {
            return $data;
        }

        $parts = explode('/', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (is_null($current)) {
                return null;
            }

            // Handle array indices
            if (is_numeric($part)) {
                $part = (int) $part;
            }

            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } elseif (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Convert object with numeric keys to array
     * Public utility method for fixing array-as-object issues
     */
    public function convert_object_to_array($value)
    {
        if (!is_array($value) && !is_object($value)) {
            return $value;
        }
        
        if (is_object($value)) {
            $value = (array) $value;
        }
        
        $keys = array_keys($value);
        $numeric_keys = array();
        foreach ($keys as $key) {
            if (is_numeric($key)) {
                $numeric_keys[] = (int) $key;
            }
        }
        
        sort($numeric_keys);
        
        $result = array();
        foreach ($numeric_keys as $num_key) {
            $result[] = $value[(string) $num_key];
        }
        
        return $result;
    }

    /**
     * Create a key for additional section from JSON Pointer path
     */
    public function create_additional_key($path)
    {
        $path = ltrim($path, '/');
        $key = str_replace('/', '.', $path);
        return $key;
    }

    /**
     * Get field definition for display in non-fixable issues
     */
    public function get_field_definition_for_issue($field_schema, $field_path)
    {
        $definition = array();
        $definition['json_schema'] = $field_schema;
        
        if (isset($field_schema['title'])) {
            $definition['title'] = $field_schema['title'];
        }
        if (isset($field_schema['description'])) {
            $definition['description'] = $field_schema['description'];
        }
        if (isset($field_schema['type'])) {
            $definition['type'] = $field_schema['type'];
        }
        
        if (isset($field_schema['items'])) {
            $definition['items'] = $field_schema['items'];
        }
        
        return $definition;
    }

    /**
     * Get schema type from schema definition
     */
    private function get_schema_type($schema)
    {
        if (!is_array($schema)) {
            return null;
        }
        
        if (isset($schema['type'])) {
            if (is_array($schema['type'])) {
                return $schema['type'][0];
            }
            return $schema['type'];
        }
        
        if (isset($schema['items'])) {
            return 'array';
        }
        
        if (isset($schema['properties'])) {
            return 'object';
        }
        
        return null;
    }

    /**
     * Check if type is allowed by schema (handles union types)
     */
    private function is_type_allowed($schema, $actual_type)
    {
        if (!is_array($schema)) {
            return false;
        }
        
        if (isset($schema['type'])) {
            if (is_array($schema['type'])) {
                return in_array($actual_type, $schema['type']);
            }
            return $schema['type'] === $actual_type;
        }
        
        if (isset($schema['items'])) {
            return $actual_type === 'array';
        }
        
        if (isset($schema['properties'])) {
            return $actual_type === 'object';
        }
        
        return false;
    }

    /**
     * Get allowed types string from schema
     */
    private function get_allowed_types_string($schema)
    {
        if (!is_array($schema)) {
            return 'unknown';
        }
        
        if (isset($schema['type'])) {
            if (is_array($schema['type'])) {
                return implode(' | ', $schema['type']);
            }
            return $schema['type'];
        }
        
        if (isset($schema['items'])) {
            return 'array';
        }
        
        if (isset($schema['properties'])) {
            return 'object';
        }
        
        return 'unknown';
    }

    /**
     * Check if object has numeric keys (indicating it should be an array)
     * Public utility method
     */
    public function is_object_with_numeric_keys($value)
    {
        if (!is_array($value) && !is_object($value)) {
            return false;
        }
        
        if (empty($value)) {
            return false;
        }
        
        if (is_object($value)) {
            $value = (array) $value;
        }
        
        $keys = array_keys($value);
        $all_numeric = true;
        $numeric_keys = array();
        
        foreach ($keys as $key) {
            if (is_numeric($key)) {
                $numeric_keys[] = (int) $key;
            } else {
                $all_numeric = false;
                break;
            }
        }
        
        if (!$all_numeric || empty($numeric_keys)) {
            return false;
        }
        
        sort($numeric_keys);
        $is_sequential = true;
        $start = $numeric_keys[0];
        for ($i = 0; $i < count($numeric_keys); $i++) {
            if ($numeric_keys[$i] !== $start + $i) {
                $is_sequential = false;
                break;
            }
        }
        
        return $is_sequential;
    }

    /**
     * Map PHP type to JSON Schema type
     */
    private function map_php_to_schema_type($php_type, $value)
    {
        switch ($php_type) {
            case 'string':
                return 'string';
            case 'integer':
                return 'integer';
            case 'double':
            case 'float':
                return 'number';
            case 'boolean':
                return 'boolean';
            case 'array':
                if (empty($value)) {
                    return 'array';
                }
                $keys = array_keys($value);
                $is_indexed = array_keys($keys) === $keys && (!empty($keys) ? $keys[0] === 0 : true);
                return $is_indexed ? 'array' : 'object';
            case 'object':
                return 'object';
            case 'NULL':
            case 'null':
                return 'null';
            default:
                return null;
        }
    }

    /**
     * Get PHP type of value
     */
    private function get_php_type($value)
    {
        if (is_array($value)) {
            return array_keys($value) === range(0, count($value) - 1) ? 'array' : 'object';
        }
        return gettype($value);
    }

    /**
     * Resolve schema $ref reference
     */
    private function resolve_schema_ref($ref, $schema)
    {
        if (strpos($ref, '#/') === 0) {
            $path = substr($ref, 2);
            $parts = explode('/', $path);
            
            $current = $schema;
            foreach ($parts as $part) {
                if (isset($current[$part])) {
                    $current = $current[$part];
                } else {
                    return array();
                }
            }
            return is_array($current) ? $current : array();
        }
        
        return array();
    }

    /**
     * Get value preview for display
     * Public utility method
     */
    public function get_value_preview($value)
    {
        if (is_null($value)) {
            return '(null)';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            $count = is_array($value) ? count($value) : count((array)$value);
            return is_array($value) ? "[Array: $count items]" : "[Object: $count properties]";
        }
        if (is_string($value)) {
            $preview = substr($value, 0, 100);
            if (strlen($value) > 100) {
                $preview .= '...';
            }
            return $preview;
        }
        return (string) $value;
    }
}

