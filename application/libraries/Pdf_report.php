<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 
 * 
 * Generate PDF reports
 * 
 *
 */
class PDF_Report{
	
	var $ci;
	var $project;
	
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

		$this->ci->load->model("Editor_model");
		$this->ci->load->library("Pagepreview");
		
		/*
		$this->ci->load->library("Metadata_template");
		$this->ci->load->library("Dataset_manager");

		$this->ci->load->helper("resource_helper");
		$this->ci->load->helper("metadata_view");
		$this->ci->load->helper('array');
		*/
    }

	function initialize($sid)
	{
		$this->project=$this->ci->Editor_model->get_row($sid);

		if (!$this->project){
			throw new Exception("Project not found");
		}
	}
	
	function generate($output_filename='trash/test.pdf',$options=array())
    {
		if (!$this->project)
		{
			throw new Exception("Project not initialized");
		}

		if ($this->project['type']=='survey'){
			//return $this->generate_pdf_ddi($sid,$output_filename,$options);
		}

        $mpdf=$this->ci->my_mpdf;

		$stylesheet='body,html,*{font-size:12px;font-family:arial,verdana}'."\r\n";
		$stylesheet.= @file_get_contents(APPPATH.'views/pdf_reports/pdf.css');
        $mpdf->WriteHTML($stylesheet,1);

        //footer
		$mpdf->defaultfooterfontsize = 8;	// in pts
		$mpdf->defaultfooterfontstyle = '';	// blank, B, I, or BI
		$mpdf->defaultfooterline = 0; 	// 1 to include line below header/above footer
		$mpdf->setFooter('{PAGENO}');

		//coverpage
		$coverpage=$this->ci->load->view('pdf_reports/coverpage',array('project'=>$this->project),TRUE);
		$mpdf->AddPage();
		$mpdf->Bookmark(t("cover"),0);
		$mpdf->WriteHTML( $coverpage );		
		
		//study description
        $mpdf->AddPage();
		$mpdf->Bookmark(t("overview"),0);
		$mpdf->WriteHTML($this->project_metadata_html());




		/*
		//coverpage
		$coverpage=$this->ci->load->view('pdf_reports/coverpage',$options,TRUE);	        
		$mpdf->AddPage();
		$mpdf->Bookmark(t("cover"),0);
		$mpdf->WriteHTML( $coverpage );
        
        //header
		$mpdf->defaultheaderfontsize = 8;	// in pts
		$mpdf->defaultheaderfontstyle = '';	// blank, B, I, or BI
		$mpdf->defaultheaderline = 0; 	// 1 to include line below header/above footer
		$mpdf->SetHeader($options['study_title']);

        //study description
        $mpdf->AddPage();
		$mpdf->Bookmark(t("overview"),0);
		$mpdf->WriteHTML($this->study_metadata_html($sid));
        
        //data files list
		$data_files_html=$this->datafiles_html($sid);
		if ($data_files_html){
			$mpdf->AddPage();
			$mpdf->Bookmark(t("file_description"),0);
			$mpdf->WriteHTML($data_files_html);
		}

        //list variables
        if (!empty($files) && isset($options['toc_variable']) && $options['toc_variable']===1){		
            $mpdf->AddPage();
            $mpdf->Bookmark(t("variable_list"),0);

            foreach($files as $file){            
                $mpdf->AddPage();            
                $mpdf->Bookmark($file['file_name'],1);

                $variables=$this->data_file_variables_list($sid,$file['file_id']);
                $mpdf->WriteHTML($variables);
            }
        }
        
        //data file and variables detailed
        if (!empty($files) && isset($options['data_dic_desc']) && $options['data_dic_desc']===1){
            $mpdf->AddPage();
            $mpdf->Bookmark(t("variable_description"),0);

            foreach($files as $file){            
                $mpdf->AddPage();            
                $mpdf->Bookmark($file['file_id'],1);

                foreach($this->variables_html($sid,$file['file_id']) as $var){
					if (strlen($var)>1000000){
						$var_list=explode("<line-break/>",$var);
						foreach($var_list as $var_){
							$mpdf->WriteHTML($var_);
						}						
					}else{
                    	$mpdf->WriteHTML($var);
					}
                }
            }
        }*/


		
        $mpdf->Output($output_filename,"F");
		return true;
    }

	/**
	 * 
	 * 
	 * Generate report for Microdata
	 * 
	 */
	/*function generate_pdf_ddi($sid=null,$output_filename='',$options=array())
    {        
        $files=$this->ci->Data_file_model->get_all_by_survey($sid);

        //$params['tempDir'] = FCPATH.'/datafiles/tmp';
        
        $mpdf=$this->ci->my_mpdf;

		$stylesheet='body,html,*{font-size:12px;font-family:arial,verdana}'."\r\n";
		$stylesheet.= @file_get_contents(APPPATH.'views/pdf_reports/ddi.css');
        $mpdf->WriteHTML($stylesheet,1);

        //footer
		$mpdf->defaultfooterfontsize = 8;	// in pts
		$mpdf->defaultfooterfontstyle = '';	// blank, B, I, or BI
		$mpdf->defaultfooterline = 0; 	// 1 to include line below header/above footer
		$mpdf->setFooter('{PAGENO}');

		//coverpage
		$coverpage=$this->ci->load->view('pdf_reports/coverpage',$options,TRUE);	        
		$mpdf->AddPage();
		$mpdf->Bookmark(t("cover"),0);
		$mpdf->WriteHTML( $coverpage );
        
        //header
		$mpdf->defaultheaderfontsize = 8;	// in pts
		$mpdf->defaultheaderfontstyle = '';	// blank, B, I, or BI
		$mpdf->defaultheaderline = 0; 	// 1 to include line below header/above footer
		$mpdf->SetHeader($options['study_title']);

        //study description
        $mpdf->AddPage();
		$mpdf->Bookmark(t("overview"),0);
		$mpdf->WriteHTML($this->study_metadata_html($sid));
        
        //data files list
		$data_files_html=$this->datafiles_html($sid);
		if ($data_files_html){
			$mpdf->AddPage();
			$mpdf->Bookmark(t("file_description"),0);
			$mpdf->WriteHTML($data_files_html);
		}

        //list variables
        if (!empty($files) && isset($options['toc_variable']) && $options['toc_variable']===1){		
            $mpdf->AddPage();
            $mpdf->Bookmark(t("variable_list"),0);

            foreach($files as $file){            
                $mpdf->AddPage();            
                $mpdf->Bookmark($file['file_name'],1);

                $variables=$this->data_file_variables_list($sid,$file['file_id']);
                $mpdf->WriteHTML($variables);
            }
        }
        
        //data file and variables detailed
        if (!empty($files) && isset($options['data_dic_desc']) && $options['data_dic_desc']===1){
            $mpdf->AddPage();
            $mpdf->Bookmark(t("variable_description"),0);

            foreach($files as $file){            
                $mpdf->AddPage();            
                $mpdf->Bookmark($file['file_id'],1);

                foreach($this->variables_html($sid,$file['file_id']) as $var){
					if (strlen($var)>1000000){
						$var_list=explode("<line-break/>",$var);
						foreach($var_list as $var_){
							$mpdf->WriteHTML($var_);
						}						
					}else{
                    	$mpdf->WriteHTML($var);
					}
                }
            }
        }


		//ext_resources_html
		if(isset($options['ext_resources']) && $options['ext_resources']===1)
		{
			$mpdf->AddPage();
			$mpdf->Bookmark(t("external_resources"),0);
			$mpdf->WriteHTML( $options['ext_resources_html']);
		}

        $mpdf->Output($output_filename,"F");
		return true;
    }*/

	/**
	 * 
	 * 
	 * Get study level metadata as HTML
	 * 
	 */
	private function project_metadata_html()
	{
		$template=$this->ci->pagepreview->get_template_project_type($this->project['type']);
		$this->ci->pagepreview->initialize($this->project,$template['template']);

		return $this->ci->load->view('project_preview/index',
			array(					
				'project'=>$this->project,
				'template'=>$template
			),true
		);
	}


	/**
	 * 
	 * Return a list of data files
	 * 
	 */
	function datafiles_html($sid=NULL)
    {
        //$this->load->model("Variable_group_model"); 
		$options['files']=$this->ci->Data_file_model->get_all_by_survey($sid);
		//$options['variable_groups_html']=$this->Variable_group_model->get_vgroup_tree_html($sid);
        $options['sid']=$sid;
		$content=$this->ci->load->view('pdf_reports/data_files',$options,TRUE);
		//$content=$this->load->view('survey_info/data_dictionary_layout',$options,TRUE);
        return $content;
    }


	/**
	 * 
	 * HTML list of variables by data file
	 * 
	 */
	public function data_file_variables_list($sid, $file_id)
    {
		$offset=0;
		$limit=15000;

		$this->ci->lang->load('ddi_fields');
		$this->ci->load->model("Variable_group_model");
        $options['sid']=$sid;
		$options['file_id']=$file_id;
		$options['variable_groups_html']=$this->ci->Variable_group_model->get_vgroup_tree_html($sid);
		$options['file_list']=$this->ci->Data_file_model->get_all_by_survey($sid);
        $options['file']=$this->ci->Data_file_model->get_file_by_id($sid,$file_id);
		$options['variables']=$this->ci->Variable_model->paginate_file_variables($sid, $file_id,$limit,$offset);
		$options['file_variables_count']=$this->ci->Variable_model->get_file_variables_count($sid,$file_id);

        if (!$options['file']){
            show_404();
		}
		
        $content=$this->ci->load->view('pdf_reports/variables_by_file',$options,TRUE);
        return $content;
    }

	function variables_html($sid,$file_id)
    {
        //echo '<pre>';
        $total_vars=$this->ci->Variable_model->get_file_variables_count($sid,$file_id);

        if($total_vars<1){
            return false;
        }

        $file_info=$this->ci->Data_file_model->get_file_by_id($sid,$file_id);

        $offset=0;
        $limit=10;//per page
        $total_pages=ceil($total_vars/$limit);
        
        for($page=0;$page<=$total_pages;){            
            $variables=$this->ci->Variable_model->paginate_file_variables($sid, $file_id,$limit,$offset);
            $vid_arr=array_column($variables,'vid');
            
            //vars with detailed metadata
            $variables=$this->ci->Variable_model->get_batch_variable_metadata($sid,$file_id,$vid_arr);

            yield $this->variable_details($sid,$file_info, $variables);

            $page++;
            $offset=$offset+$limit;
        }
    }


    public function variable_details($sid,$file_info, $variables)
    {
		$this->ci->lang->load('ddi_fields');

        $options['sid']=$sid;
        $options['file_id']=$file_info['id'];
        $options['file']=$file_info;
		$options['variables']=$variables;

		$content=$this->ci->load->view('pdf_reports/variables_ddi',$options,TRUE);		
        return $content;
    }

}// END PDF_Report Class

/* End of file PDF_Report.php */
/* Location: ./application/libraries/PDF_Report.php */