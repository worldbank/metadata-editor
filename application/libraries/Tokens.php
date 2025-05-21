<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
*
* 
* Tokens
* 
*/

/**
 * 
 * 
 * 
 * 
 * 
 * 
 *  [search-box] - search box
 *  [search-box:repo] - search box for current repo
 *  [latest-entries] - latest entries for the catalog
 *  [latest-entries:repo] - latest entries for current repo
 *  [featured] - featured entries for the catalog
 *  [featured:repo] - featured entries for current repo
 *  [site-url] - site url
 *  [site-base-url] - base url
 */
 
class Tokens
{
	protected $ci;

	function __construct()
	{
		$this->ci =& get_instance();		
		$this->ci->load->model("Stats_model");
	}

	function replace_tokens($html)
	{
		preg_match_all("/\[([^\]]*)\]/", $html, $matches);

		foreach($matches[1] as $token){
			$method=str_replace("-","_",$token);
			$method_args=explode(":",$method);
			$method=$method_args[0];
			unset($method_args[0]);

			$token_value=null;

			if (in_array(($method), array_map('strtolower', get_class_methods($this))) ){				
				$token_value=call_user_func_array(array($this, $method), $method_args);

				$html=str_replace('['.$token.']',$token_value,$html);
			}
		}

		return $html;
	}

	function site_base_url()
	{
		return base_url();
	}

	function search_box($repositoryid=null)
	{
		$options=array(
			'repositoryid'=>$repositoryid,
			'total_entries'=>$this->ci->Stats_model->get_survey_count($repositoryid)
		);

		return $this->ci->load->view("tokens/search-box",$options,true);
	}


	function latest_entries($repositoryid=null)
	{
		$options=array(
			'repositoryid'=>$repositoryid,
			'rows'=>$this->ci->Stats_model->get_latest_surveys(10,$repositoryid)
		);

		return $this->ci->load->view("tokens/recent-entries",$options,true);
	}

	function counts_by_type($repositoryid=null)
	{
		$options=array(
			'repositoryid'=>$repositoryid,
			'counts'=>$this->ci->Stats_model->get_counts_by_type($repositoryid)
		);

		return $this->ci->load->view("tokens/counts-by-type",$options,true);
	}

	function cards_featured_entries($repositoryid=null)
	{
		$this->ci->load->model("Repository_model");
		$options['rows']=$this->ci->Repository_model->get_featured_study($repositoryid);
		return $this->ci->load->view("tokens/cards-featured-entries",$options,true);
	}

	

}
