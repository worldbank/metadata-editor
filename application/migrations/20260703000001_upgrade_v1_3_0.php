<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Metadata Editor v1.3.0 database upgrade.
 *
 * Applies install/schema.mysql-update-1.3.sql (schema, roles, permissions).
 * Data backfill: run migration 20260703000002_backfill_indicator_id_values separately.
 */
class Migration_Upgrade_v1_3_0 extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Upgrade_v1_3_0::up() called');

		$sql_file = $this->get_sql_file_path('schema.mysql-update-1.3');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Starting v1.3 schema migration...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Upgrade_v1_3_0 completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
