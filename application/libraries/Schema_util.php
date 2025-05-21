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



    /**
     * 
     * 
     * Return schema version and ID
     * 
     *  - schema ID: $id
     *  - Schema version: version
     * 
     * 
     * 
     */
    function get_schema_version_info($schema_name)
    {
        $schemas=array(
            'survey'=>'survey',
            'microdata'=>'survey',
            'table'=>'table',
            'document' => 'document',
            'geospatial'=>'geospatial',
            'image' => 'image',
            'timeseries' => 'timeseries',
            'timeseries-db' =>  'timeseries-db',
            'resource' => 'resource',
            'video' => 'video',
            'script' => 'script'
        );

        if(!in_array($schema_name,array_keys($schemas))){
            throw new Exception("INVALID_SCHEMA: ".$schema_name.". Supported schemas are:". implode(", " , $schemas));
        }

        $schema_file="application/schemas/".$schemas[$schema_name]."-schema.json";

		if(!file_exists($schema_file)){
			throw new Exception("SCHEMA-NOT-FOUND: ".$schema_file);
        }

        $schema_file_path='file://' .unix_path(realpath($schema_file));

        //read schema file
        $schema_json = file_get_contents($schema_file_path);

        $schema = json_decode($schema_json);

        //return schema version and id
        return array(
            'version'=>isset($schema->version) ? $schema->version : '0.0.1',
            '$id'=>$schema->{'$id'}            
        );

    }

}//end-class