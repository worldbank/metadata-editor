<?php

/*
CCREATE TABLE `admin_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int DEFAULT NULL,
  `sid` int DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meta_unq` (`template_uid`,`sid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


  */
  
  class Admin_metadata_model extends CI_Model {

	private $fields=array(
		'template_id',
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
     * Return all admin metadata templates by a project and/or by template uid
     * 
     * @output_format: 'raw' or 'metadata'
     * @user_id: user id - if set, return metadata accessible for this user
     * 
     */
    function get_project_metadata($sid, $template_id=null, $output_format='raw',$user_id=null)
    {
        $fields=array(            
            'editor_templates.uid as template_uid',
            'editor_templates.name',            
            'admin_metadata.*'
        );

        $this->db->select($fields);
        $this->db->join('editor_templates','editor_templates.id=admin_metadata.template_id','left');
        
        if ($user_id){
            $this->db->join('admin_metadata_acl','admin_metadata_acl.template_id=editor_templates.id','left');
            $this->db->where('admin_metadata_acl.user_id',$user_id);
        }

        if ($template_id){
            $this->db->where('template_id',$metadata_type_id);
        }
        
        $this->db->where('sid',$sid);
        $result=$this->db->get('admin_metadata')->result_array();

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

                $output[$row['template_uid']]=json_decode($row['metadata'],true);
            }
        }
       
        return $output;

    }

    /**
     * 
     * 
     * Search 
     * 
     * - returns multiple admin metadata
     * - @template_id - template id
     * - @options {
     *      offset: 0,
     *      limit: 10,
     *      templates: [1,2,3]
     *      project_id:1 or IDNO
     * }
     * 
     * 
     */
    function search($options=array())
    {
        $offset=isset($options['offset']) ? $options['offset'] : 0;
        $limit=isset($options['limit']) ? $options['limit'] : 50;
        $templates_id_list=isset($options['templates']) ? $options['templates'] : null;

        $this->db->select('editor_templates.uid as template_uid, editor_templates.name as template_name, admin_metadata.sid, admin_metadata.metadata, admin_metadata.created, admin_metadata.changed, users1.username as cr_username, users2.username as ch_username');
        $this->db->join('editor_templates','editor_templates.id=admin_metadata.template_id');
        $this->db->join('users as users1','users1.id=admin_metadata.created_by','left');
        $this->db->join('users as users2','users2.id=admin_metadata.changed_by','left');
        
        if (is_array($templates_id_list) && count($templates_id_list)>0){
            $this->db->where_in('template_id',$templates_id_list);
        }

        if (isset($options['project_id'])){
            $this->db->where('admin_metadata.sid',$options['project_id']);
        }
                
        $this->db->limit($limit,$offset);
        $result=$this->db->get('admin_metadata')->result_array();

        if (isset($result))
        {
            foreach($result as $key=>$row){
                if (isset($row['metadata'])){
                    $result[$key]['metadata']=json_decode($row['metadata'],true);
                }
            }
        }

        return [
            'total'=>0,//$this->total_count($template_id),
            'data'=>$result
        ];
    }

    function total_count($template_id)
    {
        $this->db->select('count(*) as total');
        $this->db->where('template_id',$template_id);
        $result=$this->db->get('admin_metadata')->row_array();
        return $result['total'];
    }


    function select_single($template_id,$sid)
    {
        $this->db->select('editor_templates.uid as template_uid, editor_templates.name as template_name, admin_metadata.*, users1.username as cr_username, users2.username as ch_username');
        $this->db->join('editor_templates','editor_templates.id=admin_metadata.template_id');
        $this->db->join('users as users1','users1.id=admin_metadata.created_by','left');
        $this->db->join('users as users2','users2.id=admin_metadata.changed_by','left');
        $this->db->where('template_id',$template_id);
        
        $this->db->where('sid',$sid);
        $result=$this->db->get('admin_metadata')->row_array();

        if (isset($result))
        {
            if (isset($result['metadata'])){
                $result['metadata']=json_decode($result['metadata'],true);
            }
        }

        return $result;
    }


    function exists($template_id,$sid)
    {
        $this->db->select('id');
        $this->db->where('template_id',$template_id);
        $this->db->where('sid',$sid);
        $result=$this->db->get('admin_metadata')->result_array();
        return count($result)>0;
    }

    function get_project_id_by_template($template_id,$sid)
    {
        $this->db->select('id');
        $this->db->where('template_id',$template_id);
        $this->db->where('sid',$sid);
        $result=$this->db->get('admin_metadata')->row_array();

        if (isset($result) && isset($result['id'])){
            return $result['id'];
        }        
    }

    function insert($template_id, $sid, $data)
	{
        $user_id=isset($data['user_id']) ? $data['user_id'] : null;
		$data=array_intersect_key($data,array_flip($this->fields));
        $data['template_id']=$template_id;
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

		$this->db->insert('admin_metadata', $data); 
		return $this->db->insert_id();
	}

    function upsert($template_id, $sid, $data)
    {
        if ($this->exists($template_id,$sid)){

            $this->metadata_edit_audit_log(
                $template_id, 
                $sid, 
                $data['metadata'], 
                isset($data['user_id']) ? $data['user_id'] : null);

            $this->update($template_id,$sid,$data);            
        }
        else{
            $this->insert($template_id,$sid,$data);
        }
    }


    function update($template_id, $sid, $data)
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

        $this->db->where('template_id',$template_id);
        $this->db->where('sid',$sid);
        return $this->db->update('admin_metadata',$data);
    }

    function delete($template_id, $sid)
    {
        $this->db->where('template_id',$template_id);
        $this->db->where('sid',$sid);
        return $this->db->delete('admin_metadata');
    }


    function delete_all_by_project($sid)
    {
        $this->db->where('sid',$sid);
        return $this->db->delete('admin_metadata');
    }

    /**
	 * 
	 * 
	 * Get admin metadata templates [custom only]
	 * 
	 * 
	 */
	function get_admin_metadata_templates_by_acl($user_id, $uid=null)
	{
		$fields=array(
            "editor_templates.id",
            "editor_templates.uid",
            "editor_templates.data_type", 
            "editor_templates.lang", 
            "editor_templates.name", 
            "editor_templates.version", 
            "editor_templates.organization", 
            "editor_templates.author", 
            "editor_templates.description", 
            "editor_templates.created",
            "editor_templates.created_by", 
            "editor_templates.changed",
            "editor_templates.changed_by",
            "editor_templates.owner_id"        
        );
		$fields[]="'custom' as template_type";
		$this->db->select($fields);
		$this->db->order_by('name','ASC');
		$this->db->order_by('changed','DESC');
		$this->db->where("data_type","admin_meta");        

        $this->db->join('users','users.id=editor_templates.created_by','left');
        $this->db->join('users as users1','users1.id=editor_templates.changed_by','left');
        $this->db->join('admin_metadata_acl','admin_metadata_acl.template_id=editor_templates.id');
        $this->db->where('admin_metadata_acl.user_id',$user_id);
        
        if ($uid){
            $this->db->where('editor_templates.uid',$uid);
        }
        
		$result= $this->db->get('editor_templates')->result_array();
		return $result;
	}

	function get_admin_metadata_template_by_acl($user_id, $uid)
    {
        $template=$this->get_admin_metadata_templates_by_acl($user_id,$uid);

		if (!$template){
			throw new Exception("Template not found: " .$uid);
		}

		return $this->Editor_template_model->get_template_by_uid($uid);

	}


    /**
     * 
     * Keep audit log of metadata edits
     * 
     * 
     */
    function metadata_edit_audit_log($template_id, $sid, $new_metadata, $user_id)
    {
        //get original metadata
        $project=$this->select_single($template_id,$sid);

        if (!$project){
            return;
        }

        $original_metadata=$project['metadata'];

        if(empty($original_metadata)){
            return;
        }

        //generate diff
        $diff=$this->Editor_model->get_metadata_diff($metadata_original_=$original_metadata,$new_metadata, $ignore_errors=true);

        if (empty($diff)){
            return;
        }
        
        $this->audit_log->log_event(
            $obj_type_='admin-metadata',
            $obj_id_=$project['id'],
            $action='update', 
            $metadata_=$diff, 
            $user_id_=$user_id, 
            $obj_ref_id=$sid
        );        
    }



    /**
     * 
     * Get edit history
     * 
     */
    function history($template_id,$project_id, $limit=10, $offset=0)
    {
        //get admin_metadata id
        $admin_metadata_id=$this->get_project_id_by_template($template_id,$project_id);

        //get history from audit log
        return $this->Audit_log_model->get_history(
            array(
                'obj_type'=>'admin-metadata',
                'obj_id'=>$admin_metadata_id,
                'obj_ref_id'=>$project_id
            )
            ,$limit, $offset);
    }


    

}