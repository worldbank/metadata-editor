<?php

/*
CREATE TABLE `metadata_types_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metadata_type_id` int DEFAULT NULL,
  `sid` int DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


  */
  
  class Metadata_type_data_model extends CI_Model {

	private $fields=array(
		'metadata_type_id',
        'sid',
        'metadata',
        'created_by',
        'changed_by',
        'created',
        'changed'
	);
 
    public function __construct()
    {
        parent::__construct();
		//$this->output->enable_profiler(TRUE);
    }


    /**
     * 
     * Return metadata by project and/or with specific metadata type
     * 
     * @output_format: 'raw' or 'metadata'
     * @user_id: user id - if set, return metadata accessible for this user
     * 
     */
    function get_project_metadata($sid, $metadata_type_id=null, $output_format='raw',$user_id=null)
    {
        $fields=array(
            'metadata_types_data.metadata_type_id',
            'metadata_types.name as metadata_type_name',
            'metadata_types_data.sid',
            'metadata_types_data.metadata',
            'metadata_types_data.created_by',
            'metadata_types_data.changed_by',
            'metadata_types_data.created',
            'metadata_types_data.changed',            
        );

        $this->db->select($fields);
        $this->db->join('metadata_types','metadata_types.id=metadata_types_data.metadata_type_id','left');
        
        if ($user_id){
            $this->db->join('metadata_types_acl','metadata_types_acl.metadata_type_id=metadata_types_data.metadata_type_id','left');
            $this->db->where('metadata_types_acl.user_id',$user_id);
        }

        if ($metadata_type_id){
            $this->db->where('metadata_type_id',$metadata_type_id);
        }
        
        $this->db->where('sid',$sid);
        $result=$this->db->get('metadata_types_data')->result_array();

        $output=array();

        if (isset($result))
        {
            if ($output_format=='raw'){
                foreach($result as $key=>$row){
                    if (isset($row['metadata'])){
                        $result[$key]['metadata']=json_decode($row['metadata'],true);
                    }
                }
                return $result;
            }

            //metadata only
            foreach($result as $key=>$row){
                if (!isset($row['metadata'])){
                    continue;
                }

                $output[$row['metadata_type_name']]=json_decode($row['metadata'],true);
            }
        }
       
        return $output;

    }

    function select_single($metadata_type_id,$sid)
    {
        $this->db->select('metadata_types_data.*, users1.username as cr_username, users2.username as ch_username');
        $this->db->join('users as users1','users1.id=metadata_types_data.created_by','left');
        $this->db->join('users as users2','users2.id=metadata_types_data.changed_by','left');
        $this->db->where('metadata_type_id',$metadata_type_id);
        
        $this->db->where('sid',$sid);
        $result=$this->db->get('metadata_types_data')->row_array();

        if (isset($result))
        {
            if (isset($result['metadata'])){
                $result['metadata']=json_decode($result['metadata'],true);
            }
        }

        return $result;
    }


    function exists($metadata_type_id,$sid)
    {
        $this->db->select('id');
        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('sid',$sid);
        $result=$this->db->get('metadata_types_data')->result_array();
        return count($result)>0;
    }

    function insert($metadata_type_id, $sid, $data)
	{
        $user_id=isset($data['user_id']) ? $data['user_id'] : null;
		$data=array_intersect_key($data,array_flip($this->fields));
        $data['metadata_type_id']=$metadata_type_id;
        $data['sid']=$sid;

		if (!isset($data['created'])){
			$data['created']=date("U");
		}

        if (!isset($data['created_by'])){
            $data['created_by']=$user_id;
        }

		if (isset($data['metadata']) && is_array($data['metadata'])){
			$data['metadata']=json_encode($data['metadata']);			
		}

		$this->db->insert('metadata_types_data', $data); 
		return $this->db->insert_id();
	}

    function upsert($metadata_type_id, $sid, $data)
    {
        if ($this->exists($metadata_type_id,$sid)){
            $this->update($metadata_type_id,$sid,$data);
        }
        else{
            $this->insert($metadata_type_id,$sid,$data);
        }
    }



    function update($metadata_type_id, $sid, $data)
    {
        $user_id=isset($data['user_id']) ? $data['user_id'] : null;
        $data=array_intersect_key($data,array_flip($this->fields));
        
        if (isset($data['id'])){
            unset($data['id']);
        }

        if (isset($data['metadata']) && is_array($data['metadata'])){
			$data['metadata']=json_encode($data['metadata']);			
		}

        if (!isset($data['changed'])){
            $data['changed']=date("U");
        }

        if (!isset($data['changed_by'])){
            $data['changed_by']=$user_id;
        }        

        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('sid',$sid);
        return $this->db->update('metadata_types_data',$data);
    }

    function delete($metadata_type_id, $sid)
    {
        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('sid',$sid);
        return $this->db->delete('metadata_types_data');
    }
    

}