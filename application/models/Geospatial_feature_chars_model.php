<?php

/**
 * Geospatial Feature Characteristics Model
 * 
 * Handles operations for geospatial_feature_chars table
 */
class Geospatial_feature_chars_model extends CI_Model {

    private $table = 'geospatial_feature_chars';
    
    private $fields = array(
        'sid',
        'feature_id',
        'name',
        'label',
        'data_type',
        'metadata',
        'created',
        'changed',
        'created_by',
        'changed_by'
    );

    private $required = array(
        'sid',
        'feature_id',
        'name'
    );

    function __construct()
    {
        parent::__construct();
        $this->load->model('Geospatial_features_model');
    }

    /**
     * Get all characteristics
     */
    function select_all()
    {
        $this->db->select('*');
        $this->db->order_by('name', 'ASC');
        $result = $this->db->get($this->table)->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get single characteristic by ID
     */
    function select_single($id)
    {
        $this->db->select('*');
        $this->db->where('id', $id);
        $result = $this->db->get($this->table)->row_array();
        
        // Decode JSON fields
        if ($result && isset($result['metadata']) && !empty($result['metadata'])) {
            $result['metadata'] = json_decode($result['metadata'], true);
        }
        
        return $result;
    }

    /**
     * Get characteristics by feature ID
     */
    function select_by_feature_id($feature_id)
    {
        $this->db->select('*');
        $this->db->where('feature_id', $feature_id);
        $this->db->order_by('name', 'ASC');
        $result = $this->db->get($this->table)->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get characteristics by project ID
     */
    function select_by_project($sid)
    {
        $this->db->select('*');
        $this->db->where('sid', $sid);
        $this->db->order_by('name', 'ASC');
        $result = $this->db->get($this->table)->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }
        
        return $result;
    }

    /**
     * Check if project ID exists
     */
    function project_exists($sid)
    {
        $this->load->model('Editor_model');
        return $this->Editor_model->check_id_exists($sid);
    }

    /**
     * Get characteristic by name within a feature
     */
    function select_by_name_and_feature($name, $feature_id)
    {
        $this->db->select('*');
        $this->db->where('name', $name);
        $this->db->where('feature_id', $feature_id);
        $result = $this->db->get($this->table)->row_array();
        
        // Decode JSON fields
        if ($result && isset($result['metadata']) && !empty($result['metadata'])) {
            $result['metadata'] = json_decode($result['metadata'], true);
        }
        
        return $result;
    }

    /**
     * Check if characteristic ID exists
     */
    function characteristic_id_exists($id)
    {
        $this->db->select('id');
        $this->db->where('id', $id);
        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Check if characteristic name exists within a project
     */
    function characteristic_name_exists($name, $sid, $feature_id, $exclude_id = null)
    {
        $this->db->select('id');
        $this->db->where('name', $name);
        $this->db->where('sid', $sid);
        $this->db->where('feature_id', $feature_id);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Insert new characteristic
     */
    function insert($data)
    {
        $data = array_intersect_key($data, array_flip($this->fields));

        // Validate required fields
        foreach ($this->required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception('Missing required field: ' . $field);
            }
        }

        // Check if project exists
        if (!$this->project_exists($data['sid'])) {
            throw new Exception('Project not found');
        }

        // Check if feature exists
        if (!$this->Geospatial_features_model->feature_id_exists($data['feature_id'])) {
            throw new Exception('Feature not found');
        }

        // Check if name is unique within the project
        if ($this->characteristic_name_exists($data['name'], $data['sid'], $data['feature_id'])) {
            throw new Exception('Characteristic name already exists for this project');
        }

        // Handle JSON encoding for metadata field
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        // Set timestamps
        $data['created'] = date('Y-m-d H:i:s');
        $data['changed'] = date('Y-m-d H:i:s');

        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Update characteristic
     */
    function update($id, $data)
    {
        if (!$this->characteristic_id_exists($id)) {
            throw new Exception('Characteristic not found');
        }

        $data = array_intersect_key($data, array_flip($this->fields));
        
        // Remove ID from update data
        if (isset($data['id'])) {
            unset($data['id']);
        }

        // Validate required fields
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            if (empty($data['name'])) {
                throw new Exception('Name cannot be empty');
            }
        }

        // Check if feature exists (if feature_id is being updated)
        if (isset($data['feature_id'])) {
            if (!$this->Geospatial_features_model->feature_id_exists($data['feature_id'])) {
                throw new Exception('Feature not found');
            }
        }

        // Get current feature_id if not being updated
        if (!isset($data['feature_id'])) {
            $current = $this->select_single($id);
            $data['feature_id'] = $current['feature_id'];
        }

        // Get current sid if not being updated
        if (!isset($data['sid'])) {
            $current = $this->select_single($id);
            $data['sid'] = $current['sid'];
        }

        // Check if project exists (if sid is being updated)
        if (isset($data['sid'])) {
            if (!$this->project_exists($data['sid'])) {
                throw new Exception('Project not found');
            }
        }

        // Check if name is unique within the project
        if (isset($data['name'])) {
            if ($this->characteristic_name_exists($data['name'], $data['sid'], $data['feature_id'], $id)) {
                throw new Exception('Characteristic name already exists for this project');
            }
        }

        // Handle JSON encoding for metadata field
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        // Set timestamp
        $data['changed'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        $this->db->update($this->table, $data);
    }

    /**
     * Delete characteristic
     */
    function delete($id)
    {
        if (!$this->characteristic_id_exists($id)) {
            throw new Exception('Characteristic not found');
        }

        $this->db->where('id', $id);
        $this->db->delete($this->table);
    }

    /**
     * Delete all characteristics for a feature
     */
    function delete_by_feature_id($feature_id)
    {
        $this->db->where('feature_id', $feature_id);
        $this->db->delete($this->table);
    }
    

    /**
     * Get characteristics with feature information
     */
    function select_with_feature_info($feature_id = null)
    {
        $this->db->select('gfc.*, gf.name as feature_name, gf.code as feature_code');
        $this->db->from($this->table . ' gfc');
        $this->db->join('geospatial_features gf', 'gfc.feature_id = gf.id', 'left');
        
        if ($feature_id) {
            $this->db->where('gfc.feature_id', $feature_id);
        }
        
        $this->db->order_by('gf.name', 'ASC');
        $this->db->order_by('gfc.name', 'ASC');
        $result = $this->db->get()->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get characteristics grouped by data type
     */
    function select_by_data_type($data_type)
    {
        $this->db->select('*');
        $this->db->where('data_type', $data_type);
        $this->db->order_by('name', 'ASC');
        $result = $this->db->get($this->table)->result_array();
        
        // Decode JSON fields
        foreach ($result as &$row) {
            if (isset($row['metadata']) && !empty($row['metadata'])) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
        }
        
        return $result;
    }

    /**
     * Get data types used across all characteristics
     */
    function get_data_types()
    {
        $this->db->select('DISTINCT data_type', false);
        $this->db->where('data_type IS NOT NULL');
        $this->db->where('data_type !=', '');
        $this->db->order_by('data_type', 'ASC');
        $result = $this->db->get($this->table)->result_array();
        
        $types = array();
        foreach ($result as $row) {
            $types[] = $row['data_type'];
        }
        return $types;
    }
}
