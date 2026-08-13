<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * editor_variable_groups — one tree per project (UNIQUE sid).
 */
class Migration_Variable_groups_sid_unique extends MY_Migration {

	public function up()
	{
		if (!$this->db->table_exists('editor_variable_groups')) {
			throw new Exception('Migration_Variable_groups_sid_unique: editor_variable_groups table missing');
		}

		$sql_file = $this->get_sql_file_path('schema.mysql-update-variable-groups-sid-unique');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Migration_Variable_groups_sid_unique: applying schema...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Variable_groups_sid_unique: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
