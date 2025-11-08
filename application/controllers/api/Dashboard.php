<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Dashboard extends MY_REST_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Dashboard_model');
        $this->load->library('Editor_acl');
        $this->is_authenticated_or_die();
        $this->editor_acl->has_access_or_die($resource_='admin_dashboard',$privilege='view');
    }

    protected function _auth_override_check()
    {
        if ($this->session->userdata('user_id')) {
            return true;
        }
        return parent::_auth_override_check();
    }

    /**
     * 
     * Get dashboard statistics
     * 
     * Query params:
     * - refresh=1 - Force refresh by clearing cache
     */
    public function stats_get()
    {
        try {
            $refresh = $this->get('refresh');
            if ($refresh) {
                $this->Dashboard_model->clear_dashboard_cache();
            }

            $stats = $this->Dashboard_model->get_dashboard_stats();

            $this->set_response([
                'success' => true,
                'data' => $stats
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response([
                'success' => false,
                'error' => 'Failed to fetch dashboard statistics: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get recent activity
     * 
     * Query params:
     * - limit=10 - Number of items to fetch (default: 10)
     */
    public function activity_get()
    {
        try {
            $limit = $this->get('limit') ?: 10;
            $limit = max(1, (int)$limit);

            $activity = $this->Dashboard_model->get_recent_activity($limit);

            $this->set_response([
                'success' => true,
                'data' => $activity
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response([
                'success' => false,
                'error' => 'Failed to fetch recent activity: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 
     * Get project statistics
     * 
     * Query params:
     * - refresh=1 - Force refresh by clearing cache
     */
    public function projects_get()
    {
        try {
            $projects = $this->Dashboard_model->get_project_stats();

            $this->set_response([
                'success' => true,
                'data' => $projects
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response([
                'success' => false,
                'error' => 'Failed to fetch project statistics: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 
     * Get user statistics
     * 
     * Query params:
     * - refresh=1 - Force refresh by clearing cache
     */
    public function users_get()
    {
        try {
            $users = $this->Dashboard_model->get_user_stats();

            $this->set_response([
                'success' => true,
                'data' => $users
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response([
                'success' => false,
                'error' => 'Failed to fetch user statistics: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 
     * Get storage statistics
     * 
     * Query params:
     * - refresh=1 - Force refresh by clearing cache
     */
    public function storage_get()
    {
        try {
            $refresh = $this->get('refresh');
            if ($refresh) {
                $this->Dashboard_model->clear_storage_cache();
            }

            $storage = $this->Dashboard_model->get_storage_stats();

            $this->set_response([
                'success' => true,
                'data' => $storage
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response([
                'success' => false,
                'error' => 'Failed to fetch storage statistics: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get FastAPI status
     * 
     * Query params:
     * - refresh=1 - Force refresh by clearing cache
     */
    public function fastapi_status_get()
    {
        try {
            $status = $this->Dashboard_model->get_fastapi_status();

            $this->set_response([
                'success' => true,
                'data' => $status
            ], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response([
                'success' => false,
                'error' => 'Failed to fetch FastAPI status: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    /**
     * 
     * Get fastapi running jobs
     * 
     */
    public function fastapi_jobs_get()
    {
        try {
            $jobs = $this->Dashboard_model->get_fastapi_jobs();

            $this->set_response([
                'success' => true,
                'data' => $jobs
            ], REST_Controller::HTTP_OK);
        }
        catch (Exception $e) {
            $this->set_response([
                'success' => false,
                'error' => 'Failed to fetch FastAPI jobs: ' . $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
        
}
