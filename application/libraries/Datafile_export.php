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

            if ($variable['user_missings']!=''){
				$missings=explode(",",$variable['user_missings']);
				foreach($missings as $idx=>$missing){
					if (is_numeric($missing)){												
						$params['missings'][trim($variable['name'])][]=intval($missing);
					}
				}
            }*/

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
                    if (trim($cat_value_label['labl'])!=''){
                        $has_code_labels=true;
					    $tmp_code_labels->{$cat_value_label['value']}=$cat_value_label['labl'];
                    }
				}

                //if ($has_code_labels){
                    $params['value_labels'][$variable['name']]=$tmp_code_labels;
                ///}
			}

			//name/labels
			$params['name_labels'][$variable['name']]=$variable['metadata']['labl'];
        }

        return $params;
    }
}

