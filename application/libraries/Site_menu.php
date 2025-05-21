<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * NADA Site Menu
 * 
 *
 * @category	Site Menu/Navigation
 *
 */ 
class Site_Menu
{
	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', "Site_Menu Class Initialized.");
		$this->ci =& get_instance();
		
		$this->ci->lang->load('site_menu');
		//$this->ci->output->enable_profiler(TRUE);
		$this->ci->load->config("site_menus");		
	}

	/**
	*
	* return an array of all menu items
	**/
	function get_menu_items_array()
	{
		return $this->ci->config->item("site_menu");
	}

	/**
	* Returns a formatted menu true for site navigation
	*
	**/
	function get_formatted_menu_tree($items=NULL)
	{
		if($items==NULL)
		{
			$items=$this->get_menu_items_array();
		}

		$options['items']=$items;
		$options['collections']=[];//$this->get_collections_menu();
		$content=$this->ci->load->view('admin/site_menu.php',$options,true);
		return $content;		
	}
	
	
		
}

