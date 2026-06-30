-- Fix codelists.pid when created as NOT NULL (inserts without pid fail fk_codelists_pid).
-- Applied by migration 20260703000001_upgrade_v1_3_0.php when codelists table exists.

ALTER TABLE `codelists`
  MODIFY COLUMN `pid` bigint NULL
  COMMENT 'Family head row id (latest version for agency+name); set on create';
