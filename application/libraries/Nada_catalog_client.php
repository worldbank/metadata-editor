<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

/**
 * HTTP client for NADA catalog APIs (datasets, admin DSD/timeseries, resumable uploads).
 */
class Nada_catalog_client {

	/** @var string */
	private $base_url;

	/** @var string */
	private $api_key;

	/** @var int */
	private $timeout = 120;

	/** @var int */
	private $upload_timeout = 600;

	public function __construct($base_url, $api_key)
	{
		$this->base_url = rtrim((string) $base_url, '/');
		$this->api_key = (string) $api_key;
	}

	/**
	 * @param array $conn_info Row from Catalog_connections_model (url, api_key)
	 * @return self
	 */
	public static function from_connection(array $conn_info)
	{
		if (empty($conn_info['url'])) {
			throw new Exception('Catalog connection URL is not set');
		}
		if (empty($conn_info['api_key'])) {
			throw new Exception('Catalog connection API key is not set');
		}

		return new self($conn_info['url'], $conn_info['api_key']);
	}

	/**
	 * @param string $relativePath e.g. admin/data_structures/MY_DSD
	 * @return string
	 */
	public function api_url($relativePath)
	{
		$relativePath = ltrim((string) $relativePath, '/');

		return $this->base_url . '/index.php/api/' . $relativePath;
	}

	/**
	 * @param string $relativePath
	 * @return array Decoded JSON
	 */
	public function get($relativePath)
	{
		$url = $this->api_url($relativePath);
		$client = new Client(array(
			'timeout' => $this->timeout,
			'headers' => array('x-api-key' => $this->api_key),
		));

		try {
			$response = $client->request('GET', $url);
			return $this->decode_json_response((string) $response->getBody(), $url);
		} catch (ClientException $e) {
			throw $this->client_exception($e, $url);
		} catch (RequestException $e) {
			throw new Exception('NADA request failed: ' . $e->getMessage());
		}
	}

	/**
	 * @param string $relativePath
	 * @param array|null $body
	 * @return array Decoded JSON
	 */
	public function post_json($relativePath, $body = null)
	{
		$url = $this->api_url($relativePath);
		$client = new Client(array(
			'timeout' => $this->timeout,
			'headers' => array(
				'x-api-key' => $this->api_key,
				'Content-Type' => 'application/json',
			),
		));

		try {
			$options = array();
			if ($body !== null) {
				$options['json'] = $body;
			}
			$response = $client->request('POST', $url, $options);
			return $this->decode_json_response((string) $response->getBody(), $url);
		} catch (ClientException $e) {
			throw $this->client_exception($e, $url);
		} catch (RequestException $e) {
			throw new Exception('NADA request failed: ' . $e->getMessage());
		}
	}

	/**
	 * POST multipart/form-data (no file attachment).
	 *
	 * @param string $relativePath
	 * @param array $fields
	 * @return array
	 */
	public function post_multipart($relativePath, array $fields)
	{
		$url = $this->api_url($relativePath);
		$multipart = array();
		foreach ($fields as $name => $value) {
			if ($value === null || $value === '') {
				continue;
			}
			$multipart[] = array(
				'name' => (string) $name,
				'contents' => (string) $value,
			);
		}

		$client = new Client(array(
			'timeout' => $this->upload_timeout,
			'headers' => array('x-api-key' => $this->api_key),
		));

		try {
			$response = $client->request('POST', $url, array('multipart' => $multipart));
			return $this->decode_json_response((string) $response->getBody(), $url);
		} catch (ClientException $e) {
			throw $this->client_exception($e, $url);
		} catch (RequestException $e) {
			throw new Exception('NADA request failed: ' . $e->getMessage());
		}
	}

	/**
	 * GET /api/version — e.g. { "version": "5.6" }
	 *
	 * @return array
	 */
	public function get_version()
	{
		return $this->get('version');
	}

	/**
	 * Whether this catalog supports NADA resumable uploads (5.6+ or uploads API present).
	 *
	 * @return bool
	 */
	public function supports_resumable_uploads()
	{
		try {
			$info = $this->get_version();
			$version = isset($info['version']) ? trim((string) $info['version']) : '';
			if ($version !== '' && self::version_at_least($version, '5.6')) {
				return true;
			}
		} catch (Exception $e) {
			// Fall through to feature detection.
		}

		try {
			$this->get('uploads/limits');
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * @param string $version
	 * @param string $minimum e.g. 5.6 or 5.6.0
	 * @return bool
	 */
	public static function version_at_least($version, $minimum)
	{
		$version = self::normalize_version_string($version);
		$minimum = self::normalize_version_string($minimum);
		if ($version === '' || $minimum === '') {
			return false;
		}

		return version_compare($version, $minimum, '>=');
	}

	/**
	 * @param string $version
	 * @return string
	 */
	public static function normalize_version_string($version)
	{
		$version = trim((string) $version);
		if ($version === '') {
			return '';
		}
		if (preg_match('/^\d+\.\d+$/', $version)) {
			return $version . '.0';
		}

		return $version;
	}

	/**
	 * @return array{recommended_chunk_size:int,max_chunk_size:int}
	 */
	public function get_upload_limits()
	{
		$default = 8 * 1024 * 1024;
		try {
			$limits = $this->get('uploads/limits');
			$max = !empty($limits['max_chunk_size']) ? max(1, (int) $limits['max_chunk_size']) : $default;
			$recommended = !empty($limits['recommended_chunk_size'])
				? max(1, (int) $limits['recommended_chunk_size'])
				: $max;

			return array(
				'recommended_chunk_size' => min($recommended, $max),
				'max_chunk_size' => $max,
			);
		} catch (Exception $e) {
			return array(
				'recommended_chunk_size' => $default,
				'max_chunk_size' => $default,
			);
		}
	}

	/**
	 * @param string $filename
	 * @param int $totalSize
	 * @param int $totalChunks
	 * @param int $chunkSize
	 * @param array $metadata
	 * @return array Decoded NADA init response
	 */
	public function init_resumable_upload($filename, $totalSize, $totalChunks, $chunkSize, array $metadata = array())
	{
		$init = $this->post_json('uploads/init', array(
			'filename' => $filename,
			'total_size' => (int) $totalSize,
			'total_chunks' => (int) $totalChunks,
			'chunk_size' => (int) $chunkSize,
			'metadata' => $metadata,
		));

		if (empty($init['upload_id'])) {
			throw new Exception('NADA upload init did not return upload_id');
		}

		return $init;
	}

	/**
	 * @param string $uploadId
	 * @return array
	 */
	public function get_resumable_upload_status($uploadId)
	{
		return $this->get('uploads/status/' . rawurlencode((string) $uploadId));
	}

	/**
	 * Upload a local file to NADA via resumable /uploads/* and return upload_id.
	 *
	 * @param string $localPath
	 * @param string|null $filename
	 * @param callable|null $onProgress function(int $uploadedChunks, int $totalChunks)
	 * @return string upload_id
	 */
	public function upload_local_file_resumable($localPath, $filename = null, $onProgress = null)
	{
		if (!is_file($localPath) || !is_readable($localPath)) {
			throw new Exception('File is not readable: ' . $localPath);
		}

		$filename = $filename ?: basename($localPath);
		$totalSize = (int) filesize($localPath);
		if ($totalSize <= 0) {
			throw new Exception('File is empty');
		}

		$limits = $this->get_upload_limits();
		$chunkSize = $limits['max_chunk_size'];
		$totalChunks = (int) max(1, ceil($totalSize / $chunkSize));

		$init = $this->init_resumable_upload(
			$filename,
			$totalSize,
			$totalChunks,
			$chunkSize,
			array('source' => 'metadata_editor_publish')
		);

		$uploadId = (string) $init['upload_id'];
		$fh = fopen($localPath, 'rb');
		if ($fh === false) {
			throw new Exception('Could not read file for upload');
		}

		try {
			for ($chunkNumber = 0; $chunkNumber < $totalChunks; $chunkNumber++) {
				$chunkData = fread($fh, $chunkSize);
				if ($chunkData === false) {
					throw new Exception('Failed reading file chunk ' . $chunkNumber);
				}
				$actualSize = strlen($chunkData);
				if ($actualSize === 0) {
					break;
				}
				$this->upload_resumable_chunk($uploadId, $chunkNumber, $chunkData, $actualSize);
				if (is_callable($onProgress)) {
					call_user_func($onProgress, $chunkNumber + 1, $totalChunks);
				}
			}
		} finally {
			fclose($fh);
		}

		$status = $this->get_resumable_upload_status($uploadId);
		$uploadStatus = isset($status['upload_status']) ? $status['upload_status'] : '';
		if ($uploadStatus !== 'completed') {
			throw new Exception('NADA resumable upload did not complete');
		}

		return $uploadId;
	}

	/**
	 * @param string $uploadId
	 * @param int $chunkNumber
	 * @param string $chunkData
	 * @param int $chunkSize
	 * @return array
	 */
	public function upload_resumable_chunk($uploadId, $chunkNumber, $chunkData, $chunkSize)
	{
		$url = $this->api_url('uploads/chunk/' . rawurlencode($uploadId));
		$client = new Client(array(
			'timeout' => $this->upload_timeout,
			'headers' => array(
				'x-api-key' => $this->api_key,
				'Content-Type' => 'application/octet-stream',
				'X-Upload-Chunk-Number' => (string) (int) $chunkNumber,
				'X-Upload-Chunk-Size' => (string) (int) $chunkSize,
			),
		));

		try {
			$response = $client->request('POST', $url, array('body' => $chunkData));
			return $this->decode_json_response((string) $response->getBody(), $url);
		} catch (ClientException $e) {
			throw $this->client_exception($e, $url);
		} catch (RequestException $e) {
			throw new Exception('NADA chunk upload failed: ' . $e->getMessage());
		}
	}

	/**
	 * @param string $body
	 * @param string $url
	 * @return array
	 */
	private function decode_json_response($body, $url)
	{
		$decoded = json_decode($body, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$preview = strlen($body) > 500 ? substr($body, 0, 500) . '...' : $body;
			throw new Exception('Invalid JSON from NADA (' . $url . '): ' . $preview);
		}

		return is_array($decoded) ? $decoded : array('response' => $decoded);
	}

	/**
	 * @param ClientException $e
	 * @param string $url
	 * @return ApiRequestException
	 */
	private function client_exception(ClientException $e, $url)
	{
		$resp = $e->getResponse();
		$body = $resp ? (string) $resp->getBody() : $e->getMessage();
		$status = $resp ? (int) $resp->getStatusCode() : 0;
		$message = 'NADA API error';
		if ($status > 0) {
			$message .= ' (HTTP ' . $status . ')';
		}
		$decoded = json_decode($body, true);
		if (is_array($decoded)) {
			foreach (array('message', 'error', 'detail') as $key) {
				if (!empty($decoded[$key]) && is_string($decoded[$key])) {
					$message = $decoded[$key];
					break;
				}
			}
		} elseif (trim($body) !== '') {
			$message = strlen($body) > 500 ? substr($body, 0, 500) . '…' : $body;
		}

		return new ApiRequestException($message, array(
			'status' => $status,
			'api_url' => $url,
			'response_' => is_array($decoded) ? $decoded : null,
			'raw_body' => $body,
		));
	}

	/**
	 * DELETE /api/uploads/{upload_id}
	 *
	 * @param string $uploadId
	 * @return array
	 */
	public function delete_resumable_upload($uploadId)
	{
		$uploadId = trim((string) $uploadId);
		if ($uploadId === '') {
			throw new Exception('upload_id is required');
		}

		$url = $this->api_url('uploads/' . rawurlencode($uploadId));
		$client = new Client(array(
			'timeout' => $this->timeout,
			'headers' => array('x-api-key' => $this->api_key),
		));

		try {
			$response = $client->request('DELETE', $url);
			return $this->decode_json_response((string) $response->getBody(), $url);
		} catch (ClientException $e) {
			throw $this->client_exception($e, $url);
		} catch (RequestException $e) {
			throw new Exception('NADA request failed: ' . $e->getMessage());
		}
	}

	/**
	 * Delete all external resource metadata rows for a study on NADA.
	 *
	 * @param string $studyIdno
	 * @return array
	 */
	public function delete_all_study_resources($studyIdno)
	{
		$studyIdno = trim((string) $studyIdno);
		if ($studyIdno === '') {
			throw new Exception('Study IDNO is required');
		}

		return $this->post_json(
			'datasets/' . rawurlencode($studyIdno) . '/resources/delete_all',
			array('confirm' => true)
		);
	}
}
