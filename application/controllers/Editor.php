<?php
/**
 * Metadata editor
 *
 *
 */
class Editor extends MY_Controller {

	var $active_repo=NULL; //active repo object

  	public function __construct()
	{
      	parent::__construct();
		$this->load->model('Editor_model');
		$this->load->model('Editor_template_model');
		$this->load->library("Editor_acl");
	}

	function index()
	{
		$this->template->set_template('default');
		echo $this->load->view('project/home',$options=array(),true);

		//$this->template->write('content', $content,true);
		//$this->template->render();
	}

	function edit($id=null)
	{
		try{

			$project=$this->Editor_model->get_row($id);

			if (!$project){
				show_error('Project was not found');
			}

			$this->editor_acl->user_has_project_access($project['id'],$permission='edit');		
					
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

			$options['metadata_schema']=file_get_contents($schema_path);
			$options['post_url']=site_url('api/editor/update/'.$project['type'].'/'.$project['id']);
			$options['sub_section']=$this->uri->segment(6);

					
			//render
			$content= $this->load->view('metadata_editor/index_vuetify',$options,true);
			echo $content;
		}
		catch(Exception $e){
			show_error($e->getMessage());
		}
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
		$user_template=$this->Editor_template_model->get_template_by_uid($uid);

		if(!$user_template){
			show_error("Template not found");
		}

		$core_templates=$this->Editor_template_model->get_core_template_by_data_type($user_template['data_type']);

		if (!$core_templates){
			throw new Exception("No system templates found for type: " . $user_template['data_type']);
		}

		$core_template=$this->Editor_template_model->get_template_by_uid($core_templates[0]["uid"]);

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
