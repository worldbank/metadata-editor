<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH . '/libraries/MY_REST_Controller.php');

/**
 * Issues API
 *
 * Manages project metadata issues
 * 
 * Endpoints:
 * - GET    /api/issues                     - List all issues (filtered by user permissions)
 * - GET    /api/issues/{id}                - Get single issue
 * - GET    /api/issues/project/{sid}           - List issues for a project
 * - GET    /api/issues/project/{sid}/summary  - Minimal list of open issues (id, title, field_path, status) for counts/badges
 * - GET    /api/issues/project/{sid}/assessment_status - Latest metadata assessment job for the project
 * - GET    /api/issues/project/{sid}/stats    - Get issue statistics
 * - POST   /api/issues                     - Create new issue
 * - PUT    /api/issues/{id}                - Update issue
 * - DELETE /api/issues/{id}                - Delete issue
 * - POST   /api/issues/delete/{id}         - Delete issue (POST alias)
 * - POST   /api/issues/{id}/apply          - Apply suggested changes
 * - POST   /api/issues/{id}/status         - Update status
 * - POST   /api/issues/bulk_status         - Bulk update status
 * - POST   /api/issues/bulk_delete         - Bulk delete issues
 */
class Issues extends MY_REST_Controller {

    private $api_user;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Project_issues_model');
        $this->load->model('Editor_model');
        $this->load->library('Editor_acl');
        $this->load->helper('user_access');
        $this->is_authenticated_or_die();
        $this->api_user = $this->api_user();
        $this->api_user_id = $this->get_api_user_id();
    }

    public function _auth_override_check()
    {
        if ($this->session->userdata('user_id')) {
            return true;
        }
        parent::_auth_override_check();
    }

    /**
     * Get issues
     * GET /api/issues/{id} - Get single issue
     * GET /api/issues - List all issues (filtered by user permissions)
     */
    public function index_get($id = null)
    {
        try {
            // If ID is provided, get single issue
            if ($id) {
                $issue = $this->Project_issues_model->get_by_id($id);
                
                if (!$issue) {
                    throw new Exception('Issue not found');
                }

                // Check project access
                $this->editor_acl->user_has_project_access($issue['project_id'], 'view', $this->api_user);

                $response = array(
                    'status' => 'success',
                    'issue'  => $issue,
                );
                $this->set_response($response, REST_Controller::HTTP_OK);
                return;
            }

            // No ID provided - list all issues with permissions filtering
            $this->has_access($resource_ = 'editor', $privilege = 'view');

            $filters = array();
            
            // Status filter (can be comma-separated)
            if ($this->input->get('status')) {
                $status = $this->input->get('status');
                $filters['status'] = strpos($status, ',') !== false 
                    ? explode(',', $status) 
                    : $status;
            }

            // Category filter
            if ($this->input->get('category')) {
                $filters['category'] = $this->input->get('category');
            }

            // Severity filter
            if ($this->input->get('severity')) {
                $filters['severity'] = $this->input->get('severity');
            }

            // Applied filter
            if ($this->input->get('applied') !== null && $this->input->get('applied') !== '') {
                $filters['applied'] = (int) $this->input->get('applied');
            }

            // Scope filter (open | closed)
            if ($this->input->get('scope')) {
                $filters['scope'] = $this->input->get('scope');
            }

            // Field path filter
            if ($this->input->get('field_path')) {
                $filters['field_path'] = $this->input->get('field_path');
            }

            // Issue ID filter (exact match)
            if ($this->input->get('id') !== null && $this->input->get('id') !== '') {
                $filters['id'] = (int) $this->input->get('id');
            }

            // Project ID filter (exact match)
            if ($this->input->get('project_id') !== null && $this->input->get('project_id') !== '') {
                $filters['project_id'] = (int) $this->input->get('project_id');
            }

            // Scope to accessible projects unless user sees all projects
            if (!$this->editor_acl->user_sees_all_projects($this->api_user)) {
                $user_id = $this->api_user_id;
                
                // Get projects owned by user
                $this->db->select('id');
                $this->db->where('created_by', $user_id);
                $owned_projects = $this->db->get('editor_projects')->result_array();
                $project_ids = array_column($owned_projects, 'id');
                
                // Get shared projects
                $this->db->select('DISTINCT sid');
                $this->db->where('user_id', $user_id);
                $shared_projects = $this->db->get('editor_project_owners')->result_array();
                $shared_ids = array_column($shared_projects, 'sid');
                
                // Projects from collections user has access to (including inherited ACL)
                $this->load->helper('collection_acl');
                $collection_projects = $this->db->query(
                    collection_acl_sql_project_ids_for_user($user_id)
                )->result_array();
                $collection_ids = array_column($collection_projects, 'sid');
                
                // Merge all accessible project IDs
                $filters['project_ids'] = array_unique(array_merge($project_ids, $shared_ids, $collection_ids));
                
                // If user has no accessible projects, return empty result
                if (empty($filters['project_ids'])) {
                    $response = array(
                        'status'  => 'success',
                        'total'   => 0,
                        'issues'  => array(),
                        'offset'  => 0,
                        'limit'   => 50,
                    );
                    $this->set_response($response, REST_Controller::HTTP_OK);
                    return;
                }
            }

            $limit = $this->input->get('limit') ? (int) $this->input->get('limit') : 50;
            $offset = $this->input->get('offset') ? (int) $this->input->get('offset') : 0;
            $sort_by = $this->input->get('sort_by') ?: 'created';
            $sort_order = $this->input->get('sort_order') ?: 'DESC';

            $result = $this->Project_issues_model->get_all(
                $filters, 
                $limit, 
                $offset,
                $sort_by,
                $sort_order
            );

            $response = array(
                'status'  => 'success',
                'total'   => $result['total'],
                'issues'  => $result['issues'],
                'offset'  => $result['offset'],
                'limit'   => $result['limit'],
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get issues for a project
     * GET /api/issues/project/{sid}?status=open&category=Typo&limit=50&offset=0&sort_by=created&sort_order=DESC
     */
    public function project_get($sid = null)
    {
        try {
            $sid = $this->get_sid($sid);
            $this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);

            $filters = array();
            
            // Status filter (can be comma-separated)
            if ($this->input->get('status')) {
                $status = $this->input->get('status');
                $filters['status'] = strpos($status, ',') !== false 
                    ? explode(',', $status) 
                    : $status;
            }

            // Category filter
            if ($this->input->get('category')) {
                $filters['category'] = $this->input->get('category');
            }

            // Severity filter
            if ($this->input->get('severity')) {
                $filters['severity'] = $this->input->get('severity');
            }

            // Applied filter
            if ($this->input->get('applied') !== null && $this->input->get('applied') !== '') {
                $filters['applied'] = (int) $this->input->get('applied');
            }

            // Scope filter (open | closed)
            if ($this->input->get('scope')) {
                $filters['scope'] = $this->input->get('scope');
            }

            // Field path filter
            if ($this->input->get('field_path')) {
                $filters['field_path'] = $this->input->get('field_path');
            }

            $limit = $this->input->get('limit') ? (int) $this->input->get('limit') : 50;
            $offset = $this->input->get('offset') ? (int) $this->input->get('offset') : 0;
            $sort_by = $this->input->get('sort_by') ?: 'created';
            $sort_order = $this->input->get('sort_order') ?: 'DESC';

            $result = $this->Project_issues_model->get_by_project(
                $sid, 
                $filters, 
                $limit, 
                $offset,
                $sort_by,
                $sort_order
            );

            $response = array(
                'status'  => 'success',
                'total'   => $result['total'],
                'issues'  => $result['issues'],
                'offset'  => $result['offset'],
                'limit'   => $result['limit'],
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get minimal list of open issues for a project (id, title, field_path, status).
     * Only open issues; for counts and badges without full payload.
     * GET /api/issues/project/{sid}/summary?limit=500
     */
    public function project_summary_get($sid = null)
    {
        try {
            $sid = $this->get_sid($sid);
            $this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);

            $limit = $this->input->get('limit') ? (int) $this->input->get('limit') : 500;
            $result = $this->Project_issues_model->get_open_summary_by_project($sid, $limit);

            $response = array(
                'status' => 'success',
                'total'  => $result['total'],
                'issues' => $result['issues'],
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get issue statistics for a project
     * GET /api/issues/project/{sid}/stats
     */
    public function stats_get($sid = null)
    {
        try {
            $sid = $this->get_sid($sid);
            $this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);

            $stats = $this->Project_issues_model->get_project_stats($sid);

            $response = array(
                'status' => 'success',
                'stats'  => $stats,
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get metadata assessment job status for a project (latest job of type metadata_assessment_result).
     * GET /api/issues/project/{sid}/assessment_status
     * Returns the single latest assessment job for the project so the UI can show "Assessment running" or poll by uuid.
     */
    public function assessment_status_get($sid = null)
    {
        try {
            $sid = $this->get_sid($sid);
            $this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);

            $this->load->model('Job_queue_model');
            $filters = array(
                'job_type'   => 'metadata_assessment_result',
                'project_id' => (int) $sid,
            );
            if (!$this->editor_acl->user_sees_all_projects($this->api_user)) {
                $filters['user_id'] = $this->api_user_id;
            }
            $jobs = $this->Job_queue_model->get_all($filters, 1, 0);

            $job = !empty($jobs) ? $jobs[0] : null;
            $assessment_job = null;
            if ($job) {
                $assessment_job = array(
                    'uuid'         => isset($job['uuid']) ? $job['uuid'] : null,
                    'job_type'     => $job['job_type'],
                    'status'       => $job['status'],
                    'created_at'   => $job['created_at'],
                    'started_at'   => $job['started_at'],
                    'completed_at' => $job['completed_at'],
                    'error_message' => $job['error_message'],
                );
                if (!empty($job['result'])) {
                    $assessment_job['result'] = $job['result'];
                }
            }

            $response = array(
                'status'         => 'success',
                'assessment_job' => $assessment_job,
                'metadata_assessment_enabled' => metadata_assessment_enabled(),
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Create a new issue
     * POST /api/issues
     * Body: {
     *   "project_id": 123,
     *   "title": "Issue title",
     *   "description": "...",
     *   "category": "Typo / Wording",
     *   "field_path": "series_description.methodology",
     *   "severity": "medium",
     *   "current_metadata": {...},
     *   "suggested_metadata": {...},
     *   "source": "manual"
     * }
     */
    public function index_post()
    {
        try {
            $input = $this->raw_json_input();
            
            if (empty($input)) {
                throw new Exception('Request body is required');
            }

            if (empty($input['project_id'])) {
                throw new Exception('project_id is required');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($input['project_id'], 'edit', $this->api_user);

            // Set created_by
            $input['created_by'] = $this->api_user_id;

            $issue_id = $this->Project_issues_model->create($input);
            $issue = $this->Project_issues_model->get_by_id($issue_id);

            $response = array(
                'status'  => 'success',
                'message' => 'Issue created successfully',
                'issue'   => $issue,
            );
            $this->set_response($response, REST_Controller::HTTP_CREATED);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update an issue
     * PUT /api/issues/{id}
     * Body: {
     *   "title": "Updated title",
     *   "description": "...",
     *   "category": "...",
     *   "severity": "...",
     *   "assigned_to": 5,
     *   "notes": "..."
     * }
     */
    public function index_put($id = null)
    {
        try {
            if (!$id) {
                throw new Exception('Issue ID is required');
            }

            $issue = $this->Project_issues_model->get_by_id($id);
            if (!$issue) {
                throw new Exception('Issue not found');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($issue['project_id'], 'edit', $this->api_user);

            // Closed issues are read-only
            $closed_statuses = array('fixed', 'rejected', 'dismissed', 'false_positive');
            if (in_array($issue['status'], $closed_statuses)) {
                throw new Exception('This issue is closed and cannot be edited');
            }

            $input = $this->raw_json_input();
            if (empty($input)) {
                throw new Exception('Request body is required');
            }

            // When setting applied=1, set applied_on and applied_by if not provided
            if (isset($input['applied']) && (int) $input['applied'] === 1) {
                if (!isset($input['applied_on'])) {
                    $input['applied_on'] = time();
                }
                if (!isset($input['applied_by']) && $this->api_user_id) {
                    $input['applied_by'] = $this->api_user_id;
                }
            }

            $this->Project_issues_model->update($id, $input);
            $updated_issue = $this->Project_issues_model->get_by_id($id);

            $response = array(
                'status'  => 'success',
                'message' => 'Issue updated successfully',
                'issue'   => $updated_issue,
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete an issue
     * DELETE /api/issues/{id}
     */
    public function index_delete($id = null)
    {
        try {
            if (!$id) {
                throw new Exception('Issue ID is required');
            }

            $issue = $this->Project_issues_model->get_by_id($id);
            if (!$issue) {
                throw new Exception('Issue not found');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($issue['project_id'], 'edit', $this->api_user);

            $this->Project_issues_model->delete($id);

            $response = array(
                'status'  => 'success',
                'message' => 'Issue deleted successfully',
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete an issue (POST alias)
     * POST /api/issues/delete/{id}
     */
    public function delete_post($id = null)
    {
        try {
            if (!$id) {
                throw new Exception('Issue ID is required');
            }

            $issue = $this->Project_issues_model->get_by_id($id);
            if (!$issue) {
                throw new Exception('Issue not found');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($issue['project_id'], 'edit', $this->api_user);

            $this->Project_issues_model->delete($id);

            $response = array(
                'status'  => 'success',
                'message' => 'Issue deleted successfully',
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Apply suggested metadata changes
     * POST /api/issues/apply/{id}
     */
    public function apply_post($id = null)
    {
        try {
            if (!$id) {
                throw new Exception('Issue ID is required');
            }

            $issue = $this->Project_issues_model->get_by_id($id);
            if (!$issue) {
                throw new Exception('Issue not found');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($issue['project_id'], 'edit', $this->api_user);

            if (empty($issue['suggested_metadata'])) {
                throw new Exception('No suggested metadata to apply');
            }

            // Get current project metadata
            $project = $this->Editor_model->get_row($issue['project_id']);
            if (!$project) {
                throw new Exception('Project not found');
            }

            $metadata = json_decode($project['metadata'], true);
            if (!$metadata) {
                $metadata = array();
            }

            // Apply changes from suggested_metadata
            foreach ($issue['suggested_metadata'] as $field_path => $new_value) {
                $metadata = $this->_update_metadata_by_path($metadata, $field_path, $new_value);
            }

            // Update project metadata
            $this->Editor_model->update($issue['project_id'], array(
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ));

            // Mark issue as applied
            $this->Project_issues_model->mark_as_applied($id, $this->api_user_id);

            $updated_issue = $this->Project_issues_model->get_by_id($id);

            $response = array(
                'status'  => 'success',
                'message' => 'Changes applied successfully',
                'issue'   => $updated_issue,
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update issue status
     * POST /api/issues/status/{id}
     * Body: { "status": "rejected" }
     */
    public function status_post($id = null)
    {
        try {
            if (!$id) {
                throw new Exception('Issue ID is required');
            }

            $issue = $this->Project_issues_model->get_by_id($id);
            if (!$issue) {
                throw new Exception('Issue not found');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($issue['project_id'], 'edit', $this->api_user);

            $input = (array)$this->raw_json_input();
            if (empty($input['status'])) {
                throw new Exception('status is required');
            }

            $this->Project_issues_model->update_status($id, $input['status'], $this->api_user_id);
            $updated_issue = $this->Project_issues_model->get_by_id($id);

            $response = array(
                'status'  => 'success',
                'message' => 'Status updated successfully',
                'issue'   => $updated_issue,
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Bulk update status for multiple issues
     * POST /api/issues/bulk_status
     * Body: { "ids": [1, 2, 3], "status": "false_positive" }
     */
    public function bulk_status_post()
    {
        try {
            $input = $this->raw_json_input();
            
            if (empty($input['ids']) || !is_array($input['ids'])) {
                throw new Exception('ids array is required');
            }

            if (empty($input['status'])) {
                throw new Exception('status is required');
            }

            // Get first issue to check project access (assuming all belong to same project)
            $first_issue = $this->Project_issues_model->get_by_id($input['ids'][0]);
            if (!$first_issue) {
                throw new Exception('Issue not found');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($first_issue['project_id'], 'edit', $this->api_user);

            $affected = $this->Project_issues_model->bulk_update_status(
                $input['ids'], 
                $input['status'], 
                $this->api_user_id
            );

            $response = array(
                'status'  => 'success',
                'message' => $affected . ' issue(s) updated',
                'affected' => $affected,
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Bulk delete multiple issues
     * POST /api/issues/bulk_delete
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulk_delete_post()
    {
        try {
            $input = $this->raw_json_input();

            if (empty($input['ids']) || !is_array($input['ids'])) {
                throw new Exception('ids array is required');
            }

            // Get first issue to check project access (assuming all belong to same project)
            $first_issue = $this->Project_issues_model->get_by_id($input['ids'][0]);
            if (!$first_issue) {
                throw new Exception('Issue not found');
            }

            // Check edit access
            $this->editor_acl->user_has_project_access($first_issue['project_id'], 'edit', $this->api_user);

            $affected = $this->Project_issues_model->bulk_delete($input['ids']);

            $response = array(
                'status'   => 'success',
                'message'  => $affected . ' issue(s) deleted',
                'affected' => $affected,
            );
            $this->set_response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->set_response(array(
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ), REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update metadata by field path
     * 
     * @param array $metadata Current metadata
     * @param string $path Field path (e.g., "series_description.methodology")
     * @param mixed $value New value
     * @return array Updated metadata
     */
    private function _update_metadata_by_path($metadata, $path, $value)
    {
        // Parse path: "series_description.authoring_entity[0].name"
        $parts = $this->_parse_field_path($path);
        
        $current = &$metadata;
        $last_index = count($parts) - 1;

        foreach ($parts as $index => $part) {
            if ($index === $last_index) {
                // Last part - set the value
                $current[$part['key']] = $value;
            } else {
                // Intermediate part - navigate
                if (!isset($current[$part['key']])) {
                    // Create intermediate structure
                    if (isset($parts[$index + 1]['array_index']) && $parts[$index + 1]['array_index'] !== null) {
                        $current[$part['key']] = array();
                    } else {
                        $current[$part['key']] = array();
                    }
                }

                // Handle array access
                if ($part['array_index'] !== null) {
                    $array_index = $part['array_index'];
                    if (!isset($current[$part['key']][$array_index])) {
                        $current[$part['key']][$array_index] = array();
                    }
                    $current = &$current[$part['key']][$array_index];
                } else {
                    $current = &$current[$part['key']];
                }
            }
        }

        return $metadata;
    }

    /**
     * Parse field path into parts
     * 
     * @param string $path Field path
     * @return array Array of parts with 'key' and 'array_index'
     */
    private function _parse_field_path($path)
    {
        $parts = array();
        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            // Check for array notation: field[0]
            if (preg_match('/^([^\[]+)\[(\d+)\]$/', $segment, $matches)) {
                $parts[] = array(
                    'key' => $matches[1],
                    'array_index' => (int) $matches[2]
                );
            } else {
                $parts[] = array(
                    'key' => $segment,
                    'array_index' => null
                );
            }
        }

        return $parts;
    }
}
