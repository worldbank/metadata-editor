<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2009, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CodeIgniter Language Helpers
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/helpers/language_helper.html
 */

// ------------------------------------------------------------------------

/**
 * Lang
 *
 * Fetches a language variable and optionally outputs a form label
 *
 * @access	public
 * @param	string	the language line
 * @param	string	the id of the form element
 * @return	string
 */	
if ( ! function_exists('t'))
{
	function t($line, $id = '')
	{
		$CI =& get_instance();
		$str = $CI->lang->line($line);

		if ($id != '')
		{
			$str = '<label for="'.$id.'">'.$str."</label>";
		}
		if ($str=='')
		{
			//log missing translations
			log_message('DEBUG','Missing Translation - '. $line);
			return $line;
			//return '<span style="color:red">'.$line.'</span>';
		}
		//return '<span style="color:orange">'.$str.'</span>';
		//return '@'.$str;
		return $str;
	}
}

// ------------------------------------------------------------------------
/* End of file language_helper.php */
/* Location: ./system/helpers/language_helper.php */