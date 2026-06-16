<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_project_issues_table extends CI_Migration {

    public function up()
    {
        if (!$this->db->table_exists('project_issues')) {
            $this->db->query("
                CREATE TABLE `project_issues` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `project_id` int NOT NULL,
                  `title` varchar(255) DEFAULT NULL,
                  `description` text NOT NULL,
                  `category` varchar(100) DEFAULT NULL,  
                  `severity` enum('low','medium','high','critical') DEFAULT NULL,
                  `status` enum('open','accepted','rejected','fixed','dismissed','false_positive') NOT NULL DEFAULT 'open',
                  `field_path` varchar(500) DEFAULT NULL,
                  `current_metadata` json DEFAULT NULL,
                  `suggested_metadata` json DEFAULT NULL,
                  `source` varchar(50) DEFAULT NULL,
                  `created_by` int DEFAULT NULL,
                  `created` int DEFAULT NULL,
                  `assigned_to` int DEFAULT NULL,
                  `resolved_by` int DEFAULT NULL,
                  `resolved` int DEFAULT NULL,
                  `applied` tinyint(1) NOT NULL DEFAULT 0,
                  `applied_by` int DEFAULT NULL,
                  `applied_on` int DEFAULT NULL,
                  `notes` text DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_project_id` (`project_id`),
                  KEY `idx_status` (`status`),
                  KEY `idx_category` (`category`),
                  KEY `idx_field_path` (`field_path`),
                  KEY `idx_created` (`created`),
                  CONSTRAINT `fk_project_issues_project` FOREIGN KEY (`project_id`) REFERENCES `editor_projects` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    public function down()
    {
        throw new Exception("Rollback not supported - this is a one-way migration. Restore from database backup if needed.");
    }
}
