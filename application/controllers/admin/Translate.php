<?php
class Translate extends MY_Controller {
 
 	//base/template language
	var $base_lang='english';
	
 
    public function __construct()
    {
        parent::__construct();
		$this->template->set_template('admin5');

		$this->lang->load("general");
		$this->load->library('translator');
		$this->acl_manager->has_access_or_die('translate', 'edit');
		//$this->output->enable_profiler(TRUE);
    }
 
 	function index()
	{
		$data['title']=t('translate');
		$data['languages']=$this->translator->get_languages_array();
		$content=$this->load->view("translator/index_admin",$data,true);
		
		$this->template->write('title', $data['title'],true);
		$this->template->write('content', $content,true);
	  	$this->template->render();	
	}
 
	function edit($language=NULL,$translation_file=NULL)
	{	
		if (!$language)
		{
			show_error("NO_LANGUAGE_SELECTED");
		}
	
		//check if language exists
		if (!$this->translator->language_exists($language))
		{
			show_error('INVALID_LANGUAGE');
		}

		$data['edit_file_fullpath']=$this->translator->translation_file_path($language,$translation_file, true);		
		$data['save_status']=NULL;
		
		if ($this->input->post('save'))
		{
			//save form
			$data['save_status']=$this->_save($language,$translation_file);
		}
		
		$data['title']=t('translate');
		$data['active_lang_file']=$translation_file;
		$data['languages']=$this->translator->get_languages_array();
		$data['language']=$language;
		$data['rtl_languages']=array('arabic');
		$data['files']=$this->translator->get_language_files_array(APPPATH.'/language/english');
		
		//check if base translation file exists
		$data['template_file']=$this->translator->load($this->base_lang.'/'.$translation_file);				
		//$data['edit_file']=$this->translator->load($language.'/'.$translation_file);
		$data['edit_file']=$this->lang->load($translation_file, $language, true);
		
		if (!is_array($data['edit_file']))
		{
			$data['edit_file']=array();
		}

		$content=$this->load->view("translator/edit",$data,true);
		
		//set page options and render output
		$this->template->write('title', $data['title'],true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}
	
	
	function _save($language,$translation_file)
	{
		if (!$this->input->post('save'))
		{
			return FALSE;
		}

		//save the file
		$output_file=$this->translator->translation_file_path($language,$translation_file,true);

		$data['language']=$language;
		$data['template_file']=$this->translator->load($this->base_lang.'/'.$translation_file);
		$data['fill_missing']=false;
		$data['language_file']=$translation_file;
		
		$post_data=array();
		
		//reload data from POST for language
		foreach($data['template_file'] as $key=>$value)
		{
			if ($this->input->post(nada_hash($key)))
			{
				$post_data[$key]=$this->input->post(nada_hash($key));
			}
		}				
		
		$data['edit_file']=$post_data;
		$output =$this->load->view('translator/preview',$data,TRUE);
		
		//make a backup copy of the existing file
		@copy($output_file,$output_file.'.bak');
		
		//save file
		$file_contents="<?php \n"; 
		$file_contents.=$output;

		//create language folder if not exists
		if(!file_exists(dirname($output_file))){
			mkdir(dirname($output_file), 0755, true);
		}
		
		$result=@file_put_contents($output_file, $file_contents);
		
		if (!$result)
		{
			return array(
					'type'=>'error',
					'msg'=>'Could not save file. '.$output_file,
					);
		}
		else
		{
			return array(
				'type'=>'success',
				'msg'=>'File has been saved. '.$output_file,
				);

		}	
	
	}
	
	function change_lang()
	{
		$lang=$this->input->get_post("lang");
		
		if ($lang)
		{
			redirect("admin/translate/edit/".$lang);exit;
		}
	}
	
	function download($language)
	{
		$this->translator->export($language);
	}
	
}
/* End of file translate.php */
/* Location: ./controllers/admin/translate.php */