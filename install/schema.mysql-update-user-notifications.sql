-- Global in-app inbox (one row per recipient per event).
-- Applied by application/migrations/20260905000001_user_notifications.php
-- Fresh installs use install/schema.mysql.sql.

CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'recipient',
  `type` varchar(64) NOT NULL COMMENT 'e.g. publish.project_ready, project.access_granted',
  `payload` json DEFAULT NULL COMMENT 'snapshots + deep-link data (sid, titles, href fields)',
  `actor_user_id` int DEFAULT NULL COMMENT 'who triggered the event',
  `read_at` int DEFAULT NULL COMMENT 'unix time; NULL = unread',
  `email_sent_at` int DEFAULT NULL COMMENT 'unix time when digest included this row; Phase B',
  `created` int NOT NULL COMMENT 'unix time',
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created`),
  KEY `idx_user_unread` (`user_id`, `read_at`),
  KEY `idx_user_type` (`user_id`, `type`),
  KEY `idx_actor` (`actor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
