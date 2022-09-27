<?php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

use League\Csv\Reader;
use League\Csv\Statement;

require(APPPATH.'/libraries/MY_REST_Controller.php');

class R extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		require_once 'modules/guzzle/vendor/autoload.php';

		$this->load->model("Catalog_model");				
		$this->load->model("Editor_model");
		//$this->is_admin_or_die();
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

			$url='http://localhost:2121/ocpu/library/mde/R/get_r_version/json';
			
            
			$client = new Client([
				'base_uri' => 'http://localhost:2121/ocpu/library/mde/R/get_r_version/json'
			]);

			/*
			$this->config->load('doi');
			$doi_options=$this->config->item("doi");

			$username=$doi_options['user'];
			$password=$doi_options['password'];
			*/

			$request_body=[];

			$api_response = $client->request('POST', '', [
				//'auth' => [$username, $password],
				'json' => 
					$request_body
				,
				['debug' => false]
			]);

			$response=array(
				'status'=>'success',
				//'options'=>$body_options,
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

			$fileid=$options["fileid"];
			$filename=$options["filename"];
			$filetype=$options["filetype"];
			

			$client = new Client([
				//'base_uri' => 'http://localhost:2121/ocpu/library/mde/R/import/json??force=true&auto_unbox=true&digits=22'
				'base_uri' => 'http://localhost:2121/ocpu/library/nadar/R/datafile_dictionary/json?force=true&auto_unbox=true&digits=22'
			]);
			
			$project_folder=$this->Editor_model->get_project_folder($sid);
		
			if (!file_exists($project_folder)){
				throw new Exception('PROJECT_FOLDER_NOT_FOUND');
			}

			$project_folder=realpath($project_folder);

			$data_file_path=$project_folder.'/data/'.$filename;

			if (!file_exists($data_file_path)){
				throw new Exception("DATA_FILE_NOT_FOUND: ".$data_file_path);
			}

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
				//'options'=>$body_options,
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


	public function generate_csv_get($sid=null,$fileid=null,$filename=null)
	{
		try{            

			$exists=$this->Editor_model->check_id_exists($sid);

			if(!$exists){
				throw new Exception("Project not found");
			}
			
			$client = new Client([
				//'base_uri' => 'http://localhost:2121/ocpu/library/mde/R/writeCSV/json?force=true&auto_unbox=true&digits=22'
				'base_uri' => 'http://localhost:2121/ocpu/library/nadar/R/datafile_write_csv/json?force=true&auto_unbox=true&digits=22'
			]);

			$project_folder=$this->Editor_model->get_project_folder($sid);
		
			if (!file_exists($project_folder)){
				throw new Exception('PROJECT_FOLDER_NOT_FOUND');
			}

			$project_folder=realpath($project_folder);
			$data_file_path=$project_folder.'/data/'.$filename;
			$ext=pathinfo($filename, PATHINFO_EXTENSION);
			$csv_file_path=$project_folder.'/data/'.str_replace(".".$ext,".csv",$filename);

			if (!file_exists($data_file_path)){
				throw new Exception("DATA_FILE_NOT_FOUND: ".$data_file_path);
			}

			$request_body=[
				"csvPath"=>$csv_file_path,
				"type"=>"dta",
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
			$response=array_merge(array(
				'status'=>'success',
				'folder_path'=>$data_file_path,
				//'options'=>$body_options,
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
			
			//$data_file_path=$project_folder.'/data/'.$filename;
			//$ext=pathinfo($filename, PATHINFO_EXTENSION);

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
				'total'=>count($csv),
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
