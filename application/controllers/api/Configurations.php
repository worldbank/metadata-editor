<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Configurations extends MY_REST_Controller
{
	public function __construct()
	{
        parent::__construct();
        $this->load->model("Facet_model");
		$this->is_admin_or_die();
    }
    
    //override authentication to support both session authentication + api keys
    function _auth_override_check()
    {
        //session user id
        if ($this->session->userdata('user_id'))
        {
            //var_dump($this->session->userdata('user_id'));
            return true;
        }

        parent::_auth_override_check();
    }


    



}