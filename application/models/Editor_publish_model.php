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

		//project mappings for NADA
		$mappings=array(
			'microdata'=>'survey',
			'indicator'=>'timeseries',
			'indicator-db'=>'timeseries-db'			
		);

		if (array_key_exists($project_type, $mappings))
		{
			$project_type=$mappings[$project_type];
		}
		

		$catalog_url=$conn_info['url'].'/index.php/api/datasets/create/'.$project_type;
		$import_ddi_url=$conn_info['url'].'/index.php/api/datasets/import_ddi';
		$catalog_api_key=$conn_info['api_key'];
		
		//project metadata (NADA: JSON create; on failure for survey, fallback to import_ddi)
		return $this->publish_metadata(
			$sid,
			$catalog_url,
			$catalog_api_key,
			$options,
			$project_type,
			$import_ddi_url
		);
	}

	function get_project_metadata_json_path($sid)
	{
		$project=$this->Editor_model->get_basic_info($sid);
		$project_folder=$this->Editor_model->get_project_folder($sid);

		$filename=trim((string)$project['idno'])!=='' ? trim($project['idno']) : nada_hash($project['id']);
		$output_file=$project_folder.'/'.$filename.'.json';

		if (!file_exists($output_file)){
			throw new Exception("JSON metadata file not found" . $output_file);
		}

		return $output_file;
	}


	public function publish_metadata($sid,$catalog_url,$catalog_api_key,$options,$nada_dataset_type=null,$import_ddi_url=null)
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

		// Convert microdata to survey for NADA catalog compatibility
		if (isset($metadata['type']) && $metadata['type'] == 'microdata') {
			$metadata['type'] = 'survey';
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

			$response_text = (string) $api_response->getBody();
			return $this->parse_json_response($response_text);

		} catch (ClientException $e) {
			$resp = $e->getResponse();
			$response_text = (string) $resp->getBody();
			$content_type = $resp->getHeaderLine('Content-Type');
			$classified = $this->classify_catalog_error_body($response_text, $content_type);

			if ($nada_dataset_type === 'survey' && !empty($import_ddi_url)) {
				try {
					return $this->publish_metadata_import_ddi($sid, $import_ddi_url, $catalog_api_key, $options);
				} catch (Exception $ddi_ex) {
					$ddi_details = ($ddi_ex instanceof ApiRequestException) ? $ddi_ex->getDetails() : null;
					throw new ApiRequestException(
						$this->summarize_catalog_error($classified, $resp->getStatusCode())
							. ' | DDI import: ' . $ddi_ex->getMessage(),
						array_merge(
							$this->catalog_error_details_payload($classified, $resp, $catalog_url),
							[
								'ddi_fallback_error' => $ddi_ex->getMessage(),
								'ddi_fallback_details' => $ddi_details,
							]
						)
					);
				}
			}

			throw new ApiRequestException(
				$this->summarize_catalog_error($classified, $resp->getStatusCode()),
				$this->catalog_error_details_payload($classified, $resp, $catalog_url)
			);
		}
	}

	/**
	 * POST DDI XML to NADA datasets/import_ddi (multipart).
	 * Uses publish option keys: overwrite, repositoryid, access_policy, published, data_remote_url.
	 *
	 * @param string $sid Project id
	 * @param string $import_ddi_url Full URL to .../api/datasets/import_ddi
	 * @param string $catalog_api_key x-api-key value
	 * @param array $options Publish form options from metadata editor
	 * @return mixed Decoded JSON response from NADA
	 */
	private function publish_metadata_import_ddi($sid, $import_ddi_url, $catalog_api_key, $options)
	{
		$ddi_path = $this->Editor_model->generate_project_ddi($sid);

		if (!$ddi_path || !file_exists($ddi_path)) {
			throw new Exception('DDI file was not generated');
		}

		$client = new Client([
			'headers' => ['x-api-key' => $catalog_api_key],
		]);

		$multipart = $this->build_import_ddi_multipart($ddi_path, $options);

		try {
			$api_response = $client->request('POST', $import_ddi_url, [
				'multipart' => $multipart,
			]);
			$response_text = (string) $api_response->getBody();
			$decoded = $this->parse_json_response($response_text);
			if (is_array($decoded)) {
				$decoded['_published_via'] = 'import_ddi';
			}
			return $decoded;
		} catch (ClientException $e) {
			$resp = $e->getResponse();
			$body = (string) $resp->getBody();
			$content_type = $resp->getHeaderLine('Content-Type');
			$classified = $this->classify_catalog_error_body($body, $content_type);
			throw new ApiRequestException(
				$this->summarize_catalog_error($classified, $resp->getStatusCode()),
				$this->catalog_error_details_payload($classified, $resp, $import_ddi_url)
			);
		}
	}

	/**
	 * Multipart fields for NADA import_ddi: file, overwrite, repositoryid, access_policy, published, data_remote_url.
	 *
	 * @param string $ddi_path Absolute path to DDI XML
	 * @param array $options Keys from metadata editor publish options
	 * @return array Guzzle multipart array
	 */
	private function build_import_ddi_multipart($ddi_path, $options)
	{
		$file_contents = file_get_contents($ddi_path);
		if ($file_contents === false) {
			throw new Exception('Could not read DDI file');
		}

		$multipart = [
			[
				'name' => 'file',
				'contents' => $file_contents,
				'filename' => basename($ddi_path),
			],
		];

		$overwrite = isset($options['overwrite']) ? strtolower(trim((string) $options['overwrite'])) : 'no';
		if ($overwrite !== 'yes' && $overwrite !== 'no') {
			$overwrite = 'no';
		}
		$multipart[] = ['name' => 'overwrite', 'contents' => $overwrite];

		foreach (['repositoryid', 'access_policy', 'data_remote_url'] as $key) {
			if (!isset($options[$key])) {
				continue;
			}
			$val = $options[$key];
			if ($val === null || $val === '') {
				continue;
			}
			$multipart[] = ['name' => $key, 'contents' => (string) $val];
		}

		if (isset($options['published']) && $options['published'] !== '' && $options['published'] !== null) {
			$multipart[] = ['name' => 'published', 'contents' => (string) (int) $options['published']];
		}

		return $multipart;
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


	/**
	 * Remove all external resource metadata for the project study on NADA.
	 *
	 * @param int|string $sid
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @return array
	 */
	public function delete_all_nada_study_resources($sid, $user_id, $catalog_connection_id)
	{
		$project = $this->Editor_model->get_basic_info($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}
		if (empty($project['study_idno'])) {
			throw new Exception('Study IDNO is not set');
		}

		$client = $this->get_nada_catalog_client($user_id, $catalog_connection_id);

		return $client->delete_all_study_resources((string) $project['study_idno']);
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

		$output=[];

		foreach($resources as $resource){
			$output[]=$this->publish_external_resource_to_catalog($sid, $conn_info, $project, $resource, 'yes');
		}		

		return $output;
	}



	public function publish_external_resource($sid, $user_id, $connection_id, $resource_id, $overwrite = 'no', $nada_upload_id = null)
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

		$nada_upload_id = is_string($nada_upload_id) ? trim($nada_upload_id) : '';
		if ($nada_upload_id === '') {
			$nada_upload_id = null;
		}

		return $this->publish_external_resource_to_catalog($sid, $conn_info, $project, $resource, $overwrite, $nada_upload_id);
	}

	/**
	 * Map editor_resources row to NADA POST /api/resources payload.
	 *
	 * @param array $resource
	 * @param string $overwrite
	 * @param string|null $nada_upload_id
	 * @return array
	 */
	private function build_nada_resource_payload(array $resource, $overwrite = 'yes', $nada_upload_id = null)
	{
		$fields = array(
			'dctype', 'title', 'subtitle', 'author', 'dcdate', 'country', 'language',
			'contributor', 'publisher', 'rights', 'description', 'abstract', 'toc',
			'subjects', 'filename', 'dcformat', 'resource_idno', 'url',
		);

		$payload = array('overwrite' => $overwrite);
		foreach ($fields as $field) {
			if (array_key_exists($field, $resource) && $resource[$field] !== null && $resource[$field] !== '') {
				$payload[$field] = $resource[$field];
			}
		}

		if (empty($payload['resource_idno']) && !empty($resource['id_number'])) {
			$payload['resource_idno'] = $resource['id_number'];
		}

		if (!empty($nada_upload_id)) {
			$payload['upload_id'] = $nada_upload_id;
		}

		return $payload;
	}

	/**
	 * POST resource metadata to NADA, then upload attached file when filename is a stored project file (not a URL).
	 *
	 * On NADA 5.6+, uses resumable /api/uploads/* and passes upload_id on POST /api/resources/{idno}
	 * instead of a single multipart POST to /api/datasets/{idno}/files (avoids HTTP 413 on large files).
	 *
	 * @param int|string $sid
	 * @param array $conn_info Catalog connection
	 * @param array $project Row from get_basic_info (requires study_idno)
	 * @param array $resource Resource row
	 * @param string $overwrite Passed to NADA (e.g. yes|no)
	 * @param string|null $nada_upload_id Completed NADA resumable upload_id (from browser or job)
	 * @return array Keys: resource (catalog JSON response), resource_upload (optional file upload response)
	 */
	private function publish_external_resource_to_catalog($sid, $conn_info, $project, $resource, $overwrite = 'yes', $nada_upload_id = null)
	{
		if (empty($project['study_idno'])) {
			throw new Exception("Study IDNO is not set");
		}

		$resource_file_path = null;
		if (!empty((string) $resource['filename']) && !is_url($resource['filename'])) {
			$resource_file_path = $this->Editor_resource_model->get_resource_file_by_name($sid, $resource['filename']);
			if (!file_exists($resource_file_path)) {
				throw new Exception("Resource file not found: " . basename($resource_file_path));
			}
		}

		require_once APPPATH . 'libraries/Nada_catalog_client.php';
		$client = Nada_catalog_client::from_connection($conn_info);
		$study_idno = (string) $project['study_idno'];
		$resource_payload = $this->build_nada_resource_payload($resource, $overwrite, $nada_upload_id);

		if ($resource_file_path && !empty($nada_upload_id)) {
			return array(
				'resource' => $client->post_json('resources/' . rawurlencode($study_idno), $resource_payload),
				'resource_upload' => array(
					'method' => 'resumable',
					'upload_id' => $nada_upload_id,
					'filename' => basename((string) $resource['filename']),
					'source' => 'client_upload_id',
				),
			);
		}

		if ($resource_file_path && $client->supports_resumable_uploads()) {
			$upload_filename = basename((string) $resource['filename']);
			$upload_id = $client->upload_local_file_resumable($resource_file_path, $upload_filename);
			$resource_payload['upload_id'] = $upload_id;

			return array(
				'resource' => $client->post_json('resources/' . rawurlencode($study_idno), $resource_payload),
				'resource_upload' => array(
					'method' => 'resumable',
					'upload_id' => $upload_id,
					'filename' => $upload_filename,
					'source' => 'server_resumable',
				),
			);
		}

		$catalog_url = $conn_info['url'].'/index.php/api/resources/'.$study_idno;
		$catalog_api_key = $conn_info['api_key'];

		$output = array(
			'resource' => $this->make_post_request($catalog_url, $catalog_api_key, $resource_payload),
		);

		if ($resource_file_path) {
			$files_url = $conn_info['url'].'/index.php/api/datasets/'.$study_idno.'/files';
			$output['resource_upload'] = $this->make_post_file_request($files_url, $catalog_api_key, 'file', $resource_file_path);
		}

		return $output;
	}

	/**
	 * Fetch catalog info from NADA (collections, data_access_codes) for use in publish form.
	 *
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param int|string $project_id Project ID (sid)
	 * @return array ['collections' => array, 'data_access_codes' => array]
	 */
	public function get_catalog_info($user_id, $catalog_connection_id, $project_id)
	{
		$conn_info = $this->Catalog_connections_model->get_connection($user_id, $catalog_connection_id);
		if (!$conn_info) {
			throw new Exception("Target catalog was not found");
		}

		$project=$this->Editor_model->get_basic_info($project_id);
		if (!$project) {
			throw new Exception("Project not found");
		}

		$study_idno = $project['study_idno'];

		if (!$study_idno) {
			throw new Exception("Study IDNO is not set for project: " . $project_id);
		}

		$base_url = rtrim($conn_info['url'], '/');
		$api_key = $conn_info['api_key'];

		$collections = [];
		$data_access_codes = [];

		try {
			$collections_response = $this->make_get_request(
				$base_url . '/index.php/api/collections',
				$api_key
			);
			$collections = isset($collections_response['collections']) ? $collections_response['collections'] : [];
		} catch (Exception $e) {
			// Return empty so form still works; caller can log if needed
		}

		try {
			$codes_response = $this->make_get_request(
				$base_url . '/index.php/api/catalog/data_access_codes',
				$api_key
			);
			$data_access_codes = $this->normalize_nada_data_access_codes($codes_response);
		} catch (Exception $e) {
			// Return empty so form still works
		}

		$nada_version = null;
		$resumable_uploads = false;
		try {
			require_once APPPATH . 'libraries/Nada_catalog_client.php';
			$nada_client = Nada_catalog_client::from_connection($conn_info);
			$version_info = $nada_client->get_version();
			if (!empty($version_info['version'])) {
				$nada_version = (string) $version_info['version'];
			}
			$resumable_uploads = $nada_client->supports_resumable_uploads();
		} catch (Exception $e) {
			// Publish form still works without version info.
		}

		$study_info = $this->get_study_info_from_nada($study_idno, $conn_info);
		if (is_array($study_info) && $this->study_info_loaded($study_info)) {
			$access_policy = $this->resolve_study_access_policy($study_info, $data_access_codes);
			if ($access_policy !== null && $access_policy !== '') {
				$study_info['access_policy'] = $access_policy;
			}
		}

		$response = [
			'study_info' => $study_info,
			'collections_codes' => $collections,
			'data_access_codes' => $data_access_codes,
			'collections_linked' => $this->get_study_collections_from_nada($study_idno, $conn_info),
			'nada_version' => $nada_version,
			'resumable_uploads' => $resumable_uploads,
		];

		$this->load->library('Editor_nada_indicator_publish');
		if ($this->editor_nada_indicator_publish->is_indicator_project_type($project['type'])) {
			$response['indicator_publish'] = $this->get_indicator_publish_info(
				$user_id,
				$catalog_connection_id,
				$project_id
			);
		}

		return $response;
	}

	/**
	 * Prefetch indicator DSD/data status on NADA for the publish UI.
	 *
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param int|string $project_id
	 * @return array
	 */
	public function get_indicator_publish_info($user_id, $catalog_connection_id, $project_id)
	{
		$this->load->library('Editor_nada_indicator_publish');

		$project = $this->Editor_model->get_basic_info($project_id);
		if (!$project) {
			throw new Exception('Project not found');
		}

		$local = $this->editor_nada_indicator_publish->get_local_indicator_context($project['id']);
		$out = array(
			'local' => $local,
			'nada_dsd' => null,
			'nada_data' => null,
		);

		if (!$local['bound'] || empty($local['data_structure_reference'])) {
			return $out;
		}

		$conn_info = $this->Catalog_connections_model->get_connection($user_id, $catalog_connection_id);
		if (!$conn_info) {
			return $out;
		}

		try {
			$client = Nada_catalog_client::from_connection($conn_info);
			$out['nada_dsd'] = $this->editor_nada_indicator_publish->get_nada_dsd_status(
				$client,
				$local['data_structure_reference'],
				$local['data_structure_id']
			);
			if (!empty($local['study_idno'])) {
				$out['nada_data'] = $this->editor_nada_indicator_publish->get_nada_data_count(
					$client,
					$local['study_idno']
				);
			}
		} catch (Exception $e) {
			$out['prefetch_error'] = $e->getMessage();
		}

		return $out;
	}

	/**
	 * Publish indicator DSD and/or DuckDB data to NADA.
	 *
	 * @param int $sid
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param array $options publish_dsd, dsd_overwrite, publish_indicator_data
	 * @return array
	 */
	public function publish_indicator_extras($sid, $user_id, $catalog_connection_id, $options = array())
	{
		$this->load->library('Editor_nada_indicator_publish');
		$project = $this->Editor_model->get_basic_info($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}
		if (!$this->editor_nada_indicator_publish->is_indicator_project_type($project['type'])) {
			throw new Exception('Indicator publish options apply only to indicator/timeseries projects');
		}

		return $this->editor_nada_indicator_publish->publish_indicator_extras(
			$sid,
			$user_id,
			$catalog_connection_id,
			$options
		);
	}

	/**
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @return Nada_catalog_client
	 */
	public function get_nada_catalog_client($user_id, $catalog_connection_id)
	{
		require_once APPPATH . 'libraries/Nada_catalog_client.php';
		$conn_info = $this->Catalog_connections_model->get_connection($user_id, $catalog_connection_id);
		if (!$conn_info) {
			throw new Exception('Target catalog was not found');
		}

		return Nada_catalog_client::from_connection($conn_info);
	}

	/**
	 * Proxy NADA resumable upload init for step-by-step indicator data publish.
	 *
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param int|string $project_id
	 * @param array $input
	 * @return array
	 */
	public function nada_upload_init($user_id, $catalog_connection_id, $project_id, array $input)
	{
		if (!empty($input['source'])) {
			return $this->nada_upload_init_from_server_source(
				$user_id,
				$catalog_connection_id,
				$project_id,
				$input
			);
		}

		$project = $this->Editor_model->get_basic_info($project_id);
		if (!$project) {
			throw new Exception('Project not found');
		}

		if (empty($input['filename'])) {
			throw new Exception('filename is required');
		}
		if (empty($input['total_size']) || !is_numeric($input['total_size'])) {
			throw new Exception('total_size is required');
		}
		if (empty($input['total_chunks']) || !is_numeric($input['total_chunks'])) {
			throw new Exception('total_chunks is required');
		}
		if (empty($input['chunk_size']) || !is_numeric($input['chunk_size'])) {
			throw new Exception('chunk_size is required');
		}

		$metadata = isset($input['metadata']) && is_array($input['metadata']) ? $input['metadata'] : array();
		$metadata['source'] = 'metadata_editor_publish';
		$metadata['project_id'] = (int) $project['id'];

		$client = $this->get_nada_catalog_client($user_id, $catalog_connection_id);
		$init = $client->init_resumable_upload(
			(string) $input['filename'],
			(int) $input['total_size'],
			(int) $input['total_chunks'],
			(int) $input['chunk_size'],
			$metadata
		);

		return array(
			'status' => 'success',
			'upload_id' => (string) $init['upload_id'],
			'nada' => $init,
		);
	}

	/**
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param string $upload_id
	 * @param int $chunk_number
	 * @param string $chunk_data
	 * @param int $chunk_size
	 * @return array
	 */
	public function nada_upload_chunk($user_id, $catalog_connection_id, $upload_id, $chunk_number, $chunk_data, $chunk_size)
	{
		$client = $this->get_nada_catalog_client($user_id, $catalog_connection_id);
		$result = $client->upload_resumable_chunk(
			$upload_id,
			(int) $chunk_number,
			$chunk_data,
			(int) $chunk_size
		);

		$response = array(
			'status' => 'success',
		);
		if (is_array($result)) {
			$response = array_merge($response, $result);
		}
		if (!isset($response['upload_status']) && isset($response['status'])) {
			$response['upload_status'] = $response['status'];
		}

		return $response;
	}

	/**
	 * Initialize a NADA resumable upload using a file on the ME server (no browser transfer).
	 *
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param int|string $project_id
	 * @param array $input source, resource_id (for external_resource)
	 * @return array
	 */
	public function nada_upload_init_from_server_source($user_id, $catalog_connection_id, $project_id, array $input)
	{
		$source = isset($input['source']) ? trim((string) $input['source']) : '';
		if ($source === '') {
			throw new Exception('source is required');
		}

		$project = $this->Editor_model->get_basic_info($project_id);
		if (!$project) {
			throw new Exception('Project not found');
		}

		require_once APPPATH . 'libraries/Nada_catalog_client.php';
		$client = $this->get_nada_catalog_client($user_id, $catalog_connection_id);
		if (!$client->supports_resumable_uploads()) {
			throw new Exception('Target catalog does not support resumable uploads');
		}

		$limits = $client->get_upload_limits();
		$chunkSize = (int) min($limits['recommended_chunk_size'], $limits['max_chunk_size']);
		if ($chunkSize < 1) {
			$chunkSize = 8388608;
		}

		$serverFileKey = null;
		$resourceId = null;
		$filename = '';
		$filePath = '';

		if ($source === 'external_resource') {
			if (empty($input['resource_id'])) {
				throw new Exception('resource_id is required for external_resource uploads');
			}
			$resourceId = (int) $input['resource_id'];
			$fileInfo = $this->resolve_external_resource_upload_file($project_id, $resourceId);
			$filePath = $fileInfo['path'];
			$filename = $fileInfo['filename'];
		} elseif ($source === 'indicator_data') {
			$fileInfo = $this->prepare_indicator_data_export_file($project_id);
			$filePath = $fileInfo['path'];
			$filename = $fileInfo['filename'];
			$serverFileKey = $this->register_nada_upload_server_file($project_id, $filePath, $source, true);
		} else {
			throw new Exception('Unsupported upload source: ' . $source);
		}

		if (!is_file($filePath) || !is_readable($filePath)) {
			throw new Exception('Upload file is not readable');
		}

		$totalSize = (int) filesize($filePath);
		if ($totalSize <= 0) {
			throw new Exception('Upload file is empty');
		}

		$totalChunks = (int) max(1, ceil($totalSize / $chunkSize));
		$metadata = array(
			'source' => 'metadata_editor_publish',
			'project_id' => (int) $project['id'],
			'upload_source' => $source,
		);
		if ($resourceId !== null) {
			$metadata['resource_id'] = $resourceId;
		}

		$init = $client->init_resumable_upload(
			$filename,
			$totalSize,
			$totalChunks,
			$chunkSize,
			$metadata
		);

		$response = array(
			'status' => 'success',
			'upload_id' => (string) $init['upload_id'],
			'filename' => $filename,
			'total_size' => $totalSize,
			'total_chunks' => $totalChunks,
			'chunk_size' => $chunkSize,
			'source' => $source,
		);
		if ($resourceId !== null) {
			$response['resource_id'] = $resourceId;
		}
		if ($serverFileKey !== null) {
			$response['server_file_key'] = $serverFileKey;
		}

		return $response;
	}

	/**
	 * @param int|string $project_id
	 * @param int $resource_id
	 * @return array{path:string,filename:string}
	 */
	public function resolve_external_resource_upload_file($project_id, $resource_id)
	{
		$resource = $this->Editor_resource_model->select_single($project_id, $resource_id);
		if (!$resource) {
			throw new Exception('Resource not found');
		}
		if (empty($resource['filename']) || is_url($resource['filename'])) {
			throw new Exception('Resource has no local file to upload');
		}

		$file_path = $this->Editor_resource_model->get_resource_file_by_name($project_id, $resource['filename']);
		if (!is_file($file_path)) {
			throw new Exception('Resource file not found: ' . basename((string) $resource['filename']));
		}

		return array(
			'path' => $file_path,
			'filename' => basename((string) $resource['filename']),
		);
	}

	/**
	 * Export indicator timeseries CSV to a temp file for server-side NADA upload.
	 *
	 * @param int|string $project_id
	 * @return array{path:string,filename:string}
	 */
	public function prepare_indicator_data_export_file($project_id)
	{
		$this->load->library('indicator_duckdb_service');
		$this->load->library('Editor_nada_indicator_publish');

		$context = $this->editor_nada_indicator_publish->get_local_indicator_context($project_id);
		if (empty($context['has_published_data'])) {
			throw new Exception('No published indicator data in DuckDB. Import data in the editor first.');
		}

		$csvBody = $this->indicator_duckdb_service->timeseries_export_csv_body($project_id);
		$tmpPath = tempnam(sys_get_temp_dir(), 'me_nada_ts_');
		if ($tmpPath === false) {
			throw new Exception('Could not create temporary CSV file');
		}
		$csvPath = $tmpPath . '.csv';
		@unlink($tmpPath);
		if (@file_put_contents($csvPath, $csvBody) === false) {
			throw new Exception('Could not write DuckDB export CSV');
		}

		return array(
			'path' => $csvPath,
			'filename' => 'indicator_timeseries_' . (int) $project_id . '.csv',
		);
	}

	/**
	 * @param int|string $project_id
	 * @param string $source
	 * @param int|null $resource_id
	 * @param string|null $server_file_key
	 * @param int $chunk_number
	 * @param int $chunk_size
	 * @return string
	 */
	public function read_nada_upload_server_chunk($project_id, $source, $resource_id, $server_file_key, $chunk_number, $chunk_size)
	{
		$source = trim((string) $source);
		if ($source === 'external_resource') {
			if ($resource_id === null || $resource_id === '') {
				throw new Exception('resource_id is required for external_resource chunk upload');
			}
			$fileInfo = $this->resolve_external_resource_upload_file($project_id, (int) $resource_id);
			$filePath = $fileInfo['path'];
		} elseif ($source === 'indicator_data') {
			if ($server_file_key === null || $server_file_key === '') {
				throw new Exception('server_file_key is required for indicator_data chunk upload');
			}
			$filePath = $this->resolve_nada_upload_server_file($project_id, $server_file_key);
		} else {
			throw new Exception('Unsupported upload source: ' . $source);
		}

		return $this->read_server_file_chunk($filePath, (int) $chunk_number, (int) $chunk_size);
	}

	/**
	 * @param string $file_path
	 * @param int $chunk_number
	 * @param int $chunk_size
	 * @return string
	 */
	public function read_server_file_chunk($file_path, $chunk_number, $chunk_size)
	{
		if (!is_file($file_path) || !is_readable($file_path)) {
			throw new Exception('Upload file is not readable');
		}
		if ($chunk_size < 1) {
			throw new Exception('Invalid chunk size');
		}
		if ($chunk_number < 0) {
			throw new Exception('Invalid chunk number');
		}

		$offset = $chunk_number * $chunk_size;
		$totalSize = (int) filesize($file_path);
		if ($offset >= $totalSize) {
			throw new Exception('Chunk offset is beyond end of file');
		}

		$fh = @fopen($file_path, 'rb');
		if ($fh === false) {
			throw new Exception('Could not open upload file for reading');
		}

		try {
			if (@fseek($fh, $offset) !== 0) {
				throw new Exception('Could not seek upload file');
			}
			$data = @fread($fh, $chunk_size);
			if ($data === false) {
				throw new Exception('Could not read upload file chunk');
			}
			if ($data === '') {
				throw new Exception('Upload file chunk is empty');
			}

			return $data;
		} finally {
			@fclose($fh);
		}
	}

	/**
	 * @param int|string $project_id
	 * @param string $file_path
	 * @param string $source
	 * @param bool $delete_file_on_release
	 * @return string
	 */
	public function register_nada_upload_server_file($project_id, $file_path, $source, $delete_file_on_release = false)
	{
		$key = bin2hex(random_bytes(16));
		$path = $this->nada_upload_server_file_registry_path($key);
		if (!$path) {
			throw new Exception('Could not register server upload file');
		}

		$data = array(
			'path' => $file_path,
			'sid' => (int) $project_id,
			'source' => (string) $source,
			'delete_file_on_release' => (bool) $delete_file_on_release,
			'created' => time(),
		);
		if (@file_put_contents($path, json_encode($data)) === false) {
			throw new Exception('Could not register server upload file');
		}

		return $key;
	}

	/**
	 * @param int|string $project_id
	 * @param string $server_file_key
	 * @return string
	 */
	public function resolve_nada_upload_server_file($project_id, $server_file_key)
	{
		$path = $this->nada_upload_server_file_registry_path($server_file_key);
		if (!$path || !is_file($path)) {
			throw new Exception('Server upload file registration not found');
		}

		$raw = @file_get_contents($path);
		$data = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($data) || empty($data['path'])) {
			throw new Exception('Invalid server upload file registration');
		}
		if ((int) $data['sid'] !== (int) $project_id) {
			throw new Exception('ACCESS-DENIED');
		}
		if (!is_file($data['path'])) {
			throw new Exception('Registered upload file no longer exists');
		}

		return (string) $data['path'];
	}

	/**
	 * @param int|string $project_id
	 * @param string $server_file_key
	 */
	public function release_nada_upload_server_file($project_id, $server_file_key)
	{
		$path = $this->nada_upload_server_file_registry_path($server_file_key);
		if (!$path || !is_file($path)) {
			return;
		}

		$raw = @file_get_contents($path);
		$data = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($data) || (int) $data['sid'] !== (int) $project_id) {
			return;
		}

		if (!empty($data['delete_file_on_release']) && !empty($data['path']) && is_file($data['path'])) {
			@unlink($data['path']);
		}
		@unlink($path);
	}

	/**
	 * @param string $server_file_key
	 * @return string|null
	 */
	private function nada_upload_server_file_registry_path($server_file_key)
	{
		$key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $server_file_key);
		if ($key === '') {
			return null;
		}

		return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'me_nada_upload_src_' . $key . '.json';
	}

	/**
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @return array
	 */
	public function nada_upload_limits($user_id, $catalog_connection_id)
	{
		$client = $this->get_nada_catalog_client($user_id, $catalog_connection_id);
		$limits = $client->get_upload_limits();

		return array(
			'status' => 'success',
			'recommended_chunk_size' => $limits['recommended_chunk_size'],
			'max_chunk_size' => $limits['max_chunk_size'],
		);
	}

	/**
	 * @param int $user_id
	 * @param int $catalog_connection_id
	 * @param string $upload_id
	 * @return array
	 */
	public function nada_upload_status($user_id, $catalog_connection_id, $upload_id)
	{
		$client = $this->get_nada_catalog_client($user_id, $catalog_connection_id);
		$status = $client->get_resumable_upload_status($upload_id);

		return array(
			'status' => 'success',
			'upload_status' => isset($status['upload_status']) ? $status['upload_status'] : null,
			'nada' => $status,
		);
	}

	private function get_study_info_from_nada($study_idno, $conn_info)
	{
		$base_url = rtrim($conn_info['url'], '/');
		$api_key = $conn_info['api_key'];
		$study_info = null;

		try{
			$study_info = $this->make_get_request(
				$base_url . '/index.php/api/datasets/' . $study_idno,
				$api_key
			);

			if ($study_info && $study_info['dataset']) {
				$study_info = $study_info['dataset'];
				unset($study_info['metadata']);
				$study_info['status'] = 'success';
				return $study_info;
			}

			return [
				'status' => 'failed',
				'error' => 'Study info not found',
				'response' => $study_info
			];
		} catch (Exception $e) {
			return [
				'status' => 'error',
				'error' => $e->getMessage(),
				'response' => $study_info,
				'api_url' => $base_url . '/index.php/api/datasets/' . $study_idno
			];
		}		
	}

	/**
	 * @param array|null $codes_response
	 * @return array<int, array{id:mixed,type:string,title:string}>
	 */
	private function normalize_nada_data_access_codes($codes_response)
	{
		if (!is_array($codes_response) || !isset($codes_response['codes']) || !is_array($codes_response['codes'])) {
			return array();
		}

		$codes = $codes_response['codes'];
		if (isset($codes['error'])) {
			return array();
		}

		$normalized = array();
		foreach ($codes as $code) {
			if (!is_array($code)) {
				continue;
			}

			$type = '';
			if (isset($code['type']) && $code['type'] !== '') {
				$type = (string) $code['type'];
			} elseif (isset($code['model']) && $code['model'] !== '') {
				$type = (string) $code['model'];
			}

			if ($type === '') {
				continue;
			}

			$normalized[] = array(
				'id' => isset($code['id']) ? $code['id'] : (isset($code['formid']) ? $code['formid'] : null),
				'type' => $type,
				'title' => isset($code['title']) && $code['title'] !== ''
					? (string) $code['title']
					: (isset($code['fname']) ? (string) $code['fname'] : $type),
			);
		}

		return array_values($normalized);
	}

	/**
	 * @param array $study_info
	 * @return bool
	 */
	private function study_info_loaded(array $study_info)
	{
		if (isset($study_info['status']) && in_array($study_info['status'], array('failed', 'error'), true)) {
			return false;
		}

		return !empty($study_info['idno']) || !empty($study_info['title']);
	}

	/**
	 * Resolve NADA form model for publish access_policy from study row.
	 *
	 * @param array $study_info
	 * @param array $data_access_codes
	 * @return string|null
	 */
	private function resolve_study_access_policy(array $study_info, array $data_access_codes)
	{
		if (isset($study_info['data_access_type']) && $study_info['data_access_type'] !== '' && $study_info['data_access_type'] !== null) {
			return (string) $study_info['data_access_type'];
		}

		if (empty($study_info['formid'])) {
			return null;
		}

		$formid = (int) $study_info['formid'];
		foreach ($data_access_codes as $code) {
			if (!is_array($code) || !isset($code['id'])) {
				continue;
			}
			if ((int) $code['id'] === $formid && !empty($code['type'])) {
				return (string) $code['type'];
			}
		}

		return null;
	}


	private function get_study_collections_from_nada($study_idno, $conn_info)
	{
		$api_url = rtrim($conn_info['url'], '/') . '/index.php/api/datasets/collections/' . $study_idno;
		$base_url = rtrim($conn_info['url'], '/');
		$api_key = $conn_info['api_key'];

		try {
			$collections = $this->make_get_request($api_url, $api_key);

			$collections_list = [];
			$collections_tmp = [];

			if (isset($collections['datasets']) && is_array($collections['datasets'])) {
				$collections_tmp = $collections['datasets'];
			} elseif (isset($collections['collections']) && is_array($collections['collections'])) {
				$collections_tmp = $collections['collections'];
			}

			foreach ($collections_tmp as $collection) {
				if (!is_array($collection)) {
					continue;
				}
				$owner = isset($collection['collection_owner']) ? $collection['collection_owner'] : null;
				$linked = isset($collection['linked_collection']) ? $collection['linked_collection'] : null;
				if ($owner !== null && $owner !== '') {
					$collections_list[] = $owner;
				}
				if ($linked !== null && $linked !== '' && $linked !== $owner) {
					$collections_list[] = $linked;
				}
			}
			$collections_list = array_values(array_unique($collections_list));

			return [
				'status' => 'success',
				'collections' => $collections_list,
				'api_url' => $api_url
			];
		} catch (Exception $e) {
			return [
				'status' => 'error',
				'error' => $e->getMessage(),
				'collections' => [],
				'api_url' => $api_url
			];
		}
	}

	/**
	 * Truncate catalog error bodies for API responses and logs.
	 *
	 * @param string $body
	 * @param int $max
	 * @return string
	 */
	private function truncate_error_body($body, $max = 12000)
	{
		$body = (string) $body;
		if (strlen($body) <= $max) {
			return $body;
		}
		return substr($body, 0, $max) . "\n… [truncated]";
	}

	/**
	 * Detect JSON vs HTML vs plain text in a catalog HTTP error body.
	 *
	 * @param string $body Raw body
	 * @param string $content_type Response Content-Type header value
	 * @return array{format:string,parsed:mixed|null,raw:string,content_type:string}
	 */
	private function classify_catalog_error_body($body, $content_type = '')
	{
		$body = (string) $body;
		$ct = strtolower(trim(explode(';', (string) $content_type)[0]));

		if (strpos($ct, 'json') !== false) {
			$decoded = json_decode($body, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				return [
					'format' => 'json',
					'parsed' => $decoded,
					'raw' => $this->truncate_error_body($body),
					'content_type' => $content_type,
				];
			}
		}

		$trim = ltrim($body);
		if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
			$decoded = json_decode($body, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				return [
					'format' => 'json',
					'parsed' => $decoded,
					'raw' => $this->truncate_error_body($body),
					'content_type' => $content_type,
				];
			}
		}

		if (strpos($ct, 'html') !== false || preg_match('/^\s*</', $body)) {
			return [
				'format' => 'html',
				'parsed' => null,
				'raw' => $this->truncate_error_body($body),
				'content_type' => $content_type,
			];
		}

		return [
			'format' => 'text',
			'parsed' => null,
			'raw' => $this->truncate_error_body($body),
			'content_type' => $content_type,
		];
	}

	/**
	 * Short human-readable summary for ApiRequestException message (not full body).
	 *
	 * @param array $classified classify_catalog_error_body()
	 * @param int $httpStatus HTTP status from catalog
	 * @return string
	 */
	private function summarize_catalog_error(array $classified, $httpStatus)
	{
		$httpStatus = (int) $httpStatus;
		if ($classified['format'] === 'json' && is_array($classified['parsed'])) {
			$p = $classified['parsed'];
			foreach (['message', 'error', 'detail'] as $key) {
				if (isset($p[$key]) && is_string($p[$key]) && $p[$key] !== '') {
					return $this->truncate_error_body($p[$key], 500);
				}
			}
			if (isset($p['errors']) && is_array($p['errors'])) {
				$first = reset($p['errors']);
				if (is_string($first) && $first !== '') {
					return $this->truncate_error_body($first, 500);
				}
			}
			return 'NADA API error (HTTP ' . $httpStatus . ')';
		}
		if ($classified['format'] === 'html') {
			return 'NADA returned an HTML error page (HTTP ' . $httpStatus . ')';
		}
		$raw = isset($classified['raw']) ? $classified['raw'] : '';
		if ($raw !== '') {
			return $this->truncate_error_body($raw, 500);
		}
		return 'NADA request failed (HTTP ' . $httpStatus . ')';
	}

	/**
	 * Standard detail array for ApiRequestException from a classified body + PSR response.
	 *
	 * @param array $classified
	 * @param \Psr\Http\Message\ResponseInterface $resp
	 * @param string $api_url Catalog endpoint URL
	 * @return array
	 */
	private function catalog_error_details_payload(array $classified, $resp, $api_url)
	{
		return [
			'status' => $resp->getStatusCode(),
			'reason' => $resp->getReasonPhrase(),
			'response_' => $classified['parsed'],
			'body_format' => $classified['format'],
			'raw_body' => $classified['raw'],
			'content_type' => $classified['content_type'],
			'api_url' => $api_url,
		];
	}

	/**
	 * Parse response body as JSON; throw if not valid JSON.
	 *
	 * @param string $response_text Raw response body
	 * @return mixed Decoded value (array, null, etc.)
	 * @throws Exception When response is not valid JSON
	 */
	private function parse_json_response($response_text)
	{
		$decoded = json_decode($response_text, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$msg = json_last_error_msg();
			$preview = strlen($response_text) > 500 ? substr($response_text, 0, 500) . '...' : $response_text;
			throw new Exception('INVALID_RESPONSE: ' . $preview);
		}
		return $decoded;
	}

	/**
	 * Make GET request to a URL with x-api-key header.
	 *
	 * @param string $url Full URL
	 * @param string $api_key
	 * @return array Decoded JSON response
	 */
	public function make_get_request($url, $api_key)
	{
		$client = new Client([
			'base_uri' => $url,
			'headers' => ['x-api-key' => $api_key],
		]);

		try {
			$api_response = $client->request('GET', '');
			$response_text = (string) $api_response->getBody();
			return $this->parse_json_response($response_text);
		} catch (ClientException $e) {
			$resp = $e->getResponse();
			throw new Exception((string) $resp->getBody());
		} catch (Exception $e) {
			throw new Exception("request failed: " . $e->getMessage());
		}
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

			$response_text = (string) $api_response->getBody();
			return $this->parse_json_response($response_text);
		} catch (ClientException $e) {
			$resp = $e->getResponse();
			$body = (string) $resp->getBody();
			$classified = $this->classify_catalog_error_body($body, $resp->getHeaderLine('Content-Type'));
			throw new ApiRequestException(
				$this->summarize_catalog_error($classified, $resp->getStatusCode()),
				$this->catalog_error_details_payload($classified, $resp, $url)
			);
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
			$response_text = (string) $api_response->getBody();
			return $this->parse_json_response($response_text);
		} catch (ClientException $e) {
			$resp = $e->getResponse();
			$body = (string) $resp->getBody();
			$classified = $this->classify_catalog_error_body($body, $resp->getHeaderLine('Content-Type'));
			throw new ApiRequestException(
				$this->summarize_catalog_error($classified, $resp->getStatusCode()),
				$this->catalog_error_details_payload($classified, $resp, $url)
			);
		}
		catch (Exception $e) {
			throw new Exception("request failed: ". $e->getMessage());
		}
	}

	

}    