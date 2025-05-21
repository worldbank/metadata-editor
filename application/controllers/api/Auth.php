<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Auth extends REST_Controller
{
	
	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
        $this->load->model('DD_resource_model');
	}
	

	/** 
	*
	* Login 
	*
	*
	*/
  public function login_post()
  {
		$email=$this->input->post("email");
		$pass=$this->input->post("password");
		
		try{
			if ($this->ion_auth->validate_login($email,$pass)){

				//try authnication
				$authenticate=$this->ion_auth->login($email, $pass);

				if (!$authenticate){
					throw new Exception("LOGIN_FAILED");
				}

				//get user info
				$user=$this->ion_auth->get_user_by_email($email);

				if(!$user){
					throw new Exception("ERROR_LOADING_USER_DATA");
				}
				
				//get user API token
				$api_keys=$this->ion_auth->get_api_keys($user->id);

				$options=array(
					'user_id'=>$user->id,
					'user_name'=>$user->username,
					'api_keys'=>$api_keys
				);

				$response=array(
					'status'=>'success',
					'user'=>$options
				);

				$this->set_response($response, REST_Controller::HTTP_OK);
			}	
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'error',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}



	/** 
	*
	* Create API Key
	*
	*
	*/
	public function create_api_key_post()
  {
		$email=$this->input->post("email");
		$pass=$this->input->post("password");
		$key=$this->input->post("api_key");
		
		try{
			if ($this->ion_auth->validate_login($email,$pass)){

				//try authnication
				$authenticate=$this->ion_auth->login($email, $pass);

				if (!$authenticate){
					throw new Exception("LOGIN_FAILED");
				}

				//get user info
				$user=$this->ion_auth->get_user_by_email($email);

				if(!$user){
					throw new Exception("ERROR_LOADING_USER_DATA");
				}

				//generate/assign key
				$this->ion_auth->set_api_key($user->id,$key);
				
				//get user API token
				$api_keys=$this->ion_auth->get_api_keys($user->id);

				$options=array(
					'user_id'=>$user->id,
					'user_name'=>$user->username,
					'api_keys'=>$api_keys
				);

				$response=array(
					'status'=>'success',
					'user'=>$options
				);

				$this->set_response($response, REST_Controller::HTTP_OK);
			}	
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'error',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}

		
	
}
