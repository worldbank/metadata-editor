<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH . '/libraries/MY_REST_Controller.php');

/**
 * Global data structure registry API (NADA-aligned DSD catalogue).
 *
 * Base: /api/data_structures
 */
class Data_structures extends MY_REST_Controller {

    /** @var string ACL resource key (see acl_permissions.php). */
    private $registry_resource = 'data_structure';

    public function __construct()
    {
        parent::__construct();
        $this->is_authenticated_or_die();
        $this->api_user = $this->api_user();
        $this->load->model('Data_structure_model');
        $this->load->model('Data_structure_component_model');
        $this->load->model('Codelists_model');
        $this->load->library('Data_structure_util');
    }

    public function _auth_override_check()
    {
        if ($this->session->userdata('user_id')) {
            return true;
        }
        return parent::_auth_override_check();
    }

    /**
     * GET /api/data_structures — list (paginated when page is set).
     */
    public function index_get()
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            $page_raw = $this->input->get('page');
            $use_paged = ($page_raw !== null && $page_raw !== '');
            $flat = filter_var($this->input->get('flat'), FILTER_VALIDATE_BOOLEAN);

            if ($use_paged) {
                $page = max(1, (int) $page_raw);
                $per_page = (int) $this->input->get('per_page');
                if ($per_page < 1) {
                    $per_page = 50;
                }
                $search = $this->input->get('search');
                if ($search === null || $search === false) {
                    $search = $this->input->get('q');
                }
                $search = is_string($search) ? $search : '';
                $status = null;
                $status_raw = $this->input->get('status');
                if ($status_raw !== null && $status_raw !== '') {
                    $status = Data_structure_model::decode_status_filter_value($status_raw);
                }
                $p = $this->Data_structure_model->get_structures_catalog_paged(array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'search' => $search,
                    'flat' => $flat,
                    'status' => $status,
                ));
                $this->set_response(array(
                    'status' => 'success',
                    'data_structures' => Data_structure_model::encode_rows_status_for_api($p['rows']),
                    'total' => $p['total'],
                    'page' => $p['page'],
                    'per_page' => $p['per_page'],
                ), REST_Controller::HTTP_OK);
                return;
            }

            $rows = $flat
                ? $this->Data_structure_model->get_all_structures()
                : $this->Data_structure_model->get_all_structures_collapsed();

            $this->set_response(array(
                'status' => 'success',
                'data_structures' => Data_structure_model::encode_rows_status_for_api($rows),
                'total' => count($rows),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/single/{id}
     */
    public function single_get($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            if (!$id) {
                throw new Exception('Data structure id is required');
            }
            $with = filter_var($this->input->get('with_components'), FILTER_VALIDATE_BOOLEAN);
            $row = $this->Data_structure_model->get_structure_by_id((int) $id, $with);
            if (!$row) {
                throw new Exception('Data structure not found');
            }
            if ($with && !empty($row['components'])) {
                foreach ($row['components'] as $i => $c) {
                    $shaped = $this->data_structure_util->component_to_export_shape($c);
                    $shaped['id'] = (int) $c['id'];
                    $shaped['codelist_id'] = !empty($c['codelist_id']) ? (int) $c['codelist_id'] : null;
                    $row['components'][$i] = $shaped;
                }
            }
            $row = Data_structure_model::encode_row_status_for_api($row);
            $this->set_response(array('status' => 'success', 'data_structure' => $row), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/validate/{id}
     */
    public function validate_get($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            if (!$id) {
                throw new Exception('Data structure id is required');
            }
            $this->load->library('Indicator_dsd_structure_validate');
            $validation = $this->indicator_dsd_structure_validate->validate_structure_id((int) $id);
            $this->set_response(array(
                'status' => 'success',
                'validation' => $validation,
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/by_identity?agency=&name=&version=
     */
    public function by_identity_get()
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            $agency = trim((string) $this->input->get('agency'));
            $name = trim((string) $this->input->get('name'));
            $version = $this->input->get('version');
            if ($agency === '' || $name === '') {
                throw new Exception('agency and name query parameters are required');
            }
            if ($version === null || $version === false) {
                $version = '';
            } else {
                $version = trim((string) $version);
            }
            $row = $this->Data_structure_model->get_structure_by_identity($name, $agency, $version);
            if (!$row) {
                throw new Exception('Data structure not found');
            }
            $this->set_response(array(
                'status' => 'success',
                'data_structure' => Data_structure_model::encode_row_status_for_api($row),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/lookup?segment={id_or_idno}
     */
    public function lookup_get($segment = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            $row = $this->_resolve_structure_row($segment);
            if (!$row) {
                throw new Exception('Data structure not found');
            }
            $with = filter_var($this->input->get('with_components'), FILTER_VALIDATE_BOOLEAN);
            if ($with) {
                $row = $this->Data_structure_model->get_structure_by_id((int) $row['id'], true);
            }
            $this->set_response(array(
                'status' => 'success',
                'data_structure' => Data_structure_model::encode_row_status_for_api($row),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/create
     */
    public function create_post()
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'edit');
            $input = (array) $this->raw_json_input();
            if (empty($input)) {
                throw new Exception('JSON body required');
            }
            $userId = $this->get_api_user_id();
            if ($userId) {
                $input['created_by'] = (int) $userId;
                $input['updated_by'] = (int) $userId;
            }
            $id = $this->Data_structure_model->create_structure($input);
            $row = $this->Data_structure_model->get_structure_by_id($id, false);
            $this->set_response(array(
                'status' => 'success',
                'id' => $id,
                'data_structure' => Data_structure_model::encode_row_status_for_api($row),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/update/{id}
     */
    public function update_post($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'edit');
            if (!$id) {
                throw new Exception('Data structure id is required');
            }
            $input = (array) $this->raw_json_input();
            $existing = $this->Data_structure_model->get_structure_by_id((int) $id, false);
            if (!$existing) {
                throw new Exception('Data structure not found');
            }
            if ((int) $existing['status'] !== Data_structure_model::STATUS_DRAFT) {
                unset($input['agency'], $input['name'], $input['version'], $input['idno']);
            }
            $userId = $this->get_api_user_id();
            if ($userId) {
                $input['updated_by'] = (int) $userId;
            }
            $this->Data_structure_model->update_structure((int) $id, $input);
            $row = $this->Data_structure_model->get_structure_by_id((int) $id, false);
            $this->set_response(array(
                'status' => 'success',
                'data_structure' => Data_structure_model::encode_row_status_for_api($row),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/components — search components across all DSDs.
     */
    public function components_get()
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            $options = $this->_components_catalog_query_options();
            $this->_assert_components_catalog_query($options);
            $p = $this->Data_structure_component_model->search_catalog_paged($options);
            $components = array();
            foreach ($p['rows'] as $row) {
                $components[] = $this->data_structure_util->component_catalog_entry_shape($row);
            }
            $this->set_response(array(
                'status' => 'success',
                'components' => $components,
                'total' => $p['total'],
                'page' => $p['page'],
                'per_page' => $p['per_page'],
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/components/{structure_id}
     */
    public function components_post($structure_id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'edit');
            if (!$structure_id) {
                throw new Exception('Data structure id is required');
            }
            $input = (array) $this->raw_json_input();
            if (empty($input)) {
                throw new Exception('Component data is required');
            }
            $userId = $this->get_api_user_id();
            if ($userId) {
                $input['created_by'] = (int) $userId;
                $input['updated_by'] = (int) $userId;
            }
            $id = $this->Data_structure_component_model->create_component((int) $structure_id, $input);
            $row = $this->Data_structure_component_model->get_component_by_id($id);
            $this->set_response(array(
                'status' => 'success',
                'id' => $id,
                'component' => $this->data_structure_util->component_to_export_shape($row),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/component_update/{id}
     */
    public function component_update_post($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'edit');
            if (!$id) {
                throw new Exception('Component id is required');
            }
            $input = (array) $this->raw_json_input();
            if (empty($input)) {
                throw new Exception('Component data is required');
            }
            $userId = $this->get_api_user_id();
            if ($userId) {
                $input['updated_by'] = (int) $userId;
            }
            $this->Data_structure_component_model->update_component((int) $id, $input);
            $row = $this->Data_structure_component_model->get_component_by_id((int) $id);
            $this->set_response(array(
                'status' => 'success',
                'component' => $this->data_structure_util->component_to_export_shape($row),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/component_delete/{id}
     */
    public function component_delete_post($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'edit');
            if (!$id) {
                throw new Exception('Component id is required');
            }
            $this->Data_structure_component_model->delete_component((int) $id);
            $this->set_response(array('status' => 'success'), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/import_json
     */
    public function import_json_post()
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'import');
            $input = $this->_import_json_read_body();
            if ($input === null || !is_array($input) || empty($input)) {
                throw new Exception('Send JSON body or multipart "file" with a data structure document');
            }
            $overwrite = !empty($input['overwrite']);
            $dry_run = !empty($input['dry_run']);
            $userId = $this->get_api_user_id();

            $this->load->library('Data_structure_json_import');
            if (Data_structure_json_import::payload_mutates_codelists($input, $overwrite)) {
                $this->registry_require_or_die('codelist', 'import');
            }
            $summary = $this->data_structure_json_import->import_from_array($input, array(
                'overwrite' => $overwrite,
                'dry_run' => $dry_run,
                'user_id' => $userId ? (int) $userId : null,
            ));

            if (!empty($summary['data_structure']) && is_array($summary['data_structure'])) {
                $summary['data_structure'] = Data_structure_model::encode_row_status_for_api($summary['data_structure']);
            }

            $this->set_response(array(
                'status' => 'success',
                'summary' => $summary,
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/import_components_csv/{id}
     *
     * Multipart: file + payload (JSON string), or JSON body with upload_id + payload fields.
     * payload: { delimiter, dry_run?, overwrite?, components: [...] }
     */
    public function import_components_csv_post($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'edit');
            if (!$id) {
                throw new Exception('Data structure id is required');
            }
            $structure_id = (int) $id;
            if ($structure_id <= 0) {
                throw new Exception('Data structure id is required');
            }

            $read = $this->_import_components_csv_read_request();
            $payload = $read['payload'];
            $dry_run = !empty($payload['dry_run']);
            $overwrite = !empty($payload['overwrite']);
            $userId = $this->get_api_user_id();

            $this->load->library('Data_structure_csv_import');
            if (Data_structure_csv_import::payload_mutates_codelists($payload, $overwrite)) {
                $this->registry_require_or_die('codelist', 'import');
            }

            $summary = $this->data_structure_csv_import->import_from_csv(
                $structure_id,
                $read['csv_path'],
                $payload,
                array(
                    'dry_run' => $dry_run,
                    'overwrite' => $overwrite,
                    'user_id' => $userId ? (int) $userId : null,
                )
            );

            $this->set_response(array(
                'status' => 'success',
                'summary' => $summary,
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/export/{id_or_idno}
     */
    public function export_get($segment = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            $row = $this->_resolve_structure_row($segment);
            if (!$row) {
                throw new Exception('Data structure not found');
            }
            $doc = $this->data_structure_util->build_export_document((int) $row['id']);
            $export = $this->data_structure_util->sanitize_export_payload($doc);

            $download = $this->input->get('download');
            if ($download === null || $download === '') {
                $download = true;
            } else {
                $download = filter_var($download, FILTER_VALIDATE_BOOLEAN);
            }

            if ($download) {
                $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', isset($row['name']) ? $row['name'] : 'dsd');
                $filename = $safe . '_' . str_replace('.', '', (string) $row['version']) . '.json';
                $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                header('Content-Type: application/json; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: no-store, no-cache');
                echo $json;
                exit();
            }

            $this->set_response(array('status' => 'success', 'export' => $export), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/import_sdmx
     * Multipart file or raw XML; query overwrite_codelists=1, dsd_id=...
     */
    public function import_sdmx_post()
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'import');
            $this->registry_require_or_die('codelist', 'import');
            $overwrite = filter_var($this->input->get('overwrite_codelists'), FILTER_VALIDATE_BOOLEAN);
            if ($this->input->post('overwrite_codelists') === '1') {
                $overwrite = true;
            }
            $dsd_id = trim((string) ($this->input->get('dsd_id') ?: $this->input->post('dsd_id')));

            $xml = '';
            if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $xml = (string) file_get_contents($_FILES['file']['tmp_name']);
            } else {
                $raw = $this->input->raw_input_stream;
                if ($raw !== null && strpos(ltrim($raw), '<') === 0) {
                    $xml = (string) $raw;
                }
            }
            if ($xml === '') {
                throw new Exception('Provide SDMX structure XML as multipart field "file" or as raw XML body.');
            }

            $this->load->library('SDMX/Sdmx_structure_xml_import');
            $result = $this->sdmx_structure_xml_import->import_from_xml_string($xml, array(
                'overwrite_codelists' => $overwrite,
                'dsd_id' => $dsd_id !== '' ? $dsd_id : null,
            ));

            if (!empty($result['data_structure']) && is_array($result['data_structure'])) {
                $result['data_structure'] = Data_structure_model::encode_row_status_for_api($result['data_structure']);
            }

            $this->set_response(array(
                'status' => 'success',
                'result' => $result,
            ), REST_Controller::HTTP_CREATED);
        } catch (Exception $e) {
            $this->set_response(array(
                'status' => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/delete/{id}
     */
    public function delete_post($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'delete');
            if (!$id) {
                throw new Exception('Data structure id is required');
            }
            $this->Data_structure_model->delete_structure((int) $id);
            $this->set_response(array('status' => 'success'), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/duplicate/{id}
     * Optional JSON: { "name": "...", "agency": "...", "version": "..." }
     */
    public function duplicate_post($id = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'edit');
            if (!$id) {
                throw new Exception('Data structure id is required');
            }
            $source = $this->Data_structure_model->get_structure_by_id((int) $id, false);
            if (!$source) {
                throw new Exception('Data structure not found');
            }
            $input = (array) $this->raw_json_input();
            $options = array();
            if (!empty($input['name'])) {
                $options['name'] = trim((string) $input['name']);
            }
            if (!empty($input['agency'])) {
                $options['agency'] = trim((string) $input['agency']);
            }
            if (!empty($input['version'])) {
                $options['version'] = trim((string) $input['version']);
            }
            $userId = $this->get_api_user_id();
            if ($userId) {
                $options['user_id'] = (int) $userId;
            }
            $newId = $this->data_structure_util->duplicate_structure((int) $id, $options);
            $row = $this->Data_structure_model->get_structure_by_id($newId, false);
            $this->set_response(array(
                'status' => 'success',
                'id' => $newId,
                'data_structure' => Data_structure_model::encode_row_status_for_api($row),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/versions/{id_or_idno}
     * All version rows for the family containing this structure row.
     */
    public function versions_get($segment = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            $row = $this->_resolve_structure_row($segment);
            if (!$row) {
                throw new Exception('Data structure not found');
            }
            $rows = $this->Data_structure_model->get_structure_versions(
                (string) $row['name'],
                (string) $row['agency']
            );
            $this->set_response(array(
                'status' => 'success',
                'data_structures' => Data_structure_model::encode_rows_status_for_api($rows),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * POST /api/data_structures/batch_delete
     * JSON body: { "ids": [1, 2, 3] }
     */
    public function batch_delete_post()
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'delete');
            $input = (array) $this->raw_json_input();
            if (empty($input) || !isset($input['ids']) || !is_array($input['ids'])) {
                throw new Exception('JSON body with ids array is required');
            }
            $max_batch = 100;
            $seen = array();
            foreach ($input['ids'] as $v) {
                $n = (int) $v;
                if ($n >= 1) {
                    $seen[$n] = true;
                }
            }
            $ids = array_keys($seen);
            if (count($ids) === 0) {
                throw new Exception('Provide at least one valid structure id');
            }
            if (count($ids) > $max_batch) {
                throw new Exception('At most ' . $max_batch . ' structures can be deleted per request');
            }
            rsort($ids, SORT_NUMERIC);

            $deleted = array();
            $failed = array();
            foreach ($ids as $id) {
                try {
                    $this->Data_structure_model->delete_structure((int) $id);
                    $deleted[] = (int) $id;
                } catch (Exception $e) {
                    $failed[] = array(
                        'id' => (int) $id,
                        'message' => $e->getMessage(),
                    );
                }
            }

            $this->set_response(array(
                'status' => 'success',
                'deleted' => $deleted,
                'failed' => $failed,
                'deleted_count' => count($deleted),
                'failed_count' => count($failed),
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * GET /api/data_structures/projects/{id_or_idno}
     */
    public function projects_get($segment = null)
    {
        try {
            $this->registry_require_or_die($this->registry_resource, 'browse');
            $row = $this->_resolve_structure_row($segment);
            if (!$row) {
                throw new Exception('Data structure not found');
            }
            $page = max(1, (int) $this->input->get('page'));
            $per_page = (int) $this->input->get('per_page');
            if ($per_page < 1) {
                $per_page = 25;
            }
            $p = $this->Data_structure_model->get_structure_projects_paged((int) $row['id'], array(
                'page' => $page,
                'per_page' => $per_page,
            ));
            $this->set_response(array(
                'status' => 'success',
                'projects' => $p['rows'],
                'total' => $p['total'],
                'page' => $p['page'],
                'per_page' => $p['per_page'],
            ), REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param string|null $key
     * @return array|null
     */
    private function _resolve_structure_row($key)
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }
        if (ctype_digit($key)) {
            return $this->Data_structure_model->get_structure_by_id((int) $key, false);
        }
        return $this->Data_structure_model->get_structure_by_idno($key);
    }

    /**
     * @return array
     */
    private function _components_catalog_query_options()
    {
        $search = $this->input->get('search');
        if ($search === null || $search === false) {
            $search = $this->input->get('q');
        }
        $search = is_string($search) ? trim($search) : '';

        $status = null;
        $status_raw = $this->input->get('status');
        if ($status_raw !== null && $status_raw !== '') {
            $status = Data_structure_model::decode_status_filter_value($status_raw);
            if ($status === null) {
                throw new Exception('Invalid status filter');
            }
        }

        $column_type = $this->input->get('column_type');
        $column_type = is_string($column_type) ? trim($column_type) : '';

        $order_by = $this->input->get('order_by');
        $order_by = is_string($order_by) ? trim($order_by) : 'name';

        $order_dir = $this->input->get('order_dir');
        $order_dir = is_string($order_dir) ? trim($order_dir) : 'ASC';

        $structure_id = (int) $this->input->get('structure_id');
        $exclude_structure_id = (int) $this->input->get('exclude_structure_id');

        return array(
            'page' => max(1, (int) $this->input->get('page')),
            'per_page' => (int) $this->input->get('per_page'),
            'search' => $search,
            'name' => is_string($this->input->get('name')) ? trim($this->input->get('name')) : '',
            'column_type' => $column_type,
            'agency' => is_string($this->input->get('agency')) ? trim($this->input->get('agency')) : '',
            'structure_id' => $structure_id > 0 ? $structure_id : null,
            'exclude_structure_id' => $exclude_structure_id > 0 ? $exclude_structure_id : null,
            'status' => $status,
            'has_codelist' => filter_var($this->input->get('has_codelist'), FILTER_VALIDATE_BOOLEAN),
            'order_by' => $order_by,
            'order_dir' => $order_dir,
        );
    }

    /**
     * @param array $options
     * @throws Exception
     */
    private function _assert_components_catalog_query(array $options)
    {
        $search = isset($options['search']) ? trim((string) $options['search']) : '';
        $name = isset($options['name']) ? trim((string) $options['name']) : '';
        $structure_id = !empty($options['structure_id']) ? (int) $options['structure_id'] : 0;

        if ($search === '' && $name === '' && $structure_id <= 0) {
            throw new Exception('Provide search (min 2 characters), name, or structure_id');
        }
        if ($search !== '' && strlen($search) < 2) {
            throw new Exception('search must be at least 2 characters');
        }

        $column_type = isset($options['column_type']) ? trim((string) $options['column_type']) : '';
        if ($column_type !== '' && !in_array($column_type, Data_structure_component_model::$allowed_column_types, true)) {
            throw new Exception('Invalid column_type');
        }

        $order_by = isset($options['order_by']) ? strtolower(trim((string) $options['order_by'])) : 'name';
        if (!in_array($order_by, array('name', 'structure_title', 'updated'), true)) {
            throw new Exception('Invalid order_by');
        }
    }

    /**
     * @return array{ csv_path: string, payload: array }
     * @throws Exception
     */
    private function _import_components_csv_read_request()
    {
        $payload = null;
        $payload_raw = $this->input->post('payload');
        if (is_string($payload_raw) && trim($payload_raw) !== '') {
            $decoded = json_decode($payload_raw, true);
            if (!is_array($decoded)) {
                throw new Exception('Invalid payload JSON.');
            }
            $payload = $decoded;
        } else {
            $raw = $this->input->raw_input_stream;
            if ($raw !== null && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            if ($payload === null) {
                $payload = (array) $this->raw_json_input();
            }
        }
        if (empty($payload) || !is_array($payload)) {
            throw new Exception('Request payload is required.');
        }
        if (empty($payload['components']) || !is_array($payload['components'])) {
            throw new Exception('payload.components is required.');
        }

        $csv_path = null;
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $csv_path = $_FILES['file']['tmp_name'];
        }

        $upload_id = isset($payload['upload_id']) ? trim((string) $payload['upload_id']) : '';
        if ($upload_id !== '') {
            if ($csv_path !== null) {
                throw new Exception('Provide either a file upload or upload_id, not both.');
            }
            $this->load->library('Resumable_upload', null, 'resumable_upload');
            $upload = $this->resumable_upload->get_completed_upload($upload_id);
            if (!$upload || empty($upload['file_path'])) {
                throw new Exception('Completed upload not found for upload_id.');
            }
            $csv_path = $upload['file_path'];
        }

        if ($csv_path === null || $csv_path === '') {
            throw new Exception('CSV file is required (multipart file or completed upload_id).');
        }

        return array(
            'csv_path' => $csv_path,
            'payload' => $payload,
        );
    }

    /**
     * @return array|null
     */
    private function _import_json_read_body()
    {
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $data = file_get_contents($_FILES['file']['tmp_name']);
            if ($data === false || trim($data) === '') {
                return null;
            }
            $j = json_decode($data, true);
            return is_array($j) ? $j : null;
        }
        $raw = $this->input->raw_input_stream;
        if ($raw === null || trim($raw) === '') {
            $input = (array) $this->raw_json_input();
            return !empty($input) ? $input : null;
        }
        $j = json_decode($raw, true);
        return is_array($j) ? $j : null;
    }
}
