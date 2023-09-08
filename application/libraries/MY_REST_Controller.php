<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require(APPPATH.'/libraries/REST_Controller.php');

/**
 * 
 * Extends REST_CONTROLLER
 * 
 */
abstract class MY_REST_Controller extends REST_Controller {
    
    
    /**
     * 
     * Allow only admin users to access the API
     * 
     */
    public function is_admin_or_die()
    {
        try{
            if (!$this->is_admin()){
                throw new Exception("ACCESS-DENIED");
            }
        }   
        catch(Exception $e){
			$response=array(
                'status'=>'failed',
                'message'=>'ACCESS-DENIED'
            );
            $this->response($response, REST_Controller::HTTP_FORBIDDEN,false);
            die();
		}
    }


    /**
     * 
     * Allow only admin users to access the API
     * 
     */
    public function is_authenticated_or_die()
    {
        if(!$this->get_api_user_id()){
            $response=array(
                'status'=>'ACCESS-DENIED',
                'message'=>'Access denied'
            );
            $this->response($response, REST_Controller::HTTP_FORBIDDEN,false);
            die();
        }
    }

    

    /**
     * Check if logged in user has admin rights
     */
    public function is_admin()
    {
        return $this->ion_auth->is_admin($this->get_api_user_id());
    }
    

    /**
     * 
     * Return user info
     * 
     */
    public function api_user()
    {
        if(isset($this->_apiuser) && isset($this->_apiuser->user_id)){
			return $this->ion_auth->get_user($this->_apiuser->user_id);
		}

        //session user id
		if ($this->session->userdata('user_id')){
			return $this->ion_auth->get_user($this->session->userdata('user_id'));
		}

		return false;
    }


    /**
     * 
     * Get logged in API user ID
     * 
     */
    public function get_api_user_id()
	{
        //session user id
		if ($this->session->userdata('user_id')){
			return $this->session->userdata('user_id');
		}

		if(isset($this->_apiuser) && isset($this->_apiuser->user_id)){
			return $this->_apiuser->user_id;
		}

		return false;
    }



	/**
     * 
     * 
     * return raw json input
     * 
     **/
	public function raw_json_input()
	{
		$data=$this->input->raw_input_stream;				
		//$data = file_get_contents("php://input");

		if(!$data || trim($data)==""){
			return null;
		}
		
		$json=json_decode($data,true);

		if (!$json && json_last_error()!==0){
			throw new Exception("INVALID_JSON_INPUT");
		}

		return $json;
	}



    /**
	 * 
	 * 
	 * Return ID by IDNO
	 * 
	 * 
	 * @idno 		- ID | IDNO
	 * @id_format 	- ID | IDNO
	 * 
	 * Note: to use ID instead of IDNO, must pass id_format in querystring
	 * 
	 */
	public function get_sid_from_idno($idno=null)
	{		
		if(!$idno){
			throw new Exception("IDNO-NOT-PROVIDED");
		}

		$id_format=$this->input->get("id_format");

		if ($id_format=='id'){
			return $idno;
		}

        $this->load->library("Dataset_manager");
		$sid=$this->dataset_manager->find_by_idno($idno);

		if(!$sid){
			throw new Exception("IDNO-NOT-FOUND");
		}

		return $sid;
	}


    public function early_checks()
    {
        //apply ip whitelisting
        if ($this->config->item('rest_ip_whitelist_enabled') === TRUE)
        {
            $this->_check_whitelist_auth();
        }

    }

    function has_access($resource,$privilege,$repositoryid=null)
    {
        $user=$this->api_user();

        try{
            return $this->acl_manager->has_access($resource, $privilege,$user,$repositoryid);
        }
        catch(Exception $e){
            //throw new AclAccessDeniedException('ACCESS-DENIED',$e->getMessage());            
				$this->output
					->set_status_header(403)
        			->set_content_type('application/json');
				die (json_encode(
                    array(
                        'status'=>'failed',
                        'error'=>$e->getMessage()
                    )
                ));
        }        
    }


    function has_dataset_access($privilege, $sid=null,$repositoryid=null)
    {
        $user=$this->api_user();
        $resource='study';

        //get repositoryid
        if ($sid && !$repositoryid){            
            $repositoryid=$this->get_dataset_repositoryid($sid);
        }
        try{
            return $this->acl_manager->has_access('study', $privilege,$user,$repositoryid);
        }
        catch(Exception $e){
            throw new AclAccessDeniedException('ACCESS-DENIED',$e->getMessage());
        }
    }

    
    private function get_dataset_repositoryid($sid)
    {
        $this->db->select("repositoryid");
        $this->db->where("id",$sid);
        $output=$this->db->get("surveys")->row_array();
        if($output){
            return $output['repositoryid'];
        }
    }

    /**
	 * 
	 * 
	 * Return ID from IDNO
	 * 
	 * 
	 * @idno 		- ID | IDNO
	 * 
	 * 
	 */
	function get_sid($idno=null)
	{		
		if(!$idno){
			throw new Exception("IDNO-NOT-PROVIDED");
		}

		if (is_numeric($idno)){
			return $idno;
		}

		$sid=$this->Editor_model->get_project_id_by_idno($idno);

		if(!$sid){
			throw new Exception("IDNO-NOT-FOUND");
		}

		return $sid;
	}

}