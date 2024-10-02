<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Project_search
{
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
		'template_uid'
		);
		
	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Collection_model');
		$this->ci->load->model('Collection_tree_model');
		$this->ci->load->model('Editor_model');
	}


	/**
	 * 
	 * Return all projects
	 * 
	 * @offset - offset
	 * @limit - number of rows to return
	 * @fields - (optional) list of fields
	 * 
	 */
	function search($limit=10,$offset=0, $fields=array(), $search_options=array())
	{
		if (empty($fields)){
			$fields=$this->listing_fields;
		}

		foreach($fields as $idx=>$field){
			$fields[$idx]="editor_projects.".$field;
		}

		$fields[]="users.username, users_cr.username as username_cr";

		//sort [sort_by sort_order]
		$sort_=$this->get_sort_order($search_options);
				
		$this->ci->db->select(implode(",",$fields));
		$this->ci->db->order_by($sort_['sort_by'],$sort_['sort_order']);
		$this->ci->db->join("users", "users.id=editor_projects.changed_by");
		$this->ci->db->join("users as users_cr", "users_cr.id=editor_projects.created_by","left");

		if ($limit>0){
			$this->ci->db->limit($limit, $offset);
		}

		$search_filters=$this->apply_search_filters($search_options);		
		$result= $this->ci->db->get("editor_projects");
		
		if ($result){
			$result=$result->result_array();			
		}else{
			$error=$this->ci->db->error();
			throw  new Exception(implode(", ", $error));
		}

		if ($result){
			$result=$this->ci->Editor_model->decode_encoded_fields_rows($result);
		}

		return array(
			'result'=>$result,
			//'db_query'=>$this->ci->db->last_query(),
			'filters'=>$search_filters
		);
	}

	//returns the total 
	function get_total_count($search_options=array())
	{
		$this->apply_search_filters(($search_options));
		$this->ci->db->join("users", "users.id=editor_projects.changed_by");
		$result=$this->ci->db->count_all_results('editor_projects');

		return $result;
	}


	function get_sort_order($search_options)
	{
		$sort_by=isset($search_options['sort_by']) ? $search_options['sort_by'] : '';

		switch($sort_by){
			case 'title_asc':
				$sort_by='editor_projects.title';
				$sort_order='asc';
				break;
			case 'title_desc':
				$sort_by='editor_projects.title';
				$sort_order='desc';
				break;
			case 'updated_asc':
				$sort_by='editor_projects.changed';
				$sort_order='asc';
				break;
			case 'updated_desc':
				$sort_by='editor_projects.changed';
				$sort_order='desc';
				break;
			default:
				$sort_by='editor_projects.changed';
				$sort_order='desc';
				break;
		}

		return [
			'sort_by'=>$sort_by,
			'sort_order'=>$sort_order
		];
	}


	private function apply_search_filters($search_options)
	{
		$applied_filters=array();
		$ownership_types=array(
			"self",
			"shared"
		);

		/*
		if (isset($search_options['ownership']) && in_array($search_options['ownership'],$ownership_types)) {
			switch($search_options['ownership']){
				case 'self':
					$this->ci->db->where('editor_projects.created_by',(int)$project_owners[0]);
					break;
				case 'shared':
					$this->ci->db->where('editor_projects.created_by !=',(int)$project_owners[0]);
					break;
			}		
			
			$applied_filters['ownerships']=$search_options['ownership'];
		}
		*/

		//filter by ownership
		$project_owners=$this->parse_filter_values_as_int($this->get_search_filter($search_options,'user_id'));

		if ($project_owners){
			
			//projects user owns by direct sharing
			$subquery='select sid from editor_project_owners where user_id='.(int)$project_owners[0];

			//projects user can access via collections
			$collection_query='select sid from editor_collection_projects 
					inner join editor_collection_access on editor_collection_access.collection_id=editor_collection_projects.collection_id
					where editor_collection_access.user_id='.(int)$project_owners[0];
			
			$query='(editor_projects.created_by='.(int)$project_owners[0]
				 .' OR editor_projects.id in( '. $subquery.') OR editor_projects.id in ('.$collection_query.')) ';
			
			//ownership
			if (isset($search_options['ownership']) && in_array($search_options['ownership'],$ownership_types)) {
				switch($search_options['ownership']){
					case 'self':
						$this->ci->db->where('editor_projects.created_by',(int)$project_owners[0]);
						break;
					case 'shared':

						//direct shared
						$direct_shared='editor_projects.id in (select sid from editor_project_owners where user_id='.(int)$project_owners[0].')';
						$this->ci->db->or_where($direct_shared);

						//collections
						$query_shared_only='(editor_projects.created_by!='.(int)$project_owners[0]
							.' OR editor_projects.id in ('.$collection_query.') )';
						$this->ci->db->where($query_shared_only);
						break;
				}		
				$applied_filters['user_id']=$project_owners;
				$applied_filters['ownerships']=$search_options['ownership'];
			}
			else{
				//show all shared and owned projects
				$this->ci->db->where($query,null, false);
				$applied_filters['user_id']=$project_owners;
			}
		}

		//filter by collection
		$collection_filters=$this->parse_filter_values_as_int($this->get_search_filter($search_options,'collection'));
		
		if ($collection_filters){

			$subquery='select sid from editor_collection_projects where collection_id in ('.implode(",",$collection_filters).')';			
			$query='(editor_projects.id in( '. $subquery.')) ';
			$this->ci->db->where($query,null, false);
			$applied_filters['collection']=$project_owners;
		}


		//filter by type
		$data_type_filters=$this->get_search_filter($search_options,'type');
		
		if ($data_type_filters){
			$this->ci->db->where_in('type',$data_type_filters);
			$applied_filters['type']=$data_type_filters;
		}

		//keywords
		if (isset($search_options['keywords']) && !empty($search_options['keywords'])) {
			$keywords_query=$this->build_keywords_fulltext_query($search_options['keywords']);

			$escaped_keywords=$this->ci->db->escape('%'.trim($search_options['keywords']).'%');
			$where = sprintf('(title like %s OR idno like %s OR study_idno like %s) OR %s',
                        $escaped_keywords,
                        $escaped_keywords,
						$escaped_keywords,
						$keywords_query
                    );
            $this->ci->db->where($where,NULL,FALSE);			
			$applied_filters['keywords']=$search_options['keywords'];
		}

		/*
		//ownership
		$ownership_types=array(
			"self",
			"shared"
		);

		if (isset($search_options['ownership']) && in_array($search_options['ownership'],$ownership_types)) {
			switch($search_options['ownership']){
				case 'self':
					$this->ci->db->where('editor_projects.created_by',(int)$project_owners[0]);
					break;
				case 'shared':
					$this->ci->db->where('editor_projects.created_by !=',(int)$project_owners[0]);
					break;
			}		
			
			$applied_filters['ownerships']=$search_options['ownership'];
		}
		*/
		
		return $applied_filters;		
	}


	function build_keywords_like_query($keywords)
	{
		//split keywords
		$keywords_list=explode(" ",$keywords);
		
		$keyword_query=array();
		foreach($keywords_list as $idx=>$keyword){
			$keyword_query[]='title like ' .$this->ci->db->escape('%'.$keyword.'%');
		}

		$keyword_query=implode(" OR ",$keyword_query);		
		return '('.$keyword_query.')';
	}

	function build_keywords_fulltext_query($keywords)
	{
		//remove characters that are not allowed in fulltext search
		$keywords=preg_replace('/[@+\-&|!(){}[\]^"~*?:\/\\\]/','',$keywords);
		$keywords=trim($keywords);		

		$keywords_list=explode(" ",$keywords);
		$keyword_query=array();
		foreach($keywords_list as $idx=>$keyword){
			$keyword_query[]='+'.$keyword.'*';
		}

		return 'MATCH(title) AGAINST('.$this->ci->db->escape( implode(" ", $keyword_query) ).' IN BOOLEAN MODE)';
	}

	function parse_filter_values_as_int($values)
	{
		$parsed_values=array();

		if (!is_array($values)){
			$values=array($values);
		}

		foreach($values as $idx=>$value){
			if (is_numeric($value)){
				$parsed_values[]=(int)$value;
			}
		}

		return $parsed_values;
	}

	function get_search_filter($options,$filter_key)
	{
		if (!isset($options[$filter_key])){
			return false;
		}

		$values=$options[$filter_key];

		if (!$values){
			return false;
		}

		if ($values!=""){
			$values=explode(",",$values);
		}
		
		if (!is_array($values)){
			$values=array($values);
		}

		foreach($values as $idx=>$value){
			$values[$idx]=xss_clean($value);
		}

		return $values;
	}

	function get_facets($user_id=null)
	{
		$facets=array();

		//data types
		$facets['type']=array(
			array("id"=>"survey","title"=>"Microdata"),
			array("id"=>"document","title"=>"Document"),
			array("id"=>"table","title"=>"Table"),
			array("id"=>"geospatial","title"=>"Geospatial"),
			array("id"=>"image","title"=>"Image"),
			array("id"=>"script", "title"=>"Script"),
			array("id"=>"video","title"=>"Video"),
			array("id"=>"timeseries","title"=>"Timeseries"),
			array("id"=>"timeseries-db","title"=>"Timeseries DB"),
		);

		//collections
		$facets['collection']=$this->ci->Collection_tree_model->collections_tree_by_user_access($user_id);

		//ownership type
		$facets['ownership']=array(
			array("id"=>"shared","title"=>"Shared"),
			array("id"=>"self","title"=>"My projects"),
		);
		
		return $facets;
	}


}

