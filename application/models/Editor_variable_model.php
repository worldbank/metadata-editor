<?php


/**
 * 
 * Editor variable model
 * 
 */
class Editor_variable_model extends ci_model {
 
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
    }


	function chunk_reader_generator($sid,$start_uid=0,$limit=50): iterator
    {
        $last_row_uid=$start_uid;
        $max_vars=30000;
        $k=0;

        do {
            $variables=$this->chunk_read($sid,$last_row_uid,$limit);
            $k++;

            if( ($k*$limit) > $max_vars){
                break;
            }

            foreach ($variables as $variable) {
                $last_row_uid=$variable['uid'];
                yield $variable;
            }

        } while ($variables !== null);
    }

    /**
     * 
     * Select all variables using a chunked reader
     * 
     */
    function chunk_read($sid,$start_uid=0, $limit=100)
    {
        $this->db->select("uid,sid,metadata");
        $this->db->order_by('uid');
        $this->db->limit($limit);
        $this->db->where("sid",$sid);

        if ($start_uid>0){
            $this->db->where('uid >',$start_uid,false);
        }        
        
        $variables=$this->db->get("editor_variables")->result_array();

        foreach($variables as $key=>$variable){            
            $variables[$key]['metadata']=$this->Editor_model->decode_metadata($variable['metadata']);
            //$variables[$key]=$this->map_variable_fields($variables[$key]);
        }

        return $variables;
    }

    

}    