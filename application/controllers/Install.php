<?php

class Install extends CI_Controller {

	function __construct()
	{		
		parent::__construct();

		//get a list of languages
		$this->_load_language_list();

		$language=isset($_COOKIE["nada_language"]) ? $_COOKIE["nada_language"] : 'english';
		$this->config->set_item('language',$language);

		$this->load->database();
		
		//initialize
		$this->load->dbforge();
		
		//database utilities	
		$this->load->dbutil();
		
		$this->template->set_template('installer');

		$this->lang->load("general");
		$this->lang->load("users");
		$this->lang->load("install");
		//$this->output->enable_profiler(TRUE);
	}

	function _load_language_list()
	{
		$languages=scandir(APPPATH.'language/');
		$language_arr=array();
		
		foreach($languages as $lang)
		{
			if ($lang!=='.' && $lang!=='..')
			{
				$language_arr[]=$lang;
			}	
		}
		
		$this->languages=$language_arr;	
	}

	function language($name=NULL)
	{
		$languages = $this->languages;
		if (in_array($name,$languages))
		{
			setcookie('nada_language',$name,0,'/');
		}
		else
		{
			setcookie('nada_language','english',0,'/');
		}
		
		redirect("install");
	}
	
	function index()
	{	
		//test if database is already installed
		$this->is_already_installed();
		
		//test database connectivity
		$data['db_connect']=$this->_test_connection();		

		//database connection successful
		if ($data['db_connect']!==FALSE)
		{
			//database version
			$data['db_version']=$this->db->version();		
		}

		//php version
		$data['php_version']=phpversion();
		
		//test the required extensions
		$data['extensions']=$this->load->view("install/extensions",NULL,TRUE);
		
		//check required extensions
		$data['other_settings']=$this->load->view("install/other_settings",NULL,TRUE);
	
		//test folder rights
		$data['permissions']=$this->load->view("install/file_rw",NULL,TRUE);
						
		$content=$this->load->view('install/index',$data,TRUE);
				
		//render final output		
		$this->template->write('title', t('title_data_catalog'),true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}

	
	function installing($step=NULL)
	{				
		if ($step==NULL)
		{
			//test database connection
			if($this->_test_connection()===FALSE)
			{
				$this->session->set_flashdata('message', t('database_connection_failed'));
				redirect('install');
			}

			//exit if already installed	
			$this->is_already_installed();

			//else, create tables
			redirect ('install/installing/create_tables');
		}		
		else if ($step=='create_tables')
		{		
			//test database connection
			if($this->_test_connection()===FALSE)
			{
				$this->session->set_flashdata('error', t('database_connection_failed'));
				redirect('install');
			}

			//exit if already installed	
			$this->is_already_installed();				
			
			//create tables and add insert data
			$this->_create_tables();			
			$this->session->set_flashdata('message', t('database_tables_created'));
			
			//redirect to admin account registration
			redirect('install/create_user');						
		}			
	}

	
	
	/**
	 * 
	 * Creates Admin user account
	 * 
	 * @return void
	 */
	function create_user() 
	{  				
		//exit if already installed	
		$this->is_already_installed();			
		
        $this->load->library('form_validation');
        $this->data['page_title'] = t("create_admin_account");		
              		
        //validate form input
		$this->form_validation->set_rules('username', t('username'), 'xss_clean|max_length[20]');
    	$this->form_validation->set_rules('email', t('email'), 'max_length[100]|required|valid_email');
    	$this->form_validation->set_rules('first_name', t('first_name'), 'max_length[20]|required|xss_clean');
    	$this->form_validation->set_rules('last_name', t('last_name'), 'max_length[20]|required|xss_clean');
    	$this->form_validation->set_rules('phone1', t('phone'), 'max_length[20]|xss_clean|trim');
    	$this->form_validation->set_rules('company', t('company'), 'max_length[255]|xss_clean');
    	$this->form_validation->set_rules('password', t('password'), 'required|min_length['.$this->config->item('min_password_length').']|max_length['.$this->config->item('max_password_length').']|matches[password_confirm]');
    	$this->form_validation->set_rules('password_confirm', t('password_confirmation'), 'required');
		
        if ($this->form_validation->run() == true) 
		{ 
			//check to see if we are creating the user
			$username  = strtolower($this->input->post('first_name'). ' '. $this->input->post('last_name'));
        	$email     = $this->input->post('email');
        	$password  = $this->input->post('password');
        	
        	$additional_data = array('first_name' => $this->input->post('first_name'),
        							 'last_name'  => $this->input->post('last_name'),
        							 'company'    => $this->input->post('company'),
        							 'phone'      => $this->input->post('phone1'),// .'-'. $this->input->post('phone2') .'-'. $this->input->post('phone3'),
									 'active'     => $this->input->post('active'),
									 'country'     => $this->input->post('country'),
        							);
        	
        	//register the user
        	$user_created=$this->ion_auth->register($username,$password,$email,$additional_data);

			//get the user data by email
			$user=$this->ion_auth->get_user_by_email($email);
				
			if ($user)
			{
				//$data=$additional_data;
				$data['username']=$username;
				$data['active']=1;
				//$data['group_id']=1;	
												
				//update user group to ADMIN and ACTIVATE account
				$this->ion_auth->update_user($user->id, $data);	
				
				//set group membership
				$this->ion_auth_model->assign_user_group($user->id,$group_id=1);
			
				//auto login the user
				$this->ion_auth->login($email, $password, $remember=true);
				
				//redirect them back to the admin page
				$this->session->set_flashdata('message', t("form_update_success") );
				redirect("install/complete", 'refresh');
				return;
			}
			else
			{
				$this->error=$this->ion_auth->errors();				
				var_dump($this->error);
			}        	
		} 
		//display the create user form
		
		//set the flash data error message if there is one
		$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
		
		$this->data['first_name']          = array('name'   => 'first_name',
												  'id'      => 'first_name',
												  'type'    => 'text',
												  'value'   => $this->form_validation->set_value('first_name')
												 );
		$this->data['last_name']           = array('name'   => 'last_name',
												  'id'      => 'last_name',
												  'type'    => 'text',
												  'value'   => $this->form_validation->set_value('last_name')												  
												 );
		$this->data['email']              = array('name'    => 'email',
												  'id'      => 'email',
												  'type'    => 'text',
												  'value'   => $this->form_validation->set_value('email')
												 );
		$this->data['username']           = array('name'    => 'username',
												  'id'      => 'username',
												  'type'    => 'text',
												  'value'   => $this->form_validation->set_value('username')
												 );

		$this->data['company']            = array('name'    => 'company',
												  'id'      => 'company',
												  'type'    => 'text',
												  'value'   => $this->form_validation->set_value('company')
												 );
		$this->data['phone1']             = array('name'    => 'phone1',
												  'id'      => 'phone1',
												  'type'    => 'text',
												  'value'   => $this->form_validation->set_value('phone1')
												 );
		$this->data['password']           = array('name'    => 'password',
												  'id'      => 'password',
												  'type'    => 'password',
												  'value'   => $this->form_validation->set_value('password')
												 );
		$this->data['password_confirm']   = array('name'    => 'password_confirm',
												  'id'      => 'password_confirm',
												  'type'    => 'password',
												  'value'   => $this->form_validation->set_value('password_confirm')
												 );
		$this->data['active']=$this->form_validation->set_value('active',1);
		
		$content=$this->load->view('install/create_user', $this->data,TRUE);
		
		//pass data to the site's template
		$this->template->write('content', $content,true);
		
		//set page title
		$this->template->write('title', $this->data['page_title'],true);

		//render final output
		$this->template->render();	

    }
	
	function complete()
	{
			//exit if already installed	
			$this->is_already_installed();
					
			//update db configurations
			$data=array('name'=>'app_installed', 'value'=>date("U"));
			$result=$this->db->insert("configurations",$data);			

			if ($result)
			{
				//unset session	
				$this->session->unset_userdata("installing");		
			}
			
			$content=$this->load->view('install/completed',NULL,TRUE);	
			
			//pass data to the site's template
			$this->template->write('content', $content,true);
			
			//render final output
			$this->template->render();	
	}
	
	
	/**
	 * 
	 * Check if the Application is already installed
	 * If application is installed, the installer will not continue
	 * 
	 * @return bool
	 */
	function is_already_installed()
	{
		//check if database connection settings are filled in
		if ($this->db->dbdriver=='' || $this->db->username=='' || $this->db->database=='')
		{
			show_error('You have not setup database settings');
		}
		
		//test reading from database tables
		$this->db->select("name");	
		$this->db->where("name", "app_installed");
		$query=$this->db->get("configurations");
		
		if ($query)
		{
			$result=$query->row_array();
			
			if (isset($result['name']))
			{
				//Application is already installed, exit the installer
				show_error(t('page_not_found').' - <a href="'.site_url().'">'.t('return_to_site').'</a>');
				exit;
			}
		}
				
		//test database connection only if everything else above has failed
		$connected=$this->_test_connection();

		if (!$connected) 
        {
            //cannot connect to database server
			show_error('Failed to connect to database, check database settings');
        } 
	}
	
	
	
	/**
	*
	* Test database connectivity
	*
	* return	bool
	*/
	function _test_connection()
	{
		$conn_id=FALSE;
		
		switch($this->db->dbdriver)
		{
			case 'mysql':
				$conn_id = @mysql_connect($this->db->hostname, $this->db->username, $this->db->password, TRUE);
			break;
			case 'mysqli':
			$conn_id = @mysqlI_connect($this->db->hostname, $this->db->username, $this->db->password);
			break;
			case 'postgre':
				$conn_id=@pg_connect("host={$this->db->hostname} user={$this->db->username} password={$this->db->password} connect_timeout=5 dbname=postgres");
			break;
			case 'sqlsrv':				
				$auth_info = array( "UID"=>$this->db->username,"PWD"=>$this->db->password, "Database"=>$this->db->database);
				$conn_id = @sqlsrv_connect($this->db->hostname, $auth_info);
			break;
			default:
				show_error('INSTALLER::database not supported');
		}

        if (! $conn_id ) 
        {
            return FALSE;
        } 
        else 
        {
            return TRUE;
        }
	}
	
	
	
	/**
	*
	* Check folder permissions
	*
	**/
	function _check_folder_permissions()
	{
		//folder that need WRITE Permissions
		$folders=array(
					'datasets'=>$this->config->item("catalog_root"),
					'cache'=>$this->config->item("cache_path"),
					'log'=>$this->config->item("log_path"),
					);			
	}

	/**
	*
	* Create database tables
	*/
	function _create_tables()
	{
		//default
		$db_driver=$this->db->dbdriver;

		//mysql, mysqli
		if (in_array($db_driver,array('mysql','mysqli'))){
			$db_driver='mysql';
		}

		//sql file to restore database
		$filename=APPPATH.'../install/schema.'.$db_driver.'.sql';
		
		if (!file_exists($filename))
		{
			show_error(t('file_not_found'). ' - schema.sql');
		}
		
		// Temporary variable, used to store current query
		$templine = '';
		
		// Read in entire file
		$lines = file($filename);
		
		// Loop through each line
		foreach ($lines as $line)
		{
			// Skip it if it's a comment
			if (substr($line, 0, 1) == '#' || $line == '')
				continue;
		 
			// Add this line to the current segment
			$templine .= $line;
			
			// If it has a semicolon at the end, it's the end of the query
			if (substr(trim($line), -1, 1) == ';')
			{
				//log_message('info', $templine);
				$result=$this->db->query($templine);
				
				if(!$result)
				{
					log_message('error', $templine);
					//echo $this->db->last_query();
				}

				// Reset temp variable to empty
				$templine = '';
			}
		}
	}
	

}

/* End of file install.php */
/* Location: ./system/application/controllers/install.php */