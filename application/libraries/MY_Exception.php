<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
*
* Custom DB Exeption class to log and show errors related to db 
*
*	@author		Mehmood
*
* NOTE: It seems like Codeigniter error exception is designed for PHP4 and does not
* support PHP 5 exception throwing/handling
* 
* The reason this class is added here is to load it automatically and avoid any further workarounds to make this
* class available to the application before throwing this exception
*/
class MY_Exception extends Exception{ 

	var $ci=NULL;

	/*
	//available builtin methods for exceptions
	//http://www.php.net/manual/en/language.exceptions.extending.php
	
	final public  function getMessage();        // message of exception
    final public  function getCode();           // code of exception
    final public  function getFile();           // source filename
    final public  function getLine();           // source line
    final public  function getTrace();          // an array of the backtrace()
    final public  function getPrevious();       // previous exception
    final public  function getTraceAsString();  // formatted string of trace
	*/

	// Redefine the exception so message isn't optional
    public function __construct($message=NULL,$exception_type='ERROR') 
	{
		if ($message==NULL){return;}
    	$this->ci =& get_instance();
	    parent::__construct($message);
		
		$this->exception_type=$exception_type;	
		//log errors
		$this->log_error();
    }
	
	/**
	*
	* Display a simplified error message
	*
	*/
	public function message() {
		return parent::getMessage();
    }
	/**
	*
	* Display formatted detailed error message
	*
	*/
	public function message_detailed() 
	{
		$exception['message']=$this->getMessage();
		$exception['code']=$this->getCode();
		$exception['file']=$this->getFile();
		$exception['line']=$this->getLine();
		$exception['trace']=$this->getTrace();
		$exception['trace_string']=$this->getTraceAsString();
		
		//format output		
		$output=$this->ci->load->view('exceptions/detailed',$exception,TRUE);
		return $output;
    }	
	
	
	/**
	*
	* Log error messages in the filesystem and database
	*
	*/
	protected function log_error()
	{
		$msg=$this->getMessage().' in file: '.$this->getFile().' at line: '.$this->getLine();
		$msg.=$this->getTraceAsString();
			
		//log message to file
		log_message('error',$msg);
			
		//log in database
		$this->ci->db_logger->write_log($this->exception_type,$msg,$this->getFile());
	}
}


/* End of file Exceptions.php */
/* Location: ./application/libraries/Exceptions.php */