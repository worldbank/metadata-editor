<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

use League\Csv\Reader;
use League\Csv\Statement;


class DataUtils
{

	//Data API base url
	private $DataApiUrl; //'http://localhost:8000';

	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "DataUtils Class Initialized.");
		$this->ci =& get_instance();
		$this->ci->load->model("Editor_model");
		$this->ci->load->config("editor");
		$this->DataApiUrl = $this->ci->config->item('data_api_url', 'editor');
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


	/**
	 * 
	 * 
	 * Get list of jobs
	 * 
	 * 
	 */
	public function get_jobs()
	{
		try {
			$client = new Client([
				'base_uri' => $this->DataApiUrl . 'jobs'
			]);

			$api_response = $client->request('GET');
			$response = json_decode($api_response->getBody()->getContents(), true);
			return $response;
		}
		catch(Exception $e){
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
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

	public function get_file_name_labels($datafile_path)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'name-labels'
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
			'request_url'=>$client->getConfig('base_uri'),
			//'request'=>$request_body,
			'status_code'=>$api_response->getStatusCode() //e.g. 200
		];
	}

	/**
	 * get summary stats queue job status
	 * 
	 * @param string $job_id Job ID
	 * @return array Job status and results
	 * 
	 * @uses http_errors => false so we get full response body on 4xx/5xx (no Guzzle truncation)
	 */
	public function get_job_status($job_id)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'jobs/'.$job_id
		]);

		$api_response = $client->request('GET', '', [
			'debug' => false,
			'http_errors' => false
		]);

		$body_raw = $api_response->getBody()->getContents();
		$response = json_decode($body_raw, true);
		if ($response === null && $body_raw !== '') {
			$response = ['detail' => $body_raw];
		}
		return [
			'response' => $response,
			'status_code' => $api_response->getStatusCode()
		];
	}	

	/**
	 * Submit metadata review job to FastAPI.
	 * POST /review/jobs
	 *
	 * @param array $metadata Project metadata JSON object
	 * @param array $options Optional keys: manifest_file, team_preset
	 * @return array response, status_code
	 */
	public function submit_metadata_review($metadata, $options = array())
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl . 'review/jobs'
		]);

		$request_body = array(
			'metadata' => $metadata
		);

		if (!empty($options['manifest_file'])) {
			$request_body['manifest_file'] = $options['manifest_file'];
		}
		if (!empty($options['team_preset'])) {
			$request_body['team_preset'] = $options['team_preset'];
		}

		$api_response = $client->request('POST', '', [
			'json' => $request_body,
			'debug' => false,
			'http_errors' => false
		]);

		$body_raw = $api_response->getBody()->getContents();
		$response = json_decode($body_raw, true);
		if ($response === null && $body_raw !== '') {
			$response = array('detail' => $body_raw);
		}

		return array(
			'response' => $response,
			'status_code' => $api_response->getStatusCode()
		);
	}

	/**
	 * Cancel FastAPI job by ID.
	 * DELETE /jobs/{job_id}
	 *
	 * @param string $job_id FastAPI job id
	 * @return array response, status_code
	 */
	public function cancel_job($job_id)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl . 'jobs/' . $job_id
		]);

		$api_response = $client->request('DELETE', '', [
			'debug' => false,
			'http_errors' => false
		]);

		$body_raw = $api_response->getBody()->getContents();
		$response = json_decode($body_raw, true);
		if ($response === null && $body_raw !== '') {
			$response = array('detail' => $body_raw);
		}

		return array(
			'response' => $response,
			'status_code' => $api_response->getStatusCode()
		);
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

	public function export_datafile_queue($sid,$file_id,$format,$export_options=null)
	{
		$this->ci->load->library("Datafile_export");

		$client = new Client([
			'base_uri' => $this->DataApiUrl.'export-data-queue'
		]);

		$options=is_array($export_options) ? array('export_options'=>$export_options) : array();
		$request_body=$this->ci->datafile_export->get_export_params($sid,$file_id,$format,$options);

		$api_response = $client->request('POST', '', [
			'json' =>
				$request_body
			,
			['debug' => false]
		]);

		$response=json_decode($api_response->getBody()->getContents(),true);
		return [
			'request'=>$request_body,
			'response'=>$response,
			'status_code'=>$api_response->getStatusCode() //e.g. 200
		];
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
	 * Process microdata file - generates data dictionary and exports to CSV in one go
	 * 
	 * @param string $datafile_path Path to the data file
	 * @param array $options Dictionary parameters (weights, missings, dtypes, categorical)
	 * @return array Response with job_id and status
	 */
	public function process_microdata_queue($datafile_path, $options)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl.'process-microdata-queue'
		]);

		$request_body = $options;
		$request_body["file_path"] = realpath($datafile_path);
			
		$api_response = $client->request('POST', '', [
			'json' => 
				$request_body
			,
			['debug' => false]
		]);

		$response = json_decode($api_response->getBody()->getContents(), true);
		return [
			'response' => $response,
			'request_url' => $client->getConfig('base_uri'),
			'status_code' => $api_response->getStatusCode() //e.g. 200 or 202
		];
	}

	/**
	 * Queue FastAPI job to remove columns from a CSV file.
	 * POST /remove-csv-columns-queue
	 *
	 * @param string $file_path Absolute path to source CSV
	 * @param array $column_names Column names to remove from the CSV
	 * @param string $output_path Path where FastAPI should write the result CSV (overwritten if exists)
	 * @return array response, status_code (202 on success; 400/404 on validation/file errors)
	 */
	public function remove_csv_columns_queue($file_path, $column_names, $output_path)
	{
		$client = new Client([
			'base_uri' => $this->DataApiUrl . 'remove-csv-columns-queue'
		]);
		
		$request_body = array(
			'file_path' => realpath($file_path),
			'column_names' => $column_names,
			'output_path' => $output_path
		);

		$api_response = $client->request('POST', '', [
			'json' => $request_body,
			'debug' => false,
			'http_errors' => false
		]);

		$body_raw = $api_response->getBody()->getContents();
		$response = json_decode($body_raw, true);

		if ($response === null && $body_raw !== '') {
			$response = array('detail' => $body_raw);
		}

		return array(
			'response' => $response,
			'status_code' => $api_response->getStatusCode()
		);
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
			'file_physical_name'=>$filename,
			'file_name'=>$this->ci->Editor_datafile_model->data_file_filename_part($filename)
		);

		$data_file=$this->ci->Editor_datafile_model->data_file_by_name($sid,$options['file_name']);

		if (!$data_file){
			$this->ci->Editor_datafile_model->data_file_insert($sid,$options);
		}else{
			$this->ci->Editor_datafile_model->data_file_update($data_file["id"],$options);
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


	 /**
     * 
     *  Create params for data dictionary generation
     * 
     */
    function prepare_data_dictionary_params($sid, $fid,$datafile_path=null)
    {
		$this->ci->load->model("Editor_datafile_model");
		$this->ci->load->model("Editor_variable_model");
		$this->ci->load->model("Editor_model");

        if (!$datafile_path){
            $datafile_path=$this->ci->Editor_datafile_model->get_file_path($sid,$fid);
        }

        if (!$datafile_path){
            throw new Exception("Data file not found");
        }
		
		//get variables data types, missing, weights info, and metadata (for sum_stats_options.freq)
        $this->ci->db->select("name,field_dtype,user_missings,is_weight,var_wgt_id, interval_type, metadata");
        $this->ci->db->where("sid",$sid);
        $this->ci->db->where("fid",$fid);        
        $variables=$this->ci->db->get("editor_variables")->result_array();

        $params=array(
            'datafile'=> realpath($datafile_path)
        );

        $dtype_map=array(
            //'numeric'=>'float',
            'string'=>'object',
            'character'=>'object'
        );

        foreach($variables as $variable){
            if (isset($variable['var_wgt_id']) && $variable['var_wgt_id']>0 ){

				//if field and weight_field are the same, skip
				if ($variable['name']==$this->ci->Editor_variable_model->get_name_by_var_wgt_id($sid,$variable['var_wgt_id'])){
					continue;
				}

                $params['weights'][]=array(
                    'field'=>$variable['name'],
                    'weight_field'=>$this->ci->Editor_variable_model->get_name_by_var_wgt_id($sid,$variable['var_wgt_id'])
                );
            }
            /*if ($variable['user_missings']!=''){
                $params['missings'][]=array(
                    "field"=>$variable['name'],
                    "missings"=> explode(",",$variable['user_missings'])
                );
            }*/

            if ($variable['user_missings']!=''){
				$missings=explode(",",$variable['user_missings']);
				foreach($missings as $missing){
					if (!empty($missing)){
						$params['missings'][trim($variable['name'])][]=$missing;
					}					
				}
            }

            if ($variable['field_dtype']!=''){
                if (isset($dtype_map[$variable['field_dtype']])){
                    $params['dtypes'][$variable['name']]= $dtype_map[$variable['field_dtype']];
                }
            }

			//interval type [categorical - for enabling frequencies]; skip if user disabled freq
			$include_categorical = true;
			if (!empty($variable['metadata'])) {
				$meta = $this->ci->Editor_model->decode_metadata($variable['metadata']);
				if (isset($meta['sum_stats_options']['freq']) && $meta['sum_stats_options']['freq'] === false) {
					$include_categorical = false;
				}
			}
			if ($include_categorical && $variable['interval_type']!='' && $variable['interval_type']=='discrete'){
				$params['categorical'][]=$variable['name'];
			}
        }

        return $params;
    }

}

