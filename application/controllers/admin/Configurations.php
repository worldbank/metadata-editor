<?php
class Configurations extends MY_Controller {

	function __construct()
	{
		parent::__construct();
		
		$this->load->helper(array('form', 'url'));		
		$this->load->library( array('form_validation','pagination') );
       	$this->load->model('Configurations_model');		
		$this->template->set_template('admin5');
		
		$this->lang->load("general");
		$this->lang->load("configurations");
		
		//initialize db with default config values		
		$this->_init_default_configs();
		
		//$this->output->enable_profiler(TRUE);

		$this->acl_manager->has_access_or_die('configurations', 'edit');
	}
	
	private function _skip_field($field) {
		return form_error($field) !== '';
	}
	
	function index()
	{	
		$this->form_validation->set_rules('catalog_root', t('catalog_folder'), 'xss_clean|trim|max_length[255]');
		$this->form_validation->set_rules('website_title', t('website_title'), 'xss_clean|trim|max_length[255]');
		$this->form_validation->set_rules('language', t('language'), 'xss_clean|trim|max_length[255]');
			
		$settings=NULL;
		if ($this->form_validation->run() === TRUE){
			$this->update();
			$settings=$this->Configurations_model->get_config_array();
		}
		else
		{
			if ($this->input->post("submit")!==false)
			{			
				// changed:
				// Do the same as if all validation returned true, to prevent possibly deleted data
				// HOWEVER: erroneous fields will NOT be saved
				$check_if_failed = array(
					'website_title',
					'language',
				);
				
				// Check, and unset if failed validation test
				foreach($check_if_failed as $test) {
					if ($this->_skip_field($test)) {
						if (isset($_POST[$test])) {
							unset($_POST[$test]);
						}
					}
				}
				$settings=$this->Configurations_model->get_config_array();	
			}
			else
			{
				$settings=$this->Configurations_model->get_config_array();//array('title','url','html_folder');
			}	
		}

		$content=$this->load->view('site_configurations/index', $settings,true);				
		$this->template->write('content', $content,true);
		$this->template->write('title', t('Site configurations'),true);
	  	$this->template->render();	
	}
	
	function update()
	{
		$post=$_POST;
		$options=array();

		foreach($post as $key=>$value)
		{
			$value=$this->security->xss_clean($value);

			if ($key=='language')
			{
				//if language folder exists
				if (file_exists(APPPATH.'language/'.$value))
				{
					$options[$key]=$value;
				}				
			}
			else
			{
				$options[$key]=$value;
			}
		}
		
		$result=$this->Configurations_model->update($options);

		if ($result)
		{
			$this->message= t('form_update_success');
		}
		else
		{
			$this->form_validation->set_error(t('form_update_fail'));
		}
	}
	
	/**
	*
	* Callback function to check if folder exists
	*/
	function check_folder_exists($folder=NULL)
	{
		if (!is_dir($folder))
		{
			$this->form_validation->set_message("check_folder_exists","Folder specified for <b>%s</b> [$folder] was not found.");
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}


	/*
	*
	* Add missing configuration values to DB
	*
	*/
	function _init_default_configs()
	{
		//get a list of configurations key/values
		$config_defaults=APPPATH.'/config/config.defaults.php';
		
		if (file_exists($config_defaults))
		{
				include $config_defaults;
		}
		else
		{
			return FALSE;
		}
		
		if (is_array($config) && count($config) >0)
		{
			//load settings from db
			$settings=$this->Configurations_model->get_config_array();
			
			foreach($config as $key=>$value)
			{
				//Config not found in db
				if (!array_key_exists($key,$settings))
				{
					//add configuration to db
					$this->Configurations_model->add($key, $value);
				}				
			}
		}
	}
	
	/**
	*
	* Print all configurations
	**/
	function export()
	{
		//load settings from db
		$settings=$this->Configurations_model->get_config_array();
		
		foreach($settings as $key=>$value)
		{
			echo "<b>\$config['$key']</b>= $value;<BR>";
		}
	}
	
	function increment_js_css_ver()
	{
		$options=array();
		$options['js_css_version']=date("U");		
		$result=$this->Configurations_model->update($options);
		var_dump($result);
	}


	/**
	 * 
	 * Test email configurations form
	 * 
	 */
	function test_email()
	{
		$this->config->load('email');

		$email_config=array(
			'smtp_host'=>$this->config->item('smtp_host'),
			'smtp_auth'=>$this->config->item('smtp_auth'),
			'smtp_crypto'=>$this->config->item('smtp_crypto'),
			'smtp_user'=>$this->config->item('smtp_user'),
			'mail_from'=>$this->config->item('smtp_user'),
			'smtp_pass'=>'',
			'smtp_port'=>$this->config->item('smtp_port'),
			'useragent'=>$this->config->item('useragent')
		);

		$content=$this->load->view('site_configurations/test_email', $email_config,true);
		$this->template->write('content', $content,true);
		$this->template->write('title', t('Site configurations'),true);
	  	$this->template->render();	
	}

	/**
	 * 
	 * Send test email
	 * 
	 * @input = $_POST
	 * 
	 */
	function send_test_email()
	{	
		$this->config->load('email');
		$this->load->library('email');		

		$config = Array(
			'protocol'  => 'smtp',
			'useragent' =>$this->input->post('useragent'),
			'smtp_host' => $this->input->post('smtp_host'),
			'smtp_port' => $this->input->post('smtp_port'),
			'smtp_user' => $this->input->post('smtp_user'),
			'smtp_pass' => $this->input->post('smtp_pass'),
			'mailtype'  => 'html',
			'smtp_debug'  => 2,
			'smtp_auth' =>$this->input->post('smtp_auth'),
			'smtp_crypto' =>$this->input->post('smtp_crypto'),
		);

		//password
		if($config['smtp_pass']==''){
			//use password from the config file
			$config['smtp_pass']=$this->config->item("smtp_pass");
		}

		//mail from
		$email_sender=$this->input->post("mail_from");

		if(empty($email_sender)){
			$email_sender=$this->input->post('smtp_user');
		}

		$this->email->initialize($config);		
		$this->email->from($email_sender);
		$this->email->to($this->input->post('mail_to'));		
		$this->email->subject('NADA test email');
		$this->email->message('NADA test email message body');
		$this->email->send();
		echo $this->email->print_debugger();
	}
	
}

/* End of file configurations.php */
/* Location: ./system/application/controllers/configurations.php */