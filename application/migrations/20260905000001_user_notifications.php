<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Global in-app inbox (one row per recipient per event).
 * Read/unread is `read_at` on this row — not a separate status table.
 * `email_sent_at` is reserved for Phase B daily digest.
 */
class Migration_User_notifications extends MY_Migration {

	public function up()
	{
		if ($this->db->table_exists('user_notifications')) {
			log_message('info', 'Migration_User_notifications: user_notifications already exists');
			return;
		}

		$this->db->query("
			CREATE TABLE `user_notifications` (
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
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		log_message('info', 'Migration_User_notifications: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
