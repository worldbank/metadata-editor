<?php



/**
 * 
 * Collections
 * 
 */
class Collection_model extends CI_Model {


    private $fields=array('title','description','created_by','created','changed','changed_by');

    function __construct()
    {
        parent::__construct();
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

    function delete($id)
    {
        $this->db->where('id',$id);
        $this->db->delete('editor_collections');
    }

    function insert($data)
    {
        $data=array_intersect_key($data,array_flip($this->fields));
        $this->db->insert('editor_collections',$data);
        return $this->db->insert_id();
    }

    function update($id,$data)
    {
        $data=array_intersect_key($data,array_flip($this->fields));
        
        if ($data['id']){
            unset($data['id']);
        }

        $this->db->where('id',$id);
        $this->db->update('editor_collections',$data);
    }


    //add project to collection
    //remove project from collection
    //get collection projects
    //get collection projects count
    //get collection projects by user
    //get collection projects by user count

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
    function get_projects($collection_id)
    {
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



}