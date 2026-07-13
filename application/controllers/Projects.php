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
		$this->lang->load("users");
		$this->lang->load("general");
		$this->lang->load("project");
		if (!user_can_access_projects()) {
			$this->template->set_template('default');
			$options['translations'] = $this->lang->language;
			echo $this->load->view('project/no_editor_access', $options, true);
			return;
		}
		$this->template->set_template('default');
		$options['translations']=$this->lang->language;
		echo $this->load->view('project/home',$options,true);

		//$this->template->write('content', $content,true);
		//$this->template->render();
	}

	function edit($id=null)
	{
		try{
			$this->ensure_editor_memory_limit();
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
				$this->Metadata_schemas_model->get_schema_file_path($project_type);
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

			$options['template_uid']=isset($template['uid']) ? $template['uid'] : '';
			$options['post_url']=site_url('api/editor/update/'.$project['type'].'/'.$project['id']);
			$options['user_has_edit_access']=$this->user_has_edit_access($project['id']);

			$content= $this->load->view('metadata_editor/index_vuetify',$options,true);
			echo $content;
		}
		catch(Exception $e){
			show_error($e->getMessage());
		}
	}


	/**
	 *  Raise memory limit when php.ini is low.
	 */
	private function ensure_editor_memory_limit()
	{
		$target_bytes = 512 * 1024 * 1024;
		$current = ini_get('memory_limit');
		if ($current === '-1') {
			return;
		}

		$bytes = 0;
		if (preg_match('/^(\d+)([KMG])?$/i', trim((string) $current), $m)) {
			$bytes = (int) $m[1];
			$unit = isset($m[2]) ? strtoupper($m[2]) : '';
			if ($unit === 'K') {
				$bytes *= 1024;
			} elseif ($unit === 'M') {
				$bytes *= 1024 * 1024;
			} elseif ($unit === 'G') {
				$bytes *= 1024 * 1024 * 1024;
			}
		}

		if ($bytes > 0 && $bytes < $target_bytes) {
			ini_set('memory_limit', '512M');
		}
	}


	/***
	 * 
	 * Get project template or the default template for the project type
	 * 
	 */
	function get_project_template($project)
	{
		return $this->Editor_template_model->resolve_template_for_project($project);
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
