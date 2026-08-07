<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');
require_once(APPPATH.'libraries/Template_uid_conflict_exception.php');
require_once(APPPATH.'libraries/Template_uid_active_conflict_exception.php');

class Templates extends MY_REST_Controller
{

	private $user_id=null;
	private $user=null;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper("date");
		$this->load->model("Editor_template_model");
		$this->load->model("Edit_history_model");
		
		$this->load->library("Form_validation");
		//$this->is_admin_or_die();
		$this->load->library("Editor_acl");
		$this->is_authenticated_or_die();

		$this->user_id=$this->get_api_user_id();
		$this->user=$this->api_user();
	}

	//override authentication to support both session authentication + api keys
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
	 * Return all templates
	 * 
	 */
	function index_get($uid=null)
	{
		try{
			if ($uid === 'deleted'){
				return $this->deleted_list_get();
			}

			if($uid){
				return $this->template_get($uid);
			}

			$this->has_access($resource_='template_manager',$privilege='view');
			
			$result=$this->Editor_template_model->select_all();
			//array_walk($result, 'unix_date_to_gmt',array('created','changed'));
			
			$response=array(
				'status'=>'success',
				'total'=>count($result),
				'found'=>count($result),
				'templates'=>$result
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


	function deleted_list_get()
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			$result=$this->Editor_template_model->select_deleted();
			$custom=isset($result['custom']) && is_array($result['custom']) ? $result['custom'] : array();

			$response=array(
				'status'=>'success',
				'total'=>count($custom),
				'found'=>count($custom),
				'templates'=>$result
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


	function core_template_get($data_type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$data_type){
				throw new Exception("Missing parameter for `data_type`");
			}

			$resolved_uid=$this->Editor_template_model->resolve_core_template_uid($data_type);

			if (!$resolved_uid){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$result=$this->Editor_template_model->get_core_template_json($resolved_uid);			
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'template'=>$result
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

	function template_get($uid=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$result=$this->Editor_template_model->get_template_by_uid($uid);			
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$include = $this->input->get('include');
			$include_parts = array();
			if ($include !== null && $include !== '') {
				foreach (explode(',', (string)$include) as $part) {
					$part = trim($part);
					if ($part !== '') {
						$include_parts[] = $part;
					}
				}
			}

			if (in_array('schema_alignment', $include_parts, true)) {
				$this->load->library('Editor_template_schema_alignment_validator');
				$template_payload = isset($result['template']) ? $result['template'] : array();
				if (!is_array($template_payload)) {
					$template_payload = json_decode((string)$template_payload, true);
				}
				if (!is_array($template_payload)) {
					$template_payload = array();
				}
				$data_type = isset($result['data_type']) ? $result['data_type'] : '';
				$alignment = Editor_template_schema_alignment_validator::collect_enum_alignment_issues(
					$data_type,
					$template_payload,
					$uid
				);
				$result['schema_alignment'] = array(
					'issues' => isset($alignment['issues']) ? $alignment['issues'] : array(),
					'warnings' => isset($alignment['warnings']) ? $alignment['warnings'] : array(),
					'issue_count' => count(isset($alignment['issues']) ? $alignment['issues'] : array()),
				);
			}

			$this->set_response($result, REST_Controller::HTTP_OK);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function list_get($type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			$result=$this->Editor_template_model->get_templates_by_type($type);
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'result'=>$result
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


	function template_parts_get($uid=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$result=$this->Editor_template_model->get_template_parts_by_uid($uid);
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			$response=array(
				'status'=>'success',
				'result'=>$result
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


	/**
	 * 
	 * Create new template by duplicating a template
	 * @template_uid
	 * 
	 **/ 
	function duplicate_post($uid=null)
	{		
		try{			
			$this->has_access($resource_='template_manager',$privilege='duplicate');
			$result=$this->Editor_template_model->duplicate_template($uid, $this->user_id);

			if (!$result){
				throw new Exception("Failed to duplicate template");
			}

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function create_post()
	{		
		try{
			$this->has_access($resource_='template_manager',$privilege='edit');

			$options=$this->raw_json_input();
			$options['created_by']=$this->user_id;
			$options['changed_by']=$this->user_id;
			$result=$this->Editor_template_model->create_template($options);

			$output=array(
				'status'=>'success',
				'template'=>$result,
				'uid_reassigned'=>!empty($result['uid_reassigned']),
				'original_uid'=>isset($result['original_uid']) ? $result['original_uid'] : null,
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Template_uid_conflict_exception $e){
			$error_output=array(
				'status'=>'failed',
				'code'=>$e->get_conflict_code(),
				'message'=>$e->getMessage(),
				'conflict'=>$e->get_conflict_data(),
			);
			$this->set_response($error_output, REST_Controller::HTTP_CONFLICT);
		}
		catch(Template_uid_active_conflict_exception $e){
			$error_output=array(
				'status'=>'failed',
				'code'=>$e->get_conflict_code(),
				'message'=>$e->getMessage(),
				'conflict'=>$e->get_conflict_data(),
			);
			$this->set_response($error_output, REST_Controller::HTTP_CONFLICT);
		}
		catch(Exception $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage()
			);
			$this->set_response($error_output, REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function update_post($uid=null)
	{		
		try{			
			//$this->has_access($resource_='template_manager',$privilege='edit');
			$this->editor_acl->user_has_template_access($uid,$permission='edit',$this->user);

			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$template=$this->Editor_template_model->get_template_by_uid($uid);

			if (!$template){
				throw new Exception("Template not found: ".$uid);
			}

			if (!empty($template['template_type']) && $template['template_type']!=='custom'){
				throw new Exception("Read-only templates cannot be edited. Duplicate the template to customize it.");
			}

			if (!empty($template['is_deleted'])){
				throw new Exception("Template is deleted. Restore it before editing.");
			}

			$options=$this->raw_json_input(); 			
			$options['changed_by']=$this->user_id;

			$result=$this->Editor_template_model->update($uid,$options);

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


	function delete_post($uid=null)
	{		
		try{
			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$template=$this->Editor_template_model->get_template_by_uid($uid);

			if (!$template){
				throw new Exception("Template not found: ".$uid);
			}

			if (!empty($template['template_type']) && $template['template_type']!=='custom'){
				throw new Exception("Read-only templates cannot be deleted.");
			}

			if (!empty($template['is_deleted'])){
				throw new Exception("Template is already deleted.");
			}

			$this->editor_acl->user_can_manage_template($uid, $this->user);
			$result=$this->Editor_template_model->delete($uid, $this->user_id);

			$output=array(
				'status'=>'success'
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


	function restore_post($uid=null)
	{
		try{
			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$template=$this->Editor_template_model->get_template_by_uid($uid);

			if (!$template){
				throw new Exception("Template not found: ".$uid);
			}

			if (!empty($template['template_type']) && $template['template_type']!=='custom'){
				throw new Exception("Read-only templates cannot be restored.");
			}

			$this->editor_acl->user_can_manage_template($uid, $this->user);
			$result=$this->Editor_template_model->restore($uid, $this->user_id);

			$output=array(
				'status'=>'success',
				'restored'=>$result
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


	function purge_post($uid=null)
	{
		try{
			if (!$uid){
				throw new Exception("Missing parameter: UID");
			}

			$template=$this->Editor_template_model->get_template_by_uid($uid);

			if (!$template){
				throw new Exception("Template not found: ".$uid);
			}

			if (!empty($template['template_type']) && $template['template_type']!=='custom'){
				throw new Exception("Read-only templates cannot be permanently deleted.");
			}

			$this->editor_acl->user_can_manage_template($uid, $this->user);
			$result=$this->Editor_template_model->purge($uid, $this->user_id);

			$output=array(
				'status'=>'success',
				'purged'=>$result
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


	/**
	 * 
	 * Set default template
	 * @template_uid
	 * 
	 **/ 
	function default_post($type=null,$uid=null)
	{		
		try{			
			$this->has_access($resource_='template_manager',$privilege='admin');
			$result=$this->Editor_template_model->set_default_template($type,$uid);

			$output=array(
				'status'=>'success',
				'template'=>$result
			);

			$this->set_response($output, REST_Controller::HTTP_OK);			
		}
		catch(Exception $e){
			$this->set_response($e->getMessage(), REST_Controller::HTTP_BAD_REQUEST);
		}
	}


	function default_get($type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');
			$result=$this->Editor_template_model->get_default_template($type);

			if (!isset($result['template_uid'])){
				throw new Exception("Default template not found");
			}

			$template=$this->Editor_template_model->get_template_by_uid($result['template_uid']);
			
				
			$response=array(
				'status'=>'success',
				'result'=>$template
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

	function defaults_get($type=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');
			$result=$this->Editor_template_model->get_all_default_templates();
				
			$response=array(
				'status'=>'success',
				'result'=>$result
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



	/**
	 * 
	 * Share template with user
	 * 
	 * @options JSON array
	 * [
	 * 	{
	 * 		"template_id": "template id",
	 * 		"user_id": "user id",
	 * 		"permissions": "view|edit|admin"
	 * 	}
	 * ]
	 * 
	 */
	function share_post()
	{		
		try{
			
			$options=$this->raw_json_input();

			if (!is_array($options)){
				throw new Exception("Invalid input: must be an array");
			}

			foreach($options as $option){
				if (!isset($option['template_uid'])){
					throw new Exception("Missing parameter: template_uid");
				}

				$share_template=$this->Editor_template_model->get_template_by_uid($option['template_uid']);
				if ($share_template && !empty($share_template['is_deleted'])){
					throw new Exception("Template is deleted. Restore it before sharing.");
				}

				$this->editor_acl->user_has_template_access($option['template_uid'],$permission='admin',$this->user);	
			}

			$result=$this->Editor_template_model->share_template($options, $this->user_id);

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


	function share_get($uid)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			$result=$this->Editor_template_model->template_users($uid);
				
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
			$options=$this->raw_json_input();

			if (!isset($options['template_uid'])){
				throw new Exception("Missing parameter: UID");
			}

			if (!isset($options['user_id'])){
				throw new Exception("Missing parameter: user_id");
			}

			$this->editor_acl->user_has_template_access($options['template_uid'],$permission='admin',$this->user);
			$result=$this->Editor_template_model->unshare_template($options['template_uid'], $options['user_id']);

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



	/**
	 * 
	 * 
	 * Revision history for a template
	 * 
	 */
	function revisions_get($uid=null)
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$pagination = $this->get_pagination_params(15, 100);
			$result=$this->Editor_template_model->get_template_revision_history(
				$uid,
				$pagination['offset'],
				$pagination['limit']
			);	
			array_walk($result['history'], 'unix_date_to_gmt',array('created'));
			
			$response=array(
				'status'=>'success',
				//'total'=>count($result),
				//'found'=>count($result),
				'data'=>$result
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

	/**
	 * 
	 * 
	 * Check template UUID exists
	 * 
	 */
	function uid_get($uid=null)
	{
		try{
			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}
			
			$status=$this->Editor_template_model->get_uid_conflict_status($uid);
			$response=array(
				'status'=>'success',
				'found'=>$status['exists'],
				'uid_status'=>$status['status'],
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


	/**
	 * 
	 * 
	 * Replace template UUID
	 * 
	 * params - json object with `old_uid` and `new_uid`
	 * 
	 */
	function uid_post()
	{
		try{
			$options=$this->raw_json_input();

			if (!isset($options['old_uid'])){
				throw new Exception("Missing parameter for `old_uid`");
			}

			if (!isset($options['new_uid'])){
				throw new Exception("Missing parameter for `new_uid`");
			}
			
			$result=$this->Editor_template_model->replace_uid($options['old_uid'], $options['new_uid']);

			$response=array(
				'status'=>'success',
				'updated'=>$result
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

	/**
	 * 
	 * 
	 * Get translation keys for a template
	 * 
	 * Returns a flat array of all key, prop_key and title from all items for a template
	 * 
	 * @param string $uid Template UID
	 * @param string $format Optional format parameter: 'compact' for key:value format, 'full' for detailed format (default)
	 * 
	 */
	function translation_keys_get($uid=null, $format='full')
	{
		try{
			$this->has_access($resource_='template_manager',$privilege='view');

			if(!$uid){
				throw new Exception("Missing parameter for `UID`");
			}

			$result=$this->Editor_template_model->get_template_translation_keys($uid, $format);
				
			if(!$result){
				throw new Exception("TEMPLATE_NOT_FOUND");
			}

			if($format === 'compact'){
				$response=array(
					'status'=>'success',
					'translations'=>$result
				);
			} else {
				$response=array(
					'status'=>'success',
					'translation_keys'=>$result
				);
			}
			
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
