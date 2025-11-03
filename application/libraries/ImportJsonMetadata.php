<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * 
 * Import project metadata from JSON or XML files
 * 
 * Supports:
 * - JSON metadata files (all project types)
 * - XML/DDI files (survey projects)
 * - XML metadata files (geospatial projects)
 * 
 * The class automatically detects the file type and routes to the appropriate importer.
 * For survey projects, it handles complete microdata import including datafiles and variables.
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
    
    /**
     * 
     * Import metadata from JSON or XML file
     * 
     * Tries JSON first, falls back to XML if JSON is invalid or type is survey
     * 
     * @param int $sid - Project ID
     * @param string $file_path - Path to JSON or XML file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options (type, created_by, etc.)
     * @return bool|array - Returns true or import result array
     * 
     */
    function import($sid,$file_path,$validate=true,$options=array())
    {
        // Validate file exists
        if (!file_exists($file_path)){
            throw new Exception("File not found: " . $file_path);
        }

        // Detect file type
        $file_info = pathinfo($file_path);
        $file_ext = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';

        // Check if file has an extension
        if (empty($file_ext)){
            throw new Exception("File has no extension. File: " . $file_path . ". Only JSON and XML files are supported.");
        }

        // Route to appropriate importer based on file extension (try JSON first, then XML)
        if ($file_ext == 'json'){
            return $this->import_from_json($sid, $file_path, $validate, $options);
        }
        else if ($file_ext == 'xml'){
            return $this->import_from_xml($sid, $file_path, $validate, $options);
        }
        else{
            throw new Exception("Unsupported file type: " . $file_ext . ". File: " . $file_path . ". Only JSON and XML files are supported.");
        }
    }
    

    /**
     * 
     * Import metadata from JSON file
     * 
     */
    private function import_from_json($sid,$json_file_path,$validate=true,$options=array())
    {
        // Read file
        $json = file_get_contents($json_file_path);
        
        if ($json === false){
            throw new Exception("Failed to read JSON file: " . $json_file_path);
        }

        // Decode JSON
        $json_data = json_decode($json, true);

        // Validate JSON parsing
        if ($json_data === null){
            $json_error = json_last_error();
            if ($json_error !== JSON_ERROR_NONE){
                throw new Exception("Invalid JSON format: " . json_last_error_msg());
            }
        }

        //type- check json_data['type'] or json_data['schematype']
        if (!isset($json_data['type']) && !isset($json_data['schematype'])){
            throw new Exception("Invalid JSON file. Missing 'type' field.");
        }

        $type=isset($json_data['type']) ? $json_data['type'] : $json_data['schematype'];
        $json_data['type']=$type;

        // Check type mismatch
        if (isset($options['type']) && $options['type']!=$type){
            throw new Exception("Project type mismatched. The metadata 'type' is set to '".$type."', but the project type is set to '".$options['type']."'.");
        }

        // Merge options into JSON data
        $json_data=array_merge($json_data, $options);

        //fix for geospatial - flatten identificationInfo array
        if ($type=='geospatial'){
			if (isset($json_data['description']['identificationInfo']) && 
                    is_array($json_data['description']['identificationInfo']) &&
                    isset($json_data['description']['identificationInfo'][0])
                ){
				$json_data['description']['identificationInfo']=$json_data['description']['identificationInfo'][0];
			}
		}

        // Import based on project type
        if ($type=='survey'){
            $this->import_microdata_project($type, $sid,$json_data,$validate);
        }
        else{
            //import project metadata
            $this->import_project_metadata($type, $sid,$json_data,$validate);
        }

        return true;
    }


    /**
     * 
     * Import metadata from XML file (DDI for surveys)
     * 
     * @param int $sid - Project ID
     * @param string $xml_file_path - Path to XML file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options
     * @return array - Import result with details
     * 
     */
    private function import_from_xml($sid,$xml_file_path,$validate=true,$options=array())
    {
        // Determine project type
        $type = isset($options['type']) ? $options['type'] : null;

        // Check if we need to get type from existing project
        if (!$type){
            $project = $this->ci->Editor_model->get_basic_info($sid);
            if ($project){
                $type = $project['type'];
            }
        }

        // Only survey type supports XML import via DDI
        if ($type == 'survey'){
            return $this->import_ddi_from_file($sid, $xml_file_path, $validate, $options);
        }
        else if ($type == 'geospatial'){
            // Geospatial XML import
            $this->ci->load->library('Geospatial_import');
            $result = $this->ci->geospatial_import->import($sid, $xml_file_path);
            return $result;
        }
        else{
            throw new Exception("XML import is only supported for 'survey' and 'geospatial' project types. Current type: " . ($type ? $type : 'unknown'));
        }
    }


    /**
     * 
     * Import DDI/XML from file path (not file upload)
     * 
     * Uses the existing Editor_model::import_ddi_from_path() method to avoid code duplication
     * 
     * @param int $sid - Project ID
     * @param string $ddi_file_path - Path to DDI XML file
     * @param bool $validate - Whether to validate the metadata
     * @param array $options - Additional options
     * @return array - Import result
     * 
     */
    private function import_ddi_from_file($sid, $ddi_file_path, $validate, $options)
    {
        // Use the centralized DDI import method
        return $this->ci->Editor_model->import_ddi_from_path($sid, $ddi_file_path, $parseOnly=false, $options);
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
            // Validate required fields
            if (!isset($datafile['file_name'])){
                log_message('error', "Datafile missing 'file_name' field. Skipping datafile.");
                continue;
            }

            if (!isset($datafile['file_id'])){
                log_message('error', "Datafile missing 'file_id' field. Skipping datafile: " . $datafile['file_name']);
                continue;
            }

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

            // Validate variable has required fid field
            if (!isset($variable['fid'])){
                log_message('error', "Variable missing 'fid' field. Skipping variable: " . (isset($variable['name']) ? $variable['name'] : 'unknown'));
                continue;
            }

            // Check if file_id mapping exists
            if (!isset($file_id_mappings[$variable['fid']])){
                log_message('error', "File ID mapping not found for fid: " . $variable['fid'] . ". Skipping variable: " . (isset($variable['name']) ? $variable['name'] : 'unknown'));
                continue;
            }

            if ($validate){
                $this->ci->Editor_variable_model->validate($variable);
            }

            $fid=$file_id_mappings[$variable['fid']];
            $variable['fid']=$fid;

            //check if variable exists
            $variable_info=$this->ci->Editor_variable_model->variable_by_name($sid,$fid, $variable['name']);
            if (!isset($variable['var_catgry_labels'])){
                $variable['var_catgry_labels']=$this->get_variable_category_value_labels($variable);
            }

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


