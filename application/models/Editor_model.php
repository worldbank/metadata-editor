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
	/** @var string|null Cached absolute storage root */
	private $resolved_storage_path = null;
	private $schema_registry_cache = null;
	private $schema_list_cache = null;

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
		$this->load->library("Metadata_change_log");
		$this->load->library("Metadata_helper");
		$this->load->model("Editor_variable_model");
		$this->load->model("Editor_datafile_model");		
		$this->load->model("Collection_model");
		$this->load->model("Editor_template_model");

		$this->load->config("editor");
		$this->storage_path = $this->config->item('storage_path', 'editor');
	}


	/**
	 * Editor projects storage path (absolute, canonical when the directory exists).
	 */
	function get_storage_path()
	{
		if ($this->resolved_storage_path !== null) {
			return $this->resolved_storage_path;
		}

		$this->resolved_storage_path = $this->resolve_configured_path($this->storage_path);

		return $this->resolved_storage_path;
	}

	function get_temp_storage_path()
	{
		return $this->resolve_configured_path($this->tmp_storage_path);
	}

	/**
	 * Resolve a configured path to an absolute path (relative paths are anchored to FCPATH).
	 *
	 * @param string $path
	 * @return string
	 */
	function resolve_configured_path($path)
	{
		$path = trim(str_replace('\\', '/', (string) $path));
		if ($path === '') {
			return $path;
		}

		if ($path[0] !== '/') {
			$path = rtrim(str_replace('\\', '/', FCPATH), '/') . '/' . ltrim($path, '/');
		}

		$resolved = realpath($path);

		return $resolved !== false ? $resolved : $path;
	}

	/**
	 * Canonical absolute path for a file (works when the file itself does not exist yet).
	 *
	 * @param string $file_path
	 * @return string|null
	 */
	function resolve_absolute_file_path($file_path)
	{
		$file_path = trim(str_replace('\\', '/', (string) $file_path));
		if ($file_path === '') {
			return null;
		}

		$resolved = realpath($file_path);
		if ($resolved !== false) {
			return $resolved;
		}

		$dir = dirname($file_path);
		$base = basename($file_path);
		$resolved_dir = realpath($dir);
		if ($resolved_dir !== false) {
			return $resolved_dir . '/' . $base;
		}

		return $this->resolve_configured_path($file_path);
	}

	/**
	 * Absolute path to a file under a project folder (may not exist yet).
	 *
	 * @param int|string $sid
	 * @param string $relative_path e.g. data/indicator_data.csv
	 * @return string|null
	 */
	function resolve_project_file_path($sid, $relative_path)
	{
		$folder = $this->get_project_folder($sid);
		if (!$folder) {
			return null;
		}

		return $this->resolve_absolute_file_path($folder . '/' . ltrim($relative_path, '/'));
	}

	function get_project_folder($sid)
	{
		$path = $this->get_project_dirpath($sid);
		if ($path !== false && $path !== '') {
			return $this->resolve_absolute_file_path($this->get_storage_path() . '/' . $path);
		}
		return false;
	}

	/**
	 * Get project directory path relative to storage root (e.g. 5000/abc123hash).
	 * Safe for use in error messages to avoid exposing full filesystem paths.
	 */
	function get_project_dirpath($sid)
	{
		$this->db->select("dirpath");
		$this->db->where("id", $sid);
		$result = $this->db->get("editor_projects")->row_array();
		return isset($result['dirpath']) ? $result['dirpath'] : false;
	}


	/**
	 * Create folder for project.
	 * 
	 * Use a bucketed path (e.g. storage/10000/hash, storage/20000/hash) by project id
	 * 	 
	 */
	function create_project_folder($sid)
	{
		if (!$sid){
			throw new Exception("create_project_folder::invalid sid");
		}

		$storage_root = $this->get_storage_path();
		$bucket_size = 5000;
		$bucket = (int) (ceil($sid / $bucket_size) * $bucket_size);
		$relative_dirpath = $bucket . '/' . nada_hash($sid);

		$project_folder = $storage_root . '/' . $relative_dirpath;

		// create bucket folder and project folder (recursive)
		@mkdir($project_folder, 0777, true);

		if (!file_exists($project_folder)){
			throw new Exception("EDITOR_STORAGE_FOLDER_NOT_CREATED:" . $storage_root);
		}

		if (!file_exists($project_folder)){
			throw new Exception("PROJECT_FOLDER_NOT_CREATED::CHECK-PERMISSIONS-OR-PATH: " . $project_folder);
		}

		// update db with project folder path
		$options = array(
			'dirpath' => $relative_dirpath
		);

		$this->db->where("id", $sid);
		$this->db->update("editor_projects", $options);

		return $relative_dirpath;
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


	/**
	 * Get list of table columns that should be excluded from metadata column
	 * 
	 * @return array List of field names to exclude
	 */
	private function get_metadata_excluded_fields()
	{
		return array(
			'id', 'idno', 'type', 'pid',
			'title', 'abbreviation', 'authoring_entity', 'nation', 
			'year_start', 'year_end', 'study_idno',
			'metafile', 'dirpath', 'thumbnail',
			'varcount', 'published', 'is_shared', 'is_locked',
			'created', 'changed', 'created_by', 'changed_by',
			'created_utc', 'changed_utc',
			'schema', 'schema_version',
			'user_id',
			'template_uid', 'version_number', 'version_created', 
			'version_created_by', 'version_notes',
			'attributes', 'metadata', 			
			'partial_update', 'template_uid' 
		);
	}

	/**
	 * Filter out table-level fields from metadata before encoding
	 * 
	 * params:
	 * - data: array of data
	 * return:
	 * - array of filtered data
	 */
	private function filter_metadata_fields($data)
	{
		if (!is_array($data)) {
			return $data;
		}
		
		$excluded_fields = $this->get_metadata_excluded_fields();
		$filtered = array();
		
		foreach ($data as $key => $value) {
			// Skip excluded fields
			if (!in_array($key, $excluded_fields)) {
				$filtered[$key] = $value;
			}
		}
		
		return $filtered;
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

		if (!is_array($survey['metadata']) || empty($survey['metadata'])){
			$survey['metadata']=array(
				'type'=>$survey['type']			
			);
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

	/**
	 * Persist metadata blob directly without table-field extraction/filtering.
	 * Use for small metadata patches (e.g. data_structure_reference) where
	 * update_project would strip sparse metadata to an empty array.
	 *
	 * @param int $sid
	 * @param array $metadata
	 * @param int|null $user_id
	 */
	function update_metadata_array($sid, array $metadata, $user_id = null)
	{
		$this->check_project_editable($sid);

		$update = array(
			'metadata' => $this->encode_metadata($metadata),
			'changed' => time(),
		);

		if ($user_id) {
			$update['changed_by'] = (int) $user_id;
		}

		$this->db->where('id', (int) $sid);
		$this->db->update('editor_projects', $update);
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
	 * 
	 * @param int $sid - Project ID
	 * @param string|null $type - Optional project type to validate (supports legacy type mappings)
	 * @return bool - True if project exists (and matches type if provided)
	 */
    function check_id_exists($sid,$type=null)
    {
		// Legacy type mappings for backward compatibility
		$legacy_types_mappings = array(
			'survey' => 'microdata',
			'microdata' => 'survey',
			'timeseries' => 'indicator',
			'indicator' => 'timeseries',
			'timeseries-db' => 'indicator-db',
			'indicator-db' => 'timeseries-db'
		);

		$this->db->select("id,type");
		$this->db->where("id", $sid);

		// Check for type, if provided
		if ($type) {
			$types = array($type);
			if (isset($legacy_types_mappings[$type])) {
				$types[] = $legacy_types_mappings[$type];
			}
			$this->db->where_in("type", $types);
		}
				
		$result = $this->db->get("editor_projects")->row_array();
		
		return !empty($result);
	}


	

	/**
	 * 
	 * Create a new project
	 * 
	 */
	function create_project($type,$options=array())
	{
		$type = $this->resolve_canonical_type($type);
		if ($type===false){
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
			// Filter out table-level fields from metadata before encoding
			$metadata_only = $this->filter_metadata_fields($options['metadata']);
			$options['metadata']=$this->encode_metadata($metadata_only);
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
			throw new Exception("PROJECT_IS_LOCKED: This project is locked and cannot be edited.");
		}
		return true;
	}

	function update_project($type,$id,$options=array(),$validate=false)
	{
		$type = $this->resolve_canonical_type($type);
		if ($type===false){
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

		$metadata_before = $this->get_metadata($id);

		//partial update metadata
		if (isset($options['partial_update'])){
			if ($options['partial_update']==true){
				$options=$this->apply_partial_update($id,$options);
			}
			unset($options['partial_update']);
		}

		// Store original options for idno check
		$original_options = $options;

		// Indicator/timeseries: preserve DSD reference when form save omits it but binding exists
		if (in_array($type, array('indicator', 'timeseries'))) {
			$has_ref_idno = isset($options['data_structure_reference'])
				&& is_array($options['data_structure_reference'])
				&& !empty($options['data_structure_reference']['idno']);
			if (!$has_ref_idno) {
				$this->load->library('Data_structure_util');
				$resolved = $this->data_structure_util->resolve_project_reference($id, false);
				if ($resolved && !empty($resolved['idno'])) {
					$options['data_structure_reference'] = $resolved;
				}
			}
		}

		$db_options=array(
			'changed'=>isset($options['changed']) ? $options['changed'] : date("U"),
			'changed_by'=>isset($options['changed_by']) ? $options['changed_by'] : '',			
			'study_idno'=>$this->get_project_metadata_field($type,'idno',$options),
			'title'=>$this->get_project_metadata_field($type,'title',$options),
			'nation'=>$this->get_project_metadata_field($type,'country',$options),
			'year_start'=>$this->get_project_metadata_field($type,'year_start',$options),
			'year_end'=>$this->get_project_metadata_field($type,'year_end',$options),
			'attributes'=>$this->get_project_metadata_field($type,'attributes',$options),
		);

		// Filter out table-level fields from metadata before encoding
		$metadata_only = $this->filter_metadata_fields($options);
		$db_options['metadata'] = $this->encode_metadata($metadata_only);

		$options = $db_options;

		if ($template_uid){
			$options['template_uid']=$template_uid;
		}

		//idno - check if it was in the original options (before filtering)
		if (isset($original_options['idno']) && !$this->idno_exists($original_options['idno'], $id)){
			$options['idno']=$original_options['idno'];
		}

		$this->db->where('id',$id);
		$this->db->update('editor_projects',$options);

		$this->metadata_change_log->record_project_metadata_change(
			$id,
			$metadata_before,
			$metadata_only,
			'update',
			isset($original_options['changed_by']) ? $original_options['changed_by'] : null
		);
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

	/**
	 * 
	 * Update project changed timestamp
	 * 
	 */
	function update_project_changed_timestamp($sid, $changed_by=null, $changed=null)
	{
		$options=array();
		if ($changed){
			$options['changed']=$changed;
		}
		if ($changed_by){
			$options['changed_by']=$changed_by;
		}
		return $this->set_project_options($sid,$options);
	}

	/**
	 * 
	 * Set project options
	 * 
	 */	
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
		if (isset($options['idno']) && $this->idno_exists($options['idno'], $sid)){
			throw new Exception("IDNO_EXISTS: ". $options['idno']);
		}

		$this->db->where('id',$sid);
		$this->db->update('editor_projects',$options);
	}


	function patch_project($type,$id,$options=array(), $validate=true)
	{
		$type = $this->resolve_canonical_type($type);
		if ($type===false){
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

		$metadata_before = $project['metadata'];
		$metadata=$metadata_before;

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
		
		// Filter out table-level fields from metadata before encoding
		$metadata_only = $this->filter_metadata_fields($metadata);
		
		$db_options=array(
			'changed'=>isset($options['changed']) ? $options['changed'] : date("U"),
			'changed_by'=>isset($options['changed_by']) ? $options['changed_by'] : '',			
			'study_idno'=>$this->get_project_metadata_field($type,'idno',$metadata),
			'title'=>$this->get_project_metadata_field($type,'title',$metadata),
			'nation'=>$this->get_project_metadata_field($type,'country',$metadata),
			'year_start'=>$this->get_project_metadata_field($type,'year_start',$metadata),
			'year_end'=>$this->get_project_metadata_field($type,'year_end',$metadata),
			'attributes'=>$this->get_project_metadata_field($type,'attributes',$metadata),
			'metadata'=>$this->encode_metadata($metadata_only)
		);

		$this->db->where('id',$id);
		$this->db->update('editor_projects',$db_options);

		$this->metadata_change_log->record_project_metadata_change(
			$id,
			$metadata_before,
			$metadata_only,
			'patch',
			isset($options['changed_by']) ? $options['changed_by'] : null
		);
	}

	function update_project_template($sid,$type,$template_uid)
	{
		$this->check_project_editable($sid);

		$template_data_type=$this->Editor_template_model->get_template_data_type($template_uid);

		if (!$template_data_type){
			throw new Exception("TEMPLATE_NOT_FOUND: ".$template_uid);
		}

		$type = $this->resolve_canonical_type($type);
		if ($type===false){
			throw new Exception("INVALID_TYPE: ".$type);
		}

		$template_data_type_canonical = $this->resolve_canonical_type($template_data_type);
		if ($template_data_type_canonical === false) {
			$template_data_type_canonical = $template_data_type;
		}

		if ($template_data_type_canonical!=$type){
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

		$project_type_canonical = $this->resolve_canonical_type($project['type']);
		if ($project_type_canonical === false) {
			$project_type_canonical = $project['type'];
		}

		$template_data_type_canonical = $this->resolve_canonical_type($template['data_type']);
		if ($template_data_type_canonical === false) {
			$template_data_type_canonical = $template['data_type'];
		}

		if ($project_type_canonical!=$template_data_type_canonical){
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
		$resolved_type = $this->resolve_canonical_type($type);
		$schema_type = ($resolved_type !== false) ? $resolved_type : $type;

		// Project metadata may include app-managed root keys; variable schema must not strip those names if present
		if ($schema_type !== 'variable') {
			$this->load->library('Project_validation');
			$data = Project_validation::strip_application_managed_metadata_for_schema($data);
		}
		
		// Get schema file path using schema registry (handles aliases and custom schemas)
		try {
			$this->load->model('Metadata_schemas_model');
			$schema_file = $this->Metadata_schemas_model->get_schema_file_path($schema_type);
		} catch (Exception $e) {
			// For non-project types like "variable", fallback to hard-coded path
			$schema_file = "application/schemas/$schema_type-schema.json";
			if(!file_exists($schema_file)){
				throw new Exception("INVALID-DATASET-TYPE-NO-SCHEMA-DEFINED: " . $e->getMessage());
			}
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
			throw new ValidationException("SCHEMA_VALIDATION_FAILED [{$schema_type}]: ", $validator->getErrors());
		}
	}


	function get_project_metadata_field($type,$field,$data)
	{
		$type = $this->resolve_canonical_type($type) ?: $type;
		
		// Handle special fields that require custom extraction logic
		if ($field === 'country' || $field === 'nation') {
			return $this->extract_country_field($type, $data);
		}
		
		if ($field === 'year_start') {
			return $this->extract_year_start_field($type, $data);
		}
		
		if ($field === 'year_end') {
			return $this->extract_year_end_field($type, $data);
		}
		
		if ($field === 'attributes') {
			return $this->extract_attributes_field($type, $data);
		}
		
		// For simple fields (idno, title), use standard extraction
		// Try to get core field mappings from schema registry first
		$schema_field_paths = $this->get_schema_core_field_paths($type, $field);
		
		// Fallback to hard-coded mappings if schema doesn't have mappings (core schemas only)
		if ($schema_field_paths === false || empty($schema_field_paths)) {
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
				'custom'=>array(
					'metadata_type'=>'description.identification.metadata_type',
					'idno'=>'description.identification.idno',
					'title'=>'description.identification.title'
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

			if (!isset($core_fields[$type][$field])) {
				return false;
			}

			$field_path=$core_fields[$type][$field];
			$schema_field_paths = $field_path;
		}

		// Normalize to array
		if (!is_array($schema_field_paths)) {
			$schema_field_paths = array($schema_field_paths);
		}

		// Return first non-empty value
		foreach ($schema_field_paths as $path) {
			// Convert JSON Pointer format (/path/to/field) to dot notation (path.to.field)
			// for array_data_get which expects dot notation
			if (is_string($path) && strpos($path, '/') === 0) {
				// Remove leading slash and convert slashes to dots
				$path = ltrim($path, '/');
				$path = str_replace('/', '.', $path);
			}
			
			$value = array_data_get($data, $path);

			if (empty($value)){
				return null;
			}

			if ($value !== null && $value !== '' && $value !== false) {
				return $value;
			}
		}

		return false;
	}
	
	/**
	 * Extract country/nation field from metadata
	 * Handles both schema mappings and hardcoded fallbacks
	 */
	private function extract_country_field($type, $data)
	{
		// Try to get country mappings from schema registry first
		$schema_field_paths = $this->get_schema_core_field_paths($type, 'country');
		
		// Fallback to hard-coded mappings for core schemas
		if ($schema_field_paths === false || empty($schema_field_paths)) {
			// For core schemas, use the existing Metadata_helper method
			return $this->metadata_helper->extract_country_names_str($type, $data);
		}
		
		// Normalize to array
		if (!is_array($schema_field_paths)) {
			$schema_field_paths = array($schema_field_paths);
		}
		
		// Try each mapped path
		foreach ($schema_field_paths as $path) {
			// Convert JSON Pointer format to dot notation
			if (is_string($path) && strpos($path, '/') === 0) {
				$path = ltrim($path, '/');
				$path = str_replace('/', '.', $path);
			}
			
			$value = array_data_get($data, $path);
			
			if ($value !== null && $value !== '') {
				// Handle array of country objects (like survey schema)
				if (is_array($value)) {
					$country_names = array();
					foreach ($value as $item) {
						if (is_array($item) && isset($item['name'])) {
							$country_names[] = $item['name'];
						} elseif (is_string($item)) {
							$country_names[] = $item;
						}
					}
					if (!empty($country_names)) {
						return $this->metadata_helper->get_array_to_string($country_names, 3);
					}
				} elseif (is_string($value)) {
					return $value;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Extract year_start field from metadata
	 * Handles both schema mappings and hardcoded fallbacks
	 */
	private function extract_year_start_field($type, $data)
	{
		// Try to get year_start mappings from schema registry first
		$schema_field_paths = $this->get_schema_core_field_paths($type, 'year_start');
		
		// Fallback to hard-coded mappings for core schemas
		if ($schema_field_paths === false || empty($schema_field_paths)) {
			// For core schemas, use the existing Metadata_helper method
			return $this->metadata_helper->extract_year_start($type, $data);
		}
		
		// Normalize to array
		if (!is_array($schema_field_paths)) {
			$schema_field_paths = array($schema_field_paths);
		}
		
		$years = array();
		
		// Try each mapped path
		foreach ($schema_field_paths as $path) {
			// Convert JSON Pointer format to dot notation
			if (is_string($path) && strpos($path, '/') === 0) {
				$path = ltrim($path, '/');
				$path = str_replace('/', '.', $path);
			}
			
			$value = array_data_get($data, $path);
			
			if ($value !== null && $value !== '') {
				// Handle array of date objects
				if (is_array($value)) {
					foreach ($value as $item) {
						if (is_array($item) && isset($item['start'])) {
							$year = substr(trim($item['start']), 0, 4);
							if ((int)$year > 0) {
								$years[] = (int)$year;
							}
						}
					}
				} elseif (is_string($value) || is_numeric($value)) {
					// Extract year from date string or number
					$year = substr(trim((string)$value), 0, 4);
					if ((int)$year > 0) {
						$years[] = (int)$year;
					}
				}
			}
		}
		
		if (!empty($years)) {
			return min($years);
		}
		
		return false;
	}
	
	/**
	 * Extract year_end field from metadata
	 * Handles both schema mappings and hardcoded fallbacks
	 */
	private function extract_year_end_field($type, $data)
	{
		// Try to get year_end mappings from schema registry first
		$schema_field_paths = $this->get_schema_core_field_paths($type, 'year_end');
		
		// Fallback to hard-coded mappings for core schemas
		if ($schema_field_paths === false || empty($schema_field_paths)) {
			// For core schemas, use the existing Metadata_helper method
			return $this->metadata_helper->extract_year_end($type, $data);
		}
		
		// Normalize to array
		if (!is_array($schema_field_paths)) {
			$schema_field_paths = array($schema_field_paths);
		}
		
		$years = array();
		
		// Try each mapped path
		foreach ($schema_field_paths as $path) {
			// Convert JSON Pointer format to dot notation
			if (is_string($path) && strpos($path, '/') === 0) {
				$path = ltrim($path, '/');
				$path = str_replace('/', '.', $path);
			}
			
			$value = array_data_get($data, $path);
			
			if ($value !== null && $value !== '') {
				// Handle array of date objects
				if (is_array($value)) {
					foreach ($value as $item) {
						if (is_array($item)) {
							// Check for 'end' field first, then 'start' as fallback
							$date_str = isset($item['end']) ? $item['end'] : (isset($item['start']) ? $item['start'] : null);
							if ($date_str) {
								$year = substr(trim($date_str), 0, 4);
								if ((int)$year > 0) {
									$years[] = (int)$year;
								}
							}
						}
					}
				} elseif (is_string($value) || is_numeric($value)) {
					// Extract year from date string or number
					$year = substr(trim((string)$value), 0, 4);
					if ((int)$year > 0) {
						$years[] = (int)$year;
					}
				}
			}
		}
		
		if (!empty($years)) {
			return max($years);
		}
		
		return false;
	}
	
	/**
	 * Extract attributes field from metadata
	 * Handles both schema mappings and hardcoded fallbacks
	 */
	private function extract_attributes_field($type, $data)
	{
		// Try to get attributes mappings from schema registry first
		$schema_field_paths = $this->get_schema_core_field_paths($type, 'attributes');
		
		// Fallback to hard-coded mappings for core schemas
		if ($schema_field_paths === false || empty($schema_field_paths)) {
			// For core schemas, use the existing Metadata_helper method
			return $this->metadata_helper->extract_attributes($type, $data, $encode_json = true);
		}
		
		// Attributes mapping is an object with key/value pairs
		// Each key is an attribute name, each value is a field path
		if (!is_array($schema_field_paths) || empty($schema_field_paths)) {
			return false;
		}
		
		$attributes = array();
		
		// Process each attribute mapping
		foreach ($schema_field_paths as $attr_key => $attr_path) {
			if (is_string($attr_path) && $attr_path !== '') {
				// Convert JSON Pointer format to dot notation
				$path = $attr_path;
				if (strpos($path, '/') === 0) {
					$path = ltrim($path, '/');
					$path = str_replace('/', '.', $path);
				}
				
				$value = array_data_get($data, $path);
				if ($value !== null && $value !== '') {
					$attributes[$attr_key] = $value;
				}
			}
		}
		
		if (!empty($attributes)) {
			return json_encode($attributes);
		}
		
		return false;
	}

	/**
	 * Get core field paths from schema registry
	 * Returns array of paths, single path string, object (for attributes), or false if not found
	 */
	private function get_schema_core_field_paths($type, $field)
	{
		try {
			$this->load->model('Metadata_schemas_model');
			$schema = $this->Metadata_schemas_model->get_by_uid($type);
			
			if (!$schema || !isset($schema['metadata_options']['core_fields'])) {
				return false;
			}

			$core_fields = $schema['metadata_options']['core_fields'];
			
			if (!isset($core_fields[$field])) {
				return false;
			}

			$field_path = $core_fields[$field];
			
			// For attributes, return the object as-is (key/value pairs)
			if ($field === 'attributes') {
				if (is_array($field_path) && !isset($field_path[0])) {
					// It's an associative array (object), return as-is
					return $field_path;
				}
				return false;
			}
			
			// For other fields, handle arrays and strings
			// If it's already an array, return as-is
			if (is_array($field_path)) {
				return $field_path;
			}
			
			// If it's a non-empty string, return as single-element array
			if (is_string($field_path) && $field_path !== '') {
				return array($field_path);
			}
			
			return false;
		} catch (Exception $e) {
			// If schema registry fails, return false to fall back to hard-coded mappings
			return false;
		}
	}

	/**
	 * Resolve a provided type or alias to a canonical schema uid if available.
	 * Falls back to legacy known types.
	 * Returns canonical uid string, or false if not found.
	 */
	public function resolve_canonical_type($type)
	{
		if (!$type){
			return false;
		}

		// Look up via schema registry metadata table (supports alias)
		try{
			$this->load->model('Metadata_schemas_model');
			
			// First try to get by UID
			$row = $this->Metadata_schemas_model->get_by_uid($type);
			if ($row && isset($row['uid']) && $row['uid']){
				return $row['uid'];
			}
			
			// If not found by UID, try to find by alias
			if ($this->schema_list_cache === null) {
				if ($this->schema_registry_cache === null) {
					$this->load->library('Schema_registry');
					$this->schema_registry_cache = $this->schema_registry;
				}
				$this->schema_list_cache = $this->schema_registry_cache->list_schemas(array());
			}
			
			foreach ($this->schema_list_cache as $schema) {
				// Check if type matches the schema UID
				if (isset($schema['uid']) && $schema['uid'] === $type) {
					return $schema['uid'];
				}
				// Check if type matches an alias
				if (isset($schema['alias']) && !empty($schema['alias']) && $schema['alias'] === $type) {
					return $schema['uid']; // Return the canonical UID
				}
			}
		}catch(Exception $e){
			// ignore and fall through to legacy mappings
		}

		$legacy_type_map = array(
			'survey' => 'microdata',
			'timeseries' => 'indicator',
			'timeseries-db' => 'indicator-db'
		);
		
		if (isset($legacy_type_map[$type])) {
			return $legacy_type_map[$type];
		}
		
		$canonical_types = array('microdata', 'indicator', 'indicator-db');
		if (in_array($type, $canonical_types)) {
			return $type;
		}

		return false;
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
                    $db_name = $variable['name'];
                    $db_labl = isset($variable['labl']) ? $variable['labl'] : '';
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
                    $variables[$key]['name'] = $db_name;
                    $variables[$key]['labl'] = $db_labl;
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
	 * Import DDI from uploaded file
	 * 
	 * @param int $sid - Project ID
	 * @param bool $parseOnly - If true, no data import is done
	 * @param array $options - Additional options
	 * @return array - Import result
	 */
	function importDDI($sid, $parseOnly=false, $options=array())
	{
		//temporary folder
		$temp_upload_folder='datafiles/tmp';
			
		if (!file_exists($temp_upload_folder)){
			@mkdir($temp_upload_folder, 0777, true);
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

		// Use centralized import method
		return $this->import_ddi_from_path($sid, $uploaded_ddi_path, $parseOnly, $options);
	}


	/**
	 * 
	 * Import DDI from file path (centralized method to avoid duplication)
	 * 
	 * @param int $sid - Project ID
	 * @param string $ddi_file_path - Path to DDI XML file
	 * @param bool $parseOnly - If true, no data import is done
	 * @param array $options - Additional options
	 * @return array - Import result
	 */
	function import_ddi_from_path($sid, $ddi_file_path, $parseOnly=false, $options=array())
	{
		$parser_params=array(
			'file_type'=>'survey',
			'file_path'=>$ddi_file_path
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
        $known_file_ids=array(); //token -> true, used to validate <var @files> tokens during fan-out
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
			$known_file_ids[$file['id']]=true;

			if(!$parseOnly){
				$this->Editor_datafile_model->delete($sid,$data_file['file_id']);
				$this->Editor_datafile_model->insert($sid,$data_file);
			}
        }
        unset($files);

		$output['data_files']=$data_files;

        //import variables
        //$variables_imported=$this->import_variables($sid,$data_files, 
		$this->load->library('DDI_Utils');
		$variable_iterator=$parser->get_variable_iterator();
		$vid_to_uid = array();
		$pending_wgt_updates = array();
		$variable_warnings = array(); //surfaced to API response

		foreach($variable_iterator as $var_obj){
			if($parseOnly){
				$output['variables'][]=$var_obj->get_metadata_array();
				continue;
			}

			if (!$var_obj){
				continue;
			}

			$base_variable=$var_obj->get_metadata_array();

			// DDI 2.x @files is IDREFS (whitespace-separated list). A variable can
			// reference multiple data files (e.g. hierarchical IPUMS H+P layouts).
			// Fan out into one editor_variables row per referenced file so the
			// per-file UI listings, counts and joins all work unchanged.
			$file_id_tokens=DDI_Utils::split_file_ids($base_variable['file_id']);

			if (empty($file_id_tokens)){
				$variable_warnings[]=array(
					'vid'   => isset($base_variable['vid']) ? $base_variable['vid'] : '',
					'name'  => isset($base_variable['name']) ? $base_variable['name'] : '',
					'files' => $base_variable['file_id'],
					'message' => 'Variable has no @files attribute; not imported.'
				);
				continue;
			}

			// Capture weight reference (VID) before stripping; resolve to UID after all variables are inserted
			$var_wgt_ref = isset($base_variable['var_wgt_ref']) && trim($base_variable['var_wgt_ref']) !== '' ? trim($base_variable['var_wgt_ref']) : null;
			unset($base_variable['var_wgt_ref']);

			$inserted_tokens=array();
			$unknown_tokens=array();

			foreach($file_id_tokens as $fid_token){
				if (!isset($known_file_ids[$fid_token])){
					$unknown_tokens[]=$fid_token;
					continue;
				}

				$variable=$base_variable;
				$variable['file_id']=$fid_token;
				$variable['fid']=$fid_token;
				$variable['var_catgry_labels']=$this->get_variable_category_value_labels($variable);
				$variable['metadata']=$variable;

				$insert_id = $this->Editor_variable_model->insert($sid,$variable);

				// Key by fid|vid so the same VID in different data files maps to the correct UID
				$vid_key = $variable['fid'] . '|' . strtoupper(trim($variable['vid']));
				$vid_to_uid[$vid_key] = $insert_id;
				if ($var_wgt_ref !== null) {
					$pending_wgt_updates[] = array('uid' => $insert_id, 'vid_ref' => $var_wgt_ref, 'fid' => $variable['fid']);
				}

				$inserted_tokens[]=$fid_token;
			}

			if (count($file_id_tokens) > 1 && !empty($inserted_tokens)){
				$variable_warnings[]=array(
					'vid'   => isset($base_variable['vid']) ? $base_variable['vid'] : '',
					'name'  => isset($base_variable['name']) ? $base_variable['name'] : '',
					'files' => $base_variable['file_id'],
					'message' => 'Variable referenced multiple files; imported into: '.implode(', ', $inserted_tokens).'.'
				);
			}

			if (!empty($unknown_tokens)){
				$variable_warnings[]=array(
					'vid'   => isset($base_variable['vid']) ? $base_variable['vid'] : '',
					'name'  => isset($base_variable['name']) ? $base_variable['name'] : '',
					'files' => $base_variable['file_id'],
					'message' => 'Variable referenced unknown data file(s): '.implode(', ', $unknown_tokens).' (skipped for those files).'
				);
			}
		}

		// Resolve var_wgt_id for variables that reference a weight variable (VID -> UID, within same file)
		if (!$parseOnly && !empty($pending_wgt_updates)) {
			foreach ($pending_wgt_updates as $p) {
				$vid_key = $p['fid'] . '|' . strtoupper(trim($p['vid_ref']));
				$ref_uid = isset($vid_to_uid[$vid_key]) ? $vid_to_uid[$vid_key] : null;
				if ($ref_uid !== null) {
					$this->Editor_variable_model->update($sid, $p['uid'], array('var_wgt_id' => $ref_uid));
				}
			}
		}

		if (!empty($variable_warnings)){
			$output['variable_warnings']=$variable_warnings;
		}

		if($parseOnly){
			return $output;
		}

		/*
        //import variable groups
        $this->create_update_variable_groups($sid,$parser->get_variable_groups());
		*/
	
		return $output;
		
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

        if($project['type']!='survey' && $project['type']!='microdata'){
            throw new Exception("DDI is only available for Survey/MICRODATA types");
        }

		$project_folder=$this->get_project_folder($sid);


		if (!$project_folder || !file_exists($project_folder)){
			throw new Exception("download_project_ddi::Project folder not found");
		}

		$filename=trim($project['idno'])!=='' ? trim($project['idno']) : nada_hash($project['id']);

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

		$filename=nada_hash($sid);

		$pdf_path=$project_folder.'/'.$filename.'.pdf';
		return $pdf_path;
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

		// log deletion
		$metadata = array(
			'project_title' => isset($project['title']) ? $project['title'] : '',
			'project_idno' => isset($project['idno']) ? $project['idno'] : '',
			'project_type' => isset($project['type']) ? $project['type'] : '',
			'is_main_project' => $is_main_project
		);
		
		$this->audit_log->log_event(
			$obj_type = 'project',
			$obj_id = $sid,
			$action = 'delete',
			$metadata = $metadata
		);

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
		$this->db->select('id, dirpath, title, idno, type');
		$this->db->where('pid', $main_project_id);
		$this->db->where('id !=', $main_project_id); // Exclude the main project itself
		$versions = $this->db->get('editor_projects')->result_array();

		foreach ($versions as $version) {
			// Log each version deletion
			$metadata = array(
				'project_title' => isset($version['title']) ? $version['title'] : '',
				'project_idno' => isset($version['idno']) ? $version['idno'] : '',
				'project_type' => isset($version['type']) ? $version['type'] : '',
				'is_version' => true,
				'main_project_id' => $main_project_id
			);
			
			$this->audit_log->log_event(
				$obj_type = 'project',
				$obj_id = $version['id'],
				$action = 'delete',
				$metadata = $metadata
			);
			
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

	/**
	 * Return the primary idno for a project: study_idno if non-empty, else idno.
	 * Used for export ZIP naming and other display purposes.
	 *
	 * @param int $sid Project id
	 * @return string|null study_idno if set, else idno; null if both empty
	 */
	function get_project_primary_idno($sid)
	{
		$this->db->select("study_idno, idno");
		$this->db->where("id", $sid);
		$row = $this->db->get("editor_projects")->row_array();
		if (!$row) {
			return null;
		}
		$study_idno = isset($row['study_idno']) ? trim((string) $row['study_idno']) : '';
		if ($study_idno !== '') {
			return $study_idno;
		}
		$idno = isset($row['idno']) ? trim((string) $row['idno']) : '';
		return $idno !== '' ? $idno : null;
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
	/**
	 * @deprecated Use Metadata_change_log::build_change_record_safe() instead.
	 */
	function get_metadata_diff($metadata_original, $metadata_updated, $ignore_errors=false, $scope=Metadata_change_log::SCOPE_STUDY)
	{
		$record = Metadata_change_log::build_change_record_safe(
			$metadata_original,
			$metadata_updated,
			$scope
		);

		if ($record === null) {
			return false;
		}

		if ($ignore_errors && isset($record['status']) && $record['status'] === 'diff_failed') {
			return false;
		}

		return $record;
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

	/**
	 * 
	 * Refresh core metadata fields from metadata JSON
	 * 
	 * Extracts and updates core fields (title, nation, year_start, year_end, attributes, study_idno)
	 * from the metadata field without modifying the metadata itself or changed/changed_by timestamps
	 * 
	 * @param int $id - Project ID
	 * @param array $fields - Optional array of field names to refresh. If null, refreshes all fields.
	 *   Valid field names: 'title', 'nation', 'year_start', 'year_end', 'attributes', 'study_idno'
	 * @return array|false - Array of updated fields and their values, or false if no updates or project not found
	 * 
	 */
	function refresh_core_metadata_fields($id, $fields=null, $options=array())
	{
		// Get project
		$project=$this->get_row($id);
		
		if (!$project){
			return false;
		}
		
		// Check if project is locked
		if ($this->is_project_locked($id)) {
			throw new Exception("PROJECT_IS_LOCKED: This project is locked and cannot be edited.");
		}
		
		// Get metadata
		$metadata=$project['metadata'];
		
		if (!$metadata || !is_array($metadata)){
			return false;
		}
		
		// Define all refreshable fields and their database column mappings
		$refreshable_fields=array(
			'study_idno'=>'idno',      // Maps to 'idno' field extraction
			'title'=>'title',
			'nation'=>'country',        // Maps to 'country' field extraction
			'year_start'=>'year_start',
			'year_end'=>'year_end',
			'attributes'=>'attributes'
		);
		
		// If specific fields requested, filter to only those
		if ($fields && is_array($fields)){
			$refreshable_fields=array_intersect_key($refreshable_fields, array_flip($fields));
		}
		
		if (empty($refreshable_fields)){
			return false;
		}
		
		// Extract each field from metadata
		$updates=array();
		$type=$project['type'];
		
		foreach ($refreshable_fields as $db_field => $extraction_field) {
			$value=$this->get_project_metadata_field($type, $extraction_field, $metadata);
			
			// Only update if value is not false
			// For attributes, false means no attributes found
			if ($value !== false) {
				$updates[$db_field]=$value;
			} elseif ($db_field === 'attributes') {
				// For attributes, explicitly set to NULL if not found
				$updates[$db_field]=null;
			}
		}
		
		// Only update if there are changes
		if (empty($updates)){
			return false;
		}
		
		// Update database - explicitly do NOT update changed or changed_by
		$this->db->where('id', $id);
		$this->db->update('editor_projects', $updates);
		
		return $updates;
	}

	
	
}//end-class
	
