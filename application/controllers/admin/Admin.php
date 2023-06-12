<?php
class Admin extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
		$this->template->set_template('admin5'); 
		$this->load->driver('cache', array('adapter' => 'dummy', 'backup' => 'file'));

		$this->lang->load("general");
		$this->lang->load("dashboard");
		//$this->output->enable_profiler(TRUE);
    }

	function index()
	{
		$this->load->helper('date_helper');
		$data['title']=t('Dashboard');
		$content="...";
		$this->template->write('title', $data['title'],TRUE);
		$this->template->write('content', $content,TRUE);
	  	$this->template->render();
	}


	

}
/* End of file admin.php */
/* Location: ./controllers/admin/admin.php */
