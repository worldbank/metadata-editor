<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Seed editor_templates_default for all core schema types when not already set.
 */
class Migration_Editor_templates_defaults extends CI_Migration
{
	public function up()
	{
		if (!$this->db->table_exists('editor_templates_default')) {
			log_message('error', 'Migration_Editor_templates_defaults: editor_templates_default table missing');
			return;
		}

		require APPPATH . 'config/editor_templates.php';

		if (!isset($config['editor_template_defaults']) || !is_array($config['editor_template_defaults'])) {
			throw new Exception('editor_template_defaults is not defined in config/editor_templates.php');
		}

		foreach ($config['editor_template_defaults'] as $data_type => $template_uid) {
			$existing = $this->db
				->where('data_type', $data_type)
				->get('editor_templates_default')
				->row_array();

			if ($existing) {
				continue;
			}

			$this->db->insert('editor_templates_default', array(
				'data_type' => $data_type,
				'template_uid' => $template_uid,
			));
		}
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
