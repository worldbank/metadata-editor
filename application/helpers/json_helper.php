<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Code Igniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		Rick Ellis
 * @copyright	Copyright (c) 2006, pMachine, Inc.
 * @license		http://www.codeignitor.com/user_guide/license.html 
 * @link		http://www.codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Code Igniter JSON Helpers
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		Evan Baliatico
 * @link		http://www.codeigniter.com/wiki/
 */

// ------------------------------------------------------------------------

if (! function_exists('json_validate')){
  function json_validate($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
 }
}

?>