<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
* Name:  Ion Auth Model
* 
* Author:  Ben Edmunds
* 		   ben.edmunds@gmail.com
*          @benedmunds
* 
* Added Awesomeness: Phil Sturgeon
* 
* Location: http://github.com/benedmunds/CodeIgniter-Ion-Auth
*          
* Created:  10.01.2009 
* 
* Description:  Modified auth system based on redux_auth with extensive customization.  This is basically what Redux Auth 2 should be.  Original redux license is below.
* Original Author name has been kept but that does not mean that the method has not been modified.
* 
* Requirements: PHP5 or above
* 
*/ 

class Ion_auth_model extends CI_Model
{
	public $errors= array();
	
	/**
	 * Holds an array of tables used
	 *
	 * @var string
	 **/
	public $tables = array();
	
	/**
	 * activation code
	 *
	 * @var string
	 **/
	public $activation_code;
	
	/**
	 * forgotten password key
	 *
	 * @var string
	 **/
	public $forgotten_password_code;
	
	/**
	 * new password
	 *
	 * @var string
	 **/
	public $new_password;
	
	/**
	 * Identity
	 *
	 * @var string
	 **/
	public $identity;
	
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->config('ion_auth');
		$this->load->helper('cookie');
        $this->load->library('session');
		$this->load->library('password_hasher');
		$this->load->helper('hash');
		$this->tables  = $this->config->item('tables');
		$this->columns = $this->config->item('columns');
		$this->load->helper('date');
		$this->identity_column = $this->config->item('identity');
	    $this->store_salt      = $this->config->item('store_salt');
	    $this->salt_length     = $this->config->item('salt_length');
	    $this->meta_join       = $this->config->item('join');
	}
	
	/**
	 * Misc functions
	 * 
	 * Hash password : Hashes the password to be stored in the database.
     * Hash password db : This function takes a password and validates it
     * against an entry in the users table.
     * Salt : Generates a random salt value.
	 *
	 * @author Mathew
	 */
	 
	/**
	 * Hashes the password to be stored in the database.
	 *
	 * @return void
	 * @author Mathew
	 **/
	public function hash_password($password, $salt=false)
	{
	    if (empty($password))
	    {
	    	return FALSE;
	    }
	    
		return $this->password_hasher->hash_password($password);		
	}
	
	/**
	 * This function takes a password and validates it
     * against an entry in the users table.
	 *
	 * @return void
	 * @author Mathew
	 **/
	public function hash_password_db($identity, $password)
	{
	   if (empty($identity) || empty($password))
	   {
	        return FALSE;
	   }
	   
	   $query = $this->db->select('password')
	   					 ->select('salt')
						 ->where($this->identity_column, $identity)
						 ->where($this->ion_auth->_extra_where)
						 //->limit(1)
						 ->get($this->tables['users']);
            
        $result = $query->row();
        
		if ($query->num_rows() !== 1)
		{
		    return FALSE;
		}

		
		$this->password_hasher->hash_password($password);
	}
	
	/**
	 * Generates a random salt value.
	 *
	 * @return void
	 * @author Mathew
	 **/
	public function salt()
	{
		return substr(sha1(uniqid(rand(), true)), 0, $this->salt_length);
	}
    
	/**
	 * Activation functions
	 * 
     * Activate : Validates and removes activation code.
     * Deactivae : Updates a users row with an activation code.
	 *
	 * @author Mathew
	 */
	
	/**
	 * activate
	 *
	 * @return void
	 * @author Mathew
	 **/
	public function activate($id, $code = false)
	{	
		$result=FALSE;
	    if ($code != false) 
	    {  
		    $user_check = $this->db->select('active, activation_code, ' . $this->identity_column)
		        ->where('id', $id)
		        ->limit(1)
		        ->get($this->tables['users']);
		        
		    if ($user_check->num_rows() !== 1) {
		        log_message('error', "User not found for activation: id=$id");
		        return FALSE;
		    }
		    
		    $user_data = $user_check->row();
		    
		    if ($user_data->active == 1) {
		        log_message('info', "User already activated: id=$id");
		        return TRUE;
		    }
		    
		    if (empty($user_data->activation_code) || $user_data->activation_code !== $code) {
		        log_message('error', "Invalid activation code: id=$id, code=$code");
		        return FALSE;
		    }
		    
		    $data = array(
		        'activation_code' => '',
		        'active'          => 1
		    );

		    $this->db->where($this->ion_auth->_extra_where);
		    $result = $this->db->update($this->tables['users'], $data, array('id' => $id));
	    }
	    else 
	    {
			if (!$this->ion_auth->is_admin()) 
			{
				log_message('error', "account activation failed using is_admin: $id");
				return false;
			}

			$data = array(
				'activation_code' => '',
				'active' => 1
			);
		   
			$this->db->where($this->ion_auth->_extra_where);
			$result=$this->db->update($this->tables['users'], $data, array('id' => $id));
	    }
		
		return $result;//$this->db->affected_rows() == 1;
	}
	
	
	/**
	 * Deactivate
	 *
	 * @return void
	 * @author Mathew
	 **/
	public function deactivate($id = 0)
	{
	    if (empty($id))
	    {
	        return FALSE;
	    }
	    
		$activation_code       = nada_random_hash(40);
		$this->activation_code = $activation_code;
		
		$data = array(
			'activation_code' => $activation_code,
			'active'          => 0
		);
        
		$this->db->where($this->ion_auth->_extra_where);
		$result=$this->db->update($this->tables['users'], $data, array('id' => $id));
		log_message('info', "delog_message('info' query: ".$this->db->last_query());
		return $result;
	}

	/**
	 * change password
	 *
	 * @return bool
	 * @author Mathew
	 *
	 **/
	public function change_password($identity, $old, $new)
	{
	    $query = $this->db->select('password')
						  ->where($this->identity_column, $identity)
						  ->where($this->ion_auth->_extra_where)
						  ->limit(1)
						  ->get($this->tables['users']);
	    $result = $query->row();

	    $db_password = $result->password; 
		
		$password_valid=$this->validate_password($identity,$old);
		
		//validate old password
		if (!$password_valid)
		{
			return FALSE;
		}
		
		if ($password_valid)
	    {
	        $data = array(
				'password' => $this->hash_password($new)
			);
			
	        $this->db->where($this->ion_auth->_extra_where);
	        $result=$this->db->update($this->tables['users'], $data, array($this->identity_column => $identity));	        
	        return $result;
	    }
	    
	    return FALSE;
	}
	
	
	//validate password
	public function validate_password($identity,$password)
	{
		if (empty($identity) || empty($password) || !$this->identity_check($identity))
	    {
	        return FALSE;
	    }

	    $query = $this->db->select('username,email, id, password')
						  ->where($this->identity_column, $identity)
						  ->where($this->ion_auth->_extra_where)
						  ->where('active', 1)
						  //->limit(1)
						  ->get($this->tables['users']);

        $result = $query->row();

        if ($query->num_rows() == 1)
        {
            //use secure password hashing only
            $password_validated=$this->password_hasher->check_password($password,$hash=$result->password);
			
			if ($password_validated)
    		{
    		    return TRUE;
    		}
        }
	
		return FALSE;	
	}
	
	
	/**
	 * Checks username
	 *
	 * @return bool
	 * @author Mathew
	 **/
	public function username_check($username = '')
	{
	    if (empty($username))
	    {
	        return FALSE;
	    }
		   
	    return $this->db->where('username', $username)
	    	->where($this->ion_auth->_extra_where)
			->count_all_results($this->tables['users']) > 0;
	}
	
	/**
	 * Checks email
	 *
	 * @return bool
	 * @author Mathew
	 **/
	public function email_check($email = '')
	{
	    if (empty($email))
	    {
	        return FALSE;
	    }
		   
	    return $this->db->where('email', $email)
	    	->where($this->ion_auth->_extra_where)
			->count_all_results($this->tables['users']) > 0;
	}
	
	/**
	 * Identity check
	 *
	 * @return bool
	 * @author Mathew
	 **/
	protected function identity_check($identity = '')
	{
	    if (empty($identity))
	    {
	        return FALSE;
	    }
	    
	    return $this->db->where($this->identity_column, $identity)
					->count_all_results($this->tables['users']) > 0;
	}

	/**
	 * Insert a forgotten password key.
	 *
	 * @return bool
	 * @author Mathew
	 **/
	public function forgotten_password($email = '')
	{
	    if (empty($email))
	    {
	        return FALSE;
		}
		
		$user=$this->get_userinfo_by_email($email);

		if(!$user){
			return FALSE;
		}

		$user=(object)$user;

		$expiry_time=new DateTime();
		$expiry_time->modify("4 hour");

		$key=rand(0,9999999);
		$key=$key.':'.$user->id.':'.$expiry_time->format("U");
		$key=base64_encode($key);
			
		$this->forgotten_password_code = $key;
		$this->db->where($this->ion_auth->_extra_where);
		$result=$this->db->update($this->tables['users'], array('forgotten_password_code' => $key), array('email' => $email));		
		return $result;
	}
	
	/**
	 * Forgotten Password Complete
	 *
	 * @return string
	 * @author Mathew
	 **/
	public function forgotten_password_complete($code)
	{
	    if (empty($code))
	    {
	        return FALSE;
	    }
		   
		$this->db->where('forgotten_password_code', $code);
		
		$user=$this->db->get($this->tables['users'])->result_array();

		if(count($user)!=1){
			return false;
		}

		$user=(object)$user[0];

		$code_arr=explode(":",base64_decode($code));
		$token=array(
			'code' => $code_arr[0],
			'user_id' => @$code_arr[1],
			'expiry' => @$code_arr[2]			
		);
		
		if($user->id != $token['user_id']){
			return false;
		}

		//code expired?
		if(empty($token['expiry']) || date("U") > $token['expiry']){			
			return false;
		}

		//remove reset code
		$this->db->where('id',$user->id);
		$this->db->update($this->tables['users'], array('forgotten_password_code' => null));

		//login user
		$this->update_last_login($user->id);
		$this->session->set_userdata('email',  $user->email);
		$this->session->set_userdata('username',  $user->username);
		$this->session->set_userdata('user_id',  $user->id);
		return true;
	}


	public function reset_password($email, $password)
	{
		$password=$this->hash_password($password);
		$result=$this->set_password($email,$password);
		
		if ($result==false){
			return false;
		}

		//remove reset code
		$this->db->where('email',$email);
		$this->db->update($this->tables['users'], array('forgotten_password_code' => null));

		return true;
	}



	public function validate_forgot_password_code($code)
	{
	    if (empty($code)){
	        return false;
	    }
		   
		$this->db->where('forgotten_password_code', $code);
		
		$user=$this->db->get($this->tables['users'])->result_array();

		if(count($user)!=1){
			return false;
		}

		$user=(object)$user[0];

		$code_arr=explode(":",base64_decode($code));
		$token=array(
			'code' => $code_arr[0],
			'user_id' => @$code_arr[1],
			'expiry' => @$code_arr[2]			
		);
		
		if($user->id != $token['user_id']){
			return false;
		}

		//code expired?
		if(empty($token['expiry']) || date("U") > $token['expiry']){			
			return false;
		}

		return array(
			'user'=>$user,
			'token'=>$token
		);
	}


	/**
	 * profile
	 *
	 * @return void
	 * @author Mathew
	 **/
	public function profile($identity = '')
	{ 
	    if (empty($identity))
	    {
	        return FALSE;
	    }
	    
		$this->db->select(array(
	    	$this->tables['users'].'.id',
	    	$this->tables['users'].'.username',
	    	$this->tables['users'].'.password',
	    	$this->tables['users'].'.email',
	    	$this->tables['users'].'.activation_code',
	    	$this->tables['users'].'.forgotten_password_code',
	    	$this->tables['users'].'.ip_address',
	    	$this->tables['users'].'.active',
	    	//$this->tables['groups'].'.name AS group_name',
	    	//$this->tables['groups'].'.description AS group_description'
	    ));

		if (!empty($this->columns))
        {
            foreach ($this->columns as $field)
            {
                $this->db->select($this->tables['meta'] .'.' . $field);
            }
        }

		$this->db->join($this->tables['meta'], $this->tables['users'].'.id = '.$this->tables['meta'].'.'.$this->meta_join, 'left');
		//$this->db->join($this->tables['groups'], $this->tables['users'].'.group_id = '.$this->tables['groups'].'.id', 'left');
		
		if (strlen($identity) === 32)
	    {
	        $this->db->where($this->tables['users'].'.forgotten_password_code', $identity);
	    }
	    else
	    {
	        $this->db->where($this->tables['users'].'.'.$this->identity_column, $identity);
	    }
	    
		$this->db->where($this->ion_auth->_extra_where);
		   
		$this->db->limit(1);
		$result = $this->db->get($this->tables['users']);

		if($result){
			return $result->row();
		}

		return false;
	}

	

	/**
	 * Basic functionality
	 * 
	 * Register
	 * Login
	 *
	 * @author Mathew
	 */
	
	/**
	 * register
	 *
	 * @return bool
	 * @author Mathew
	 **/
	public function register($username, $password, $email, $additional_data = false, $group_name = false, $auth_type=NULL)
	{	
	    if (empty($username) || empty($password) || empty($email) )
	    {
	        return FALSE;
	    }
		
		//check if email already exists
		if ($this->email_check($email))
		{
			$this->errors[]='Email already exists';
			return FALSE;	
		}
	    
	    // If username is taken, use username1 or username2, etc.
	    if ($this->identity != 'username') 
	    {
		    for($i = 0; $this->username_check($username); $i++)
		    {
		    	if($i > 0)
		    	{
		    		$username .= $i;
		    	}
		    }
	    }
		/*
        // Group ID
        if(empty($group_name))
        {
        	$group_name = $this->config->item('default_group');
        }
        
	    $group_id = $this->db->select('id')
				    	 ->where('name', $group_name)
				    	 ->get($this->tables['groups'])
				    	 ->row()->id;
		*/
		
	    // IP Address
        $ip_address = $this->input->ip_address();
	    
        if ($this->store_salt) 
        {
        	$salt = $this->salt();
        }
        else 
        {
        	$salt = false;
        }
		
		$password = $this->hash_password($password, $salt);
		
        // Users table.
		$data = array(
			'username'   => $username, 
			'password'   => $password,
  			'email'      => $email,
			//'group_id'   => $group_id,
			'ip_address' => $ip_address,
        	'created_on' => now(),
			'last_login' => now(),
			'active'     => 1,
			'authtype'	 => $auth_type
		);
		
		if ($this->store_salt) 
        {
        	$data['salt'] = $salt;
        }
		  
		$this->db->insert($this->tables['users'], array_merge($data, $this->ion_auth->_extra_set));
        
		// Meta table.
		$id = $this->db->insert_id();
		
		//Add user info to the meta table
		$data = array($this->meta_join => $id);
		
		if (!empty($this->columns))
	    {
	        foreach ($this->columns as $input)
	        {
	        	if (is_array($additional_data) && isset($additional_data[$input])) 
	        	{
	        		$data[$input] = $additional_data[$input];	
	        	}
	        	else 
	        	{
	            	$data[$input] = $this->input->post($input);
	        	}
	        }
	    }

		$this->db->insert($this->tables['meta'], $data);
		
		return $id;
	}
	
	/**
	 * login
	 *
	 * @return bool
	 * @author Mathew
	 **/
	public function login($identity, $password, $remember=FALSE)
	{
	    if (empty($identity) || empty($password) || !$this->identity_check($identity))
	    {
	        return FALSE;
	    }

	    $query = $this->db->select('username,email, id, password')
						  ->where($this->identity_column, $identity)
						  ->where($this->ion_auth->_extra_where)
						  ->where('active', 1)
						  //->limit(1)
						  ->get($this->tables['users']);

        $result = $query->row();

        if ($query->num_rows() == 1)
        {
            //use secure password hashing only
            $password_validated=$this->password_hasher->check_password($password,$hash=$result->password);
			
			if ($password_validated)
    		{
        		$this->update_last_login($result->id);
    		    $this->session->set_userdata('email',  $result->email);
				$this->session->set_userdata('username',  $result->username);
    		    $this->session->set_userdata('user_id',  $result->id); //everyone likes to overwrite id so we'll use user_id
    		    
    		    //$group_row = $this->db->select('name')->where('id', $result->group_id)->get($this->tables['groups'])->row();

    		    //$this->session->set_userdata('group',  $group_row->name);
    		    
    		    if ($remember && $this->config->item('remember_users'))
    		    {
    		    	$this->remember_user($result->id);
    		    }
    		    
    		    return TRUE;
    		}
        }
        $this->increase_login_attempts($identity);
		return FALSE;		
	}
	
	/**
	*
	* Set user password
	**/
	public function set_password($email,$password_hash)
	{		
		$options=array(
			'password'=>$password_hash
		);
		
		$this->db->where('email',$email);
		return $this->db->update($this->tables['users'],$options);
	}
	
	/**
	 * get_users
	 *
	 * @return object Users
	 * @author Ben Edmunds
	 **/
	public function get_users($group_name = false)
	{
		$this->db->select(array(
	    	$this->tables['users'].'.id',
			//$this->tables['users'].'.group_id',
	    	$this->tables['users'].'.username',
	    	$this->tables['users'].'.password',
	    	$this->tables['users'].'.email',
	    	$this->tables['users'].'.activation_code',
	    	$this->tables['users'].'.forgotten_password_code',
	    	$this->tables['users'].'.ip_address',
	    	$this->tables['users'].'.active',
	    	//$this->tables['groups'].'.name AS user_group',
	    	//$this->tables['groups'].'.description AS group_description'
	    ));
	    
		if (!empty($this->columns))
        {
            foreach ($this->columns as $field)
            {
                $this->db->select($this->tables['meta'].'.'. $field);
            }
        }
        
		$this->db->join($this->tables['meta'], $this->tables['users'].'.id = '.$this->tables['meta'].'.'.$this->meta_join, 'left');
		//$this->db->join($this->tables['groups'], $this->tables['users'].'.group_id = '.$this->tables['groups'].'.id', 'left');
		
		if(!empty($group_name))
		{
	    	$this->db->where($this->tables['groups'].'.name', $group_name);
		}
		
		return $this->db->where($this->ion_auth->_extra_where)
					    ->get($this->tables['users']);
	}
	
	/**
	 * get_active_users
	 *
	 * @return object
	 * @author Ben Edmunds
	 **/
	public function get_active_users($group_name = false)
	{
	    $this->db->where($this->tables['users'].'.active', 1);
	    
		return $this->get_users($group_name);
	}
	
	/**
	 * get_inactive_users
	 *
	 * @return object
	 * @author Ben Edmunds
	 **/
	public function get_inactive_users($group_name = false)
	{
	    $this->db->where($this->tables['users'].'.active', 0);
	    
		return $this->get_users($group_name);
	}
	
	/**
	 * get_user
	 *
	 * @return 		object
	 * @author 		Phil Sturgeon
	 **/
	public function get_user($id = false)
	{
		//if no id was passed use the current users id
		if (empty($id)) 
		{
			$id = $this->session->userdata('user_id');
		}
		
		if (!$id)
		{
			return FALSE;
		}
		$this->db->flush_cache();
		$this->db->select('users.*,first_name,last_name,company,phone,country');
		$this->db->join($this->tables['meta']. ' meta', 'users.id = meta.user_id', 'inner');		
		$this->db->where($this->tables['users'].'.id', $id);
		$user=$this->db->get($this->tables['users'].' users')->row();
		
		if(!$user){
			return false;
		}

		//get user groups
		$user->groups=$this->get_groups_by_user($id);		
		return $user;
	}
	
	/**
	 * get_user_by_email
	 *
	 * @return object
	 * @author Ben Edmunds
	 **/
	public function get_user_by_email($email)
	{
		$this->db->where($this->tables['users'].'.email', $email);
		$this->db->limit(1);
		
		return $this->get_users();
	}

	public function get_userinfo_by_email($email)
	{
		$this->db->where('email', $email);
		return $this->db->get("users")->row_array();
	}

	/**
	 * get_user_by_username
	 *
	 * @return object
	 **/
	public function get_user_by_username($username)
	{
		$this->db->where($this->tables['users'].'.username', $username);
		$this->db->limit(1);
		
		return $this->get_users();
	}
	
	/**
	 * get_newest_users
	 *
	 * @return object
	 * @author Ben Edmunds
	 **/
	public function get_newest_users($limit = 10)
  	{
    	$this->db->order_by($this->tables['users'].'.created_on', 'desc');
    	$this->db->limit($limit);
    	
    	return $this->get_users();
  	}
	
	/**
	 * get_users_group
	 *
	 * @return object
	 * @author Ben Edmunds
	 **/
	public function get_users_group($id=false)
	{
		//if no id was passed use the current users id
		if (!$id)  
		{
			$id = $this->session->userdata('user_id');
		}
		
	    $query = $this->db->select('group_id')
						  ->where('id', $id)
						  ->get($this->tables['users']);

		$user = $query->row();
		
		return $this->db->select('name, description')
						->where('id', $user->group_id)
						->get($this->tables['groups'])
						->row();
	}
	

	/**
	 * update_user
	 *
	 * @return bool
	 * @author Phil Sturgeon
	 * @modified	Mehmood
	 **/
	public function update_user($id, $data)
	{
	    $this->db->trans_begin();
	    
		$update_needed=false;

		$roles=array();
		
		//user role IDs
		if (isset($data['role_id']) || array_key_exists('role_id',$data))
		{			
			if(is_array($data['role_id'])){
				$roles=$data['role_id'];
			}
			else{
				$roles[]=$data['role_id'];
			}	
			unset($data['role_id']);
		}
				
		
	    if (!empty($this->columns))
	    {						
	        foreach ($this->columns as $field)
	        {
	        	if (is_array($data) && isset($data[$field])) 
	        	{
	            	$this->db->set($field, $data[$field]);
	            	unset($data[$field]);
	            	$update_needed=TRUE;
	        	}
	        }
			
	        if ($update_needed)
	        {
	        	// 'user_id' = $id
				$this->db->where($this->meta_join, $id);
	        	$this->db->update($this->tables['meta']);
	        }	
	    }
	    
        if (array_key_exists('username', $data) || array_key_exists('password', $data) || array_key_exists('email', $data)) 
        {
	        if (array_key_exists('password', $data))
			{
			    $data['password'] = $this->hash_password($data['password']);
			}
	
			$this->db->where($this->ion_auth->_extra_where);
			$this->db->update($this->tables['users'], $data, array('id' => $id));
        }

		
		//user role membership

        //update user roles info
		if (is_array($roles) && count($roles)>0)
		{

			//remove any existing user roles
			$this->db->query(sprintf('delete from %s where user_id=%d',
				'user_roles', 
				(int)$id ));

			foreach($roles as $role_id){
				$options=array(
						'role_id'	=> $role_id,
						'user_id'	=> $id 
						);
				$this->db->insert('user_roles',$options);
			}
		}
		
		    
		if ($this->db->trans_status() === FALSE)
		{
		    $this->db->trans_rollback();
		    return FALSE;
		}
		else
		{
		    $this->db->trans_commit();
		    return TRUE;
		}
	}
	

	/**
	 * delete_user
	 *
	 * @return bool
	 * @author Phil Sturgeon
	 **/
	public function delete_user($id)
	{
		$this->db->trans_begin();
		
		$this->db->delete($this->tables['meta'], array($this->meta_join => $id));
		$this->db->delete($this->tables['users'], array('id' => $id));
		
		if ($this->db->trans_status() === FALSE)
		{
		    $this->db->trans_rollback();
		    return FALSE;
		}
		else
		{
		    $this->db->trans_commit();
		    return TRUE;
		}
	}
	
	
	public function assign_user_group($user_id,$group_id)
	{
		$options=array(
			'role_id'=>$group_id,
			'user_id'=>$user_id
		);

		$this->db->insert('user_roles',$options);
	}
	

	/**
	 * update_last_login
	 *
	 * @return bool
	 * @author Ben Edmunds
	 **/
	public function update_last_login($id)
	{
		$this->load->helper('date');

		if (isset($this->ion_auth) && isset($this->ion_auth->_extra_where))
		{
			$this->db->where($this->ion_auth->_extra_where);
		}		
		$this->db->update($this->tables['users'], array('last_login' => now()), array('id' => $id));
		
		return $this->db->affected_rows() == 1;
	}
	

	/**
	 * set_lang
	 *
	 * @return bool
	 * @author Ben Edmunds
	 **/
	public function set_lang($lang = 'en')
	{	
		set_cookie(array(
			'name'   => 'lang_code',
			'value'  => $lang,
			'expire' => $this->config->item('user_expire') + time()
		));
		
		return TRUE;
	}
	
	/**
	 * login_remembed_user
	 *
	 * @return bool
	 * @author Ben Edmunds
	 **/
	public function login_remembered_user()
	{
		//check for valid data
		if (!get_cookie('identity') || !get_cookie('remember_code') || !$this->identity_check(get_cookie('identity'))) 
		{
			return FALSE;
		}
		
		//get the user
		$query = $this->db->select($this->identity_column.', id, username')
						  ->where($this->identity_column, get_cookie('identity'))
						  ->where('remember_code', get_cookie('remember_code'))
						  ->limit(1)
						  ->get($this->tables['users']);
        
		//if the user was found, sign them in
        if ($query->num_rows() == 1)
        {
        	$user = $query->row();
        	
        	$this->update_last_login($user->id);
        	
            $this->session->set_userdata($this->identity_column,  $user->{$this->identity_column});
    		$this->session->set_userdata('id',  $user->id); //kept for backwards compatibility
    		$this->session->set_userdata('user_id',  $user->id); //everyone likes to overwrite id so we'll use user_id
    		//$this->session->set_userdata('group_id',  $user->group_id);
    		$this->session->set_userdata('username',  $user->username);
    		    
    		//$group_row = $this->db->select('name')->where('id', $user->group_id)->get($this->tables['groups'])->row();
	    
    		//$this->session->set_userdata('group',  $group_row->name);
    		
    		//extend the users cookies if the option is enabled
    		if ($this->config->item('user_extend_on_login'))
    		{
    			$this->remember_user($user->id);
    		}
    		
    		return TRUE;
          }
        
		  return FALSE;		
	}
	
	/**
	 * remember_user
	 *
	 * @return bool
	 * @author Ben Edmunds
	 **/
	private function remember_user($id)
	{
		return FALSE;
		
		if (!$id) 
		{			
			return FALSE;
		}
		
		$salt = nada_random_hash(40);
		
		$is_updated=$this->db->update($this->tables['users'], array('remember_code' => $salt), array('id' => $id));
		
		if ($is_updated !== FALSE) 
		{
			$user = $this->get_user($id)->row();
			
			$identity = array('name'   => 'identity',
	                   		  'value'  => $user->{$this->identity_column},
	                   		  'expire' => $this->config->item('user_expire'),
	               			 );
			set_cookie($identity); 
			
			$remember_code = array('name'   => 'remember_code',
	                   		  	   'value'  => $salt,
	                   		  	   'expire' => $this->config->item('user_expire'),
	               			 	  );
			set_cookie($remember_code); 
			
			return TRUE;
		}
		
		return FALSE;
	}	
	
	/**
	* Returns a list of all countries in the database
	*
	*/
	function get_all_countries()
	{
		$this->db->select('countryid,name');
		$this->db->order_by("name","asc");
		$query=$this->db->get('countries');
		
		$output=array('-'=>'-');
		
		if ($query)
		{
			$rows=$query->result_array();
			
			foreach($rows as $row)
			{
				$output[$row['name']]=$row['name'];
			}				
		}
		
		return $output;
	}
	
	
	/**
	*
	* Returna an array of email addresses for all administrators
	*
	**/
	function get_admin_emails()
	{
		$this->db->select('username,email');
		$this->db->where('group_id',1);
		$this->db->where('active',1);
		$query=$this->db->get($this->tables['users']);
		
		$emails=array();
		if ($query)
		{
			$rows=$query->result_array();
			
			foreach($rows as $row)
			{
				$emails[]=$row['email'];	
			}
			
			return $emails;
		}
		return FALSE;
	}
	
	
	/**
	*
	* Checks if a user is admin or not
	*/
	function is_admin($userid)
	{	
		$user=$this->get_user($userid);
		return $this->acl_manager->user_is_admin($user);
	}
	
	

	
	/**
	*
	* Return all admin user accounts
	**/
	public function get_admin_users($group_name = false)
	{
		$this->db->select(array(
	    	$this->tables['users'].'.id',
			$this->tables['users'].'.group_id',
	    	$this->tables['users'].'.username',
	    	$this->tables['users'].'.password',
	    	$this->tables['users'].'.email',
	    	$this->tables['users'].'.activation_code',
	    	$this->tables['users'].'.forgotten_password_code',
	    	$this->tables['users'].'.ip_address',
	    	$this->tables['users'].'.active',
	    	$this->tables['groups'].'.name AS group_name',
	    	$this->tables['groups'].'.description AS group_description'
	    ));
	    
		if (!empty($this->columns))
        {
            foreach ($this->columns as $field)
            {
                $this->db->select($this->tables['meta'].'.'. $field);
            }
        }
        
		$this->db->join($this->tables['meta'], $this->tables['users'].'.id = '.$this->tables['meta'].'.'.$this->meta_join, 'left');
		$this->db->join($this->tables['groups'], $this->tables['users'].'.group_id = '.$this->tables['groups'].'.id', 'left');
		//get only admin accounts
		$this->db->where($this->tables['groups'].'.name !=', 'user');
		
		if(!empty($group_name))
		{
	    	$this->db->where($this->tables['groups'].'.name', $group_name);
		}
		
		$query=$this->db->where($this->ion_auth->_extra_where)
					    ->get($this->tables['users']);
		if($query)
		{
			return $query->result_array();
		}
		
		return FALSE;	
	}

	/**
	 * get group memberships for a user
	 *
	 * @return object
	 * @author Ben Edmunds
	 * @modified Mehmood
	 **/
	public function get_groups_by_user($id=FALSE)
	{
		//if no id was passed use the current users id
		if (!$id)  {
			$id = $this->session->userdata('user_id');
		}
		
		$roles=$this->acl_manager->get_user_roles($id);
		return $roles;
	}

	function get_groupid_by_name($name)
	{
		$this->db->select('id');
		$this->db->where('name',$name);
		$result=$this->db->get('groups')->row_array();

		if($result){
			return $result['id'];
		}

		return false;
	}

	
	
	
	
	function impersonate($user_id,$current_user)
	{
		if (!is_numeric($user_id)){
			return FALSE;
		}
		
		$user_obj=$this->get_user($user_id);
		
		//$this->update_last_login($result->id);
		$this->session->set_userdata('email',  $user_obj->email);
		$this->session->set_userdata('username',  $user_obj->username);
		$this->session->set_userdata('user_id',  $user_obj->id);
		$this->session->set_userdata('impersonate_user',  $current_user->id);		
	}
	
	function exit_impersonate()
	{
		if (!$this->session->userdata('impersonate_user')){
			return FALSE;
		}
		
		$user_id=(int)$this->session->userdata('impersonate_user');
		$user_obj=$this->get_user($user_id);
		$this->session->set_userdata('email',  $user_obj->email);
		$this->session->set_userdata('username',  $user_obj->username);
		$this->session->set_userdata('user_id',  $user_obj->id);
		$this->session->unset_userdata('impersonate_user');		
	}

	
	
	
	/**
	 * clear_login_attempts
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 * 
	 * @param string $identity
	 **/
	public function clear_login_attempts($identity, $expire_period = 86400) 
	{
		if ($this->config->item('track_login_attempts')) 
		{
			$ip_address = $this->input->ip_address();

			$this->db->where(array('ip_address' => $ip_address, 'login' => $identity));
			// Purge obsolete login attempts
			$this->db->where('time <', time() - $expire_period);

			$this->db->delete($this->tables['login_attempts']);
			//echo date("H:i:s",time()- $expire_period);
			//echo $this->db->last_query();exit;
		}
	}
	
	
	/**
	 * increase_login_attempts
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 * 
	 * @param string $identity
	 **/
	public function increase_login_attempts($identity) 
	{
		if ($this->config->item('track_login_attempts')) 
		{
			$ip_address = $this->input->ip_address();
			$this->db->insert($this->tables['login_attempts'], array('ip_address' => $ip_address, 'login' => $identity, 'time' => time()));
		}
	}
	
	
	/**
	 * is_max_login_attempts_exceeded
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 * 
	 * @param string $identity
	 * @return boolean
	 **/
	public function is_max_login_attempts_exceeded($identity) 
	{
		//lockout time
		$login_lockout_period=$this->config->item("login_lockout_period");
		
		//clear limits after lock time period
		$this->clear_login_attempts($identity,$login_lockout_period);
	
		if ($this->config->item('track_login_attempts')) {
			$max_attempts = $this->config->item('maximum_login_attempts');
			if ($max_attempts > 0) {
				$attempts = $this->get_attempts_num($identity);
				return $attempts >= $max_attempts;
			}
		}
		return FALSE;
	}

	/**
	 * Get number of attempts to login occured from given IP-address or identity
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 * 
	 * @param	string $identity
	 * @return	int
	 */
	function get_attempts_num($identity)
	{
		$ip_address = $this->input->ip_address();

		$this->db->select('1', FALSE);
		//$this->db->where('ip_address', $ip_address);
		if (strlen($identity) > 0) $this->db->where('login', $identity);

		$qres = $this->db->get($this->tables['login_attempts']);
		return $qres->num_rows();
	}


	/**
	 * 
	 * 
	 * Validate resource
	 * @options - array of resource fields
	 * @is_new - boolean - if set to true, requires resource_id field to be set
	 * 
	 **/
	function validate_login($email,$password)
	{		
		$options=array(
			'email'=>$email,
			'password'=>$password
		);

		$this->load->library("form_validation");
		$this->form_validation->reset_validation();
		$this->form_validation->set_data($options);
	
		$this->form_validation->set_rules('email', t('email'), 'trim|required|valid_email|max_length[100]');
		$this->form_validation->set_rules('password', t('password'), 'required|max_length[100]');
		
		if ($this->form_validation->run() == TRUE){
			return TRUE;
		}
		
		//failed
		$errors=$this->form_validation->error_array();
		$error_str=$this->form_validation->error_array_to_string($errors);
		throw new ValidationException("VALIDATION_ERROR: ".$error_str, $errors);
	}

	/**
	 * 
	 * 
	 * Return user's API keys
	 * 
	 * 
	 */
	function get_api_keys($user_id)
	{
		$this->db->where("user_id",$user_id);
		$result=$this->db->get("api_keys")->result_array();

		if(!$result){
			return false;
		}

		$output=array();
		foreach($result as $row){
			$output[]=$row['api_key'];
		}

		return $output;
	}


	/**
	 * 
	 * 
	 * Create a new API token for user
	 * 
	 * @user id - User ID
	 * @token (optional) If null, generate a random token
	 */
	function set_api_key($user_id,$key=NULL)
	{
		if(!$key || strlen(trim($key))<10 ){
			$key=nada_random_hash(32);
		}

		$options=array(
			'user_id'=>$user_id,
			'api_key'=>$key,
			'date_created'=>date("U"),
			'level'=>0,
			'ignore_limits'=>1
		);

		$this->db->insert("api_keys",$options);
	}

	//remove api key
	function delete_api_key($user_id,$api_key)
	{
		$this->db->where("user_id",$user_id);
		$this->db->where("api_key",$api_key);
		$this->db->delete("api_keys");
	}

	/**
	 * 
	 * Generate a new OTP code
	 */
	function generate_otp_code()
	{
		$code=sprintf('%010d', mt_rand(1,mt_getrandmax()));
		return $code;
	}

	function store_otp_code($user_id)
	{
		$code=$this->generate_otp_code();

		$expiry_time=new DateTime();
		$expiry_time->modify("15 minutes");		

		$options=array(
			'otp_code'=>$code,
			'otp_expiry'=>$expiry_time->format("U")
		);

		$this->db->where('id',$user_id);
		$this->db->update('users',$options);

		return $code;
	}


	function is_user_id_valid($user_id)
	{
		$this->db->where('id',$user_id);
		$result=$this->db->get('users')->row_array();

		if(!$result){
			return false;
		}

		return true;
	}
}