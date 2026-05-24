<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared indicator DSD structure validation (registry components and bound project columns).
 */
class Indicator_dsd_structure_validate {

    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    /**
     * Validate column rows (indicator_dsd shape: name, column_type, metadata, codelist_*).
     *
     * @param array $columns
     * @param array $options optional: include_role_checklist (bool)
     * @return array valid, errors, warnings, summary, roles?, columns
     */
    public function validate_columns(array $columns, array $options = array())
    {
        $errors = array();
        $warnings = array();

        $by_type = array();
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $type = isset($column['column_type']) ? (string) $column['column_type'] : '';
            if ($type === '') {
                continue;
            }
            if (!isset($by_type[$type])) {
                $by_type[$type] = array();
            }
            $by_type[$type][] = $column;
        }

        $required_for_data = array('indicator_id', 'time_period', 'observation_value');
        foreach ($required_for_data as $type) {
            $count = isset($by_type[$type]) ? count($by_type[$type]) : 0;
            if ($count === 0) {
                $errors[] = "Required column type '{$type}' is missing. Exactly one column of this type is required for data import.";
            } elseif ($count > 1) {
                $column_names = array_map(function ($col) {
                    return isset($col['name']) ? $col['name'] : '?';
                }, $by_type[$type]);
                $errors[] = "Column type '{$type}' has {$count} columns (max allowed: 1). Found: " . implode(', ', $column_names);
            }
        }

        $geo_count = isset($by_type['geography']) ? count($by_type['geography']) : 0;
        if ($geo_count === 0) {
            $warnings[] = "Recommended column type 'geography' is not set. Data import may still work; mapping and charts may be limited.";
        } elseif ($geo_count > 1) {
            $column_names = array_map(function ($col) {
                return isset($col['name']) ? $col['name'] : '?';
            }, $by_type['geography']);
            $errors[] = "Column type 'geography' has {$geo_count} columns (max allowed: 1). Found: " . implode(', ', $column_names);
        }

        $optional_single_types = array('periodicity', 'indicator_name');
        foreach ($optional_single_types as $type) {
            $count = isset($by_type[$type]) ? count($by_type[$type]) : 0;
            if ($count > 1) {
                $column_names = array_map(function ($col) {
                    return isset($col['name']) ? $col['name'] : '?';
                }, $by_type[$type]);
                $errors[] = "Column type '{$type}' has {$count} columns (max allowed: 1). Found: " . implode(', ', $column_names);
            }
        }

        $valid_types = array(
            'dimension', 'time_period', 'measure', 'attribute',
            'indicator_id', 'indicator_name', 'annotation',
            'geography', 'observation_value', 'periodicity',
        );

        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $type = isset($column['column_type']) ? $column['column_type'] : '';
            $name = isset($column['name']) ? $column['name'] : '?';
            if ($type !== '' && !in_array($type, $valid_types, true)) {
                $errors[] = "Column '{$name}' has invalid column_type '{$type}'";
            }
            if (!empty($column['name']) && $this->is_reserved_system_column_name($column['name'])) {
                $errors[] = "Column '{$column['name']}' uses a reserved name (cannot start with '_').";
            }
        }

        $seen_names = array();
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $name = isset($column['name']) ? trim((string) $column['name']) : '';
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            if (isset($seen_names[$key])) {
                $errors[] = "Duplicate column name '{$name}' (case-insensitive match with '{$seen_names[$key]}').";
            } else {
                $seen_names[$key] = $name;
            }
        }

        foreach ($this->collect_global_codelist_definition_errors($columns) as $msg) {
            $errors[] = $msg;
        }

        $result = array(
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => array(
                'total_columns' => count($columns),
                'by_type' => array_map(function ($cols) {
                    return count($cols);
                }, $by_type),
            ),
            'columns' => $columns,
        );

        if (!empty($options['include_role_checklist'])) {
            $result['roles'] = $this->build_role_checklist($by_type);
        }

        return $result;
    }

    /**
     * @param array $by_type
     * @return array
     */
    public function build_role_checklist(array $by_type)
    {
        $roles = array();

        foreach (array(
            array('type' => 'indicator_id', 'label' => 'Indicator ID', 'tier' => 'required'),
            array('type' => 'time_period', 'label' => 'Time period', 'tier' => 'required'),
            array('type' => 'observation_value', 'label' => 'Observation value', 'tier' => 'required'),
            array('type' => 'geography', 'label' => 'Geography', 'tier' => 'recommended'),
        ) as $def) {
            $count = isset($by_type[$def['type']]) ? count($by_type[$def['type']]) : 0;
            $roles[] = array(
                'type' => $def['type'],
                'label' => $def['label'],
                'tier' => $def['tier'],
                'count' => $count,
                'present' => $count === 1,
                'issue' => $count > 1 ? 'duplicate' : ($count === 0 && $def['tier'] === 'required' ? 'missing' : ($count === 0 ? 'missing_recommended' : null)),
            );
        }

        return $roles;
    }

    /**
     * @param int $structure_id
     * @return array
     */
    public function validate_structure_id($structure_id)
    {
        $this->ci->load->model('Data_structure_component_model');
        $this->ci->load->library('Data_structure_util');

        $components = $this->ci->Data_structure_component_model->get_components_by_structure_id((int) $structure_id);
        $columns = array();
        $sort = 0;
        foreach ($components as $comp) {
            $columns[] = $this->ci->data_structure_util->component_to_indicator_dsd_row($comp, $sort++, null);
        }

        return $this->validate_columns($columns, array('include_role_checklist' => true));
    }

    /**
     * @param string $name
     * @return bool
     */
    public function is_reserved_system_column_name($name)
    {
        $name = (string) $name;
        return $name !== '' && $name[0] === '_';
    }

    /**
     * Global registry codelist checks (no project-local codelists).
     *
     * @param array $columns
     * @return array
     */
    private function collect_global_codelist_definition_errors(array $columns)
    {
        $errors = array();
        $this->ci->load->model('Codelists_model');

        foreach ($columns as $column) {
            $label = isset($column['name']) && $column['name'] !== '' ? $column['name'] : '?';
            $ctype = isset($column['codelist_type']) ? $column['codelist_type'] : 'none';
            if ($ctype !== 'global') {
                continue;
            }
            $pk = isset($column['global_codelist_id']) ? (int) $column['global_codelist_id'] : 0;
            if ($pk <= 0) {
                $errors[] = "Column '{$label}': global vocabulary is selected but no registry codelist is linked.";
                continue;
            }
            $cl = $this->ci->Codelists_model->get_by_id($pk);
            if (!$cl) {
                $errors[] = "Column '{$label}': global vocabulary registry id {$pk} was not found.";
                continue;
            }
            $codes = $this->ci->Codelists_model->get_codes((int) $cl['id'], null, false);
            if (!is_array($codes) || count($codes) === 0) {
                $errors[] = "Column '{$label}': linked global codelist has no codes.";
            }
        }

        return $errors;
    }
}
