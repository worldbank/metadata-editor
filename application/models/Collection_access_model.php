<?php


/*
CREATE TABLE editor_collection_access(  
    id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    collection_id int NOT NULL,
    user_id int NOT NULL,
    permissions varchar(100) not null    
);
*/

/**
 * 
 * Collections access model
 * 
 */
class Collection_access_model extends CI_Model {


    private $fields=array('id','collection_id','user_id','permissions');
    private $permissions=array(
        'view'=>'View',
        'edit'=>'Edit',
        //'delete'=>'Delete',
        'admin'=>'Admin'
    );

    function __construct()
    {
        parent::__construct();
    }


    /**
     * 
     * Get list of users with access to a collection
     * 
     * 
     */
    function select_all($collection_id)
    {
        $this->db->select('editor_collection_access.*,users.username,users.email');
        $this->db->join('users','users.id=editor_collection_access.user_id');
        $this->db->where('collection_id',$collection_id);
        return $this->db->get('editor_collection_access')->result_array();
    }

    function delete($id)
    {
        $this->db->where('id',$id);
        $this->db->delete('editor_collection_access');
    }

    function delete_user($collection_id,$user_id)
    {
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);
        $this->db->delete('editor_collection_access');
    }

    function permission_exists($collection_id,$user_id)
    {
        $this->db->select('id');
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);        
        return $this->db->count_all_results('editor_collection_access');
    }

    function insert($data)
    {
        if ($this->permission_exists($data['collection_id'],$data['user_id'])){
            throw new Exception("User already has access to this collection");
        }

        $data=array_intersect_key($data,array_flip($this->fields));
        
        if (isset($data['id'])){
            unset($data['id']);
        }

        $this->db->insert('editor_collection_access',$data);
        return $this->db->insert_id();
    }

    function update($id,$data)
    {
        $data=array_intersect_key($data,array_flip($this->fields));
        
        if (isset($data['id'])){
            unset($data['id']);
        }

        $this->db->where('id',$id);
        $this->db->update('editor_collection_access',$data);
    }


    function get_permissions()
    {
        return $this->permissions;
    }


}