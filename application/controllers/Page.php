<?php
class Page extends MY_Controller {
 
    public function __construct()
    {
        parent::__construct($skip_auth=TRUE);
		$this->lang->load('general');
		//$this->output->enable_profiler(TRUE);
		
		//set template for print
		if ($this->input->get("print")==='yes'){
			$this->template->set_template('blank');
		}
    }
    
	function index()
	{	
		$data="page text";
		
		$content=$this->load->view('homepage', $data,true);
		$this->template->write('title', "Metadata editor",true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}
	

}
/* End of file page.php */
/* Location: ./controllers/page.php */