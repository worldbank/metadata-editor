<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Add missing provenance-schema.json to Image and Video schema_files.
 *
 * Both schemas reference provenance-schema.json via $ref but the registry
 * rows omitted that file, which breaks ReDoc preview (Invalid JSON pointer).
 */
class Migration_Fix_image_video_schema_files extends CI_Migration
{
	public function up()
	{
		if (!$this->db->table_exists('metadata_schemas')) {
			log_message('error', 'Migration_Fix_image_video_schema_files: metadata_schemas table missing');
			return;
		}

		$this->ensure_related_schema_file('video', 'provenance-schema.json', true);
		$this->ensure_related_schema_file('image', 'provenance-schema.json', false);
	}

	public function down()
	{
		throw new Exception('Rollback not supported — restore from database backup if needed.');
	}

	/**
	 * @param string $uid Schema UID
	 * @param string $filename Related schema filename to ensure
	 * @param bool $replace_when_empty Replace schema_files when empty instead of appending
	 */
	private function ensure_related_schema_file($uid, $filename, $replace_when_empty = false)
	{
		$row = $this->db
			->where('uid', $uid)
			->get('metadata_schemas')
			->row_array();

		if (!$row) {
			return;
		}

		$files = $this->decode_schema_files($row['schema_files']);

		if (in_array($filename, $files, true)) {
			return;
		}

		if ($replace_when_empty && empty($files)) {
			$files = array($filename);
		} else {
			$files[] = $filename;
		}

		$this->db
			->where('uid', $uid)
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
}
