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
    public $pdf_mode = false;
	
	function __construct()
	{
        $this->ci =& get_instance();
        $this->ci->load->helper("array");
    }

    function initialize($metadata,$template,$pdf_mode=false)
    {
        $this->metadata=$metadata;
        $this->template=$template;
        $this->pdf_mode = (bool)$pdf_mode;
    }

    function render_html()
    {
        echo $this->render_element($this->template['items']);
    }

    private function render_element($items)
    {
        $output=array();

        foreach($items as $idx=>$item){            
            $item_type = isset($item['type']) ? $item['type'] : 'string';
            switch($item_type)
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
                case 'number':
                case 'date':
                case 'textarea':
                case 'dropdown':
                case 'dropdown-custom':
                    $output[]= $this->render_text($item);
                    break;

                default:
                    // Display widgets and unknown scalar types must not abort PDF/HTML export
                    $output[]= $this->render_text($item);
                    break;
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

        return $this->ci->load->view('project_preview/fields/field_array_accordion',array(
            'data'=>$value,
            'template'=>$item,
            'pdf_mode'=>$this->pdf_mode
        ),true);
    }

    private function render_array($item)
    {
        $value=array_data_get($this->metadata, $this->get_metadata_dot_key($item['key']));
        
        if (!$value){
            return false;
        }

        return $this->ci->load->view('project_preview/fields/field_array',array(
            'data'=>$value,
            'template'=>$item,
            'pdf_mode'=>$this->pdf_mode
        ),true);
    }

    private function render_simple_array($item)
    {
        $value=array_data_get($this->metadata, $this->get_metadata_dot_key($item['key']));
        
        if (!$value){
            return false;
        }

        return $this->ci->load->view('project_preview/fields/field_simple_array',array(
            'data'=>$value,
            'template'=>$item,
            'pdf_mode'=>$this->pdf_mode
        ),true);
    }
    
    private function render_text($item)
    {
        $value=array_data_get($this->metadata, $this->get_metadata_dot_key($item['key']));

        if (!$value){
            return false;
        }

        return $this->ci->load->view('project_preview/fields/field_text',array(
            'data'=>$value,
            'template'=>$item,
            'pdf_mode'=>$this->pdf_mode
        ),true);
    }


    function get_metadata_dot_key($key)
    {
        return 'metadata.'.str_replace("/",".",$key);
    }

    
}