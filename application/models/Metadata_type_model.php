<?php

/*
CREATE TABLE `metadata_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `title` varchar(300) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `schema_id` int NOT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


  */
  
  class Metadata_type_model extends CI_Model {

	private $fields=array(
		'name',
        'type',
        'title',
        'description',
        'schema_id',
        'created_by',
        'changed_by',
        'created',
        'changed'
	);

    private $update_fields=array(
        'name',
        'type',
        'title',
        'description',
        'schema_id',
        'changed_by',
        'changed'
    );
 
    public function __construct()
    {
        parent::__construct();
		//$this->output->enable_profiler(TRUE);
    }


    function select_all($offset=0,$limit=10)
    {
        $this->db->select('metadata_types.*,metadata_schemas.title as schema_title, metadata_schemas.name as schema_name,users.username as cr_username, users1.username as ch_username');
        $this->db->join('users','users.id=metadata_types.created_by','left');
        $this->db->join('users as users1','users1.id=metadata_types.changed_by','left');
        $this->db->join('metadata_schemas','metadata_schemas.id=metadata_types.schema_id','left');
        $this->db->order_by('metadata_types.created','DESC');
        
        //$this->db->order_by('title','ASC');

        if ($limit){
            $this->db->limit($limit,$offset);
        }

        $result=$this->db->get('metadata_types')->result_array();

        return array(
            'status'=>'success',
            'count'=>$this->select_all_count(),
            'offset'=>$offset,
            'limit'=>$limit,
            'result'=>$result
        );
    }

    function select_all_count()
    {
        $this->db->select('count(*) as count');
        $result=$this->db->get('metadata_types')->row_array();
        return $result['count'];
    }


    /**
     * 
     * Select all metadata types by user access
     * 
     */
    function select_all_by_user($user_id)
    {
        $this->db->select('metadata_types.*,metadata_schemas.title as schema_title, metadata_schemas.name as schema_name,users.username as cr_username, users1.username as ch_username');
        $this->db->join('users','users.id=metadata_types.created_by','left');
        $this->db->join('users as users1','users1.id=metadata_types.changed_by','left');
        $this->db->join('metadata_schemas','metadata_schemas.id=metadata_types.schema_id','left');
        $this->db->join('metadata_types_acl','metadata_types_acl.metadata_type_id=metadata_types.id');
        $this->db->where('metadata_types_acl.user_id',$user_id);        
        $this->db->order_by('metadata_types.created','DESC');
        $result= $this->db->get('metadata_types')->result_array();

        return array(
            'status'=>'success',
            'count'=>count($result),
            'result'=>$result
        );
    }


    /**
     * 
     * 
     * Return permissions for a single type by user
     * 
     */
    function get_user_permissions($metadata_type_id,$user_id)
    {
        $this->db->select('metadata_types_acl.permissions');
        $this->db->where('metadata_type_id',$metadata_type_id);
        $this->db->where('user_id',$user_id);
        $result=$this->db->get('metadata_types_acl')->result_array();

        $permissions_list=array('view','edit','admin');

        $permissions=array();

        if ($result){
            foreach($result as $row){
                if (in_array($row['permissions'],$permissions_list)){
                    $permissions[]=$row['permissions'];
                }
            }            
        }

        return $permissions;
    }
    


    function select_single_by_id($id)
    {
        $this->db->select('*');        
        $this->db->where('metadata_types.id',$id);
        $row=$this->db->get('metadata_types')->row_array();

        if (!$row){
            return false;
        }

        //get schema
        if ($row['schema_id']){
            $this->load->model('Metadata_schema_model');
            $schema=$this->Metadata_schema_model->select_single_by_id($row['schema_id']);
            $row['metadata_schema']=$schema;
        }

        return $row;
    }


    function select_single_by_name($name)
    {
        $this->db->select('*');
        $this->db->where('name',$name);
        $row=$this->db->get('metadata_types')->row_array();

        if ($row){
            return $this->select_single_by_id($row['id']);
        }        
    }
    
    function name_exists($name)
    {
        $row=$this->select_single_by_name($name);

        if (!$row){
            return false;
        }

        return true;
    }

    function get_id_by_name($name)
    {
        $this->db->select('id');
        $this->db->where('name',$name);
        $result=$this->db->get('metadata_types')->row_array();

        if($result){
            return $result['id'];
        }

        return false;
    }
     

    function delete_by_id($id)
    {
        if ($this->check_type_usage($id)>0){
            throw new ValidationException("TYPE_IN_USE", array(
                array('property'=>'id','message'=>'Metadata type is in use and cannot be deleted.')));
        }

        $this->db->where('id',$id);
        return $this->db->delete('metadata_types');
    }


    function check_type_usage($type_id)
    {
        $this->db->select('count(*) as count');
        $this->db->where('metadata_type_id',$type_id);
        $result=$this->db->get('metadata_types_data')->row_array();

        return $result['count'];
    }

    function create($options)
    {
        $user_id=isset($options['user_id']) ? $options['user_id'] : null;
        $data=$this->validate_form($options);
        $data['created_by']=$user_id;
        $data['created']=date("U");

        //check if name exists
        if ($this->name_exists($data['name'])){
            throw new ValidationException("NAME_EXISTS", array(array('property'=>'name','message'=>'Name already exists')));
        }

        return $this->insert($data);
    }

    function validate_form($options)
    {
        $schema_id=isset($options['schema_id']) ? $options['schema_id'] : null;
        $schema_urn=isset($options['schema_urn']) ? $options['schema_urn'] : null;        

        if (!$schema_id && !$schema_urn){
            throw new ValidationException("SCHEMA_ID: Schema ID or URN is required", 
            array(
                array('property'=>'schema_id',
                'message'=>'Schema ID or URN is required')
            ));
        }

        //get schema ID
        $this->load->model('Metadata_schema_model');

        if(isset($options['schema_id'])){
            $schema_=$this->Metadata_schema_model->select_single_by_id($schema_id);
            if(!$schema_){
                throw new ValidationException("SCHEMA_ID: Schema not found", array(array('property'=>'schema_id','message'=>'Schema not found')));
            }
        }

        if (!$schema_id && $schema_urn){
            $schema_id=$this->Metadata_schema_model->get_id_by_urn($schema_urn);
        }

        if (!$schema_id){
            throw new ValidationException("SCHEMA_URN: Schema not found", array(array('property'=>'schema_urn','message'=>'Schema not found')));
        }

        $options['schema_id']=$schema_id;

        $this->load->library('form_validation');
        $this->form_validation->set_data($options);

        $this->form_validation->set_rules('name', 'name', 'required|alpha_dash|max_length[200]');
        $this->form_validation->set_rules('title', 'Title', 'required|max_length[300]');
        $this->form_validation->set_rules('type', 'Type', 'max_length[100]');
        $this->form_validation->set_rules('schema_id', 'Schema ID', 'required');

        if ($this->form_validation->run() == FALSE){
             throw new ValidationException("VALIDATION_FAILED", $this->format_schema_errors($this->form_validation->error_array()));
        }

        $data=array(
            'name'=>$options['name'],            
            'title'=>$options['title'],
            'type'=>isset($options['type']) ? $options['type'] : null,
            'description'=>isset($options['description']) ? $options['description'] : null,
            'schema_id'=>$options['schema_id'],            
        );

        return $data;
    }


	function insert($data)
	{
		//keep only the fields that we need
		$data=array_intersect_key($data,array_flip($this->fields));

		if (!isset($data['created'])){
			$data['created']=date("U");
		}

		if (isset($data['schema']) && is_array($data['schema'])){
			$data['schema']=json_encode($data['schema']);			
		}

		$this->db->insert('metadata_types', $data); 
		return $this->db->insert_id();
	}


    function update($id,$options)
    {
        $user_id=isset($options['user_id']) ? $options['user_id'] : null;
        $data=$this->validate_form($options);
        $data=array_intersect_key($options,array_flip($this->update_fields));
        
        if (!isset($data['changed'])){
            $data['changed']=date("U");
        }

        if (!isset($options['changed_by'])){
            $data['changed_by']=$user_id;
        }

        //check for duplicate name
        if (isset($data['name'])){
            $row=$this->select_single_by_name($data['name']);
            if ($row && $row['id']!=$id){
                throw new ValidationException("NAME_EXISTS", array(array('property'=>'name','message'=>'Name already exists')));
            }
        }

        $this->db->where('id',$id);
        return $this->db->update('metadata_types',$data);
    }


    /**
     * 
     * Convert form errors to schema errors
     * 
     */
    function format_schema_errors($errors){

        $output=array();
        foreach($errors as $field=>$error){
            $output[]=array(
                'property'=>$field,
                'message'=>$error
            );
        }

        return $output;
    }


}