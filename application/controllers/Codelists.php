<?php
/**
 * Global Codelists Controller
 *
 * Manages global codelists pages
 *
 */
class Codelists extends MY_Controller {

  	public function __construct()
	{
      	parent::__construct();
		$this->load->model('Codelists_model');
		$this->load->library("Editor_acl");
		$this->lang->load("users");
		$this->lang->load("general");
	}

	/**
	 * 
	 * Codelists listing page (vue-router handles all routing)
	 * 
	 */
	function index()
	{
		$this->editor_acl->has_access_or_die('codelist', 'view');
		$this->template->set_template('default');
		$options['translations']=$this->lang->language;
		$this->load->config('iso_languages');
		$options['iso_languages'] = $this->config->item('iso_languages');
		if (!is_array($options['iso_languages'])) {
			$options['iso_languages'] = array();
		}
		echo $this->load->view('codelists/index',$options,true);
	}

	/**
	 * 
	 * Edit route - vue-router handles the routing, this just loads the same page
	 * 
	 */
	function edit($id = null)
	{
		// Same as index - vue-router handles the routing
		$this->index();
	}
}

/* End of file Codelists.php */
/* Location: ./application/controllers/Codelists.php */
