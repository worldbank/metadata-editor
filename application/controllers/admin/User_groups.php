<?php
class User_groups extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		
		$this->load->helper(array('form', 'url'));		
		$this->load->library( array('form_validation','pagination') );
       	$this->load->model('User_groups_model');
		
		//menu language file
		$this->lang->load('general');
		$this->lang->load('user_groups');
		
		//set default template
		$this->template->set_template('admin');
		
	}
	
	public function index() 
	{
		$result['rows']=$this->_search();
		$content=$this->load->view('user_groups/index', $result,true);
		$this->template->write('content', $content,true);
		$this->template->write('title', t('user_groups_management'),true);
	  	$this->template->render();
	}

	
	public function add() {
		$this->edit();
	}
	
	public function edit($id=NULL)	
	{
		$this->html_form_url=site_url().'/admin/user_groups';		
		
		if (!is_numeric($id)  && $id!=NULL){
			show_error('INVALID ID');
		}
		
		if (is_numeric($id)){
			$this->html_form_url.='/edit/'.$id;
		}
		else{
			$this->html_form_url.='/add';
		}
		
		$obj=NULL;
		$content=NULL;
		
		//edit page link
		if ($this->input->post("linktype")==1){
			$this->edit_link($id);return;
		}

		//validation rules
		$this->form_validation->set_rules('name', t('name'), 'xss_clean|trim|required|max_length[100]');
		$this->form_validation->set_rules('description', t('description'), 'xss_clean|trim|max_length[255]');
		$this->form_validation->set_rules('group_type', t('group_type'), 'xss_clean|trim|required|max_length[45]');
		$this->form_validation->set_rules('access_type', t('access_type'), 'xss_clean|trim|required|max_length[45]');
				
		//process form				
		if ($this->form_validation->run() == TRUE)
		{
			$options=array();
			$post_arr=$_POST;
						
			//read post values to pass to db
			foreach($post_arr as $key=>$value)
			{
				$options[$key]=$this->input->post($key);
			}
			
			//for non-admin accounts force access_type to NONE
			if ($options['group_type']!='admin'){
				$options['access_type']='none';
			}

			//set pid
			if (!is_numeric($options['pid'])){
				$options['pid']=0;
			}

			if ($id==NULL){
				$options['pid']=0;
				$db_result=$this->User_groups_model->insert($options);
			}
			else{
				//update db
				$db_result=$this->User_groups_model->update($id,$options);
			}
						
			if ($db_result===TRUE){
				//update successful
				$this->session->set_flashdata('message', t('form_update_success'));
				redirect("admin/user_groups","refresh");
			}
			else{
				//update failed
				$this->form_validation->set_error(t('form_update_fail'));
			}
		}
		else //loading form the first time
		{
				if ( is_numeric($id) ){
					//get menu from db
					$obj=$this->User_groups_model->select_single($id);
								
					if (!$obj){
						show_error("INVALID ID");
					}
				
					$obj=(object)$obj;				
				}
		}

		//show form
		$content=$this->load->view('user_groups/edit',$obj,true);									
		$this->template->write('content', $content,true);
	  	$this->template->render();								
	}
	
	
	
	function delete($id)
	{			
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
				redirect('admin/user_groups',"refresh");
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
				redirect('admin/user_groups');
			}	
		}
		else if ($this->input->post('submit')!='')
		{
			foreach($delete_arr as $item)
			{
				//confirm delete	
				$this->User_groups_model->delete($item);
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
				redirect('admin/user_groups');
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

	
	private function _search() {
		//records to show per page
		$per_page = 15;
				
		//current page
		$offset=$this->input->get('offset');//$this->uri->segment(4);

		//sort order
		$sort_order=$this->input->get('sort_order') ? $this->input->get('sort_order') : 'asc';
		$sort_by=$this->input->get('sort_by') ? $this->input->get('sort_by') : 'weight';

		//filter
		$filter=NULL;

		//simple search
		if ($this->input->get_post("keywords") ){
			$filter[0]['field']=$this->input->get_post('field');
			$filter[0]['keywords']=$this->input->get_post('keywords');			
		}		
		
		//records
		$rows=$this->User_groups_model->search($per_page, $offset,$filter, $sort_by, $sort_order);

		//total records in the db
		$total = $this->User_groups_model->search_count();

		if ($offset>$total)
		{
			$offset=$total-$per_page;
			
			//search again
			$rows=$this->User_groups_model->search($per_page, $offset,$filter, $sort_by, $sort_order);
		}
		
		//set pagination options
		$base_url = site_url('admin/user_groups');
		$config['base_url'] = $base_url;
		$config['total_rows'] = $total;
		$config['per_page'] = $per_page;
		$config['query_string_segment']="offset"; 
		$config['page_query_string'] = TRUE;
		$config['additional_querystring']=get_querystring( array('keywords', 'field'));//pass any additional querystrings
		$config['num_links'] = 1;
		$config['full_tag_open'] = '<span class="page-nums">' ;
		$config['full_tag_close'] = '</span>';
		
		//intialize pagination
		$this->pagination->initialize($config); 
		return $rows;		
	}
	
}
	
	
	