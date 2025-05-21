<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	http://codeigniter.com/user_guide/general/hooks.html
|
*/

$hook['pre_system'][] = array(
                                'class'    => '',
                                'function' => 'pre_system_url_check',
                                'filename' => '',
                                'filepath' => 'hooks',
                                'params'   => array()
                                );

$hook['post_controller_constructor'][] = array(
                                'class'    => '',
                                'function' => 'disable_annonymous_access',
                                'filename' => '',
                                'filepath' => 'hooks',
                                'params'   => array()
                                );

$hook['post_controller_constructor'][] = array(
                                'class'    => '',
                                'function' => 'disable_admin_access',
                                'filename' => '',
                                'filepath' => 'hooks',
                                'params'   => array()
                                );
//XHPROF: To enable XHPROF stats enable hooks below
/*
$hook['pre_controller'] = array(
  'class'  => 'XHProf',
  'function' => 'XHProf_Start',
  'filename' => 'xhprof.php',
  'filepath' => 'hooks',
  'params' => array()
);
 
$hook['post_controller'] = array(
   'class'  => 'XHProf',
   'function' => 'XHProf_End',
   'filename' => 'xhprof.php',
   'filepath' => 'hooks',
   'params' => array()
);
*/

/*
//automatically login
$hook['post_controller_constructor'][] = array(
                                'class'    => '',
                                'function' => 'admin_auto_login',
                                'filename' => '',
                                'filepath' => 'hooks',
                                'params'   => array()
                                );
*/

/**
*
* Force SSL for urls under /auth if Server has 
* SSL Support
*
*
**/
function pre_system_url_check()
{
	//load configurations
    include APPPATH.'config/config.php';
	
	if (!$config)
	{
		return FALSE;
	}
	
	$http_port=(int)$config["http_port"];
	$enable_ssl=(bool)$config["enable_ssl"];
	$proxy_ssl=(bool)$config['proxy_ssl'];
	$proxy_ssl_header=$config["proxy_ssl_header"];
	$proxy_ssl_header_value=$config["proxy_ssl_header_value"];
	
	if (!$enable_ssl && !$proxy_ssl)
	{
		return FALSE;
	}

	$path_info='';
	if(isset($_SERVER["PATH_INFO"]))
	{
		$path_info=$_SERVER["PATH_INFO"];
	}
	
	$segments= array_filter(explode("/",$path_info));
	
	$url="https://".$_SERVER['HTTP_HOST']._request_uri();

	//build url for redirect to https page
	$redirect= "https://".$_SERVER['HTTP_HOST']._request_uri();
		
	//remove http port
	if ($http_port>0 && $http_port!=80)
	{
		$redirect=str_replace(':'.$http_port,'',$redirect);
	}

	//if page is served using HTTPS
	$is_https=FALSE;
	
	//check if using SSL/Proxy/Headers
	if ($proxy_ssl===TRUE && $proxy_ssl_header!='')
	{		
		//see if the variable is set
		if (isset($_SERVER[$proxy_ssl_header]))
		{
			if ($_SERVER[$proxy_ssl_header]==$proxy_ssl_header_value)
			{
				$is_https=TRUE;
			}
		}
		else
		{
			//avoid redirect loop
			return FALSE; 
		}		
	}
	//check SSL using server HTTPS variablbe
	else if (isset($_SERVER['HTTPS']))
	{
		if($_SERVER['HTTPS']=="on")
		{
			$is_https=TRUE;
		}	
	}
	
	//page is not viewed using HTTPS
	if($is_https===FALSE)
	{	
		//if url first segment has AUTH redirect to HTTPS		
		//if (current($segments)=='auth'){
			//redirect to SSL page
			header("Location:$redirect");
		//}		
	}
}

/**
*
* Returns the URI for the current page
**/
function _request_uri()
{
	//on IIS 6 request_uri is not available
	if (!empty($_SERVER['REQUEST_URI']))
	{
		return $_SERVER['REQUEST_URI'];
	}
	else
	{
		return $_SERVER['PHP_SELF'];
	}
}

/**
*
* If annonymous access is set to false, ask users to login
*/
function disable_annonymous_access($params)
{
		$CI =& get_instance();
		
		if ($CI->config->item("site_password_protect")!=='yes')
		{
			return;
		}

        $CI->load->helper('url'); // to be on the safe side

		//disable rules for the auth/ url, otherwise user will never see the login page
        if($CI->uri->segment(1) !== 'auth' && $CI->uri->segment(1) !== 'api')
        {
			//remember the page user was on
			$destination=$CI->uri->uri_string();
			$CI->session->set_userdata("destination",$destination);

			if (!$CI->ion_auth->logged_in()) 
			{
				//check ajax requests
				if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
				{
					header('HTTP/1.0 401 Unauthorized');
					exit;
				}			
				
				//redirect to the login page
				redirect("auth/login/?destination=$destination", 'refresh');
			}
        }
}


/**
*
* Disable Admin access
*/
function disable_admin_access($params)
{
		return;
		$CI =& get_instance();		
        $CI->load->helper('url'); // to be on the safe side

		//URL allowed to access admin area
		$allowed_host='http://localhost/';
				
		//segments to disable
		$disallowed_segment='/admin';
		
		//accessing from the allowed host
		if (strpos(current_url(),$allowed_host)!==FALSE)
		{
			return;
		}
		
		//accessing from dis-allowed host
		//check if accessing restricted pages
		if (strpos(current_url(),$disallowed_segment))
		{
			show_404();
		}
}


function admin_auto_login()
{
		$CI =& get_instance();		
        $CI->load->helper('url'); // to be on the safe side
			
		if (!$CI->ion_auth->logged_in()) 
		{
			$CI->ion_auth->login('', '');
		}
}

/* End of file hooks.php */
/* Location: ./system/application/config/hooks.php */