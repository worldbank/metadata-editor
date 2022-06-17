<?php
/**
 * Catalog Maintenance Controller
 *
 * handles all Catalog Maintenance pages
 *
 */
class Metadata_editor extends MY_Controller {

	var $active_repo=NULL; //active repo object

  	public function __construct()
	{
      	parent::__construct();
     	$this->load->model('Catalog_model');
		$this->load->model('Licensed_model');
		$this->load->model('Form_model');
		$this->load->model('Data_classification_model');
		$this->load->model('Repository_model');
		$this->load->model('Citation_model');
		$this->load->model('Search_helper_model');
		$this->load->model('Catalog_admin_search_model');
		$this->load->library('pagination');
		$this->load->helper('querystring_helper','url');
		$this->load->helper('form');
		//$this->load->helper("catalog");
		$this->template->set_template('admin'); 
		$this->load->library("Dataset_manager");

		//load language file
		$this->lang->load('general');
		$this->lang->load('catalog_search');
		$this->lang->load('catalog_admin');
		$this->lang->load('permissions');
		$this->lang->load('resource_manager');

		//$this->output->enable_profiler(TRUE);
		//$this->acl->clear_active_repo();

		//set active repo
		$repo_obj=(object)$this->Repository_model->select_single($this->Repository_model->user_active_repo());

		if (empty($repo_obj) || $this->Repository_model->user_active_repo()==0){
			//set active repo to CENTRAL
			$data=$this->Repository_model->get_central_catalog_array();
			$this->active_repo=(object)$data;
		}
		else{
			//set active repo
			$this->active_repo=$repo_obj;
			$data=$this->Repository_model->get_repository_by_repositoryid($repo_obj->repositoryid);
		}

		//set collection sticky bar options
		$collection=$this->load->view('repositories/repo_sticky_bar',$data,TRUE);
		$this->template->add_variable($name='collection',$value=$collection);
	}

	function metadata_editor_files($id)
	{
		$survey=$this->dataset_manager->get_row($id);

		if (!$survey){
			show_error('Survey was not found');
		}

		$this->acl_manager->has_access_or_die('study', 'edit',null,$survey['repositoryid']);

		$this->load->model("Data_file_model");

		$options=array(
			'survey'=>$survey,
			'files'=>$this->Data_file_model->get_all_by_survey($id)
		);

		//render
		$this->template->set_template('admin5');
		$content= $this->load->view('metadata_editor/microdata_files',$options,true);
		$this->template->write('content', $content,true);
		$this->template->render();
	}

	function edit($id=null)
	{
		$survey=$this->dataset_manager->get_row($id);

		if (!$survey){
			show_error('Survey was not found');
		}

		$this->acl_manager->has_access_or_die('study', 'edit',null,$survey['repositoryid']);
				 
		$template_file="{$survey['type']}_form_template.json";
		$template_path=null;
		
		//locations to look for templates
		$template_locations=array(
			'application/metadata_editor_templates/custom',
			'application/views/metadata_editor/metadata_editor_templates',
		);

		//look for template in all locations and pick the first one found
		foreach($template_locations as $path){
			if (file_exists($path.'/'.$template_file)){
				$template_path=$path.'/'.$template_file;
				break;
			}
		}
		
		//$template_path="application/metadata_editor_templates/{$survey['type']}_form_template.json";
		$schema_path="application/schemas/{$survey['type']}-schema.json";

		if(!file_exists($template_path)){
			show_error('Template not found::'. $template_path);
		}

		if(!file_exists($schema_path)){
			show_error('Schema not found::'. $schema_path);
		}

		$metadata_subset=array(
			'repositoryid'=>$survey['repositoryid'],
			'access_policy'=>$survey['data_access_type'],
			'published'=>$survey['published']			
		);
		

		$metadata=$this->dataset_manager->get_metadata($id);

		$options['sid']=$id;
		$options['survey']=$survey;
		$options['type']=$survey['type'];		

		if (!empty($metadata)){
			$options['metadata']=$this->dataset_manager->get_metadata($id);//array_merge($metadata_subset,$this->dataset_manager->get_metadata($id));
		}else{
			$options['metadata']=null;
		}

		if($survey['type']=='geospatial'){
			//show_error('GEOSPATIAL-TYPE-NOT-SUPPORTED');
		}

		//fix schema elements with mixed types
		if ($survey['type']=='survey'){
			//coll_mode
			$coll_mode=array_data_get($options['metadata'], 'study_desc.method.data_collection.coll_mode');
			if(!empty($coll_mode) && !is_array($coll_mode)){
				set_array_nested_value($options['metadata'],'study_desc.method.data_collection.coll_mode',(array)$coll_mode,'.');
			}
		}


		$options['metadata_template']=file_get_contents($template_path);
		$options['metadata_template_arr']=json_decode($options['metadata_template'],true);
		$options['metadata_schema']=file_get_contents($schema_path);
		$options['post_url']=site_url('api/datasets/update/'.$survey['type'].'/'.$survey['idno']);
		//$options['metadata']=array();
		$options['metadata']['merge_options']='replace';
		$options['sub_section']=$this->uri->segment(6);

		//var_dump($options['sub_section']);
				
		//render
		//$this->template->set_template('admin5');
		$content= $this->load->view('metadata_editor/index_vuetify',$options,true);
		echo $content;
		//$this->template->write('content', $content,true);
		//$this->template->render();
	}
	
}
/* End of file catalog.php */
/* Location: ./controllers/admin/catalog.php */
