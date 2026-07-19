<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;

/**
 * Schema registry service.
 *
 * Provides access to metadata schemas stored in the metadata_schemas table
 * and on disk. 
 */
class Schema_registry
{
    protected $ci;

    protected $core_schema_initialized = false;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->helper('url');
    }

    /**
     * 
     * Return all schemas with optional filters.
     *
     * 
     * params:
     * - options: array of options
     *
     * return:
     * - array of schemas
     */
    public function list_schemas($options = array())
    {
        $this->ensure_core_schema_records();
        $schemas = $this->get_model()->get_all($options);

        foreach ($schemas as &$schema) {
            if (isset($schema['uid'])) {
                $schema['icon_url'] = $this->get_schema_icon_url($schema['uid']);
                $schema['icon_full_url'] = $this->get_schema_icon_full_url($schema['uid']);
                $schema['display_name'] = $this->get_schema_display_name($schema['uid']);
            }
        }

        return $schemas;
    }

    /**
     * Ensure any bundled core schemas exist in metadata_schemas table
     */
    protected function ensure_core_schema_records()
    {
        if ($this->core_schema_initialized) {
            return;
        }

        $this->ensure_custom_core_schema();

        $this->core_schema_initialized = true;
    }

    /**
     * Seed the catch-all "custom" schema if it is missing from the registry.
     */
    protected function ensure_custom_core_schema()
    {
        $model = $this->get_model();
        $existing = $model->get_by_uid('custom');
        if ($existing) {
            return;
        }

        $model->insert(array(
            'uid' => 'custom',
            'title' => 'Custom',
            'agency' => 'IHSN',
            'description' => 'Catch-all schema for content that does not match other core types.',
            'is_core' => 1,
            'status' => 'active',
            'storage_path' => '',
            'filename' => 'custom-schema.json',
            'schema_files' => array(),
            'metadata_options' => array(
                'core_fields' => array(
                    'idno' => '/identification/idno',
                    'title' => '/identification/title'
                )
            ),
            'alias' => ''
        ));
    }

    /**
     * Build schema file manifest and (optionally) path lookup.
     */
    public function build_schema_file_manifest($schema, $schema_dir, $with_lookup = false)
    {
        $files = array();
        $lookup = array();

        $entries = array();
        if (!empty($schema['filename'])) {
            $entries[] = array(
                'filename' => $schema['filename'],
                'is_main' => true
            );
        }

        if (!empty($schema['schema_files']) && is_array($schema['schema_files'])) {
            foreach ($schema['schema_files'] as $file) {
                $entries[] = array(
                    'filename' => $file,
                    'is_main' => false
                );
            }
        }

        foreach ($entries as $entry) {
            $filename = $entry['filename'];
            if ($filename === '') {
                continue;
            }
            $path = unix_path($schema_dir . '/' . $filename);

            if (!is_file($path)) {
                continue;
            }

            $size = @filesize($path);
            $modified = @filemtime($path);

            $info = array(
                'filename' => $filename,
                'is_main' => (bool)$entry['is_main'],
                'size' => $size !== false ? (int)$size : null,
                'modified' => $modified !== false ? (int)$modified : null,
                'download_url' => site_url('api/schemas/file/' . rawurlencode($schema['uid']) . '/' . rawurlencode($filename))
            );

            $files[] = $info;
            if ($with_lookup) {
                $lookup[$filename] = $path;
            }
        }

        if ($with_lookup) {
            return array(
                'files' => $files,
                'lookup' => $lookup
            );
        }

        return $files;
    }

    /**
     * Load all schema documents (main + related) from disk.
     */
    public function load_schema_documents($schema, $schema_dir)
    {
        $documents = array();

        $main_path = unix_path($schema_dir . '/' . $schema['filename']);
        if (!is_file($main_path)) {
            throw new Exception("Main schema file not found: " . $schema['filename']);
        }

        $main_content = file_get_contents($main_path);
        if ($main_content === false) {
            throw new Exception("Failed to read main schema file.");
        }

        $main_decoded = json_decode($main_content, true);
        if ($main_decoded === null) {
            throw new Exception("Main schema file contains invalid JSON.");
        }

        $documents[$schema['filename']] = $main_decoded;

        if (!empty($schema['schema_files']) && is_array($schema['schema_files'])) {
            foreach ($schema['schema_files'] as $file) {
                $path = unix_path($schema_dir . '/' . $file);
                if (!is_file($path)) {
                    throw new Exception("Schema reference file not found: " . $file);
                }

                $content = file_get_contents($path);
                if ($content === false) {
                    throw new Exception("Failed to read schema reference file: " . $file);
                }

                $decoded = json_decode($content, true);
                if ($decoded === null) {
                    throw new Exception("Schema reference file contains invalid JSON: " . $file);
                }

                $documents[$file] = $decoded;
            }
        }

        return $documents;
    }

    /**
     * Validate a set of schema documents collectively.
     */
    public function validate_schema_documents($documents, $main_filename, $schema_dir)
    {
        $schema_storage = new SchemaStorage();

        foreach ($documents as $filename => $document) {
            $uri = 'file://' . unix_path($schema_dir . '/' . $filename);
            $as_object = json_decode(json_encode($document));
            if ($as_object === null) {
                throw new Exception("Failed to normalize schema document: " . $filename);
            }
            $schema_storage->addSchema($uri, $as_object);

            $basename = pathinfo($filename, PATHINFO_FILENAME);
            if ($basename && $basename !== $filename) {
                $alias_uri = 'file://' . unix_path($schema_dir . '/' . $basename);
                $schema_storage->addSchema($alias_uri, $as_object);
            }
        }

        $root_uri = 'file://' . unix_path($schema_dir . '/' . $main_filename);

        $resolved = $schema_storage->resolveRef($root_uri);
        $validator = new Validator(new Factory($schema_storage));

        $validator->validate(
            $resolved,
            (object)['$ref' => 'https://json-schema.org/draft-07/schema']
        );

        if (!$validator->isValid()) {
            $messages = array();
            foreach ($validator->getErrors() as $error) {
                $messages[] = (isset($error['property']) ? $error['property'] : '') . ': ' . $error['message'];
            }
            throw new Exception("Schema validation failed: " . implode("; ", $messages));
        }
    }

    /**
     * Validate a single JSON schema by itself.
     */
    public function assert_valid_json_schema($schema_data, $filename = null)
    {
        $schema_object = json_decode(json_encode($schema_data));

        if ($schema_object === null) {
            $label = $filename ? " ({$filename})" : '';
            throw new Exception("Schema file{$label} could not be parsed as JSON.");
        }

        $schema_array = json_decode(json_encode($schema_data), true);
        if (!$this->looks_like_json_schema($schema_array)) {
            $label = $filename ? " ({$filename})" : '';
            throw new Exception("Schema file{$label} does not appear to be a JSON Schema document.");
        }

        $this->assert_supported_json_schema_version($schema_array, $filename);

        $schema_storage = new SchemaStorage();
        $factory = new Factory($schema_storage);
        $validator = new Validator($factory);

        try {
            $validator->validate(
                $schema_object,
                (object)['$ref' => 'https://json-schema.org/draft-07/schema']
            );
        } catch (\TypeError $error) {
            $paths = $this->find_boolean_items_paths($schema_object);
            if (!empty($paths)) {
                $label = $filename ? " ({$filename})" : '';
                throw new Exception("Schema file{$label} uses boolean 'items' at: " . implode(', ', $paths) . ". Draft-07 requires 'items' to be an object schema or an array of schemas.");
            }

            throw $error;
        }

        if (!$validator->isValid()) {
            $messages = array();
            foreach ($validator->getErrors() as $error) {
                $messages[] = (isset($error['property']) ? $error['property'] : '') . ': ' . $error['message'];
            }
            $label = $filename ? " ({$filename})" : '';
            throw new Exception("Invalid JSON Schema{$label}: " . implode("; ", $messages));
        }
    }

    /**
     * Validate combined schema documents using SchemaStorage.
     */
    public function validate_json_schema($schema_dir, $root_filename, $root_schema, $related_schemas = array())
    {
        $schema_storage = new SchemaStorage();

        $this->assert_supported_json_schema_version($root_schema, $root_filename);

        $schema_dir = unix_path($schema_dir);
        $root_uri = 'file://' . unix_path($schema_dir . '/' . $root_filename);
        $schema_storage->addSchema($root_uri, $root_schema);

        foreach ($related_schemas as $filename => $schema_obj) {
            $this->assert_supported_json_schema_version($schema_obj, $filename);
            $full_uri = 'file://' . unix_path($schema_dir . '/' . $filename);
            $schema_storage->addSchema($full_uri, $schema_obj);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            if ($basename && $basename !== $filename) {
                $alias_uri = 'file://' . unix_path($schema_dir . '/' . $basename);
                $schema_storage->addSchema($alias_uri, $schema_obj);
            }
        }

        $resolved = $schema_storage->resolveRef($root_uri);

        $this->assert_no_boolean_items($resolved, $root_filename);

        $factory = new Factory($schema_storage);
        $validator = new Validator($factory);

        try {
            $validator->validate(
                $resolved,
                (object)['$ref' => 'https://json-schema.org/draft-07/schema']
            );
        } catch (\TypeError $error) {
            $paths = $this->find_boolean_items_paths($resolved);
            if (!empty($paths)) {
                $label = $root_filename ? " ({$root_filename})" : '';
                throw new Exception("Schema file{$label} uses boolean 'items' at: " . implode(', ', $paths) . ". Convert these to Draft-07 compatible schemas before upload.");
            }

            throw $error;
        }

        if (!$validator->isValid()) {
            $messages = array();
            foreach ($validator->getErrors() as $error) {
                $messages[] = (isset($error['property']) ? $error['property'] : '') . ': ' . $error['message'];
            }
            throw new Exception("Schema validation failed: " . implode("; ", $messages));
        }
    }

    public function build_schema_alias_map($schema, $documents)
    {
        $aliases_by_filename = array();
        $lookup = array();
        $used = array();

        foreach ($documents as $filename => $doc) {
            $is_main = ($filename === $schema['filename']);
            $candidate = $is_main ? $this->sanitize_schema_alias($schema['uid']) : $this->sanitize_schema_alias(pathinfo($filename, PATHINFO_FILENAME));

            if ($candidate === '') {
                $candidate = 'Schema_' . count($used);
            }

            $alias = $candidate;
            $suffix = 1;
            while (in_array($alias, $used, true)) {
                $alias = $candidate . '_' . $suffix;
                $suffix++;
            }

            $used[] = $alias;
            $aliases_by_filename[$filename] = $alias;

            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $keys = array($filename);
            if ($basename) {
                $keys[] = $basename;
            }

            foreach ($keys as $key) {
                $normalized = $this->normalize_ref_target($key);
                if ($normalized === '') {
                    continue;
                }
                $lookup[$normalized] = $alias;
            }
        }

        return array(
            'by_filename' => $aliases_by_filename,
            'lookup' => $lookup
        );
    }

    public function generate_openapi_spec($schema, $documents, $alias_map)
    {
        $components = array();
        $lookup = $alias_map['lookup'];

        foreach ($documents as $filename => $doc) {
            $alias = $alias_map['by_filename'][$filename];
            $rewritten = $doc;
            $this->rewrite_schema_refs($rewritten, $lookup, $alias);
            $components[$alias] = $rewritten;
        }

        $main_alias = $alias_map['by_filename'][$schema['filename']];

        $title = $schema['title'] ? $schema['title'] : $schema['uid'];
        $version = $this->detect_schema_version($documents[$schema['filename']]);
        $description = $schema['description'] ? $schema['description'] : '';

        return array(
            'openapi' => '3.0.3',
            'info' => array(
                'title' => $title,
                'version' => $version,
                'description' => $description
            ),
            'paths' => array(
                '/schemas/' . $schema['uid'] => array(
                    'post' => array(
                        'summary' => strtoupper($schema['uid']),
                        'requestBody' => array(
                            'required' => true,
                            'content' => array(
                                'application/json' => array(
                                    'schema' => array(
                                        '$ref' => '#/components/schemas/' . $main_alias
                                    )
                                )
                            )
                        ),
                        'responses' => array(
                            '201' => array(
                                'description' => 'Schema payload accepted'
                            )
                        )
                    )
                )
            ),
            'components' => array(
                'schemas' => $components
            )
        );
    }

    public function convert_to_yaml($data)
    {
        if (function_exists('yaml_emit')) {
            $encoding = defined('YAML_UTF8_ENCODING') ? YAML_UTF8_ENCODING : 0;
            $linebreak = defined('YAML_LN_BREAK') ? YAML_LN_BREAK : "\n";
            return yaml_emit($data, $encoding, $linebreak);
        }

        return $this->array_to_yaml($data);
    }

    public function inline_schema($filename, $documents, $schema_dir, &$visited = array())
    {
        if (!isset($documents[$filename])) {
            throw new Exception("Schema document not found: " . $filename);
        }

        // Initialize resolution_stack for circular reference detection
        $resolution_stack = array();

        $document = $documents[$filename];

        if (isset($document['allOf']) && is_array($document['allOf'])) {
            $merged = $this->merge_allOf($document['allOf'], $documents, $schema_dir, $visited, $filename, $resolution_stack);
            // Use deep_merge to properly merge nested properties arrays
            $document = $this->deep_merge($document, $merged);
            unset($document['allOf']);
        }

        $document = $this->resolve_refs_recursive($document, $documents, $schema_dir, $filename, $visited, $resolution_stack);

        if (isset($document['components'])) {
            unset($document['components']);
        }
        
        // After resolving all refs, definitions are no longer needed
        if (isset($document['definitions'])) {
            unset($document['definitions']);
        }

        return $document;
    }


    /**
     * 
     * Collect schema fields
     * 
     * params:
     * - schema: schema array
     * - base_path: base path
     * - fields: array of fields
     * - is_required: boolean
     * - format: format
     * - seen_paths: array of seen paths
     * 
     * return:
     * - array of fields
     */
    public function collect_schema_fields($schema, $base_path = '', &$fields = array(), $is_required = false, $format = 'json_patch', &$seen_paths = array())
    {
        if (!is_array($schema)) {
            return $fields;
        }

        // Initialize seen_paths on first call
        if (empty($seen_paths)) {
            $seen_paths = array();
        }

        // Skip allOf - inline_schema should have already merged allOf into properties
        // If allOf still exists, it means the schema wasn't properly inlined
        // Processing it here would create duplicates
        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            // Log a warning or just skip it
            // allOf should have been processed by inline_schema
            return $fields;
        }

        // Determine if this is a meaningful field (not just an intermediate container)
        $has_type = isset($schema['type']) && $schema['type'] !== '' && $schema['type'] !== null;
        $has_enum = isset($schema['enum']) && is_array($schema['enum']) && !empty($schema['enum']);
        $has_const = isset($schema['const']);
        $has_properties = isset($schema['properties']) && is_array($schema['properties']) && !empty($schema['properties']);
        $has_items = isset($schema['items']);
        $has_pattern_props = isset($schema['patternProperties']) && is_array($schema['patternProperties']) && !empty($schema['patternProperties']);
        
        // Determine the actual type (handle both single type and array of types)
        $type_value = '';
        $is_array_type = false;
        $is_object_type = false;
        if ($has_type) {
            if (is_array($schema['type'])) {
                $type_value = implode('|', $schema['type']);
                $is_array_type = in_array('array', $schema['type']);
                $is_object_type = in_array('object', $schema['type']);
            } else {
                $type_value = $schema['type'];
                $is_array_type = ($type_value === 'array');
                $is_object_type = ($type_value === 'object');
            }
        }
        
        // Arrays with items should be added as fields (to show the array itself)
        // Objects with properties should be added if they have title/description (meaningful metadata)
        $is_object_with_properties = $is_object_type && $has_properties;
        $has_title_or_description = (isset($schema['title']) && $schema['title'] !== '') || 
                                    (isset($schema['description']) && $schema['description'] !== '');
        
        // Only add entry if it's a meaningful field:
        // - Has a primitive type (string, number, boolean, etc.) - not object/array
        // - Has enum values
        // - Has const
        // - OR is an array (even with items - we want to show the array itself)
        // - OR is an object with title/description (even if it has properties - we want to show the object itself)
        // - OR is a leaf container (object/array with no nested structure)
        $is_leaf_container = ($is_array_type || $is_object_type) && 
                            !$has_properties && !$has_items && !$has_pattern_props;
        $is_primitive_type = $has_type && !$is_array_type && !$is_object_type;
        // Include arrays even if they have items (we want to show the array itself)
        // Include objects with properties if they have title/description (we want to show the object itself)
        $has_meaningful_data = ($is_primitive_type || $has_enum || $has_const || $is_array_type || 
                              ($is_object_type && $has_title_or_description) || $is_leaf_container);

        // Only add entry for meaningful fields that we haven't seen
        if ($base_path !== '' && $has_meaningful_data && !isset($seen_paths[$base_path])) {
            // Don't add if type is empty string (filter out empty containers)
            if ($type_value !== '' || $has_enum || $has_const) {
                // Convert canonical_path (json_patch format) to default format for path
                // Replace /*/ with / to remove array wildcards
                $default_path = str_replace('/*/', '/', $base_path);
                // Also handle /* at the end
                $default_path = str_replace('/*', '', $default_path);
                
                $entry = array(
                    'path' => $default_path,
                    'canonical_path' => $base_path,
                    'title' => isset($schema['title']) ? (string)$schema['title'] : '',
                    'description' => isset($schema['description']) ? (string)$schema['description'] : '',
                    'type' => $type_value,
                    'enum' => $has_enum ? $schema['enum'] : null,
                    'required' => (bool)$is_required
                );
                $fields[] = $entry;
                $seen_paths[$base_path] = true;
            }
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $required = array();
            if (isset($schema['required']) && is_array($schema['required'])) {
                $required = $schema['required'];
            }
            $required_set = array_flip($required);

            foreach ($schema['properties'] as $name => $child) {
                $canonical_path = $this->join_schema_path($base_path, $name, 'json_patch');
                // Skip if we've already processed this path
                if (!isset($seen_paths[$canonical_path])) {
                    $this->collect_schema_fields($child, $canonical_path, $fields, isset($required_set[$name]), $format, $seen_paths);
                }
            }
        }

        if (isset($schema['patternProperties']) && is_array($schema['patternProperties'])) {
            foreach ($schema['patternProperties'] as $pattern => $child) {
                $canonical_path = $this->join_schema_path($base_path, '(' . $pattern . ')', 'json_patch');
                if (!isset($seen_paths[$canonical_path])) {
                    $this->collect_schema_fields($child, $canonical_path, $fields, false, $format, $seen_paths);
                }
            }
        }

        if (isset($schema['additionalProperties'])) {
            $canonical_path = $this->join_schema_path($base_path, '*', 'json_patch');
            if (is_array($schema['additionalProperties']) && !isset($seen_paths[$canonical_path])) {
                $this->collect_schema_fields($schema['additionalProperties'], $canonical_path, $fields, false, $format, $seen_paths);
            }
        }

        if (isset($schema['items'])) {
            $items = $schema['items'];
            $canonical_path = $this->join_schema_path($base_path, '*', 'json_patch');
            if (is_array($items)) {
                if ($this->is_assoc_array($items)) {
                    // Mark the array item path as seen to prevent adding the container itself
                    // We only want to process its properties
                    if (!isset($seen_paths[$canonical_path])) {
                        $seen_paths[$canonical_path] = true;
                        $this->collect_schema_fields($items, $canonical_path, $fields, false, $format, $seen_paths);
                    }
                } else {
                    foreach ($items as $index => $child) {
                        $canonical_path = $this->join_schema_path($base_path, (string)$index, 'json_patch');
                        if (!isset($seen_paths[$canonical_path])) {
                            $this->collect_schema_fields($child, $canonical_path, $fields, false, $format, $seen_paths);
                        }
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Join schema path
     * 
     * params:
     * - base: base path
     * - segment: segment
     * - format: format
     * return:
     * - string of joined path
     */
    public function join_schema_path($base, $segment, $format = 'default')
    {
        if ($base === '') {
            $base = '';
        }

        if ($segment === '' || $segment === null) {
            return $base;
        }

        if ($format === 'json_patch') {
            if ($base === '') {
                return '/' . $segment;
            }
            return rtrim($base, '/') . '/' . $segment;
        }

        if ($base === '') {
            return '/' . $segment;
        }

        return rtrim($base, '/') . '/' . $segment;
    }

    /**
     * Template key prefix aliases for item-form sections.
     *
     * Maps template prefixes to schema array property names so validation can
     * accept e.g. variable.name when the schema path is variables.name.
     *
     * @param string $schema_uid Schema UID or alias (microdata, survey, …)
     * @return array template_prefix => schema_prefix
     */
    public function get_template_key_aliases($schema_uid)
    {
        $uid = strtolower((string)$schema_uid);

        if (in_array($uid, array('microdata', 'survey'), true)) {
            return array(
                'variable' => 'variables',
                'data_file' => 'data_files'
            );
        }

        return array();
    }

    /**
     * Convert collected schema fields to dotted template-style keys.
     *
     * @param array $fields Output of collect_schema_fields()
     * @return array{keys: string[], fields: array}
     */
    public function fields_to_dotted_template_keys($fields)
    {
        $dotted_fields = array();
        $keys = array();

        if (!is_array($fields)) {
            return array('keys' => array(), 'fields' => array());
        }

        foreach ($fields as $field) {
            $slash_path = isset($field['path']) ? $field['path'] : '';
            $slash_path = ltrim((string)$slash_path, '/');
            if ($slash_path === '') {
                continue;
            }

            $slash_path = str_replace('/*/', '/', $slash_path);
            $slash_path = preg_replace('#/\*$#', '', $slash_path);
            $dotted_key = str_replace('/', '.', $slash_path);

            if ($dotted_key === '' || isset($keys[$dotted_key])) {
                continue;
            }

            $keys[$dotted_key] = true;
            $dotted_fields[] = array(
                'key' => $dotted_key,
                'path' => $slash_path,
                'title' => isset($field['title']) ? $field['title'] : '',
                'description' => isset($field['description']) ? $field['description'] : '',
                'type' => isset($field['type']) ? $field['type'] : '',
                'required' => !empty($field['required'])
            );
        }

        return array(
            'keys' => array_keys($keys),
            'fields' => $dotted_fields
        );
    }

    private function looks_like_json_schema($schema)
    {
        if (is_object($schema)) {
            $schema = (array)$schema;
        }

        if (!is_array($schema)) {
            return false;
        }

        $known_keywords = array(
            '$schema', '$id', '$ref', '$defs', 'definitions',
            'type', 'properties', 'items', 'additionalProperties',
            'required', 'enum', 'const', 'allOf', 'anyOf', 'oneOf',
            'not', 'if', 'then', 'else', 'format', 'pattern',
            'minimum', 'maximum', 'minLength', 'maxLength',
            'contains', 'minItems', 'maxItems', 'uniqueItems',
            'patternProperties', 'dependencies', 'dependentSchemas',
            'dependentRequired'
        );

        foreach ($known_keywords as $keyword) {
            if (array_key_exists($keyword, $schema)) {
                return true;
            }
        }

        return false;
    }

    private function assert_supported_json_schema_version($schema_data, $filename = null)
    {
        if (is_object($schema_data)) {
            $schema_data = json_decode(json_encode($schema_data), true);
        }

        if (!is_array($schema_data)) {
            return;
        }

        if (isset($schema_data['$schema'])) {
            $declared = trim((string)$schema_data['$schema']);

            if ($declared !== '') {
                $allowed = array(
                    'http://json-schema.org/draft-07/schema',
                    'http://json-schema.org/draft-07/schema#',
                    'https://json-schema.org/draft-07/schema',
                    'https://json-schema.org/draft-07/schema#'
                );

                $normalize = function ($value) {
                    $value = str_replace('http://', 'https://', $value);
                    return rtrim($value, '#');
                };

                $normalized_declared = $normalize($declared);

                $isAllowed = false;
                foreach ($allowed as $allowed_uri) {
                    if ($normalized_declared === $normalize($allowed_uri)) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed) {
                    $label = $filename ? " ({$filename})" : '';
                    throw new Exception("Schema file{$label} uses unsupported JSON Schema draft '{$declared}'. Only Draft-07 schemas are supported.");
                }
            }
        }

        $this->assert_no_boolean_items($schema_data, $filename);
    }

    private function assert_no_boolean_items($node, $filename = null)
    {
        $paths = $this->find_boolean_items_paths($node);

        if (!empty($paths)) {
            $label = $filename ? " ({$filename})" : '';
            throw new Exception("Schema file{$label} uses boolean 'items' at: " . implode(', ', $paths) . ". Draft-07 requires 'items' to be an object schema or an array of schemas.");
        }
    }

    private function find_boolean_items_paths($node, $pointer = '', &$paths = array())
    {
        if (is_object($node)) {
            $node = (array)$node;
        }

        if (!is_array($node)) {
            return $paths;
        }

        $isAssoc = $this->is_assoc_array($node);

        if ($isAssoc) {
            foreach ($node as $key => $value) {
                $currentPointer = $pointer . '/' . $key;

                if ($key === 'items' && is_bool($value)) {
                    $paths[] = $currentPointer ?: '/items';
                }

                $this->find_boolean_items_paths($value, $currentPointer, $paths);
            }
        } else {
            foreach ($node as $index => $value) {
                $currentPointer = $pointer . '/' . $index;
                $this->find_boolean_items_paths($value, $currentPointer, $paths);
            }
        }

        return $paths;
    }

    private function rewrite_schema_refs(&$node, $lookup, $current_alias = null, $schema_dir = null)
    {
        if (is_array($node)) {
            foreach ($node as $key => &$value) {
                if ($key === '$ref' && is_string($value)) {
                    if ($current_alias && strpos($value, '#/definitions/') === 0) {
                        $value = '#/components/schemas/' . $current_alias . substr($value, 1);
                        continue;
                    }

                    $parts = explode('#', $value, 2);
                    $ref_target = $parts[0];
                    $fragment = isset($parts[1]) ? $parts[1] : '';

                    if ($ref_target === '' || $ref_target === null) {
                        if ($current_alias && strpos($fragment, '/definitions/') === 0) {
                            $value = '#/components/schemas/' . $current_alias . '/' . ltrim($fragment, '/');
                        }
                        continue;
                    }

                    if (strpos($ref_target, '#') === 0 || preg_match('#^https?://#i', $ref_target)) {
                        continue;
                    }

                    $normalized = $this->normalize_ref_target($ref_target);
                    if (isset($lookup[$normalized])) {
                        $new_ref = '#/components/schemas/' . $lookup[$normalized];
                        $fragment = ltrim($fragment, '/');
                        if ($fragment !== '') {
                            $new_ref .= '/' . $fragment;
                        }
                        $value = $new_ref;
                    } elseif ($schema_dir && strpos($ref_target, 'file://') === 0) {
                        $local_path = substr($ref_target, 7);
                        $local_path = str_replace('\\', '/', $local_path);
                        $local_path = preg_replace('#^/+?#', '/', $local_path);

                        if (is_file($local_path)) {
                            $contents = file_get_contents($local_path);
                            if ($contents !== false) {
                                $decoded = json_decode($contents, true);
                                if (is_array($decoded)) {
                                    $this->rewrite_schema_refs($decoded, $lookup, $current_alias, $schema_dir);
                                    $node = $decoded;
                                }
                            }
                        }
                        unset($node['$ref']);
                    }
                } elseif (is_array($value)) {
                    $this->rewrite_schema_refs($value, $lookup, $current_alias, $schema_dir);
                }
            }
        }
    }

    private function normalize_ref_target($target)
    {
        if ($target === null) {
            return '';
        }

        $target = str_replace('\\', '/', trim($target));
        $target = preg_replace('#/+#', '/', $target);
        $target = preg_replace('#^\./#', '', $target);
        $target = ltrim($target, '/');

        if ($target === '') {
            return '';
        }

        $target = basename($target);

        if ($target === '') {
            return '';
        }

        if (substr($target, -5) === '.json') {
            $target = substr($target, 0, -5);
        }

        return $target;
    }

    private function sanitize_schema_alias($value)
    {
        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value);
        return trim($value, '_');
    }

    private function detect_schema_version($schema_document)
    {
        if (is_array($schema_document)) {
            if (isset($schema_document['version']) && is_string($schema_document['version'])) {
                return $schema_document['version'];
            }
            if (isset($schema_document['info']) && is_array($schema_document['info']) && isset($schema_document['info']['version'])) {
                return (string)$schema_document['info']['version'];
            }
            if (isset($schema_document['$id']) && is_string($schema_document['$id'])) {
                $parts = explode('/', $schema_document['$id']);
                $candidate = array_pop($parts);
                if ($candidate) {
                    return $candidate;
                }
            }
        }

        return '1.0.0';
    }

    private function array_to_yaml($data, $indent = 0)
    {
        $yaml = '';
        $indent_str = str_repeat('  ', $indent);

        if (is_array($data)) {
            $assoc = $this->is_assoc_array($data);

            foreach ($data as $key => $value) {
                if ($assoc) {
                    $yaml .= $indent_str . $key . ':';
                    if (is_array($value)) {
                        $yaml .= "\n" . $this->array_to_yaml($value, $indent + 1);
                    } else {
                        $yaml .= ' ' . $this->yaml_scalar($value) . "\n";
                    }
                } else {
                    $yaml .= $indent_str . '-';
                    if (is_array($value)) {
                        $yaml .= "\n" . $this->array_to_yaml($value, $indent + 1);
                    } else {
                        $yaml .= ' ' . $this->yaml_scalar($value) . "\n";
                    }
                }
            }
        } else {
            $yaml .= $indent_str . $this->yaml_scalar($data) . "\n";
        }

        return $yaml;
    }

    private function yaml_scalar($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return $value;
        }

        $escaped = str_replace('"', '\"', (string)$value);
        if ($escaped === '' || preg_match('/[:\-\?\[\]\{\},&\*\#\!\|\>\<\=\%\@\`]/', $escaped)) {
            return '"' . $escaped . '"';
        }

        return $escaped;
    }

    private function is_assoc_array($array)
    {
        if (!is_array($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function merge_allOf($allOf, $documents, $schema_dir, &$visited, $current_filename = null, &$resolution_stack = array())
    {
        // Initialize resolution_stack if not provided
        if (!is_array($resolution_stack)) {
            $resolution_stack = array();
        }

        $result = array();

        foreach ($allOf as $entry) {
            if (isset($entry['$ref'])) {
                $resolved = $this->resolve_ref($entry['$ref'], $documents, $schema_dir, $current_filename, $visited, $resolution_stack);
                if ($resolved !== null) {
                    // When resolving a file reference without fragment, resolve_ref now returns
                    // only the extracted mergeable schema parts (already cleaned)
                    // So we can merge directly without further extraction
                    $result = $this->deep_merge($result, $resolved);
                }
                continue;
            }

            if (isset($entry['allOf']) && is_array($entry['allOf'])) {
                $expanded = $this->merge_allOf($entry['allOf'], $documents, $schema_dir, $visited, $current_filename, $resolution_stack);
                // Use deep_merge to properly merge nested properties arrays
                $entry = $this->deep_merge($entry, $expanded);
                unset($entry['allOf']);
            }

            $entry = $this->resolve_refs_recursive($entry, $documents, $schema_dir, $current_filename, $visited, $resolution_stack);
            $result = $this->deep_merge($result, $entry);
        }

        return $result;
    }
    
    /**
     * Extract only mergeable parts from a schema document (properties, type, required, etc.)
     * Exclude metadata fields like title, description, $id, $schema that shouldn't overwrite
     * For properties, preserve the structure but don't recursively extract - just copy as-is
     */
    private function extract_mergeable_schema_parts($schema)
    {
        if (!is_array($schema)) {
            return array();
        }
        
        $mergeable_keys = array(
            'type',
            'properties',
            'required',
            'items',
            'additionalProperties',
            'patternProperties',
            'allOf',
            'anyOf',
            'oneOf',
            'enum',
            'const',
            'format',
            'minimum',
            'maximum',
            'minLength',
            'maxLength',
            'minItems',
            'maxItems',
            'uniqueItems'
            // Note: We don't include 'definitions' here because they should be resolved before merging
        );
        
        $result = array();
        foreach ($mergeable_keys as $key) {
            if (isset($schema[$key])) {
                // For 'properties', copy the entire structure as-is
                // The properties object should already be cleaned by resolve_refs_recursive
                // We don't want to recursively extract because that would strip nested properties
                $result[$key] = $schema[$key];
            }
        }
        
        return $result;
    }

    private function resolve_refs_recursive($node, $documents, $schema_dir, $current_filename = null, &$visited = array(), &$resolution_stack = array())
    {
        // Initialize resolution_stack if not provided
        if (!is_array($resolution_stack)) {
            $resolution_stack = array();
        }

        if (is_array($node)) {
            if (isset($node['$ref']) && is_string($node['$ref'])) {
                $resolved = $this->resolve_ref($node['$ref'], $documents, $schema_dir, $current_filename, $visited, $resolution_stack);
                if ($resolved !== null && is_array($resolved)) {
                    // Merge the resolved reference with the original node's metadata
                    // This preserves title, description, _xpath, etc. from the original property
                    // while inlining the referenced schema structure
                    $merged = $resolved;
                    
                    // Preserve metadata fields from the original node (these should override the resolved reference)
                    $metadata_fields = array('title', 'description', '_xpath', 'propertyType', 'format', 'default', 'examples');
                    foreach ($metadata_fields as $field) {
                        if (isset($node[$field])) {
                            $merged[$field] = $node[$field];
                        }
                    }
                    
                    // Merge properties if both have them
                    if (isset($node['properties']) && is_array($node['properties']) && 
                        isset($resolved['properties']) && is_array($resolved['properties'])) {
                        $merged['properties'] = array_merge($resolved['properties'], $node['properties']);
                    }
                    
                    // Remove the $ref key
                    unset($merged['$ref']);
                    
                    return $merged;
                }
                // If resolution returned null (circular reference), keep the $ref as is
                // This allows the schema to still be valid, but the $ref won't be inlined
                return $node;
            }

            foreach ($node as $key => $value) {
                $node[$key] = $this->resolve_refs_recursive($value, $documents, $schema_dir, $current_filename, $visited, $resolution_stack);
            }
        }

        return $node;
    }

    private function resolve_ref($ref, $documents, $schema_dir, $current_filename = null, &$visited = array(), &$resolution_stack = array())
    {
        // Initialize resolution_stack if not provided (track what we're currently resolving to detect cycles)
        if (!is_array($resolution_stack)) {
            $resolution_stack = array();
        }

        $parts = explode('#', $ref, 2);
        $target = $parts[0];
        $fragment = isset($parts[1]) ? $parts[1] : '';

        if ($target === '' || $target === null) {
            if ($current_filename === null) {
                return null;
            }
            $target = $current_filename;
        } else {
            if (strpos($target, 'file://') === 0) {
                $target_path = substr($target, 7);
                $target_path = str_replace('\\', '/', $target_path);
                $target_path = preg_replace('#^/+?#', '/', $target_path);

                if (!is_file($target_path)) {
                    return null;
                }

                $content = file_get_contents($target_path);
                if ($content === false) {
                    return null;
                }

                $decoded = json_decode($content, true);
                if ($decoded === null) {
                    return null;
                }

                $documents[$target_path] = $decoded;
                $target = basename($target_path);
            }

            $target = basename($target);
        }

        // Create a unique key for this specific reference (including fragment)
        $ref_key = $target . '#' . $fragment;
        
        // Check if we're already in the process of resolving this reference (circular reference detection)
        if (in_array($ref_key, $resolution_stack)) {
            // Circular reference detected - return null or a marker to break the cycle
            // For now, return null to break the cycle. The schema validation should handle this.
            return null;
        }
        
        // Check if we've already resolved this specific reference (cache lookup)
        if (isset($visited[$ref_key])) {
            // Return a copy to avoid modifying the original
            $cached = $visited[$ref_key];
            return is_array($cached) ? json_decode(json_encode($cached), true) : $cached;
        }

        // Add to resolution stack to track we're currently resolving this
        $resolution_stack[] = $ref_key;

        $document = $this->load_schema_document($target, $documents, $schema_dir);
        if ($document === null) {
            array_pop($resolution_stack);
            return null;
        }

        $resolved = $document;

        if ($fragment !== '') {
            $tokens = explode('/', ltrim($fragment, '/'));
            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }
                $token = str_replace('~1', '/', str_replace('~0', '~', $token));
                if (is_array($resolved) && array_key_exists($token, $resolved)) {
                    $resolved = $resolved[$token];
                } else {
                    array_pop($resolution_stack);
                    return null;
                }
            }
            // Make a deep copy of the resolved fragment to avoid modifying the original document
            $resolved = is_array($resolved) ? json_decode(json_encode($resolved), true) : $resolved;
        } else {
            // When resolving a file reference without fragment, we want only the schema structure
            // not the entire document with metadata. Extract only mergeable parts.
            // But first, resolve all refs in the document
            $resolved = $this->resolve_refs_recursive($resolved, $documents, $schema_dir, $target, $visited, $resolution_stack);
            // Then extract only the schema constraints (properties, type, etc.)
            $resolved = $this->extract_mergeable_schema_parts($resolved);
            // Remove from resolution stack
            array_pop($resolution_stack);
            // Cache the result
            $visited[$ref_key] = is_array($resolved) ? json_decode(json_encode($resolved), true) : $resolved;
            return $resolved;
        }

        // Resolve any $refs within the resolved fragment
        $resolved = $this->resolve_refs_recursive($resolved, $documents, $schema_dir, $target, $visited, $resolution_stack);
        
        // Remove from resolution stack
        array_pop($resolution_stack);
        
        // Make a deep copy to avoid modifying the original document
        $resolved_copy = is_array($resolved) ? json_decode(json_encode($resolved), true) : $resolved;
        
        // Cache the result
        $visited[$ref_key] = $resolved_copy;

        return $resolved_copy;
    }

    private function load_schema_document($filename, $documents, $schema_dir)
    {
        if (isset($documents[$filename])) {
            return $documents[$filename];
        }

        $path = unix_path($schema_dir . '/' . $filename);
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if ($decoded === null) {
            return null;
        }

        return $decoded;
    }

    private function deep_merge($base, $overlay)
    {
        foreach ($overlay as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                // For 'properties', 'patternProperties', and 'definitions', merge by key
                // These are associative arrays where each key should be merged independently
                // We merge keys at the same level, not recursively merge into nested properties
                if ($key === 'properties' || $key === 'patternProperties' || $key === 'definitions') {
                    // Merge by key: if both have the same key, recursively merge the value
                    // If only one has the key, add it
                    foreach ($value as $prop_key => $prop_value) {
                        if (isset($base[$key][$prop_key]) && is_array($base[$key][$prop_key]) && is_array($prop_value)) {
                            // Both have the same property key - recursively merge the property schemas
                            // But don't merge properties->prop_name->properties with root properties
                            $base[$key][$prop_key] = $this->deep_merge($base[$key][$prop_key], $prop_value);
                        } else {
                            // New property key - just add it
                            $base[$key][$prop_key] = $prop_value;
                        }
                    }
                } else {
                    // For other keys, recursively merge
                    $base[$key] = $this->deep_merge($base[$key], $value);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Get icon URL for a schema.
     *
     * @param string $schema_uid Schema UID
     * @return string Icon URL (relative to base_url)
     */
    public function get_schema_icon_url($schema_uid)
    {
        $this->ci->config->load('schemas');
        $schema_config = $this->ci->config->item('schemas');

        if (!is_array($schema_config)) {
            return null;
        }

        $icon_path = isset($schema_config['icon_path']) ? $schema_config['icon_path'] : 'images';
        $icons = isset($schema_config['icons']) && is_array($schema_config['icons']) ? $schema_config['icons'] : array();
        $default_icon = isset($schema_config['default_icon']) ? $schema_config['default_icon'] : null;
        $icon_extension = isset($schema_config['icon_extension']) ? $schema_config['icon_extension'] : '';

        $icon_filename = null;

        if (!empty($icons) && isset($icons[$schema_uid]) && $icons[$schema_uid]) {
            $icon_filename = $icons[$schema_uid];
        } elseif (!empty($default_icon)) {
            $icon_filename = $default_icon;
        }

        if (!$icon_filename) {
            return null;
        }

        if ($icon_extension && substr($icon_filename, -strlen($icon_extension)) !== $icon_extension) {
            $icon_filename .= $icon_extension;
        }

        $icon_path = trim($icon_path);
        $icon_path = $icon_path === '' ? '' : trim($icon_path, '/');

        if ($icon_path === '') {
            return ltrim($icon_filename, '/');
        }

        return $icon_path . '/' . ltrim($icon_filename, '/');
    }

    /**
     * Get full icon URL (absolute) for a schema.
     *
     * @param string $schema_uid Schema UID
     * @return string Full icon URL
     */
    public function get_schema_icon_full_url($schema_uid)
    {
        $relative_path = $this->get_schema_icon_url($schema_uid);

        if (!$relative_path) {
            return null;
        }

        $base = '';

        if (function_exists('base_url')) {
            $base = base_url();
        } elseif (isset($this->ci->config)) {
            $base = $this->ci->config->item('base_url');
        }

        $base = is_string($base) ? rtrim($base, '/') : '';
        $relative = ltrim($relative_path, '/');

        if ($relative === '') {
            return $base !== '' ? $base : null;
        }

        if ($base === '') {
            return $relative;
        }

        return $base . '/' . $relative;
    }

    /**
     * Get display name for a schema.
     * 
     * Note: Display names should be provided via translations (schemas_lang.php).
     * This method provides a fallback by capitalizing the UID.
     *
     * @param string $schema_uid Schema UID
     * @return string Display name (falls back to capitalized UID)
     */
    public function get_schema_display_name($schema_uid)
    {
        // Display names are handled via translations
        // Fallback: capitalize first letter of UID
        return ucfirst(str_replace('-', ' ', $schema_uid));
    }

    /**
     * Check if a schema UID is reserved (core schema).
     *
     * @param string $schema_uid Schema UID
     * @return bool True if reserved, false otherwise
     */
    public function is_reserved_uid($schema_uid)
    {
        $this->ci->config->load('schemas');
        $schema_config = $this->ci->config->item('schemas');

        if (!is_array($schema_config)) {
            return false;
        }

        $reserved = isset($schema_config['reserved_uids']) && is_array($schema_config['reserved_uids'])
            ? $schema_config['reserved_uids']
            : array();

        return in_array($schema_uid, $reserved);
    }

    /**
     * Lazy load metadata schemas model.
     *
     * @return Metadata_schemas_model
     * @throws Exception
     */
    private function get_model()
    {
        if (!isset($this->ci->Metadata_schemas_model)) {
            $this->ci->load->model('Metadata_schemas_model');
        }
        return $this->ci->Metadata_schemas_model;
    }
}

