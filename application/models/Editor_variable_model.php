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
        'is_key',
        'user_missings',
        'field_dtype',
        'var_wgt_id',
        'interval_type',
        'metadata'
    );
 
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
    }


	function chunk_reader_generator($sid,$start_uid=0,$limit=150): iterator
    {
        $last_row_uid=$start_uid;

        do {
            $variables=$this->chunk_read($sid,$last_row_uid,$limit);

            if (!$variables){
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
        $this->db->select("uid,sid,fid,name,labl,metadata");
        $this->db->order_by('uid');
        $this->db->limit($limit);
        $this->db->where("sid",$sid);

        if ($start_uid>0){
            $this->db->where('uid >',$start_uid,false);
        }        
        
        $variables=$this->db->get("editor_variables")->result_array();

        foreach($variables as $key=>$variable){            
            $variables[$key]['metadata']=$this->Editor_model->decode_metadata($variable['metadata']);
            $this->normalize_variable_metadata($variables[$key]['metadata']);
            //$variables[$key]=$this->map_variable_fields($variables[$key]);
        }

        return $variables;
    }

    function delete($sid,$uid_list)
    {
        foreach($uid_list as $uid){
            $weight_var_in_use=$this->check_weight_variable_usage($sid,$uid);
            if ($weight_var_in_use){
                throw new Exception("Cannot delete variable because it is being used as a weight variable: ".$uid);
            }
        }

        $this->db->where('sid',$sid);
        $this->db->where_in('uid',$uid_list);
        $this->db->delete('editor_variables');
        return 
        [
            'rows'=>$this->db->affected_rows(),
            'query'=>$this->db->last_query()
        ];
    }


    /**
     * 
     * 
     * Check if weight variable is in use
     * 
     * checks if var_wgt_id is set to the uid of the variable to be deleted
     * 
     * 
     */
    function check_weight_variable_usage($sid,$uid)
    {
        $this->db->select("uid,var_wgt_id");        
        $this->db->where("sid",$sid);
        $this->db->where("var_wgt_id",$uid);
        $result=$this->db->get("editor_variables")->result_array();

        if (count($result)>0){
            return true;
        }

        return false;
    }

    /**
     * Parse sum_stats_options based on variable data type and interval type.
     *
     * Key rule (takes precedence over everything else): if a variable has
     * value/labels (var_catgry_labels, or var_catgry entries that carry a
     * non-empty labl), frequencies are enabled regardless of the variable's
     * data type (numeric, character, fixed, date) or interval type (discrete
     * or continuous). This covers Stata, SPSS and similar imports where any
     * variable with attached value labels should default to showing
     * frequencies in the UI.
     *
     * As a secondary signal, frequencies are also enabled when the variable's
     * interval type is 'discrete'. Otherwise frequencies default to false.
     * The data type still controls defaults for the other options (wgt, mean,
     * stdev, etc.) because those are not meaningful for non-numeric data.
     *
     * User choices in the UI are always preserved (not overwritten on
     * re-import) by bulk_upsert_dictionary().
     */
    private function parse_sum_stats_options($variable)
    {
        $data_type = isset($variable['var_format']['type']) ? $variable['var_format']['type'] : '';
        $var_intrvl = isset($variable['var_intrvl']) ? $variable['var_intrvl'] : (isset($variable['metadata']['var_intrvl']) ? $variable['metadata']['var_intrvl'] : null);
        $is_discrete = ($var_intrvl === 'discrete');
        $has_value_labels = $this->variable_has_value_labels($variable);
        $enable_freq = $has_value_labels || $is_discrete;

        switch ($data_type) {
            case 'numeric':
                return array(
                    'wgt' => true,
                    'freq' => $enable_freq,
                    'missing' => true,
                    'vald' => true,
                    'invd' => true,
                    'min' => true,
                    'max' => true,
                    'mean' => true,
                    'mean_wgt' => true,
                    'stdev' => true,
                    'stdev_wgt' => true
                );

            case 'character':
            case 'fixed':
                return array(
                    'wgt' => false,
                    'freq' => $enable_freq,
                    'missing' => true,
                    'vald' => true,
                    'invd' => true,
                    'min' => false,
                    'max' => false,
                    'mean' => false,
                    'mean_wgt' => false,
                    'stdev' => false,
                    'stdev_wgt' => false
                );

            case 'date':
                return array(
                    'wgt' => false,
                    'freq' => $enable_freq,
                    'missing' => true,
                    'vald' => true,
                    'invd' => true,
                    'min' => true,
                    'max' => true,
                    'mean' => false,
                    'mean_wgt' => false,
                    'stdev' => false,
                    'stdev_wgt' => false
                );

            default:
                return array(
                    'wgt' => false,
                    'freq' => $enable_freq,
                    'missing' => true,
                    'vald' => true,
                    'invd' => true,
                    'min' => false,
                    'max' => false,
                    'mean' => false,
                    'mean_wgt' => false,
                    'stdev' => false,
                    'stdev_wgt' => false
                );
        }
    }

    /**
     * Detect whether a variable carries value/labels.
     *
     * Returns true when var_catgry_labels contains at least one entry with a
     * non-empty labl, or when var_catgry contains at least one entry with a
     * non-empty labl. Checks both the top-level keys and the nested metadata
     * payload so it works in every code path that calls parse_sum_stats_options.
     *
     * @param array $variable
     * @return bool
     */
    private function variable_has_value_labels($variable)
    {
        $candidates = array();

        foreach (array('var_catgry_labels', 'var_catgry') as $key) {
            if (isset($variable[$key]) && is_array($variable[$key])) {
                $candidates[] = $variable[$key];
            }
            if (isset($variable['metadata'][$key]) && is_array($variable['metadata'][$key])) {
                $candidates[] = $variable['metadata'][$key];
            }
        }

        foreach ($candidates as $entries) {
            foreach ($entries as $entry) {
                $entry = is_array($entry) ? $entry : (array)$entry;
                if (isset($entry['labl']) && trim((string)$entry['labl']) !== '') {
                    return true;
                }
            }
        }

        return false;
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

			//extract interval type [categorical - for enabling frequencies]
			if (isset($variable['var_intrvl'])) {
				$variable['interval_type'] = $variable['var_intrvl'];
			}

			$this->validate($variable);

			if($variable_info){
                // for existing variables, merge metadata with existing metadata
                // update only few fields e.g. sum_stats, catgry, etc
                $variable_info['metadata']=$this->update_summary_stats($sid,$variable_info['uid'],$variable_info['metadata'],$variable);
                
                if (!isset($variable_info['uid'])){
                    var_dump($variable_info);
                    die();
                }

                if (isset($variable_info['metadata']['update_required'])){
                    unset($variable_info['metadata']['update_required']);
                }
                
				$this->update($sid,$variable_info['uid'],$variable_info);
			}
			else{
                $variable['metadata']=$variable;
                
                // Set sum_stats_options based on data type for new variables
                if (!isset($variable['metadata']['sum_stats_options'])) {
                    $variable['metadata']['sum_stats_options'] = $this->parse_sum_stats_options($variable);
                }
                
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
                // If disabled category frequencies, do not overwrite var_catgry
                if ($element === 'var_catgry' && isset($variable['sum_stats_options']['freq']) && $variable['sum_stats_options']['freq'] === false) {
                    continue;
                }
                $variable[$element]=$stats[$element];
                
                if ($element === 'var_intrvl') {
                    $variable['interval_type'] = $stats[$element];
                }
            }
        }
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

			// Extract interval_type from metadata if present
			if (isset($variable['var_intrvl'])) {
				$variable['interval_type'] = $variable['var_intrvl'];
			}

			$this->validate($variable);
			$variable['metadata']=$variable;

			if($uid){
				$this->update($sid,$uid,$variable);
			}
			else{
				//set sum_stats_options based on data type
				if (!isset($variable['metadata']['sum_stats_options'])) {
					$variable['metadata']['sum_stats_options'] = $this->parse_sum_stats_options($variable);
				}
						
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
     * @offset = offset for pagination (default: 0)
     * @limit = limit for pagination (default: null, returns all)
     * 
     **/
    function select_all($sid,$file_id=null,$metadata_detailed=false,$offset=0,$limit=null)
    {
        if ($metadata_detailed==true){
            $fields="uid,sid,fid,vid,name,labl,sort_order,is_weight,var_wgt_id,metadata";
        }else{
            $fields="uid,sid,fid,vid,name,labl,field_dtype,sort_order";
        }
        
        $this->db->select($fields);
        $this->db->where("sid",$sid);
        $this->db->order_by("sort_order,uid","asc");

        if($file_id){
            $this->db->where("fid",$file_id);
        }

        // Apply pagination if limit is specified
        if($limit !== null && $limit > 0){
            $this->db->limit($limit, $offset);
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
                    $db_name = $variable['name'];
                    $db_labl = isset($variable['labl']) ? $variable['labl'] : '';
                    $var_metadata=$this->Editor_model->decode_metadata($variable['metadata']);
                    $this->normalize_variable_metadata($var_metadata);
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
                    $variables[$key]['name'] = $db_name;
                    $variables[$key]['labl'] = $db_labl;
                }
            }
        }

        return $variables;
    }

    function vid_by_uid($sid,$uid)
    {
        $this->db->select("vid");
        $this->db->where("sid",$sid);
        $this->db->where("uid",$uid);

        $variable=$this->db->get("editor_variables")->row_array();

        if ($variable){
            return $variable['vid'];
        }

        return false;
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

    //return a list of variable VID/UIDs
    function vid_uid_list($sid)
    {
        $this->db->select("vid,uid");
        $this->db->where("sid",$sid);
        $result=$this->db->get("editor_variables")->result_array();

        $output=array();
        foreach($result as $row)
        {
            $output[$row['vid']]=$row['uid'];
        }

        return $output;
    }

    /**
     * 
     * Return a list of variable UID/VIDs (reverse mapping of vid_uid_list)
     * 
     * @sid - study ID
     * 
     **/
    function uid_vid_list($sid)
    {
        $this->db->select("uid,vid");
        $this->db->where("sid",$sid);
        $result=$this->db->get("editor_variables")->result_array();

        $output=array();
        foreach($result as $row)
        {
            $output[$row['uid']]=$row['vid'];
        }

        return $output;
    }

    /**
     * 
     * Get total count of variables for a study
     * 
     * @sid - study ID
     * @file_id - optional file ID filter
     * 
     **/
    function count_all($sid,$file_id=null)
    {
        $this->db->select("COUNT(*) as total", false);
        $this->db->where("sid",$sid);

        if($file_id){
            $this->db->where("fid",$file_id);
        }

        $result=$this->db->get("editor_variables")->row_array();
        return (int)$result['total'];
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
			$this->normalize_variable_metadata($variable['metadata']);
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
			$this->normalize_variable_metadata($variable['metadata']);
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
                    $db_name = $variable['name'];
                    $db_labl = isset($variable['labl']) ? $variable['labl'] : '';
                    $var_metadata=$this->Editor_model->decode_metadata($variable['metadata']);
                    $this->normalize_variable_metadata($var_metadata);
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
                    $variables[$key]['name'] = $db_name;
                    $variables[$key]['labl'] = $db_labl;
                }
            }
        }
		return $variables;
    }

	/**
	 * Normalize variable metadata when read from DB so fields that should be numeric
	 * are int/float, and invalid values are removed. Fixes existing data where e.g.
	 * var_wgt was stored as string "1" or var_wgt_id in metadata overwrites DB value.
	 *
	 * @param array $var_metadata Decoded metadata (modified in place)
	 */
	private function normalize_variable_metadata(&$var_metadata)
	{
		if (!is_array($var_metadata)) {
			return;
		}

		// var_wgt: must be 0 or 1 (integer) for UI and schema
		if (array_key_exists('var_wgt', $var_metadata)) {
			$v = $var_metadata['var_wgt'];
			if ($v === 1 || $v === '1' || $v === true) {
				$var_metadata['var_wgt'] = 1;
			} elseif ($v === 0 || $v === '0' || $v === false || $v === '' || $v === null) {
				$var_metadata['var_wgt'] = 0;
			} elseif (is_numeric($v) && (int)$v !== 0) {
				$var_metadata['var_wgt'] = 1;
			} else {
				$var_metadata['var_wgt'] = 0;
			}
		}

		// var_wgt_id in metadata: positive integer UID only; 0/"0" mean no weight (omit from metadata)
		if (array_key_exists('var_wgt_id', $var_metadata)) {
			$v = $var_metadata['var_wgt_id'];
			if (is_numeric($v) && (int)$v > 0) {
				$var_metadata['var_wgt_id'] = (int)$v;
			} else {
				unset($var_metadata['var_wgt_id']);
			}
		}
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
	 * Validate variable name for renaming (Stata/SPSS-style rules).
	 * Rules: must start with a letter; only letters, numbers, underscores; no spaces, no dots; max 32 chars.
	 * Use this for the rename flow; use a separate validation when importing data.
	 *
	 * @param string $name Variable name to validate
	 * @return array ['valid' => bool, 'message' => string] empty message when valid
	 */
	public function validate_variable_name_for_rename($name)
	{
		$name = trim($name);
		$max_len = 32;
		if ($name === '') {
			return array('valid' => false, 'message' => 'Variable name is required.', 'reason' => 'empty');
		}
		if (strlen($name) > $max_len) {
			return array('valid' => false, 'message' => 'Variable name cannot be longer than ' . $max_len . ' characters.', 'reason' => 'too_long');
		}
		if (!preg_match('/^[a-zA-Z]/', $name)) {
			$reason = (substr($name, 0, 1) === '_') ? 'leading_underscore' : 'starts_with_number';
			return array('valid' => false, 'message' => 'Variable name must start with a letter (a-z, A-Z).', 'reason' => $reason);
		}
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
			return array('valid' => false, 'message' => 'Variable name may only contain letters, numbers, and underscores. No spaces or special characters.', 'reason' => 'invalid_chars');
		}
		return array('valid' => true, 'message' => '', 'reason' => '');
	}

	/**
	 * Get list of variables with invalid names (Stata/SPSS rules) for a file.
	 *
	 * @param int $sid Project ID
	 * @param string $fid File ID
	 * @return array [ ['name' => string, 'message' => string, 'reason' => string], ... ]
	 */
	public function get_invalid_variable_names($sid, $fid)
	{
		$names = $this->get_variable_names_by_file($sid, $fid);
		$invalid = array();
		foreach ($names as $name) {
			$result = $this->validate_variable_name_for_rename($name);
			if (!$result['valid']) {
				$invalid[] = array(
					'name' => $name,
					'message' => $result['message'],
					'reason' => isset($result['reason']) ? $result['reason'] : 'invalid'
				);
			}
		}
		return $invalid;
	}

	/**
	 * Rename variables for a data file. Validates each new name (Stata/SPSS rules), updates DB, returns applied renames.
	 * Caller is responsible for rewriting the CSV header (Editor_datafile_model->rewrite_csv_header).
	 *
	 * @param int $sid Project ID
	 * @param string $fid File ID
	 * @param array $renames Array of ['old_name' => string, 'new_name' => string]
	 * @return array ['applied' => [['old_name'=>'','new_name'=>''], ...], 'errors' => [], 'rename_map' => [old=>new]]
	 */
	public function rename_variables($sid, $fid, $renames)
	{
		$this->Editor_model->check_project_editable($sid);
		$existing_names = $this->get_variable_names_by_file($sid, $fid);
		$names_in_use = array_flip($existing_names);
		$applied = array();
		$errors = array();
		$rename_map = array();

		foreach ($renames as $item) {
			$old_name = isset($item['old_name']) ? trim($item['old_name']) : '';
			$new_name = isset($item['new_name']) ? trim($item['new_name']) : '';
			if ($old_name === '' || $new_name === '') {
				$errors[] = array('old_name' => $old_name, 'new_name' => $new_name, 'message' => 'old_name and new_name are required.');
				continue;
			}
			if ($old_name === $new_name) {
				continue;
			}
			$valid = $this->validate_variable_name_for_rename($new_name);
			if (!$valid['valid']) {
				$errors[] = array('old_name' => $old_name, 'new_name' => $new_name, 'message' => $valid['message']);
				continue;
			}
			if (!isset($names_in_use[$old_name])) {
				$errors[] = array('old_name' => $old_name, 'new_name' => $new_name, 'message' => 'Variable not found.');
				continue;
			}
			if (isset($names_in_use[$new_name])) {
				$errors[] = array('old_name' => $old_name, 'new_name' => $new_name, 'message' => 'Another variable already has this name.');
				continue;
			}
			$this->db->where('sid', $sid);
			$this->db->where('fid', $fid);
			$this->db->where('name', $old_name);
			$this->db->update('editor_variables', array('name' => $new_name));
			if ($this->db->affected_rows() > 0) {
				$applied[] = array('old_name' => $old_name, 'new_name' => $new_name);
				$rename_map[$old_name] = $new_name;
				unset($names_in_use[$old_name]);
				$names_in_use[$new_name] = true;
			}
		}

		return array('applied' => $applied, 'errors' => $errors, 'rename_map' => $rename_map);
	}


    /**
     * 
     * Validate variable against schema
     * 
     */
    function validate_schema($data)
    {
        return $this->Editor_model->validate_schema('variable', $data);
    }


    function validate_variables($sid)
    {
        foreach($this->chunk_reader_generator($sid) as $variable){
            try{
                $result=$this->validate_schema(array_remove_empty($variable['metadata']));
            }
            catch(ValidationException $e){
                $validation_errors=$e->GetValidationErrors();

                foreach($validation_errors as $idx=>$error)
                {
                    if (isset($error['message'])){
                        $validation_errors[$idx]['property']= 'variables/' . $variable['fid'];
                        $validation_errors[$idx]['message']=$error['message'] . ' - FILE: ' . $variable['fid'] . ' - Variable: ' . $variable['metadata']['name'];
                    }                    
                }

                throw new ValidationException("VALIDATION_ERROR: ".$e->getMessage(),$validation_errors);
            }
            
            //yield $variable['metadata'];
        }
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
        $this->Editor_model->check_project_editable($sid);

        foreach($options as $key=>$value){
            if(!in_array($key,$this->fields)){
                unset($options[$key]);
            }
        }
        if (isset($options['name'])) {
            $options['name'] = trim($options['name']);
        }

        $options['sid']=$sid;

        //metadata
        if(isset($options['metadata'])){
            // Sync var_invalrng.values and is_missing on categories
            $options['metadata'] = $this->sync_invalrng_and_is_missing($options['metadata']);
            // Remove var_catgry when freq is disabled
            $options['metadata'] = $this->remove_catgry_when_freq_disabled($options['metadata']);
            
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
        $this->Editor_model->check_project_editable($sid);

        foreach($options as $key=>$value){
            if(!in_array($key,$this->fields)){
                unset($options[$key]);
            }
        }
        if (isset($options['name'])) {
            $options['name'] = trim($options['name']);
        }

        $options['sid']=$sid;
        

        //metadata
        if(isset($options['metadata'])){
            // Sync var_invalrng.values and is_missing on categories
            $options['metadata'] = $this->sync_invalrng_and_is_missing($options['metadata']);
            // Remove var_catgry when sum_stats_options.freq is false
            $options['metadata'] = $this->remove_catgry_when_freq_disabled($options['metadata']);
            
            $core=$this->get_variable_core_fields($options['metadata']);
            $options=array_merge($options,$core);
            $options['metadata']=$this->Editor_model->encode_metadata($options['metadata']);
        }

        $this->db->where('sid',$sid);
        $this->db->where('uid',$uid);
        $this->db->update("editor_variables",$options);
        return $uid;
    }


    /**
     * Extract core DB columns from variable metadata. Mutates $variable to keep var_wgt_id in metadata
     * aligned with the editor rules: character variables cannot reference a weight; 0 / empty / "0" mean none.
     *
     * @param array $variable Metadata array (by reference)
     * @return array Core fields for editor_variables row
     */
    function get_variable_core_fields(&$variable)
    {
        //fid,vid,name,labl,sort_order,user_missings,is_weight,field_dtype, field_format
        $missings=(array)array_data_get($variable,'var_invalrng.values','');
        $missings=implode(",",$missings);
        $dtype=array_data_get($variable,'var_format.type','');
        $interval_type=array_data_get($variable,'var_intrvl','');

        $var_wgt_id_out = 0;
        if ($dtype === 'character') {
            unset($variable['var_wgt_id']);
        } elseif (isset($variable['var_wgt_id'])) {
            $raw = $variable['var_wgt_id'];
            if ($raw !== '' && $raw !== null && is_numeric($raw) && (int)$raw > 0) {
                $var_wgt_id_out = (int)$raw;
                $variable['var_wgt_id'] = $var_wgt_id_out;
            } else {
                unset($variable['var_wgt_id']);
            }
        }

        $core_fields=array(
            'fid'=>$variable['fid'],
            'vid'=>$variable['vid'],
            'name'=>$variable['name'],
            'labl'=>isset($variable['labl']) ? $variable['labl'] : '',
            'user_missings'=>$missings,
            'is_weight'=> isset($variable['var_wgt']) ? (int)$variable['var_wgt'] : 0,
            'is_key'=> isset($variable['is_key']) ? (int)$variable['is_key'] : 0,
            'field_dtype'=>$dtype,
            'interval_type'=>$interval_type,
            'var_wgt_id'=>$var_wgt_id_out,
        );

        return $core_fields;
    }

    /**
     * 
     * Sync var_invalrng.values and is_missing on categories
     *      
     * 1. var_invalrng.values is populated from categories with is_missing=1
     * 2. is_missing on each category is set from var_invalrng.values (omitted when not missing)
     * 
     * @param array $variable Variable metadata array
     * @return array Variable with synced var_invalrng.values and is_missing
     */
    private function sync_invalrng_and_is_missing($variable)
    {
        // Check if var_invalrng.values was set
        $var_invalrng_was_set = isset($variable['var_invalrng']) && 
                                 isset($variable['var_invalrng']['values']);
        
        // Initialize var_invalrng structure if not exists
        if (!isset($variable['var_invalrng'])) {
            $variable['var_invalrng'] = array('values' => array());
        }
        if (!isset($variable['var_invalrng']['values'])) {
            $variable['var_invalrng']['values'] = array();
        }

        // Step 1: Extract is_missing from categories and populate var_invalrng.values
        if (!$var_invalrng_was_set && isset($variable['var_catgry']) && is_array($variable['var_catgry'])) {
            $missing_from_categories = array();
            foreach($variable['var_catgry'] as $cat) {
                if (isset($cat['is_missing']) && 
                    ($cat['is_missing'] == '1' || $cat['is_missing'] == 'Y' || $cat['is_missing'] == 1 || $cat['is_missing'] === true)) {
                    if (isset($cat['value']) && $cat['value'] !== null && $cat['value'] !== '') {
                        $missing_from_categories[] = (string)$cat['value'];
                    }
                }
            }
            
            if (!empty($missing_from_categories)) {
                $variable['var_invalrng']['values'] = array_values(array_unique($missing_from_categories));
            }
        }

        // Step 2: Set is_missing on each category from var_invalrng.values (omit key when not missing)
        if (isset($variable['var_catgry']) && is_array($variable['var_catgry'])) {
            $missing_vals = array();
            if (isset($variable['var_invalrng']['values']) && is_array($variable['var_invalrng']['values'])) {
                $missing_vals = array_map('strval', $variable['var_invalrng']['values']);
            }
            foreach($variable['var_catgry'] as &$cat) {
                if (!isset($cat['value'])) {
                    continue;
                }
                $cv = (string)$cat['value'];
                if (in_array($cv, $missing_vals, true)) {
                    $cat['is_missing'] = '1';
                } else {
                    unset($cat['is_missing']);
                }
            }
            unset($cat);
        }

        return $variable;
    }

    /**
     * Remove var_catgry from metadata when sum_stats_options.freq is false.
     * var_catgry_labels is kept so user-edited labels can be restored when freq is re-enabled.
     *
     * @param array $variable Variable metadata array
     * @return array Variable with var_catgry removed when freq is disabled
     */
    private function remove_catgry_when_freq_disabled($variable)
    {
        if (isset($variable['sum_stats_options']['freq']) && $variable['sum_stats_options']['freq'] === false) {
            unset($variable['var_catgry']);
        }
        return $variable;
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


    /**
     * 
     * Populate variable category value/labels 
     * 
     * Note: this will replace existing values/labels from variable var_catgry field
     * 
     * 
     */
    function populate_categry_labels($sid)
    {     
        $output=[
            'skipped'=>[],
            'updated'=>[],
        ];
        foreach($this->chunk_reader_generator($sid) as $variable){

            if (isset($variable['metadata']['var_catgry_labels']) && 
                    is_array($variable['metadata']['var_catgry_labels']) && 
                    count($variable['metadata']['var_catgry_labels'])>0)
                {
                $output['skipped'][]=$variable['uid'];
                continue;
            }

            if (isset($variable['metadata']['var_catgry'])){

                //ignore if stats_option is set to not include frequencies
                if (isset($variable['metadata']['sum_stats_options']) && 
                    isset($variable['metadata']['sum_stats_options']['freq']) &&
                    $variable['metadata']['sum_stats_options']['freq'] == false
                    ){
                    $output['skipped_stats'][]=$variable['uid'];
                    continue;
                }

                $labels=array();                
                foreach($variable['metadata']['var_catgry'] as $i=>$catgry){

                    if (!isset($catgry['value'])) {
                        continue;
                    }

                    $labels[]=array(
                        'value'=>$catgry['value'],
                        'labl'=> isset($catgry['labl']) ? $catgry['labl'] :''
                    );                    
                }

                $variable['metadata']['var_catgry_labels']=$labels;
                $output['updated'][]=$variable['uid'];
                $this->update($sid,$variable['uid'],array('metadata'=>$variable['metadata']));
            }
            else {
                $output['skipped'][]=$variable['uid'];
            }
        }
        return $output;  
    }


    function get_variable_names_by_file($sid,$file_id)
    {
        $this->db->select("name");
        $this->db->where("sid",$sid);
        $this->db->where("fid",$file_id);
        $result=$this->db->get("editor_variables")->result_array();

        $names=array();
        foreach($result as $row){
            $names[]=$row['name'];
        }

        return $names;
    }


    /**
     * 
     * Return variable by data file name + variable name
     * 
     */
    function get_variable_by_filename($sid,$file_name, $var_name)
    {
        $this->db->select("v.*");
        $this->db->from("editor_variables v");
        $this->db->join("editor_data_files f","f.file_id=v.fid");
        $this->db->where("v.sid",$sid);
        $this->db->where("f.file_name",$file_name);
        $this->db->where("v.name",$var_name);
        $variable=$this->db->get()->row_array();

        if(isset($variable['metadata'])){
			$variable['metadata']=$this->Editor_model->decode_metadata($variable['metadata']);
			$this->normalize_variable_metadata($variable['metadata']);
		}

        return $variable;
    }


    /**
     * 
     * Get key variables
     *  
     * 
     **/
    function key_variables($sid,$file_id=null)
    {
        $this->db->select("uid,sid,fid,vid,name,labl,field_dtype,sort_order,is_key");
        $this->db->where("sid",$sid);
        $this->db->where("is_key",1);
        $this->db->order_by("sort_order,uid","asc");

        if($file_id){
            $this->db->where("fid",$file_id);
        }

        $variables=$this->db->get("editor_variables")->result_array();

        return $variables;
    }



    /**
     * 
     * 
     * Get key variable names or VIDs
     * 
     * @param $use_vid - true|false - use vid or name
     * 
     */
    function get_key_variable_names($sid,$file_id=null, $use_vid=true)
    {
       $key_variables=$this->key_variables($sid,$file_id);

        $names=array();
        foreach($key_variables as $row){
            if ($use_vid){
                $names[]=$row['vid'];
            }
            else{
                $names[]=$row['name'];
            }
        }

        return $names;
    }


    

}    