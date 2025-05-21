<?php


/**
 * 
 * templates access and sharing
 * 
 * 
 */
class Template_acl_model extends ci_model {
 
    /*
    CREATE TABLE `editor_template_acl` (
    `id` int NOT NULL AUTO_INCREMENT,
    `template_id` int NOT NULL,
    `permissions` varchar(100) DEFAULT NULL,
    `user_id` int NOT NULL,
    `created` int DEFAULT NULL,
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

    - Return all users with access to a template
    - Add a user to a template
    - Remove a user from a template
    - Check if a user has access to a template
    - Get the owner of a template

    */


    function list_users($template_id)
    {
        $this->db->select('users.id as user_id,username,email,meta.first_name,meta.last_name, editor_template_acl.permissions');
        $this->db->join('meta','users.id=meta.user_id');
        $this->db->join('editor_template_acl','users.id=editor_template_acl.user_id');
        $this->db->where('editor_template_acl.template_id',$template_id);
        $this->db->order_by('username','ASC');
        return $this->db->get('users')->result_array();
    }


    function add_user($template_id,$user_id,$permissions)
    {
        $data=array(
            'template_id'=>$template_id,
            'user_id'=>$user_id,
            'permissions'=>$permissions,
            'created'=>time()
        );

        if (!in_array($permissions,array_keys($this->permissions))){
            throw new Exception("Invalid permission");
        }

        if ($this->user_has_access($template_id,$user_id)){
            return $this->update_user($template_id,$user_id,$permissions);
        }

        $this->db->insert('editor_template_acl',$data);
    }


    function update_user($template_id,$user_id,$permissions)
    {
        $data=array(
            'permissions'=>$permissions
        );

        if (!in_array($permissions,array_keys($this->permissions))){
            throw new Exception("Invalid permission");
        }

        $this->db->where('template_id',$template_id);
        $this->db->where('user_id',$user_id);
        $this->db->update('editor_template_acl',$data);
    }


    function remove_user($template_id,$user_id)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('user_id',$user_id);
        return $this->db->delete('editor_template_acl');
    }


    function get_user_permissions($template_id,$user_id)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('editor_template_acl')->result_array();
        return $result;
    }


    function user_has_access($template_id,$user_id)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('editor_template_acl')->result_array();
        return count($result)>0;
    }

    


}
