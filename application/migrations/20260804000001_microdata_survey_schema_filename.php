<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH . 'core/MY_Migration.php');

/**
 * Point microdata registry at compositional survey-schema.json (replaces microdata-schema.json).
 *
 * Applies install/schema.mysql-update-microdata-survey-schema.sql.
 */
class Migration_Microdata_survey_schema_filename extends MY_Migration {

	public function up()
	{
		log_message('info', 'Migration_Microdata_survey_schema_filename::up() called');

		if (!$this->db->table_exists('metadata_schemas')) {
			log_message('error', 'Migration_Microdata_survey_schema_filename: metadata_schemas table missing');
			return;
		}

		$sql_file = $this->get_sql_file_path('schema.mysql-update-microdata-survey-schema');

		if (!file_exists($sql_file)) {
			throw new Exception('SQL file not found: ' . $sql_file);
		}

		log_message('info', 'Updating metadata_schemas.filename for microdata...');
		$this->execute_sql_file($sql_file);

		$this->ensure_microdata_project_schema_file();

		log_message('info', 'Migration_Microdata_survey_schema_filename completed successfully');
	}

	private function ensure_microdata_project_schema_file()
	{
		$row = $this->db
			->where('uid', 'microdata')
			->get('metadata_schemas')
			->row_array();

		if (!$row) {
			return;
		}

		$files = $this->decode_schema_files($row['schema_files']);

		if (in_array('project-schema.json', $files, true)) {
			return;
		}

		array_unshift($files, 'project-schema.json');

		$this->db
			->where('uid', 'microdata')
			->update('metadata_schemas', array(
				'schema_files' => json_encode(array_values($files)),
				'updated' => time(),
			));
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
