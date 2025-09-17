<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 
 * 
 * Generate PDF reports
 * 
 *
 */
class Html_Report{
	
	private $ci;
	public $project;
	public $template_translations;
	
    //constructor
	function __construct($params=NULL)
	{
		$this->ci =& get_instance();
		
		$this->ci->load->model("Editor_model");
		$this->ci->load->model("Editor_template_model");
		$this->ci->load->model("Editor_datafile_model");
		$this->ci->load->model("Editor_variable_model");
		$this->ci->load->library("Pagepreview");
		$this->ci->load->library("Project_json_writer");
		$this->ci->load->helper('metadata_view_helper');
		$this->ci->load->language('general');
		$this->ci->load->language('project');
    }

	/**
	 * Generate HTML content for reports
	 * 
	 * @param string $sid - Study ID
	 * @param array $options - Options including 'pdf_mode' for PDF generation
	 * @return string HTML content
	 */
	function generate($sid, $options=array())
    {
		$this->project=$this->ci->Editor_model->get_row($sid);

		if (!$this->project){
			throw new Exception("Project not found");
		}

		if (isset($options['exclude_private_fields']) && $options['exclude_private_fields']==1){
			$this->ci->project_json_writer->json_remove_private_fields($sid,$this->project['metadata']);
		}
		
		// Generate project metadata HTML
		$html = $this->project_metadata_html();
		
		// Add data files for survey/microdata projects
		if ($this->project['type'] == 'survey') {
			$html .= $this->data_files_html($sid);
			
			// Add variables list for each data file
			$data_files = $this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);
			foreach($data_files as $data_file) {
				$html .= $this->data_file_variables_list($sid, $data_file['file_id']);
			}

			// Add variables detailed only if total variables < 1500 (same threshold for both HTML and PDF)
			$total_variables = 0;
			foreach($data_files as $data_file) {
				$total_variables += $this->ci->Editor_datafile_model->get_file_varcount($sid, $data_file['file_id']);
			}
			
			if ($total_variables < 1500) {
				foreach($data_files as $data_file) {
					$html .= $this->variables_detailed_html($sid, $data_file['file_id']);
				}
			}
		}
		
		// Determine if this is PDF mode
		$pdf_mode = isset($options['pdf_mode']) && $options['pdf_mode'] === true;
		
		return $this->ci->load->view('project_preview/html_report', array(
			'html' => $html, 
			'project' => $this->project, 
			'pdf_mode' => $pdf_mode
		), true);
    }

	/**
	 * Generate HTML content for PDF (convenience method)
	 * 
	 * @param string $sid - Study ID
	 * @param array $options - Options
	 * @return string HTML content optimized for PDF
	 */
	function generate_for_pdf($sid, $options=array())
    {
		$options['pdf_mode'] = true;
		return $this->generate($sid, $options);
    }


	/**
	 * 
	 * 
	 * Get study level metadata as HTML
	 * 
	 */
	 function project_metadata_html()
	{
		// Use project's assigned template if available, otherwise use default
		if (isset($this->project['template_uid']) && !empty($this->project['template_uid'])) {
			$template = $this->ci->Editor_template_model->get_template_by_uid($this->project['template_uid']);
			if (!$template) {
				throw new Exception("Template not found: " . $this->project['template_uid']);
			}
			// Ensure template has the correct structure
			if (!isset($template['template'])) {
				throw new Exception("Template structure invalid: " . $this->project['template_uid']);
			}
		} else {
			$template = $this->ci->pagepreview->get_template_project_type($this->project['type']);
		}
		
		// Load template translations using existing function
		$this->template_translations = $this->ci->Editor_template_model->get_template_translation_keys($template['uid'], 'compact');
		
		$this->ci->pagepreview->initialize($this->project,$template['template']);

		$html=$this->ci->load->view('project_preview/index',
			array(					
				'project'=>$this->project,
				'template'=>$template
			),true
		);

		return $html;
	}

	/**
	 * 
	 * Return a list of data files
	 * 
	 */
	function data_files_html($sid=NULL)
    {        
		$options['files']=$this->ci->Editor_datafile_model->select_all($sid, $include_file_info=false);		
        $options['sid']=$sid;
		$content=$this->ci->load->view('pdf_reports/microdata/data_files',$options,TRUE);
        return $content;
    }

	/**
	 * 
	 * HTML list of variables by data file
	 * 
	 */
	public function data_file_variables_list($sid, $file_id)
    {
        $file = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);		
		$variables = $this->ci->Editor_variable_model->select_all($sid, $file_id, $metadata_detailed=false);
		$file_variables_count = count($variables);

		$options = array(
			'file' => $file,
			'variables' => $variables,
			'file_variables_count' => $file_variables_count,
			'html_report' => $this,  // Pass the Html_report instance for template translations
			'project' => $this->project  // Pass project data for links
		);
		
		$content = $this->ci->load->view('project_preview/variables_by_file', $options, TRUE);
		return $content;
    }


	/**
	 * Get variable details for HTML report
	 * 
	 * @param string $sid - Study ID
	 * @param string $uid - Variable UID
	 * @return string HTML content
	 */
	public function variables_detailed_html($sid, $file_id)
	{
		$total_vars = $this->ci->Editor_datafile_model->get_file_varcount($sid, $file_id);

		if($total_vars < 1){
			return false;
		}
		
		$file_info = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);
		$variables = $this->ci->Editor_variable_model->select_all($sid, $file_id, $metadata_detailed=true);

		foreach($variables as $idx => $variable){
			$variables[$idx] = $this->transform_variable($variable);
		}

		return $this->variable_details($sid, $file_info, $variables);
	}

	public function variable_details($sid, $file_info, $variables)
	{
		$options['sid'] = $sid;
		$options['file_id'] = $file_info['id'];
		$options['file'] = $file_info;
		$options['variables'] = $variables;
		$options['html_report'] = $this;  // Pass the Html_report instance for template translations

		$content = $this->ci->load->view('project_preview/variables_ddi', $options, TRUE);
		return $content;
	}

	function transform_variable($variable)
	{		
		$sid = (int)$variable['sid'];
		unset($variable['uid']);
		unset($variable['sid']);

		$var_catgry_labels = $this->get_indexed_variable_category_labels($variable["var_catgry_labels"]);

		//process summary statistics
		$sum_stats_options = isset($variable['sum_stats_options']) ? $variable['sum_stats_options'] : [];
		$sum_stats_enabled_list = [];
		foreach($sum_stats_options as $option => $value){
			if ($value === true || $value == 1){
				$sum_stats_enabled_list[] = $option;
			}
		}

		//keep only enabled summary statistics (if sum_stats_options is set)
		if (count($sum_stats_enabled_list) > 0){			
			if (isset($variable['var_sumstat']) && is_array($variable['var_sumstat']) ){
				foreach($variable['var_sumstat'] as $idx => $sumstat){
					if (!in_array($sumstat['type'], $sum_stats_enabled_list)){
						unset($variable['var_sumstat'][$idx]);
					}
				}
				//fix to get a JSON array instead of Object
				$variable['var_sumstat'] = array_values((array)$variable['var_sumstat']);
			}
		}

		//value ranges [counts, min, max] - remove min and max if not enabled
		if (isset($variable['var_valrng']['range']) && is_array($variable['var_valrng']['range']) ){
			foreach($variable['var_valrng']['range'] as $range_key => $range){
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
					foreach($variable['var_catgry'] as $idx => $cat){

						//remove freq if not enabled
						if (isset($cat['stats']) && is_array($cat['stats']) ){
							foreach($cat['stats'] as $stat_idx => $stat){
								if ($stat['type'] == 'freq'){
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
			foreach($variable['var_catgry'] as $idx => $cat){
				if (isset($var_catgry_labels[$cat['value']])){
					$variable['var_catgry'][$idx]['labl'] = $var_catgry_labels[$cat['value']];
				}
			}
		}

		//var_wgt_id field - replace UID with VID
		if (isset($variable['var_wgt_id']) && $variable['var_wgt_id'] !== ''){
			$variable['var_wgt_id'] = $this->ci->Editor_variable_model->vid_by_uid($sid, $variable['var_wgt_id']);
		}

		array_remove_empty($variable);
		return $variable;
	}

	function get_indexed_variable_category_labels($cat_labels)
	{
		$output = array();
		foreach($cat_labels as $cat){
			if (isset($cat['labl']) && isset($cat['value'])){
				$output[$cat['value']] = $cat['labl'];
			}
		}

		return $output;
	}

	/**
	 * Get translation from template using existing translation keys
	 * 
	 * @param string $key - The key to look for in template translations
	 * @param string $fallback - Fallback text if not found in template
	 * @return string
	 */
	public function get_template_translation($key, $fallback = '')
	{
		if (is_array($this->template_translations) && isset($this->template_translations[$key])) {
			return $this->template_translations[$key];
		}

		if (t($key) != $key){
			return t($key);
		}

		if (!empty($fallback)){
			return $fallback;
		}
		
		return $key;
	}



	

}// END HTML_Report Class

/* End of file HTML_Report.php */
/* Location: ./application/libraries/Html_Report.php */