<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Mailer
{
	
	private $ci;

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->ci =& get_instance();
		//$this->ci->load->config('email');
		$this->ci->config->load('email');
		$this->ci->load->library('email');
	}

	/**
	 * 
	 * Send email
	 * 
	 * $subject, $message, $to, $from=null, $cc=null, $bcc=null
	 * 
	 * options[
	 * 	'subject'=>'',
	 * 	'message'=>'',
	 * 	'to'=>'',
	 * 	'from'=>'', //if not set, use smtp_email or website_webmaster_email
	 * 	'cc'=>'',
	 * 	'bcc'=>''
	 * 	]
	 * 
	 */
	function send($options=array())
	{
		$options=$this->validateOptions($options);

		$this->ci->email->clear();
		$this->ci->email->initialize();
		$this->ci->email->from($options['from']);
		$this->ci->email->to($options['to']);
		$this->ci->email->subject($options['subject']);
		$this->ci->email->message($options['message']);

		if (isset($options['cc'])){
			$this->ci->email->cc($options['cc']);
		}

		if (isset($options['bcc'])){
			$this->ci->email->bcc($options['bcc']);
		}
		
		if ($this->ci->email->send()){			
			return TRUE;
		}
		else
		{
			//echo 'Mailer Error: ' .  $this->ci->email->print_debugger();
			return FALSE;
		}
	}


	/**
	 * 
	 * Validate email options
	 * 
	 * 
	 * 
	 * options[
	 * 	'subject'=>'',
	 * 	'message'=>'',
	 * 	'to'=>'',
	 * 	'from'=>'', //if not set, use smtp_email or website_webmaster_email
	 * 	'cc'=>'',
	 * 	'bcc'=>''
	 * 	]
	 * 
	 */
	function validateOptions($options)
	{
		$required=array('subject','message','to');
		foreach($required as $r){
			if (!isset($options[$r])){
				throw new Exception("Email option [$r] not set");
			}
		}

		//from
		if (!isset($options['from'])){
			if ($this->ci->config->item('smtp_email')!==NULL){
				$options['from'] =$this->ci->config->item('smtp_email');
			}
			else{
				$options['from'] =$this->ci->config->item('website_webmaster_email');
			}			
		}

		if (!$options['from']){
			throw new Exception("Email [FROM] not set");
		}

		return $options;
	}

}

