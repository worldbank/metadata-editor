<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Publish_form_validator.php';

/**
 * Shipped publish intake form (project_publications.intake).
 */
class Publish_intake_form
{
	/**
	 * @return string|null Absolute path
	 */
	public static function form_path()
	{
		static $path = null;
		if ($path !== null) {
			return $path;
		}

		$relative = 'publish/publish-intake-form.json';
		$config_path = APPPATH . 'config/publish.php';
		if (is_file($config_path)) {
			$config = array();
			include $config_path;
			if (isset($config['publish_intake_form']) && $config['publish_intake_form'] !== '') {
				$relative = (string) $config['publish_intake_form'];
			}
		}

		$path = APPPATH . ltrim($relative, '/');
		return $path;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public static function load_form()
	{
		$path = self::form_path();
		if (!is_file($path)) {
			throw new Exception('Publish intake form is not available');
		}
		$form = json_decode(file_get_contents($path), true);
		if (!is_array($form)) {
			throw new Exception('Publish intake form is invalid');
		}
		return $form;
	}

	/**
	 * @param array|null $intake
	 * @param array $params strict (bool), context (submit|storage)
	 * @return array
	 * @throws Exception
	 */
	public static function validate($intake, $params = array())
	{
		$form = self::load_form();
		$params['error_prefix'] = 'Publish intake is invalid';
		return Publish_form_validator::validate($form, $intake, $params);
	}

	/**
	 * @param array|null $intake
	 * @param string $context
	 * @return array
	 */
	public static function normalize($intake, $context = Publish_form_validator::CONTEXT_STORAGE)
	{
		$form = self::load_form();
		return Publish_form_validator::normalize($form, is_array($intake) ? $intake : array(), $context);
	}
}
