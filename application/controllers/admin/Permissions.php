<?php
class Permissions extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		
		$this->load->helper(array('form', 'url'));
       	//$this->load->model('User_Groups_model');
		$this->load->model('Permissions_model');
		//$this->load->model('Repository_model');
		$this->load->library("Acl_manager");
		
		//language files
		$this->lang->load('general');
		$this->lang->load('user_groups');
		$this->lang->load('permissions');
		//$this->output->enable_profiler(TRUE);

		//set default template
		$this->template->set_template('admin5');		
	}

	
	function index()
	{
		$this->roles();
	}


	/**
	*
	* Manage Permission URLs 
	**/
	function manage($role_id=null)
	{
		if (!is_numeric($role_id)){
			redirect("admin/permissions/roles");
		}

		$role =$this->acl_manager->get_role_by_id($role_id);

		if (empty($role)){
			show_error(t("Role was not found"));
		}

		if ($role['is_locked']==1){
			show_error("System role cannot be edited");
		}

		$data=$this->acl_manager->get_all_permissions();
		$role_permissions=$this->acl_manager->get_role_permissions($role_id);

		//process post
		if($post_data=$this->input->post('resource')){
			$data['post_values']=$post_data;
			$this->acl_manager->remove_role_permissions($role_id);
			foreach($post_data as $resource=>$permissions){
				$this->acl_manager->set_role_permissions($role_id,$resource, (array)$permissions);
			}
		}
		else{
			$data['post_values']=array();
			foreach($role_permissions as $row){
				$data['post_values'][$row['resource']]=$row['permissions'];
			}
		}
		

		$data['active_id']=$role_id;
		$data['roles']=$this->acl_manager->get_roles();

		$contents=$this->load->view('permissions/index',$data,TRUE);
		
		$this->template->write('content', $contents,true);
	  	$this->template->render();
	}



	function roles()
	{
		$data['roles']=$this->acl_manager->get_roles();
		$contents=$this->load->view('permissions/roles',$data,TRUE);		
		$this->template->write('content', $contents,true);
	  	$this->template->render();
	}


	function create_role()
	{
		$this->load->library("form_validation");
		$this->form_validation->reset_validation();
		$this->form_validation->set_data($_POST);

		$this->form_validation->set_rules('role', t('role'), 'xss_clean|trim|alpha_numeric_spaces|required|max_length[100]');
		$this->form_validation->set_rules('description', t('description'), 'xss_clean|max_length[255]');

		if ($this->form_validation->run() == TRUE){

			$role =$this->input->post("role");
			$description=$this->input->post("description");

			try{
				$this->acl_manager->create_role($role,$description);
				$this->session->set_flashdata('message', t('form_update_success'));	
			}
			catch(Exception $e){
				$this->session->set_flashdata('error', t($e->getMessage()));	
			}
		}
		else{
			$errors=$this->form_validation->error_array();
			$error_str=$this->form_validation->error_array_to_string($errors);
			

			$this->session->set_flashdata('error', $error_str);			
		}
		
		redirect('admin/permissions/roles');
	}



	function edit_role($id=null)
	{
		if(empty($id)){
			show_error(t("ID not provided"));
		}

		$role =$this->acl_manager->get_role_by_id($id);

		if (empty($role)){
			show_error(t("Role was not found"));
		}

		//validation rules
		$this->form_validation->set_rules('role', t('role'), 'xss_clean|trim|alpha_numeric_spaces|required|max_length[100]');
		$this->form_validation->set_rules('description', t('description'), 'xss_clean|max_length[255]');
		$this->form_validation->set_rules('weight', t('weight'), 'xss_clean|is_natural|max_length[3]');
				
		//process form				
		if ($this->form_validation->run() == TRUE)
		{
			$options=array();
			$post_arr=$_POST;
						
			foreach($post_arr as $key=>$value){
				$options[$key]=$this->input->post($key);
			}					
															
			//update db
			$db_result=$this->acl_manager->update_role(
				$id,
				$this->input->post("role"),
				$this->input->post("description"),
				$this->input->post("weight")
			);
						
			if ($db_result===TRUE){
				$this->session->set_flashdata('message', t('form_update_success'));
				redirect("admin/permissions/roles","refresh");
			}
			else{
				$this->form_validation->set_error(t('form_update_fail'));
			}
		}
		
		$contents=$this->load->view('permissions/role_edit',$role,TRUE);		
		$this->template->write('content', $contents,true);
		$this->template->render();
	}
	
	
	/**
	* 
	* Delete a role
	* 
	*/
	function delete_role($id)
	{			
		$this->acl_manager->has_access_or_die('user', 'edit');

		if (!is_numeric($id)){
			show_error('INVALID_ROLE_ID');
		}
				
		if ($this->input->post('cancel')!=''){
			//redirect page url
			$destination=$this->input->get_post('destination');
			
			if ($destination!=""){
				redirect($destination);
			}
			else{
				redirect('admin/permissions');
			}	
		}
		else if ($this->input->post('submit')!='')
		{			
			//confirm delete	
			$this->acl_manager->delete_role($id);
								
			//redirect page url
			$destination=$this->input->get_post('destination');
			
			if ($destination!=""){
				redirect($destination);
			}
			else{
				redirect('admin/permissions');
			}	
		}
		else
		{
			//ask for confirmation
			$content=$this->load->view('resources/delete', NULL,true);
			
			$this->template->write('content', $content,true);
	  		$this->template->render();
		}		
	}
}