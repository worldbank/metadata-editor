<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Publish intake JSON on project_publications.intake
 */

if (!function_exists('publish_intake_form_library')) {
	function publish_intake_form_library()
	{
		if (!class_exists('Publish_intake_form')) {
			require_once APPPATH . 'libraries/Publish_form_validator.php';
			require_once APPPATH . 'libraries/Publish_intake_form.php';
		}
	}
}

if (!function_exists('publish_intake_form_load')) {
	/**
	 * @return array
	 * @throws Exception
	 */
	function publish_intake_form_load()
	{
		publish_intake_form_library();
		return Publish_intake_form::load_form();
	}
}

if (!function_exists('publish_intake_validate')) {
	/**
	 * @param array|null $intake
	 * @param array $params optional strict, context
	 * @return array
	 * @throws Exception
	 */
	function publish_intake_validate($intake, $params = array())
	{
		publish_intake_form_library();
		return Publish_intake_form::validate($intake, $params);
	}
}

if (!function_exists('publish_intake_normalize')) {
	/**
	 * @param array|null $intake
	 * @param string $context
	 * @return array
	 */
	function publish_intake_normalize($intake, $context = 'storage')
	{
		publish_intake_form_library();
		return Publish_intake_form::normalize($intake, $context);
	}
}
