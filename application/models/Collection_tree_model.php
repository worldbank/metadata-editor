<?php



/**
 * 
 * Collections tree model
 * 
 */
class Collection_tree_model extends CI_Model {


    private $fields=array(
        'parent_id',
        'child_id',
        'depth'
    );

    function __construct()
    {
        parent::__construct();
    }


    /**
     * 
     * insert
     * 
     */
    function insert($parent_id,$child_id)
    {
        $data=array(
            'parent_id'=>$parent_id,
            'child_id'=>$child_id,
            'depth'=>0
        );

        if ($parent_id==$child_id){
            
            if ($this->get_item($parent_id,$child_id,0)){
                return true;
            }

            return $this->db->insert('editor_collections_tree',$data);
        }

        $sql='insert into editor_collections_tree(parent_id, child_id, depth)
                select p.parent_id, c.child_id, p.depth+c.depth+1
                    from editor_collections_tree p, editor_collections_tree c
                where p.child_id='. $this->db->escape($parent_id) .' and c.parent_id='. $this->db->escape($child_id) .';';

        $result=$this->db->query($sql);
        return $result;
    }

    /**
     * 
     * Delete collection tree
     * 
     *  - deletes node + all children of the parent_id
     * 
     */
    function delete($parent_id)
    {
        /*
            note: not supported by MYSQL

            delete from editor_collections_tree
            where child_id in (select child_id from editor_collections_tree where parent_id=28)
        */

        //$this->db->where('child_id in (select child_id from editor_collections_tree where parent_id='. $this->db->escape($parent_id) .')');
        //return $this->db->delete('editor_collections_tree');

        //get list of all children ids
        $delete_list=$this->select_collection_tree_nodes($parent_id);

        if (!$delete_list){
            return true;
        }

        //delete all children
        $this->db->where_in('child_id',$delete_list);
        return $this->db->delete('editor_collections_tree');
    }


    function collections_tree_by_user_access($user_id)
    {
        //get user info
        $user = $this->ion_auth->get_user($user_id);

        // Check if user is collection admin (global admin or collection admin)
        if ($user && $this->editor_acl->is_collection_admin($user)) {
            // Global admin or collection admin - return all collections with projects without ACL filtering
            $sql='SELECT 
                    ect.parent_id, 
                    ect.child_id, 
                    ect.depth, 
                    c.*
                FROM 
                    editor_collections_tree ect
                INNER JOIN 
                    editor_collections c ON ect.child_id = c.id
                WHERE 
                    ect.parent_id IN (
                        SELECT 
                            t.parent_id
                        FROM 
                            editor_collections c
                        INNER JOIN 
                            editor_collections_tree t ON c.id = t.parent_id
                        WHERE 
                            c.pid IS NULL
                    )
                    AND ect.child_id IN (
                        SELECT DISTINCT 
                            ecp.collection_id
                        FROM 
                            editor_collection_projects ecp
                    )
                ORDER BY 
                    c.title, ect.child_id, ect.parent_id, ect.id, ect.depth;';
        } else {
            // Non-admin user - apply ACL filtering with implied access
            // Collection ACL access implies ability to see projects in that collection
            $sql='SELECT 
                    ect.parent_id, 
                    ect.child_id, 
                    ect.depth, 
                    c.*
                FROM 
                    editor_collections_tree ect
                INNER JOIN 
                    editor_collections c ON ect.child_id = c.id
                WHERE 
                    -- Only show collections that have projects
                    ect.child_id IN (
                        SELECT DISTINCT 
                            ecp.collection_id
                        FROM 
                            editor_collection_projects ecp
                    )
                    -- AND user has access via Collection ACL, Project ACL, or ownership
                    AND ect.child_id IN (
                        -- Collections accessible via Collection ACL (NEW system)
                        SELECT collection_id 
                        FROM editor_collection_acl 
                        WHERE user_id = '. $this->db->escape($user_id) . '
                        
                        UNION
                        
                        -- Collections accessible via Project ACL (OLD system)
                        SELECT DISTINCT collection_id
                        FROM editor_collection_project_acl
                        WHERE user_id = '. $this->db->escape($user_id) . '
                        
                        UNION
                        
                        -- Collections owned by user
                        SELECT id as collection_id
                        FROM editor_collections
                        WHERE created_by = '. $this->db->escape($user_id) . '
                    )
                    -- AND show root level collections
                    AND ect.parent_id IN (
                        SELECT 
                            t.parent_id
                        FROM 
                            editor_collections c
                        INNER JOIN 
                            editor_collections_tree t ON c.id = t.parent_id
                        WHERE 
                            c.pid IS NULL
                    )
                ORDER BY 
                    c.title, ect.child_id, ect.parent_id, ect.id, ect.depth;';
        }

        $items=$this->db->query($sql)->result_array();
        
        // Add project counts to collections
        $this->load->model('Collection_model');
        $collection_projects_count = $this->Collection_model->get_projects_count_all();
        
        foreach($items as $key => $item){
            $items[$key]['projects'] = isset($collection_projects_count[$item['id']]) 
                ? $collection_projects_count[$item['id']] 
                : 0;
        }
        
        //create tree
        $tree=$this->build_collection_tree($items);

        return $tree;
    }

    /**
     * 
     * Return all children of a collection node + parent node
     * 
     */
    function select_collection_tree_nodes($parent_id)
    {
        /*
        select * from editor_collections_tree
        where child_id in (select child_id from editor_collections_tree where parent_id=28)
        */
        
        $this->db->select('child_id');
        $this->db->where('child_id in (select child_id from editor_collections_tree where parent_id='. $this->db->escape($parent_id) .')');
        $result= $this->db->get('editor_collections_tree')->result_array();
        
        $output=array();
        foreach($result as $row){
            $output[]=$row['child_id'];
        }
        return $output;
    }

     /**
     * 
     * Return all children of a collection node
     * 
     */
    function select_collection_children_nodes($parent_id)
    {
        /*
        select * from editor_collections_tree where parent_id=60;
        */
        
        $this->db->select('child_id');
        $this->db->where('parent_id',$parent_id);
        $result= $this->db->get('editor_collections_tree')->result_array();
        
        $output=array();
        foreach($result as $row){
            $output[]=$row['child_id'];
        }
        return $output;
    }



    function get_item($parent_id,$child_id,$depth)
    {
        $this->db->select('*');
        $this->db->where('parent_id',$parent_id);
        $this->db->where('child_id',$child_id);
        $this->db->where('depth',$depth);
        $result=$this->db->get('editor_collections_tree')->row_array();
        return $result;
    }
    

    function select($parent_id)
    {
        $sql='select * from editor_collections_tree where parent_id='. $this->db->escape($parent_id) .';';
        $result=$this->db->query($sql);
        return $result->result_array();
    }

    function truncate_tree()
    {
        $this->db->truncate('editor_collections_tree');
    }

    function get_tree($parent_id=null)
    {
        $items=$this->get_tree_flat($parent_id);
        
        $tree=array();
        if ($parent_id){
            $tree=$this->get_tree_root($parent_id);
            $tree['items']=$this->build_collection_tree($items,$parent_id);
        }else{
            $tree=$this->build_collection_tree($items,$parent_id);
        }
        return $tree;
    }

    function get_tree_root($id)
    {
        $this->db->select('*');
        $this->db->where('id',$id);
        $result=$this->db->get('editor_collections')->row_array();
        return $result;
    }

    private function build_collection_tree(array &$collections, $parentId = 0) 
    {
        $output = array();
    
        foreach ($collections as $collection) {
            if ($collection['pid'] == $parentId) {
                $children = $this->build_collection_tree($collections, $collection['id']);
                if ($children) {
                    $collection['items'] = $children;
                }
                $output[] = $collection;
                //unset($collections[$collection['id']]);
            }
        }
        return $output;
    }
    
    
    /*function build_tree_closure(&$collections, $depth=0, $parent_id=null)
    {
        $output=array();
        foreach($collections as $idx=>$collection){

            if ($collection['depth']==$depth){

                //match parent_id if not null
                if ($parent_id && $collection['parent_id']!=$parent_id){
                    continue;
                }

                //find children
                $children=$this->build_tree_closure($collections,$depth+1,$collection['parent_id']);
                if ($children){
                    $collection['items']=$children;
                }
                $output[]=$collection;
                unset($collections[$idx]);
            }
        }
        return $output;
    }*/


    function get_tree_flat($parent_id=null)
    {
        $this->db->select('ect.parent_id,ect.child_id,ect.depth,c.*');
        $this->db->from('editor_collections_tree ect');
        $this->db->join('editor_collections c','ect.child_id=c.id');

        if ($parent_id){
            $this->db->where('ect.parent_id',$parent_id);
        }else{
            $this->db->where('ect.parent_id in (select id from editor_collections where pid is null)');
        }
        
        $this->db->order_by('ect.depth','asc');
        $this->db->order_by('c.title','asc');

        $result=$this->db->get()->result_array();
        return $result;        
    }


    /**
     * Get flat tree list filtered by user access
     * Users can see collections they have access to and their parent chain
     * 
     * @param int $user_id User ID
     * @param int $parent_id Optional parent collection ID
     * @return array Filtered flat tree list
     */
    function get_tree_flat_by_user($user_id, $parent_id=null)
    {
        // Get collections user has access to
        $this->db->select('collection_id');
        $this->db->from('editor_collection_acl');
        $this->db->where('user_id', $user_id);
        $user_access = $this->db->get()->result_array();
        
        $accessible_ids = array();
        foreach ($user_access as $access) {
            $accessible_ids[] = $access['collection_id'];
        }
        
        // Add collections owned by user
        $this->db->select('id');
        $this->db->from('editor_collections');
        $this->db->where('created_by', $user_id);
        $owned_collections = $this->db->get()->result_array();
        foreach ($owned_collections as $owned) {
            if (!in_array($owned['id'], $accessible_ids)) {
                $accessible_ids[] = $owned['id'];
            }
        }
        
        // If no access to any collections, return empty array
        if (empty($accessible_ids)) {
            return array();
        }
        
        // Get all collections to find parent chains
        $this->db->select('id, pid, created_by');
        $all_collections = $this->db->get('editor_collections')->result_array();
        
        // Build lookup for parent chain
        $collection_lookup = array();
        foreach ($all_collections as $col) {
            $collection_lookup[$col['id']] = $col;
        }
        
        // Get parent chain for accessible collections
        $ids_to_include = $accessible_ids;
        foreach ($accessible_ids as $cid) {
            $current_id = $cid;
            while (isset($collection_lookup[$current_id])) {
                $current = $collection_lookup[$current_id];
                if (!in_array($current_id, $ids_to_include)) {
                    $ids_to_include[] = $current_id;
                }
                if ($current['pid'] !== null && $current['pid'] != 0) {
                    $current_id = $current['pid'];
                } else {
                    break;
                }
            }
        }
        
        // Now get the flat tree with filtering
        $this->db->select('ect.parent_id,ect.child_id,ect.depth,c.*');
        $this->db->from('editor_collections_tree ect');
        $this->db->join('editor_collections c','ect.child_id=c.id');
        $this->db->where_in('c.id', $ids_to_include);

        if ($parent_id){
            $this->db->where('ect.parent_id',$parent_id);
        }else{
            $this->db->where('ect.parent_id in (select id from editor_collections where pid is null)');
        }
        
        $this->db->order_by('ect.depth','asc');
        $this->db->order_by('c.title','asc');

        $result=$this->db->get()->result_array();
        return $result;        
    }


    function get_all_collections_list()
    {
        $this->db->select('*');
        $this->db->order_by('pid','asc');
        return $this->db->get('editor_collections')->result_array();
    }


    /**
     * 
     * Rebuild tree
     * 
     */
    function rebuild_tree()
	{
			//truncate all data
			$this->truncate_tree();

			$collections_tree=$this->Collection_model->get_collection_tree();

			//read all data from collections
			$collections=$this->Collection_model->select_all();

			foreach($collections as $collection){
				$this->insert($collection['id'],$collection['id']);
			}

			$walk_tree=function($collections_tree) use (&$walk_tree){
				foreach($collections_tree as $collection){
					$parent_id=isset($collection['pid'])?$collection['pid']: $collection['id'];
					$this->insert($parent_id,$collection['id']);
					
					if (isset($collection['items'])){
						$walk_tree($collection['items']);						
					}
				}
			};

			$walk_tree($collections_tree);

	}

    

}