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
	 * import_options - [ "doc_desc", "study_desc", "data_files", "variable_info", "variable_categories", "variable_questions", "variable_weights", "variable_groups" ]
	 * 
	 * 
	 */
	function import_ddi($sid, $ddi_path, $options=array(), $import_options=array())
	{
		$parser_params=array(
			'file_type'=>'survey',
			'file_path'=>$ddi_path
		);

		$this->ci->load->library('DDI2_import');
		$this->ci->load->library('Metadata_parser', $parser_params);

		$parser=$this->ci->metadata_parser->get_reader();
		$project_json=$this->ci->ddi2_import->transform_ddi_fields($parser->get_metadata_array());

		$project_db=$this->ci->Editor_model->get_row($sid);

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


		// update data files		
		// - match by data file name
		// - update only the description field

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

			$update_options=array(
				'description'=>$data_file['description'],
			);

			if (in_array("data_files",$import_options)){
				$this->ci->Editor_datafile_model->update_by_filename($sid,$data_file['file_name'],$update_options);
			}
        }
        

        //import variables
		// - match data files by file name
		// - update only
		// - "variable_info", "variable_categories", "variable_questions", "variable_weights"
        
		//list with file name as key and fid as value
		$db_datafiles_names_map=$this->ci->Editor_datafile_model->file_id_name_list($sid);

		$variable_iterator=$parser->get_variable_iterator();

		foreach($variable_iterator as $var_obj)
        {
            $variable=$var_obj->get_metadata_array();
            $variable['fid']=$variable['file_id'];

			//skip
			if (!isset($data_files[$variable['fid']])){
				continue;
			}

			//update variable info
			// - match by variable name
			// - match by data file name [ db requires fid, get fid from db using data_file_name]

			//data file name
			$data_file_name=$data_files[$variable['fid']]['file_name'];

			//get variable from db
			$variable_db=$this->ci->Editor_variable_model->get_variable_by_filename($sid,$data_file_name,$variable['name']);

			//skip
			if (!$variable_db){
				continue;
			}

            $variable['var_catgry_labels']=$this->get_variable_category_value_labels($variable);
            
			//update/merge variable metadata
			$variable_db['metadata']=$this->update_variable_metadata($variable_db['metadata'], $variable, $import_options);

			//update db
			$this->ci->Editor_variable_model->update($sid,$variable_db['uid'],$variable_db);
		}


		/*
        //import variable groups
        $this->create_update_variable_groups($sid,$parser->get_variable_groups());
		*/	
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

		//variable weights
		if (in_array("variable_weights", $import_options)){
			//$variable_db['var_wgt_id']=$variable_import['var_wgt_id'];
			$variable_db['var_wgt']=$variable_import['var_wgt'];
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


