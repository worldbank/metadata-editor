<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * 
 * 
 * Generate html preview for the project
 *
 *
 */ 
class Pagepreview{
    
    private $ci;
    private $metadata;
    private $template;
	
	function __construct()
	{
        $this->ci =& get_instance();
        $this->ci->load->helper("array");
        $this->ci->load->model("Editor_template_model");
    }

    function initialize($metadata,$template)
    {
        $this->metadata=$metadata;
        $this->template=$template;
    }

    function render_html()
    {
        echo $this->render_element($this->template['items']);
    }

    private function render_element($items)
    {
        $output=array();

        foreach($items as $idx=>$item){            
            switch($item['type'])
            {
                case 'section_container':
                    $output[]= $this->render_section_container($item);
                    break;
                case 'section':
                    $output[]= $this->render_section($item);
                    break;
                case 'nested_array':
                    $output[]= $this->render_nested_array($item);
                    break;
                case 'array':
                    $output[]= $this->render_array($item);
                    break;
                case 'simple_array':
                    $output[]= $this->render_simple_array($item);
                    break;
                case 'text':
                case 'string':
                case 'boolean':
                case 'integer':
                    $output[]= $this->render_text($item);
                    break;

                default:
                    throw new Exception("not supported: ". $item['type']);
            }
        }

        return implode("", $output);
    }


    private function render_section_container($item){
        $output=array();
        $output[]='<div id="'.html_escape($item['key']).'">';
        $output[]='<h1 class="field-section-container mt-3" >'.html_escape($item['title']).'</h1>';

        if (isset($item['items'])){
            $el_html=$this->render_element($item['items']);
            if(empty($el_html)){
                return false;
            }
            $output[]=$el_html;
        }
        
        $output[]='</div>';        
        return implode("",$output);
    }
    
    private function render_section($item)
    {
        $output=array();
        $item_key=isset($item['prop_key']) ? $item['prop_key'] : $item['key'];
        $output[]='<div id="'.html_escape($item_key).'">';
        $output[]='<h2 class="field-section mt-3">'.html_escape($item['title']).'</h2>';

        if (isset($item['items'])){
            $el_html=$this->render_element($item['items']);
            if(empty($el_html)){
                return false;
            }
            $output[]=$el_html;
        }
        
        $output[]='</div>';        
        return implode("",$output);
    }
    
    private function render_nested_array($item)
    {
        $value=array_data_get($this->metadata, $this->get_metadata_dot_key($item['key']));
        
        if (!$value){
            return false;
        }

        return $this->ci->load->view('project_preview/fields/field_array_accordion',array('data'=>$value,'template'=>$item),true);
    }

    private function render_array($item)
    {
        $value=array_data_get($this->metadata, $this->get_metadata_dot_key($item['key']));
        
        if (!$value){
            return false;
        }

        return $this->ci->load->view('project_preview/fields/field_array',array('data'=>$value,'template'=>$item),true);
    }

    private function render_simple_array($item)
    {
        $value=array_data_get($this->metadata, $this->get_metadata_dot_key($item['key']));
        
        if (!$value){
            return false;
        }

        return $this->ci->load->view('project_preview/fields/field_simple_array',array('data'=>$value,'template'=>$item),true);
    }
    
    private function render_text($item)
    {
        $value=array_data_get($this->metadata, $this->get_metadata_dot_key($item['key']));

        if (!$value){
            return false;
        }

        return $this->ci->load->view('project_preview/fields/field_text',array('data'=>$value,'template'=>$item),true);
    }


    function get_metadata_dot_key($key)
    {
        return 'metadata.'.str_replace("/",".",$key);
    }


    function get_template_project_type($type)
	{
		/*$user_template=$this->Editor_template_model->get_template_by_uid($uid);

		if(!$user_template){
			show_error("Template not found");
		}

		return $user_template;*/

        $template_file_name='application/templates/display/'.$type.'_display_template.json';

		if (file_exists($template_file_name)){
			$template['template']=json_decode(file_get_contents($template_file_name),true);
			return $template;
		}

		$core_templates=$this->ci->Editor_template_model->get_core_templates_by_type($type);

		if (!$core_templates){
			throw new Exception("No system templates found for type: "); 
		}

		//var_dump($core_templates);
		//die();

		$core_template=$this->ci->Editor_template_model->get_template_by_uid($core_templates[0]["uid"]);

		return $core_template;
	}

    
}