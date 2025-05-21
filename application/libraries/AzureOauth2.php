<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
*
*
* Microsoft Azure AD Oauth2 Authentication
*
*
* OAuth 2.0 Endpoints  
*
* Authorization endpoint (v1) 
* https://login.microsoftonline.com/{tenant-id}/oauth2/authorize  
* 
* Token endpoint (v1) 
* https://login.microsoftonline.com/{tenant-id}/oauth2/token 
*
* Logout endpoint (v1) 
* https://login.microsoftonline.com/{tenant-id}/oauth2/logout 
*
* Authorization endpoint (v2) 
* https://login.microsoftonline.com/{tenant-id}/oauth2/v2.0/authorize  
*
*
* Token endpoint (v2) 
* https://login.microsoftonline.com/{tenant-id}/oauth2/v2.0/token 
*
* Microsoft Graph API endpoint 
* https://graph.microsoft.com 
* 
*/

use Firebase\JWT\JWT;
 
class AzureOauth2
{
	protected $ci;

	function __construct()
	{
		log_message('debug', "AzureOauth2 class initialized");
		$this->ci =& get_instance();
		$this->ci->config->load("ion_auth");
		$this->tables  = $this->ci->config->item('tables');
		$this->ci->load->model("Ion_auth_model");
	}

	private function login_user($email)
	{
	    if (empty($email) ){
	        return FALSE;
	    }

	    $query = $this->ci->db->select('username,email, id, password')
            ->where("email", $email)
            ->where($this->ci->ion_auth->_extra_where)
            ->where('active', 1)
            ->get($this->tables['users']);
						  
        $result = $query->row();

        if ($query->num_rows() == 1){
            $this->update_last_login($result->id);
            $this->ci->session->set_userdata('email',  $result->email);
            $this->ci->session->set_userdata('username',  $result->username);
            $this->ci->session->set_userdata('user_id',  $result->id);
            return TRUE;
        }
        
		return FALSE;		
	}
	
	
	/**
	 * update_last_login
	 *
	 * @return bool
	 * @author Ben Edmunds
	 **/
	public function update_last_login($id)
	{
		$this->ci->load->helper('date');

		if (isset($this->ci->ion_auth) && isset($this->ci->ion_auth->_extra_where)){
			$this->ci->db->where($this->ci->ion_auth->_extra_where);
        }
        
		$this->ci->db->update($this->tables['users'], array('last_login' => now()), array('id' => $id));		
		return $this->ci->db->affected_rows() == 1;
    }
    

    function login()
    {
        if (!isset($_POST['id_token'])){
            show_error('AUTHENTICATION HEADERS NOT ENABLED');
        }

        try{
            $jwt = $_POST['id_token'];
            $decoded=$this->validate_jwt($jwt);
            
            if(!$decoded){
                throw new Exception("token not valid");
            }
        }
        catch(Exception $e){
          show_error($e->getMessage());
          die();
        }
      
        $user= new stdclass();
        $user->email=$decoded['unique_name'];
        $user->fname=$decoded['family_name'];
        $user->lname=$decoded['given_name'];
        
        $additional_data = array(
            'first_name' => $user->fname,
            'last_name'  => $user->lname,
            //'company'    => 'WB',
            'email'		=>$user->email,
            'identity'	=>$user->email
        );
			
						
        if (!$user->email){
            show_error('USER_NOT_FOUND');
        }
			
        //check user is already registered
        $user_info=$this->ci->ion_auth_model->get_user_by_email($user->email)->row_array(); 
        //$user_info=$user_info->result_array();
			
        if (is_array($user_info) && count($user_info)>0){
            //login to site			
            $this->login_user($user->email);
        }
        else
        {				
            //register user if not already registered
            $this->ci->ion_auth_model->register($user->fname, md5(date("U")), $user->email, $additional_data, $group_name='user', $auth_type="AAD");

            //login to site			
            $this->login_user($user->email);            
            $user_info=$this->ci->ion_auth_model->get_user_by_email($user->email)->row_array(); 
        }
			
        //log
        $this->ci->db_logger->write_log('login',$user->email);

        return true;
    }


    function validate_jwt($jwt)
    {
        $decoded = JWT::decode($jwt, $this->get_azure_public_keys(), array('RS256'));
        $decoded_array = (array) $decoded;
        print_r($decoded_array);    
        return $decoded_array;
    }


    /**
     * 
     * Get Azure public keys
     * 
     */
    function get_azure_public_keys()
    {
        if (file_exists('datafiles/tmp/public_keys.json')){
            $public_keys=file_get_contents('datafiles/tmp/public_keys.json');
            $public_keys=json_decode($public_keys,true);
        }else{
            $public_keys_url = 'https://login.windows.net/common/discovery/keys';
            $public_keys = $this->download_azure_public_keys($public_keys_url);
            file_put_contents('datafiles/tmp/public_keys.json',json_encode($public_keys));
        }

        return $public_keys;
    }

    /**
     * 
     * 
     * Get public keys from Azure
     * 
     * Source: https://stackoverflow.com/questions/32143743/verifying-jwt-from-azure-active-directory
     * author: https://stackoverflow.com/users/5159992/knightsbridge
     * 
     */
    function download_azure_public_keys($public_keys_url='https://login.windows.net/common/discovery/keys') 
    {
        $array_keys = array();
    
        $public_keys = file_get_contents($public_keys_url);
        $public_keys = (array)json_decode($public_keys, true);
    
        foreach($public_keys['keys'] as $public_key) {
            $string_certText = "-----BEGIN CERTIFICATE-----\r\n".chunk_split($public_key['x5c'][0],64)."-----END CERTIFICATE-----\r\n";
            $array_keys[$public_key['kid']] = $this->get_public_key_X5C($string_certText);
        }
    
        return $array_keys;
    }
    
    /**
     * 
     * Extract Public key from X5c
     * 
     */
    function get_public_key_X5C($cert_text) 
    {
        $object_cert = openssl_x509_read($cert_text);
        $object_pubkey = openssl_pkey_get_public($object_cert);
        $array_publicKey = openssl_pkey_get_details($object_pubkey);
        return $array_publicKey['key'];
    }

}
