<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use React\EventLoop\Factory;

/**
 * CLI Worker Controller
 * 
 * Handles background job processing using ReactPHP event loop
 * 
 * Usage:
 *   php index.php cli/worker/run
 *   php index.php cli/worker/run --poll-interval=5
 *   php index.php cli/worker/run --max-jobs=500
 */
class Worker extends CI_Controller
{
    private static $current_job_id = null;
    private static $current_job_completed = false;
    private static $shutdown_handler_registered = false;

    private $loop;
    private $poll_interval = 5; // seconds
    private $max_jobs = 0;      // 0 = unlimited; exit after N jobs to prevent memory leaks
    private $jobs_processed = 0;
    private $worker_id;
    private $pid_file;
    private $heartbeat_file;
    private $tmp_path;
    
    public function __construct()
    {
        parent::__construct();
        
        // Only allow CLI access
        if (php_sapi_name() !== 'cli') {
            show_error('This controller can only be accessed via CLI');
            exit(1);
        }
        
        // Load ReactPHP
        require_once __DIR__ . '/../../../modules/reactphp/vendor/autoload.php';
        
        // Load job handlers
        require_once APPPATH . 'libraries/Jobs/JobHandlerInterface.php';
        require_once APPPATH . 'libraries/Jobs/JobRegistry.php';
        
        // Load required models and config
        $this->load->database();
        $this->load->library('db_keepalive');
        $this->load->model('Job_queue_model');
        $this->load->config('editor');
        
        // Setup tmp path for PID and heartbeat files
        $storage_path = $this->config->item('storage_path', 'editor');
        $this->tmp_path = rtrim($storage_path, '/') . '/tmp';

        // Ensure tmp directory exists
        if (!file_exists($this->tmp_path)) {
            @mkdir($this->tmp_path, 0777, true);
        }
        
        // Parse command line arguments
        $this->parse_arguments();
        
        // Generate unique worker ID
        $this->worker_id = 'worker-' . getmypid() . '-' . time();
        
        // Setup file paths
        $this->pid_file = $this->tmp_path . '/worker.pid';
        $this->heartbeat_file = $this->tmp_path . '/worker.heartbeat';
    }
    
    /**
     * Parse command line arguments
     */
    private function parse_arguments()
    {
        global $argv;
        
        foreach ($argv as $arg) {
            if (strpos($arg, '--poll-interval=') === 0) {
                $this->poll_interval = (int) substr($arg, 16);
            }
            if (strpos($arg, '--max-jobs=') === 0) {
                $this->max_jobs = (int) substr($arg, 11);
            }
        }
    }
    
    /**
     * Main worker run method
     * Starts the ReactPHP event loop and processes jobs
     */
    public function run()
    {
        // Long-running daemon: do not inherit web PHP max_execution_time limits
        set_time_limit(0);
        ini_set('max_execution_time', '0');

        $this->loop = Factory::create();
        
        echo "[Worker] Starting job queue worker\n";
        echo "[Worker] Worker ID: {$this->worker_id}\n";
        echo "[Worker] Poll interval: {$this->poll_interval} seconds\n";
        if ($this->max_jobs > 0) {
            echo "[Worker] Max jobs per run: {$this->max_jobs} (exit after to prevent memory leaks)\n";
        }
        echo "[Worker] PID file: {$this->pid_file}\n";
        echo "[Worker] Heartbeat file: {$this->heartbeat_file}\n";
        echo "[Worker] Press Ctrl+C to stop\n\n";
        
        // Create PID file
        $this->create_pid_file();
        
        // Maintenance: reset stuck processing jobs and expire ancient pending jobs
        $maintenance = $this->Job_queue_model->run_job_maintenance();
        if ($maintenance['reset_stuck'] > 0) {
            echo "[Worker] Reset {$maintenance['reset_stuck']} stuck job(s)\n";
        }
        if ($maintenance['expired_pending'] > 0) {
            echo "[Worker] Expired {$maintenance['expired_pending']} stale pending job(s)\n";
        }
        
        // Update heartbeat immediately
        $this->update_heartbeat();
        
        // Update heartbeat every 5 seconds
        $this->loop->addPeriodicTimer(5, function() {
            $this->update_heartbeat();
        });
        
        // Process queue periodically
        $this->loop->addPeriodicTimer($this->poll_interval, function() {
            $this->process_queue();
        });

        // Periodic maintenance (stuck reset + pending expiry)
        $this->loop->addPeriodicTimer(3600, function() {
            $this->run_job_maintenance();
        });
        
        // Handle graceful shutdown
        if (function_exists('pcntl_signal')) {
            $this->loop->addSignal(SIGTERM, function() {
                echo "\n[Worker] SIGTERM received, shutting down gracefully...\n";
                $this->cleanup_files();
                $this->loop->stop();
            });
            
            $this->loop->addSignal(SIGINT, function() {
                echo "\n[Worker] SIGINT received, shutting down gracefully...\n";
                $this->cleanup_files();
                $this->loop->stop();
            });
        }
        
        // Register shutdown function for cleanup and orphaned job recovery
        $this->register_shutdown_handlers();
        
        // Run the event loop (blocks until stopped)
        $this->loop->run();
        
        // Cleanup on exit
        $this->cleanup_files();
        
        echo "[Worker] Worker stopped\n";
    }
    
    /**
     * Create PID file
     */
    private function create_pid_file()
    {
        $pid_data = array(
            'pid' => getmypid(),
            'worker_id' => $this->worker_id,
            'started_at' => date('Y-m-d H:i:s'),
            'poll_interval' => $this->poll_interval
        );
        
        file_put_contents($this->pid_file, json_encode($pid_data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Register shutdown handlers once per process.
     */
    private function register_shutdown_handlers()
    {
        register_shutdown_function(array($this, 'cleanup_files'));

        if (!self::$shutdown_handler_registered) {
            register_shutdown_function(array(__CLASS__, 'handle_abnormal_shutdown'));
            self::$shutdown_handler_registered = true;
        }
    }

    /**
     * Mark the active job failed when the worker exits without completing it.
     */
    public static function handle_abnormal_shutdown()
    {
        if (self::$current_job_id === null || self::$current_job_completed) {
            return;
        }

        $job_id = self::$current_job_id;
        self::$current_job_id = null;

        if (!function_exists('get_instance')) {
            return;
        }

        $ci =& get_instance();
        if (!$ci || !isset($ci->Job_queue_model)) {
            return;
        }

        try {
            if (isset($ci->db_keepalive)) {
                $ci->db_keepalive->ping();
            }
            $ci->Job_queue_model->mark_failed($job_id, 'Worker process exited unexpectedly');
        } catch (Throwable $e) {
            log_message('error', 'Worker::handle_abnormal_shutdown failed for job #' . $job_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Update heartbeat file
     */
    private function update_heartbeat()
    {
        $this->load->library('Worker_heartbeat');
        Worker_heartbeat::touch($this->worker_id);
    }
    
    /**
     * Cleanup PID and heartbeat files on shutdown
     */
    public function cleanup_files()
    {
        if (file_exists($this->pid_file)) {
            @unlink($this->pid_file);
        }
        
        if (file_exists($this->heartbeat_file)) {
            @unlink($this->heartbeat_file);
        }
    }
    
    /**
     * Process jobs from the queue
     */
    private function process_queue()
    {
        $job = null;

        try {
            $this->db_keepalive->ping();

            // Get next pending job
            $job = $this->Job_queue_model->get_next_job($this->worker_id);
            
            if (!$job) {
                // No jobs available
                return;
            }
            
            $job_uuid = isset($job['uuid']) ? $job['uuid'] : 'N/A';
            echo "[Worker] Processing job #{$job['id']} (UUID: {$job_uuid}, Type: {$job['job_type']}) at " . date('Y-m-d H:i:s') . "\n";
            
            // Job is already marked as processing by get_next_job()
            // Handle the job
            $this->handle_job($job);

            $this->jobs_processed++;
            if ($this->max_jobs > 0 && $this->jobs_processed >= $this->max_jobs) {
                echo "[Worker] Reached max jobs ({$this->max_jobs}), exiting for restart (memory leak prevention)\n";
                $this->loop->stop();
                return;
            }

        } catch (Throwable $e) {
            log_message('error', 'Worker::process_queue error: ' . $e->getMessage());
            echo "[Worker] Error: " . $e->getMessage() . "\n";
            
            if (isset($job['id'])) {
                $this->db_keepalive->ping();
                $this->Job_queue_model->mark_failed($job['id'], $e->getMessage());
            }
        }
    }
    
    /**
     * Handle a single job
     * 
     * @param array $job Job data
     */
    private function handle_job($job)
    {
        $payload = $job['payload'];

        self::$current_job_id = $job['id'];
        self::$current_job_completed = false;
        
        try {
            // Get handler for this job type
            $handler = JobRegistry::getHandler($job['job_type']);
            
            if (!$handler) {
                throw new Exception("No handler found for job type: {$job['job_type']}");
            }
            
            // Process the job using the handler
            $result = $handler->process($job, $payload);
            
            $this->db_keepalive->ping();
            $this->Job_queue_model->mark_completed($job['id'], $result);
            self::$current_job_completed = true;
            self::$current_job_id = null;
            $job_uuid = isset($job['uuid']) ? $job['uuid'] : 'N/A';
            echo "[Worker] Job #{$job['id']} (UUID: {$job_uuid}) completed successfully\n";
            
        } catch (Throwable $e) {
            self::$current_job_id = null;
            $this->db_keepalive->ping();
            $this->Job_queue_model->mark_failed($job['id'], $e->getMessage());
            $job_uuid = isset($job['uuid']) ? $job['uuid'] : 'N/A';
            echo "[Worker] Job #{$job['id']} (UUID: {$job_uuid}) failed: " . $e->getMessage() . "\n";
            throw $e; // Re-throw to be caught by process_queue
        }
    }
    
    /**
     * Reset stuck processing jobs and expire stale pending jobs (records kept as failed)
     */
    private function run_job_maintenance()
    {
        try {
            $this->db_keepalive->ping();
            $maintenance = $this->Job_queue_model->run_job_maintenance();
            if ($maintenance['reset_stuck'] > 0) {
                echo "[Worker] Reset {$maintenance['reset_stuck']} stuck job(s)\n";
            }
            if ($maintenance['expired_pending'] > 0) {
                echo "[Worker] Expired {$maintenance['expired_pending']} stale pending job(s)\n";
            }
        } catch (Throwable $e) {
            log_message('error', 'Worker::run_job_maintenance error: ' . $e->getMessage());
            echo "[Worker] Error during job maintenance: " . $e->getMessage() . "\n";
        }
    }
    
    
}

