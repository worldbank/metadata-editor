<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Ensure collection project-ACL and collection-ACL tables exist.
 *
 * Older installs used editor_collection_access. Fresh installs create
 * editor_collection_project_acl in schema.mysql.sql. There was no prior
 * migration for the rename, so production can hit MySQL 1146 on tree_get.
 */
class Migration_Collection_project_acl_table extends MY_Migration {

	public function up()
	{
		$this->ensure_project_acl_table();
		$this->ensure_collection_acl_table();

		log_message('info', 'Migration_Collection_project_acl_table: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	private function ensure_project_acl_table()
	{
		$has_new = $this->db->table_exists('editor_collection_project_acl');
		$has_old = $this->db->table_exists('editor_collection_access');

		if (!$has_new && $has_old) {
			$this->db->query('ALTER TABLE `editor_collection_access` RENAME TO `editor_collection_project_acl`');
			log_message('info', 'Migration_Collection_project_acl_table: renamed editor_collection_access');
		} elseif (!$has_new) {
			$this->db->query("
				CREATE TABLE `editor_collection_project_acl` (
				  `id` int NOT NULL AUTO_INCREMENT,
				  `collection_id` int NOT NULL,
				  `user_id` int NOT NULL,
				  `permissions` varchar(100) DEFAULT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			log_message('info', 'Migration_Collection_project_acl_table: created editor_collection_project_acl');
		}

		$this->add_index_if_missing(
			'editor_collection_project_acl',
			'idx_ecpa_user_collection',
			'user_id, collection_id'
		);
	}

	private function ensure_collection_acl_table()
	{
		if (!$this->db->table_exists('editor_collection_acl')) {
			$this->db->query("
				CREATE TABLE `editor_collection_acl` (
				  `id` int NOT NULL AUTO_INCREMENT,
				  `collection_id` int NOT NULL,
				  `permissions` varchar(100) DEFAULT NULL,
				  `user_id` int NOT NULL,
				  `created` int DEFAULT NULL,
				  `changed` int DEFAULT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			log_message('info', 'Migration_Collection_project_acl_table: created editor_collection_acl');
		}

		$this->add_index_if_missing(
			'editor_collection_acl',
			'idx_eca_user_collection',
			'user_id, collection_id'
		);
	}

	private function add_index_if_missing($table, $index_name, $columns)
	{
		$found = $this->db->query(
			'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ' . $this->db->escape($index_name)
		)->row_array();
		if ($found) {
			return;
		}
		$this->db->query('ALTER TABLE `' . $table . '` ADD INDEX `' . $index_name . '` (' . $columns . ')');
	}
}
