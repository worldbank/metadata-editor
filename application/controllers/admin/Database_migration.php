<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Database_migration extends MY_Controller {
 
    function __construct() 
    {
        parent::__construct();
        $this->template->set_template('admin5');
        $this->lang->load('general');
        
        $is_admin = $this->acl_manager->user_is_admin();
        
        if (!$is_admin) {
            show_error('Access denied');
        }
        
        $this->load->database();
    }
  
    function index()
    {
        $this->load->config('migration');
        
        $data = array();
        $data['page_title'] = 'Database Migrations';
        $data['migration_enabled'] = $this->config->item('migration_enabled');
        $data['current_version'] = $this->get_current_version();
        $data['available_migrations'] = $this->get_available_migrations();
        $data['db_debug_enabled'] = $this->db->db_debug === TRUE;
        
        $content = $this->load->view('admin/database_migration/index', $data, TRUE);
        
        $this->template->write('title', $data['page_title'], TRUE);
        $this->template->write('content', $content, TRUE);
        $this->template->render();
    }
    
    function run($version = null)
    {
        if (!$version) {
            $this->session->set_flashdata('error', 'Migration version required');
            redirect('admin/database_migration');
        }
        
        $this->load->config('migration');
        
        if ($this->config->item('migration_enabled') !== TRUE) {
            $this->session->set_flashdata('error', 'Migrations are disabled. Enable in application/config/migration.php');
            redirect('admin/database_migration');
        }
        
        // Check and disable database debug mode for security
        $db_debug_was_enabled = $this->ensure_db_debug_disabled();
        
        $migration_output = '';
        $migration_success = false;
        $error_message = '';
        $before_version = '';
        $after_version = '';
        
        try {
            // Start output buffering to capture migration output (must be active
            // so migration echoes don't hit the browser before the theme sends headers)
            ob_start();
            
            $this->load->library('migration');
            
            // Check if migrations table exists, create if not
            if (!$this->db->table_exists('migrations')) {
                $this->load->dbforge();
                $this->dbforge->add_field(array(
                    'version' => array('type' => 'BIGINT', 'constraint' => 20, 'unsigned' => TRUE),
                ));
                $this->dbforge->add_key('version', TRUE);
                $this->dbforge->create_table('migrations', TRUE);
            }
            
            // CodeIgniter migrations require the table to have at least one row
            // If empty, initialize with version 0
            $query = $this->db->get('migrations');
            if ($query->num_rows() == 0) {
                $this->db->insert('migrations', array('version' => 0));
            }
            
            $before_version = $this->get_current_version();
            
            if ($version === 'latest') {
                $result = $this->migration->latest();
            } else {
                $result = $this->migration->version($version);
            }
            
            $after_version = $this->get_current_version();
            
            // Get captured output (clean the buffer and get contents)
            if (ob_get_level() > 0) {
                $migration_output = ob_get_clean();
            }
            
            if ($result === FALSE) {
                $error_message = 'Migration failed: ' . $this->migration->error_string();
                $migration_success = false;
            } else {
                $migration_success = true;
            }
        } catch (Exception $e) {
            // Get any output before exception
            if (ob_get_level() > 0) {
                $migration_output = ob_get_clean();
            }
            $error_message = 'Migration error: ' . $e->getMessage();
            $migration_success = false;
        }
        
        // Prepare data for view
        $data = array();
        $data['page_title'] = 'Migration Output';
        $data['version'] = $version;
        $data['migration_output'] = $migration_output;
        $data['migration_success'] = $migration_success;
        $data['error_message'] = $error_message;
        $data['before_version'] = $before_version;
        $data['after_version'] = $after_version;
        $data['db_debug_was_enabled'] = $db_debug_was_enabled;
        
        // Load view instead of redirecting
        $content = $this->load->view('admin/database_migration/run_output', $data, TRUE);
        
        $this->template->write('title', $data['page_title'], TRUE);
        $this->template->write('content', $content, TRUE);
        $this->template->render();
    }
    
    /**
     * Mark migrations as applied through the given version without running SQL.
     * Use when upgrades were applied manually outside this tool.
     */
    function mark_applied($version = null)
    {
        if (!$version) {
            $this->session->set_flashdata('error', 'Version number required');
            redirect('admin/database_migration');
        }

        if (!$this->is_valid_migration_version($version)) {
            $this->session->set_flashdata('error', 'Invalid version format. Expected 14-digit timestamp (e.g., 20251022000001)');
            redirect('admin/database_migration');
        }

        if (!$this->migration_version_exists($version)) {
            $this->session->set_flashdata('error', 'Migration version not found: ' . $version);
            redirect('admin/database_migration');
        }

        $current_version = $this->get_current_version();

        if ((int)$version <= (int)$current_version) {
            $this->session->set_flashdata('error', 'Version ' . $version . ' is already marked as applied (current: ' . $current_version . ')');
            redirect('admin/database_migration');
        }

        $this->change_stored_version('mark_applied', $version, $version);
        redirect('admin/database_migration');
    }

    /**
     * Unmark a migration and all later migrations by lowering the stored version
     * to the migration immediately before the target.
     */
    function unmark($version = null)
    {
        if (!$version) {
            $this->session->set_flashdata('error', 'Version number required');
            redirect('admin/database_migration');
        }

        if (!$this->is_valid_migration_version($version)) {
            $this->session->set_flashdata('error', 'Invalid version format. Expected 14-digit timestamp (e.g., 20251022000001)');
            redirect('admin/database_migration');
        }

        if (!$this->migration_version_exists($version)) {
            $this->session->set_flashdata('error', 'Migration version not found: ' . $version);
            redirect('admin/database_migration');
        }

        $current_version = $this->get_current_version();

        if ((int)$version > (int)$current_version) {
            $this->session->set_flashdata('error', 'Version ' . $version . ' is not marked as applied (current: ' . $current_version . ')');
            redirect('admin/database_migration');
        }

        $previous_version = $this->get_previous_version($version);
        $this->change_stored_version('unmark', $version, $previous_version);
        redirect('admin/database_migration');
    }

    /**
     * Directly set the stored migration version (CLI parity / advanced use).
     */
    function set_version($version = null)
    {
        if (!$version) {
            $this->session->set_flashdata('error', 'Version number required');
            redirect('admin/database_migration');
        }

        if (!$this->is_valid_migration_version($version)) {
            $this->session->set_flashdata('error', 'Invalid version format. Expected 14-digit timestamp (e.g., 20251022000001)');
            redirect('admin/database_migration');
        }

        $this->change_stored_version('set_version', $version, $version);
        redirect('admin/database_migration');
    }

    private function change_stored_version($action, $target_version, $new_version)
    {
        try {
            $this->ensure_migrations_table();

            $before_version = $this->get_current_version();

            $this->db->truncate('migrations');
            $result = $this->db->insert('migrations', array('version' => $new_version));

            if (!$result) {
                $this->session->set_flashdata('error', 'Failed to update migration version');
                return;
            }

            $this->log_version_change($action, $target_version, $before_version, (string)$new_version);

            if ($action === 'mark_applied') {
                $message = 'Marked migrations as applied through version ' . $target_version
                    . ' (previous: ' . $before_version . ', now: ' . $new_version . ')';
            } elseif ($action === 'unmark') {
                $message = 'Unmarked from version ' . $target_version
                    . '. Migrations after ' . ($new_version === '0' ? 'none' : $new_version)
                    . ' are pending again (previous: ' . $before_version . ', now: ' . $new_version . ')';
            } else {
                $message = 'Migration version set to ' . $new_version . ' (previous: ' . $before_version . ')';
            }

            $this->session->set_flashdata('message', $message);
        } catch (Exception $e) {
            $this->session->set_flashdata('error', 'Error updating migration version: ' . $e->getMessage());
        }
    }

    private function ensure_migrations_table()
    {
        if (!$this->db->table_exists('migrations')) {
            $this->load->dbforge();
            $this->dbforge->add_field(array(
                'version' => array('type' => 'BIGINT', 'constraint' => 20, 'unsigned' => TRUE),
            ));
            $this->dbforge->add_key('version', TRUE);
            $this->dbforge->create_table('migrations', TRUE);
        }
    }

    private function log_version_change($action, $target_version, $before_version, $after_version)
    {
        $user = $this->ion_auth->current_user();
        $user_label = $user ? ($user->email ? $user->email : 'user#' . $user->id) : 'unknown';

        log_message(
            'info',
            'Database migration version ' . $action
            . ' by ' . $user_label
            . ' target=' . $target_version
            . ' before=' . $before_version
            . ' after=' . $after_version
        );
    }

    private function is_valid_migration_version($version)
    {
        return (bool)preg_match('/^\d{14}$/', $version);
    }

    private function migration_version_exists($version)
    {
        foreach ($this->get_available_migrations() as $migration) {
            if ($migration['version'] === $version) {
                return true;
            }
        }

        return false;
    }

    private function get_previous_version($version)
    {
        $versions = array();
        foreach ($this->get_available_migrations() as $migration) {
            $versions[] = $migration['version'];
        }

        $index = array_search($version, $versions, true);

        if ($index === false || $index === 0) {
            return '0';
        }

        return $versions[$index - 1];
    }

    private function get_current_version()
    {
        if (!$this->db->table_exists('migrations')) {
            return '0';
        }
        
        // Get the latest version (in case there are multiple rows)
        $this->db->select('version');
        $this->db->order_by('version', 'DESC');
        $this->db->limit(1);
        $query = $this->db->get('migrations');
        $row = $query->row();
        
        return $row ? (string)$row->version : '0';
    }
    
    private function get_available_migrations()
    {
        $migrations = array();
        $migration_path = APPPATH . 'migrations/';
        
        if (!is_dir($migration_path)) {
            return $migrations;
        }
        
        $files = scandir($migration_path);
        
        foreach ($files as $file) {
            if (preg_match('/^(\d{14})_(.+)\.php$/', $file, $matches)) {
                $migrations[] = array(
                    'version' => $matches[1],
                    'name' => ucwords(str_replace('_', ' ', $matches[2])),
                    'file' => $file
                );
            }
        }

        usort($migrations, function ($a, $b) {
            return strcmp($a['version'], $b['version']);
        });
        
        return $migrations;
    }
    
    /**
     * Ensure database debug mode is disabled for security during migrations
     * 
     * Database debug mode can expose sensitive information in error messages.
     * This method checks if it's enabled and disables it for the migration session.
     * 
     * @return bool Returns TRUE if debug was enabled and got disabled
     */
    private function ensure_db_debug_disabled()
    {
        if ($this->db->db_debug === TRUE) {
            log_message('info', 'Database debug mode was enabled, disabling for migration session');
            
            // Disable for this session only
            $this->db->db_debug = FALSE;
            
            return TRUE;
        }
        
        return FALSE;
    }
}

