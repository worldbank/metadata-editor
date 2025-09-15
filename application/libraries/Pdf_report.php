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
	var $options;
	
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

		// Set image resolution to 300 DPI
		$this->ci->my_mpdf->img_dpi = 300;

		// Load language file for PDF reports - use current language setting
		$current_lang = $this->ci->config->item('language');
		$this->ci->lang->load("ddi_report", $current_lang);
		$this->ci->load->model("Editor_model");
		$this->ci->load->library("Pagepreview");
		$this->ci->load->helper("pdf_html_helper");
		$this->ci->load->model("Editor_datafile_model"); 
		$this->ci->load->helper('metadata_view_helper');
		$this->ci->load->library('Latex_processor');
		
    }

	function initialize($sid, $options=array())
	{
		$this->project=$this->ci->Editor_model->get_row($sid);

		if (!$this->project){
			throw new Exception("Project not found");
		}

		// Handle private fields option - if include_private_fields is false, exclude them
		if (isset($options['include_private_fields']) && $options['include_private_fields']==false){
			$this->ci->project_json_writer->json_remove_private_fields($sid,$this->project['metadata']);
		}

		// Store options for use in generate method
		$this->options = $options;
	}
	
	function generate($output_filename='trash/test.pdf',$options=array())
	{
		// Increase memory limit for PDF generation
		ini_set('memory_limit', '512M');
		#ignore deprecation warnings
		error_reporting(E_ALL & ~E_DEPRECATED);
		

		if (!$this->project){
			throw new Exception("Project not initialized");
		}

		if ($this->project['type']=='survey'){
			//return $this->generate_pdf_ddi($sid,$output_filename,$options);
		}

		if ($this->project['type']=='timeseries'){
			$latex_elements=$this->ci->latex_processor->get_latex_elements($this->project['metadata']);
			
			if (count($latex_elements)>0){
				// Process LaTeX content before PDF generation
				$this->ci->latex_processor->process_latex_content($this->project['metadata']);
			}
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
		$project_metadata_html = $this->project_metadata_html();
		$this->writeHTMLInChunks($mpdf, $project_metadata_html);
		
		// Clear memory after processing project metadata
		unset($project_metadata_html);


		if ($this->project['type']=='survey'){
			$sid=$this->project['id'];
			$data_files=$this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);
					
			//data files list
			$data_files_html=$this->datafiles_html($this->project['id']);

				if ($data_files_html){
					$mpdf->AddPage();
					$mpdf->Bookmark(t("file_description"),0);
					$this->writeHTMLInChunks($mpdf, $data_files_html);
				}

			foreach($data_files as $data_file){
				set_time_limit(0);
				
				// Force garbage collection before processing each data file
				gc_collect_cycles();

				//data file variable list
				$mpdf->AddPage();
				$mpdf->Bookmark($data_file['file_name'],0);
				$mpdf->Bookmark(t("variable_list"),1);
				$variables_html = $this->data_file_variables_list($sid, $data_file['file_id']);
				$this->writeHTMLInChunks($mpdf, $variables_html);
				
				// Clear memory after processing each data file
				unset($variables_html);

				//data file variables detailed
				$mpdf->AddPage();
				$mpdf->Bookmark(t("variable_description"),1);
				$variables_detailed_html = $this->variables_html($sid, $data_file['file_id']);
				$this->writeHTMLInChunks($mpdf, $variables_detailed_html);
				
				// Clear memory after processing detailed variables
				unset($variables_detailed_html);
			}		


			//external resources
			if(isset($this->options['include_external_resources']) && $this->options['include_external_resources']==1)
			{
				$ext_resources_html = $this->external_resources_html($this->project['id']);
				if ($ext_resources_html) {
					$mpdf->AddPage();
					$mpdf->Bookmark(t("external_resources"),0);
					$this->writeHTMLInChunks($mpdf, $ext_resources_html);
					
					// Clear memory after processing external resources
					unset($ext_resources_html);
				}
			}

		}
		
		// Final memory cleanup before output
		gc_collect_cycles();
		
        $mpdf->Output($output_filename,"F");
		return true;
    }

	/**
	 * Write HTML content in smaller chunks to avoid PCRE backtrack limit
	 * 
	 * @param object $mpdf The mPDF object
	 * @param string $html The HTML content to write
	 * @param int $chunk_size Maximum chunk size (default: 500000 characters)
	 */
	private function writeHTMLInChunks($mpdf, $html, $chunk_size = 500000)
	{
		if (empty($html)) {
			return;
		}
		
		// If HTML is small enough, write it directly
		if (strlen($html) <= $chunk_size) {
			$mpdf->WriteHTML($html);
			return;
		}
		
		// Split HTML into chunks, trying to break at logical points
		$chunks = $this->splitHTMLIntoChunks($html, $chunk_size);
		
		foreach ($chunks as $chunk) {
			if (!empty(trim($chunk))) {
				$mpdf->WriteHTML($chunk);
			}
		}
	}

	/**
	 * Split HTML into chunks at logical break points
	 * 
	 * @param string $html The HTML content
	 * @param int $chunk_size Maximum chunk size
	 * @return array Array of HTML chunks
	 */
	private function splitHTMLIntoChunks($html, $chunk_size)
	{
		$chunks = array();
		$current_pos = 0;
		$html_length = strlen($html);
		
		while ($current_pos < $html_length) {
			$chunk_end = $current_pos + $chunk_size;
			
			// If this is the last chunk, take everything remaining
			if ($chunk_end >= $html_length) {
				$chunks[] = substr($html, $current_pos);
				break;
			}
			
			// Try to find a good break point (end of tag, paragraph, etc.)
			$break_points = array('</div>', '</p>', '</table>', '</tr>', '</td>', '</th>', '<br>', '<br/>');
			$best_break = $chunk_end;
			
			foreach ($break_points as $break_point) {
				$break_pos = strrpos(substr($html, $current_pos, $chunk_size), $break_point);
				if ($break_pos !== false) {
					$break_pos += $current_pos + strlen($break_point);
					if ($break_pos > $current_pos && $break_pos <= $chunk_end) {
						$best_break = $break_pos;
						break;
					}
				}
			}
			
			$chunks[] = substr($html, $current_pos, $best_break - $current_pos);
			$current_pos = $best_break;
		}
		
		return $chunks;
	}

	/**
	 * 
	 * 
	 * Get study level metadata as HTML
	 * 
	 */
	private function project_metadata_html()
	{
		// Use selected template if provided, otherwise use default
		if (isset($this->options['template_uid']) && !empty($this->options['template_uid'])) {
			$template = $this->ci->Editor_template_model->get_template_by_uid($this->options['template_uid']);
			if (!$template) {
				throw new Exception("Template not found: " . $this->options['template_uid']);
			}
			// Ensure template has the correct structure
			if (!isset($template['template'])) {
				throw new Exception("Template structure invalid: " . $this->options['template_uid']);
			}
		} else {
			$template = $this->ci->pagepreview->get_template_project_type($this->project['type']);
		}
		
		
		$this->ci->pagepreview->initialize($this->project, $template['template']);

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
	 * Return external resources HTML
	 * 
	 */
	function external_resources_html($sid=NULL)
    {        
		$this->ci->load->model('Editor_resource_model');
		
		// Get all external resources or specific ones if selected
		if (isset($this->options['external_resource_ids']) && !empty($this->options['external_resource_ids'])) {
			$resources = array();
			foreach ($this->options['external_resource_ids'] as $resource_id) {
				$resource = $this->ci->Editor_resource_model->select_single($sid, $resource_id);
				if ($resource) {
					$resources[] = $resource;
				}
			}
		} else {
			$resources = $this->ci->Editor_resource_model->select_all($sid);
		}

		if (empty($resources)) {
			return false;
		}

		$options['resources'] = $resources;
		$options['sid'] = $sid;
		
		$content = $this->ci->load->view('pdf_reports/external_resources', $options, TRUE);
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