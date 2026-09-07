<?php
/**
 * Curator publishing hub: queue and global history.
 */
class Publish_queue extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('Editor_acl');
		$this->lang->load('general');
	}

	public function index()
	{
		$this->editor_acl->has_access_or_die($resource_ = 'editor', $privilege = 'view');

		$user_id = $this->session->userdata('user_id');
		if (!$this->ion_auth->is_admin($user_id)
			&& !$this->editor_acl->user_has_global_project_access($user_id, 'view')) {
			show_error('Access denied', 403);
		}

		$options = array(
			'translations' => $this->lang->language,
		);
		echo $this->load->view('publish_queue/index', $options, true);
	}
}
