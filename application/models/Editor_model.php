<?php

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;


/**
 * 
 * Metadata editor projects
 * 
 */
class Editor_model extends CI_Model {

	private $storage_path='datafiles/editor';

	private $types=array(
        'survey'=>'microdata',
        'geospatial'=>'geospatial',
        'timeseries'=>'timeseries',
        'document'=>'document',
        'image'=>'image',
        'video'=>'video',
        'table'=>'table',
        'script'=>'script',
        'visualization'=>'visualization'
    );

	private $data_file_fields=array(
		'id',
		'sid',
		'file_id',
		'file_uri',
		'file_name',
		'description', 
		'case_count',
		'var_count',
		'producer',
		'data_checks',
		'missing_data',
		'version',
		'notes',
		'metadata'
	);
 
	//fields for the study description
	private $survey_fields=array(
		'id',
		'type',
		'idno',
		'title',
		'abbreviation',
		'dirpath',
		'metafile',
		'year_start',
		'year_end',
		'published',
		'varcount',
		'created',
		'changed',
		'created_by',
		'changed_by',
		'metadata',
		'thumbnail'
		);
		
	
	private $listing_fields=array(
		'id',
		'type',
		'idno',
		'title',
		'abbreviation',
		'nation',
		'year_start',
		'year_end',
		'published',
		'created',
		'changed',
		'varcount',
		'created_by',
		'changed_by'
		);
	

	private $encoded_fields=array(
		"metadata"
	);

 
    public function __construct()
    {
		parent::__construct();
		$this->load->helper("Array");
		$this->load->library("form_validation");		
		$this->load->model("Editor_variable_model");
	}


	/**
	 * 
	 * Editor projects storage path
	 * 
	 */
	function get_storage_path()
	{
		return $this->storage_path;
	}


	function get_project_folder($sid)
	{
		$this->db->select("dirpath");
		$this->db->where("id",$sid);
		$result=$this->db->get("editor_projects")->row_array();

		if ($result){
			$path=$result['dirpath'];

			if (!empty($path)){
				$storage_root=$this->get_storage_path();
				return $storage_root.'/'.$path;
			}
		}

		return false;
	}


	/**
	 * 
	 * 
	 * create folder for project
	 * 
	 */
	function create_project_folder($sid)
	{
		if (!$sid){
			throw new Exception("create_project_folder::invalid sid");
		}

		$storage_root=$this->get_storage_path();
		$project_folder=$storage_root.'/'.md5($sid);

        //create the repo folder and survey folder
        @mkdir($project_folder, 0777, $recursive=true);

        if(!file_exists($project_folder)){
            throw new Exception("EDITOR_STORAGE_FOLDER_NOT_CREATED:".$storage_root);
        }

        if(!file_exists($project_folder)){
            throw new Exception("PROJECT_FOLDER_NOT_CREATED::CHECK-PERMISSIONS-OR-PATH: ".$project_folder);
        }

		//update db with project folder path
		$options=array(
			'dirpath'=>md5($sid)
		);

		$this->db->where("id",$sid);
		$this->db->update("editor_projects",$options);

        return md5($sid);
	}
	
	
	/**
	 * 
	 * Return all datasets
	 * 
	 * @offset - offset
	 * @limit - number of rows to return
	 * @fields - (optional) list of fields
	 * 
	 */
	function get_all($limit=10,$offset=0, $fields=array())
	{
		if (empty($fields)){
			$fields=$this->listing_fields;
		}
		
		$this->db->select(implode(",",$fields));
		$this->db->order_by('changed','desc');

		if ($limit>0){
			$this->db->limit($limit, $offset);
		}
		
		$result= $this->db->get("editor_projects");
		
		if ($result){
			$result=$result->result_array();
		}else{
			$error=$this->db->error();
			throw  new Exception(implode(", ", $error));
		}

		if($result){
			return $this->decode_encoded_fields_rows($result);
		}

		return false;
	}

	//returns the total 
	function get_total_count()
	{
		return $this->db->count_all('editor_projects');
	}


	/**
	 * 
	 * returns a list of datasets by type
	 * 
	 * 
	 */
	function get_list_by_type($dataset_type=null, $limit=100, $start=0)
	{
		$this->db->select('id,idno');
		
		if($dataset_type){
			$this->db->where('type',$dataset_type);
		}

		if(is_numeric($start)){
			$this->db->where('id>',$start);
		}

		if(!empty($limit)){
			$this->db->limit($limit);
		}

		return $this->db->get("editor_projects")->result_array();
	}


	/**
	 * 
	 * returns a list of datasets by type
	 * 
	 * 
	 */
	function get_list_all($dataset_type=null,$published=1)
	{
		$this->db->select('id,idno,type');
		
		if(!empty($dataset_type)){
			$this->db->where('type',$dataset_type);
		}

		if(!empty($published)){
			$this->db->where('published',$published);
		}
		
		return $this->db->get("editor_projects")->result_array();
	}

	
	//decode all encoded fields
	function decode_encoded_fields($data)
	{
		if(!$data){
			return $data;
		}

		foreach($data as $key=>$value){
			if(in_array($key,$this->encoded_fields)){
				$data[$key]=$this->decode_metadata($value);
			}
		}
		return $data;
	}

	//decode multiple rows
	function decode_encoded_fields_rows($data)
	{
		$result=array();
		foreach($data as $row){
			$result[]=$this->decode_encoded_fields($row);
		}
		return $result;
	}


	//encode metadata for db storage
    public function encode_metadata($metadata_array)
    {
        return base64_encode(serialize($metadata_array));
    }


    //decode metadata to array
    public function decode_metadata($metadata_encoded)
    {
        return unserialize(base64_decode($metadata_encoded));
	}

	//get the survey by id
    function get_row($sid)
    {
		$this->db->select("*");		
		$this->db->where("id",$sid);
		
		$survey=$this->db->get("editor_projects")->row_array();
		
		if($survey){
			$survey=$this->decode_encoded_fields($survey);
		}

        return $survey;
	}

	//get project basic info
    function get_basic_info($sid)
    {
		$this->db->select("id,idno,type");		
		$this->db->where("id",$sid);
		
		$survey=$this->db->get("editor_projects")->row_array();
        return $survey;
	}

	/**
	 * 
	 * Check project ID exists
	 */
    function check_id_exists($sid,$type=null)
    {
		$this->db->select("id,type");
		$this->db->where("id",$sid);

		if ($type){
			$this->db->where("type",$type);
		}
		
		$survey=$this->db->get("editor_projects")->row_array();
		
		if($survey){
			return true;
		}

        return false;
	}


	/**
	 * 
	 * Create a new project
	 * 
	 */
	function create_project($type,$options=array())
	{
		if (!array_key_exists($type,$this->types)){
			throw new Exception("INVALID_TYPE: ".$type);
		}

		$options['type']=$type;

		$this->db->insert('editor_projects',$options);
		return $this->db->insert_id();
	}

	function update_project($type,$id,$options=array(),$validate=false)
	{
		if (!array_key_exists($type,$this->types)){
			throw new Exception("INVALID_TYPE: ".$type);
		}

		if ($validate){
			$this->validate_schema($type,$options);
		}

		$options=array(
			'idno'=>$this->get_project_metadata_field($type,'idno',$options),
			'title'=>$this->get_project_metadata_field($type,'title',$options),
			'metadata'=>$this->encode_metadata($options)
		);

		$this->db->where('id',$id);
		$this->db->update('editor_projects',$options);
	}

	function validate_schema($type,$data)
	{
		$schema_file="application/schemas/$type-schema.json";

		if(!file_exists($schema_file)){
			throw new Exception("INVALID-DATASET-TYPE-NO-SCHEMA-DEFINED");
		}

		// Validate
		$validator = new JsonSchema\Validator;
		$validator->validate($data, 
				(object)['$ref' => 'file://' . unix_path(realpath($schema_file))],
				Constraint::CHECK_MODE_TYPE_CAST 
				+ Constraint::CHECK_MODE_COERCE_TYPES 
				+ Constraint::CHECK_MODE_APPLY_DEFAULTS
			);

		if ($validator->isValid()) {
			return true;
		} else {			
			/*foreach ($validator->getErrors() as $error) {
				echo sprintf("[%s] %s\n", $error['property'], $error['message']);
			}*/
			throw new ValidationException("SCHEMA_VALIDATION_FAILED [{$type}]: ", $validator->getErrors());
		}
	}


	function get_project_metadata_field($type,$field,$data)
	{
		$core_fields=array(
			'survey'=>array(
				'idno'=>'study_desc.title_statement.idno',
				'title'=>'study_desc.title_statement.title'				
			)
		);

		if(!array_key_exists($type,$core_fields)){
			return false;
		}

		$field_path=$core_fields[$type][$field];
		return array_data_get($data, $field_path); 
	}


	/**
	 * 
	 * Get all data files by project ID
	 * 
	 */
    function data_files($sid)
    {
        $this->db->select("*");
		$this->db->where("sid",$sid);
		$this->db->order_by('file_name');
		$files=$this->db->get("editor_data_files")->result_array();

		if(empty($files)){
			return false;
		}
		
		//add file_id as key
		$output=array();
		foreach($files as $file){
			$output[$file['file_id']]=$file;
		}

		//apply sorting to keep files in the order - F1, F2...F9, F10, F11
		$file_keys = array_keys($output);
  		natsort($file_keys);

		$sorted_files=array();

  		foreach ($file_keys as $key_){
			$sorted_files[$key_] = $output[$key_];
		}

  		return $sorted_files;
	}

	//get an array of all file IDs e.g. F1, F2, ...
    function data_files_list($sid)
    {
        $this->db->select("file_id");
        $this->db->where("sid",$sid);
		$result=$this->db->get("editor_data_files")->result_array();
		
		$output=array();
		foreach($result as $row){
			$output[]=$row['file_id'];
		}

		return $output;
	}

	//get data file by file_id
    function data_file_by_id($sid,$file_id)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where("file_id",$file_id);
        return $this->db->get("editor_data_files")->row_array();
	}

	function data_file_by_name($sid,$file_name)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
		$file_name=$this->data_file_filename_part($file_name);
        $this->db->where("file_name",$file_name);
        return $this->db->get("editor_data_files")->row_array();
	}

	function data_file_generate_fileid($sid)
    {
        $this->db->select("file_id");
        $this->db->where("sid",$sid);
        $result=$this->db->get("editor_data_files")->result_array();

		if (!$result){
			return 'F1';
		}

		$max=1;
		foreach($result as $row)
		{
			$val=substr($row['file_id'],1);
			if (strtoupper(substr($row['file_id'],0,1))=='F' && is_numeric($val)){
				if ($val >$max){
					$max=$val;
				}
			}
		}

		return 'F'.($max +1);
	}


	function data_file_delete($sid,$file_id)
    {        
        $this->db->where("sid",$sid);
        $this->db->where("file_id",$file_id);
        $this->db->delete("editor_data_files");
		$this->data_file_delete_variables($sid,$file_id);
	}

	function data_file_delete_variables($sid,$file_id)
	{
		$this->db->where("sid",$sid);
        $this->db->where("fid",$file_id);
        return $this->db->delete("editor_variables");
	}


	/**
	*
	* insert new file and return the new file id
	*
	* @options - array()
	*/
	function data_file_insert($sid,$options)
	{		
		$data=array();
		//$data['created']=date("U");
		//$data['changed']=date("U");
		
		foreach($options as $key=>$value){
			if (in_array($key,$this->data_file_fields) ){
				$data[$key]=$value;
			}
		}

		//filename
		if ($data['file_name']){
			$data['file_name']=$this->data_file_filename_part($data['file_name']);
		}

		$data['sid']=$sid;		
		$result=$this->db->insert('editor_data_files', $data);

		if ($result===false){
			throw new MY_Exception($this->db->_error_message());
		}
		
		return $this->db->insert_id();
	}

	/**
	 * 
	 * Get filename without file extension
	 * 
	 */
	function data_file_filename_part($filename)
	{
		$info=pathinfo($filename);
		return $info['filename'];
	}
	
	
	/**
	*
	* update file
	*
	* @options - array()
	*/
	function data_file_update($id,$options)
	{
		$data=array();
		
		foreach($options as $key=>$value)
		{
			if ($key=='id'){
				continue;
			}

			if (in_array($key,$this->data_file_fields) ){
				$data[$key]=$value;
			}
		}

		//filename
		if ($data['file_name']){
			$data['file_name']=$this->data_file_filename_part($data['file_name']);
		}
		
		$this->db->where('id',$id);
		$result=$this->db->update('editor_data_files', $data);

		if ($result===false){
			throw new MY_Exception($this->db->_error_message());
		}
		
		return TRUE;
	}


	/**
	 * 
	 * 
	 * Validate data file
	 * @options - array of fields
	 * @is_new - boolean - for new records
	 * 
	 **/
	function validate_data_file($options,$is_new=true)
	{		
		$this->load->library("form_validation");
		$this->form_validation->reset_validation();
		$this->form_validation->set_data($options);
	
		//validation rules for a new record
		if($is_new){				
			#$this->form_validation->set_rules('surveyid', 'IDNO', 'xss_clean|trim|max_length[255]|required');
			//$this->form_validation->set_rules('file_id', 'File ID', 'required|xss_clean|trim|max_length[50]');	
			$this->form_validation->set_rules('file_name', 'File name', 'required|xss_clean|trim|max_length[200]');	
			$this->form_validation->set_rules('case_count', 'Case count', 'xss_clean|trim|max_length[10]');	
			$this->form_validation->set_rules('var_count', 'Variable count', 'xss_clean|trim|max_length[10]');	

			
			//file id
			$this->form_validation->set_rules(
				'file_id', 
				'File ID',
				array(
					"required",
					"max_length[50]",
					"trim",
					"alpha_dash",
					"xss_clean",
					//array('validate_file_id',array($this, 'validate_file_id')),				
				)		
			);

		}
		
		if ($this->form_validation->run() == TRUE){
			return TRUE;
		}
		
		//failed
		$errors=$this->form_validation->error_array();
		$error_str=$this->form_validation->error_array_to_string($errors);
		throw new ValidationException("VALIDATION_ERROR: ".$error_str, $errors);
	}

	//validate data file ID
	public function validate_file_id($file_id)
	{	
		$sid=null;
		if(array_key_exists('sid',$this->form_validation->validation_data)){
			$sid=$this->form_validation->validation_data['sid'];
		}

		//list of all existing FileIDs
		$files=$this->data_files_list($sid);

		if(in_array($file_id,$files)){
			$this->form_validation->set_message(__FUNCTION__, 'FILE_ID already exists. The FILE_ID should be unique.' );
			return false;
		}

		return true;
	}


	/**
     * 
     * 
     * get all variables attached to a study
     * 
     * @metadata_detailed = true|false - include detailed metadata
     * 
     **/
    function variables($sid,$file_id=null,$metadata_detailed=false)
    {
        if ($metadata_detailed==true){
            $fields="uid,sid,fid,vid,name,labl,metadata";
        }else{
            $fields="uid,sid,fid,vid,name,labl";
        }
        
        $this->db->select($fields);
        $this->db->where("sid",$sid);

        if($file_id){
            $this->db->where("fid",$file_id);
        }

        $variables=$this->db->get("editor_variables")->result_array();

        /*$exclude_metadata=array(
            'var_format',
            'var_sumstat',
            'var_val_range',
            'loc_start_pos',
            'loc_end_pos',
            'loc_width',
            'loc_rec_seg_no',
        );*/

        $exclude_metadata=[];

        if ($metadata_detailed==true){
            foreach($variables as $key=>$variable){
                if(isset($variable['metadata'])){
                    $var_metadata=$this->decode_metadata($variable['metadata']);
                    unset($variable['metadata']);
                    foreach($exclude_metadata as $ex){
                        if (array_key_exists($ex, $var_metadata)){
                            unset($var_metadata[$ex]);
                        }
                    }
                    if (isset($variable['var_catgry']['stats'])){
                        unset($variable['var_catgry']['stats']);
                    }
                    $variables[$key]=array_merge($variable,$var_metadata);
                }
            }
        }

        return $variables;
    }


	function variable_uid_by_vid($sid,$vid)
    {
        $this->db->select("uid");
        $this->db->where("sid",$sid);
        $this->db->where("vid",$vid);

        $variable=$this->db->get("editor_variables")->row_array();

        if ($variable){
            return $variable['uid'];
        }

        return false;
    }

	function variable_uid_by_name($sid,$fid,$var_name)
    {
        $this->db->select("uid");
        $this->db->where("sid",$sid);
		$this->db->where("fid",$fid);
        $this->db->where("name",$var_name);

        $variable=$this->db->get("editor_variables")->row_array();

        if ($variable){
            return $variable['uid'];
        }

        return false;
    }

	/**
	 * 
	 * 
	 * Validate data file
	 * @options - array of fields
	 * @is_new - boolean - for new records
	 * 
	 **/
	function validate_variable($options,$is_new=true)
	{		
		$this->load->library("form_validation");
		$this->form_validation->reset_validation();
		$this->form_validation->set_data($options);
	
		//validation rules for a new record
		if($is_new){				
			$this->form_validation->set_rules('fid', 'File ID', 'xss_clean|trim|max_length[50]|required|alpha_dash');
			$this->form_validation->set_rules('vid', 'Variable ID', 'required|xss_clean|trim|max_length[100]|alpha_dash');	
			$this->form_validation->set_rules('name', 'Variable name', 'required|xss_clean|trim|max_length[255]');	
			//$this->form_validation->set_rules('labl', 'Label', 'required|xss_clean|trim|max_length[255]');	
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
     * 
     * insert new variable
     * 
     * 
     */
    public function variable_insert($sid,$options)
    {
        $valid_fields=array(
            'name',
            'labl',
            'qstn',
            'catgry',
            'keywords',
            'sid',
            'fid',
            'vid',
            'metadata'
        );

        foreach($options as $key=>$value){
            if(!in_array($key,$valid_fields)){
                unset($options[$key]);
            }
        }

        $options['sid']=$sid;

        //metadata
        if(isset($options['metadata'])){
            $options['metadata']=$this->encode_metadata($options['metadata']);
        }

        $this->db->insert("editor_variables",$options);
        $insert_id=$this->db->insert_id();
        return $insert_id;
    }

    public function variable_update($sid,$uid,$options)
    {
        $valid_fields=array(
            'name',
            'labl',
            'qstn',
            'catgry',
            'keywords',
            'sid',
            'fid',
            'vid',
            'metadata'
        );

        foreach($options as $key=>$value){
            if(!in_array($key,$valid_fields)){
                unset($options[$key]);
            }
        }

        $options['sid']=$sid;

        //metadata
        if(isset($options['metadata'])){            
            $options['metadata']=$this->encode_metadata($options['metadata']);
        }

        $this->db->where('sid',$sid);
        $this->db->where('uid',$uid);
        $this->db->update("editor_variables",$options);
        return $uid;
    }


	function importJSON($sid,$type,$json_data,$validate=true)
	{
		return $this->update_project($type,$sid,$json_data,$validate);
	}


	/**
	 * 
	 * 
	 * @parseOnly true|false - if true, no data import is done
	 */
	function importDDI($sid, $parseOnly=false)
	{
		//temporary folder
		$temp_upload_folder='datafiles/tmp';
			
		if (!file_exists($temp_upload_folder)){
			@mkdir($temp_upload_folder);
		}
		
		if (!file_exists($temp_upload_folder)){
			show_error('DATAFILES-TEMP-FOLDER-NOT-SET');
		}

		//upload class configurations for DDI
		$config['upload_path'] 	 = $temp_upload_folder;
		$config['overwrite'] 	 = FALSE;
		$config['encrypt_name']	 = TRUE;
		$config['allowed_types'] = 'xml';

		$this->load->library('upload', $config);

		//name of the field for file upload
		$file_field_name='file';
		
		//process uploaded ddi file
		$ddi_upload_result=$this->upload->do_upload($file_field_name);

		$uploaded_ddi_path=NULL;

		//ddi upload failed
		if (!$ddi_upload_result){
			$error = $this->upload->display_errors();
			throw new Exception($error);
		}
		else //successful upload
		{
			//get uploaded file information
			$uploaded_ddi_path = $this->upload->data();
			$uploaded_ddi_path=$uploaded_ddi_path['full_path'];
		}		

		$parser_params=array(
			'file_type'=>'survey',
			'file_path'=>$uploaded_ddi_path
		);

		$this->load->library('DDI2_import');
		$this->load->library('Metadata_parser', $parser_params);
		$parser=$this->metadata_parser->get_reader();		
		$output=$this->ddi2_import->transform_ddi_fields($parser->get_metadata_array()); 

		//import study description
		if (!$parseOnly){
			$this->update_project($type='survey',$sid,$output,$validate=true);
		}
				
		//get list of data files
        $files=(array)$parser->get_data_files();

        //check if data file is empty
        foreach($files as $idx =>$file){
            $is_null=true;
            foreach(array_keys($file) as $file_field){
                if(!empty($file[$file_field])){
                    $is_null=false;
                }
            }
            if($is_null){
                //remove empty data file
                unset($files[$idx]);
            }
        }

        $data_files=array();
        foreach($files as $file){
            if(trim($file['id'])=='' && trim($file['file_id'])!='' ){
                $file['id']=$file['file_id'];
            }
            $data_file=array(
                'file_id'       =>$file['id'],
                'file_name'     =>str_replace(".NSDstat","",$file['filename']),
				'file_type'		=>$file['filetype'],
                'description'   =>$file['fileCont'],
                'case_count'    =>$file['caseQnty'],
                'var_count'     =>$file['varQnty']
            );
			$data_files[]=$data_file;

			if(!$parseOnly){
				$this->data_file_delete($sid,$data_file['file_id']);
				$this->data_file_insert($sid,$data_file);
			}
        }
        unset($files);

		$output['data_files']=$data_files;
		
        //import variables
        //$variables_imported=$this->import_variables($sid,$data_files, 
		$variable_iterator=$parser->get_variable_iterator();

		foreach($variable_iterator as $var_obj){
			if($parseOnly){
				$output['variables'][]=$var_obj->get_metadata_array();
			}else{
				$variable=$var_obj->get_metadata_array();
				$variable['fid']=$variable['file_id'];
				$variable['metadata']=$variable;
				$this->variable_insert($sid,$variable);
			}
		}

		if($parseOnly){
			return $output;
		}

		/*
        //import variable groups
        $this->create_update_variable_groups($sid,$parser->get_variable_groups());
		*/
	
		//return $output;
		
	}

	
	/*function download_project_thumbnail($sid)
	{				
		$project_folder=$this->get_project_folder($sid);

		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("Project folder not found");
		}

		$thumbnail_path=$project_folder.'/'

		if(file_exists($ddi_path)){
			$this->load->helper("download");
			force_download2($ddi_path);
		}
	}*/


	function download_project_ddi($sid)
	{		
		$ddi_path=$this->generate_project_ddi($sid);

		if(file_exists($ddi_path)){
			$this->load->helper("download");
			force_download2($ddi_path);
		}
	}

	function generate_project_ddi($sid)
	{
		$this->load->library("Editor_DDI_Writer");
        $project=$this->get_row($sid);

        if($project['type']!='survey'){
            throw new Exception("DDI is only available for Survey/MICRODATA types");
        }

		$project_folder=$this->get_project_folder($sid);

		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("download_project_ddi::Project folder not found");
		}

		$filename=trim($project['idno'])!=='' ? trim($project['idno']) : md5($project['id']);

		$ddi_path=$project_folder.'/'.$filename.'.xml';
		$this->editor_ddi_writer->generate_ddi($sid,$ddi_path);
		return $ddi_path;
	}

	/**
	 * 
	 * Export project metadata as JSON
	 * 
	 * @output_file (optional) path to output file
	 * 
	 */
	function download_project_json($sid)
	{
		$json_path=$this->generate_project_json($sid);

		//download json
		if(file_exists($json_path)){
			header("Content-type: application/json; charset=utf-8");
			$stdout = fopen('php://output', 'w');			
			$fh = fopen($json_path, 'r');
			stream_copy_to_stream($fh, $stdout);
			fclose($fh);
			fclose($stdout);
		}
	}

	/**
	 * 
	 * Generete project JSON
	 */
	function generate_project_json($sid)
	{
		$project=$this->get_row($sid);
		$project_folder=$this->get_project_folder($sid);

		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("download_project_json::Project folder not found");
		}

		$filename=trim($project['idno'])!=='' ? trim($project['idno']) : md5($project['id']);
		$output_file=$project_folder.'/'.$filename.'.json';

		$fp = fopen($output_file, 'w');

		$metadata=$project['metadata'];
		$basic_info=array(
			'type'=>$project['type']
		);
		
		$output=array_merge($basic_info, $metadata );

		if($project['type']=='survey'){			
			$output['data_files'] = function () use ($sid) {
				$files=$this->data_files($sid);
				if ($files){
					foreach($files as $file){
						unset($file['id']);
						unset($file['sid']);
						yield $file;
					}
				}
			};

			$output['variables'] = function () use ($sid) {
				foreach($this->Editor_variable_model->chunk_reader_generator($sid) as $variable){
					yield $variable['metadata'];
				}
			};

			/*$output['variable_groups'] = function () use ($sid) {
				$var_groups=$this->Variable_group_model->select_all($sid);
				foreach($var_groups as $var_group){
					yield $var_group;
				}			
			};*/
		}
		
		$encoder = new \Violet\StreamingJsonEncoder\StreamJsonEncoder(
			$output,
			function ($json) use ($fp) {
				fwrite($fp, $json);
			}
		);
		//$encoder->setOptions(JSON_PRETTY_PRINT);
		$encoder->encode();
		fclose($fp);
		
		return $output_file;
	}

	
	function catalog_connections($user_id)
	{
		$this->db->select("id,title,url,user_id");
		$this->db->where("user_id",$user_id);
		$result=$this->db->get("editor_catalogs")->result_array();
		return $result;
	}

	function get_catalog_connection($user_id,$id)
	{
		$this->db->select("*");
		$this->db->where("user_id",$user_id);
		$this->db->where("id",$id);
		$result=$this->db->get("editor_catalogs")->row_array();
		return $result;
	}

	function catalog_connection_create($options=[])
	{
		$fields=array('title','url','api_key','user_id');

		$data_options=[];
		foreach($fields as $req_field){
			if(!isset($options[$req_field])){
				throw new Exception("Field is required: ".$req_field);
			}
			if (empty($options[$req_field])){
				throw new Exception("Field is required: ".$req_field);
			}

			$data_options[$req_field]=$options[$req_field];
		}

		$this->db->insert("editor_catalogs",$data_options);
		return $this->db->insert_id();
	}

	
}//end-class
	
