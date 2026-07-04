<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Migration extends CI_Migration {

    protected $migration_report = array();
    
    public function __construct()
    {
        parent::__construct();
        $this->prevent_timeouts();
    }
    
    /**
     * Prevent timeouts during long-running migrations
     * 
     * Sets appropriate limits for:
     * - PHP execution time
     * - Memory usage
     * - Database timeouts
     */
    protected function prevent_timeouts()
    {
        // Remove PHP execution time limit for CLI migrations
        if (php_sapi_name() === 'cli') {
            set_time_limit(0);
            ini_set('max_execution_time', '0');
            
            echo "⚙ Timeout prevention configured:\n";
            echo "  • PHP execution time: unlimited\n";
        } else {
            // For web context: set limits but do not echo (would cause "headers already sent"
            // when the theme later calls header()). Log instead.
            set_time_limit(0);
            ini_set('max_execution_time', '0');
            
            $current_memory = ini_get('memory_limit');
            $current_bytes = $this->parse_memory_limit($current_memory);
            $recommended_bytes = 512 * 1024 * 1024; // 512MB
            
            if ($current_bytes < $recommended_bytes) {
                ini_set('memory_limit', '512M');
                log_message('info', 'Migration: memory limit increased to 512M (was ' . $current_memory . ')');
            } else {
                log_message('info', 'Migration: memory limit ' . $current_memory . ' (sufficient)');
            }
        }
    }
    
    /**
     * Parse memory limit string to bytes
     * Handles formats like: 128M, 1G, 512K
     */
    protected function parse_memory_limit($limit)
    {
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $value = (int)$limit;
        
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    protected function execute_sql_file($filename)
    {
        $templine = '';
        $lines = file($filename);
        $statement_num = 0;
        $succeeded = 0;
        $skipped = 0;
        $failed = 0;
        
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "Migration Report: " . basename($filename) . "\n";
        echo str_repeat('=', 80) . "\n\n";
        flush();
        
        foreach ($lines as $line_num => $line) {
            if (substr(trim($line), 0, 2) == '--' || substr(trim($line), 0, 1) == '#' || trim($line) == '') {
                continue;
            }
            
            $templine .= $line;
            
            if (substr(trim($line), -1, 1) == ';' || strtoupper(substr(trim($line), -2)) == 'GO') {
                $templine = str_replace(';', '', $templine);
                $templine = str_replace('GO', '', $templine);
                $templine = trim($templine);
                
                if (!empty($templine)) {
                    $statement_num++;
                    
                    // Show statement being executed
                    echo "Statement #{$statement_num} (line {$line_num}):\n";
                    echo $this->format_sql_for_display($templine) . "\n";
                    flush();
                    
                    // Execute with error suppression
                    $start_time = microtime(true);
                    $result = $this->db->query($templine);
                    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
                    
                    if ($result === FALSE) {
                        $error = $this->db->error();
                        $error_code = $error['code'];
                        
                        // Check if error is safe to skip (handles "42S21/2705" format internally)
                        if ($this->is_safe_to_skip_error($error_code, $this->db->dbdriver)) {
                            $skipped++;
                            echo "✓ SKIPPED (already applied) - Error {$error_code}: {$error['message']}\n";
                            echo "  Time: {$execution_time}ms\n\n";
                            flush();
                            
                            log_message('info', "Skipped (error {$error_code}): " . substr($templine, 0, 100) . '...');
                            
                            $this->migration_report[] = array(
                                'statement' => $statement_num,
                                'line' => $line_num,
                                'sql' => $templine,
                                'status' => 'SKIPPED',
                                'error_code' => $error_code,
                                'message' => $error['message'],
                                'time_ms' => $execution_time
                            );
                        } else {
                            $failed++;
                            echo "✗ FAILED - Error {$error_code}: {$error['message']}\n";
                            echo "  Time: {$execution_time}ms\n\n";
                            flush();
                            
                            $error_msg = "Migration failed at line {$line_num}\n";
                            $error_msg .= "Error {$error_code}: {$error['message']}\n";
                            $error_msg .= "SQL: {$templine}";
                            log_message('error', $error_msg);
                            
                            $this->migration_report[] = array(
                                'statement' => $statement_num,
                                'line' => $line_num,
                                'sql' => $templine,
                                'status' => 'FAILED',
                                'error_code' => $error_code,
                                'message' => $error['message'],
                                'time_ms' => $execution_time
                            );
                            
                            $this->print_migration_summary($succeeded, $skipped, $failed);
                            throw new Exception($error_msg);
                        }
                    } else {
                        $succeeded++;
                        $affected_rows = $this->db->affected_rows();
                        echo "✓ SUCCESS";
                        if ($affected_rows >= 0) {
                            echo " - {$affected_rows} row(s) affected";
                        }
                        echo "\n  Time: {$execution_time}ms\n\n";
                        flush();
                        
                        log_message('info', 'Migration statement succeeded: ' . substr($templine, 0, 100) . '...');
                        
                        $this->migration_report[] = array(
                            'statement' => $statement_num,
                            'line' => $line_num,
                            'sql' => $templine,
                            'status' => 'SUCCESS',
                            'affected_rows' => $affected_rows,
                            'time_ms' => $execution_time
                        );
                    }
                }
                
                $templine = '';
            }
        }
        
        $this->print_migration_summary($succeeded, $skipped, $failed);
        
        log_message('info', "SQL file execution completed: {$filename} (Success: {$succeeded}, Skipped: {$skipped}, Failed: {$failed})");
    }
    
    protected function format_sql_for_display($sql, $max_length = 200)
    {
        $sql = preg_replace('/\s+/', ' ', $sql);
        if (strlen($sql) > $max_length) {
            return substr($sql, 0, $max_length) . '...';
        }
        return $sql;
    }
    
    protected function print_migration_summary($succeeded, $skipped, $failed)
    {
        echo str_repeat('=', 80) . "\n";
        echo "Migration Summary\n";
        echo str_repeat('=', 80) . "\n";
        echo "✓ SUCCESS: {$succeeded} statement(s)\n";
        echo "⊘ SKIPPED: {$skipped} statement(s) (already applied)\n";
        echo "✗ FAILED:  {$failed} statement(s)\n";
        echo str_repeat('=', 80) . "\n\n";
        flush();
    }
    
    public function get_migration_report()
    {
        return $this->migration_report;
    }
    
    /**
     * Extract native error code from database error
     * 
     * SQL Server ODBC returns errors in format: "SQLSTATE/NativeCode" (e.g., "42S11/1913")
     * This extracts the native code for consistent checking
     * 
     * @param mixed $error_code Error code from database
     * @return int Native error code
     */
    protected function extract_native_error_code($error_code)
    {
        // SQL Server ODBC format: "42S11/1913" -> extract 1913
        if (is_string($error_code) && strpos($error_code, '/') !== false) {
            $parts = explode('/', $error_code);
            return (int)$parts[1];
        }
        
        // Already numeric or standard format
        return (int)$error_code;
    }
    
    protected function is_safe_to_skip_error($error_code, $db_driver)
    {
        if (in_array($db_driver, array('mysql', 'mysqli'))) {
            $safe_errors = array(
                1050,  // Table already exists
                1051,  // Unknown table (DROP TABLE on non-existent table)
                1060,  // Duplicate column name
                1061,  // Duplicate key name
                1062,  // Duplicate entry (INSERT on existing unique key)
                1068,  // Multiple primary keys
                1091,  // Can't DROP - doesn't exist
                1146,  // Table doesn't exist
                1826,  // Duplicate foreign key constraint name
            );
            
            // MySQL SQLSTATE codes (fallback)
            $safe_sqlstates = array('42S01', '42S02', '42S21', '42S22');
            
        } elseif ($db_driver == 'sqlsrv') {
            $safe_errors = array(
                1712,  // Cannot ALTER TABLE because clustered index is being rebuilt
                1913,  // The operation failed because an index or statistics already exists
                2705,  // Column already exists
                2714,  // Object already exists (table/view)
                3728,  // Could not drop constraint (does not exist)
                15723, // Object already exists (SQL Server)
            );
            
            // SQL Server SQLSTATE codes (fallback)
            $safe_sqlstates = array(
                '42S01',  // Base table or view already exists
                '42S02',  // Base table or view not found
                '42S11',  // Index already exists
                '42S21',  // Column already exists
                '42S22',  // Column not found
            );
            
        } else {
            $safe_errors = array();
            $safe_sqlstates = array();
        }
        
        // Extract native error code from compound format (e.g., "42S21/2705" -> 2705)
        $native_code = $this->extract_native_error_code($error_code);
        
        // Extract SQLSTATE from compound format (e.g., "42S21/2705" -> "42S21")
        $sqlstate = $this->extract_sqlstate_code($error_code);
        
        // Check native error code
        if (in_array($native_code, $safe_errors)) {
            return true;
        }
        
        // Check SQLSTATE code
        if (isset($safe_sqlstates) && in_array($sqlstate, $safe_sqlstates)) {
            return true;
        }
        
        // Check original error code (in case it's already numeric or plain SQLSTATE)
        if (in_array($error_code, $safe_errors)) {
            return true;
        }
        
        if (isset($safe_sqlstates) && in_array($error_code, $safe_sqlstates)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extract SQLSTATE code from compound format
     * 
     * @param mixed $error_code Error code from database
     * @return string SQLSTATE code or original if not compound
     */
    protected function extract_sqlstate_code($error_code)
    {
        // SQL Server ODBC format: "42S21/2705" -> extract "42S21"
        if (is_string($error_code) && strpos($error_code, '/') !== false) {
            $parts = explode('/', $error_code);
            return trim($parts[0]);
        }
        
        // Return as-is if it's already a string (might be SQLSTATE)
        return (string)$error_code;
    }
    
    protected function get_sql_file_path($filename)
    {
        return APPPATH . '../install/' . $filename . '.sql';
    }
}

