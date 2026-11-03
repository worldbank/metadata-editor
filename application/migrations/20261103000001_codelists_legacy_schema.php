<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Upgrade a legacy `codelists` table to the global codelists registry schema.
 *
 * Installs that ran the April 2026 schema have `codelists` with
 * (agency, codelist_id, version, name, description, ...). The v1.3 upgrade uses
 * CREATE TABLE codelists, which is skipped as 1050 (table exists), leaving
 * the table without pid / idno / version_seq / title / status and causing
 * "Unknown column 'version_seq'" on codelist create.
 *
 * Converts in place so row ids (and FKs from codelist_labels, codelist_items,
 * data_structure_components, editor_templates_codelists) are preserved:
 *   legacy name        -> title
 *   legacy codelist_id -> name (SDMX maintainable id)
 */
class Migration_Codelists_legacy_schema extends MY_Migration {

	private $table = 'codelists';

	public function up()
	{
		if (!$this->db->table_exists($this->table)) {
			log_message('info', 'Migration_Codelists_legacy_schema: no codelists table, skipping');
			return;
		}

		$fields = $this->db->list_fields($this->table);
		if (in_array('version_seq', $fields, true) && !in_array('codelist_id', $fields, true)) {
			log_message('info', 'Migration_Codelists_legacy_schema: codelists already up to date');
			return;
		}

		$this->drop_index_if_exists('uq_codelist_identity');
		$this->drop_index_if_exists('idx_agency');
		$this->drop_index_if_exists('idx_created');

		if (in_array('codelist_id', $fields, true)) {
			$this->assert_max_length('codelist_id', 64);
			if (!in_array('title', $fields, true)) {
				$this->db->query('ALTER TABLE `codelists` ADD COLUMN `title` varchar(255) NULL AFTER `version`');
			}
			$this->db->query('UPDATE `codelists` SET `title` = `name`');
			$this->db->query('UPDATE `codelists` SET `name` = TRIM(`codelist_id`)');
			$this->db->query('ALTER TABLE `codelists` DROP COLUMN `codelist_id`');
		}

		$this->assert_max_length('agency', 64);
		$this->assert_max_length('name', 64);
		$this->assert_max_length('version', 32);

		$fields = $this->db->list_fields($this->table);
		if (!in_array('pid', $fields, true)) {
			$this->db->query("ALTER TABLE `codelists` ADD COLUMN `pid` bigint NULL COMMENT 'Family head row id (latest version for agency+name); set on create' AFTER `id`");
		}
		if (!in_array('idno', $fields, true)) {
			$this->db->query('ALTER TABLE `codelists` ADD COLUMN `idno` varchar(191) NULL AFTER `pid`');
		}
		if (!in_array('version_seq', $fields, true)) {
			$this->db->query("ALTER TABLE `codelists` ADD COLUMN `version_seq` int NULL COMMENT 'Monotonic sequence within (agency, name)' AFTER `version`");
		}
		if (!in_array('title', $fields, true)) {
			$this->db->query('ALTER TABLE `codelists` ADD COLUMN `title` varchar(255) NULL AFTER `version_seq`');
		}
		if (!in_array('status', $fields, true)) {
			$this->db->query("ALTER TABLE `codelists` ADD COLUMN `status` enum('draft','active','locked','archived') NOT NULL DEFAULT 'active' AFTER `uri`");
		}

		$this->backfill_rows();

		$this->db->query("
			ALTER TABLE `codelists`
			  MODIFY COLUMN `idno` varchar(191) NOT NULL,
			  MODIFY COLUMN `agency` varchar(64) NOT NULL,
			  MODIFY COLUMN `name` varchar(64) NOT NULL COMMENT 'SDMX maintainable id (NADA codelists.name)',
			  MODIFY COLUMN `version` varchar(32) NOT NULL,
			  MODIFY COLUMN `version_seq` int NOT NULL COMMENT 'Monotonic sequence within (agency, name)',
			  MODIFY COLUMN `title` varchar(255) NOT NULL COMMENT 'Human-readable list title'
		");

		$this->add_index_if_missing('uq_codelists_idno', 'UNIQUE', '`idno`');
		$this->add_index_if_missing('uq_codelist_identity', 'UNIQUE', '`agency`, `name`, `version`');
		$this->add_index_if_missing('uq_codelists_family_seq', 'UNIQUE', '`agency`, `name`, `version_seq`');
		$this->add_index_if_missing('idx_codelists_agency', 'INDEX', '`agency`');
		$this->add_index_if_missing('idx_codelists_pid', 'INDEX', '`pid`');
		$this->add_index_if_missing('idx_codelists_status', 'INDEX', '`status`');
		$this->add_index_if_missing('idx_codelists_created', 'INDEX', '`created_at`');

		$fk = $this->db->query(
			"SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'codelists'
			   AND CONSTRAINT_NAME = 'fk_codelists_pid'"
		)->row_array();
		if (!$fk) {
			$this->db->query('ALTER TABLE `codelists` ADD CONSTRAINT `fk_codelists_pid` FOREIGN KEY (`pid`) REFERENCES `codelists` (`id`) ON DELETE RESTRICT');
		}

		log_message('info', 'Migration_Codelists_legacy_schema: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	/**
	 * Fill version_seq, idno, title and pid per (agency, name) family, oldest id first.
	 */
	private function backfill_rows()
	{
		$rows = $this->db->query(
			'SELECT `id`, `agency`, `name`, `version`, `version_seq`, `idno`, `title` FROM `codelists` ORDER BY `agency`, `name`, `id`'
		)->result_array();

		$families = array();
		foreach ($rows as $row) {
			$families[$row['agency'] . "\0" . $row['name']][] = $row;
		}

		foreach ($families as $family) {
			$used = array();
			foreach ($family as $row) {
				if ((int) $row['version_seq'] > 0) {
					$used[(int) $row['version_seq']] = true;
				}
			}

			$seq = 0;
			$head_id = 0;
			$head_seq = 0;
			foreach ($family as $row) {
				$update = array();
				$row_seq = (int) $row['version_seq'];
				if ($row_seq <= 0) {
					do {
						$seq++;
					} while (isset($used[$seq]));
					$row_seq = $seq;
					$used[$row_seq] = true;
					$update['version_seq'] = $row_seq;
				}
				if (trim((string) $row['idno']) === '') {
					$update['idno'] = substr($this->make_idno($row['agency'], $row['name'], $row['version']), 0, 191);
				}
				if (trim((string) $row['title']) === '') {
					$update['title'] = $row['name'];
				}
				if ($update) {
					$this->db->where('id', (int) $row['id'])->update($this->table, $update);
				}
				if ($row_seq > $head_seq) {
					$head_seq = $row_seq;
					$head_id = (int) $row['id'];
				}
			}

			$ids = array_map(function ($r) { return (int) $r['id']; }, $family);
			$this->db->where_in('id', $ids)->update($this->table, array('pid' => $head_id));
		}
	}

	/** Same format as Codelists_model::make_idno(). */
	private function make_idno($agency, $name, $version)
	{
		$agency  = trim((string) $agency) !== '' ? trim((string) $agency) : 'NADA';
		$version = trim((string) $version) !== '' ? trim((string) $version) : '1.0';
		return $agency . '_' . trim((string) $name) . '_' . $version;
	}

	private function assert_max_length($column, $max)
	{
		$rows = $this->db->query(
			'SELECT `id`, `' . $column . '` AS v FROM `codelists` WHERE CHAR_LENGTH(`' . $column . '`) > ' . (int) $max . ' LIMIT 10'
		)->result_array();
		if ($rows) {
			$ids = implode(', ', array_map(function ($r) { return $r['id']; }, $rows));
			throw new Exception("codelists.{$column} exceeds {$max} characters for id(s): {$ids}. Shorten these values and re-run the migration.");
		}
	}

	private function drop_index_if_exists($index_name)
	{
		$found = $this->db->query(
			'SHOW INDEX FROM `codelists` WHERE Key_name = ' . $this->db->escape($index_name)
		)->row_array();
		if ($found) {
			$this->db->query('ALTER TABLE `codelists` DROP INDEX `' . $index_name . '`');
		}
	}

	private function add_index_if_missing($index_name, $type, $columns)
	{
		$found = $this->db->query(
			'SHOW INDEX FROM `codelists` WHERE Key_name = ' . $this->db->escape($index_name)
		)->row_array();
		if ($found) {
			return;
		}
		$this->db->query('ALTER TABLE `codelists` ADD ' . $type . ' `' . $index_name . '` (' . $columns . ')');
	}
}
