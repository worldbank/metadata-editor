<?php

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;
use Ramsey\Uuid\Uuid;

use Swaggest\JsonDiff\JsonDiff;
use Swaggest\JsonDiff\JsonPatch;
use Swaggest\JsonDiff\JsonPointer;
use Swaggest\JsonDiff\JsonMergePatch;


/**
 * 
 * Metadata editor projects
 * 
 */
class Editor_model extends CI_Model {

	private $storage_path='datafiles/editor';
	private $tmp_storage_path='datafiles/editor';

	private $types=array(
        'survey'=>'microdata',
        'geospatial'=>'geospatial',
        'timeseries'=>'timeseries',
		'timeseries-db'=>'timeseries-db',
        'document'=>'document',
        'image'=>'image',
        'video'=>'video',
        'table'=>'table',
        'script'=>'script',
        'visualization'=>'visualization'
    );

	private $listing_fields=array(
		'id',
		'type',
		'idno',
		'study_idno',
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
		'changed_by',
		'is_shared',
		"thumbnail",
		"attributes",
		);
	

	private $encoded_fields=array(
		"metadata"
	);

 
    public function __construct()
    {
		parent::__construct();
		$this->load->helper("Array");
		$this->load->library("form_validation");
		$this->load->library("Audit_log");
		$this->load->library("Metadata_helper");
		$this->load->model("Editor_variable_model");
		$this->load->model("Editor_datafile_model");		
		$this->load->model("Collection_model");
		$this->load->model("Editor_template_model");

		$this->load->config("editor");
		$this->storage_path = $this->config->item('storage_path', 'editor');
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

	function get_temp_storage_path()
	{
		return $this->tmp_storage_path;
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
        return unserialize(base64_decode((string)$metadata_encoded));
	}

	//get the survey by id
    function get_row($sid)
    {
		$this->db->select("*");		
		$this->db->where("id",$sid);
		
		$survey=$this->db->get("editor_projects")->row_array();

		if (!$survey){
			return false;
		}
		
		if($survey){
			$survey=$this->decode_encoded_fields($survey);
		}

		if (!is_array($survey['metadata']) && !empty($survey['metadata'])){
			$survey['metadata']=array(
				'type'=>$survey['type']			
			);
		}

		if (isset($survey['metadata'])){
			if (isset($survey['idno'])){			
				$survey['metadata']['idno']=$survey['idno'];
			}			
		}

		if (isset($survey['attributes']) && !empty($survey['attributes'])){
			$survey['attributes']=json_decode($survey['attributes'],true);
		}

        return $survey;
	}


	/**
	 * 
	 * Return metadata for a project by SID
	 * 
	 */
	function get_metadata($sid)
	{
		$project=$this->get_row($sid);

		if (isset($project['metadata'])){
			return $project['metadata'];
		}
	}

	//get project basic info
    function get_basic_info($sid)
    {
		$this->db->select("id,pid,idno,study_idno,type,study_idno,title,abbreviation,nation,year_start,year_end,published,created,changed, template_uid, is_locked, version_number, version_created, version_created_by, version_created");
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

		$template_uid=isset($options['template_uid']) ? $options['template_uid'] : null;
		$default_template=$this->Editor_template_model->get_default_template($type);

		$options['type']=$type;
		$options['template_uid']=isset($default_template['template_uid']) ? $default_template['template_uid'] : '';

		if (!isset($options['metadata'])){			
			$options['metadata']=$this->encode_metadata(array('type'=>$type));
		}
		else{
			$options['metadata']=$this->encode_metadata($options['metadata']);
		}

		$this->db->insert('editor_projects',$options);
		$new_id=$this->db->insert_id();

		if ($template_uid){
			$this->update_project_template($new_id,$type,$template_uid);
		}

		return $new_id;
	}

	/**
	 * 
	 * Check if project is locked
	 * 
	 */
	function is_project_locked($sid)
	{
		$this->db->select('is_locked');
		$this->db->where('id', $sid);
		$result = $this->db->get('editor_projects')->row_array();
		
		return $result && $result['is_locked'] == 1;
	}

	/**
	 * 
	 * Unlock a project (set is_locked to 0)
	 * 
	 */
	function unlock_project($sid)
	{
		$this->db->where('id', $sid);
		$this->db->update('editor_projects', array('is_locked' => 0));
		
		// Check if update was successful
		if ($this->db->affected_rows() == 0) {
			throw new Exception("PROJECT_NOT_FOUND: Project with ID $sid not found");
		}
		
		return true;
	}


	/**
	 * 
	 * Lock a project (set is_locked to 1)
	 * 
	 */
	function lock_project($sid)
	{
		$this->db->where('id', $sid);
		$this->db->update('editor_projects', array('is_locked' => 1));
		
		return true;
	}


	/**
	 * 
	 * Check if project can be edited (not locked)
	 * 
	 */
	function check_project_editable($sid)
	{
		if ($this->is_project_locked($sid)) {
			throw new Exception("PROJECT_IS_LOCKED: This project is locked and cannot be edited." .$sid);
		}
		return true;
	}

	function update_project($type,$id,$options=array(),$validate=false)
	{
		if (!array_key_exists($type,$this->types)){
			throw new Exception("INVALID_TYPE: ".$type);
		}

		$this->check_project_editable($id);

		$template_uid=null;

		//template
		if (isset($options['template_uid'])){
			$this->update_project_template($id,$type,$options['template_uid']);
		}

		if ($validate){
			$this->validate_schema($type,$options);
		}

		//generate/log diff
		$diff=$this->get_metadata_diff($this->get_metadata($id),$options, $ignore_errors=true);
		$this->audit_log->log_event($obj_type='project',$obj_id=$id,$action='update', $metadata=$diff, isset($options['changed_by']) ? $options['changed_by'] : null);

		//partial update metadata
		if (isset($options['partial_update'])){
			if ($options['partial_update']==true){
				$options=$this->apply_partial_update($id,$options);
			}
			unset($options['partial_update']);
		}

		$options=array(
			'changed'=>isset($options['changed']) ? $options['changed'] : date("U"),
			'changed_by'=>isset($options['changed_by']) ? $options['changed_by'] : '',			
			'study_idno'=>$this->get_project_metadata_field($type,'idno',$options),
			'title'=>$this->get_project_metadata_field($type,'title',$options),
			'nation'=>$this->metadata_helper->extract_country_names_str($type,$options),
			'year_start'=>$this->metadata_helper->extract_year_start($type,$options),
			'year_end'=>$this->metadata_helper->extract_year_end($type,$options),
			'metadata'=>$this->encode_metadata($options),
			'attributes'=>$this->metadata_helper->extract_attributes($type,$options, $encode_json=true),
		);

		if ($template_uid){
			$options['template_uid']=$template_uid;
		}

		//idno
		if (isset($options['idno']) && !$this->idno_exists($options['idno'])){
			$options['idno']=$options['idno'];
		}

		$this->db->where('id',$id);
		$this->db->update('editor_projects',$options);
	}

	/**
	 * 
	 * Update partial metadata
	 * 
	 */
	function apply_partial_update($id,$partial_options)
	{
		$options=$this->get_row($id);
		$options['metadata']=array_replace_recursive($options['metadata'],$partial_options);
		return $options['metadata'];
	}

	function set_project_options($sid,$options=array())
	{
		$this->check_project_editable($sid);

		$valid_options=array(
			"thumbnail",
			"template_uid",
			"created",
			"created_by",
			"changed_by",
			"changed",
			"idno"
		);

		foreach($options as $key=>$value){
			if (!in_array($key,$valid_options)){
				unset($options[$key]);
			}
		}

		//idno
		if (isset($options['idno']) && $this->idno_exists($options['idno'])){
			throw new Exception("IDNO_EXISTS: ". $options['idno']);
		}

		$this->db->where('id',$sid);
		$this->db->update('editor_projects',$options);
	}


	function patch_project($type,$id,$options=array(), $validate=true)
	{
		if (!array_key_exists($type,$this->types)){
			throw new Exception("INVALID_TYPE: ".$type);
		}

		$this->check_project_editable($id);

		if (!isset($options['patches'])){
			throw new Exception("`Patches` parameter is required");
		}

		$project=$this->get_row($id);

		if (!$project){
			throw new Exception("PROJECT_NOT_FOUND: ".$id);
		}

		$metadata=$project['metadata'];

		if (!is_object($metadata)){
			$metadata=json_decode(json_encode($metadata));
		}

		//apply patches
		$patch = JsonPatch::import($options['patches']);
		$patch->setFlags(1);
		$patch->apply($metadata);

		//convert metadata to array
		$metadata=json_decode(json_encode($metadata),true);

		//validate schema
		if ($validate==true){
			$this->validate_schema($type,$metadata);
		}
		
		$db_options=array(
			'changed'=>isset($options['changed']) ? $options['changed'] : date("U"),
			'changed_by'=>isset($options['changed_by']) ? $options['changed_by'] : '',			
			'study_idno'=>$this->get_project_metadata_field($type,'idno',$metadata),
			'title'=>$this->get_project_metadata_field($type,'title',$metadata),
			'metadata'=>$this->encode_metadata($metadata)
		);

		$this->db->where('id',$id);
		$this->db->update('editor_projects',$db_options);
	}

	function update_project_template($sid,$type,$template_uid)
	{
		$this->check_project_editable($sid);

		$template_data_type=$this->Editor_template_model->get_template_data_type($template_uid);

		if (!$template_data_type){
			throw new Exception("TEMPLATE_NOT_FOUND: ".$template_uid);
		}

		if ($template_data_type!=$type){
			throw new Exception("TEMPLATE_TYPE_MISMATCHED: ".$template_data_type . '!='. $type);
		}

		return $this->set_project_options($sid,array('template_uid'=>$template_uid));		
	}

	/**
	 * 
	 * Set project template
	 * 
	 */
	function set_project_template($sid,$template_uid)
	{		
		$project=$this->get_basic_info($sid);

		if (!$project){
			throw new Exception("PROJECT_NOT_FOUND: ".$sid);
		}

		$this->check_project_editable($sid);

		$this->load->model("Editor_template_model");
		$template=$this->Editor_template_model->get_template_by_uid($template_uid);

		if (!$template){
			throw new Exception("TEMPLATE_NOT_FOUND: ".$template_uid);
		}

		if ($project['type']!=$template['data_type']){
			throw new Exception("TEMPLATE_TYPE_MISMATCHED: ".$template['data_type'] . '!='. $project['type']);
		}

		$options=array(
			'template_uid'=>$template_uid
		);

		$this->db->where('id',$sid);
		$this->db->update('editor_projects',$options);
		return true;
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

//			var_dump($validator->getErrors());

			throw new ValidationException("SCHEMA_VALIDATION_FAILED [{$type}]: ", $validator->getErrors());
		}
	}


	function get_project_metadata_field($type,$field,$data)
	{
		//set image core title field - DCMI or IPTC
		if ($type=='image'){
			if (isset($data['image_description']['dcmi']['title'])){
				$image_title_field='image_description.dcmi.title';
			}else{
				$image_title_field='image_description.iptc.photoVideoMetadataIPTC.title';
			}
		}else{
			$image_title_field='image_description.iptc.photoVideoMetadataIPTC.title';
		}

		$core_fields=array(
			'survey'=>array(
				'idno'=>'study_desc.title_statement.idno',
				'title'=>'study_desc.title_statement.title'				
			),
			'document'=>array(
				'idno'=>'document_description.title_statement.idno',
				'title'=>'document_description.title_statement.title'
			),
			'table'=>array(
				'idno'=>'table_description.title_statement.idno',
				'title'=>'table_description.title_statement.title'
			),
			'script'=>array(
				'idno'=>'project_desc.title_statement.idno',
				'title'=>'project_desc.title_statement.title'
			),
			'video'=>array(
				'idno'=>'video_description.idno',
				'title'=>'video_description.title'
			),
			'timeseries'=>array(
				'idno'=>'series_description.idno',
				'title'=>'series_description.name',
			),
			'timeseries-db'=>array(
				'idno'=>'database_description.title_statement.idno',
				'title'=>'database_description.title_statement.title'
			),
			'geospatial'=>array(
				'idno'=>'description.idno',
				'title'=>'description.identificationInfo.citation.title'
			),
			'image'=>array(
				'idno'=>'image_description.idno',
				'title'=>$image_title_field
			),
		);


		if(!array_key_exists($type,$core_fields)){
			return false;
		}

		$field_path=$core_fields[$type][$field];
		return array_data_get($data, $field_path); 
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
	 * Get variable by UID
	 * 
	 */
	function variable($sid,$uid,$metadata_detailed=false)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
		$this->db->where("uid",$uid);
        $variable=$this->db->get("editor_variables")->row_array();

		if(isset($variable['metadata']) && $metadata_detailed==true){
			$variable['metadata']=$this->decode_metadata($variable['metadata']);			
		}
		return $variable;
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



    public function variable_update($sid,$uid,$options)
    {
        $this->check_project_editable($sid);

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
		//fix for geospatial IdentifcationInfo
		if ($type=='geospatial'){
			if (isset($json_data['description']['identificationInfo']) && is_array($json_data['description']['identificationInfo']) ){
				$json_data['description']['identificationInfo']=$json_data['description']['identificationInfo'][0];
			}
		}
		return $this->update_project($type,$sid,$json_data,$validate);
	}


	/**
	 * 
	 * 
	 * @parseOnly true|false - if true, no data import is done
	 */
	function importDDI($sid, $parseOnly=false, $options=array())
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

		$output=array_merge($output,$options);

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
				$this->Editor_datafile_model->delete($sid,$data_file['file_id']);
				$this->Editor_datafile_model->insert($sid,$data_file);
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
				
				if (!$var_obj){
					continue;
				}

				$variable=$var_obj->get_metadata_array();
				$variable['fid']=$variable['file_id'];
				$variable['var_catgry_labels']=$this->get_variable_category_value_labels($variable);
				$variable['metadata']=$variable;
				$this->Editor_variable_model->insert($sid,$variable);
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

	/** 
	 * 
	 * return variable category value/labels 
	 * 
	 * */
	function get_variable_category_value_labels($variable)
	{
		$var_catgry_labels=[];
		if (!isset($variable['var_catgry'])){
			return $var_catgry_labels;
		}

		foreach($variable['var_catgry'] as $catgry)
		{
			$var_catgry_labels[]=array(
				'value'=>$catgry['value'],
				'labl'=>$catgry['labl']
			);
		}

		return $var_catgry_labels;
	}

	
	function download_project_thumbnail($sid)
	{				
		$project_folder=$this->get_project_folder($sid);
		$thumbnail=$this->get_thumbnail($sid);

		if(!$thumbnail){
			throw new Exception("Thumbnail not found");
		}

		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("Project folder not found");
		}

		$thumbnail_path=$project_folder.'/'.$thumbnail;

		if(file_exists($thumbnail_path)){
			header('Content-type: image/jpeg');
			readfile($thumbnail_path);
			die();
			//$this->load->helper("download");
			//force_download2($thumbnail_path);			
		}else{
			throw new Exception("Thumbnail not found");
		}
	}


	function get_thumbnail($sid)
	{
		$this->db->select("thumbnail");
		$this->db->where('id',$sid);
		$result=$this->db->get("editor_projects")->row_array();

		if (isset($result['thumbnail'])){
			return $result['thumbnail'];
		}
	}

	function get_thumbnail_file($sid)
	{
		$project_folder=$this->get_project_folder($sid);
		$thumbnail=$this->get_thumbnail($sid);

		if(!$thumbnail){
			return false;
		}

		if (!$project_folder){
			return false;
		}

		$thumbnail_path=$project_folder.'/'.$thumbnail;

		if(file_exists($thumbnail_path)){
			return $thumbnail_path;
		}

		return false;
	}


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

	function generate_project_pdf($sid, $options=array())
	{
		$this->load->library("Pdf_report");
        $pdf_path=$this->get_pdf_path($sid);

		$this->pdf_report->initialize($sid, $options);
		$this->pdf_report->generate($pdf_path);		
		return $pdf_path;
	}

	function download_project_pdf($sid)
	{		
		$pdf_path=$this->get_pdf_path($sid);

		if(file_exists($pdf_path)){
			$this->load->helper("download");
			force_download2($pdf_path);
		}else{
			throw new Exception("PDF file not found");
		}
	}

	function get_pdf_info($sid)
	{
		$pdf_path=$this->get_pdf_path($sid);

		if(file_exists($pdf_path)){
			return array(
				'created'=>date("c",filemtime($pdf_path)),
				'file_size'=>format_bytes(filesize($pdf_path),2)
			);
		}

		return false;
	}

	private function get_pdf_path($sid)
	{
		$project_folder=$this->get_project_folder($sid);

		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("Project folder not found");
		}

		$filename=md5($sid);

		$pdf_path=$project_folder.'/'.$filename.'.pdf';
		return $pdf_path;
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


	function catalog_connection_delete($catalog_id, $user_id)
	{
		$data_options=[
			'user_id'=>$user_id,
			'id'=>$catalog_id
		];

		$this->db->where("id",$catalog_id);
		return $this->db->delete("editor_catalogs",$data_options);		
	}

	function delete_project($sid)
	{
		// Check if project is locked
		$this->check_project_editable($sid);

		// Get project info to check if it's a main project
		$project = $this->get_basic_info($sid);
		if (!$project) {
			throw new Exception("Project not found");
		}

		// Check if this is a main project (pid is 0, NULL, or equals the project id)
		$is_main_project = ($project['pid'] == 0 || $project['pid'] == null || $project['pid'] == $sid);

		if ($is_main_project) {
			// Delete all versions first
			$this->delete_project_versions($sid);
		}

		// Delete the main project
		$this->delete_single_project($sid);
	}

	/**
	 * 
	 * Delete all versions for a main project
	 * 
	 */
	function delete_project_versions($main_project_id)
	{
		// Get all versions for this main project
		$this->db->select('id, dirpath');
		$this->db->where('pid', $main_project_id);
		$this->db->where('id !=', $main_project_id); // Exclude the main project itself
		$versions = $this->db->get('editor_projects')->result_array();

		foreach ($versions as $version) {
			$this->delete_single_project($version['id']);
		}
	}

	/**
	 * 
	 * Delete a single project and all its related data
	 * 
	 */
	function delete_single_project($sid)
	{
		// Delete project folder
		$project_folder = $this->get_project_folder($sid);
		$storage_root = $this->get_storage_path();

		if ($storage_root !== $project_folder && file_exists($project_folder)) {
			remove_folder($project_folder);
		}
		
		// Delete from all related tables
		$this->db->where('id', $sid);
		$this->db->delete("editor_projects");

		$this->db->where('sid', $sid);
		$this->db->delete("editor_data_files");

		$this->db->where('sid', $sid);
		$this->db->delete("editor_resources");

		$this->db->where('sid', $sid);
		$this->db->delete("editor_variables");

		// Delete variable groups
		$this->db->where('sid', $sid);
		$this->db->delete("editor_variable_groups");

		// Delete admin metadata projects
		$this->db->where('sid', $sid);
		$this->db->delete("admin_metadata_projects");

		// Delete collection associations
		$this->db->where('sid', $sid);
		$this->db->delete("editor_collection_projects");

		// Delete project owners
		$this->db->where('sid', $sid);
		$this->db->delete("editor_project_owners");
	}


	function generate_uuid()
	{
		return Uuid::uuid4();
	}


	function get_project_id_by_idno($idno)
	{
		$this->db->select("id");
		$this->db->where("idno",$idno);
		$result=$this->db->get("editor_projects")->result_array();

		if ($result && count($result)>1){
			throw new Exception("Multiple projects found with the same IDNO: ". $idno);
		}
		
		if (isset($result[0]['id'])){
			return $result[0]['id'];
		}
	}


	function get_project_idno_by_id($sid)
	{
		$this->db->select("idno");
		$this->db->where("id",$sid);
		$result=$this->db->get("editor_projects")->row_array();

		if (isset($result['idno'])){
			return $result['idno'];
		}
	}

	function validate_idno($idno)
	{
		if (is_numeric($idno)){
			throw new Exception("IDNO cannot be numeric");
		}
	}

	/**
	 * 
	 * 
	 * Validate IDNO format
	 * 
	 * only allow alphanumeric characters, dashes, underscores, and periods
	 * cannot be numeric only
	 * max length = 200
	 * 
	 */
	function validate_idno_format($idno)
	{
		if (!preg_match('/^[a-zA-Z0-9\-\_\.]+$/', $idno)){
			throw new Exception("IDNO_INVALID_FORMAT: Only alphanumeric characters, dashes, underscores, and periods are allowed");
		}

		if (is_numeric($idno)){
			throw new Exception("IDNO_INVALID_FORMAT: IDNO cannot be numeric only");
		}

		if (strlen($idno)>200){
			throw new Exception("IDNO_INVALID_FORMAT: IDNO cannot be longer than 200 characters");
		}

		return true;		
	}

	
	function idno_exists($idno,$sid=null)
	{
		$this->db->select("id");
		$this->db->where("idno",$idno);

		if ($sid){
			$this->db->where("id !=",$sid);
		}

		$result=$this->db->get("editor_projects")->row_array();

		if (isset($result['id'])){
			return true;
		}
		return false;
	}

	/**
	 * 
	 * Get project last modified and created info
	 * 
	 */
	function get_edits_info($sid)
	{
		$this->db->select("users.username, users_cr.username as username_cr, editor_projects.created, editor_projects.changed");
		$this->db->join("users", "users.id=editor_projects.changed_by");
		$this->db->join("users as users_cr", "users_cr.id=editor_projects.created_by","left");		
		$this->db->where("editor_projects.id",$sid);
		$result=$this->db->get("editor_projects")->row_array();
		return $result;
	}


	/**
	 * 
	 * Return patch for metadata diff
	 * 
	 */
	function get_metadata_diff($metadata_original, $metadata_updated, $ignore_errors=false)
	{
		try{
			//remove fields that are not needed for diff
			$fields_to_remove=[
				'schema',
				'schema_version',
				'type',				
				'created_by',
				'created',
				'changed'
			];

			foreach($fields_to_remove as $field){
				if (isset($metadata_original[$field])){
					unset($metadata_original[$field]);
				}
				if (isset($metadata_updated[$field])){
					unset($metadata_updated[$field]);
				}
			}

			$diff = new JsonDiff($metadata_original, $metadata_updated, JsonDiff::TOLERATE_ASSOCIATIVE_ARRAYS);

			$patch=$diff->getPatch();

			$json_patch=[				
				//'added'=>$diff->getAdded(),
				//'changed'=>$diff->getModifiedNew(),
				'removed'=>$diff->getRemoved(),
				'patch'=>$patch,
			];

			$json_patch= array_filter($json_patch, function($value) {
				return !empty($value);
			});

			if (empty($json_patch)){
				return false; //no changes
			}

			//encode
			$json_patch=json_decode(json_encode($json_patch),true);

			return $json_patch;
		} catch (Exception $e) {
			if ($ignore_errors==true){
				return false;
			}
			throw new Exception("Metadata diff failed: ".$e->getMessage());
		}
		
	}


	/**
	 * 
	 * Transfer project ownership
	 * 
	 */
	function transfer_ownership($sid,$new_owner_id)
	{
		$project=$this->get_row($sid);

		if (!$project){
			throw new Exception("Project not found");
		}

		$options=array(
			'changed'=>date("U"),
			'changed_by'=>$new_owner_id,
			'created_by'=>$new_owner_id
		);

		$this->db->where('id',$sid);
		return $this->db->update('editor_projects',$options);
	}
		
	
	/**
	 * 
	 * Find a specific version project by version number
	 * 
	 * @param int $main_project_id - The main project ID
	 * @param string $version_number - Version number (e.g., "1.0.0")
	 * @return int|null - The project ID for the specific version, or null if not found
	 */
	function find_version_by_number($main_project_id, $version_number)
	{
		$this->db->select("id");
		$this->db->where("pid", $main_project_id);
		$this->db->where("version_number", $version_number);
		$result = $this->db->get("editor_projects")->row_array();
		
		if (isset($result['id'])){
			return $result['id'];
		}
		
		return null;
	}

	function get_metadata_version_notes($sid)
	{
		$project=$this->get_row($sid);

		$mapping=[
			'survey'=>'study_desc/version_statement/version_notes',
			'timeseries'=>'series_description/version_statement/version_notes',
			'timeseries-db'=>'database_description/version/notes',			
			'script'=>'project_desc/version_statement/version_notes',
			//geospatial - no version notes fields available
		];

		$version_notes=null;

		if (isset($mapping[$project['type']])){
			$field_path=$mapping[$project['type']];

			$version_notes=get_array_nested_value($project['metadata'], $field_path,"/");
		}

		return $version_notes;
	}
	
}//end-class
	
