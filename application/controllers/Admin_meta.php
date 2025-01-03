<?php
/**
 * 
 * Admin metadata
 *
 */
class Admin_meta extends MY_Controller {

  	public function __construct()
	{
      	parent::__construct();
		$this->load->model('Editor_model');
		$this->load->model('Editor_template_model');
		$this->load->library("Editor_acl");
		$this->lang->load("users");
		$this->lang->load("general");
		$this->lang->load("template_manager");		
	}

	function index()
	{
		$this->editor_acl->has_access_or_die($resource_='editor',$privilege='view');
		$this->lang->load("project");
		$this->template->set_template('default');
		$options['translations']=$this->lang->language;
		echo $this->load->view('admin_meta/index',$options,true);

		//$this->template->write('content', $content,true);
		//$this->template->render();
	}


	
}
/* End of file Admin_meta.php */
/* Location: ./controllers/Admin_meta.php */
