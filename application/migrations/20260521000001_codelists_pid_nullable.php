<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Align codelists.pid with install/schema-codelists.sql (nullable until family head is set).
 *
 * Some databases were created with pid BIGINT NOT NULL, which makes inserts without pid
 * fail (implicit 0 violates fk_codelists_pid).
 */
class Migration_Codelists_pid_nullable extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Codelists_pid_nullable::up()');

		if (!$this->db->table_exists('codelists') || !$this->db->field_exists('pid', 'codelists')) {
			log_message('info', 'codelists.pid missing; skipping nullable migration');
			return;
		}

		$this->db->query(
			'ALTER TABLE `codelists` MODIFY COLUMN `pid` bigint NULL '
			. "COMMENT 'Family head row id (latest version for agency+name); set on create'"
		);

		log_message('info', 'Migration_Codelists_pid_nullable completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
