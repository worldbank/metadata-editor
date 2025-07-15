<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

class Languages extends MY_REST_Controller
{
	private $api_user;
	private $user_id;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");		
		$this->load->library("Form_validation");
		
		$this->is_authenticated_or_die();
		$this->api_user=$this->api_user();
		$this->user_id=$this->get_api_user_id();
	}

	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}
	
	
	/**
	 * 
	 * 
	 * Return all Languages
	 * 
	 */
	function index_get($uid=null)
	{
		try{			
			$languages=$this->config->item("supported_languages");
			$language_codes=$this->config->item("language_codes");

			$lang=$this->session->userdata('language');

				$lang=!empty($lang) ? $lang : $this->config->item('language');

			$result=array();
			if (!$languages)
			{
				$this->set_response(array('status'=>'failed', 'message'=>'No languages available'),
					REST_Controller::HTTP_NOT_FOUND);
				return;
			}

			foreach ($language_codes as $language) {
				$result[] = array(
					'code' => $language['code'],
					'name' => $language['name'],
					'display' => $language['display'],
					'current' => ($language['code'] === $lang)
				);
			}

			$response = array(
				'status' => 'success',
				'languages' => $result,
				'current_language' => $lang,
				'current_language_title' => isset($language_codes[$lang]) ? $language_codes[$lang]['display'] : $lang
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
