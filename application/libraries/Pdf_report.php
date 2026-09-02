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
	var $html_report;
	
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

		$this->ci->my_mpdf->img_dpi = 96;
		$this->ci->my_mpdf->simpleTables = true;
		$this->ci->my_mpdf->curlTimeout = 5;
		$this->ci->my_mpdf->curlExecutionTimeout = 5;

		// Load language file for PDF reports - will be set in initialize method based on options
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

		// remove fields marked private
		if (isset($options['include_private_fields']) && $options['include_private_fields']==false){
			$this->ci->project_json_writer->json_remove_private_fields($sid,$this->project['metadata']);
		}

		$this->ci->load->library("html_report");
		$this->html_report = new Html_report();
		$this->html_report->project = $this->project;
		$this->html_report->pdf_mode = true;

		$template = $this->resolve_pdf_template($this->project, $options);
		$this->html_report->template = $template;
		$this->html_report->template_translations = $this->ci->Editor_template_model->get_template_translation_keys($template['uid'], 'compact');

		// Store options for use in generate method
		$this->options = $options;
	}
	
	function generate($output_filename='trash/test.pdf',$options=array())
	{
		ini_set('memory_limit', '512M');

		if (!$this->project){
			throw new Exception("Project not initialized");
		}

		if ($this->project['type']=='timeseries' || $this->project['type']=='indicator'){
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
		$project_metadata_html = $this->html_report->project_metadata_html();
		$this->writeHTMLInChunks($mpdf, $project_metadata_html);
		
		// Clear memory after processing project metadata
		unset($project_metadata_html);


		if ($this->project['type']=='survey' || $this->project['type']=='microdata'){
			$sid=$this->project['id'];
			$data_files=$this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);
					
			//data files list
			$data_files_html=$this->html_report->data_files_html($this->project['id']);

				if ($data_files_html){
					$mpdf->AddPage();
					$mpdf->Bookmark(t("file_description"),0);
					$this->writeHTMLInChunks($mpdf, $data_files_html);
				}

			// Check total variables count to determine if we should include detailed variables
			$total_variables = 0;
			foreach($data_files as $data_file) {
				$total_variables += $this->ci->Editor_datafile_model->get_file_varcount($sid, $data_file['file_id']);
			}
			$include_variable_list = $this->option_enabled('include_variable_list', true);
			$include_variable_details = $this->option_enabled('include_variable_details', true);

			$data_file_count = 0;
			foreach($data_files as $data_file){
				set_time_limit(0);
				$file_id = isset($data_file['file_id']) ? $data_file['file_id'] : '';
				$file_varcount = $this->ci->Editor_datafile_model->get_file_varcount($sid, $file_id);
				
				// Force garbage collection only every 3 data files to reduce CPU usage
				if ($data_file_count % 3 == 0) {
					gc_collect_cycles();
				}

				if (!$include_variable_list && !$include_variable_details) {
					$data_file_count++;
					continue;
				}

				//data file variable list
				if ($include_variable_list) {
					$mpdf->AddPage();
					$mpdf->Bookmark($data_file['file_name'],0);
					$mpdf->Bookmark(t("variable_list"),1);
					$variables_html = $this->html_report->data_file_variables_list($sid, $data_file['file_id']);
					$this->writeHTMLInChunks($mpdf, $variables_html);
					unset($variables_html);
				}

				//data file variables detailed - only if requested and total variables < 1500
				if ($include_variable_details && $total_variables < 1500) {
					$mpdf->AddPage();
					if (!$include_variable_list) {
						$mpdf->Bookmark($data_file['file_name'],0);
					}
					$mpdf->Bookmark(t("variable_description"),1);
					$batch_size = 25;
					$offset = 0;
					while ($offset < (int)$file_varcount) {
						$variables_detailed_html = $this->html_report->variables_detailed_html($sid, $data_file['file_id'], $offset, $batch_size);
						if ($variables_detailed_html === false || $variables_detailed_html === '') {
							break;
						}
						$this->writeHTMLInChunks($mpdf, $variables_detailed_html);
						unset($variables_detailed_html);
						$offset += $batch_size;
					}
				}
				
				$data_file_count++;
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
		
		// Final memory cleanup before output (only if we have many data files)
		if (isset($data_file_count) && $data_file_count > 2) {
			gc_collect_cycles();
		}
		
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
	 * Return a list of data files
	 * 
	 */
	function datafiles_html($sid=NULL)
    {        
		$options['files']=$this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);		
        $options['sid']=$sid;
		$content=$this->ci->load->view('pdf_reports/microdata/data_files',$options,TRUE);
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
		
		// Get all external resources
		$resources = $this->ci->Editor_resource_model->select_all($sid);

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



	function transform_variable($variable, $vid_map=array())
	{		
		$sid=(int)$variable['sid'];
		unset($variable['uid']);
		unset($variable['sid']);

		$cat_labels=isset($variable["var_catgry_labels"]) ? $variable["var_catgry_labels"] : array();
		$var_catgry_labels=$this->get_indexed_variable_category_labels($cat_labels);

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

		cap_variable_categories_for_report($variable, 500);

		//add var_catgry labels
		if (isset($variable['var_catgry']) && is_array($variable['var_catgry']) ){
			foreach($variable['var_catgry'] as $idx=>$cat){
				if (isset($cat['value']) && isset($var_catgry_labels[$cat['value']])){
					$variable['var_catgry'][$idx]['labl']=$var_catgry_labels[$cat['value']];
				}
			}
		}


		//var_wgt_id field - replace UID with VID
		if (isset($variable['var_wgt_id']) && $variable['var_wgt_id']!==''){
			$wgt_uid=$variable['var_wgt_id'];
			if (isset($vid_map[$wgt_uid])) {
				$variable['var_wgt_id']=$vid_map[$wgt_uid];
			} else {
				$variable['var_wgt_id']=$this->ci->Editor_variable_model->vid_by_uid($sid,$wgt_uid);
			}
		}

		array_remove_empty($variable);
		return $variable;
	}


	function get_indexed_variable_category_labels($cat_labels)
	{
		$output=array();
		if (!is_array($cat_labels)) {
			return $output;
		}
		foreach($cat_labels as $cat){
			if (isset($cat['labl']) && isset($cat['value'])){
				$output[$cat['value']]=$cat['labl'];
			}
		}

		return $output;
	}

	private function resolve_pdf_template($project, $options)
	{
		$requested_uid = isset($options['template_uid']) ? $options['template_uid'] : null;
		if (!empty($requested_uid)) {
			$template = $this->ci->Editor_template_model->get_template_by_uid($requested_uid);
			if ($template && isset($template['template']) && !(isset($template['is_deleted']) && $template['is_deleted'] == 1)) {
				$template_type = isset($template['data_type']) ? $template['data_type'] : '';
				$project_type = isset($project['type']) ? $project['type'] : '';
				$microdata_types = array('survey', 'microdata');
				$compatible = ($template_type === '')
					|| ($template_type === $project_type)
					|| (in_array($template_type, $microdata_types, true) && in_array($project_type, $microdata_types, true));
				if ($compatible) {
					return $template;
				}
			}
		}

		return $this->ci->Editor_template_model->resolve_template_for_project($project);
	}

	private function option_enabled($key, $default = true)
	{
		if (!isset($this->options[$key])) {
			return $default;
		}

		$value = $this->options[$key];
		if ($value === false || $value === 0 || $value === '0') {
			return false;
		}

		return (int)$value === 1 || $value === true || $value === '1';
	}

}// END PDF_Report Class

/* End of file PDF_Report.php */
/* Location: ./application/libraries/PDF_Report.php */