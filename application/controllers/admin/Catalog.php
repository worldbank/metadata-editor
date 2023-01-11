<?php
/**
 * Catalog Maintenance Controller
 *
 * handles all Catalog Maintenance pages
 *
 */
class Catalog extends MY_Controller {

	var $active_repo=NULL; //active repo object

  	public function __construct()
	{
      	parent::__construct();
     	$this->load->model('Catalog_model');
		$this->load->model('Licensed_model');
		$this->load->model('Form_model');
		$this->load->model('Data_classification_model');
		$this->load->model('Repository_model');
		$this->load->model('Citation_model');
		$this->load->model('Search_helper_model');
		$this->load->model('Catalog_admin_search_model');
		$this->load->library('pagination');
		$this->load->helper('querystring_helper','url');
		$this->load->helper('form');
		//$this->load->helper("catalog");
		$this->template->set_template('admin'); 
		$this->load->library("Dataset_manager");

		//load language file
		$this->lang->load('general');
		$this->lang->load('catalog_search');
		$this->lang->load('catalog_admin');
		$this->lang->load('permissions');
		$this->lang->load('resource_manager');

		//$this->output->enable_profiler(TRUE);
		//$this->acl->clear_active_repo();

		//set active repo
		$repo_obj=(object)$this->Repository_model->select_single($this->Repository_model->user_active_repo());

		if (empty($repo_obj) || $this->Repository_model->user_active_repo()==0){
			//set active repo to CENTRAL
			$data=$this->Repository_model->get_central_catalog_array();
			$this->active_repo=(object)$data;
		}
		else{
			//set active repo
			$this->active_repo=$repo_obj;
			$data=$this->Repository_model->get_repository_by_repositoryid($repo_obj->repositoryid);
		}

		//set collection sticky bar options
		$collection=$this->load->view('repositories/repo_sticky_bar',$data,TRUE);
		$this->template->add_variable($name='collection',$value=$collection);
	}



	/**
	 * Default page
	 *
	 */
	function index()
	{
		$this->template->set_template('admin5'); 
		//css files
		$inline_styles=$this->load->view('catalog/catalog_style',NULL, TRUE);
        $this->template->add_css($inline_styles,'embed');


		//js files
		$this->template->add_js('var site_url="'.site_url().'";','embed');
		$this->template->add_js('javascript/catalog_admin.js');

		//set filter on active repo
		if (isset($this->active_repo) && $this->active_repo!=null){
			$this->Catalog_model->active_repo=$this->active_repo->repositoryid;
		}

		$this->acl_manager->has_access_or_die('study', 'view',null,$this->active_repo->repositoryid);

		//get surveys
		$db_rows=$this->_search();

		//get survey tags
		$this->catalog_tags=$this->Catalog_model->get_all_survey_tags($this->active_repo->repositoryid);

		//get country list for filter
		$this->catalog_countries=$this->Catalog_model->get_all_survey_countries($this->active_repo->repositoryid);

		//data access types
		$this->data_access_types=$this->Form_model->get_all();
		//data types
		$this->catalog_data_types=$this->Search_helper_model->get_dataset_types($this->active_repo->repositoryid); 

		if ($db_rows['rows'])
		{
			$sid_list=array();
			foreach($db_rows['rows'] as $row) 
			{
				$sid_list[]=$row['id'];
			}

			//get citations per study
			$citations=$this->Citation_model->get_citations_count_by_survey_list($sid_list);

			foreach($db_rows['rows'] as $key=>$row)
			{
				if (array_key_exists($row['id'],$citations))
				{
					$db_rows['rows'][$key]['citations']=$citations[$row['id']];
				}
			}
		}

		$db_rows['active_repo_obj']=$this->active_repo;
		$content=$this->load->view('catalog/index', $db_rows,true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}
	

	function search()
	{		
		if (isset($this->active_repo) && $this->active_repo!=null){
			$this->Catalog_model->active_repo=$this->active_repo->repositoryid;
		}

		$this->acl_manager->has_access_or_die('study', 'view',null,$this->active_repo->repositoryid);

		$data= $this->_search();
		$data['active_repo_obj']=$this->active_repo;
		$this->load->view('catalog/search', $data);
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
		$per_page = $this->input->get("ps");

		if($per_page===FALSE || !is_numeric($per_page))
		{
			$per_page=15;
		}

		//current page
		$curr_page=$this->input->get('per_page');//$this->uri->segment(4);

		//filter to further limit search
		$filter=array();

		/*if (isset($this->active_repo) && $this->active_repo!=null)
		{
			$filter=array('repositoryid'=>$this->active_repo->repositoryid);
		}*/

		$search_options=array();

		foreach($_GET as $key=>$value)
		{
			$search_options[$key]=$this->input->get($key,TRUE);
		}

		$this->Catalog_admin_search_model->set_active_repo($this->active_repo->repositoryid);

		//survey rows
		$surveys=$this->Catalog_admin_search_model->search($search_options,$per_page,$curr_page, $filter);

		$survey_id_array=array();

		if(is_array($surveys))
		{
			foreach($surveys as $row)
			{
				$survey_id_array[]=$row['id'];
			}

			//survey repository owners/links
			$survey_repos=(array)$this->Repository_model->get_survey_repositories($survey_id_array);

			$survey_lic_pending=$this->Licensed_model->get_pending_requests_count($survey_id_array);

			//attach survey repositories
			foreach($surveys as $key=>$row)
			{
				$surveys[$key]['repositories']=FALSE;

				if (array_key_exists($row['id'],$survey_repos))
				{
					$surveys[$key]['repositories']=$survey_repos[$row['id']];
				}

				if (is_array($survey_lic_pending) && array_key_exists($row['id'],$survey_lic_pending))
				{
					$surveys[$key]['pending_lic_requests']=$survey_lic_pending[$row['id']];
				}
			}
		}


		$data['rows']=$surveys;

		//total records in the db
		$total = $this->Catalog_admin_search_model->search_count;

		if ($curr_page>$total)
		{
			$curr_page=$total-$per_page;

			//search again
			$data['rows']=$this->Catalog_admin_search_model->search($search_options,$per_page,$curr_page, $filter);
		}

		//set pagination options
		$base_url = site_url('admin/catalog');
		$config['base_url'] = $base_url;
		$config['total_rows'] = $total;
		$config['per_page'] = $per_page;
		$config['page_query_string'] = TRUE;
		$config['additional_querystring']=get_querystring( array('sort_by','sort_order','keywords', 'field','ps','titl','surveyid','producer','published','nation','tag','no_question','no_datafile','dtype'));//pass any additional querystrings
		$config['next_link'] = t('page_next');
		$config['num_links'] = 5;
		$config['prev_link'] = t('page_prev');
		$config['first_link'] = t('page_first');
		$config['last_link'] = t('last');
		$config['full_tag_open'] = '<span class="page-nums">' ;
		$config['full_tag_close'] = '</span>';

		//intialize pagination
		$this->pagination->initialize($config);

		//survey id array
		$surveys=array();
		if (isset($data['rows']))
		{
			foreach($data['rows'] as $row)
			{
				$surveys[]=$row['id'];
			}

			//load additional data for surveys

			//survey tags
			$survey_tags=$this->Catalog_model->get_tags_by_survey($surveys);

			//attach to surveys
			foreach($data['rows'] as $key=>$row)
			{
				if (array_key_exists($row['id'],$survey_tags))
				{
					$data['rows'][$key]['tags']=$survey_tags[$row['id']];
				}
			}
		}//endif

		$data['total_found']=$total;
		return $data;
	}

	
	//return temp upload folder path
	private function get_temp_upload_folder()
	{
		//catalog folder path
		$catalog_root=$this->config->item("catalog_root");
		
		//if not fixed path, use a relative path
		if (!file_exists($catalog_root) )
		{
			$catalog_root=FCPATH.$catalog_root;
		}

		//create .htaccess if not already exists
		//@file_put_contents($catalog_root.'/.htaccess','deny from all');
		//@chmod($catalog_root.'/.htaccess',0444);

		$temp_upload_folder=$catalog_root.'/tmp';

		if (!file_exists($temp_upload_folder))
		{
			@mkdir($temp_upload_folder);
		}

		if (!file_exists($temp_upload_folder))
		{
			show_error('DATAFILES-TEMP-FOLDER-NOT-SET');
		}

		return $temp_upload_folder;
	}


	function upload()
	{
		$this->add_study();
	}

	/**
	 * Upload form for DDI (xml) file
	 *
	 * @return void
	 **/
	function add_study()
	{
		//user has permissions on the repo
		//$this->acl->user_has_repository_access($this->active_repo->id);
		$this->acl_manager->has_access_or_die('study', 'create',null,$this->active_repo->repositoryid);

		$this->template->set_template('admin');

		//show upload form when no DDI is uploaded
		if(!$this->input->post("submit")){
			$content=$this->load->view('catalog/ddi_upload_form', array('active_repo'=>$this->active_repo),true);
			$this->template->write('content', $content,true);
	  		$this->template->render();
			return;
		}

		$overwrite=$this->input->post("overwrite");
		$repositoryid=$this->input->post("repositoryid");

		if($overwrite=='yes'){
			$overwrite=TRUE;
		}
		else{
			$overwrite=FALSE;
		}

		//process form

		$temp_upload_folder=$this->get_temp_upload_folder();

		//upload class configurations for DDI
		$config['upload_path'] 	 = $temp_upload_folder;
		$config['overwrite'] 	 = FALSE;
		$config['encrypt_name']	 = TRUE;
		$config['allowed_types'] = 'xml';

		$this->load->library('upload', $config);

		//process uploaded ddi file
		$ddi_upload_result=$this->upload->do_upload();

		$uploaded_ddi_path=NULL;

		//ddi upload failed
		if (!$ddi_upload_result){
			$error = $this->upload->display_errors();
			$this->db_logger->write_log('ddi-upload',$error,'catalog');
			$this->session->set_flashdata('error', $error);
			redirect('admin/catalog/add_study','refresh');
		}
		else //successful upload
		{
			//get uploaded file information
			$uploaded_ddi_path = $this->upload->data();
			$uploaded_ddi_path=$uploaded_ddi_path['full_path'];
			$this->db_logger->write_log('ddi-upload','success','catalog');
		}

		$this->load->model("Data_file_model");
		$this->load->library('DDI2_import');
		
		$user=$this->ion_auth->current_user();

		$ddi_path=$uploaded_ddi_path;
		$params=array(
			'file_type'=>'survey',
			'file_path'=>$ddi_path,
			'user_id'=>$user->id,
			'repositoryid'=>$repositoryid,
			'overwrite'=>$overwrite
		);

		try{
			//import ddi
			$result=$this->ddi2_import->import($params);

			//import rdf
			$rdf_result=$this->upload_rdf_file($result['sid']);

			$this->events->emit('db.after.update', 'surveys', $result['sid'],'refresh');
			$this->session->set_flashdata('success', $result);
			redirect('admin/catalog/edit/'.$result['sid'],'refresh');return;
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);

			$error_str='Validation Error<br/><pre class="error-pre">'.print_r($e->GetValidationErrors(),true).'</pre>';			
			$this->session->set_flashdata('error', $error_str);
			redirect('admin/catalog/add_study','refresh');return;
		}
		catch(Exception $e){
			$this->session->set_flashdata('error', $e->getMessage());
			redirect('admin/catalog/add_study','refresh');return;
		}
	}


	private function upload_rdf_file($sid)
	{
		$this->load->library('catalog_admin');
		
		//upload class configurations for RDF
		$config['upload_path'] = $this->get_temp_upload_folder();
		$config['overwrite'] = FALSE;
		$config['encrypt_name']=TRUE;
		$config['allowed_types'] = 'rdf';

		$this->upload->initialize($config);

		//process uploaded rdf file
		$rdf_upload_result=$this->upload->do_upload('rdf');

		$uploaded_rdf_path='';

		if ($rdf_upload_result)
		{
			$uploaded_rdf_path = $this->upload->data();
			$uploaded_rdf_path=$uploaded_rdf_path['full_path'];
		}

		if ($uploaded_rdf_path!="")
		{
			//import rdf
			$this->catalog_admin->import_rdf($sid,$uploaded_rdf_path);

			//delete rdf
			@unlink($uploaded_rdf_path);
		}

		return true;
	}

	
	/**
	 * 
	 * Sanitize file name
	 */
	private function sanitize_filename($name)
	{
		return preg_replace('/[^a-zA-Z0-9-_\.]/','-',$name);
	}

	/**
	* Imports an uploaded DDI file or batch import
	*
	*/
	function batch_refresh()
	{
		//$this->acl->user_has_repository_access($this->active_repo->id);
		$this->acl_manager->has_access_or_die('study', 'batch_refresh',null,$this->active_repo->repositoryid);

		//list of all surveys
		$data['surveys']=$this->Catalog_model->select_all_compact('survey');
		//show
		$contents=$this->load->view('catalog/ddi_batch_refresh',$data,TRUE);
		$this->template->write('content', $contents,true);
	  	$this->template->render();
	}
	

	/**
	*
	* Refresh DDI Information in the database
	*
	* Note: Useful for updating study information in the database for existing DDIs
	**/
	function refresh($id=NULL)
	{		
		//$this->acl->user_has_repository_access($this->active_repo->id);
		$this->acl_manager->has_access_or_die('study', 'refresh',null,$this->active_repo->repositoryid);

		if (!is_numeric($id)){
			show_404();
		}

		$this->load->model("Dataset_model");
		$this->load->model("Data_file_model");
		$this->load->library('DDI2_import');


		$is_ajax=$this->input->get("ajax");

		//get survey ddi file path by id
		$ddi_file=$this->Catalog_model->get_survey_ddi_path($id);

		if ($ddi_file===FALSE){
			if($is_ajax==FALSE){
				show_error('DDI_NOT_FOUND');
			}
			else{
				die (json_encode(array('error'=>'DDI_NOT_FOUND' )));
			}
		}
		
		$user=$this->ion_auth->current_user();
		$dataset=$this->Dataset_model->get_row($id);

		$params=array(
			'file_type'=>'survey',
			'file_path'=>$ddi_file,
			'user_id'=>$user->id,
			'repositoryid'=>$dataset['repositoryid'],
			'overwrite'=>'yes'
		);

		try{			
			$result=$this->ddi2_import->import($params,$id);

			//reset changed and created dates
			$update_options=array(
				'changed'=>$dataset['changed'],
				'created'=>$dataset['created'],
				'repositoryid'=>$dataset['repositoryid']
			);

			$this->Dataset_model->update_options($id,$update_options);
			$this->events->emit('db.after.update', 'surveys', $id,'refresh');

			if ($is_ajax){
				die (json_encode(array('success'=>'UPDATED: '.$id) ));
			}
	
			$this->session->set_flashdata('success', $result);			
			redirect('admin/catalog/edit/'.$id,'refresh');return;
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);

			$error_str='Validation Error<br/><pre class="error-pre">'.print_r($e->GetValidationErrors(),true).'</pre>';
			
			if ($is_ajax){
				die (json_encode(array('error'=>$error_str) ));
			}

			$this->session->set_flashdata('error', $error_str);
			redirect('admin/catalog/edit/'.$id,'refresh');return;
		}
		catch(Exception $e){
			if ($is_ajax){
				die (json_encode(array('error'=>$e->getMessage()) ));
			}
			$this->session->set_flashdata('error', $e->getMessage());
			redirect('admin/catalog/edit/'.$id,'refresh');return;
		}
	}

	

	/**
	*
	* Clear files from the imports folder
	**/
	function clear_import_folder()
	{		
		$this->load->helper('file');
		$import_folder=$this->config->item('ddi_import_folder');

		if (!file_exists($import_folder) )
		{
			show_error('IMPORT-FOLDER-NOT-SET');
		}

		//read files
		$files['files']=get_dir_file_info($import_folder);

		if ( $files['files'])
		{
			foreach($files['files'] as $key=>$value)
			{
				if (in_array(substr($value['name'],-4),array('.xml','.rdf')) )
				{
					unlink($value['server_path']);
				}
			}
		}

		redirect('admin/catalog/batch_import');
	}


	/**
	*
	* Upload files to the IMPORTS folder
	**/
	function process_batch_uploads()
	{
		//import folder path
		$import_folder=$this->config->item('ddi_import_folder');

		if (!file_exists($import_folder)){
			show_error('FOLDER-NOT-SET');
		}

		$config = array(
				'max_tmp_file_age' 		=> 900,
				'max_execution_time' 	=> 300,
				'target_dir' 			=> $import_folder,
				'allowed_extensions'	=>'xml|rdf',
				'overwrite_file'		=>TRUE
				);

		$this->load->library('Chunked_uploader', $config, 'uploader');

		try
		{
			$this->uploader->upload();

			if ($this->uploader->is_completed())
			{
				$output=array(
					'status'=>'success',
					//'file'=>$this->uploader->get_file_path()
				);
				die ( json_encode($output));
			}
			else
			{
				//echo "still uploading";
			}
		}
		catch (Exception $ex)
		{
			$response = array('error'>$ex->getMessage());
			echo json_encode($response);exit;
		}
	}



	/**
	 * Imports multiple ddi files from the server folder
	 *
	 *
	 * @return void
	 **/
	function batch_import()
	{
		//user has permissions on the repo
		//$this->acl->user_has_repository_access($this->active_repo->id);
		$this->acl_manager->has_access_or_die('study', 'batch_import',null,$this->active_repo->repositoryid);

		$this->load->helper('file');

		//import folder path
		$import_folder=$this->config->item('ddi_import_folder');

		if (!file_exists($import_folder) ){
			$import_folder="/datasets";
		}

		//read files
		$files['files']=get_dir_file_info($import_folder);

		if ( $files['files']){
			foreach($files['files'] as $key=>$value){				
				if (substr($value['name'],-4)!='.xml'){
					unset($files['files'][$key]);
				}
			}
		}
		
		$options=array(
			'repositories'=>$this->Repository_model->select_all(),
			'files'=>$files['files'],
			'active_repository'=>$this->active_repo->repositoryid
		);

		$content=$this->load->view('catalog/ddi_batch_import', $options, true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}

	/**
	* Imports a ddi file using batch import
	*
	* returns the output in JSON format
	*/
	function do_batch_import()
	{
		//get the encoded file path from post
		$encoded_filepath=$this->input->post("id");
		$overwrite=(bool)$this->input->post("overwrite");

		//decode
		$ddi_file=base64_decode($encoded_filepath);

		//check file exists
		if (!file_exists($ddi_file)){
			echo json_encode(array('error'=>t("file_not_found")) );
			exit;
		}

		//repository
		$repositoryid=$this->input->post("repositoryid");

		//validate if user has access to the selected repository
		if (!$this->acl_manager->has_access('study', 'create',null, $repositoryid)){
			echo json_encode(array('error'=>t('REPO_ACCESS_DENIED')) );
			exit;
		}
		
		$this->load->model("Data_file_model");
		$this->load->library('DDI2_import');
		
		$user=$this->ion_auth->current_user();

		$ddi_path=$ddi_file;
		$params=array(
			'file_type'=>'survey',
			'file_path'=>$ddi_path,
			'user_id'=>$user->id,
			'repositoryid'=>$repositoryid,
			'overwrite'=>$overwrite
		);

		try{
			//import ddi
			$result=$this->ddi2_import->import($params);

			//try importing the RDF if exists. The RDF must match the XML file name
			$rdf_result=$this->_import_rdf($result['sid'],str_replace(".xml",".rdf",$result['idno']));

			$msg='<strong>'. $result['idno']. '</strong> - <em>'.$result['varcount'].' '.t('variables').'</em>';
			log_message('info', $msg);

			//return the json success message
			echo json_encode(array('success'=>$msg) );
			exit;
		}
		catch(ValidationException $e){
			$error_output=array(
				'status'=>'failed',
				'message'=>$e->getMessage(),
				'errors'=>$e->GetValidationErrors()
			);
			
			$error=print_r($e->GetValidationErrors(),true);
			echo json_encode(array('error'=>$error) );
			die();
		}
		catch(Exception $e){
			$error=print_r($e->getMessage(),true);
			echo json_encode(array('error'=>$error) );
			die();
		}
	}

	

	/**
	*
	* Import RDF file
	**/
	function _import_rdf($surveyid,$filepath)
	{
		//check file exists
		if (!file_exists($filepath))
		{
			return FALSE;
		}

		//read rdf file contents
		$rdf_contents=file_get_contents($filepath);

		//load RDF parser class
		$this->load->library('RDF_Parser');
		$this->load->model('Survey_resource_model');

		//parse RDF to array
		$rdf_array=$this->rdf_parser->parse($rdf_contents);

		if ($rdf_array===FALSE || $rdf_array==NULL)
		{
			return FALSE;
		}

		//Import
		$rdf_fields=$this->rdf_parser->fields;

		//success
		foreach($rdf_array as $rdf_rec)
		{
			$insert_data['survey_id']=$surveyid;

			foreach($rdf_fields as $key=>$value)
			{
				if ( isset($rdf_rec[$rdf_fields[$key]]))
				{
					$insert_data[$key]=trim($rdf_rec[$rdf_fields[$key]]);
				}
			}

			//check if it is not a URL
			if (!is_url($insert_data['filename']))
			{
				//clean file paths
				$insert_data['filename']=unix_path($insert_data['filename']);

				//remove slash before the file path otherwise can't link the path to the file
				if (substr($insert_data['filename'],1,1)=='/')
				{
					$insert_data['filename']=substr($insert_data['filename'],2,255);
				}
			}

			//check if the resource already exists for the study
			$resource_exists=$this->Survey_resource_model->survey_resource_exists($surveyid,$insert_data['title'],$insert_data['type'],$insert_data['filename']);

			if (!$resource_exists)
			{
				//insert into db
				$this->Survey_resource_model->insert($insert_data);
			}
		}
	}

	function delete($id)
	{
		$this->acl_manager->has_access_or_die('study', 'delete',null,$this->active_repo->repositoryid);
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
				redirect('admin/catalog');
			}
		}
		else
		{
			$delete_arr[]=$id;
		}

		//test user has permission to delete study or not
		//$this->acl->user_has_study_access($id);		

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
				redirect('admin/catalog');
			}
		}
		else if ($this->input->post('submit')!='')
		{
			foreach($delete_arr as $item)
			{
				//get survey info
				$survey=$this->Catalog_model->get_survey($item);

				//delete if exists
				if ($survey)
				{
					//delete survey and related data from other tables
					$this->Catalog_model->delete($item);
					$this->events->emit('db.after.delete', 'surveys', $item,'delete');

					//log deletion
					$survey_name=$survey['idno']. ' - '.$survey['title'].' - '. $survey['year_start'].' - '. $survey['nation'];
					$this->db_logger->write_log('study-deleted',$survey_name,'catalog',$item);
				}
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
				//redirect($destination);
			}
			else
			{
				redirect('admin/catalog');
			}
		}
		else
		{
			$items=array(); //list of deleted items

			foreach($delete_arr as $item)
			{
				//get survey info
				$survey=$this->Catalog_model->get_survey($item);

				//exists
				if ($survey)
				{
					//log deletion
					$survey_name=$survey['idno']. ' - '.$survey['title'].' - '. $survey['year_start'].' - '. $survey['nation'];
					$items[]=$survey_name;
				}
			}

			//ask for confirmation
			$content=$this->load->view('resources/delete', array('deleted_items'=>$items),true);

			$this->template->write('content', $content,true);
	  		$this->template->render();
		}
	}

	/**
	*
	* Export External Resources as RDF
	**/
	function export_rdf($id=NULL)
	{
		$this->load->helper('download');
		$data=$this->Catalog_model->get_survey_rdf($id);
		force_download('rdf-'.$id.'.rdf', $data);
		//application/rdf+xml
	}


	



	/**
	* Returns survey DDI file
	* as .xml or .zip
	*
	*/
	function ddi($id=NULL)
	{
		if (!is_numeric($id))
		{
			show_404();
		}

		$format=$this->input->get("format");

		//required for getting ddi file path
		$this->load->model('Catalog_model');
		$this->load->helper('download');

		//get ddi file path from db
		$ddi_file=$this->Catalog_model->get_survey_ddi_path($id);

		if ($ddi_file===FALSE)
		{
			show_404();
		}

		if (file_exists($ddi_file))
		{
			if($format=='zip')
			{
				$this->load->library('zip');

				//zip file path
				$zip_file=$ddi_file.'.zip';

				//create zip if not created already
				if (!file_exists($zip_file))
				{
					$this->zip->read_file($ddi_file);
					$this->zip->archive($zip_file);
				}

				//download zip file
				if (file_exists($zip_file))
				{
					force_download2($zip_file);
					return;
				}
			}

			//download the xml file
			force_download2($ddi_file);
			return;
		}
		else
		{
			show_404();
		}
	}



	/**
	*
	* Replace a DDI
	*
	**/
	function replace_ddi($sid=NULL)
	{
		if (!is_numeric($sid)){
			show_error("ID_INVALID");
		}

		//$this->acl->user_has_study_access($sid);
		$this->acl_manager->has_access_or_die('study', 'replace_ddi',null,$this->active_repo->repositoryid);


		if(!isset($_FILES['userfile'])){			
			$data['id']=$sid;
			$data['survey']=$this->Catalog_model->select_single($sid);

			$content=$this->load->view('catalog/replace_ddi',$data,TRUE);
			$this->template->write('content', $content,true);
			$this->template->render();
			return;
		}

		//no file uploaded?
		if ($_FILES['userfile']['size']==0){		
			$this->session->set_flashdata('error', "NO_FILE_UPLOADED");	
			redirect('admin/catalog/replace_ddi/'.$sid,'refresh');exit;
		}

		//catalog folder path
		$catalog_root=$this->config->item("catalog_root");

		if (!file_exists($catalog_root) ){
			show_error("CATALOG_ROOT_NOT_FOUND");
		}

		$tmp_path=unix_path($catalog_root.'/tmp');

		try
		{
			//upload the ddi
			$upload_result=$this->upload_ddi_file($key='userfile',$destination=$tmp_path);

			if(!$upload_result){				
				$error = $this->upload->display_errors();
				$this->db_logger->write_log('ddi-upload',$error,'catalog');
				throw new Exception($error);
			}

			$this->catalog_admin->replace_ddi($sid,$new_ddi_file=$upload_result['full_path']);
		}
		catch (Exception $e)
		{
			$this->session->set_flashdata('error', $e->getMessage());
			redirect('admin/catalog/replace_ddi/'.$sid,'refresh');			
		}
	}



	/**
	*
	* Export citations as serialized array
	*
	**/
	function export_citations($id=NULL)
	{
		if (!is_numeric($id))
		{
			show_404();
		}

		$this->load->model('Citation_model');

		//get citations by survey id
		$citations=$this->Citation_model->serialize_citations_by_survey($id);

		echo $citations;
	}


	function copy_study()
	{

		//user has permissions on the repo
		//$this->acl->user_has_repository_access($this->active_repo->id);
		$this->acl_manager->has_access_or_die('study', 'view',null,$this->active_repo->repositoryid);
		$this->template->add_js('var site_url="'.site_url().'";','embed');

		if (isset($this->active_repo) && $this->active_repo->repositoryid=='central')
		{
			show_error('ACTION_NOT_ALLOWED');
		}

		$this->load->library('Copy_studies_search');

		$ps=(int)$this->input->get("ps");
		if($ps==0 || $ps>500)
		{
			$ps=50;
		}

		$per_page=(int)$this->input->get("per_page");
		$sort_by=$this->input->get("sort_by");
		$sort_order=$this->input->get("sort_order");

		$search_options=array();

		$search_valid_options=array("keywords","selected_only");

		foreach($_GET as $key=>$value)
		{
			if(in_array($key,$search_valid_options))
			{
				$search_options[$key]=$this->input->get($key);
			}
		}


		$search_options['repositoryid']=$this->active_repo->repositoryid;

		//get an array of survey ID that are already linked in the active collection
		$db_rows['linked_studies']=$this->Repository_model->get_repo_linked_studies($this->active_repo->repositoryid);

		if (isset($search_options['selected_only']))
		{
			$search_options['selected_only']=$db_rows['linked_studies'];
		}

		$total=$this->copy_studies_search->search_count($search_options);
		$db_rows['rows']=$this->copy_studies_search->search($search_options,$limit = $ps, $offset = $per_page,$sort_by,$sort_order);
		$db_rows['active_repo']=$this->active_repo;

		//set filter on active repo
		if (isset($this->active_repo) && $this->active_repo!=null)
		{
			//$filter=$this->Catalog_model->filter;
			$this->Catalog_model->active_repo=$this->active_repo->repositoryid;
			$this->Catalog_model->active_repo_negate=TRUE;
		}


		//set pagination options
		$base_url = site_url('admin/catalog/copy_study');
		$config['base_url'] = $base_url;
		$config['total_rows'] = $total;
		$config['per_page'] = $ps;
		$config['page_query_string'] = TRUE;
		$config['additional_querystring']=get_querystring( array('sort_by','sort_order','keywords', 'field','ps','selected_only'));//pass any additional querystrings
		$config['next_link'] = t('page_next');
		$config['num_links'] = 5;
		$config['prev_link'] = t('page_prev');
		$config['first_link'] = t('page_first');
		$config['last_link'] = t('last');
		$config['full_tag_open'] = '<span class="page-nums">' ;
		$config['full_tag_close'] = '</span>';

		//intialize pagination
		$this->pagination->initialize($config);

		//load the contents of the page into a variable
		$content=$this->load->view('catalog/copy_studies', $db_rows,true);

		//pass data to the site's template
		$this->template->write('content', $content,true);

		//render final output
	  	$this->template->render();
	}


	function do_copy_study($repositoryid=NULL,$sid=NULL)
	{
		if ($repositoryid==NULL || !is_numeric($sid))
		{
			show_404();
		}

		$this->Catalog_model->copy_study($repositoryid,$sid);
		echo json_encode(array('success'=>$repositoryid));
		exit;
	}


	/**
	*
	* Transfer Ownership of a study to another catalog
	*
	* @surveyid  number | string in case of multiple IDs seperated by comma
	**/
	function transfer($surveyid=NULL)
	{
		//user has permissions on the repo
		//$this->acl->user_has_repository_access($this->active_repo->id);
		$this->acl_manager->has_access_or_die('study', 'transfer_ownership',null,$this->active_repo->repositoryid);

		if ($surveyid==NULL && !$this->input->post("sid")){
			show_error("PARAM_MISSING");
		}

		//active repositoryid
		$active_repo=$this->active_repo->repositoryid;

		if (!$active_repo){
			show_error("NO_ACTIVE_REPO_SET");
		}

		if ($this->input->post("sid")){
			$surveys_arr=$this->input->post("sid");
		}
		else{
			$surveys_arr=explode(",",$surveyid);
		}

		$surveys=array();

		//get survey info by id
		foreach($surveys_arr as $id)
		{
			if (is_numeric($id)){
				$survey_row=$this->Catalog_model->get_survey($id);
				if ($survey_row){
					$surveys[$id]=$this->Catalog_model->get_survey($id);
				}
			}
		}

		//postback?
		if ($this->input->post("submit"))
		{
			$repositoryid=$this->input->post("repositoryid");

			//validate repository
			if ($repositoryid=='central'){
				$exists=true;
			}
			else{
				$exists=$this->Catalog_model->repository_exists($repositoryid);
			}

			if (!$exists){
				$this->form_validation->set_error(t('error_no_collection_selected'));
			}
			else{
				foreach($surveys as $key=>$value){
					//transfer ownership
					$this->Catalog_model->transfer_ownership($repositoryid,$key);
				}
				$this->session->set_flashdata('message', t('msg_study_ownership_has_changed'));
				redirect('admin/catalog');
			}
		}

		$options=array(
			'repositories'=>$this->Repository_model->select_all(),
			'surveys'=>$surveys
		);

		$content=$this->load->view('catalog/transfer_ownership',$options,TRUE);
		$this->template->write('content', $content,true);
  		$this->template->render();
	}


	/**
	*
	* Unlink a study from a repository
	*
	**/
	function unlink($repositoryid,$surveyid)
	{
		if (!is_numeric($surveyid))
		{
			show_error("INVALID_ID");
		}

		$result=$this->Catalog_model->unlink_study($repositoryid,$surveyid);

		if ($result!==FALSE)
		{
			$content='Study link was removed successfully!';
		}
		else
		{
			$content='Error: Failed to remove study link';
		}

		$this->session->set_flashdata('message', $content);

		redirect('admin/catalog');
	}

	/**
	*
	* Attach admin/reviewer note to a study
	**/
	function attach_note($sid,$type)
	{
		//$this->output->enable_profiler(TRUE);
		if (!is_numeric($sid))
		{
			show_404();
		}

		$note=$this->input->post("note");

		$result=$this->Catalog_model->attach_note($sid,$note, $note_type=$type);

		if ($result)
		{
			$this->output->set_content_type('application/json');
			$this->output->set_output(json_encode(array('success'=>"updated")));
			return TRUE;
		}

			$this->output->set_content_type('application/json');
			$this->output->set_output(json_encode(array('error'=>"failed")));
	}


	/**
	*
	* Publish/Unpublish studies
	*
	* $id single numeric value or a comma seperated list of IDs
	* TODO: remove - has been replaced by udpate function
	**/
	function publish($id,$publish=1)
	{
		if (!in_array($publish,array(0,1))){
			$publish=1;
		}

		//array of id to be published
		$id_arr=array();

		//is ajax call
		$ajax=$this->input->get_post('ajax');

		if (!is_numeric($id)){
			$tmp_arr=explode(",",$id);
			foreach($tmp_arr as $key=>$value)
			{
				if (is_numeric($value)){
					$id_arr[]=$value;
				}
			}

			if (count($id_arr)==0)
			{
				//for ajax return JSON output
				if ($ajax!=''){
					echo json_encode(array('error'=>"invalid id was provided") );
					exit;
				}

				$this->session->set_flashdata('error', 'Invalid id was provided.');
				redirect('admin/catalog');
			}
		}
		else{
			$id_arr[]=$id;
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
				redirect('admin/catalog');
			}
		}
		else if ($this->input->post('submit')!='')
		{
			foreach($id_arr as $item)
			{
				//get survey info
				$survey=$this->Catalog_model->get_survey($item);
				$this->acl_manager->has_access_or_die('study', 'publish',null,$survey['repositoryid']);

				//if exists
				if ($survey){
					//publish/unpublish a study
					$result=$this->Catalog_model->publish_study($item,$publish);
					$this->events->emit('db.after.update', 'surveys', $item,'publish');
					//log
					$survey_name=$survey['idno']. ' - '.$survey['title'].' - '. $survey['year_start'].' - '. $survey['nation'];
					$this->db_logger->write_log('study-published',$survey_name,'catalog',$item);
				}
			}

			//raise db update event
			//$this->events->emit('db.after.update', 'surveys', $id_arr,'atomic');

			//for ajax calls, return output as JSON
			if ($ajax!=''){
				echo json_encode(array('success'=>"true") );
				exit;
			}

			//redirect page url
			$destination=$this->input->get_post('destination');
			redirect('admin/catalog');			
		}
		else
		{
			$items=array(); //list of items

			foreach($id_arr as $item){				
				$survey=$this->Catalog_model->get_survey($item);
				$this->acl_manager->has_access_or_die('study', 'publish',null,$survey['repositoryid']);

				if ($survey){
					$survey_name=$survey['idno']. ' - '.$survey['title'].' - '. $survey['year_start'].' - '. $survey['nation'];
					$items[]=$survey_name;
				}
			}

			//ask for confirmation
			$content=$this->load->view('catalog/publish_confirm', array('items'=>$items,'publish'=>$publish),true);

			$this->template->write('content', $content,true);
	  		$this->template->render();
		}
	}

	
	
	function create($type=null)
	{
		$this->acl_manager->has_access_or_die('study', 'create',null,$this->active_repo->repositoryid);
		$this->template->set_template('admin5');
		
		if(!$type){
			$type=$this->input->get("type");
		}

		if(!$type){
			show_error("Type not set");
		}

		$sid=$this->Dataset_model->create_new(
			$idno=$this->Dataset_model->GUID(), 
			$type, 
			$repositoryid='central', 
			$title='untitled', 
			$created_by=1
		);

		redirect("admin/catalog/edit/".$sid);
		return;
		
		$template_path="application/metadata_editor_templates/{$type}_form_template.json";
		$schema_path="application/schemas/{$type}-schema.json";

		if(!file_exists($template_path)){
			show_error('Template not found::'. $template_path);
		}

		if(!file_exists($schema_path)){
			show_error('Schema not found::'. $schema_path);
		}
		
		$options['sid']=null;
		$options['survey']=array(
			'title'=>'New survey'
		);

		$options['type']=$type;
		$options['metadata_template']=file_get_contents($template_path);
		$options['metadata_schema']=file_get_contents($schema_path);
		$options['post_url']=site_url('api/datasets/create/'.$type);
		$options['metadata']=array();
		$options['metadata']['merge_options']='replace';
				
		//render
		$content=$this->load->view('metadata_editor/inline',$options, true);
		$this->template->write('content', $content,true);
	  	$this->template->render();		
	}

	function metadata_editor_files($id)
	{
		$survey=$this->dataset_manager->get_row($id);

		if (!$survey){
			show_error('Survey was not found');
		}

		$this->acl_manager->has_access_or_die('study', 'edit',null,$survey['repositoryid']);

		$this->load->model("Data_file_model");

		$options=array(
			'survey'=>$survey,
			'files'=>$this->Data_file_model->get_all_by_survey($id)
		);

		//render
		$this->template->set_template('admin5');
		$content= $this->load->view('metadata_editor/microdata_files',$options,true);
		$this->template->write('content', $content,true);
		$this->template->render();
	}

	function metadata_editor($id=null)
	{
		$survey=$this->dataset_manager->get_row($id);

		if (!$survey){
			show_error('Survey was not found');
		}

		$this->acl_manager->has_access_or_die('study', 'edit',null,$survey['repositoryid']);
				 
		$template_file="{$survey['type']}_form_template.json";
		$template_path=null;
		
		//locations to look for templates
		$template_locations=array(
			'application/metadata_editor_templates/custom',
			'application/metadata_editor_templates',
		);

		//look for template in all locations and pick the first one found
		foreach($template_locations as $path){
			if (file_exists($path.'/'.$template_file)){
				$template_path=$path.'/'.$template_file;
				break;
			}
		}
		
		//$template_path="application/metadata_editor_templates/{$survey['type']}_form_template.json";
		$schema_path="application/schemas/{$survey['type']}-schema.json";

		if(!file_exists($template_path)){
			show_error('Template not found::'. $template_path);
		}

		if(!file_exists($schema_path)){
			show_error('Schema not found::'. $schema_path);
		}

		$metadata_subset=array(
			'repositoryid'=>$survey['repositoryid'],
			'access_policy'=>$survey['data_access_type'],
			'published'=>$survey['published']			
		);
		

		$metadata=$this->dataset_manager->get_metadata($id);

		$options['sid']=$id;
		$options['survey']=$survey;
		$options['type']=$survey['type'];		

		if (!empty($metadata)){
			$options['metadata']=$this->dataset_manager->get_metadata($id);//array_merge($metadata_subset,$this->dataset_manager->get_metadata($id));
		}else{
			$options['metadata']=null;
		}

		if($survey['type']=='geospatial'){
			//show_error('GEOSPATIAL-TYPE-NOT-SUPPORTED');
		}

		//fix schema elements with mixed types
		if ($survey['type']=='survey'){
			//coll_mode
			$coll_mode=array_data_get($options['metadata'], 'study_desc.method.data_collection.coll_mode');
			if(!empty($coll_mode) && !is_array($coll_mode)){
				set_array_nested_value($options['metadata'],'study_desc.method.data_collection.coll_mode',(array)$coll_mode,'.');
			}
		}


		$options['metadata_template']=file_get_contents($template_path);
		$options['metadata_schema']=file_get_contents($schema_path);
		$options['post_url']=site_url('api/datasets/update/'.$survey['type'].'/'.$survey['idno']);
		//$options['metadata']=array();
		$options['metadata']['merge_options']='replace';
		$options['sub_section']=$this->uri->segment(6);

		var_dump($options['sub_section']);
				
		//render
		$this->template->set_template('admin5');
		$content= $this->load->view('metadata_editor/inline',$options,true);
		$this->template->write('content', $content,true);
		$this->template->render();
	}

	function widgets($sid)
	{
		$this->load->model("Widget_model");
		$options['widget_storage_root']='files/embed/';
		$options['widgets']=$this->Widget_model->widgets_by_study($sid);
		
		$content= $this->load->view('widgets/related_widgets', $options,TRUE);
		$this->template->write('content', $content,true);
		$this->template->render();
	}

	/**
	 * Edit survey - by id
	 *
	 * @return void
	 *
	 **/
	function edit($id=NULL)
	{
		if ( !is_numeric($id)){
			show_error('Invalid parameters were passed');
		}

		$active_repository=FALSE;

		//get active repository
		if (isset($this->active_repo) && $this->active_repo!=NULL){
			$active_repository=$this->active_repo->repositoryid;
		}

		//test user study permissiosn
		//$this->acl->user_has_study_access($id);		

		if ($this->uri->segment(6)=='datafiles'){
			return $this->metadata_editor_files($id);
		}

		if ($this->uri->segment(5)=='metadata'){
			return $this->metadata_editor($id);
		}

		if ($this->uri->segment(5)=='widgets'){
			return $this->widgets($id);
		}

		$this->load->model('Citation_model');
		$this->load->model('Catalog_notes_model');
		$this->load->model('Catalog_tags_model');
		$this->load->model('Survey_alias_model');		
		
		$this->load->library('catalog_admin');
		$this->load->library('chicago_citation');
		
		//$this->load->library('ion_auth');

		$this->load->library("catalog_admin");

		$active_repository=FALSE;

		//get active repository
		if (isset($this->active_repo) && $this->active_repo!=NULL){
			$active_repository=$this->active_repo->repositoryid;
		}
		
		$survey_row=$this->Catalog_model->select_single($id,$active_repository);

		if (!$survey_row){
			show_error('Survey was not found');
		}

		$this->acl_manager->has_access_or_die('study', 'edit',null,$survey_row['repositoryid']);

		$survey_row['survey_id']=$id;
		$survey_row['repositoryid']=$active_repository;
		$survey_row['is_featured']=$this->Repository_model->is_a_featured_study($this->active_repo->id,$id);

		//study warnings
		$survey_row['warnings']=$this->catalog_admin->get_study_warnings($id);

		//get survey countries
		$survey_row['countries']=$this->Catalog_model->get_survey_countries($id);

		//check if survey has citations
		$survey_row['has_citations']=$this->Catalog_model->has_citations($id);

		//get survey files
		$survey_row['files_formatted']=$this->catalog_admin->managefiles($id);

		//get survey files ArrayAccess
		$survey_row['files']=$this->catalog_admin->get_files_array($id);

		//get microdata attached to the study
		$survey_row['microdata_files']=$this->Survey_resource_model->get_microdata_resources($id); 

		//get resources
		//$resources['rows']=$this->catalog_admin->resources($id);
		//$survey_row['resources']=$this->load->view('catalog/study_resources', $resources,true);

		//survey collections for current survey
		$survey_row['collections']=$this->catalog_admin->get_formatted_collections($id,$survey_row['repo']);

		//formatted list of external resources
		$survey_row['resources']=$this->catalog_admin->get_formatted_resources($id);

		//formatted list of data files
		$survey_row['data_files']=array();//$this->catalog_admin->get_formatted_data_files($id);

		//get all study notes
		$survey_row['study_notes']=$this->Catalog_notes_model->get_notes_by_study($id);

		//survey tags
		$tags['tags'] = $this->Catalog_tags_model->survey_tags($id);

		//all tags
		$tags['tag_list']=$this->Catalog_model->get_all_survey_tags();

		$survey_row['tags']=$this->load->view('catalog/admin_tags', $tags, true);

		//other survey IDs
		$survey_aliases = $this->Survey_alias_model->get_aliases($id);
		$survey_row['survey_aliases']=$this->load->view('catalog/survey_aliases', array('rows'=>$survey_aliases), true);
		$survey_row['survey_alias_array']=$survey_aliases;

		//get citations for the current survey
		$selected_citations= $this->Citation_model->get_citations_by_survey($id);

		//TODO: recheck
		//see if the edited citation has citations attached, otherwise assign empty array
		$survey_row['selected_citations_id_arr']=$this->_get_related_citations_array($selected_citations);
		$survey_row['selected_citations'] = $selected_citations;

		//get study relationships
		$this->load->model("Related_study_model");
		$survey_row['related_studies']=$this->Related_study_model->get_relationships($id);

		//array of all relationship types
		$survey_row['relationship_types']=$this->Related_study_model->get_relationship_types_array();

		//pdf documentation for study
		$survey_row['pdf_documentation']=$this->catalog_admin->get_study_pdf($id);

		//Data classifications
		$data_classfications = $this->Data_classification_model->get_all();
		$survey_row['data_classifications']=$data_classfications;
		$survey_row['data_licenses']=$this->Form_model->get_all();

		$this->config->load('data_access');

		//data classifications is enabled?
		$data_classifications_enabled=(bool)$this->config->item("data_classifications_enabled");
		$survey_row['data_classifications_enabled']=$data_classifications_enabled;

		//by default, set classifcation to PUBLIC
		if($data_classifications_enabled==false){
			$survey_row['data_class_id']=$data_classfications['public']['id'];
		}

		$survey_row['data_access_dropdown']=$this->da_by_class($survey_row['data_class_id'],$survey_row['formid'],'html',true);

		$content=$this->load->view('catalog/edit_study', $survey_row,TRUE);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}


	/**
	 * 
	 * Get data access dropdown list by data classification
	 * 
	 * @return_output - if true, return the html
	 */
	function da_by_class($classification_code=null,$da_id=null,$format='html',$return_output=false)
	{
		if(is_numeric($classification_code)){
			$classification=$this->Data_classification_model->get_single($classification_code);
			if(isset($classification['code'])){
				$classification_code=$classification['code'];
			}
		}

		//load data classification + license options
		$this->config->load('data_access');
		$data_access_options=$this->config->item("data_access_options");

		$data_classfications = $this->Data_classification_model->get_all();

		//data access options by classification
		$da_options=isset($data_access_options[$classification_code]) ? $data_access_options[$classification_code] : array();

		$data_access_list=$this->Form_model->get_all();

		$output=array();
		foreach($da_options as $da){
			if(isset($data_access_list[$da])){
				$output[$da]=$data_access_list[$da];
			}
		}

		if($format=="html"){
			$html= $this->load->view('catalog/data_access_dropdown', array('da_list'=>$output,'selected'=>$da_id),TRUE);
			if ($return_output==true) {
				return $html;
			}
			echo $html;
			return;
		}
		
		return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode($output));

	}



	/*
	*
	* Update various study options
	*/
	function update()
	{
		//study id
		$id=$this->input->post("sid");

		if (!is_numeric($id)){
			show_404();
		}
		
		$survey=$this->Catalog_model->get_survey($id);

		if(!$survey){
			show_404();
		}

		//test user study permissiosn
		//$this->acl->user_has_study_access($id);
		$this->acl_manager->has_access_or_die('study', 'edit',null,$survey['repositoryid']);

		//is ajax call
		$ajax=$this->input->get_post('ajax');

		//allowed fields
		$allowed_keys=array('published','formid','link_indicator','link_study','link_da','license_id','data_class_id');

		$options=array();
		foreach($_POST as $key=>$value){
			if (in_array($key,$allowed_keys)){
				$options[$key]=$this->input->post($key);
			}
		}
		
		$options['id']=$id;
		$result=$this->Catalog_model->update_survey_options($options);

		if ($result){
			$this->session->set_flashdata('message', t('form_update_success'));
		}
		else{
			$this->session->set_flashdata('error', t('form_update_failed'));
		}
		//$this->events->emit('db.after.update', 'surveys', $id,'atomic');
		redirect('admin/catalog/edit/'.$id);
	}


	/**
	*
	* Returns formatted selected survey list from session
	*
	* @skey= survey id (internal)
	**/
	function related_citations($skey,$isajax=1)
	{
       	if (!is_numeric($skey)){
			return FALSE;
		}

		$this->load->model('Citation_model');

		//get survey info from db
		$data['related_citations']=$this->Citation_model->get_citations_by_survey($skey);

		$data['survey_id']=$skey;

		//load formatted list
		$output=$this->load->view("catalog/related_citations",$data,TRUE);

		if ($isajax==1)
		{
			echo $output;
		}
		else
		{
			return $output;
		}
	}




	/**
	*
	* Returns an array of Citation IDs
	*
	**/
	function _get_related_citations_array($citations)
	{
		if (!is_array($citations))
		{
			return FALSE;
		}

		$result=array();
		foreach($citations as $citation)
		{
			$result[]=$citation['id'];
		}
		return $result;
	}


	/**
	*
	* add/update related study
	*
	*	@sid_1			parent study id
	*	@sid_2			child studies comma separated list e.g 1,2,3,4
	*	@rel_id			relationship id
	**/
	function update_related_study($sid_1=null,$sid_2=null,$rel_id=null)
	{
		if(!is_numeric($sid_1) || !is_numeric($rel_id))
		{
			show_error("INVALID_PARAMS");
		}

		$sid_2_arr=explode(",",$sid_2);

		foreach($sid_2_arr as $value)
		{
			if(!is_numeric($value))
			{
				show_error("INVALID_PARAM");
			}
		}

		$this->load->model("Related_study_model");
		$this->Related_study_model->update_relationship($sid_1,$sid_2_arr,$rel_id);
	}


	/**
	*
	*	Remove a single study relationship
	**/
	function remove_related_study($sid_1=null,$sid_2=null,$rel_id=null)
	{
		if(!is_numeric($sid_1) || !is_numeric($rel_id) || !is_numeric($sid_2) )
		{
			show_error("INVALID_PARAMS");
		}

		$sid_2_arr=explode(",",$sid_2);

		$this->load->model("Related_study_model");
		$this->Related_study_model->delete_relationship($sid_1,$sid_2,$rel_id=null);

    	echo $this->db->last_query();
	}

	function get_related_studies($sid)
	{
		if(!is_numeric($sid))
		{
			show_error("INVALID-PARAMS");
		}

		$this->load->model("Related_study_model");
		$survey_row['related_studies']=$this->Related_study_model->get_relationships($sid);

		//array of all relationship types
		$survey_row['relationship_types']=$this->Related_study_model->get_relationship_types_array();
		$survey_row['survey_id']=$sid;
		$this->load->view('catalog/related_studies_tab',$survey_row);
	}

	function set_featured_study($repositoryid,$sid,$status)
	{
		$result=$this->Repository_model->set_featured_study($repositoryid,$sid,$status);

		if ($this->input->get('destination')){
			redirect($this->input->get('destination'));
		}

		if ($this->input->get("ajax")){
			echo json_encode(array('status'=>$result));
		}
	}


	//list all featured studies
	function featured_studies()
	{
		$data['featured_studies']=$this->Repository_model->get_all_featured_studies();
		$content=$this->load->view('catalog/featured_studies', $data,TRUE);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}


	/**
	 * 
	 * Attach survey data files to external resources
	 * 
	 */
	function attach_data_file_resources($sid,$file_id)
	{
		$this->load->model("Dataset_model");
		$this->load->model("Survey_resource_model");
		$this->load->model("Data_file_model");
		$this->load->model("Data_file_resources_model");

		$survey=$this->Dataset_model->get_row($sid);

		if(!$survey){
			show_error("DATASET-NOT-FOUND");
		}

		//load data file and resources
		//TODO

		$data['sid']=$sid;
		$data['file_id']=$file_id;
		$data['survey']=$survey;

		//get file info
		$data['file']=$this->Data_file_model->get_file_by_id($sid,$file_id);

		//get all microdata type external resources
		$data['resources']=$this->Survey_resource_model->get_microdata_resources($sid);

		//get current data file and attached resources
		$data['attached_resources']=$this->Data_file_resources_model->get_file_resources($sid,$file_id);

		$content=$this->load->view('catalog/attach_data_file_resources',$data,true);
		
		$this->template->set_template('admin_blank');
		$this->template->write('content', $content,true);
		$this->template->render();
	}

	//process posted form
	function attach_data_file_resources_post($sid,$file_id)
	{
		$this->load->model("Data_file_resources_model");
		$resources=$this->input->post("resource_id");
		$formats=$this->input->post("format");

		$options=array();
		foreach($resources as $idx=>$value)
		{
			echo $value;
			echo "-";
			echo $formats[$idx];

			$options[]=array(
				'resource_id'=>$value,
				'sid'=>$sid,
				'fid'=>$file_id,
				'file_format'=>$formats[$idx]
			);
		}

		//update data file resources links
		$this->Data_file_resources_model->batch_update($sid, $file_id, $options);

		redirect('admin/catalog/attach_data_file_resources/'.$sid.'/'.$file_id);
	}


	/**
	 *
	 * Upload ddi file for ddi replace
	 * 
	 * @return array
	 */
	private function upload_ddi_file($key,$destination)
	{
		if ($_FILES[$key]['size']==0)
		{
			return false;
		}
		$config['encrypt_name']	 = TRUE;
		$config['upload_path'] = $destination;
		$config['allowed_types'] = 'xml';
		$config['overwrite'] = true;
		$this->load->library('upload', $config);
		if ( ! $this->upload->do_upload($key))
		{
			throw new Exception( $this->upload->display_errors() );
		}
		else
		{
			$data = $this->upload->data();
			return $data;
		}
	}


	function doi($sid=null) 
	{
		$this->template->set_template('admin_blank');
 
		$this->load->model("Dataset_model");
		$dataset=$this->Dataset_model->get_row($sid);

		$this->config->load('doi');
		$doi_options=$this->config->item("doi");
		
		$options=array();
		$options['dataset']=$dataset;
		$options['doi_options']=$doi_options;
		$content=$this->load->view('catalog/doi', $options,true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}

	/*
	*
	* Set DOI
	*
	*/
	function update_doi()
	{
		//study id
		$id=$this->input->post("sid");

		if (!is_numeric($id)){
			show_404();
		}
		
		$survey=$this->Catalog_model->get_survey($id);

		if(!$survey){
			show_404();
		}

		//test user study permissiosn
		//$this->acl->user_has_study_access($id);
		$this->acl_manager->has_access_or_die('study', 'edit',null,$survey['repositoryid']);

		//is ajax call
		$ajax=$this->input->get_post('ajax');
		
		$doi=$this->input->post("doi");

		try{
			$result=$this->Dataset_model->assign_doi($id,$doi);
		}
		catch(Exception $e){
			$this->session->set_flashdata('error', $e->getMessage());
			redirect('admin/catalog/edit/'.$id);
		}

		$this->session->set_flashdata('message', t('form_update_success'));		
		
		$this->events->emit('db.after.update', 'surveys', $id,'atomic');
		redirect('admin/catalog/edit/'.$id);
	}
	
}
/* End of file catalog.php */
/* Location: ./controllers/admin/catalog.php */
