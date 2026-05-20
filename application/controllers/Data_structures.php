<?php
/**
 * Global data structures (DSD registry) pages.
 */
class Data_structures extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Editor_acl');
        $this->lang->load('general');
    }

    function index()
    {
        $this->editor_acl->has_access_or_die('data_structure', 'view');
        $this->template->set_template('default');
        $options = array('translations' => $this->lang->language);
        echo $this->load->view('data_structures/index', $options, true);
    }

    function edit($id = null)
    {
        $this->index();
    }
}
