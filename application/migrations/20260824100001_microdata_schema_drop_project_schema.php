<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Drop request-only project-schema.json from the microdata stored-document schema_files list.
 *
 * survey-schema.json no longer allOfs project-schema.json. Create/update request
 * parameters stay in project-schema.json and OpenAPI, not in stored study JSON.
 */
class Migration_Microdata_schema_drop_project_schema extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Microdata_schema_drop_project_schema::up() called');

		if (!$this->db->table_exists('metadata_schemas')) {
			log_message('error', 'Migration_Microdata_schema_drop_project_schema: metadata_schemas table missing');
			return;
		}

		$row = $this->db
			->where('uid', 'microdata')
			->get('metadata_schemas')
			->row_array();

		if (!$row) {
			return;
		}

		$files = $this->decode_schema_files($row['schema_files']);
		$filtered = array_values(array_filter($files, function ($file) {
			return $file !== 'project-schema.json';
		}));

		if ($filtered === array_values($files)) {
			log_message('info', 'Migration_Microdata_schema_drop_project_schema: project-schema.json already absent');
			return;
		}

		$this->db
			->where('uid', 'microdata')
			->update('metadata_schemas', array(
				'schema_files' => json_encode($filtered),
				'updated' => time(),
			));

		log_message('info', 'Migration_Microdata_schema_drop_project_schema completed successfully');
	}

	private function decode_schema_files($value)
	{
		if (is_array($value)) {
			return array_values($value);
		}

		if (!is_string($value) || trim($value) === '') {
			return array();
		}

		$decoded = json_decode($value, true);

		return is_array($decoded) ? array_values($decoded) : array();
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}
}
