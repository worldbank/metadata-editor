-- Link optional associations between dat/micro external resources and editor data files.
--
-- Existing DBs: migration 20260703000001_upgrade_v1_3_0.php (Admin → Database Migration)
-- or run this file manually after backup.

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
