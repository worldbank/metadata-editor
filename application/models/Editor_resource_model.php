<?php


/**
 * 
 * Editor resources and files
 * 
 */
class Editor_resource_model extends ci_model {

	private $documentation_types=array(
		'documentation'=>'documentation',
		'data' => 'data',
		'thumbnail' => ''
	);
 
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Editor_model');
    }

    /**
	*
	* Return all resources attached to a survey
	*
	* @fields - comma seperated list of field names
	*
	**/
	function select_all($sid,$fields=null)
	{
		if(!empty($fields)){
			$this->db->select($fields);
		}else{
			$this->db->select('*');
		}
		$this->db->where('sid',$sid);
		return $this->db->get('editor_resources')->result_array();
	}


    function select_single($sid,$resource_id)
	{
		$this->db->select('*');
		$this->db->where('sid',$sid);
        $this->db->where('id',$resource_id);
		return $this->db->get('editor_resources')->row_array();
	}


    function delete($sid,$resource_id)
	{
		$this->delete_file_by_resource($sid,$resource_id);

		$this->db->where('sid',$sid);
        $this->db->where('id',$resource_id);
		return $this->db->delete('editor_resources');
	}


	/**
	 * 
	 * Return all resources with files attached to a survey
	 * 
	 */
	function get_resources_uploaded_files($sid,$fields=null)
	{
		$result=$this->files($sid);

		$files=[];		
        foreach($result as $file){
            $file_=explode("/",substr($file,1));
            if (count($file_)==1){
                $files["_"][]=$file_[0];
            }else{
                $files[$file_[0]][]=$file_[1];
            }            
        }

		//only files from documentation folder are considered
		if (isset($files['documentation'])){
			$files=$files['documentation'];
		}

		//external resources
        $resources=$this->select_all($sid,$fields=null);
        $resources_by_filename=[];
        foreach($resources as $resource){
            $resources_by_filename[$resource['filename']]=$resource;
        }

        if (isset($files)){
            foreach($files as $key=>$file){
                if (isset($resources_by_filename[$file])){
                    $files[$key]=array(
                        'file'=>$file,
                        'resource'=>$resources_by_filename[$file]
                    );
                }else{
					$files[$key]=array(
                        'file'=>$file,
                        'resource'=>false
                    );
				}
            }
        }

		return $files;
	}

    /**
	 * 	
	 *
	 * upload external resource file
	 *
	 * @sid - survey id
     * @file_type data | documentation
	 * @file_field_name 	- name of POST file variable
	 *  
	 **/ 
	function upload_file($sid,$file_type='documentation',$file_field_name='file',$remove_spaces=true)
	{
        $survey_folder=$this->Editor_model->get_project_folder($sid);

        if (!$survey_folder){
            $this->Editor_model->create_project_folder($sid);
            $survey_folder=$this->Editor_model->get_project_folder($sid); 
        }
		
		if (!file_exists($survey_folder)){
			throw new Exception('EDITOR_FOLDER_NOT_FOUND: '.$survey_folder);
		}

        $survey_folder_type=$survey_folder.'/'.$file_type;
        @mkdir($survey_folder_type, 0777, $recursive=true);

        if (!file_exists($survey_folder_type)){
			throw new Exception('EDITOR_SUB_FOLDER_NOT_FOUND: '.$survey_folder_type);
		}

		//upload class configurations for RDF
		$config['upload_path'] = $survey_folder_type;
		$config['overwrite'] = true;
		$config['encrypt_name']=false;
		$config['remove_spaces'] = $remove_spaces;//convert spaces or not
		$config['allowed_types'] = str_replace(",","|",$this->config->item("allowed_resource_types"));

		
		$this->load->library('upload', $config);
		//$this->upload->initialize($config);

		//process uploaded rdf file
		$upload_result=$this->upload->do_upload($file_field_name);

		if (!$upload_result){
			throw new Exception($this->upload->display_errors());
		}

		return $this->upload->data();		
	}


    public function upload_temporary_file($allowed_file_type,$file_field_name='file',$temp_upload_folder=null)
    {
        if (!$temp_upload_folder){
            $temp_upload_folder='datafiles/tmp';
        }

		if (!file_exists($temp_upload_folder)){
			@mkdir($temp_upload_folder);
		}
		
		if (!file_exists($temp_upload_folder)){
			show_error('TEMP-FOLDER-NOT-SET');
		}

		//upload class configurations for DDI
		$config['upload_path'] 	 = $temp_upload_folder;
		$config['overwrite'] 	 = FALSE;
		$config['encrypt_name']	 = TRUE;
		$config['allowed_types'] = $allowed_file_type;

		$this->load->library('upload', $config);

		//upload
		$upload_result=$this->upload->do_upload($file_field_name);

		if (!$upload_result){
			$error = $this->upload->display_errors();
			throw new Exception($error);
		}
		else //successful upload
		{
			//get uploaded file information
			$uploaded_path = $this->upload->data();
			$uploaded_path=$uploaded_path['full_path'];
            return $uploaded_path;
		}
    }

	function upload_thumbnail($sid,$file_field_name='file')
	{
        $survey_folder=$this->Editor_model->get_project_folder($sid);

        if (!$survey_folder){
            $this->Editor_model->create_project_folder($sid);
            $survey_folder=$this->Editor_model->get_project_folder($sid); 
        }
		
		if (!file_exists($survey_folder)){
			throw new Exception('EDITOR_FOLDER_NOT_FOUND: '.$survey_folder);
		}

		//upload class configurations for RDF
		$config['upload_path'] = $survey_folder;
		$config['overwrite'] = true;
		$config['encrypt_name']=false;
		$config['remove_spaces'] = true;
		$config['allowed_types'] = "jpg|jpeg|png|gif";
		
		$this->load->library('upload', $config);
		$upload_result=$this->upload->do_upload($file_field_name);

		if (!$upload_result){
			throw new Exception($this->upload->display_errors());
		}

		$file_uploaded= $this->upload->data();
		$thumbnail_path=$file_uploaded["file_path"].'thumbnail-'.$sid.$file_uploaded['file_ext'];
		rename($file_uploaded["full_path"],$thumbnail_path);
		$file_uploaded['thumbnail_path']=$thumbnail_path;

		return array(
			'thumbnail_path'=>$thumbnail_path,
			'thumbnail_filename'=>basename($thumbnail_path)
		);
	}


	/**
	 * 
	 * Delete thumbnail
	 * 
	 */
	function delete_thumbnail($sid)
	{
		$thumbnail_path=$this->Editor_model->get_thumbnail_file($sid);

		if (!$thumbnail_path){
			return false;
		}

		if (file_exists($thumbnail_path)){
			unlink($thumbnail_path);
		}

		return true;
	}

	

	/**
	 * 	
	 *
	 * Upload data 
	 *
	 * @sid - survey id
     * @file_id file id
	 * @append - 0=false, 1=true - if false, replace data
	 *  
	 **/ 
	function upload_data($sid,$file_id,$file_field_name='file', $append=false)
	{
        $survey_folder=$this->Editor_model->get_project_folder($sid);

        if (!$survey_folder){
            $this->Editor_model->create_project_folder($sid);
            $survey_folder=$this->Editor_model->get_project_folder($sid); 
        }
		
		if (!file_exists($survey_folder)){
			throw new Exception('EDITOR_FOLDER_NOT_FOUND: '.$survey_folder);
		}

		$datafile=$this->Editor_model->data_file_by_id($sid,$file_id);
			
		if (!$datafile){
			throw new Exception("DATAFILE_NOT_FOUND: ".$file_id);
		}

		$filename=$datafile['file_name'];		

        $survey_folder_type=$survey_folder.'/data';
        @mkdir($survey_folder_type, 0777, $recursive=true);

        if (!file_exists($survey_folder_type)){
			throw new Exception('EDITOR_SUB_FOLDER_NOT_FOUND: '.$survey_folder_type);
		}

		$csv_file_path=$survey_folder.'/data/'.$filename.'.csv';

		//upload class configurations for RDF
		$config['upload_path'] = $survey_folder_type;
		$config['overwrite'] = true;
		$config['encrypt_name']=false;
		$config['file_name']=$filename.'.csv';
		$config['remove_spaces'] = true; //convert spaces or not
		$config['allowed_types'] = str_replace(",","|",$this->config->item("allowed_resource_types"));
		
		$this->load->library('upload', $config);
		//$this->upload->initialize($config);

		//process uploaded rdf file
		$upload_result=$this->upload->do_upload($file_field_name);

		if (!$upload_result){
			throw new Exception($this->upload->display_errors());
		}

		return $this->upload->data();		
	}

    /**
	*
	* Import RDF file
	**/
	public function import_rdf($surveyid,$filepath)
	{
		//check file exists
		if (!file_exists($filepath)){
			throw new Exception("FILE-NOT-FOUND: ".$filepath);
		}
		
		//read rdf file contents
		$rdf_contents=file_get_contents($filepath);
			
		//load RDF parser class
		$this->load->library('RDF_Parser');
			
		//parse RDF to array
		$rdf_array=$this->rdf_parser->parse($rdf_contents);

		if ($rdf_array===FALSE || $rdf_array==NULL){
			return FALSE;
		}

		//Import
		$rdf_fields=$this->rdf_parser->fields;

		$output=array(
			'added'=>0,
			'skipped'=>0
		);

		//success
		foreach($rdf_array as $rdf_rec)
		{
			$insert_data['sid']=$surveyid;
			
			foreach($rdf_fields as $key=>$value)
			{
				if ( isset($rdf_rec[$rdf_fields[$key]]))
				{
					$insert_data[$key]=trim($rdf_rec[$rdf_fields[$key]]);
				}	
			}
			
			//check filenam is URL?
			$insert_data['filename']=$this->normalize_filename($insert_data['filename']);

            if(isset($insert_data['type'])){
                $insert_data['dctype']=$insert_data['type'];
            }

            //insert into db
            $this->insert($insert_data);
            $output['added']++;
		}
	
		return $output;
	}

    /**
	*
	* Import RDF file
	**/
	public function import_json($sid,$filepath)
	{
		//check file exists
		if (!file_exists($filepath)){
			throw new Exception("FILE-NOT-FOUND: ".$filepath);
		}
		
		//read rdf file contents
		$resources=json_decode(file_get_contents($filepath),true);

        if (isset($resources["resources"])){
            $resources=$resources["resources"];
        }

        $output=array(
			'added'=>0,
			'skipped'=>0
		);

        foreach($resources as $resource){

            if ($this->validate_resource($resource)){

                $resource['sid']=$sid;

                //get dctype by code
                if(isset($resource['dctype'])){ 
                    //$resource['dctype']=$this->get_dctype_label_by_code($resource['dctype']);
                }

                /*if(isset($options['dcformat'])){ 
                    $options['dcformat']=$this->Survey_resource_model->get_dcformat_label_by_code($options['dcformat']);
                }*/

                //validate resource
                if ($this->validate_resource($resource)){
                    $resource_id=$this->insert($resource);
                    $output['added']++;
                }
            }
        }    
        
        return $output;
	}


	function normalize_filename($filename)
	{
		//check filenam is URL?
		if (!is_url($filename))
		{
			//clean file paths
			$filename=unix_path($filename);
			
			//keep only the filename, remove path
			return basename($filename);
		}

		return $filename;
	}


    /**
	* update external resource
	*
	*	resource_id		int
	* 	options			array
	**/
	function update($resource_id,$options)
	{
		//allowed fields
		$valid_fields=array(
			'sid',
			'dctype',
			'title',
			'subtitle',
			'author',
			'dcdate',
			'country',
			'language',
			//'id_number',
			'contributor',
			'publisher',
			'rights',
			'description',
			'abstract',
			'toc',
			'subjects',
			'filename',
			'dcformat',
			'changed');

		//add date modified
		$options['changed']=date("U");
					
		if (isset($options['filename'])){
			$options['filename']=$this->normalize_filename($options['filename']);
		}
		
		$update_arr=array();

		//build update statement
		foreach($options as $key=>$value)
		{
			if (in_array($key,$valid_fields) )
			{
				$update_arr[$key]=$value;
			}
		}
		
		//update db
		$this->db->where('id', $resource_id);
		$result=$this->db->update('editor_resources', $update_arr); 
		
		return $result;		
	}
	
	
	/**
	* 
	*	Add external resource
	*
	**/
	function insert($options)
	{
		//allowed fields
		$valid_fields=array(
			'sid',
			'dctype',
			'title',
			'subtitle',
			'author',
			'dcdate',
			'country',
			'language',
			//'id_number',
			'contributor',
			'publisher',
			'rights',
			'description',
			'abstract',
			'toc',
			'subjects',
			'filename',
			'dcformat',
			'changed');

		$options['changed']=date("U");

		//remove slash before the file path otherwise can't link the path to the file
		if (isset($options['filename'])){
			if (substr($options['filename'],0,1)=='/'){
				$options['filename']=substr($options['filename'],1,255);
			}
		}
		
		if (isset($options['dctype'])){
            $dctype_code=$this->get_dctype_code_from_string($options['dctype']);
            $options['dctype']=$this->get_dctype_label_by_code($dctype_code);
		}
		if (isset($options['format'])){
			$options['dcformat']=$options['format'];
		}
		
		if (isset($options['filename'])){
			$options['filename']=$this->normalize_filename($options['filename']);
		}

		$data=array();

		//build update statement
		foreach($options as $key=>$value){
			if (in_array($key,$valid_fields)){
				$data[$key]=$value;
			}
		}

		$this->db->insert('editor_resources', $data); 		
		return $this->db->insert_id();
	}

    /**
	 * 
	 * 
	 * Return the dctype code from text
	 * 
	 * e.g. Document [doc/adm] will return doc/adm
	 * 
	 */
	function get_dctype_code_from_string($dctype)
	{
		preg_match_all("/\[([^\]]*)\]/", $dctype, $matches);
		$result= $matches[1];
		if ($result){
			return $result[0];
		}
		return $dctype;
	}

    /**
	* returns DC Types
	*
	*
	**/
	function get_dc_types()
	{
		$result= $this->db->get('dctypes')->result_array();

		$list=array();
		foreach($result as $row){
			$list[$row['title']]=$row['title'];
		}
		
		return $list;
	}


    /**
	 * 
	 * 
	 * Return the DCTYPE label by code
	 * 
	 * 
	 */
	function get_dctype_label_by_code($dctype)
	{
		$codes=array(
			'doc/adm'=>'Document, Administrative [doc/adm]',
			'doc/anl'=>'Document, Analytical [doc/anl]',
			'doc/oth'=>'Document, Other [doc/oth]',
			'doc/qst'=>'Document, Questionnaire [doc/qst]',
			'doc/ref'=>'Document, Reference [doc/ref]',
			'doc/rep'=>'Document, Report [doc/rep]',
			'doc/tec'=>'Document, Technical [doc/tec]',
			'aud'=>'Audio [aud]',
			'dat'=>'Database [dat]',
			'map'=>'Map [map]',
			'dat/micro'=>'Microdata File [dat/micro]',
			'pic'=>'Photo [pic]',
			'prg'=>'Program [prg]',
			'tbl'=>'Table [tbl]',
			'vid'=>'Video [vid]',
			'web'=>'Web Site [web]'
		);

		if(array_key_exists($dctype,$codes)){
			return $codes[$dctype];
		}
		
		return $dctype;
	}

	/**
	 * 
	 * 
	 * Return the dcformat label by code
	 * 
	 * 
	 */
	function get_dcformat_label_by_code($dcformat)
	{
		$codes=array(
			'application/x-compressed'=>'Compressed, Generic []',
			'application/zip'=>'Compressed, ZIP',
			'application/x-cspro'=>'Data, CSPro',
			'application/dbase'=>'Data, dBase',
			'application/msaccess'=>'Data, Microsoft Access',
			'application/x-sas'=>'Data, SAS',
			'application/x-spss'=>'Data, SPSS',
			'application/x-stata'=>'Data, Stata',
			'text'=>'Document, Generic',
			'text/html'=>'Document, HTML',
			'application/msexcel'=>'Document, Microsoft Excel',
			'application/mspowerpoint'=>'Document, Microsoft PowerPoint',
			'application/msword'=>'Document, Microsoft Word',
			'application/pdf'=>'Document, PDF',
			'application/postscript'=>'Document, Postscript',
			'text/plain'=>'Document, Plain',
			'text/wordperfect'=>'Document, WordPerfect',
			'image/gif'=>'Image, GIF',
			'image/jpeg'=>'Image, JPEG',
			'image/png'=>'Image, PNG',
			'image/tiff'=>'Image, TIFF'
		);

		if(array_key_exists($dcformat,$codes)){
			return $codes[$dcformat] . ' ['.$dcformat.']';
		}
		
		return $dcformat;
	}


    /**
	 * 
	 * 
	 * Validate resource
	 * @options - array of resource fields
	 * 
	 **/
	function validate_resource($options,$is_new=true)
	{		
		$this->load->library("form_validation");
		$this->form_validation->reset_validation();
		$this->form_validation->set_data($options);
	
		//validate form input
		if(!$is_new){
		}

		//below rules only get applied if inserting a new record or filled in when updating a record
		if($is_new || (!$is_new && isset($options['dctype']) )) {
			$this->form_validation->set_rules('dctype', 'Resource Type', 'xss_clean|trim|max_length[100]|required');
			$this->form_validation->set_rules('title', 'Title', 'xss_clean|trim|max_length[255]|required');
			$this->form_validation->set_rules('url', 'URL', 'xss_clean|trim|max_length[255]');	
		}

        if ($this->form_validation->run() == TRUE){
			return TRUE;
		}
		
		//failed
		$errors=$this->form_validation->error_array();
		$error_str=$this->form_validation->error_array_to_string($errors);
		throw new ValidationException("VALIDATION_ERROR: ".$error_str, $errors);
	}


	/**
	 * 
	 * Return all files for the project
	 * 
	 */
    function files($sid) 
    {
        $this->load->helper("file");
        $project_folder=$this->Editor_model->get_project_folder($sid);
        $result=get_dir_recursive($project_folder,$make_relative_to=$project_folder);
        return $result['files'];        
    }


	/**
	 * 
	 * return project files as a nested tree
	 */
	function files_tree($sid) 
    {
        $this->load->helper("file");
        $project_folder=$this->Editor_model->get_project_folder($sid);
        $result=get_dir_recursive($project_folder,$make_relative_to=$project_folder);
        return $result['files'];        
    }

	/**
	 * 
	 * 
	 * Return all files with sizes
	 * 
	 * @details - true/false - return file details
	 * 
	 * 
	 */
	function files_with_sizes($sid,$details=false)
	{
		$this->load->helper("file");
		$project_folder=$this->Editor_model->get_project_folder($sid);
		$result=get_dir_size($project_folder,$details);
		return $result;        
	}


	function get_resource_file_by_name($sid,$filename)
	{
		$project_folder=$this->Editor_model->get_project_folder($sid);		
		$resource_file=$project_folder.'/documentation/'.$filename;

		if (file_exists($resource_file)){
			return $resource_file;
		}

		return false;
	}


	/**
	 * 
	 * Return all files with info
	 * 
	 * 
	 */
	function files_summary($sid)
    {        
        $result['files']=$this->files($sid);

        $files=array();
        foreach($result['files'] as $file){
            $file_=explode("/",substr($file,1));
            if (count($file_)==1){
                $files["_"][]=$file_[0];
            }else{
                $files[$file_[0]][]=$file_[1];
            }            
        }

		/*if (is_array($files['data'])){
        	sort($files['data']);
		}*/

		// return $files;

		//external resources
        $resources=$this->select_all($sid,$fields=null);
        $resources_by_filename=[];
        foreach($resources as $resource){
            $resources_by_filename[$resource['filename']]=$resource;
        }

        if (isset($files['documentation'])){
            foreach($files['documentation'] as $key=>$file){				
                if (array_key_exists($file,$resources_by_filename)){
                    $files['documentation'][$key]=array(
                        'file'=>$file,
                        'resource'=>$resources_by_filename[$file]
                    );
                }else{
					$files['documentation'][$key]=array(
                        'file'=>$file,
                        'resource'=>false
                    );
				}
            }
        }

		$files['external_resources']=$resources;

		//data files
		$data_files=$this->Editor_datafile_model->select_all($sid);

		$data_files_by_name=array(); 
		if (is_array($data_files)){
			foreach($data_files as $key=>$file){
				$data_files_by_name[$file['file_name']]=$file;
			}
		}

		$files['data_files']=$data_files_by_name;

       return $files;
    }

	/**
	 * 
	 * Generate resources JSON and save to project folder
	 * 
	 */
	function write_json($sid)
	{
		$project=$this->Editor_model->get_basic_info($sid);
		$project_folder=$this->Editor_model->get_project_folder($sid);

		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("write_json::Project folder not found");
		}		

		$resources=$this->Editor_resource_model->select_all($sid,$fields=null);

		$remove_fields=array("sid","id");
		foreach($resources as $idx=>$resource){
			foreach($remove_fields as $f){
				if (isset($resources[$idx][$f])){
					unset($resources[$idx][$f]);
				}
			}
		}

		$filename=trim($project['idno'])!=='' ? trim($project['idno']) : md5($project['id']);
		$filename.='.rdf.json';

		$path = $this->Editor_model->get_project_folder($sid);
		$resource_file=$path.'/'.$filename;

		if (file_exists($resource_file)){
			unlink($resource_file);
		}

		file_put_contents($resource_file,json_encode($resources,JSON_PRETTY_PRINT));		
		return $resource_file;
	}


    function write_rdf($sid)
    {
		$project=$this->Editor_model->get_basic_info($sid);
        $path = $this->Editor_model->get_project_folder($sid);

		if (!$path || !file_exists($path)){
			throw new Exception("write_rdf::Project folder not found");
		}

		$filename=trim($project['idno'])!=='' ? trim($project['idno']) : md5($project['id']);
		$filename.='.rdf';
		$resource_file=$path.'/'.$filename;

        if (file_exists($resource_file)){
            unlink($resource_file);
        }

        $rdf_xml=$this->generate_rdf($sid);
		file_put_contents($resource_file,$rdf_xml);

        return $resource_file;
    }

    /**
     * 
     * Generate RDF xml file
     */
	function generate_rdf($id)
	{		
		$rows=$this->select_all($id);
		
		$line_br="\r\n";
		
		$rdf='<?xml version=\'1.0\' encoding=\'UTF-8\'?>'.$line_br;
		$rdf.='<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">'.$line_br;
		
		foreach($rows as $row)
		{
			$row=(object)$row;	
			$rdf.=sprintf('<rdf:Description rdf:about="%s">',htmlentities($row->filename,ENT_QUOTES,'UTF-8'));
			$rdf.='<rdf:label><![CDATA['.$row->title.']]></rdf:label>';
			$rdf.='<dc:title><![CDATA['.$row->title.']]></dc:title>';
			
			if ($row->author)
			{
				$rdf.='<dc:creator><![CDATA['.$row->author.']]></dc:creator>';
			}	
			if ($row->publisher)
			{			
				$rdf.='<dc:publisher><![CDATA['.$row->publisher.']]></dc:publisher>';
			}
			if ($row->contributor)
			{
				$rdf.='<dc:contributor><![CDATA['.$row->contributor.']]></dc:contributor>';
			}	
			if ($row->dcdate)
			{
				$rdf.='<dcterms:created>'.$row->dcdate.'</dcterms:created>';
			}	
			if ($row->dcformat)
			{
				$rdf.='<dc:format><![CDATA['.$row->dcformat.']]></dc:format>';
			}	
			if ($row->dctype)
			{
				$rdf.='<dc:type><![CDATA['.$row->dctype.']]></dc:type>';
			}	
			if ($row->country)
			{
				$rdf.='<dcterms:spatial><![CDATA['.$row->country.']]></dcterms:spatial>';
			}	
			if ($row->description)
			{
				$rdf.='<dc:description><![CDATA['.$row->description.']]></dc:description>';							
			}	
			if ($row->toc)
			{
				$rdf.='<dcterms:tableOfContents><![CDATA['.$row->toc.']]></dcterms:tableOfContents>';
			}	
			if ($row->abstract)
			{
				$rdf.='<dcterms:abstract><![CDATA['.$row->abstract.']]></dcterms:abstract>';
			}
			$rdf.='</rdf:Description>'.$line_br;
		}
		$rdf.='</rdf:RDF>';
		
		return $rdf;
	}


	/**
	 * 
	 * Check if a file exists
	 * 
	 */
	function check_file_exists($sid,$documentation_type,$filename)
	{
		$project_folder=$this->Editor_model->get_project_folder($sid);
		$resource_file=$project_folder.'/'.$documentation_type.'/'.$this->normalize_filename($filename);

		if (file_exists($resource_file)){
			return true;
		}

		return false;
	}


	/**
	 * 
	 * Delete a file
	 * 
	 */
	function delete_file($sid,$documentation_type,$filename)
	{
		$project_folder=$this->Editor_model->get_project_folder($sid);
		$resource_file=$project_folder.'/'.$documentation_type.'/'. $this->normalize_filename($filename);

		if (file_exists($resource_file)){
			unlink($resource_file);
		}
	}


	/**
	 * 
	 * Delete file by resource id
	 * 
	 */
	function delete_file_by_resource($sid,$resource_id)
	{
		$resource=$this->select_single($sid,$resource_id);

		if ($resource){
			$this->delete_file($sid,'documentation',$resource['filename']);
		}
	}


	function unzip_resource_file($sid,$resource_id)	
	{
		$resource=$this->select_single($sid,$resource_id);

		if (!$resource || !$resource['filename']){
			throw new Exception("Resource not found or filename not set");
		}

		//get resource filename extension
		$ext=pathinfo($resource['filename'], PATHINFO_EXTENSION);

		//get file name without extension
		$filename=pathinfo($resource['filename'], PATHINFO_FILENAME);

		if ($ext!=='zip'){
			throw new Exception("Not a zip file");
		}
		
		$project_folder=$this->Editor_model->get_project_folder($sid);
		$resource_file=$project_folder.'/documentation/'.$resource['filename'];

		$zip = new ZipArchive;
		$res = $zip->open($resource_file);
		if ($res === TRUE) {
			$zip->extractTo($project_folder.'/tmp/'.$filename);
			$zip->close();
			return true;
		} else {
			throw new Exception("Failed to extract zip file");
		}		
	}

	function unzip_file($sid,$file_name)
	{
		$project_folder=$this->Editor_model->get_project_folder($sid);
		
		$file_name=substr($file_name,1);
		$file_parts=explode("/",$file_name);

		if (!in_array($file_parts[0],array_keys($this->documentation_types))){
			throw new Exception("Invalid file path");
		}

		$file_path=$project_folder.'/'.$file_name;

		if (!file_exists($file_path)){
			throw new Exception("File not found");
		}

		$ext=pathinfo($file_path, PATHINFO_EXTENSION);
		$basename_no_ext=pathinfo($file_path, PATHINFO_FILENAME);

		if ($ext!=='zip'){
			throw new Exception("Not a zip file");
		}

		$zip = new ZipArchive;
		$res = $zip->open($file_path);
		if ($res === TRUE) {
			$zip->extractTo($project_folder.'/tmp/'.$basename_no_ext);
			$zip->close();
			return true;
		} else {
			throw new Exception("Failed to extract zip file");
		}		
	}

}    