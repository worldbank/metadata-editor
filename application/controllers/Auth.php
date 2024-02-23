<?php

class Auth extends MY_Controller {

    function __construct()
    {
		parent::__construct($skip_auth=TRUE);
		
		$this->config->load('auth');
        $this->load->library('Nada_csrf');
        $this->load->library('ion_auth');
        $this->load->library('session');
        $this->load->library('form_validation');
        $this->load->helper('url');

    	$this->template->set_template('default');
    	//$this->template->write('sidebar', $this->_menu(),true);

		$this->lang->load('general');
		$this->lang->load('users');

		$this->load->driver('captcha_lib');
		//$this->output->enable_profiler(TRUE);
	}
	

	/**
	 * 
	 * Load authentication class from config
	 * 
	 */
	private function load_auth_driver()
	{
		$driver=$this->config->item('authentication_driver');
		$drivers_list=$this->config->item('authentication_drivers');

		$driver_classpath=$drivers_list[$driver];
		
		if (!file_exists($driver_classpath)){
			show_error("Class not found:: ".$driver_classpath);
		}

		try{
			require_once $driver_classpath;
			$this->auth=new $driver;
			return $this->auth;
		}
		catch(Error $e){
			show_error( $e->getMessage());
		}
	}


	function _remap($method)
	{				
		$auth=$this->load_auth_driver();

        if (in_array(strtolower($method), array_map('strtolower', get_class_methods($auth))))
		{
            $uri = $this->uri->segment_array();
            unset($uri[1]);
            unset($uri[2]);

            call_user_func_array(array($auth, $method), $uri);
        }
        else {
			show_404();
		}
	}


	/**
	*
	* validate CSRF token
	*
	*/
	function validate_token()
	{		
		if (!$this->nada_csrf->validate_token())
		{
			$this->form_validation->set_message('validate_token', t('form_already_saved'));
			return FALSE;
		}

		return TRUE;
	}

	/**
	*
	* validate captcha
	*
	*/
	function validate_captcha()
	{
		$output=$this->captcha_lib->check_answer();

		if ($output===FALSE){
			$this->form_validation->set_message('validate_captcha', t('invalid_captcha'));
		}

		return $output;
	}

	
    
}//end-class
