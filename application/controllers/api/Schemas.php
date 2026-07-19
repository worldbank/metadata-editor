<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

/**
 * Schemas API
 *
 * Provides access to metadata schema registry.
 */
class Schemas extends MY_REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Schema_registry');
        $this->load->helper('date');
        $this->is_authenticated_or_die();
    }

    /**
     * Allow session authentication in addition to API keys.
     */
    public function _auth_override_check()
    {
        if ($this->session->userdata('user_id')) {
            return true;
        }
        parent::_auth_override_check();
    }

    /**
     * List schemas
     *
     * Query parameters:
     *  - include_core=true|false (default true)
     *  - status=active|deprecated|draft
     *  - search=string
     */
    public function index_get()
    {
        try {
            $include_core = $this->input->get('include_core');
            $status = $this->input->get('status');
            $search = $this->input->get('search');

            $options = array();

            if ($include_core !== null) {
                $options['include_core'] = filter_var($include_core, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($options['include_core'] === null) {
                    unset($options['include_core']);
                }
            }

            if ($status) {
                $options['status'] = $status;
            }

            if ($search) {
                $options['search'] = $search;
            }

            $schemas = $this->schema_registry->list_schemas($options);

            $response = array(
                'status' => 'success',
                'count' => count($schemas),
                'schemas' => $schemas
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );

            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Create new schema (custom/user defined)
     *
     * Expects multipart/form-data with fields:
     *  - uid
     *  - title
     *  - agency
     *  - description
     *  - metadata_options (JSON string)
     *  - main_schema (file)
     *  - schema_files[] (optional files)
     */
    public function index_post()
    {
        try {
            $this->is_admin_or_die();

            $this->load->model('Metadata_schemas_model');
            $this->load->library('Schema_registry');
            $this->load->helper(array('string', 'file'));

            $uid = trim($this->input->post('uid'));
            $title = trim($this->input->post('title'));
            $agency = trim($this->input->post('agency'));
            $description = trim($this->input->post('description'));
            $metadata_options_raw = $this->input->post('metadata_options');

            if (empty($uid)) {
                throw new Exception("UID is required");
            }

            if (!preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $uid)) {
                throw new Exception("Invalid UID format. Use 3-64 characters (letters, numbers, dash, underscore).");
            }

            if ($this->Metadata_schemas_model->get_by_uid($uid)) {
                throw new Exception("Schema with UID already exists: {$uid}");
            }

            if (empty($_FILES['main_schema']) || !isset($_FILES['main_schema']['tmp_name'])) {
                throw new Exception("Main schema JSON file is required.");
            }

            list($storage_root, $schema_dir) = $this->setup_schema_directory($uid);

            // Save main schema file
            $main_file_tmp = $_FILES['main_schema']['tmp_name'];
            $main_filename = !empty($_FILES['main_schema']['name']) ? $_FILES['main_schema']['name'] : 'root.json';

            if (!is_uploaded_file($main_file_tmp)) {
                throw new Exception("Invalid main schema upload.");
            }

            $main_json = file_get_contents($main_file_tmp);
            if ($main_json === false || json_decode($main_json, true) === null) {
                throw new Exception("Main schema is not valid JSON.");
            }
            $main_json_decoded = json_decode($main_json);

            $main_filename = $this->sanitize_filename($main_filename);
            if (empty($main_filename)) {
                $main_filename = 'root.json';
            }

            $this->schema_registry->assert_valid_json_schema($main_json_decoded, $main_filename);

            $main_destination = $schema_dir . '/' . $main_filename;
            if (!@move_uploaded_file($main_file_tmp, $main_destination)) {
                throw new Exception("Failed to store main schema file.");
            }

            $related_files_list = array();
            $related_files_decoded = array();
            $related_uploads = array();

            if (!empty($_FILES['schema_files']) && is_array($_FILES['schema_files']['name'])) {
                $related_uploads = $this->collect_related_uploads($_FILES['schema_files']);
                foreach ($related_uploads as $upload) {
                $this->schema_registry->assert_valid_json_schema($upload['decoded_json'], $upload['clean_name']);
                    $related_files_decoded[$upload['clean_name']] = $upload['decoded_json'];
                    $related_files_list[] = $upload['clean_name'];
                }
            }

            $this->schema_registry->validate_json_schema($schema_dir, $main_filename, $main_json_decoded, $related_files_decoded);

            $this->write_related_files($related_uploads, $schema_dir);

            $now = date('U');

            $insert_data = array(
                'uid' => $uid,
                'title' => $title,
                'agency' => $agency,
                'description' => $description,
                'is_core' => 0,
                'status' => 'active',
                'storage_path' => $uid,
                'filename' => $main_filename,
                'schema_files' => $related_files_list,
                'metadata_options' => $this->parse_metadata_options($metadata_options_raw),
                'created' => $now,
                'created_by' => $this->api_user ? $this->api_user->id : null
            );

            $schema_id = $this->Metadata_schemas_model->insert($insert_data);

            $result = $this->Metadata_schemas_model->get_by_id($schema_id);
            $result = $this->regenerate_schema_template_for_schema($result, true);

            $response = array(
                'status' => 'success',
                'schema' => $result
            );

            $this->set_response($response, REST_Controller::HTTP_CREATED);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function files_get($uid = null)
    {
        try {
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);

            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: " . $schema_dir);
            }

            $manifest = $this->schema_registry->build_schema_file_manifest($schema, $schema_dir);

            $response = array(
                'status' => 'success',
                'schema' => array(
                    'uid' => $schema['uid'],
                    'title' => $schema['title'],
                    'description' => $schema['description'],
                    'is_core' => (int)$schema['is_core']
                ),
                'files' => $manifest,
                'links' => array(
                    'openapi_json' => site_url('api/schemas/openapi/' . rawurlencode($schema['uid'])),
                    'openapi_yaml' => site_url('api/schemas/openapi/' . rawurlencode($schema['uid']) . '?format=yaml')
                )
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function files_post($uid = null)
    {
        try {
            $this->is_admin_or_die();
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            if (!empty($schema['is_core'])) {
                throw new Exception("Core schemas cannot be modified.");
            }

            $mode = trim((string)$this->input->post('mode'));
            if ($mode === '') {
                throw new Exception("Mode is required. Allowed values: replace_main, add_related.");
            }

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: " . $schema_dir);
            }

            switch ($mode) {
                case 'replace_main':
                    $result = $this->handle_replace_main_schema($schema, $schema_dir);
                    break;
                case 'add_related':
                    $result = $this->handle_add_related_schemas($schema, $schema_dir);
                    break;
                default:
                    throw new Exception("Invalid mode. Allowed values: replace_main, add_related.");
            }

            $response = array(
                'status' => 'success',
                'message' => $result['message'],
                'schema' => $result['schema'],
                'files' => $result['files']
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function files_delete($uid = null)
    {
        try {
            $this->is_admin_or_die();
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            if (!empty($schema['is_core'])) {
                throw new Exception("Core schemas cannot be modified.");
            }

            $filename = $this->input->get('filename');

            if ($filename === null || $filename === '') {
                $raw_input = $this->input->raw_input_stream;
                if (!empty($raw_input)) {
                    $decoded = json_decode($raw_input, true);
                    if (isset($decoded['filename'])) {
                        $filename = $decoded['filename'];
                    }
                }
            }

            if ($filename === null || $filename === '') {
                throw new Exception("Filename is required.");
            }

            $filename = basename($filename);

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: " . $schema_dir);
            }

            $result = $this->handle_delete_related_schema($schema, $schema_dir, $filename);

            $response = array(
                'status' => 'success',
                'message' => $result['message'],
                'schema' => $result['schema'],
                'files' => $result['files']
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function regenerate_template_post($uid = null)
    {        
        try {
            $this->is_admin_or_die();
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            if (!empty($schema['is_core'])) {
                throw new Exception("Core schemas already include templates.");
            }

            $updated = $this->regenerate_schema_template_for_schema($schema, true);

            $response = array(
                'status' => 'success',
                'schema' => $updated
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function file_get($uid = null, $filename = null)
    {
        try {
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            if ($filename === null || $filename === '') {
                throw new Exception("Filename is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: " . $schema_dir);
            }

            $filename = basename($filename);
            $manifest = $this->schema_registry->build_schema_file_manifest($schema, $schema_dir, true);

            if (!isset($manifest['lookup'][$filename])) {
                throw new Exception("Schema file not found: " . $filename);
            }

            $file_path = $manifest['lookup'][$filename];

            if (!is_file($file_path)) {
                throw new Exception("Schema file not found: " . $filename);
            }

            $contents = file_get_contents($file_path);
            if ($contents === false) {
                throw new Exception("Failed to read schema file: " . $filename);
            }

            $this->output
                ->set_content_type('application/json')
                ->set_status_header(200)
                ->set_header('Content-Disposition: inline; filename="' . $filename . '"')
                ->set_output($contents);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function detail_get($uid = null)
    {
        try {
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            // Enrich schema with icon URLs and metadata
            if (isset($schema['uid'])) {
                $schema['icon_url'] = $this->schema_registry->get_schema_icon_url($schema['uid']);
                $schema['icon_full_url'] = $this->schema_registry->get_schema_icon_full_url($schema['uid']);
                $schema['display_name'] = $this->schema_registry->get_schema_display_name($schema['uid']);
            }

            $response = array(
                'status' => 'success',
                'schema' => $schema
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function openapi_get($uid = null)
    {
        try {
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: " . $schema_dir);
            }

            $documents = $this->schema_registry->load_schema_documents($schema, $schema_dir);
            $alias_map = $this->schema_registry->build_schema_alias_map($schema, $documents);
            $spec = $this->schema_registry->generate_openapi_spec($schema, $documents, $alias_map);

            $format = strtolower($this->input->get('format'));
            if ($format === 'yaml' || $format === 'yml') {
                $yaml = $this->schema_registry->convert_to_yaml($spec);
                $this->output
                    ->set_status_header(200)
                    ->set_content_type('application/yaml')
                    ->set_output($yaml);
                return;
            }

            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function update_post($uid = null)
    {
        try {
            $this->is_admin_or_die();
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            // Allow updating core schema metadata_options and selected fields; actual write filtering is handled by the model

            $title = trim($this->input->post('title'));
            $agency = trim($this->input->post('agency'));
            $description = trim($this->input->post('description'));
            $metadata_options_raw = $this->input->post('metadata_options');
            $status = $this->input->post('status');

            if ($title === '') {
                throw new Exception("Title is required.");
            }

            $metadata_options = $this->parse_metadata_options($metadata_options_raw);

            $update_data = array(
                'title' => $title,
                'agency' => $agency,
                'description' => $description,
                'metadata_options' => $metadata_options,
                'updated' => date('U'),
                'updated_by' => $this->api_user ? $this->api_user->id : null
            );

            if (!empty($status)) {
                $allowed_status = array('active', 'deprecated', 'draft');
                if (!in_array($status, $allowed_status, true)) {
                    throw new Exception("Invalid status value.");
                }
                $update_data['status'] = $status;
            }

            $this->Metadata_schemas_model->update($schema['id'], $update_data);

            $updated = $this->Metadata_schemas_model->get_by_id($schema['id']);
            $updated = $this->regenerate_schema_template_for_schema($updated, true);

            $response = array(
                'status' => 'success',
                'schema' => $updated
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function compiled_schema_get($uid = null)
    {
        try {
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: " . $schema_dir);
            }

            $documents = $this->schema_registry->load_schema_documents($schema, $schema_dir);

            $compiled = $this->schema_registry->inline_schema($schema['filename'], $documents, $schema_dir);

            $this->output
                ->set_content_type('application/json')
                ->set_status_header(200)
                ->set_output(json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Schema field paths for a schema UID.
     *
     * GET /api/schemas/fields/{uid}
     *
     * Query:
     *  - format=json_patch|default|dotted
     *      json_patch/default: slash paths from collect_schema_fields (existing)
     *      dotted: template-oriented dotted keys + keys[] list
     *
     * Response always includes template_key_aliases (template prefix → schema prefix).
     */
    public function fields_get($uid = null)
    {
        try {
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $format = $this->input->get('format', true);
            $format = $format ? strtolower(trim($format)) : 'default';
            if (!in_array($format, array('json_patch', 'default', 'dotted'), true)) {
                throw new Exception("Invalid format parameter. Allowed values: json_patch, default, dotted");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            $schema_dir = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if (!is_dir($schema_dir)) {
                throw new Exception("Schema directory not found: " . $schema_dir);
            }

            // Resolve actual filename (handles aliases / renamed files)
            $schema_file = $this->Metadata_schemas_model->get_schema_file_path($uid);
            $actual_filename = basename($schema_file);
            $schema_for_loading = $schema;
            $schema_for_loading['filename'] = $actual_filename;

            $documents = $this->schema_registry->load_schema_documents($schema_for_loading, $schema_dir);
            $compiled = $this->schema_registry->inline_schema($actual_filename, $documents, $schema_dir);

            // Walk with json_patch paths; convert to dotted when requested
            $collect_format = ($format === 'dotted') ? 'json_patch' : $format;
            $fields = array();

            // Only collect fields from properties, not from root schema metadata
            if (isset($compiled['properties']) && is_array($compiled['properties'])) {
                $seen_paths = array();
                $required = array();
                if (isset($compiled['required']) && is_array($compiled['required'])) {
                    $required = $compiled['required'];
                }
                $required_set = array_flip($required);

                foreach ($compiled['properties'] as $name => $child) {
                    $canonical_path = '/' . $name;
                    if (!isset($seen_paths[$canonical_path])) {
                        $this->schema_registry->collect_schema_fields(
                            $child,
                            $canonical_path,
                            $fields,
                            isset($required_set[$name]),
                            $collect_format,
                            $seen_paths
                        );
                    }
                }
            } else {
                $this->schema_registry->collect_schema_fields($compiled, '', $fields, false, $collect_format);
            }

            $schema_uid = isset($schema['uid']) ? $schema['uid'] : $uid;
            $template_key_aliases = $this->schema_registry->get_template_key_aliases($schema_uid);
            // Also resolve by request uid (alias) in case it differs
            if (empty($template_key_aliases) && strtolower((string)$uid) !== strtolower((string)$schema_uid)) {
                $template_key_aliases = $this->schema_registry->get_template_key_aliases($uid);
            }

            if ($format === 'dotted') {
                $dotted = $this->schema_registry->fields_to_dotted_template_keys($fields);
                $response = array(
                    'status' => 'success',
                    'schema_uid' => $schema_uid,
                    'count' => count($dotted['fields']),
                    'keys' => $dotted['keys'],
                    'fields' => $dotted['fields'],
                    'template_key_aliases' => $template_key_aliases
                );
            } else {
                $response = array(
                    'status' => 'success',
                    'schema_uid' => $schema_uid,
                    'count' => count($fields),
                    'fields' => $fields,
                    'template_key_aliases' => $template_key_aliases
                );
            }

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    private function setup_schema_directory($uid)
    {
        $storage_root = $this->Metadata_schemas_model->get_custom_base_path();

        if (!$storage_root) {
            throw new Exception("Custom schema storage path not configured.");
        }

        $storage_root = unix_path($storage_root);

        if (!is_dir($storage_root)) {
            if (!@mkdir($storage_root, 0777, true)) {
                throw new Exception("Custom schema storage path does not exist and could not be created: " . $storage_root);
            }
        }

        if (!is_writable($storage_root)) {
            throw new Exception("Custom schema storage path is not writable: " . $storage_root);
        }

        $schema_dir = unix_path($storage_root . '/' . $uid);

        if (!is_dir($schema_dir)) {
            if (!@mkdir($schema_dir, 0777, true)) {
                throw new Exception("Failed to create schema directory: " . $schema_dir);
            }
        }

        if (!is_writable($schema_dir)) {
            throw new Exception("Schema directory is not writable: " . $schema_dir);
        }

        return array($storage_root, $schema_dir);
    }

    private function collect_related_uploads($files)
    {
        $collected = array();
        $total = count($files['name']);

        for ($i = 0; $i < $total; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }

            $tmp_name = $files['tmp_name'][$i];
            $original_name = $files['name'][$i];

            if (!is_uploaded_file($tmp_name)) {
                continue;
            }

            $file_contents = file_get_contents($tmp_name);
            if ($file_contents === false) {
                continue;
            }

            $decoded_json = json_decode($file_contents);
            if ($decoded_json === null) {
                continue;
            }

            $clean_name = $this->sanitize_filename($original_name);
            if (empty($clean_name)) {
                continue;
            }

            $collected[] = array(
                'clean_name' => $clean_name,
                'tmp_name' => $tmp_name,
                'decoded_json' => $decoded_json
            );
        }

        return $collected;
    }

    private function write_related_files($uploads, $target_dir)
    {
        foreach ($uploads as $upload) {
            $destination = $target_dir . '/' . $upload['clean_name'];
            @move_uploaded_file($upload['tmp_name'], $destination);
        }
    }

    private function handle_replace_main_schema($schema, $schema_dir)
    {
        if (empty($_FILES['main_schema']) || !isset($_FILES['main_schema']['tmp_name'])) {
            throw new Exception("Main schema file is required.");
        }

        $tmp_name = $_FILES['main_schema']['tmp_name'];
        if (!is_uploaded_file($tmp_name)) {
            throw new Exception("Invalid main schema upload.");
        }

        $raw_contents = file_get_contents($tmp_name);
        if ($raw_contents === false) {
            throw new Exception("Failed to read uploaded main schema file.");
        }

        $decoded = json_decode($raw_contents, true);
        if ($decoded === null) {
            throw new Exception("Uploaded main schema file is not valid JSON.");
        }

        $original_name = !empty($_FILES['main_schema']['name']) ? $_FILES['main_schema']['name'] : 'main.json';
        $clean_name = $this->sanitize_filename($original_name);
        if ($clean_name === '') {
            $clean_name = 'main.json';
        }

        $existing_schema_files = array();
        if (!empty($schema['schema_files']) && is_array($schema['schema_files'])) {
            $existing_schema_files = $schema['schema_files'];
        }

        $this->schema_registry->assert_valid_json_schema($decoded, $clean_name);

        $documents = array();
        try {
            $documents = $this->schema_registry->load_schema_documents($schema, $schema_dir);
        } catch (Exception $e) {
            $documents = array();
        }

        if (empty($documents) && !empty($existing_schema_files)) {
            foreach ($existing_schema_files as $related) {
                $path = unix_path($schema_dir . '/' . $related);
                if (!is_file($path)) {
                    continue;
                }
                $contents = file_get_contents($path);
                if ($contents === false) {
                    continue;
                }
                $decoded_related = json_decode($contents, true);
                if ($decoded_related !== null) {
                    $documents[$related] = $decoded_related;
                }
            }
        }

        if (!empty($schema['filename']) && isset($documents[$schema['filename']])) {
            unset($documents[$schema['filename']]);
        }

        $documents[$clean_name] = $decoded;

        $this->schema_registry->validate_schema_documents($documents, $clean_name, $schema_dir);

        $destination = unix_path($schema_dir . '/' . $clean_name);
        if (is_file($destination)) {
            @unlink($destination);
        }
        if (!@move_uploaded_file($tmp_name, $destination)) {
            throw new Exception("Failed to store the uploaded main schema file.");
        }

        if ($clean_name !== $schema['filename'] && !empty($schema['filename'])) {
            $previous = unix_path($schema_dir . '/' . $schema['filename']);
            if (is_file($previous)) {
                @unlink($previous);
            }
        }

        $schema_files_changed = false;
        if ($clean_name !== $schema['filename']) {
            $filtered = array_values(array_filter($existing_schema_files, function ($item) use ($clean_name) {
                return $item !== $clean_name;
            }));
            if ($filtered !== $existing_schema_files) {
                $existing_schema_files = $filtered;
                $schema_files_changed = true;
            }
        }

        $update_data = array(
            'filename' => $clean_name,
            'updated' => date('U'),
            'updated_by' => $this->api_user ? $this->api_user->id : null
        );

        if ($schema_files_changed) {
            $update_data['schema_files'] = array_values($existing_schema_files);
        }

        $this->Metadata_schemas_model->update($schema['id'], $update_data);
        $updated = $this->Metadata_schemas_model->get_by_id($schema['id']);
        $updated = $this->regenerate_schema_template_for_schema($updated, true);
        $manifest = $this->schema_registry->build_schema_file_manifest($updated, $schema_dir);

        return array(
            'schema' => $updated,
            'files' => $manifest,
            'message' => 'Main schema file replaced successfully.'
        );
    }

    private function handle_add_related_schemas($schema, $schema_dir)
    {
        if (empty($_FILES['schema_files']) || !is_array($_FILES['schema_files']['name'])) {
            throw new Exception("No related schema files were uploaded.");
        }

        $uploads = $this->collect_related_uploads($_FILES['schema_files']);
        if (empty($uploads)) {
            throw new Exception("No valid related schema files were uploaded.");
        }

        $existing_files = array();
        if (!empty($schema['schema_files']) && is_array($schema['schema_files'])) {
            $existing_files = $schema['schema_files'];
        }

        $documents = $this->schema_registry->load_schema_documents($schema, $schema_dir);

        foreach ($uploads as $upload) {
            if ($upload['clean_name'] === $schema['filename']) {
                throw new Exception("Uploaded filename matches the main schema filename: " . $upload['clean_name']);
            }

            $this->schema_registry->assert_valid_json_schema($upload['decoded_json'], $upload['clean_name']);

            $doc_array = json_decode(json_encode($upload['decoded_json']), true);
            if ($doc_array === null) {
                throw new Exception("Failed to normalize uploaded schema file: " . $upload['clean_name']);
            }

            $documents[$upload['clean_name']] = $doc_array;
        }

        $this->schema_registry->validate_schema_documents($documents, $schema['filename'], $schema_dir);

        foreach ($uploads as $upload) {
            $destination = unix_path($schema_dir . '/' . $upload['clean_name']);
            if (is_file($destination)) {
                @unlink($destination);
            }
            if (!@move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new Exception("Failed to store related schema file: " . $upload['clean_name']);
            }
            if (!in_array($upload['clean_name'], $existing_files, true)) {
                $existing_files[] = $upload['clean_name'];
            }
        }

        $existing_files = array_values(array_unique($existing_files));

        $update_data = array(
            'schema_files' => array_values($existing_files),
            'updated' => date('U'),
            'updated_by' => $this->api_user ? $this->api_user->id : null
        );

        $this->Metadata_schemas_model->update($schema['id'], $update_data);
        $updated = $this->Metadata_schemas_model->get_by_id($schema['id']);
        $updated = $this->regenerate_schema_template_for_schema($updated, true);
        $manifest = $this->schema_registry->build_schema_file_manifest($updated, $schema_dir);

        return array(
            'schema' => $updated,
            'files' => $manifest,
            'message' => 'Related schema files uploaded successfully.'
        );
    }

    private function handle_delete_related_schema($schema, $schema_dir, $filename)
    {
        $existing_files = !empty($schema['schema_files']) && is_array($schema['schema_files'])
            ? $schema['schema_files']
            : array();

        $is_main = ($filename === $schema['filename']);

        if (!$is_main && !in_array($filename, $existing_files, true)) {
            throw new Exception("Schema file not found: " . $filename);
        }

        $documents = array();
        try {
            $documents = $this->schema_registry->load_schema_documents($schema, $schema_dir);
        } catch (Exception $e) {
            $documents = array();
        }

        if (!empty($documents) && isset($documents[$filename])) {
            unset($documents[$filename]);
        }

        $remaining_related = array_values(array_filter($existing_files, function ($item) use ($filename) {
            return $item !== $filename;
        }));

        $update_data = array(
            'updated' => date('U'),
            'updated_by' => $this->api_user ? $this->api_user->id : null
        );

        if ($is_main) {
            $new_main = count($remaining_related) ? $remaining_related[0] : '';

            if ($new_main !== '') {
                if (!empty($documents)) {
                    $this->schema_registry->validate_schema_documents($documents, $new_main, $schema_dir);
                }
                $update_data['filename'] = $new_main;
                $update_data['schema_files'] = array_values(array_filter($remaining_related, function ($item) use ($new_main) {
                    return $item !== $new_main;
                }));
            } else {
                $update_data['filename'] = '';
                $update_data['schema_files'] = array();
            }
        } else {
            if (!empty($documents) && $schema['filename'] !== '') {
                $this->schema_registry->validate_schema_documents($documents, $schema['filename'], $schema_dir);
            }
            $update_data['schema_files'] = $remaining_related;
        }

        $target = unix_path($schema_dir . '/' . $filename);
        if (is_file($target)) {
            @unlink($target);
        }

        $this->Metadata_schemas_model->update($schema['id'], $update_data);
        $updated = $this->Metadata_schemas_model->get_by_id($schema['id']);
        $updated = $this->regenerate_schema_template_for_schema($updated, true);
        $manifest = $this->schema_registry->build_schema_file_manifest($updated, $schema_dir);

        return array(
            'schema' => $updated,
            'files' => $manifest,
            'message' => 'Schema file deleted successfully.'
        );
    }

    private function sanitize_filename($filename)
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return trim($filename, '_');
    }

    private function parse_metadata_options($raw_value)
    {
        if ($raw_value === null || $raw_value === '' || $raw_value === false) {
            return array();
        }

        $decoded = json_decode($raw_value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid metadata_options JSON: " . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new Exception("metadata_options must be a JSON object.");
        }

        return $decoded;
    }

    public function index_delete($uid = null)
    {
        try {
            $this->is_admin_or_die();
            $this->load->model('Metadata_schemas_model');

            if (empty($uid)) {
                throw new Exception("Schema UID is required.");
            }

            $schema = $this->Metadata_schemas_model->get_by_uid($uid);

            if (!$schema) {
                throw new Exception("Schema not found: " . $uid);
            }

            if (!empty($schema['is_core'])) {
                throw new Exception("Core schemas cannot be deleted.");
            }

            if ($this->schema_in_use_by_templates($uid)) {
                throw new Exception("Schema is in use by templates.");
            }

            if ($this->schema_in_use_by_projects($uid)) {
                throw new Exception("Schema is in use by projects.");
            }

            $schema_path = $this->Metadata_schemas_model->resolve_schema_path($schema);
            if ($schema_path && is_dir($schema_path)) {
                $this->delete_directory($schema_path);
            }

            $this->Metadata_schemas_model->delete($schema['id']);

            $response = array(
                'status' => 'success',
                'message' => 'Schema deleted successfully.'
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
            $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    private function schema_in_use_by_templates($schema_uid)
    {
        $this->db->from('editor_templates');
        $this->db->where('data_type', $schema_uid);
        return $this->db->count_all_results() > 0;
    }

    private function schema_in_use_by_projects($schema_uid)
    {
        $this->db->from('editor_projects');
        $this->db->where('type', $schema_uid);
        return $this->db->count_all_results() > 0;
    }

    private function regenerate_schema_template_for_schema($schema, $force = false)
    {
        if (!$schema || !empty($schema['is_core'])) {
            return $schema;
        }

        $this->load->library('Schema_template_generator');

        $result = $this->schema_template_generator->regenerate($schema, array(
            'force' => $force,
            'updated_by' => $this->api_user ? $this->api_user->id : null
        ));

        if (isset($result['schema']) && is_array($result['schema'])) {
            return $result['schema'];
        }

        return $schema;
    }

    private function delete_directory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), array('.', '..'));

        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}

