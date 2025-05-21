<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * URL Filter
 *
 * Automatically converts URLs (http, ftp, email, ...) into hyperlinks
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		Steven Wittens (http://drupal.org/user/10)
 * @link		http://drupal.org/project/urlfilter
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
if ( ! function_exists('url_filter'))
{
	function url_filter($text)
	{        
		$text   = ' ' . $text. ' ';
		$text = preg_replace_callback("!(<p>|<li>|<br\s*/?>|[ \n\r\t\(])((http://|https://|ftp://|mailto:|smb://|afp://|file://|gopher://|news://|ssl://|sslv2://|sslv3://|tls://|tcp://|udp://)([a-zA-Z0-9@:%_+*~#?&=.,/;-]*[a-zA-Z0-9@:%_+*~#&=/;-]))([.,?]?)(?=(</p>|</li>|<br\s*/?>|[ \n\r\t\)]))!i", 'callback_url_filter_replace', $text);
		$text = preg_replace("!(<p>|<li>|<br\s*/?>|[ \n\r\t\(])([A-Za-z0-9._-]+@[A-Za-z0-9._+-]+\.[A-Za-z]{2,4})([.,?]?)(?=(</p>|</li>|<br\s*/?>|[ \n\r\t\)]))!i", '\1<a href="mailto:\2">\2</a>\3', $text);
		$text = preg_replace_callback("!(<p>|<li>|[ \n\r\t\(])(www\.[a-zA-Z0-9@:%_+*~#?&=.,/;-]*[a-zA-Z0-9@:%_+~#\&=/;-])([.,?]?)(?=(</p>|</li>|<br\s*/?>|[ \n\r\t\)]))!i", 'callback_url_filter_replace', $text);
		$text = substr($text, 1, -1);		
		return $text;
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

if ( ! function_exists('callback_url_filter_replace'))
{
	function callback_url_filter_replace($match) {		
	  $caption = $match[2];
	  $output= $match[1] . '<a href="'. $match[2] .'" title="'. $match[2] .'">'. $caption .'</a>';
	  if (isset($match[5]))
	  {
		$output.=$match[5];
	  }
	  return $output;
	}
	/*
	function urlfilter_replace2($match) {
	  $match[2] = ($match[2]);
	  $caption = (($match[2]));
	  $match[2] = ($match[2]);
	  return $match[1] . '<a href="http://'. $match[2] .'" title="'. $match[2] .'">'. $caption .'</a>'. $match[3];
	}*/
}


	 /**
	 * wordwrap for utf8 encoded strings
	 *
	 * @param string $str
	 * @param integer $len
	 * @param string $what
	 * @return string
	 * @author Milian Wolff <mail@milianw.de>
	 */
if ( ! function_exists('utf8_wordwrap'))
{
	function utf8_wordwrap($str, $width, $break, $cut = false) 
	{
		if (!$cut) 
		{
			$regexp = '#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){'.$width.',}\b#U';
		} 
		else 
		{
			$regexp = '#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){'.$width.'}#';
		}
		
		if (function_exists('mb_strlen')) 
		{
			$str_len = mb_strlen($str,'UTF-8');
		} 
		else 
		{
			$str_len = preg_match_all('/[\x00-\x7F\xC0-\xFD]/', $str, $var_empty);
		}
		
		$while_what = ceil($str_len / $width);
		$i = 1;
		$return = '';
		
		while ($i < $while_what) 
		{
			preg_match($regexp, $str,$matches);
			$string = $matches[0];
			$return .= $string.$break;
			$str = substr($str, strlen($string));
			$i++;
		}
		return $return.$str; 
	}
}	
/* End of file url_filter.php */
/* Location: ./application/helpers/url_filter.php */