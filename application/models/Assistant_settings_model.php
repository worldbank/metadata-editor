<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Per-project assistant settings. Today one switch: keep_local, "never send this project to a hosted
 * model". Off by default. The table comes from migration 20261117000001 (php index.php cli/migrate/latest).
 */
class Assistant_settings_model extends CI_Model {

	const TABLE = 'assistant_project_settings';

	/**
	 * True when the project must stay on this server. An unreadable setting counts as true: a failure
	 * must never send data out.
	 */
	public function keep_local($sid)
	{
		try {
			if (!$this->db->table_exists(self::TABLE)) {
				// Not migrated yet: no project can have been switched, so none is kept local.
				log_message('error', 'Assistant_settings_model: table ' . self::TABLE . ' is missing; run the database migrations');
				return false;
			}
			$row = $this->db->select('keep_local')->where('sid', (int)$sid)->get(self::TABLE)->row_array();
			return $row ? ((int)$row['keep_local'] === 1) : false;
		} catch (Exception $e) {
			log_message('error', 'Assistant_settings_model::keep_local ' . $e->getMessage());
			return true;
		}
	}

	public function set_keep_local($sid, $value, $user_id)
	{
		if (!$this->db->table_exists(self::TABLE)) {
			throw new Exception('The assistant settings table is missing. An administrator needs to run the database migrations.');
		}
		$this->db->query(
			'INSERT INTO `' . self::TABLE . '` (sid, keep_local, updated_by, updated_on) VALUES (?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE keep_local = VALUES(keep_local), updated_by = VALUES(updated_by), updated_on = VALUES(updated_on)',
			array((int)$sid, $value ? 1 : 0, $user_id ? (int)$user_id : null, time())
		);
	}
}
