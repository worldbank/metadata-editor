<?php
/**
 * User settings. Catalog registry lives at /settings/catalogs.
 */
class Settings extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('Editor_acl');
		$this->lang->load('general');
		$this->lang->load('project');
	}

	public function index()
	{
		redirect('settings/catalogs');
	}

	public function catalogs()
	{
		$this->editor_acl->has_access_or_die($resource_ = 'editor', $privilege = 'view');
		$options = array(
			'translations' => $this->lang->language
		);
		echo $this->load->view('settings/index', $options, true);
	}
}
