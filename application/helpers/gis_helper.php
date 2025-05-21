<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GIS helper function
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 */

// ------------------------------------------------------------------------


  /**
   * bbox_to_wkt
   *
   * Converts bounding box to WKT
   *
   * @access	public
   * @param	float
   * @param	float
   * @param	float
   * @param	float
   * @return	string
   */
  function bbox_to_wkt($north, $south, $east, $west)
  {
	if (!is_numeric($north) || !is_numeric($south) || !is_numeric($east) || !is_numeric($west) )
	{
	  return FALSE;
	}
	
	return "POLYGON(($west $north, $east $north, $east $south, $west $south, $west $north))";
  }

  // ------------------------------------------------------------------------

