<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Default no captcha
 *
 * @package		Recaptcha
 * @subpackage	Libraries
 */

class Captcha_lib_none extends CI_Driver {

	protected $CI;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		//$this->CI =& get_instance();		
		//$this->CI->load->library('recaptcha');
	}

	public function get_html()
	{
		return '';
	}	
	
	public function check_answer()
	{
		return true;
	}
	
	public function get_question_field()
	{
		return '';
	}
}	
