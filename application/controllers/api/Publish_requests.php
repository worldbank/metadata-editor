<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH . '/libraries/MY_REST_Controller.php');

/**
 * Publish request lifecycle: owner submit, curator queue, resolve, history.
 *
 * Base path: /api/publish_requests (alias: /api/publish-requests)
 * Catalog push remains on /api/publish.
 */
class Publish_requests extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Project_publications_model');
		$this->load->library('Editor_acl');
		$this->is_authenticated_or_die();
	}

	public function _auth_override_check()
	{
		if ($this->session->userdata('user_id')) {
			return true;
		}
		parent::_auth_override_check();
	}

	/**
	 * GET /api/publish_requests/project/{sid}
	 */
	public function project_get($sid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'view');
			$this->require_publications_table();

			$publications = $this->Project_publications_model->list_by_project($sid);
			$this->set_response(array(
				'status' => 'success',
				'publications' => $publications,
				'summary' => $this->Project_publications_model->derive_summary($publications),
			), REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * GET /api/publish_requests/project/{sid}/context
	 */
	public function project_context_get($sid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit');
			$this->require_publications_table();

			$this->load->library('Project_publish_ready');
			$context = $this->project_publish_ready->ready_context($sid);
			$this->set_response(array(
				'status' => 'success',
			) + $context, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * GET /api/publish_requests/project/{sid}/history
	 */
	public function project_history_get($sid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit');
			$this->require_publications_table();

			$user_id = (int) $this->get_api_user_id();
			$is_admin = $this->editor_acl->user_is_admin($this->ion_auth->get_user($user_id));
			$result = $this->Project_publications_model->list_events_by_project($sid, array(
				'limit' => $this->input->get('limit'),
				'offset' => $this->input->get('offset'),
				'events' => $this->Project_publications_model->publication_history_event_types(),
				'user_id' => $user_id,
				'is_admin' => $is_admin,
			));

			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * DELETE /api/publish_requests/project/{sid}/history/{event_id}
	 */
	public function project_history_delete($sid = null, $event_id = null)
	{
		$this->run_project_history_delete($sid, $event_id);
	}

	/**
	 * POST /api/publish_requests/project/{sid}/history/{event_id}/delete
	 * POST alias when DELETE is blocked by infrastructure.
	 */
	public function project_history_delete_post($sid = null, $event_id = null)
	{
		$this->run_project_history_delete($sid, $event_id);
	}

	/**
	 * PUT /api/publish_requests/project/{sid}
	 */
	public function project_put($sid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit');
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$payload = $this->raw_json_input();
			if (!is_array($payload)) {
				$payload = array();
			}

			$result = $this->Project_publications_model->mark_ready($sid, $user_id, $payload);
			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * DELETE /api/publish_requests/project/{sid}
	 */
	public function project_delete($sid = null)
	{
		$this->run_project_clear($sid);
	}

	/**
	 * POST /api/publish_requests/project/{sid}/clear
	 * POST alias when DELETE is blocked by infrastructure.
	 */
	public function project_clear_post($sid = null)
	{
		$this->run_project_clear($sid);
	}

	/**
	 * POST /api/publish_requests/project/{sid}/submit
	 */
	public function project_submit_post($sid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit');
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$payload = $this->raw_json_input();
			if (!is_array($payload)) {
				$payload = array();
			}

			$result = $this->Project_publications_model->submit_for_publishing($sid, $user_id, $payload);
			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Publish_submit_validation_exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
				'details' => $e->getDetails(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * POST /api/publish_requests/project/{sid}/withdraw
	 */
	public function project_withdraw_post($sid = null)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit');
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$payload = $this->raw_json_input();
			$catalog_ids = null;
			if (is_array($payload) && isset($payload['catalog_ids'])) {
				$catalog_ids = $payload['catalog_ids'];
			}

			$result = $this->Project_publications_model->withdraw_requests($sid, $user_id, $catalog_ids);
			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * GET /api/publish_requests/intake_form
	 */
	public function intake_form_get()
	{
		try {
			$this->require_user_id();
			$this->load->helper('publish_intake');
			$form = publish_intake_form_load();

			$this->set_response(array(
				'status' => 'success',
				'intake_form' => $form,
			), REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * GET /api/publish_requests/queue
	 */
	public function queue_get()
	{
		try {
			$this->require_publish_queue_access();

			$this->load->library('Project_publish_ready');
			$result = $this->project_publish_ready->ready_queue(array(
				'catalog_id' => $this->int_query('catalog_id', 0),
				'limit' => $this->int_query('limit', 50),
				'offset' => $this->int_query('offset', 0),
			));

			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * GET /api/publish_requests/queue/history
	 */
	public function queue_history_get()
	{
		try {
			$this->require_publish_queue_access();

			$this->load->library('Project_publish_ready');
			$result = $this->project_publish_ready->queue_history(array(
				'catalog_id' => $this->int_query('catalog_id', 0),
				'limit' => $this->int_query('limit', 50),
				'offset' => $this->int_query('offset', 0),
			));

			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * GET /api/publish_requests/{publication_id}
	 */
	public function placement_get($publication_id = null)
	{
		try {
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$publication_id = (int) $publication_id;
			if ($publication_id < 1) {
				throw new Exception('Publication id is required');
			}

			$placement = $this->Project_publications_model->get_for_publish_prefill($publication_id);
			$sid = (int) $placement['sid'];

			try {
				$this->editor_acl->user_has_project_access($sid, $permission = 'view');
			}
			catch (Exception $e) {
				if (empty($placement['ready_at']) || !$this->user_has_publish_queue_access($user_id)) {
					throw new Exception('Access denied');
				}
			}

			$this->set_response(array(
				'status' => 'success',
			) + $placement, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * POST /api/publish_requests/{publication_id}/resolve
	 */
	public function resolve_post($publication_id = null)
	{
		try {
			$this->require_publish_queue_access();
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$publication_id = (int) $publication_id;
			if ($publication_id < 1) {
				throw new Exception('Publication id is required');
			}

			$payload = $this->raw_json_input();
			if (!is_array($payload)) {
				$payload = array();
			}

			$action = isset($payload['action']) ? $payload['action'] : '';
			if ($action === '' && isset($payload['status'])) {
				$action = $payload['status'];
			}

			$this->load->library('Project_publish_ready');
			$result = $this->project_publish_ready->resolve_ready($publication_id, $user_id, $action, $payload);
			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * POST /api/publish_requests/resolve_batch
	 */
	public function resolve_batch_post()
	{
		try {
			$this->require_publish_queue_access();
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$payload = $this->raw_json_input();
			if (!is_array($payload)) {
				$payload = array();
			}

			$publication_ids = isset($payload['publication_ids']) ? $payload['publication_ids'] : array();
			if (!is_array($publication_ids)) {
				throw new Exception('publication_ids must be an array');
			}

			$action = isset($payload['action']) ? $payload['action'] : '';
			if ($action === '' && isset($payload['status'])) {
				$action = $payload['status'];
			}

			$this->load->library('Project_publish_ready');
			$result = $this->project_publish_ready->resolve_ready_batch($user_id, $publication_ids, $action, $payload);
			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * @param int|string|null $sid
	 * @param int|string|null $event_id
	 */
	private function run_project_history_delete($sid, $event_id)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit');
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$is_admin = $this->editor_acl->user_is_admin($this->ion_auth->get_user($user_id));
			$result = $this->Project_publications_model->delete_published_event($event_id, $sid, $user_id, $is_admin);
			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	/**
	 * @param int|string|null $sid
	 */
	private function run_project_clear($sid)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission = 'edit');
			$user_id = $this->require_user_id();
			$this->require_publications_table();

			$payload = $this->raw_json_input();
			$catalog_ids = null;
			if (is_array($payload) && isset($payload['catalog_ids'])) {
				$catalog_ids = $payload['catalog_ids'];
			}

			$this->load->library('Project_publish_ready');
			$result = $this->project_publish_ready->clear_ready($sid, $user_id, $catalog_ids);
			$this->set_response(array(
				'status' => 'success',
			) + $result, REST_Controller::HTTP_OK);
		}
		catch (Exception $e) {
			$this->respond_failed($e);
		}
	}

	private function require_publications_table()
	{
		if (!$this->db->table_exists('project_publications')) {
			throw new Exception('Publication ledger is not available');
		}
	}

	/**
	 * @return int
	 */
	private function require_user_id()
	{
		$user_id = (int) $this->get_api_user_id();
		if ($user_id < 1) {
			throw new Exception('User-login-required');
		}
		return $user_id;
	}

	/**
	 * @param string $key
	 * @param int $default
	 * @return int
	 */
	private function int_query($key, $default)
	{
		$value = $this->input->get($key);
		if ($value === null || $value === '') {
			return (int) $default;
		}
		return (int) $value;
	}

	private function respond_failed(Exception $e)
	{
		$this->set_response(array(
			'status' => 'failed',
			'message' => $e->getMessage(),
		), REST_Controller::HTTP_BAD_REQUEST);
	}

	private function user_has_publish_queue_access($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return false;
		}
		if ($this->ion_auth->is_admin($user_id)) {
			return true;
		}
		return $this->editor_acl->user_has_global_project_access($user_id, 'view');
	}

	private function require_publish_queue_access()
	{
		$user_id = $this->require_user_id();
		if (!$this->user_has_publish_queue_access($user_id)) {
			throw new Exception('Access denied');
		}
	}
}
