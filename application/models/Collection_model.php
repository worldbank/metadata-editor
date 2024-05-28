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
        $items=$this->Collection_tree_model->select_collection_tree_nodes($id);

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
            $this->add_projects($collection_id,$sids);
        }
    }

    function add_projects($collection_id,$sids)
    {
        $data=array();

        foreach($sids as $sid)
        {
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
        return $this->db->count_all_results('editor_collection_access');
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
        $this->db->select('editor_collections.id,editor_collections.title');
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
        $this->db->join('editor_collection_access','editor_collections.id=editor_collection_access.collection_id');
        $this->db->where('user_id',$user_id);
        $this->db->or_where('editor_collections.created_by',$user_id);
        $result=$this->db->get('editor_collections')->result_array();
        return $result;
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

        $result=$this->db->get('editor_collection_access')->result_array();
        $output=array();
        foreach($result as $row){
            $output[$row['collection_id']]=$row['users'];
        }
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

}