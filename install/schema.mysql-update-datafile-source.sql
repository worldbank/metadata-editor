-- Metadata Editor: editor_data_files source-file columns
--
-- Adds columns to track the uploaded source file (Stata/SPSS/CSV), format version,
-- and attach/upload audit fields. Does not change file_physical_name for existing rows.
--
-- Preferred: Admin > Database Migrations
--   Run migration 20260708000001_datafile_source_columns
--
-- Manual: mysql ... < install/schema.mysql-update-datafile-source.sql
-- Ignore duplicate column/key errors if re-running statements that were already applied.


-- ---------------------------------------------------------------------------
-- editor_data_files: source file metadata
-- ---------------------------------------------------------------------------

ALTER TABLE `editor_data_files`
  ADD COLUMN `source_format` varchar(10) DEFAULT NULL
    COMMENT 'csv|dta|sav — format of the uploaded source file'
    AFTER `store_data`;

ALTER TABLE `editor_data_files`
  ADD COLUMN `source_format_version` varchar(50) DEFAULT NULL
    COMMENT 'Stata/SPSS file format version (e.g. 14, 118)'
    AFTER `source_format`;

ALTER TABLE `editor_data_files`
  ADD COLUMN `source_upload_filename` varchar(500) DEFAULT NULL
    COMMENT 'Original client filename at upload (before sanitize)'
    AFTER `source_format_version`;

ALTER TABLE `editor_data_files`
  ADD COLUMN `source_status` varchar(20) NOT NULL DEFAULT 'unknown'
    COMMENT 'present|missing|not_applicable|unknown'
    AFTER `source_upload_filename`;

ALTER TABLE `editor_data_files`
  ADD COLUMN `source_attached_at` int DEFAULT NULL
    COMMENT 'Unix time when source file was uploaded or attached'
    AFTER `source_status`;

ALTER TABLE `editor_data_files`
  ADD COLUMN `source_attached_by` int DEFAULT NULL
    COMMENT 'User id who uploaded or attached the source file'
    AFTER `source_attached_at`;

ALTER TABLE `editor_data_files`
  ADD KEY `idx_edf_source_status` (`source_status`);

ALTER TABLE `editor_data_files`
  ADD KEY `idx_edf_source_format` (`source_format`);


-- ---------------------------------------------------------------------------
-- Backfill from existing file_physical_name (does not rewrite physical names)
-- ---------------------------------------------------------------------------

-- Rows whose physical file is CSV: treat as CSV source; status unknown until
-- attach/import confirms native CSV vs converted-from-Stata/SPSS legacy.
UPDATE `editor_data_files`
SET
  `source_format` = 'csv',
  `source_status` = 'unknown'
WHERE `file_physical_name` IS NOT NULL
  AND `file_physical_name` != ''
  AND LOWER(`file_physical_name`) LIKE '%.csv'
  AND (`source_format` IS NULL OR `source_format` = '');

-- Rows whose physical file is still Stata/SPSS (uncommon after cleanup, but possible).
UPDATE `editor_data_files`
SET
  `source_format` = LOWER(SUBSTRING_INDEX(`file_physical_name`, '.', -1)),
  `source_status` = 'present'
WHERE `file_physical_name` IS NOT NULL
  AND `file_physical_name` != ''
  AND LOWER(`file_physical_name`) REGEXP '\\.(dta|sav)$'
  AND (`source_format` IS NULL OR `source_format` = '');
