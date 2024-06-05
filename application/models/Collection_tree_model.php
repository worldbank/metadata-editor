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
            note: note supported by MYSQL

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
        $sql='select ect.parent_id,ect.child_id,ect.depth,c.* from editor_collections_tree ect
        inner join editor_collections c on ect.child_id=c.id
        where ect.parent_id in (
                select c.id from editor_collections c
                inner join editor_collections_tree t on c.id = t.parent_id 
                where t.child_id in (
                        select ect.child_id from editor_collections_tree ect
                            inner join editor_collections c on ect.child_id=c.id
                            where ect.parent_id in (
                                    select c.id from editor_collections c
                                    inner join editor_collections_tree t on c.id = t.parent_id 
                                    inner join editor_collection_access eca on eca.collection_id=c.id
                                    where eca.user_id='. $this->db->escape($user_id) . '
                            )
                
                ) and c.pid is null    
        )
        order by ect.child_id,ect.parent_id,ect.id,ect.depth;';
        

        $items=$this->db->query($sql)->result_array();
        
        //create tree
        $tree=$this->build_collection_tree($items);

        return $tree;
    }

    /**
     * 
     * Return all children of a collection node
     * 
     */
    function select_collection_tree_nodes($parent_id)
    {
        /*
        select * from editor_collections_tree
        where child_id in (select child_id from editor_collections_tree where parent_id=28)
        */
        
        $this->db->select('parent_id');
        $this->db->where('child_id in (select child_id from editor_collections_tree where parent_id='. $this->db->escape($parent_id) .')');
        $result= $this->db->get('editor_collections_tree')->result_array();
        
        $output=array();
        foreach($result as $row){
            $output[]=$row['parent_id'];
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