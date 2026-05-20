<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Codelists extends MY_REST_Controller
{
	/** @var string ACL resource key (see acl_permissions.php). */
	private $registry_resource = 'codelist';

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Codelists_model");
		
		$this->is_authenticated_or_die();
		$this->api_user = $this->api_user();
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
	 * List codelists (catalogue).
	 *
	 * GET /api/codelists
	 * Default: one row per family (collapsed, id = pid) with versions_count.
	 * flat=1: all version rows. collapsed=0 forces flat; collapsed=1 forces collapsed.
	 * with_counts=1 (default): item_count, dsd_component_count.
	 * page + per_page: paginated (max 200 per page). Without page: legacy offset/limit or full list.
	 */
	function index_get()
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			$filters = array();

			$agency = $this->input->get('agency');
			if ($agency) {
				$filters['agency'] = $agency;
			}

			$search = $this->input->get('search');
			if ($search === null || $search === '') {
				$search = $this->input->get('q');
			}
			if ($search) {
				$filters['search'] = $search;
			}

			if (filter_var($this->input->get('exclude_archived'), FILTER_VALIDATE_BOOLEAN)) {
				$filters['exclude_archived'] = true;
			}

			$status = $this->input->get('status');
			if ($status !== null && $status !== '') {
				$filters['status'] = $status;
			}

			$with_counts = $this->input->get('with_counts');
			$with_counts = ($with_counts === null || $with_counts === '' || $with_counts === '1' || $with_counts === 'true');
			$flat = $this->_codelists_catalog_flat_mode();

			$page_raw = $this->input->get('page');
			if ($page_raw !== null && $page_raw !== '') {
				$page = max(1, (int) $page_raw);
				$per_page = $this->input->get('per_page');
				$per_page = ($per_page !== null && $per_page !== '') ? (int) $per_page : 50;
				$p = $this->Codelists_model->get_codelists_paged(array(
					'page' => $page,
					'per_page' => $per_page,
					'search' => isset($filters['search']) ? $filters['search'] : '',
					'with_counts' => $with_counts,
					'flat' => $flat,
					'agency' => isset($filters['agency']) ? $filters['agency'] : null,
					'status' => isset($filters['status']) ? $filters['status'] : null,
					'exclude_archived' => !empty($filters['exclude_archived']),
				));
				$this->set_response(array(
					'status' => 'success',
					'codelists' => $p['rows'],
					'total' => $p['total'],
					'page' => $p['page'],
					'per_page' => $p['per_page'],
					'collapsed' => !$flat,
				), REST_Controller::HTTP_OK);
				return;
			}

			if ($flat) {
				$offset = (int) $this->input->get('offset');
				$limit = $this->input->get('limit');
				if ($limit !== null) {
					$limit = (int) $limit;
				}
				$codelists = $this->Codelists_model->get_all($filters, $offset, $limit, 'created_at', 'ASC');
				if ($with_counts && !empty($codelists)) {
					$this->Codelists_model->attach_catalog_counts($codelists, false);
				}
				$total = $this->Codelists_model->count($filters);
			} else {
				$codelists = $this->Codelists_model->get_all_collapsed($filters, $with_counts);
				$total = $this->Codelists_model->count_collapsed($filters);
			}

			$response = array(
				'status' => 'success',
				'codelists' => $codelists,
				'total' => $total,
				'collapsed' => !$flat,
			);

			$limit = $this->input->get('limit');
			if ($flat && $limit !== null && (int) $limit > 0) {
				$response['offset'] = (int) $this->input->get('offset');
				$response['limit'] = (int) $limit;
			}

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * flat=1 => all version rows; collapsed=1 => family heads; default collapsed.
	 */
	private function _codelists_catalog_flat_mode()
	{
		$flat = ($this->input->get('flat') === '1' || $this->input->get('flat') === 'true');
		if ($this->input->get('collapsed') === '1' || $this->input->get('collapsed') === 'true') {
			$flat = false;
		}
		if ($this->input->get('collapsed') === '0' || $this->input->get('collapsed') === 'false') {
			$flat = true;
		}
		if ($this->input->get('flat') === '1' || $this->input->get('flat') === 'true') {
			$flat = true;
		}
		return $flat;
	}

	/**
	 * GET /api/codelists/versions/{name}?agency=
	 */
	function versions_get($name = null)
	{
		try {
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$name) {
				throw new Exception('Codelist name (SDMX id) is required');
			}
			$agency = $this->input->get('agency');
			$rows = $this->Codelists_model->get_codelist_versions($name, $agency ?: null);
			if ($this->input->get('with_counts') !== '0' && $this->input->get('with_counts') !== 'false') {
				$this->Codelists_model->attach_catalog_counts($rows, false);
			}
			$this->set_response(array(
				'status' => 'success',
				'codelists' => $rows,
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get single codelist by ID
	 * 
	 * GET /api/codelists/{id}
	 * 
	 */
	function single_get($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			$codelist = $this->Codelists_model->get_by_id($id);

			if (!$codelist) {
				throw new Exception("Codelist not found");
			}

			$response = array(
				'status' => 'success',
				'codelist' => $codelist
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Get codelist by agency, name (SDMX id), and version (version may be empty for blank DB version)
	 * 
	 * GET /api/codelists/by-identity/{agency}/{name}/{version}
	 * 
	 */
	function by_identity_get($agency = null, $name = null, $version = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$agency || !$name) {
				throw new Exception("Agency and name (SDMX maintainable id) are required");
			}
			if ($version === null || $version === '') {
				$version = '';
			}

			$codelist = $this->Codelists_model->get_by_identity($agency, $name, $version);

			if (!$codelist) {
				throw new Exception("Codelist not found");
			}

			$response = array(
				'status' => 'success',
				'codelist' => $codelist
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Resolve a registry codelist by SDMX identity (query params; safe for special characters).
	 *
	 * GET /api/codelists/lookup_by_identity?agency=&name=&version=
	 * version is optional (empty string matches codelists with blank version).
	 *
	 * @return void JSON { status, codelist } or error
	 */
	function lookup_by_identity_get()
	{
		try {
			$this->registry_require_or_die($this->registry_resource, 'browse');
			$agency = $this->input->get('agency');
			$name = $this->input->get('name');
			$version = $this->input->get('version');

			$agency = $agency !== null && $agency !== false ? trim((string) $agency) : '';
			$name = $name !== null && $name !== false ? trim((string) $name) : '';
			if ($version === null || $version === false) {
				$version = '';
			} else {
				$version = trim((string) $version);
			}

			if ($agency === '' || $name === '') {
				throw new Exception('agency and name query parameters are required');
			}

			$codelist = $this->Codelists_model->get_by_identity($agency, $name, $version);

			if (!$codelist) {
				throw new Exception('Codelist not found for the given agency, name, and version');
			}

			$this->set_response(array(
				'status' => 'success',
				'codelist' => $codelist,
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Create new codelist
	 * 
	 * POST /api/codelists
	 * 
	 */
	function index_post()
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			$data = (array)$this->raw_json_input();

			if (empty($data)) {
				throw new Exception("Codelist data is required");
			}

			// Validate required fields
			if (empty($data['agency'])) {
				throw new Exception("Agency is required");
			}
			if (empty($data['name'])) {
				throw new Exception("Name (SDMX maintainable id) is required");
			}
			if (empty($data['version'])) {
				throw new Exception("Version is required");
			}
			if (empty($data['title'])) {
				throw new Exception("Title is required");
			}

			if (isset($data['status'])) {
				$data['status'] = $this->Codelists_model->normalize_status($data['status']);
				if (!$this->_codelist_registry_admin()
					&& !in_array($data['status'], array('draft', 'active'), true)) {
					throw new Exception('Only administrators can create codelists with locked or archived status');
				}
			}

			// Check if codelist already exists
			$existing = $this->Codelists_model->get_by_identity(
				$data['agency'],
				$data['name'],
				$data['version']
			);

			if ($existing) {
				throw new Exception("Codelist with this agency, name, and version already exists");
			}

			// Create codelist
			$id = $this->Codelists_model->create($data);

			if (!$id) {
				throw new Exception("Failed to create codelist");
			}

			$response = array(
				'status' => 'success',
				'id' => $id,
				'message' => 'Codelist created successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Import codelists from SDMX-ML (2.1 / 3.0 structure message).
	 *
	 * POST /api/codelists/import_sdmx
	 *
	 * Body: raw SDMX-XML; multipart field {@code file}; or JSON {@code {"url":"https://..."}} to fetch SDMX-ML (http/https only, SSRF-hardened).
	 * Query: {@code dry_run}=1 preview only; {@code replace}=1 overwrite existing same agency/id/version.
	 */
	function import_sdmx_post()
	{
		try {
			$this->registry_require_or_die($this->registry_resource, 'import');
			$this->load->library('SDMX/SdmxCodelistImporter');
			$dry = filter_var($this->input->get('dry_run'), FILTER_VALIDATE_BOOLEAN);
			$replace = filter_var($this->input->get('replace'), FILTER_VALIDATE_BOOLEAN);

			$xml = $this->_import_sdmx_read_xml_body();
			if ($xml === null || trim($xml) === '') {
				throw new Exception('Send SDMX-ML (raw body or multipart "file"), or JSON {"url":"https://..."}');
			}
			$maxBytes = 15 * 1024 * 1024;
			if (strlen($xml) > $maxBytes) {
				throw new Exception('XML exceeds maximum size (15 MB)');
			}

			$importer = $this->sdmxcodelistimporter;
			$parsed = $importer->parseString($xml);
			if ($parsed['status'] !== 'success') {
				throw new Exception(isset($parsed['message']) ? $parsed['message'] : 'SDMX parse failed');
			}

			$userId = $this->get_api_user_id();
			$imported = array();
			$skipped = array();
			$failed = array();
			$allWarnings = isset($parsed['warnings']) ? $parsed['warnings'] : array();
			$allWarnings = array_merge($allWarnings, $importer->get_warnings());

			foreach ($parsed['codelists'] as $cl) {
				$r = $this->Codelists_model->import_sdmx_codelist($cl, array(
					'dry_run' => $dry,
					'replace_existing' => $replace,
					'created_by' => $userId ? (int) $userId : null,
				));
				if (!empty($r['warnings'])) {
					$allWarnings = array_merge($allWarnings, $r['warnings']);
				}
				if (empty($r['ok'])) {
					$failed[] = $r;
					continue;
				}
				if (isset($r['action']) && $r['action'] === 'skipped') {
					$skipped[] = $r;
					continue;
				}
				$imported[] = $r;
			}

			$overall = 'success';
			if (count($failed) > 0 && (count($imported) === 0 && count($skipped) === 0)) {
				$overall = 'failed';
			} elseif (count($failed) > 0) {
				$overall = 'partial';
			}

			$http = REST_Controller::HTTP_OK;
			if ($overall === 'failed' && !$dry) {
				$http = REST_Controller::HTTP_BAD_REQUEST;
			}

			$this->set_response(array(
				'status' => $overall,
				'dry_run' => $dry,
				'sdmx_version' => isset($parsed['sdmx_version']) ? $parsed['sdmx_version'] : null,
				'imported' => $imported,
				'skipped' => $skipped,
				'failed' => $failed,
				'warnings' => array_values(array_unique($allWarnings)),
			), $http);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * @return string|null
	 */
	private function _import_sdmx_read_xml_body()
	{
		if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
			$data = file_get_contents($_FILES['file']['tmp_name']);
			return $data !== false ? $data : null;
		}
		$raw = $this->input->raw_input_stream;
		if ($raw === null || $raw === '') {
			return null;
		}
		$trim = ltrim($raw);
		if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
			$j = json_decode($raw, true);
			if (is_array($j) && !empty($j['url']) && is_string($j['url'])) {
				return $this->_import_sdmx_fetch_url(trim($j['url']));
			}
		}
		return $raw;
	}

	/**
	 * Fetch SDMX-ML over http(s) with basic SSRF protections (no private/reserved targets).
	 *
	 * @param string $url
	 * @return string
	 * @throws Exception
	 */
	private function _import_sdmx_fetch_url($url)
	{
		if (strlen($url) > 2048) {
			throw new Exception('URL is too long');
		}
		$this->_import_sdmx_validate_remote_url($url);

		$maxBytes = 15 * 1024 * 1024;
		$body = null;
		$http = 0;

		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_USERAGENT, 'MetadataEditor-CodelistImport/1.0');
			if (defined('CURLOPT_PROTOCOLS')) {
				curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
				curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}
			if (defined('CURLOPT_MAXFILESIZE')) {
				curl_setopt($ch, CURLOPT_MAXFILESIZE, $maxBytes);
			}
			$body = curl_exec($ch);
			$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$cerr = (string) curl_error($ch);
			$errno = (int) curl_errno($ch);
			curl_close($ch);
			if ($body === false || $errno !== 0) {
				throw new Exception('Failed to fetch URL' . ($cerr !== '' ? ': ' . $cerr : ''));
			}
			if ($http >= 400) {
				throw new Exception('URL returned HTTP ' . $http);
			}
		} else {
			$ctx = stream_context_create(array(
				'http' => array(
					'timeout' => 30,
					'follow_location' => 1,
					'max_redirects' => 5,
					'user_agent' => 'MetadataEditor-CodelistImport/1.0',
				),
				'ssl' => array(
					'verify_peer' => true,
					'verify_peer_name' => true,
				),
			));
			$body = @file_get_contents($url, false, $ctx);
			if ($body === false) {
				throw new Exception('Failed to fetch URL');
			}
			if (!empty($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
				$http = (int) $m[1];
			} else {
				$http = 200;
			}
			if ($http >= 400) {
				throw new Exception('URL returned HTTP ' . $http);
			}
		}

		if (strlen($body) > $maxBytes) {
			throw new Exception('Downloaded XML exceeds maximum size (15 MB)');
		}
		if (trim($body) === '') {
			throw new Exception('Empty response from URL');
		}
		return $body;
	}

	/**
	 * @param string $url
	 * @throws Exception
	 */
	private function _import_sdmx_validate_remote_url($url)
	{
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			throw new Exception('Invalid URL');
		}
		$parts = parse_url($url);
		if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
			throw new Exception('Invalid URL');
		}
		$scheme = strtolower($parts['scheme']);
		if (!in_array($scheme, array('http', 'https'), true)) {
			throw new Exception('Only http and https URLs are allowed');
		}
		$host = $parts['host'];
		$h = strtolower($host);

		if ($h === 'localhost' || $h === '0.0.0.0' || strpos($h, '127.') === 0 || $h === '::1'
			|| strpos($h, '169.254.') === 0) {
			throw new Exception('URL host is not allowed');
		}

		if (filter_var($host, FILTER_VALIDATE_IP)) {
			if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				throw new Exception('URL host is not allowed');
			}
			return;
		}

		$resolved = @gethostbyname($host);
		if ($resolved !== false && $resolved !== '' && $resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
			if (!filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				throw new Exception('URL resolves to a disallowed address');
			}
		}
	}

	/**
	 * 
	 * Update existing codelist
	 * 
	 * POST /api/codelists/{id}
	 * 
	 */
	function update_post($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			// Verify the codelist exists
			$existing = $this->Codelists_model->get_by_id($id);
			if (!$existing) {
				throw new Exception("Codelist not found");
			}

			$data = (array)$this->raw_json_input();

			if (empty($data)) {
				throw new Exception("Codelist data is required");
			}

			// Don't allow changing identity fields
			unset($data['agency']);
			unset($data['name']);
			unset($data['version']);
			unset($data['idno']);

			$this->_apply_codelist_update_rules($existing, $data);

			// Update codelist
			$result = $this->Codelists_model->update($id, $data);

			if (!$result) {
				throw new Exception("Failed to update codelist");
			}

			$response = array(
				'status' => 'success',
				'id' => $id,
				'message' => 'Codelist updated successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Delete codelist (canonical — use this from browsers / locked-down proxies).
	 *
	 * POST /api/codelists/delete/{id}
	 */
	function delete_post($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'delete');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			$this->Codelists_model->require_deletable($id);

			$result = $this->Codelists_model->delete($id);

			if (!$result) {
				throw new Exception("Failed to delete codelist");
			}

			$response = array(
				'status' => 'success',
				'message' => 'Codelist deleted successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * DELETE /api/codelists/{id} — delegates to delete_post().
	 * Many IT environments block HTTP DELETE; clients should use POST delete_post instead.
	 */
	function index_delete($id = null)
	{
		$this->delete_post($id);
	}

	/**
	 * 
	 * Get all codes for a codelist
	 * 
	 * GET /api/codelists/codes/{codelist_id}
	 * Query params:
	 *   language (optional)         — filter labels to a specific language
	 *   search   (optional)         — LIKE filter on code value or any label text
	 *   offset   (optional, int)    — pagination offset (default 0)
	 *   limit    (optional, int)    — page size (default 50; pass 0 to return all)
	 *   compact  (optional, 1/true) — flatten labels to id, code, label, description,
	 *                                  sort_order, parent_id (defaults to English label)
	 * 
	 */
	function codes_get($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			// Verify the codelist exists
			$codelist = $this->Codelists_model->get_by_id($id);
			if (!$codelist) {
				throw new Exception("Codelist not found");
			}

			$language = $this->input->get('language') ?: null;
			$search   = $this->input->get('search')   ?: null;
			$offset   = max(0, (int) $this->input->get('offset'));
			$compact  = filter_var($this->input->get('compact'), FILTER_VALIDATE_BOOLEAN);

			$raw_limit = $this->input->get('limit');
			if ($raw_limit === null || $raw_limit === '') {
				$limit = 50;
			} else {
				$limit = (int) $raw_limit;
				if ($limit <= 0) {
					$limit = null; // 0 or negative = no limit
				}
			}

			$total = $this->Codelists_model->count_codes($id, $search);
			$codes = $this->Codelists_model->get_codes($id, $language, true, $search, $offset, $limit);

			if ($compact) {
				$codes = $this->_compact_codes($codes, $language ?: 'en');
			}

			$response = array(
				'status'  => 'success',
				'total'   => $total,
				'offset'  => $offset,
				'limit'   => $limit,
				'codes'   => $codes,
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Flatten the verbose codes-with-labels array into the compact shape:
	 *   { id, code, label, description, sort_order, parent_id }
	 *
	 * Label resolution order: preferred language → first available → empty string.
	 *
	 * @param array  $codes     Result of Codelists_model::get_codes()
	 * @param string $preferred Preferred language code (e.g. 'en')
	 * @return array
	 */
	private function _compact_codes(array $codes, $preferred = 'en')
	{
		$out = array();
		foreach ($codes as $c) {
			$labels = isset($c['labels']) && is_array($c['labels']) ? $c['labels'] : array();

			// Pick the preferred language row; fall back to the first available one.
			$chosen = null;
			foreach ($labels as $l) {
				if ((string) $l['language'] === $preferred) {
					$chosen = $l;
					break;
				}
			}
			if ($chosen === null && !empty($labels)) {
				$chosen = $labels[0];
			}

			$out[] = array(
				'id'          => $c['id'],
				'code'        => $c['code'],
				'label'       => $chosen ? (string) $chosen['label'] : '',
				'description' => $chosen ? (string) $chosen['description'] : '',
				'sort_order'  => $c['sort_order'],
				'parent_id'   => $c['parent_id'],
			);
		}
		return $out;
	}

	/**
	 * 
	 * Get single code by ID
	 * 
	 * GET /api/codelists/codes/{code_id}
	 * Query params: language (optional)
	 * 
	 */
	function code_get($code_id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$code_id) {
				throw new Exception("Code ID is required");
			}

			$language = $this->input->get('language');
			$code = $this->Codelists_model->get_code_by_id($code_id, $language);

			if (!$code) {
				throw new Exception("Code not found");
			}

			$response = array(
				'status' => 'success',
				'code' => $code
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Add a code to a codelist
	 * 
	 * POST /api/codelists/codes/{codelist_id}
	 * 
	 */
	function codes_post($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			$this->Codelists_model->require_content_mutable($id);

			$data = (array)$this->raw_json_input();

			if (empty($data)) {
				throw new Exception("Code data is required");
			}

			if (empty($data['code'])) {
				throw new Exception("Code identifier is required");
			}

			// Add code
			$code_id = $this->Codelists_model->add_code($id, $data);

			if (!$code_id) {
				throw new Exception("Failed to add code");
			}

			$response = array(
				'status' => 'success',
				'id' => $code_id,
				'message' => 'Code added successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * 
	 * Update an existing code
	 * 
	 * POST /api/codelists/codes/{code_id}
	 * 
	 */
	function code_update_post($code_id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$code_id) {
				throw new Exception("Code ID is required");
			}

			$existing = $this->Codelists_model->get_code_by_id($code_id);
			if (!$existing) {
				throw new Exception("Code not found");
			}

			$this->Codelists_model->require_content_mutable((int) $existing['codelist_id']);

			$data = (array)$this->raw_json_input();

			if (empty($data)) {
				throw new Exception("Code data is required");
			}

			// Don't allow changing codelist_id (FK to codelists.id)
			unset($data['codelist_id']);

			// Update code
			$result = $this->Codelists_model->update_code($code_id, $data);

			if (!$result) {
				throw new Exception("Failed to update code");
			}

			$response = array(
				'status' => 'success',
				'id' => $code_id,
				'message' => 'Code updated successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Delete a code item (canonical — POST).
	 *
	 * POST /api/codelists/code_delete/{code_id}
	 */
	function code_delete_post($code_id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$code_id) {
				throw new Exception("Code ID is required");
			}

			$code = $this->Codelists_model->get_code_by_id($code_id);
			if (!$code) {
				throw new Exception("Code not found");
			}

			$this->Codelists_model->require_content_mutable((int) $code['codelist_id']);

			$result = $this->Codelists_model->delete_code($code_id);

			if (!$result) {
				throw new Exception("Failed to delete code");
			}

			$response = array(
				'status' => 'success',
				'message' => 'Code deleted successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * POST /api/codelists/codes_delete/{code_id} — same as code_delete_post().
	 */
	function codes_delete_post($code_id = null)
	{
		$this->code_delete_post($code_id);
	}

	/**
	 * DELETE /api/codelists/code_delete/{code_id} — delegates to code_delete_post().
	 * Prefer POST (code_delete_post or codes_delete_post) when DELETE is blocked.
	 */
	function code_delete_delete($code_id = null)
	{
		$this->code_delete_post($code_id);
	}

	/**
	 * 
	 * Set a label for a code
	 * 
	 * POST /api/codelists/codes/{code_id}/labels
	 * Body: { "language": "en", "label": "Annual", "description": "Annual frequency" }
	 * 
	 */
	function code_label_post($code_id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$code_id) {
				throw new Exception("Code ID is required");
			}

			$code = $this->Codelists_model->get_code_by_id($code_id);
			if (!$code) {
				throw new Exception("Code not found");
			}

			$codelist_pk = (int) $code['codelist_id'];
			$this->Codelists_model->require_content_mutable($codelist_pk);

			$data = (array)$this->raw_json_input();

			if (empty($data['language'])) {
				throw new Exception("Language is required");
			}
			if (empty($data['label'])) {
				throw new Exception("Label is required");
			}

			if (!$this->Codelists_model->codelist_has_language($codelist_pk, $data['language'])) {
				throw new Exception("Language is not enabled for this codelist. Add it under codelist translations first.");
			}

			$label_id = $this->Codelists_model->set_code_label(
				$code_id,
				$data['language'],
				$data['label'],
				isset($data['description']) ? $data['description'] : null
			);

			if (!$label_id) {
				throw new Exception("Failed to set label");
			}

			$response = array(
				'status' => 'success',
				'id' => $label_id,
				'message' => 'Label set successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Delete a code label row (canonical — POST).
	 *
	 * POST /api/codelists/label_delete/{label_id}
	 */
	function label_delete_post($label_id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$label_id) {
				throw new Exception("Label ID is required");
			}

			// Get label to find code and codelist
			$this->db->where('id', $label_id);
			$label = $this->db->get('codelist_items_labels')->row_array();

			if (!$label) {
				throw new Exception("Label not found");
			}

			$code = $this->Codelists_model->get_code_by_id($label['codelist_item_id']);
			if (!$code) {
				throw new Exception("Code not found");
			}

			$this->Codelists_model->require_content_mutable((int) $code['codelist_id']);

			$result = $this->Codelists_model->delete_code_label($label_id);

			if (!$result) {
				throw new Exception("Failed to delete label");
			}

			$response = array(
				'status' => 'success',
				'message' => 'Label deleted successfully'
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * POST /api/codelists/labels_delete/{label_id} — same as label_delete_post().
	 */
	function labels_delete_post($label_id = null)
	{
		$this->label_delete_post($label_id);
	}

	/**
	 * DELETE /api/codelists/label_delete/{label_id} — delegates to label_delete_post().
	 * Prefer POST (label_delete_post or labels_delete_post) when DELETE is blocked.
	 */
	function label_delete_delete($label_id = null)
	{
		$this->label_delete_post($label_id);
	}

	/**
	 * 
	 * Get hierarchical structure of codes
	 * 
	 * GET /api/codelists/{id}/hierarchy
	 * Query params: language (optional)
	 * 
	 */
	function hierarchy_get($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			// Verify the codelist exists
			$codelist = $this->Codelists_model->get_by_id($id);
			if (!$codelist) {
				throw new Exception("Codelist not found");
			}

			$language = $this->input->get('language');
			$hierarchy = $this->Codelists_model->get_hierarchical_structure($id, $language);

			$response = array(
				'status' => 'success',
				'hierarchy' => $hierarchy
			);

			$this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * List header translations for a codelist (codelist_labels).
	 *
	 * GET /api/codelists/codelist_translations/{id}
	 */
	function codelist_translations_get($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			$codelist = $this->Codelists_model->get_by_id($id);
			if (!$codelist) {
				throw new Exception("Codelist not found");
			}

			$rows = $this->Codelists_model->get_codelist_translations($id);

			$this->set_response(array(
				'status' => 'success',
				'translations' => $rows
			), REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Upsert one codelist header translation.
	 *
	 * POST /api/codelists/codelist_translations/{id}
	 * Body: { "language": "en", "label": "...", "description": "..." }
	 */
	function codelist_translations_post($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			$this->Codelists_model->require_content_mutable($id);

			$data = (array)$this->raw_json_input();

			if (empty($data['language'])) {
				throw new Exception("Language is required");
			}
			if (!isset($data['label']) || $data['label'] === '') {
				throw new Exception("Label is required");
			}

			if (!$this->_codelists_iso_language_valid($data['language'])) {
				throw new Exception("Unknown language code (not in iso_languages config)");
			}

			$tid = $this->Codelists_model->set_codelist_translation(
				(int) $id,
				$data['language'],
				$data['label'],
				isset($data['description']) ? $data['description'] : null
			);

			if (!$tid) {
				throw new Exception("Failed to save translation");
			}

			$this->set_response(array(
				'status' => 'success',
				'id' => $tid,
				'message' => 'Translation saved'
			), REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Delete a codelist header translation row (canonical — POST).
	 *
	 * POST /api/codelists/codelist_translation_delete/{translation_id}
	 */
	function codelist_translation_delete_post($translation_id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'edit');
			if (!$translation_id) {
				throw new Exception("Translation ID is required");
			}

			$row = $this->Codelists_model->get_codelist_translation_by_id($translation_id);
			if (!$row) {
				throw new Exception("Translation not found");
			}

			$codelist_pk = (int) $row['codelist_id'];
			$this->Codelists_model->require_content_mutable($codelist_pk);
			$existing = $this->Codelists_model->get_codelist_translations($codelist_pk);
			if (count($existing) <= 1) {
				throw new Exception("Cannot remove the last codelist language. Add another language first.");
			}

			if (!$this->Codelists_model->delete_codelist_translation($translation_id)) {
				throw new Exception("Failed to delete translation");
			}

			$this->set_response(array(
				'status' => 'success',
				'message' => 'Translation deleted'
			), REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * POST /api/codelists/translation_delete/{translation_id} — same as codelist_translation_delete_post().
	 */
	function translation_delete_post($translation_id = null)
	{
		$this->codelist_translation_delete_post($translation_id);
	}

	/**
	 * DELETE /api/codelists/codelist_translation_delete/{translation_id} — delegates to codelist_translation_delete_post().
	 * Prefer POST when DELETE is blocked.
	 */
	function codelist_translation_delete_delete($translation_id = null)
	{
		$this->codelist_translation_delete_post($translation_id);
	}

	/**
	 * Export a single codelist as NADA-compatible JSON (seed / import_json shape).
	 *
	 * GET /api/codelists/export_json/{id}
	 * Query: download=1 — Content-Disposition attachment (default for browsers)
	 */
	function export_json_get($id = null)
	{
		try {
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$id) {
				throw new Exception('Codelist ID is required');
			}

			$codelist = $this->Codelists_model->get_by_id($id);
			if (!$codelist) {
				throw new Exception('Codelist not found');
			}

			$doc = $this->Codelists_model->export_nada_json_document((int) $id);

			$download = $this->input->get('download');
			if ($download === null || $download === '') {
				$download = true;
			} else {
				$download = filter_var($download, FILTER_VALIDATE_BOOLEAN);
			}

			if ($download) {
				$safe_id = preg_replace('/[^A-Za-z0-9_\-]/', '_', $codelist['name']);
				$filename = $safe_id . '_' . str_replace('.', '', (string) $codelist['version']) . '.json';
				$json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
				if ($json === false) {
					throw new Exception('Failed to encode JSON');
				}
				header('Content-Type: application/json; charset=UTF-8');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				header('Cache-Control: no-store, no-cache');
				echo $json;
				exit();
			}

			$this->set_response(array(
				'status' => 'success',
				'export' => $doc,
			), REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Import one codelist from NADA-compatible JSON.
	 *
	 * POST /api/codelists/import_json
	 *
	 * Body: JSON object (flat NADA seed shape or { "codelist": {…}, "overwrite": bool, "dry_run": bool }).
	 * Multipart field {@code file} with .json contents is also supported.
	 * Query: dry_run=1, replace=1 (overrides body flags when set).
	 */
	function import_json_post()
	{
		try {
			$this->registry_require_or_die($this->registry_resource, 'import');
			$input = $this->_import_json_read_body();
			if ($input === null || !is_array($input) || empty($input)) {
				throw new Exception('Send JSON body or multipart "file" with a codelist document');
			}

			$dry = $this->_import_bool_param('dry_run', $input, false);
			$replace = $this->_import_bool_param('replace', $input, false);
			if (!$replace) {
				$replace = $this->_import_bool_param('overwrite', $input, false);
			}

			$payload = $input;
			if (isset($input['codelist']) && is_array($input['codelist'])) {
				$payload = $input;
			}

			$userId = $this->get_api_user_id();
			$r = $this->Codelists_model->import_json_codelist($payload, array(
				'dry_run' => $dry,
				'replace_existing' => $replace,
				'created_by' => $userId ? (int) $userId : null,
			));

			$warnings = isset($r['warnings']) ? $r['warnings'] : array();
			if (empty($r['ok'])) {
				$this->set_response(array(
					'status' => 'failed',
					'message' => isset($r['message']) ? $r['message'] : 'Import failed',
					'result' => $r,
					'warnings' => $warnings,
				), REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$overall = 'success';
			if (isset($r['action']) && $r['action'] === 'skipped') {
				$overall = 'success';
			}

			$http = REST_Controller::HTTP_OK;
			if (!$dry && isset($r['action']) && $r['action'] === 'created') {
				$http = REST_Controller::HTTP_OK;
			}

			$this->set_response(array(
				'status' => $overall,
				'dry_run' => $dry,
				'imported' => array($r),
				'warnings' => array_values($warnings),
			), $http);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Export a single codelist as a SDMX-ML Structure message.
	 *
	 * GET /api/codelists/export_sdmx/{id}
	 * Query params:
	 *   version (optional) — '2.1' (default) or '3.0'
	 *
	 * Returns Content-Type: application/xml with a download filename.
	 */
	function export_sdmx_get($id = null)
	{
		try {
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			$codelist = $this->Codelists_model->get_by_id($id);
			if (!$codelist) {
				throw new Exception("Codelist not found");
			}

			$raw_version = $this->input->get('version') ?: '2.1';
			$version = ($raw_version === '3.0') ? '3.0' : '2.1';

			// Load all codes with labels (no pagination for export)
			$codes = $this->Codelists_model->get_codes($id, null, true, null, 0, null);

			// Load codelist-level translations (multilingual name/description)
			$translations = $this->Codelists_model->get_codelist_translations($id);

			$this->load->library('SDMX/SdmxCodelistExporter');
			$xml = $this->sdmxcodelistexporter->export(
				array(
					array(
						'codelist'     => $codelist,
						'translations' => $translations,
						'codes'        => $codes,
					),
				),
				$version
			);

			$safe_id = preg_replace('/[^A-Za-z0-9_\-]/', '_', $codelist['name']);
			$filename = $safe_id . '_' . str_replace('.', '', $version) . '.xml';

			header('Content-Type: application/xml; charset=UTF-8');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Cache-Control: no-store, no-cache');
			echo $xml;
			exit();
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status'  => 'failed',
				'message' => $e->getMessage()
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * Site administrators may set locked/archived or unlock codelists.
	 *
	 * @return bool
	 */
	/**
	 * @return array|null
	 */
	private function _import_json_read_body()
	{
		if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
			$data = file_get_contents($_FILES['file']['tmp_name']);
			if ($data === false || trim($data) === '') {
				return null;
			}
			$j = json_decode($data, true);
			return is_array($j) ? $j : null;
		}
		$raw = $this->input->raw_input_stream;
		if ($raw === null || trim($raw) === '') {
			return null;
		}
		$j = json_decode($raw, true);
		return is_array($j) ? $j : null;
	}

	/**
	 * @param string $name Query param name
	 * @param array  $body JSON body
	 * @param bool   $default
	 * @return bool
	 */
	private function _import_bool_param($name, array $body, $default)
	{
		$q = $this->input->get($name);
		if ($q !== null && $q !== '') {
			return filter_var($q, FILTER_VALIDATE_BOOLEAN);
		}
		if (array_key_exists($name, $body)) {
			return $this->_import_boolish($body[$name], $default);
		}

		return (bool) $default;
	}

	/**
	 * @param mixed $value
	 * @param bool  $default
	 * @return bool
	 */
	private function _import_boolish($value, $default)
	{
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return ((int) $value) === 1;
		}
		if (is_string($value)) {
			$v = strtolower(trim($value));
			if (in_array($v, array('1', 'true', 'yes', 'on'), true)) {
				return true;
			}
			if (in_array($v, array('0', 'false', 'no', 'off'), true)) {
				return false;
			}
		}

		return (bool) $default;
	}

	private function _codelist_registry_admin()
	{
		return $this->is_registry_admin($this->registry_resource);
	}

	/**
	 * Enforce content mutability and status transition rules on update payloads.
	 *
	 * @param array $existing
	 * @param array $data Passed by reference; may unset fields
	 * @throws Exception
	 */
	private function _apply_codelist_update_rules(array $existing, array &$data)
	{
		$current_status = $this->Codelists_model->normalize_status(isset($existing['status']) ? $existing['status'] : null);
		$new_status = isset($data['status'])
			? $this->Codelists_model->normalize_status($data['status'])
			: $current_status;

		$content_fields = array('name', 'description', 'uri', 'changed_at', 'changed_by', 'created_at', 'created_by');
		$has_content_change = false;
		foreach ($content_fields as $field) {
			if (array_key_exists($field, $data)) {
				$has_content_change = true;
				break;
			}
		}

		if (isset($data['status']) && !$this->Codelists_model->is_status_transition_allowed($current_status, $new_status, $this->_codelist_registry_admin())) {
			throw new Exception('You are not allowed to change codelist status from ' . $current_status . ' to ' . $new_status);
		}

		if (!$this->Codelists_model->is_content_mutable($existing)) {
			if ($has_content_change) {
				throw new Exception('Codelist is ' . $current_status . ' and cannot be edited');
			}
			if (!isset($data['status']) || $new_status === $current_status) {
				throw new Exception('Codelist is ' . $current_status . ' and cannot be modified');
			}
			// Status-only unlock/archive for admins.
			$data = array('status' => $new_status);
			return;
		}

		if (isset($data['status'])
			&& !$this->_codelist_registry_admin()
			&& !in_array($new_status, array('draft', 'active'), true)) {
			throw new Exception('Only administrators can set locked or archived status');
		}
	}

	/**
	 * @param string $code
	 * @return bool
	 */
	private function _codelists_iso_language_valid($code)
	{
		$this->load->config('iso_languages');
		$iso = $this->config->item('iso_languages');
		return is_array($iso) && array_key_exists($code, $iso);
	}

	/**
	 * Legacy endpoint: content_hash column removed from codelists. No-op success for existing clients.
	 *
	 * POST /api/codelists/{id}/update-hash
	 */
	function update_hash_post($id = null)
	{
		try{
			$this->registry_require_or_die($this->registry_resource, 'browse');
			if (!$id) {
				throw new Exception("Codelist ID is required");
			}

			$codelist = $this->Codelists_model->get_by_id($id);
			if (!$codelist) {
				throw new Exception("Codelist not found");
			}

			$this->set_response(array(
				'status' => 'success',
				'message' => 'Content hash is not stored in this schema version'
			), REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output = array(
				'status' => 'failed',
				'message' => $e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}
}
