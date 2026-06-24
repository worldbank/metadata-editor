<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Global DSD registry utilities: project binding and metadata reference.
 */
class Data_structure_util {

    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Data_structure_model');
        $this->ci->load->model('Data_structure_component_model');
        $this->ci->load->model('Editor_project_dsd_model');
        $this->ci->load->model('Indicator_dsd_model');
        $this->ci->load->model('Editor_model');
        $this->ci->load->model('Codelists_model');
    }

    /**
     * Build interchange reference from a data_structures row (no internal ids).
     *
     * @param array $structure
     * @return array
     */
    public function build_reference_from_structure(array $structure)
    {
        return array(
            'idno' => isset($structure['idno']) ? (string) $structure['idno'] : '',
            'agency' => isset($structure['agency']) ? (string) $structure['agency'] : '',
            'name' => isset($structure['name']) ? (string) $structure['name'] : '',
            'version' => isset($structure['version']) ? (string) $structure['version'] : '',
        );
    }

    /**
     * Resolve data_structure_reference (idno or agency+name+version) to structure row.
     *
     * @param array|string $reference
     * @return array|false
     */
    public function resolve_reference($reference)
    {
        if (is_string($reference)) {
            $reference = array('idno' => trim($reference));
        }
        if (!is_array($reference)) {
            return false;
        }

        if (!empty($reference['idno'])) {
            $row = $this->ci->Data_structure_model->get_structure_by_idno(trim((string) $reference['idno']));
            if ($row) {
                return $row;
            }
        }

        $name = isset($reference['name']) ? trim((string) $reference['name']) : '';
        if ($name === '' && isset($reference['id'])) {
            $name = trim((string) $reference['id']);
        }
        if ($name === '') {
            return false;
        }

        $agency = isset($reference['agency']) ? trim((string) $reference['agency']) : null;
        $version = isset($reference['version']) ? trim((string) $reference['version']) : null;

        return $this->ci->Data_structure_model->get_structure_by_identity($name, $agency, $version);
    }

    /**
     * Read data_structure_reference from project metadata.
     *
     * @param int $sid
     * @return array|null
     */
    public function get_project_reference($sid)
    {
        $meta = $this->ci->Editor_model->get_metadata($sid);
        if (!is_array($meta) || empty($meta['data_structure_reference'])) {
            return null;
        }
        $ref = $meta['data_structure_reference'];
        return is_array($ref) ? $ref : array('idno' => (string) $ref);
    }

    /**
     * Effective data_structure_reference for export/publish: metadata when complete,
     * otherwise from editor_project_dsd + global data_structures row.
     *
     * @param int  $sid
     * @param bool $backfill_metadata When true, persist resolved reference on project metadata
     * @return array|null
     */
    public function resolve_project_reference($sid, $backfill_metadata = false)
    {
        $sid = (int) $sid;
        $ref = $this->get_project_reference($sid);
        if (is_array($ref) && !empty($ref['idno'])) {
            return $ref;
        }

        $binding = $this->ci->Editor_project_dsd_model->get_by_sid($sid);
        if (!$binding || empty($binding['data_structure_id'])) {
            return (is_array($ref) && $ref !== array()) ? $ref : null;
        }

        $structure = $this->ci->Data_structure_model->get_structure_by_id((int) $binding['data_structure_id'], false);
        if (!$structure) {
            return (is_array($ref) && $ref !== array()) ? $ref : null;
        }

        $resolved = $this->build_reference_from_structure($structure);
        if (empty($resolved['idno'])) {
            return (is_array($ref) && $ref !== array()) ? $ref : null;
        }

        if (is_array($ref)) {
            foreach (array('uri', 'notes') as $k) {
                if (!empty($ref[$k]) && empty($resolved[$k])) {
                    $resolved[$k] = (string) $ref[$k];
                }
            }
        }

        if ($backfill_metadata) {
            $this->set_project_reference($sid, $resolved, null);
        }

        return $resolved;
    }

    /**
     * Persist data_structure_reference on project metadata; remove inline data_structure.
     *
     * @param int   $sid
     * @param array $reference
     * @param int   $user_id
     */
    public function set_project_reference($sid, array $reference, $user_id = null)
    {
        $project = $this->ci->Editor_model->get_row($sid);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $meta = $this->ci->Editor_model->get_metadata($sid);
        if (!is_array($meta)) {
            $meta = array();
        }

        $ref = array();
        foreach (array('idno', 'agency', 'name', 'version', 'uri', 'notes') as $k) {
            if (isset($reference[$k]) && $reference[$k] !== '' && $reference[$k] !== null) {
                $ref[$k] = (string) $reference[$k];
            }
        }
        $meta['data_structure_reference'] = $ref;
        unset($meta['data_structure']);

        $this->ci->Editor_model->update_metadata_array((int) $sid, $meta, $user_id);
    }

    /**
     * Remove data_structure_reference from project metadata.
     *
     * @param int $sid
     * @param int|null $user_id
     */
    public function clear_project_reference($sid, $user_id = null)
    {
        $project = $this->ci->Editor_model->get_row($sid);
        if (!$project) {
            throw new Exception('Project not found');
        }

        $meta = $this->ci->Editor_model->get_metadata($sid);
        if (!is_array($meta)) {
            return;
        }

        if (!isset($meta['data_structure_reference'])) {
            return;
        }

        unset($meta['data_structure_reference']);

        $type = isset($project['type']) ? $project['type'] : 'indicator';
        if (empty($meta)) {
            $meta = array('type' => $type);
        }

        $this->ci->Editor_model->update_metadata_array((int) $sid, $meta, $user_id);
    }

    /**
     * Detach global DSD from project: unbind registry link, clear metadata reference,
     * and drop published timeseries data.
     *
     * @param int $sid
     * @param int|null $user_id
     * @return array
     * @throws Exception
     */
    public function unbind_project($sid, $user_id = null)
    {
        $sid = (int) $sid;
        if ($sid <= 0) {
            throw new Exception('Invalid project id');
        }

        $binding = $this->ci->Editor_project_dsd_model->get_by_sid($sid);
        $columns = $this->ci->Indicator_dsd_model->select_all($sid, false);

        if (!$binding && empty($columns)) {
            throw new Exception('No data structure is attached to this project');
        }

        if ($binding) {
            $this->ci->Editor_project_dsd_model->unbind($sid);
        }

        $this->ci->Indicator_dsd_model->clear_published_data_tracking($sid);

        $this->clear_project_reference($sid, $user_id);

        $this->ci->load->library('indicator_duckdb_service');
        $drop = $this->ci->indicator_duckdb_service->timeseries_drop($sid);

        $ts_dropped = true;
        $warnings = array();
        if (is_array($drop) && !empty($drop['error'])) {
            $hc = isset($drop['http_code']) ? (int) $drop['http_code'] : 0;
            // 404 = no table yet; 0 = data API unreachable — structure removal still succeeded
            if ($hc !== 404 && $hc !== 0) {
                $ts_dropped = false;
                $warnings[] = isset($drop['message']) ? $drop['message'] : 'Timeseries drop failed';
            } elseif ($hc === 0) {
                $ts_dropped = false;
                $warnings[] = 'Published data could not be dropped (data API unavailable). Remove structure succeeded; restart the data API and remove data manually if needed.';
            }
        }

        return array(
            'sid' => $sid,
            'unbound' => $binding !== null,
            'dsd_columns_deleted' => 0,
            'timeseries_dropped' => $ts_dropped,
            'warnings' => $warnings,
        );
    }

    /**
     * Bind project to global DSD (structure read live from registry; no per-project column table).
     *
     * @param int    $sid
     * @param int    $data_structure_id
     * @param int    $user_id
     * @return array summary
     * @throws Exception
     */
    public function bind_project($sid, $data_structure_id, $user_id, $indicator_id_value = null)
    {
        $sid = (int) $sid;
        $data_structure_id = (int) $data_structure_id;
        $structure = $this->ci->Data_structure_model->get_structure_by_id($data_structure_id, false);
        if (!$structure) {
            throw new Exception('Data structure not found');
        }

        $components = $this->ci->Data_structure_component_model->get_components_by_structure_id($data_structure_id);
        if (empty($components)) {
            throw new Exception('Data structure has no components');
        }

        if ($indicator_id_value === null || trim((string) $indicator_id_value) === '') {
            $indicator_id_value = $this->resolve_default_indicator_id_value($sid);
        } else {
            $indicator_id_value = trim((string) $indicator_id_value);
        }

        $this->ci->Editor_project_dsd_model->bind(
            $sid,
            $data_structure_id,
            $indicator_id_value !== '' ? $indicator_id_value : null
        );
        $this->set_project_reference($sid, $this->build_reference_from_structure($structure), $user_id);
        if ($indicator_id_value !== '') {
            $this->sync_indicator_id_value_to_metadata($sid, $indicator_id_value, $user_id);
        }

        return array(
            'sid' => $sid,
            'data_structure_id' => $data_structure_id,
            'indicator_id_value' => $indicator_id_value !== '' ? $indicator_id_value : null,
            'columns' => count($components),
            'data_structure_reference' => $this->build_reference_from_structure($structure),
        );
    }

    /**
     * Bind by reference payload (idno or agency+name+version).
     *
     * @param int        $sid
     * @param array      $reference
     * @param int        $user_id
     * @return array
     * @throws Exception
     */
    public function bind_project_by_reference($sid, array $reference, $user_id, $indicator_id_value = null)
    {
        $structure = $this->resolve_reference($reference);
        if (!$structure) {
            throw new Exception('Global data structure not found for the given reference');
        }

        return $this->bind_project($sid, (int) $structure['id'], $user_id, $indicator_id_value);
    }

    /**
     * Series idno from project metadata (series_description.idno for indicator/timeseries).
     *
     * @param int $sid
     * @return string
     */
    public function resolve_series_idno($sid)
    {
        $project = $this->ci->Editor_model->get_row($sid);
        if (!$project) {
            return '';
        }

        $type = isset($project['type']) ? $project['type'] : 'timeseries';
        $meta = isset($project['metadata']) && is_array($project['metadata'])
            ? $project['metadata']
            : $this->ci->Editor_model->get_metadata($sid);
        if (!is_array($meta)) {
            return '';
        }

        $from_meta = $this->ci->Editor_model->get_project_metadata_field($type, 'idno', $meta);
        if ($from_meta !== false && $from_meta !== null && trim((string) $from_meta) !== '') {
            return trim((string) $from_meta);
        }

        if (!empty($meta['series_description']['idno'])) {
            return trim((string) $meta['series_description']['idno']);
        }

        return '';
    }

    /**
     * Default indicator ID value for import (series_description.idno from metadata).
     *
     * @param int $sid
     * @return string
     */
    public function resolve_default_indicator_id_value($sid)
    {
        return $this->resolve_series_idno($sid);
    }

    /**
     * Persist indicator_id_value on project metadata.
     *
     * @param int        $sid
     * @param string     $value
     * @param int|null   $user_id
     */
    public function sync_indicator_id_value_to_metadata($sid, $value, $user_id = null)
    {
        $meta = $this->ci->Editor_model->get_metadata($sid);
        if (!is_array($meta)) {
            $meta = array();
        }
        $meta['indicator_id_value'] = trim((string) $value);
        $this->ci->Editor_model->update_metadata_array((int) $sid, $meta, $user_id);
    }

    /**
     * Update bound indicator ID value for a project.
     *
     * @param int    $sid
     * @param string $indicator_id_value
     * @param int    $user_id
     * @return array
     * @throws Exception
     */
    public function update_project_indicator_id_value($sid, $indicator_id_value, $user_id = null)
    {
        $sid = (int) $sid;
        $binding = $this->ci->Editor_project_dsd_model->get_by_sid($sid);
        if (!$binding) {
            throw new Exception('No data structure is attached to this project');
        }

        $value = trim((string) $indicator_id_value);
        if ($value === '') {
            throw new Exception('indicator_id_value cannot be empty');
        }

        $this->ci->Editor_project_dsd_model->update_indicator_id_value($sid, $value);
        $this->sync_indicator_id_value_to_metadata($sid, $value, $user_id);

        return array(
            'sid' => $sid,
            'indicator_id_value' => $value,
        );
    }

    /**
     * Update constant series FREQ for projects without a periodicity column.
     *
     * @param int    $sid
     * @param string $implied_freq_code SDMX FREQ code or empty to clear
     * @param int    $user_id
     * @return array
     * @throws Exception
     */
    public function update_project_implied_freq_code($sid, $implied_freq_code, $user_id = null)
    {
        $sid = (int) $sid;
        $binding = $this->ci->Editor_project_dsd_model->get_by_sid($sid);
        if (!$binding) {
            throw new Exception('No data structure is attached to this project');
        }

        $this->ci->load->library('SDMX/Sdmx_time_period');
        $value = trim((string) $implied_freq_code);
        if ($value !== '' && !$this->ci->sdmx_time_period->is_allowed_freq_code($value)) {
            throw new Exception("FREQ '{$value}' is not a configured SDMX FREQ code.");
        }

        $this->ci->Editor_project_dsd_model->update_implied_freq_code($sid, $value);

        return array(
            'sid' => $sid,
            'implied_freq_code' => $value !== '' ? $value : null,
        );
    }

    /**
     * @param array $component data_structure_components row
     * @param int   $sort_order
     * @param int   $user_id
     * @return array
     */
    public function component_to_indicator_dsd_row(array $component, $sort_order, $user_id)
    {
        $meta = array();
        if (!empty($component['metadata'])) {
            $decoded = is_string($component['metadata'])
                ? json_decode($component['metadata'], true)
                : $component['metadata'];
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $row = array(
            'name' => $component['name'],
            'label' => isset($component['label']) ? $component['label'] : null,
            'description' => isset($component['description']) ? $component['description'] : null,
            'data_type' => isset($component['data_type']) ? $component['data_type'] : null,
            'column_type' => $component['column_type'],
            'sort_order' => isset($component['sort_order']) ? (int) $component['sort_order'] : $sort_order,
            'created_by' => $user_id,
            'changed_by' => $user_id,
            'codelist_type' => 'none',
            'global_codelist_id' => null,
            'code_list' => null,
            'code_list_reference' => null,
        );

        if (!empty($meta)) {
            $row['metadata'] = $meta;
        }

        $cid = isset($component['codelist_id']) ? (int) $component['codelist_id'] : 0;
        if ($cid > 0) {
            $cl = $this->ci->Codelists_model->get_by_id($cid);
            if ($cl) {
                $row['codelist_type'] = 'global';
                $row['global_codelist_id'] = $cid;
                $row['code_list_reference'] = array(
                    'idno' => isset($cl['idno']) ? (string) $cl['idno'] : '',
                    'agency' => isset($cl['agency']) ? (string) $cl['agency'] : '',
                    'name' => isset($cl['name']) ? (string) $cl['name'] : '',
                    'version' => isset($cl['version']) ? (string) $cl['version'] : '',
                );
            }
        }

        return $row;
    }

    /**
     * Export payload for interchange.
     *
     * Options:
     * - inline_codelists (bool): embed codelist + items on dimension/geography components (default false = codelist_reference only)
     * - nest_components (bool): place components under data_structure for import_json (default false = legacy root-level components)
     *
     * @param int   $data_structure_id
     * @param array $options
     * @return array
     */
    public function build_export_document($data_structure_id, array $options = array())
    {
        $inlineCodelists = !empty($options['inline_codelists']);
        $nestComponents = !empty($options['nest_components']);

        $row = $this->ci->Data_structure_model->get_structure_by_id((int) $data_structure_id, true);
        if (!$row) {
            throw new Exception('Data structure not found');
        }

        $components = isset($row['components']) ? $row['components'] : array();
        unset($row['components']);
        $row = Data_structure_model::encode_row_status_for_api($row);

        $out_components = array();
        foreach ($components as $comp) {
            $out_components[] = $this->component_to_export_shape($comp, $inlineCodelists);
        }

        if ($nestComponents) {
            $row['components'] = $out_components;
            return array(
                'data_structure' => $row,
            );
        }

        return array(
            'data_structure' => $row,
            'components' => $out_components,
        );
    }

    /**
     * NADA import_json expects components under data_structure; ME export uses a root-level components array.
     *
     * @param int  $data_structure_id
     * @param bool $overwrite
     * @return array Payload for POST .../api/admin/data_structures/import_json
     */
    public function build_nada_import_payload($data_structure_id, $overwrite = false)
    {
        $doc = $this->build_export_document((int) $data_structure_id);
        $export = $this->sanitize_export_payload($doc);

        return $this->normalize_export_for_nada_import_json($export, $overwrite);
    }

    /**
     * @param array $export Document from build_export_document + sanitize_export_payload
     * @param bool  $overwrite
     * @return array
     */
    public function normalize_export_for_nada_import_json(array $export, $overwrite = false)
    {
        if (empty($export['data_structure']) || !is_array($export['data_structure'])) {
            throw new Exception('Export document missing data_structure');
        }

        $payload = $export;
        $components = array();

        if (isset($payload['components']) && is_array($payload['components'])) {
            $components = $payload['components'];
            unset($payload['components']);
        } elseif (isset($payload['data_structure']['components']) && is_array($payload['data_structure']['components'])) {
            $components = $payload['data_structure']['components'];
        }

        $payload['data_structure']['components'] = $components;
        $payload['overwrite'] = $overwrite ? true : false;

        return $payload;
    }

    /**
     * Pre-sync ME codelists referenced by a DSD to NADA before import_json (reference-only DSD payload).
     *
     * @param Nada_catalog_client $client
     * @param int                 $data_structure_id
     * @param bool                $overwrite When true, re-import items for codelists already on NADA
     * @return array{synced:array,skipped:array}
     */
    public function sync_dsd_codelists_to_nada(Nada_catalog_client $client, $data_structure_id, $overwrite = false)
    {
        $row = $this->ci->Data_structure_model->get_structure_by_id((int) $data_structure_id, true);
        if (!$row) {
            throw new Exception('Data structure not found');
        }

        $components = isset($row['components']) && is_array($row['components']) ? $row['components'] : array();
        $codelistIds = array();

        foreach ($components as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $columnType = isset($comp['column_type']) ? trim((string) $comp['column_type']) : '';
            if (!in_array($columnType, array('dimension', 'geography'), true)) {
                continue;
            }
            $cid = isset($comp['codelist_id']) ? (int) $comp['codelist_id'] : 0;
            if ($cid > 0) {
                $codelistIds[$cid] = true;
            }
        }

        $summary = array(
            'synced' => array(),
            'skipped' => array(),
        );

        foreach (array_keys($codelistIds) as $codelistId) {
            $doc = $this->ci->Codelists_model->export_nada_json_document((int) $codelistId);
            $idno = isset($doc['idno']) ? trim((string) $doc['idno']) : '';
            if ($idno === '') {
                continue;
            }

            $existsOnNada = $this->nada_codelist_exists($client, $idno);
            if ($existsOnNada && !$overwrite) {
                $summary['skipped'][] = array(
                    'idno' => $idno,
                    'reason' => 'already_on_nada',
                );
                continue;
            }

            $response = $client->post_json('admin/codelists/import_json', array(
                'codelist' => $doc,
                'overwrite' => ($existsOnNada && $overwrite) ? true : false,
            ));

            $summary['synced'][] = array(
                'idno' => $idno,
                'existed' => $existsOnNada,
                'overwrite' => ($existsOnNada && $overwrite),
                'response' => $response,
            );
        }

        return $summary;
    }

    /**
     * @param Nada_catalog_client $client
     * @param string              $idno
     * @return bool
     */
    public function nada_codelist_exists(Nada_catalog_client $client, $idno)
    {
        $idno = trim((string) $idno);
        if ($idno === '') {
            return false;
        }

        try {
            $client->get('admin/codelists/by_idno/' . rawurlencode($idno));
            return true;
        } catch (ApiRequestException $e) {
            $details = $e->getDetails();
            $http = isset($details['status']) ? (int) $details['status'] : 0;
            if ($http === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Build inline codelist binding (idno, name, agency, version, items[]) for DSD JSON export/import.
     *
     * @param int $codelist_pk
     * @return array
     */
    public function codelist_to_inline_binding($codelist_pk)
    {
        $doc = $this->ci->Codelists_model->export_nada_json_document((int) $codelist_pk);

        $binding = array(
            'idno' => isset($doc['idno']) ? (string) $doc['idno'] : '',
            'name' => isset($doc['name']) ? (string) $doc['name'] : '',
            'agency' => isset($doc['agency']) ? (string) $doc['agency'] : '',
            'version' => isset($doc['version']) ? (string) $doc['version'] : '',
        );
        if (isset($doc['description']) && trim((string) $doc['description']) !== '') {
            $binding['description'] = trim((string) $doc['description']);
        }

        $items = array();
        $rawItems = isset($doc['items']) && is_array($doc['items']) ? $doc['items'] : array();
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = isset($item['code']) ? trim((string) $item['code']) : '';
            if ($code === '') {
                continue;
            }
            $entry = array('code' => $code);
            $label = null;
            if (isset($item['label']) && $item['label'] !== '' && $item['label'] !== null) {
                $label = is_string($item['label']) ? trim($item['label']) : (string) $item['label'];
            } elseif (isset($item['title']) && $item['title'] !== '' && $item['title'] !== null) {
                $label = is_string($item['title']) ? trim($item['title']) : (string) $item['title'];
            }
            if ($label !== null && $label !== '') {
                $entry['label'] = $label;
            }
            if (isset($item['description']) && trim((string) $item['description']) !== '') {
                $entry['description'] = trim((string) $item['description']);
            }
            if (isset($item['sort_order']) && $item['sort_order'] !== null && $item['sort_order'] !== '') {
                $entry['sort_order'] = (int) $item['sort_order'];
            }
            $items[] = $entry;
        }
        $binding['items'] = $items;

        return $binding;
    }

    /**
     * @param array $component DB row
     * @param bool  $inline_codelists When true, embed codelist + items (no codelist_reference) on dimension/geography
     * @return array
     */
    public function component_to_export_shape(array $component, $inline_codelists = false)
    {
        $out = array(
            'name' => $component['name'],
            'label' => isset($component['label']) ? $component['label'] : null,
            'description' => isset($component['description']) ? $component['description'] : null,
            'data_type' => isset($component['data_type']) ? $component['data_type'] : null,
            'column_type' => $component['column_type'],
            'sort_order' => isset($component['sort_order']) ? (int) $component['sort_order'] : 0,
        );

        if (!empty($component['metadata'])) {
            $meta = is_string($component['metadata'])
                ? json_decode($component['metadata'], true)
                : $component['metadata'];
            if (is_array($meta)) {
                foreach ($meta as $k => $v) {
                    $out[$k] = $v;
                }
            }
        }

        $cid = isset($component['codelist_id']) ? (int) $component['codelist_id'] : 0;
        $columnType = isset($component['column_type']) ? trim((string) $component['column_type']) : '';
        $needsCodelist = in_array($columnType, array('dimension', 'geography'), true);
        if ($cid > 0 && $needsCodelist) {
            if ($inline_codelists) {
                $out['codelist'] = $this->codelist_to_inline_binding($cid);
            } else {
                $cl = $this->ci->Codelists_model->get_by_id($cid);
                if ($cl) {
                    $out['codelist_reference'] = array(
                        'idno' => isset($cl['idno']) ? (string) $cl['idno'] : '',
                        'agency' => isset($cl['agency']) ? (string) $cl['agency'] : '',
                        'name' => isset($cl['name']) ? (string) $cl['name'] : '',
                        'version' => isset($cl['version']) ? (string) $cl['version'] : '',
                    );
                }
            }
        }

        return $out;
    }

    /**
     * Catalogue search row: export shape + ids + parent structure summary.
     *
     * @param array $row Joined row from Data_structure_component_model::search_catalog_paged()
     * @return array
     */
    public function component_catalog_entry_shape(array $row)
    {
        $componentRow = array(
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'name' => isset($row['name']) ? $row['name'] : '',
            'label' => isset($row['label']) ? $row['label'] : null,
            'description' => isset($row['description']) ? $row['description'] : null,
            'data_type' => isset($row['data_type']) ? $row['data_type'] : null,
            'column_type' => isset($row['column_type']) ? $row['column_type'] : '',
            'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
            'codelist_id' => !empty($row['codelist_id']) ? (int) $row['codelist_id'] : null,
            'metadata' => isset($row['metadata']) ? $row['metadata'] : null,
        );

        $out = $this->component_to_export_shape($componentRow);
        $out['id'] = (int) $row['id'];
        $out['codelist_id'] = $componentRow['codelist_id'];

        $this->ci->load->model('Data_structure_model');
        $statusCode = isset($row['structure_status']) ? (int) $row['structure_status'] : 0;
        $out['data_structure'] = array(
            'id' => (int) $row['data_structure_id'],
            'agency' => isset($row['structure_agency']) ? (string) $row['structure_agency'] : '',
            'name' => isset($row['structure_name']) ? (string) $row['structure_name'] : '',
            'version' => isset($row['structure_version']) ? (string) $row['structure_version'] : '',
            'title' => isset($row['structure_title']) ? $row['structure_title'] : null,
            'idno' => isset($row['structure_idno']) ? $row['structure_idno'] : null,
            'status' => Data_structure_model::status_code_to_slug($statusCode),
        );

        return $out;
    }

    /**
     * Duplicate a data structure and its components as a new draft family.
     * Codelist bindings are reused (global catalogue references unchanged).
     *
     * @param int   $source_id
     * @param array $options Optional: name, agency, version, user_id
     * @return int New data structure id
     * @throws Exception
     */
    public function duplicate_structure($source_id, array $options = array())
    {
        $source_id = (int) $source_id;
        $source = $this->ci->Data_structure_model->get_structure_by_id($source_id, true);
        if (!$source) {
            throw new Exception('Data structure not found');
        }

        $agency = array_key_exists('agency', $options) && trim((string) $options['agency']) !== ''
            ? trim((string) $options['agency'])
            : (string) $source['agency'];
        $version = array_key_exists('version', $options) && trim((string) $options['version']) !== ''
            ? trim((string) $options['version'])
            : Data_structure_model::DEFAULT_VERSION;
        $baseName = array_key_exists('name', $options) && trim((string) $options['name']) !== ''
            ? trim((string) $options['name'])
            : (string) $source['name'] . '_copy';
        $name = $this->_unique_duplicate_identity_name($agency, (string) $source['name'], $baseName, $version);

        $title = isset($source['title']) ? $source['title'] : null;
        if ($title !== null && trim((string) $title) !== '') {
            $title = trim((string) $title) . ' (copy)';
        }

        $userId = isset($options['user_id']) ? (int) $options['user_id'] : null;
        if ($userId <= 0) {
            $userId = null;
        }

        $this->ci->db->trans_begin();
        try {
            $createData = array(
                'agency'      => $agency,
                'name'        => $name,
                'version'     => $version,
                'title'       => $title,
                'description' => isset($source['description']) ? $source['description'] : null,
                'notes'       => isset($source['notes']) ? $source['notes'] : null,
                'status'      => 'draft',
                'metadata'    => isset($source['metadata']) ? $source['metadata'] : null,
            );
            if ($userId) {
                $createData['created_by'] = $userId;
                $createData['updated_by'] = $userId;
            }

            $newId = (int) $this->ci->Data_structure_model->create_structure($createData);
            $components = isset($source['components']) && is_array($source['components'])
                ? $source['components']
                : array();

            foreach ($components as $comp) {
                if (!is_array($comp)) {
                    continue;
                }
                $row = array(
                    'name'        => $comp['name'],
                    'label'       => isset($comp['label']) ? $comp['label'] : null,
                    'description' => isset($comp['description']) ? $comp['description'] : null,
                    'data_type'   => isset($comp['data_type']) ? $comp['data_type'] : null,
                    'column_type' => $comp['column_type'],
                    'sort_order'  => isset($comp['sort_order']) ? (int) $comp['sort_order'] : 0,
                    'codelist_id' => !empty($comp['codelist_id']) ? (int) $comp['codelist_id'] : null,
                    'metadata'    => isset($comp['metadata']) ? $comp['metadata'] : null,
                );
                if ($userId) {
                    $row['created_by'] = $userId;
                    $row['updated_by'] = $userId;
                }
                $this->ci->Data_structure_component_model->create_component($newId, $row);
            }

            if ($this->ci->db->trans_status() === false) {
                throw new Exception('Failed to duplicate data structure.');
            }
            $this->ci->db->trans_commit();
            return $newId;
        } catch (Exception $e) {
            $this->ci->db->trans_rollback();
            throw $e;
        }
    }

    /**
     * @param string $agency
     * @param string $sourceName Original structure name (for suffix pattern).
     * @param string $baseName   First candidate name.
     * @param string $version
     * @return string
     */
    private function _unique_duplicate_identity_name($agency, $sourceName, $baseName, $version)
    {
        $name = $baseName;
        $counter = 0;
        while ($this->ci->Data_structure_model->get_structure_by_identity($name, $agency, $version)) {
            $counter++;
            $name = $sourceName . '_copy_' . $counter;
        }
        return $name;
    }

    /**
     * Strip internal ids from export document (NADA interchange rules).
     *
     * @param mixed $value
     * @return mixed
     */
    public function sanitize_export_payload($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $is_list = array_keys($value) === range(0, count($value) - 1);
        if ($is_list) {
            $out = array();
            foreach ($value as $item) {
                $out[] = $this->sanitize_export_payload($item);
            }
            return $out;
        }
        $blocked = array(
            'content_hash' => true,
            'metadata' => true,
            'created_by' => true,
            'updated_by' => true,
            'pid' => true,
        );
        $out = array();
        foreach ($value as $k => $v) {
            $key = (string) $k;
            if ($key === 'id' || isset($blocked[$key]) || preg_match('/_id$/', $key)) {
                continue;
            }
            $out[$key] = $this->sanitize_export_payload($v);
        }
        return $out;
    }

}
