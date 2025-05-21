<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;
use Ramsey\Uuid\Uuid;


class MetadataSetReport
{

    private $ci;
    private $schema_items=[];


    function __construct()
	{
		log_message('debug', "MetadataSetReport Class Initialized.");
		$this->ci =& get_instance();		
        $this->ci->load->model("Editor_model");
	}


    /**
     * 
     * Generate SDMX 3.0 Metadataset Report
     * 
     */
    function json($sid)
    {
        $metadata=$this->ci->Editor_model->get_row($sid);

        $report=[];
        $report['meta']=[
            "schema"=>"https://raw.githubusercontent.com/sdmx-twg/sdmx-json/master/metadata-message/tools/schemas/2.0.0/sdmx-json-metadata-schema.json",
            "id"=>$metadata['idno'],
            "test"=>false,
            "prepared"=> date('Y-m-d\TH:i:s\Z'),   // "2021-08-20T08:00:00-05:00",
            "contentLanguages"=>[
                "en"
            ],
            'sender'=>[
                'id'=>'SDMX',
                'name'=>'SDMX',
                'contact'=>[
                    'name'=>'SDMX',
                    'email'=>''
                ]
            ],

        ];
        $report['data']['metadatasets'][]=$this->recursive_metadataset_attributes($metadata['metadata']);
        return $report;
    }


    private function recursive_metadataset_attributes($metadata, $parent_key=null)
    {
        $output=[];
        foreach($metadata as $key=>$value){

            //check if value is not associative array
            //is not associative array
            //if (is_int($key)){                
                /*$attributes=$this->recursive_metadataset_attributes($value);
                $output['attributes'][]=array(
                    'idx'=>$key,
                    'attributes'=>$attributes['attributes']
                );*/
                /*$attributes=$this->array_type_metadata_attributes($value, $parent_key);
                $output['attributes'][]=array(
                    'id'=>$parent_key,
                    'attributes'=>$attributes['attributes']
                );*/
                
            //}
            if (is_array($value)){

                //is not associative array
                $value_keys=array_keys($value);
                if (is_int($value_keys[0])){
                    $attributes=$this->array_type_metadata_attributes($value, $key);
                    foreach($attributes['attributes'] as $attr){
                        $output['attributes'][]=array(
                            'id'=>$key,
                            'attributes'=>$attr['attributes']
                        );
                    }
                    /*$output['attributes'][]=array(
                        'id__'=>$key,
                        'attributes'=>$attributes['attributes']
                    );*/
                }
                else{
                    $attributes=$this->recursive_metadataset_attributes($value, $key);
                    $output['attributes'][]=array(
                        'id'=>$key,
                        'attributes'=>$attributes['attributes']
                    ); 
                }


                /*$attributes=$this->recursive_metadataset_attributes($value, $key);
                $output['attributes'][]=array(
                    'id'=>$key,
                    'attributes'=>$attributes['attributes']
                );*/ 
            }
            else{
                $output['attributes'][]=array(
                    'id'=>$key,
                    'value'=>$value
                );
            }
        }

        return $output;
    }

    //for array type metadata attributes
    private function array_type_metadata_attributes($metadata, $metadata_key){
        
        /*
        example of repeatable array type metadata attributes

        $metadata=data
        $metadata_key=CONTACT

        "attributes":[
					{
						"id":"CONTACT",
						"attributes":[
							{
								"id":"ORGANISATION",
								"value":"Eurostat, the statistical office of the European Union"
							}
                        ]
                    },
                    {
                        "id":"CONTACT",
                        "attributes":[
                            {
                                "id":"ORGANISATION",
                                "value":"Eurostat, the statistical office of the European Union"
                            }
                        ]
                    }
                ]
        */
        
        $output=[];
        foreach($metadata as $key=>$value){
            $attributes=$this->recursive_metadataset_attributes($value);
            $output['attributes'][]=array(
                'id'=>$metadata_key,
                'attributes'=>$attributes['attributes']
            ); 
        }

        return $output;

    }


    function validate_schema($data)
	{
		$schema_file="application/schemas/sdmx-json-metadata-schema.json";

		if(!file_exists($schema_file)){
			throw new Exception("INVALID-DATASET-TYPE-NO-SCHEMA-DEFINED");
		}

		// Validate
		$validator = new JsonSchema\Validator;
		$validator->validate($data, 
				(object)['$ref' => 'file://' . unix_path(realpath($schema_file))],
				Constraint::CHECK_MODE_TYPE_CAST 
				+ Constraint::CHECK_MODE_COERCE_TYPES 
				+ Constraint::CHECK_MODE_APPLY_DEFAULTS
			);

		if ($validator->isValid()) {
			return true;
		} else {			
			/*foreach ($validator->getErrors() as $error) {
				echo sprintf("[%s] %s\n", $error['property'], $error['message']);
			}*/

//			var_dump($validator->getErrors());

			throw new ValidationException("SCHEMA_VALIDATION_FAILED: ", $validator->getErrors());
		}
	}

}