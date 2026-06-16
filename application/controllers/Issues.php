<?php
/**
 * Issues management page controller
 */
class Issues extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Editor_acl');
        $this->lang->load('general');
        $this->lang->load('project');
    }

    public function index()
    {
        $this->editor_acl->has_access_or_die($resource_ = 'editor', $privilege = 'view');
        $options = array(
            'translations' => $this->lang->language
        );
        echo $this->load->view('issues/index', $options, true);
    }

    /**
     * Edit a single issue by ID.
     * URL: issues/edit/{id}
     */
    public function edit($id = null)
    {
        $this->editor_acl->has_access_or_die($resource_ = 'editor', $privilege = 'view');
        if (empty($id) || !ctype_digit((string) $id)) {
            show_404();
            return;
        }
        $options = array(
            'translations' => $this->lang->language,
            'issue_id'     => (int) $id
        );
        echo $this->load->view('issues/view', $options, true);
    }
}
