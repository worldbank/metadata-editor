<?php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

require(APPPATH.'/libraries/MY_REST_Controller.php');

class R extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		require_once 'modules/guzzle/vendor/autoload.php';

		$this->load->model("Catalog_model");				
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


	public function data_dictionary_get($idno=null,$fileid=null,$filename=null,$filetype=null)
	{

		$sid=$this->get_sid_from_idno($idno);

		try{            
			$client = new Client([
				'base_uri' => 'http://localhost:2121/ocpu/library/mde/R/import/json??force=true&auto_unbox=true&digits=22'
			]);

			/*
			$this->config->load('doi');
			$doi_options=$this->config->item("doi");

			$username=$doi_options['user'];
			$password=$doi_options['password'];
			*/

			$survey_folder=$this->Catalog_model->get_survey_path_full($sid);
		
			if (!file_exists($survey_folder)){
				throw new Exception('SURVEY_FOLDER_NOT_FOUND');
			}

			$survey_folder=FCPATH.$survey_folder;

			$data_file_path=$survey_folder.'/'.$filename;

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
				'folder_path'=>$survey_folder,
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


	public function generate_csv_get($idno=null,$fileid=null,$filename=null)
	{

		$sid=$this->get_sid_from_idno($idno);

		try{            
			$client = new Client([
				'base_uri' => 'http://localhost:2121/ocpu/library/mde/R/writeCSV/json??force=true&auto_unbox=true&digits=22'
			]);

			/*
			$this->config->load('doi');
			$doi_options=$this->config->item("doi");

			$username=$doi_options['user'];
			$password=$doi_options['password'];
			*/

			$survey_folder=$this->Catalog_model->get_survey_path_full($sid);
		
			if (!file_exists($survey_folder)){
				throw new Exception('SURVEY_FOLDER_NOT_FOUND');
			}

			$survey_folder=FCPATH.$survey_folder;

			$data_file_path=$survey_folder.'/'.$filename;

			$ext=pathinfo($filename, PATHINFO_EXTENSION);

			$csv_file_path=$survey_folder.'/'.str_replace(".".$ext,".csv",$filename);

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
				'folder_path'=>$survey_folder,
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
	
}
