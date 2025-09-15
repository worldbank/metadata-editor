<?php

use Swaggest\JsonDiff\JsonDiff;
use Swaggest\JsonDiff\JsonPatch;
use Swaggest\JsonDiff\JsonPointer;
use Swaggest\JsonDiff\JsonMergePatch;

/**
 * 
 * Editor templates
 * 
 */

class Editor_template_model extends ci_model {
 
	private $fields=array(
		"id",
		"uid",
		"data_type", 
		"lang", 
		"name", 
		"version", 
		"organization", 
		"author", 
		"description", 
		"instructions",
		"template", 
		"created",
		"created_by", 
		"changed",
		"changed_by",
		"owner_id",
		"deleted_at",
		"deleted_by",
		"is_deleted"
	);


	private $permissions=array(
		'view'=>'View',
		'edit'=>'Edit',		
		'admin'=>'Admin'
	);

	private $core_templates=[];
	private $ci;

    public function __construct()
    {
        parent::__construct();		
		$this->ci =& get_instance();
		$this->init_core_templates();
		$this->ci->load->model('Template_acl_model');
    }


	function init_core_templates()
	{
		require_once(APPPATH.'config/editor_templates.php');

		if (!isset($config)){		
			throw new Exception("config/editor_templates not loaded");
		}

		foreach($config as $key=>$templates){

			foreach($templates as $idx=>$template){

				$template_json='';
				$template_path=APPPATH.'/views/'.$template['template'];

				if (file_exists($template_path)){
					$template_json=$template['template'];//json_decode(file_get_contents($template_path),true);
				}
				else{
					//throw new Exception("template not found" .$template_path);
				}

				$this->core_templates[]=array(
					'uid'=>$template['uid'],
					'template_type'=>'core',
					'name'=> $template['name'],
					'data_type'=>$key,
					'lang'=>$template['lang'],
					'template'=>$template_json
				);
			}
		}
	}

	function get_core_template_by_uid($uid)
	{
		foreach($this->core_templates as $template){
			if ($template['uid']==$uid){
				return $template;
			}
		}
	}

	function get_core_templates_by_type($type)
	{
		$templates_=array();
		foreach($this->core_templates as $idx=>$template){
			if ($type==$template['data_type']){
				$templates_[]= $template;
			}
		}
		return $templates_;
	}


	function get_custom_template_by_uid($uid)
	{
		return $this->select_single($uid);
	}

	function get_template_by_uid($uid)
	{
		//check core
		$template=$this->get_core_template_by_uid($uid);
		if ($template){
			$template['template']=$this->get_core_template_json($template['uid']);
			return $template;
		}

		//custom
		$template=$this->get_custom_template_by_uid($uid);
		if ($template){
			$template['template']=json_decode($template['template'],true);
		}
		return $template;
	}

	
	/**
	 * 
	 * Return the data type of a template by UID
	 * 
	 */
	function get_template_data_type($uid)
	{
		$template=$this->get_template_by_uid($uid);

		if ($template){
			return $template['data_type'];
		}

		return false;
	}


	function get_templates_by_type($type)
	{
		$fields=array_diff($this->fields,["template"]);
		$fields[]="'custom' as template_type";
		$this->db->select($fields);
		$this->db->order_by('name','ASC');
		$this->db->order_by('changed','DESC');
		$this->db->where("data_type",$type);
		$result= $this->db->get('editor_templates')->result_array();

		$core=$this->get_core_template_by_data_type($type);

		array_splice($result,0,0,$core);
		return $result;
	}

	function get_core_template_by_data_type($data_type)
	{
		$core_=array();
		foreach($this->core_templates as $template){
			if ($template['data_type']==$data_type){
				$core_[]=$template;
			}
		}
		
		return $core_;
	}

	function get_core_template_json($uid)
	{
		foreach($this->core_templates as $template){
			if ($template['uid']==$uid){				
				$template_path=APPPATH.'/views/'.$template["template"];
				if (!file_exists($template_path)){
					throw new Exception("Template not found:",$template['template']);
				}

				try{
					$template_content=file_get_contents($template_path);
				} catch (Exception $e) {
					throw new Exception("Failed to read template file: ".$template_path. " - ".$e->getMessage());
				}

				return json_decode($template_content,true);
			}
		}
		throw new Exception("Core template not found: " . $uid);
	}

    /**
	*
	* Return all templates
	*
	**/
	function select_all()
	{
		$fields=array_diff($this->fields,["template"]);
		
		//add prefix to fields
		$fields=array_map(function($field){
			return 'editor_templates.'.$field;
		},$fields);

		$fields[]="'custom' as template_type";

		$this->db->select($fields);

		//get user info
		$this->db->join('users','users.id=editor_templates.owner_id','left');
		$this->db->select('users.username as owner_username, users.email as owner_email');

		//get changed by
		$this->db->join('users as changed_by','changed_by.id=editor_templates.changed_by','left');
		$this->db->select('changed_by.username as changed_by_username, changed_by.email as changed_by_email');

		//get created by
		$this->db->join('users as created_by','created_by.id=editor_templates.created_by','left');
		$this->db->select('created_by.username as created_by_username, created_by.email as created_by_email');

		$this->db->order_by('editor_templates.changed','DESC');
		$this->db->order_by('editor_templates.name','ASC');
		$this->db->where('editor_templates.is_deleted is NULL or editor_templates.is_deleted =0',null, false);

		$result= $this->db->get('editor_templates')->result_array();
		$default_templates=$this->get_all_default_templates();

		$defaults=array();
		foreach($default_templates as $row)
		{
			$defaults[$row['data_type']]=$row['template_uid'];
		}

		foreach($result as $idx=>$row)
		{
			if (isset($defaults[$row['data_type']]) && $defaults[$row['data_type']] == $row['uid']){				
				$result[$idx]["default"]=true;
			}else
			{
				$result[$idx]["default"]=false;
			}
		}

		$core_templates=$this->core_templates;
		foreach($core_templates as $idx=>$row)
		{
			if (isset($defaults[$row['data_type']]) && $defaults[$row['data_type']] == $row['uid']){				
				$core_templates[$idx]["default"]=true;
			}else
			{
				$core_templates[$idx]["default"]=false;
			}
		}	

		return [
			'core'=>$core_templates,
			'custom'=>$result
		];
	}

    function select_single($uid)
	{
		$this->db->select('*');
		$this->db->where('uid',$uid);
		return $this->db->get('editor_templates')->row_array();
	}

	function get_id_by_uid($uid)
	{
		$this->db->select('id');
		$this->db->where('uid',$uid);
		$result=$this->db->get('editor_templates')->row_array();

		if (isset($result['id'])){
			return $result['id'];
		}
		return false;
	}

	function check_uid_exists($uid)
	{
		$this->db->select('uid');
		$this->db->where('uid',$uid);
		$result=$this->db->get('editor_templates')->row_array();

		if (isset($result['uid'])){
			return true;
		}

		//check core templates
		return $this->check_core_uid_exists($uid);
	}

	function check_core_uid_exists($uid)
	{
		foreach($this->core_templates as $template){
			if ($template['uid']==$uid){
				return true;
			}
		}
		return false;
	}


    function delete($uid, $user_id=null)
	{
		$template=$this->select_single($uid);
		
		if (!$template){
			throw new Exception("Template not found: " .$uid);
		}

		//check if template is in use
		$count=$this->get_project_count($uid);

		if ($count>0){
			throw new Exception("Template is in use by ".$count. " projects");
		}

		//only delete if is_deleted is 1
		if ($template['is_deleted']==0){
			//soft delete
			return $this->soft_delete($uid,$user_id);
		}

        $this->db->where('uid',$uid);
		return $this->db->delete('editor_templates');
	}

	/**
	 * 
	 * 
	 * Flag template as deleted
	 * 
	 * 
	 * 
	 */
	function soft_delete($uid, $user_id=null)
	{
		$template=$this->select_single($uid);
		
		if (!$template){
			throw new Exception("Template not found: " .$uid);
		}

		$options=array();
		$options['deleted_at']=date("U");
		$options['deleted_by']=$user_id;
		$options['is_deleted']=1;

		return $this->update($uid,$options);
	}

    /**
	*
	*	uid
	* 	options	array
	**/
	function update($uid,$options)
	{
		$template=$this->select_single($uid);

		if (!$template){
			throw new Exception("Template not found: " .$uid);
		}

		$valid_fields=$this->fields;
		unset($valid_fields['id']);
		unset($valid_fields['uid']);

		$options['changed']=date("U");	
		
		if (!isset($options['changed'])){
			$options["changed"]=date("U");
		}
		
		$update_arr=array();

		foreach($options as $key=>$value){
			if (in_array($key,$valid_fields)){
				$update_arr[$key]=$value;
			}
		}

		if (isset($update_arr['template'])){
			$update_arr['template']= json_encode($update_arr['template']);
		}
		
		$this->db->where('uid', $uid);
		$result=$this->db->update('editor_templates', $update_arr);

		if ($result==false){
			throw new Exception("Update failed");
		}

		//generate diff
		//$compare_fields=explode(",","author,description,data_type,instructions,lang,name,organization,uid,version,template");
		$compare_fields=array('template');//only compare template field

		$template_id=$template['id'];
		$changed_by=isset($options['changed_by']) ? $options['changed_by'] : null;

		//filter $template fields to only include fields that are in $compare_fields
		$template=array_intersect_key($template,array_flip($compare_fields));
		//remove empty db fields
		$template=array_filter($template);
		$template['template']=json_decode($template['template'],true);

		//filter $options
		$options=array_intersect_key($options,array_flip($compare_fields));

		//generate diff as json-patch
		$diff=$this->get_metadata_diff($template,$options, $ignore_errors=true);
		
		if ($diff!=false && $diff!='[]'){
			$this->Edit_history_model->log($obj_type='template',$obj_id=$template_id,$action='update', $metadata=$diff, $user_id=$changed_by);
		}

//		return $diff;
		return $result;
	}

	function create_template($options)
	{
		$template_options=array();

		$remove_fields=array(
			"id",
			"owner_id",
			"is_private",
			"is_published",
			"is_deleted",
			"deleted_by",
			"deleted_at"
		);

		if (isset($options['result']['template'])){
			$options=$options['result'];
		}

		foreach($options as $key=>$value){
			if (in_array($key,$this->fields) && !in_array($key,$remove_fields)){
				$template_options[$key]=$value;
			}
		}

		if (!isset($template_options['owner_id'])){
			$template_options['owner_id']=$template_options['created_by'];
		}

		if (!isset($template_options['data_type'])){
			throw new Exception("Template::Data type is not set");
		}

		if (!isset($template_options['uid'])){
			$template_options["uid"]=md5($template_options['data_type'].'-'.mt_rand());
		}
		else{
			$exists=$this->check_uid_exists($template_options['uid']);

			if ($exists==true){
				throw new Exception("Template with UID already exists");
			}
		}

		if (isset($template_options['template'])){
			$template_options['template']=json_encode($template_options['template']);
		}

		if (!isset($template_options['created'])){
			$template_options["created"]=date("U");
		}

		if (!isset($template_options['changed'])){
			$template_options["changed"]=date("U");
		}

		return $this->insert($template_options);
	}
	
	
	/**
	* 
	*	Create new template
	*
	**/
	function insert($options)
	{
		//allowed fields
		$valid_fields=$this->fields;

		$options['created']=date("U");
		$options['changed']=date("U");

		$data=array();
		foreach($options as $key=>$value){
			if (in_array($key,$valid_fields)){
				$data[$key]=$value;
			}
		}

		$this->db->insert('editor_templates', $data); 		
		return $this->db->insert_id();
	}


	function duplicate_template($uid, $user_id=null)
	{
		//check core template for uid
		$template=$this->get_core_template_by_uid($uid);
		$template_json='';

		if(!$template){
			$template=$this->get_custom_template_by_uid($uid);
			if($template){
				$template['template']=json_decode($template['template'],true);
			}
		}else{
			$template['template']=$this->get_core_template_json($template['uid']);
		}

		if(!$template){
			throw new Exception("Template ".$uid. " not found");
		}

		//create template
		$template_options=array(
			"uid"=>md5($template['data_type'].'-'.mt_rand()),
			"data_type"=>$template['data_type'],
			"lang"=>'en', 
			"name"=>$template['name']. ' - copy', 
			"template"=>json_encode($template['template']),
			"created"=>date("U"), 
			"changed"=>date("U"),
			"created_by"=>$user_id,
			"changed_by"=>$user_id,
			"owner_id"=>$user_id
		);
		
		return array(
			'id'=>$this->insert($template_options),
			'uid'=>$template_options['uid']
		);
	}

	function get_template_parts_by_uid($uid)
	{
		$template=$this->get_template_by_uid($uid);

		if($template)
		{
			$output=[];
			$this->get_template_part($template,null,$output);
			return $output;
		}
	}

	function get_template_part($items, $parent = null, &$output)
	{
		foreach ($items as $item) {
			if (isset($item['items'])) {
				$parent_ = isset($item['key']) ? $item['key'] : null;
				$this->get_template_part($item['items'], $parent_, $output);
			}
			if (isset($item['key'])) {
				$item["parent"] = $parent;
				$output[$item['key']] = $item;
			}
		}
	}


	function get_all_default_templates()
	{
		$this->db->select("*");
		return $this->db->get("editor_templates_default")->result_array();
	}

	function get_default_template($type)
	{
		$this->db->select("*");
		$this->db->where("data_type",$type);
		return $this->db->get("editor_templates_default")->row_array();
	}

	function set_default_template($type,$template_uid)
	{
		$this->remove_default_template($type);

		$options=array(
			'template_uid'=>$template_uid,
			'data_type'=>$type
		);

		return $this->db->insert("editor_templates_default",$options);
	}

	function remove_default_template($type)
	{
		$this->db->where("data_type",$type);
		return $this->db->delete("editor_templates_default");
	}


	function get_project_template($sid)
	{
		$this->db->select("template_uid");
		$this->db->where("id",$sid);
		$result=$this->db->get("editor_projects")->row_array();

		if (!isset($result['template_uid'])){
			throw new Exception("Project does not have a template");
		}

		$template=$this->get_template_by_uid($result['template_uid']);

		if (!$template){
			throw new Exception("Template not found");
		}

		return $template;
	}


	function set_template_owner($uid,$user_id)
	{
		$this->db->where('uid',$uid);
		$this->db->update('editor_templates',array('owner_id'=>$user_id));
	}


	function share_template($options, $user_id=null)
	{
		if (!is_array($options)){
			throw new Exception("Invalid options. Must be an array");
		}

		foreach($options as $key=>$value)
		{
			if (!isset($value['template_uid'])){
				throw new Exception("Missing parameter: Template UID");
			}

			if (!isset($value['user_id'])){
				throw new Exception("Missing parameter: User ID");
			}

			if (!isset($value['permissions'])){
				$value['permissions']='view';
			}else{
				if (!array_key_exists($value['permissions'],$this->permissions)){
					throw new Exception("Invalid permission: ".$value['permissions']);
				}
			}

			//validate template_uid
			$template_id=$this->get_id_by_uid($value['template_uid']);

			if (!$template_id){
				throw new Exception("Template not found: " .$value['template_uid']);
			}

			//validate user_id
			if ($this->ion_auth_model->is_user_id_valid($value['user_id'])==false){
				throw new Exception("User not found: ".$value['user_id']);
			}

			$this->Template_acl_model->add_user($template_id,$value['user_id'],$value['permissions']);
		}		
	}


	/**
	 * 
	 * Get a list of users that have access to a template
	 * 
	 */
	function template_users($uid)
	{
		$template_id=$this->get_id_by_uid($uid);

		if (!$template_id){
			throw new Exception("Template not found: " .$uid);
		}

		return $this->Template_acl_model->list_users($template_id);
	}


	function unshare_template($template_uid, $user_id)
	{
		$template_id=$this->get_id_by_uid($template_uid);

		if (!$template_id){
			throw new Exception("Template not found: " .$template_uid);
		}

		return $this->Template_acl_model->remove_user($template_id,$user_id);
	}


	
	/**
	 * 
	 * 
	 * Get a count of projects using a template
	 * 
	 * 
	 */
	function get_project_count($template_uid)
	{
		$this->db->select("count(id) as count");
		$this->db->where("template_uid",$template_uid);
		$result=$this->db->get("editor_projects")->row_array();

		if (isset($result['count'])){
			return $result['count'];
		}
		return 0;
	}


	/**
	 * 
	 * Return patch for metadata diff
	 * 
	 */
	function get_metadata_diff($metadata_original, $metadata_updated, $ignore_errors=false)
	{
		try{
			$diff = new JsonDiff($metadata_original, $metadata_updated, JsonDiff::TOLERATE_ASSOCIATIVE_ARRAYS);

			$patch=$diff->getPatch();
			return json_encode($patch);
		} catch (Exception $e) {
			if ($ignore_errors==true){
				return false;
			}
			throw new Exception("Metadata diff failed: ".$e->getMessage());
		}
		
	}


	/**
	 * 
	 * 
	 * Get template revision history
	 */
	function get_template_revision_history($template_uid, $offset=0, $limit=10)
	{
		$template_id=$this->get_id_by_uid($template_uid);

		if (!$template_id){
			throw new Exception("Template not found: " .$template_uid);
		}

		$output=array();
		$output['total']=$this->get_template_revision_history_count($template_uid);
		$output['limit']=$limit;
		$output['offset']=$offset;

		$this->db->select('edit_history.*,users.username');
		$this->db->join('users','users.id=edit_history.user_id','left');
		$this->db->where('obj_type','template');
		$this->db->where('obj_id',$template_id);		
		$this->db->limit($limit,$offset);		

		$this->db->order_by('created','desc');
		$result = $this->db->get("edit_history")->result_array();		

		foreach($result as $idx=>$row)
		{
			$result[$idx]['metadata']=json_decode($row['metadata'],true);
		}

		$output['found']=count($result);
		$output['history']=$result;
		return $output;
	}

	/**
	 * 
	 * 
	 * Get template revision history count
	 * 
	 */
	function get_template_revision_history_count($template_uid)
	{
		$template_id=$this->get_id_by_uid($template_uid);

		if (!$template_id){
			throw new Exception("Template not found: " .$template_uid);
		}

		$this->db->select('count(id) as count');
		$this->db->where('obj_type','template');
		$this->db->where('obj_id',$template_id);		
		$result = $this->db->get("edit_history")->row_array();		

		if (isset($result['count'])){
			return $result['count'];
		}
		return 0;
	}



	function replace_uid($old_uid, $new_uid)
	{
		//check if old uid exists
		if (!$this->check_uid_exists($old_uid)){
			throw new Exception("Old UID not found: " .$old_uid);
		}

		//validate new uid
		$this->validate_uid_format($new_uid);

		//check if new uid exists
		if ($this->check_uid_exists($new_uid)){
			throw new Exception("New UID already exists: " .$new_uid);
		}

		$options=array(
			'uid'=>$new_uid
		);

		$this->db->where('uid',$old_uid);
		return $this->db->update('editor_templates',$options);
	}


	/**
	 * 
	 * 
	 * Validate uid format
	 * 
	 *  - alphanumeric
	 *  - between 3 and 32 characters
	 *  - can contain dashes
	 * 
	 */
	function validate_uid_format($uid)
	{
		if (!preg_match('/^[a-zA-Z0-9-]{3,32}$/', $uid)){
			throw new Exception("Invalid UID format. Must be alphanumeric and between 3 and 32 characters");
		}
	}



	/**
	 * 
	 * 
	 * Get admin metadata templates [custom only]
	 * 
	 * 
	 */
	function get_admin_metadata_templates($template_uid=null)
	{
		$fields=array_diff($this->fields,["template"]);
		$fields[]="'custom' as template_type";
		$this->db->select($fields);
		$this->db->order_by('name','ASC');
		$this->db->order_by('changed','DESC');
		$this->db->where("data_type","admin_meta");

		if ($template_uid){
			$this->db->where("uid",$template_uid);
		}

		$result= $this->db->get('editor_templates')->result_array();
		return $result;
	}

	function get_admin_metadata_template($uid)
	{
		$template=$this->get_admin_metadata_templates($uid);

		if (!$template){
			throw new Exception("Template not found: " .$uid);
		}

		return $this->get_template_by_uid($uid);
	}
    
}