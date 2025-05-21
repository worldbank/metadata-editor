<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Google Recaptcha driver
 *
 * @package		Recaptcha
 * @subpackage	Libraries
 */

class Captcha_lib_recaptcha extends CI_Driver {

	protected $CI;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->CI =& get_instance();		
		$this->CI->load->library('recaptcha');
	}

	public function get_html()
	{
		return $this->CI->recaptcha->recaptcha_get_html();
	}	
	
	public function check_answer()
	{
		 $response = $this->CI->recaptcha->recaptcha_check_answer(
			 				$this->CI->input->server('REMOTE_ADDR'),
							$this->CI->input->post($this->get_question_field()),
							$debug=false
		 );
		 
		 return $response['is_valid'];
	}
	
	public function get_question_field()
	{
		return 'g-recaptcha-response';
	}
}	
