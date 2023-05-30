<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Datafile_update
{
	function __construct()
	{
		$this->ci =& get_instance();
	}


	function update($sid,$file_id,$datafile_path)
	{		
		//get file basic metadata [rows, columns, variable name and label]
		$var_names_labels=$this->ci->datautils->get_file_name_labels($datafile_path);
		
		if (!isset($var_names_labels['variables'])){
			throw new Exception("No variables found in file");
		}

		//file info
		$datafile=$this->ci->Editor_datafile_model->data_file_by_id($sid,$file_id);

		//get names of variables in file
		$names=$this->ci->Editor_variable_model->get_variable_names_by_file($sid,$file_id);

		//compare variable names
		$imported_names=array_column($var_names_labels['variables'],"name");

		$compared=array_diff($names,$imported_names);

		if (count($compared)>0){
			throw new Exception("Variables not found in the file: " . substr(implode(", ", $compared), 0, 100));
		}

		//if all variable names match, replace/append
		//replace file



			//append file
			
		//if variable names do not match, throw error

		$output=array(
			'status'=>'success',
			'uploaded_path'=>$datafile_path,
			'var_names_labels'=>$var_names_labels,
			'compared'=>$compared,
			'datafile'=>$datafile,
			'datafile_names'=>$names,
			//'uploaded_file_name'=>$uploaded_file_name,
			//'base64'=>base64_encode($uploaded_file_name)				
		);
					
		return $output;
	}
}

