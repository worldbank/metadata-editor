-- Notifications extras (Phase B — not applied yet).
-- Inbox table `user_notifications` lives in install/schema.mysql.sql
-- and application/migrations/20260905000001_user_notifications.php.
--
-- Apply after core schema (requires `users` table).
-- Engine/charset aligned with install/schema.mysql.sql

-- ---------------------------------------------------------------------------
-- Per-user preferences (e.g. mute all product notification emails)
-- One row per user; absence of row = defaults (email not muted).
-- Auth/account emails (reset password, OTP, activation) ignore this flag.
-- Phase B: add a migration before enabling daily digest.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
  `user_id` int NOT NULL,
  `mute_email_all` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = no notification emails; in-app still used',
  `created` int DEFAULT NULL COMMENT 'unix time',
  `changed` int DEFAULT NULL COMMENT 'unix time',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
