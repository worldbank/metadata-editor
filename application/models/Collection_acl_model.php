<?php


/**
 * 
 * collections access and sharing
 * 
 * 
 */
class Collection_acl_model extends ci_model {
 
    /*
    CREATE TABLE `editor_collection_acl` (
    `id` int NOT NULL AUTO_INCREMENT,
    `collection_id` int NOT NULL,
    `permissions` varchar(100) DEFAULT NULL,
    `user_id` int NOT NULL,
    `created` int DEFAULT NULL,
    `changed` int DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1;
    */

    private $permissions=array(
        'view'=>'View',
        'edit'=>'Edit',
        //'delete'=>'Delete',
        'admin'=>'Admin'
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
    }


    /*

    - Return all users with access to a collection
    - Add a user to a collection
    - Remove a user from a collection
    - Check if a user has access to a collection
    - Get the owner of a collection

    */


    function list_users($collection_id)
    {
        $this->db->select('users.id as user_id,username,email,meta.first_name,meta.last_name, editor_collection_acl.permissions');
        $this->db->join('meta','users.id=meta.user_id');
        $this->db->join('editor_collection_acl','users.id=editor_collection_acl.user_id');
        $this->db->where('editor_collection_acl.collection_id',$collection_id);
        $this->db->order_by('username','ASC');
        return $this->db->get('users')->result_array();
    }


    function add_user($collection_id,$user_id,$permissions)
    {
        $data=array(
            'collection_id'=>$collection_id,
            'user_id'=>$user_id,
            'permissions'=>$permissions,
            'created'=>time()
        );

        if (!in_array($permissions,array_keys($this->permissions))){
            throw new Exception("Invalid permission");
        }

        if ($this->user_has_access($collection_id,$user_id)){
            return $this->update_user($collection_id,$user_id,$permissions);
        }

        $this->db->insert('editor_collection_acl',$data);
    }


    function update_user($collection_id,$user_id,$permissions)
    {
        $data=array(
            'permissions'=>$permissions,
            'changed'=>time()
        );

        if (!in_array($permissions,array_keys($this->permissions))){
            throw new Exception("Invalid permission");
        }

        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);
        $this->db->update('editor_collection_acl',$data);
    }


    function remove_user($collection_id,$user_id)
    {
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);
        return $this->db->delete('editor_collection_acl');
    }


    function get_user_permissions($collection_id,$user_id)
    {
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('editor_collection_acl')->result_array();
        return $result;
    }


    function user_has_access($collection_id,$user_id)
    {
        $this->db->where('collection_id',$collection_id);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('editor_collection_acl')->result_array();
        return count($result)>0;
    }

    


}
