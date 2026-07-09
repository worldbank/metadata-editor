<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Resource_link_source_mode extends CI_Migration
{
	public function up()
	{
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
		if ($this->db->field_exists('source_physical_mtime', 'editor_resource_data_files')) {
			$this->dbforge->drop_column('editor_resource_data_files', 'source_physical_mtime');
		}
		if ($this->db->field_exists('source_mode', 'editor_resource_data_files')) {
			$this->dbforge->drop_column('editor_resource_data_files', 'source_mode');
		}
	}
}
