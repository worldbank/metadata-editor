<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Widen columns that store schema UIDs (projects, templates, template defaults).
 *
 * Applies install/schema.mysql-update-editor-projects-type.sql.
 */
class Migration_Editor_projects_type_width extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Editor_projects_type_width::up() called');

		$sql_file = $this->get_sql_file_path('schema.mysql-update-editor-projects-type');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Starting editor_projects.type column width migration...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Editor_projects_type_width completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
