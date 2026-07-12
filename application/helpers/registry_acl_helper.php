<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Registry (codelist / data_structure) ACL flags for web UI and user_info.
 */
if (!function_exists('registry_acl_user_info_flags')) {

	/**
	 * @param object|null $user Optional user object; defaults to current session user.
	 * @return array<string, bool>
	 */
	function registry_acl_user_info_flags($user = null)
	{
		$ci =& get_instance();
		if (!isset($ci->acl_manager)) {
			$ci->load->library('Acl_manager', null, 'acl_manager');
		}
		if ($user === null) {
			$user = $ci->acl_manager->current_user();
		}
		$flags = $ci->acl_manager->registry_user_info_flags($user);
		$flags['has_schema_permission'] = $ci->acl_manager->check_access('schema', 'view', $user);

		$ci->load->helper('user_access');
		return array_merge($flags, site_features_user_info());
	}
}
