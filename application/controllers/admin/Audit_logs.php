<?php
class Audit_logs extends MY_Controller {

	function __construct()
	{
		parent::__construct();
		
		$this->load->helper(array('form', 'url'));		
		$this->load->library( array('form_validation','pagination') );
		
		//language files
		$this->lang->load('general');
		$this->lang->load('audit_logs');
		
		//set template to admin
		$this->template->set_template('admin5');
		
		//$this->output->enable_profiler(TRUE);
		$this->disable_page_cache();
	}
	
	//expire page immediately
    private function disable_page_cache()
    {	
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
    }
	
	function index()
	{
		$this->acl_manager->has_access_or_die('audit_logs', 'view');

		$data['title'] = t('audit_logs');
		$content = $this->load->view('admin/audit_logs/index', $data, true);
		
		$this->template->write('content', $content, true);
		$this->template->write('title', $data['title'], true);
	  	$this->template->render();	
	}
}
