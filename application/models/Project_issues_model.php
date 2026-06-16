<?php

/**
 * Project Issues Model
 *
 * Manages issues detected in project metadata
 * Table: project_issues
 */
class Project_issues_model extends CI_Model {

    private $issue_fields = array(
        'project_id',
        'title',
        'description',
        'category',
        'field_path',
        'severity',
        'status',
        'current_metadata',
        'suggested_metadata',
        'source',
        'created_by',
        'created',
        'assigned_to',
        'resolved_by',
        'resolved',
        'applied',
        'applied_by',
        'applied_on',
        'notes'
    );

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get issue by ID
     *
     * @param int $id Issue ID
     * @return array|false Issue row or false
     */
    public function get_by_id($id)
    {
        $this->db->select('project_issues.*, editor_projects.title as project_title,
            u_created.username as created_by_username,
            u_assigned.username as assigned_to_username,
            u_resolved.username as resolved_by_username,
            u_applied.username as applied_by_username');
        $this->db->join('editor_projects', 'editor_projects.id = project_issues.project_id', 'left');
        $this->db->join('users as u_created', 'u_created.id = project_issues.created_by', 'left');
        $this->db->join('users as u_assigned', 'u_assigned.id = project_issues.assigned_to', 'left');
        $this->db->join('users as u_resolved', 'u_resolved.id = project_issues.resolved_by', 'left');
        $this->db->join('users as u_applied', 'u_applied.id = project_issues.applied_by', 'left');
        $this->db->where('project_issues.id', (int) $id);

        $query = $this->db->get('project_issues');

        if ($query === false) {
            log_message('error', 'Database query failed in get_by_id for ID: ' . $id);
            return false;
        }

        $row = $query->row_array();

        if ($row) {
            $row = $this->_decode_json_fields($row);
        }

        return $row ?: false;
    }

    /**
     * Get all issues for a project
     *
     * @param int $project_id Project ID
     * @param array $filters Optional filters: status, category, severity, applied
     * @param int $limit Limit (default 50)
     * @param int $offset Offset
     * @param string $sort_by Sort column (default: created)
     * @param string $sort_order Sort order (ASC|DESC, default: DESC)
     * @return array Keys: total, issues, offset, limit
     */
    public function get_by_project($project_id, $filters = array(), $limit = 50, $offset = 0, $sort_by = 'created', $sort_order = 'DESC')
    {
        $project_id = (int) $project_id;
        $limit = $limit === null || $limit < 1 ? 50 : (int) $limit;
        $offset = (int) $offset;
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        // Validate sort column
        $allowed_sort = array('id', 'created', 'resolved', 'status', 'severity', 'category');
        if (!in_array($sort_by, $allowed_sort)) {
            $sort_by = 'created';
        }

        // Count total
        $this->db->from('project_issues');
        $this->db->where('project_id', $project_id);
        $this->_apply_filters($filters);
        $total = $this->db->count_all_results();

        // Get issues
        $this->db->from('project_issues');
        $this->db->where('project_id', $project_id);
        $this->_apply_filters($filters);
        $this->db->order_by($sort_by, $sort_order);
        $this->db->limit($limit, $offset);
        $issues = $this->db->get()->result_array();

        // Decode JSON fields
        foreach ($issues as &$issue) {
            $issue = $this->_decode_json_fields($issue);
        }

        return array(
            'total'  => $total,
            'issues' => $issues,
            'offset' => $offset,
            'limit'  => $limit,
        );
    }

    /**
     * Get an issue by project ID and title (exact match).
     * Used to find existing issues for assessment sync (match by detected_issue = title).
     *
     * @param int $project_id Project ID
     * @param string $title Issue title
     * @return array|false Issue row or false
     */
    public function get_by_project_and_title($project_id, $title)
    {
        $project_id = (int) $project_id;
        $title = trim((string) $title);
        if ($title === '') {
            return false;
        }
        $this->db->from('project_issues');
        $this->db->where('project_id', $project_id);
        $this->db->where('title', $title);
        $this->db->limit(1);
        $row = $this->db->get()->row_array();
        if ($row) {
            $row = $this->_decode_json_fields($row);
        }
        return $row ?: false;
    }

    /**
     * Get minimal list of open issues for a project (id, title, field_path, status).
     * Used for counts and badges; only returns open issues.
     *
     * @param int $project_id Project ID
     * @param int $limit Max rows (default 500)
     * @return array Keys: total, issues
     */
    public function get_open_summary_by_project($project_id, $limit = 500)
    {
        $project_id = (int) $project_id;
        $limit = $limit === null || $limit < 1 ? 500 : (int) $limit;

        $this->db->select('id, title, field_path, status');
        $this->db->from('project_issues');
        $this->db->where('project_id', $project_id);
        $this->db->where('status', 'open');
        $this->db->order_by('created', 'DESC');
        $this->db->limit($limit);
        $issues = $this->db->get()->result_array();

        $this->db->from('project_issues');
        $this->db->where('project_id', $project_id);
        $this->db->where('status', 'open');
        $total = $this->db->count_all_results();

        return array(
            'total'  => $total,
            'issues' => $issues,
        );
    }

    /**
     * Get all issues across projects
     *
     * @param array $filters Optional filters: status, category, severity, applied, project_ids
     * @param int $limit Limit (default 50)
     * @param int $offset Offset
     * @param string $sort_by Sort column (default: created)
     * @param string $sort_order Sort order (ASC|DESC, default: DESC)
     * @return array Keys: total, issues, offset, limit
     */
    public function get_all($filters = array(), $limit = 50, $offset = 0, $sort_by = 'created', $sort_order = 'DESC')
    {
        $limit = $limit === null || $limit < 1 ? 50 : (int) $limit;
        $offset = (int) $offset;
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        // Validate sort column
        $allowed_sort = array('id', 'created', 'resolved', 'status', 'severity', 'category', 'project_id');
        if (!in_array($sort_by, $allowed_sort)) {
            $sort_by = 'created';
        }

        // Count total
        $this->db->from('project_issues');
        $this->_apply_filters($filters);

        // Filter by project IDs if provided (for permissions)
        if (!empty($filters['project_ids']) && is_array($filters['project_ids'])) {
            $this->db->where_in('project_id', $filters['project_ids']);
        }

        $total = $this->db->count_all_results();

        // Get issues
        $this->db->select('project_issues.*, editor_projects.title as project_title');
        $this->db->from('project_issues');
        $this->db->join('editor_projects', 'editor_projects.id = project_issues.project_id', 'left');
        $this->_apply_filters($filters);

        // Filter by project IDs if provided (for permissions)
        if (!empty($filters['project_ids']) && is_array($filters['project_ids'])) {
            $this->db->where_in('project_issues.project_id', $filters['project_ids']);
        }

        $this->db->order_by('project_issues.' . $sort_by, $sort_order);
        $this->db->limit($limit, $offset);
        $issues = $this->db->get()->result_array();

        // Decode JSON fields
        foreach ($issues as &$issue) {
            $issue = $this->_decode_json_fields($issue);
        }

        return array(
            'total'  => $total,
            'issues' => $issues,
            'offset' => $offset,
            'limit'  => $limit,
        );
    }

    /**
     * Create a new issue
     *
     * @param array $data Issue data
     * @return int Issue ID
     * @throws Exception
     */
    public function create($data)
    {
        // Validate required fields
        if (empty($data['project_id'])) {
            throw new Exception('project_id is required');
        }
        if (empty(trim((string) (isset($data['title']) ? $data['title'] : '')))) {
            throw new Exception('title is required');
        }
        if (empty(trim((string) (isset($data['description']) ? $data['description'] : '')))) {
            throw new Exception('description is required');
        }

        // Filter allowed fields
        $insert_data = $this->_filter_fields($data);

        // Encode JSON fields
        $insert_data = $this->_encode_json_fields($insert_data);

        // Set defaults
        if (!isset($insert_data['status'])) {
            $insert_data['status'] = 'open';
        }
        if (!isset($insert_data['applied'])) {
            $insert_data['applied'] = 0;
        }
        if (!isset($insert_data['created'])) {
            $insert_data['created'] = time();
        }

        $this->db->insert('project_issues', $insert_data);
        $insert_id = $this->db->insert_id();

        if (!$insert_id) {
            throw new Exception('Failed to create issue');
        }

        return $insert_id;
    }

    /**
     * Update an issue
     *
     * @param int $id Issue ID
     * @param array $data Update data
     * @return bool
     * @throws Exception
     */
    public function update($id, $data)
    {
        $id = (int) $id;
        
        if (!$this->exists($id)) {
            throw new Exception('Issue not found');
        }

        // Filter allowed fields
        $update_data = $this->_filter_fields($data);

        // Remove fields that shouldn't be updated
        unset($update_data['id']);
        unset($update_data['project_id']);
        unset($update_data['created']);
        unset($update_data['created_by']);

        // When clearing applied, clear applied_on and applied_by
        if (array_key_exists('applied', $update_data) && (int) $update_data['applied'] === 0) {
            $update_data['applied_on'] = null;
            $update_data['applied_by'] = null;
        }

        // Validate required fields when present
        if (array_key_exists('title', $update_data) && trim((string) $update_data['title']) === '') {
            throw new Exception('title is required');
        }
        if (array_key_exists('description', $update_data) && trim((string) $update_data['description']) === '') {
            throw new Exception('description is required');
        }

        if (empty($update_data)) {
            return true;
        }

        // Encode JSON fields
        $update_data = $this->_encode_json_fields($update_data);

        $this->db->where('id', $id);
        return $this->db->update('project_issues', $update_data);
    }

    /**
     * Delete an issue
     *
     * @param int $id Issue ID
     * @return bool
     */
    public function delete($id)
    {
        $id = (int) $id;
        $this->db->where('id', $id);
        return $this->db->delete('project_issues');
    }

    /**
     * Mark issue as applied
     *
     * @param int $id Issue ID
     * @param int $user_id User who applied the change
     * @return bool
     * @throws Exception
     */
    public function mark_as_applied($id, $user_id)
    {
        $id = (int) $id;
        $user_id = (int) $user_id;

        if (!$this->exists($id)) {
            throw new Exception('Issue not found');
        }

        $update_data = array(
            'applied'    => 1,
            'applied_by' => $user_id,
            'applied_on' => time(),
            'status'     => 'fixed',
        );

        $this->db->where('id', $id);
        return $this->db->update('project_issues', $update_data);
    }

    /**
     * Update issue status
     *
     * @param int $id Issue ID
     * @param string $status New status
     * @param int|null $user_id User making the change
     * @return bool
     * @throws Exception
     */
    public function update_status($id, $status, $user_id = null)
    {
        $id = (int) $id;
        $allowed_statuses = array('open', 'accepted', 'rejected', 'fixed', 'dismissed', 'false_positive');

        if (!in_array($status, $allowed_statuses)) {
            throw new Exception('Invalid status');
        }

        if (!$this->exists($id)) {
            throw new Exception('Issue not found');
        }

        $update_data = array(
            'status' => $status,
        );

        // If status is closed/resolved, set resolved timestamp
        if (in_array($status, array('rejected', 'fixed', 'dismissed', 'false_positive'))) {
            $update_data['resolved'] = time();
            if ($user_id) {
                $update_data['resolved_by'] = (int) $user_id;
            }
        }

        $this->db->where('id', $id);
        return $this->db->update('project_issues', $update_data);
    }

    /**
     * Get issue statistics for a project
     *
     * @param int $project_id Project ID
     * @return array Statistics
     */
    public function get_project_stats($project_id)
    {
        $project_id = (int) $project_id;

        // Total issues
        $this->db->where('project_id', $project_id);
        $total = $this->db->count_all_results('project_issues');

        // By status
        $this->db->select('status, COUNT(*) as count');
        $this->db->where('project_id', $project_id);
        $this->db->group_by('status');
        $by_status = $this->db->get('project_issues')->result_array();

        // By category
        $this->db->select('category, COUNT(*) as count');
        $this->db->where('project_id', $project_id);
        $this->db->where('category IS NOT NULL');
        $this->db->group_by('category');
        $by_category = $this->db->get('project_issues')->result_array();

        // By severity
        $this->db->select('severity, COUNT(*) as count');
        $this->db->where('project_id', $project_id);
        $this->db->where('severity IS NOT NULL');
        $this->db->group_by('severity');
        $by_severity = $this->db->get('project_issues')->result_array();

        // Applied count
        $this->db->where('project_id', $project_id);
        $this->db->where('applied', 1);
        $applied_count = $this->db->count_all_results('project_issues');

        return array(
            'total'       => $total,
            'by_status'   => $by_status,
            'by_category' => $by_category,
            'by_severity' => $by_severity,
            'applied'     => $applied_count,
        );
    }

    /**
     * Check if issue exists
     *
     * @param int $id Issue ID
     * @return bool
     */
    public function exists($id)
    {
        $this->db->where('id', (int) $id);
        return $this->db->count_all_results('project_issues') > 0;
    }

    /**
     * Bulk update status
     *
     * @param array $ids Issue IDs
     * @param string $status New status
     * @param int|null $user_id User making the change
     * @return int Number of rows affected
     * @throws Exception
     */
    public function bulk_update_status($ids, $status, $user_id = null)
    {
        if (empty($ids) || !is_array($ids)) {
            return 0;
        }

        $allowed_statuses = array('open', 'accepted', 'rejected', 'fixed', 'dismissed', 'false_positive');
        if (!in_array($status, $allowed_statuses)) {
            throw new Exception('Invalid status');
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $update_data = array(
            'status' => $status,
        );

        // If status is closed/resolved, set resolved timestamp
        if (in_array($status, array('rejected', 'fixed', 'dismissed', 'false_positive'))) {
            $update_data['resolved'] = time();
            if ($user_id) {
                $update_data['resolved_by'] = (int) $user_id;
            }
        }

        $this->db->where_in('id', $ids);
        $this->db->update('project_issues', $update_data);
        
        return $this->db->affected_rows();
    }

    /**
     * Bulk delete issues
     *
     * @param array $ids Issue IDs
     * @return int Number of rows affected
     */
    public function bulk_delete($ids)
    {
        if (empty($ids) || !is_array($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $this->db->where_in('id', $ids);
        $this->db->delete('project_issues');

        return $this->db->affected_rows();
    }

    /**
     * Apply filters to query
     *
     * @param array $filters
     */
    private function _apply_filters($filters)
    {
        // Scope restricts the status set; an explicit status filter further narrows within the scope
        if (isset($filters['scope']) && !empty($filters['scope'])) {
            if ($filters['scope'] === 'open') {
                $this->db->where_in('status', array('open', 'accepted'));
            } elseif ($filters['scope'] === 'closed') {
                $this->db->where_in('status', array('fixed', 'rejected', 'dismissed', 'false_positive'));
            }
        } elseif (isset($filters['status']) && !empty($filters['status'])) {
            $vals = is_array($filters['status']) ? $filters['status'] : explode(',', $filters['status']);
            $vals = array_filter(array_map('trim', $vals));
            if (count($vals) > 1) {
                $this->db->where_in('status', $vals);
            } else {
                $this->db->where('status', reset($vals));
            }
        }

        if (isset($filters['category']) && !empty($filters['category'])) {
            $vals = is_array($filters['category']) ? $filters['category'] : explode(',', $filters['category']);
            $vals = array_filter(array_map('trim', $vals));
            if (count($vals) > 1) {
                $this->db->where_in('category', $vals);
            } else {
                $this->db->where('category', reset($vals));
            }
        }

        if (isset($filters['severity']) && !empty($filters['severity'])) {
            $vals = is_array($filters['severity']) ? $filters['severity'] : explode(',', $filters['severity']);
            $vals = array_filter(array_map('trim', $vals));
            if (count($vals) > 1) {
                $this->db->where_in('severity', $vals);
            } else {
                $this->db->where('severity', reset($vals));
            }
        }

        if (isset($filters['applied']) && $filters['applied'] !== null && $filters['applied'] !== '') {
            $this->db->where('applied', (int) $filters['applied']);
        }

        if (isset($filters['field_path']) && !empty($filters['field_path'])) {
            $this->db->like('field_path', $filters['field_path']);
        }

        if (isset($filters['id']) && $filters['id'] !== null && $filters['id'] !== '') {
            $this->db->where($this->table . '.id', (int) $filters['id']);
        }

        if (isset($filters['project_id']) && $filters['project_id'] !== null && $filters['project_id'] !== '') {
            $this->db->where($this->table . '.project_id', (int) $filters['project_id']);
        }
    }

    /**
     * Filter fields to only allowed ones
     *
     * @param array $data
     * @return array
     */
    private function _filter_fields($data)
    {
        $filtered = array();
        foreach ($this->issue_fields as $field) {
            if (isset($data[$field])) {
                $filtered[$field] = $data[$field];
            }
        }
        return $filtered;
    }

    /**
     * Encode JSON fields
     *
     * @param array $data
     * @return array
     */
    private function _encode_json_fields($data)
    {
        $json_fields = array('current_metadata', 'suggested_metadata');
        foreach ($json_fields as $field) {
            if (isset($data[$field])) {
                if (is_array($data[$field]) || is_object($data[$field])) {
                    $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }
        return $data;
    }

    /**
     * Decode JSON fields
     *
     * @param array $row
     * @return array
     */
    private function _decode_json_fields($row)
    {
        $json_fields = array('current_metadata', 'suggested_metadata');
        foreach ($json_fields as $field) {
            if (isset($row[$field]) && !empty($row[$field])) {
                $decoded = json_decode($row[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$field] = $decoded;
                }
            }
        }
        return $row;
    }
}
