<?php

require_once 'application/libraries/Auth/AuthInterface.php';
require_once 'application/libraries/Auth/OidcAuthBase.php';

/**
 * OIDC Authentication Driver for Public Clients (SPA/PKCE)
 * 
 * This driver handles OIDC authentication for single-page applications
 * and public clients that cannot securely store a client secret.
 * Uses PKCE (Proof Key for Code Exchange) for security.
 */
class OidcAuthSpa extends OidcAuthBase implements AuthInterface {

    /**
     * Main login method - shows SPA login page with OIDC button
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

        // Check if OIDC is enabled
        $show_oidc_button = $this->oidc_enabled;
        $show_default_login = !$this->oidc_enabled || 
                             (isset($this->oidc_config['show_default_login']) && 
                              $this->oidc_config['show_default_login'] === true);

        // If OIDC enabled and default login hidden, show only OIDC button
        // (No auto-redirect for SPA - user must click button)

        //validate form input (only if default login is shown)
        if ($show_default_login) {
            $this->ci->form_validation->set_rules('email', t('email'), 'trim|required|valid_email|max_length[100]');
            $this->ci->form_validation->set_rules('password', t('password'), 'required|max_length[100]');
            $this->ci->form_validation->set_rules($this->ci->captcha_lib->get_question_field(), t('captcha'), 'trim|required|callback_validate_captcha');
        }

        if ($show_default_login && $this->ci->form_validation->run() == true) {             
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
            
            // For SPA driver, always use login-spa.php (it handles both with/without default login)
            $content=$this->ci->load->view('auth/login-spa', $this->data,TRUE);

            $this->ci->template->write('content', $content,true);
            $this->ci->template->write('title', t('login'),true);
            $this->ci->template->render();
        }
    }

    /**
     * Initiate OIDC login flow
     * For SPA/public clients, redirects to login page (user must click button)
     * This method exists for compatibility but should not be used directly
     */
    function oidc_login()
    {
        if (!$this->oidc_enabled) {
            show_error('OIDC authentication is not enabled');
        }

        // For public clients, redirect to login page
        // The user should click the OIDC button which calls oidc_authorize_api via JavaScript
        $this->ci->session->set_flashdata('message', 'Please use the OIDC login button on the login page.');
        redirect('auth/login', 'refresh');
    }

    /**
     * API endpoint: Get authorization URL for SPA/public client
     * Returns JSON with authorization URL and PKCE parameters
     */
    function oidc_authorize_api()
    {
        if (!$this->oidc_enabled) {
            $this->ci->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array('error' => 'OIDC authentication is not enabled')));
            return;
        }

        try {
            $this->ci->load->library('OidcClient');
            
            // Generate state and nonce
            $state = nada_random_hash(32);
            $nonce = nada_random_hash(32);
            
            // Generate PKCE codes (required for public clients)
            $pkce_codes = $this->ci->oidcclient->generatePkceCodes();
            $code_challenge = $pkce_codes['code_challenge'];
            $code_verifier = $pkce_codes['code_verifier'];
            
            // For public clients, redirect_uri should point to dedicated SPA callback endpoint
            $redirect_uri = site_url('auth/oidc_callback_spa');
            
            // Get authorization URL with PKCE
            $auth_url = $this->ci->oidcclient->getAuthorizationUrl($state, $nonce, $code_challenge, array(), $redirect_uri);
            
            // Return JSON response for frontend
            $response = array(
                'auth_url' => $auth_url,
                'code_verifier' => $code_verifier,
                'state' => $state,
                'nonce' => $nonce
            );
            
            $this->ci->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
                
        } catch (Exception $e) {
            log_message('error', 'OIDC authorize API failed: ' . $e->getMessage());
            $this->ci->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(array('error' => 'Failed to generate authorization URL: ' . $e->getMessage())));
        }
    }
    
    /**
     * API endpoint: Handle OIDC callback for SPA/public client
     * Accepts code, state, and code_verifier from frontend
     * Returns JSON with tokens and user info
     */
    function oidc_callback_api()
    {
        if (!$this->oidc_enabled) {
            $this->ci->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array('error' => 'OIDC authentication is not enabled')));
            return;
        }

        try {
            $this->ci->load->library('OidcClient');
            
            // Get parameters from POST/GET
            $code = $this->ci->input->post('code') ?: $this->ci->input->get('code');
            $state = $this->ci->input->post('state') ?: $this->ci->input->get('state');
            $code_verifier = $this->ci->input->post('code_verifier') ?: $this->ci->input->get('code_verifier');
            $nonce = $this->ci->input->post('nonce') ?: $this->ci->input->get('nonce');
            
            // Validate required parameters
            if (empty($code)) {
                throw new Exception('Authorization code is required');
            }
            if (empty($code_verifier)) {
                throw new Exception('code_verifier is required for PKCE flow');
            }
            if (empty($state)) {
                throw new Exception('State parameter is required');
            }
            if (empty($nonce)) {
                throw new Exception('Nonce parameter is required');
            }
            
            // Check for errors from provider
            $error = $this->ci->input->get('error') ?: $this->ci->input->post('error');
            if ($error) {
                $error_description = $this->ci->input->get('error_description') ?: $this->ci->input->post('error_description');
                throw new Exception('OIDC error: ' . $error . ($error_description ? ' - ' . $error_description : ''));
            }
            
            // Exchange code for tokens using PKCE
            $tokens = $this->ci->oidcclient->exchangeCodeForTokens($code, $state, $code_verifier);
            
            if (empty($tokens['id_token'])) {
                throw new Exception('No ID token in response');
            }
            
            $id_token = $tokens['id_token'];
            
            // Validate ID token
            $claims = $this->ci->oidcclient->validateIdToken($id_token, $nonce);
            
            // Map claims to user data
            $user_data = $this->mapClaimsToUserData($claims);
            
            $user_id = $this->complete_oidc_authentication($claims, $user_data);
            $user_info = $this->ci->ion_auth_model->get_user_row($user_id);
            
            // Store ID token in session for logout if needed
            if (isset($this->oidc_config['logout_endpoint']) && $this->oidc_config['logout_endpoint']) {
                $this->ci->session->set_userdata('oidc_id_token', $id_token);
            }
            
            // Session will be automatically saved by CodeIgniter at the end of the request
            
            // Prepare response with tokens and user info
            $response = array(
                'id_token' => $id_token,
                'access_token' => isset($tokens['access_token']) ? $tokens['access_token'] : null,
                'token_type' => isset($tokens['token_type']) ? $tokens['token_type'] : 'Bearer',
                'expires_in' => isset($tokens['expires_in']) ? $tokens['expires_in'] : null,
                'user' => array(
                    'id' => $user_id,
                    'email' => $user_info ? $user_info->email : $user_data['email'],
                    'username' => $user_info ? $user_info->username : (isset($user_data['username']) ? $user_data['username'] : $user_data['email']),
                    'first_name' => isset($user_data['first_name']) ? $user_data['first_name'] : '',
                    'last_name' => isset($user_data['last_name']) ? $user_data['last_name'] : '',
                )
            );
            
            $this->ci->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
                
        } catch (Exception $e) {
            log_message('error', 'OIDC callback API failed: ' . $e->getMessage());
            $this->ci->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array('error' => $e->getMessage())));
        }
    }

    /**
     * SPA callback page for public clients
     * This page handles the OAuth callback and exchanges code for tokens via JavaScript
     */
    function oidc_callback_spa()
    {
        if (!$this->oidc_enabled) {
            show_error('OIDC authentication is not enabled');
        }

        $this->ci->template->set_template('blank');
        $this->data['title'] = 'Completing login...';

        // Check for popup mode
        $popup_mode = $this->ci->input->get('mode') === 'popup' || $this->ci->input->post('mode') === 'popup';

        // Get callback parameters
        $code = $this->ci->input->get('code');
        $state = $this->ci->input->get('state');
        $error = $this->ci->input->get('error');
        $error_description = $this->ci->input->get('error_description');

        // Pass data to view
        $this->data['code'] = $code;
        $this->data['state'] = $state;
        $this->data['error'] = $error;
        $this->data['error_description'] = $error_description;
        $this->data['popup_mode'] = $popup_mode;
        $this->data['callback_api_url'] = site_url('auth/oidc_callback_api');
        $this->data['login_url'] = $popup_mode ? site_url('auth/login?mode=popup') : site_url('auth/login');
        $this->data['home_url'] = $popup_mode ? site_url('auth/login_success?mode=popup') : $this->ci->config->item('base_url');

        $content = $this->ci->load->view('auth/oidc_callback_spa', $this->data, TRUE);
        $this->ci->template->write('content', $content, true);
        $this->ci->template->write('title', $this->data['title'], true);
        $this->ci->template->render();
    }
}

//end-class

