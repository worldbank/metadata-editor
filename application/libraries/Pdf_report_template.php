<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 
 * 
 * Generate PDF for Templates
 * 
 *
 */
class PDF_report_template{
	
	var $ci;
	var $template;
	
    //constructor
	function __construct($params=NULL)
	{
		$this->ci =& get_instance();
		
		if (isset($params['codepage']) ){
			$codepage=$params['codepage'];
		}
		else{
			$codepage=$this->ci->config->item("pdf_codepage");		
		}
			
		$this->ci->load->library('my_mpdf',array('codepage'=>$codepage));

		//to use core fonts only - works only for latin languages
		//$this->ci->load->library('my_mpdf',array('codepage'=>$codepage, 'mode'=>'c'));

		//$this->ci->load->helper('xslt_helper');
		//$this->ci->lang->load("ddibrowser");

		$this->ci->load->model("Editor_template_model");
		
		/*
		$this->ci->load->library("Metadata_template");
		$this->ci->load->library("Dataset_manager");

		$this->ci->load->helper("resource_helper");
		$this->ci->load->helper("metadata_view");
		$this->ci->load->helper('array');
		*/
    }

	function initialize($uid)
	{
		$this->template=$this->ci->Editor_template_model->get_template_by_uid($uid);

		if(!$this->template){
			show_error("Template not found");
		}

	}
	
	function generate($output_filename='template.pdf',$options=array())
    {
		if (!$this->template){
			throw new Exception("Template not initialized");
		}

        $mpdf=$this->ci->my_mpdf;
		$mpdf->h2bookmarks = array('H1'=>0, 'H2'=>1, 'H3'=>2,'H4'=>3);
		$mpdf->h2toc = array(
			'H1' => 0,
			'H2' => 1,
			'H3' => 2
		);


		$stylesheet='body,html,*{font-size:12px;font-family:arial,verdana}'."\r\n";
		$stylesheet.= @file_get_contents(APPPATH.'views/pdf_reports/pdf.css');
        $mpdf->WriteHTML($stylesheet,1);

        //footer
		$mpdf->defaultfooterfontsize = 8;	// in pts
		$mpdf->defaultfooterfontstyle = '';	// blank, B, I, or BI
		$mpdf->defaultfooterline = 0; 	// 1 to include line below header/above footer
		$mpdf->setFooter('{PAGENO}');
        $mpdf->AddPage();
		$mpdf->WriteHTML($this->template_html());
		
		//$mpdf->Output();
        //$mpdf->Output($output_filename,"F");
		$mpdf->OutputHttpDownload(basename($output_filename));
		return true;
    }

	

	/**
	 * 
	 * 
	 * Get study level metadata as HTML
	 * 
	 */
	private function template_html()
	{

		//parse Markdown for instructions
		if (isset($this->template['instructions'])){
			$this->ci->load->library('MarkdownParser');
			$this->template['instructions']=$this->ci->markdownparser->parse_markdown($this->template['instructions']);
		}

		return $this->ci->load->view('templates/print',array("template"=>$this->template),true);
	}



}// END PDF_Report Class

/* End of file PDF_Report.php */
/* Location: ./application/libraries/PDF_Report.php */