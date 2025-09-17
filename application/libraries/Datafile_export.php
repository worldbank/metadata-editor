<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * Export data file to formats [csv, dta, sav, rda, por, sas7bdat, xpt, xlsx, xls]
 * 
 */
class Datafile_export
{
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model("Editor_model");
		$this->ci->load->model("Editor_datafile_model");
		$this->ci->load->model("Editor_variable_model");
		//$this->ci->load->model("Editor_variable_groups_model");
	}
	

	/**
     * 
     *  Create params for data dictionary export
     * 
     * @ format - export format (sav, dta)
     * 
     */
    function get_export_params($sid, $fid, $format)
    {
        $datafile_path=$this->ci->Editor_datafile_model->get_file_csv_path($sid,$fid);

        if (!$datafile_path){
            throw new Exception("Data file CSV not found");
        }
			
        $this->ci->db->select("name,field_dtype,user_missings,is_weight,var_wgt_id,metadata");
        $this->ci->db->where("sid",$sid);
        $this->ci->db->where("fid",$fid);        
        $variables=$this->ci->db->get("editor_variables")->result_array();

        $params=array(
            'file_path'=> realpath($datafile_path),
			'export_format'=>$format,
        );

        $dtype_map=array(
            //'numeric'=>'float',
            'string'=>'object',
            'character'=>'object'
        );

        $missing_values=array();

        foreach($variables as $variable){
            if (isset($variable['var_wgt_id']) && $variable['var_wgt_id']>0 ){
                $params['weights'][]=array(
                    'field'=>$variable['name'],
                    'weight_field'=>$this->ci->Editor_variable_model->get_name_by_var_wgt_id($sid,$variable['var_wgt_id'])
                );
            }
            
            /*
            // we cannot export user defined missings
            // user defined missings should be added a as category value/labels
            // this is because STATA/SPSS, won't recognize user defined missings
            */

            if (trim($variable['user_missings'])!=''){
                // Validate user_missings for Stata export
                if ($this->validate_user_missings_for_stata($variable, $format)) {
                    $missings=explode(",",$variable['user_missings']);
                    foreach($missings as $idx=>$missing){
                        $missing = trim($missing);
                        
                        // For Stata export, only include valid missing values
                        if ($format === 'dta') {
                            if ($this->is_valid_stata_missing_value($missing)) {
                                // Convert numeric missing values to proper format for Stata
                                if ($missing === '.') {
                                    $params['missings'][trim($variable['name'])][] = '.';
                                } else {
                                    // Single letters a-z are already valid
                                    $params['missings'][trim($variable['name'])][] = $missing;
                                }
                            }
                        } else {
                            // For other formats, use original logic
                            if ($variable['field_dtype']!='character'){
                                if (!$this->is_string_value($missing)){
                                    $params['missings'][trim($variable['name'])][]=$missing+0;
                                }
                            }else{	
                                $params['missings'][trim($variable['name'])][]=$missing;
                            }
                        }
                    }
                }
                // For character field_dtype in Stata, user_missings are excluded
            }

            if ($variable['field_dtype']!=''){
                if (isset($dtype_map[$variable['field_dtype']])){
                    $params['dtypes'][$variable['name']]= $dtype_map[$variable['field_dtype']];
                }
            }

			//value/labels
			$variable['metadata']=$this->ci->Editor_model->decode_metadata($variable['metadata']);
			if (isset($variable['metadata']['var_catgry_labels']) && is_array($variable['metadata']['var_catgry_labels']) && count($variable['metadata']['var_catgry_labels'])>0){
				$catgry_labels=(array)$variable['metadata']['var_catgry_labels'];

                $tmp_code_labels=new stdClass();
                $has_code_labels=false;			
				foreach($catgry_labels as $cat_value_label){
                    if (isset($cat_value_label['labl']) && trim($cat_value_label['labl'])!=''){
                        $has_code_labels=true;
					    $tmp_code_labels->{$cat_value_label['value']}=$cat_value_label['labl'];
                    }
				}

                // validate value labels - SPSS supports string codes for value/labels while STATA does not
                // For stata, validate if value/labels contain string codes, if yes, exclude from export
                if (in_array($format, ['dta', 'sav']) && $has_code_labels) {
                    try{
                        $this->validate_variable_value_labels($variable, $tmp_code_labels, $format);
                    } catch (Exception $e) {
                        $has_code_labels=false;
                    }
                }

                if ($has_code_labels){
                    $params['value_labels'][$variable['name']]=$tmp_code_labels;
                }
			}

			//name/labels
			$params['name_labels'][$variable['name']]=$variable['metadata']['labl'];
        }

        return $params;
    }

    /**
     * Comprehensive validation for export formats - checks both user_missings and value_labels
     * 
     * @param int $sid - project ID
     * @param int $fid - file ID
     * @param string $format - export format (dta, sav)
     * @param bool $stop_on_first_error - if true, stops on first validation error; if false, collects all errors
     * @return array - validation result
     */
    function validate_datafile_export($sid, $fid, $format, $stop_on_first_error = true)
    {
        $this->ci->db->select("name,field_dtype,user_missings,is_weight,var_wgt_id,metadata");
        $this->ci->db->where("sid", $sid);
        $this->ci->db->where("fid", $fid);        
        $variables = $this->ci->db->get("editor_variables")->result_array();

        $validation_results = array(
            'valid' => true,
            'errors' => array(),
            'variables_checked' => 0,
            'variables_with_missings' => 0,
            'variables_with_labels' => 0,
            'missing_value_errors' => array(),
            'value_label_errors' => array()
        );

        foreach ($variables as $variable) {
            $validation_results['variables_checked']++;            
            $variable['metadata'] = $this->ci->Editor_model->decode_metadata($variable['metadata']);

            // Validate user_missings
            if (trim($variable['user_missings']) != '') {
                $validation_results['variables_with_missings']++;
                
                if (!$this->validate_user_missings_for_stata($variable, $format)) {
                    $validation_results['valid'] = false;
                    
                    if ($variable['field_dtype'] === 'character') {
                        $error_message = "Variable '{$variable['name']}' has field_dtype 'character' and contains user_missings which are not allowed for Stata export.";
                    } else {
                        $invalid_missings = array();
                        $missings = explode(",", $variable['user_missings']);
                        foreach ($missings as $missing) {
                            $missing = trim($missing);
                            if (!$this->is_valid_stata_missing_value($missing)) {
                                $invalid_missings[] = $missing;
                            }
                        }
                        $invalid_missings_str = implode(', ', $invalid_missings);
                        $error_message = "Variable '{$variable['name']}' contains invalid missing values [{$invalid_missings_str}] for Stata export. Only '.' and single letters 'a' to 'z' are allowed.";
                    }
                    
                    $error_data = array(
                        'variable_name' => $variable['name'],
                        'field_dtype' => $variable['field_dtype'],
                        'error' => $error_message,
                        'error_type' => 'missing_values'
                    );
                    
                    $validation_results['errors'][] = $error_data;
                    $validation_results['missing_value_errors'][] = $error_data;
                    
                    if ($stop_on_first_error) {
                        break;
                    }
                }
            }

            // Validate value labels
            if ($validation_results['valid'] || !$stop_on_first_error) {
                if (isset($variable['metadata']['var_catgry_labels']) && 
                    is_array($variable['metadata']['var_catgry_labels']) && 
                    count($variable['metadata']['var_catgry_labels']) > 0) {
                    
                    $validation_results['variables_with_labels']++;
                    
                    $catgry_labels = (array)$variable['metadata']['var_catgry_labels'];
                    $tmp_code_labels = new stdClass();
                    $has_code_labels = false;
                    
                    foreach ($catgry_labels as $cat_value_label) {
                        if (isset($cat_value_label['labl']) && trim($cat_value_label['labl']) != '') {
                            $has_code_labels = true;
                            $tmp_code_labels->{$cat_value_label['value']} = $cat_value_label['labl'];
                        }
                    }

                    if ($has_code_labels) {
                        try {
                            $this->validate_variable_value_labels($variable, $tmp_code_labels, $format, $stop_on_first_error);
                        } catch (Exception $e) {
                            $validation_results['valid'] = false;
                            
                            $error_data = array(
                                'variable_name' => $variable['name'],
                                'error' => $e->getMessage(),
                                'error_type' => 'value_labels'
                            );
                            
                            $validation_results['errors'][] = $error_data;
                            $validation_results['value_label_errors'][] = $error_data;
                            
                            if ($stop_on_first_error) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $validation_results;
    }




    /**
     * 
     * Validate value labels for a single variable
     * 
     * Check values in value labels are compatible with the specified export format
     * For STATA: Check values are strings including numbers with leading zeros
     * examples:
     *  numeric values with leading zeros "01" "02" "03" 
     *  strings values e.g. "1st" "ND" "yes" "no" 
     * 
     * ignores stata special missing values:
     *  - "a" "b" "c" to "z" 
     * 
     * @param array $variable - variable array
     * @param array $value_labels - value labels array
     * @param string $format - export format (sav, dta)
     * @param bool $stop_on_first_error - if true, throws exception on first invalid value; if false, collects all invalid values
     * 
     */
    private function validate_variable_value_labels($variable, $value_labels, $format, $stop_on_first_error = true)
    {
        $variable_name = $variable['name'];
        $invalid_values = array();
               
        //currently only validate for Stata [dta] format
        if ($format === 'dta') {
            foreach ($value_labels as $value => $label) {
                if ($this->is_string_value($value)) {
                    if ($stop_on_first_error) {
                        throw new Exception("STATA Export Error: Variable '{$variable_name}' has string value '{$value}' which is not compatible with STATA export format.");
                    } else {
                        $invalid_values[] = $value;
                    }
                }
            }
            
            if (!$stop_on_first_error && !empty($invalid_values)) {
                $invalid_values_str = implode(', ', $invalid_values);
                throw new Exception("STATA Export Error: Variable '{$variable_name}' has string values [{$invalid_values_str}] which are not compatible with STATA export format.");
            }
        }        
    }
    
    /**
     * Check if a value should be treated as string for STATA export
     * 
     * @param string $value The value to check
     * @return bool True if value should be treated as string
     */
    private function is_string_value($value)
    {
        // Ignore single character values a-z (STATA special missing values)
        if (strlen($value) === 1 && preg_match('/^[a-z]$/', $value)) {
            return false;
        }
        
        // Check for numeric values with leading zeros (e.g., "01", "02")
        if (preg_match('/^0\d+$/', $value)) {
            return true;
        }
        
        // If it's a valid numeric value (including negative numbers), treat as numeric
        if (is_numeric($value)) {
            return false;
        }
        
        // Check for non-numeric values that are not single letters
        if (!is_numeric($value) && !preg_match('/^[a-z]$/', $value)) {
            return true;
        }
        
        return false;
    }

    /**
     * Validate user_missings for Stata export
     * 
     * For Stata export:
     * 1. Variables with field_dtype "character" cannot have user_missings
     * 2. Only valid Stata missing values are allowed: "." and letters "a" to "z"
     * 
     * @param array $variable - variable array containing field_dtype and user_missings
     * @param string $format - export format (dta, sav)
     * @return bool - true if user_missings are valid for the format, false otherwise
     */
    private function validate_user_missings_for_stata($variable, $format)
    {
        if ($format !== 'dta') {
            return true; // Only validate for Stata export
        }
        
        // If field_dtype is "character", user_missings should be excluded for Stata
        if ($variable['field_dtype'] === 'character') {
            return false;
        }
        
        // Validate individual missing values for Stata compatibility
        if (trim($variable['user_missings']) != '') {
            $missings = explode(",", $variable['user_missings']);
            foreach ($missings as $missing) {
                $missing = trim($missing);
                if (!$this->is_valid_stata_missing_value($missing)) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Check if a missing value is valid for Stata export
     * 
     * Stata only accepts:
     * - "." (dot) for numeric missing values
     * - Single letters "a" to "z" for extended missing values
     * 
     * @param string $value - the missing value to check
     * @return bool - true if valid Stata missing value, false otherwise
     */
    private function is_valid_stata_missing_value($value)
    {
        // Stata accepts "." for numeric missing values
        if ($value === '.') {
            return true;
        }
        
        // Stata accepts single letters a-z for extended missing values
        if (strlen($value) === 1 && preg_match('/^[a-z]$/', $value)) {
            return true;
        }
        
        return false;
    }

    private function is_valid_missing_value($value, $format)
    {
        if ($format === 'dta') {
            //

            return $this->is_string_value($value);
        }
        
        return true;
    }
}

