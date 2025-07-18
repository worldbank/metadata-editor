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
        $this->load->model('Catalog_connections_model');
        $this->load->model('Editor_resource_model');
    }

    function publish_to_catalog($sid,$user_id,$catalog_connection_id,$options=[])
	{
		$conn_info=$this->Catalog_connections_model->get_connection($user_id,$catalog_connection_id);

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
		return $this->publish_metadata($sid,$catalog_url,$catalog_api_key,$options);
	}

	function get_project_metadata_json_path($sid)
	{
		$project=$this->Editor_model->get_basic_info($sid);
		$project_folder=$this->Editor_model->get_project_folder($sid);

		$filename=trim((string)$project['idno'])!=='' ? trim($project['idno']) : md5($project['id']);
		$output_file=$project_folder.'/'.$filename.'.json';

		if (!file_exists($output_file)){
			throw new Exception("JSON metadata file not found" . $output_file);
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

		try{
			$api_response = $client->request('POST', '', [
				'json' => $metadata,
				['debug' => false]
			]);

			$response=array(
				'status'=>'success',
				'folder_path'=>$metadata_json_path,
				'code' => $api_response->getStatusCode(),// 200
				'reason' => $api_response->getReasonPhrase(), // OK
				'response_' =>$api_response->getBody()
			);

			$response_text=(string) $api_response->getBody();
			$response_json=json_decode($response_text,true);

			if(!$response_json){
				return $response_text;
			}

			return $response_json;

		} catch (ClientException $e) {
			$resp=$e->getResponse();
			//throw new Exception((string) $resp->getBody());

			//response is in json format, check if is valid json
			$response_text=(string) $resp->getBody();
			$response_json=json_decode($response_text,true);

			throw new ApiRequestException(
				$message = $response_text,
				$details = [
					'status' => $resp->getStatusCode(),// 200
					'reason' => $resp->getReasonPhrase(), // OK
					'response_' =>$response_json,
					'api_url' => $catalog_url
				]
			);
		}
	}

	function publish_thumbnail($sid,$user_id,$catalog_connection_id,$options=[])
	{
		$conn_info=$this->Catalog_connections_model->get_connection($user_id,$catalog_connection_id);

		if (!$conn_info){
			throw new Exception("Target catalog was not found");
		}

		$project=$this->Editor_model->get_basic_info($sid);

		if (!$project){
			throw new Exception("Project not found");
		}

		$thumbnail_file=$this->Editor_model->get_thumbnail_file($sid);

		if (!$thumbnail_file){
			throw new Exception("Thumbnail file not found");
		}

		if (!$project['study_idno']){
			throw new Exception("Study IDNO is not set");
		}

		$catalog_url=$conn_info['url'].'/index.php/api/datasets/thumbnail/'.$project['study_idno'];
		$catalog_api_key=$conn_info['api_key'];
		
		$api_response=$this->make_post_file_request($catalog_url, $catalog_api_key, $file_field_name='file', $file_path=$thumbnail_file);
		return $api_response;
	}


	function publish_external_resources($sid,$user_id,$catalog_connection_id,$options=[])
	{
		$conn_info=$this->Catalog_connections_model->get_connection($user_id,$catalog_connection_id);

		if (!$conn_info){
			throw new Exception("Target catalog was not found");
		}

		$project=$this->Editor_model->get_basic_info($sid);

		if (!$project){
			throw new Exception("Project not found");
		}

		$resources=$this->Editor_resource_model->select_all($sid);

		$catalog_url=$conn_info['url'].'/index.php/api/resources/'.$project['study_idno'];
		$catalog_api_key=$conn_info['api_key'];
		
		$output=[];

		foreach($resources as $resource){
			$resource['overwrite']="yes";
			$output[]=$this->make_post_request($catalog_url,$catalog_api_key,$resource);
		}		

		return $output;
	}



	public function publish_external_resource($sid,$user_id,$connection_id,$resource_id,$overwrite="no")
	{
		$conn_info=$this->Catalog_connections_model->get_connection($user_id,$connection_id);

		if (!$conn_info){
			throw new Exception("Target catalog was not found");
		}

		$project=$this->Editor_model->get_basic_info($sid);

		if (!$project){
			throw new Exception("Project not found");
		}

		//get resource
		$resource=$this->Editor_resource_model->select_single($sid,$resource_id);

		if (!$resource){
			throw new Exception("Resource not found");
		}

		$catalog_url=$conn_info['url'].'/index.php/api/resources/'.$project['study_idno'];
		$catalog_api_key=$conn_info['api_key'];
		$resource['overwrite']=$overwrite;

		$output=[];

		//post resource metadata
		$output['resource']=$this->make_post_request($catalog_url, $catalog_api_key, $post_body=$resource, $body_format='json', $headers=null);

		if (!empty((string)$resource['filename']) && !is_url($resource['filename']))
		{	
			//get resource file
			$resource_file_path=$this->Editor_resource_model->get_resource_file_by_name($sid,$resource['filename']);

			//upload resource file
			if (file_exists($resource_file_path)){		
				$catalog_url=$conn_info['url'].'/index.php/api/datasets/'.$project['study_idno'].'/files';
				$output['resource_upload']=$this->make_post_file_request($catalog_url, $catalog_api_key, $file_field_name='file', $file_path=$resource_file_path);
			}
			else{
				throw new Exception("Resource file not found: " . basename($resource_file_path));
			}
		}

		return $output;
	}

	public function make_post_request($url, $api_key, $post_body=null, $body_format='json', $headers=null)
	{
		$client = new Client([				
			'base_uri' => $url,
			'headers' => ['x-api-key' => $api_key]
		]);
					
		try{
			$api_response = $client->request('POST', '', [
				'json' => $post_body,
				['debug' => false]
			]);

			$response_text=(string) $api_response->getBody();
			$response_json=json_decode($response_text,true);

			if(!$response_json){
				return $response_text;
			}

			return $response_json;
		} catch (ClientException $e) {
			$resp=$e->getResponse();
			throw new Exception((string) $resp->getBody());			
		}
		catch (Exception $e) {
			throw new Exception("request failed: ". $e->getMessage());
		}
	}

	public function make_post_file_request($url, $api_key, $file_field_name='file', $file_path='')
	{
		$client = new Client([				
			'base_uri' => $url,
			'headers' => ['x-api-key' => $api_key]
		]);
					
		try{	
			$body=[
				'multipart' => [
					[
						'Content-type' => 'multipart/form-data',
						'name'     => $file_field_name,
						'contents' => fopen($file_path, 'r'),
						'filename' => basename($file_path)
					]
				]
			];

			$api_response = $client->request('POST','', $body);
			$response_text=(string) $api_response->getBody();
			$response_json=json_decode($response_text,true);

			if(!$response_json){
				return $response_text;
			}
		} catch (ClientException $e) {
			$resp=$e->getResponse();			
			throw new Exception((string) $resp->getBody());
		}
		catch (Exception $e) {
			throw new Exception("request failed: ". $e->getMessage());
		}
	}

	

}    