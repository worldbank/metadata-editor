<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Validates core editor template registry (config/editor_templates.php vs filesystem).
 */
class Editor_core_template_validator
{
	/**
	 * @param array $options include_orphans (bool), include_defaults (bool)
	 * @return array{errors: string[], warnings: string[]}
	 */
	public static function validate(array $options = array())
	{
		$include_orphans = !isset($options['include_orphans']) || $options['include_orphans'];
		$include_defaults = !isset($options['include_defaults']) || $options['include_defaults'];

		$errors = array();
		$warnings = array();

		$registry = self::load_registry();
		$base_path = self::resolve_base_path();
		$entries = $registry['entries'];
		$defaults = $registry['defaults'];

		$uids = array();
		$registered_files = array();

		foreach ($entries as $entry) {
			$data_type = $entry['data_type'];
			$uid = $entry['uid'];
			$relative = $entry['template'];

			if ($uid === '') {
				$errors[] = "Template entry for data type '{$data_type}' is missing uid.";
				continue;
			}

			if (isset($uids[$uid])) {
				$errors[] = "Duplicate template uid '{$uid}' (data types '{$uids[$uid]}' and '{$data_type}').";
			} else {
				$uids[$uid] = $data_type;
			}

			if ($relative === '') {
				$errors[] = "Template '{$uid}' has an empty template path.";
				continue;
			}

			if (strpos($relative, '..') !== false) {
				$errors[] = "Template '{$uid}' uses an invalid path: {$relative}";
				continue;
			}

			$registered_files[$relative] = $uid;
			$absolute = self::join_path($base_path, $relative);

			if (!is_file($absolute)) {
				$errors[] = "Template file not found for '{$uid}': {$absolute}";
				continue;
			}

			$json = @file_get_contents($absolute);
			if ($json === false) {
				$errors[] = "Template file is not readable for '{$uid}': {$absolute}";
				continue;
			}

			$decoded = json_decode($json, true);
			if (!is_array($decoded)) {
				$errors[] = "Template file is not valid JSON for '{$uid}': {$relative}";
				continue;
			}

			if (!isset($decoded['items']) || !is_array($decoded['items'])) {
				$errors[] = "Template '{$uid}' is missing a top-level items array: {$relative}";
			} else {
				require_once APPPATH . 'libraries/Editor_template_schema_alignment_validator.php';
				$alignment = Editor_template_schema_alignment_validator::validate_template(
					$data_type,
					$decoded,
					$uid
				);
				foreach ($alignment['warnings'] as $message) {
					$warnings[] = $message;
				}
				foreach ($alignment['errors'] as $message) {
					$errors[] = $message;
				}
			}
		}

		if ($include_defaults) {
			foreach ($defaults as $data_type => $template_uid) {
				if (!isset($uids[$template_uid])) {
					$errors[] = "Default template uid '{$template_uid}' for data type '{$data_type}' is not registered in editor_templates.php.";
					continue;
				}

				if ($uids[$template_uid] !== $data_type) {
					$errors[] = "Default template '{$template_uid}' is registered under data type '{$uids[$template_uid]}', not '{$data_type}'.";
				}
			}
		}

		if ($include_orphans && is_dir($base_path)) {
			foreach (glob($base_path . '/*.json') as $absolute) {
				$basename = basename($absolute);
				if (!isset($registered_files[$basename])) {
					$warnings[] = "Orphan template file on disk (not in editor_templates.php): {$basename}";
				}
			}
		}

		return array(
			'errors' => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * @return array{entries: array<int, array>, defaults: array<string, string>}
	 */
	public static function load_registry()
	{
		require APPPATH . 'config/editor_templates.php';

		if (!isset($config) || !is_array($config)) {
			throw new RuntimeException('config/editor_templates.php did not define $config.');
		}

		$defaults = array();
		if (isset($config['editor_template_defaults']) && is_array($config['editor_template_defaults'])) {
			$defaults = $config['editor_template_defaults'];
		}

		$entries = array();
		foreach ($config as $data_type => $templates) {
			if ($data_type === 'editor_template_defaults' || !is_array($templates)) {
				continue;
			}

			foreach ($templates as $template) {
				if (!is_array($template) || !isset($template['uid'])) {
					continue;
				}

				$entries[] = array(
					'data_type' => $data_type,
					'uid' => isset($template['uid']) ? (string)$template['uid'] : '',
					'template' => isset($template['template']) ? (string)$template['template'] : '',
					'lang' => isset($template['lang']) ? (string)$template['lang'] : '',
					'name' => isset($template['name']) ? (string)$template['name'] : '',
				);
			}
		}

		return array(
			'entries' => $entries,
			'defaults' => $defaults,
		);
	}

	public static function resolve_base_path()
	{
		$base_path = APPPATH . 'editor_templates';

		if (function_exists('get_instance')) {
			$ci = get_instance();
			if (isset($ci->config)) {
				$ci->config->load('editor');
				$editor_config = $ci->config->item('editor');
				if (is_array($editor_config) && !empty($editor_config['core_template_path'])) {
					$base_path = $editor_config['core_template_path'];
				}
			}
		} else {
			$editor_config_file = APPPATH . 'config/editor.php';
			if (is_file($editor_config_file)) {
				require $editor_config_file;
				if (isset($config['editor']['core_template_path']) && $config['editor']['core_template_path'] !== '') {
					$base_path = $config['editor']['core_template_path'];
				}
			}
		}

		return self::normalize_path(rtrim($base_path, '/'));
	}

	private static function join_path($base, $relative)
	{
		return self::normalize_path($base . '/' . ltrim(str_replace('\\', '/', $relative), '/'));
	}

	private static function normalize_path($path)
	{
		if (function_exists('unix_path')) {
			return unix_path($path);
		}

		return str_replace('\\', '/', $path);
	}
}
