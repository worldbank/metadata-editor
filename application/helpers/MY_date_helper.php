<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Convert date to relative date format
 *
 * @access	public
 * @param	number	time
 * @return	string
 * @link 	http://www.geekshangout.com/node/5
 * @author	http://www.geekshangout.com/users/phileddies
 */	
if ( ! function_exists('relative_date'))
{
	function relative_date($time) 
	{
		$today = strtotime(date('M j, Y'));
		$reldays = ($time - $today)/86400;
		if ($reldays >= 0 && $reldays < 1) 
		{
			return 'Today';
		} 
		else if ($reldays >= 1 && $reldays < 2) 
		{
			return 'Tomorrow';
		} 
		else if ($reldays >= -1 && $reldays < 0) 
		{
			return 'Yesterday';
		}
	
		if (abs($reldays) < 7) 
		{
			if ($reldays > 0) 
			{
				$reldays = floor($reldays);
				return 'In ' . $reldays . ' day' . ($reldays != 1 ? 's' : '');
			} 
			else 
			{
				$reldays = abs(floor($reldays));
				return $reldays . ' day' . ($reldays != 1 ? 's' : '') . ' ago';
			}
		}
	
		if (abs($reldays) < 182) 
		{
			return date('l, j F',$time ? $time : time());
		} 
		else 
		{
			return date('l, j F, Y',$time ? $time : time());
		}
	}
}

/**
 * 
 * 
 *  Convert an associated array dates to gmt
 *
 *  usage - 	array_walk($studies, 'unix_date_to_gmt',array('created','changed')); 
 *
 * @item - associated array
 * @column_names - array of date field names to be converted  
 */
if ( ! function_exists('unix_date_to_gmt'))
{
	function unix_date_to_gmt(&$item, $key, $column_names=array())
	{
		foreach($column_names as $col){
			if (isset($item[$col])){
				$item[$col]=date("c",$item[$col]);
			}
		}
	}
}	

if ( ! function_exists('unix_date_to_gmt_row'))
{
	function unix_date_to_gmt_row(&$item, $key, $column_names=array())
	{
		if(in_array($key,$column_names) && !empty($item)){
			$item=date("c",$item);
		}
	}
}	