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

		$this->editor_acl->user_has_template_access($uid,$permission='edit');
		$core_templates=$this->Editor_template_model->get_core_template_by_data_type($user_template['data_type']);

		if (!$core_templates){
			throw new Exception("No system templates found for type: " . $user_template['data_type']);
		}

		$core_template=$this->Editor_template_model->get_template_by_uid($core_templates[0]["uid"]);

		$options=array(
			'user_template_info'=>$user_template,
			'core_template'=>$core_template,
			'user_template'=>$user_template,
			'translations'=>$this->lang->language
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
