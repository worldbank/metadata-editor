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

		$this->ci->lang->load("ddi_report");
		$this->ci->load->model("Editor_model");
		$this->ci->load->library("Pagepreview");
		$this->ci->load->helper("pdf_html_helper");
		$this->ci->load->model("Editor_datafile_model"); 
		
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
		if (!$this->project){
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

		if ($this->project['type']=='survey'){
			$sid=$this->project['id'];
			$data_files=$this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);
					
			//data files list
			$data_files_html=$this->datafiles_html($this->project['id']);

			if ($data_files_html){
				$mpdf->AddPage();
				$mpdf->Bookmark(t("file_description"),0);
				$mpdf->WriteHTML($data_files_html);
			}

			foreach($data_files as $data_file){
				//data file variable list
				$mpdf->AddPage();
				$mpdf->Bookmark($data_file['file_name'],0);
				$mpdf->Bookmark(t("variable_list"),1);
				$mpdf->WriteHTML($this->data_file_variables_list($sid, $data_file['file_id']));

				//data file variables detailed
				$mpdf->AddPage();
				$mpdf->Bookmark(t("variable_description"),1);
				$mpdf->WriteHTML($this->variables_html($sid, $data_file['file_id']));
			}		


			//ext_resources_html
			/*if(isset($options['ext_resources']) && $options['ext_resources']===1)
			{
				$mpdf->AddPage();
				$mpdf->Bookmark(t("external_resources"),0);
				$mpdf->WriteHTML( $options['ext_resources_html']);
			}*/

		}
		
        $mpdf->Output($output_filename,"F");
		return true;
    }

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
		$options['files']=$this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);		

		//echo "<pre>";
		//var_dump($options['files']);

        $options['sid']=$sid;
		$content=$this->ci->load->view('pdf_reports/microdata/data_files',$options,TRUE);
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
		//$offset=0;
		//$limit=15000;

		$this->ci->lang->load('ddi_fields');
		$this->ci->load->model("Editor_variable_model");
        $options['sid']=$sid;
		$options['file_id']=$file_id;
		//$options['variable_groups_html']=$this->ci->Variable_group_model->get_vgroup_tree_html($sid);
		//$options['file_list']=$this->ci->Editor_datafile_model->select_all($sid, false);
        $options['file']=$this->ci->Editor_datafile_model->data_file_by_id($sid,$file_id);		
		$options['variables']=$this->ci->Editor_variable_model->select_all($sid,$file_id,$metadata_detailed=false);

		$options['file_variables_count']=count($options['variables']);
        $content=$this->ci->load->view('pdf_reports/microdata/variables_by_file',$options,TRUE);
        return $content;
    }

	function variables_html($sid,$file_id)
    {
        $total_vars=$this->ci->Editor_datafile_model->get_file_varcount($sid,$file_id);

        if($total_vars<1){
            return false;
        }
		
        $file_info=$this->ci->Editor_datafile_model->data_file_by_id($sid,$file_id);
		$variables=$this->ci->Editor_variable_model->select_all($sid,$file_id,$metadata_detailed=true);

		foreach($variables as $idx=>$variable){
			$variables[$idx]=$this->transform_variable($variable);
		}

		return $this->variable_details($sid,$file_info, $variables);
    }


    public function variable_details($sid,$file_info, $variables)
    {
		$this->ci->lang->load('ddi_fields');

        $options['sid']=$sid;
        $options['file_id']=$file_info['id'];
        $options['file']=$file_info;
		$options['variables']=$variables;

		$content=$this->ci->load->view('pdf_reports/microdata/variables_ddi',$options,TRUE);
        return $content;
    }



	function transform_variable($variable)
	{		
		$sid=(int)$variable['sid'];
		unset($variable['uid']);
		unset($variable['sid']);

		$var_catgry_labels=$this->get_indexed_variable_category_labels($variable["var_catgry_labels"]);

		//process summary statistics
		$sum_stats_options = isset($variable['sum_stats_options']) ? $variable['sum_stats_options'] : [];
		$sum_stats_enabled_list=[];
		foreach($sum_stats_options as $option=>$value){
			if ($value===true || $value==1){
				$sum_stats_enabled_list[]=$option;
			}
		}

		//keep only enabled summary statistics (if sum_stats_options is set)
		if (count($sum_stats_enabled_list) > 0){			
			if (isset($variable['var_sumstat']) && is_array($variable['var_sumstat']) ){
				foreach($variable['var_sumstat'] as $idx=>$sumstat){
					if (!in_array($sumstat['type'], $sum_stats_enabled_list)){
						unset($variable['var_sumstat'][$idx]);
					}
				}
				//fix to get a JSON array instead of Object
				$variable['var_sumstat']=array_values((array)$variable['var_sumstat']);
			}
		}

		//value ranges [counts, min, max] - remove min and max if not enabled
		if (isset($variable['var_valrng']['range']) && is_array($variable['var_valrng']['range']) ){
			foreach($variable['var_valrng']['range'] as $range_key=>$range){
				//only check for min and max
				if (!in_array($range_key, array("min", "max"))){
					continue;
				}

				if (count($sum_stats_enabled_list) > 0){	
					if (!in_array($range_key, $sum_stats_enabled_list)){
						unset($variable['var_valrng']['range'][$range_key]);
					}
				}
			}
		}

		if (count($sum_stats_enabled_list) > 0){	
			//remove category freq if not enabled
			if (!in_array('freq', $sum_stats_enabled_list)){
				if (isset($variable['var_catgry']) && is_array($variable['var_catgry']) ){
					foreach($variable['var_catgry'] as $idx=>$cat){

						//remove freq if not enabled
						if (isset($cat['stats']) && is_array($cat['stats']) ){
							foreach($cat['stats'] as $stat_idx=>$stat){
								if ($stat['type']=='freq'){
									unset($variable['var_catgry'][$idx]['stats'][$stat_idx]);
								}
							}						
						}
					}
				}
			}
		}

		//add var_catgry labels
		if (isset($variable['var_catgry']) && is_array($variable['var_catgry']) ){
			foreach($variable['var_catgry'] as $idx=>$cat){
				if (isset($var_catgry_labels[$cat['value']])){
					$variable['var_catgry'][$idx]['labl']=$var_catgry_labels[$cat['value']];
				}
			}
		}


		//var_wgt_id field - replace UID with VID
		if (isset($variable['var_wgt_id']) && $variable['var_wgt_id']!==''){
			$variable['var_wgt_id']=$this->ci->Editor_variable_model->vid_by_uid($sid,$variable['var_wgt_id']);
		}

		array_remove_empty($variable);
		return $variable;
	}


	function get_indexed_variable_category_labels($cat_labels)
	{
		$output=array();
		foreach($cat_labels as $cat){
			if (isset($cat['labl']) && isset($cat['value'])){
				$output[$cat['value']]=$cat['labl'];
			}
		}

		return $output;
	}

}// END PDF_Report Class

/* End of file PDF_Report.php */
/* Location: ./application/libraries/PDF_Report.php */