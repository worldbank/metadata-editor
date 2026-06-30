-- Global data structure registry (NADA-aligned DSD catalogue)
-- Apply after schema-codelists.sql. Reset: schema-data-structures-drop.sql then this file.
-- SDMX time: components use column_type time_period / periodicity only (no time_period_format).
-- Constant series FREQ: editor_project_dsd.implied_freq_code (when no periodicity column).
-- Existing DBs: migration 20260703000001_upgrade_v1_3_0.php

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
