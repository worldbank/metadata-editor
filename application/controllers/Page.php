<?php
class Page extends MY_Controller {
 
    public function __construct()
    {
        parent::__construct($skip_auth=TRUE);
		$this->lang->load('general');
		//$this->output->enable_profiler(TRUE);
		
		//set template for print
		if ($this->input->get("print")==='yes'){
			$this->template->set_template('blank');
		}
    }
    
	function index()
	{	
		echo "index";
		return;
		if (in_array($this->uri->segment(1), array('page','pages')) ){
			if ($this->uri->segment(2)!=''){
				//get page data
				$data=$this->Menu_model->get_page($this->uri->segment(2));
			}
			else{
				//this part will never get executed
				return false;				
			}			
		}
		else{		
			//get default home page
			$default_home=$this->config->item("default_home_page");			
			$page_=$this->uri->segment(1);

			//show home page
			if ($page_==false){							
				if ($default_home!==FALSE){
					//check if the page is a link or a static page
					$data=$this->Menu_model->get_page($default_home);
					
					if($data){
						if ($data['linktype']!==0){
							//redirect
							redirect($default_home);return;
						}
					}
					else{
							//redirect
							redirect($default_home);return;
					}
				}

				//no default home page set				
				//get the page with minimum weight to be the home page
				$data=$this->Menu_model->get_page_by_min_weight();

				if ($data){
					//link or page
					if ($data['linktype']!==0){
						//link
						redirect($data['url']);
					}
				}
			}			
			else{
				//static pages in the database
				$data=$this->Menu_model->get_page($page_);
			}
		}
		
		//page not found in the database
		if ( empty($data)){
			if (!$this->static_page()){
				//show 404 page;
				$this->_error_page();
			}	
			return;			
		}
		else{
			//link
			if ($data['linktype']==1){
				if ($this->static_page()!==FALSE){
					return;
				}
			}
		}
		
		$content=$this->load->view('page_index', $data,true);
		$this->template->write('title', $data['title'],true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}
	

	function user_bar()
	{
		$this->load->view('user_bar');
	}
	
	function switch_language($lang=NULL)
	{
		if ($lang==NULL){
			show_404();
		}
		
		$valid_languages=$this->config->item("supported_languages");
		
		if (in_array($lang,$valid_languages))
		{
			//set language in the user session cooke
			$this->session->set_userdata('language',strtolower($lang));
			
			$destination=site_home();
			
			if ($this->input->get("destination")){
				$destination=$this->input->get("destination");
			}			
			redirect($destination);
		}
		else{
			show_error("Invalid Language selected!");
		}
	}
	
	
	
	function _error_page()
	{	
		//check if url mapping is available for the url
		$uri=$this->uri->uri_string();
		
		$this->db->where("source",$uri);
		$result=$this->db->get("url_mappings")->row_array();
		
		if ($result){
			$destination=$result["target"];
			redirect($destination);
			return;
		}
		
		header('HTTP/1.0 404 Not Found');
		$content=$this->load->view("404_page",NULL,TRUE);

		if ($this->input->is_ajax_request()) {
			die($content);
		}

		$this->template->write('title', t('page not found'),true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}
	

	/**
	 * 
	 * Load pages from views
	 * 
	 * All pages are loaded from views/static folder. All pages shipped with NADA
	 * are located in the views/static folder. All user defined pages, must be created
	 * inside the views/static/custom folder. 
	 * 
	 * Overriding NADA static pages
	 * Create or copy the page under views/static/custom folder with the same name
	 * 
	 */
	function static_page()
	{
		$page=$this->uri->segment(1);
		$data=array();

		$this->load->model("repository_model");
		$this->lang->load('catalog_search');
		$this->load->model("stats_model");
		$this->title=$page;

		//user defined custom pages
		if(file_exists('application/views/static/custom/'.$page.'.php')){
			$content=$this->load->view('static/custom/'.$page,null,true);
			$this->template->write('title', $this->title,true);
			$this->template->write('content', $content,true);
			$this->template->render();
			return true;		
		}

		//default nada custom page
		if(file_exists('application/views/static/'.$page.'.php')){
			$content=$this->load->view('static/'.$page,null,true);
			$this->template->write('title', $this->title,true);
			$this->template->write('content', $content,true);
			$this->template->render();
			return true;
		}
	}
}
/* End of file page.php */
/* Location: ./controllers/page.php */