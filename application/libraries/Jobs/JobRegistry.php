<?php

/**
 * Job Registry
 * 
 * Manages registration and retrieval of job handlers
 */
class JobRegistry
{
    private static $handlers = array();
    private static $initialized = false;
    private static $aliases = array(
        'pdf_generation' => 'generate_pdf',
    );
    
    /**
     * Initialize the registry by loading all job handlers
     */
    public static function initialize()
    {
        if (self::$initialized) {
            return;
        }
        
        // Load all handlers from the handlers directory
        $handlers_path = __DIR__ . '/handlers/';
        
        if (is_dir($handlers_path)) {
            $files = glob($handlers_path . '*Job.php');
            
            foreach ($files as $file) {
                try {
                    require_once $file;
                    
                    $class_name = basename($file, '.php');
                    
                    if (class_exists($class_name)) {
                        $handler = new $class_name();
                        
                        if ($handler instanceof JobHandlerInterface) {
                            $job_type = $handler->getJobType();
                            self::$handlers[$job_type] = $handler;
                        }
                    }
                } catch (Throwable $e) {
                    log_message('error', 'JobRegistry: failed to load handler ' . basename($file) . ': ' . $e->getMessage());
                }
            }
        }
        
        self::$initialized = true;
    }
    
    /**
     * Get a handler for a specific job type
     * 
     * @param string $job_type Job type
     * @return JobHandlerInterface|null Handler instance or null if not found
     */
    public static function getHandler($job_type)
    {
        self::initialize();

        if (isset(self::$aliases[$job_type])) {
            $job_type = self::$aliases[$job_type];
        }
        
        return isset(self::$handlers[$job_type]) ? self::$handlers[$job_type] : null;
    }
    
    /**
     * Get all registered job types
     * 
     * @return array Array of job type strings
     */
    public static function getJobTypes()
    {
        self::initialize();
        
        return array_keys(self::$handlers);
    }
    
    /**
     * Check if a job type is registered
     * 
     * @param string $job_type Job type
     * @return bool True if registered
     */
    public static function hasHandler($job_type)
    {
        self::initialize();

        if (isset(self::$aliases[$job_type])) {
            $job_type = self::$aliases[$job_type];
        }
        
        return isset(self::$handlers[$job_type]);
    }
    
    /**
     * Register a handler manually (for testing or custom registration)
     * 
     * @param JobHandlerInterface $handler Handler instance
     */
    public static function register(JobHandlerInterface $handler)
    {
        $job_type = $handler->getJobType();
        self::$handlers[$job_type] = $handler;
    }
}

