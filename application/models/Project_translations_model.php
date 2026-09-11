<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Swaggest\JsonDiff\JsonPointer;
use Swaggest\JsonDiff\JsonPointerException;

/**
 * Study-metadata translation overlays stored as a sparse JSON tree.
 */
class Project_translations_model extends CI_Model {

	private $table_translations = 'project_translations';

	private $denied_path_prefixes = array(
		'/variables',
		'/data_files',
		'/variable_groups',
		'/data_structure',
		'/data_structure_reference',
		'/admin_metadata',
	);

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Editor_model');
	}

	public function get_project_language($sid)
	{
		$sid = (int) $sid;
		if (!$this->db->field_exists('language', 'editor_projects')) {
			return null;
		}

		$this->db->select('language');
		$this->db->where('id', $sid);
		$row = $this->db->get('editor_projects')->row_array();
		if (!$row) {
			return null;
		}

		$language = isset($row['language']) ? trim((string) $row['language']) : '';
		return $language !== '' ? $language : null;
	}

	public function set_project_language($sid, $language, $user_id = null)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);
		$this->Editor_model->check_project_editable($sid);

		if (!$this->db->field_exists('language', 'editor_projects')) {
			throw new Exception('PROJECT_LANGUAGE_NOT_AVAILABLE');
		}

		$language = $this->normalize_language_code($language);

		if ($this->get_translation_header($sid, $language)) {
			throw new Exception('CANNOT_SET_PRIMARY_TO_TARGET_LANGUAGE');
		}

		$now = date('U');
		$update = array(
			'language' => $language,
			'changed' => $now,
		);
		if ($user_id) {
			$update['changed_by'] = (int) $user_id;
		}

		$this->db->where('id', $sid);
		$this->db->update('editor_projects', $update);

		return $language;
	}

	public function list_translations($sid)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);

		$this->db->where('sid', $sid);
		$this->db->order_by('language', 'ASC');
		$rows = $this->db->get($this->table_translations)->result_array();

		foreach ($rows as &$row) {
			$row['id'] = (int) $row['id'];
			$row['sid'] = (int) $row['sid'];
			$metadata = $this->decode_metadata(isset($row['metadata']) ? $row['metadata'] : null);
			$row['metadata'] = $metadata;
			$row['fields_count'] = $this->count_leaves($metadata);
		}

		return array(
			'language' => $this->get_project_language($sid),
			'translations' => $rows,
		);
	}

	public function get_translation($sid, $language)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);
		$language = $this->normalize_language_code($language);

		$header = $this->get_translation_header($sid, $language);
		if (!$header) {
			throw new Exception('TRANSLATION_NOT_FOUND');
		}

		$metadata = $this->decode_metadata(isset($header['metadata']) ? $header['metadata'] : null);
		$header['id'] = (int) $header['id'];
		$header['sid'] = (int) $header['sid'];
		$header['metadata'] = $metadata;
		$header['fields_count'] = $this->count_leaves($metadata);

		return $header;
	}

	/**
	 * Check stored overlays against current source metadata.
	 * Does not score completeness — only stale or invalid translated paths.
	 */
	public function validate_overlays($sid)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);

		if (!$this->db->table_exists($this->table_translations)) {
			return array(
				'valid' => true,
				'language' => $this->get_project_language($sid),
				'issues' => array(),
				'translations' => array(),
			);
		}

		$source = $this->Editor_model->get_metadata($sid);
		if (!is_array($source)) {
			$source = array();
		}

		$list = $this->list_translations($sid);
		$issues = array();
		$summaries = array();

		foreach ($list['translations'] as $row) {
			$language = $row['language'];
			$leaves = $this->flatten_metadata(isset($row['metadata']) ? $row['metadata'] : array());
			$issue_count = 0;

			foreach ($leaves as $path => $value) {
				$issue = $this->overlay_path_issue($source, $path);
				if ($issue === null) {
					continue;
				}

				$issues[] = array_merge($issue, array(
					'language' => $language,
					'path' => $path,
					'value' => $value,
				));
				$issue_count++;
			}

			$summaries[] = array(
				'language' => $language,
				'fields_count' => count($leaves),
				'issue_count' => $issue_count,
			);
		}

		return array(
			'valid' => empty($issues),
			'language' => $list['language'],
			'issues' => $issues,
			'translations' => $summaries,
		);
	}

	public function add_translation($sid, $language, $user_id = null)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);
		$this->Editor_model->check_project_editable($sid);

		$project_language = $this->get_project_language($sid);
		if ($project_language === null) {
			throw new Exception('PROJECT_LANGUAGE_REQUIRED');
		}

		$language = $this->normalize_language_code($language);
		if ($language === $project_language) {
			throw new Exception('CANNOT_TRANSLATE_PRIMARY_LANGUAGE');
		}

		if ($this->get_translation_header($sid, $language)) {
			throw new Exception('TRANSLATION_EXISTS');
		}

		$now = (int) date('U');
		$row = array(
			'sid' => $sid,
			'language' => $language,
			'metadata' => null,
			'created' => $now,
			'created_by' => $user_id ? (int) $user_id : null,
			'changed' => $now,
			'changed_by' => $user_id ? (int) $user_id : null,
		);

		if (!$this->db->insert($this->table_translations, $row)) {
			$db_error = $this->db->error();
			throw new Exception('FAILED_TO_ADD_TRANSLATION: ' . $db_error['message']);
		}

		return $this->get_translation($sid, $language);
	}

	public function delete_translation($sid, $language)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);
		$this->Editor_model->check_project_editable($sid);

		$language = $this->normalize_language_code($language);
		$header = $this->get_translation_header($sid, $language);
		if (!$header) {
			throw new Exception('TRANSLATION_NOT_FOUND');
		}

		$this->db->where('id', (int) $header['id']);
		if (!$this->db->delete($this->table_translations)) {
			$db_error = $this->db->error();
			throw new Exception('FAILED_TO_DELETE_TRANSLATION: ' . $db_error['message']);
		}

		return true;
	}

	public function save_metadata($sid, $language, $metadata, $user_id = null)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);
		$this->Editor_model->check_project_editable($sid);

		$language = $this->normalize_language_code($language);
		$header = $this->get_translation_header($sid, $language);
		if (!$header) {
			throw new Exception('TRANSLATION_NOT_FOUND');
		}

		if (!is_array($metadata)) {
			throw new Exception('METADATA_REQUIRED');
		}

		$source = $this->Editor_model->get_metadata($sid);
		if (!is_array($source)) {
			$source = array();
		}

		$clean = $this->sanitize_overlay($metadata, $source);
		$encoded = !empty($clean) ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
		if ($encoded === false) {
			throw new Exception('INVALID_TRANSLATION_METADATA');
		}

		$now = (int) date('U');
		$this->db->where('id', (int) $header['id']);
		if (!$this->db->update($this->table_translations, array(
			'metadata' => $encoded,
			'changed' => $now,
			'changed_by' => $user_id ? (int) $user_id : null,
		))) {
			$db_error = $this->db->error();
			throw new Exception('FAILED_TO_SAVE_TRANSLATION: ' . $db_error['message']);
		}

		return $this->get_translation($sid, $language);
	}

	/**
	 * Build a download document with only translated leaves.
	 * Array indexes stay arrays (holes padded) so this is not the stored overlay.
	 */
	public function export_translation_document($sid, $language)
	{
		$translation = $this->get_translation($sid, $language);
		$project = $this->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('PROJECT_NOT_FOUND');
		}

		$overlay = isset($translation['metadata']) && is_array($translation['metadata'])
			? $translation['metadata']
			: array();
		$metadata = array();
		foreach ($this->flatten_metadata($overlay) as $path => $value) {
			$this->set_by_pointer($metadata, $path, $value, true);
		}

		$this->load->library('schema_util');
		$schema_info = $this->schema_util->get_schema_version_info($project['type']);
		if (!$schema_info) {
			throw new Exception('SCHEMA_INFO_NOT_FOUND');
		}

		$idno = isset($project['idno']) ? trim((string) $project['idno']) : '';
		if ($idno === '') {
			$idno = nada_hash($project['id']);
		}

		$document = array(
			'schema' => $schema_info['$id'],
			'schema_version' => $schema_info['version'],
			'type' => $project['type'],
			'idno' => $project['idno'],
			'language' => $translation['language'],
		);

		return array(
			'filename' => $idno . '-' . $translation['language'] . '.json',
			'document' => array_merge($document, $metadata),
		);
	}

	/**
	 * Overlay interchange file: stored sparse tree, not padded arrays.
	 */
	public function export_overlay_document($sid, $language)
	{
		$translation = $this->get_translation($sid, $language);
		$project = $this->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('PROJECT_NOT_FOUND');
		}

		$overlay = isset($translation['metadata']) && is_array($translation['metadata'])
			? $translation['metadata']
			: array();

		$idno = isset($project['idno']) ? trim((string) $project['idno']) : '';
		if ($idno === '') {
			$idno = nada_hash($project['id']);
		}

		return array(
			'filename' => $idno . '-' . $translation['language'] . '-overlay.json',
			'document' => array(
				'format' => 'overlay',
				'type' => $project['type'],
				'idno' => $project['idno'],
				'language' => $translation['language'],
				'source_language' => $this->get_project_language($sid),
				'metadata' => $overlay,
			),
		);
	}

	/**
	 * Overlay interchange file built from original study metadata (offline translate kit).
	 * language is null; set it before import.
	 */
	public function export_source_overlay_document($sid)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);

		$project = $this->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('PROJECT_NOT_FOUND');
		}

		$source = $this->Editor_model->get_metadata($sid);
		if (!is_array($source)) {
			$source = array();
		}

		$overlay = array();
		foreach ($this->flatten_metadata($source) as $path => $value) {
			try {
				$this->assert_study_metadata_path($path);
			} catch (Exception $e) {
				continue;
			}
			$this->set_by_pointer($overlay, $path, $value);
		}

		$idno = isset($project['idno']) ? trim((string) $project['idno']) : '';
		if ($idno === '') {
			$idno = nada_hash($project['id']);
		}

		return array(
			'filename' => $idno . '-source-overlay.json',
			'document' => array(
				'format' => 'overlay',
				'type' => $project['type'],
				'idno' => $project['idno'],
				'language' => null,
				'source_language' => $this->get_project_language($sid),
				'metadata' => $overlay,
			),
		);
	}

	/**
	 * Replace a language overlay from an interchange document. Creates the language if needed.
	 */
	public function import_overlay($sid, $document, $user_id = null)
	{
		$sid = (int) $sid;
		$this->assert_project_exists($sid);
		$this->Editor_model->check_project_editable($sid);

		if (!is_array($document)) {
			throw new Exception('INVALID_OVERLAY_JSON');
		}

		$format = isset($document['format']) ? trim((string) $document['format']) : '';
		if ($format !== 'overlay') {
			throw new Exception('OVERLAY_FORMAT_REQUIRED');
		}

		if (!isset($document['language']) || trim((string) $document['language']) === '') {
			throw new Exception('LANGUAGE_REQUIRED');
		}

		$project = $this->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('PROJECT_NOT_FOUND');
		}

		if (isset($document['type']) && trim((string) $document['type']) !== '') {
			$project_type = $this->Editor_model->resolve_canonical_type($project['type']) ?: $project['type'];
			$file_type = $this->Editor_model->resolve_canonical_type($document['type']) ?: $document['type'];
			if ($project_type && $file_type && $project_type !== $file_type) {
				throw new Exception('OVERLAY_TYPE_MISMATCH');
			}
		}

		$project_idno = isset($project['idno']) ? trim((string) $project['idno']) : '';
		$file_idno = isset($document['idno']) ? trim((string) $document['idno']) : '';
		if ($project_idno !== '' && $file_idno !== '' && strcasecmp($project_idno, $file_idno) !== 0) {
			throw new Exception('OVERLAY_IDNO_MISMATCH');
		}

		$language = $this->normalize_language_code($document['language']);
		if (!$this->get_translation_header($sid, $language)) {
			$this->add_translation($sid, $language, $user_id);
		}

		$metadata = isset($document['metadata']) && is_array($document['metadata'])
			? $document['metadata']
			: array();

		return $this->save_metadata($sid, $language, $metadata, $user_id);
	}

	public function tree_from_fields($fields)
	{
		$incoming = $this->normalize_fields_payload($fields);
		$tree = array();
		foreach ($incoming as $item) {
			$value = isset($item['value']) ? trim((string) $item['value']) : '';
			if ($value === '') {
				continue;
			}
			$path = $this->normalize_field_path($item['field_path']);
			$this->set_by_pointer($tree, $path, $value);
		}
		return $tree;
	}

	public function normalize_language_code($language)
	{
		$language = strtolower(trim((string) $language));
		if ($language === '') {
			throw new Exception('LANGUAGE_REQUIRED');
		}

		$language = str_replace('_', '-', $language);

		$mapped = $this->map_folder_to_code($language);
		if ($mapped !== null) {
			return $mapped;
		}

		if (preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})?$/', $language) || preg_match('/^[a-z]{3}$/', $language)) {
			return $language;
		}

		throw new Exception('INVALID_LANGUAGE: ' . $language);
	}

	public function normalize_field_path($path)
	{
		$path = trim((string) $path);
		if ($path === '' || $path === '/') {
			throw new Exception('INVALID_FIELD_PATH');
		}

		if ($path[0] !== '/') {
			$path = preg_replace('/\[(\d+)\]/', '/$1', $path);
			$path = str_replace('.', '/', $path);
			if ($path === '' || $path[0] !== '/') {
				$path = '/' . ltrim($path, '/');
			}
		}

		if (strlen($path) > 500) {
			throw new Exception('FIELD_PATH_TOO_LONG');
		}

		if (strpos($path, '//') !== false || preg_match('/\/\.\.(\/|$)/', $path)) {
			throw new Exception('INVALID_FIELD_PATH');
		}

		return $path;
	}

	public function assert_study_metadata_path($path)
	{
		foreach ($this->denied_path_prefixes as $prefix) {
			if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
				throw new Exception('FIELD_PATH_NOT_STUDY_METADATA: ' . $path);
			}
		}
	}

	private function sanitize_overlay($node, $source, $prefix = '')
	{
		if (!is_array($node)) {
			throw new Exception('INVALID_TRANSLATION_METADATA');
		}

		$out = array();
		foreach ($node as $key => $value) {
			$path = $prefix . '/' . $key;
			if (is_array($value)) {
				$child = $this->sanitize_overlay($value, $source, $path);
				if (!empty($child)) {
					$out[$key] = $child;
				}
				continue;
			}

			if (is_bool($value) || is_object($value)) {
				throw new Exception('FIELD_VALUE_NOT_SCALAR: ' . $path);
			}

			$value = trim((string) $value);
			if ($value === '') {
				continue;
			}

			$this->assert_study_metadata_path($path);
			$this->source_value($source, $path);
			$out[$key] = $value;
		}

		return $out;
	}

	private function flatten_metadata($node, $prefix = '')
	{
		$out = array();
		if (!is_array($node)) {
			return $out;
		}

		foreach ($node as $key => $value) {
			$path = $prefix . '/' . $key;
			if (is_array($value)) {
				$out = array_merge($out, $this->flatten_metadata($value, $path));
				continue;
			}
			if (is_scalar($value) && !is_bool($value) && trim((string) $value) !== '') {
				$out[$path] = (string) $value;
			}
		}

		return $out;
	}

	private function count_leaves($node)
	{
		return count($this->flatten_metadata($node));
	}

	private function set_by_pointer(&$tree, $path, $value, $pad_array_holes = false)
	{
		$parts = explode('/', ltrim($path, '/'));
		$ref = &$tree;
		$last = count($parts) - 1;
		foreach ($parts as $i => $key) {
			$is_index = $key !== '' && (string) (int) $key === (string) $key;
			if ($is_index) {
				$key = (int) $key;
				if ($pad_array_holes) {
					for ($n = 0; $n < $key; $n++) {
						if (!array_key_exists($n, $ref)) {
							$ref[$n] = new \stdClass();
						}
					}
					ksort($ref);
				}
			}
			if ($i === $last) {
				$ref[$key] = $value;
				return;
			}
			if ($pad_array_holes && isset($ref[$key]) && $ref[$key] instanceof \stdClass) {
				$ref[$key] = array();
			}
			if (!isset($ref[$key]) || !is_array($ref[$key])) {
				$ref[$key] = array();
			}
			$ref = &$ref[$key];
		}
	}

	private function decode_metadata($raw)
	{
		if ($raw === null || $raw === '') {
			return array();
		}
		if (is_array($raw)) {
			return $raw;
		}
		$decoded = json_decode((string) $raw, true);
		return is_array($decoded) ? $decoded : array();
	}

	private function map_folder_to_code($language)
	{
		$language_codes = $this->config->item('language_codes');
		if (!is_array($language_codes)) {
			return null;
		}

		foreach ($language_codes as $folder => $entry) {
			$entry = is_array($entry) ? $entry : (array) $entry;
			$folder_name = str_replace('_', '-', strtolower((string) $folder));
			$name = isset($entry['name']) ? str_replace('_', '-', strtolower((string) $entry['name'])) : '';
			if (($folder_name === $language || $name === $language) && !empty($entry['code'])) {
				return strtolower(str_replace('_', '-', (string) $entry['code']));
			}
		}

		return null;
	}

	private function get_translation_header($sid, $language)
	{
		$this->db->where('sid', (int) $sid);
		$this->db->where('language', $language);
		return $this->db->get($this->table_translations)->row_array();
	}

	private function assert_project_exists($sid)
	{
		if (!$this->Editor_model->check_id_exists($sid)) {
			throw new Exception('PROJECT_NOT_FOUND');
		}
	}

	private function normalize_fields_payload($fields)
	{
		if (!is_array($fields)) {
			throw new Exception('FIELDS_REQUIRED');
		}

		$out = array();
		$is_list = $fields === array() || array_keys($fields) === range(0, count($fields) - 1);

		if ($is_list) {
			foreach ($fields as $item) {
				if (!is_array($item) || !isset($item['field_path'])) {
					throw new Exception('INVALID_FIELD_ITEM');
				}
				$out[] = array(
					'field_path' => $item['field_path'],
					'value' => isset($item['value']) ? $item['value'] : '',
				);
			}
			return $out;
		}

		foreach ($fields as $path => $value) {
			$out[] = array(
				'field_path' => $path,
				'value' => $value,
			);
		}

		return $out;
	}

	private function source_value($metadata, $path)
	{
		$issue = $this->overlay_path_issue($metadata, $path);
		if ($issue !== null) {
			throw new Exception($issue['code'] . ': ' . $path);
		}

		return JsonPointer::getByPointer($metadata, $path);
	}

	private function overlay_path_issue($metadata, $path)
	{
		try {
			$this->assert_study_metadata_path($path);
		} catch (Exception $e) {
			return array(
				'type' => 'not_study_metadata',
				'code' => 'FIELD_PATH_NOT_STUDY_METADATA',
				'message' => 'Translated field is not study metadata: ' . $path,
			);
		}

		try {
			$value = JsonPointer::getByPointer($metadata, $path);
		} catch (JsonPointerException $e) {
			return array(
				'type' => 'not_in_source',
				'code' => 'FIELD_PATH_NOT_IN_SOURCE',
				'message' => 'Translated field is not in the original metadata: ' . $path,
			);
		}

		if (is_array($value) || is_object($value) || is_bool($value)) {
			return array(
				'type' => 'not_scalar',
				'code' => 'FIELD_PATH_NOT_SCALAR',
				'message' => 'Original field is not a text value: ' . $path,
			);
		}

		if ($value === null || trim((string) $value) === '') {
			return array(
				'type' => 'source_empty',
				'code' => 'FIELD_PATH_SOURCE_EMPTY',
				'message' => 'Original field is empty: ' . $path,
			);
		}

		return null;
	}
}
