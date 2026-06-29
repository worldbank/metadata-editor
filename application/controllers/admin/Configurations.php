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
		$this->config->load('iso_languages');
		
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
		$this->form_validation->set_rules('language', t('language'), 'xss_clean|trim|max_length[255]');
			
		$settings=NULL;
		if ($this->form_validation->run() === TRUE){
			$this->update();
			if (!empty($this->message)) {
				$this->session->set_flashdata('message', $this->message);
			}
			redirect('admin/configurations');
		}
		else
		{
			if ($this->input->post("submit")!==false)
			{			
				// changed:
				// Do the same as if all validation returned true, to prevent possibly deleted data
				// HOWEVER: erroneous fields will NOT be saved
				$check_if_failed = array(
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

		// Language: pass ISO list, discovered folders, and current DB mapping to view
		$settings['iso_languages']     = $this->config->item('iso_languages');
		$settings['available_folders'] = $this->_get_language_folders();

		$raw_sl       = isset($settings['supported_languages']) ? $settings['supported_languages'] : null;
		$lang_mapping = array();
		if (!empty($raw_sl)) {
			$decoded_sl = is_string($raw_sl) ? json_decode($raw_sl, true) : (array)$raw_sl;
			if (is_array($decoded_sl)) {
				foreach ($decoded_sl as $entry) {
					$entry = is_array($entry) ? $entry : (array)$entry;
					if (isset($entry['folder'])) {
						$lang_mapping[$entry['folder']] = $entry;
					} elseif (is_string($entry)) {
						// Old flat-string format fallback
						$lang_mapping[$entry] = array('folder' => $entry, 'code' => '', 'display' => ucfirst($entry), 'direction' => 'ltr');
					}
				}
			}
		}
		if (!isset($lang_mapping['english'])) {
			$lang_mapping['english'] = array('folder' => 'english', 'code' => 'en', 'display' => 'English', 'direction' => 'ltr');
		}
		$settings['lang_mapping'] = $lang_mapping;
		unset($settings['supported_languages'], $settings['language_codes']);

		// Editor user access (stored in configurations table)
		$settings['grant_editor_default'] = default_editor_role_enabled();
		$settings['project_sharing_enabled'] = project_sharing_enabled();

		// Add editor config values (read-only, from config/editor.php)
		$this->config->load('editor');
		$editor_config = $this->config->item('editor');
		if (is_array($editor_config)) {
			$settings['editor_storage_path']     = isset($editor_config['storage_path'])     ? $editor_config['storage_path']     : '';
			$settings['editor_user_schema_path'] = isset($editor_config['user_schema_path']) ? $editor_config['user_schema_path'] : '';
			$settings['editor_data_api_url']     = isset($editor_config['data_api_url'])     ? $editor_config['data_api_url']     : '';
		}

		// Add analytics config values (read-only, from config/analytics.php)
		$this->config->load('analytics', FALSE, TRUE);
		$settings['analytics_enabled']           = $this->config->item('analytics_enabled') ? true : false;
		$settings['analytics_track_hash_changes'] = $this->config->item('analytics_track_hash_changes') ? true : false;

		$content=$this->load->view('site_configurations/index', $settings,true);				
		$this->template->write('content', $content,true);
		$this->template->write('title', t('Site configurations'),true);
	  	$this->template->render();	
	}
	
	function update()
	{
		$post    = $_POST;
		$options = array();

		// Handle language mapping: lang_enabled[folder] and lang_code[folder] are nested arrays
		$lang_enabled = is_array($this->input->post('lang_enabled')) ? $this->input->post('lang_enabled') : array();
		$lang_code    = is_array($this->input->post('lang_code'))    ? $this->input->post('lang_code')    : array();

		if (!empty($lang_code)) {
			$iso_languages     = $this->config->item('iso_languages');
			$available_folders = $this->_get_language_folders();
			$mapping = array();

			foreach ($available_folders as $folder) {
				if (!isset($lang_enabled[$folder])) continue;
				$code = isset($lang_code[$folder]) ? $this->security->xss_clean($lang_code[$folder]) : '';
				$iso  = !empty($code) && isset($iso_languages[$code]) ? $iso_languages[$code] : null;
				$mapping[] = array(
					'folder'    => $folder,
					'code'      => $code,
					'display'   => $iso ? $iso['display']   : ucfirst($folder),
					'direction' => $iso ? $iso['direction'] : 'ltr',
				);
			}

			// Ensure at least one language is always saved
			if (empty($mapping)) {
				$mapping[] = array('folder' => 'english', 'code' => 'en', 'display' => 'English', 'direction' => 'ltr');
			}

			$options['supported_languages'] = json_encode($mapping);
		}

		// Default roles for new users (Editor toggle only; User is always assigned)
		$this->load->helper('user_access');
		$grant_editor = $this->input->post('grant_editor_default') === '1';
		$default_roles = array('User');
		if ($grant_editor) {
			$default_roles[] = 'Editor';
		}
		$options['default_user_roles'] = json_encode($default_roles);

		// Project sharing toggle
		$options['project_sharing'] = $this->input->post('project_sharing') === '1' ? '1' : '0';

		// Remove nested-array keys so the generic loop below doesn't try to process them
		unset($post['lang_enabled'], $post['lang_code'], $post['submit'], $post['grant_editor_default'], $post['project_sharing']);

		foreach($post as $key=>$value)
		{
			$value = $this->security->xss_clean($value);

			if ($key === 'language')
			{
				// Validate folder exists in app or userdata language directory
				$available = $this->_get_language_folders();
				if (in_array($value, $available)) {
					$options[$key] = $value;
				}
			}
			else
			{
				$options[$key] = $value;
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
	 * Scan disk for available language folders (application/language only).
	 */
	private function _get_language_folders()
	{
		$this->load->library('translator');
		return $this->translator->get_languages_array();
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