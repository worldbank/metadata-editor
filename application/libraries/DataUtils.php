<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

use League\Csv\Reader;
use League\Csv\Statement;


class DataUtils
{

	//Data API base url
	private $DataApiUrl; //'http://localhost:2121';

	//temporary storage for creating files via data api
	private $DataStoragePath;	

	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "DataUtils Class Initialized.");
		require_once 'modules/guzzle/vendor/autoload.php';
		$this->ci =& get_instance();
		$this->ci->load->model("Editor_model");

		$this->ci->load->config("editor");

		$this->DataApiUrl = $this->ci->config->item('data_api_url', 'editor');
		$this->DataStoragePath=$this->ci->config->item('data_storage_path', 'editor');
	}


	/**
	 * 
	 * get status
	 * 
	 */
	public function status()
	{
		try{
			$client = new Client([
				'base_uri' => $this->DataApiUrl.'status'
			]);

			$request_body=[];
			$api_response = $client->request('GET');

			$response=json_decode($api_response->getBody()->getContents(),true);
			return $response;
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			return $error_output;
		}
	}

	public function import_file_meta($datafile_path)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'metadata'
		]);
		
		$request_body=[
			"file_path"=> realpath($datafile_path)
		];
			
		$api_response = $client->request('POST', '', [
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return $response;
	}

	
	public function generate_summary_stats($datafile_path)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'data-dictionary'
		]);
		
		$request_body=[
			"file_path"=> realpath($datafile_path)
		];
			
		$api_response = $client->request('POST', '', [
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return $response;
	}


	/**
	 * 
	 * 
	 *  $options - array of options
	 * 	- var_names - array of variable names
	 *  - weights - array of weights [weight_field, field]
	 * 
	 */
	public function generate_summary_stats_variable($datafile_path, $options)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'data-dictionary-variable'
		]);

		//$options['weights'][]=['weight_field'=>'a3_1','field'=>'a1'];

		$request_body=$options;		
		$request_body["file_path"]=realpath($datafile_path);
			
		$api_response = $client->request('POST', '', [
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return $response;
	}

	/**
	 * 
	 * 
	 *  $options - array of options
	 * 	- var_names - array of variable names
	 *  - weights - array of weights [weight_field, field]
	 * 
	 */
	public function generate_summary_stats_queue($datafile_path, $options)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'data-dictionary-queue'
		]);

		//$options['weights'][]=['weight_field'=>'a3_1','field'=>'a1'];

		$request_body=$options;		
		$request_body["file_path"]=realpath($datafile_path);
			
		$api_response = $client->request('POST', '', [
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return [
			'response'=>$response,
			'status_code'=>$api_response->getStatusCode() //e.g. 200
		];
	}

	//get summary stats queue job status
	public function get_job_status($job_id)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'jobs/'.$job_id
		]);
			
		$api_response = $client->request('GET', '', [
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return [
			'response'=>$response,
			'status_code'=>$api_response->getStatusCode() //e.g. 200
		];
	}


	public function generate_csv($datafile_path)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'generate-csv'
		]);
		
		$request_body=[
			"file_path"=> realpath($datafile_path)
		];
			
		$api_response = $client->request('POST', '', [
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return $response;
	}


	public function generate_csv_queue($datafile_path)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'generate-csv-queue'
		]);
		
		$request_body=[
			"file_path"=> realpath($datafile_path)
		];
			
		$api_response = $client->request('POST', '', [
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return [
			'response'=>$response,
			'status_code'=>$api_response->getStatusCode() //e.g. 200
		];
	}


	/**
	 * 
	 * 
	 * Generate data dictionary from Data (SPSS, Stata, etc)
	 * @filename - data file name
	 * 
	 */
	public function generate_data_dictionary($sid=null,$filename=null)
	{
		$datafile_info=$this->ci->Editor_model->data_file_by_name($sid,$filename);

		if ($datafile_info){
			throw new Exception("Data file already exists: ".$filename);
		}

		//generate a new file ID e.g. F1...
		$fileid=$this->ci->Editor_model->data_file_generate_fileid($sid);

		//file type 
		$filetype=$this->get_file_extension($filename);

		if (!$this->is_allowed_data_type($filetype)){
			throw  new Exception("Invalid data file type");
		}			

		$project_folder=$this->ci->Editor_model->get_project_folder($sid);
	
		if (!file_exists($project_folder)){
			throw new Exception('PROJECT_FOLDER_NOT_FOUND: '. $project_folder);
		}

		$project_folder=realpath($project_folder);
		$data_file_path=$project_folder.'/data/'.$filename;

		if (!file_exists($data_file_path)){
			throw new Exception("DATA_FILE_NOT_FOUND: ".$data_file_path);
		}

		$client = new Client([
			'base_uri' => 'http://localhost:2121/ocpu/library/nadar/R/datafile_dictionary/json?force=true&auto_unbox=true&digits=22'
		]);
		
		$request_body=[
			"freqLimit"=>50,
			"fileId"=>$fileid,
			"type"=>$filetype,
			"filepath"=> $data_file_path
		];
			
		$api_response = $client->request('POST', '', [
			//'auth' => [$username, $password],
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);

		if (isset($response["result"]) && $response["result"]=="error"){
			throw new Exception($response["message"]);
		}

		$response=array_merge(array(
			'status'=>'success',
			'folder_path'=>$project_folder,
			'file_id'=>$fileid,
			//'options'=>$body_options,
			'code' => $api_response->getStatusCode(),// 200
			'reason' => $api_response->getReasonPhrase() // OK
		), $response);

		return $response;		
	}


	function get_file_extension($name)
	{		
		$file_info=pathinfo($name);
		return strtoupper($file_info['extension']);
	}

	function is_allowed_data_type($ext)
	{
		$allowed_types=array('DTA','SAV','CSV');
		if (in_array($ext,$allowed_types)){
			return true;
		}
		return false;
	}


	/**
	 * 
	 * Import data file + variable metadata from import data file
	 * 
	 */
	function import_data_dictionary($sid,$fileid,$filename,$variables)
	{
		$this->create_data_file($sid,$fileid,$filename);
		return $this->import_variables($sid,$fileid,$variables);
	}

	/**
	 * 
	 * Create new data file for imported data file
	 * 
	 */
	function create_data_file($sid,$fileid,$filename)
	{
		$options=array(
			'file_id'=>$fileid,
			'file_url'=>$filename,
			'file_name'=>$this->ci->Editor_model->data_file_filename_part($filename)
		);

		$data_file=$this->ci->Editor_model->data_file_by_name($sid,$options['file_name']);

		if (!$data_file){
			$this->ci->Editor_model->data_file_insert($sid,$options);
		}else{
			$this->ci->Editor_model->data_file_update($data_file["id"],$options);
		}
	}

	function import_variables($sid,$fileid,$variables)
	{
		$valid_data_files=$this->ci->Editor_datafile_model->list($sid);

		$max_variable_id=$this->get_max_vid($sid);

			
		//validate all variables
		foreach($variables as $idx=>$variable){
			$max_variable_id=$max_variable_id+1;
			$variable['file_id']=$fileid;
			$variable['vid']= 'V'.$max_variable_id;

			if (!in_array($fileid,$valid_data_files)){
				throw new Exception("Invalid `file_id`: valid values are: ". implode(", ", $valid_data_files ));
			}

			//check if variable already exists
			$uid=$this->ci->Editor_model->variable_uid_by_name($sid,$variable['file_id'],$variable['name']);
			$variable['fid']=$variable['file_id'];

			$this->ci->Editor_model->validate_variable($variable);
			$variable['metadata']=$variable;

			if($uid){
				$this->ci->Editor_model->variable_update($sid,$uid,$variable);
			}
			else{						
				$this->ci->Editor_model->variable_insert($sid,$variable);
			}

			$result[]=$variable['vid'];
		}

		return $result;
	}


	function get_max_vid($sid)
	{
        $this->ci->db->select("vid");
        $this->ci->db->where("sid",$sid);
        $result=$this->ci->db->get("editor_variables")->result_array();

		if (!$result){
			return 0;
		}

		$max=0;
		foreach($result as $row)
		{
			$val=substr($row['vid'],1);
			if (strtoupper(substr($row['vid'],0,1))=='V' && is_numeric($val)){
				if ($val >$max){
					$max=$val;
				}
			}
		}

		return $max;
	}

}

