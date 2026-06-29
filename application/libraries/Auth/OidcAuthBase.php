<?php

require_once 'application/libraries/Auth/AuthInterface.php';
require_once 'application/libraries/Auth/DefaultAuth.php';

/**
 * Abstract base class for OIDC authentication drivers
 * Contains shared methods used by both confidential and public client flows
 */
abstract class OidcAuthBase extends DefaultAuth implements AuthInterface {

    protected $oidc_config;
    protected $oidc_enabled;

    function __construct()
    {
        parent::__construct($skip_auth=TRUE);
        
        $this->oidc_config = $this->ci->config->item('oidc_auth');
        $this->oidc_enabled = !empty($this->oidc_config) && 
                             isset($this->oidc_config['enabled']) && 
                             $this->oidc_config['enabled'] === true;
        
        $this->ci->load->model("Ion_auth_model");
    }

    /**
     * Map OIDC claims to user data
     */
    protected function mapClaimsToUserData($claims)
    {
        $mappings = isset($this->oidc_config['claim_mappings']) 
            ? $this->oidc_config['claim_mappings'] 
            : array(
                'email' => 'email',
                'first_name' => 'given_name',
                'last_name' => 'family_name',
                'username' => 'preferred_username'
            );
        
        $user_data = array();
        
        // Map email (required)
        if (isset($mappings['email']) && isset($claims[$mappings['email']])) {
            $user_data['email'] = $claims[$mappings['email']];
        } elseif (isset($claims['email'])) {
            $user_data['email'] = $claims['email'];
        }
        
        // Map first name
        if (isset($mappings['first_name']) && isset($claims[$mappings['first_name']])) {
            $user_data['first_name'] = $claims[$mappings['first_name']];
        } elseif (isset($claims['given_name'])) {
            $user_data['first_name'] = $claims['given_name'];
        }
        
        // Map last name
        if (isset($mappings['last_name']) && isset($claims[$mappings['last_name']])) {
            $user_data['last_name'] = $claims[$mappings['last_name']];
        } elseif (isset($claims['family_name'])) {
            $user_data['last_name'] = $claims['family_name'];
        }
        
        // Map username (fallback to email)
        if (isset($mappings['username']) && isset($claims[$mappings['username']])) {
            $user_data['username'] = $claims[$mappings['username']];
        } elseif (isset($claims['preferred_username'])) {
            $user_data['username'] = $claims['preferred_username'];
        } else {
            $user_data['username'] = $user_data['email'];
        }
        
        return $user_data;
    }

    /**
     * Login user from OIDC
     */
    protected function login_user_from_oidc($email)
    {
        if (empty($email)) {
            return FALSE;
        }

        $query = $this->ci->db->select('username,email, id, password')
            ->where("email", $email)
            ->where($this->ci->ion_auth->_extra_where)
            ->where('active', 1)
            ->get($this->ci->config->item('tables')['users']);
                  
        $result = $query->row();

        if ($query->num_rows() == 1){
            $this->update_last_login($result->id);
            $this->ci->session->set_userdata('email',  $result->email);
            $this->ci->session->set_userdata('username',  $result->username);
            $this->ci->session->set_userdata('user_id',  $result->id);
            $this->ci->session->set_userdata('id',  $result->id);  // Also set 'id' for compatibility

            // Log the login
            $this->ci->db_logger->write_log('login', $result->email);
            
            return TRUE;
        }
        
        return FALSE;
    }

    /**
     * Register user from OIDC claims
     */
    protected function register_user_from_oidc($user_data)
    {
        $username = isset($user_data['username']) ? $user_data['username'] : $user_data['email'];
        $email = $user_data['email'];
        $password = nada_random_hash(); // Random password since OIDC handles auth
        
        $additional_data = array(
            'first_name' => isset($user_data['first_name']) ? $user_data['first_name'] : '',
            'last_name'  => isset($user_data['last_name']) ? $user_data['last_name'] : '',
            'email'      => $email,
            'identity'   => $username
        );
        
        $user_id = $this->ci->ion_auth_model->register(
            $username, 
            $password, 
            $email, 
            $additional_data, 
            $group_name = 'user', 
            $auth_type = 'OIDC'
        );

        if ($user_id) {
            $this->ci->ion_auth->set_user_default_roles($user_id);
        }
    }

    /**
     * Update last login timestamp
     */
    protected function update_last_login($id)
    {
        $this->ci->load->helper('date');

        if (isset($this->ci->ion_auth) && isset($this->ci->ion_auth->_extra_where)){
            $this->ci->db->where($this->ci->ion_auth->_extra_where);
        }
        
        $this->ci->db->update(
            $this->ci->config->item('tables')['users'], 
            array('last_login' => now()), 
            array('id' => $id)
        );
        
        return $this->ci->db->affected_rows() == 1;
    }

    /**
     * Centralized OIDC error logging
     */
    protected function log_oidc_error($context, $error_message, $user_email = null)
    {
        $log_data = array(
            'context' => $context,
            'error' => $error_message,
            'user_email' => $user_email ?: $this->ci->session->userdata('email'),
            'user_agent' => $this->ci->input->user_agent(),
            'timestamp' => date('Y-m-d H:i:s')
        );
        
        log_message('error', 'OIDC Error [' . $context . ']: ' . $error_message . 
                   ' | User: ' . ($log_data['user_email'] ?: 'anonymous'));
    }

    /**
     * Disable page cache
     */
    protected function disable_page_cache()
    {
        header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Cache-Control: post-check=0, pre-check=0', false );
        header( 'Pragma: no-cache' );
    }

    /**
     * Validate state parameter consistently for both client types
     */
    protected function validate_state($provided_state, $client_type = 'confidential')
    {
        if (!isset($this->oidc_config['validate_state']) || !$this->oidc_config['validate_state']) {
            return true;
        }
        
        $stored_state = null;
        
        if ($client_type === 'confidential') {
            $stored_state = $this->ci->session->userdata('oidc_state');
        } else {
            // For public clients, state validation happens on frontend,
            // but we should still validate it was passed correctly
            if (empty($provided_state)) {
                return false;
            }
            // Additional validation can be added here if needed
            return true;
        }
        
        if (empty($stored_state) || empty($provided_state)) {
            return false;
        }
        
        return hash_equals($stored_state, $provided_state);
    }
    
    /**
     * Set secure session data for OIDC
     *
     * 
     * @param array $user_data User data to store
     * @param array $tokens Token data including id_token
     */
    protected function set_secure_oidc_session($user_data, $tokens = array())
    {
        // Store ID token securely in session (not localStorage)
        if (!empty($tokens['id_token'])) {
            $this->ci->session->set_userdata('oidc_id_token', $tokens['id_token']);
        }
        
        // Set secure cookie flags if enabled in configuration
        if (function_exists('session_set_cookie_params') && 
            isset($this->oidc_config['secure_cookies']) && 
            $this->oidc_config['secure_cookies']) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
    
    /**
     * Enhanced logout with OIDC support
     */
    function logout()
    {
        $this->disable_page_cache();
        $this->data['title'] = t("logout");
        
        // Clear OIDC-specific session data
        $this->ci->session->unset_userdata('oidc_id_token');
        
        // Clear local session
        $logout = $this->ci->ion_auth->logout();
        
        // If OIDC logout endpoint is configured, redirect to provider logout
        if ($this->oidc_enabled && 
            isset($this->oidc_config['logout_endpoint']) && 
            $this->oidc_config['logout_endpoint']) {
            
            try {
                $this->ci->load->library('OidcClient');
                $logout_url = $this->ci->oidcclient->getEndSessionUrl(site_url(''));
                
                if ($logout_url) {
                    redirect($logout_url, 'refresh');
                    return;
                }
            } catch (Exception $e) {
                log_message('error', 'OIDC logout failed: ' . $e->getMessage());
                // Fall through to default logout
            }
        }
        
        // Default logout redirect
        redirect('', 'refresh');
    }
}

//end-class

