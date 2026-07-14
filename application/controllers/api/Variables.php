<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Variables extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_model");
		$this->load->model("Editor_datafile_model");
		$this->load->model("Editor_variable_model");
		
		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
	}

	//override authentication to support both session authentication + api keys
	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}

	
	/**
	 * 
	 * List dataset variables
	 * 
	 * Pagination with offset and limit parameters
	 * Query params: detailed (0|1), offset (default: 0), limit (default: null - all)
	 * 
	 */
	function index_get($sid=null,$file_id=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view', $this->api_user);
			$user_id=$this->get_api_user_id();        			
			$variable_detailed=(int)$this->input->get("detailed");
			
			$offset=(int)$this->input->get("offset");
			$limit=$this->input->get("limit");

			if($limit !== null){
				$limit=(int)$limit;
			}

			$survey_variables=$this->Editor_variable_model->select_all($sid,$file_id,$variable_detailed,$offset,$limit);
			
			//pre-load vid_uid mapping
			$vid_uid_cache=null;
			if(count($survey_variables) > 0){
				$vid_uid_cache = $this->Editor_variable_model->vid_uid_list($sid);
			}
			$this->update_variable_weight_info($sid,$survey_variables,$vid_uid_cache);
			
			$response=array(
				'variables'=>$survey_variables
			);
			
			//include total count if pagination is used
			if($limit !== null && $limit > 0){
				$response['total']=$this->Editor_variable_model->count_all($sid,$file_id);
				$response['offset']=$offset;
				$response['limit']=$limit;
			}

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Get single variable by UID or variable name
	 * 
	 * @uid - variable UID
	 * @name - variable name
	 * @file_id - file ID [required for name]
	 * 
	 */
	function single_get($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);
			$user_id=$this->get_api_user_id();

			$uid=(int)$this->input->get("uid");
			$name=$this->input->get("name");
			$file_id=$this->input->get("file_id");

			if (!$uid && !$name){
				throw new Exception("Invalid `uid` or `name` parameter");
			}

			if ($uid){
				$variable=$this->Editor_variable_model->variable($sid,$uid, $variable_detailed=true);
			}
			else{
				if (!$file_id){
					throw new Exception("Invalid `file_id` parameter");
				}

				$variable=$this->Editor_variable_model->variable_by_name($sid,$file_id,$name, $variable_detailed=true);
			}
						
			$response=array(
				'variable'=>$variable
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * Get all key variables by project or data file
	 * 
	 * @sid - project ID
	 * @file_id - (optional) file ID
	 * 
	 * 
	 * 
	 */
	function key_get($sid=null,$file_id=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);			

			$survey_variables=$this->Editor_variable_model->key_variables($sid,$file_id);
			// Load vid_uid mapping for weight info update
			$vid_uid_cache=null;
			if(count($survey_variables) > 0){
				$vid_uid_cache = $this->Editor_variable_model->vid_uid_list($sid);
			}
			$this->update_variable_weight_info($sid,$survey_variables,$vid_uid_cache);
			
			$response=array(
				'variables'=>$survey_variables
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
	


	function by_name_post($sid=null,$file_id=null)
	{
		try{

			$options=(array)$this->raw_json_input();

			if (!isset($options['var_names']) || !is_array($options['var_names'])){
				throw new Exception("Invalid var_names parameter");
			}

			$this->editor_acl->user_has_project_access($sid,$permission='view',$this->api_user);
			$user_id=$this->get_api_user_id();
			
			$variable_detailed=1;//(int)$this->input->get("detailed");
			$survey_variables=$this->Editor_variable_model->variables_by_name($sid,$file_id,$options['var_names'],$variable_detailed);
			
			$response=array(
				'variables'=>$survey_variables
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}		
	}


	/**
	 * 
	 * 
	 * Create variables for Datasets
	 * @idno - dataset IDNo
	 * @merge_metadata - true|false 
	 * 	- true = partial update metadata 
	 *  - false = replace all metadata with new
	 */
	function index_post($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user);
			$options=(array)$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			//check if a single variable input is provided or a list of variables
			$key=key($options);

			//convert to list of a list
			if(!is_numeric($key)){
				$tmp_options=array();
				$tmp_options[]=$options;
				$options=null;
				$options=$tmp_options;
			}

			$valid_data_files=$this->Editor_datafile_model->list($sid);
			
			//validate all variables
			foreach($options as $key=>$variable){

				if (!isset($variable['fid'])){
					throw new Exception("`fid` is required");
				}

				if (!in_array($variable['fid'],$valid_data_files)){
					throw new Exception("Invalid `fid`: valid values are: ". implode(", ", $valid_data_files ));
				}

				if (!isset($variable['vid'])){
					throw new Exception("`vid` is required");
				}

				if (!isset($variable['name']) || trim($variable['name'] ?? '') === ''){
					throw new Exception("`name` is required");
				}
				$variable['name'] = trim($variable['name']);

				$variable['file_id']=$variable['fid'];
				$this->Editor_model->validate_variable($variable);
				$variable['metadata']=$variable;

				$uid = isset($variable['uid']) ? (is_numeric($variable['uid']) ? (int)$variable['uid'] : null) : null;

				if ($uid !== null && $uid > 0) {
					// Update by uid: ensure variable exists and belongs to this project and file
					$existing = $this->Editor_variable_model->variable($sid, $uid, false);
					if (empty($existing)) {
						throw new Exception("Variable not found for the given uid.");
					}
					if ($existing['fid'] !== $variable['fid']) {
						throw new Exception("Variable uid does not match the given file (fid). Correct variable must be updated for this file.");
					}
					$new_name = $variable['name'];
					$old_name = isset($existing['name']) ? trim($existing['name']) : '';
					if ($new_name !== $old_name) {
						// Name changed: validate new name (Stata/SPSS rules)
						$valid = $this->Editor_variable_model->validate_variable_name_for_rename($new_name);
						if (!$valid['valid']) {
							throw new Exception($valid['message']);
						}
						$other = $this->Editor_variable_model->variable_by_name($sid, $variable['fid'], $new_name, false);
						if (!empty($other) && (int)$other['uid'] !== (int)$uid) {
							throw new Exception("Another variable already has this name.");
						}
					}
					$this->Editor_variable_model->update($sid, $uid, $variable);
					if ($new_name !== $old_name && $old_name !== '') {
						$this->Editor_datafile_model->rewrite_csv_header($sid, $variable['fid'], array($old_name => $new_name));
					}
				} else {
					// No uid: resolve by name, update if found else insert
					$variable_info = $this->Editor_variable_model->variable_by_name($sid, $variable['file_id'], $variable['name'], false);
					if ($variable_info) {
						$this->Editor_variable_model->update($sid, $variable_info['uid'], $variable);
					} else {
						$this->Editor_variable_model->insert($sid, $variable);
					}
				}

				$result[]=$variable['vid'];
			}

			$response=array(
				'status'=>'success',
				'variables'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	function create_post($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit',$this->api_user);
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['variable'])){
				throw new Exception("`variable` is required");
			}

			$valid_data_files=$this->Editor_datafile_model->list($sid);
			
			//validate all variables
			$variable=$options['variable'];

			if (!isset($variable['file_id'])){
				throw new Exception("`file_id` is required");
			}

			if (!in_array($variable['file_id'],$valid_data_files)){
				throw new Exception("Invalid `file_id`: valid values are: ". implode(", ", $valid_data_files ));
			}

			if (!isset($variable['vid']) ){
				throw new Exception("`vid` is required");
			}
				
			$variable['fid']=$variable['file_id'];
			$this->Editor_variable_model->validate_variable($variable);
			
			$variable['metadata']=$variable;
			$uid=$this->Editor_variable_model->insert($sid,$variable);

			if(!$uid){
				throw new Exception("Failed to create variable");
			}

			$variable=$this->Editor_variable_model->variable($sid,$uid);
			
			$response=array(
				'status'=>'success',
				'variable'=>$variable
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * 
	 * 
	 * Delete variables by UID
	 * 
	 *  
	 */
	function index_delete($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');

			$options=(array)$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['uid']) || !is_array($options['uid'])){
				throw new Exception("`uid` is required and must be an array");
			}

			$this->load->model("Editor_variable_model");

			$result=$this->Editor_variable_model->delete($sid,$options['uid']);
			
			$response=array(
				'status'=>'success',
				'variables'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	function delete_post($sid=null)
	{
		return $this->index_delete($sid);
	}



	function order_post($sid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			$options=$this->raw_json_input();
			$user_id=$this->get_api_user_id();

			if (!isset($options['sorted_uid']) || !is_array($options['sorted_uid'])){
				throw new Exception("`sorted_uid` is required");
			}

			$result=$this->Editor_variable_model->set_sort_order($sid,$options['sorted_uid']);
			
			$response=array(
				'status'=>'success',
				'result'=>$result
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * Apply change case (title / upper / lower) to variable name and/or label for all variables in a data file.
	 *
	 * POST /api/variables/change_case/{sid}/{fid}
	 * Body: { "case_type": "title"|"upper"|"lower", "fields": ["name"] and/or ["labl"] }
	 */
	function change_case_post($sid=null, $fid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid, $permission='edit', $this->api_user);
			$user_id = $this->get_api_user_id();

			$options = $this->raw_json_input();
			if (!is_array($options)){
				$options = array();
			}

			$case_type = isset($options['case_type']) ? $options['case_type'] : '';
			$allowed_case = array('title', 'upper', 'lower');
			if (!in_array($case_type, $allowed_case)){
				throw new Exception("`case_type` is required and must be one of: " . implode(', ', $allowed_case));
			}

			$fields = isset($options['fields']) && is_array($options['fields']) ? $options['fields'] : array();
			$allowed_fields = array('name', 'labl');
			$fields = array_intersect($fields, $allowed_fields);
			if (empty($fields)){
				throw new Exception("`fields` is required and must contain at least one of: name, labl");
			}

			$valid_data_files = $this->Editor_datafile_model->list($sid);
			if (!in_array($fid, $valid_data_files)){
				throw new Exception("Invalid `fid`: valid values are: " . implode(", ", $valid_data_files));
			}

			$variables = $this->Editor_variable_model->select_all($sid, $fid, $metadata_detailed=true);
			$updated = 0;

			foreach ($variables as $variable){
				$changed = false;
				foreach ($fields as $field){
					if (!array_key_exists($field, $variable)){
						continue;
					}
					$value = $variable[$field];
					if (!is_string($value) || $value === ''){
						continue;
					}
					if ($case_type === 'title'){
						$value = $this->_apply_title_case($value);
					} elseif ($case_type === 'upper'){
						$value = mb_strtoupper($value, 'UTF-8');
					} else {
						$value = mb_strtolower($value, 'UTF-8');
					}
					$variable[$field] = $value;
					$changed = true;
				}
				if (!$changed){
					continue;
				}
				$variable['metadata'] = $variable;
				$this->Editor_variable_model->update($sid, $variable['uid'], $variable);
				$updated++;
			}

			$response = array(
				'status' => 'success',
				'updated' => $updated
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * Rename variables for a data file. Accepts an array of renames; updates DB and CSV header.
	 *
	 * POST /api/variables/rename/{sid}/{fid}
	 * Body: { "renames": [ {"old_name": "var1", "new_name": "var1_renamed"}, ... ] }
	 */
	function rename_post($sid = null, $fid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$valid_data_files = $this->Editor_datafile_model->list($sid);
			if (!in_array($fid, $valid_data_files)) {
				throw new Exception("Invalid `fid`: valid values are: " . implode(", ", $valid_data_files));
			}

			$body = $this->raw_json_input();
			$renames = isset($body['renames']) && is_array($body['renames']) ? $body['renames'] : array();
			if (empty($renames)) {
				throw new Exception("`renames` is required and must be a non-empty array of { old_name, new_name }.");
			}

			$result = $this->Editor_variable_model->rename_variables($sid, $fid, $renames);

			if (!empty($result['rename_map'])) {
				$this->Editor_datafile_model->rewrite_csv_header($sid, $fid, $result['rename_map']);
			}

			$response = array(
				'status' => 'success',
				'applied' => $result['applied'],
				'errors' => $result['errors']
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	/**
	 * Apply title case to a string (first letter of each word uppercase, rest lowercase).
	 */
	private function _apply_title_case($str)
	{
		if (function_exists('mb_convert_case')){
			return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
		}
		return ucwords(strtolower($str));
	}


	/**
	 * Batch set sum_stats_options for variables by interval type (discrete or contin).
	 *
	 * POST /api/variables/batch_sum_stats_options/{sid}/{fid}
	 * Body: { "interval_type": "discrete"|"contin", "sum_stats_options": { "freq": true, "missing": false, ... } }
	 * Only keys present in sum_stats_options are applied; others are left unchanged per variable.
	 */
	function batch_sum_stats_options_post($sid = null, $fid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit', $this->api_user);

			$valid_data_files = $this->Editor_datafile_model->list($sid);
			if (!in_array($fid, $valid_data_files)) {
				throw new Exception("Invalid `fid`: valid values are: " . implode(", ", $valid_data_files));
			}

			$body = $this->raw_json_input();
			$interval_type = isset($body['interval_type']) ? trim((string) $body['interval_type']) : '';
			$sum_stats_options = isset($body['sum_stats_options']) && is_array($body['sum_stats_options']) ? $body['sum_stats_options'] : array();

			if ($interval_type !== 'discrete' && $interval_type !== 'contin') {
				throw new Exception("`interval_type` must be 'discrete' or 'contin'");
			}
			if (empty($sum_stats_options)) {
				throw new Exception("`sum_stats_options` must be a non-empty object");
			}

			$variables = $this->Editor_variable_model->select_all($sid, $fid, $metadata_detailed = true);
			$updated = 0;

			foreach ($variables as $variable) {
				$var_intrvl = isset($variable['var_intrvl']) ? $variable['var_intrvl'] : (isset($variable['interval_type']) ? $variable['interval_type'] : null);
				if ($var_intrvl !== $interval_type) {
					continue;
				}

				if (!isset($variable['sum_stats_options']) || !is_array($variable['sum_stats_options'])) {
					$variable['sum_stats_options'] = array();
				}
				foreach ($sum_stats_options as $key => $value) {
					$variable['sum_stats_options'][$key] = $value;
				}

				$this->Editor_variable_model->update($sid, $variable['uid'], array('metadata' => $variable));
				$updated++;
			}

			$response = array(
				'status' => 'success',
				'updated' => $updated
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	//fix variable-weight from VID to UID
	private function update_variable_weight_info($sid,&$variables,&$vid_uid_cache=null)
    {
        // use cache if provided
        if($vid_uid_cache === null){
            $vid_uid_cache = $this->Editor_variable_model->vid_uid_list($sid);
        }

        foreach($variables as $idx=>$variable)
        {
            //if variable var_wgt_id is set
            if (isset($variable['var_wgt_id']))
            {
                if (isset($vid_uid_cache[$variable['var_wgt_id']])){
                    $variables[$idx]['var_wgt_id']=$vid_uid_cache[$variable['var_wgt_id']];
                }
            }
        }
    }


	/**
	 * 
	 * Export data dictionary as CSV (info rows then category rows).
	 * 
	 * @sid - project ID
	 * @fid - file ID
	 * 
	 * Query: download=1 — Content-Disposition attachment (default for browsers)
	 * 
	 */
	function export_csv_get($sid=null, $fid=null)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='view', $this->api_user);
			$user_id=$this->get_api_user_id();

			if (!$sid || !$fid) {
				throw new Exception('Project ID and file ID are required');
			}

			$datafile = $this->Editor_datafile_model->data_file_by_id($sid, $fid);
			if (!$datafile) {
				throw new Exception('Data file not found');
			}

			$this->load->library('Data_dictionary_csv');
			$csv = $this->data_dictionary_csv->export_csv($sid, $fid);

			$download = $this->input->get('download');
			if ($download === null || $download === '') {
				$download = true;
			} else {
				$download = filter_var($download, FILTER_VALIDATE_BOOLEAN);
			}

			if ($download) {
				$filename = $this->data_dictionary_csv->dictionary_filename_for_datafile($datafile);
				header('Content-Type: text/csv; charset=UTF-8');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				header('Cache-Control: no-store, no-cache');
				echo $csv;
				exit();
			}

			$this->set_response(array(
				'status' => 'success',
				'csv' => $csv,
				'format_version' => Data_dictionary_csv::FORMAT_VERSION,
			), REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	
}
