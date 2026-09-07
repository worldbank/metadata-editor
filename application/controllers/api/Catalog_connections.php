<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Catalog_connections extends MY_REST_Controller
{
	private $api_user;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->helper("catalog");
		$this->load->model("Catalog_connections_model");
		$this->load->library("Editor_acl");
		$this->load->model("Audit_log_model");
		$this->load->library("Audit_log");
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
		$this->api_user_id=$this->get_api_user_id();
	}

	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}

	/**
	 * 
	 * List catalog connections by current logged-in user
	 * 
	 */
	function index_get()
	{
		try{
			$connections=$this->Catalog_connections_model->get_connections($this->api_user_id);
			$can_manage_official=$this->can_manage_official_catalogs();
			
			$response=array(
				'status'=>'success',
				'connections'=>$connections,
				'is_admin'=>$this->is_admin(),
				'can_manage_official'=>$can_manage_official,
				'current_user_id'=>$this->api_user_id
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

	/**
	 * 
	 * Create new catalog connection
	 * 
	 */
	function index_post()
	{
		try{
			$options=$this->raw_json_input();
			$options['user_id']=$this->api_user_id;

			$result=$this->Catalog_connections_model->create($options, $this->can_manage_official_catalogs());
			
			$response=array(
				'status'=>$result
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

	/**
	 * 
	 * Update catalog connection
	 * 
	 */
	function update_post()
	{
		try{
			$options=$this->raw_json_input();
			$options['user_id']=$this->api_user_id;

			// Validate that catalog connection exists and belongs to user
			if (!isset($options['id'])){
				throw new Exception("Catalog ID is required");
			}

			$result=$this->Catalog_connections_model->update($options['id'],$options,$this->can_manage_official_catalogs());
			
			$response=array(
				'status'=>$result
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

	/**
	 * 
	 * Delete catalog connection
	 * 
	 */
	function delete_post()
	{
		try{
			$catalog_id=$this->input->post('catalog_id');

			if (!isset($catalog_id)){
				throw new Exception("Catalog ID is required");
			}

			$delete_catalog = $this->input->post('delete_catalog');
			$delete_catalog = $delete_catalog === '1' || $delete_catalog === 1 || $delete_catalog === true || $delete_catalog === 'true';

			$result=$this->Catalog_connections_model->delete($catalog_id, $this->api_user_id, $delete_catalog, $this->can_manage_official_catalogs());
			
			$response=array(
				'status'=>$result
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

	/**
	 * 
	 * Get single catalog connection by ID
	 * 
	 */
	function single_get($id=null)
	{
		try{
			if (!$id){
				throw new Exception("Catalog ID is required");
			}

			$connection=$this->Catalog_connections_model->get_connection($this->api_user_id, $id);
			
			if (!$connection){
				throw new Exception("Catalog connection not found");
			}

			unset($connection['api_key']);

			$response=array(
				'status'=>'success',
				'connection'=>$connection
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

	/**
	 * GET /api/catalog_connections/publish_form/{catalog_id}
	 *
	 * Shipped publish form for the catalog's type (Submit UI and validation).
	 */
	function publish_form_get($id = null)
	{
		try {
			if (!$id) {
				throw new Exception('Catalog ID is required');
			}

			$connection = $this->Catalog_connections_model->get_connection($this->api_user_id, $id);
			if (!$connection) {
				throw new Exception('Catalog connection not found');
			}

			$this->load->helper('catalog_publish_options');
			$type = isset($connection['type']) ? $connection['type'] : 'nada';
			$form = catalog_publish_form_load($type);
			if ($form === null) {
				throw new Exception('Publish form is not available for this catalog type');
			}

			unset($connection['api_key']);

			$this->set_response(array(
				'status' => 'success',
				'catalog_type' => $type,
				'connection' => $connection,
				'publish_form' => $form,
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
	 * GET /api/catalog_connections/curators/{catalog_id}
	 */
	function curators_get($id = null)
	{
		try {
			$this->require_manage_official_catalogs();
			$catalog = $this->require_shared_catalog($id);
			$curators = $this->Catalog_connections_model->get_curators((int) $catalog['id']);

			$this->set_response(array(
				'status' => 'success',
				'catalog_id' => (int) $catalog['id'],
				'curators' => $curators,
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
	 * POST /api/catalog_connections/curators/{catalog_id}
	 */
	function curators_post($id = null)
	{
		try {
			$this->require_manage_official_catalogs();
			$catalog = $this->require_shared_catalog($id);
			$options = $this->raw_json_input();
			$user_id = isset($options['user_id']) ? (int) $options['user_id'] : 0;
			$this->Catalog_connections_model->add_curator((int) $catalog['id'], $user_id);

			$this->set_response(array(
				'status' => 'success',
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
	 * POST /api/catalog_connections/remove_curator
	 */
	function remove_curator_post()
	{
		try {
			$this->require_manage_official_catalogs();
			$options = $this->raw_json_input();
			if (!is_array($options) || empty($options)) {
				$options = $this->input->post();
			}
			$catalog_id = isset($options['catalog_id']) ? (int) $options['catalog_id'] : 0;
			$user_id = isset($options['user_id']) ? (int) $options['user_id'] : 0;
			$this->require_shared_catalog($catalog_id);
			$this->Catalog_connections_model->remove_curator($catalog_id, $user_id);

			$this->set_response(array(
				'status' => 'success',
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
	 * GET /api/catalog_connections/search_users?keywords=
	 */
	function search_users_get()
	{
		try {
			$this->require_manage_official_catalogs();
			$keywords = $this->get('keywords');
			if (!$keywords) {
				throw new Exception('Missing parameter for `keywords`');
			}

			$this->load->model('Editor_owners_model');
			$users = $this->Editor_owners_model->search_users($keywords);

			$this->set_response(array(
				'status' => 'success',
				'total' => count($users),
				'users' => $users,
			), REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	private function can_manage_official_catalogs()
	{
		return $this->editor_acl->user_can_manage_official_catalogs($this->api_user);
	}

	private function require_manage_official_catalogs()
	{
		if (!$this->can_manage_official_catalogs()) {
			throw new Exception('Access denied');
		}
	}

	private function require_shared_catalog($id)
	{
		if (!$id) {
			throw new Exception('Catalog ID is required');
		}

		$catalog = $this->Catalog_connections_model->get_catalog($id);
		if (!$catalog) {
			throw new Exception('Catalog connection was not found');
		}
		if (!catalog_is_official(isset($catalog['is_official']) ? $catalog['is_official'] : 0)) {
			throw new Exception('Curators can only be assigned to shared connections');
		}
		return $catalog;
	}
}