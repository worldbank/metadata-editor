<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Analytics Configuration
|--------------------------------------------------------------------------
|
| Enable or disable analytics tracking across the application
|
*/

// Enable/disable analytics tracking
$config['analytics_enabled'] = TRUE;

// Track hash (#) changes in URLs (e.g., /page#section)
// Set to FALSE to only track full page loads, not hash navigation
// Useful for SPAs where hash changes don't represent significant page views
$config['analytics_track_hash_changes'] = FALSE;

/* End of file analytics.php */
/* Location: ./application/config/analytics.php */

