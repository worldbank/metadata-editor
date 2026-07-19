<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Add indexes used by inheritable collection / project-via-collection ACL lookups.
 *
 * Applies install/schema.mysql-update-collection-acl-indexes.sql.
 * Duplicate index names are skipped safely by MY_Migration.
 */
class Migration_Collection_acl_indexes extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Collection_acl_indexes::up() called');

		$sql_file = $this->get_sql_file_path('schema.mysql-update-collection-acl-indexes');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Starting collection ACL indexes migration...');
		$this->execute_sql_file($sql_file);
		log_message('info', 'Migration_Collection_acl_indexes completed successfully');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
