<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Indicator_util
 *
 * Utilities for indicator/timeseries projects, including global DSD reference export.
 */
class Indicator_util
{
	private $ci;

	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Indicator_dsd_model');
		$this->ci->load->model('Editor_project_dsd_model');
	}

	/**
	 * Export fields for project JSON: data_structure_reference when bound to global registry.
	 *
	 * @param int $sid
	 * @return array Keys: data_structure_reference (may be empty)
	 */
	public function get_data_structure_export_fields($sid)
	{
		$this->ci->load->library('Data_structure_util');
		$ref = $this->ci->data_structure_util->get_project_reference($sid);
		if ($ref) {
			return array('data_structure_reference' => $ref);
		}

		return array();
	}
}
