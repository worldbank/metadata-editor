<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * External Resource Helper
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 */

// ------------------------------------------------------------------------

/**
 * removes the brackets added by nesstar to include the value of the field
 *
 * e.g. English [en] - removes [en]
 *
 * @access	public
 * @return	array
 */	
if ( ! function_exists('strip_brackets'))
{
  
function strip_brackets($value)
{
	$pos=strpos($value,"[");
	if ($pos > 0)
	{
		return substr($value,0,$pos-1);
	}
	
	return $value;
}
  
}

if ( ! function_exists('check_resource_file'))
{
	function check_resource_file($file_path)
	{
		$file_path=unix_path($file_path);
		if (file_exists($file_path))
		{
			return $file_path;
		}
		return FALSE;
	}
}
/* End of file email_helper.php */
/* Location: ./system/helpers/resource_helper.php */