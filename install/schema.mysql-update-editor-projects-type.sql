-- Metadata Editor: widen columns that store schema UIDs
--
-- Schema UIDs live in metadata_schemas.uid (varchar(100), app limit 64 chars).
-- These columns must match so custom schema UIDs are not truncated on insert.
--
-- Preferred: Admin > Database Migrations
--   Run migration 20260803000001_editor_projects_type_width
--
-- Manual: mysql ... < install/schema.mysql-update-editor-projects-type.sql


ALTER TABLE `editor_projects`
  MODIFY COLUMN `type` varchar(100) DEFAULT NULL;

ALTER TABLE `editor_templates`
  MODIFY COLUMN `uid` varchar(100) DEFAULT NULL;

ALTER TABLE `editor_templates`
  MODIFY COLUMN `data_type` varchar(100) NOT NULL;

ALTER TABLE `editor_templates_default`
  MODIFY COLUMN `data_type` varchar(100) NOT NULL;
