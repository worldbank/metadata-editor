<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Metadata Template manager
 *
 */
class Metadata_template {
   
   var $CI;
   var $template;
   var $config;
   
      
   private $survey_type;
   private $metadata;
   private $view_path;
   
 
   /**
	 * Constructor
	 *
	 * Loads template configuration, template regions, and validates existence of 
	 * default template
	 *
	 * @access	public
	 */
   function __construct()
   {
	  
   }
   

   /**
	*
	* 
	* @type - survey, timeseries, geospatial
	**/ 
   function initialize($type, $metadata, $template=null)
   {	  
	  $this->metadata=$metadata;
	  $this->survey_type=$type;	  
	  
	  // Copy an instance of CI so we can use the entire framework.
	  $this->CI =& get_instance();
			  
	  // Load the template config file and setup our master template and regions
	  include(APPPATH.'config/metadata_template.php');
	  
	  if (!isset($config)){		
		 throw new Exception("config/metadata_template not loaded");
	  }
	  	  
	  $this->config = $config;

	  if (!isset($config[$type]) || !isset($config[$type]['template'])){
		 throw new Exception("METADATA_VIEW_NOT_DEFINED: ".$type);
	  }
	  
	  if ($template){
		$this->view_path=$template;
	  }else{
	  	$this->view_path=$config[$type]['template'];
	  }

	  //language file
	  $lang_file=$config[$type]['language_translations'];
	  
	  //language e.g. english,french...
	  $lang_language=$this->CI->config->item("language");

	  $lang_file_path=APPPATH.'/language/'.$lang_language.'/'.$lang_file.'_lang.php';

	  //load language file
	  if (file_exists($lang_file_path)){
	  	$this->CI->lang->load($lang_file);
	  }
	  else{
		log_message('error', "missing language file {$lang_file_path}");
	  }
	  
	  if (!file_exists(APPPATH.'/views/'.$this->view_path.'.php')){
		 throw new exception ("METADATA_VIEW_NOT_FOUND: ".$this->view_path);	 
	  }
   }
   
   /*
   
   //load the metadata template from configurations
   private function load_template($survey_type, $metadata_format)
   {
	  //load the JSON metadata template
	  $this->metadata_template=$this->get_metadata_template($survey_type, $metadata_format);	  
	  
	  //xpath mappings to simpler names
	  $this->mappings=(array)$this->metadata_template->xpath_map;
	  
	  //fields definition
	  // field->type
	  // field->template
	  // field->columns when field type is array
	  $this->fields=$this->metadata_template->fields;
	  
	  //sections, items
	  $this->layout=$this->metadata_template->layout;
   }
   */
   
   //flatten the structure into key/value pairs
   function flatten_schema($schema,$parent_name='',$output=array())
   {
		foreach($schema->properties as $key=>$prop)
		{
			$key_name=$key;
			if($parent_name!==''){
				$key_name=$parent_name.'/'.$key;
			}
			print $key_name."\r\n";

			$output[]=$key_name;

			if ($prop->type=='object'){                                            
				$output=$this->schema_properties($prop,$key_name,$output);
			}
		}

		return $output;
	}
   
   
   public function get_metadata_template($survey_type, $metadata_format)
   {
	  if (!$this->config){
		 throw new Exception("Metadata template config is not loaded");
	  }
	  
	  //get all templates by the survey type
	  $formats=$this->config[$survey_type]['template'];
	  
	  if (array_key_exists($metadata_format,$formats))
	  {
		 $template_path= $formats[$metadata_format];
		 
		 if (!file_exists($template_path)){
			throw new Exception("TEMPLATE_FILE_MISSING: $template_path");
		 }
		 
		 return json_decode(file_get_contents($template_path));
	  }
	  
	  throw new Exception("FORMAT_NOT_FOUND: $metadata_format");	  
   }
   
   
   private function apply_mappings(){}

   
   
   public function render_html()
   {
	  //load helper
	  $this->CI->load->helper('metadata_view_helper');
	  
	  //render the view
	  return $this->CI->load->view($this->view_path,array('metadata'=>$this->metadata),TRUE);
   }
   
   /**
	* 
	* Render selected sections only
	*
    */
   public function render_section_html($sections)
   {
	  $this->CI->load->helper('metadata_view_helper');
	  return $this->CI->load->view($this->view_path,array('metadata'=>$this->metadata, 'sections'=>(array)$sections),TRUE);	
   }
   
   
   
   private function render_node($node,$section)
   {	  
	  $output=NULL;
	  $output['items']=array();
	  $output['sections']=array();
	  	  
	  //items
	  if (isset($node->items)){
		 foreach($node->items as $item)
		 {
			$html=$this->render_field($item);
			//if ($html!=""){
			   $output['items'][]=$html;
			//}
		 }
	  }
	  
	  //sections
	  if (isset($node->sections)){
		 foreach($node->sections as $section_name=>$section_node)
		 {
			$section_output=$this->render_node($section_node,$section_name);
			//if ($section_output!=""){
			   $output['sections'][]=$section_output;
			//}
		 }
	  }
	  
	  $html_output=implode("",$output['items']);
	  $html_output.=implode("", $output['sections']);
	  
	  return $html_output;
   }
   
   private function render_field($name)
   {
	  //load field info
	  $field=$this->get_field_type($name);
	  $field->name=$name;
	  
	  //load field value
	  $value=$this->get_field_value($name);
	  
	  if (!$value)
	  {
		 return NULL;
	  }
	  
	  //field template
	  $view_template='metadata_templates/fields/field_'.$field->type;
	  
	  //if field has its own template set, use that
	  if (isset($field->template))
	  {
		 $view_template=$field->template;
	  }
	  
	  //render the view
	  return $this->CI->load->view($view_template, array('field'=>$field, 'data'=>$value), TRUE);
   }
   
   
   private function get_field_type($name)
   {
	  $field=@$this->fields->{$name};
	  
	  if (!$field)
	  {
		 throw new Exception("FIELD_NOT_DEFINED $name");
	  }
	  
	  return $field;
   }
   
   private function get_field_value($name)
   {
	  //search name in the mappings to findout the xpath key
	  $xpath = array_search($name, $this->mappings);
	  	  
	  if ($xpath){
		 return $this->get_metadata_by_xpath($xpath);
	  }
   }
   
   
   private function get_metadata_by_xpath($xpath)
   {
	  if (array_key_exists($xpath, $this->metadata))
	  {
		 $metadata_value= $this->metadata[$xpath];
		 return $metadata_value;
	  }
   }
   
   
}
// END Template Class

/* End of file Template.php */
/* Location: ./system/application/libraries/Template.php */