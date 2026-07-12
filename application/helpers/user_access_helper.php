<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Site user-access settings and editor ACL flags for the web UI.
 */

if (!function_exists('default_user_role_names')) {

	/** Role names that may be assigned automatically to new users (never managers/admin). */
	function default_user_role_allowlist()
	{
		return array('User', 'Editor');
	}

	/**
	 * Role names assigned to newly registered users (from site configurations DB).
	 *
	 * @return string[]
	 */
	function default_user_role_names()
	{
		$ci =& get_instance();
		$roles = $ci->config->item('default_user_roles');

		if (is_string($roles)) {
			$decoded = json_decode($roles, true);
			$roles = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
		}

		if (!is_array($roles) || empty($roles)) {
			$fallback = $ci->config->item('editor_user_roles');
			$roles = is_array($fallback) ? $fallback : array();
		}

		$roles = array_values(array_intersect(default_user_role_allowlist(), array_map('strval', $roles)));

		if (empty($roles)) {
			return array('User');
		}

		return $roles;
	}
}

if (!function_exists('default_editor_role_enabled')) {

	/**
	 * Whether new users automatically receive the Editor role.
	 */
	function default_editor_role_enabled()
	{
		return in_array('Editor', default_user_role_names(), true);
	}
}

if (!function_exists('project_sharing_enabled')) {

	/**
	 * Whether project sharing is enabled (site configuration or editor.php fallback).
	 */
	function project_sharing_enabled()
	{
		$ci =& get_instance();
		$value = $ci->config->item('project_sharing');

		if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
			return false;
		}

		return true;
	}
}

if (!function_exists('metadata_assessment_enabled')) {

	/**
	 * Whether metadata assessment (Issues → Assess metadata) is enabled site-wide.
	 */
	function metadata_assessment_enabled()
	{
		$ci =& get_instance();
		$value = $ci->config->item('metadata_assessment_enabled');

		if ($value === false || $value === 0 || $value === '0' || $value === 'false' || $value === null) {
			return false;
		}

		return true;
	}
}

if (!function_exists('metadata_assessment_monthly_limit')) {

	/**
	 * Site-wide monthly metadata assessment limit.
	 * Returns 0 when unlimited (no enforcement).
	 */
	function metadata_assessment_monthly_limit()
	{
		$ci =& get_instance();
		$value = $ci->config->item('metadata_assessment_monthly_limit');

		if ($value === false || $value === null || $value === '') {
			return 200;
		}

		return max(0, (int) $value);
	}
}

if (!function_exists('metadata_assessment_monthly_limit_applies')) {

	/**
	 * Whether a monthly assessment cap is configured (limit > 0).
	 * A limit of 0 means unlimited.
	 */
	function metadata_assessment_monthly_limit_applies()
	{
		return metadata_assessment_monthly_limit() > 0;
	}
}

if (!function_exists('user_can_access_projects')) {

	/**
	 * Whether the user may open the projects editor (/projects).
	 */
	function user_can_access_projects($user = null)
	{
		$ci =& get_instance();

		if (!isset($ci->acl_manager)) {
			$ci->load->library('Acl_manager', null, 'acl_manager');
		}

		if ($user === null) {
			$user = $ci->acl_manager->current_user();
		}

		if (!$user) {
			return false;
		}

		return $ci->acl_manager->check_access('editor', 'view', $user)
			|| $ci->acl_manager->check_access('project_manager', 'view', $user);
	}
}

if (!function_exists('user_has_global_project_access')) {

	/**
	 * Global access to all projects via the Project manager role (not site Admin).
	 */
	function user_has_global_project_access($user = null, $permission = 'view')
	{
		$ci =& get_instance();

		if (!isset($ci->acl_manager)) {
			$ci->load->library('Acl_manager', null, 'acl_manager');
		}

		if ($user === null) {
			$user = $ci->acl_manager->current_user();
		}

		if (!$user) {
			return false;
		}

		if ($permission === null || $permission === '') {
			$permission = 'view';
		}

		return $ci->acl_manager->check_access('project_manager', $permission, $user);
	}
}

if (!function_exists('site_feature_enabled')) {

	/**
	 * Whether a site feature module is enabled (issues, data_structures, schemas, tags).
	 */
	function site_feature_enabled($feature)
	{
		$key_map = array(
			'issues' => 'issues_enabled',
			'data_structures' => 'data_structures_enabled',
			'schemas' => 'schemas_enabled',
			'tags' => 'tags_enabled',
		);

		if (!isset($key_map[$feature])) {
			return true;
		}

		$ci =& get_instance();
		$value = $ci->config->item($key_map[$feature]);

		if ($value === false || $value === 0 || $value === '0' || $value === 'false' || $value === null) {
			return false;
		}

		return true;
	}
}

if (!function_exists('enabled_project_schema_uids')) {

	/**
	 * Enabled project schema UIDs for create/import UI.
	 * Returns null when all schemas are enabled.
	 *
	 * @return string[]|null
	 */
	function enabled_project_schema_uids()
	{
		$ci =& get_instance();
		$value = $ci->config->item('enabled_project_schemas');

		if ($value === null || $value === '' || $value === false) {
			return null;
		}

		if (is_string($value)) {
			$decoded = json_decode($value, true);
			$value = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
		}

		if (!is_array($value) || empty($value)) {
			return null;
		}

		return array_values(array_unique(array_map('strval', $value)));
	}
}

if (!function_exists('project_schema_enabled')) {

	/**
	 * Whether a project schema UID is enabled for create/import UI.
	 */
	function project_schema_enabled($uid)
	{
		$enabled = enabled_project_schema_uids();
		if ($enabled === null) {
			return true;
		}

		return in_array((string) $uid, $enabled, true);
	}
}

if (!function_exists('site_features_user_info')) {

	/**
	 * Site feature flags for CI.user_info (Vue editor shell).
	 *
	 * @return array<string, mixed>
	 */
	function site_features_user_info()
	{
		$enabled = enabled_project_schema_uids();

		return array(
			'issues_enabled' => site_feature_enabled('issues'),
			'data_structures_enabled' => site_feature_enabled('data_structures'),
			'schemas_enabled' => site_feature_enabled('schemas'),
			'tags_enabled' => site_feature_enabled('tags'),
			'enabled_project_schemas' => $enabled,
		);
	}
}

if (!function_exists('build_editor_user_info')) {

	/**
	 * Standard CI.user_info payload for Vue editor shell pages.
	 *
	 * @param object|null $user
	 * @param bool $show_editor_access_notice When true, UI may show the missing-editor-role notice
	 * @return array<string, mixed>
	 */
	function build_editor_user_info($user = null, $show_editor_access_notice = false)
	{
		$ci =& get_instance();

		if (!isset($ci->acl_manager)) {
			$ci->load->library('Acl_manager', null, 'acl_manager');
		}

		if ($user === null) {
			$user = $ci->acl_manager->current_user();
		}

		$username = $ci->session->userdata('username');
		$is_admin = $user ? (bool) $ci->acl_manager->user_is_admin($user) : false;
		$has_editor_access = $user ? user_can_access_projects($user) : false;
		$has_global_project_access = $user ? user_has_global_project_access($user, 'view') : false;

		$info = array_merge(array(
			'username' => $username,
			'is_logged_in' => !empty($username),
			'is_admin' => $is_admin,
			'can_access_site_admin' => $user ? (bool) $ci->acl_manager->has_site_admin_access($user) : false,
			'has_editor_access' => $has_editor_access,
			'has_global_project_access' => $has_global_project_access,
			'show_editor_access_notice' => $show_editor_access_notice && !empty($username) && !$is_admin && !$has_editor_access,
		), registry_acl_user_info_flags($user));

		return $info;
	}
}
