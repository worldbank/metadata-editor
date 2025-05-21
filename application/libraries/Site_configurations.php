<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Loads the configurations from the database
 *
 **/
class Site_configurations{

	/**
	 * __construct
	 *
	 * @return void
	 **/
	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('configurations_model');
		
		//load settings from db
		$settings=$this->ci->configurations_model->load();
		
		//list of settings stored in db in JSON format
		$json_formatted=array('admin_allowed_ip','admin_allowed_hosts');
		
		//update the config array with values from DB
		if ($settings)
		{
			foreach($settings as $setting)
			{
				//setting is stored in DB using JSON array format
				if (in_array($setting['name'],$json_formatted))
				{
					//check if JSON is valid
					if (json_decode($setting['value'])!==FALSE)
					{
						$this->ci->config->set_item($setting['name'], json_decode($setting['value']));
					}	
				}
				else //normal non-json values
				{
					$this->ci->config->set_item($setting['name'], $setting['value']);
				}	
			}
		}
		
	}
	
}