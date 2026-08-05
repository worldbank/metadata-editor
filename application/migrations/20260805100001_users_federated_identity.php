<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * users — federated identity columns (OIDC iss/sub/oid, domain-equivalence linking).
 */
class Migration_Users_federated_identity extends MY_Migration {

	public function up()
	{
		if ($this->db->field_exists('identity_subject', 'users')) {
			log_message('info', 'Migration_Users_federated_identity: columns already exist');
			return;
		}

		$sql_file = $this->get_sql_file_path('schema.mysql-update-users-identity');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Migration_Users_federated_identity: applying schema...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Users_federated_identity: completed');
	}

	public function down()
	{
		if (!$this->db->field_exists('identity_subject', 'users')) {
			return;
		}

		$this->db->query('ALTER TABLE `users` DROP INDEX `uq_users_federated_identity`');
		$this->db->query('ALTER TABLE `users`
			DROP COLUMN `identity_subject_claim`,
			DROP COLUMN `identity_subject`,
			DROP COLUMN `identity_namespace`,
			DROP COLUMN `identity_issuer`');
	}
}
