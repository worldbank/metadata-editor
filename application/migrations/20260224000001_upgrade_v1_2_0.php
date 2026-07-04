<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Metadata Editor v1.2.0 database upgrade.
 *
 * Applies install/schema.mysql-update-1.2.sql (schema, roles, permissions).
 * Safe to re-run after unmarking in Admin > Database Migrations.
 */
class Migration_Upgrade_v1_2_0 extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Upgrade_v1_2_0::up() called');

		$this->drop_legacy_tags_table_if_needed();

		$sql_file = $this->get_sql_file_path('schema.mysql-update-1.2');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Starting v1.2 schema migration...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Upgrade_v1_2_0 completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	/**
	 * Drop pre-v1.2 legacy `tags` table only before project_tags exists.
	 * Avoids destroying the new tags table when this migration is re-run.
	 */
	protected function drop_legacy_tags_table_if_needed()
	{
		if ($this->db->table_exists('project_tags')) {
			log_message('info', 'project_tags exists; skipping legacy tags drop');
			return;
		}

		if (!$this->db->table_exists('tags')) {
			return;
		}

		log_message('info', 'Dropping legacy tags table before v1.2 tag schema');
		echo "Dropping legacy `tags` table (project_tags not present yet)...\n";
		$this->db->query('DROP TABLE IF EXISTS `tags`');
	}
}
