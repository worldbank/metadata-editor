<?php
class Variable_model extends CI_Model {



    public function __construct()
    {
        parent::__construct();
        $this->load->model("Dataset_model");

    }

    public function remove_all_variables($sid)
    {
        $this->db->where("sid",$sid);
        $this->db->delete("variables");
    }

    public function remove_variable($sid,$fid, $vid)
    {
        $this->db->where("sid",$sid);
        $this->db->where("fid",$fid);
        $this->db->where("vid",$vid);
        return $this->db->delete("variables");
    }

    //get a single variable
    function select_single($vid)
    {
        $this->db->select("*");
        $this->db->where("uid",$vid);
        $variable=$this->db->get("variables")->row_array();

        if(isset($variable['metadata'])){                   
            $variable['metadata']=$this->Dataset_model->decode_metadata($variable['metadata']);
            $variable=$this->map_variable_fields($variable);
        }

        return $variable;
    }

    /**
     * 
     * Select all variables
     * 
     */
    function select_all($sid)
    {
        $this->load->model("Dataset_model");
        $this->db->select("sid,metadata");
        $this->db->where("sid",$sid);
        $variables=$this->db->get("variables")->result_array();

        foreach($variables as $key=>$variable){            
            $variables[$key]['metadata']=$this->Dataset_model->decode_metadata($variable['metadata']);
            $variable=$this->map_variable_fields($variable);
        }

        return $variables;
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
        $this->load->model("Dataset_model");
        $this->db->select("uid,sid,metadata");
        $this->db->order_by('uid');
        $this->db->limit($limit);
        $this->db->where("sid",$sid);

        if ($start_uid>0){
            $this->db->where('uid >',$start_uid,false);
        }        
        
        $variables=$this->db->get("variables")->result_array();

        foreach($variables as $key=>$variable){            
            $variables[$key]['metadata']=$this->Dataset_model->decode_metadata($variable['metadata']);
            $variables[$key]=$this->map_variable_fields($variables[$key]);
        }

        return $variables;
    }


    /**
     * 
     * Get a single variable vid and Survey ID
     * 
     */
    function get_var_by_vid($sid,$vid)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where("vid",$vid);
        
        $variable=$this->db->get("variables")->row_array();

        if(isset($variable['metadata'])){                 
            $variable['metadata']=$this->Dataset_model->decode_metadata($variable['metadata']);
            $variable=$this->map_variable_fields($variable);
        }

        return $variable;
    }

    /**
     * 
     * 
     * Get a single variable by variable UID
     * 
     */
    function get_var_by_uid($sid,$uid)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where("uid",$uid);

        $variable=$this->db->get("variables")->row_array();

        if(isset($variable['metadata'])){            
            $variable['metadata']=$this->Dataset_model->decode_metadata($variable['metadata']);
            $variable=$this->map_variable_fields($variable);
        }

        return $variable;
    }


    //get a single variable
    function get_by_var_id($sid, $file_id=null, $var_id)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where("vid",$var_id);

        if($file_id){
            $this->db->where("fid",$file_id);
        }

        $variable=$this->db->get("variables")->row_array();

        if(isset($variable['metadata'])){            
            $variable['metadata']=$this->Dataset_model->decode_metadata($variable['metadata']);
            $variable=$this->map_variable_fields($variable);
        }

        return $variable;
    }

    //get multiple variables by VIDs
    function get_batch_variable_metadata($sid, $file_id=null, $vid_arr=array())
    {
        if(empty($vid_arr)){
            return false;
        }

        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where_in("vid",$vid_arr);

        if($file_id){
            $this->db->where("fid",$file_id);
        }

        $variables=$this->db->get("variables")->result_array();

        foreach($variables as $idx=>$variable){
            if(isset($variable['metadata'])){
                $variables[$idx]['metadata']=$this->Dataset_model->decode_metadata($variable['metadata']);
                $variables[$idx]=$this->map_variable_fields($variables[$idx]);
            }
        }

        return $variables;
    }


    function get_uid_by_vid($sid,$vid)
    {
        $this->db->select("uid");
        $this->db->where("sid",$sid);
        $this->db->where("vid",$vid);

        $variable=$this->db->get("variables")->row_array();

        if ($variable){
            return $variable['uid'];
        }

        return false;
    }


    /**
     * 
     * 
     * get all variables attached to a study
     * 
     * @metadata_detailed = true|false - include detailed metadata
     * 
     **/
    function list_by_dataset($sid,$file_id=null,$metadata_detailed=false)
    {
        if ($metadata_detailed==true){
            $fields="uid,sid,fid,vid,name,labl,metadata";
        }else{
            $fields="uid,sid,fid,vid,name,labl";
        }
        
        $this->db->select($fields);
        $this->db->where("sid",$sid);

        if($file_id){
            $this->db->where("fid",$file_id);
        }

        $variables=$this->db->get("variables")->result_array();

        /*$exclude_metadata=array(
            'var_format',
            'var_sumstat',
            'var_val_range',
            'loc_start_pos',
            'loc_end_pos',
            'loc_width',
            'loc_rec_seg_no',
        );*/

        $exclude_metadata=[];

        if ($metadata_detailed==true){
            foreach($variables as $key=>$variable){
                if(isset($variable['metadata'])){
                    $var_metadata=$this->Dataset_model->decode_metadata($variable['metadata']);
                    unset($variable['metadata']);
                    foreach($exclude_metadata as $ex){
                        if (array_key_exists($ex, $var_metadata)){
                            unset($var_metadata[$ex]);
                        }
                    }
                    if (isset($variable['var_catgry']['stats'])){
                        unset($variable['var_catgry']['stats']);
                    }
                    $variables[$key]=array_merge($variable,$var_metadata);
                }
            }
        }

        return $variables;
    }


    /**
     * 
     * Get all files by dataset
     * 
     * @sid - int - dataset ID
     * @file_id - string - File ID using the format - F1, F2
     * 
     */
    function get_all_by_file($sid, $file_id)
    {
        $this->db->select("uid,vid,name,labl,qstn");
        $this->db->where("sid",$sid);
        $this->db->where("fid",$file_id);
        $this->db->order_by("uid");
        return $this->db->get("variables")->result_array();
    }

    /**
     * 
     * Paginate variables by data
     * 
     * @sid - int - dataset ID
     * @file_id - string - File ID using the format - F1, F2
     * @limit - number of records
     * @offset - starting row position
     * 
     */
    function paginate_file_variables($sid, $file_id, $limit=null,$offset=0)
    {
        $this->db->select("uid,vid,name,labl,qstn");
        $this->db->where("sid",$sid);
        $this->db->where("fid",$file_id);
        $this->db->order_by("uid");

        if (is_numeric($limit) && $limit>0){
            $this->db->limit($limit, (int)$offset);
        }

        return $this->db->get("variables")->result_array();
    }

    /**
     * 
     * Get variables count by a data file
     * 
     */
    function get_file_variables_count($sid, $file_id)
    {
        $this->db->where("sid",$sid);
        $this->db->where("fid",$file_id);
        $this->db->from("variables");
        return $this->db->count_all_results(); 
    }


    /**
     * 
     * get variables count by sid
     */
    function get_variables_count($sid)
    {
        $this->db->where('sid',$sid);
        $this->db->from("variables");
        return $this->db->count_all_results();        
    }


    /**
     * 
     * 
     * insert new variable
     * 
     * 
     */
    public function insert($sid,$options)
    {
        $valid_fields=array(
            'name',
            'labl',
            'qstn',
            'catgry',
            'keywords',
            'sid',
            'fid',
            'vid',
            'metadata'
        );

        foreach($options as $key=>$value){
            if(!in_array($key,$valid_fields)){
                unset($options[$key]);
            }
        }

        $options['sid']=$sid;

        //metadata
        if(isset($options['metadata'])){
            $options=$this->map_variable_fields($options);
            $options['metadata']=$this->Dataset_model->encode_metadata($options['metadata']);
        }

        $this->db->insert("variables",$options);
        $insert_id=$this->db->insert_id();
        //$this->update_survey_timestamp($sid);
        return $insert_id;
    }

    public function update($sid,$uid,$options)
    {
        $valid_fields=array(
            'name',
            'labl',
            'qstn',
            'catgry',
            'keywords',
            'sid',
            'fid',
            'vid',
            'metadata'
        );

        foreach($options as $key=>$value){
            if(!in_array($key,$valid_fields)){
                unset($options[$key]);
            }
        }

        $options['sid']=$sid;

        //metadata
        if(isset($options['metadata'])){
            $options=$this->map_variable_fields($options);
            $options['metadata']=$this->Dataset_model->encode_metadata($options['metadata']);
        }

        $this->db->where('sid',$sid);
        $this->db->where('uid',$uid);
        $this->db->update("variables",$options);
        //$this->update_survey_timestamp($sid);
        return $uid;
    }

    function update_survey_timestamp($sid,$changed=null)
    {
        if(!$changed){
            $changed=date("U");
        }

        $options=array(
            'changed'=>$changed
        );

        $this->db->where("id",$sid);
        $this->db->update('surveys',$options);
    }    


    public function batch_insert($sid,$variables)
    {
        $valid_fields=array(
            'name',
            'labl',
            'qstn',
            'catgry',
            'keywords',
            'sid',
            'fid',
            'metadata',
            'vid'
        );

        //remove fields that are not in the valid_fields list
        foreach($variables as $key=>$variable)
        {
            $variables[$key]=array_intersect_key($variable,array_flip($valid_fields));
            $variables[$key]['sid']=$sid;
            if(isset($variable['metadata'])){
                $variable=$this->map_variable_fields($variable);
                $variables[$key]['metadata']=$this->Dataset_model->encode_metadata($variable['metadata']);
            }
        }

        $this->db->insert_batch('variables', $variables);
        $this->update_survey_timestamp($sid);
    }




    /**
	 * 
	 * 
	 * Validate data file
	 * @options - array of fields
	 * @is_new - boolean - for new records
	 * 
	 **/
	function validate_variable($options,$is_new=true)
	{		
		$this->load->library("form_validation");
		$this->form_validation->reset_validation();
		$this->form_validation->set_data($options);
	
		//validation rules for a new record
		if($is_new){				
			$this->form_validation->set_rules('fid', 'File ID', 'xss_clean|trim|max_length[50]|required|alpha_dash');
			$this->form_validation->set_rules('vid', 'Variable ID', 'required|xss_clean|trim|max_length[100]|alpha_dash');	
			$this->form_validation->set_rules('name', 'Variable name', 'required|xss_clean|trim|max_length[255]');	
			//$this->form_validation->set_rules('labl', 'Label', 'required|xss_clean|trim|max_length[255]');	
		}
		
		if ($this->form_validation->run() == TRUE){
			return TRUE;
		}
		
		//failed
		$errors=$this->form_validation->error_array();
		$error_str=$this->form_validation->error_array_to_string($errors);
		throw new ValidationException("VALIDATION_ERROR: ".$error_str, $errors);
    }
    


    /**
     * 
     * 
     * Export variables
     * 
     * @variables - array of variables
     * @format - JSON | CSV
     * 
     */
    function export($variables,$format='json')
	{
        if ($format=='json'){
            return;
        }        
        
        $columns=array(
            'survey_idno',
            'sid',
            'file_id',
            'vid',
            'name',
            'labl',
            'var_intrvl',
            'var_dcml',
            'var_wgt',
            'var_start_pos',
            'var_end_pos',
            'var_width',
            'var_imputation',
            'var_security',
            'var_respunit',
            'var_qstn_preqtxt',
            'var_qstn_qstnlit',
            'var_qstn_postqtxt',
            'var_qstn_ivulnstr',
            'var_universe',
            'var_sumstat',
            'var_txt',
            'var_catgry',
            'var_codinstr',
            'var_concept',
            'var_format',
            'var_notes',
            );
        
        $filename='variables-'.date("m-d-y-his").'.csv';
        header('Content-Encoding: UTF-8');
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment;filename='.$filename);
        $fp = fopen('php://output', 'w');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        //add column names
        fputcsv($fp, $columns);

        foreach($variables as $row)
        {
            $data=array();
            foreach($columns as $col)
            {
                if (!isset($row[$col])){
                    $data[$col]='';
                    continue;
                }
                
                if(is_array($row[$col])){
                    $data[$col]=json_encode($row[$col]);
                }
                else{
                    $data[$col]=$row[$col];
                }                
            }

            fputcsv($fp, $data);
        }

        fclose($fp);
	}


    function variable_basic_info($sid,$vid)
    {
        $this->db->select("surveys.id as sid, surveys.idno, surveys.nation,variables.vid, variables.name");
        $this->db->where("sid",$sid);
        $this->db->where("vid",$vid);
        $this->db->join('surveys','surveys.id=variables.sid');
        
        $variable=$this->db->get("variables")->row_array();        
        return $variable;
    }

    /**
     * 
     * Fix for inconsistent variable schema fields
     */
    function map_variable_fields($variable)
    {
        $mappings=array(
            'var_start_pos'=>'loc_start_pos',
            'var_end_pos'=>'loc_end_pos',
            'var_width'=>'loc_width',
            'var_rec_seg_no'=>'loc_rec_seg_no',
        );

        if (isset($variable['metadata'])){
            foreach($variable['metadata'] as $key=>$value){
                //complex types e.g. repeatable array types
                if(array_key_exists($key,$mappings)){ 
                    $variable['metadata'][$mappings[$key]]=$value;
                    unset($variable['metadata'][$key]);
                }
            }
        }

        return $variable;
    }
	
}
	
