<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Import DDI2 codebook 
 *
 */
class DDI2_Import{

    private $ci;
    private $file_path;//metadata file path
    private $file_type; //survey,timeseries,etc
    private $sid;
    private $user_id;
    private $repositoryid='central';
    private $published=0;
    private $formid=6;//data not available
    private $link_da=null;
    private $catalog_root; //storage folder path
    private $overwrite=false;

    //which field to use for country name - nation | geog_coverage
    private $geog_coverage_field='nation'; 

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    
    /**
     * 
     * import ddi
     * 
     * params
     * - file_type - [survey]
	 * - file_path  - ddi file path
	 * - user_id
	 * - repositoryid 
	 * - overwrite - yes
	 * - partial - true or false - if true, import only study level metadata
     * 
     **/ 
    public function import($params,$sid=null)
    {
        $parser_params=array(
            'file_type'=>'survey',
            'file_path'=>$params['file_path']
        );

        $partial=isset($params['partial']) ? $params['partial'] : false;

        $this->ci->load->library('Metadata_parser', $parser_params);
        $this->ci->load->library('Dataset_manager');
        $this->ci->load->model('Survey_type_model');
        $this->ci->load->model("Variable_model");
        $this->ci->load->model("Variable_group_model");        
        $this->ci->load->model('Catalog_model');
        $this->catalog_root=get_catalog_root();

        //required parameters
        $required=array(
            'file_path',
            'user_id',
            'repositoryid',
            'overwrite'
        );

        //optional params
        $optional=array(
            'published',
            'formid',
            'link_da'
        );
        
        foreach($required as $field){
            if(array_key_exists($field,$params)){
                $this->{$field}=$params[$field];
            }
            else{
                throw new Exception("DDI2_IMPORT::MISSING_PARAMS: ".$field);
            }
        }

        foreach($optional as $field){
            if(array_key_exists($field,$params)){
                $this->{$field}=$params[$field];
            }
        }

        //parser to read metadata
        $parser=$this->ci->metadata_parser->get_reader();

        $idno=$parser->get_id();

        if (trim($idno)==''){
            throw new Exception(t('Required IDNo element is not set in the DDI xml file. Fix the DDI and set the codeBook/@ID and/or stdyDscr/citation/titlStmt/IDNo element.'));
        }

        if(trim($idno)==''){
            throw new Exception('CODEBOOK_IDNO_MISSING');
        }

        //sanitize ID to remove anything except a-Z1-9 characters
        /*if ($idno!==$this->sanitize_filename($idno)){
            throw new Exception(t('IDNO_INVALID_FORMAT').': '.$idno);
        }*/       

        //check if the study already exists, find the sid
        if (!$sid){
            $sid=$this->ci->dataset_manager->find_by_idno($idno);
            $this->sid=$sid;
        }        

        //study found
		if ($sid){
            //overwrite?
            if(!$this->overwrite){
                throw new Exception("SURVEY_ALREADY_EXISTS: ".$sid);
            }
            
            //load existing options
            $dataset_row=$this->ci->dataset_manager->get_row($sid);

            if(!isset($params['repositoryid'])){
                $this->repositoryid=$dataset_row['repositoryid'];
            }

            if(!isset($params['formid'])){
                $this->formid=$dataset_row['formid'];
            }

            $repositoryid=$this->repositoryid;
            $ddi_filename=$this->sanitize_filename($idno).".xml";

            $survey_target_filepath=$this->ci->Dataset_model->get_metadata_file_path($sid);
            $survey_folder_rel_path =$this->ci->Dataset_model->get_dirpath($sid);            

            if(!$survey_target_filepath){
                $survey_folder_hash=nada_hash($repositoryid.':'.$idno); 
                $this->setup_folder($repositoryid,$survey_folder_hash);
            }

		}else{//new study
            $repositoryid=$this->repositoryid;
            $ddi_filename=$this->sanitize_filename($idno).".xml";

            //generate survey folder name hash
            $survey_folder_hash=nada_hash($repositoryid.':'.$idno);

            //survey folder path
            $survey_folder_path=$this->setup_folder($repositoryid,$survey_folder_hash);
            
            //partial relative path to survey folder
            $survey_folder_rel_path=$repositoryid.'/'.$survey_folder_hash;

            //target file path
            $survey_target_filepath=unix_path($survey_folder_path.'/'.$ddi_filename);
        }

        //copy the xml file to the survey folder - skip copying if source and target are the same (e.g. for ddi refresh)
        if($this->file_path!==$survey_target_filepath){
            $copied=$this->copy_file($this->file_path, $survey_target_filepath);
        }
        
        $options=$this->transform_ddi_fields($parser->get_metadata_array());                
        $options['created_by']=$this->user_id;
		$options['changed_by']=$this->user_id;
		$options['changed']=date("U");
        $options['repositoryid']=$repositoryid;
        $options['metafile']=$ddi_filename;


        //options only for new studies
		if (!$sid){
            $options['repositoryid']=$repositoryid;
            $options['dirpath']		= $repositoryid.'/'.$survey_folder_hash;
			$options['published']	= $this->published;
			$options['formid']		= $this->formid;
            $options['created']		= date("U");
            $options['created_by']  = $this->user_id;
        }
        
        //if params are set
        if ($this->published!=null){
            $options['published']=$this->published;
        }

        if ($this->formid!=null){
            $options['formid']=$this->formid;
        }

        if ($this->link_da!=null){
            $options['link_da']=$this->link_da;
        }

        if ($this->repositoryid!=null){
            $options['repositoryid']=$this->repositoryid;
        }
                
        //validate & create study
		if(!$sid){
            //create new survey and return the sid
            $sid=$this->ci->dataset_manager->create_dataset('survey',$options);
            $this->sid=$sid;
		}
        else //existing survey
        {
            $this->ci->dataset_manager->update_dataset($sid,'survey',$options);
        }

        //set survey owner repo
        $this->ci->Dataset_model->set_dataset_owner_repo($sid,$this->repositoryid);   

        if($partial){
            return array(
                'sid'=>$sid,
                'idno'=>$idno,
                'partial'=>$partial,
                'published'=>$this->published,
                'repositoryid'=>$this->repositoryid,  
                'folder'=>$survey_folder_rel_path
            );
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
            $data_files[$file['id']]=$file;
        } 
        unset($files);

        //import data files and update data_files with file db id
        $data_files=$this->update_survey_data_files($sid,$data_files);

        //import variables
        $variables_imported=$this->import_variables($sid,$data_files, $parser->get_variable_iterator());

        //import variable groups
        $this->create_update_variable_groups($sid,$parser->get_variable_groups());

        //update survey varcount
        $this->ci->dataset_manager->update_varcount($sid);

        //index variable keywords
        $this->ci->dataset_manager->index_variable_data($sid);
                    
        return array(
            'sid'=>$sid,
            'idno'=>$idno,
            'varcount'=>$variables_imported,
            'published'=>$this->published,
            'repositoryid'=>$this->repositoryid,  
            'folder'=>$survey_folder_rel_path
        );
    }

    
    
    //transform structure of ddi fields  to survey type fields
    function transform_ddi_fields($metadata)
    {
        //mappings from DDI to NADA SURVEY type
        $ddi_mappings=$this->ci->config->item('survey',"metadata_parser",TRUE);

        $mappings=array();
        $complex_fields=array();
        foreach($ddi_mappings as $key=>$value){
            $mappings[$value['xpath']][]=$key;

            if(isset($value['type']) && $value['type']=='array' ){
                $complex_fields[$value['xpath']]['type']='array';
            }
        }

        $output=array();
        //only importing what is mapped
        foreach($mappings as $xpath=>$values)
        {
            foreach($values as $value){            
                //metadata exists?
                if(isset($metadata[$xpath])){
                    $element_value=$metadata[$xpath];

                    //complex type?
                    if(isset($ddi_mappings[$value]['type']) &&  $ddi_mappings[$value]['type']=='array'){
                        $this->array_nested_path($output, $value, $element_value, $glue = '/');
                    }
                    else{
                        //non-complex types
                        //value in array format
                        if(is_array($element_value) ){
                            //echo $value."-----\r\n";
                            //var_dump($element_value);
                            $this->array_nested_path($output, $value, implode(" ",$element_value), $glue = '/');
                        }
                        else { //simple element
                            #$output[$mappings[$key]]=$value;
                            $this->array_nested_path($output, $value, $element_value, $glue = '/');
                        }
                    }
                                  
                }
            }    
        }

        return $output;
    }

    
    //return an array with the nested path and value
    function array_nested_path(&$array, $parents, $value, $glue = '/')
    {
        $parents = explode($glue, (string) $parents);
        $reference = &$array;
        foreach ($parents as $key) {
            if (!array_key_exists($key, $reference)) {
                $reference[$key] = [];
            }
            $reference = &$reference[$key];
        }
        $reference = $value;
        unset($reference);

        return $array;
    }

    function get_array_nested_value($data, $path, $glue = '/')
    {
        $paths = explode($glue, (string) $path);
        $reference = $data;
        foreach ($paths as $key) {
            if (!array_key_exists($key, $reference)) {
                return false;
            }
            $reference = $reference[$key];
        }
        return $reference;
    }



    public function get_sid()
	{
		return $this->sid;
    }
    

    //create folder for the study
    public function setup_folder($repositoryid, $folder_name)
    {
        $repository_folder=$this->catalog_root.'/'.$repositoryid;
        $survey_folder=$repository_folder.'/'.$folder_name;

        //create the repo folder and survey folder
        @mkdir($survey_folder, 0777, $recursive=true);

        if(!file_exists($repository_folder)){
            throw new Exception("REPO_FOLDER_NOT_CREATED:".$repository_folder);
        }

        if(!file_exists($survey_folder)){
            throw new Exception("DDI2_IMPORT::SURVEY_FOLDER_NOT_CREATED::CHECK-PERMISSIONS-OR-PATH: ".$survey_folder);
        }

        return $survey_folder;
    }


	public function copy_file($source,$target)
	{
		if(!copy($source, $target)){
            throw new Exception(sprintf("FILE_NOT_COPIED: \r\n source: %s \r\n target: %s ",$source,$target));
        }

		if(!file_exists($target)){
			throw new Exception("FILE_NOT_COPIED: ".$target);
        }
	}

    //convert array keys for metadata
    function transform_metadata_keys($metadata_arr){
        $output=array();
        foreach($metadata_arr as $el_key=>$el_value)
        {
            $el_key=strtolower(str_replace("/","_",$el_key));
            $output[$el_key]=$el_value;
        }
        return $output;
    }


    private function create_update_variable_groups($sid,$variable_groups)
    {
        //delete existing variable groups
        $this->ci->Variable_group_model->delete($sid);
        
        if(is_array($variable_groups)){
			foreach($variable_groups as $vgroup){
				$this->ci->Variable_group_model->insert($sid,$vgroup);
			}
		}
    }

    private function update_survey_data_files($sid, $files)
    {
        //delete existing data files for the survey
        $this->ci->db->where('sid',$sid);
        $this->ci->db->delete('data_files');

        //import files
        foreach($files as $key=>$file)
        {            
            $options=array(
                'sid'           =>$sid,
                'file_id'       =>$file['id'],
                'file_name'     =>str_replace(".NSDstat","",$file['filename']),
                'description'   =>$file['fileCont'],
                'case_count'    =>$file['caseQnty'],
                'var_count'     =>$file['varQnty']
            );

			if ($this->ci->Data_file_model->validate_data_file($options)){				
                $file_id=$this->ci->Data_file_model->insert($sid,$options);
                $files[$key]['id']=$file_id;
			}
        }

        return $files;
    }


    //@years array (1999, 2000, ...)
    private function update_survey_years($sid, $years)
    {
        $this->ci->Survey_model->update_survey_years($sid, $years);
    }

    //@topics array(topic, vocab, uri)
    private function update_survey_topics($sid, $topics)
    {
        $this->ci->Survey_model->update_survey_topics($sid, $topics);
    }

    //@countries array(name, abbreviation)
    private function update_survey_countries($sid, $countries)
    {
        $this->ci->Survey_model->update_survey_countries($sid, $countries);
    }

    private function update_survey_locations($sid, $bounds=array())
    {
        //delete any existing locations
        $this->ci->db->delete('survey_locations',array('sid' => $sid));

        if(!is_array($bounds)){
            return false;
        }
        
        foreach($bounds as $bbox)
        {
            $north=$bbox['north'];
            $south=$bbox['south'];
            $east=$bbox['east'];
            $west=$bbox['west'];

            $this->ci->load->helper("gis_helper");
            $bbox_wkt=$this->ci->db->escape(bbox_to_wkt($north, $south, $east, $west));

            $this->ci->db->set('sid',$sid);
            $this->ci->db->set('location','GeomFromText('.$bbox_wkt.')',false);
            $this->ci->db->insert('survey_locations');
        }
    }

    private function encode_metadata($metadata_array)
    {
        return $this->ci->Survey_model->encode_metadata($metadata_array); //base64_encode(serialize($metadata_array));
    }


    /**
	*
	*Sanitize file name
	**/
	function sanitize_filename($name)
	{
		return preg_replace('/[^a-zA-Z0-9-_\.]/','-',$name);
	}


    private function import_variables($sid,$data_files, $variable_iterator)
    {
        //delete existing variables + variables metadata
        $this->ci->Variable_model->remove_all_variables($sid);

        if(!$data_files){
            return 0;
        }
        
        if (!$variable_iterator){
            return 0;
        }

        $batch_inserts=true; //enable or disable batch inserts
        $batch_insert_size=200; //rows inserted at once
        $batch_insert_count=0;
        $batch_options=array();
        $k=0;

        //@var_obj is an instance of the interfaceVariable e.g. DdiVariable
        foreach($variable_iterator as $var_obj)
        {
            $fid=null;
            
            if(!$var_obj){
                continue;
            }

            if (isset($data_files[$var_obj->get_file_id()])){
                //get file id
                $fid=$data_files[$var_obj->get_file_id()]['id'];    
            }            
            
            if(!$fid){
                throw new exception("var @files attribute not set. ". $var_obj->get_file_id());
            }
            
            //transform fields to map to variable fields and validate
            //$variable=$this->map_variable_fields($var_obj->get_metadata_array());
            $variable=$var_obj->get_metadata_array();
            $variable['fid']=$variable['file_id'];   
            
            try{
                $this->ci->Variable_model->validate_variable($variable);
            }
            catch(ValidationException $e){                
                throw new ValidationException("VALIDATION_ERROR: ".$e->getMessage(), $variable);
                
            }
            catch(Exception $e){
                throw new Exception('DDI2_IMPORT::VARIABLE-IMPORT: '.$e->getMessage());
            }


            //fid = auto generated numeric id for file
            //file_id = user defined .e.g. F1, F2
            
            $options=array(
                //'file_id'   	=> $var_obj->get_file_id(),
                //'fid'   		=> $fid,
                'fid'           =>$var_obj->get_file_id(), //F1, F2
                'vid'		    => $var_obj->get_id(),
                'name'			=> $var_obj->get_name(),
                'labl'			=> $var_obj->get_label(),
                'qstn'			=> $var_obj->get_question(),
                'catgry'		=> $var_obj->get_categories_str(),
                'keywords'      => $var_obj->get_notes(),
                'sid'	        => $sid,
                'metadata'      => $variable
            );

            if(!$batch_inserts){
                $variable_id=$this->ci->Variable_model->insert($sid,$variable);

                if (!$variable_id) {
                    throw new Exception("variable not created " . $this->ci->db->last_query());
                }
            }

            if ($batch_inserts){
                $batch_options[$k]=$options;

                if(count($batch_options)==$batch_insert_size){                    
                    $this->ci->Variable_model->batch_insert($sid,$batch_options);
                    $batch_options=[];
                }
            }            
            $k++;
       }

        if(count($batch_options)>0){
            $this->ci->Variable_model->batch_insert($sid,$batch_options);
        }
        return $k;//no. of variables imported
    }


    //rename variable fields to match survey > variable schema fields
    function map_variable_fields($variable)
    {
        $mappings=array(
            'id'=>'vid',
            'name'=>'name',
            'files'=>'file_id',
            'dcml'=>'var_dcml',
            'intrvl'=>'var_intrvl',
            'loc_start_pos'=>'var_start_pos',
            'loc_end_pos'=>'var_end_pos',
            'loc_width'=>'var_width',
            'loc_rec_seg_no'=>'var_rec_seg_no',
            'labl'=>'labl',
            'imputation'=>'var_imputation',
            'security'=>'var_security',
            'resp_unit'=>'var_respunit',
            'preqtxt'=>'var_qstn_preqtxt',
            'qstnlit'=>'var_qstn_qstnlit',
            'postqtxt'=>'var_qstn_postqtxt',
            'ivuinstr'=>'var_qstn_ivulnstr',
            'universe'=>'var_universe',
            'universe_clusion'=>'universe_clusion',
            'sumstat'=>'var_sumstat',
            'catgry'=>'var_catgry',
            'txt'=>'var_txt',
            'codinstr'=>'var_codeinstr',
            'concept'=>'var_concept',
            'var_format_type'=>'var_format',
            'var_format_schema'=>'var_format_schema',
            'notes'=>'var_notes'
        );

        //define repeatable fields
        $complex_fields=array(
            "sumstat"=>array(
                'type'=>'array'
            ),
            'concept'=>array(
                'type'=>'array'
            ),
            'catgry'=>array(
                'type'=>'array'
            )
        );

        $output=array();

        foreach($variable as $key=>$value){
            //complex types e.g. repeatable array types
			if(array_key_exists($key,$complex_fields)){ 
				$output[$mappings[$key]]=$value;
			}
			else{
                //non-complex types
                if(array_key_exists($key,$mappings)){
                    //value is not in array format
                    if(!is_array($value)){
                        $output[$mappings[$key]]=$value;
                    }
                    else { //simple element
                        $output[$mappings[$key]]=implode(" ",$value);
                    }
                }
                else{//element mapping not found
                    $output[$key]=$value;
                }    
			}
		}

        return $output;
    }

}
