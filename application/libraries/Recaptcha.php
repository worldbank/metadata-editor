<?php

/**
*
* Google Recaptcha v2
*
**/
require_once 'modules/recaptchav2/autoload.php';

class recaptcha
{
    public $recaptcha;
    
    function __construct()
    {
        $CI =&get_instance();
	$CI->config->load("captcha");//load captcha configurations
        $this->recaptcha=config_item('recaptcha');
    }

    /**
     *
     * Returns HTML/JS for embedding on the form
     * 
     */
    function recaptcha_get_html ()
    {            
        if ($this->recaptcha['publickey'] == null || $this->recaptcha['publickey'] == '') {
                die ("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>");
        } 

        return '
        <div class="g-recaptcha" data-sitekey="'.$this->recaptcha['publickey'].'"></div>
        <script type="text/javascript"
                src="https://www.google.com/recaptcha/api.js?hl='.$this->recaptcha['language'].'">
        </script>';       
    }
    
    /**
     * 
     * validate Recaptcha on form submission
     * 
     * @recaptcha_response = value of g-captcha-response 
     */
    function recaptcha_check_answer ($remoteip, $recaptcha_response)
    {

        // If the form submission includes the "g-captcha-response" field
        // Create an instance of the service using your secret
        $recaptcha = new \ReCaptcha\ReCaptcha($this->recaptcha['privatekey']);

        // If file_get_contents() is locked down on your PHP installation to disallow
        // its use with URLs, then you can use the alternative request method instead.
        // This makes use of fsockopen() instead.
        //  $recaptcha = new \ReCaptcha\ReCaptcha($secret, new \ReCaptcha\RequestMethod\SocketPost());

        // Make the call to verify the response and also pass the user's IP address
        $resp = $recaptcha->verify($recaptcha_response, $remoteip);

        $recaptcha_response = array();
        
        if ($resp->isSuccess()){
                $recaptcha_response['is_valid'] = true;
        }
        else {
                $recaptcha_response['is_valid'] = false;
                $recaptcha_response['error'] = $resp->getErrorCodes();
        }
                
        return $recaptcha_response;
    }

}