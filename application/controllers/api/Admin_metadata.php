<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

/**
 * 
 * Admin Metadata
 * 
 */
class Admin_metadata extends MY_REST_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		
		$this->load->model("Editor_model");
        $this->load->model("Metadata_type_model");
        $this->load->model("Metadata_schema_model");
        $this->load->model("Metadata_type_data_model");
        $this->load->model("Metadata_type_acl_model");
		$this->load->library("Editor_acl");		
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
	}

    /**
     * 
     * Get all metadata types
     * 
     */
    function index_get($name_or_id=null)
    {
        return $this->type_get($name_or_id);
    }

    /**
     * 
     * Metadata types
     * 
     */
    function type_get($name_or_id=null)
    {
        try{
                    
            $offset=(int)$this->input->get("offset");
			$limit=(int)$this->input->get("limit");

            if (!$offset){
                $offset=0;
            }

            if (!$limit){
                $limit=10;
            }

            if ($name_or_id){
                if (is_numeric($name_or_id)){
                    $result=$this->Metadata_type_model->select_single_by_id($name_or_id);
                }else{
                    $result=$this->Metadata_type_model->select_single_by_name($name_or_id);
                }

                if (!$result){
                    throw new Exception("NOT FOUND");
                }

                //get permissions
                $result['permissions']=$this->Metadata_type_model->get_user_permissions($result['id'],$this->api_user->id);

            }else{
                $result=$this->Metadata_type_model->select_all($offset,$limit);
            }

            $this->set_response($result, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }


    /**
     * 
     * Get metadata types by user and/or type id/name
     * 
     */
    function type_by_user_get($type_id=null)
    {
        try{
        
            if ($type_id){
                if (is_numeric($type_id)){
                    $meta_type_id=$type_id;
                }else{
                    $meta_type_id=$this->Metadata_type_model->get_id_by_name($type_id);
                }

                $this->editor_acl->user_has_metadata_type_access($meta_type_id,$permission='view',$this->api_user);
                $result=$this->Metadata_type_model->select_single_by_id($meta_type_id);

                if (!$result){
                    throw new Exception("NOT FOUND");
                }

                $result['permissions']=$this->Metadata_type_model->get_user_permissions($result['id'],$this->api_user->id);
            }
            else{
                $result=$this->Metadata_type_model->select_all_by_user($this->api_user->id);

                if (isset($result['result']) && is_array($result['result'])){
                    foreach($result['result'] as $key=>$row){
                        $result['result'][$key]['permissions']=$this->Metadata_type_model->get_user_permissions($row['id'],$this->api_user->id);
                    }
                }
            }

            $this->set_response($result, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }


    /**
     * 
     * Create a new metadata type
     * 
     * 
     */
    function index_post()
    {
        return $this->type_post();
    }
    
    function type_post()
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

			$result=$this->Metadata_type_model->create($options);

            if (!$result){
                throw new Exception("Error creating metadata type");
            }

            $metadata_type=$this->Metadata_type_model->select_single_by_id($result);

			$output=array(
				'status'=>'success',
                'metadata_type'=>$metadata_type,
                '_links'=>array(
                    'metadata_type'=>site_url('api/metadata/type/'.$metadata_type['name'])
                ),
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
        catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>(array)$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		} 
    }

    /**
     * 
     * Create a new metadata type
     * 
     * 
     */
    function type_update_post($metadata_type_id=null)
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

			$result=$this->Metadata_type_model->update($metadata_type_id,$options);

            if (!$result){
                throw new Exception("Error creating metadata type");
            }

			$output=array(
				'status'=>'success',
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
        catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>(array)$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		} 
    }


     /**
     * 
     * Delete type
     * 
     */
    function type_delete_post($type_id)
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

			$result=$this->Metadata_type_model->delete_by_id($type_id);            

			$output=array(
				'status'=>'success',                
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
        catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>(array)$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),				
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}        
    }
    


    /**
     * 
     * Create new schema
     * 
     */
    function schema_post()
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

			$result=$this->Metadata_schema_model->create($options);
            $schema_idno=$this->Metadata_schema_model->get_urn_by_id($result);

			$output=array(
				'status'=>'success',
                'schema_idno'=>$schema_idno,
                '_links'=>array(
                    'schema'=>site_url('api/metadata/schema/'.$schema_idno)
                ),
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
        catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>(array)$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),				
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}        
    }

    /**
     * 
     * Update schema
     * 
     */
    function schema_update_post($schema_id)
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

			$result=$this->Metadata_schema_model->update($schema_id,$options);
            $schema_idno=$this->Metadata_schema_model->get_urn_by_id($result);

			$output=array(
				'status'=>'success',
                'schema_idno'=>$schema_idno,
                '_links'=>array(
                    'schema'=>site_url('api/metadata/schema/'.$schema_idno)
                ),
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
        catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>(array)$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),				
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}        
    }



    /**
     * 
     * Delete schema
     * 
     */
    function schema_delete_post($schema_id)
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

			$result=$this->Metadata_schema_model->delete_by_id($schema_id);            

			$output=array(
				'status'=>'success',                
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),				
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}        
    }


    /**
     * 
     * 
     * Return all schemas
     * 
     * 
     */
    function schemas_get($schema_idno=null)
    {
        try{
            if ($schema_idno){                
                return $this->schema_get($schema_idno);
            }

            $result=$this->Metadata_schema_model->select_all();
            
            if (!$result){
                throw new Exception("NOT FOUND");
            }

            $this->set_response($result, REST_Controller::HTTP_OK);		
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 
     * 
     * Return Metadata type schema definition
     * 
     * 
     */
    function schema_get($schema_idno=null)
    {
        try{
            $result=$this->Metadata_schema_model->select_single_by_urn($schema_idno);
            
            if (!$result){
                throw new Exception("NOT FOUND");
            }

            if (!isset($result['schema'])){
                throw new Exception("Schema definition is missing!");
            }            

            $this->set_response($result['schema'], REST_Controller::HTTP_OK);		
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }


    function schema_by_id_get($schema_id)
    {
        try{
            $result=$this->Metadata_schema_model->select_single_by_id($schema_id);
            
            if (!$result){
                throw new Exception("NOT FOUND");
            }

            if (!isset($result['schema'])){
                throw new Exception("Schema definition is missing!");
            }
            
            $output=array(
				'status'=>'success',
                'result'=>$result
			);

            $this->set_response($result, REST_Controller::HTTP_OK);		
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }
    }



    /**
     * 
     * 
     * Get metadata (data) by project id and/or metadata type name
     * 
     * 
     */
    function data_get($project_id, $metadata_type_name=null)
    {
        try{
            $project_id=$this->get_sid($project_id);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $output_format=$this->input->get('output_format');

            if (!in_array($output_format, array('raw','metadata'))){
                $output_format='raw';
            }

            $metadata_type_id=null;

            if ($metadata_type_name){
                $metadata_type_id=$this->Metadata_type_model->get_id_by_name($metadata_type_name);
                $this->editor_acl->user_has_metadata_type_access($metadata_type_id,$permission='view',$this->api_user);
                $result=$this->Metadata_type_data_model->select_single($metadata_type_id,$project_id);
            }else{
                $result=$this->Metadata_type_data_model->get_project_metadata($project_id, $metadata_type_id, $output_format, $this->api_user->id);
            }
            
            if (!$result){
                throw new Exception("NO Metadata found");
            }

            $this->set_response($result, REST_Controller::HTTP_OK);
        }
        catch(Exception $e){
            $error_response=array(
                'status'=>'error',
                'message'=>$e->getMessage()
            );
            $this->set_response($error_response, REST_Controller::HTTP_BAD_REQUEST);
        }

    }


    function data_post()
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

            if (!isset($options['project_id'])){
                throw new Exception("Project ID is required");
            }

            if (!isset($options['metadata_type_name'])){
                throw new Exception("Metadata type name 'metadata_type_name'  is required");
            }

            $project_id=$this->get_sid($options['project_id']);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $metadata_type_id=$this->Metadata_type_model->get_id_by_name($options['metadata_type_name']);
            $this->editor_acl->user_has_metadata_type_access($metadata_type_id,$permission='edit',$this->api_user);

            //metadata
            if (!isset($options['metadata'])){
                throw new Exception("Metadata is missing!");
            }

			$result=$this->Metadata_type_data_model->upsert($metadata_type_id, $project_id,$options);

			$output=array(
				'status'=>'success',
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}      
    }


    function data_remove_post()
    {
        try{
			$options=$this->raw_json_input();
            $options['user_id']=$this->api_user->id;

            if (!isset($options['project_id'])){
                throw new Exception("Project ID is required");
            }

            if (!isset($options['metadata_type_name'])){
                throw new Exception("Metadata type name 'metadata_type_name'  is required");
            }

            $project_id=$this->get_sid($options['project_id']);

            if (!$project_id){
                throw new Exception("Project not found");
            }

            $metadata_type_id=$this->Metadata_type_model->get_id_by_name($options['metadata_type_name']);

			$result=$this->Metadata_type_data_model->delete($metadata_type_id, $project_id);            

			$output=array(
				'status'=>'success',
                'result'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}      
    }


    //Metadata types ACL

    /**
	 * 
	 * Share Admin Metadata with user
	 * 
	 * @options JSON array
	 * [
	 * 	{
	 * 		"metadata_type_id": "metadata_type_id",
	 * 		"user_id": "user id",
	 * 		"permissions": "view|edit|admin"
	 * 	}
	 * ]
	 * 
	 */
	function share_post()
	{		
		try{
			$this->has_access($resource_='admin_metadata',$privilege='admin');
			$options=$this->raw_json_input();

			if (!is_array($options)){
				throw new Exception("Invalid input: must be an array");
			}

			foreach($options as $option){
				if (!isset($option['metadata_type_id'])){
					throw new Exception("Missing parameter: metadata_type_id");
				}

				//$this->editor_acl->user_has_metadata_type_access($option['metadata_type_id'],$permission='admin',$this->api_user);
                $this->Metadata_type_acl_model->add_user($option['metadata_type_id'],$option['user_id'],$option['permissions']);
			}

			$output=array(
				'status'=>'success',
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function share_get($metadata_type_id=null)
	{
		try{
			$this->has_access($resource_='admin_metadata',$privilege='view');

            if (!$metadata_type_id){
                throw new Exception("Missing parameter: metadata_type_id");
            }

			$result=$this->Metadata_type_acl_model->list_users($metadata_type_id);
				
			$response=array(
				'status'=>'success',
				'users'=>$result
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


	function remove_access_post()
	{
		try{
            $this->has_access($resource_='admin_metadata',$privilege='admin');
			$options=$this->raw_json_input();

			if (!isset($options['metadata_type_id'])){
				throw new Exception("Missing parameter: metadata_type_id");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

            $result=$this->Metadata_type_acl_model->remove_user($options['metadata_type_id'],$options['user_id']);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

	
    
    function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}
	
}
