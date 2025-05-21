<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Image captcha driver using GD to generate image captchas
 *
 * @author		IHSN
 */

class Captcha_lib_image_captcha extends CI_Driver {

	protected $CI;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->CI =& get_instance();		
		$this->CI->load->library('image_captcha');
	}

	public function get_html()
	{
		return $this->CI->image_captcha->create_question();
	}	
	
	public function check_answer()
	{
		 $response = $this->CI->image_captcha->validate_captcha();		 
		 return $response;
	}
	
	public function get_question_field()
	{
		return 'captcha_question';
	}
}	
