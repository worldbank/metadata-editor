<?php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

use League\Csv\Reader;
use League\Csv\Statement;

require(APPPATH.'/libraries/MY_REST_Controller.php');

class R extends MY_REST_Controller
{

	//R API base url
	private $rApiUrl; //'http://localhost:2121/ocpu/library/';

	//temporary storage for creating files via R
	private $rStoragePath;	

	public function __construct()
	{
		parent::__construct();
		require_once 'modules/guzzle/vendor/autoload.php';

		$this->load->model("Editor_model");
		$this->load->library("Rutils");
		//$this->is_admin_or_die();

		$this->load->config("editor");

		$this->rApiUrl = $this->config->item('r_api_url', 'editor');
		$this->rStoragePath=$this->config->item('r_storage_path', 'editor');
	}

	
    
	//override authentication to support both session authentication + api keys
	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}
	
	/**
	 * 
	 *  Get R version
	 * 
	 */
	public function version_get($type=NULL)
	{
		try{

			$client = new Client([
				'base_uri' => $this->rApiUrl.'mde/R/get_r_version/json'
			]);

			$request_body=[];
			$api_response = $client->request('POST', '', [
				'json' => 
					$request_body
				,
				['debug' => false]
			]);

			$response=array(
				'status'=>'success',
 				'api_response'=>json_decode($api_response->getBody()->getContents(),true),
				'code' => $api_response->getStatusCode(),// 200
				'reason' => $api_response->getReasonPhrase() // OK
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}	
	}

	private function get_file_extension($name)
	{		
		$file_info=pathinfo($name);
		return strtoupper($file_info['extension']);
	}

	private function is_allowed_data_type($ext)
	{
		$allowed_types=array('DTA','SAV','CSV');
		if (in_array($ext,$allowed_types)){
			return true;
		}
		return false;
	}


	/**
	 * 
	 *  Import a data file
	 * 
	 *  - generate data dictionary using R
	 *  - import data dictionary to db
	 * 
	 */
	public function import_data_file_post($sid=null)
	{		
		$options=$this->raw_json_input();		

		try{

			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}
			
			$filename=$options["filename"];
			$response=$this->rutils->generate_data_dictionary($sid,$filename);

			if (!isset($response["variables"])){
				throw new Exception("Failed to export data dictionary from data file");	
			}

			$response['imported_variables']=$this->rutils->import_data_dictionary($sid,$response["file_id"],$filename,$response["variables"]);
			$this->set_response($response, REST_Controller::HTTP_OK);
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}	
	}


	/**
	 * 
	 * 
	 * Generate data dictionary from Data (SPSS, Stata, etc)
	 * @filename - data file name
	 * 
	 */
	public function data_dictionary_post($sid=null)
	{
		$options=$this->raw_json_input();

		try{

			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}
			
			$required_params=array("fileid","filename","filetype");
			foreach($required_params as $param){
				if(!isset($options[$param])){
					throw new Exception("Required parameter is missing: " . $param);
				}
			}

			$filename=$options["filename"];

			//generate a new file ID e.g. F1...
			$fileid=$this->Editor_model->data_file_generate_fileid($sid);

			//file type 
			$filetype=$this->get_file_extension($filename);

			if (!$this->is_allowed_data_type($filetype)){
				throw  new Exception("Invalid data file type");
			}			

			$project_folder=$this->Editor_model->get_project_folder($sid);
		
			if (!file_exists($project_folder)){
				throw new Exception('PROJECT_FOLDER_NOT_FOUND');
			}

			$project_folder=realpath($project_folder);
			$data_file_path=$project_folder.'/data/'.$filename;

			if (!file_exists($data_file_path)){
				throw new Exception("DATA_FILE_NOT_FOUND: ".$data_file_path);
			}

			$client = new Client([
				'base_uri' => $this->rApiUrl.'nadar/R/datafile_dictionary/json?force=true&auto_unbox=true&digits=22'
			]);
			
			$request_body=[
				"freqLimit"=>50,
				"fileId"=>$fileid,
				"type"=>$filetype,
				"filepath"=> $data_file_path
			];
			  
			$api_response = $client->request('POST', '', [
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
				'code' => $api_response->getStatusCode(),// 200
				'reason' => $api_response->getReasonPhrase() // OK
			), $response);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}	
	}


	public function generate_csv_post($sid=null)
	{
		try{
			$options=$this->raw_json_input();
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}			
			
			$client = new Client([
				'base_uri' => $this->rApiUrl.'nadar/R/datafile_write_csv/json?force=true&auto_unbox=true&digits=22'
			]);

			$project_folder=$this->Editor_model->get_project_folder($sid);
		
			if (!file_exists($project_folder)){
				throw new Exception('PROJECT_FOLDER_NOT_FOUND');
			}

			$filename=isset($options['filename']) ? $options['filename'] : '';

			if (!$filename){
				throw new Exception("parameter filename is missing");
			}

			$project_folder=realpath($project_folder);
			$data_file_path=$project_folder.'/data/'.$filename;
			$ext=pathinfo($filename, PATHINFO_EXTENSION);
			
			$csv_file_path=$this->rStoragePath.'/'.str_replace(".".$ext,".csv",$filename);

			if (!file_exists($data_file_path)){
				throw new Exception("DATA_FILE_NOT_FOUND: ".$data_file_path);
			}

			$request_body=[
				"csvPath"=>$csv_file_path,
				"type"=>$this->get_file_extension($filename),
				"filepath"=> $data_file_path
			];
			  
			$api_response = $client->request('POST', '', [
				'json' => 
					$request_body
				,
				['debug' => false]
			]);

			$response=json_decode($api_response->getBody()->getContents(),true);

			$csv_mv_path=dirname($data_file_path) . '/' .basename($csv_file_path);

			if (isset($response['result']) && $response['result']=='ok'){				
				rename($csv_file_path,$csv_mv_path);
			}

			$response=array_merge(array(
				'status'=>'success',
				'folder_path'=>$data_file_path,
				'csv_path'=>$csv_mv_path,
				'code' => $api_response->getStatusCode(),// 200
				'reason' => $api_response->getReasonPhrase() // OK
			), $response);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}	
	}

	private function getCsvLinesCount($file_path)
	{
		$file = new \SplFileObject($file_path, 'r');
		$file->seek(PHP_INT_MAX);

		return $file->key();
	}


	public function read_csv_get($sid=null,$fileid=null)
	{
		try{
			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}

			$project_folder=$this->Editor_model->get_project_folder($sid);			
		
			if (!file_exists($project_folder)){
				throw new Exception('PROJECT_FOLDER_NOT_FOUND');
			}

			$project_folder=realpath($project_folder);

			//get filename by FID
			$datafile=$this->Editor_model->data_file_by_id($sid,$fileid);
			
			if (!$datafile){
				throw new Exception("DATAFILE_NOT_FOUND");
			}

			$filename=$datafile['file_name'];
			$csv_file_path=$project_folder.'/data/'.$filename.'.csv';

			/*if (!file_exists($data_file_path)){
				throw new Exception("DATA_FILE_NOT_FOUND: ".$data_file_path);
			}*/

			if (!file_exists($csv_file_path)){
				throw new Exception("CSV_FILE_NOT_FOUND: ".$csv_file_path);
			}

			$csv = Reader::createFromPath($csv_file_path, 'r');
			$csv->setHeaderOffset(0); //set the CSV header offset
			$offset=(int)$this->input->get("offset");
			$limit=(int)$this->input->get("limit");

			if ($limit <1 || $limit>100){
				$limit=100;
			}

			$stmt = Statement::create()
				->offset($offset)
				->limit($limit)
			;

			$records = $stmt->process($csv);
			/*foreach ($records as $record) {
				//do something here
			}*/

			$response=array(
				'csv'=>$csv_file_path,
				'total'=>$this->getCsvLinesCount($csv_file_path),
				//'total'=>'?',
				'offset'=>$offset,
				'limit'=>$limit,
				'records'=>$records
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}	
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}	
	}
	
}
