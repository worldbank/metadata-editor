<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Replace unused editor_projects.published with editorial status (draft|complete|archived).
 * No data migration — published was never used. status is optional (NULL until set).
 */
class Migration_Editor_projects_status extends MY_Migration {

	public function up()
	{
		if (!$this->db->table_exists('editor_projects')) {
			throw new Exception('Migration_Editor_projects_status: editor_projects table missing');
		}

		if (!$this->db->field_exists('status', 'editor_projects')) {
			$this->db->query("
				ALTER TABLE `editor_projects`
				ADD COLUMN `status` varchar(20) DEFAULT NULL AFTER `varcount`
			");
		} else {
			$this->db->query("
				ALTER TABLE `editor_projects`
				MODIFY COLUMN `status` varchar(20) DEFAULT NULL
			");
		}

		if ($this->db->field_exists('published', 'editor_projects')) {
			$this->db->query("ALTER TABLE `editor_projects` DROP COLUMN `published`");
		}

		$this->add_index_if_missing('editor_projects', 'idx_editor_projects_status', 'status');

		log_message('info', 'Migration_Editor_projects_status: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	private function add_index_if_missing($table, $index_name, $column)
	{
		$found = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($index_name))->row_array();
		if ($found) {
			return;
		}
		$this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` (`{$column}`)");
	}
}
