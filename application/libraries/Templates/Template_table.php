<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class Template_table
{

    private $ci;
    private $schema_items;

    function __construct()
	{
		log_message('debug', "Template_table Class Initialized.");
		$this->ci =& get_instance();		
        $this->ci->load->model("Editor_template_model");
	}


    

    function template_to_array($template_uid)
    {
        $template=$this->ci->Editor_template_model->get_template_by_uid($template_uid);

        if (!$template){
            throw new Exception("TEMPLATE_NOT_FOUND");
        }
        
        if (isset($template['template'])){
            $template=$template['template'];
        }

        if (!isset($template['items'])){
            throw new Exception("TEMPLATE_ITEMS_NOT_FOUND");
        }

        $json_schema_path = 'application/schemas/timeseries-schema.json';
        $this->schema_items=$this->json_schema_to_array($json_schema_path);

        $array=$this->template_to_array_recursive($template['items']);
        return $array;
    }

    function template_to_array_recursive($template)
    {
        $array=[];
        foreach ($template as $key=>$item){
            
            if (!isset($item['type'])){
                $item['type']='string';
            }

            //section container
            if ($item['type']=='section_container'){

                $array=array_merge($array, $this->template_to_array_recursive($item['items']));
                continue;
            }

            //section
            if ($item['type']=='section'){
                if (isset($item['items'])){
                    $array=array_merge($array, $this->template_to_array_recursive($item['items']));
                }
                
                if (isset($item['props'])){
                    $array=array_merge($array, $this->template_to_array_recursive($item['props']));
                }

                //$array=array_merge($array, $this->template_to_array_recursive($item['items']));
                continue;
            }

            //nested_array
            if ($item['type']=='nested_array'){
                $array=array_merge($array, $this->template_to_array_recursive($item['props']));
                continue;
            }

            //array
            if ($item['type']=='array'){
                $array[$this->convert_key($item['key'])]=[
                    'type'=>isset($item['type']) ? $item['type'] : 'string',
                    'title'=>isset($item['title']) ? $item['title'] : '',
                    'description'=>isset($item['help_text']) ? $item['help_text'] : '',
                    'parent'=>str_replace(".", "/", $this->extract_parent_key($item['key']))
                ];
                
                //array with no props 
                if (!isset($item['props'])){
                    continue;
                }

                $tmp_=$this->template_to_array_recursive($item['props']);
                foreach($tmp_ as $k=>$v){
                    if (!isset($array[$k])){
                        $array[$k]=$v;
                    }                    
                }
                continue;
            }

            $new_key=isset($item['prop_key']) ? $item['prop_key'] : $item['key'];
            $parent_key=str_replace(".", "/", $this->extract_parent_key($new_key));

            //check if parent item exists
            if (!isset($array[$parent_key])){
                //get parent info from schema
                if (isset($this->schema_items[$parent_key])){                    
                    $array[$this->convert_key($parent_key)]=$this->schema_items[$parent_key];
                }
                else{
                    $array[$this->convert_key($parent_key)]=[
                        'type'=>'object',
                        'title'=>$parent_key,
                        'description'=>'NOT-AVAILABLE',
                        'parent'=>''
                    ];
                }
            }

            $array[$this->convert_key($new_key)]=[
                'type'=>isset($item['type']) ? $item['type'] : 'string',
                'title'=>isset($item['title']) ? $item['title'] : '',
                'description'=>isset($item['help_text']) ? $item['help_text'] : '',
                'parent'=>str_replace("/", "__", $parent_key)
            ];

            if (isset($item['enum'])){
                $array[$this->convert_key($new_key)]['enum']=$item['enum'];
            }

            if (isset($value['properties'])){
                $array=array_merge($array, $this->template_to_array_recursive($value['properties'], $new_key, $key));
            }

            if (isset($value['items'])){
                $array=array_merge($array, $this->template_to_array_recursive($value['items'], $new_key, $key));
            }
        }

        return $array;
    }



    /**
     * 
     * Extract parent key from full path
     * 
     */
    function extract_parent_key($key)
    {
        $parts=explode(".", $key);
        $parent_key='';
        if (count($parts)>1){
            $parent_key=implode(".", array_slice($parts, 0, -1));
        }
        return $parent_key;
    }

    /**
     * 
     * Convert key to schema format
     * 
     */
    function convert_key($key, $search='.', $replace='/'){
        return str_replace($search, $replace, $key);
    }


    function json_schema_to_array($json_schema_path)
    {
        $json_schema = json_decode(file_get_contents($json_schema_path));                
        $array = $this->json_schema_to_array_recursive($json_schema);
        return $array;
    }
    
    function json_schema_to_array_recursive($json_schema, $parent_key = '', $parent_element_id='')
    {
        $array = array();
        if (isset($json_schema->properties))
        {
            foreach ($json_schema->properties as $key => $value)
            {
                //create a flat array using parent key and child key
                $new_key = $parent_key . ($parent_key ? '/' : '') . $key;
                $array[$new_key] = [
                    'type' => isset($value->type) ? $value->type : 'string',
                    'title' => isset($value->title) ? $value->title : '',
                    'description' => isset($value->description) ? $value->description : '',
                    'parent' => str_replace("/", "__", $parent_key)
                ];

                //enum?
                if (isset($value->enum))
                {
                    $array[$new_key]['enum'] = $value->enum;
                }

                //if the child has properties, call the function recursively
                if (isset($value->properties))
                {
                    $array = array_merge($array, $this->json_schema_to_array_recursive($value, $new_key, $key));
                }

                if (isset($value->items))
                {
                    $array = array_merge($array, $this->json_schema_to_array_recursive($value->items, $new_key, $key));
                }
            }
        }
        return $array;

        
    }
        
}