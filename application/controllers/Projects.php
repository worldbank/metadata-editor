<?php
/**
 * Metadata editor Projects
 *
 *
 */
class Projects extends MY_Controller {

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

	function index()
	{
		$this->editor_acl->has_access_or_die($resource_='editor',$privilege='view');
		$this->lang->load("project");
		$this->template->set_template('default');
		$options['translations']=$this->lang->language;
		echo $this->load->view('project/home',$options,true);

		//$this->template->write('content', $content,true);
		//$this->template->render();
	}

	function edit($id=null)
	{
		try{
			$this->lang->load("project");
			$project=$this->Editor_model->get_basic_info($id);

			if (!$project){
				show_error('Project was not found');
			}

			$project_id=isset($project['pid']) ? $project['pid'] : $project['id'];
			$this->editor_acl->user_has_project_access($project_id,$permission='view');
			
			// Get schema file path using schema registry (handles aliases and filename mismatches)
			$this->load->model('Metadata_schemas_model');
			$project_type = $project['type'];
			
			try {
				$schema_path = $this->Metadata_schemas_model->get_schema_file_path($project_type);
			} catch (Exception $e) {
				show_error('Schema not found: ' . $e->getMessage());
			}

			$options['sid']=$id;
			$options['idno']=$project['idno'];
			$options['title']=$project['title'];
			$options['type']=$project['type'];
			
			if ($project['type']=='geospatial'){
				$this->lang->load("geospatial");
			}

			$this->lang->load('indicator_dsd');

			$options['translations']=$this->lang->language;

			$template=$this->get_project_template($project);

			if (!$template){
				show_error("Template not found for project");
			}

			$tpl_body = isset($template['template']) && is_array($template['template'])
				? $template['template']
				: null;
			$options['template_structure_valid'] = $tpl_body !== null
				&& isset($tpl_body['items'])
				&& is_array($tpl_body['items']);

			$options['metadata_template']=json_encode($template);
			$options['metadata_template_arr']=$tpl_body !== null ? $tpl_body : array();
			$options['metadata_schema']=file_get_contents($schema_path);
			$options['post_url']=site_url('api/editor/update/'.$project['type'].'/'.$project['id']);
			$options['user_has_edit_access']=$this->user_has_edit_access($project['id']);

			$content= $this->load->view('metadata_editor/index_vuetify',$options,true);
			echo $content;
		}
		catch(Exception $e){
			show_error($e->getMessage());
		}
	}


	/***
	 * 
	 * Get project template or the default template for the project type
	 * 
	 */
	function get_project_template($project)
	{
		$template=NULL;
		
		//load template set for the project
		if (isset($project['template_uid']) && !empty($project['template_uid'])){
			$template=$this->Editor_template_model->get_template_by_uid($project['template_uid']);
			
			// Check if template is soft-deleted (is_deleted=1)
			// If deleted, fallback to default/core template
			if ($template && isset($template['is_deleted']) && $template['is_deleted'] == 1){
				$template = NULL; // Force fallback
			}
		}

		if (!$template){		
			//load default template for the project type
			$default_template=$this->Editor_template_model->get_default_template($project['type']);

			if (isset($default_template['template_uid'])){
				$template=$this->Editor_template_model->get_template_by_uid($default_template['template_uid']);
				
				// Check if default template is also deleted
				if ($template && isset($template['is_deleted']) && $template['is_deleted'] == 1){
					$template = NULL; // Force fallback to core
				}
			}
		}
		
		//load core template for the project type
		if (empty($template)){
			$core_templates_by_type=$this->Editor_template_model->get_core_templates_by_type($project['type']);

			if (!$core_templates_by_type){
				throw new Exception("Template not found for type", $project['type']);
			}

			//load default core template by type
			$template=$this->Editor_template_model->get_template_by_uid($core_templates_by_type[0]["uid"]);
		}
		
		return $template;
	}



	function get_template_custom_parts($items,&$output)
	{		
		foreach($items as $item){
			if (isset($item['items'])){
				$this->get_template_custom_parts($item['items'],$output);
			}
			if (isset($item['type']) 
				&& $item['type']=='section_container' 
				&& (isset($item['is_editable']) && $item['is_editable']==false)){				
				$output[$item['key']]=$item;
			}
		}        
	}

	function get_template_editable_parts(&$items)
	{
		foreach($items as $key=>$item){
			if (isset($item['items'])){
				if (isset($item['is_editable']) && $item['is_editable']==false){
					//delete
					unset($items[$key]);
				}else{
					$this->get_template_editable_parts($item['items']);
				}
			}
			
		}
	}


	function templates($uid=null)
	{
		/*
		if ($uid){
			return $this->template_edit($uid);
		}

		$options['translations']=$this->lang->language;
		echo $this->load->view('templates/index',$options,true);
		*/
		redirect('templates/');
	}


	function template_edit($uid)
	{
		redirect('templates/'.$uid);		
	}


	/**
	 * 
	 * Check if user has edit access to the project
	 * 
	 */
	private function user_has_edit_access($sid)
	{
		try{
			$this->editor_acl->user_has_project_access($sid,$permission='edit');
			
			//locked?
			if ($this->Editor_model->is_project_locked($sid)) {
				return false;
			}
			
			return true;
		}
		catch(Exception $e){
			return false;
		}
	}

	/**
	 * 
	 * Project comparison page
	 * 
	 */
	function compare()
	{
		$this->editor_acl->has_access_or_die($resource_='editor',$privilege='view');
		$this->lang->load("project");
		$this->template->set_template('default');
		$options['translations']=$this->lang->language;
		echo $this->load->view('project/compare',$options,true);
	}

	
}
/* End of file projects.php */
/* Location: ./controllers/projects.php */
