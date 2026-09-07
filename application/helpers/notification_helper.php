<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Classify an ACL write as granted / updated / revoked, or null when unchanged.
 *
 * @param string|null $previous_permission
 * @param string|null $new_permission
 * @return string|null granted|updated|revoked
 */
if (!function_exists('notification_access_event')) {
	function notification_access_event($previous_permission, $new_permission)
	{
		$previous = notification_normalize_permission($previous_permission);
		$new = notification_normalize_permission($new_permission);
		if ($new === '') {
			return $previous === '' ? null : 'revoked';
		}
		if ($previous === '') {
			return 'granted';
		}
		if ($previous === $new) {
			return null;
		}
		return 'updated';
	}
}

/**
 * @param mixed $permission
 * @return string
 */
if (!function_exists('notification_normalize_permission')) {
	function notification_normalize_permission($permission)
	{
		if ($permission === null) {
			return '';
		}
		return strtolower(trim((string) $permission));
	}
}

/**
 * Pull a single permission string from ACL lookup shapes.
 *
 * @param mixed $value
 * @return string|null
 */
if (!function_exists('notification_first_permission')) {
	function notification_first_permission($value)
	{
		if (is_string($value)) {
			$value = trim($value);
			return $value === '' ? null : strtolower($value);
		}
		if (!is_array($value) || $value === array()) {
			return null;
		}
		if (isset($value['permissions'])) {
			return notification_first_permission($value['permissions']);
		}
		if (isset($value[0])) {
			return notification_first_permission($value[0]);
		}
		return null;
	}
}

/**
 * Inbox family for a notification type.
 *
 * @param string $type
 * @return string publish|sharing|ownership|collection|template|other
 */
if (!function_exists('notification_family')) {
	function notification_family($type)
	{
		$type = trim((string) $type);
		if (strpos($type, 'publish.') === 0) {
			return 'publish';
		}
		if ($type === 'project.ownership_transferred') {
			return 'ownership';
		}
		if (strpos($type, 'collection.') === 0) {
			return 'collection';
		}
		if (strpos($type, 'template.') === 0 || strpos($type, 'admin_metadata.') === 0) {
			return 'template';
		}
		if (strpos($type, 'project.access_') === 0) {
			return 'sharing';
		}
		return 'other';
	}
}

/**
 * Type prefixes (or exact types) that belong to an inbox family.
 *
 * @param string $family
 * @return array{prefixes: string[], exact: string[]}
 */
if (!function_exists('notification_family_match')) {
	function notification_family_match($family)
	{
		switch (trim((string) $family)) {
			case 'publish':
				return array('prefixes' => array('publish.'), 'exact' => array());
			case 'sharing':
				return array('prefixes' => array('project.access_'), 'exact' => array());
			case 'ownership':
				return array('prefixes' => array(), 'exact' => array('project.ownership_transferred'));
			case 'collection':
				return array('prefixes' => array('collection.'), 'exact' => array());
			case 'template':
				return array('prefixes' => array('template.', 'admin_metadata.'), 'exact' => array());
			default:
				return array('prefixes' => array(), 'exact' => array());
		}
	}
}

/**
 * Site-configured inbox retention in days. Default 30; clamped to 1–365.
 *
 * @return int
 */
if (!function_exists('notification_retention_days')) {
	function notification_retention_days()
	{
		$ci =& get_instance();
		$value = $ci->config->item('notifications_retention_days');
		if ($value === false || $value === null || $value === '') {
			return 30;
		}
		$days = (int) $value;
		if ($days < 1) {
			return 1;
		}
		if ($days > 365) {
			return 365;
		}
		return $days;
	}
}

/**
 * Unix timestamp: rows older than this are outside the inbox window.
 *
 * @return int
 */
if (!function_exists('notification_retention_cutoff')) {
	function notification_retention_cutoff()
	{
		return time() - (notification_retention_days() * 86400);
	}
}
