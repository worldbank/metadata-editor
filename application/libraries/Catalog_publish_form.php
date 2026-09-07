<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Publish_form_validator.php';

/**
 * Shipped catalog publish forms: load, normalize, and validate placement options.
 */
class Catalog_publish_form
{
	const CONTEXT_SUBMIT = 'submit';
	const CONTEXT_STORAGE = 'storage';
	const CONTEXT_READY = 'ready';

	/**
	 * @param string $type
	 * @return string
	 */
	public static function normalize_catalog_type($type)
	{
		$type = strtolower(trim((string) $type));
		if ($type === '' || $type === 'external') {
			return $type === 'external' ? 'other' : 'nada';
		}
		return $type;
	}

	/**
	 * @return array<string, array>
	 */
	public static function types_config()
	{
		static $cached = null;
		if ($cached !== null) {
			return $cached;
		}

		$cached = array();
		$path = APPPATH . 'config/catalog_types.php';
		if (is_file($path)) {
			$config = array();
			include $path;
			if (isset($config['catalog_types']) && is_array($config['catalog_types'])) {
				$cached = $config['catalog_types'];
			}
		}
		return $cached;
	}

	/**
	 * @param string $type
	 * @return array|null
	 */
	public static function get_type_config($type)
	{
		$type = self::normalize_catalog_type($type);
		$all = self::types_config();
		return isset($all[$type]) ? $all[$type] : null;
	}

	/**
	 * @param string $type
	 * @return string|null Absolute path
	 */
	public static function publish_form_path($type)
	{
		$config = self::get_type_config($type);
		if (!$config || empty($config['publish_form'])) {
			return null;
		}
		return APPPATH . ltrim((string) $config['publish_form'], '/');
	}

	/**
	 * @param string $type
	 * @return array|null
	 * @throws Exception
	 */
	public static function load_form($type)
	{
		$path = self::publish_form_path($type);
		if ($path === null) {
			return null;
		}
		if (!is_file($path)) {
			throw new Exception('Publish form is not available for this catalog type');
		}
		$json = file_get_contents($path);
		$form = json_decode($json, true);
		if (!is_array($form)) {
			throw new Exception('Publish form is invalid');
		}
		return $form;
	}

	/**
	 * @param array $form
	 * @param string|null $context
	 * @return array<int, array>
	 */
	public static function fields_for_context(array $form, $context = null)
	{
		return Publish_form_validator::fields_for_context($form, $context);
	}

	/**
	 * @param array $form
	 * @return array<int, string>
	 */
	public static function allowed_option_keys(array $form)
	{
		return Publish_form_validator::allowed_keys($form);
	}

	/**
	 * @param string $type
	 * @param array|null $options
	 * @param string $context
	 * @return array
	 */
	public static function normalize_options($type, $options, $context = self::CONTEXT_STORAGE)
	{
		if (!is_array($options)) {
			return array();
		}
		$form = self::load_form($type);
		if ($form === null) {
			return array();
		}
		return Publish_form_validator::normalize($form, $options, $context);
	}

	/**
	 * @param string $type
	 * @param array|null $options
	 * @param array $params strict (bool), context (submit|storage)
	 * @return array normalized options
	 * @throws Exception
	 */
	public static function validate($type, $options, $params = array())
	{
		$type = self::normalize_catalog_type($type);
		if ($options === null || $options === '') {
			$options = array();
		}
		if (!is_array($options)) {
			throw new Exception('Publish options must be an object');
		}

		$form = self::load_form($type);
		if ($form === null) {
			if ($options === array()) {
				return array();
			}
			throw new Exception('Publish options are not supported for this catalog type');
		}

		$params['error_prefix'] = 'Publish options are invalid';
		return Publish_form_validator::validate($form, $options, $params);
	}
}
