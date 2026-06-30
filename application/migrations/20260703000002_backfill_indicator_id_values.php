<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Populate editor_project_dsd.indicator_id_value from project metadata (series idno).
 *
 * Run after 20260703000001_upgrade_v1_3_0 when data_structures / editor_project_dsd exist.
 * Separated from schema migration so large sites can run it as a second web/CLI step.
 */
class Migration_Backfill_indicator_id_values extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Backfill_indicator_id_values::up()');

		if (!$this->db->table_exists('editor_project_dsd')
			|| !$this->db->field_exists('indicator_id_value', 'editor_project_dsd')) {
			log_message('info', 'editor_project_dsd.indicator_id_value not available; skipping backfill');
			echo "⊘ SKIPPED: editor_project_dsd.indicator_id_value not available\n";
			return;
		}

		$this->load->library('Data_structure_util');
		$rows = $this->db->get('editor_project_dsd')->result_array();
		$updated = 0;
		$skipped = 0;

		foreach ($rows as $row) {
			if (!empty($row['indicator_id_value'])) {
				$skipped++;
				continue;
			}

			$sid = (int) $row['sid'];
			$default = $this->data_structure_util->resolve_default_indicator_id_value($sid);
			if ($default === '') {
				$skipped++;
				continue;
			}

			$this->db->where('sid', $sid)->update('editor_project_dsd', array(
				'indicator_id_value' => $default,
			));
			$this->data_structure_util->sync_indicator_id_value_to_metadata($sid, $default);
			$updated++;
			log_message('info', "Backfilled indicator_id_value for project sid {$sid}");
		}

		echo "Indicator ID backfill: {$updated} updated, {$skipped} skipped\n";
		log_message('info', "Migration_Backfill_indicator_id_values completed (updated: {$updated}, skipped: {$skipped})");
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
