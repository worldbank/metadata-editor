<?php


/*
CREATE TABLE editor_collection_project_acl(  
    id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
    collection_id int NOT NULL,
    user_id int NOT NULL,
    permissions varchar(100) not null    
);
*/

/**
 * 
 * Collections project ACL model - manages access to projects within collections
 * 
 */
class Collection_project_acl_model extends CI_Model {


    private $fields=array(
        'id',
        'collection_id',
        'user_id',
        'permissions'
    );

    public $permissions=array(
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
     * Get list of users with access to projects in a collection
     * 
     * 
     */
    function select_all($collection_id)
    {
        $this->db->select('editor_collection_project_acl.*,users.username,users.email');
        $this->db->join('users','users.id=editor_collection_project_acl.user_id');
        $this->db->where('collection_id',$collection_id);
        return $this->db->get('editor_collection_project_acl')->result_array();
    }

    function delete($id)
    {
        $this->db->where('id',$id);
        $this->db->delete('editor_collection_project_acl');
    }

    function delete_user($collection_id,$user_id)
    {
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);
        $this->db->delete('editor_collection_project_acl');
    }

    function permission_exists($collection_id,$user_id)
    {
        $this->db->select('id');
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);        
        return $this->db->count_all_results('editor_collection_project_acl');
    }

    function get_permission_id($collection_id,$user_id)
    {
        $this->db->select('id');
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);        
        $result=$this->db->get('editor_collection_project_acl')->row_array();

        if($result){
            return $result['id'];
        }

        return false;
    }

    function insert($data)
    {
        if ($this->permission_exists($data['collection_id'],$data['user_id'])){
            throw new Exception("User already has access to projects in this collection");
        }

        $data=array_intersect_key($data,array_flip($this->fields));
        
        if (isset($data['id'])){
            unset($data['id']);
        }

        $this->db->insert('editor_collection_project_acl',$data);
        return $this->db->insert_id();
    }

    function update($id,$data)
    {
        $data=array_intersect_key($data,array_flip($this->fields));
        
        if (isset($data['id'])){
            unset($data['id']);
        }

        $this->db->where('id',$id);
        $this->db->update('editor_collection_project_acl',$data);
    }

    function upsert($data)
    {
        $permission_id=$this->get_permission_id($data['collection_id'],$data['user_id']);

        if ($permission_id){
            return $this->update($permission_id,$data);
        }

        return $this->insert($data);
    }


    function get_permissions()
    {
        return $this->permissions;
    }


    /**
     * Effective project-ACL privilege for a user on a collection.
     * Includes grants on the collection itself and all ancestors.
     *
     * @param int $collection_id
     * @param int $user_id
     * @return string|null view|edit|admin
     */
    function get_effective_permission($collection_id, $user_id)
    {
        $this->load->helper('collection_acl');
        $collection_id = (int) $collection_id;
        $user_id = (int) $user_id;

        $sql = 'SELECT a.permissions
            FROM editor_collections_tree t
            INNER JOIN editor_collection_project_acl a
                ON a.collection_id = t.parent_id
                AND a.user_id = ' . $this->db->escape($user_id) . '
            WHERE t.child_id = ' . $this->db->escape($collection_id);

        $rows = $this->db->query($sql)->result_array();
        $permissions = array();
        foreach ($rows as $row) {
            if (!empty($row['permissions'])) {
                $permissions[] = $row['permissions'];
            }
        }
        return collection_acl_max_permission($permissions);
    }


    /**
     * SQL selecting project SIDs accessible via inherited collection project ACL.
     *
     * @param int $user_id
     * @return string
     */
    function sql_project_ids_for_user($user_id)
    {
        $this->load->helper('collection_acl');
        return collection_acl_sql_project_ids_for_user($user_id);
    }


}
