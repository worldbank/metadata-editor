<?php

/**
 * 
 * Catalog Connections Model
 * Handles catalog connection operations for publishing metadata
 * 
 */
class Catalog_connections_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 
     * Get all catalog connections for a user
     * 
     * @param int $user_id
     * @return array
     */
    function get_connections($user_id)
    {
        $this->db->select("id,title,url,user_id");
        $this->db->where("user_id", $user_id);
        $result = $this->db->get("editor_catalogs")->result_array();
        return $result;
    }

    /**
     * 
     * Get a single catalog connection by ID
     * 
     * @param int $user_id
     * @param int $id
     * @return array|false
     */
    function get_connection($user_id, $id)
    {
        $this->db->select("*");
        $this->db->where("user_id", $user_id);
        $this->db->where("id", $id);
        $result = $this->db->get("editor_catalogs")->row_array();
        return $result;
    }

    /**
     * 
     * Create a new catalog connection
     * 
     * @param array $options
     * @return int|false
     */
    function create($options = [])
    {
        $fields = array('title', 'url', 'api_key', 'user_id');

        $data_options = [];
        foreach($fields as $req_field) {
            if(!isset($options[$req_field])) {
                throw new Exception("Field is required: " . $req_field);
            }
            if (empty($options[$req_field])) {
                throw new Exception("Field is required: " . $req_field);
            }

            $data_options[$req_field] = $options[$req_field];
        }

        $this->db->insert("editor_catalogs", $data_options);
        return $this->db->insert_id();
    }

    /**
     * 
     * Update an existing catalog connection
     * 
     * @param array $options
     * @return bool
     */
    function update($catalog_id,$options = [])
    {
        $fields = array('title', 'url', 'api_key', 'user_id');

        //if api_key is null, remove it from the options
        if (isset($options['api_key']) && $options['api_key'] === null) {
            unset($options['api_key']);
        }

        $data_options = [];
        foreach($fields as $req_field) {
            if(!isset($options[$req_field])) {
                throw new Exception("Field is required: " . $req_field);
            }
            if (empty($options[$req_field]) && $req_field != 'api_key') {
                throw new Exception("Field is required: " . $req_field);
            }

            //only add field if it is not null
            if (!empty($options[$req_field])) {
                $data_options[$req_field] = $options[$req_field];
            }
        }

        $this->db->where("id", $catalog_id);
        $this->db->where("user_id", $options['user_id']);
        $result=$this->db->update("editor_catalogs", $data_options);
        var_dump($this->db->last_query());
        return $result;
    }

    /**
     * 
     * Delete a catalog connection
     * 
     * @param int $catalog_id
     * @param int $user_id
     * @return bool
     */
    function delete($catalog_id, $user_id)
    {
        $data_options = [
            'user_id' => $user_id,
            'id' => $catalog_id
        ];

        $this->db->where("id", $catalog_id);
        return $this->db->delete("editor_catalogs", $data_options);
    }

    /**
     * 
     * Check if a catalog connection exists and belongs to user
     * 
     * @param int $catalog_id
     * @param int $user_id
     * @return bool
     */
    function exists($catalog_id, $user_id)
    {
        $this->db->select("id");
        $this->db->where("id", $catalog_id);
        $this->db->where("user_id", $user_id);
        $result = $this->db->get("editor_catalogs")->row_array();
        
        return isset($result['id']);
    }

    /**
     * 
     * Get catalog connection by ID (alias for get_connection)
     * 
     * @param int $id
     * @param int $user_id
     * @return array|false
     */
    function get_by_id($id, $user_id)
    {
        return $this->get_connection($user_id, $id);
    }

    /**
     * 
     * Validate catalog connection data
     * 
     * @param array $data
     * @param bool $is_update
     * @return bool
     */
    function validate($data, $is_update = false)
    {
        $required_fields = ['title', 'url', 'user_id'];
        
        if ($is_update) {
            $required_fields[] = 'id';
        }

        foreach($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field is required: " . $field);
            }
        }

        // Validate URL format
        if (isset($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL format");
        }

        return true;
    }

    /**
     * 
     * Get catalog connections count for a user
     * 
     * @param int $user_id
     * @return int
     */
    function count_by_user($user_id)
    {
        $this->db->where("user_id", $user_id);
        return $this->db->count_all_results("editor_catalogs");
    }

    /**
     * 
     * Get all catalog connections (admin function)
     * 
     * @return array
     */
    function get_all()
    {
        $this->db->select("id,title,url,user_id");
        $result = $this->db->get("editor_catalogs")->result_array();
        return $result;
    }
} 