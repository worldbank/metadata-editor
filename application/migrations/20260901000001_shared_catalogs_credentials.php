<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Split editor_catalogs into shared identity + per-user API keys.
 */
class Migration_Shared_catalogs_credentials extends MY_Migration {

	public function up()
	{
		$this->load->helper('catalog');

		if (!$this->db->table_exists('editor_catalogs')) {
			throw new Exception('Migration_Shared_catalogs_credentials: editor_catalogs table missing');
		}

		$already_split = $this->db->table_exists('editor_catalog_credentials')
			&& $this->db->field_exists('uid', 'editor_catalogs')
			&& !$this->db->field_exists('api_key', 'editor_catalogs');

		if ($already_split) {
			log_message('info', 'Migration_Shared_catalogs_credentials: already applied');
			echo "⊘ SKIPPED: shared catalogs already in place\n";
			return;
		}

		$this->ensure_credentials_table();
		$this->ensure_catalog_identity_columns();

		if ($this->db->field_exists('api_key', 'editor_catalogs')) {
			$this->migrate_legacy_rows();
			$this->drop_legacy_catalog_columns();
		}

		$this->add_catalog_identity_indexes();
		$this->add_credentials_foreign_key();

		log_message('info', 'Migration_Shared_catalogs_credentials: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	private function ensure_credentials_table()
	{
		if ($this->db->table_exists('editor_catalog_credentials')) {
			return;
		}

		$sql_file = $this->get_sql_file_path('schema.mysql-update-shared-catalogs');
		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		$this->execute_sql_file($sql_file);
	}

	private function ensure_catalog_identity_columns()
	{
		$columns = array(
			'uid' => "ALTER TABLE `editor_catalogs` ADD COLUMN `uid` varchar(100) NULL",
			'type' => "ALTER TABLE `editor_catalogs` ADD COLUMN `type` varchar(50) NOT NULL DEFAULT 'nada'",
			'url_normalized' => "ALTER TABLE `editor_catalogs` ADD COLUMN `url_normalized` varchar(500) NULL",
			'created' => "ALTER TABLE `editor_catalogs` ADD COLUMN `created` int NULL",
			'changed' => "ALTER TABLE `editor_catalogs` ADD COLUMN `changed` int NULL",
			'created_by' => "ALTER TABLE `editor_catalogs` ADD COLUMN `created_by` int NULL",
		);

		foreach ($columns as $field => $sql) {
			if (!$this->db->field_exists($field, 'editor_catalogs')) {
				$this->db->query($sql);
			}
		}
	}

	private function migrate_legacy_rows()
	{
		$rows = $this->db->get('editor_catalogs')->result_array();
		if (empty($rows)) {
			return;
		}

		$groups = array();
		foreach ($rows as $row) {
			$groups[$this->legacy_group_key($row)][] = $row;
		}

		$used_uids = array();
		$now = time();
		$surviving_ids = array();

		foreach ($groups as $group_key => $group) {
			usort($group, function ($a, $b) {
				return ((int) $a['id']) - ((int) $b['id']);
			});

			$survivor = $group[0];
			$catalog_id = (int) $survivor['id'];
			$surviving_ids[] = $catalog_id;

			$is_nada = strpos($group_key, '__') !== 0;
			$url_normalized = null;
			$display_url = isset($survivor['url']) ? trim((string) $survivor['url']) : '';
			if ($is_nada) {
				$url_normalized = $group_key;
				$display_url = $group_key;
			} elseif ($display_url === '') {
				$display_url = null;
			}

			$title = isset($survivor['title']) ? trim((string) $survivor['title']) : '';
			if ($title === '') {
				$title = $is_nada ? $url_normalized : ('catalog-' . $catalog_id);
			}

			$uid = $this->unique_uid($title, $used_uids);
			$used_uids[$uid] = true;

			$this->db->where('id', $catalog_id)->update('editor_catalogs', array(
				'uid' => $uid,
				'title' => $title,
				'type' => $is_nada ? 'nada' : 'other',
				'url' => $display_url,
				'url_normalized' => $url_normalized,
				'created' => $now,
				'changed' => $now,
				'created_by' => !empty($survivor['user_id']) ? (int) $survivor['user_id'] : null,
			));

			foreach ($group as $row) {
				$api_key = isset($row['api_key']) ? trim((string) $row['api_key']) : '';
				$user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
				if ($api_key === '' || $user_id < 1) {
					continue;
				}

				$exists = $this->db
					->where('catalog_id', $catalog_id)
					->where('user_id', $user_id)
					->get('editor_catalog_credentials')
					->row_array();

				if ($exists) {
					continue;
				}

				$this->db->insert('editor_catalog_credentials', array(
					'catalog_id' => $catalog_id,
					'user_id' => $user_id,
					'api_key' => $api_key,
					'created' => $now,
					'changed' => $now,
				));
			}

			foreach ($group as $row) {
				$other_id = (int) $row['id'];
				if ($other_id === $catalog_id) {
					continue;
				}
				$this->db->where('id', $other_id)->delete('editor_catalogs');
			}
		}

		echo "Shared catalogs: " . count($surviving_ids) . " catalog(s) after merge\n";
	}

	private function legacy_group_key($row)
	{
		$url = isset($row['url']) ? trim((string) $row['url']) : '';
		if ($url === '') {
			return '__empty_' . (int) $row['id'];
		}

		try {
			return normalize_catalog_url($url);
		} catch (Exception $e) {
			return '__invalid_' . (int) $row['id'];
		}
	}

	private function unique_uid($title, array $used_uids)
	{
		$base = catalog_uid_slug($title);
		$uid = $base;
		$i = 2;
		while (isset($used_uids[$uid])) {
			$uid = $base . '-' . $i;
			$i++;
		}
		return $uid;
	}

	private function drop_legacy_catalog_columns()
	{
		if ($this->db->field_exists('api_key', 'editor_catalogs')) {
			$this->db->query('ALTER TABLE `editor_catalogs` DROP COLUMN `api_key`');
		}
		if ($this->db->field_exists('user_id', 'editor_catalogs')) {
			$this->db->query('ALTER TABLE `editor_catalogs` DROP COLUMN `user_id`');
		}
	}

	private function add_catalog_identity_indexes()
	{
		if ($this->db->field_exists('uid', 'editor_catalogs')) {
			$null_uids = $this->db->where('uid IS NULL', null, false)->count_all_results('editor_catalogs');
			if ($null_uids > 0) {
				throw new Exception('Migration_Shared_catalogs_credentials: uid still NULL on some catalogs');
			}
			$this->db->query('ALTER TABLE `editor_catalogs` MODIFY `uid` varchar(100) NOT NULL');
			$this->db->query('ALTER TABLE `editor_catalogs` MODIFY `title` varchar(200) NOT NULL');
		}

		$this->add_unique_index_if_missing('editor_catalogs', 'unq_editor_catalogs_uid', 'uid');
		$this->add_unique_index_if_missing('editor_catalogs', 'unq_editor_catalogs_url_normalized', 'url_normalized');
	}

	private function add_unique_index_if_missing($table, $index_name, $column)
	{
		$found = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($index_name))->row_array();
		if ($found) {
			return;
		}
		$this->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$index_name}` (`{$column}`)");
	}

	private function add_credentials_foreign_key()
	{
		$found = $this->db->query("
			SELECT CONSTRAINT_NAME
			FROM information_schema.TABLE_CONSTRAINTS
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'editor_catalog_credentials'
			  AND CONSTRAINT_NAME = 'fk_editor_catalog_credentials_catalog'
			  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
		")->row_array();

		if ($found) {
			return;
		}

		$this->db->query('
			ALTER TABLE `editor_catalog_credentials`
			ADD CONSTRAINT `fk_editor_catalog_credentials_catalog`
			FOREIGN KEY (`catalog_id`) REFERENCES `editor_catalogs` (`id`) ON DELETE CASCADE
		');
	}
}
