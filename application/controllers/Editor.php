<?php
/**
 * Catalog Maintenance Controller
 *
 * handles all Catalog Maintenance pages
 *
 */
class Editor extends MY_Controller {

	var $active_repo=NULL; //active repo object

  	public function __construct()
	{
      	parent::__construct();
		$this->load->model('Editor_model');
		$this->load->model('Editor_template_model');

     	/*$this->load->model('Catalog_model');
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
		*/
		//$this->template->set_template('admin'); 

		/*
		$this->load->library("Dataset_manager");

		//load language file
		$this->lang->load('general');
		$this->lang->load('catalog_search');
		$this->lang->load('catalog_admin');
		$this->lang->load('permissions');
		$this->lang->load('resource_manager');

		*/
		/*

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
		*/
	}

	function index()
	{
		//$this->template->set_template('admin5');
		echo $this->load->view('metadata_editor/home',$options=array(),true);

		//$this->template->write('content', $content,true);
		//$this->template->render();
	}
	

	function edit($id=null)
	{
		$project=$this->Editor_model->get_row($id);

		if (!$project){
			show_error('Project was not found');
		}

		//$this->acl_manager->has_access_or_die('study', 'edit',null,$survey['repositoryid']);
				 
		$template_file="{$project['type']}_form_template.json";

		$template_path='';
		
		//locations to look for templates
		$template_locations=array(
			'application/views/metadata_editor/metadata_editor_templates/custom',
			'application/views/metadata_editor/metadata_editor_templates',
		);

		//look for template in all locations and pick the first one found
		foreach($template_locations as $path){
			if (file_exists($path.'/'.$template_file)){
				$template_path=$path.'/'.$template_file;
				break;
			}
		}
		
		$schema_path="application/schemas/{$project['type']}-schema.json";

		if(!file_exists($template_path)){
			show_error('Template not found::'. $template_path);
		}

		if(!file_exists($schema_path)){
			show_error('Schema not found::'. $schema_path);
		}

		$options['sid']=$id;
		$options['title']=$project['title'];
		$options['type']=$project['type'];		
		$options['metadata']=$project['metadata'];

		//fix schema elements with mixed types
		if ($project['type']=='survey'){
			//coll_mode
			$coll_mode=array_data_get($options['metadata'], 'study_desc.method.data_collection.coll_mode');
			if(!empty($coll_mode) && !is_array($coll_mode)){
				set_array_nested_value($options['metadata'],'study_desc.method.data_collection.coll_mode',(array)$coll_mode,'.');
			}
		}

		$options['metadata_template']=file_get_contents($template_path);
		$options['metadata_template_arr']=json_decode($options['metadata_template'],true);

		//load template
		if (isset($project['template_uid']) && !empty($project['template_uid'])){
			$template=$this->Editor_template_model->get_template_by_uid($project['template_uid']);
		}
		
		if (empty($template)){
			$core_templates_by_type=$this->Editor_template_model->get_core_templates_by_type($project['type']);
			if (!$core_templates_by_type){
				throw new Exception("Template not found for type", $project['type']);
			}

			//load default core template by type
			$template=$this->Editor_template_model->get_template_by_uid($core_templates_by_type[0]["uid"]);
		}

		$options['metadata_template']=json_encode($template);
		$options['metadata_template_arr']=$template['template'];
/*

		$template_custom_parts=[];
		$this->get_template_custom_parts($options['metadata_template_arr']["items"],$template_custom_parts);
		//var_dump($template_custom_parts);
		//print_r($template_custom_parts);

		//remove elements with is_editable = false
		$custom_sections=[];
		$items_x=$options['metadata_template_arr']["items"];
		$this->get_template_editable_parts($items_x);

*/
/*
		echo '<pre>';
		print_r($template_custom_parts);
		echo "<HR>";
		print_r($items_x);
		die();
*/
		//$user_template=$this->Editor_template_model->get_template_by_uid($uid);


		$options['metadata_schema']=file_get_contents($schema_path);
		$options['post_url']=site_url('api/editor/update/'.$project['type'].'/'.$project['id']);
		$options['sub_section']=$this->uri->segment(6);

				
		//render
		$content= $this->load->view('metadata_editor/index_vuetify',$options,true);
		echo $content;
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
		if ($uid){
			return $this->template_edit($uid);
		}

		echo $this->load->view('metadata_editor/templates_index',$options=array(),true);
	}

	function template_edit($uid)
	{
		$this->template->set_template('blank');
		//echo "edit template" . $uid;

		$user_template=$this->Editor_template_model->get_template_by_uid($uid);

		if(!$user_template){
			show_error("Template not found");
		}

		$core_template=$this->Editor_template_model->get_core_template_json($user_template['data_type']);

		$options=array(
			'user_template_info'=>$user_template,
			'core_template'=>$core_template,
			'user_template'=>$user_template
		);

		unset($options['user_template_info']['template']);

		echo $this->load->view('template_manager/index',$options,true);
	}

	
}
/* End of file metadata_editor.php */
/* Location: ./controllers/admin/metadata_editor.php */
