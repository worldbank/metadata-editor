<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use League\Csv\Reader;
use League\Csv\Statement;


class Datafile_update
{
	function __construct()
	{
		$this->ci =& get_instance();
	}


	function update($sid,$file_id,$datafile_path) 
	{
		$file_ext=pathinfo($datafile_path,PATHINFO_EXTENSION);

		//csv?
		if (strtolower($file_ext)=='csv'){
			$var_names_labels['variables']=$this->get_csv_columns($datafile_path);
		}
		else{
			//get file basic metadata [rows, columns, variable name and label]
			$var_names_labels=$this->ci->datautils->get_file_name_labels($datafile_path);
		}

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

		//create a new csv file name using the original file name		
		//$df_parts=pathinfo($datafile['file_physical_name']);

		$target_filepath=$this->ci->Editor_model->get_project_folder($sid).'/data/'.$datafile['file_name'].'.'.$file_ext;

		//create folder if not already exists
		if (!file_exists($this->ci->Editor_model->get_project_folder($sid).'/data/')){
			mkdir($this->ci->Editor_model->get_project_folder($sid).'/data/',0777,true);
		}

		//replace original data file
		rename($datafile_path,$target_filepath);

		$this->ci->Editor_datafile_model->update($datafile['id'],array(
			'file_physical_name'=>basename($target_filepath)
		));
	
		$output=array(
			'status'=>'success',
			'uploaded_path'=>$datafile_path,
			'var_names_labels'=>$var_names_labels,
			'compared'=>$compared,
			'datafile'=>$datafile,
			'datafile_names'=>$names,
			'target_filepath'=>$target_filepath,
			'datafile_path'=>$datafile_path,
			//'uploaded_file_name'=>$uploaded_file_name,
			//'base64'=>base64_encode($uploaded_file_name)				
		);
					
		return $output;
	}

	function get_csv_columns($csv_file_path)
	{			
		if (!file_exists($csv_file_path)){
			throw new Exception("CSV_FILE_NOT_FOUND: ".$csv_file_path);
		}

		$csv = Reader::createFromPath($csv_file_path, 'r');
		$csv->setHeaderOffset(0); //set the CSV header offset
		$offset=0;
		$limit=1;

		$columns = $csv->getHeader(); //returns the CSV header record
		
		$output=array();
		foreach($columns as $column_name){
			$output[]=array(
				'name'=>$column_name
			);
		}

		return $output;
	}

}

