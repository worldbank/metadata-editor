<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Analytics Helper
 * 
 * Helper functions for analytics configuration and utilities
 * 
 * @package    Metadata Editor
 * @category   Helpers
 */

if ( ! function_exists('is_analytics_enabled'))
{
	/**
	 * Check if analytics tracking is enabled
	 * 
	 * @return boolean
	 */
	function is_analytics_enabled()
	{
		$CI =& get_instance();
		$CI->config->load('analytics', TRUE);
		$enabled = $CI->config->item('analytics_enabled', 'analytics');
		return $enabled !== FALSE;
	}
}

if ( ! function_exists('analytics_track_hash_changes'))
{
	/**
	 * Check if hash changes should be tracked
	 * 
	 * @return boolean
	 */
	function analytics_track_hash_changes()
	{
		$CI =& get_instance();
		$CI->config->load('analytics', TRUE);
		$track_hash = $CI->config->item('analytics_track_hash_changes', 'analytics');
		return $track_hash === TRUE;
	}
}

if ( ! function_exists('get_analytics_config'))
{
	/**
	 * Get analytics configuration as array
	 * 
	 * @return array
	 */
	function get_analytics_config()
	{
		$CI =& get_instance();
		$CI->config->load('analytics', TRUE);
		
		return array(
			'enabled' => is_analytics_enabled(),
			'track_hash_changes' => analytics_track_hash_changes()
		);
	}
}

/* End of file analytics_helper.php */
/* Location: ./application/helpers/analytics_helper.php */


