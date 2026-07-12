<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Loads site configurations from the database into CI config.
 */
class Site_configurations {

	/** @var CI_Controller */
	protected $ci;

	/**
	 * @return void
	 */
	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('configurations_model');

		$settings = $this->ci->configurations_model->load();

		$json_formatted = array(
			'admin_allowed_ip',
			'admin_allowed_hosts',
			'supported_languages',
			'default_user_roles',
			'enabled_project_schemas',
		);

		if ($settings) {
			foreach ($settings as $setting) {
				if (in_array($setting['name'], $json_formatted)) {
					$decoded = json_decode($setting['value'], true);
					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$this->ci->config->set_item($setting['name'], $decoded);
					}
				} else {
					$this->ci->config->set_item($setting['name'], $setting['value']);
				}
			}
		}

		$this->normalize_boolean_config('project_sharing');
		$this->normalize_boolean_config('metadata_assessment_enabled');
		$this->normalize_boolean_config('issues_enabled');
		$this->normalize_boolean_config('data_structures_enabled');
		$this->normalize_boolean_config('schemas_enabled');
		$this->normalize_boolean_config('tags_enabled');
		$this->normalize_enabled_project_schemas();
		$this->build_language_codes();
	}

	/**
	 * Cast a site config flag from DB string to boolean for strict checks in API code.
	 *
	 * @param string $key
	 */
	protected function normalize_boolean_config($key)
	{
		$value = $this->ci->config->item($key);
		if ($value === null) {
			return;
		}

		$enabled = !($value === false || $value === 0 || $value === '0' || $value === 'false');
		$this->ci->config->set_item($key, $enabled);
	}

	/**
	 * Normalize enabled_project_schemas: empty array means all schemas enabled (null).
	 */
	protected function normalize_enabled_project_schemas()
	{
		$value = $this->ci->config->item('enabled_project_schemas');

		if ($value === null || $value === '' || $value === false) {
			$this->ci->config->set_item('enabled_project_schemas', null);
			return;
		}

		if (is_string($value)) {
			$decoded = json_decode($value, true);
			$value = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
		}

		if (!is_array($value) || empty($value)) {
			$this->ci->config->set_item('enabled_project_schemas', null);
			return;
		}

		$this->ci->config->set_item(
			'enabled_project_schemas',
			array_values(array_unique(array_map('strval', $value)))
		);
	}

	/**
	 * Derive language_codes and folder list from supported_languages.
	 */
	protected function build_language_codes()
	{
		$lang_data = $this->ci->config->item('supported_languages');
		if (!is_array($lang_data) || empty($lang_data)) {
			return;
		}

		$first = $lang_data[0];
		if (!((is_array($first) && isset($first['folder'])) ||
			(is_object($first) && isset($first->folder)))) {
			return;
		}

		$language_codes = array();
		$folder_names   = array();
		foreach ($lang_data as $entry) {
			$entry  = is_array($entry) ? $entry : (array)$entry;
			$folder = isset($entry['folder']) ? $entry['folder'] : null;
			if (!$folder) {
				continue;
			}
			$folder_names[] = $folder;
			$language_codes[$folder] = array(
				'name'          => $folder,
				'language_file' => $folder,
				'display'       => isset($entry['display'])   ? $entry['display']   : ucfirst($folder),
				'code'          => isset($entry['code'])      ? $entry['code']      : '',
				'direction'     => isset($entry['direction']) ? $entry['direction'] : 'ltr',
			);
		}
		$this->ci->config->set_item('language_codes',      $language_codes);
		$this->ci->config->set_item('supported_languages', $folder_names);
	}
}
