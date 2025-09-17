<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Image Captcha
 *
 *
 *
 *
 *
 */
class Image_captcha{
	
	var $ci;
	var $captcha_folder='';
	
	//GD extension loaded?
	var $is_enabled=FALSE;
	
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->config->load("captcha");		
		
		//setup captcha folder if not set
		$path=$this->ci->config->item('captcha_img_path');
		
		if (!file_exists($path))
		{
			if (!@mkdir($path))
			{
				log_message('error', "Captcha img_path not set - ".$path);
				show_error('Captcha folder is not set:'.$path);
			}
		}
				
		
		//check If GD extension is enabled
		if (!extension_loaded('gd')) 
		{
			$this->is_enabled=FALSE;
			log_message('error', "Captcha::GD library not installed");	
		}
		else
		{
			$this->is_enabled=TRUE;
		}		
		
    }


	
	function create_question()
	{
		$this->ci->load->helper('captcha');
		
		$word=strtolower($this->get_random_string());
		
		$vals = array(
		'word'	 	 => $word,
		'img_path'	 => $this->ci->config->item('captcha_img_path'),//APPPATH.'/../cache/captcha',
		'img_url'	 => $this->ci->config->item('captcha_img_url'),
		'font_path'	 => $this->ci->config->item('captcha_font_path'),
		'img_width'	 => $this->ci->config->item('captcha_img_width'),
		'img_height' => $this->ci->config->item('captcha_img_height'),
		'expiration' => $this->ci->config->item('captcha_expiration')
		);

		$cap = create_captcha($vals);

		$answer=base64_encode(nada_hash($word));

		$output=sprintf('<div class="image_captcha field mb-3">%s 
						<label class="desc">%s</label>
						<input type="text" maxlength="10" class="captcha_question input-flex" size="25" name="captcha_question"/>						
						</div>',$cap['image'],t('captcha_provide_answer'));
		$output.='<input type="hidden" name="cqa" value="'.$answer.'"/>';

		return $output;
	}
	
	/**
	*
	* Source: copied from CI's Captcha Helper class
	**/
	function get_random_string($length=6)
	{
		$pool = '23456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		$str = '';
		for ($i = 0; $i < $length; $i++)
		{
			$str .= substr($pool, mt_rand(0, strlen($pool) -1), 1);
		}
		return $str;
	}

	/**
	*
	* validate math captcha
	*
	*/
	function validate_captcha()
	{
		$question=base64_encode(nada_hash($this->ci->input->get_post('captcha_question')));
		$answer=$this->ci->input->get_post('cqa');

		if ($question!==$answer)
		{
			$this->ci->form_validation->set_message('validate_captcha', t('captcha_answer_not_matched'));
			return FALSE;
		}
		return TRUE;
	}
	
	
	function is_enabled()
	{		
		return $this->is_enabled;
	}
	
}// END class

/* End of file image_captch.php */
/* Location: ./application/libraries/image_captcha.php */