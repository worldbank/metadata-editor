<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Registry and visibility helpers for per-template project editor modules.
 */
class Editor_project_modules
{
	/** @var array|null */
	private static $modules_cache = null;

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function all_modules()
	{
		if (self::$modules_cache !== null) {
			return self::$modules_cache;
		}

		$config = array();
		if (function_exists('get_instance')) {
			$ci =& get_instance();
			$ci->load->config('editor_project_modules');
			$config = $ci->config->item('editor_project_modules');
		} else {
			$config_path = APPPATH . 'config/editor_project_modules.php';
			if (is_readable($config_path)) {
				include $config_path;
				if (isset($config['editor_project_modules'])) {
					$config = $config['editor_project_modules'];
				}
			}
		}

		$modules = isset($config['modules']) && is_array($config['modules']) ? $config['modules'] : array();
		self::$modules_cache = $modules;
		return self::$modules_cache;
	}

	/**
	 * @param string|null $data_type
	 * @return array<int, array<string, mixed>>
	 */
	public static function modules_for_data_type($data_type)
	{
		if ($data_type === null || $data_type === '') {
			return array();
		}

		$out = array();
		foreach (self::all_modules() as $module) {
			if (!isset($module['data_types']) || !is_array($module['data_types'])) {
				continue;
			}
			if (in_array($data_type, $module['data_types'], true)) {
				$out[] = $module;
			}
		}
		return $out;
	}

	/**
	 * Whether a module is shown in the project editor (default: true).
	 *
	 * @param array<string, mixed> $template Decoded template root (type, items, editor_modules, …)
	 * @param array<string, mixed>|string $module Module registry entry or module id
	 */
	public static function is_module_visible($template, $module)
	{
		if (!is_array($template)) {
			return true;
		}

		$module_id = is_array($module) && !empty($module['id']) ? $module['id'] : $module;
		if (!is_string($module_id) || $module_id === '') {
			return true;
		}

		if (!isset($template['editor_modules']) || !is_array($template['editor_modules'])) {
			return true;
		}
		if (!isset($template['editor_modules'][$module_id]) || !is_array($template['editor_modules'][$module_id])) {
			return true;
		}

		$entry = $template['editor_modules'][$module_id];
		return !isset($entry['show_in_editor']) || $entry['show_in_editor'] !== false;
	}
}
