-- Shared catalog identity + per-user credentials (Step 1 publish workflow).
-- Applied by application/migrations/20260901000001_shared_catalogs_credentials.php
-- Fresh installs use install/schema.mysql.sql.

CREATE TABLE IF NOT EXISTS `editor_catalog_credentials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `catalog_id` int NOT NULL,
  `user_id` int NOT NULL,
  `api_key` varchar(200) NOT NULL,
  `created` int DEFAULT NULL,
  `changed` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_editor_catalog_credentials_catalog_user` (`catalog_id`,`user_id`),
  KEY `idx_editor_catalog_credentials_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
