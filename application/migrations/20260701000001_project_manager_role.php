<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Add Project manager role with global project access (editor:view + project_manager:admin).
 * Renames legacy optional install role "project_manager" to "Project manager" when present.
 */
class Migration_Project_manager_role extends MY_Migration {

	private $role_name = 'Project manager';

	private $role_description = 'Global access to all projects';

	public function up()
	{
		log_message('info', 'Migration_Project_manager_role::up()');

		if (!$this->db->table_exists('roles') || !$this->db->table_exists('role_permissions')) {
			log_message('info', 'roles/role_permissions missing; skipping Project manager role');
			return;
		}

		$role_id = $this->ensure_project_manager_role();
		$this->ensure_role_permission($role_id, 'editor', 'view');
		$this->ensure_role_permission($role_id, 'project_manager', 'admin');

		log_message('info', 'Migration_Project_manager_role completed');
	}

	private function ensure_project_manager_role()
	{
		$role = $this->db
			->where('name', $this->role_name)
			->limit(1)
			->get('roles')
			->row_array();

		if (!empty($role['id'])) {
			return (int) $role['id'];
		}

		$legacy = $this->db
			->where('name', 'project_manager')
			->limit(1)
			->get('roles')
			->row_array();

		if (!empty($legacy['id'])) {
			$this->db->where('id', (int) $legacy['id']);
			$this->db->update('roles', array(
				'name' => $this->role_name,
				'description' => $this->role_description,
			));
			log_message('info', 'Renamed legacy project_manager role to Project manager (id ' . $legacy['id'] . ')');
			return (int) $legacy['id'];
		}

		$this->db->insert('roles', array(
			'name' => $this->role_name,
			'description' => $this->role_description,
			'weight' => 0,
			'is_admin' => 0,
			'is_locked' => 0,
		));
		$role_id = (int) $this->db->insert_id();
		log_message('info', "Created role {$this->role_name} (id {$role_id})");

		return $role_id;
	}

	private function ensure_role_permission($role_id, $resource, $permission)
	{
		$exists = $this->db
			->where('role_id', (int) $role_id)
			->where('resource', $resource)
			->limit(1)
			->get('role_permissions')
			->num_rows() > 0;

		if ($exists) {
			log_message('info', "{$resource}:{$permission} already set for Project manager (id {$role_id})");
			return;
		}

		$this->db->insert('role_permissions', array(
			'role_id' => (int) $role_id,
			'resource' => $resource,
			'permissions' => $permission,
		));

		log_message('info', "Granted {$resource}:{$permission} to Project manager (id {$role_id})");
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
