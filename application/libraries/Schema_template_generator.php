<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Schema template generator
 *
 * Generates full-field templates from JSON Schemas and persists them
 * as system (read-only) templates linked to custom schemas.
 */
class Schema_template_generator
{
    /**
     * @var CI_Controller
     */
    protected $ci;

    /**
     * @var Schema_registry
     */
    protected $schema_registry;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->library('Schema_registry');
        $this->ci->load->model('Metadata_schemas_model');
        $this->ci->load->model('Editor_template_model');

        $this->schema_registry = $this->ci->schema_registry;
    }

    /**
     * Regenerate (or generate) the system template for a schema.
     *
     * @param array $schema Schema DB row
     * @param array $options ['force' => bool, 'updated_by' => user id]
     * @return array
     * @throws Exception
     */
    public function regenerate($schema, $options = array())
    {
        if (empty($schema) || empty($schema['uid'])) {
            throw new Exception("Schema is required.");
        }

        if (!empty($schema['is_core'])) {
            throw new Exception("Core schemas already ship with templates.");
        }

        $force = !empty($options['force']);
        $updated_by = isset($options['updated_by']) ? $options['updated_by'] : null;

        $schema_dir = $this->ci->Metadata_schemas_model->resolve_schema_path($schema);
        if (!is_dir($schema_dir)) {
            throw new Exception("Schema directory not found: " . $schema_dir);
        }

        $documents = $this->schema_registry->load_schema_documents($schema, $schema_dir);
        $compiled = $this->schema_registry->inline_schema($schema['filename'], $documents, $schema_dir);

        $schema_hash = $this->compute_schema_hash($compiled);

        $metadata_options = $this->normalize_metadata_options($schema);
        $existing_hash = isset($metadata_options['template']['schema_hash']) ? $metadata_options['template']['schema_hash'] : null;

        if (!$force && $existing_hash && $existing_hash === $schema_hash) {
            return array(
                'status' => 'skipped',
                'schema' => $schema,
                'template_uid' => isset($metadata_options['template']['template_uid']) ? $metadata_options['template']['template_uid'] : null,
                'schema_hash' => $schema_hash
            );
        }

        $template_payload = $this->build_template($schema, $compiled);
        $template_uid = $this->ci->Editor_template_model->upsert_generated_template($schema, $template_payload);

        $metadata_options['template'] = array_merge(
            isset($metadata_options['template']) && is_array($metadata_options['template']) ? $metadata_options['template'] : array(),
            array(
                'schema_hash' => $schema_hash,
                'template_uid' => $template_uid,
                'generated' => date('U')
            )
        );

        $update_data = array(
            'metadata_options' => $metadata_options,
            'updated' => date('U')
        );

        if ($updated_by !== null) {
            $update_data['updated_by'] = $updated_by;
        }

        $this->ci->Metadata_schemas_model->update($schema['id'], $update_data);
        $refreshed = $this->ci->Metadata_schemas_model->get_by_id($schema['id']);

        return array(
            'status' => 'updated',
            'schema' => $refreshed,
            'template_uid' => $template_uid,
            'schema_hash' => $schema_hash
        );
    }

    /**
     * Build template payload from compiled schema.
     */
    protected function build_template($schema, $compiled_schema)
    {
        $title = !empty($schema['title']) ? $schema['title'] : ucfirst($schema['uid']) . ' Template';
        $description = !empty($schema['description']) ? $schema['description'] : '';

        $items = $this->build_section_items($compiled_schema, '', 0);

        // Ensure templates have at least one section_container or section for template manager compatibility
        // If no top-level containers exist, wrap all items in a default section_container
        $items = $this->ensure_section_container($items, $title);

        return array(
            'type' => 'template',
            'title' => $title,
            'description' => $description,
            'items' => $items
        );
    }

    /**
     * Ensure template has at least one section_container or section at the top level.
     * If none exist, wrap all items in a default section_container.
     *
     * @param array $items Template items
     * @param string $default_title Title to use for the wrapper container
     * @return array Items with at least one section_container/section
     */
    protected function ensure_section_container($items, $default_title = 'Fields')
    {
        if (empty($items)) {
            return $items;
        }

        // Check if any top-level item is a section_container or section
        $has_container = false;
        foreach ($items as $item) {
            if (isset($item['type']) && in_array($item['type'], array('section_container', 'section'), true)) {
                $has_container = true;
                break;
            }
        }

        // If no containers exist, wrap all items in a default section_container
        if (!$has_container) {
            return array(
                array(
                    'type' => 'section_container',
                    'key' => 'fields',
                    'title' => $default_title . ' Fields',
                    'help_text' => '',
                    'expanded' => true,
                    'items' => $items
                )
            );
        }

        return $items;
    }

    protected function build_section_items($schema, $base_path = '', $depth = 0)
    {
        $items = array();

        if (!isset($schema['properties']) || !is_array($schema['properties'])) {
            return $items;
        }

        $required = isset($schema['required']) && is_array($schema['required'])
            ? array_flip($schema['required'])
            : array();

        foreach ($schema['properties'] as $name => $child) {
            $path = $this->join_key($base_path, $name);
            $items[] = $this->build_node($child, $path, $name, $depth + 1, isset($required[$name]));
        }

        return $items;
    }

    protected function build_node($schema, $path, $name, $depth, $is_required = false)
    {
        if ($this->is_array_schema($schema)) {
            return $this->build_array_field($schema, $path, $name, $depth + 1, $is_required);
        }

        if ($this->is_object_schema($schema)) {
            return $this->build_object_field($schema, $path, $name, $depth, $is_required);
        }

        return $this->build_field($schema, $path, $name, $is_required);
    }

    protected function build_object_field($schema, $path, $name, $depth, $is_required = false)
    {
        return array(
            'type' => $depth <= 1 ? 'section_container' : 'section',
            'key' => $path,
            'title' => $this->resolve_title($schema, $name),
            'help_text' => isset($schema['description']) ? $schema['description'] : '',
            'expanded' => $depth <= 2,
            'items' => $this->build_section_items($schema, $path, $depth)
        );
    }

    protected function build_field($schema, $path, $name, $is_required = false)
    {
        $field = array(
            'key' => $path,
            'title' => $this->resolve_title($schema, $name),
            'type' => $this->normalize_type($schema),
            'required' => (bool)$is_required,
            'help_text' => isset($schema['description']) ? $schema['description'] : '',
            'display_type' => $this->resolve_display_type($schema)
        );

        $enum = $this->build_enum($schema);
        if (!empty($enum)) {
            $field['enum'] = $enum;
            $field['enum_store_column'] = 'code';
        }

        return $field;
    }

    protected function build_array_field($schema, $path, $name, $depth, $is_required = false)
    {
        $items_schema = isset($schema['items']) ? $schema['items'] : array();
        $props = $this->build_array_props($items_schema, $path);
        $child_items = array();

        if (!empty($items_schema) && is_array($items_schema)) {
            $child_items = $this->build_section_items($items_schema, $path, $depth);
        }

        return array(
            'key' => $path,
            'title' => $this->resolve_title($schema, $name),
            'type' => 'array',
            'required' => (bool)$is_required,
            'help_text' => isset($schema['description']) ? $schema['description'] : '',
            'props' => $props,
            'items' => !empty($child_items) ? $child_items : null
        );
    }

    protected function build_array_props($schema, $base_path)
    {
        $props = array();

        if ($this->is_object_schema($schema) && isset($schema['properties'])) {
            $required = isset($schema['required']) && is_array($schema['required'])
                ? array_flip($schema['required'])
                : array();

            foreach ($schema['properties'] as $name => $child) {
                $prop_path = $this->join_key($base_path, $name);

                if ($this->is_object_schema($child)) {
                    $nested = $this->build_array_props($child, $prop_path);
                    $props = array_merge($props, $nested);
                    continue;
                }

                $props[] = array(
                    'key' => $name,
                    'title' => $this->resolve_title($child, $name),
                    'type' => $this->normalize_type($child),
                    'prop_key' => $prop_path,
                    'required' => isset($required[$name]),
                    'help_text' => isset($child['description']) ? $child['description'] : '',
                    'display_type' => $this->resolve_display_type($child)
                );

                $enum = $this->build_enum($child);
                if (!empty($enum)) {
                    $props[count($props) - 1]['enum'] = $enum;
                    $props[count($props) - 1]['enum_store_column'] = 'code';
                }
            }

            return $props;
        }

        // Fallback for arrays of primitives
        $props[] = array(
            'key' => 'value',
            'title' => $this->resolve_title($schema, 'value'),
            'type' => $this->normalize_type($schema),
            'prop_key' => $base_path,
            'required' => false,
            'help_text' => isset($schema['description']) ? $schema['description'] : '',
            'display_type' => $this->resolve_display_type($schema)
        );

        $enum = $this->build_enum($schema);
        if (!empty($enum)) {
            $props[count($props) - 1]['enum'] = $enum;
            $props[count($props) - 1]['enum_store_column'] = 'code';
        }

        return $props;
    }

    protected function resolve_title($schema, $fallback)
    {
        if (isset($schema['title']) && $schema['title'] !== '') {
            return $schema['title'];
        }

        return $this->humanize($fallback);
    }

    protected function resolve_display_type($schema)
    {
        $type = $this->normalize_type($schema);

        switch ($type) {
            case 'integer':
            case 'number':
                return 'number';
            case 'boolean':
                return 'checkbox';
            case 'string':
            default:
                if (isset($schema['format']) && in_array($schema['format'], array('date', 'date-time'), true)) {
                    return 'text';
                }
                if (isset($schema['enum']) && is_array($schema['enum']) && count($schema['enum']) > 0) {
                    return 'dropdown';
                }
                return 'text';
        }
    }

    protected function build_enum($schema)
    {
        if (!isset($schema['enum']) || !is_array($schema['enum']) || empty($schema['enum'])) {
            return array();
        }

        $enum = array();
        foreach ($schema['enum'] as $value) {
            $enum[] = array(
                'code' => $value,
                'label' => (string)$value
            );
        }

        return $enum;
    }

    protected function normalize_type($schema)
    {
        if (!isset($schema['type'])) {
            return 'string';
        }

        if (is_array($schema['type'])) {
            return reset($schema['type']);
        }

        return $schema['type'];
    }

    protected function is_object_schema($schema)
    {
        $type = $this->normalize_type($schema);
        return $type === 'object' || isset($schema['properties']);
    }

    protected function is_array_schema($schema)
    {
        $type = $this->normalize_type($schema);
        return $type === 'array';
    }

    protected function join_key($base, $segment)
    {
        if ($base === '' || $base === null) {
            return $segment;
        }

        return $base . '.' . $segment;
    }

    protected function normalize_metadata_options($schema)
    {
        if (isset($schema['metadata_options']) && is_array($schema['metadata_options'])) {
            return $schema['metadata_options'];
        }

        if (!empty($schema['metadata_options']) && is_string($schema['metadata_options'])) {
            $decoded = json_decode($schema['metadata_options'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array();
    }

    protected function humanize($value)
    {
        $value = str_replace(array('_', '-', '.'), ' ', (string)$value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        if ($value === '') {
            return 'Field';
        }
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    protected function compute_schema_hash($schema)
    {
        return sha1(json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
