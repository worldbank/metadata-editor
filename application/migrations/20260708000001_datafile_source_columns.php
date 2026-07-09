<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Add source-file columns to editor_data_files and backfill from file_physical_name.
 *
 * Applies install/schema.mysql-update-datafile-source.sql.
 */
class Migration_Datafile_source_columns extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Datafile_source_columns::up() called');

		$sql_file = $this->get_sql_file_path('schema.mysql-update-datafile-source');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Starting editor_data_files source columns migration...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Datafile_source_columns completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
