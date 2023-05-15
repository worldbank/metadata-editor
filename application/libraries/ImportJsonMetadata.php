<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * 
 * 
 * Import project metadata from JSON file
 * 
 * 
 */
class ImportJsonMetadata
{

	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "ImportJsonMetadata Class Initialized.");
		$this->ci =& get_instance();

		$this->ci->load->model("Editor_model");
        $this->ci->load->model("Editor_datafile_model");
        $this->ci->load->model("Editor_variable_model");
        $this->ci->load->model("Editor_variable_groups_model");
	}
    
    function import($sid,$json_file_path,$validate=true,$options=array())
    {
        $json = file_get_contents($json_file_path);
        $json_data = json_decode($json, true);

        if (!isset($json_data['type'])){
            throw new Exception("Invalid JSON file. Missing 'type' field.");
        }

        $type=$json_data['type'];

        if (isset($options['type']) && $options['type']!=$type){
            throw new Exception("Project type mismatched. The metadata 'type' is set to '".$json_data['type']."', but the project type is set to '".$options['type']."'.");
        }

        $json_data=array_merge($json_data, $options);

        //fix for geospatial
        if ($type=='geospatial'){
			if (isset($json_data['description']['identificationInfo']) && 
                    is_array($json_data['description']['identificationInfo']) &&
                    isset($json_data['description']['identificationInfo'][0])
                ){
				$json_data['description']['identificationInfo']=$json_data['description']['identificationInfo'][0];
			}
		}

        if ($type=='survey'){
            $this->import_microdata_project($type, $sid,$json_data,$validate);
        }
        else{
            //import project metadata
            $this->import_project_metadata($type, $sid,$json_data,$validate);
        }
    }


    function import_microdata_project($type, $sid,$json_data,$validate=true)
    {
        $datafiles=[];
        $variables=[];
        $variable_groups=[];

        //get data files, variables and variable groups
        if (isset($json_data['data_files'])){
            $datafiles=$json_data['data_files'];
            unset($json_data['data_files']);
        }

        if (isset($json_data['variables'])){
            $variables=$json_data['variables'];
            unset($json_data['variables']);
        }

        if (isset($json_data['variable_groups'])){
            $variable_groups=$json_data['variable_groups'];
            unset($json_data['variable_groups']);
        }

        $this->import_project_metadata($type, $sid,$json_data, $validate);

        //import data file metadata
        $file_id_mappings= $this->import_datafile_metadata($sid,$datafiles, $validate);

        //import variable metadata
        $this->import_variable_metadata($sid,$variables, $file_id_mappings, $validate);

        //import variable groups
        //$this->import_variable_groups($sid,$variable_groups, $validate);
    }

    /**
     * 
     * Import project metadata
     * 
     */
    function import_project_metadata($type,$sid,$json_data,$validate=true)
    {
        $this->ci->Editor_model->update_project($type,$sid,$json_data,$validate);
    }
    

    /**
     * 
     * Import data file metadata
     * 
     */
    function import_datafile_metadata($sid,$datafiles,$validate=true)
    {
        $file_id_mapping=[];//need these to map file_id in variables

        foreach($datafiles as $datafile)
        {
            //check if file exists
            $file_info=$this->ci->Editor_datafile_model->data_file_by_name($sid,$datafile['file_name']);

            if ($file_info){
                $file_id_mapping[$datafile['file_id']]=$file_info['file_id'];
                $datafile['file_id']=$file_info['file_id'];

                if ($validate){
                    $this->ci->Editor_datafile_model->validate($datafile);
                }            
    
                $this->ci->Editor_datafile_model->update($file_info['id'],$datafile);
            }
            else{
                $file_id=$this->ci->Editor_datafile_model->generate_fileid($sid);
                $file_id_mapping[$datafile['file_id']]=$file_id;
                $datafile['file_id']=$file_id;

                if ($validate){
                    $this->ci->Editor_datafile_model->validate($datafile);
                }
                
                $this->ci->Editor_datafile_model->insert($sid,$datafile);
            }
        }
        return $file_id_mapping;
    }


    /**
     * 
     * Import variable metadata
     * 
     */
    function import_variable_metadata($sid,$variables, $file_id_mappings, $validate=true)
    {
        foreach($variables as $variable){

            if ($validate){
                $this->ci->Editor_variable_model->validate($variable);
            }

            $fid=$file_id_mappings[$variable['fid']];
            $variable['fid']=$fid;

            //check if variable exists
            $variable_info=$this->ci->Editor_variable_model->variable_by_name($sid,$fid, $variable['name']);
            $variable['var_catgry_labels']=$this->get_variable_category_value_labels($variable);

            //remove fields
            $exclude=array("uid","sid");
            foreach($exclude as $field)
            {
                if (isset($variable[$field])){
                    unset($variable[$field]);
                }
            }            
            
            $variable['metadata']=$variable;

            if ($variable_info){
                $this->ci->Editor_variable_model->update($sid, $variable_info['uid'],$variable);
            }
            else{
                //if not exists, insert
                $this->ci->Editor_variable_model->insert($sid,$variable);
            }
        }
    }


    function get_variable_category_value_labels($variable)
    {
        $labels=[];

        if (isset($variable['var_catgry'])){
            foreach($variable['var_catgry'] as $category){
                if (isset($category['value'])){                    
                    $labels[]=array(
                        'value'=>$category['value'],
                        'labl'=>isset($category['labl']) ? $category['labl'] : ''
                    );
                }
            }
        }

        return $labels;

    }

    

}


