<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * 
 * 
 * CSRF using Slim/csrf
 *
 *
 */ 
class Nada_csrf{
    
    private $ci;
    private $slimGuard; 
    private $token_name_key;
    private $token_value_key;
    private $token_name;
    private $token_value;
	
	function __construct()
	{
		$this->slimGuard = new \Slim\Csrf\Guard;
        $this->slimGuard->validateStorage();
        $this->ci =& get_instance();
    }

    function generate_token()
    {
        $this->slimGuard->generateToken();
        
        $this->token_name_key = $this->slimGuard->getTokenNameKey();
        $this->token_value_key = $this->slimGuard->getTokenValueKey();
        $this->token_name = $this->slimGuard->getTokenName();
        $this->token_value = $this->slimGuard->getTokenValue();
        
        return [
            'keys' => [
                'name'  => $this->token_name_key,
                'value' => $this->token_value_key
            ],
            'name'  => $this->token_name,
            'value' => $this->token_value
        ];
        
    }


    function get_token_name()
    {
        return $this->token_name;
    }

    function get_token_name_key()
    {
        return $this->token_name_key;
    }

    function get_token_value_key()
    {
        return $this->token_value_key;
    }

    function get_token_value()
    {
        return $this->token_value;
    }
    

    function validate_token()
    {
        return $this->slimGuard->validateToken($this->ci->input->post($this->token_name_key), $this->ci->input->post($this->token_value_key));
    }    


}    