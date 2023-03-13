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
	}

	function index()
	{
		$this->template->set_template('default');
		echo $this->load->view('collections/index',$options=array(),true);
	}


	
}
/* End of file collections.php */
/* Location: ./controllers/collections.php */
