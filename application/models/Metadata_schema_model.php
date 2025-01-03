<?php

/*
CREATE TABLE `metadata_schemas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agency` varchar(200) DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `version` varchar(100) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` varchar(300) DEFAULT NULL,
  `schema` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `changed_by` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_id` (`agency`,`name`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


  */
  
  class Metadata_schema_model extends CI_Model {

	private $fields=array(
		'agency',
        'name',
        'version',
        'title',
        'description',
        'schema',
        'created_by',
        'changed_by',
        'created',
        'changed'
	);

    private $required_fields=array(
        'agency',
        'name',
        'version',
        'title',        
        'schema'
    );
    
    //update field
    private $update_fields=array(
        'agency',
        'name',
        'version',
        'title',
        'description',
        'schema',
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
        $fields=array(
            'metadata_schemas.id',
            'metadata_schemas.agency',
            'metadata_schemas.name',
            'metadata_schemas.version',
            'metadata_schemas.title',
            'metadata_schemas.description',
            'metadata_schemas.created_by',
            'metadata_schemas.created',
            'metadata_schemas.changed',
            'metadata_schemas.changed_by',
            'users.username as cr_username',
            'users1.username as ch_username'
        );

        $this->db->select(implode(",",$fields));
        $this->db->join('users','users.id=metadata_schemas.created_by','left');
        $this->db->join('users as users1','users1.id=metadata_schemas.changed_by','left');
        $this->db->order_by('title','ASC');
        $this->db->order_by('created','DESC');        

        if ($limit){
            $this->db->limit($limit,$offset);
        }

        $result=$this->db->get('metadata_schemas')->result_array();

        foreach($result as $k=>$row){
            $result[$k]['_links']=array(
                'self'=>site_url('api/admin-metadata/schema/'.$row['agency'].":".$row['name'].":".$row['version'])
            );
        }

        return array(
            'count'=>$this->select_all_count(),
            'offset'=>$offset,
            'limit'=>$limit,
            'result'=>$result
        );
    }

    function select_all_count()
    {
        $this->db->select('count(*) as count');
        $result=$this->db->get('metadata_schemas')->row_array();
        return $result['count'];
    }


    function select_single_by_id($id)
    {
        $this->db->select('*');
        $this->db->where('id',$id);
        $row=$this->db->get('metadata_schemas')->row_array();

        if (isset($row['schema'])){
            $row['schema']=json_decode($row['schema'],true);
        }

        return $row;
    }

    function select_single_by_urn($schema_urn)
    {
        $id=$this->get_id_by_urn($schema_urn);
        if ($id){
            return $this->select_single_by_id($id);
        }
        throw new Exception("Schema not found");
    }

    function select_single($agency,$name,$version)
    {
        $this->db->select('*');
        $this->db->where('agency',$agency);
        $this->db->where('name',$name);
        $this->db->where('version',$version);
        $row=$this->db->get('metadata_schemas');

        if ($row->num_rows()>0){
            $row=$row->row_array();
            if ($row['schema']){
                $row['schema']=json_decode($row['schema'],true);
            }
            return $row;
        }
    }
    
    function schema_exists($agency,$name,$version)
    {
        $row=$this->select_single($agency,$name,$version);

        if (!$row){
            return false;
        }

        return true;
    }
    

    /**
     * 
     * Get Schema ID by URN string key
     * 
     * @param string $schema_urn
     * @return int
     * 
     * URN format: agency:name:version
     * 
     */
    function get_id_by_urn($schema_urn)
    {
        $parts=explode(":",$schema_urn);
        if (count($parts)!=3){
            throw new Exception("Invalid URN format");
        }

        $agency=$parts[0];
        $name=$parts[1];
        $version=$parts[2];

        $row=$this->select_single($agency,$name,$version);

        if ($row){
            return $row['id'];
        }

        return null;        
    }

    function get_urn_by_id($id)
    {
        $row=$this->select_single_by_id($id);
        if ($row){
            return $row['agency'].":".$row['name'].":".$row['version'];
        }
        return null;
    }
     

    function delete_by_id($id)
    {

        //check if schema is in use
        if ($this->get_schema_usage($id)){
            throw new Exception("Schema is in use, cannot be deleted");
        }

        $this->db->where('id',$id);
        return $this->db->delete('metadata_schemas');
    }

    function get_schema_usage($schema_id)
    {
        $this->db->select('id');
        $this->db->where('schema_id',$schema_id);
        $result=$this->db->get('metadata_types')->result_array();
        return count($result)>0;
    }


    function create($options)
    {
        $user_id=isset($options['user_id']) ? $options['user_id'] : null;
        $data=$this->validate_form_data($options);
        $data['created_by']=$user_id;

        if (!isset($data['created'])){
            $data['created']=date("U");
        }

        //check if schema exists
        if ($this->schema_exists($data['agency'],$data['name'],$data['version'])){
            throw new Exception("Schema already exists");
        }

        return $this->insert($data);
    }

    function update($id,$options)
    {
        $user_id=isset($options['user_id']) ? $options['user_id'] : null;
        $data=$this->validate_form_data($options);        
        $data=array_intersect_key($data,array_flip($this->update_fields));
        
        if (isset($data['id'])){
            unset($data['id']);
        }

        $data['changed_by']=$user_id;

        if (!isset($data['changed'])){
            $data['changed']=date("U");
        }

        //check if schema exists
        $schema_id=$this->get_id_by_urn($data['agency'].":".$data['name'].":".$data['version']);
        if ($schema_id && $schema_id!=$id){
                throw new Exception("Failed: Schema already exists");
        }

        $this->db->where('id',$id);
        return $this->db->update('metadata_schemas',$data);
    }

    function validate_form_data($options)
    {        
        if (isset($options['schema'])){
            $options['schema']=json_encode($options['schema']);
        }

        $this->load->library('form_validation');
        $this->form_validation->set_data($options);
        $this->form_validation->set_rules('agency', 'Agency', 'required|alpha_dash|max_length[50]');
        $this->form_validation->set_rules('name', 'Name', 'required|alpha_dash|max_length[100]');
        $this->form_validation->set_rules('version', 'Version', 'required|max_length[15]|validate_semantic_version');
        $this->form_validation->set_rules('title', 'Title', 'required');
        $this->form_validation->set_rules('schema', 'Schema', 'validate_json_value');

        if ($this->form_validation->run() == FALSE)
        {
            throw new ValidationException("VALIDATION_FAILED", $this->format_schema_errors($this->form_validation->error_array()));
        }
        else{

            //validate schema
            if (isset($options['schema']) && json_validate($options['schema'])==false){
                throw new Exception("Schema definition is invalid");
            }
        }

        $data=array(
            'agency'=>$options['agency'],
            'name'=>$options['name'],
            'version'=>$options['version'],
            'title'=>$options['title'],
            'description'=>isset($options['description']) ? $options['description'] : null,
            'schema'=>$options['schema']            
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

		$this->db->insert('metadata_schemas', $data); 
		return $this->db->insert_id();
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