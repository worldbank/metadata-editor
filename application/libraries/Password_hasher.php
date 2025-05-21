<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Password Hashing wrapper class
 * 
 *
 *
 *
 * @subpackage	Libraries
 *
 */ 
class Password_hasher{
    
	private $hasher=NULL;
	
	function __construct($params=NULL)
	{
		//load Portable PHP password hashing framework
		require_once APPPATH."libraries/PasswordHash.php";
		
		# Try to use stronger but system-specific hashes, with a possible fallback to
		# the weaker portable hashes.
		$this->hasher = new PasswordHash(8, FALSE);		
	}
	
	//create a hashed password
	public function hash_password($password)
	{
		if (strlen($password)>72)
		{
			return FALSE;
		}
		
		return $this->hasher->HashPassword($password);
	}
	
	//check if password matches the hash
	public function check_password($password,$hash)
	{
		if (strlen($password)>72)
		{
			return FALSE;
		}
		
		return $this->hasher->CheckPassword($password, $hash);
	}

}
// END password_hasher Class

/* End of file password_hasher.php */
/* Location: ./application/libraries/password_hasher.php */