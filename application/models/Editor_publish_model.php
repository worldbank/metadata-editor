<?php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;



/**
 * 
 * Editor publish projects to NADA catalogs
 * 
 */
class Editor_publish_model extends ci_model {
 
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
		require_once 'modules/guzzle/vendor/autoload.php';
    }

    function publish_to_catalog($sid,$user_id,$catalog_connection_id,$options=[])
	{
		$conn_info=$this->Editor_model->get_catalog_connection($user_id,$catalog_connection_id);

		if (!$conn_info){
			throw new Exception("Target catalog was not found");
		}

		$project=$this->Editor_model->get_basic_info($sid);

		if (!$project){
			throw new Exception("Project not found");
		}

		$project_type=$project['type'];
		$catalog_url=$conn_info['url'].'/index.php/api/datasets/create/'.$project_type;
		$catalog_api_key=$conn_info['api_key'];
		
		//project metadata
		$result=$this->publish_metadata($sid,$catalog_url,$catalog_api_key,$options);

		return $result;

		//project external resources

		//project files
	}

	function get_project_metadata_json_path($sid)
	{
		$project=$this->Editor_model->get_basic_info($sid);
		$project_folder=$this->Editor_model->get_project_folder($sid);

		$filename=trim($project['idno'])!=='' ? trim($project['idno']) : md5($project['id']);
		$output_file=$project_folder.'/'.$filename.'.json';

		if (!file_exists($output_file)){
			throw new Exception("JSON metadata file not found");
		}

		return $output_file;
	}


	public function publish_metadata($sid,$catalog_url,$catalog_api_key,$options)
	{
		$client = new Client([				
			'base_uri' => $catalog_url,
			'headers' => ['x-api-key' => $catalog_api_key]
		]);
		
		$metadata_json_path=$this->get_project_metadata_json_path($sid);
		
		$metadata=json_decode(file_get_contents($metadata_json_path),true);

		if (!$metadata){
			throw new Exception("Failed to load project metadata: ".$metadata_json_path);
		}

		foreach($options as $key=>$option){
			$metadata[$key]=$option;
		}

		//$metadata=array_merge($options,$metadata);
			
		try{
			$api_response = $client->request('POST', '', [
				//'auth' => [$username, $password],
				'json' => $metadata,
				['debug' => false]
			]);

			$response=array(
				'status'=>'success',
				'folder_path'=>$metadata_json_path,
				//'options'=>$body_options,
				'code' => $api_response->getStatusCode(),// 200
				'reason' => $api_response->getReasonPhrase(), // OK
				'response_' =>$api_response->getBody()
			);

			$response_text=(string) $api_response->getBody();
			$response_json=json_decode($response_text,true);

			if(!$response_json){
				return $response_text;
			}

			/*if ($response_json["dataset"]){
				return $response_json["dataset"];
			}*/

			return $response_json;
			die();

			return $response;
		} catch (ClientException $e) {
			$resp=$e->getResponse();
			throw new Exception((string) $resp->getBody());
			return;

			http_response_code($resp->getStatusCode());
			echo $resp->getBody();
			die();			
		}
	}

}    