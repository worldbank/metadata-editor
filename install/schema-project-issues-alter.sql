-- Upgrade existing project_issues table (e.g. v1.2.0 pre-provisioned schema).
-- One statement per column/index/constraint; duplicate errors are skipped by MY_Migration.
-- Applied when project_issues already exists: migration 20260703000001_upgrade_v1_3_0.php

ALTER TABLE `project_issues` ADD COLUMN `project_id` int NOT NULL AFTER `id`;

ALTER TABLE `project_issues` ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `project_id`;

ALTER TABLE `project_issues` ADD COLUMN `description` text NOT NULL AFTER `title`;

ALTER TABLE `project_issues` ADD COLUMN `category` varchar(100) DEFAULT NULL AFTER `description`;

ALTER TABLE `project_issues` ADD COLUMN `severity` enum('low','medium','high','critical') DEFAULT NULL AFTER `category`;

ALTER TABLE `project_issues` ADD COLUMN `status` enum('open','accepted','rejected','fixed','dismissed','false_positive') NOT NULL DEFAULT 'open' AFTER `severity`;

ALTER TABLE `project_issues` ADD COLUMN `field_path` varchar(500) DEFAULT NULL AFTER `status`;

ALTER TABLE `project_issues` ADD COLUMN `current_metadata` json DEFAULT NULL AFTER `field_path`;

ALTER TABLE `project_issues` ADD COLUMN `suggested_metadata` json DEFAULT NULL AFTER `current_metadata`;

ALTER TABLE `project_issues` ADD COLUMN `source` varchar(50) DEFAULT NULL AFTER `suggested_metadata`;

ALTER TABLE `project_issues` ADD COLUMN `created_by` int DEFAULT NULL AFTER `source`;

ALTER TABLE `project_issues` ADD COLUMN `created` int DEFAULT NULL AFTER `created_by`;

ALTER TABLE `project_issues` ADD COLUMN `assigned_to` int DEFAULT NULL AFTER `created`;

ALTER TABLE `project_issues` ADD COLUMN `resolved_by` int DEFAULT NULL AFTER `assigned_to`;

ALTER TABLE `project_issues` ADD COLUMN `resolved` int DEFAULT NULL AFTER `resolved_by`;

ALTER TABLE `project_issues` ADD COLUMN `applied` tinyint(1) NOT NULL DEFAULT 0 AFTER `resolved`;

ALTER TABLE `project_issues` ADD COLUMN `applied_by` int DEFAULT NULL AFTER `applied`;

ALTER TABLE `project_issues` ADD COLUMN `applied_on` int DEFAULT NULL AFTER `applied_by`;

ALTER TABLE `project_issues` ADD COLUMN `notes` text DEFAULT NULL AFTER `applied_on`;

ALTER TABLE `project_issues` ADD KEY `idx_project_id` (`project_id`);

ALTER TABLE `project_issues` ADD KEY `idx_status` (`status`);

ALTER TABLE `project_issues` ADD KEY `idx_category` (`category`);

ALTER TABLE `project_issues` ADD KEY `idx_field_path` (`field_path`);

ALTER TABLE `project_issues` ADD KEY `idx_created` (`created`);

ALTER TABLE `project_issues` ADD CONSTRAINT `fk_project_issues_project` FOREIGN KEY (`project_id`) REFERENCES `editor_projects` (`id`) ON DELETE CASCADE;
