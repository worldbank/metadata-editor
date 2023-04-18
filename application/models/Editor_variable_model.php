<?php


/**
 * 
 * Editor variable model
 * 
 */
class Editor_variable_model extends ci_model {

    private $fields=array(
        'name',
        'labl',
        'sid',
        'fid',
        'vid',
        'sort_order',
        'is_weight',
        'user_missings',
        'field_dtype',
        'var_wgt_id',
        'metadata'
    );
 
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

    function bulk_upsert_dictionary($sid,$fileid,$variables)
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
			$variable_info=$this->variable_by_name($sid,$fileid,$variable['name'],true);

			$variable['fid']=$fileid;

			$this->validate($variable);
			#$variable['metadata']=$variable;

			if($variable_info){
                // for existing variables, merge metadata with existing metadata
                // update only few fields e.g. sum_stats, catgry, etc
                $variable_info['metadata']=$this->update_summary_stats($sid,$variable_info['uid'],$variable_info['metadata'],$variable);
                #$variable_info['metadata']=$variable_info;
                if (!isset($variable_info['uid'])){
                    var_dump($variable_info);
                    die();
                }
                
				$this->update($sid,$variable_info['uid'],$variable_info);
			}
			else{
                $variable['metadata']=$variable;
				$this->insert($sid,$variable);
			}

			$result[]=$variable['vid'];
		}

		return $result;
    }


    function update_summary_stats($sid, $uid, $variable, $stats)
    {
        $summary_stats_elements=array(
            "var_intrvl",
            "var_valrng",
            "var_sumstat",
            "var_catgry",
            "var_format",
            "var_invalrng"
        );

        foreach($summary_stats_elements as $element)
        {
            if (isset($stats[$element])){
                $variable[$element]=$stats[$element];
            }
        }

        /*
        //update var_catgry stats keeping the value/labl pairs        
        if (isset($stats['var_catgry'])){
            $new_cat=array();
            foreach($stats['var_catgry'] as $idx=>$val){
                if (isset($val['value'])){
                    $new_cat[$val['value']]=$val;
                }
            }

            //apply to existing categories
            if (isset($variable['var_catgry'])){
                foreach($variable['var_catgry'] as $idx=>$val){
                    if (isset($val['value']) && isset($new_cat[$val['value']])){
                        $variable['var_catgry'][$idx]=$new_cat[$val['value']];
                    }
                }

                //replace categories stats
                foreach($variable['var_catgry'] as $idx=>$catgry){
                    if (array_key_exists($catgry['value'], $new_cat)){
                        if (isset($new_cat[$idx]['stats'])){
                            $variable['var_catgry'][$idx]['stats']=$new_cat[$idx]['stats'];
                        }
                    }
                }
            }
        }*/

        return $variable;
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
            $fields="uid,sid,fid,vid,name,labl,sort_order,metadata";
        }else{
            $fields="uid,sid,fid,vid,name,labl,sort_order";
        }
        
        $this->db->select($fields);
        $this->db->where("sid",$sid);
        $this->db->order_by("sort_order,uid","asc");

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

    function variable_by_name($sid,$fid,$name,$metadata_detailed=false)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where("fid",$fid);
		$this->db->where("name",$name);
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
        foreach($options as $key=>$value){
            if(!in_array($key,$this->fields)){
                unset($options[$key]);
            }
        }

        $options['sid']=$sid;

        //metadata
        if(isset($options['metadata'])){
            $core=$this->get_variable_core_fields($options['metadata']);
            $options=array_merge($options,$core);
            $options['metadata']=$this->Editor_model->encode_metadata($options['metadata']);
        }

        $this->db->insert("editor_variables",$options);
        $insert_id=$this->db->insert_id();
        return $insert_id;
    }

    public function update($sid,$uid,$options)
    {
        foreach($options as $key=>$value){
            if(!in_array($key,$this->fields)){
                unset($options[$key]);
            }
        }
                
        $options['sid']=$sid;
        

        //metadata
        if(isset($options['metadata'])){            
            $core=$this->get_variable_core_fields($options['metadata']);
            $options=array_merge($options,$core);
            $options['metadata']=$this->Editor_model->encode_metadata($options['metadata']);
        }

        $this->db->where('sid',$sid);
        $this->db->where('uid',$uid);
        $this->db->update("editor_variables",$options);
        return $uid;
    }


    function get_variable_core_fields($variable)
    {
        //fid,vid,name,labl,sort_order,user_missings,is_weight,field_dtype, field_format
        $missings=(array)array_data_get($variable,'var_invalrng.values','');
        $missings=implode(",",$missings);
        $dtype=array_data_get($variable,'var_format.type','');

        $core_fields=array(
            'fid'=>$variable['fid'],
            'vid'=>$variable['vid'],
            'name'=>$variable['name'],
            'labl'=>$variable['labl'],
            'user_missings'=>$missings,
            'is_weight'=> isset($variable['var_wgt']) ? (int)$variable['var_wgt'] : 0,
            'field_dtype'=>$dtype            
            //'field_format'=>$variable['field_format'],
        );

        return $core_fields;
    }


    /**
     * 
     *  Create params for data dictionary generation
     * 
     */
    function prepare_data_dictionary_params($sid, $fid)
    {

        $datafile_path=$this->Editor_datafile_model->get_file_path($sid,$fid);

        if (!$datafile_path){
            throw new Exception("Data file not found");
        }
			
        $this->db->select("name,field_dtype,user_missings,is_weight,var_wgt_id");
        $this->db->where("sid",$sid);
        $this->db->where("fid",$fid);        
        $variables=$this->db->get("editor_variables")->result_array();

        $params=array(
            'datafile'=> realpath($datafile_path)
        );

        foreach($variables as $variable){
            if (isset($variable['var_wgt_id']) && $variable['var_wgt_id']>0 ){
                $params['weights'][]=array(
                    'field'=>$variable['name'],
                    'weight_field'=>$this->get_name_by_var_wgt_id($sid,$variable['var_wgt_id'])
                );
            }
            if ($variable['user_missings']!=''){
                $params['missings'][]=array(
                    "field"=>$variable['name'],
                    "missings"=> explode(",",$variable['user_missings'])
                );
            }            
        }

        return $params;
    }


    function get_name_by_var_wgt_id($sid,$uid)
    {
        $this->db->select("uid,name");
        $this->db->where("sid",$sid);
        $this->db->where("uid",$uid);
        $row=$this->db->get("editor_variables")->row_array();
        
        if ($row){
            return $row['name'];
        }

        return '';
    }


    function set_sort_order($sid, $values)
    {
        //delete existing sort order
        $this->delete_sort_tmp($sid);

        //insert into a temp table
        $this->insert_sort_tmp($sid,$values);

        //update sort order
        $this->db->query('update editor_variables v                             
                                inner join editor_variables_sort_tmp tmp on tmp.var_uid=v.uid  
                                set v.sort_order=tmp.sort_order
                                where v.sid='.(int)$sid);

        $this->delete_sort_tmp($sid);
    }

    function insert_sort_tmp($sid,$values)
    {
        $options=array();
        foreach($values as $idx=>$value){
            $options[]=array(
                'sid'=>$sid,
                'var_uid'=>$value,
                'sort_order'=>$idx
            );
        }

        $this->db->insert_batch("editor_variables_sort_tmp",$options);
    }

    function delete_sort_tmp($sid)
    {
        $this->db->where('sid',$sid);
        $this->db->delete("editor_variables_sort_tmp");
    }
    

}    