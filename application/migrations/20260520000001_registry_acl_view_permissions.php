<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Grant catalogue view on global codelist / data_structure resources to roles that
 * already have editor access, so in-project DSD pickers keep working after ACL enforcement.
 */
class Migration_Registry_acl_view_permissions extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Registry_acl_view_permissions::up()');

		if (!$this->db->table_exists('role_permissions')) {
			log_message('info', 'role_permissions table missing; skipping registry ACL backfill');
			return;
		}

		$this->db->select('role_id');
		$this->db->distinct();
		$this->db->where('resource', 'editor');
		$rows = $this->db->get('role_permissions')->result_array();

		foreach ($rows as $row) {
			$role_id = (int) $row['role_id'];
			if ($role_id < 1) {
				continue;
			}
			foreach (array('codelist', 'data_structure') as $resource) {
				$exists = $this->db
					->where('role_id', $role_id)
					->where('resource', $resource)
					->limit(1)
					->get('role_permissions')
					->num_rows() > 0;
				if ($exists) {
					continue;
				}
				$this->db->insert('role_permissions', array(
					'role_id' => $role_id,
					'resource' => $resource,
					'permissions' => 'view',
				));
				log_message('info', "Granted {$resource}:view to role_id {$role_id}");
			}
		}

		log_message('info', 'Migration_Registry_acl_view_permissions completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
