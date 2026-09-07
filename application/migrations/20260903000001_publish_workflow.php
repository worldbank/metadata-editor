<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Publish request ledger, event log, and catalog curators (Phase 1 workflow).
 */
class Migration_Publish_workflow extends MY_Migration {

	public function up()
	{
		if (!$this->db->table_exists('editor_catalogs')) {
			throw new Exception('Migration_Publish_workflow: editor_catalogs table missing');
		}

		if (!$this->db->table_exists('project_publications')) {
			$this->db->query("
				CREATE TABLE `project_publications` (
				  `id` int NOT NULL AUTO_INCREMENT,
				  `sid` int NOT NULL,
				  `catalog_id` int NOT NULL,
				  `status` varchar(30) DEFAULT NULL,
				  `request` varchar(30) DEFAULT NULL,
				  `remote_id` varchar(255) DEFAULT NULL,
				  `remote_url` varchar(500) DEFAULT NULL,
				  `source` varchar(30) DEFAULT NULL,
				  `options` json DEFAULT NULL,
				  `intake` json DEFAULT NULL,
				  `ready_at` int DEFAULT NULL,
				  `ready_by` int DEFAULT NULL,
				  `request_note` text,
				  `return_reason` text,
				  `requested_by` int DEFAULT NULL,
				  `requested_at` int DEFAULT NULL,
				  `updated_by` int DEFAULT NULL,
				  `updated_at` int DEFAULT NULL,
				  `created` int DEFAULT NULL,
				  `changed` int DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `unq_project_publications_sid_catalog` (`sid`,`catalog_id`),
				  KEY `idx_project_publications_catalog` (`catalog_id`),
				  KEY `idx_project_publications_request` (`request`),
				  KEY `idx_project_publications_ready_at` (`ready_at`),
				  CONSTRAINT `fk_project_publications_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `editor_catalogs` (`id`) ON DELETE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}

		if (!$this->db->table_exists('project_publication_events')) {
			$this->db->query("
				CREATE TABLE `project_publication_events` (
				  `id` int NOT NULL AUTO_INCREMENT,
				  `publication_id` int NOT NULL,
				  `event` varchar(50) NOT NULL,
				  `actor_user_id` int DEFAULT NULL,
				  `payload` json DEFAULT NULL,
				  `created` int DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `idx_project_publication_events_publication` (`publication_id`),
				  CONSTRAINT `fk_project_publication_events_publication` FOREIGN KEY (`publication_id`) REFERENCES `project_publications` (`id`) ON DELETE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}

		if (!$this->db->table_exists('catalog_curators')) {
			$this->db->query("
				CREATE TABLE `catalog_curators` (
				  `id` int NOT NULL AUTO_INCREMENT,
				  `catalog_id` int NOT NULL,
				  `user_id` int NOT NULL,
				  `created` int DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `unq_catalog_curators_catalog_user` (`catalog_id`,`user_id`),
				  KEY `idx_catalog_curators_user` (`user_id`),
				  CONSTRAINT `fk_catalog_curators_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `editor_catalogs` (`id`) ON DELETE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}

		log_message('info', 'Migration_Publish_workflow: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
