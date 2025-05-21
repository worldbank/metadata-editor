<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Catalog Helpers
 *
 */

// ------------------------------------------------------------------------

/**
 * Load email settings from database
 *
 *
 * @access	public
 * @return	array
 */	
if ( ! function_exists('filter'))
{

	function filter()
	{
		$ci =& get_instance();
		$allowed=array(
					'title',
					'idno',
					'published',
					'tag',
					'nation'
					);
					
		$output=array();			
		foreach($allowed as $key)
		{
			if ($ci->input->get($key))
			{
				$output[$key]=($ci->input->get($key));
			}
		}
		
		$result=array();
		foreach($output as $key=>$value)
		{
			if (is_array($value))
			{
				$result[]=$key.': ['.implode(', ',$value).']';
			}
			else
			{
				$result[]=$key.': '.$value.'';
			}	
		}
		
		return $result;
	}  
  
}

/* End of file email_helper.php */
/* Location: ./system/helpers/email_helper.php */