<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Metadata Editor v1.3.0 database upgrade (consolidates post-v1.2.0 migrations).
 *
 * SQL fragments (install/*.sql):
 *   - schema-project-issues.sql (create) / schema-project-issues-alter.sql (upgrade)
 *   - schema-codelists.sql + schema-data-structures.sql
 *   - schema-codelists-pid-nullable.sql
 *   - schema-resource-datafile-links.sql
 *   - schema-job-queue-cancelled.sql
 *
 * Role/ACL backfills run in PHP (idempotent).
 */
class Migration_Upgrade_v1_3_0 extends MY_Migration {

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
		log_message('info', 'Migration_Upgrade_v1_3_0::up()');

		$this->apply_project_issues();
		$this->execute_sql_fragment_if_table_missing('codelists', 'schema-codelists');
		$this->execute_sql_fragment_if_table_missing('data_structures', 'schema-data-structures');
		$this->drop_legacy_tables();
		$this->execute_sql_fragment_if_table_exists('codelists', 'schema-codelists-pid-nullable');
		$this->apply_resource_datafile_links();
		$this->apply_job_queue_cancelled_status();
		$this->backfill_registry_acl_view_permissions();
		$this->ensure_template_manager_role_permissions();
		$this->ensure_registry_manager_roles();
		$this->ensure_project_manager_role();

		log_message('info', 'Migration_Upgrade_v1_3_0 completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	private function apply_project_issues()
	{
		if (!$this->db->table_exists('project_issues')) {
			log_message('info', 'project_issues missing; creating from schema-project-issues.sql');
			$this->execute_sql_fragment('schema-project-issues');
			return;
		}

		log_message('info', 'project_issues exists; applying schema-project-issues-alter.sql');
		echo "Applying project_issues column/index upgrades (existing table)...\n";
		$this->execute_sql_fragment('schema-project-issues-alter');
	}

	private function execute_sql_fragment_if_table_missing($table, $filename)
	{
		if ($this->db->table_exists($table)) {
			log_message('info', "{$table} already exists; skipping {$filename}.sql");
			echo "⊘ SKIPPED: {$table} already exists ({$filename}.sql)\n";
			return;
		}

		$this->execute_sql_fragment($filename);
	}

	private function execute_sql_fragment_if_table_exists($table, $filename)
	{
		if (!$this->db->table_exists($table)) {
			log_message('info', "{$table} missing; skipping {$filename}.sql");
			echo "⊘ SKIPPED: {$table} does not exist ({$filename}.sql)\n";
			return;
		}

		$this->execute_sql_fragment($filename);
	}

	private function execute_sql_fragment($filename)
	{
		$sql_file = $this->get_sql_file_path($filename);
		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}
		$this->execute_sql_file($sql_file);
	}

	private function drop_legacy_tables()
	{
		$this->db->query('SET FOREIGN_KEY_CHECKS = 0');

		foreach (array('local_codelist_items', 'local_codelists', 'indicator_dsd') as $table) {
			if (!$this->db->table_exists($table)) {
				continue;
			}
			$this->load->dbforge();
			$this->dbforge->drop_table($table, true);
			log_message('info', 'Dropped legacy table: ' . $table);
		}

		$this->db->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	private function apply_resource_datafile_links()
	{
		if ($this->db->table_exists('editor_resource_data_files')
			&& $this->db->field_exists('source_type', 'editor_resources')
			&& $this->db->field_exists('bundle_type', 'editor_resources')) {
			log_message('info', 'editor_resource_data_files and editor_resources columns already exist; skipping');
			echo "⊘ SKIPPED: resource/datafile links already applied\n";
			return;
		}

		$this->execute_sql_fragment('schema-resource-datafile-links');
	}

	private function apply_job_queue_cancelled_status()
	{
		if (!$this->db->table_exists('job_queue')) {
			log_message('info', 'job_queue table missing; skipping cancelled status migration');
			echo "⊘ SKIPPED: job_queue table does not exist\n";
			return;
		}

		if ($this->job_queue_has_cancelled_status()) {
			log_message('info', 'job_queue.status already includes cancelled; skipping');
			echo "⊘ SKIPPED: cancelled status already present on job_queue.status\n";
			return;
		}

		$this->execute_sql_fragment('schema-job-queue-cancelled');
	}

	private function job_queue_has_cancelled_status()
	{
		$query = $this->db->query("SHOW COLUMNS FROM `job_queue` LIKE 'status'");
		if (!$query || $query->num_rows() === 0) {
			return false;
		}

		$row = $query->row_array();
		if (empty($row['Type'])) {
			return false;
		}

		return stripos($row['Type'], 'cancelled') !== false;
	}

	private function backfill_registry_acl_view_permissions()
	{
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
	}

	private function ensure_template_manager_role_permissions()
	{
		if (!$this->db->table_exists('role_permissions') || !$this->db->table_exists('roles')) {
			return;
		}

		$role = $this->db
			->where('name', 'Template manager')
			->limit(1)
			->get('roles')
			->row_array();

		if (empty($role['id'])) {
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
			return;
		}

		$this->db->insert('role_permissions', array(
			'role_id' => $role_id,
			'resource' => 'template_manager',
			'permissions' => 'admin',
		));
		log_message('info', "Granted template_manager:admin to role_id {$role_id}");
	}

	private function ensure_registry_manager_roles()
	{
		if (!$this->db->table_exists('roles') || !$this->db->table_exists('role_permissions')) {
			return;
		}

		foreach ($this->manager_roles as $def) {
			$this->ensure_manager_role($def);
		}
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
			return;
		}

		$this->db->insert('role_permissions', array(
			'role_id' => $role_id,
			'resource' => $def['resource'],
			'permissions' => 'admin',
		));
		log_message('info', "Granted {$def['resource']}:admin to role {$def['name']} (id {$role_id})");
	}

	private function ensure_project_manager_role()
	{
		if (!$this->db->table_exists('roles') || !$this->db->table_exists('role_permissions')) {
			return;
		}

		$role_id = $this->resolve_project_manager_role_id();
		$this->ensure_role_permission($role_id, 'editor', 'view');
		$this->ensure_role_permission($role_id, 'project_manager', 'admin');
	}

	private function resolve_project_manager_role_id()
	{
		$role = $this->db
			->where('name', 'Project manager')
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
				'name' => 'Project manager',
				'description' => 'Global access to all projects',
			));
			log_message('info', 'Renamed legacy project_manager role to Project manager (id ' . $legacy['id'] . ')');
			return (int) $legacy['id'];
		}

		$this->db->insert('roles', array(
			'name' => 'Project manager',
			'description' => 'Global access to all projects',
			'weight' => 0,
			'is_admin' => 0,
			'is_locked' => 0,
		));
		$role_id = (int) $this->db->insert_id();
		log_message('info', "Created role Project manager (id {$role_id})");

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
			return;
		}

		$this->db->insert('role_permissions', array(
			'role_id' => (int) $role_id,
			'resource' => $resource,
			'permissions' => $permission,
		));
		log_message('info', "Granted {$resource}:{$permission} to role_id {$role_id}");
	}
}
