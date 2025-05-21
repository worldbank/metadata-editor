<?php
class Logs extends MY_Controller {

	var $errors='';
	var $search_fields=array('username','email','status');
	
	function __construct()
	{
		parent::__construct();
		
		$this->load->helper(array('form', 'url'));		
		$this->load->library( array('form_validation','pagination') );
       	$this->load->model('Sitelog_model');
		
		//menu language file
		$this->lang->load('sitelogs');
		
		//set default template
		$this->template->set_template('admin');
		
		//$this->output->enable_profiler(TRUE);
	}
	
	function index()
	{			
		//get array of db rows		
		$result['rows']=$this->_search();
		
		//load the contents of the page into a variable
		$content=$this->load->view('sitelogs/index', $result,true);
	
		$this->template->write('content', $content,true);
		$this->template->write('title', t('site_logs'),true);
	  	$this->template->render();	
	}
	
	/**
	 * Search - internal method, supports pagination, sorting
	 *
	 **/
	function _search()
	{
		//records to show per page
		$per_page = 100;
				
		//current page
		$offset=$this->input->get('offset');//$this->uri->segment(4);

		//sort order
		$sort_order=$this->input->get('sort_order') ? $this->input->get('sort_order') : 'DESC';
		$sort_by=$this->input->get('sort_by') ? $this->input->get('sort_by') : 'logtime';

		//filter
		$filter=NULL;

		//simple search
		if ($this->input->get_post("keywords") ){
			$filter[0]['field']=$this->input->get_post('field');
			$filter[0]['keywords']=$this->input->get_post('keywords');			
		}		
		
		//records
		$rows=$this->Sitelog_model->search($per_page, $offset,$filter, $sort_by, $sort_order);

		//total records in the db
		$total = $this->Sitelog_model->search_count();

		if ($offset>$total)
		{
			$offset=$total-$per_page;
			
			//search again
			$rows=$this->Sitelog_model->search($per_page, $offset,$filter, $sort_by, $sort_order);
		}
		
		//set pagination options
		$base_url = site_url('admin/logs');
		$config['base_url'] = $base_url;
		$config['total_rows'] = $total;
		$config['per_page'] = $per_page;
		$config['query_string_segment']="offset"; 
		$config['page_query_string'] = TRUE;
		$config['additional_querystring']=get_querystring( array('keywords', 'field','sort_by','sort_order'));//pass any additional querystrings
		$config['num_links'] = 5;
		$config['full_tag_open'] = '<span class="page-nums">' ;
		$config['full_tag_close'] = '</span>';
		$config['last_link'] = '&raquo;';
		$config['first_link'] = '&laquo;';
		
		//intialize pagination
		$this->pagination->initialize($config); 
		return $rows;		
	}
	
	
	/**
	* show file logs
	**/
	function file_logs($year=NULL,$month=NULL, $day=NULL)
	{
		$log_file='logs/log-'.date("Y-mm-dd",date("U")).'.php';
		
		if (is_numeric($year) && is_numeric($month) && is_numeric($day))
		{
			$log_file="logs/log-$year-$month-$day.php";
		}
		
		$this->load->view("sitelogs/log_file",array('log_file'=>$log_file));		
	}
	
	/**
	* Export to csv
	**/
	function export()
	{
		$query=$this->db->query('select top 10000 * from sitelogs');
		
		$csv = fopen('backup/sitelogs-'.date("U").'.csv', 'w');

		foreach($query->result() as $row)
		{
			$row=(array)$row;
			fputcsv($csv, array_values($row),"\t");
		}
		
		fclose($csv);
	}
}

/* End of file logs.php */
/* Location: ./system/application/controllers/logs.php */