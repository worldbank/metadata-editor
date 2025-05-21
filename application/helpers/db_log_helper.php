<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * database logging
 *
 */	
if ( ! function_exists('log_db'))
{
	function log_db($level = 'error', $message='', $php_error = FALSE)
	{
		echo $this->db->last_query();exit;
	}
}

/* End of file search_helper.php */
/* Location: ./application/helpers/search_helper.php */