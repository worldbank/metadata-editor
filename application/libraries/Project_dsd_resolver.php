<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Resolve indicator project DSD columns from global registry (editor_project_dsd + data_structure_components).
 */
class Project_dsd_resolver {

    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('Editor_project_dsd_model');
        $this->ci->load->model('Data_structure_component_model');
        $this->ci->load->model('Codelists_model');
        $this->ci->load->library('Data_structure_util');
    }

    /**
     * @param int $sid
     * @return bool
     */
    public function is_bound($sid)
    {
        return $this->ci->Editor_project_dsd_model->get_by_sid((int) $sid) !== null;
    }

    /**
     * @param int $sid
     * @throws Exception
     */
    public function require_bound($sid)
    {
        if (!$this->is_bound($sid)) {
            throw new Exception('Attach a global data structure to this project first.');
        }
        $binding = $this->get_binding($sid);
        $components = $this->ci->Data_structure_component_model->get_components_by_structure_id(
            (int) $binding['data_structure_id']
        );
        if (empty($components)) {
            throw new Exception('The bound data structure has no components.');
        }
    }

    /**
     * @param int $sid
     * @return array|null editor_project_dsd row
     */
    public function get_binding($sid)
    {
        return $this->ci->Editor_project_dsd_model->get_by_sid((int) $sid);
    }

    /**
     * Columns for a bound project (same shape as Indicator_dsd_model::select_all).
     *
     * @param int  $sid
     * @param bool $metadata_detailed
     * @param int  $offset
     * @param int|null $limit
     * @return array
     */
    public function get_columns($sid, $metadata_detailed = false, $offset = 0, $limit = null)
    {
        $binding = $this->get_binding($sid);
        if (!$binding || empty($binding['data_structure_id'])) {
            return array();
        }

        $components = $this->ci->Data_structure_component_model->get_components_by_structure_id(
            (int) $binding['data_structure_id']
        );
        if (empty($components)) {
            return array();
        }

        $sid = (int) $sid;
        $rows = array();
        $sort = 0;
        foreach ($components as $comp) {
            $row = $this->ci->data_structure_util->component_to_indicator_dsd_row($comp, $sort++, null);
            $comp_id = (int) $comp['id'];
            $row['id'] = $comp_id;
            $row['sid'] = $sid;
            $row['component_id'] = $comp_id;

            if (!$metadata_detailed) {
                unset($row['description'], $row['metadata'], $row['created_by'], $row['changed_by']);
            }

            $rows[] = $row;
        }

        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, (int) $offset, (int) $limit);
        } elseif ($offset > 0) {
            $rows = array_slice($rows, (int) $offset);
        }

        return $rows;
    }

    /**
     * Single column by global component id.
     *
     * @param int $sid
     * @param int $component_id
     * @param bool $metadata_detailed
     * @return array|false
     */
    public function get_column_by_component_id($sid, $component_id, $metadata_detailed = true)
    {
        $columns = $this->get_columns($sid, $metadata_detailed);
        $component_id = (int) $component_id;
        foreach ($columns as $col) {
            if ((int) $col['id'] === $component_id) {
                return $col;
            }
        }

        return false;
    }
}
