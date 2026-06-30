-- Metadata Editor v1.2.x -> v1.3.0 database upgrade
--
-- Back up your database before running this script.
--
-- Preferred: Admin > Database Migrations
--   1. Run migration 20260703000001_upgrade_v1_3_0 (schema + roles)
--   2. Run migration 20260703000002_backfill_indicator_id_values (data backfill; no SQL in this file)
--
-- Manual: mysql ... < install/schema.mysql-update-1.3.sql
-- Ignore duplicate column/table/key errors if re-running statements that were already applied.


-- ---------------------------------------------------------------------------
-- project_issues
-- v1.2.0 fresh installs already have this table (without title); use ALTERs below.
-- Older upgrades: CREATE runs only when the table is missing.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `project_issues` (
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


-- ---------------------------------------------------------------------------
-- Global codelists registry
-- ---------------------------------------------------------------------------

CREATE TABLE codelists (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  pid BIGINT NULL COMMENT 'Family head row id (latest version for agency+name); set on create',
  idno VARCHAR(191) NOT NULL,
  agency VARCHAR(64) NOT NULL,
  name VARCHAR(64) NOT NULL COMMENT 'SDMX maintainable id (NADA codelists.name)',
  version VARCHAR(32) NOT NULL,
  version_seq INT NOT NULL COMMENT 'Monotonic sequence within (agency, name)',
  title VARCHAR(255) NOT NULL COMMENT 'Human-readable list title',
  description TEXT NULL,
  uri VARCHAR(500) NULL,
  status ENUM('draft','active','locked','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_at TIMESTAMP NULL,
  created_by INT NULL,
  changed_by INT NULL,
  UNIQUE KEY uq_codelists_idno (idno),
  UNIQUE KEY uq_codelist_identity (agency, name, version),
  UNIQUE KEY uq_codelists_family_seq (agency, name, version_seq),
  KEY idx_codelists_agency (agency),
  KEY idx_codelists_pid (pid),
  KEY idx_codelists_status (status),
  KEY idx_codelists_created (created_at),
  CONSTRAINT fk_codelists_pid FOREIGN KEY (pid) REFERENCES codelists (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE codelist_labels (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  codelist_id BIGINT NOT NULL COMMENT 'FK -> codelists.id',
  language VARCHAR(10) NOT NULL,
  label VARCHAR(500) NOT NULL,
  description TEXT NULL,
  FOREIGN KEY (codelist_id) REFERENCES codelists(id) ON DELETE CASCADE,
  UNIQUE KEY uq_codelist_language (codelist_id, language),
  KEY idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE codelist_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  codelist_id BIGINT NOT NULL COMMENT 'FK -> codelists.id',
  code VARCHAR(150) NOT NULL,
  parent_id BIGINT NULL,
  sort_order INT NULL,
  FOREIGN KEY (codelist_id) REFERENCES codelists(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES codelist_items(id) ON DELETE SET NULL,
  UNIQUE KEY uq_item_per_list (codelist_id, code),
  KEY idx_parent_id (parent_id),
  KEY idx_sort (codelist_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE codelist_items_labels (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  codelist_item_id BIGINT NOT NULL,
  language VARCHAR(10) NOT NULL,
  label VARCHAR(500) NOT NULL,
  description TEXT NULL,
  FOREIGN KEY (codelist_item_id) REFERENCES codelist_items(id) ON DELETE CASCADE,
  UNIQUE KEY uq_codelist_item_language (codelist_item_id, language),
  KEY idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Global data structure registry (DSD catalogue)
-- ---------------------------------------------------------------------------

CREATE TABLE `data_structures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` int DEFAULT NULL,
  `agency` varchar(64) NOT NULL DEFAULT 'NADA',
  `name` varchar(64) NOT NULL COMMENT 'SDMX maintainable id',
  `version` varchar(32) NOT NULL,
  `version_seq` int NOT NULL,
  `idno` varchar(191) DEFAULT NULL,
  `status` smallint NOT NULL DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `notes` text,
  `content_hash` char(64) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created` int DEFAULT NULL,
  `updated` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_data_structures_identity` (`agency`,`name`,`version`),
  UNIQUE KEY `unq_data_structures_family_seq` (`agency`,`name`,`version_seq`),
  UNIQUE KEY `unq_data_structures_idno` (`idno`),
  KEY `idx_data_structures_agency_name` (`agency`,`name`),
  KEY `idx_data_structures_pid` (`pid`),
  CONSTRAINT `fk_data_structures_pid` FOREIGN KEY (`pid`) REFERENCES `data_structures` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `data_structure_components` (
  `id` int NOT NULL AUTO_INCREMENT,
  `data_structure_id` int NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` text,
  `data_type` enum('string','integer','float','double','date','boolean') DEFAULT NULL,
  `column_type` enum('dimension','time_period','measure','attribute','indicator_id','indicator_name','annotation','geography','observation_value','periodicity') NOT NULL,
  `codelist_id` bigint DEFAULT NULL COMMENT 'FK -> codelists.id',
  `metadata` json DEFAULT NULL,
  `created` int DEFAULT NULL,
  `updated` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_dsc_structure_name` (`data_structure_id`,`name`),
  KEY `idx_dsc_structure_sort` (`data_structure_id`,`sort_order`),
  KEY `idx_dsc_codelist` (`codelist_id`),
  CONSTRAINT `fk_dsc_data_structure` FOREIGN KEY (`data_structure_id`) REFERENCES `data_structures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dsc_codelist` FOREIGN KEY (`codelist_id`) REFERENCES `codelists` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `editor_project_dsd` (
  `sid` int NOT NULL,
  `data_structure_id` int NOT NULL,
  `indicator_id_value` varchar(191) DEFAULT NULL COMMENT 'Value written to indicator_id column on CSV import',
  `implied_freq_code` varchar(16) DEFAULT NULL COMMENT 'SDMX FREQ when structure has no periodicity column',
  `has_published_data` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 after successful timeseries import',
  `published_row_count` int DEFAULT NULL,
  `data_imported_at` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `updated` int DEFAULT NULL,
  PRIMARY KEY (`sid`),
  KEY `idx_editor_project_dsd_structure` (`data_structure_id`),
  CONSTRAINT `fk_editor_project_dsd_project` FOREIGN KEY (`sid`) REFERENCES `editor_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_editor_project_dsd_structure` FOREIGN KEY (`data_structure_id`) REFERENCES `data_structures` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Drop abandoned legacy per-project DSD / codelist tables (if present)
-- ---------------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `local_codelist_items`;

DROP TABLE IF EXISTS `local_codelists`;

DROP TABLE IF EXISTS `indicator_dsd`;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------------------
-- Codelists.pid nullable (fix early schemas created with NOT NULL pid)
-- ---------------------------------------------------------------------------

ALTER TABLE `codelists`
  MODIFY COLUMN `pid` bigint NULL
  COMMENT 'Family head row id (latest version for agency+name); set on create';


-- ---------------------------------------------------------------------------
-- Microdata external resource <-> data file links
-- ---------------------------------------------------------------------------

ALTER TABLE `editor_resources`
  ADD COLUMN `source_type` varchar(20) DEFAULT 'manual' COMMENT 'manual|generated' AFTER `dcformat`,
  ADD COLUMN `bundle_type` varchar(10) DEFAULT NULL COMMENT 'single|zip' AFTER `source_type`;

CREATE TABLE IF NOT EXISTS `editor_resource_data_files` (
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
  `generated_at` int DEFAULT NULL,
  `created` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_resource_file` (`resource_id`, `file_id`),
  KEY `idx_erdf_sid_resource` (`sid`, `resource_id`),
  KEY `idx_erdf_sid_file` (`sid`, `file_id`),
  KEY `idx_erdf_resource` (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------------
-- Job queue: cancelled status (metadata assessment / job cancel API)
-- ---------------------------------------------------------------------------

ALTER TABLE `job_queue`
  MODIFY COLUMN `status` enum('pending','held','processing','completed','failed','cancelled') DEFAULT 'pending';


-- ---------------------------------------------------------------------------
-- Roles and permissions (registry managers, Project manager, Editor catalogue view)
-- ---------------------------------------------------------------------------

INSERT INTO `roles` (`name`, `description`, `weight`, `is_admin`, `is_locked`)
SELECT 'Tag manager', 'Global role for managing tags', 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'Tag manager');

INSERT INTO `roles` (`name`, `description`, `weight`, `is_admin`, `is_locked`)
SELECT 'Codelist manager', 'Global role for managing codelists', 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'Codelist manager');

INSERT INTO `roles` (`name`, `description`, `weight`, `is_admin`, `is_locked`)
SELECT 'Data structure manager', 'Global role for managing data structures', 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'Data structure manager');

INSERT INTO `roles` (`name`, `description`, `weight`, `is_admin`, `is_locked`)
SELECT 'Project manager', 'Global access to all projects', 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'Project manager');

UPDATE `roles`
SET `name` = 'Project manager', `description` = 'Global access to all projects'
WHERE `name` = 'project_manager';

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'codelist', 'view'
FROM `roles` r
INNER JOIN `role_permissions` rp ON rp.role_id = r.id AND rp.resource = 'editor'
WHERE NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'codelist'
);

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'data_structure', 'view'
FROM `roles` r
INNER JOIN `role_permissions` rp ON rp.role_id = r.id AND rp.resource = 'editor'
WHERE NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'data_structure'
);

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'template_manager', 'admin'
FROM `roles` r
WHERE r.name = 'Template manager'
AND NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'template_manager'
);

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'tag', 'admin'
FROM `roles` r
WHERE r.name = 'Tag manager'
AND NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'tag'
);

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'codelist', 'admin'
FROM `roles` r
WHERE r.name = 'Codelist manager'
AND NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'codelist'
);

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'data_structure', 'admin'
FROM `roles` r
WHERE r.name = 'Data structure manager'
AND NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'data_structure'
);

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'editor', 'view'
FROM `roles` r
WHERE r.name = 'Project manager'
AND NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'editor'
);

INSERT INTO `role_permissions` (`role_id`, `resource`, `permissions`)
SELECT r.id, 'project_manager', 'admin'
FROM `roles` r
WHERE r.name = 'Project manager'
AND NOT EXISTS (
  SELECT 1 FROM `role_permissions` x WHERE x.role_id = r.id AND x.resource = 'project_manager'
);


-- ---------------------------------------------------------------------------
-- Data backfill (no SQL)
-- ---------------------------------------------------------------------------
-- After this script, run Admin > Database Migrations > 20260703000002_backfill_indicator_id_values
-- to populate editor_project_dsd.indicator_id_value from project metadata (series idno).
-- There is no SQL equivalent; skip if you have no indicator projects bound to a DSD.
