<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use League\Csv\Reader;
use League\Csv\Statement;


class Datafile_update
{
	function __construct()
	{
		$this->ci =& get_instance();
	}


	function update($sid,$file_id,$datafile_path,$user_id=null) 
	{
		$file_ext=strtolower(pathinfo($datafile_path,PATHINFO_EXTENSION));

		//file info
		$datafile=$this->ci->Editor_datafile_model->data_file_by_id($sid,$file_id);
		if (!$datafile){
			throw new Exception("Data file not found: ".$file_id);
		}

		//get names of variables in file
		$names=$this->ci->Editor_variable_model->get_variable_names_by_file($sid,$file_id);

		$var_names_labels=array();
		if ($file_ext=='csv'){
			$var_names_labels['variables']=$this->get_csv_columns($datafile_path);
			$imported_names=array_column($var_names_labels['variables'],"name");
			$compared=array_diff($names,$imported_names);
			if (count($compared)>0){
				throw new Exception("Variables not found in the file: " . substr(implode(", ", $compared), 0, 100));
			}
			$file_info=array('format'=>'csv','format_version'=>null);
		}
		else{
			// Validate columns + capture format/version via FastAPI name-labels
			$var_names_labels=$this->ci->datautils->get_file_name_labels($datafile_path, array(
				'expected_columns' => $names,
				'include_file_info' => true,
				'include_comparison' => true,
				'columns_only' => true,
			));
			if (!isset($var_names_labels['variables'])){
				throw new Exception("No variables found in file");
			}
			$comparison=isset($var_names_labels['comparison']) ? $var_names_labels['comparison'] : null;
			if (is_array($comparison) && empty($comparison['match'])){
				$missing=isset($comparison['missing_in_file']) ? $comparison['missing_in_file'] : array();
				throw new Exception("Variables not found in the file: " . substr(implode(", ", $missing), 0, 100));
			}
			// Fallback if comparison not returned
			if (!is_array($comparison)){
				$imported_names=array_column($var_names_labels['variables'],"name");
				$compared=array_diff($names,$imported_names);
				if (count($compared)>0){
					throw new Exception("Variables not found in the file: " . substr(implode(", ", $compared), 0, 100));
				}
			}
			$file_info=isset($var_names_labels['file_info']) && is_array($var_names_labels['file_info'])
				? $var_names_labels['file_info']
				: array('format'=>$file_ext,'format_version'=>null);
		}

		$target_filepath=$this->ci->Editor_model->get_project_folder($sid).'/data/'.$datafile['file_name'].'.'.$file_ext;

		//create folder if not already exists
		if (!file_exists($this->ci->Editor_model->get_project_folder($sid).'/data/')){
			mkdir($this->ci->Editor_model->get_project_folder($sid).'/data/',0777,true);
		}

		//replace original data file
		rename($datafile_path,$target_filepath);

		$source_update=$this->ci->Editor_datafile_model->build_source_fields_from_path(
			$target_filepath,
			basename($datafile_path)
		);
		$source_update=array_merge(
			$source_update,
			$this->ci->Editor_datafile_model->source_fields_from_file_info($file_info)
		);
		$now=date('U');
		if (!empty($source_update['source_format']) && $source_update['source_format'] !== 'csv') {
			$source_update['source_attached_at']=$now;
			if ($user_id !== null && $user_id !== '') {
				$source_update['source_attached_by']=(int)$user_id;
			}
		}

		$update_fields=array_merge(array(
			'file_physical_name'=>basename($target_filepath)
		), $source_update);

		$this->ci->Editor_datafile_model->update($datafile['id'],$update_fields);
	
		$output=array(
			'status'=>'success',
			'uploaded_path'=>$datafile_path,
			'var_names_labels'=>$var_names_labels,
			'compared'=>isset($compared)?$compared:array(),
			'comparison'=>isset($comparison)?$comparison:null,
			'file_info'=>$file_info,
			'datafile'=>$datafile,
			'datafile_names'=>$names,
			'target_filepath'=>$target_filepath,
			'datafile_path'=>$datafile_path,
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

