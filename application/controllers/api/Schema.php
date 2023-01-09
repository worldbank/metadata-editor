<?php

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Uri\UriResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Schema extends MY_REST_Controller {
 
    public function __construct()
    {
        parent::__construct($skip_auth=TRUE);
        $this->load->library("Schema_util");
    }

    
    function fields_get($schema_name)
    {
        try{
			$output=$this->schema_util->get_schema_elements($schema_name);

            $response=array(
                'status'	=> 'success',            
                'fields'	=> $output
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
            $error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);			
		}
    }


    function template_get($schema_name)
    {
        try{
			$output=$this->schema_util->get_schema_template($schema_name);

            $response=array(
                'status'	=> 'success',            
                'fields'	=> $output
            );

            $this->set_response($response, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
            $error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);			
		}
    }


    
 
    
    
}