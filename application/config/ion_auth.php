<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
* Name:  Ion Auth Config
* 
* Author: Ben Edmunds
* 		  ben.edmunds@gmail.com
*         @benedmunds
*          
* Added Awesomeness: Phil Sturgeon
* 
* Location: http://github.com/benedmunds/ion_auth/
*          
* Created:  10.01.2009 
* 
* Description:  Modified auth system based on redux_auth with extensive customization.  This is basically what Redux Auth 2 should be.  Original redux license is below.
* Original Author name has been kept but that does not mean that the method has not been modified.
* 
*/

	/**
	 * Tables.
	 **/
	$config['tables']['groups']  		= 'groups';
	$config['tables']['users']   		= 'users';
	$config['tables']['meta']    		= 'meta';
	$config['tables']['permissions']    = 'user_permissions';
	$config['tables']['user_groups']  	= 'users_groups';
	$config['tables']['login_attempts']  = 'login_attempts';

	/**
   	* Track the number of failed login attempts for each user or ip. 
   	**/
	$config['track_login_attempts'] = FALSE;
	
	/**
	* Set the maximum number of failed login attempts.
	* This maximum is not enforced by the library, but is 
	* used by $this->ion_auth->is_max_login_attempts_exceeded().
	* The controller should check this function and act
	* appropriately. If this variable set to 0, there is no maximum.
	**/
	$config['maximum_login_attempts'] = 10;	
	$config['login_lockout_period'] = 60*5;//5 minutes
	
	/**
	 * Site Title, example.com
	 */
	$config['site_title']		   = "NADA";
	
	/**
	 * Admin Email, admin@example.com
	 */
	$config['admin_email']		   = "noreply@example.org";
	
	/**
	 * Default group, use name
	 */
	$config['default_group']       = 'user';
	
	/**
	 * Default administrators group, use name
	 */
	$config['admin_group']         = 'admin';
	 
	/**
	 * Meta table column you want to join WITH.
	 * Joins from users.id
	 **/
	$config['join']                = 'user_id';
	
	/**
	 * Columns in your meta table,
	 * id not required.
	 **/
	$config['columns']             = array('first_name', 'last_name', 'company', 'phone','country');
	
	/**
	 * A database column which is used to
	 * login with.
	 **/
	$config['identity']            = 'email';
		 
	/**
	 * Minimum Required Length of Password
	 **/
	$config['min_password_length'] = 5;
	
	/**
	 * Maximum Allowed Length of Password
	 **/
	$config['max_password_length'] = 20;	

	/**
	 * Enable complex password
	 *
	 * This requires the password to must have atleast one uppercase, one lowercase, one number and one special charater
	 * - Only whitelisted Special characters are allowed
	 * - For password length, the minimum and maximum password length settings are used
	 **/
	$config['require_complex_password']=true;

	/**
	 * Email Activation for registration
	 **/
	$config['email_activation']    = true;
	
	/**
	 * Allow users to be remembered and enable auto-login
	 **/
	$config['remember_users']      = true;
	
	/**
	 * How long to remember the user (seconds)
	 **/
	$config['user_expire']         = 0;//3600*24*10;
	
	/**
	 * Extend the users cookies everytime they auto-login
	 **/
	$config['user_extend_on_login'] = false;
	
	/**
	 * Folder where email templates are stored.
     * Default : auth/
	 **/
	$config['email_templates']     = 'auth/email/';
	
	/**
	 * activate Account Email Template
     * Default : activate.tpl.php
	 **/
	$config['email_activate']   = 'activate.tpl.php';
	
	/**
	 * Forgot Password Email Template
     * Default : forgot_password.tpl.php
	 **/
	$config['email_forgot_password']   = 'forgot_password.tpl.php';

	/**
	 * Forgot Password Complete Email Template
     * Default : new_password.tpl.php
	 **/
	$config['email_forgot_password_complete']   = 'new_password.tpl.php';
	
	/**
	 * Salt Length
	 **/
	$config['salt_length'] = 10;

	/**
	 * Should the salt be stored in the database
	 **/
	$config['store_salt'] = false;
		
	/**
	 * Message Start Delimiter
	 **/
	$config['message_start_delimiter'] = '<p>';
	
	/**
	 * Message End Delimiter
	 **/
	$config['message_end_delimiter'] = '</p>';
	
	/**
	 * Error Start Delimiter
	 **/
	$config['error_start_delimiter'] = '<p>';
	
	/**
	 * Error End Delimiter
	 **/
	$config['error_end_delimiter'] = '</p>';

	
	/*
	|--------------------------------------------------------------------------
	| Default user roles
	|--------------------------------------------------------------------------
	|
	|
	*/

	//Assign default user role for new users
	$config['editor_user_roles']=['user'];
        
        
	
/* End of file ion_auth.php */
/* Location: ./system/application/config/ion_auth.php */