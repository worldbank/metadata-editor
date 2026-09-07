<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH . '/libraries/MY_REST_Controller.php');

/**
 * Current-user in-app inbox.
 */
class Notifications extends MY_REST_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('User_notifications_model');
		$this->load->library('Editor_acl');
		$this->lang->load('general');
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
	 * GET /api/notifications?limit=&offset=&unread=1&type=
	 */
	public function index_get()
	{
		try {
			$user_id = (int) $this->get_api_user_id();
			$identifier = $this->uri->segment(3);
			if ($identifier && ctype_digit((string) $identifier)) {
				$row = $this->User_notifications_model->get_for_user($identifier, $user_id);
				if (!$row) {
					$this->set_response(array(
						'status' => 'failed',
						'message' => 'Notification not found',
					), REST_Controller::HTTP_NOT_FOUND);
					return;
				}
				$this->set_response(array(
					'status' => 'success',
					'notification' => $row,
				), REST_Controller::HTTP_OK);
				return;
			}

			$unread = $this->input->get('unread');
			$result = $this->User_notifications_model->list_for_user($user_id, array(
				'limit' => $this->input->get('limit'),
				'offset' => $this->input->get('offset'),
				'unread' => ($unread === '1' || $unread === 'true'),
				'type' => $this->input->get('type'),
				'family' => $this->input->get('family'),
			));

			$this->set_response(array(
				'status' => 'success',
				'total' => $result['total'],
				'limit' => $result['limit'],
				'offset' => $result['offset'],
				'unread_count' => $this->User_notifications_model->unread_count($user_id),
				'notifications' => $result['rows'],
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * GET /api/notifications/unread_count
	 */
	public function unread_count_get()
	{
		try {
			$user_id = (int) $this->get_api_user_id();
			$this->set_response(array(
				'status' => 'success',
				'unread_count' => $this->User_notifications_model->unread_count($user_id),
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * POST /api/notifications/{id}/read
	 */
	public function read_post($id = null)
	{
		try {
			$user_id = (int) $this->get_api_user_id();
			$row = $this->User_notifications_model->mark_read($id, $user_id);
			if (!$row) {
				$this->set_response(array(
					'status' => 'failed',
					'message' => 'Notification not found',
				), REST_Controller::HTTP_NOT_FOUND);
				return;
			}

			$this->set_response(array(
				'status' => 'success',
				'notification' => $row,
				'unread_count' => $this->User_notifications_model->unread_count($user_id),
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * POST /api/notifications/read_all
	 */
	public function read_all_post()
	{
		try {
			$user_id = (int) $this->get_api_user_id();
			$updated = $this->User_notifications_model->mark_all_read($user_id);
			$this->set_response(array(
				'status' => 'success',
				'updated' => $updated,
				'unread_count' => 0,
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->set_response(array(
				'status' => 'failed',
				'message' => $e->getMessage(),
			), REST_Controller::HTTP_BAD_REQUEST);
		}
	}
}
