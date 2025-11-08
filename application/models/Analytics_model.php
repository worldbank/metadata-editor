<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Analytics Model
 * Handles storage, aggregation, and retrieval of analytics data
 */
class Analytics_model extends CI_Model
{
    const DAILY_TOTAL_KEY = '__TOTAL__';
    const API_LOGS_TOTAL_KEY = '__TOTAL__';
    const API_LOGS_TOTAL_METHOD = '__ALL__';

    private $cache_prefix = 'analytics_';
    private $cache_ttl = 900; // 15 minutes
    
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->driver('cache', ['adapter' => 'file']);
    }

    /**
     * Track an event
     * 
     * @param array $event_data Event data to store
     * @return bool Success status
     */
    public function track_event($event_data)
    {
        return $this->db->insert('analytics_events', $event_data);
    }

    /**
     * Get analytics statistics
     * 
     * @param int $days Number of days to fetch stats for
     * @return array Analytics statistics
     */
    public function get_analytics_stats($days = 30)
    {
        $cache_key = $this->cache_prefix . 'stats_' . $days;
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== FALSE) {
            return $cached;
        }
        
        $cutoff = strtotime("-{$days} days");
        
        $stats = [
            'today' => $this->get_today_stats(),
            'period' => $this->get_period_stats($days),
            'top_pages' => $this->get_top_pages($days, 10),
            'top_events' => $this->get_top_events($days, 10),
            'device_breakdown' => $this->get_device_breakdown($days),
            'browser_breakdown' => $this->get_browser_breakdown($days)
        ];
        
        $this->cache->save($cache_key, $stats, $this->cache_ttl);
        
        return $stats;
    }

    /**
     * Get today's statistics
     * 
     * @return array Today's stats
     */
    private function get_today_stats()
    {
        $today_start = date('Y-m-d 00:00:00');
        
        // Total events today
        $total_events = $this->db
            ->where('created_at >=', $today_start)
            ->count_all_results('analytics_events');
        
        // Page views today
        $page_views = $this->db
            ->where('event_type', 'page_view')
            ->where('created_at >=', $today_start)
            ->count_all_results('analytics_events');
        
        // Unique users today
        $unique_users = $this->db
            ->select('COUNT(DISTINCT user_id) as count')
            ->where('created_at >=', $today_start)
            ->where('user_id IS NOT NULL')
            ->get('analytics_events')
            ->row()->count ?? 0;
        
        // Unique sessions today
        $unique_sessions = $this->db
            ->select('COUNT(DISTINCT session_id) as count')
            ->where('created_at >=', $today_start)
            ->get('analytics_events')
            ->row()->count ?? 0;
        
        // Active users (last hour)
        $active_users = $this->db
            ->select('COUNT(DISTINCT user_id) as count')
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->where('user_id IS NOT NULL')
            ->get('analytics_events')
            ->row()->count ?? 0;
        
        return [
            'total_events' => (int)$total_events,
            'page_views' => (int)$page_views,
            'unique_users' => (int)$unique_users,
            'unique_sessions' => (int)$unique_sessions,
            'active_users' => (int)$active_users
        ];
    }

    /**
     * Get statistics for a period
     * 
     * @param int $days Number of days
     * @return array Period stats
     */
    private function get_period_stats($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total events in period
        $total_events = $this->db
            ->where('created_at >=', $cutoff)
            ->count_all_results('analytics_events');
        
        // Unique users in period
        $unique_users = $this->db
            ->select('COUNT(DISTINCT user_id) as count')
            ->where('created_at >=', $cutoff)
            ->where('user_id IS NOT NULL')
            ->get('analytics_events')
            ->row()->count ?? 0;
        
        // Unique sessions in period
        $unique_sessions = $this->db
            ->select('COUNT(DISTINCT session_id) as count')
            ->where('created_at >=', $cutoff)
            ->get('analytics_events')
            ->row()->count ?? 0;
        
        // Avg session duration (from session_start to session_end events)
        $avg_session_duration = $this->db
            ->select('AVG(JSON_EXTRACT(data, "$.duration")) as avg_duration')
            ->where('event_type', 'session_end')
            ->where('created_at >=', $cutoff)
            ->get('analytics_events')
            ->row()->avg_duration ?? 0;
        
        return [
            'total_events' => (int)$total_events,
            'unique_users' => (int)$unique_users,
            'unique_sessions' => (int)$unique_sessions,
            'avg_session_duration' => round((float)$avg_session_duration, 2),
            'days' => (int)$days
        ];
    }

    /**
     * Get top pages by views
     * 
     * @param int $days Number of days
     * @param int $limit Number of results
     * @return array Top pages
     */
    public function get_top_pages($days = 30, $limit = 10)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->db
            ->select('
                page,
                COUNT(*) as views,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT session_id) as unique_sessions
            ')
            ->where('event_type', 'page_view')
            ->where('created_at >=', $cutoff)
            ->group_by('page')
            ->order_by('views', 'DESC')
            ->limit($limit)
            ->get('analytics_events')
            ->result_array();
    }

    /**
     * Get top events
     * 
     * @param int $days Number of days
     * @param int $limit Number of results
     * @return array Top events
     */
    private function get_top_events($days = 30, $limit = 10)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->db
            ->select('
                event_type,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users
            ')
            ->where('created_at >=', $cutoff)
            ->group_by('event_type')
            ->order_by('count', 'DESC')
            ->limit($limit)
            ->get('analytics_events')
            ->result_array();
    }

    /**
     * Get device breakdown
     * 
     * @param int $days Number of days
     * @return array Device stats
     */
    private function get_device_breakdown($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->db
            ->select('
                JSON_EXTRACT(data, "$.device_type") as device_type,
                COUNT(*) as count
            ')
            ->where('event_type', 'session_start')
            ->where('created_at >=', $cutoff)
            ->group_by('device_type')
            ->order_by('count', 'DESC')
            ->get('analytics_events')
            ->result_array();
    }

    /**
     * Get browser breakdown
     * 
     * @param int $days Number of days
     * @return array Browser stats
     */
    private function get_browser_breakdown($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->db
            ->select('
                JSON_EXTRACT(data, "$.browser") as browser,
                COUNT(*) as count
            ')
            ->where('event_type', 'session_start')
            ->where('created_at >=', $cutoff)
            ->group_by('browser')
            ->order_by('count', 'DESC')
            ->get('analytics_events')
            ->result_array();
    }

    /**
     * Get session information
     * 
     * @param string $session_id Session ID
     * @return array Session info
     */
    public function get_session_info($session_id)
    {
        // Get session summary
        $summary = $this->db
            ->select('
                session_id,
                user_id,
                browser_id,
                MIN(created_at) as session_start,
                MAX(created_at) as session_end,
                COUNT(*) as total_events,
                COUNT(DISTINCT page) as pages_viewed,
                ip_address,
                user_agent
            ')
            ->where('session_id', $session_id)
            ->group_by('session_id')
            ->get('analytics_events')
            ->row_array();
        
        if (empty($summary)) {
            return null;
        }
        
        // Get session events
        $events = $this->db
            ->select('event_type, page, data, created_at')
            ->where('session_id', $session_id)
            ->order_by('created_at', 'ASC')
            ->get('analytics_events')
            ->result_array();
        
        $summary['events'] = $events;
        $summary['duration'] = strtotime($summary['session_end']) - strtotime($summary['session_start']);
        
        return $summary;
    }

    /**
     * Get chart data for visualizations
     * 
     * @param string $type Chart type (calls, sessions, etc.)
     * @param int $days Number of days
     * @return array Chart data
     */
    public function get_chart_data($type = 'calls', $days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        switch ($type) {
            case 'sessions':
                return $this->get_sessions_chart_data($cutoff);
            case 'page_views':
                return $this->get_page_views_chart_data($cutoff);
            case 'performance':
                return $this->get_performance_chart_data($cutoff);
            default:
                return $this->get_events_chart_data($cutoff);
        }
    }

    /**
     * Get performance analytics summary
     * 
     * @param int $days Number of days to analyze
     * @return array Performance statistics
     */
    public function get_performance_analytics($days = 30)
    {
        // Check cache first
        $cache_key = $this->cache_prefix . 'performance_' . $days;
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== FALSE) {
            return $cached;
        }
        
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get slow pages (from slow_page events - pages that took > 5 seconds)
        $slow_pages_raw = $this->db
            ->select('
                page,
                AVG(CAST(JSON_EXTRACT(data, "$.load_time") AS UNSIGNED)) as avg_load_time,
                AVG(CAST(JSON_EXTRACT(data, "$.response_time") AS UNSIGNED)) as avg_server_time,
                AVG(CAST(JSON_EXTRACT(data, "$.dom_ready") AS UNSIGNED)) as avg_dom_ready,
                MAX(CAST(JSON_EXTRACT(data, "$.load_time") AS UNSIGNED)) as max_load_time,
                COUNT(*) as samples
            ')
            ->where('event_type', 'slow_page')
            ->where('created_at >=', $cutoff)
            ->group_by('page')
            ->order_by('avg_load_time', 'DESC')
            ->limit(10)
            ->get('analytics_events')
            ->result_array();
        
        // Get average load times by page (from performance events - filter after query)
        $avg_load_times_raw = $this->db
            ->select('
                page,
                AVG(CAST(JSON_EXTRACT(data, "$.load_time") AS UNSIGNED)) as avg_load_time,
                AVG(CAST(JSON_EXTRACT(data, "$.response_time") AS UNSIGNED)) as avg_server_time,
                AVG(CAST(JSON_EXTRACT(data, "$.dom_ready") AS UNSIGNED)) as avg_dom_ready,
                COUNT(*) as samples
            ')
            ->where('event_type', 'performance')
            ->where('created_at >=', $cutoff)
            ->group_by('page')
            ->having('samples >', 5) // Only pages with enough samples
            ->order_by('avg_load_time', 'DESC')
            ->get('analytics_events')
            ->result_array();
        
        // Combine slow_page events with slow performance events
        // Use slow_page events as primary source, then add slow performance pages
        $avg_load_times = array();
        
        // First, add pages from slow_page events
        foreach ($slow_pages_raw as $row) {
            if (!empty($row['page'])) {
                $avg_load_times[] = array(
                    'page' => $row['page'],
                    'avg_load_time' => (float)$row['avg_load_time'],
                    'avg_server_time' => (float)($row['avg_server_time'] ?? 0),
                    'avg_dom_ready' => (float)($row['avg_dom_ready'] ?? 0),
                    'max_load_time' => (float)($row['max_load_time'] ?? 0),
                    'samples' => (int)$row['samples'],
                    'is_slow_page_event' => true
                );
            }
        }
        
        // Then, add slow pages from performance events (filter > 3 seconds, avoid duplicates)
        $existing_pages = array();
        if (!empty($avg_load_times)) {
            $existing_pages = array_column($avg_load_times, 'page');
        }
        
        foreach ($avg_load_times_raw as $row) {
            $avg_time = (float)($row['avg_load_time'] ?? 0);
            $page = $row['page'] ?? '';
            
            // Only add if avg > 3 seconds and not already in list
            if (!empty($page) && $avg_time > 3000 && !in_array($page, $existing_pages)) {
                $avg_load_times[] = array(
                    'page' => $page,
                    'avg_load_time' => $avg_time,
                    'avg_server_time' => (float)($row['avg_server_time'] ?? 0),
                    'avg_dom_ready' => (float)($row['avg_dom_ready'] ?? 0),
                    'max_load_time' => $avg_time, // Use avg as max for performance events
                    'samples' => (int)($row['samples'] ?? 0),
                    'is_slow_page_event' => false
                );
                $existing_pages[] = $page; // Track to avoid duplicates
            }
        }
        
        // Sort by avg_load_time descending
        if (!empty($avg_load_times)) {
            usort($avg_load_times, function($a, $b) {
                return $b['avg_load_time'] <=> $a['avg_load_time'];
            });
            
            // Limit to top 10
            $avg_load_times = array_slice($avg_load_times, 0, 10);
        }
        
        // Get slow pages count (pages > 5 seconds)
        $slow_pages_count = $this->db
            ->where('event_type', 'slow_page')
            ->where('created_at >=', $cutoff)
            ->count_all_results('analytics_events');
        
        // Get slow API calls count (API calls > 1 second)
        $slow_api_count = $this->db
            ->where('event_type', 'api_slow')
            ->where('created_at >=', $cutoff)
            ->count_all_results('analytics_events');
        
        // Get overall average performance metrics
        $overall_stats = $this->db
            ->select('
                AVG(CAST(JSON_EXTRACT(data, "$.load_time") AS UNSIGNED)) as avg_load_time,
                AVG(CAST(JSON_EXTRACT(data, "$.response_time") AS UNSIGNED)) as avg_server_time,
                AVG(CAST(JSON_EXTRACT(data, "$.dom_ready") AS UNSIGNED)) as avg_dom_ready,
                AVG(CAST(JSON_EXTRACT(data, "$.dns_time") AS UNSIGNED)) as avg_dns_time,
                AVG(CAST(JSON_EXTRACT(data, "$.tcp_time") AS UNSIGNED)) as avg_tcp_time,
                COUNT(*) as total_samples
            ')
            ->where('event_type', 'performance')
            ->where('created_at >=', $cutoff)
            ->get('analytics_events')
            ->row_array();
        
        // Performance trend (daily averages)
        $performance_trend_raw = $this->db
            ->select('
                DATE(created_at) as date,
                AVG(CAST(JSON_EXTRACT(data, "$.load_time") AS UNSIGNED)) as avg_load_time,
                AVG(CAST(JSON_EXTRACT(data, "$.response_time") AS UNSIGNED)) as avg_server_time,
                COUNT(*) as samples
            ')
            ->where('event_type', 'performance')
            ->where('created_at >=', $cutoff)
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('analytics_events')
            ->result_array();
        
        // Ensure numeric values
        $performance_trend = array();
        foreach ($performance_trend_raw as $row) {
            $performance_trend[] = array(
                'date' => $row['date'],
                'avg_load_time' => (float)$row['avg_load_time'],
                'avg_server_time' => (float)$row['avg_server_time'],
                'samples' => (int)$row['samples']
            );
        }
        
        // Get slow API calls (top slowest endpoints)
        $slow_api_calls = $this->db
            ->select('
                page,
                AVG(CAST(JSON_EXTRACT(data, "$.response_time") AS UNSIGNED)) as avg_response_time,
                MAX(CAST(JSON_EXTRACT(data, "$.response_time") AS UNSIGNED)) as max_response_time,
                COUNT(*) as samples
            ')
            ->where('event_type', 'api_slow')
            ->where('created_at >=', $cutoff)
            ->group_by('page')
            ->order_by('avg_response_time', 'DESC')
            ->limit(10)
            ->get('analytics_events')
            ->result_array();
        
        // Get average API response times
        $api_performance_stats = $this->db
            ->select('
                AVG(CAST(JSON_EXTRACT(data, "$.response_time") AS UNSIGNED)) as avg_api_time,
                COUNT(*) as total_api_calls
            ')
            ->where('event_type', 'api_performance')
            ->where('created_at >=', $cutoff)
            ->get('analytics_events')
            ->row_array();
        
        $result = [
            'avg_load_times' => is_array($avg_load_times) ? $avg_load_times : [],
            'slow_pages_count' => (int)$slow_pages_count,
            'slow_api_count' => (int)$slow_api_count,
            'slow_api_calls' => is_array($slow_api_calls) ? $slow_api_calls : [],
            'api_performance' => [
                'avg_response_time' => round((float)($api_performance_stats['avg_api_time'] ?? 0), 2),
                'total_calls' => (int)($api_performance_stats['total_api_calls'] ?? 0)
            ],
            'overall' => [
                'avg_load_time' => round((float)($overall_stats['avg_load_time'] ?? 0), 2),
                'avg_server_time' => round((float)($overall_stats['avg_server_time'] ?? 0), 2),
                'avg_dom_ready' => round((float)($overall_stats['avg_dom_ready'] ?? 0), 2),
                'avg_dns_time' => round((float)($overall_stats['avg_dns_time'] ?? 0), 2),
                'avg_tcp_time' => round((float)($overall_stats['avg_tcp_time'] ?? 0), 2),
                'total_samples' => (int)($overall_stats['total_samples'] ?? 0)
            ],
            'trend' => $performance_trend
        ];
        
        // Cache the result for 15 minutes
        $this->cache->save($cache_key, $result, $this->cache_ttl);
        
        return $result;
    }

    /**
     * Get performance chart data
     */
    private function get_performance_chart_data($cutoff)
    {
        $data = $this->db
            ->select('
                DATE(created_at) as date,
                AVG(JSON_EXTRACT(data, "$.load_time")) as avg_load_time
            ')
            ->where('event_type', 'performance')
            ->where('created_at >=', $cutoff)
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('analytics_events')
            ->result_array();
        
        return [
            'labels' => array_column($data, 'date'),
            'values' => array_column($data, 'avg_load_time')
        ];
    }

    /**
     * Get events chart data
     */
    private function get_events_chart_data($cutoff)
    {
        $data = $this->db
            ->select('
                DATE(created_at) as date,
                COUNT(*) as count
            ')
            ->where('created_at >=', $cutoff)
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('analytics_events')
            ->result_array();
        
        return [
            'labels' => array_column($data, 'date'),
            'values' => array_column($data, 'count')
        ];
    }

    /**
     * Get sessions chart data
     */
    private function get_sessions_chart_data($cutoff)
    {
        $data = $this->db
            ->select('
                DATE(created_at) as date,
                COUNT(DISTINCT session_id) as count
            ')
            ->where('created_at >=', $cutoff)
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('analytics_events')
            ->result_array();
        
        return [
            'labels' => array_column($data, 'date'),
            'values' => array_column($data, 'count')
        ];
    }

    /**
     * Get page views chart data
     */
    private function get_page_views_chart_data($cutoff)
    {
        $data = $this->db
            ->select('
                DATE(created_at) as date,
                COUNT(*) as count
            ')
            ->where('event_type', 'page_view')
            ->where('created_at >=', $cutoff)
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('analytics_events')
            ->result_array();
        
        return [
            'labels' => array_column($data, 'date'),
            'values' => array_column($data, 'count')
        ];
    }

    /**
     * Aggregate analytics data to daily stats
     * 
     * @return array Aggregation result
     */
    public function aggregate_analytics()
    {
        // Get date range to aggregate
        $last_aggregated = $this->get_last_aggregated_date();
        $today = date('Y-m-d');

        if ($last_aggregated >= $today) {
            return [
                'success' => true,
                'message' => 'Already up to date',
                'last_aggregated' => $last_aggregated,
                'time_exceeded' => false,
                'more_pending' => false
            ];
        }

        // Aggregate each day with an overall execution time budget
        $startTime = microtime(true);
        $maxDurationSeconds = 30;
        $timeExceeded = false;

        $current_date = $last_aggregated ? date('Y-m-d', strtotime($last_aggregated . ' +1 day')) : date('Y-m-d', strtotime('-90 days'));
        $days_aggregated = 0;
        $last_processed_date = $last_aggregated;

        while ($current_date < $today) {
            $this->aggregate_day($current_date);
            $days_aggregated++;
            $last_processed_date = $current_date;

            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));

            // Stop if we have exceeded the overall time budget (after finishing the current day)
            if ((microtime(true) - $startTime) >= $maxDurationSeconds) {
                $timeExceeded = true;
                break;
            }
        }

        $morePending = $current_date < $today;

        return [
            'success' => true,
            'days_aggregated' => $days_aggregated,
            'last_aggregated' => $last_processed_date,
            'time_exceeded' => $timeExceeded,
            'more_pending' => $morePending
        ];
    }

    public function aggregate_api_logs()
    {
        if (!$this->db->table_exists('api_logs') || !$this->db->table_exists('api_logs_daily')) {
            return [
                'success' => false,
                'message' => 'API logs tables are missing',
                'days_aggregated' => 0,
                'last_aggregated' => null,
                'time_exceeded' => false,
                'more_pending' => false
            ];
        }

        $hasIpTable = $this->db->table_exists('api_logs_ip_daily');
        $hasUserTable = $this->db->table_exists('api_logs_user_daily');

        $lastAggregated = $this->get_last_api_logs_aggregated_date();
        $today = date('Y-m-d');

        if ($lastAggregated && $lastAggregated >= $today) {
            return [
                'success' => true,
                'message' => 'API logs already up to date',
                'last_aggregated' => $lastAggregated,
                'days_aggregated' => 0,
                'time_exceeded' => false,
                'more_pending' => false
            ];
        }

        $startTime = microtime(true);
        $maxDurationSeconds = 30;
        $timeExceeded = false;

        $currentDate = $lastAggregated ? date('Y-m-d', strtotime($lastAggregated . ' +1 day')) : date('Y-m-d', strtotime('-30 days'));
        $daysAggregated = 0;
        $lastProcessedDate = $lastAggregated;

        while ($currentDate < $today) {
            $processed = $this->aggregate_api_logs_day($currentDate, $hasIpTable, $hasUserTable);
            if ($processed) {
                $daysAggregated++;
                $lastProcessedDate = $currentDate;
            }

            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));

            if ((microtime(true) - $startTime) >= $maxDurationSeconds) {
                $timeExceeded = true;
                break;
            }
        }

        $morePending = $currentDate < $today;

        return [
            'success' => true,
            'days_aggregated' => $daysAggregated,
            'last_aggregated' => $lastProcessedDate,
            'time_exceeded' => $timeExceeded,
            'more_pending' => $morePending
        ];
    }

    private function aggregate_api_logs_day($date, $hasIpTable = true, $hasUserTable = true)
    {
        $startTimestamp = strtotime($date . ' 00:00:00');
        $endTimestamp = strtotime($date . ' +1 day 00:00:00');

        $overall = $this->db
            ->select('
                COUNT(*) as total_requests,
                SUM(CASE WHEN response_code >= 400 OR authorized = "0" THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN response_code < 400 AND authorized = "1" THEN 1 ELSE 0 END) as success_count,
                AVG(rtime) as avg_response_time
            ')
            ->where('time >=', $startTimestamp)
            ->where('time <', $endTimestamp)
            ->get('api_logs')
            ->row_array();

        if (empty($overall) || (int)($overall['total_requests'] ?? 0) === 0) {
            $this->db->where('stat_date', $date)->delete('api_logs_daily');
            if ($hasIpTable) {
                $this->db->where('stat_date', $date)->delete('api_logs_ip_daily');
            }
            if ($hasUserTable) {
                $this->db->where('stat_date', $date)->delete('api_logs_user_daily');
            }
            return false;
        }

        $avgResponseTime = $overall['avg_response_time'] !== null ? (float)$overall['avg_response_time'] : 0;

        $this->db->replace('api_logs_daily', [
            'stat_date' => $date,
            'uri' => self::API_LOGS_TOTAL_KEY,
            'method' => self::API_LOGS_TOTAL_METHOD,
            'total_requests' => (int)$overall['total_requests'],
            'success_count' => (int)$overall['success_count'],
            'error_count' => (int)$overall['error_count'],
            'avg_response_time' => $avgResponseTime,
            'avg_runtime' => $avgResponseTime
        ]);

        $logStats = $this->db
            ->select('
                uri,
                method,
                COUNT(*) as total_requests,
                SUM(CASE WHEN response_code >= 400 OR authorized = "0" THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN response_code < 400 AND authorized = "1" THEN 1 ELSE 0 END) as success_count,
                AVG(rtime) as avg_response_time
            ')
            ->where('time >=', $startTimestamp)
            ->where('time <', $endTimestamp)
            ->group_by(['uri', 'method'])
            ->get('api_logs')
            ->result_array();

        foreach ($logStats as $stat) {
            $avg = $stat['avg_response_time'] !== null ? (float)$stat['avg_response_time'] : 0;

            $this->db->replace('api_logs_daily', [
                'stat_date' => $date,
                'uri' => $stat['uri'],
                'method' => $stat['method'],
                'total_requests' => (int)$stat['total_requests'],
                'success_count' => (int)$stat['success_count'],
                'error_count' => (int)$stat['error_count'],
                'avg_response_time' => $avg,
                'avg_runtime' => $avg
            ]);
        }

        if ($hasIpTable) {
            $ipStats = $this->db
                ->select('
                    ip_address,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN response_code >= 400 OR authorized = "0" THEN 1 ELSE 0 END) as error_count,
                    AVG(rtime) as avg_response_time
                ')
                ->where('time >=', $startTimestamp)
                ->where('time <', $endTimestamp)
                ->group_by('ip_address')
                ->get('api_logs')
                ->result_array();

            foreach ($ipStats as $stat) {
                $avg = $stat['avg_response_time'] !== null ? (float)$stat['avg_response_time'] : 0;

                $this->db->replace('api_logs_ip_daily', [
                    'stat_date' => $date,
                    'ip_address' => $stat['ip_address'],
                    'total_requests' => (int)$stat['total_requests'],
                    'error_count' => (int)$stat['error_count'],
                    'avg_response_time' => $avg
                ]);
            }
        }

        if ($hasUserTable) {
            $userStats = $this->db
                ->select('
                    user_id,
                    api_key,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN response_code >= 400 OR authorized = "0" THEN 1 ELSE 0 END) as error_count,
                    AVG(rtime) as avg_response_time
                ')
                ->where('time >=', $startTimestamp)
                ->where('time <', $endTimestamp)
                ->group_by(['user_id', 'api_key'])
                ->get('api_logs')
                ->result_array();

            foreach ($userStats as $stat) {
                $avg = $stat['avg_response_time'] !== null ? (float)$stat['avg_response_time'] : 0;

                $userId = isset($stat['user_id']) && $stat['user_id'] !== null ? (int)$stat['user_id'] : 0;
                $apiKey = isset($stat['api_key']) && $stat['api_key'] !== null ? $stat['api_key'] : '';

                $this->db->replace('api_logs_user_daily', [
                    'stat_date' => $date,
                    'user_id' => $userId,
                    'api_key' => $apiKey,
                    'total_requests' => (int)$stat['total_requests'],
                    'error_count' => (int)$stat['error_count'],
                    'avg_response_time' => $avg
                ]);
            }
        }

        return true;
    }

    /**
     * Aggregate data for a specific day
     */
    private function aggregate_day($date)
    {
        $start_date = $date . ' 00:00:00';
        $end_date = date('Y-m-d 00:00:00', strtotime($date . ' +1 day'));

        // 0. Aggregate overall site totals for the day
        $overall_stats = $this->db
            ->select('
                SUM(CASE WHEN event_type = "page_view" THEN 1 ELSE 0 END) as total_views,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT session_id) as unique_sessions,
                SUM(CASE WHEN event_type = "click" THEN 1 ELSE 0 END) as total_clicks,
                SUM(CASE WHEN event_type = "error" THEN 1 ELSE 0 END) as total_errors
            ')
            ->where('created_at >=', $start_date)
            ->where('created_at <', $end_date)
            ->get('analytics_events')
            ->row_array();

        if (!$overall_stats) {
            $overall_stats = [];
        }

        $this->db->replace('analytics_daily', [
            'stat_date' => $date,
            'page' => self::DAILY_TOTAL_KEY,
            'obj_type' => null,
            'obj_value' => null,
            'total_views' => (int)($overall_stats['total_views'] ?? 0),
            'unique_users' => (int)($overall_stats['unique_users'] ?? 0),
            'unique_ips' => (int)($overall_stats['unique_ips'] ?? 0),
            'unique_sessions' => (int)($overall_stats['unique_sessions'] ?? 0),
            'total_clicks' => (int)($overall_stats['total_clicks'] ?? 0),
            'total_errors' => (int)($overall_stats['total_errors'] ?? 0)
        ]);
        
        // 1. Aggregate by page (existing behavior - page-level stats)
        $page_stats = $this->db
            ->select('
                page,
                COUNT(*) as total_views,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT session_id) as unique_sessions,
                SUM(CASE WHEN event_type = "click" THEN 1 ELSE 0 END) as total_clicks,
                SUM(CASE WHEN event_type = "error" THEN 1 ELSE 0 END) as total_errors
            ')
            ->where('created_at >=', $start_date)
            ->where('created_at <', $end_date)
            ->where('event_type', 'page_view')
            ->group_by('page')
            ->get('analytics_events')
            ->result_array();
        
        // Insert/update daily stats for page-level aggregation
        foreach ($page_stats as $stat) {
            $this->db->replace('analytics_daily', [
                'stat_date' => $date,
                'page' => $stat['page'],
                'obj_type' => null,  // Page-level (no object)
                'obj_value' => null,
                'total_views' => $stat['total_views'],
                'unique_users' => $stat['unique_users'],
                'unique_ips' => $stat['unique_ips'],
                'unique_sessions' => $stat['unique_sessions'],
                'total_clicks' => $stat['total_clicks'],
                'total_errors' => $stat['total_errors']
            ]);
        }
        
        // 2. Aggregate by object (NEW - object-level stats)
        $object_stats = $this->db
            ->select('
                obj_type,
                obj_value,
                COUNT(*) as total_views,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT session_id) as unique_sessions,
                SUM(CASE WHEN event_type = "click" THEN 1 ELSE 0 END) as total_clicks,
                SUM(CASE WHEN event_type = "error" THEN 1 ELSE 0 END) as total_errors
            ')
            ->where('created_at >=', $start_date)
            ->where('created_at <', $end_date)
            ->where('obj_type IS NOT NULL')
            ->where('obj_value IS NOT NULL')
            ->group_by(['obj_type', 'obj_value'])
            ->get('analytics_events')
            ->result_array();
        
        // Insert/update daily stats for object-level aggregation
        foreach ($object_stats as $stat) {
            // Get a representative page for this object (most common page for this object)
            $representative_page = $this->db
                ->select('page, COUNT(*) as cnt')
                ->where('created_at >=', $start_date)
                ->where('created_at <', $end_date)
                ->where('obj_type', $stat['obj_type'])
                ->where('obj_value', $stat['obj_value'])
                ->group_by('page')
                ->order_by('cnt', 'DESC')
                ->limit(1)
                ->get('analytics_events')
                ->row();
            
            $this->db->replace('analytics_daily', [
                'stat_date' => $date,
                'page' => $representative_page ? $representative_page->page : '',
                'obj_type' => $stat['obj_type'],
                'obj_value' => $stat['obj_value'],
                'total_views' => $stat['total_views'],
                'unique_users' => $stat['unique_users'],
                'unique_ips' => $stat['unique_ips'],
                'unique_sessions' => $stat['unique_sessions'],
                'total_clicks' => $stat['total_clicks'],
                'total_errors' => $stat['total_errors']
            ]);
        }
    }

    /**
     * Get last aggregated date
     */
    private function get_last_aggregated_date()
    {
        $result = $this->db
            ->select_max('stat_date')
            ->get('analytics_daily')
            ->row();
        
        return $result->stat_date ?? null;
    }

    private function get_last_api_logs_aggregated_date()
    {
        $result = $this->db
            ->select_max('stat_date')
            ->get('api_logs_daily')
            ->row();

        return $result->stat_date ?? null;
    }

    public function get_api_logs_aggregation_status()
    {
        $tablesExist = $this->db->table_exists('api_logs') && $this->db->table_exists('api_logs_daily');
        if (!$tablesExist) {
            return [
                'available' => false,
                'message' => 'API logs tables are missing'
            ];
        }

        $lastAggregated = $this->get_last_api_logs_aggregated_date();
        $today = date('Y-m-d');

        $totalLogs = $this->db->count_all('api_logs');

        $aggregatedRows = $this->db->count_all('api_logs_daily');

        $hasIpTable = $this->db->table_exists('api_logs_ip_daily');
        $hasUserTable = $this->db->table_exists('api_logs_user_daily');

        $ipRows = $hasIpTable ? $this->db->count_all('api_logs_ip_daily') : null;
        $userRows = $hasUserTable ? $this->db->count_all('api_logs_user_daily') : null;

        $needsAggregation = !$lastAggregated || $lastAggregated < date('Y-m-d', strtotime('-1 day'));

        return [
            'available' => true,
            'last_aggregated' => $lastAggregated,
            'total_raw_logs' => $totalLogs,
            'daily_rows' => $aggregatedRows,
            'ip_rows' => $ipRows,
            'user_rows' => $userRows,
            'needs_aggregation' => $needsAggregation,
            'today' => $today
        ];
    }

    /**
     * Archive and cleanup old analytics data
     * 
     * @return array Cleanup result
     */
    public function archive_and_cleanup()
    {
        // Archive logs older than 90 days
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        
        // Get months to archive
        $months = $this->db
            ->select('DATE_FORMAT(created_at, "%Y-%m") as month')
            ->where('created_at <', $cutoff_date)
            ->group_by('month')
            ->get('analytics_events')
            ->result_array();
        
        $archived_months = 0;
        $deleted_rows = 0;
        
        foreach ($months as $month_row) {
            $month = $month_row['month'];
            
            // Archive to CSV
            $archived = $this->archive_month($month);
            if ($archived) {
                $archived_months++;
                
                // Delete archived data
                $month_start = $month . '-01 00:00:00';
                $month_end = date('Y-m-d 00:00:00', strtotime($month . '-01 +1 month'));
                
                $this->db
                    ->where('created_at >=', $month_start)
                    ->where('created_at <', $month_end)
                    ->delete('analytics_events');
                
                $deleted_rows += $this->db->affected_rows();
            }
        }
        
        return [
            'success' => true,
            'archived_months' => $archived_months,
            'deleted_rows' => $deleted_rows,
            'cutoff_date' => date('Y-m-d', strtotime($cutoff_date))
        ];
    }

    /**
     * Archive a month's data to CSV
     */
    private function archive_month($month)
    {
        $month_start = $month . '-01 00:00:00';
        $month_end = date('Y-m-d 00:00:00', strtotime($month . '-01 +1 month'));
        
        // Get data for the month
        $data = $this->db
            ->where('created_at >=', $month_start)
            ->where('created_at <', $month_end)
            ->order_by('created_at', 'ASC')
            ->get('analytics_events')
            ->result_array();
        
        if (empty($data)) {
            return false;
        }
        
        // Create archive directory if it doesn't exist
        $archive_dir = APPPATH . '../logs/analytics_archive';
        if (!is_dir($archive_dir)) {
            mkdir($archive_dir, 0755, true);
        }
        
        // Write to CSV
        $filename = $archive_dir . '/analytics_events_' . $month . '.csv';
        $fp = fopen($filename, 'w');
        
        // Write headers
        fputcsv($fp, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        return true;
    }

    /**
     * Get aggregation status
     */
    public function get_aggregation_status()
    {
        $last_aggregated = $this->get_last_aggregated_date();
        
        // Count raw events
        $total_events = $this->db->count_all('analytics_events');
        
        // Count events older than 90 days
        $old_events = $this->db
            ->where('created_at <', date('Y-m-d H:i:s', strtotime('-90 days')))
            ->count_all_results('analytics_events');
        
        // Count aggregated days
        $aggregated_days = $this->db->count_all('analytics_daily');
        
        return [
            'last_aggregated' => $last_aggregated,
            'total_raw_events' => $total_events,
            'old_events_pending_cleanup' => $old_events,
            'aggregated_days' => $aggregated_days,
            'needs_aggregation' => $last_aggregated < date('Y-m-d', strtotime('-1 day')),
            'needs_cleanup' => $old_events > 0
        ];
    }

    /**
     * Clear analytics cache
     */
    public function clear_analytics_cache()
    {
        // Clear all analytics-related cache keys
        $this->cache->delete($this->cache_prefix . 'stats_7');
        $this->cache->delete($this->cache_prefix . 'stats_30');
        $this->cache->delete($this->cache_prefix . 'stats_90');
        $this->cache->delete($this->cache_prefix . 'performance_7');
        $this->cache->delete($this->cache_prefix . 'performance_30');
        $this->cache->delete($this->cache_prefix . 'performance_90');
    }
}

