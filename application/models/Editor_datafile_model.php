<?php

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use JsonSchema\Constraints\Factory;
use JsonSchema\Constraints\Constraint;
use League\Csv\Reader;


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
		'created',
		'changed',
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
		$this->load->model("Editor_model");
		$this->load->model("Editor_resource_model");
	}

	/**
	 * Whitelist and sanitize optional datafile metadata from upload requests.
	 *
	 * @param array $metadata Keys may be description, producer, data_checks, missing_data, version, notes.
	 * @return array Non-empty patch to merge into insert/update (values may be null).
	 */
	private function normalize_datafile_upload_metadata(array $metadata)
	{
		$allowed = array('description', 'producer', 'data_checks', 'missing_data', 'version', 'notes');
		$out = array();
		foreach ($allowed as $key) {
			if (!array_key_exists($key, $metadata)) {
				continue;
			}
			$val = $metadata[$key];
			if ($val === null || $val === false) {
				$out[$key] = null;
				continue;
			}
			$val = is_string($val) ? $val : (string) $val;
			$val = trim($val);
			if ($key === 'producer' || $key === 'version') {
				if (strlen($val) > 255) {
					throw new Exception("Field {$key} must not exceed 255 characters.");
				}
			}
			$out[$key] = ($val === '') ? null : $val;
		}
		return $out;
	}

	/**
	 * 
	 * Create new data file by uploading a data file (csv, dta, sav)
	 *
	 * Provide either a standard multipart field `file` or a completed resumable `upload_id`, not both.
	 *
	 * @param array|null $metadata Optional datafile fields: description, producer, data_checks,
	 * missing_data, version, notes. Only keys present in this array are applied; empty string becomes
	 * NULL. Omitted keys are left unchanged when overwriting an existing file row.
	 * 
	 */
	function upload_create($sid,$overwrite=false, $store_data=null,$user_id=null,$upload_id=null,$metadata=null)
	{
		$metadata = is_array($metadata) ? $metadata : array();
		$meta_patch = $this->normalize_datafile_upload_metadata($metadata);
		$upload_id = ($upload_id !== null && $upload_id !== '') ? trim((string)$upload_id) : '';
		$has_file = isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']);

		if ($upload_id !== '' && $has_file) {
			throw new Exception("Provide either a file upload or upload_id, not both.");
		}
		if ($upload_id === '' && !$has_file) {
			throw new Exception("File upload is required, or provide upload_id after completing a chunked upload.");
		}

		if ($upload_id !== '') {
			$this->load->library('Resumable_upload', null, 'uploader');
			$upload_info = $this->uploader->get_completed_upload($upload_id);
			if (!$upload_info) {
				throw new Exception("Resumable upload not found or not complete. Upload all chunks before registering the data file.");
			}
			// Must use sanitized `filename` (not original_filename): that is what move_resumable_upload
			// stores on disk and what we persist as file_name. Using original broke duplicate detection
			// when sanitize_filename() changed the basename (e.g. spaces → underscores).
			$datafile_info = $this->data_file_by_name($sid, $this->filename_part($upload_info['filename']));
			// Also match legacy rows created before that fix (file_name from original basename).
			if (!$datafile_info && isset($upload_info['original_filename'])) {
				$by_original = $this->data_file_by_name($sid, $this->filename_part($upload_info['original_filename']));
				if ($by_original) {
					$datafile_info = $by_original;
				}
			}
		} else {
			$datafile_info = $this->check_uploaded_file_exists($sid);
		}

		if ($overwrite==false && $datafile_info){
			throw new Exception("Data file already exists. To overwrite, use the overwrite parameter.");
		}
		
		// If overwrite is true and file exists, delete the physical file
		if ($overwrite==true && $datafile_info){
			$this->delete_physical_file($sid, $datafile_info['file_id']);
		}

		if ($upload_id !== '') {
			$upload_result = $this->Editor_resource_model->move_resumable_upload($sid, 'data', $upload_id);
		} else {
			$upload_result = $this->Editor_resource_model->upload_file($sid,$file_type='data',$file_field_name='file', $remove_spaces=false);
		}
		$uploaded_file_name=$upload_result['file_name'];
		$uploaded_path=$upload_result['full_path'];

		// Validate uploaded file name
		validate_filename($uploaded_file_name, 200);

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
				'store_data'=>$store_data,
				'created_by'=>$user_id,
				'changed_by'=>$user_id
			);
			if (!empty($meta_patch)) {
				$options = array_merge($options, $meta_patch);
			}

			$result=$this->insert($sid,$options);
		}else{
			//update data file
			$options=array(
				'file_physical_name'=>$uploaded_file_name,
				'file_name'=>$this->filename_part($uploaded_file_name),
				'file_path'=>$uploaded_path,
				'store_data'=>$store_data,
				'changed_by'=>$user_id
			);
			if (!empty($meta_patch)) {
				$options = array_merge($options, $meta_patch);
			}

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
			throw new Exception("Data file physical name not found: ");
		}

		$project_folder_path=$this->Editor_model->get_project_folder($sid).'/data/';
		$filepath=$project_folder_path.$filename;

		if (file_exists($filepath)){
			return $filepath;
		}

		// Resolve CSV path (tries .csv and .CSV for case-sensitive filesystems)
		$filepath_csv=$this->resolve_csv_path($project_folder_path,$this->filename_part($filename));
		if ($filepath_csv){
			return $filepath_csv;
		}

		throw new Exception("Data file not found: ".$filepath);
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

	/**
	 * Compare DB variables and CSV columns for a data file; return what is out of sync.
	 *
	 * - columns_to_remove_from_csv: column names in the CSV that are not in the DB (extra in file).
	 * - columns_in_db_not_in_csv: variable names in the DB that are not in the CSV (CSV has fewer columns).
	 *
	 * @param int $sid Project ID
	 * @param string $file_id Data file ID (e.g. F1)
	 * @param bool $include_names Include all variable names from db and csv in the result
	 * @return array db_variable_names, csv_column_names, columns_to_remove_from_csv, columns_in_db_not_in_csv, in_sync, csv_exists
	 */
	function get_columns_out_of_sync($sid, $file_id, $include_names=false)
	{
		$empty_result = array(
			'db_variable_names' => array(),
			'csv_column_names' => array(),
			'columns_to_remove_from_csv' => array(),
			'columns_in_db_not_in_csv' => array(),
			'in_sync' => true,
			'csv_exists' => false,
		);

		$csv_path = $this->get_file_csv_path($sid, $file_id);
		if (!$csv_path || !file_exists($csv_path)) {
			$empty_result['csv_exists'] = false;
			return $empty_result;
		}
		$empty_result['csv_exists'] = true;

		$this->load->model('Editor_variable_model');
		$db_names = $this->Editor_variable_model->get_variable_names_by_file($sid, $file_id);

		try {
			$csv = Reader::createFromPath($csv_path, 'r');
			$csv->setHeaderOffset(0);
			$csv_columns = $csv->getHeader();
			$csv_names = is_array($csv_columns) ? $csv_columns : array();
		} catch (Exception $e) {
			$empty_result['db_variable_names'] = $db_names;
			$empty_result['csv_column_names'] = array();
			$empty_result['in_sync'] = false;
			return $empty_result;
		}

		$columns_to_remove_from_csv = array_values(array_diff($csv_names, $db_names));
		$columns_in_db_not_in_csv = array_values(array_diff($db_names, $csv_names));
		$in_sync = (count($columns_to_remove_from_csv) === 0 && count($columns_in_db_not_in_csv) === 0);

		$result=array();

		if ($include_names){
			$result['db_variable_names'] = $db_names;
			$result['csv_column_names'] = $csv_names;
		}

		//merge with result
		$result=array_merge($result, array(
			'columns_to_remove_from_csv' => $columns_to_remove_from_csv,
			'columns_in_db_not_in_csv' => $columns_in_db_not_in_csv,
			'in_sync' => $in_sync,
			'csv_exists' => true,
		));

		return $result;
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
			$dirpath = $this->Editor_model->get_project_dirpath($sid);
			$path_for_message = ($dirpath !== false && $dirpath !== '') ? $dirpath.'/data/tmp/'.$filename : $filename;
			throw new Exception("Data file not found: ".$path_for_message);
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

		$project_folder_path=$this->Editor_model->get_project_folder($sid).'/data/';
		$original_path=$project_folder_path.$filename;

		// CSV path: if uploaded file is already CSV (any case), use its exact path so case-sensitive filesystems find it
		$is_original_csv=(strtolower($this->get_file_extension($filename))==='csv');
		if ($is_original_csv){
			$csv_path=$original_path;
			$csv_filename=$filename;
		} else {
			$base=$this->filename_part($filename);
			$csv_path=$this->resolve_csv_path($project_folder_path,$base);
			$csv_filename=$csv_path ? basename($csv_path) : $base.'.csv';
		}

		$files=array(
			'original'=>array(
				'filename'=>$filename,
				'filepath'=>$original_path,
				'file_exists'=>file_exists($original_path),
				'file_info'=>pathinfo($original_path),
				#'file_size'=>format_bytes(filesize($project_folder_path.$filename)),
			),
			'csv'=>array(
				'filename'=>$csv_filename,
				'filepath'=>$csv_path ?: $project_folder_path.$this->filename_part($filename).'.csv',
				'file_exists'=>$csv_path ? file_exists($csv_path) : false,
				'file_info'=>$csv_path ? pathinfo($csv_path) : pathinfo($project_folder_path.$this->filename_part($filename).'.csv'),
				#'file_size'=>format_bytes(filesize($project_folder_path.$filename_csv)),
			)
		);

		//file sizes
		if ($files['original']['file_exists']){
			$files['original']['file_size']=format_bytes(filesize($original_path));
		}

		if ($files['csv']['file_exists']){
			$files['csv']['file_size']=format_bytes(filesize($files['csv']['filepath']));
		}
		
		return $files;
	}

	/**
	 * Resolve path to CSV file; tries .csv and .CSV so case-sensitive filesystems work.
	 * @param string $dir Directory path (no trailing slash required)
	 * @param string $base Filename without extension
	 * @return string|null Full path if file exists, null otherwise
	 */
	private function resolve_csv_path($dir,$base)
	{
		$dir=rtrim($dir,'/');
		foreach (array('.csv','.CSV') as $ext){
			$path=$dir.'/'.$base.$ext;
			if (file_exists($path) && is_file($path)){
				return $path;
			}
		}
		return null;
	}

	/**
	 * Rewrite CSV header with renamed column names. Reads only the first row, then streams the rest.
	 *
	 * @param int $sid Project ID
	 * @param string $fid File ID
	 * @param array $rename_map [ old_name => new_name, ... ]
	 * @throws Exception if CSV not found or write fails
	 */
	public function rewrite_csv_header($sid, $fid, $rename_map)
	{
		if (empty($rename_map)) {
			return;
		}
		$csv_path = $this->get_file_csv_path($sid, $fid);
		if (!$csv_path || !file_exists($csv_path)) {
			throw new Exception("CSV file not found for this data file.");
		}
		$fp = fopen($csv_path, 'r');
		if ($fp === false) {
			throw new Exception("Could not open CSV file.");
		}
		$header = fgetcsv($fp);
		if ($header === false) {
			fclose($fp);
			throw new Exception("Could not read CSV header.");
		}
		$pos_after_header = ftell($fp);
		fclose($fp);
		$new_header = array();
		foreach ($header as $col) {
			$new_header[] = isset($rename_map[$col]) ? $rename_map[$col] : $col;
		}
		$tmp_path = $csv_path . '.tmp.' . uniqid();
		$tmp = fopen($tmp_path, 'w');
		if ($tmp === false) {
			throw new Exception("Could not create temporary file.");
		}
		fputcsv($tmp, $new_header);
		$fp = fopen($csv_path, 'r');
		if ($fp === false) {
			fclose($tmp);
			@unlink($tmp_path);
			throw new Exception("Could not reopen CSV file.");
		}
		fseek($fp, $pos_after_header);
		stream_copy_to_stream($fp, $tmp);
		fclose($fp);
		fclose($tmp);
		if (!rename($tmp_path, $csv_path)) {
			@unlink($tmp_path);
			throw new Exception("Could not replace CSV file.");
		}
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

	function data_file_by_pk_id($pk_id, $sid=null)
    {
        $this->db->select("*");
        if ($sid){
            $this->db->where("sid",$sid);
        }
        $this->db->where("id",$pk_id);
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

		$output=array(
			'processed' => 0,
			'deleted' => 0,
			'skipped' => 0,
			'files' => array()
		);

		foreach($files as $file){
			$output['processed']++;

			// Skip if file_physical_name is empty
			if (empty($file['file_physical_name'])) {
				$output['skipped']++;
				$output['files'][] = [
					'file_id' => $file['file_id'],
					'file_name' => $file['file_name'],
					'file_physical_name' => $file['file_physical_name'],
					'status' => 'skipped',
				];
				continue;
			}

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
				
				//remove original file - only if it's a file, not a directory
				if (file_exists($original_path) && is_file($original_path)){
					unlink($original_path);
					$output['deleted']++;
					$output['files'][] = [
						'file_id' => $file['file_id'],
						'file_name' => $file['file_physical_name'],
						'file_physical_name' => $file['file_physical_name'],
						'status' => 'deleted',
						'type' => 'original'
					];
				} else {
					$output['skipped']++;
					$reason = 'Original file not deleted: ';
					if (!file_exists($original_path)) {
						$reason .= 'file does not exist';
					} else if (!is_file($original_path)) {
						$reason .= 'path is a directory, not a file';
					}
					$output['files'][] = [
						'file_id' => $file['file_id'],
						'file_name' => $file['file_physical_name'],
						'file_physical_name' => $file['file_physical_name'],
						'status' => 'skipped',
					];
				}

				//remove csv file - only if it's a file, not a directory
				if (file_exists($csv_path) && is_file($csv_path)){
					unlink($csv_path);
					$output['deleted']++;
					$output['files'][] = [
						'file_id' => $file['file_id'],
						'file_name' => $filename_csv,
						'file_physical_name' => $filename_csv,
						'status' => 'deleted',
						'type' => 'csv'
					];
				} else {
					$output['skipped']++;
					$reason = 'CSV file not deleted: ';
					if (!file_exists($csv_path)) {
						$reason .= 'file does not exist';
					} else if (!is_file($csv_path)) {
						$reason .= 'path is a directory, not a file';
					}
					$output['files'][] = [
						'file_id' => $file['file_id'],
						'file_name' => $filename_csv,
						'file_physical_name' => $filename_csv,
						'status' => 'skipped',
					];
				}
			}
			else{
				//remove original file (non-csv) if csv exists
				if (!$is_csv && file_exists($original_path) && is_file($original_path) && file_exists($csv_path) && is_file($csv_path)){
					unlink($original_path);
					$output['deleted']++;
					$output['files'][] = [
						'file_id' => $file['file_id'],
						'file_name' => $file['file_physical_name'],
						'file_physical_name' => $file['file_physical_name'],
						'status' => 'deleted',
						'type' => 'original',
					];
				} else {
					$output['skipped']++;
					$reason = 'Original file not deleted: ';
					if ($is_csv) {
						$reason .= 'file is already CSV, no conversion needed';
					} else if (!file_exists($original_path)) {
						$reason .= 'original file does not exist';
					} else if (!is_file($original_path)) {
						$reason .= 'original file is a directory';
					} else if (!file_exists($csv_path)) {
						$reason .= 'CSV file does not exist';
					} else if (!is_file($csv_path)) {
						$reason .= 'CSV file is a directory';
					}
					$output['files'][] = [
						'file_id' => $file['file_id'],
						'file_name' => $file['file_physical_name'],
						'file_physical_name' => $file['file_physical_name'],
						'status' => 'skipped',
					];
				}
			}

		}

		return $output;
	}


	private function get_file_extension($filename)
	{
		if (empty($filename)){
			return '';
		}

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
		$this->Editor_model->check_project_editable($sid);

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
		$this->Editor_model->check_project_editable($sid);

		$data=array();
		//$data['created']=date("U");
		//$data['changed']=date("U");
		
		foreach($options as $key=>$value){
			if (in_array($key,$this->data_file_fields) ){
				$data[$key]=$value;
			}
		}

		if(!isset($data['created'])){
			$data['created']=date("U");
		}

		if(!isset($data['changed'])){
			$data['changed']=date("U");
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
		$data_file=$this->data_file_by_pk_id($id);

		if (!$data_file){
			throw new Exception("DATA_FILE_NOT_FOUND: " . $id);
		}

		$this->Editor_model->check_project_editable($data_file['sid']);

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

		$data['changed']=date("U");

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
		// Check if project is locked
		$this->Editor_model->check_project_editable($sid);

		foreach($options as $key=>$value){
			if ($key=='id'){
				unset($options[$key]);
			}

			if (!in_array($key,$this->data_file_fields) ){
				unset($options[$key]);
			}
		}

		$options['changed']=date("U");
		
		$this->db->where('sid',$sid);
		$this->db->where('file_name',$file_name);
		$result=$this->db->update('editor_data_files', $options);

		if ($result===false){
			throw new MY_Exception($this->db->_error_message());
		}
		
		return TRUE;
	}


	/**
	 * Duplicate a data file: create a new data file, copy the CSV file, and copy all variables.
	 * New file_id and file_name are generated. Variable vids are reassigned (project-wide unique).
	 * Weight variable references (var_wgt_id) are remapped to the new variable uids.
	 *
	 * @param int $sid Project ID
	 * @param string $source_file_id Source data file ID (e.g. F1)
	 * @param int|null $user_id User ID for created_by/changed_by
	 * @return array The new data file row (with file_id, file_name, etc.)
	 */
	function duplicate_datafile($sid, $source_file_id, $user_id = null)
	{
		$this->Editor_model->check_project_editable($sid);

		$source = $this->data_file_by_id($sid, $source_file_id);
		if (!$source) {
			throw new Exception("Data file not found: " . $source_file_id);
		}

		$new_file_id = $this->generate_fileid($sid);
		$base_name = $this->filename_part($source['file_name']);
		$new_file_name = $base_name . '_copy';
		$counter = 0;
		while ($this->data_file_by_name($sid, $new_file_name)) {
			$counter++;
			$new_file_name = $base_name . '_copy' . ($counter > 1 ? $counter : '');
		}
		$ext = $this->get_file_extension($source['file_physical_name']);
		$new_file_physical_name = $new_file_name . ($ext ? '.' . $ext : '');
		$new_csv_name = $new_file_name . '.csv';

		$project_folder = $this->Editor_model->get_project_folder($sid);
		$data_folder = rtrim($project_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;

		$now = date("U");
		$options = array(
			'sid' => $sid,
			'file_id' => $new_file_id,
			'file_name' => $new_file_name,
			'file_physical_name' => $new_file_physical_name,
			'description' => $source['description'],
			'case_count' => $source['case_count'],
			'var_count' => $source['var_count'],
			'producer' => $source['producer'],
			'data_checks' => $source['data_checks'],
			'missing_data' => $source['missing_data'],
			'version' => $source['version'],
			'notes' => $source['notes'],
			'metadata' => $source['metadata'],
			'wght' => $this->max_wght($sid) + 1,
			'store_data' => isset($source['store_data']) ? (int)$source['store_data'] : 1,
			'created' => $now,
			'changed' => $now,
			'created_by' => $user_id,
			'changed_by' => $user_id,
		);
		$this->db->insert('editor_data_files', $options);
		$new_pk_id = $this->db->insert_id();
		if (!$new_pk_id) {
			throw new Exception("Failed to insert duplicate data file");
		}

		// Copy physical CSV if it exists
		$source_csv_path = $this->get_file_csv_path($sid, $source_file_id);
		if ($source_csv_path && file_exists($source_csv_path) && is_file($source_csv_path)) {
			$dest_csv_path = $data_folder . $new_csv_name;
			if (!copy($source_csv_path, $dest_csv_path)) {
				throw new Exception("Failed to copy data file to " . $new_csv_name);
			}
			$this->db->where('id', $new_pk_id);
			$this->db->update('editor_data_files', array('file_physical_name' => $new_csv_name, 'changed' => $now));
		}

		// Copy variables: load all for source file, assign new vids, insert for new file, then fix var_wgt_id
		$this->load->model('Editor_variable_model');
		$this->db->select('*');
		$this->db->where('sid', $sid);
		$this->db->where('fid', $source_file_id);
		$this->db->order_by('sort_order, uid');
		$variables = $this->db->get('editor_variables')->result_array();

		$max_vid = $this->Editor_variable_model->get_max_vid($sid);
		$uid_map = array(); // old_uid => new_uid
		$var_wgt_updates = array(); // new_uid => old_var_wgt_id (to update to new uid later)

		foreach ($variables as $idx => $row) {
			$old_uid = (int)$row['uid'];
			$old_var_wgt_id = isset($row['var_wgt_id']) ? (int)$row['var_wgt_id'] : 0;
			$max_vid++;
			$new_vid = 'V' . $max_vid;

			$metadata = $this->Editor_model->decode_metadata(isset($row['metadata']) ? $row['metadata'] : '');
			if (is_array($metadata)) {
				$metadata['fid'] = $new_file_id;
				$metadata['file_id'] = $new_file_id;
				$metadata['vid'] = $new_vid;
			}

			$insert_opts = array(
				'sid' => $sid,
				'fid' => $new_file_id,
				'vid' => $new_vid,
				'name' => $row['name'],
				'labl' => $row['labl'],
				'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
				'user_missings' => $row['user_missings'],
				'is_weight' => isset($row['is_weight']) ? (int)$row['is_weight'] : 0,
				'is_key' => isset($row['is_key']) ? (int)$row['is_key'] : 0,
				'field_dtype' => $row['field_dtype'],
				'field_format' => isset($row['field_format']) ? $row['field_format'] : null,
				'var_wgt_id' => 0,
				'interval_type' => isset($row['interval_type']) ? $row['interval_type'] : null,
				'metadata' => $metadata,
			);
			$new_uid = $this->Editor_variable_model->insert($sid, $insert_opts);
			$uid_map[$old_uid] = $new_uid;
			if ($old_var_wgt_id > 0) {
				$var_wgt_updates[$new_uid] = $old_var_wgt_id;
			}
		}

		// Update var_wgt_id on new variables to point to new uids
		foreach ($var_wgt_updates as $new_uid => $old_var_wgt_id) {
			$new_var_wgt_id = isset($uid_map[$old_var_wgt_id]) ? $uid_map[$old_var_wgt_id] : 0;
			$this->Editor_variable_model->update($sid, $new_uid, array('var_wgt_id' => $new_var_wgt_id));
		}

		return $this->data_file_by_id($sid, $new_file_id);
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

	function data_file_insert($sid,$options)
	{		
		// Check if project is locked
		$this->Editor_model->check_project_editable($sid);

		$data=array();
		$data['created']=date("U");
		$data['changed']=date("U");
		
		foreach($options as $key=>$value){
			if (in_array($key,$this->data_file_fields) ){
				$data[$key]=$value;
			}
		}

		//filename
		if ($data['file_name']){
			$data['file_name']=$this->filename_part($data['file_name']);
			
			// Validate file name if physical name is provided
			if (isset($data['file_physical_name'])) {
				validate_filename($data['file_physical_name'], 200);
			}
		}

		$data['sid']=$sid;		
		$result=$this->db->insert('editor_data_files', $data);

		if ($result===false){
			throw new MY_Exception($this->db->_error_message());
		}
		
		return $this->db->insert_id();
	}

	function data_file_filename_part($filename)
	{
		$info=pathinfo($filename);
		return $info['filename'];
	}

	function data_file_update($id,$options)
	{
		// Get the project ID from the data file
		$this->db->select('sid');
		$this->db->where('id', $id);
		$data_file = $this->db->get('editor_data_files')->row_array();
		
		if (!$data_file) {
			throw new Exception("DATA_FILE_NOT_FOUND: " . $id);
		}
		
		// Check if project is locked
		$this->Editor_model->check_project_editable($data_file['sid']);

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

		$data['changed']=date("U");

		// Handle file name change - rename physical files
		if (isset($data['file_name'])){
			$new_file_name = $this->filename_part($data['file_name']);
			$old_data_file = $this->data_file_by_pk_id($id, $data_file['sid']);
			
			if ($old_data_file && $old_data_file['file_name'] != $new_file_name) {
				// Validate the new file name
				validate_filename($new_file_name, 200);
				
				// Check if new file name already exists
				$existing_file = $this->data_file_by_name($data_file['sid'], $new_file_name);
				if ($existing_file && $existing_file['id'] != $id) {
					throw new Exception("Data file name '{$new_file_name}' already exists");
				}
				
				$extension = $this->get_file_extension($old_data_file['file_physical_name']);
				$new_physical_name = $new_file_name . ($extension ? '.' . $extension : '');
				$this->rename_physical_files($data_file['sid'], $id, $old_data_file['file_physical_name'], $new_physical_name);
				$data['file_physical_name'] = $new_physical_name;
			}
		}
		
		$this->db->where('id',$id);
		$result=$this->db->update('editor_data_files', $data);

		if ($result===false){
			throw new MY_Exception($this->db->_error_message());
		}
		
		return TRUE;
	}

	/**
	 * Rename physical files when data file name changes
	 */
	private function rename_physical_files($sid, $file_id, $old_physical_name, $new_physical_name)
	{
		$project_folder = $this->Editor_model->get_project_folder($sid);
		$data_folder = $project_folder . '/data/';
		
		// Get logical names for CSV file renaming
		$old_logical_name = $this->filename_part($old_physical_name);
		$new_logical_name = $this->filename_part($new_physical_name);
		
		// Paths for original and CSV files
		$old_original_path = $data_folder . $old_physical_name;
		$new_original_path = $data_folder . $new_physical_name;
		$old_csv_path = $data_folder . $old_logical_name . '.csv';
		$new_csv_path = $data_folder . $new_logical_name . '.csv';
		
		// Rename original file if it exists
		if (file_exists($old_original_path) && is_file($old_original_path)) {
			if (!rename($old_original_path, $new_original_path)) {
				throw new Exception("Failed to rename original file from {$old_physical_name} to {$new_physical_name}");
			}
		}
		
		// Rename CSV file if it exists
		if (file_exists($old_csv_path) && is_file($old_csv_path)) {
			if (!rename($old_csv_path, $new_csv_path)) {
				throw new Exception("Failed to rename CSV file from " . basename($old_csv_path) . " to " . basename($new_csv_path));
			}
		}
	}

	function data_files_get_varcount($sid)
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

	function validate_data_file($options,$is_new=true)
	{		
		$this->load->library("form_validation");
		$this->form_validation->reset_validation();
		$this->form_validation->set_data($options);
	
		//validation rules for a new record
		if($is_new){				
			#$this->form_validation->set_rules('surveyid', 'IDNO', 'xss_clean|trim|max_length[255]|required');
			//$this->form_validation->set_rules('file_id', 'File ID', 'required|xss_clean|trim|max_length[50]');	
			$this->form_validation->set_rules('file_name', 'File name', 'required|xss_clean|trim|max_length[200]|validate_file_name');	
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


	
}//end-class
	
