<?php


/**
 * 
 * Admin Metadata access and sharing
 * 
 * 
 */
class Metadata_type_acl_model extends ci_model {
 
    /*
    CREATE TABLE `metadata_types_acl` (
        `id` int NOT NULL AUTO_INCREMENT,
        `metadata_type_id` int NOT NULL,
        `permissions` varchar(100) DEFAULT NULL,
        `user_id` int NOT NULL,
        `created` int DEFAULT NULL,
        `changed` int default null,
        `created_by` int default null,
        `changed_by` int default null,
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



    function list_users($metadata_type_id)
    {
        $this->db->select('users.id as user_id,username,email,meta.first_name,meta.last_name, metadata_types_acl.permissions');
        $this->db->join('meta','users.id=meta.user_id');
        $this->db->join('metadata_types_acl','users.id=metadata_types_acl.user_id');
        $this->db->where('metadata_types_acl.metadata_type_id',$metadata_type_id);
        $this->db->order_by('username','ASC');
        return $this->db->get('users')->result_array();        
    }


    function add_user($metadata_type_id,$user_id,$permissions)
    {
        $data=array(
            'metadata_type_id'=>$metadata_type_id,
            'user_id'=>$user_id,
            'permissions'=>$permissions,
            'created'=>time()
        );

        if (!in_array($permissions,array_keys($this->permissions))){
            throw new Exception("Invalid permission");
        }

        if ($this->user_has_access($metadata_type_id,$user_id)){
            return $this->update_user($metadata_type_id,$user_id,$permissions);
        }

        $this->db->insert('metadata_types_acl',$data);
    }


    function update_user($metadata_type_id,$user_id,$permissions)
    {
        $data=array(
            'permissions'=>$permissions
        );

        if (!in_array($permissions,array_keys($this->permissions))){
            throw new Exception("Invalid permission");
        }

        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('user_id',$user_id);
        return $this->db->update('metadata_types_acl',$data);
    }


    function remove_user($metadata_type_id,$user_id)
    {
        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('user_id',$user_id);
        return $this->db->delete('metadata_types_acl');
    }


    function get_user_permissions($metadata_type_id,$user_id)
    {
        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('user_id',$user_id);
        return $this->db->get('metadata_types_acl')->result_array();
    }


    function user_has_access($metadata_type_id,$user_id)
    {
        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('metadata_types_acl')->result_array();
        return count($result)>0;
    }

    


}
