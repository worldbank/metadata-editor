<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * RDF Parser Class
 * 
 *
 * Usage:
 * 
 * $parserObj->parse(rdf_string); //returns an array of parsed data
 *
 *
 * @package		NADA 2.1
 * @subpackage	Libraries
 * @category	RDF Parser
 * @author		Mehmood
 * @link		-
 *
 */
class RDF_Parser{
    
	//List of study fields in the order returned by the XSLT
	var $fields=array
			(
				'title'=>0,
				'author'=>1,
				'dcdate'=>2,
				'country'=>3,
				'language'=>4,
				'contributor'=>5,
				'publisher'=>6,
				'description'=>7,
				'abstract'=>8,
				'toc'=>9,
				'filename'=>10,
				'format'=>11,
				'type'=>12,
				'subtitle'=>13
			);
	
    //constructor
	function __construct()
	{
		$CI =& get_instance();
		$CI->load->helper('xslt_helper');
    }

	
	/**
	 * Import RDF into an Array
	 * using the XSLT. 
	 *
	 * @return array
	 **/
	function parse($rdf_str)
	{		
		$xslt=APPPATH.'../xslt/dc2sql.xslt';
		$output=xsl_transform($rdf_str,$xslt,$parameters=NULL, $format="xml");		
		$output=str_replace('<?xml version="1.0" encoding="UTF-8"?>','',$output);
		
		if ($output=='' || strlen($output) <100)
		{
			return NULL;
		}
		
		//explode records
		$records=explode('{RCRD}',$output);
		$result=NULL;
		//convert into an array
		foreach($records as $rec)
		{
			//explode rows
			$rows=explode('{LN}',$rec);
			
			if ( strlen($rows[0])>1)
			{
				//add to array		
				$result[]=$rows;
			}			
		}
		
		return $result;
	}
	
	
}
// END RDF Parser Class

/* End of file RDF_Parser.php */
/* Location: ./application/libraries/RDF_Parser.php */