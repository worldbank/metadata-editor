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


    /**
     * Effective collection ACL rows for a user on a collection.
     * Includes grants on the collection itself and all ancestors (closure table).
     * Strongest privilege wins; no deny / override.
     *
     * @param int $collection_id
     * @param int $user_id
     * @return array rows with permissions key (same shape as get_user_permissions)
     */
    function get_effective_user_permissions($collection_id, $user_id)
    {
        $collection_id = (int) $collection_id;
        $user_id = (int) $user_id;

        $sql = 'SELECT a.permissions
            FROM editor_collections_tree t
            INNER JOIN editor_collection_acl a
                ON a.collection_id = t.parent_id
                AND a.user_id = ' . $this->db->escape($user_id) . '
            WHERE t.child_id = ' . $this->db->escape($collection_id);

        return $this->db->query($sql)->result_array();
    }


    /**
     * Strongest effective collection ACL privilege for a user on a collection.
     *
     * @param int $collection_id
     * @param int $user_id
     * @return string|null view|edit|admin
     */
    function get_effective_permission($collection_id, $user_id)
    {
        $this->load->helper('collection_acl');
        $rows = $this->get_effective_user_permissions($collection_id, $user_id);
        $permissions = array();
        foreach ($rows as $row) {
            if (!empty($row['permissions'])) {
                $permissions[] = $row['permissions'];
            }
        }
        return collection_acl_max_permission($permissions);
    }


    /**
     * Collection IDs the user can access via collection ACL inheritance:
     * direct ACL (or ownership) plus all descendants.
     *
     * @param int $user_id
     * @param bool $include_owned
     * @return array [collection_id => strongest known privilege from seed grants]
     */
    function get_accessible_collection_ids_with_permissions($user_id, $include_owned = true)
    {
        $user_id = (int) $user_id;
        $this->load->helper('collection_acl');

        $this->db->select('collection_id, permissions');
        $this->db->from('editor_collection_acl');
        $this->db->where('user_id', $user_id);
        $user_access = $this->db->get()->result_array();

        $seed = array();
        foreach ($user_access as $access) {
            $cid = (int) $access['collection_id'];
            $seed[$cid] = collection_acl_max_permission(array(
                isset($seed[$cid]) ? $seed[$cid] : null,
                $access['permissions']
            ));
        }

        if ($include_owned) {
            $this->db->select('id');
            $this->db->from('editor_collections');
            $this->db->where('created_by', $user_id);
            $owned = $this->db->get()->result_array();
            foreach ($owned as $row) {
                $cid = (int) $row['id'];
                $seed[$cid] = 'admin';
            }
        }

        if (empty($seed)) {
            return array();
        }

        $seed_ids = array_keys($seed);
        $accessible = $seed;

        // Expand to all descendants via closure table (includes raising a
        // weaker direct grant when an ancestor seed has a stronger privilege).
        $this->db->select('parent_id, child_id');
        $this->db->from('editor_collections_tree');
        $this->db->where_in('parent_id', $seed_ids);
        $this->db->where('depth >', 0);
        $descendants = $this->db->get()->result_array();

        foreach ($descendants as $row) {
            $parent_id = (int) $row['parent_id'];
            $child_id = (int) $row['child_id'];
            if (!isset($seed[$parent_id])) {
                continue;
            }
            $accessible[$child_id] = collection_acl_max_permission(array(
                isset($accessible[$child_id]) ? $accessible[$child_id] : null,
                $seed[$parent_id]
            ));
        }

        return $accessible;
    }


}
