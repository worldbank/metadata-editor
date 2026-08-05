<?php

require_once 'application/libraries/Auth/AuthInterface.php';
require_once 'application/libraries/Auth/OidcAuthBase.php';

/**
 * OIDC Authentication Driver for Confidential Clients (Server-Side)
 * 
 * This driver handles OIDC authentication for server-side PHP applications
 * that can securely store a client secret. Uses standard authorization code flow
 * with optional PKCE for additional security.
 */
class OidcAuth extends OidcAuthBase implements AuthInterface {

    /**
     * Main login method - shows dual login options if OIDC enabled
     */
    function login()
    {
        if ($this->ci->input->get("isajax")) {
            return $this->login_ajax();
        }

        $this->ci->template->set_template('default');
        $this->data['title'] = t("login");

        // Check for popup mode
        $popup_mode = $this->ci->input->get('mode') === 'popup' || $this->ci->input->post('mode') === 'popup';

        if ($popup_mode) {
            $this->ci->template->set_template('blank');
        }

        // Check if OIDC is enabled and should show default login
        $show_oidc_button = $this->oidc_enabled;
        $show_default_login = !$this->oidc_enabled || 
                             (isset($this->oidc_config['show_default_login']) && 
                              $this->oidc_config['show_default_login'] === true);

        // If OIDC enabled and default login hidden, redirect to OIDC
        if ($this->oidc_enabled && !$show_default_login) {
            redirect('auth/oidc_login', 'refresh');
            return;
        }

        //validate form input
        $this->ci->form_validation->set_rules('email', t('email'), 'trim|required|valid_email|max_length[100]');
        $this->ci->form_validation->set_rules('password', t('password'), 'required|max_length[100]');
        
        if ($show_default_login) {
            $this->ci->form_validation->set_rules($this->ci->captcha_lib->get_question_field(), t('captcha'), 'trim|required|callback_validate_captcha');
        }

        if ($this->ci->form_validation->run() == true) {             
            $remember = false;

            //track login attempts?
            if ($this->ci->config->item("track_login_attempts")===TRUE)
            {
                //check if max login attempts limit reached
                $max_login_limit=$this->ci->ion_auth->is_max_login_attempts_exceeded($this->ci->input->post('email'));

                if ($max_login_limit)
                {
                    $this->ci->session->set_flashdata('error', t("max_login_attempted"));
                    sleep(3);
                    // Preserve popup mode
                    if ($popup_mode) {
                        redirect("auth/login?mode=popup", 'refresh');
                    } else {
                        redirect("auth/login", 'refresh');
                    }
                }
            }

            if ($this->attempt_password_login_with_resolver(
                $this->ci->input->post('email'),
                $this->ci->input->post('password'),
                $remember
            ))
            {
                //log
                $this->ci->db_logger->write_log('login',$this->ci->input->post('email'));

                // If popup mode, redirect to success page instead of destination
                if ($popup_mode) {
                    redirect("auth/login_success?mode=popup", 'refresh');
                } else {
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
            }
            else
            { 	//if the login was un-successful
                //redirect them back to the login page
                $this->ci->session->set_flashdata('error', t("login_failed"));

                // Preserve popup mode in redirect
                if ($popup_mode) {
                    redirect("auth/login?mode=popup", 'refresh');
                } else {
                    redirect("auth/login", 'refresh');
                }
            }
        }
        else
        {
            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('error');
            
            // Pass popup mode to view
            $this->data['popup_mode'] = $this->ci->input->get('mode') === 'popup';

            // OIDC configuration for view
            $this->data['show_oidc_button'] = $show_oidc_button;
            $this->data['show_default_login'] = $show_default_login;
            $this->data['oidc_provider_name'] = isset($this->oidc_config['provider_name']) 
                ? $this->oidc_config['provider_name'] 
                : 'OIDC Provider';
            $this->data['oidc_provider_icon'] = isset($this->oidc_config['provider_icon']) 
                ? $this->oidc_config['provider_icon'] 
                : '';

            if ($show_default_login) {
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
            }
            
            $content=$this->ci->load->view('auth/login', $this->data,TRUE);

            $this->ci->template->write('content', $content,true);
            $this->ci->template->write('title', t('login'),true);
            $this->ci->template->render();
        }
    }

    /**
     * Initiate OIDC login flow (server-side for confidential clients)
     */
    function oidc_login()
    {
        if (!$this->oidc_enabled) {
            show_error('OIDC authentication is not enabled');
        }

        try {
            $this->ci->load->library('OidcClient');
            
            // Check for popup mode
            $popup_mode = $this->ci->input->get('mode') === 'popup' || $this->ci->input->post('mode') === 'popup';
            
            // Generate state and nonce
            $state = nada_random_hash(32);
            $nonce = nada_random_hash(32);
            
            // Generate PKCE codes if enabled (even for confidential clients, PKCE adds extra security)
            $code_challenge = null;
            $code_verifier = null;
            if (isset($this->oidc_config['use_pkce']) && $this->oidc_config['use_pkce']) {
                $pkce_codes = $this->ci->oidcclient->generatePkceCodes();
                $code_challenge = $pkce_codes['code_challenge'];
                $code_verifier = $pkce_codes['code_verifier'];
                $this->ci->session->set_userdata('oidc_code_verifier', $code_verifier);
            }
            
            // Store in session for validation
            $this->ci->session->set_userdata('oidc_state', $state);
            $this->ci->session->set_userdata('oidc_nonce', $nonce);
            
            // Store popup mode in session
            if ($popup_mode) {
                $this->ci->session->set_userdata('oidc_popup_mode', true);
            }
            
            // Store destination if provided
            $destination = $this->ci->input->get('destination') ?: $this->ci->session->userdata("destination");
            if ($destination) {
                $this->ci->session->set_userdata('oidc_destination', $destination);
            }
            
            // Get authorization URL (with PKCE if enabled)
            $auth_url = $this->ci->oidcclient->getAuthorizationUrl($state, $nonce, $code_challenge);
            
            // Redirect to OIDC provider
            redirect($auth_url, 'refresh');
            
        } catch (Exception $e) {
            log_message('error', 'OIDC login failed: ' . $e->getMessage());
            $this->ci->session->set_flashdata('error', 'OIDC authentication failed: ' . $e->getMessage());
            
            // Preserve popup mode in error redirect
            $popup_mode = $this->ci->session->userdata('oidc_popup_mode');
            if ($popup_mode) {
                redirect('auth/login?mode=popup', 'refresh');
            } else {
                redirect('auth/login', 'refresh');
            }
        }
    }

    /**
     * Handle OIDC callback (server-side for confidential clients)
     */
    function callback()
    {
        if (!$this->oidc_enabled) {
            show_error('OIDC authentication is not enabled');
        }

        try {
            $this->ci->load->library('OidcClient');
            
            // Get stored nonce (for confidential clients only)
            $nonce = $this->ci->session->userdata('oidc_nonce');
            if (empty($nonce)) {
                throw new Exception('Session expired. Please try logging in again.');
            }
            
            // Handle different response modes
            $code = null;
            $state = null;
            $id_token = null;
            $tokens = null;
            
            if ($this->oidc_config['response_mode'] === 'form_post') {
                $code = $this->ci->input->post('code');
                $state = $this->ci->input->post('state');
                $id_token = $this->ci->input->post('id_token');
            } else {
                $code = $this->ci->input->get('code');
                $state = $this->ci->input->get('state');
            }
            
            // Check for errors
            $error = $this->ci->input->get('error') ?: $this->ci->input->post('error');
            if ($error) {
                $error_description = $this->ci->input->get('error_description') ?: $this->ci->input->post('error_description');
                throw new Exception('OIDC error: ' . $error . ($error_description ? ' - ' . $error_description : ''));
            }
            
            if (empty($code) && empty($id_token)) {
                throw new Exception('No authorization code or ID token received');
            }
            
            // Exchange code for tokens only if id_token is not already present
            // Authorization code flow (response_type=code) requires token exchange
            if (empty($id_token) && !empty($code)) {
                // Validate state parameter
                // State is required when using authorization code flow
                if (empty($state)) {
                    throw new Exception('State parameter is required for authorization code flow');
                }
                if (!$this->validate_state($state, 'confidential')) {
                    throw new Exception('Invalid state parameter - possible CSRF attack');
                }
                
                // Get code_verifier from session if PKCE was used
                $code_verifier = $this->ci->session->userdata('oidc_code_verifier');
                if (!empty($code_verifier)) {
                    $this->ci->session->unset_userdata('oidc_code_verifier');
                }
                
                $tokens = $this->ci->oidcclient->exchangeCodeForTokens($code, $state, $code_verifier);
                $id_token = $tokens['id_token'];
                
                // Clear state from session after successful validation
                $this->ci->session->unset_userdata('oidc_state');
            } elseif (!empty($id_token) && !empty($state)) {
                // If id_token is present directly (e.g., form_post response), still validate state if provided
                if (!$this->validate_state($state, 'confidential')) {
                    throw new Exception('Invalid state parameter - possible CSRF attack');
                }
                $this->ci->session->unset_userdata('oidc_state');
            }
            
            // Validate ID token
            $claims = $this->ci->oidcclient->validateIdToken($id_token, $nonce);
            
            // Clear OIDC session data
            $this->ci->session->unset_userdata('oidc_nonce');
            // State already cleared above after validation
            
            // Store ID token for logout if needed
            if (isset($this->oidc_config['logout_endpoint']) && $this->oidc_config['logout_endpoint']) {
                $this->ci->session->set_userdata('oidc_id_token', $id_token);
            }
            
            // Map claims to user data
            $user_data = $this->mapClaimsToUserData($claims);

            $this->complete_oidc_authentication($claims, $user_data);
            
            // Check for popup mode
            $popup_mode = $this->ci->session->userdata('oidc_popup_mode');
            $this->ci->session->unset_userdata('oidc_popup_mode');
            
            // Get destination
            $destination = $this->ci->session->userdata('oidc_destination');
            $this->ci->session->unset_userdata('oidc_destination');
            
            // Handle redirect based on popup mode
            if ($popup_mode) {
                // In popup mode, redirect to login_success page
                redirect("auth/login_success?mode=popup", 'refresh');
            } else {
                // Normal mode - redirect to destination or home
                if ($destination && $destination != '') {
                    redirect($destination, 'refresh');
                } else {
                    redirect($this->ci->config->item('base_url'), 'refresh');
                }
            }
            
        } catch (Exception $e) {
            log_message('error', 'OIDC callback failed: ' . $e->getMessage());
            $this->ci->session->set_flashdata('error', 'Authentication failed: ' . $e->getMessage());
            
            // Preserve popup mode in error redirect
            $popup_mode = $this->ci->session->userdata('oidc_popup_mode');
            $this->ci->session->unset_userdata('oidc_popup_mode');
            
            if ($popup_mode) {
                redirect('auth/login?mode=popup', 'refresh');
            } else {
                redirect('auth/login', 'refresh');
            }
        }
    }
}

//end-class
