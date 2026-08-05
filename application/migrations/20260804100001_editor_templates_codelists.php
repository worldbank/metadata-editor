<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * editor_templates_codelists — template field bindings to global codelists.
 */
class Migration_Editor_templates_codelists extends MY_Migration {

	public function up()
	{
		if ($this->db->table_exists('editor_templates_codelists')) {
			log_message('info', 'Migration_Editor_templates_codelists: table already exists');
			return;
		}

		if (!$this->db->table_exists('editor_templates')) {
			throw new Exception('Migration_Editor_templates_codelists: editor_templates table missing');
		}

		if (!$this->db->table_exists('codelists')) {
			throw new Exception('Migration_Editor_templates_codelists: codelists table missing');
		}

		$sql_file = $this->get_sql_file_path('schema.mysql-update-editor-templates-codelists');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Creating editor_templates_codelists...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Editor_templates_codelists completed successfully');
	}

	public function down()
	{
		if ($this->db->table_exists('editor_templates_codelists')) {
			$this->db->query('DROP TABLE IF EXISTS `editor_templates_codelists`');
		}
	}
}
