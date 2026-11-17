<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Per-project settings of the server assistant (application/models/Assistant_settings_model.php).
 *
 * One switch today: keep_local, "never send this project to a hosted model", set by an administrator
 * from the assistant panel. Off (0) when a project has no row. Fresh installs create the table in
 * install/schema.mysql.sql.
 */
class Migration_Assistant_project_settings extends MY_Migration {

	public function up()
	{
		if (!$this->db->table_exists('assistant_project_settings')) {
			$this->db->query("
				CREATE TABLE `assistant_project_settings` (
				  `sid` int NOT NULL,
				  `keep_local` tinyint(1) NOT NULL DEFAULT 0,
				  `updated_by` int DEFAULT NULL,
				  `updated_on` int DEFAULT NULL,
				  PRIMARY KEY (`sid`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			log_message('info', 'Migration_Assistant_project_settings: created assistant_project_settings');
		}
	}

	public function down()
	{
		if ($this->db->table_exists('assistant_project_settings')) {
			$this->db->query('DROP TABLE `assistant_project_settings`');
		}
	}
}
