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

    function delete($sid,$uid_list)
    {
        $this->db->where('sid',$sid);
        $this->db->where_in('uid',$uid_list);
        $this->db->delete('editor_variables');
        return 
        [
            'rows'=>$this->db->affected_rows(),
            'query'=>$this->db->last_query()
        ];
    }


    function bulk_upsert($sid,$fileid,$variables)
	{
		$valid_data_files=$this->Editor_datafile_model->list($sid);
		$max_variable_id=$this->get_max_vid($sid);
			
		foreach($variables as $idx=>$variable)
        {
			$max_variable_id=$max_variable_id+1;
			$variable['file_id']=$fileid;
			$variable['vid']= 'V'.$max_variable_id;

			if (!in_array($fileid,$valid_data_files)){
				throw new Exception("Invalid `file_id`: valid values are: ". implode(", ", $valid_data_files ));
			}

			//check if variable already exists
			$uid=$this->uid_by_name($sid,$variable['file_id'],$variable['name']);
			$variable['fid']=$variable['file_id'];

			$this->validate($variable);
			$variable['metadata']=$variable;

			if($uid){
				$this->update($sid,$uid,$variable);
			}
			else{						
				$this->insert($sid,$variable);
			}

			$result[]=$variable['vid'];
		}

		return $result;
	}


	function get_max_vid($sid)
	{
        $this->db->select("vid");
        $this->db->where("sid",$sid);
        $result=$this->db->get("editor_variables")->result_array();

		if (!$result){
			return 0;
		}

		$max=0;
		foreach($result as $row)
		{
			$val=substr($row['vid'],1);
			if (strtoupper(substr($row['vid'],0,1))=='V' && is_numeric($val)){
				if ($val >$max){
					$max=$val;
				}
			}
		}

		return $max;
	}



    /**
     * 
     * 
     * get all variables attached to a study
     * 
     * @metadata_detailed = true|false - include detailed metadata
     * 
     **/
    function select_all($sid,$file_id=null,$metadata_detailed=false)
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

        $variables=$this->db->get("editor_variables")->result_array();

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
                    $var_metadata=$this->Editor_model->decode_metadata($variable['metadata']);
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


	function uid_by_vid($sid,$vid)
    {
        $this->db->select("uid");
        $this->db->where("sid",$sid);
        $this->db->where("vid",$vid);

        $variable=$this->db->get("editor_variables")->row_array();

        if ($variable){
            return $variable['uid'];
        }

        return false;
    }

	function uid_by_name($sid,$fid,$var_name)
    {
        $this->db->select("uid");
        $this->db->where("sid",$sid);
		$this->db->where("fid",$fid);
        $this->db->where("name",$var_name);

        $variable=$this->db->get("editor_variables")->row_array();

        if ($variable){
            return $variable['uid'];
        }

        return false;
    }


	/**
	 * 
	 * Get variable by UID
	 * 
	 */
	function variable($sid,$uid,$metadata_detailed=false)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
		$this->db->where("uid",$uid);
        $variable=$this->db->get("editor_variables")->row_array();

		if(isset($variable['metadata']) && $metadata_detailed==true){
			$variable['metadata']=$this->Editor_model->decode_metadata($variable['metadata']);
		}
		return $variable;
    }


    function variables_by_name($sid,$fid,$var_names,$metadata_detailed=false)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where("fid",$fid);
		$this->db->where_in("name",$var_names);
        $variables=$this->db->get("editor_variables")->result_array();

		$exclude_metadata=[];
        if ($metadata_detailed==true){
            foreach($variables as $key=>$variable){
                if(isset($variable['metadata'])){
                    $var_metadata=$this->Editor_model->decode_metadata($variable['metadata']);
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
	 * 
	 * Validate data file
	 * @options - array of fields
	 * @is_new - boolean - for new records
	 * 
	 **/
	function validate($options,$is_new=true)
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
            $options['metadata']=$this->Editor_model->encode_metadata($options['metadata']);
        }

        $this->db->insert("editor_variables",$options);
        $insert_id=$this->db->insert_id();
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
            $options['metadata']=$this->Editor_model->encode_metadata($options['metadata']);
        }

        $this->db->where('sid',$sid);
        $this->db->where('uid',$uid);
        $this->db->update("editor_variables",$options);
        return $uid;
    }
    

}    