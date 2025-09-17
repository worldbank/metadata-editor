<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Database logger
 * 
 * Log entries in database
 *
 *
 *
 *
 * @package		NADA 2.1
 * @subpackage	Libraries
 * @category	logging
 * @author		Mehmood
 * @link		-
 *
 */
class DB_Logger{    	
	
	var $ci;
    //constructor
	function __construct() 
	{
		$this->ci =& get_instance();
		$this->ci->config->load("bots");
    }

	/**
	* log
	*
	*	@type		search,survey,login,logout,register,forgot-pass,reset-pass,change-pass
	*	@section	ddibrowser sections (overview, sampling,datafile,download,public-form,direct-form)
	*
	* @return boolean
	*/
	function write_log($type, $message=NULL, $section=NULL,$surveyid=0)
	{	
		//no logging if it is a bot
		if ($this->ci->config->item("ignore_bot_logging")===TRUE)
		{
			if ($this->is_bot())
			{
				return false;
			}
		}
		
		$username='';

		//check if user is logged in
		if ($this->ci->ion_auth->logged_in()) 
		{
			$user=$this->ci->ion_auth->current_user();
			if ($user)
			{
				$username=$user->email;
			}
    	}
		
		$log=array(
				'url'		=> substr($this->current_page_url(),0,255),//current_url(),
				'logtime'	=> date("U"),
				'ip'		=> $this->ci->input->ip_address(),
				'sessionid'	=> session_id(),
				'logtype'	=> $type,
				'surveyid'	=> (int)$surveyid,
				'keyword'	=> $message,
				'username'	=> $username,
				'section'	=> substr($section,0,255),
				'useragent'	=> substr(strtolower($_SERVER['HTTP_USER_AGENT']),0,300)
				);
		
		return $this->ci->db->insert("sitelogs",$log);
	}
	
	/*get page url with querystrings */
	function current_page_url() 
	{
		$get=$_GET;
		$querystring=array();
		
		foreach($get as $key=>$value)
		{
			if (is_array($value))
			{
				$value_=implode(',',$value);
			}
			else
			{
				$value_=$value;
			}
			$querystring[]="$key=$value_";
		}
		
		$url=uri_string().'?'.implode('&',$querystring);
	 	return $url;
	}
	
	/**
	*  test user-agent for BOT
	*
	*  returns true if it is a BOT
	*/
	function is_bot($agent=NULL)
	{
		//load ignore list
		$ignore_array=$this->ci->config->item("bot_ignore");
	
		if (!$ignore_array || !is_array($ignore_array))
		{
			//echo "empty array";exit;
			return FALSE;
		}

		if (!$agent)
		{
			//get the page user agent	 
			$agent = strtolower($_SERVER['HTTP_USER_AGENT']);
		}	
	
		//check if the page user-agent is a bot
		foreach($ignore_array as $bot)
		{
			if (trim($bot)=='')
			{
				continue;
			}
			
			if(stripos($agent,trim(strtolower($bot)))!==false)
			{
				//echo "found";exit;
				return true;// bot found
			}
		}
		//echo "not bot";exit;
		return false;
	}
	
	
	function increment_study_view_count($study_id)
	{
		if ($this->is_bot())
		{
			return false;
		}

		$this->ci->load->model("catalog_model");
		$this->ci->catalog_model->increment_study_view_count($study_id);
	}
	
	function increment_study_download_count($study_id)
	{
		if ($this->is_bot())
		{
			return false;
		}

		$this->ci->load->model("catalog_model");
		$this->ci->catalog_model->increment_study_download_count($study_id);
	}
	
	
}// END DB_Logger

/* End of file DB_Logger.php */
/* Location: ./application/libraries/DB_Logger.php */