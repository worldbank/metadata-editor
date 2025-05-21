<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Create PDF reports
 * 
 *
 *
 * @package		NADA 2.1
 * @subpackage	Libraries
 * @author		Mehmood
 * @link		-
 *
 */
class PDF_Export{    	
	
	var $ci;
	
    //constructor
	function __construct()
	{
		$this->ci =& get_instance();
		$codepage=$this->ci->config->item("pdf_codepage");		
		$this->ci->load->library('my_mpdf',array('codepage'=>$codepage));
    }
	
	function create_pdf($contents, $stylesheet=NULL)
	{
		$mpdf=$this->ci->my_mpdf;
		
		if ($stylesheet!=NULL)
		{
			$stylesheet = file_get_contents($stylesheet);	
			$mpdf->WriteHTML($stylesheet,1);//The parameter 1 tells that this is css/style only and no body/html/text
		}
			
		// Set a simple Footer including the page number
		$mpdf->setFooter('page {PAGENO}');	
		set_time_limit(0);
		//$mpdf->AddPage();
		$mpdf->WriteHTML($contents);
		$mpdf->Output();
	}	

	function create_pdf_from_url($url, $stylesheet)
	{
		$mpdf=$this->ci->my_mpdf;
		
		if ($stylesheet!=NULL)
		{
			$stylesheet = file_get_contents($stylesheet);	
			$mpdf->WriteHTML($stylesheet,1);//The parameter 1 tells that this is css/style only and no body/html/text
		}
			
		// Set a simple Footer including the page number
		$mpdf->setFooter('page {PAGENO}');	
		set_time_limit(0);
		$mpdf->AddPage();
		$mpdf->WriteHTML( file_get_contents($url) );
		$mpdf->Output();
	}	
	
}// END PDF_Export Class

/* End of file PDF_Export.php */
/* Location: ./application/libraries/PDF_Export.php *////