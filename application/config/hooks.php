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



/* End of file hooks.php */
/* Location: ./system/application/config/hooks.php */