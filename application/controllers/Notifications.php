<?php
/**
 * Global in-app notifications inbox.
 */
class Notifications extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('Editor_acl');
		$this->lang->load('general');
	}

	public function index()
	{
		$this->editor_acl->has_access_or_die($resource_ = 'editor', $privilege = 'view');
		$options = array(
			'translations' => $this->lang->language,
		);
		echo $this->load->view('notifications/index', $options, true);
	}
}
