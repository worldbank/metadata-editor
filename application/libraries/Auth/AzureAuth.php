<?php

require_once 'application/libraries/Auth/AuthInterface.php';
require_once 'application/libraries/Auth/DefaultAuth.php';

class AzureAuth extends DefaultAuth implements AuthInterface {

    function __construct()
    {
        parent::__construct($skip_auth=TRUE);

		//$_POST['id_token']='';    
    }


	function AzureSSO()
	{
		//try authenticating with Azure
		if (!$this->ci->input->post('id_token') ){			
			$this->ci->session->set_userdata('login_attempts', (int)$this->ci->session->userdata('login_attempts') + 1);

			if ($this->ci->session->userdata('login_attempts')>5){
				$this->ci->session->unset_userdata('login_attempts');
				show_error("Failed to login. Refresh the page to try again.");
			}

			$azure_auth_configs=$this->ci->config->item("azure_auth");
			$redirect_url=$azure_auth_configs['authorize_endpoint'].'?client_id='.$azure_auth_configs['client_id'].'&response_mode=form_post&response_type=code%20id_token&nonce='.md5(time());
			redirect($redirect_url,'refresh');
		}

		$this->ci->session->unset_userdata('login_attempts');
		$this->ci->load->library('AzureOauth2');
		$this->ci->azureoauth2->login();

		redirect(site_url('catalog'), 'refresh');
	}

    //log the user in
    function alternate()
    {
		$this->ci->template->set_template('blank');
        $this->data['title'] = t("login");

		if($this->ci->input->get('destination'))
		{
			$destination=$this->ci->input->get('destination');
			$this->session->unset_userdata('destination');
		}
		else {
        	$destination=$this->ci->session->userdata("destination");
		}

        //validate form input
    	$this->ci->form_validation->set_rules('email', t('email'), 'trim|required|valid_email|max_length[100]');
	    $this->ci->form_validation->set_rules('password', t('password'), 'required|max_length[100]');

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

				if ($destination!="")
				{
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
				$this->ci->db_logger->write_log('login-failed',$this->ci->input->post('email'));

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

			$content=$this->ci->load->view('auth/login', $this->data,TRUE);

			//pass data to the site's template
			$this->ci->template->write('content', $content,true);

			//set page title
			$this->ci->template->write('title', t('login'),true);

			//render final output
			$this->ci->template->render();
		}
    }

}//end-class
