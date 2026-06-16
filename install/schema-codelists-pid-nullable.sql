-- Fix codelists.pid when created as NOT NULL (inserts without pid fail fk_codelists_pid).

ALTER TABLE `codelists`
  MODIFY COLUMN `pid` bigint NULL
  COMMENT 'Family head row id (latest version for agency+name); set on create';
