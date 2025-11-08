<?php

use GuzzleHttp\Client;

defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
        $this->load->model('User_model');
        $this->load->model('Audit_log_model');
        $this->load->library('DataUtils');
        $this->config->load('editor');
    }

    /**
     * Get comprehensive dashboard statistics (with caching)
     */
    public function get_dashboard_stats()
    {
        // Try to get cached dashboard stats first
        $cached_stats = $this->get_cached_dashboard_stats();
        if ($cached_stats !== false) {
            $cached_stats['fastapi'] = $this->get_fastapi_status();
            $cached_stats['fastapi_jobs'] = $this->get_fastapi_jobs();
            return $cached_stats;
        }
        
        // If no cache, calculate all stats
        $stats = [
            'projects' => $this->get_project_stats(),
            'users' => $this->get_user_stats(),
            'disk_space' => $this->get_disk_space_stats(),
            'activity' => $this->get_recent_activity(5),
            'analytics' => $this->get_analytics_summary(),
            'api_logs' => $this->get_api_logs_summary(),
            'system' => $this->get_system_info(),
            'fastapi' => $this->get_fastapi_status(),
            'fastapi_jobs' => $this->get_fastapi_jobs()
        ];

        // Cache the results for 15 minutes (shorter than storage cache)
        $this->cache_dashboard_stats($stats);
        
        return $stats;
    }

    /**
     * Get cached dashboard statistics
     */
    private function get_cached_dashboard_stats()
    {
        $this->load->driver('cache');
        return $this->cache->get('dashboard_stats');
    }

    /**
     * Cache dashboard statistics
     */
    private function cache_dashboard_stats($stats)
    {
        $this->load->driver('cache');
        $this->cache->save('dashboard_stats', $stats, 900); // Cache for 15 minutes
    }

    /**
     * Clear dashboard cache (force refresh)
     */
    public function clear_dashboard_cache()
    {
        $this->load->driver('cache');
        $this->cache->delete('dashboard_stats');
        $this->cache->delete('dashboard_storage_stats');
    }

    public function clear_storage_cache()
    {
        $this->load->driver('cache');
        $this->cache->delete('dashboard_storage_stats');
    }

    /**
     * Get project statistics by type (optimized with single query)
     */
    public function get_project_stats()
    {
        // Use a single optimized query to get all project stats
        $thirty_days_ago = time() - (30 * 24 * 60 * 60);
        $this_month_start = mktime(0, 0, 0, date('n'), 1, date('Y'));
        
        $this->db->select('
            COUNT(*) as total_projects,
            SUM(CASE WHEN created >= ' . $thirty_days_ago . ' THEN 1 ELSE 0 END) as recent_30_days,
            SUM(CASE WHEN created >= ' . $this_month_start . ' THEN 1 ELSE 0 END) as this_month,
            SUM(CASE WHEN published = 1 THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN published = 0 OR published IS NULL THEN 1 ELSE 0 END) as unpublished
        ');
        $this->db->from('editor_projects');
        $project_totals = $this->db->get()->row_array();

        // Get projects by type (separate query for grouping)
        $this->db->select('type, COUNT(*) as count');
        $this->db->from('editor_projects');
        $this->db->group_by('type');
        $projects_by_type = $this->db->get()->result_array();

        // Format project types for display
        $project_types = [];
        foreach ($projects_by_type as $type) {
            $project_types[] = [
                'type' => $type['type'] ?: 'unknown',
                'count' => (int)$type['count']
            ];
        }

        return [
            'total' => (int)$project_totals['total_projects'],
            'by_type' => $project_types,
            'published' => (int)$project_totals['published'],
            'unpublished' => (int)$project_totals['unpublished'],
            'recent_30_days' => (int)$project_totals['recent_30_days'],
            'this_month' => (int)$project_totals['this_month']
        ];
    }

    /**
     * Get user statistics
     */
    public function get_user_stats()
    {
        // Get total users count
        $total_users = $this->db->count_all('users');

        // Get active users (logged in within last 30 days)
        $thirty_days_ago = time() - (30 * 24 * 60 * 60);
        $this->db->where('last_login >=', $thirty_days_ago);
        $active_users = $this->db->count_all_results('users');

        // Get users created this month
        $this_month_start = mktime(0, 0, 0, date('n'), 1, date('Y'));
        $this->db->where('created_on >=', $this_month_start);
        $this_month_users = $this->db->count_all_results('users');

        // Get users by status
        $this->db->select('active, COUNT(*) as count');
        $this->db->from('users');
        $this->db->group_by('active');
        $users_by_status = $this->db->get()->result_array();

        // Get users with no projects
        $this->db->from('users u');
        $this->db->join('editor_projects ep', 'u.id = ep.created_by', 'left');
        $this->db->where('ep.id IS NULL');
        $users_without_projects = $this->db->count_all_results();

        // Get top active users (by project count)
        $this->db->select('u.username, u.email, COUNT(ep.id) as project_count');
        $this->db->from('users u');
        $this->db->join('editor_projects ep', 'u.id = ep.created_by', 'left');
        $this->db->group_by('u.id, u.username, u.email');
        $this->db->order_by('project_count', 'DESC');
        $this->db->limit(5);
        $top_users = $this->db->get()->result_array();

        // Format status stats
        $status_stats = [];
        foreach ($users_by_status as $status) {
            $status_stats[] = [
                'status' => $status['active'] ? 'active' : 'inactive',
                'count' => (int)$status['count']
            ];
        }

        return [
            'total' => $total_users,
            'active' => $active_users,
            'this_month' => $this_month_users,
            'by_status' => $status_stats,
            'top_active' => $top_users,
            'no_projects' => $users_without_projects
        ];
    }

    /**
     * Get storage usage statistics (optimized for large datasets)
     */
    public function get_storage_stats()
    {
        $storage_path = $this->Editor_model->get_storage_path();
        
        // Try to get cached storage stats first
        $cached_stats = $this->get_cached_storage_stats();
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        // If no cache, calculate with optimizations
        $stats = $this->calculate_storage_stats_optimized($storage_path);
        
        // Cache the results for 1 hour
        $this->cache_storage_stats($stats);
        
        return $stats;
    }

    /**
     * Optimized storage calculation with sampling for large datasets
     */
    private function calculate_storage_stats_optimized($storage_path)
    {
        // Get project count first to determine if we need sampling
        $total_projects = $this->db->count_all('editor_projects');
        
        if ($total_projects > 1000) {
            // For large datasets, use sampling approach
            return $this->calculate_storage_with_sampling($storage_path, $total_projects);
        } else {
            // For smaller datasets, use full calculation
            return $this->calculate_storage_full($storage_path);
        }
    }

    /**
     * Full storage calculation (for smaller datasets)
     */
    private function calculate_storage_full($storage_path)
    {
        $calculation_method = 'full_fast';
        $total_size = $this->get_directory_size_fast($storage_path);
        if ($total_size === null) {
            $total_size = $this->calculate_directory_size($storage_path);
            $calculation_method = 'full_recursive';
        }
        $file_count = $this->count_files_in_directory($storage_path);
        
        return $this->format_storage_stats($total_size, $file_count, $storage_path, $calculation_method);
    }

    /**
     * Sampling-based storage calculation (for large datasets)
     */
    private function calculate_storage_with_sampling($storage_path, $total_projects)
    {
        // Sample 10% of projects or minimum 100 projects
        $sample_size = max(100, min(500, intval($total_projects * 0.1)));
        
        // Get random sample of project directories
        $this->db->select('dirpath');
        $this->db->from('editor_projects');
        $this->db->where('dirpath IS NOT NULL');
        $this->db->order_by('RAND()');
        $this->db->limit($sample_size);
        $sample_projects = $this->db->get()->result_array();
        
        $total_size = 0;
        $file_count = 0;
        $used_fast = false;
        $used_recursive = false;
        
        foreach ($sample_projects as $project) {
            $project_path = $storage_path . '/' . $project['dirpath'];
            if (is_dir($project_path)) {
                $project_size = $this->get_directory_size_fast($project_path);
                if ($project_size === null) {
                    $project_size = $this->calculate_directory_size($project_path);
                    $used_recursive = true;
                } else {
                    $used_fast = true;
                }
                $total_size += $project_size;
                $file_count += $this->count_files_in_directory($project_path);
            }
        }

        // Extrapolate to full dataset
        if ($sample_size > 0) {
            $avg_size_per_project = $total_size / $sample_size;
            $avg_files_per_project = $file_count / $sample_size;
            
            $total_size = $avg_size_per_project * $total_projects;
            $file_count = $avg_files_per_project * $total_projects;
        }
        
        $calculation_method = 'sampling';
        if ($used_fast && !$used_recursive) {
            $calculation_method = 'sampling_fast';
        } elseif ($used_fast && $used_recursive) {
            $calculation_method = 'sampling_mixed';
        }
        
        return $this->format_storage_stats($total_size, $file_count, $storage_path, $calculation_method);
    }

    /**
     * Format storage statistics with project type breakdown
     */
    private function format_storage_stats($total_size, $file_count, $storage_path, $calculation_method = 'full')
    {
        // Get storage by project type (approximate)
        $this->db->select('type, COUNT(*) as count');
        $this->db->from('editor_projects');
        $this->db->group_by('type');
        $projects_by_type = $this->db->get()->result_array();

        // Calculate approximate storage per type (rough estimate)
        $avg_size_per_project = $file_count > 0 ? $total_size / $file_count : 0;
        $storage_by_type = [];
        foreach ($projects_by_type as $type) {
            $storage_by_type[] = [
                'type' => $type['type'] ?: 'unknown',
                'count' => (int)$type['count'],
                'estimated_size' => round((int)$type['count'] * $avg_size_per_project)
            ];
        }

        $calculation_source = strpos($calculation_method, 'sampling') === 0 ? 'estimate' : 'exact';

        return [
            'total_size' => $total_size,
            'total_size_formatted' => $this->format_bytes($total_size),
            'file_count' => $file_count,
            'by_type' => $storage_by_type,
            'storage_path' => $storage_path,
            'calculation_method' => $calculation_method,
            'calculation_source' => $calculation_source
        ];
    }

    /**
     * Get cached storage statistics
     */
    private function get_cached_storage_stats()
    {
        $this->load->driver('cache');
        return $this->cache->get('dashboard_storage_stats');
    }

    /**
     * Cache storage statistics
     */
    private function cache_storage_stats($stats)
    {
        $this->load->driver('cache');
        $this->cache->save('dashboard_storage_stats', $stats, 3600); // Cache for 1 hour
    }

    /**
     * Get recent activity feed
     */
    public function get_recent_activity($limit = 10)
    {
        // Get recent audit log entries
        $this->db->select('al.*, u.username, ep.title as project_title, ep.type as project_type');
        $this->db->from('audit_logs al');
        $this->db->join('users u', 'al.user_id = u.id', 'left');
        $this->db->join('editor_projects ep', 'al.obj_ref_id = ep.id AND al.obj_type = "project"', 'left');
        $this->db->order_by('al.created', 'DESC');
        $this->db->limit($limit);
        $audit_logs = $this->db->get()->result_array();

        // Get recent project creations
        $this->db->select('ep.*, u.username');
        $this->db->from('editor_projects ep');
        $this->db->join('users u', 'ep.created_by = u.id', 'left');
        $this->db->order_by('ep.created', 'DESC');
        $this->db->limit(5);
        $recent_projects = $this->db->get()->result_array();

        // Format activity items
        $activity_items = [];
        
        // Add audit log entries
        foreach ($audit_logs as $log) {
            // Convert datetime to timestamp for consistent handling
            $timestamp = is_string($log['created']) ? strtotime($log['created']) : $log['created'];
            
            $activity_items[] = [
                'type' => 'audit',
                'timestamp' => $timestamp,
                'user' => $log['username'],
                'action' => $log['action_type'],
                'object_type' => $log['obj_type'],
                'object_id' => $log['obj_ref_id'],
                'project_title' => $log['project_title'],
                'project_type' => $log['project_type'],
                'description' => $this->format_activity_description($log)
            ];
        }

        // Add recent project creations
        foreach ($recent_projects as $project) {
            $activity_items[] = [
                'type' => 'project',
                'timestamp' => $project['created'],
                'user' => $project['username'],
                'action' => 'created',
                'object_type' => 'project',
                'object_id' => $project['id'],
                'project_title' => $project['title'],
                'project_type' => $project['type'],
                'description' => "Created project: {$project['title']}"
            ];
        }

        // Sort by timestamp and limit
        usort($activity_items, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return array_slice($activity_items, 0, $limit);
    }

    /**
     * Calculate directory size recursively
     */
    private function calculate_directory_size($directory)
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Count files in directory recursively
     */
    private function count_files_in_directory($directory)
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format activity description
     */
    private function format_activity_description($log)
    {
        $action = ucfirst($log['action_type']);
        $object_type = ucfirst($log['obj_type']);
        
        if ($log['project_title']) {
            return "{$action} {$object_type}: {$log['project_title']}";
        } else {
            return "{$action} {$object_type} #{$log['obj_ref_id']}";
        }
    }

    /**
     * Get disk space statistics
     * Uses native PHP functions for cross-platform compatibility
     */
    public function get_disk_space_stats()
    {
        $storage_path = $this->Editor_model->get_storage_path();
        
        try {
            // Use PHP native functions for cross-platform compatibility
            $total = @disk_total_space($storage_path);
            $free = @disk_free_space($storage_path);
            
            // Check if functions returned valid values
            if ($total === false || $free === false || $total === 0) {
                return $this->get_disk_space_fallback($storage_path);
            }
            
            $used = $total - $free;
            $percentage = ($used / $total) * 100;
            
            // Determine status based on usage
            $status = 'ok';
            if ($percentage >= 85) {
                $status = 'critical';
            } elseif ($percentage >= 70) {
                $status = 'warning';
            }
            
            return [
                'total' => $total,
                'total_formatted' => $this->format_bytes($total),
                'free' => $free,
                'free_formatted' => $this->format_bytes($free),
                'used' => $used,
                'used_formatted' => $this->format_bytes($used),
                'percentage' => round($percentage, 1),
                'status' => $status,
                'storage_path' => $storage_path,
                'available' => true
            ];
            
        } catch (Exception $e) {
            return $this->get_disk_space_fallback($storage_path);
        }
    }

    /**
     * Fallback when disk space functions are unavailable
     */
    private function get_disk_space_fallback($storage_path)
    {
        return [
            'total' => 0,
            'total_formatted' => 'N/A',
            'free' => 0,
            'free_formatted' => 'N/A',
            'used' => 0,
            'used_formatted' => 'N/A',
            'percentage' => 0,
            'status' => 'unavailable',
            'storage_path' => $storage_path,
            'available' => false,
            'error' => 'Disk space information unavailable'
        ];
    }

    /**
     * Get analytics summary for dashboard
     */
    private function get_analytics_summary()
    {
        // Check if analytics is enabled
        if (!$this->db->table_exists('analytics_events')) {
            return null;
        }

        $this->load->model('Analytics_model');
        
        try {
            // Use database timezone for accurate date comparison
            // Get current date from database to avoid timezone issues
            $today_result = $this->db->query('SELECT CURDATE() as today')->row();
            $today_from_db = $today_result ? $today_result->today : date('Y-m-d');
            $today_start = $today_from_db . ' 00:00:00';
            $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));

            $today_date = new DateTime($today_from_db);
            $start_30d = (clone $today_date)->modify('-29 days');
            $thirty_days_ago_date = $start_30d->format('Y-m-d');
            $thirty_days_ago = $start_30d->format('Y-m-d H:i:s');
            
            // Fetch aggregated totals for the last 30 days (fallback to raw if missing)
            $aggregated_totals_query = $this->db
                ->select('stat_date, total_views, unique_users, unique_sessions, total_errors')
                ->from('analytics_daily')
                ->where('page', Analytics_model::DAILY_TOTAL_KEY)
                ->where('stat_date >=', $thirty_days_ago_date)
                ->order_by('stat_date', 'ASC')
                ->get();
            $aggregated_totals_rows = $aggregated_totals_query ? $aggregated_totals_query->result_array() : [];
            $aggregated_totals_map = [];
            foreach ($aggregated_totals_rows as $row) {
                $aggregated_totals_map[$row['stat_date']] = $row;
            }

            // Page views today (prefer aggregated data)
            if (isset($aggregated_totals_map[$today_from_db])) {
                $page_views_today = (int)$aggregated_totals_map[$today_from_db]['total_views'];
            } else {
                $page_views_today = $this->db
                    ->where('event_type', 'page_view')
                    ->where('DATE(created_at) =', $today_from_db)
                    ->count_all_results('analytics_events');
            }
            
            // Active users (last hour)
            $active_users = $this->db
                ->select('COUNT(DISTINCT user_id) as count')
                ->where('created_at >=', $last_hour)
                ->where('user_id IS NOT NULL')
                ->get('analytics_events')
                ->row()->count ?? 0;
            
            // Sessions (30 days) - prefer aggregated unique sessions sum
            if (!empty($aggregated_totals_rows)) {
                $sessions_30d = array_sum(array_map(function($row) {
                    return (int)($row['unique_sessions'] ?? 0);
                }, $aggregated_totals_rows));
            } else {
                $sessions_30d = $this->db
                    ->select('COUNT(DISTINCT session_id) as count')
                    ->where('created_at >=', $thirty_days_ago)
                    ->get('analytics_events')
                    ->row()->count ?? 0;
            }
            
            // Errors today (prefer aggregated)
            if (isset($aggregated_totals_map[$today_from_db])) {
                $errors_today = (int)$aggregated_totals_map[$today_from_db]['total_errors'];
            } else {
                $errors_today = $this->db
                    ->where('event_type', 'error')
                    ->where('DATE(created_at) =', $today_from_db)
                    ->count_all_results('analytics_events');
            }
            
            // Top pages (today only, limit 10) - prefer aggregated page stats
            $top_pages_query = $this->db
                ->select('page, SUM(total_views) as views')
                ->from('analytics_daily')
                ->where('stat_date', $today_from_db)
                ->where('page !=', Analytics_model::DAILY_TOTAL_KEY)
                ->group_by('page')
                ->order_by('views', 'DESC')
                ->limit(10)
                ->get();
            $top_pages = $top_pages_query ? $top_pages_query->result_array() : [];

            if (empty($top_pages)) {
                $top_pages = $this->db
                    ->select('page, COUNT(*) as views')
                    ->where('event_type', 'page_view')
                    ->where('DATE(created_at) =', $today_from_db)
                    ->group_by('page')
                    ->order_by('views', 'DESC')
                    ->limit(10)
                    ->get('analytics_events')
                    ->result_array();
            }
            
            // Top user agents (browser-os-device)
            $top_user_agents = $this->db
                ->select('user_agent, COUNT(*) as count')
                ->where('user_agent IS NOT NULL')
                ->where('created_at >=', $thirty_days_ago)
                ->group_by('user_agent')
                ->order_by('count', 'DESC')
                ->limit(5)
                ->get('analytics_events')
                ->result_array();
            
            // Traffic chart data (last 30 days, daily counts) - prefer aggregated totals
            $traffic_chart = [];
            $chart_iter = clone $start_30d;
            while ($chart_iter <= $today_date) {
                $date_key = $chart_iter->format('Y-m-d');
                $views = isset($aggregated_totals_map[$date_key]) ? (int)$aggregated_totals_map[$date_key]['total_views'] : null;
                if ($views === null) {
                    $views = (int)$this->db
                        ->select('COUNT(*) as cnt')
                        ->where('event_type', 'page_view')
                        ->where('DATE(created_at) =', $date_key)
                        ->get('analytics_events')
                        ->row()->cnt ?? 0;
                }
                $traffic_chart[] = [
                    'date' => $date_key,
                    'views' => $views
                ];
                $chart_iter->modify('+1 day');
            }
            
            // Today's hourly traffic data (use DATE() for timezone-safe comparison)
            $hourly_traffic = $this->db
                ->select('HOUR(created_at) as hour, COUNT(*) as views')
                ->where('event_type', 'page_view')
                ->where('DATE(created_at) =', $today_from_db)
                ->group_by('HOUR(created_at)')
                ->order_by('hour', 'ASC')
                ->get('analytics_events')
                ->result_array();
            
            // Top users (today only, logged-in users only)
            $top_users = array();
            try {
                $top_users_data = $this->db
                    ->select('user_id, COUNT(*) as page_views')
                    ->where('event_type', 'page_view')
                    ->where('user_id IS NOT NULL')
                    ->where('DATE(created_at) =', $today_from_db)
                    ->group_by('user_id')
                    ->order_by('page_views', 'DESC')
                    ->limit(10)
                    ->get('analytics_events')
                    ->result_array();
                
                // Enrich with user details
                if (!empty($top_users_data)) {
                    // Get user details from users table directly
                    foreach ($top_users_data as $user_data) {
                        $user = $this->db
                            ->select('id, username, email')
                            ->where('id', $user_data['user_id'])
                            ->get('users')
                            ->row_array();
                        
                        if ($user) {
                            $top_users[] = array(
                                'user_id' => $user_data['user_id'],
                                'username' => $user['username'],
                                'email' => $user['email'],
                                'page_views' => (int)$user_data['page_views']
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                // If top users query fails, just use empty array (don't break analytics)
                $top_users = array();
            }
            
            // Performance analytics (last 30 days)
            $performance = null;
            try {
                $performance = $this->Analytics_model->get_performance_analytics(30);
            } catch (Exception $e) {
                // If performance query fails, set to null (don't break analytics)
                $performance = null;
            }
            
            return array(
                'page_views_today' => (int)$page_views_today,
                'active_users' => (int)$active_users,
                'sessions_30d' => (int)$sessions_30d,
                'errors_today' => (int)$errors_today,
                'top_pages' => $top_pages,
                'top_user_agents' => $top_user_agents,
                'traffic_chart' => $traffic_chart,
                'hourly_traffic' => $hourly_traffic,
                'top_users' => $top_users,
                'performance' => $performance
            );
            
        } catch (Exception $e) {
            // If analytics fails, return null (don't break dashboard)
            return null;
        }
    }

    private function get_api_logs_summary($days = 30)
    {
        if (!$this->db->table_exists('api_logs_daily')) {
            return [
                'available' => false,
                'message' => 'API logs aggregation tables are unavailable.',
                'has_data' => false
            ];
        }

        $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $totalsRow = $this->db
            ->select('SUM(total_requests) as total_requests, SUM(success_count) as success_count, SUM(error_count) as error_count, SUM(avg_response_time * total_requests) / NULLIF(SUM(total_requests), 0) as avg_response_time', false)
            ->where('stat_date >=', $startDate)
            ->get('api_logs_daily')
            ->row_array();

        $totalRequests = (int)($totalsRow['total_requests'] ?? 0);
        $successCount = (int)($totalsRow['success_count'] ?? 0);
        $errorCount = (int)($totalsRow['error_count'] ?? 0);
        $avgResponse = isset($totalsRow['avg_response_time']) ? (float)$totalsRow['avg_response_time'] : null;
        $errorRate = $totalRequests > 0 ? ($errorCount / $totalRequests) : 0;

        $topEndpoints = $this->db
            ->select('uri, method, SUM(total_requests) as total_requests, SUM(success_count) as success_count, SUM(error_count) as error_count, SUM(avg_response_time * total_requests) / NULLIF(SUM(total_requests), 0) as avg_response_time', false)
            ->where('stat_date >=', $startDate)
            ->group_by(['uri', 'method'])
            ->order_by('total_requests', 'DESC')
            ->limit(5)
            ->get('api_logs_daily')
            ->result_array();

        $topEndpoints = array_map(function ($row) {
            $total = (int)($row['total_requests'] ?? 0);
            $errors = (int)($row['error_count'] ?? 0);
            $success = (int)($row['success_count'] ?? 0);
            $avg = isset($row['avg_response_time']) ? (float)$row['avg_response_time'] : null;
            return [
                'uri' => $row['uri'],
                'method' => $row['method'],
                'total_requests' => $total,
                'success_count' => $success,
                'error_count' => $errors,
                'avg_response_time' => $avg,
                'error_rate' => $total > 0 ? ($errors / $total) : 0
            ];
        }, $topEndpoints);

        $topIps = [];
        if ($this->db->table_exists('api_logs_ip_daily')) {
            $topIps = $this->db
                ->select('ip_address, SUM(total_requests) as total_requests, SUM(error_count) as error_count, SUM(avg_response_time * total_requests) / NULLIF(SUM(total_requests), 0) as avg_response_time', false)
                ->where('stat_date >=', $startDate)
                ->group_by('ip_address')
                ->order_by('total_requests', 'DESC')
                ->limit(5)
                ->get('api_logs_ip_daily')
                ->result_array();

            $topIps = array_map(function ($row) {
                $total = (int)($row['total_requests'] ?? 0);
                $errors = (int)($row['error_count'] ?? 0);
                $avg = isset($row['avg_response_time']) ? (float)$row['avg_response_time'] : null;
                return [
                    'ip_address' => $row['ip_address'],
                    'total_requests' => $total,
                    'error_count' => $errors,
                    'avg_response_time' => $avg,
                    'error_rate' => $total > 0 ? ($errors / $total) : 0
                ];
            }, $topIps);
        }

        $topUsers = [];
        if ($this->db->table_exists('api_logs_user_daily')) {
            $topUsers = $this->db
                ->select('aul.user_id, aul.api_key, SUM(aul.total_requests) as total_requests, SUM(aul.error_count) as error_count, SUM(aul.avg_response_time * aul.total_requests) / NULLIF(SUM(aul.total_requests), 0) as avg_response_time, u.username', false)
                ->from('api_logs_user_daily aul')
                ->join('users u', 'aul.user_id = u.id', 'left')
                ->where('aul.stat_date >=', $startDate)
                ->group_by(['aul.user_id', 'aul.api_key', 'u.username'])
                ->order_by('total_requests', 'DESC')
                ->limit(5)
                ->get()
                ->result_array();

            $topUsers = array_map(function ($row) {
                $total = (int)($row['total_requests'] ?? 0);
                $errors = (int)($row['error_count'] ?? 0);
                $avg = isset($row['avg_response_time']) ? (float)$row['avg_response_time'] : null;
                $userId = (int)($row['user_id'] ?? 0);
                $apiKey = $row['api_key'] ?? '';
                $username = $row['username'] ?? null;
                return [
                    'user_id' => $userId,
                    'api_key' => $apiKey,
                    'username' => $username,
                    'display_name' => $this->resolve_api_log_user_display($userId, $username, $apiKey),
                    'total_requests' => $total,
                    'error_count' => $errors,
                    'avg_response_time' => $avg,
                    'error_rate' => $total > 0 ? ($errors / $total) : 0
                ];
            }, $topUsers);
        }

        return [
            'available' => true,
            'has_data' => $totalRequests > 0,
            'days' => $days,
            'start_date' => $startDate,
            'totals' => [
                'total_requests' => $totalRequests,
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'avg_response_time' => $avgResponse,
                'error_rate' => $errorRate
            ],
            'top_endpoints' => $topEndpoints,
            'top_ips' => $topIps,
            'top_users' => $topUsers
        ];
    }

    private function resolve_api_log_user_display($userId, $username, $apiKey)
    {
        if (!empty($username)) {
            return $username;
        }

        if (!empty($userId)) {
            return 'User #' . $userId;
        }

        if (!empty($apiKey)) {
            return 'API Key ' . substr($apiKey, 0, 8) . '…';
        }

        return 'Anonymous';
    }

    /**
     * Attempt to get directory size using OS-specific utilities
     */
    private function get_directory_size_fast($path)
    {
        if (!is_dir($path)) {
            return null;
        }

        $escaped_path = escapeshellarg($path);

        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows - use PowerShell
            $command = 'powershell -NoProfile -Command "(Get-ChildItem -LiteralPath ' . $escaped_path . ' -Recurse -ErrorAction SilentlyContinue | Measure-Object -Property Length -Sum).Sum"';
            $output = @shell_exec($command);
            if ($output === null) {
                return null;
            }
            $size = trim($output);
            return is_numeric($size) ? (int)$size : null;
        }

        $commands = [];
        if (PHP_OS_FAMILY === 'Linux') {
            $commands[] = ['cmd' => 'du -sb ' . $escaped_path . ' 2>/dev/null', 'multiplier' => 1];
        }
        // Fallback for macOS/BSD or if -sb not available
        $commands[] = ['cmd' => 'du -sk ' . $escaped_path . ' 2>/dev/null', 'multiplier' => 1024];

        foreach ($commands as $entry) {
            $output = @shell_exec($entry['cmd']);
            if (!$output) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($output));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                return (int)$parts[0] * (int)$entry['multiplier'];
            }
        }

        return null;
    }

    private function get_system_info()
    {
        $php_version = PHP_VERSION;

        $os_family = PHP_OS_FAMILY;
        switch ($os_family) {
            case 'Windows':
                $os_type = 'Windows';
                break;
            case 'Darwin':
                $os_type = 'macOS';
                break;
            case 'Linux':
                $os_type = 'Linux';
                break;
            default:
                $os_type = PHP_OS;
        }

        $memory_limit = ini_get('memory_limit');
        $upload_limit = ini_get('upload_max_filesize');
        $post_limit = ini_get('post_max_size');
        $timezone = function_exists('date_default_timezone_get') ? date_default_timezone_get() : 'UTC';

        return [
            'php_version' => $php_version,
            'os_type' => $os_type,
            'memory_limit' => $memory_limit,
            'upload_limit' => $upload_limit,
            'post_limit' => $post_limit,
            'timezone' => $timezone
        ];
    }

    public function get_fastapi_status()
    {
        $api_url = $this->config->item('data_api_url', 'editor');
        $result = [
            'status' => 'unknown',
            'label' => 'Unknown',
            'message' => 'FastAPI status is unavailable.',
            'color' => 'grey',
            'icon' => 'mdi-help-circle',
            'base_url' => $api_url,
            'details' => null
        ];

        $response = $this->datautils->status();


        if ($response['status'] == 'ok') {
            $result['status'] = 'online';
            $result['label'] = 'Online';
            $result['color'] = 'success';
            $result['icon'] = 'mdi-check-circle';
            $result['message'] = 'FastAPI backend is online.';
        } else {
            $result['status'] = 'offline';
            $result['label'] = 'Offline';
            $result['color'] = 'error';
            $result['icon'] = 'mdi-alert-circle';

            if (!empty($response['message'])) {
                $result['message'] = $response['message'];
            } else {
                $result['message'] = 'FastAPI backend is offline.';
            }
        }

        return $result;
    }


    public function get_fastapi_jobs()
    {
        $response = $this->datautils->get_jobs();
        return $response;
    }
}
