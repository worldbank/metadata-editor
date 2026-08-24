<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;

/**
 *
 * JSON Schema validator
 * 
 *
 */ 
class Schema_validator
{
	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "Schema_validator Class Initialized.");
		//$this->ci =& get_instance();
	}

    function validate_schema($schema_path,$data)
        {
            if(!file_exists($schema_path)){
                throw new Exception("INVALID-DATASET-TYPE-NO-SCHEMA-DEFINED");
            }

            // Validate
            $validator = new JsonSchema\Validator;
            $validator->validate($data, 
                    (object)['$ref' => 'file://' . unix_path(realpath($schema_path))],
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
                $CI =& get_instance();
                $CI->load->library('Project_validation');
                throw new ValidationException(
                    "SCHEMA_VALIDATION_FAILED [{basename($schema_path)}]: ",
                    Project_validation::filter_redundant_json_schema_errors($validator->getErrors())
                );
            }
        }

    }//end-class