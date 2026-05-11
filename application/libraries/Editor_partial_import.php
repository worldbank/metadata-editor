<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * 
 * 
 * Import partial metadata from XML or JSON
 * 
 *  - full or partial selected metadata
 * 
 * 
 */
class Editor_partial_import
{

	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "editor_partial_import Class Initialized.");
		$this->ci =& get_instance();

		$this->ci->load->model("Editor_model");
        $this->ci->load->model("Editor_datafile_model");
        $this->ci->load->model("Editor_variable_model");
        $this->ci->load->model("Editor_variable_groups_model");
	}
    
    /**
	 * 
	 * 	 
	 * import_options - [ "doc_desc", "study_desc", "data_files", "variable_info", "variable_categories", "variable_questions", "variable_weights", "variable_groups", "import_new_data" ]
	 * 
	 * 
	 */
	function import_ddi($sid, $ddi_path, $options=array(), $import_options=array())
	{
		//import new data files and variables. if set to false,
		//only update existing data files and variables. 
		//no new data files or variables will be created.		
		$import_new_data = in_array("import_new_data", $import_options);

		if (!$import_new_data){
			$import_new_data=true;
		}

		$parser_params=array(
			'file_type'=>'survey',
			'file_path'=>$ddi_path
		);

		$this->ci->load->library('DDI2_import');
		$this->ci->load->library('Metadata_parser', $parser_params);

		$parser=$this->ci->metadata_parser->get_reader();
		$project_json=$this->ci->ddi2_import->transform_ddi_fields($parser->get_metadata_array());

		$project_db=$this->ci->Editor_model->get_row($sid);

		if (!is_array($project_db['metadata'])){
			$project_db['metadata']=(array)$project_db['metadata'];
		}		

		//remove doc_desc if not set
		if (in_array("document_description",$import_options)){
			if (isset($project_json['doc_desc'])){
				$project_db['metadata']['doc_desc']=$project_json['doc_desc'];
			}
		}

		if (in_array("study_description",$import_options)){
			if (isset($project_json['study_desc'])){
				$project_db['metadata']['study_desc']=$project_json['study_desc'];
			}
		}

		if (isset($project_json['doc_desc']) || isset($project_json['study_desc'])){
			//update project study level metadata
			$this->ci->Editor_model->update_project($type='survey',$sid,$project_db['metadata'],$validate=true);
		}

		if (in_array("data_files",$import_options)){
					
			//get list of data files
			$files=(array)$parser->get_data_files();

			//check if data file is empty
			foreach($files as $idx =>$file){
				$is_null=true;
				foreach(array_keys($file) as $file_field){
					if(!empty($file[$file_field])){
						$is_null=false;
					}
				}
				if($is_null){
					//remove empty data file
					unset($files[$idx]);
				}
			}

			// import data files		
			// - match by data file name
			// - if exists: update only the description field
			// - if not exists: create new data file entry (only if $import_new_data is true)

			$data_files=array();
			foreach($files as $file){
				if(trim($file['id'])=='' && trim($file['file_id'])!='' ){
					$file['id']=$file['file_id'];
				}
				$data_file=array(
					'file_id'       =>$file['id'],
					'file_name'     =>str_replace(".NSDstat","",$file['filename']),
					'file_type'		=>$file['filetype'],
					'description'   =>$file['fileCont'],
					'case_count'    =>$file['caseQnty'],
					'var_count'     =>$file['varQnty']
				);
				$data_files[$file['id']]=$data_file;

				// Skip if file_name is empty
				if (empty($data_file['file_name'])) {
					continue;
				}

				// Check if data file exists
				$existing_file = $this->ci->Editor_datafile_model->data_file_by_name($sid, $data_file['file_name']);
				
				if ($existing_file) {
					// Update only description field for existing files
					$update_options = array(
						'description' => $data_file['description'],
					);
					try {
						$this->ci->Editor_datafile_model->update_by_filename($sid, $data_file['file_name'], $update_options);
					} catch (Exception $e) {
						log_message('error', "Failed to update data file '{$data_file['file_name']}': " . $e->getMessage());
						throw new Exception("Failed to update data file '{$data_file['file_name']}': " . $e->getMessage());
					}
				} else {
					// Only create new data file if $import_new_data is true
					if ($import_new_data) {
						// Create new data file entry
						$insert_options = array(
							'file_id' => $data_file['file_id'],
							'file_name' => $data_file['file_name'],
							'file_type' => $data_file['file_type'],
							'description' => $data_file['description'],
							'case_count' => $data_file['case_count'],
							'var_count' => $data_file['var_count'],
							'created_by' => isset($options['changed_by']) ? $options['changed_by'] : null,
							'changed_by' => isset($options['changed_by']) ? $options['changed_by'] : null,
						);
						try {
							$this->ci->Editor_datafile_model->insert($sid, $insert_options);
						} catch (Exception $e) {
							log_message('error', "Failed to create data file '{$data_file['file_name']}': " . $e->getMessage());
							throw new Exception("Failed to create data file '{$data_file['file_name']}': " . $e->getMessage());
						}
					} else {
						// Skip creating new data file when $import_new_data is false
						log_message('debug', "Skipping creation of new data file '{$data_file['file_name']}' - import_new_data is false");
					}
				}
			}
		}

        //import variables
		// - match data files by file name
		// - if exists: update variable metadata based on import options
		// - if not exists: create new variable entry (only if $import_new_data is true)
		// - "variable_info", "variable_categories", "variable_questions", "variable_weights"

		//list with file name as key and fid as value
		$db_datafiles_names_map=$this->ci->Editor_datafile_model->file_id_name_list($sid);


		$this->ci->load->library('DDI_Utils');
		$variable_iterator=$parser->get_variable_iterator();
		$variable_warnings=array();

		foreach($variable_iterator as $var_obj)
        {
			if (!$var_obj){
				continue;
			}

            $base_variable=$var_obj->get_metadata_array();

			// Skip if variable name is empty
			if (empty($base_variable['name'])) {
				continue;
			}

			// DDI 2.x @files is IDREFS (whitespace-separated list). Fan out one
			// variable row per referenced file so per-file lookups continue to work.
			$file_id_tokens=DDI_Utils::split_file_ids($base_variable['file_id']);

			if (empty($file_id_tokens)){
				$variable_warnings[]=array(
					'vid'   => isset($base_variable['vid']) ? $base_variable['vid'] : '',
					'name'  => $base_variable['name'],
					'files' => $base_variable['file_id'],
					'message' => 'Variable has no @files attribute; not imported.'
				);
				continue;
			}

			$inserted_tokens=array();
			$unknown_tokens=array();

			foreach($file_id_tokens as $fid_token){
				if (!isset($data_files[$fid_token])){
					$unknown_tokens[]=$fid_token;
					continue;
				}

				$variable=$base_variable;
				$variable['file_id']=$fid_token;
				$variable['fid']=$fid_token;

				//update variable info
				// - match by variable name
				// - match by data file name [ db requires fid, get fid from db using data_file_name]

				//data file name
				$data_file_name=$data_files[$fid_token]['file_name'];

				//get variable from db
				$variable_db=$this->ci->Editor_variable_model->get_variable_by_filename($sid,$data_file_name,$variable['name']);

				$variable['var_catgry_labels']=$this->get_variable_category_value_labels($variable);

				// Populate var_invalrng.values from categories with is_missing=1 or missing='Y'
				if (isset($variable['var_catgry']) && is_array($variable['var_catgry'])) {
					$missing_values = array();
					foreach($variable['var_catgry'] as $cat) {
						if (isset($cat['is_missing']) &&
							($cat['is_missing'] == '1' || $cat['is_missing'] == 'Y' || $cat['is_missing'] == 1)) {
							if (isset($cat['value']) && $cat['value'] !== null && $cat['value'] !== '') {
								$missing_values[] = (string)$cat['value'];
							}
						}
					}
					if (!empty($missing_values)) {
						$variable['var_invalrng'] = array('values' => array_values(array_unique($missing_values)));
					} else if (!isset($variable['var_invalrng'])) {
						$variable['var_invalrng'] = array('values' => array());
					}

					// Remove is_missing from categories
					foreach($variable['var_catgry'] as &$cat) {
						if (isset($cat['is_missing'])) {
							unset($cat['is_missing']);
						}
					}
					unset($cat);
				}

				if ($variable_db) {
					// Update existing variable metadata
					$variable_db['metadata']=$this->update_variable_metadata($variable_db['metadata'], $variable, $import_options);

					// Resolve weight variable reference (VID -> UID) when importing variable_weights
					if (in_array("variable_weights", $import_options)) {
						$ref = isset($variable['var_wgt_ref']) ? trim($variable['var_wgt_ref']) : '';
						if ($ref !== '') {
							$wgt_uid = $this->ci->Editor_variable_model->uid_by_vid($sid, $ref);
							$variable_db['var_wgt_id'] = ($wgt_uid !== false) ? $wgt_uid : 0;
						} else {
							$variable_db['var_wgt_id'] = 0;
						}
					}

					//update db
					try {
						$this->ci->Editor_variable_model->update($sid,$variable_db['uid'],$variable_db);
						$inserted_tokens[]=$fid_token;
					} catch (Exception $e) {
						log_message('error', "Failed to update variable '{$variable['name']}': " . $e->getMessage());
						throw new Exception("Failed to update variable '{$variable['name']}': " . $e->getMessage());
					}
				} else {
					// Only create new variable if $import_new_data is true
					if ($import_new_data) {
						// Create new variable
						$fid = isset($db_datafiles_names_map[$data_file_name]) ? $db_datafiles_names_map[$data_file_name] : null;
						if (!$fid) {
							log_message('error', "Data file '{$data_file_name}' not found in database for variable '{$variable['name']}'");
							continue;
						}

						// Generate new variable ID
						$max_vid = $this->ci->Editor_variable_model->get_max_vid($sid);
						$new_vid = 'V' . ($max_vid + 1);

						$var_wgt_ref = isset($variable['var_wgt_ref']) ? trim($variable['var_wgt_ref']) : '';
						unset($variable['var_wgt_ref']);

						$new_variable = array(
							'fid' => $fid,
							'vid' => $new_vid,
							'name' => $variable['name'],
							'labl' => isset($variable['labl']) ? $variable['labl'] : '',
							'field_dtype' => isset($variable['field_dtype']) ? $variable['field_dtype'] : 'numeric',
							'sort_order' => 0,
							'metadata' => $variable,
							'created_by' => isset($options['changed_by']) ? $options['changed_by'] : null,
							'changed_by' => isset($options['changed_by']) ? $options['changed_by'] : null,
						);

						try {
							$new_uid = $this->ci->Editor_variable_model->insert($sid, $new_variable);
							if ($var_wgt_ref !== '' && $new_uid) {
								$wgt_uid = $this->ci->Editor_variable_model->uid_by_vid($sid, $var_wgt_ref);
								if ($wgt_uid !== false) {
									$this->ci->Editor_variable_model->update($sid, $new_uid, array('var_wgt_id' => $wgt_uid));
								}
							}
							$inserted_tokens[]=$fid_token;
						} catch (Exception $e) {
							log_message('error', "Failed to create variable '{$variable['name']}': " . $e->getMessage());
							throw new Exception("Failed to create variable '{$variable['name']}': " . $e->getMessage());
						}
					} else {
						// Skip creating new variable when $import_new_data is false
						log_message('debug', "Skipping creation of new variable '{$variable['name']}' - import_new_data is false");
					}
				}
			}

			if (count($file_id_tokens) > 1 && !empty($inserted_tokens)){
				$variable_warnings[]=array(
					'vid'   => isset($base_variable['vid']) ? $base_variable['vid'] : '',
					'name'  => $base_variable['name'],
					'files' => $base_variable['file_id'],
					'message' => 'Variable referenced multiple files; applied to: '.implode(', ', $inserted_tokens).'.'
				);
			}

			if (!empty($unknown_tokens)){
				$variable_warnings[]=array(
					'vid'   => isset($base_variable['vid']) ? $base_variable['vid'] : '',
					'name'  => $base_variable['name'],
					'files' => $base_variable['file_id'],
					'message' => 'Variable referenced unknown data file(s): '.implode(', ', $unknown_tokens).' (skipped for those files).'
				);
			}
		}


		/*
        //import variable groups
        $this->create_update_variable_groups($sid,$parser->get_variable_groups());
		*/

		$result=array();
		if (!empty($variable_warnings)){
			$result['variable_warnings']=$variable_warnings;
		}
		return $result;
	}

	/**
	 * 
	 * 
	 * Update/merge variable metadata depending on the options
	 * 
	 */
	function update_variable_metadata($variable_db, $variable_import, $import_options)
	{
		//variable info - labl
		if (in_array("variable_info",$import_options)){
			$variable_db['labl']=$variable_import['labl'];
		}

		//variable categories - category value/labels
		if (in_array("variable_categories",$import_options)){
			$variable_db['var_catgry_labels']=$variable_import['var_catgry_labels'];
		}

		//variable questions - qstn
		if (in_array("variable_questions",$import_options)){
			$variable_db['var_qstn_preqtxt']=$variable_import['var_qstn_preqtxt'];
			$variable_db['var_qstn_qstnlit']=$variable_import['var_qstn_qstnlit'];
			$variable_db['var_qstn_postqtxt']=$variable_import['var_qstn_postqtxt'];
			$variable_db['var_qstn_ivuinstr']=$variable_import['var_qstn_ivuinstr'];
		}

		//variable documentation - everything except questions, categories, and labl
		if (in_array("variable_documentation", $import_options)){
			//$variable_db['var_intrvl']=$variable_import['var_intrvl'];
			//$variable_db['var_dcml']=$variable_import['var_dcml'];
			$variable_db['var_imputation']=$variable_import['var_imputation'];
			$variable_db['var_security']=$variable_import['var_security'];
			$variable_db['var_resp_unit']=$variable_import['var_resp_unit'];
			$variable_db['var_analysis_unit']=$variable_import['var_analysis_unit'];
			$variable_db['var_universe']=$variable_import['var_universe'];
			$variable_db['var_txt']=$variable_import['var_txt'];
			$variable_db['var_codinstr']=$variable_import['var_codinstr'];
			$variable_db['var_concept']=$variable_import['var_concept'];
			$variable_db['var_format']=$variable_import['var_format'];
			$variable_db['var_notes']=$variable_import['var_notes'];
			$variable_db['var_valrng']=$variable_import['var_valrng'];
			$variable_db['var_invalrng']=$variable_import['var_invalrng'];			
		}

		//variable weights: var_wgt = 0/1 (is this variable the weight)
		if (in_array("variable_weights", $import_options)){
			$variable_db['var_wgt'] = isset($variable_import['var_wgt']) ? (int)$variable_import['var_wgt'] : 0;
		}

		return $variable_db;
	}


	/** 
	 * 
	 * return variable category value/labels 
	 * 
	 * */
	function get_variable_category_value_labels($variable)
	{
		$var_catgry_labels=[];
		if (!isset($variable['var_catgry'])){
			return $var_catgry_labels;
		}

		foreach($variable['var_catgry'] as $catgry)
		{
			$var_catgry_labels[]=array(
				'value'=>$catgry['value'],
				'labl'=>$catgry['labl']
			);
		}

		return $var_catgry_labels;
	}    

}


