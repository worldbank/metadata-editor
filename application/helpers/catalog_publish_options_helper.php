<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Placement options JSON: validated against shipped catalog publish forms.
 */

if (!function_exists('catalog_publish_form_library')) {
	function catalog_publish_form_library()
	{
		if (!class_exists('Catalog_publish_form')) {
			require_once APPPATH . 'libraries/Catalog_publish_form.php';
		}
	}
}

if (!function_exists('catalog_publish_options_from_publish_form')) {
	/**
	 * Normalize publish-page / NADA push options for storage on a placement row.
	 *
	 * @param array $options
	 * @return array
	 */
	function catalog_publish_options_from_publish_form($options)
	{
		catalog_publish_form_library();
		return Catalog_publish_form::normalize_options(
			'nada',
			is_array($options) ? $options : array(),
			Catalog_publish_form::CONTEXT_STORAGE
		);
	}
}

if (!function_exists('catalog_publish_options_validate')) {
	/**
	 * Validate placement options using the shipped publish form for catalog.type.
	 *
	 * @param string $type
	 * @param array|null $options
	 * @param array $params optional strict, context (submit|storage)
	 * @return array
	 * @throws Exception
	 */
	function catalog_publish_options_validate($type, $options, $params = array())
	{
		catalog_publish_form_library();
		return Catalog_publish_form::validate($type, $options, $params);
	}
}

if (!function_exists('catalog_publish_form_load')) {
	/**
	 * @param string $type
	 * @return array|null
	 * @throws Exception
	 */
	function catalog_publish_form_load($type)
	{
		catalog_publish_form_library();
		return Catalog_publish_form::load_form($type);
	}
}

if (!function_exists('catalog_publication_status_from_nada_options')) {
	/**
	 * @param array $options
	 * @return string draft|published
	 */
	function catalog_publication_status_from_nada_options($options)
	{
		if (is_array($options) && array_key_exists('published', $options) && (int) $options['published'] === 0) {
			return 'draft';
		}
		return 'published';
	}
}
