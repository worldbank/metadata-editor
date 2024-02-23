<?php
class MY_Controller extends CI_Controller
{	
    public $_ci_plugins = array();
    var $_ci_ob_level;
    public $_ci_view_path      = '';
    var $_ci_library_paths  = array();
    var $_ci_model_paths    = array();
    var $_ci_helper_paths   = array();
    var $_base_classes      = array(); // Set by the controller class
    var $_ci_cached_vars    = array();
    var $_ci_classes        = array();
    var $_ci_loaded_files   = array();
    var $_ci_models         = array();
    var $_ci_helpers        = array();
    var $_ci_varmap         = array('unit_test' => 'unit', 'user_agent' => 'agent');
	
	var $is_admin=TRUE;
	
	/**
	* Manages both admin/non-admin users
	*
	* @skip			skip authentication  (true=skip authentication)
	* @is_admin		requires the user to have admin rights
	*/
	public function __construct($skip=FALSE,$is_admin=TRUE)
	{		
		parent::__construct();
		
		//test if application is installed
		$this->is_app_installed();

		$this->site_maintenance();
	
		//switch language
		$this->_switch_language();
		$this->lang->load("general");
		$this->load->model('Permissions_model');
			
		$this->load->library(array('site_configurations','session','ion_auth','form_validation','acl_manager'));	
		$this->is_admin=$is_admin;
		
		//require authentication for protected pages e.g. admin	
		if ($skip===FALSE){
		   
		   //apply IP restrictions for site administration
		   $this->apply_ip_restrictions();
		   
		   //apply server host name restrictions for site administration
		   $this->apply_hostname_restrictions();
		   
			//check user is logged in or not
			$this->_auth();
			
			//get user object with all user info
			$user=$this->ion_auth->current_user();
		
			if (!$user){
				return FALSE;
			}
			
			//check user has access to the url
			/*if (!$this->acl->user_has_url_access() ){
				show_error(t('ACCESS_DENIED'));
			}*/
			
			if ($this->config->item("otp_verification")===1){
				if ($this->session->userdata('verify_otp')!==1){
					//otp expired or not set?
					if (date("U")>$user->otp_expiry || !$user->otp_code){
						$this->ion_auth->send_otp_code($user->id);
					}
					redirect('auth/verify_code');
				}
			}
		}
	}

	

	/**
	 * 
	 * Show offline message during maintenance
	 * 
	 * 
	 */
	function site_maintenance()
	{
		$offline=$this->config->item("maintenance_mode");
		
		if($offline!==1){
			return true;
		}

		$allowed_urls=array('auth');

		if ($this->ion_auth->logged_in() 
			//&& $this->ion_auth->is_admin()
		){
			return true;
		}

		if (in_array($this->uri->segment(1), $allowed_urls)){
			return true;
		}

		echo $this->load->view('static/offline',null,true);
		die();
	}




	/**
	 * 
	 * Apply IP restrictions for Site Admin
	 * 
	 */
	 public function apply_ip_restrictions()
	 {
		$user_ip=$this->input->ip_address();  		
		$ip_list=$this->config->item("admin_allowed_ip");
		
		if ($ip_list!==FALSE)
		{
		  if (is_array($ip_list) && count($ip_list)>0)
		  {
			  //check ip is in the allowed list  
			  if (!in_array($user_ip, $ip_list))
			  {
				 //log
				 $this->db_logger->write_log('blocked','site access blocked from ip:'.$user_ip,'access-blocked');
				 
				 //show page not found  
				 show_404(); 
			  }  
		  }     
		} 
	 }

	  
	/**
	 * 
	 * Restrict access to site administration based on 
	 * HOSTNAME used for accessing the site
	 * 
	 */
	 public function apply_hostname_restrictions()
	 {	 	
		$http_host=$this->input->server("HTTP_HOST");
		$allowed_hosts=$this->config->item("admin_allowed_hosts");
		
		$http_host=explode(":",$http_host);
		$http_host=$http_host[0];
		
		  if (is_array($allowed_hosts) && count($allowed_hosts)>0)
		  {
			  //check host is in the allowed list  
			  if (!in_array($http_host, $allowed_hosts))
			  {
				 //log
				 $this->db_logger->write_log('blocked',sprintf('site access blocked from ip [%s], using host [%s]',$this->input->ip_address(),$http_host),'host-access-blocked');
				 
				 //show page not found  
				 show_404(); 
			  }  
		  }     
	 } 
	 
    
	/**
	* Switch site language using cookies
	*
	**/
	function _switch_language()
	{
		if($this->session->userdata('language'))
		{	
	        //switch language
			$this->config->set_item('language',$this->session->userdata('language'));
		}
	}
	
	/**
	*
	*
	* check if user is logged in or not
	**/
	function _auth()
	{
		$destination=$this->uri->uri_string();
		
		//check if ajax is set
		if ($this->input->get_post("ajax"))
		{
			$destination.='/?ajax='.$this->input->get_post("ajax");
		}
		//check if print is set
		if ($this->input->get_post("print"))
		{
			$destination.='/?print='.$this->input->get_post("print");
		}
				
		$this->session->set_userdata("destination",$destination);

		//not logged in
    	if (!$this->ion_auth->logged_in()) 
		{
			//check ajax requests
			if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
			{
				header('HTTP/1.0 401 Unauthorized');
				exit;
			}			
		
			//redirect them to the login page
			redirect("auth/login/?destination=$destination", 'refresh');
    	}
    	/*elseif (!$this->ion_auth->can_access_site_admin() && $this->is_admin==TRUE ) 
		{
			log_message('error', 'MY_CONTROLLER::_auth::access denied for user: '.$this->ion_auth->current_user_identity());
			show_error("Access denied");
    	}*/
	}

	//get public site menu
	function _menu()
	{
		$data['menus']= $this->Menu_model->select_all();		
		$content=$this->load->view('default_menu', $data,true);
		return $content;
	}
	
 	/**
	* Test if app is properly installed and can connect to db
	*/
 	function is_app_installed()
	{
		$this->load->database();
		
		//check if database connection settings are filled in
		if ($this->db->dbdriver=='' || $this->db->username=='' || $this->db->database=='')
		{
			show_error('You have not setup a database');
		}
		
		//test reading from database tables
		$this->db->limit(1);
		$query=$this->db->get('configurations');
		
		if ($query)
		{
			return TRUE;
		}
				
		//test database connection only if everything else above has failed
		switch($this->db->dbdriver)
		{
			case 'mysql':
				$conn_id = @mysql_connect($this->db->hostname, $this->db->username, $this->db->password, TRUE);
				break;
			case 'mysqli':
				$conn_id = mysqli_connect($this->db->hostname, $this->db->username, $this->db->password);								
				break;	
			case 'postgre':
				$conn_id=@pg_connect("host={$this->db->hostname} user={$this->db->username} password={$this->db->password} connect_timeout=5 dbname=postgres");
				break;
			case 'sqlsrv':
				$auth_info = array( "UID"=>$this->db->username,"PWD"=>$this->db->password);
				$conn_id = @sqlsrv_connect($this->db->hostname, $auth_info);
				break;
			case 'oci8':
				$conn_id = @oci_connect($this->db->username, $this->db->password, $this->db->hostname);
				break;				
			default:
				show_error('MY_CONTROLLER::database not supported '.$this->db->dbdriver);
		}

		if (!$conn_id ) 
        {
            //cannot connect to database server
			show_error('Failed to connect to database, check database settings');
        } 
        else //can connect to db server but not to the database
        {
            redirect("install");
        }
	}

	
}	