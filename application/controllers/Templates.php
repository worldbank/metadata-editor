<?php
/**
 * Metadata editor
 *
 *
 */
class Templates extends MY_Controller {

	var $active_repo=NULL; //active repo object

  	public function __construct()
	{
      	parent::__construct();
		$this->load->model('Editor_model');
		$this->load->model('Editor_template_model');
		$this->load->library("Editor_acl");
		$this->lang->load("users");
		$this->lang->load("general");
		$this->lang->load("template_manager");		
	}


	function index($uid=null)
	{
		if ($uid){
			return $this->edit($uid);
		}

		$this->editor_acl->has_access_or_die($resource_='template_manager',$privilege='view');
		$options['translations']=$this->lang->language;
		echo $this->load->view('templates/index',$options,true);
	}

	function edit($uid)
	{
		
		$this->template->set_template('blank');		
		$user_template=$this->Editor_template_model->get_template_by_uid($uid);

		if(!$user_template){
			show_error("Template not found");
		}

		
		// Core and generated templates are read-only in the editor
		$is_read_only_template = false;
		if (!empty($user_template['template_type']) && in_array($user_template['template_type'], array('core', 'generated'), true)) {
			$is_read_only_template = true;
		} elseif (!empty($user_template['is_generated'])) {
			$is_read_only_template = true;
		} elseif ($this->Editor_template_model->get_core_template_by_uid($uid)) {
			$is_read_only_template = true;
		}

		// Check if user has edit access (only for editable custom templates)
		$user_has_edit_access = false;
		if (!$is_read_only_template) {
			try {
				$this->editor_acl->user_has_template_access($uid,$permission='edit');
				$user_has_edit_access = true;
			} catch (Exception $e) {
				// User doesn't have edit access, but can still view
				$user_has_edit_access = false;
			}
		}
		
		$core_templates=$this->Editor_template_model->get_core_template_by_data_type($user_template['data_type']);

		$core_template=null;

		if (!empty($core_templates)){
			$core_template=$this->Editor_template_model->get_template_by_uid($core_templates[0]["uid"]);
		} else {
			$generated_uid=$this->Editor_template_model->build_generated_template_uid($user_template['data_type']);
			$core_template=$this->Editor_template_model->get_template_by_uid($generated_uid);

			if (!$core_template){
				$core_template=$user_template;
			}
		}

		// Get icon for this template's data type
		$template_icon_url = null;
		if (!empty($user_template['data_type'])) {
			$this->load->library('Schema_registry');
			$template_icon_url = $this->schema_registry->get_schema_icon_full_url($user_template['data_type']);
		}

		$options=array(
			'user_template_info'=>$user_template,
			'core_template'=>$core_template,
			'user_template'=>$user_template,
			'translations'=>$this->lang->language,
			'template_icon_url'=>$template_icon_url,
			'user_has_edit_access'=>$user_has_edit_access
		);

		unset($options['user_template_info']['template']);
		echo $this->load->view('template_manager/index',$options,true);
	}

	
	function preview($uid)
	{
		$this->editor_acl->has_access_or_die($resource_='template_manager',$privilege='view');
		$this->template->set_template('blank');		
		$user_template=$this->Editor_template_model->get_template_by_uid($uid);

		if(!$user_template){
			show_error("Template not found");
		}

		if (isset($user_template['template']) && is_string($user_template['template'])) {
			$decoded = json_decode($user_template['template'], true);
			if (is_array($decoded)) {
				$user_template['template'] = $decoded;
			}
		}

		//parse Markdown for instructions
		if (isset($user_template['instructions'])){
			$this->load->library('MarkdownParser');
			$user_template['instructions']=$this->markdownparser->parse_markdown($user_template['instructions']);
		}

		echo $this->load->view('templates/preview',array("template"=>$user_template),true);
	}

	function table($uid)
	{
		$this->load->library("Templates/Template_table");
		$this->editor_acl->has_access_or_die($resource_='template_manager',$privilege='view');
		$this->template->set_template('blank');		
		$user_template=$this->Editor_template_model->get_template_by_uid($uid);

		if(!$user_template){
			show_error("Template not found");
		}

		$result=$this->template_table->template_to_array($uid);
		echo $this->load->view('templates/table_output',array("data"=>$result),true);
		die();
	}


	function pdf($uid)
	{
		$this->load->library('Pdf_report_template');
		$this->editor_acl->has_access_or_die($resource_='template_manager',$privilege='view');
		//$this->template->set_template('blank');
		$this->pdf_report_template->initialize($uid);
		$this->pdf_report_template->generate($output_file_name=$uid.'.pdf');
	}

	
}
/* End of file templates.php */
/* Location: ./controllers/templates.php */
