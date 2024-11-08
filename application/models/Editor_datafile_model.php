<?php

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;


/**
 * 
 * Editor datafile model for microdata
 * 
 */
class Editor_datafile_model extends CI_Model {

	private $data_file_fields=array(
		'id',
		'sid',
		'file_id',
		'file_physical_name',
		'file_name',
		'description', 
		'case_count',
		'var_count',
		'producer',
		'data_checks',
		'missing_data',
		'version',
		'notes',
		'metadata',
		'wght',
		'store_data',
	);
		

	private $encoded_fields=array(
		"metadata"
	);

 
    public function __construct()
    {
		parent::__construct();
		$this->load->helper("Array");
		$this->load->library("form_validation");		
		$this->load->model("Editor_model");
		$this->load->model("Editor_resource_model");
	}


	/**
	 * 
	 * Create new data file by uploading a data file (csv, dta, sav)
	 * 
	 */
	function upload_create($sid,$overwrite=false, $store_data=null)
	{
		$datafile_info=$this->check_uploaded_file_exists($sid);

		if ($overwrite==false && $datafile_info){
			throw new Exception("Data file already exists. To overwrite, use the overwrite parameter.");
		}

		//upload file
		$upload_result=$this->Editor_resource_model->upload_file($sid,$file_type='data',$file_field_name='file', $remove_spaces=false);
		$uploaded_file_name=$upload_result['file_name'];
		$uploaded_path=$upload_result['full_path'];

		if ($store_data=='store'){
			$store_data=1;
		}else {
			$store_data=0;
		}

		if (!$datafile_info){
			//create data file
			$options=array(
				'sid'=>$sid,
				'file_id'=>$this->generate_fileid($sid),
				'file_physical_name'=>$uploaded_file_name,
				'file_name'=>$this->filename_part($uploaded_file_name),
				'wght'=>$this->max_wght($sid)+1,
				'store_data'=>$store_data
			);

			$result=$this->insert($sid,$options);
		}else{
			//update data file
			$options=array(
				'file_physical_name'=>$uploaded_file_name,
				'file_name'=>$this->filename_part($uploaded_file_name),
				'file_path'=>$uploaded_path,
				'store_data'=>$store_data
			);

			$result=$this->update($datafile_info['id'],$options);
		}

		return [
			'uploaded'=>[
				'uploaded_file_name'=>$uploaded_file_name,
				'base64'=>base64_encode($uploaded_file_name),
				//'uploaded_path'=>$uploaded_path
			],
			'file_id'=>$this->file_id_by_name($sid,$uploaded_file_name)
		];
	}

	function check_uploaded_file_exists($sid)
	{
		if (isset($_FILES) && isset($_FILES['file'])){
			$filename=$_FILES['file']['name'];
			$filename=$this->filename_part($filename);
			$exists=$this->data_file_by_name($sid,$filename);
			return $exists;
		}

		return false;
	}
	
	

	/**
	 * 
	 * Get path to the data physical file
	 * 
	 */
	function get_file_path($sid,$file_id)
	{
		$datafile=$this->data_file_by_id($sid,$file_id);

		if (!$datafile){
			throw new Exception("Data file ID not found: ");
		}

		$filename=$datafile['file_physical_name'];

		if (empty($filename)){
			//throw new Exception("Data file physical name not found: ");
			return false;
		}

		$filepath=$this->Editor_model->get_project_folder($sid).'/data/'.$filename;

		if (!file_exists($filepath)){
			throw new Exception("Data file not found: ".$filepath);
		}
		
		return $filepath;
	}

	function get_file_csv_path($sid, $file_id)
	{
		$files=$this->get_files_info($sid,$file_id);

		if (!isset($files['csv'])){
			return false;
		}

		$csv_path=$files['csv']['filepath'];

		if (!file_exists($csv_path)){
			return false;
		}

		return $csv_path;
	}

	/**
	 * 
	 * Check data file CSV exists
	 * 
	 */
	function check_csv_exists($sid, $file_id)
	{
		try{
			$csv_path=$this->get_file_csv_path($sid,$file_id);
			
			if (!$csv_path){
				return false;
			}

			return $csv_path;
		}
		catch(Exception $e){
			return false;
		}
	}


	function get_tmp_file_info($sid,$fid,$type)
	{
		$datafile=$this->data_file_by_id($sid,$fid);

		if (!$datafile){
			throw new Exception("Data file ID not found: ");
		}

		$filename=$datafile['file_physical_name'];

		if (empty($filename)){
			throw new Exception("Data file not set");
		}

		$filename=$this->filename_part($filename).'.'.$type;
		$project_folder_path=$this->Editor_model->get_project_folder($sid).'/data/tmp/';


		if (!file_exists(realpath($project_folder_path.$filename))){
			throw new Exception("Data file not found: ".$project_folder_path.$filename);
		}

		return [
			'filename'=>$filename,
			'filepath'=>$project_folder_path.$filename,				
			'file_info'=>pathinfo($project_folder_path.$filename),
			'file_size'=>format_bytes(filesize($project_folder_path.$filename)),
		];		
	}


	/**
	 * 
	 * Get path for data original + csv file
	 * 
	 */
	function get_files_info($sid,$file_id)
	{
		$datafile=$this->data_file_by_id($sid,$file_id);

		if (!$datafile){
			throw new Exception("Data file ID not found: ");
		}

		$filename=$datafile['file_physical_name'];		

		if (empty($filename)){
			return[
			];
		}

		$filename_csv=$this->filename_part($filename).'.csv';
		$project_folder_path=$this->Editor_model->get_project_folder($sid).'/data/';

		$files=array(
			'original'=>array(
				'filename'=>$filename,
				'filepath'=>$project_folder_path.$filename,
				'file_exists'=>file_exists($project_folder_path.$filename),
				'file_info'=>pathinfo($project_folder_path.$filename),
				#'file_size'=>format_bytes(filesize($project_folder_path.$filename)),
			),
			'csv'=>array(
				'filename'=>$filename_csv,
				'filepath'=>$project_folder_path.$filename_csv,
				'file_exists'=>file_exists($project_folder_path.$filename_csv),
				'file_info'=>pathinfo($project_folder_path.$filename_csv),
				#'file_size'=>format_bytes(filesize($project_folder_path.$filename_csv)),
			)
		);

		//file sizes
		if ($files['original']['file_exists']){
			$files['original']['file_size']=format_bytes(filesize($project_folder_path.$filename));
		}

		if ($files['csv']['file_exists']){
			$files['csv']['file_size']=format_bytes(filesize($project_folder_path.$filename_csv));
		}
		
		return $files;
	}


	/**
	 * 
	 * Get all data files by project ID
	 * 
	 */
    function select_all($sid, $include_file_info=false)
    {
        $this->db->select("*");
		$this->db->where("sid",$sid);
		$this->db->order_by('wght','ASC');
		$this->db->order_by('file_name','ASC');
		$files=$this->db->get("editor_data_files")->result_array();

		if(empty($files)){
			return array();
		}

		//get varcounts
		$varcounts=$this->get_varcount($sid);
		
		//add file_id as key
		$output=array();
		foreach($files as $file){
			$output[$file['file_id']]=$file;
			//add varcounts
			$output[$file['file_id']]['var_count']=isset($varcounts[$file['file_id']]) ? $varcounts[$file['file_id']] : 0;
		}

		//apply sorting to keep files in the order - F1, F2...F9, F10, F11
		$file_keys = array_keys($output);
  		//natsort($file_keys);

		$sorted_files=array();

  		foreach ($file_keys as $key_){
			$sorted_files[$key_] = $output[$key_];
			if($include_file_info){
				$sorted_files[$key_]['file_info']=$this->get_files_info($sid,$key_);
			}
		}

  		return $sorted_files;
	}

	//get an array of all file IDs e.g. F1, F2, ...
    function list($sid)
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
	 * Get FILE_ID by file name - e.g. F1, F2, F3
	 */
	function file_id_by_name($sid,$file_name)
	{
		$this->db->select("file_id");
		$this->db->where("sid",$sid);
		$this->db->where("file_name",$this->filename_part($file_name));
		$result=$this->db->get("editor_data_files")->row_array();
		
		if ($result){
			return $result['file_id'];
		}

		return false;
	}


	/**
	 * 
	 * Get a list of all file names with file_id
	 */
	function file_id_name_list($sid)
	{
		$this->db->select("file_id, file_name");
		$this->db->where("sid",$sid);
		$result= $this->db->get("editor_data_files")->result_array();

		$output=array();
		foreach($result as $row)
		{
			$output[$row['file_name']]=$row['file_id'];
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

	function data_file_by_pk_id($sid,$id)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
        $this->db->where("id",$id);
        return $this->db->get("editor_data_files")->row_array();
	}

	function data_file_by_name($sid,$file_name)
    {
        $this->db->select("*");
        $this->db->where("sid",$sid);
		$file_name=$this->filename_part($file_name);
        $this->db->where("file_name",$file_name);
        return $this->db->get("editor_data_files")->row_array();
	}
	

	function generate_fileid($sid)
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

	function max_wght($sid)
    {
        $this->db->select("max(wght) as max_wght");
        $this->db->where("sid",$sid);
        $result=$this->db->get("editor_data_files")->row_array();

		if ($result && is_numeric($result['max_wght'])){
			return $result['max_wght'];
		}

		return 0;
	}


	/**
	 * 
	 * Clean up data files
	 * 
	 *  - remove files marked to be deleted
	 *  - remove original (non-csv) files if csv exists
	 * 
	 */
	function cleanup($sid, $file_id=null)
	{
		$files=[];
		if ($file_id){

			$file=$this->data_file_by_id($sid,$file_id);
			
			if (!$file){
				throw new Exception("Data file not found: " . $file_id);
			}

			$files[]=$file;
		}
		else {
			$files=$this->select_all($sid);
		}

		//get project folder
		$project_folder=$this->Editor_model->get_project_folder($sid);

		$output=array();

		foreach($files as $file){

			//is csv file?
			$is_csv=strtolower($this->get_file_extension($file['file_physical_name']))=='csv';

			//data csv file name
			$filename_csv=$file['file_name'].'.csv';

			//original file path
			$original_path=$project_folder.'/data/'.$file['file_physical_name'];

			//csv file path
			$csv_path=$project_folder.'/data/'.$filename_csv;

			//if store_data==0, delete the file
			//remove original + csv file
			if ($file['store_data']==0){
				
				//remove original file
				if (file_exists($original_path)){
					unlink($original_path);

					$output[]=[
						'file_id'=>$file['file_id'],
						'file_name'=>$file['file_physical_name'],
						//'file_path'=>$original_path,
						'status'=>'deleted'
					];
				}

				//remove csv file
				if (file_exists($csv_path)){
					unlink($csv_path);

					$output[]=[
						'file_id'=>$file['file_id'],
						'file_name'=>$filename_csv,
						//'file_path'=>$csv_path,
						'status'=>'deleted'
					];
				}
			}
			else{
				//remove original file (non-csv) if csv exists
				if (!$is_csv && file_exists($original_path)){
					unlink($original_path);

					$output[]=[
						'file_id'=>$file['file_id'],
						'file_name'=>$file['file_physical_name'],
						//'file_path'=>$original_path,
						'status'=>'deleted'
					];
				}
			}

		}

		return $output;
	}


	private function get_file_extension($filename)
	{
		$info=pathinfo($filename);
		return $info['extension'];
	}


	/**
	 * 
	 * Delete data file
	 * 
	 */
	function delete_physical_file($sid,$file_id)
	{
		try{
			$file_path=$this->get_file_path($sid,$file_id);
			unlink($file_path);
		}
		catch(Exception $e){
			return false;
		}
	}

	function delete($sid,$file_id)
    {        
        $this->db->where("sid",$sid);
        $this->db->where("file_id",$file_id);
        $this->db->delete("editor_data_files");
		$this->delete_variables($sid,$file_id);
	}

	function delete_variables($sid,$file_id)
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
	function insert($sid,$options)
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
			$data['file_name']=$this->filename_part($data['file_name']);
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
	function filename_part($filename)
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
	function update($id,$options)
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
		if (isset($data['file_name'])){
			$data['file_name']=$this->filename_part($data['file_name']);
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
	 * Update data file by file name
	 * 
	 * @sid - project ID
	 * @file_name - data file name without file extension
	 * @options - array of fields
	 * 
	 */
	function update_by_filename($sid,$file_name,$options)
	{
		foreach($options as $key=>$value){
			if ($key=='id'){
				unset($options[$key]);
			}

			if (!in_array($key,$this->data_file_fields) ){
				unset($options[$key]);
			}
		}
		
		$this->db->where('sid',$sid);
		$this->db->where('file_name',$file_name);
		$result=$this->db->update('editor_data_files', $options);

		if ($result===false){
			throw new MY_Exception($this->db->_error_message());
		}
		
		return TRUE;
	}


	function get_varcount($sid)
	{
		$this->db->select("sid,fid, count(*) as varcount");
		$this->db->where("sid",$sid);
		$this->db->group_by("sid,fid");
		$result= $this->db->get("editor_variables")->result_array();		

		$output=array();
		foreach($result as $row)
		{
			$output[$row['fid']]=$row['varcount'];
		}

		return $output;
	}


	function get_file_varcount($sid,$file_id)
	{
		$this->db->select("count(sid) as varcount");
		$this->db->where("sid",$sid);
		$this->db->where("fid",$file_id);
		$result= $this->db->get("editor_variables")->row_array();
		return $result['varcount'];
	}


	/**
	 * 
	 * 
	 * Validate data file
	 * @options - array of fields
	 * @is_new - boolean - for new records
	 * 
	 **/
	function validate($options,$is_new=true)
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
		$files=$this->list($sid);

		if(in_array($file_id,$files)){
			$this->form_validation->set_message(__FUNCTION__, 'FILE_ID already exists. The FILE_ID should be unique.' );
			return false;
		}

		return true;
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


	/**
	 * 
	 * Create new data file by uploading a data file (csv, dta, sav)
	 * 
	 */
	function temp_upload_file($sid)
	{
		//upload file
		$upload_result=$this->Editor_resource_model->upload_file($sid,$file_type='_tmp',$file_field_name='file', $remove_spaces=false);
		$uploaded_file_name=$upload_result['file_name'];
		$uploaded_path=$upload_result['full_path'];

		return [
			'uploaded_file_name'=>$uploaded_file_name,
			'base64'=>base64_encode($uploaded_file_name),
			'uploaded_path'=>$uploaded_path
		];
	}

	
}//end-class
	
