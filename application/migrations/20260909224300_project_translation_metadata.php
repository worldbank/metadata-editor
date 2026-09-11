<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Project primary language and study-metadata translation overlays.
 */
class Migration_Project_translation_metadata extends MY_Migration {

	public function up()
	{
		if (!$this->db->table_exists('editor_projects')) {
			throw new Exception('Migration_Project_translation_metadata: editor_projects table missing');
		}

		if (!$this->db->field_exists('language', 'editor_projects')) {
			$this->db->query("
				ALTER TABLE `editor_projects`
				ADD COLUMN `language` varchar(10) DEFAULT NULL
			");
		}

		if (!$this->db->table_exists('project_translations')) {
			$this->db->query("
				CREATE TABLE `project_translations` (
				  `id` bigint NOT NULL AUTO_INCREMENT,
				  `sid` int NOT NULL,
				  `language` varchar(10) NOT NULL,
				  `metadata` json DEFAULT NULL,
				  `created` int NOT NULL,
				  `created_by` int DEFAULT NULL,
				  `changed` int NOT NULL,
				  `changed_by` int DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `uq_project_translation_lang` (`sid`,`language`),
				  CONSTRAINT `fk_project_translations_project`
				    FOREIGN KEY (`sid`) REFERENCES `editor_projects` (`id`) ON DELETE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		} else {
			if (!$this->db->field_exists('metadata', 'project_translations')) {
				$this->db->query("
					ALTER TABLE `project_translations`
					ADD COLUMN `metadata` json DEFAULT NULL
				");
			}

			if ($this->db->table_exists('project_translation_fields')) {
				$this->fold_field_rows_into_metadata();
				$this->db->query('DROP TABLE `project_translation_fields`');
			}

			if ($this->db->field_exists('status', 'project_translations')) {
				$this->db->query('ALTER TABLE `project_translations` DROP COLUMN `status`');
			}

			if ($this->index_exists('project_translations', 'idx_project_translations_language')) {
				$this->db->query('ALTER TABLE `project_translations` DROP INDEX `idx_project_translations_language`');
			}
		}

		log_message('info', 'Migration_Project_translation_metadata: completed');
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	private function fold_field_rows_into_metadata()
	{
		$headers = $this->db->get('project_translations')->result_array();
		foreach ($headers as $header) {
			$translation_id = (int) $header['id'];
			$this->db->where('translation_id', $translation_id);
			$this->db->order_by('field_path', 'ASC');
			$fields = $this->db->get('project_translation_fields')->result_array();
			if (!$fields) {
				continue;
			}

			$tree = array();
			foreach ($fields as $field) {
				$path = isset($field['field_path']) ? (string) $field['field_path'] : '';
				$value = isset($field['value']) ? trim((string) $field['value']) : '';
				if ($path === '' || $value === '') {
					continue;
				}
				$this->set_by_pointer($tree, $path, $value);
			}

			$encoded = !empty($tree) ? json_encode($tree, JSON_UNESCAPED_UNICODE) : null;
			$this->db->where('id', $translation_id);
			$this->db->update('project_translations', array('metadata' => $encoded));
		}
	}

	private function set_by_pointer(&$tree, $path, $value)
	{
		$parts = explode('/', ltrim($path, '/'));
		$ref = &$tree;
		$last = count($parts) - 1;
		foreach ($parts as $i => $key) {
			$is_index = $key !== '' && (string) (int) $key === (string) $key;
			if ($is_index) {
				$key = (int) $key;
			}
			if ($i === $last) {
				$ref[$key] = $value;
				return;
			}
			if (!isset($ref[$key]) || !is_array($ref[$key])) {
				$ref[$key] = array();
			}
			$ref = &$ref[$key];
		}
	}

	private function index_exists($table, $index)
	{
		$query = $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', array($index));
		return $query && $query->num_rows() > 0;
	}
}
