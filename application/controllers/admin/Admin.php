<?php
class Admin extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
		$this->template->set_template('admin5'); 
		$this->lang->load("general");
		$this->lang->load("dashboard");
		$this->load->library("Editor_acl");
		//$this->output->enable_profiler(TRUE);
    }

	function index()
	{
		$this->editor_acl->has_access_or_die($resource_='admin_dashboard',$privilege='view');
		$data['title']=t('Dashboard');
		$content=$this->load->view('admin/dashboard/index', $data, true);
		$this->template->write('title', $data['title'],TRUE);
		$this->template->write('content', $content,TRUE);
	  	$this->template->render();
	}


	

}
/* End of file admin.php */
/* Location: ./controllers/admin/admin.php */
