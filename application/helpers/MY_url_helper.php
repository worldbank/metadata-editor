<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * URL helpers
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		International household survey network
 * @link		---
 */

// ------------------------------------------------------------------------

/**
 * JS Redirect
 *
 * JS redirect
 *
 * @access	public
 * @param	string	the URL
 * @param	string	the URL
 * @return	string
 */
if ( ! function_exists('js_redirect'))
{
	function js_redirect($uri = '',$seconds=0)
	{
		$output= '<script type="text/javascript">';
		$output.= "var seconds=$seconds;";
		$output.= "var uri=\"$uri\";";
		$output.= 'setTimeout("window.location=\'"+uri+"\'",seconds*1000);';
		$output.= '</script>';
        $output.= '<noscript>';
        $output.= '<meta http-equiv="refresh" content="'.$seconds.';url='.$uri.'" />';
        $output.= '</noscript>';
        
        return $output;
	}
}

// ------------------------------------------------------------------------

/**
 * Prep URL extended to support ftp:// or https:
 *
 * Simply adds the http:// part if missing
 *
 * @access	public
 * @param	string	the URL
 * @return	string
 */

if ( ! function_exists('prep_url'))
{
	function prep_url($str = '')
	{
		if ($str == 'http://' OR $str == '')
		{
			return '';
		}

		if (substr($str, 0, 7) != 'http://' && substr($str, 0, 8) != 'https://' && substr($str, 0, 6) != 'ftp://')
		{
			$str = 'http://'.$str;
		}

		return $str;
	}
}

/**
*
* Returns the site home page set by the user
*
**/
if ( ! function_exists('site_home'))
{
	function site_home()
	{
    	$CI =& get_instance();
		$home=$CI->config->item("default_home_page");
        if($home==false)
        {
        	return 'catalog';
        }
        return $home;
	}
} 

	//check if SSL is available on the server
	function is_ssl_enabled()
	{
		$CI =& get_instance();
		if ($CI->config->item("proxy_ssl")===FALSE &&  $CI->config->item("enable_ssl")===FALSE)
		{
			//dont't check anything
			return FALSE;
		}
		
		$is_https=FALSE;
		
		if ($CI->config->item("proxy_ssl"))
		{
			//echo "using proxy";
			$proxy_ssl_header=$CI->config->item("proxy_ssl_header");
			
			//see if the variable is set
			if (isset($_SERVER[$proxy_ssl_header]))
			{
				$is_https=TRUE;
			}
		}
		//check SSL using server HTTPS variablbe
		else if ($CI->config->item("enable_ssl") && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=="on")
		{
				$is_https=TRUE;
		}

		return $is_https;
	}
	
	//check if current page is acccessed using HTTPS
	function is_ssl_request()
	{	
		$CI =& get_instance();
		if ($CI->config->item("proxy_ssl")===FALSE &&  $CI->config->item("enable_ssl")===FALSE)
		{
			//dont't check anything
			return FALSE;
		}
		
		//current page is loaded using HTTPS?	
		$is_https=FALSE;
		
		if ($CI->config->item("proxy_ssl"))
		{
			//echo "using proxy";
			$proxy_ssl_header=$CI->config->item("proxy_ssl_header");
			$proxy_ssl_header_value=$CI->config->item("proxy_ssl_header_value");
			
			//check if using SSL/Proxy/Headers
			if ($proxy_ssl_header!='' && $proxy_ssl_header_value!='')
			{		
				//see if the variable is set
				if (isset($_SERVER[$proxy_ssl_header]))
				{
					if ($_SERVER[$proxy_ssl_header]==$proxy_ssl_header_value)
					{
						$is_https=TRUE;
					}
				}
			}
		}
		//check SSL using server HTTPS variablbe
		else if ($CI->config->item("enable_ssl") && isset($_SERVER['HTTPS']))
		{
			//echo "dfoudfdfdfd";
			if($_SERVER['HTTPS']=="on")
			{
				$is_https=TRUE;
			}	
		}

		return $is_https;		
	}

/**
*
* Returns JS/CSS base url
*
**/
if ( ! function_exists('js_base_url'))
{
	function js_base_url()
	{
    	if (defined('JS_BASE_URL'))
        {
        	return JS_BASE_URL;
		}
		return base_url();
	}
} 

/**
 * Anchor Link
 *
 * Creates an anchor based on the local URL.
 *
 * @access	public
 * @param	string	the URL
 * @param	string	the link title
 * @param	mixed	any attributes
 * @return	string
 */
if ( ! function_exists('anchor'))
{
	function anchor($uri = '', $title = '', $attributes = '')
	{
		$title = (string) $title;

		if ( ! is_array($uri))
		{
			$site_url = ( ! preg_match('!^\w+://! i', $uri)) ? site_url($uri) : $uri;
		}
		else
		{
			$site_url = site_url($uri);
		}

		if ($title == '')
		{
			$title = $site_url;
		}

		if ($attributes != '')
		{
			$attributes = _parse_attributes($attributes);
		}

		return '<a href="'.$site_url.'"'.$attributes.'>'.$title.'</a>';
	}
}


	

/**
 * Base URL
 *
 * Returns the "base_url" item from your config file
 *
 * @access	public
 * @return	string
 */
if ( ! function_exists('base_url'))
{
	function base_url($uri = '')
	{
		$CI =& get_instance();
		$base_url=$CI->config->slash_item('base_url');
		
		if (is_ssl_enabled() 
			//&& is_ssl_protected(current_url())==TRUE
		)
		{
			$base_url=str_replace("http:","https:",$base_url);
		}
		
		// Append URI if provided
		if ($uri !== '')
		{
			$base_url .= ltrim($uri, '/');
		}
		
		return $base_url;
	}
}	

/**
 * IS_SSL_PROTECTED
 *
 * Checks if a URL is protected by SSL
 * 
 *
 * @access	public
 * @param	string
 * @return	string
 */
if ( ! function_exists('is_ssl_protected'))
{
	function is_ssl_protected($url)
	{
		$CI =& get_instance();
		$ssl_urls=$CI->config->item("ssl_urls"); //array of SSL protected URLs
	
		if (!$ssl_urls)
		{
			//default value if config setting is not set
			$ssl_urls=array('/auth/');
		}
		
		foreach($ssl_urls as $ssl_part)
		{			
			if (strpos($url,$ssl_part)!==FALSE)
			{
				return TRUE;
			}	
		}
		
		return FALSE;
	}
}

/**
 * Site URL
 *
 * Create a local URL based on your basepath. Segments can be passed via the
 * first parameter either as a string or an array.
 *
 * @access	public
 * @param	string
 * @return	string
 */
if ( ! function_exists('site_url'))
{
	function site_url($uri = '')
	{	
		$CI =& get_instance();
		$url= $CI->config->site_url($uri);
		$ssl_urls=$CI->config->item("ssl_urls");
		
		if (is_ssl_enabled() 
			//&& is_ssl_protected(current_url())!==FALSE
		)
		{
			return $url=str_replace("http:","https:",$url);
		}
		
		return $url;

	}
}

/**
 * NADA Site URL
 *
 * Returns the "nada_site_url" item from your config file if set
 *
 * @access	public
 * @return	string
 */
if ( ! function_exists('nada_site_url'))
{
	function nada_site_url()
	{
		$CI =& get_instance();
		$nada_site_url=$CI->config->slash_item('nada_site_url');
		
		if ($nada_site_url!==FALSE && $nada_site_url!='')
		{
			return $nada_site_url;
		}
		
		return base_url();
	}
}	



/**
 * Parse out the attributes
 *
 * Some of the functions use this
 *
 * @access	private
 * @param	array
 * @param	bool
 * @return	string
 */
if ( ! function_exists('_parse_attributes'))
{
    function _parse_attributes($attributes, $javascript = FALSE)
    {
        if (is_string($attributes))
        {
            return ($attributes != '') ? ' '.$attributes : '';
        }

        $att = '';
        foreach ($attributes as $key => $val)
        {
            if ($javascript == TRUE)
            {
                $att .= $key . '=' . $val . ',';
            }
            else
            {
                $att .= ' ' . $key . '="' . $val . '"';
            }
        }

        if ($javascript == TRUE AND $att != '')
        {
            $att = substr($att, 0, -1);
        }

        return $att;
    }
}




    /**
     * Turn all URLs in clickable links.
     * 
     * @param string $value
     * @param array  $protocols  http/https, ftp, mail, twitter
     * @param array  $attributes
     * @param string $mode       normal or all
     * @return string
	 * @author - https://stackoverflow.com/users/2533787/nothingctrl
	 * @author - https://stackoverflow.com/users/5774880/ruddernation-designs
	 * @author - https://stackoverflow.com/questions/1960461/convert-plain-text-urls-into-html-hyperlinks-in-php
	 * 
     */
	if ( ! function_exists('linkify'))
	{	
		function linkify($str) {
			
			//email addresses	
			$mail_pattern = "/([A-z0-9\._-]+\@[A-z0-9_-]+\.)([A-z0-9\_\-\.]{1,}[A-z])/";
			$str = preg_replace($mail_pattern, '<a href="mailto:$1$2">$1$2</a>', $str);
			
			//urls
			$url_pattern = '/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/';   
			return preg_replace($url_pattern, '<a href="$0" target="_blank" title="$0">$0</a>', $str);

			/*
			//urls starting with www
			$url_pattern = '~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i'; 
			return preg_replace($url_pattern, '<a href="$0" target="_blank" title="$0">$0</a>', $str);
			*/
		 }
	}

	/**
	 * 
	 * Encode email for display
	 * 
	 * @author - david walsh
	 * @author_website - https://davidwalsh.name/php-email-encode-prevent-spam
	 * 
	 */
	if ( ! function_exists('encode_email'))
	{
		function encode_email($e) {
			for ($i = 0; $i < strlen($e); $i++) { $output .= '&#'.ord($e[$i]).';'; }
			return $output;
		}
	}




	if ( ! function_exists('username_initials'))
	{
		function username_initials($username) {
			$initials = '';
			$words = explode(' ', $username);
			foreach ($words as $word) {
				$initials .= $word[0];
			}
			if (strlen($initials) > 2) {
				$initials= substr($initials, 0, 2);
			}

			return strtoupper($initials);
		}
	}

	
	{
		function nada_site_url()
		{
			$CI =& get_instance();
			$nada_site_url=$CI->config->slash_item('nada_site_url');
			
			if ($nada_site_url!==FALSE && $nada_site_url!='')
			{
				return $nada_site_url;
			}
			
			return base_url();
		}
	}


/* End of file MY_url_helper.php */
/* Location: ./application/helpers/MY_url_helper.php */