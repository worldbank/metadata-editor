<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Fix core field mappings for the bundled `custom` schema.
 *
 * The registry seed pointed idno/title at /identification/*, but custom-schema.json
 * and the custom templates store them under /description/identification/*. Project
 * title and idno were therefore never extracted on save.
 *
 * Only the old seeded values are rewritten; mappings an admin changed are left alone.
 */
class Migration_Custom_schema_core_field_paths extends MY_Migration {

	private $table = 'metadata_schemas';

	private $replacements = array(
		'idno' => array('/identification/idno', '/description/identification/idno'),
		'title' => array('/identification/title', '/description/identification/title'),
	);

	public function up()
	{
		if (!$this->db->table_exists($this->table)) {
			return;
		}

		$row = $this->db->select('id, metadata_options')
			->where('uid', 'custom')
			->get($this->table)
			->row_array();

		if (!$row || empty($row['metadata_options'])) {
			return;
		}

		$options = json_decode($row['metadata_options'], true);
		if (!is_array($options) || !isset($options['core_fields']) || !is_array($options['core_fields'])) {
			return;
		}

		$changed = false;
		foreach ($this->replacements as $field => $pair) {
			list($old, $new) = $pair;
			if (!isset($options['core_fields'][$field])) {
				continue;
			}

			$value = $options['core_fields'][$field];
			if (is_string($value) && $value === $old) {
				$options['core_fields'][$field] = $new;
				$changed = true;
			} elseif (is_array($value)) {
				foreach ($value as $i => $path) {
					if ($path === $old) {
						$options['core_fields'][$field][$i] = $new;
						$changed = true;
					}
				}
			}
		}

		if (!$changed) {
			return;
		}

		$this->db->where('id', (int)$row['id']);
		$this->db->update($this->table, array('metadata_options' => json_encode($options)));
		log_message('info', 'Migration_Custom_schema_core_field_paths: updated custom schema core_fields');
	}

	public function down()
	{
		// No-op: the old paths never matched any data.
	}
}
