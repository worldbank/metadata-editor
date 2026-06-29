<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Add Tag manager, Codelist manager, and Data structure manager roles with admin
 * permissions on their registry resources (parity with Collection / Schema managers).
 */
class Migration_Registry_manager_roles extends MY_Migration {

	private $manager_roles = array(
		array(
			'name' => 'Tag manager',
			'description' => 'Global role for managing tags',
			'resource' => 'tag',
		),
		array(
			'name' => 'Codelist manager',
			'description' => 'Global role for managing codelists',
			'resource' => 'codelist',
		),
		array(
			'name' => 'Data structure manager',
			'description' => 'Global role for managing data structures',
			'resource' => 'data_structure',
		),
	);

	public function up()
	{
		log_message('info', 'Migration_Registry_manager_roles::up()');

		if (!$this->db->table_exists('roles') || !$this->db->table_exists('role_permissions')) {
			log_message('info', 'roles/role_permissions missing; skipping registry manager roles');
			return;
		}

		foreach ($this->manager_roles as $def) {
			$this->ensure_manager_role($def);
		}

		log_message('info', 'Migration_Registry_manager_roles completed');
	}

	private function ensure_manager_role($def)
	{
		$role = $this->db
			->where('name', $def['name'])
			->limit(1)
			->get('roles')
			->row_array();

		if (empty($role['id'])) {
			$this->db->insert('roles', array(
				'name' => $def['name'],
				'description' => $def['description'],
				'weight' => 0,
				'is_admin' => 0,
				'is_locked' => 0,
			));
			$role_id = (int) $this->db->insert_id();
			log_message('info', "Created role {$def['name']} (id {$role_id})");
		} else {
			$role_id = (int) $role['id'];
		}

		$exists = $this->db
			->where('role_id', $role_id)
			->where('resource', $def['resource'])
			->limit(1)
			->get('role_permissions')
			->num_rows() > 0;

		if ($exists) {
			log_message('info', "{$def['resource']}:admin already set for role {$def['name']} (id {$role_id})");
			return;
		}

		$this->db->insert('role_permissions', array(
			'role_id' => $role_id,
			'resource' => $def['resource'],
			'permissions' => 'admin',
		));

		log_message('info', "Granted {$def['resource']}:admin to role {$def['name']} (id {$role_id})");
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
