<?php
defined('BASEPATH') OR exit('No direct script access allowed');



/*
CREATE TABLE `admin_metadata_projects` (
  `id` int NOT NULL,
  `sid` int DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
*/

/**
 * 
 * Add/remove admin metadata templates to projects
 * 
 */
  class Admin_metadata_projects_model extends CI_Model {

	private $fields=array(
        'sid',
        'template_id'
	);
 
    public function __construct()
    {
        parent::__construct();
		//$this->output->enable_profiler(TRUE);
    }


    function find($sid, $template_id)
    {
        $this->db->select('id');
        $this->db->where('template_id',$template_id);
        $this->db->where('sid',$sid);
        $this->db->limit(1);
        $result=$this->db->get('admin_metadata_projects')->row_array();

        if (!$result){
            return false;
        }

        if (count($result)>0){
            return $result['id'];
        }
    }
    

    function exists($sid, $template_id)
    {
        $this->db->select('id');
        $this->db->where('template_id',$template_id);
        $this->db->where('sid',$sid);
        $result=$this->db->get('admin_metadata_projects')->result_array();
        return count($result)>0;
    }

    function attach($sid, $template_id)
	{
        $data['template_id']=$template_id;
        $data['sid']=$sid;

        $exists=$this->find($sid, $template_id);
        if ($exists){
            return $exists;
        }
        
		$this->db->insert('admin_metadata_projects', $data); 
		return $this->db->insert_id();
	}



    function delete($sid, $template_id)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('sid',$sid);
        return $this->db->delete('admin_metadata_projects');
    }


    function delete_all_by_project($sid)
    {
        $this->db->where('sid',$sid);
        return $this->db->delete('admin_metadata_projects');
    }



    /**
     * 
     * check if a template is attached to a project
     * 
     * 
     * 
     */
    function is_attached($sid, $template_id)
    {
        $this->db->select('sid');
        $this->db->where('sid',$sid);
        $result=$this->db->get('admin_metadata_projects')->result_array();
        return count($result)>0;
    }

}