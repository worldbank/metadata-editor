<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Editorial lifecycle status for editor_projects (not catalog placement status).
 * NULL means unset; status is only stored when explicitly assigned.
 */

if (!function_exists('editor_project_statuses')) {
	function editor_project_statuses()
	{
		return array('draft', 'complete', 'archived');
	}
}

if (!function_exists('normalize_editor_project_status')) {
	/**
	 * @param mixed $value
	 * @return string|null
	 */
	function normalize_editor_project_status($value)
	{
		if ($value === null || $value === '') {
			return null;
		}
		$status = strtolower(trim((string) $value));
		if (!in_array($status, editor_project_statuses(), true)) {
			throw new Exception('Invalid project status. Use one of: draft, complete, archived.');
		}
		return $status;
	}
}
