<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2009, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Querystring helper
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 * @author		International household survey network
 * @link		---
 */

// ------------------------------------------------------------------------

/**
 * Element
 *
 * Returns querystring for the provided variable keyws
 *
 * @access	public
 * @param	array
 * @return	string	returns a querystring e.g. ?key=value&key2=value2
 */	
if ( ! function_exists('get_querystring'))
{
	function get_querystring($keys)
	{
		$ci=& get_instance();

		$querystring='';
		foreach($keys as $key)
		{
			$query_value=$ci->input->get_post($key);
			if ( is_array( $query_value) )
			{
				$values=$ci->input->get_post($key);
				foreach($values as $value){
					if ($querystring==''){
						$querystring.=$key.'[]='.rawurlencode($value);
					}
					else
					{
						$querystring.='&'.$key.'[]='.rawurlencode($value);
					}
				}				
			}
			else if ($query_value!='')
			{
				if ($querystring==''){
					$querystring.=$key.'='.rawurlencode($query_value);
				}
				else
				{
					$querystring.='&'.$key.'='.rawurlencode($query_value);
				}
			}	
		}
		return $querystring;
	}
}

if ( ! function_exists('get_sess_querystring'))
{
	function get_sess_querystring($keys,$sessid)
	{
		$querystring='';
		foreach($keys as $key)
		{
			$query_value=get_post_sess($sessid,$key);
			if ( is_array( $query_value) )
			{
				$values=get_post_sess($sessid,$key);
				foreach($values as $value){
					if ($querystring==''){
						$querystring.=$key.'[]='.rawurlencode($value);
					}
					else
					{
						$querystring.='&'.$key.'[]='.rawurlencode($value);
					}
				}				
			}
			else if ($query_value!='')
			{
				if ($querystring==''){
					$querystring.=$key.'='.rawurlencode($query_value);
				}
				else
				{
					$querystring.='&'.$key.'='.rawurlencode($query_value);
				}
			}	
		}
		return $querystring;
	}
}

// ------------------------------------------------------------------------

/**
 * Element
 *
 * Creates a link for sorting fields
 *
 * @access	public
 * @param	string
 * @param	string
 * @param	string
 * @param	string
 * @param	array - pass variable names from the querystring to be included in the link
 * @return	string	link 
 */	
if ( ! function_exists('create_sort_link'))
{
	function create_sort_link($sort_by,$sort_order,$field,$label,$page_url,$querystring_keys='')
	{

		$img_sort_asc='<i class="fa fa-caret-up mr-2"></i>';
		$img_sort_desc='<i class="fa fa-caret-down mr-2"></i>';

		$additional_querystring='';
		if  (is_array($querystring_keys ) )
		{
			$additional_querystring='&'.get_querystring($querystring_keys);
		}
		
		if ($field==$sort_by){
			//set sort order, if it was ascending, set to desc for the link or vice versa
			if ($sort_order=='' || $sort_order=='asc' ){
				$sort_order_alter='desc';
				$img_sort_order=$img_sort_asc;
			}
			else{
				$sort_order_alter='asc';
				$img_sort_order=$img_sort_desc;
			}			
			
			//column with the asc/desc image
			return '<a class="selected" data-sort_by="'.$field.'" data-sort_order="'.$sort_order_alter.'" href="'.$page_url.'?sort_by='.$field.'&sort_order='.$sort_order_alter.$additional_querystring.'">'.$label.' '.$img_sort_order.'</a>';
		}
		else{
			//column without the asc/desc image
			return '<a data-sort_by="'.$field.'" data-sort_order="asc" href="'.$page_url.'?sort_by='.$field.'&sort_order=asc'.$additional_querystring.'">'.$label.'</a>';
		}
	}

}

if ( ! function_exists('create_sess_sort_link'))
{
	function create_sess_sort_link($sessid,$sort_by,$sort_order,$field,$label,$page_url,$querystring_keys='')
	{
		$img_sort_asc='<img src="images/arrow-desc.png" alt="DESC" border="0"/>';
		$img_sort_desc='<img src="images/arrow-asc.png" alt="ASC" border="0"/>';

		$additional_querystring='';
		if  (is_array($querystring_keys ) )
		{
			$additional_querystring='&'.get_sess_querystring($querystring_keys,$sessid);
		}
		
		if ($field==$sort_by){
			//set sort order, if it was ascending, set to desc for the link or vice versa
			if ($sort_order=='' || $sort_order=='asc' ){
				$sort_order_alter='desc';
				$img_sort_order=$img_sort_asc;
			}
			else{
				$sort_order_alter='asc';
				$img_sort_order=$img_sort_desc;
			}			
			
			//column with the asc/desc image
			return '<a class="selected" href="'.$page_url.'?sort_by='.$field.'&sort_order='.$sort_order_alter.$additional_querystring.'">'.$label.' '.$img_sort_order.'</a>';
		}
		else{
			//column without the asc/desc image
			return '<a href="'.$page_url.'?sort_by='.$field.'&sort_order=asc'.$additional_querystring.'">'.$label.'</a>';
		}
	}

}

/* End of file querystring_helper.php */
/* Location: ./application/helpers/querystring_helper.php */
