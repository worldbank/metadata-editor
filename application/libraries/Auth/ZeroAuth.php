<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once 'application/libraries/Auth/AuthInterface.php';

/**
 *
 * ZeroAuth - Local/desktop mode (no password, one-click login)
 *
 */
class ZeroAuth implements AuthInterface
{
    protected $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->assert_zero_auth_allowed();
        $this->ci->load->library('ion_auth');
        $this->ci->load->library('session');
        $this->ci->load->library('form_validation');
        $this->ci->load->database();
        $this->ci->load->helper('url');
        $this->ci->load->library('acl_manager');
        $this->ci->template->set_template('default');
        $this->ci->lang->load('general');
        $this->ci->lang->load('users');
    }

    /**
     * ZeroAuth config with safe defaults.
     */
    private function get_zero_auth_config()
    {
        $config = $this->ci->config->item('zero_auth');
        if (!is_array($config)) {
            $config = array();
        }

        $allowed_hosts = array('localhost', '127.0.0.1');
        if (isset($config['allowed_hosts']) && is_array($config['allowed_hosts'])) {
            $allowed_hosts = $config['allowed_hosts'];
        }

        return array(
            'enabled'       => !empty($config['enabled']),
            'admin_email'   => isset($config['admin_email']) ? $config['admin_email'] : 'local-admin@localhost',
            'admin_name'    => isset($config['admin_name']) ? $config['admin_name'] : 'Local Administrator',
            'allowed_hosts' => $allowed_hosts,
        );
    }

    /**
     * Block ZeroAuth unless explicitly enabled for an allowed local host.
     */
    private function assert_zero_auth_allowed()
    {
        $conf = $this->get_zero_auth_config();

        if (!$conf['enabled']) {
            show_error('ZeroAuth is disabled. Set zero_auth.enabled to true for local/desktop use only.', 403);
        }

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            show_error('ZeroAuth is not available in production.', 403);
        }

        $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        $host = preg_replace('/:\d+$/', '', $host);

        if (!empty($conf['allowed_hosts'])) {
            $allowed = false;
            foreach ($conf['allowed_hosts'] as $allowed_host) {
                if (strtolower((string)$allowed_host) === $host) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                show_error('ZeroAuth is not available on this host.', 403);
            }
        }
    }

    /**
     * Resolve the local admin account without creating one.
     */
    private function resolve_zero_auth_user()
    {
        $conf = $this->get_zero_auth_config();

        $user = $this->get_user_by_authtype('zero_auth');
        if ($user) {
            return $user;
        }

        $user = $this->ci->ion_auth->get_user_by_email($conf['admin_email']);
        if ($user) {
            $this->mark_user_as_zero_auth($user->id);
            return $user;
        }

        $user = $this->find_sole_site_admin_user();
        if ($user) {
            $this->mark_user_as_zero_auth($user->id);
            return $user;
        }

        return null;
    }

    private function get_user_by_authtype($authtype)
    {
        $row = $this->ci->db
            ->where('authtype', $authtype)
            ->where('active', 1)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get('users')
            ->row();

        if (!$row) {
            return null;
        }

        return $this->ci->ion_auth->get_user($row->id);
    }

    private function find_sole_site_admin_user()
    {
        $admin_role_ids = $this->ci->acl_manager->get_admin_role_ids();
        if (empty($admin_role_ids)) {
            return null;
        }

        $this->ci->db->select('users.id');
        $this->ci->db->from('users');
        $this->ci->db->join('user_roles', 'user_roles.user_id = users.id');
        $this->ci->db->where_in('user_roles.role_id', $admin_role_ids);
        $this->ci->db->where('users.active', 1);
        $this->ci->db->group_by('users.id');
        $rows = $this->ci->db->get()->result_array();

        if (count($rows) !== 1) {
            return null;
        }

        return $this->ci->ion_auth->get_user((int)$rows[0]['id']);
    }

    private function mark_user_as_zero_auth($user_id)
    {
        $this->ci->db
            ->where('id', (int)$user_id)
            ->update('users', array('authtype' => 'zero_auth'));
    }

    private function sync_admin_display_name($user, $name)
    {
        if (!$user || trim($name) === '') {
            return;
        }

        $updates = array();
        if (!isset($user->first_name) || $user->first_name !== $name) {
            $updates['first_name'] = $name;
        }
        if (!isset($user->identity) || $user->identity !== $name) {
            $updates['identity'] = $name;
        }

        if (!empty($updates)) {
            $this->ci->ion_auth->update_user($user->id, $updates);
        }
    }

    /**
     * Ensure the ZeroAuth admin user exists and has admin access; create and assign if not.
     */
    private function ensure_admin_user()
    {
        $conf = $this->get_zero_auth_config();
        $user = $this->resolve_zero_auth_user();

        if (!$user) {
            $email = $conf['admin_email'];
            $name = $conf['admin_name'];
            $username = $name;
            $password = bin2hex(random_bytes(32));
            $additional_data = array(
                'first_name' => $name,
                'last_name'  => '',
                'identity'   => $name,
                'email'      => $email,
            );
            $this->ci->ion_auth->register($username, $password, $email, $additional_data, false, 'zero_auth');
            $user = $this->resolve_zero_auth_user();
        }

        if ($user) {
            $this->sync_admin_display_name($user, $conf['admin_name']);

            try {
                if (!$this->ci->acl_manager->user_is_admin($user)) {
                    $admin_role = $this->ci->acl_manager->get_role_by_name('admin');
                    if (!empty($admin_role['id'])) {
                        $this->ci->acl_manager->set_user_role($user->id, (int) $admin_role['id']);
                    }
                }
            } catch (Exception $e) {
                $admin_role = $this->ci->acl_manager->get_role_by_name('admin');
                if (!empty($admin_role['id'])) {
                    $this->ci->acl_manager->set_user_role($user->id, (int) $admin_role['id']);
                }
            }
        }

        return $user;
    }

    /**
     * Set session for the local admin user (no password authentication).
     */
    private function set_local_admin_session($user)
    {
        $this->ci->load->model('ion_auth_model');
        $this->ci->ion_auth_model->update_last_login($user->id);

        $identity = $this->ci->config->item('identity');
        if ($identity) {
            $this->ci->session->set_userdata($identity, $user->{$identity});
        }
        $this->ci->session->set_userdata('email', $user->email);
        $this->ci->session->set_userdata('username', $user->username);
        $this->ci->session->set_userdata('user_id', $user->id);
    }

    function index()
    {
        redirect('auth/login');
    }

    function login()
    {
        if ($this->ci->input->get('isajax')) {
            return $this->login_ajax();
        }

        $this->ci->template->set_template('default');
        $this->data['title'] = t('login');

        $popup_mode = $this->ci->input->get('mode') === 'popup' || $this->ci->input->post('mode') === 'popup';
        if ($popup_mode) {
            $this->ci->template->set_template('blank');
        }

        $do_login = $this->ci->input->post('zero_auth_login') === '1' || $this->ci->input->get('do') === 'login';

        if ($do_login) {
            $user = $this->ensure_admin_user();

            if ($user) {
                $this->set_local_admin_session($user);
                if ($popup_mode) {
                    redirect('auth/login_success?mode=popup', 'refresh');
                }
                $destination = $this->ci->session->userdata('destination');
                if ($destination !== '' && $destination !== null) {
                    $this->ci->session->unset_userdata('destination');
                    redirect($destination, 'refresh');
                }
                redirect($this->ci->config->item('base_url'), 'refresh');
            }

            $this->ci->session->set_flashdata('error', t('local_mode_login_failed'));
        }

        $conf = $this->get_zero_auth_config();
        $this->data['error'] = $this->ci->session->flashdata('error');
        $this->data['popup_mode'] = $popup_mode;
        $this->data['local_admin_name'] = $conf['admin_name'];
        if ($popup_mode) {
            $this->data['show_default_login'] = false;
            $this->data['show_oidc_button'] = false;
        }

        $content = $this->ci->load->view('auth/login_zero', $this->data, true);
        $this->ci->template->write('content', $content, true);
        $this->ci->template->write('title', $this->data['title'], true);
        $this->ci->template->render();
    }

    function login_ajax()
    {
        $user = $this->ensure_admin_user();

        if ($user) {
            $this->set_local_admin_session($user);
            $this->json_response(array('status' => 'success'), 200);
        }
        $this->json_response(array('status' => 'error', 'message' => 'Login failed'), 401);
    }

    private function json_response($body, $status_code = 200)
    {
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body);
        die();
    }

    function login_success()
    {
        if (!$this->ci->ion_auth->logged_in()) {
            $popup_mode = $this->ci->input->get('mode') === 'popup';
            redirect($popup_mode ? 'auth/login?mode=popup' : 'auth/login', 'refresh');
        }
        $this->ci->template->set_template('blank');
        $this->data['title'] = 'Login Successful';
        $this->data['popup_mode'] = $this->ci->input->get('mode') === 'popup';
        $content = $this->ci->load->view('auth/login_success', $this->data, true);
        $this->ci->template->write('content', $content, true);
        $this->ci->template->write('title', $this->data['title'], true);
        $this->ci->template->render();
    }

    function logout()
    {
        $this->ci->ion_auth->logout();
        redirect('', 'refresh');
    }

    function profile()
    {
        $this->disable_page_cache();
        $this->_is_logged_in();

        $data['user'] = $this->ci->ion_auth->get_user($this->ci->session->userdata('user_id'));
        $data['api_keys'] = $this->ci->ion_auth->get_api_keys($this->ci->session->userdata('user_id'));
        $content = $this->ci->load->view('auth/profile_view', $data, true);

        $this->ci->template->write('title', t('profile'), true);
        $this->ci->template->write('content', $content, true);
        $this->ci->template->render();
    }

    private function disable_page_cache()
    {
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }

    function _is_logged_in()
    {
        $destination = $this->ci->uri->uri_string();
        $this->ci->session->set_userdata('destination', $destination);
        if (!$this->ci->ion_auth->logged_in()) {
            redirect('auth/login/?destination=' . urlencode($destination), 'refresh');
        }
    }

    function edit_profile()
    {
        $this->disable_page_cache();
        $this->_is_logged_in();
        $this->ci->load->library('Nada_csrf');
        $csrf = $this->ci->nada_csrf->generate_token();
        $data['user'] = $this->ci->ion_auth->get_user($this->ci->session->userdata('user_id'));

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
            $this->ci->ion_auth->update_user($data['user']->id, $update_data);
            $this->ci->session->set_flashdata('message', t('profile_updated'));
            redirect('auth/profile', 'refresh');
        }

        $data['csrf'] = $csrf;
        $content = $this->ci->load->view('auth/profile_edit', $data, true);
        $this->ci->template->write('title', t('edit_profile'), true);
        $this->ci->template->write('content', $content, true);
        $this->ci->template->render();
    }

    function generate_api_key()
    {
        $this->_is_logged_in();
        $this->ci->ion_auth->set_api_key($this->ci->session->userdata('user_id'));
        redirect('auth/profile', 'refresh');
    }

    function delete_api_key()
    {
        $this->_is_logged_in();
        $this->ci->ion_auth->delete_api_key($this->ci->session->userdata('user_id'), $this->ci->input->get('api_key'));
        redirect('auth/profile', 'refresh');
    }

    function change_password()
    {
        $this->disable_page_cache();
        $this->_is_logged_in();
        $this->ci->load->library('Nada_csrf');
        $csrf = $this->ci->nada_csrf->generate_token();
        $use_complex_password = $this->ci->config->item('require_complex_password');
        $user = $this->ci->ion_auth->get_user($this->ci->session->userdata('user_id'));

        $this->ci->form_validation->set_rules('old', t('old_password'), 'required|max_length[20]|xss_clean');
        $this->ci->form_validation->set_rules('new', t('new_password'), 'required|min_length[' . $this->ci->config->item('min_password_length') . ']|max_length[' . $this->ci->config->item('max_password_length') . ']|matches[new_confirm]|is_complex_password[' . $use_complex_password . ']');
        $this->ci->form_validation->set_rules('new_confirm', t('confirm_new_password'), 'required|max_length[20]');
        $this->ci->form_validation->set_rules('form_token', 'FORM TOKEN', 'trim|callback_validate_token');

        if (!$this->ci->form_validation->run()) {
            $this->data['message'] = (validation_errors()) ? validation_errors() : $this->ci->session->flashdata('message');
            $this->data['old_password'] = array('name' => 'old', 'id' => 'old', 'type' => 'password');
            $this->data['new_password'] = array('name' => 'new', 'id' => 'new', 'type' => 'password');
            $this->data['new_password_confirm'] = array('name' => 'new_confirm', 'id' => 'new_confirm', 'type' => 'password');
            $this->data['user_id'] = array('name' => 'user_id', 'id' => 'user_id', 'type' => 'hidden', 'value' => $user->id);
            $this->data['csrf'] = $csrf;
            $output = $this->ci->load->view('auth/change_password', $this->data, true);
            $this->ci->template->write('content', $output, true);
            $this->ci->template->write('title', t('change_password'), true);
            $this->ci->template->render();
            return;
        }

        $identity = $this->ci->session->userdata($this->ci->config->item('identity'));
        $change = $this->ci->ion_auth->change_password($identity, $this->ci->input->post('old'), $this->ci->input->post('new'));
        if ($change) {
            $this->ci->session->set_flashdata('message', t('password_change_success'));
            redirect('auth/change_password', 'refresh');
        }
        $this->ci->session->set_flashdata('error', t('password_change_failed'));
        redirect('auth/change_password', 'refresh');
    }

    function forgot_password()
    {
        $this->disable_page_cache();
        $this->ci->session->set_flashdata('message', t('local_mode_no_forgot_password'));
        redirect('auth/login', 'refresh');
    }

    function reset_password($code = null)
    {
        redirect('auth/login', 'refresh');
    }

    function activate($id = null, $code = false)
    {
        show_404();
    }

    function deactivate($id)
    {
        show_404();
    }

    function create_user()
    {
        $this->disable_page_cache();
        $this->ci->session->set_flashdata('message', t('local_mode_no_registration'));
        redirect('auth/login', 'refresh');
    }

    function register()
    {
        $this->ci->session->set_flashdata('message', t('local_mode_no_registration'));
        redirect('auth/login', 'refresh');
    }

    function verify_code()
    {
        show_404();
    }

    function send_otp_code()
    {
        show_404();
    }
}
