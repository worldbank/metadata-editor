<?php

/**
 * Shared helpers for collection ACL privilege ranking and inheritance.
 *
 * Effective permission = strongest among self + ancestors (no override / deny).
 */

if (!function_exists('collection_acl_permission_rank')) {
	/**
	 * Numeric rank for comparing view < edit < admin.
	 *
	 * @param string|null $permission
	 * @return int
	 */
	function collection_acl_permission_rank($permission)
	{
		switch ($permission) {
			case 'admin':
				return 3;
			case 'edit':
				return 2;
			case 'view':
				return 1;
			default:
				return 0;
		}
	}
}

if (!function_exists('collection_acl_max_permission')) {
	/**
	 * Return the strongest privilege from a list of permission strings.
	 *
	 * @param array $permissions
	 * @return string|null view|edit|admin or null if empty/invalid
	 */
	function collection_acl_max_permission(array $permissions)
	{
		$max = null;
		$max_rank = 0;
		foreach ($permissions as $permission) {
			$rank = collection_acl_permission_rank($permission);
			if ($rank > $max_rank) {
				$max_rank = $rank;
				$max = $permission;
			}
		}
		return $max;
	}
}

if (!function_exists('collection_acl_sql_project_ids_for_user')) {
	/**
	 * SQL selecting project SIDs accessible via collection project ACL,
	 * including grants inherited from ancestor collections.
	 *
	 * @param int $user_id
	 * @return string
	 */
	function collection_acl_sql_project_ids_for_user($user_id)
	{
		$user_id = (int) $user_id;
		return 'SELECT DISTINCT ecp.sid
			FROM editor_collection_projects ecp
			INNER JOIN editor_collections_tree t ON t.child_id = ecp.collection_id
			INNER JOIN editor_collection_project_acl a ON a.collection_id = t.parent_id
			WHERE a.user_id = ' . $user_id;
	}
}
