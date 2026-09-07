<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Official vs private catalogs on editor_catalogs (is_official).
 * Migrates legacy visibility column when present; relaxes url_normalized uniqueness.
 */
class Migration_Catalog_is_official extends MY_Migration {

	public function up()
	{
		if (!$this->db->table_exists('editor_catalogs')) {
			throw new Exception('Migration_Catalog_is_official: editor_catalogs table missing');
		}

		$has_visibility = $this->db->field_exists('visibility', 'editor_catalogs');
		$has_official = $this->db->field_exists('is_official', 'editor_catalogs');

		if (!$has_official && !$has_visibility) {
			$this->db->query("ALTER TABLE `editor_catalogs` ADD COLUMN `is_official` tinyint(1) NOT NULL DEFAULT 0");
		} elseif (!$has_official) {
			$this->db->query("ALTER TABLE `editor_catalogs` ADD COLUMN `is_official` tinyint(1) NOT NULL DEFAULT 0");
		}

		if ($has_visibility) {
			$this->db->query("UPDATE `editor_catalogs` SET `is_official` = 1 WHERE `visibility` IN ('official', 'public', 'managed')");
			$this->drop_index_if_exists('editor_catalogs', 'idx_editor_catalogs_visibility');
			$this->db->query("ALTER TABLE `editor_catalogs` DROP COLUMN `visibility`");
		}

		$this->drop_index_if_exists('editor_catalogs', 'unq_editor_catalogs_url_normalized');
		$this->add_index_if_missing('editor_catalogs', 'idx_editor_catalogs_is_official', 'is_official');
		$this->add_index_if_missing('editor_catalogs', 'idx_editor_catalogs_url_normalized', 'url_normalized');

		log_message('info', 'Migration_Catalog_is_official: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	private function drop_index_if_exists($table, $index_name)
	{
		$found = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($index_name))->row_array();
		if (!$found) {
			return;
		}
		$this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$index_name}`");
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
