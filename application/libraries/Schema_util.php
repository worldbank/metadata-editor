<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Uri\UriResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;

/**
 *
 * JSON Schema helper class
 * 
 *
 */ 
class Schema_util
{
	/**
	 * Constructor
	 */
	function __construct()
	{
        $this->ci =& get_instance();
        $this->ci->load->helper('array');
		log_message('debug', "Schema_validator Class Initialized.");
		//$this->ci =& get_instance();
	}


    function schema_properties($schema,$parent=null,&$output=array())
    {
        if(isset($schema->{'$ref'})){
            $sub_schema=($this->load_schema_ref($schema->{'$ref'}));

            //if($parent_path!=''){
                /*$output[$parent."x"]=array(
                    'type'=>@$sub_schema->type,
                    'title'=>@$sub_schema->title,
                    'description'=>@$sub_schema->description
                );*/
            //}

            $this->schema_properties($sub_schema, $parent,$output);
        }

        if(isset($schema->items)){            
            $this->schema_properties($schema->items, $parent,$output);            
        }

        $props=array('properties','allOf');
        
        foreach($props as $prop_type){
            if(!isset($schema->{$prop_type})){
                continue;
            }

            foreach($schema->{$prop_type} as $key=>$value)
            {
                /*var_dump($prop_type);
                var_dump($key);
                die();*/

                if ($prop_type=='allOf'){

                    if($parent!==null){
                        //echo $parent;

                        if(isset($value->type)){
                            //echo '['.$value->type.']';
                        }
                        //echo "<BR>";
                    }

                    $parent_path=$parent;

                }
                else{
                    //echo $parent."/".$key;

                    if(isset($value->type)){
                        //echo '['.$value->type.']';
                    }
                    //echo "<BR>";

                    $parent_path=$parent.'/'.$key;
                }

                if($parent_path!=''){
                    $output[$parent_path]=array(
                        'type'=>@$value->type,
                        'title'=>@$value->title,
                        'description'=>@$value->description
                    );
                }
                
                
                if(isset($value->{'$ref'})){
                    //echo "FOUNC REF";

                    $sub_schema=($this->load_schema_ref($value->{'$ref'}));
                    //var_dump($sub_schema);
                    //die();
                    $this->schema_properties($sub_schema, $parent_path,$output);
                }

                if(isset($value->{'items'})){
                    $this->schema_properties($value->items, $parent_path,$output);
                }
                
                $this->schema_properties($value,$parent_path,$output);
            }
        }    
    }


    function schema_properties2($schema,$parent=null)
    {

        $props=array('properties','allOf');

        if(!isset($schema->properties)){
            return;
        }

        foreach($schema->properties as $key=>$value)
        {
            echo $parent."/".$key;

            if(isset($value->{'$ref'})){
                echo "FOUNC REF";

                $sub_schema=($this->load_schema_ref($value->{'$ref'}));
                //var_dump($sub_schema);
                //die();
                $this->schema_properties($sub_schema, $parent."/XXXX/".$key);
            }
            
            echo "<BR>";

            if(isset($value->properties))
            {
                $this->schema_properties($value,$parent."/".$key);
            }            
        }
    }

    function load_schema_ref($ref)
    {
        $resolver = new JsonSchema\Uri\UriResolver();
        $retriever = new JsonSchema\Uri\UriRetriever();                
        $refResolver = new JsonSchema\SchemaStorage($retriever, $resolver);
        $schema = $refResolver->resolveRef($ref);
        $options = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
        //return json_encode($schema, $options);
        return $schema;
    }

    function schema_elements($schema,$parent=null,&$output=array())
    {
        if(isset($schema->{'$ref'})){
            $sub_schema=($this->load_schema_ref($schema->{'$ref'}));

            //if($parent_path!=''){
                /*$output[$parent."x"]=array(
                    'type'=>@$sub_schema->type,
                    'title'=>@$sub_schema->title,
                    'description'=>@$sub_schema->description
                );*/
            //}

            $this->schema_elements($sub_schema, $parent,$output);
        }

        if(isset($schema->items)){            
            $output[$parent]['items']=$schema->items;
        }

        $props=array('properties','allOf');
        
        foreach($props as $prop_type){
            if(!isset($schema->{$prop_type})){
                continue;
            }

            foreach($schema->{$prop_type} as $key=>$value)
            {
                if ($prop_type=='allOf'){

                    if($parent!==null){
                        //echo $parent;

                        if(isset($value->type)){
                            //echo '['.$value->type.']';
                        }
                        //echo "<BR>";
                    }

                    $parent_path=$parent;

                }
                else{
                    //echo $parent."/".$key;

                    if(isset($value->type)){
                        //echo '['.$value->type.']';
                    }
                    //echo "<BR>";

                    if ($parent==""){
                        $parent_path=$key;
                    }else{
                        $parent_path=$parent.'/'.$key;
                    }
                    
                }

                if($parent_path!=''){
                    $output[$parent_path]=array(
                        'type'=>@$value->type,
                        'title'=>@$value->title,
                        'description'=>@$value->description,
                        '_ddi_xpath'=>@$value->_ddi_xpath
                    );
                }
                
                
                if(isset($value->{'$ref'})){
                    //echo "FOUNC REF";

                    $sub_schema=($this->load_schema_ref($value->{'$ref'}));
                    //var_dump($sub_schema);
                    //die();
                    $this->schema_elements($sub_schema, $parent_path,$output);
                }

                if(isset($value->{'items'})){
                    //$output[$parent_path]["items"]=$value->items;
                    //$this->schema_elements($value->items, $parent_path,$output);
                }
                
                $this->schema_elements($value,$parent_path,$output);
            }
        }    
    }


    function get_schema_elements($schema_name)
    {
        $schemas=array(
            'survey',
            'table',
            'document',
            'geospatial',
            'image',
            'timeseries',
            'resource',
            'video',
            'script',
            'variable'
        );

        if(!in_array($schema_name,$schemas)){
            throw new Exception("INVALID_SCHEMA: ".$schema_name.". Supported schemas are:". implode(", " , $schemas));
        }

        $schema_file="api-documentation/catalog-admin/".$schema_name."-schema.json";

		if(!file_exists($schema_file)){
			throw new Exception("INVALID-DATASET-TYPE-NO-SCHEMA-DEFINED");
        }

        $schema_file_path='file://' .unix_path(realpath($schema_file));

        $resolver = new JsonSchema\Uri\UriResolver();
        $retriever = new JsonSchema\Uri\UriRetriever();
        
        $urlSchema=$schema_file_path;
        //$urlSchema='file:///Volumes/webdev/nada4/api-documentation/catalog-admin/geospatial-schema.json#/definitions/graphic_overview';
        
        $refResolver = new JsonSchema\SchemaStorage($retriever, $resolver);
        $schema = $refResolver->resolveRef($urlSchema);
        $options = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;

        $output=array();
        $this->schema_elements($schema,"",$output);

        return $output;
    
    }

}//end-class