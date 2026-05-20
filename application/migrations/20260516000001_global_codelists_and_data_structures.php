<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Global codelists registry + data structure catalogue + project DSD binding.
 *
 * Applies install/schema-codelists.sql and install/schema-data-structures.sql when
 * those tables are missing. Drops abandoned legacy per-project tables if present
 * (indicator_dsd, local_codelists). No data migration from legacy schemas.
 */
class Migration_Global_codelists_and_data_structures extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Global_codelists_and_data_structures::up()');

		$this->install_codelists_if_missing();
		$this->install_data_structures_if_missing();
		$this->drop_legacy_tables();
		$this->backfill_indicator_id_values();

		log_message('info', 'Migration_Global_codelists_and_data_structures completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	private function install_codelists_if_missing()
	{
		if ($this->db->table_exists('codelists')) {
			log_message('info', 'codelists table already exists; skipping schema-codelists.sql');
			return;
		}

		$schema_file = $this->get_sql_file_path('schema-codelists');
		if (!file_exists($schema_file)) {
			throw new Exception('SQL file not found: ' . $schema_file);
		}
		$this->execute_sql_file($schema_file);
	}

	private function install_data_structures_if_missing()
	{
		if ($this->db->table_exists('data_structures')) {
			log_message('info', 'data_structures table already exists; skipping schema-data-structures.sql');
			return;
		}

		$schema_file = $this->get_sql_file_path('schema-data-structures');
		if (!file_exists($schema_file)) {
			throw new Exception('SQL file not found: ' . $schema_file);
		}
		$this->execute_sql_file($schema_file);
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

	private function backfill_indicator_id_values()
	{
		if (!$this->db->table_exists('editor_project_dsd')
			|| !$this->db->field_exists('indicator_id_value', 'editor_project_dsd')) {
			return;
		}

		$this->load->library('Data_structure_util');
		$rows = $this->db->get('editor_project_dsd')->result_array();
		foreach ($rows as $row) {
			if (!empty($row['indicator_id_value'])) {
				continue;
			}
			$sid = (int) $row['sid'];
			$default = $this->data_structure_util->resolve_default_indicator_id_value($sid);
			if ($default === '') {
				continue;
			}
			$this->db->where('sid', $sid)->update('editor_project_dsd', array(
				'indicator_id_value' => $default,
			));
			$this->data_structure_util->sync_indicator_id_value_to_metadata($sid, $default);
		}
	}
}
