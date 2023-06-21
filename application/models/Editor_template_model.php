<?php

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
		"template", 
		"created", 
		"changed"
	);

	private $core_templates=[];
	private $ci;

    public function __construct()
    {
        parent::__construct();		
		$this->ci =& get_instance();
		$this->init_core_templates();
    }


	function init_core_templates()
	{
		require_once(APPPATH.'config/editor_templates.php');

		if (!isset($config)){		
			throw new Exception("config/editor_templates not loaded");
		}

		//echo "<pre>";
		//print_r($config);
		//die();
		
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

				return json_decode(file_get_contents($template_path),true);
			}
		}		
	}

    /**
	*
	* Return all templates
	*
	**/
	function select_all()
	{
		$fields=array_diff($this->fields,["template"]);
		$fields[]="'custom' as template_type";
		$this->db->select($fields);
		$this->db->order_by('name','ASC');
		$this->db->order_by('changed','DESC');
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

	function check_uid_exists($uid)
	{
		$this->db->select('uid');
		$this->db->where('uid',$uid);
		$result=$this->db->get('editor_templates')->row_array();

		if (isset($result['uid'])){
			return true;
		}
		return false;
	}

    function delete($uid)
	{		
        $this->db->where('uid',$uid);
		return $this->db->delete('editor_templates');
	}

    /**
	*
	*	uid
	* 	options	array
	**/
	function update($uid,$options)
	{
		//allowed fields
		$valid_fields=$this->fields;
		unset($valid_fields['id']);
		unset($valid_fields['uid']);

		$options['changed']=date("U");		
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
		return $result;		
	}

	function create_template($options)
	{

		$template_options=array();

		if (isset($options['result']['template'])){
			$options=$options['result'];
		}

		foreach($options as $key=>$value){
			if (in_array($key,$this->fields)){
				$template_options[$key]=$value;
			}
		}

		if (isset($template_options['id'])){
			unset($template_options["id"]);
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

		$template_options["created"]=date("U");
		$template_options["changed"]=date("U");

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


	function duplicate_template($uid)
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
			"changed"=>date("U")
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



    
}