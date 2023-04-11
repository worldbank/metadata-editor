<?php
class editor_variable_groups_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Editor_model");
    }

    /**
     * 
     * Remove all variable groups
     * 
     */
    public function remove_all_variable_groups($sid)
    {
        $this->db->where("sid",$sid);
        $this->db->delete("editor_variable_groups");
    }

    /**
     * 
     * Get all variable groups by dataset
     * 
     */
    function select_all($sid)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $result= $this->db->get("editor_variable_groups")->row_array();

        if($result){
            return $this->decode_metadata($result['metadata']);
        }
        
    }




    /**
     * 
     * Delete a single variable group
     * 
     */
    public function delete($sid)
    {
        $this->db->where("sid",$sid);
        $this->db->delete("editor_variable_groups");
    }


    /**
     * 
     * Insert variable group
     * 
     */
    public function insert($sid,$metadata)
    {
        $options=array(
            'sid'=>$sid,
            'metadata'=>$this->encode_metadata($metadata)
        );

        $this->db->insert("editor_variable_groups",$options);
        return $this->db->insert_id();
    }


    /**
     * 
     * Update variable group
     * 
     */
    public function update($sid,$metadata)
    {
        $options=array(            
            'metadata'=>$this->encode_metadata($metadata)
        );

        $this->db->where("sid",$sid);
        $this->db->update("editor_variable_groups",$options);
        return $this->db->affected_rows();
    }


    function upsert($sid,$metadata)
    {
        $result=$this->check_exists($sid);

        if($result){
            $this->update($sid,$metadata);
        }
        else{
            $this->insert($sid,$metadata);
        }
    }


    function check_exists($sid)
    {
        $this->db->select("sid");
        $this->db->where("sid",$sid);
        $result= $this->db->get("editor_variable_groups")->row_array();
        return $result;
    }


	//encode metadata for db storage
    public function encode_metadata($metadata_array)
    {
        return base64_encode(serialize($metadata_array));
    }


    //decode metadata to array
    public function decode_metadata($metadata_encoded)
    {
        return unserialize(base64_decode((string)$metadata_encoded));
	}

}    