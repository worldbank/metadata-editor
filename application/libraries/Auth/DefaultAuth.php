<?php
require_once 'application/libraries/Auth/AuthInterface.php';

class DefaultAuth implements AuthInterface
{

	protected $ci;

    function __construct()
    {
		//parent::__construct($skip_auth=TRUE);
		$this->ci =& get_instance();
		
		$this->ci->load->library('Nada_csrf');
        $this->ci->load->library('ion_auth');
        $this->ci->load->library('session');
        $this->ci->load->library('form_validation');
        $this->ci->load->database();
        $this->ci->load->helper('url');    	

    	$this->ci->template->set_template('default');
    	//$this->ci->template->write('sidebar', $this->_menu(),true);

		$this->ci->lang->load('general');
		$this->ci->lang->load('users');

		$this->ci->load->driver('captcha_lib');

      	//$this->output->enable_profiler(TRUE);
    }

    //redirect to login page
    function index()
    {
    	redirect("auth/login");
		show_404();
    }

    //expire page immediately
    private function disable_page_cache()
    {
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
	}
	
	function generate_api_key()
	{
		$this->_is_logged_in();
		$this->ci->ion_auth->set_api_key($this->ci->session->userdata('user_id'));
		redirect("auth/profile", 'refresh');
	}


	function delete_api_key()
	{
		$this->_is_logged_in();
		$this->ci->ion_auth->delete_api_key($this->ci->session->userdata('user_id'),$this->ci->input->get("api_key"));
		redirect("auth/profile", 'refresh');
	}


	function profile()
	{
		//don't let browsers cache this page
		$this->disable_page_cache();

		//check if user is logged in
		$this->_is_logged_in();
		//$this->ci->load->model('Licensed_model');
		//$this->ci->lang->load("licensed_request");

		//get user info
		$data['user']= $this->ci->ion_auth->get_user($this->ci->session->userdata('user_id'));

		//get user api keys
		$data['api_keys']=$this->ci->ion_auth->get_api_keys($this->ci->session->userdata('user_id'));

		//get user licensed requests
		//$data['lic_requests']=$this->ci->Licensed_model->get_user_requests($data['user']->id);

		$content=$this->ci->load->view('auth/profile_view',$data,TRUE);

		$this->ci->template->write('title', t('profile'),true);
		$this->ci->template->write('content', $content,true);
	  	$this->ci->template->render();
	}

	function edit_profile()
	{
		$this->disable_page_cache();
		$this->_is_logged_in();
		$csrf=$this->ci->nada_csrf->generate_token();

		//currently logged in user
		$data['user']= $this->ci->ion_auth->get_user($this->ci->session->userdata('user_id'));

    	$this->ci->form_validation->set_rules('first_name', t('first_name'), 'trim|required|xss_clean|max_length[50]');
    	$this->ci->form_validation->set_rules('last_name', t('last_name'), 'trim|required|xss_clean|max_length[50]');
    	$this->ci->form_validation->set_rules('phone', t('phone'), 'trim|xss_clean|max_length[20]');
    	$this->ci->form_validation->set_rules('company', t('company'), 'trim|xss_clean|max_length[100]');
		$this->ci->form_validation->set_rules('country', t('country'), 'trim|xss_clean|max_length[100]');
		$this->ci->form_validation->set_rules('form_token', 'FORM TOKEN', 'trim|callback_validate_token');

		if ($this->ci->form_validation->run() == true) {
			$update_data = array(
				'first_name' => $this->ci->input->post('first_name'),
				'last_name'  => $this->ci->input->post('last_name'),
				'company'    => $this->ci->input->post('company'),
				'phone'      => $this->ci->input->post('phone'),
				'country'    => $this->ci->input->post('country'),
				);
			$this->ci->ion_auth->update_user($data['user']->id,$update_data);
        	$this->ci->session->set_flashdata('message', t("profile_updated"));
       		redirect("auth/profile", 'refresh');
		}
		$data['csrf']=$csrf;
		$content=$this->ci->load->view('auth/profile_edit',$data,TRUE);

		$this->ci->template->write('title', t('edit_profile'),true);
		$this->ci->template->write('content', $content,true);
	  	$this->ci->template->render();
	}



	/**
	* checks if a user is logged in, otherwise redirects to the login page
	*
	*/
	function _is_logged_in()
	{
		$destination=$this->ci->uri->uri_string();
		$this->ci->session->set_userdata("destination",$destination);

    	if (!$this->ci->ion_auth->logged_in())
		{
	    	//redirect them to the login page
			redirect("auth/login/?destination=$destination", 'refresh');
    	}
	}

    //log the user in
    function login()
    {
		if ($this->ci->input->get("isajax")) {
			return $this->login_ajax();
		}

		$this->ci->template->set_template('blank');
        $this->data['title'] = t("login");

        //validate form input
    	$this->ci->form_validation->set_rules('email', t('email'), 'trim|required|valid_email|max_length[100]');
	    $this->ci->form_validation->set_rules('password', t('password'), 'required|max_length[100]');
		$this->ci->form_validation->set_rules($this->ci->captcha_lib->get_question_field(), t('captcha'), 'trim|required|callback_validate_captcha');

        if ($this->ci->form_validation->run() == true) { //check to see if the user is logging in
        	//check for "remember me"
        	if ($this->ci->input->post('remember') == 1)
			{
        		$remember = true;
        	}
        	else
			{
        		$remember = false;
        	}


			//track login attempts?
			if ($this->ci->config->item("track_login_attempts")===TRUE)
			{
				//check if max login attempts limit reached
				$max_login_limit=$this->ci->ion_auth->is_max_login_attempts_exceeded($this->ci->input->post('email'));

				if ($max_login_limit)
				{
					$this->ci->session->set_flashdata('error', t("max_login_attempted"));
					sleep(3);
					redirect("auth/login");
				}
			}


        	if ($this->ci->ion_auth->login($this->ci->input->post('email'), $this->ci->input->post('password'), $remember)) //if the login is successful
			{
				//log
				$this->ci->db_logger->write_log('login',$this->ci->input->post('email'));

				$destination=$this->ci->session->userdata("destination");

				if ($destination!="")
				{
					$this->ci->session->unset_userdata('destination');
					redirect($destination, 'refresh');
				}
				else
				{
				 	redirect($this->ci->config->item('base_url'), 'refresh');
				}
	        }
	        else
			{ 	//if the login was un-successful
	        	//redirect them back to the login page
	        	$this->ci->session->set_flashdata('error', t("login_failed"));

				//log
				//$this->ci->db_logger->write_log('login-failed',$this->ci->input->post('email'));

	        	redirect("auth/login", 'refresh'); //use redirects instead of loading views for compatibility with MY_Controller libraries
	        }
        }
		else
		{  	//the user is not logging in so display the login page
	        //set the flash data error message if there is one
	        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('error');

			$this->data['email']      = array('name'    => 'email',
                                              'id'      => 'email',
                                              'type'    => 'text',
                                              'value'   => $this->ci->form_validation->set_value('email'),
                                             );
            $this->data['password']   = array('name'    => 'password',
                                              'id'      => 'password',
                                              'type'    => 'password',
                                             );
			$this->data['captcha_question']=$this->ci->captcha_lib->get_html();
			
			$content=$this->ci->load->view('auth/login', $this->data,TRUE);

			$this->ci->template->write('content', $content,true);
			$this->ci->template->write('title', t('login'),true);
			$this->ci->template->render();
		}
    }

	function login_ajax()
    {
        //validate form input
    	$this->ci->form_validation->set_rules('email', t('email'), 'trim|required|valid_email|max_length[100]');
	    $this->ci->form_validation->set_rules('password', t('password'), 'required|max_length[100]');
		//$this->ci->form_validation->set_rules($this->ci->captcha_lib->get_question_field(), t('captcha'), 'trim|required|callback_validate_captcha');

        if ($this->ci->form_validation->run() == true) { //check to see if the user is logging in
        	if ($this->ci->ion_auth->login(
					$this->ci->input->post('email'), 
					$this->ci->input->post('password'), $remember=true)) //if the login is successful
			{
				//success
				$this->json_response($data=array(
					'status'=>'success'
				),200);
			}
	        else
			{ 	
				//login failed
				$this->json_response($data=array(
					'status'=>'error',
					'message'=>'Login failed'
				),401);
	        }
        }
		else
		{  	
			$this->json_response($data=array(
				'status'=>'error',
				'message'=>'Login failed'
			),401);
		}
    }

	function json_response($body,$status_code=200)
	{		
		http_response_code($status_code);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($body);
		die();
	}

    //log the user out
	function logout()
	{
        $this->disable_page_cache();
		$this->data['title'] = t("logout");
        $logout = $this->ci->ion_auth->logout();
        redirect('', 'refresh');
	}
	

    //change password
	function change_password()
	{
		$this->disable_page_cache();
		$csrf=$this->ci->nada_csrf->generate_token();
	    $use_complex_password=$this->ci->config->item("require_complex_password");

	    $this->ci->form_validation->set_rules('old', t('old_password'), 'required|max_length[20]|xss_clean');
	    $this->ci->form_validation->set_rules('new', t('new_password'), 'required|min_length['.$this->ci->config->item('min_password_length').']|max_length['.$this->ci->config->item('max_password_length').']|matches[new_confirm]|is_complex_password['.$use_complex_password.']');
	    $this->ci->form_validation->set_rules('new_confirm', t('confirm_new_password'), 'required|max_length[20]');
   	    $this->ci->form_validation->set_rules('form_token', 'FORM TOKEN', 'trim|callback_validate_token');

	    if (!$this->ci->ion_auth->logged_in()) {
	    	redirect('auth/login', 'refresh');
		}
		
	    $user = $this->ci->ion_auth->get_user($this->ci->session->userdata('user_id'));

	    if ($this->ci->form_validation->run() == false){
	        //set the flash data error message if there is one
	        $this->data['message'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('message');

	        $this->data['old_password']           = array('name'    => 'old',
		                                               	  'id'      => 'old',
		                                              	  'type'    => 'password',
		                                                 );
	        $this->data['new_password']           = array('name'    => 'new',
		                                               	  'id'      => 'new',
		                                              	  'type'    => 'password',
		                                                 );
        	$this->data['new_password_confirm']   = array('name'    => 'new_confirm',
                                                      	  'id'      => 'new_confirm',
                                                      	  'type'    => 'password',
        												 );
        	$this->data['user_id']                = array('name'    => 'user_id',
                                                      	  'id'      => 'user_id',
                                                      	  'type'    => 'hidden',
        												  'value'   => $user->id,
        												 );
			$this->data['csrf']=$csrf;
        	$output=$this->ci->load->view('auth/change_password', $this->data,TRUE);

			$this->ci->template->write('content', $output,true);
			$this->ci->template->write('title', t('change_password'),true);
			$this->ci->template->render();
	    }
    else{
        $identity = $this->ci->session->userdata($this->ci->config->item('identity'));
        $change = $this->ci->ion_auth->change_password($identity, $this->ci->input->post('old'), $this->ci->input->post('new'));

		if ($change) {
			$this->ci->session->set_flashdata('message', t('password_changed_success'));
			redirect('auth/change_password', 'refresh');
		}
		else {
			$this->ci->session->set_flashdata('error', t('password_change_failed'));
			redirect('auth/change_password', 'refresh');
		}
    }
	}



	//forgot password
	function forgot_password()
	{
		$this->disable_page_cache();
		$this->ci->form_validation->set_rules('email', t('email'), 'trim|required|xss_clean|max_length[100]');

		if ($this->ci->form_validation->run() == false)
		{
	    	//setup the input
	    	$this->data['email'] = array('name'    => 'email',
                                         'id'      => 'email',
        						   	    );
	    	//set any errors and display the form
        	$this->data['message'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('message');
    		$content=$this->ci->load->view('auth/forgot_password', $this->data,true);

			$this->ci->template->write('content', $content,true);
			$this->ci->template->write('title', t('forgot_password'),true);
		  	$this->ci->template->render();
	    }
	    else
		{
	        //run the forgotten password method to email an activation code to the user
			$forgotten = $this->ci->ion_auth->forgotten_password($this->ci->input->post('email'));

			if ($forgotten) //if there were no errors
			{
				//$this->ci->session->set_flashdata('message', $this->message);
	            //redirect("auth/login", 'refresh'); //we should display a confirmation page here instead of the login page
				$contents=$this->ci->load->view('auth/forgot_pass_confirm',NULL,TRUE);
				$this->ci->template->write('content', $contents,true);
				$this->ci->template->write('title', t('forgot_password'),true);
				$this->ci->template->render();
			}
			else {
				$this->ci->session->set_flashdata('message', t('email_failed'));
	            redirect("auth/forgot_password", 'refresh');
			}
	    }
	}

	//reset password - final step for forgotten password
	public function reset_password($code=null)
	{
		if (empty($code)){
			show_404();
		}
		
		$this->disable_page_cache();

		$validate_code=$this->ci->ion_auth_model->validate_forgot_password_code($code);

		if($validate_code==false){
			$this->ci->session->set_flashdata('error', t('password_change_failed'));
    		redirect('auth/forgot_password', 'refresh');
		}

	    $use_complex_password=$this->ci->config->item("require_complex_password");
	    $this->ci->form_validation->set_rules('new', t('new_password'), 'required|min_length['.$this->ci->config->item('min_password_length').']|max_length['.$this->ci->config->item('max_password_length').']|matches[new_confirm]|is_complex_password['.$use_complex_password.']');
	    $this->ci->form_validation->set_rules('new_confirm', t('confirm_new_password'), 'required|max_length[20]');
		
	    $user = $validate_code['user'];

	    if ($this->ci->form_validation->run() == false){
	        //set the flash data error message if there is one
	        $data['message'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('message');

	        $data['new_password']           = array('name'    => 'new',
														'id'      => 'new',
														'type'    => 'password',
														);
        	$data['new_password_confirm']   = array('name'    => 'new_confirm',
                                                      	  'id'      => 'new_confirm',
                                                      	  'type'    => 'password',
        												 );			
			$data['code']=$code;
        	$output=$this->ci->load->view('auth/reset_password', $data,TRUE);

			$this->ci->template->write('content', $output,true);
			$this->ci->template->write('title', t('reset_password'),true);
			$this->ci->template->render();
	    }
	    else{
			$result=$this->ci->ion_auth_model->reset_password($user->email,$this->ci->input->post('new'));

    		if ($result) {
				$this->ci->session->set_flashdata('message', t('password_change_success'));
				redirect('auth/login', 'refresh');
    		}
    		else {
    			$this->ci->session->set_flashdata('error', t('password_change_failed'));    			
    		}
	    }

		return;
		$this->disable_page_cache();

		$reset = $this->ci->ion_auth->forgotten_password_complete($code);

		if ($reset) //if the reset worked then send them to the login page
		{
			$this->ci->session->set_flashdata('message', t('forgot_password_success'));
			redirect("auth/change_password", 'refresh');			
		}
		else //if the reset didnt work then send them back to the forgot password page
		{
			$this->ci->session->set_flashdata('message', t('forgot_password_failed'));
            redirect("auth/forgot_password", 'refresh');
		}
	}


	//activate the user
	function activate($id=NULL, $code=false)
	{
		$this->disable_page_cache();
		$activation = $this->ci->ion_auth->activate($id, $code);

		$data=array();

        if ($activation)
		{
			$data['success']=true;
        }
        else
		{
			$data['failed']=true;
        }

		$content=$this->ci->load->view('auth/msg_account_activation',$data,TRUE);
		$this->ci->template->write('title', t('user_account_activation'),true);
		$this->ci->template->write('content', $content,true);
		$this->ci->template->write('title', t('user_account_activation'),true);
		$this->ci->template->render();
    }

    //deactivate the user
	function deactivate($id)
	{
		$this->disable_page_cache();
		if ($this->ci->ion_auth->logged_in() && $this->ci->ion_auth->is_admin())
		{
	        //de-activate the user
	        $this->ci->ion_auth->deactivate($id);
		}
        //redirect them back to the auth page
        redirect("auth", 'refresh');
    }


	function create_user()
	{
		$this->disable_page_cache();
		$this->_create_user();
	}

    //create a new user
	function _create_user()
	{		
		$this->data['title'] = t("register");		
		$content=NULL;

		$use_complex_password=$this->ci->config->item("require_complex_password");
		$csrf=$this->ci->nada_csrf->generate_token();

        //validate form input
    	$this->ci->form_validation->set_rules('first_name', t('first_name'), 'trim|required|xss_clean|max_length[50]');
    	$this->ci->form_validation->set_rules('last_name', t('last_name'), 'trim|required|xss_clean|max_length[50]');
    	$this->ci->form_validation->set_rules('email', t('email'), 'trim|required|valid_email|max_length[100]|check_user_email_exists');
    	//$this->ci->form_validation->set_rules('phone1', t('phone'), 'trim|xss_clean|max_length[20]');
    	//$this->ci->form_validation->set_rules('company', t('company'), 'trim|xss_clean|max_length[100]');
		$this->ci->form_validation->set_rules('country', t('country'), 'trim|xss_clean|max_length[150]|check_user_country_valid');
    	$this->ci->form_validation->set_rules('password', t('password'), 'required|min_length['.$this->ci->config->item('min_password_length').']|max_length['.$this->ci->config->item('max_password_length').']|matches[password_confirm]|is_complex_password['.$use_complex_password.']');
    	$this->ci->form_validation->set_rules('password_confirm', t('password_confirmation'), 'required');
		//$this->ci->form_validation->set_rules('form_token', 'FORM TOKEN', 'trim|callback_validate_token');
		$this->ci->form_validation->set_rules('csrf_token', 'CSRF TOKEN', 'trim|callback_validate_token');
    	$this->ci->form_validation->set_rules($this->ci->captcha_lib->get_question_field(), t('captcha'), 'trim|required|callback_validate_captcha');

        if ($this->ci->form_validation->run() === TRUE)
		{
			//log
			//$this->ci->db_logger->write_log('register',$this->ci->input->post('email'));

			//check to see if we are creating the user
			$username  = $this->ci->input->post('first_name').' '.$this->ci->input->post('last_name');
        	$email     = $this->ci->input->post('email');
        	$password  = $this->ci->input->post('password');

        	$additional_data = array('first_name' => $this->ci->input->post('first_name'),
        							 'last_name'  => $this->ci->input->post('last_name'),
        							 //'company'    => $this->ci->input->post('company'),
        							 //'phone'      => $this->ci->input->post('phone1'),// .'-'. $this->ci->input->post('phone2') .'-'. $this->ci->input->post('phone3'),
									 'country'      => $this->ci->input->post('country'),
									 'email'=>$email,
									 'identity'=>$username
        							);
        	$this->ci->ion_auth->register($username,$password,$email,$additional_data);
			$content=$this->ci->load->view('auth/create_user_confirm',NULL,TRUE);
		}
		else
		{
			//set the flash data error message if there is one
	        $this->data['message'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('message');

			$this->data['captcha_question']=$this->ci->captcha_lib->get_html();

			$this->data['first_name']          = array('name'   => 'first_name',
		                                              'id'      => 'first_name',
		                                              'type'    => 'text',
		                                              'value'   => $this->ci->form_validation->set_value('first_name'),
		                                             );
            $this->data['last_name']           = array('name'   => 'last_name',
		                                              'id'      => 'last_name',
		                                              'type'    => 'text',
		                                              'value'   => $this->ci->form_validation->set_value('last_name'),
		                                             );
            $this->data['email']              = array('name'    => 'email',
		                                              'id'      => 'email',
		                                              'type'    => 'text',
		                                              'value'   => $this->ci->form_validation->set_value('email'),
		                                             );
            /*$this->data['company']            = array('name'    => 'company',
		                                              'id'      => 'company',
		                                              'type'    => 'text',
		                                              'value'   => $this->ci->form_validation->set_value('company'),
		                                             );
            $this->data['phone1']             = array('name'    => 'phone1',
		                                              'id'      => 'phone1',
		                                              'type'    => 'text',
		                                              'value'   => $this->ci->form_validation->set_value('phone1'),
		                                             );*/
		    $this->data['password']           = array('name'    => 'password',
		                                              'id'      => 'password',
		                                              'type'    => 'password',
		                                              'value'   => $this->ci->form_validation->set_value('password'),
		                                             );
            $this->data['password_confirm']   = array('name'    => 'password_confirm',
                                                      'id'      => 'password_confirm',
                                                      'type'    => 'password',
                                                      'value'   => $this->ci->form_validation->set_value('password_confirm'),
                                                     );
			$this->data['csrf']=$csrf;	
			$content=$this->ci->load->view('auth/create_user', $this->data,TRUE);
		}

		//render final output
		$this->ci->template->write('content', $content,true);
		$this->ci->template->write('title', $this->data['title'],true);
		$this->ci->template->render();
    }


	function register()
	{
		//show 404 if User Registration is disabled or Site is running under protected mode
		if ($this->ci->config->item("site_user_register")==='no' || $this->ci->config->item("site_password_protect")==='yes')
		{
			show_404();
		}

		$this->_create_user();
	}


	//get public site menu
	function _menu()
	{
		$this->ci->load->model('menu_model');
		$data['menus']= $this->ci->menu_model->select_all();
		$content=$this->ci->load->view('default_menu', $data,true);
		return $content;
	}


	function _remap($method)
	{
        if (in_array(strtolower($method), array_map('strtolower', get_class_methods($this))))
		{
            $uri = $this->ci->uri->segment_array();
            unset($uri[1]);
            unset($uri[2]);

            call_user_func_array(array($this, $method), $uri);
        }
        else {
			show_404();
		}
	}


	/**
	 * 
	 * Verify OTP code
	 * 
	 */
	function verify_code()
	{
		if ($this->ci->config->item("otp_verification")!==1 || !$this->ci->ion_auth->is_admin()){
			show_404();
		}

		$this->ci->form_validation->set_rules('code', t('verification_code'), 'trim|required|xss_clean|max_length[10]');

		if ($this->ci->form_validation->run() == false)
		{
	    	//set any errors and display the form
        	$this->data['message'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('message');
    		$content=$this->ci->load->view('auth/verify_otp', null,true);

			$this->ci->template->write('content', $content,true);
			$this->ci->template->write('title', t('verify_otp'),true);
		  	$this->ci->template->render();
	    }
	    else
		{
			$user=$this->ci->ion_auth->current_user();
			$code=$this->ci->input->post("code");

			try{							
					//otp expired or not set?
					if (date("U")>$user->otp_expiry || !$user->otp_code){
						throw new Exception("Code has expired");
					}
				
				if($code==$user->otp_code){
					$this->ci->session->set_userdata("verify_otp",1);
					$this->ci->session->set_userdata("verified_otp",$code);
					redirect("admin", 'refresh');
				}
				
				throw new exception("Code verification failed");
			}
			catch(Exception $e){
				//$this->ci->db_logger->write_log('otp-error',$e->getMessage(). ' user: '.$user->email);
				$this->ci->session->set_flashdata('error', $e->getMessage());
				redirect("auth/verify_code", 'refresh');
			}			
	    }
	}

	/**
	 * 
	 * 
	 * Email new OTP code
	 * 
	 */
	function send_otp_code()
	{
		if ($this->ci->config->item("otp_verification")!==1 || !$this->ci->ion_auth->is_admin()){
			show_404();
		}
		
		$user_id=$this->ci->session->userdata('user_id');
		$this->ci->ion_auth->send_otp_code($user_id);
		//write_log($type, $message=NULL, $section=NULL,$surveyid=0)
		$this->ci->db_logger->write_log('otp','code sent for user:'.$user_id);
		$this->ci->session->set_flashdata('message', t('Check your email for verification code'));
		redirect("auth/verify_code", 'refresh');
	}

}//end-class
