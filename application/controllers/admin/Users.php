<?php
class Users extends MY_Controller {

	var $errors='';
	var $search_fields=array('username','email','status');
	
	function __construct()
	{
		parent::__construct();
		
		$this->load->helper(array('form', 'url'));		
		$this->load->library( array('form_validation','pagination') );		
       	$this->load->model('User_model');
		
		//language files
		$this->lang->load('general');
		$this->lang->load('users');
		
		//set template to admin
		$this->template->set_template('admin5');
		
		//$this->output->enable_profiler(TRUE);
		$this->disable_page_cache();
	}
	
	//expire page immediately
    private function disable_page_cache()
    {	
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
    }
	
	function index()
	{
		$this->acl_manager->has_access_or_die('user', 'view');

		//get array of db rows		
		$result['rows']=$this->_search();
		
		$user_id_arr=array();
		foreach($result['rows'] as $row){
			$user_id_arr[]=$row['id'];
		}
				
		//get user groups 
		$result['user_groups']=$this->User_model->get_user_roles($user_id_arr);

		$content=$this->load->view('users/index', $result,true);
		$this->template->write('content', $content,true);
		$this->template->write('title', t('title_user_management'),true);
	  	$this->template->render();	
	}
	
	/**
	 * Search - internal method, supports pagination, sorting
	 *
	 * @return string
	 * @author IHSN
	 **/
	function _search()
	{
		//records to show per page
		$per_page = 15;
				
		//current page
		$offset=$this->input->get('offset');//$this->uri->segment(4);

		//sort order
		$sort_order=$this->input->get('sort_order') ? $this->input->get('sort_order') : 'asc';
		$sort_by=$this->input->get('sort_by') ? $this->input->get('sort_by') : 'title';

		//filter
		$filter=NULL;

		//simple search
		if ($this->input->get_post("keywords") )
		{
			$filter[0]['field']=$this->input->get_post('field');
			$filter[0]['keywords']=$this->input->get_post('keywords');			
		}		
		
		if ($this->input->get('user_group')) {
			$rows=$this->User_model->get_users_by_group((int)$this->input->get('user_group'), $per_page, $offset,$filter, $sort_by, $sort_order);

			$total = $this->User_model->search_count();
		} else {
			//records
			$rows=$this->User_model->search($per_page, $offset,$filter, $sort_by, $sort_order);

			//total records in the db
			$total = $this->User_model->search_count();

			if ($offset>$total)
			{
				$offset=$total-$per_page;
			
				//search again
				$rows=$this->User_model->search($per_page, $offset,$filter, $sort_by, $sort_order);
			}
		}
		
		//set pagination options
		$base_url = site_url('admin/users');
		$config['base_url'] = $base_url;
		$config['total_rows'] = $total;
		$config['per_page'] = $per_page;
		$config['query_string_segment']="offset"; 
		$config['page_query_string'] = TRUE;
		$config['additional_querystring']=get_querystring( array('keywords', 'field','sort_by','sort_order'));//pass any additional querystrings
		$config['num_links'] = 1;
		$config['full_tag_open'] = '<span class="page-nums">' ;
		$config['full_tag_close'] = '</span>';

		//intialize pagination
		$this->pagination->initialize($config); 
		return $rows;		
	}
	
	function add() 
	{  
		$this->acl_manager->has_access_or_die('user', 'create');
		$this->data['page_title'] = t("create_user_account");
              		
        //validate form input
		$this->form_validation->set_rules('username', t('username'), 'xss_clean|max_length[20]|required|callback_username_exists');
    	$this->form_validation->set_rules('email', t('email'), 'max_length[100]|required|valid_email|callback_email_exists');
    	$this->form_validation->set_rules('first_name', t('first_name'), 'max_length[20]|required|xss_clean');
    	$this->form_validation->set_rules('last_name', t('last_name'), 'max_length[20]|required|xss_clean');
    	$this->form_validation->set_rules('phone1', t('phone'), 'max_length[20]|xss_clean|trim');
    	$this->form_validation->set_rules('company', t('company'), 'max_length[255]|xss_clean');
    	$this->form_validation->set_rules('password', t('password'), 'required|min_length['.$this->config->item('min_password_length').']|max_length['.$this->config->item('max_password_length').']|matches[password_confirm]');
    	$this->form_validation->set_rules('password_confirm', t('password_confirmation'), 'required');

		//phone is required for administrators
		/*
		if ($this->input->post("group_id")==1)
		{
	    	$this->form_validation->set_rules('phone1', t('phone'), 'xss_clean|trim|required|max_length[20]');
		}
		*/

        if ($this->form_validation->run() == true) 
		{ 
			//check to see if we are creating the user
			$username  = strtolower($this->input->post('username'));
        	$email     = $this->input->post('email');
        	$password  = $this->input->post('password');
        	
        	$additional_data = array('first_name' => $this->input->post('first_name'),
        							 'last_name'  => $this->input->post('last_name'),
        							 'company'    => $this->input->post('company'),
        							 'phone'      => $this->input->post('phone1'),// .'-'. $this->input->post('phone2') .'-'. $this->input->post('phone3'),
									 'active'     => $this->input->post('active'),
									 'country'    => $this->input->post('country'),
        							 'active'     => $this->input->post('active'),
									 'role_id'    => $this->input->post('role')
        							);
        	
        	//register the user
			$user_created=$this->ion_auth_model->register($username, $password, $email, $additional_data);
			
        	if ($user_created)
        	{
				$data['username']=$username;
        		$data['active']=$additional_data['active'];
				$data['role_id']=$additional_data['role_id'];	
				
        		//get the user data by email
        		$user=$this->ion_auth->get_user_by_email($email);

				//update user group to ADMIN and ACTIVATE account
        		$this->ion_auth->update_user($user->id, $data);	        	
        	}  
        	
        	//redirect them back to the admin page
        	$this->session->set_flashdata('message', t("form_update_success") );
       		redirect("admin/users", 'refresh');
		} 
		else 
		{ 
			//display the create user form
	        
			//set the flash data error message if there is one
	        $this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			
			$this->data['first_name']          = array('name'   => 'first_name',
		                                              'id'      => 'first_name',
		                                              'type'    => 'text',
		                                              'value'   => $this->form_validation->set_value('first_name'),
													  'class'	=> 'form-control'
		                                             );
            $this->data['last_name']           = array('name'   => 'last_name',
		                                              'id'      => 'last_name',
		                                              'type'    => 'text',
		                                              'value'   => $this->form_validation->set_value('last_name'),
													  'class'	=> 'form-control'
		                                             );
            $this->data['email']              = array('name'    => 'email',
		                                              'id'      => 'email',
		                                              'type'    => 'text',
		                                              'value'   => $this->form_validation->set_value('email'),
													  'class'	=> 'form-control'
		                                             );
            $this->data['username']           = array('name'    => 'username',
		                                              'id'      => 'username',
		                                              'type'    => 'text',
		                                              'value'   => $this->form_validation->set_value('username'),
													  'class'	=> 'form-control'
		                                             );

            $this->data['company']            = array('name'    => 'company',
		                                              'id'      => 'company',
		                                              'type'    => 'text',
		                                              'value'   => $this->form_validation->set_value('company'),
													  'class'	=> 'form-control'
		                                             );
            $this->data['phone1']             = array('name'    => 'phone1',
		                                              'id'      => 'phone1',
		                                              'type'    => 'text',
		                                              'value'   => $this->form_validation->set_value('phone1'),
													  'class'	=> 'form-control'
		                                             );
		    $this->data['password']           = array('name'    => 'password',
		                                              'id'      => 'password',
		                                              'type'    => 'password',
		                                              'value'   => $this->form_validation->set_value('password'),
													  'class'	=> 'form-control'
		                                             );
            $this->data['password_confirm']   = array('name'    => 'password_confirm',
                                                      'id'      => 'password_confirm',
                                                      'type'    => 'password',
                                                      'value'   => $this->form_validation->set_value('password_confirm'),
													  'class'	=> 'form-control'
                                                     );
            $this->data['active']=$this->form_validation->set_value('active',1);
			
			$this->data['roles']=array();

			if($this->input->post('role')){
				$this->data['user_role']=$this->input->post('role');
			}
			
			$this->data['roles']= $this->acl_manager->get_roles();//full list of roles
			$this->data['options_country']= $this->ion_auth_model->get_all_countries();
			
            $content=$this->load->view('users/create', $this->data,TRUE);
			$this->template->write('content', $content,true);
			$this->template->write('title', $this->data['page_title'],true);
			$this->template->render();	
		}
    }	
	


	function edit($id) 
	{  		
		$this->acl_manager->has_access_or_die('user', 'edit');
		$this->data['page_title'] = t("edit_user_account");	
		$use_complex_password=$this->config->item("require_complex_password");
	              		
        //validate form input
		$this->form_validation->set_rules('username', t('username'), 'trim|required|callback_username_exists');
    	$this->form_validation->set_rules('email', t('email'), 'max_length[100]|required|valid_email|callback_email_exists');		
    	$this->form_validation->set_rules('first_name', t('first_name'), 'trim|required|xss_clean');
    	$this->form_validation->set_rules('last_name', t('last_name'), 'trim|required|xss_clean');
    	$this->form_validation->set_rules('phone1', t('phone'), 'trim|xss_clean');
    	$this->form_validation->set_rules('company', t('company_name'), 'trim|xss_clean');

		if ($this->input->post("password") || $this->input->post("password_confirm") )
		{
	    	$this->form_validation->set_rules('password', t('password'), 'required|min_length['.$this->config->item('min_password_length').']|max_length['.$this->config->item('max_password_length').']|matches[password_confirm]|is_complex_password['.$use_complex_password.']');
    		$this->form_validation->set_rules('password_confirm', t('password_confirmation'), 'required');
		}
				
        if ($this->form_validation->run() == true) 
		{ 
			$data = array(
				'username' => $this->input->post('username'),
				'email' 	=> $this->input->post('email'),
				'first_name' => $this->input->post('first_name'),
				'last_name'  => $this->input->post('last_name'),
				'company'    => $this->input->post('company'),
				'phone'      => $this->input->post('phone1'),
				'active'     => $this->input->post('active'),
				'role_id'     => $this->input->post('role'),
				'country'     => $this->input->post('country'),
			);
						
			//change password, if not empty
			if ($this->input->post("password") ){
				$data['password']=$this->input->post('password');
			}

        	//update user 
        	$this->ion_auth->update_user($id,$data);
        	
        	$this->session->set_flashdata('message', "User updated");
       		redirect("admin/users", 'refresh');
		} 
		else 
		{ 
			//displaying the form for the first time
	        
			//get user id
			$db_data=$this->ion_auth->get_user($id);

			if (!$db_data){
				show_404();
			}
			
			//load data from post-back. need this for loading user group selection, 
			//other values are populated on postback			
			
			//set the flash data error message if there is one
	        $this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
			
			$this->data['id']          			= array('name'   => 'id',
		                                              'id'      => 'id',
		                                              'type'    => 'hidden',
		                                              'value'   => $this->form_validation->set_value('id',$id),
		                                             );

			$this->data['first_name']          = array('name'   => 'first_name',
		                                              'id'      => 'first_name',
		                                              'type'    => 'text',
													  'value'   => $this->form_validation->set_value('first_name',$db_data->first_name),
													  'class'	=> 'form-control'
		                                             );
            $this->data['last_name']           = array('name'   => 'last_name',
		                                              'id'      => 'last_name',
		                                              'type'    => 'text',
		                                              'value'   => $this->form_validation->set_value('last_name',$db_data->last_name),
													  'class'	=> 'form-control'
													);
            $this->data['email']              = array('name'    => 'email',
		                                              'id'      => 'email',
		                                              'type'    => 'text',
													  'value'   => $this->form_validation->set_value('email',$db_data->email),
													  'class'	=> 'form-control'													  
		                                             );
            $this->data['username']           = array('name'    => 'username',
		                                              'id'      => 'username',
		                                              'type'    => 'text',
													  'value'   => $this->form_validation->set_value('username',$db_data->username),
													  'class'	=> 'form-control'													  
		                                             );

            $this->data['company']            = array('name'    => 'company',
		                                              'id'      => 'company',
		                                              'type'    => 'text',
													  'value'   => $this->form_validation->set_value('company',$db_data->company),
													  'class'	=> 'form-control'													  
		                                             );
            $this->data['phone1']             = array('name'    => 'phone1',
		                                              'id'      => 'phone1',
		                                              'type'    => 'text',
													  'value'   => $this->form_validation->set_value('phone1',$db_data->phone),
													  'class'	=> 'form-control'													  
		                                             );
		    $this->data['password']           = array('name'    => 'password',
		                                              'id'      => 'password',
		                                              'type'    => 'password',
													  'value'   => $this->form_validation->set_value('password'),
													  'class'	=> 'form-control'													  
		                                             );
            $this->data['password_confirm']   = array('name'    => 'password_confirm',
                                                      'id'      => 'password_confirm',
                                                      'type'    => 'password',
													  'value'   => $this->form_validation->set_value('password_confirm'),
													  'class'	=> 'form-control'													  
                                                     );
			$this->data['country']=$db_data->country;
			
			if($this->input->post('id')){
				$db_data->user_role=$this->input->post('role');
			}else{				
				if (isset($db_data->groups) && count($db_data->groups) >0){
					$db_data->user_role=array_keys($db_data->groups);
				}
			}

            $this->data['active']= $this->form_validation->set_value('active',$db_data->active);
			
			$this->data['user_role']=array();
			if(isset($db_data->user_role)){
				$this->data['user_role']= $db_data->user_role;//roles assigned to user
			}

			$this->data['roles']= $this->acl_manager->get_roles();//full list of roles
			$this->data['options_country']= $this->ion_auth_model->get_all_countries();

			$content=$this->load->view('users/edit', $this->data,TRUE);						
			$this->template->write('content', $content,true);
			$this->template->write('title', $this->data['page_title'],true);
			$this->template->render();	
		}
    }	
	
	//check if the email address exists in db
	function email_exists($email)
	{
		$user_data=$this->ion_auth->get_user_by_email($email);

		if (!$user_data)
		{
			RETURN TRUE;
		}

		//check if editing user, exclude the current user
		$userid=$this->input->post("id");
		
		if ($userid==$user_data->id)
		{
			return TRUE;
		}

		if ($user_data)
		{
			$this->form_validation->set_message('email_exists', t('callback_email_exists') );
			return FALSE;
		}
		return TRUE;
	}
	
	//check if the username exists in db
	function username_exists($username)
	{
		$user_data=$this->ion_auth->get_user_by_username($username);
		
		if (!$user_data)
		{
			RETURN TRUE;
		}

		//check if editing user, exclude the current user
		$userid=$this->input->post("id");
		
		if ($userid==$user_data->id)
		{
			return TRUE;
		}
		
		if ($user_data)
		{
			$this->form_validation->set_message('username_exists', t('callback_username_exists') );
			return FALSE;
		}
		return TRUE;
	}
		
	

	function _save_user($id=-1)
	{

		$this->session->set_flashdata('message', '<div class="success"><i>'.'user '.'</i> updated</div>' );
		redirect('admin/users');
		exit;

		$u= new User;
		
		if ($id>-1)
		{
			//edit user
			$u->id=$id;
		}

		//populate with post data
		$u->username =$this->input->post('username');
		$u->email = $this->input->post('email');
		
		//skip validation if editing and passwords are blank
		if ($id!=-1 && $this->input->post('password') =="" && $this->input->post('passconf')=="")
		
		//editing an existing user
		if ($id>-1)
		{
			if ($this->input->post('password')=="" && $this->input->post('passconf')=="")
			{
				//skip validation
			}
			else
			{
				$u->password = $this->input->post('password');
				$u->confirm_password = $this->input->post('passconf');
			}
		}
		//add a new record
		else if ($id==-1)
		{
			$u->password = $this->input->post('password');
			$u->confirm_password = $this->input->post('passconf');			
		}

		$u->title=$this->input->post('title');
		$u->fname=$this->input->post('fname');
		$u->lname=$this->input->post('lname');
		$u->organization=$this->input->post('organization');
		$u->address=$this->input->post('address');
		$u->country=$this->input->post('country');
		$u->telephone=$this->input->post('telephone');
		$u->fax=$this->input->post('fax');
		$u->status=$this->input->post('status');
		$u->role=$this->input->post('role');
		
		$u->validate();
		
		if ($u->valid)
		{
			// Validation Passed
			echo 'validation passed';
		}
		else
		{
			// Validation Failed
			$this->errors='<div class="error-box">'.$u->error->string.'</div>';
			return false;
		}	

		// Begin transaction
		$u->trans_begin();
			
		// Attempt to save user
		$u->save();

		// Check status of transaction
		if ($u->trans_status() === FALSE)
		{
			// Transaction failed, rollback
			$u->trans_rollback();
			
			$this->errors='<div class="error-box">'.$u->error->string.'</div>';
			return false;
		}
		else
		{
			// Transaction successful, commit
			$u->trans_commit();
			$this->session->set_flashdata('message', '<div class="success-box"><i>'.$u->username.'</i> updated</div>' );
			redirect('admin/users');
		}
			
		// Show all errors
		//echo $u->error->string;

		//success
		/*$success_msg['message']='Form updated successfully.';
		$this->session->set_flashdata('message', 'Form updated successfully-session flash.');
		$content=$this->load->view('success',$success_msg,true);*/

	}//end-function
	
	

	//validation for add/edit user	
	function _edit_validation($is_editing=FALSE)
	{	
		$this->form_validation->set_error_delimiters('<li>', '</li>');
		$this->form_validation->set_rules('username', t('username'), 'trim|required|min_length[5]|max_length[20]|alpha_numeric');
		
		//skip validation
		if ($is_editing==TRUE && !isset($_POST['password']) )
		{
			$this->form_validation->set_rules('password', 'Password', 'required|matches[passconf]|md5');
			$this->form_validation->set_rules('passconf', 'Password Confirmation', 'required');
		}
		
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
	}


	/**
	* Delete one or more records
	* note: to use with ajax/json, pass the ajax as querystring
	* 
	* id 	int or comma seperate string
	*/
	function delete($id)
	{			
		$this->acl_manager->has_access_or_die('user', 'delete');

		//array of id to be deleted
		$delete_arr=array();
	
		//is ajax call
		$ajax=$this->input->get_post('ajax');

		if (!is_numeric($id))
		{
			$tmp_arr=explode(",",$id);
		
			foreach($tmp_arr as $key=>$value)
			{
				if (is_numeric($value))
				{
					$delete_arr[]=$value;
				}
			}
			
			if (count($delete_arr)==0)
			{
				//for ajax return JSON output
				if ($ajax!='')
				{
					echo json_encode(array('error'=>"invalid id was provided") );
					exit;
				}
				
				$this->session->set_flashdata('error', 'Invalid id was provided.');
				redirect('admin/users',"refresh");
			}	
		}		
		else
		{
			$delete_arr[]=$id;
		}
		
		if ($this->input->post('cancel')!='')
		{
			//redirect page url
			$destination=$this->input->get_post('destination');
			
			if ($destination!="")
			{
				redirect($destination);
			}
			else
			{
				redirect('admin/users');
			}	
		}
		else if ($this->input->post('submit')!='')
		{
			foreach($delete_arr as $item)
			{
				//confirm delete	
				$this->User_model->delete($item);
			}

			//for ajax calls, return output as JSON						
			if ($ajax!='')
			{
				echo json_encode(array('success'=>"true") );
				exit;
			}
						
			//redirect page url
			$destination=$this->input->get_post('destination');
			
			if ($destination!="")
			{
				redirect($destination);
			}
			else
			{
				redirect('admin/users');
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
	
	/**
	*
	* Batch import users using CSV
	*
	**/
	function batch_import()
	{	
		$this->acl_manager->has_access_or_die('user', 'add');

		if ($this->input->post("csv")){
			$this->_do_batch_import($this->input->post("csv"));
		}
		
		$content=$this->load->view("users/batch_import",NULL,TRUE);		
		$this->template->write('content', $content,true);
		$this->template->render();		
	}
	
	function _do_batch_import($csv_data,$seperator=',')
	{
		$this->load->library('csvreader');		
		$this->csvreader->separator = $seperator;
		$users_arr=$this->csvreader->parse_string($csv_data, $p_NamedFields = true);
		
		if (count($users_arr)>0)
		{
			foreach($users_arr as $user)
			{
				//log
				$this->db_logger->write_log('user-batch-import',$user['email']);
	
				//check to see if we are creating the user
				$username  = strtolower($user['firstname']).' '.strtolower($user['lastname']);
				$email     = $user['email'];
				$password  = $user['password'];
				
				$additional_data = array('first_name' => $user['firstname'],
										 'last_name'  => $user['lastname'],
										 'company'    => 'N/A',
										 'phone'      => '0000',
										 'country'      => $user['country'],
										 'email'		=>	$email,
										 'identity'		=>$username
										);
				
				//register the user
				$result=$this->ion_auth->register($username,$password,$email,$additional_data);

				if ($result)
				{
					echo '<BR>user account created successfully for: '.$email;
				}
				else
				{
					echo '<BR>failed: '.$email;
				}
			}
			exit;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	*
	* Impersonate as other users
	* 
	* TODO: remove
	*/
	function impersonate()
	{
		show_error('This feature has been removed');

		/*//get admin accounts with limited access
		$data['roles']=$this->acl_manager->get_roles();
		
		if($this->input->post("user"))
		{
			$this->ion_auth_model->impersonate((int)$this->input->post("user"),$this->acl->current_user());
			redirect('admin');return;
		}
		
		$content=$this->load->view('users/impersonate',$data,TRUE);
		$this->template->write('content', $content,true);
		$this->template->render();	
		*/
	}
	
	function exit_impersonate()
	{
		show_error('disabled');
		/*$this->ion_auth_model->exit_impersonate();
		redirect("admin");	
		*/
	}
	
	
	
}

/* End of file users.php */
/* Location: ./application/controllers/users.php */