<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Project_json_writer
{
		
	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Editor_template_model');		
		$this->ci->load->model('Editor_datafile_model');
		$this->ci->load->model('Editor_variable_model');		
	}


	/**
	 * 
	 * Export project metadata as JSON
	 * 
	 * @output_file (optional) path to output file
	 * 
	 */
	function download_project_json($sid, $exclude_private_fields=0)
	{
		$json_path=$this->generate_project_json($sid, $exclude_private_fields);

		//download json
		if(file_exists($json_path)){
			header("Content-type: application/json; charset=utf-8");
			$stdout = fopen('php://output', 'w');			
			$fh = fopen($json_path, 'r');
			stream_copy_to_stream($fh, $stdout);
			fclose($fh);
			fclose($stdout);
		}
	}

	/**
	 * 
	 * Remove private fields from JSON
	 * 
	 */
	function json_remove_private_fields($sid,&$json)
	{
		//load project template
		$template=$this->ci->Editor_template_model->get_project_template($sid);
		
		//$project=$this->ci->Editor_model->get_row($sid);
		//$metadata=(array)$project['metadata'];

		$output=array();
		$this->walk_template($template['template']['items'], $output);

		foreach($output as $private_field){
			array_unset_value($json, $private_field, $glue = '.');
		}

		return $json;
	}
	

	/**
	 * 
	 * Walk template and get private fields
	 * 	 
	 */
	function walk_template($items, &$output)
	{		
		foreach($items as $key=>$item){
			$is_private=isset($item['is_private']) ? $item['is_private'] : false;


			if ($is_private){
				//echo "Key: ".$item['key']. " - " . $is_private  . "\n";
				$output[]=$item['key'];
			}

			/*if (isset($item['props'])){
				$this->walk_template_props($item['props'], $item_path=$item['key'], $metadata);
			}*/

			if (isset($item['items'])){
				$this->walk_template($item['items'], $output);
			}
		}
	}

	/*function walk_template_props($props, $item_path, &$metadata)
	{
		foreach($props as $key=>$prop){

			$is_private=isset($prop['is_private']) ? $prop['is_private'] : false;

			if ($is_private){
				echo "Key: ".$prop['prop_key']. " - " . $is_private  .$item_path.'.*.'.$prop['key']. "\n";
			}

			if (isset($prop['props'])){
				$this->walk_template_props($prop['props'], $item_path.'.*.'.$prop['key'], $metadata);
			}
			//echo "Prop: ".$prop['key']. "\n";
		}
	}*/



	/**
	 * 
	 * Generete project JSON
	 */
	function generate_project_json($sid, $exclude_private_fields=0)
	{
		$project=$this->ci->Editor_model->get_row($sid);
		$project_folder=$this->ci->Editor_model->get_project_folder($sid);

		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("download_project_json::Project folder not found");
		}

		$filename=trim((string)$project['idno'])!=='' ? trim($project['idno']) : md5($project['id']);
		$output_file=$project_folder.'/'.$filename.'.json';

		$fp = fopen($output_file, 'w');

		$metadata=(array)$project['metadata'];

		if ($exclude_private_fields==1){
			$this->json_remove_private_fields($sid,$metadata);
		}

		$basic_info=array(
			'type'=>$project['type'],
			'idno'=>$project['idno'],
		);
		
		$output=array_merge($basic_info, $metadata );

		if($project['type']=='survey'){			
			$output['data_files'] = function () use ($sid) {
				$files=$this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);
				if ($files){
					foreach($files as $file){
						unset($file['id']);
						unset($file['sid']);
						yield $file;
					}
				}
			};

			$output['variables'] = function () use ($sid) {
				foreach($this->ci->Editor_variable_model->chunk_reader_generator($sid) as $variable){
					$variable=$this->transform_variable($variable);
					yield $variable['metadata'];
				}
			};

			/*$output['variable_groups'] = function () use ($sid) {
				$var_groups=$this->Variable_group_model->select_all($sid);
				foreach($var_groups as $var_group){
					yield $var_group;
				}			
			};*/
		}
		
		$encoder = new \Violet\StreamingJsonEncoder\StreamJsonEncoder(
			$output,
			function ($json) use ($fp) {
				fwrite($fp, $json);
			}
		);
		//$encoder->setOptions(JSON_PRETTY_PRINT);
		$encoder->encode();
		fclose($fp);
		
		return $output_file;
	}

	function transform_variable($variable)
	{		
		$sid=(int)$variable['sid'];
		unset($variable['metadata']['uid']);
		unset($variable['metadata']['sid']);

		$var_catgry_labels=$this->get_indexed_variable_category_labels($variable['metadata']["var_catgry_labels"]);

		//process summary statistics
		$sum_stats_options = isset($variable['metadata']['sum_stats_options']) ? $variable['metadata']['sum_stats_options'] : [];
		$sum_stats_enabled_list=[];
		foreach($sum_stats_options as $option=>$value){
			if ($value===true || $value==1){
				$sum_stats_enabled_list[]=$option;
			}
		}

		if (isset($variable['metadata']['var_sumstat']) && is_array($variable['metadata']['var_sumstat']) ){
			foreach($variable['metadata']['var_sumstat'] as $idx=>$sumstat){
				if (!in_array($sumstat['type'], $sum_stats_enabled_list)){
					unset($variable['metadata']['var_sumstat'][$idx]);
				}
			}
			//fix to get a JSON array instead of Object
			$variable['metadata']['var_sumstat']=array_values((array)$variable['metadata']['var_sumstat']);
		}

		//value ranges [counts, min, max] - remove min and max if not enabled
		if (isset($variable['metadata']['var_valrng']['range']) && is_array($variable['metadata']['var_valrng']['range']) ){
			foreach($variable['metadata']['var_valrng']['range'] as $range_key=>$range){
				//only check for min and max
				if (!in_array($range_key, array("min", "max"))){
					continue;
				}

				if (!in_array($range_key, $sum_stats_enabled_list)){
					unset($variable['metadata']['var_valrng']['range'][$range_key]);
				}
			}
		}

		//remove category freq if not enabled
		if (!in_array('freq', $sum_stats_enabled_list)){
			if (isset($variable['metadata']['var_catgry']) && is_array($variable['metadata']['var_catgry']) ){
				foreach($variable['metadata']['var_catgry'] as $idx=>$cat){

					//remove freq if not enabled
					if (isset($cat['stats']) && is_array($cat['stats']) ){
						foreach($cat['stats'] as $stat_idx=>$stat){
							if ($stat['type']=='freq'){
								unset($variable['metadata']['var_catgry'][$idx]['stats'][$stat_idx]);
							}
						}						
					}
				}
			}
		}

		//add var_catgry labels
		if (isset($variable['metadata']['var_catgry']) && is_array($variable['metadata']['var_catgry']) ){
			foreach($variable['metadata']['var_catgry'] as $idx=>$cat){
				if (isset($var_catgry_labels[$cat['value']])){
					$variable['metadata']['var_catgry'][$idx]['labl']=$var_catgry_labels[$cat['value']];
				}
			}
		}


		//var_wgt_id field - replace UID with VID
		if (isset($variable['metadata']['var_wgt_id']) && $variable['metadata']['var_wgt_id']!==''){
			$variable['metadata']['var_wgt_id']=$this->ci->Editor_variable_model->vid_by_uid($sid,$variable['metadata']['var_wgt_id']);
		}

		array_remove_empty($variable);
		return $variable;
	}


	function get_indexed_variable_category_labels($cat_labels)
	{
		$output=array();
		foreach($cat_labels as $cat){
			if (isset($cat['labl']) && isset($cat['value'])){
				$output[$cat['value']]=$cat['labl'];
			}
		}

		return $output;
	}


}

