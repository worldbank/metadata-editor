<?php


/**
 * 
 * Admin metadata templates access and sharing for metadata management
 * 
 * 
 */
class Admin_metadata_acl_model extends ci_model {
 
    /*
    CREATE TABLE `admin_metadata_acl` (
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
        $this->db->select('users.id as user_id,username,email,meta.first_name,meta.last_name, admin_metadata_acl.permissions');
        $this->db->join('meta','users.id=meta.user_id');
        $this->db->join('admin_metadata_acl','users.id=admin_metadata_acl.user_id');
        $this->db->where('admin_metadata_acl.template_id',$template_id);
        $this->db->order_by('username','ASC');
        $result=$this->db->get('users')->result_array();
        return $result;
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

        $this->db->insert('admin_metadata_acl',$data);
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
        $this->db->update('admin_metadata_acl',$data);
    }


    function remove_user($template_id,$user_id)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('user_id',$user_id);
        return $this->db->delete('admin_metadata_acl');
    }


    function get_user_permissions($template_id,$user_id)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('admin_metadata_acl')->result_array();

        $permissions=array();
        
        foreach ($result as $row){
            $permissions[]=$row['permissions'];
        }

        return $permissions;
    }


    function user_has_access($template_id,$user_id)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('user_id',$user_id);
        $query=$this->db->get('admin_metadata_acl');

        if (!$query)
        {
            return false;
        }

        $result=$query->result_array();

        return count($result)>0;
    }


    function is_admin_metadata_template($template_id)
    {
        $this->db->select('id');
        $this->db->where('template_id',$template_id);
        $this->db->where('data_type','admin_meta');
        $result=$this->db->get('editor_templates')->result_array();

        return count($result)>0;
    }

    /**
     * 
     * Get all templates that a user has access to
     * 
     *  @template_uid_list: list of template uids to filter (optional)
     * 
     * 
     */
    function get_templates_id_by_user($user_id, $template_uid_list=null)
    {
        $this->db->select('admin_metadata_acl.template_id');
        $this->db->where('user_id',$user_id);

        if (is_array($template_uid_list) && count($template_uid_list)>0){
            $this->db->join('editor_templates','editor_templates.id=admin_metadata_acl.template_id');
            $this->db->where_in('uid',$template_uid_list);
        }

        $result=$this->db->get('admin_metadata_acl')->result_array();

        $templates=array();
        foreach ($result as $row){
            $templates[]=$row['template_id'];
        }

        return $templates;
    }

    


}
