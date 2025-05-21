<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Create Reports from the DDI to several different formats 
 * 
 * Available formats are:
 *	- PDF
 *	- WORD
 *	- EXCEL
 *
 *
 * @package		NADA 2.1
 * @subpackage	Libraries
 * @author		Mehmood
 * @link		-
 *
 */
class DDI_Report{    	
	
	var $ci;
	
	var $errors=array();
	
    //constructor
	function __construct()
	{
		$this->ci =& get_instance();		
		$this->ci->load->helper('xslt_helper');
    }
	
function generate_report($ddi_file, $option=array())
{
	$options=array(
			'overview', 
			'sampling', 
			'questionnaires', 
			'datacollection', 
			'dataprocessing',
			'dataappraisal'
			);

	$codepage=$this->ci->config->item("pdf_codepage");		
	$this->ci->load->library('my_mpdf',array('codepage'=>$codepage));
	$stylesheet = file_get_contents(FCPATH.'/themes/ddibrowser/ddi.css');
	
	$mpdf=$this->ci->my_mpdf;
	$mpdf->WriteHTML($stylesheet,1);	// The parameter 1 tells that this is css/style only and no body/html/text
	
	// Set a simple Footer including the page number
	$mpdf->setFooter('page {PAGENO}');
	
	foreach($options as $option)
	{	
		set_time_limit(0);
		$contents=$this->get_section($ddi_file,$option);
		$mpdf->AddPage();
		//$mpdf->Bookmark($option,1++);
		$mpdf->WriteHTML( $contents );
	}
	
	//get data files list
	$datafiles_str=$this->get_section($ddi_file,"datafiles-list");
	$start_pos=strpos($datafiles_str,'{START}');
	$datafiles_str=substr($datafiles_str,$start_pos);
	$datafiles_str=str_replace('{START}','',$datafiles_str);
	$datafiles_arr=explode('{BR}',$datafiles_str);
	
	//var_dump($datafiles_arr);exit;
	
	//print datafile and variable list
	foreach($datafiles_arr as $value)
	{
		$value=trim($value);
		if ($value!="")
		{
			//explode datafile id,name
			$value_arr=explode("=",$value);
			$value_arr[1]=str_replace('.NSDstat','',$value_arr[1]);
			$mpdf->AddPage();
			$mpdf->Bookmark($value_arr[1],1);
			
			$contents=$this->get_section($ddi_file,"datafile", $value_arr[0]);
			//echo $contents;
			$mpdf->WriteHTML( $contents );
		}	
		set_time_limit(0);
		//break;
	}

	//print each variable details
	$mpdf->AddPage();
	$mpdf->Bookmark("Variable Description",0);
	
	foreach($datafiles_arr as $value)
	{
		$value=trim($value);
		if ($value!="")
		{
			//explode datafile id,name
			$value_arr=explode("=",$value);
			$value_arr[1]=str_replace('.NSDstat','',$value_arr[1]);
			$mpdf->AddPage();
			$mpdf->Bookmark($value_arr[1],1);
			//$mpdf->WriteHTML( "TODO");
			$mpdf->WriteHTML( $this->get_section($ddi_file,"datafile-vars-detail", $value_arr[0]) );
		}	
		set_time_limit(0);
		//break;
	}


	$output_filename='test-report'.date("U").'.pdf';
	$mpdf->Output($output_filename,"F");
	
	echo $output_filename. ' created'; 
	//$mpdf->Output(); 
}	
	
function get_section($xml, $section, $param1=NULL)
{
		$params=array();
		
		switch($section)
		{
			case 'overview':
				$xslt='xslt/ddi_overview.xslt';
			break;
			
			case 'sampling':
				$xslt='xslt/ddi_sampling.xslt';		
			break;
	
			case 'questionnaires':
				$xslt='xslt/ddi_questionnaires.xslt';		
			break;
	
			case 'datacollection':
				$xslt='xslt/ddi_datacollection.xslt';		
			break;
			
			case 'dataprocessing':
				$xslt='xslt/ddi_dataprocessing.xslt';		
			break;
	
			case 'dataappraisal':
				$xslt='xslt/ddi_dataappraisal.xslt';		
			break;
		
			case 'datafiles-list'://returns a list of datafiles as plaintext
				$xslt='modules/mpdf/xslt/ddi_datafile_list.xslt';		
			break;		
			
			case 'datafile':
				$xslt='modules/mpdf/xslt/ddi_datafile.xslt';
				$params=array('file'=>$param1);
				//echo $param1;exit;
			break;		
	
			case 'variables':
				$xslt='xslt/dataset_variables.xslt';
				$params=array('file'=>$param1);
			break;		
	
			case 'datafile-vars-detail':
				$xslt='modules/mpdf/xslt/ddi_datafile_variables.xslt';
				$params=array('file'=>$param1);
			break;		
		}
		
		$parameters=array_merge( array('lang'=>"EN"), $params);
		//$parameters=array('lang'=>"EN");
		
		$output= xsl_transform($xml,$xslt,$parameters, $format="xml");		
		$output=trim(str_replace('<?xml version="1.0" encoding="UTF-8"?>','',$output));
		$output=trim(str_replace('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>','',$output));

		//$output=str_replace('php-survey-id',$surveyid, $output);
		$output=str_replace('<table ','<table repeat_header="1" ', $output);
		return $output;
	}


}// END DDI Report Class

/* End of file DDI_Report.php */
/* Location: ./application/libraries/DDI_Report.php *////