<?php
/**
 * Metadata editor
 *
 *
 */
class Collections extends MY_Controller {

  	public function __construct()
	{
      	parent::__construct();
		$this->load->model('Collection_model');
		$this->load->library("Editor_acl");
	}

	function index()
	{
		$this->editor_acl->has_access_or_die($resource_='collection',$privilege='view');
		$this->template->set_template('default');
		echo $this->load->view('collections/index',$options=array(),true);
	}


	
}
/* End of file collections.php */
/* Location: ./controllers/collections.php */
