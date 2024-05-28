<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 
 * 
 * Generate PDF reports
 * 
 *
 */
class Html_Report{
	
	var $ci;
	
    //constructor
	function __construct($params=NULL)
	{
		$this->ci =& get_instance();
		
		$this->ci->load->model("Editor_model");
		$this->ci->load->library("Pagepreview");
    }

	function generate($sid)
    {
		$this->project=$this->ci->Editor_model->get_row($sid);

		if (!$this->project){
			throw new Exception("Project not found");
		}
		
		return $this->project_metadata_html();
    }


	/**
	 * 
	 * 
	 * Get study level metadata as HTML
	 * 
	 */
	 function project_metadata_html()
	{
		$template=$this->ci->pagepreview->get_template_project_type($this->project['type']);
		$this->ci->pagepreview->initialize($this->project,$template['template']);

		$html=$this->ci->load->view('project_preview/index',
			array(					
				'project'=>$this->project,
				'template'=>$template
			),true
		);

		return $this->ci->load->view('project_preview/html_report',array('html'=>$html),true);
	}




}// END HTML_Report Class

/* End of file HTML_Report.php */
/* Location: ./application/libraries/Html_Report.php */