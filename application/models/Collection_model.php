<?php



/**
 * 
 * Collections
 * 
 */
class Collection_model extends CI_Model {


    private $fields=array(
        'title',
        'description',
        'wgt',
        'pid',
        'created_by',
        'created',
        'changed',
        'changed_by'
    );

    private $required=array(
        'title'
    );

    function __construct()
    {
        parent::__construct();
        $this->load->model("Collection_tree_model");
        $this->load->model("Editor_model");
    }


    /**
     * 
     * Get all collections
     * 
     * 
     */
    function select_all()
    {
        $this->db->select('editor_collections.*,users.username');
        $this->db->join('users','users.id=editor_collections.created_by','left');
        $this->db->order_by('title','ASC');
        return $this->db->get('editor_collections')->result_array();
    }


    function select_single($id)
    {
        $this->db->select('*');
        $this->db->where('id',$id);
        return $this->db->get('editor_collections')->row_array();
    }

    function get_pid($id)
    {
        $this->db->select('pid');
        $this->db->where('id',$id);
        $result=$this->db->get('editor_collections')->row_array();

        if ($result){
            return $result['pid'];
        }
    }
    

    function delete($id)
    {
        $this->db->where('id',$id);
        $this->db->delete('editor_collections');
    }


    function delete_nested($id)
    {
        //get all children of this collection
        $items=$this->Collection_tree_model->select_collection_children_nodes($id);

        //remove tree (node + children)
        $this->Collection_tree_model->delete($id);

        //remove collection + children
        if (is_array($items) && count($items)>0){
            $this->db->where_in('id',$items);
            $this->db->delete('editor_collections');
        }
    }

    function insert($data)
    {
        $data=array_intersect_key($data,array_flip($this->fields));

        if (!isset($data['pid'])){
            $data['pid']=null;
        }

        if ($data['pid']!=null && !$this->collection_id_exists($data['pid'])){
            throw new Exception('Parent collection does not exist');
        }

        if (isset($data['title'])){
            $data['title']=trim($data['title']);
        }

        //required fields
        foreach($this->required as $field){
            if (!isset($data[$field]) || empty($data[$field])){
                throw new Exception('Missing required field: '.$field);
            }
        }                

        //check if title + pid is unique
        if ($this->is_unique($data['title'],$data['pid'])){
            throw new Exception('Collection already exists');
        }

        $this->db->insert('editor_collections',$data);
        $collection_id=$this->db->insert_id();

        if ($collection_id){
            $this->Collection_tree_model->insert($collection_id,$collection_id);

            if ($data['pid']){
                $this->Collection_tree_model->insert($data['pid'],$collection_id);
            }
        }

        return $collection_id;
    }



    function update($id,$data)
    {
        $data=array_intersect_key($data,array_flip($this->fields));
        
        if (isset($data['id'])){
            unset($data['id']);
        }

        if (isset($data['pid']) && !$this->collection_id_exists($data['pid'])){
            throw new Exception('Parent collection does not exist');            
        }

        if (isset($data['title'])){
            $data['title']=trim($data['title']);

            if (empty($data['title'])){
                throw new Exception('Title cannot be empty');
            }

            if ($this->is_unique($data['title'],$this->get_pid($id),$id)){
                throw new Exception('Collection already exists');
            }
        }

        $this->db->where('id',$id);
        $this->db->update('editor_collections',$data);
    }


    /**
     * Add project to collection
     * 
     */
    function add_project($collection_id,$sid)
    {
        $data=array(
            'collection_id'=>$collection_id,
            'sid'=>$sid
        );

        $this->db->insert('editor_collection_projects',$data);
        return $this->db->insert_id();
    }

    function add_batch_projects($collections,$sids)
    {
        $collections=(array)$collections;
        $sids=(array)$sids;

        foreach($collections as $collection_id){

            //check if collection id exists
            $collection_exists=$this->collection_id_exists($collection_id);

            if (!$collection_exists){
                throw new Exception('Collection not found: '.$collection_id);
            }

            $this->add_projects($collection_id,$sids);
        }
    }

    function add_projects($collection_id,$sids)
    {
        $data=array();

        foreach($sids as $sid)
        {
            //check if project id exists
            $project_exists=$this->Editor_model->check_id_exists($sid);

            if (!$project_exists){
                throw new Exception('Project not found: '.$sid);
            }

            if (!$this->collection_project_exists($collection_id,$sid)){
                $data[]=array(
                    'collection_id'=>$collection_id,
                    'sid'=>$sid
                );
            }
        }

        if (count($data)==0){
            return;
        }

        $this->db->insert_batch('editor_collection_projects',$data);
    }

    function collection_project_exists($collection_id,$sid)
    {
        $this->db->select('sid');
        $this->db->where('collection_id',$collection_id);
        $this->db->where('sid',$sid);
        return $this->db->count_all_results('editor_collection_projects');
    }


    /**
     * 
     * Batch remove projects from multiple collections
     * 
     */
    function remove_batch_projects($collections,$sids)
    {
        $collections=(array)$collections;
        $sids=(array)$sids;
        $sids = array_map('intval', $sids);

        foreach($collections as $collection_id){

            //check if collection id exists
            $collection_exists=$this->collection_id_exists($collection_id);

            if (!$collection_exists){
                throw new Exception('Collection not found: '.$collection_id);
            }

            $this->remove_projects($collection_id,$sids);
        }
    }

    /**
     * Remove project from collection
     * 
     */
    function remove_projects($collection_id,$sids)
    {
        $this->db->where('collection_id',$collection_id);
        $this->db->where_in('sid',$sids);
        return $this->db->delete('editor_collection_projects');
    }

    /**
     * Get collection projects
     * 
     */
    function get_projects($collection_id, $project_type=null)
    {

        if ($project_type)
        {
            $this->db->select('editor_projects.type,editor_collection_projects.sid');
            $this->db->join('editor_projects','editor_projects.id=editor_collection_projects.sid');
            $this->db->where('editor_collection_projects.collection_id',$collection_id);
            $this->db->where('editor_projects.type',$project_type);
            return $this->db->get('editor_collection_projects')->result_array();
        }

        $this->db->select('sid');
        $this->db->where('collection_id',$collection_id);
        return $this->db->get('editor_collection_projects')->result_array();
    }

    /**
     * 
     * Get collection projects list
     * 
     */
    function get_projects_list($collection_id)
    {
        $this->db->select('editor_projects.id,editor_projects.idno, editor_projects.type,editor_projects.title,editor_projects.created, editor_projects.changed ');
        $this->db->join('editor_projects','editor_projects.id=editor_collection_projects.sid');
        $this->db->where('collection_id',$collection_id);
        return $this->db->get('editor_collection_projects')->result_array();
    }



    /**
     * Get collection projects count
     * 
     */
    function get_projects_count($collection_id)
    {
        $this->db->select('sid');
        $this->db->where('collection_id',$collection_id);
        return $this->db->count_all_results('editor_collection_projects');
    }

    function get_users_count($collection_id)
    {
        $this->db->select('user_id');
        $this->db->where('collection_id',$collection_id);
        		return $this->db->count_all_results('editor_collection_project_acl');
    }


    /**
     * 
     * Get projects count for multiple collections
     * 
     */
    function get_projects_count_multiple($collection_ids)
    {
        $this->db->select('collection_id, count(sid) as projects');
        $this->db->where_in('collection_id',$collection_ids);
        $this->db->group_by('collection_id');
        $result=$this->db->get('editor_collection_projects')->result_array();
        $output=array();
        foreach($result as $row){
            $output[$row['collection_id']]=$row['projects'];
        }
        return $output;
    }


    /**
     * Get collection by project
     * 
     */
    function get_collection_by_project($sid)
    {
        $this->db->select('editor_collections.id,editor_collections.title, editor_collections.pid');
        $this->db->join('editor_collections','editor_collections.id=editor_collection_projects.collection_id');
        $this->db->where('sid',$sid);
        $result=$this->db->get('editor_collection_projects')->result_array();
        return $result;
    }


    /**
     * 
     * Get collection by user access
     * 
     */
    function get_collection_by_user($user_id)
    {
        $this->db->select('editor_collections.id,editor_collections.title');
        		$this->db->join('editor_collection_project_acl','editor_collections.id=editor_collection_project_acl.collection_id');
        $this->db->where('user_id',$user_id);
        $this->db->or_where('editor_collections.created_by',$user_id);
        $result=$this->db->get('editor_collections')->result_array();
        return $result;
    }


    function get_collection_id_by_name($name)
    {
        $this->db->select('id');
        $this->db->where('title',$name);
        $this->db->where('pid',null);
        $result=$this->db->get('editor_collections')->row_array();

        if ($result){
            return $result['id'];
        }
    }



    /**
     * Get collections by projects
     * 
     */
    function collections_by_projects($sids)
    {
        $this->db->select('editor_collections.id,editor_collections.title,editor_collection_projects.sid');
        $this->db->where_in('sid',$sids);
        $this->db->join('editor_collections','editor_collections.id=editor_collection_projects.collection_id');
        $result=$this->db->get('editor_collection_projects')->result_array();
        $output=array();

        foreach($result as $row){
            $output[$row['sid']][]=$row;
        }

        return $output;
    }


    function collections_list()
    {
        $this->db->select('id,title');
        $this->db->order_by('title','ASC');
        return $this->db->get('editor_collections')->result_array();
    }


    function get_project_id_by_idno($idno)
    {
        $this->db->select('id');
        $this->db->where('idno',$idno);
        $result=$this->db->get('editor_projects')->row_array();

        if ($result){
            return $result['id'];
        }
    }


    function collection_id_exists($id)
    {
        $this->db->select('id');
        $this->db->where('id',$id);
        return $this->db->count_all_results('editor_collections');
    }


    function is_unique($title,$pid=null,$id=null)
    {
        $this->db->select('id');
        $this->db->where('title',$title);

        if ($pid){
            $this->db->where('pid',$pid);
        }else{
            $this->db->where('pid is null');
        }

        if ($id){
            $this->db->where('id !=',$id);
        }

        return $this->db->count_all_results('editor_collections');
    }


    /**
     * 
     * Get projects count for all collections
     * 
     */
    function get_projects_count_all($collection_id=null)
    {
        $this->db->select('collection_id, count(sid) as projects');
        $this->db->group_by('collection_id');
        
        if ($collection_id){
            $this->db->where('collection_id',$collection_id);
        }

        $result=$this->db->get('editor_collection_projects')->result_array();
        $output=array();
        foreach($result as $row){
            $output[$row['collection_id']]=$row['projects'];
        }
        return $output;
    }

    function get_users_count_all($collection_id=null)
    {
        $this->db->select('collection_id, count(user_id) as users');
        $this->db->group_by('collection_id');

        if ($collection_id){
            $this->db->where('collection_id',$collection_id);
        }

        		$result=$this->db->get('editor_collection_project_acl')->result_array();
        $output=array();
        foreach($result as $row){
            $output[$row['collection_id']]=$row['users'];
        }
        return $output;
    }


    /**
     * 
     * Flatten collection tree
     * 
     */
    private function flatten_collection_tree($tree,$parent,&$output)
    {
        foreach($tree as $branch){
            $output[]=array(
                'id'=>$branch['id'],
                'title'=>$parent.$branch['title'],
                'projects'=>$branch['projects'],
                'users'=>$branch['users']
            );

            if (isset($branch['items'])){
                $this->flatten_collection_tree($branch['items'],$parent.$branch['title'].' / ',$output);
            }
        }
    }


    function get_collection_flatten_tree($id=null)
    {
        $tree=$this->get_collection_tree($id);

        $output=array();
        $this->flatten_collection_tree($tree,$parent='',$output);

        return $output;
    }


    /**
     * Get flattened collection tree filtered by user access
     * 
     * @param int $user_id User ID
     * @param int $id Optional collection ID to get specific subtree
     * @return array Flattened filtered collection tree
     */
    function get_collection_flatten_tree_by_user($user_id, $id=null)
    {
        $tree=$this->get_collection_tree_by_user($user_id, $id);

        $output=array();
        $this->flatten_collection_tree($tree,$parent='',$output);

        return $output;
    }


    
    /**
     * 
     * Get a tree of all collections
     * 
     * 
     */
    function get_collection_tree($id=null)    
    {
        $collections=$this->select_all();
        $collection_projects_count=$this->get_projects_count_all();
        $collection_users_count=$this->get_users_count_all();

        foreach($collections as &$collection){
            $collection['projects']=isset($collection_projects_count[$collection['id']]) ? $collection_projects_count[$collection['id']] : 0;
            $collection['users']=isset($collection_users_count[$collection['id']]) ? $collection_users_count[$collection['id']] : 0;
        }

        $tree=$this->build_collection_tree($collections);

        if ($id){
            $tree=$this->get_collection_tree_by_id($tree,$id);
        }

        return $tree;
    }


    /**
     * Get collection tree filtered by user access
     * Users can see collections they have access to and their parent chain
     * but NOT siblings they don't have access to
     * 
     * @param int $user_id User ID
     * @param int $id Optional collection ID to get specific subtree
     * @return array Filtered collection tree
     */
    function get_collection_tree_by_user($user_id, $id=null)
    {
        // Get all collections
        $collections=$this->select_all();
        $collection_projects_count=$this->get_projects_count_all();
        $collection_users_count=$this->get_users_count_all();

        foreach($collections as &$collection){
            $collection['projects']=isset($collection_projects_count[$collection['id']]) ? $collection_projects_count[$collection['id']] : 0;
            $collection['users']=isset($collection_users_count[$collection['id']]) ? $collection_users_count[$collection['id']] : 0;
        }

        // Get collections the user has explicit access to
        $this->db->select('collection_id, permissions');
        $this->db->from('editor_collection_acl');
        $this->db->where('user_id', $user_id);
        $user_access = $this->db->get()->result_array();

        // Create set of accessible collection IDs
        $accessible_ids = array();
        foreach ($user_access as $access) {
            $accessible_ids[$access['collection_id']] = $access['permissions'];
        }

        // If user is the owner of any collection, add it to accessible IDs with admin permission
        foreach ($collections as $collection) {
            if ($collection['created_by'] == $user_id) {
                $accessible_ids[$collection['id']] = 'admin';
            }
        }

        // If no access to any collections, return empty array
        if (empty($accessible_ids)) {
            return array();
        }

        // Get parent chain for all accessible collections
        $ids_to_include = $this->get_parent_chain_for_collections(array_keys($accessible_ids), $collections);

        // Filter collections to only those that should be visible
        $filtered_collections = array();
        foreach ($collections as $collection) {
            if (in_array($collection['id'], $ids_to_include)) {
                $filtered_collections[] = $collection;
            }
        }

        // Build tree from filtered collections
        $tree=$this->build_collection_tree($filtered_collections);

        if ($id){
            $tree=$this->get_collection_tree_by_id($tree,$id);
        }

        return $tree;
    }


    /**
     * Get all parent collections for a set of collection IDs
     * This ensures we can show the hierarchy path to accessible collections
     * 
     * @param array $collection_ids Array of collection IDs
     * @param array $all_collections All collections array
     * @return array Array of collection IDs including parents
     */
    private function get_parent_chain_for_collections($collection_ids, $all_collections)
    {
        $ids_to_include = $collection_ids;
        
        // Create lookup array for quick parent finding
        $collection_lookup = array();
        foreach ($all_collections as $collection) {
            $collection_lookup[$collection['id']] = $collection;
        }

        // For each accessible collection, walk up to find all parents
        foreach ($collection_ids as $cid) {
            $current_id = $cid;
            
            // Walk up the parent chain
            while (isset($collection_lookup[$current_id])) {
                $current = $collection_lookup[$current_id];
                
                // Add current collection to the list
                if (!in_array($current_id, $ids_to_include)) {
                    $ids_to_include[] = $current_id;
                }
                
                // Move to parent
                if ($current['pid'] !== null && $current['pid'] != 0) {
                    $current_id = $current['pid'];
                } else {
                    break; // Reached root
                }
            }
        }

        return $ids_to_include;
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

    private function get_collection_tree_by_id($tree,$id)
    {
        foreach($tree as $branch){
            if ($branch['id']==$id){
                return $branch;
            }

            if (isset($branch['items'])){
                $result=$this->get_collection_tree_by_id($branch['items'],$id);
                if ($result){
                    return $result;
                }
            }
        }
    }




    /**
     * 
     * Copy collection
     *  - projects
     * - users + permissions
     * 
     */
    function copy($source_id,$target_id)
    {
        $source=$this->select_single($source_id);
        $target=$this->select_single($target_id);

        if (!$source){
            throw new Exception('Source collection not found');
        }

        if (!$target){
            throw new Exception('Target collection not found');
        }

        //copy projects from source to target
        $this->db->query('insert into editor_collection_projects (collection_id, sid) '.
            'select '.$target_id.' as collection_id,sid from editor_collection_projects where collection_id='.$source_id);

        //copy users from source to target
        		$this->db->query('insert into editor_collection_project_acl (collection_id, user_id, permissions) '.
		'select '.$target_id.' as collection_id,user_id,permissions from editor_collection_project_acl where collection_id='.$source_id);

        return true;
    }


     /**
     * 
     * move collection
     *  
     * - set the PID for source to target
     * 
     */
    function move($source_id,$target_id)
    {
        $source=$this->select_single($source_id);
        $target=$this->select_single($target_id);

        if (!$source){
            throw new Exception('Source collection not found');
        }

        if ($target_id>0 && !$target){
            throw new Exception('Target collection not found');
        }

        if ($source_id==$target_id){
            throw new Exception('Source and target collection cannot be the same');
        }

        //check if title + pid is unique
        if ($this->is_unique($source['title'],$target_id)){
            throw new Exception('Collection already exists');
        }

        //update source collection
        $this->db->where('id',$source_id);
        $this->db->update('editor_collections',array('pid'=>$target_id));

        //rebuild tree
        $this->Collection_tree_model->rebuild_tree();
        return true;
    }

    /**
     * 
     * Get all collections with user's permission levels
     * Returns collections user can access with their permission levels
     * 
     * @param int $user_id User ID to check permissions for
     * @return array Array of collections with permission information
     */
    function get_collections_with_user_permissions($user_id)
    {
        // Get all collections
        $this->db->select('editor_collections.*, users.username as owner_username');
        $this->db->from('editor_collections');
        $this->db->join('users', 'users.id = editor_collections.created_by', 'left');
        $this->db->order_by('editor_collections.title', 'ASC');
        $collections = $this->db->get()->result_array();

        // Get user's collection ACL permissions
        $this->db->select('collection_id, permissions');
        $this->db->from('editor_collection_acl');
        $this->db->where('user_id', $user_id);
        $user_permissions = $this->db->get()->result_array();

        // Create lookup array for user permissions
        $permission_lookup = array();
        foreach ($user_permissions as $perm) {
            $permission_lookup[$perm['collection_id']] = $perm['permissions'];
        }

        // Build result with permission information
        $result = array();
        foreach ($collections as $collection) {
            $collection_id = $collection['id'];
            $permissions = isset($permission_lookup[$collection_id]) ? $permission_lookup[$collection_id] : 'view';
            
            // Check if user is collection owner
            if ($collection['created_by'] == $user_id) {
                $permissions = 'admin';
            }

            $result[] = array(
                'id' => $collection_id,
                'title' => $collection['title'],
                'description' => $collection['description'],
                'pid' => $collection['pid'],
                'created_by' => $collection['created_by'],
                'created' => $collection['created'],
                'changed' => $collection['changed'],
                'owner_username' => $collection['owner_username'],
                'permissions' => $permissions
            );
        }

        return $result;
    }

}