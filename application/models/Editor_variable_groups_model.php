<?php
class editor_variable_groups_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Editor_model");
        $this->load->library("Variable_groups_tree");
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
     * @param int $sid
     * @param mixed $unused kept for callers that pass a second argument
     * @return array
     */
    function select_all($sid, $unused=null)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $result= $this->db->get("editor_variable_groups")->row_array();

        if(!$result){
            return array();
        }

        $decoded=$this->decode_metadata($result['metadata']);
        return Variable_groups_tree::coerce($decoded);
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
            'metadata'=>$this->encode_metadata(Variable_groups_tree::normalize($metadata))
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
            'metadata'=>$this->encode_metadata(Variable_groups_tree::normalize($metadata))
        );

        $this->db->where("sid",$sid);
        $this->db->update("editor_variable_groups",$options);
        return $this->db->affected_rows();
    }


    function upsert($sid,$metadata)
    {
        $metadata=Variable_groups_tree::normalize($metadata);
        $result=$this->check_exists($sid);

        if($result){
            $this->update($sid,$metadata);
        }
        else{
            $this->insert($sid,$metadata);
        }
    }


    /**
     * Import DDI/JSON interchange (or a nested tree) and persist as UID storage.
     * Empty input clears the project's groups.
     *
     * @param int $sid
     * @param mixed $groups
     */
    function import_from_interchange($sid,$groups)
    {
        if(!is_array($groups) || $groups===array()){
            $this->delete($sid);
            return;
        }

        $this->load->model('Editor_variable_model');
        $vid_to_uid=$this->Editor_variable_model->vid_uid_list($sid);
        $tree=Variable_groups_tree::nest_from_import($groups,$vid_to_uid);

        if($tree===array()){
            $this->delete($sid);
            return;
        }

        $this->upsert($sid,$tree);
    }


    /**
     * Remove deleted variable UIDs from group membership.
     *
     * @param int $sid
     * @param array $uid_list
     */
    function remove_variable_uids($sid,$uid_list)
    {
        if(!$this->check_exists($sid)){
            return;
        }

        $updated=Variable_groups_tree::remove_uids($this->select_all($sid),$uid_list);
        $this->write_metadata($sid,$updated);
    }


    /**
     * Rewrite membership UIDs after a project/version copy.
     *
     * @param int $sid
     * @param array $uid_map old_uid => new_uid
     */
    function remap_variable_uids($sid,$uid_map)
    {
        if(!$this->check_exists($sid)){
            return;
        }

        $updated=Variable_groups_tree::remap_uids($this->select_all($sid),$uid_map);
        $this->write_metadata($sid,$updated);
    }


    /**
     * Persist a tree without re-validating (prune/remap of legacy rows).
     */
    private function write_metadata($sid,$metadata)
    {
        $this->db->where("sid",$sid);
        $this->db->update("editor_variable_groups",array(
            'metadata'=>$this->encode_metadata($metadata)
        ));
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
