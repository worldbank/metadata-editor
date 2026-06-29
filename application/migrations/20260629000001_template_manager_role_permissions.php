<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Grant template_manager:admin to the Template manager role when missing (fresh installs
 * before schema.mysql.sql included this seed row).
 */
class Migration_Template_manager_role_permissions extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Template_manager_role_permissions::up()');

		if (!$this->db->table_exists('role_permissions') || !$this->db->table_exists('roles')) {
			log_message('info', 'roles/role_permissions missing; skipping template manager ACL backfill');
			return;
		}

		$role = $this->db
			->where('name', 'Template manager')
			->limit(1)
			->get('roles')
			->row_array();

		if (empty($role['id'])) {
			log_message('info', 'Template manager role not found; skipping ACL backfill');
			return;
		}

		$role_id = (int) $role['id'];
		$exists = $this->db
			->where('role_id', $role_id)
			->where('resource', 'template_manager')
			->limit(1)
			->get('role_permissions')
			->num_rows() > 0;

		if ($exists) {
			log_message('info', "template_manager permissions already set for role_id {$role_id}");
			return;
		}

		$this->db->insert('role_permissions', array(
			'role_id' => $role_id,
			'resource' => 'template_manager',
			'permissions' => 'admin',
		));

		log_message('info', "Granted template_manager:admin to role_id {$role_id}");
		log_message('info', 'Migration_Template_manager_role_permissions completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
