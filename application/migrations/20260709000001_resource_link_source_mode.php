<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Resource_link_source_mode extends CI_Migration
{
	public function up()
	{
		// Table comes from the v1.3 upgrade; sites whose migration version was already
		// past it never got the table, so create it here (with the new columns).
		if (!$this->db->table_exists('editor_resource_data_files')) {
			$this->db->query("CREATE TABLE IF NOT EXISTS `editor_resource_data_files` (
				`id` int NOT NULL AUTO_INCREMENT,
				`sid` int NOT NULL,
				`resource_id` int NOT NULL,
				`file_id` varchar(100) NOT NULL,
				`export_format` varchar(20) DEFAULT NULL,
				`export_version` varchar(20) DEFAULT NULL,
				`zip_entry_name` varchar(255) DEFAULT NULL,
				`link_type` varchar(20) DEFAULT NULL COMMENT 'generated|manual|associated',
				`data_file_changed` int DEFAULT NULL,
				`source_csv_mtime` int DEFAULT NULL,
				`source_mode` varchar(20) DEFAULT NULL COMMENT 'original|generated — how the linked export was produced',
				`source_physical_mtime` int DEFAULT NULL COMMENT 'Unix mtime of source file when original was used',
				`generated_at` int DEFAULT NULL,
				`created` int DEFAULT NULL,
				`created_by` int DEFAULT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_resource_file` (`resource_id`, `file_id`),
				KEY `idx_erdf_sid_resource` (`sid`, `resource_id`),
				KEY `idx_erdf_sid_file` (`sid`, `file_id`),
				KEY `idx_erdf_resource` (`resource_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			return;
		}

		if (!$this->db->field_exists('source_mode', 'editor_resource_data_files')) {
			$this->db->query("ALTER TABLE `editor_resource_data_files`
				ADD COLUMN `source_mode` varchar(20) DEFAULT NULL
				COMMENT 'original|generated — how the linked export was produced'
				AFTER `source_csv_mtime`");
		}
		if (!$this->db->field_exists('source_physical_mtime', 'editor_resource_data_files')) {
			$this->db->query("ALTER TABLE `editor_resource_data_files`
				ADD COLUMN `source_physical_mtime` int DEFAULT NULL
				COMMENT 'Unix mtime of source file when original was used'
				AFTER `source_mode`");
		}
	}

	public function down()
	{
		if (!$this->db->table_exists('editor_resource_data_files')) {
			return;
		}
		if ($this->db->field_exists('source_physical_mtime', 'editor_resource_data_files')) {
			$this->dbforge->drop_column('editor_resource_data_files', 'source_physical_mtime');
		}
		if ($this->db->field_exists('source_mode', 'editor_resource_data_files')) {
			$this->dbforge->drop_column('editor_resource_data_files', 'source_mode');
		}
	}
}
