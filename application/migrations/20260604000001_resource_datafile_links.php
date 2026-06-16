<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Microdata external resource ↔ data file links + generated resource metadata.
 *
 * Applies install/schema-resource-datafile-links.sql:
 * - editor_resources.source_type, editor_resources.bundle_type
 * - editor_resource_data_files
 *
 * Existing DBs: safe to re-run (duplicate column/table errors are skipped by MY_Migration).
 */
class Migration_Resource_datafile_links extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Resource_datafile_links::up()');

		if ($this->db->table_exists('editor_resource_data_files')
			&& $this->db->field_exists('source_type', 'editor_resources')
			&& $this->db->field_exists('bundle_type', 'editor_resources')) {
			log_message('info', 'editor_resource_data_files and editor_resources columns already exist; skipping');
			echo "⊘ SKIPPED: schema already applied\n";
			return;
		}

		$sql_file = $this->get_sql_file_path('schema-resource-datafile-links');
		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		$this->execute_sql_file($sql_file);

		log_message('info', 'Migration_Resource_datafile_links completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
