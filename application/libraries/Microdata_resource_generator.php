<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Generate or regenerate dat/micro external resources from editor data files.
 */
class Microdata_resource_generator
{
	const SUPPORTED_FORMATS = array('csv', 'dta', 'sav', 'xpt', 'json');

	/** @var CI_Controller */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Editor_datafile_model');
		$this->ci->load->model('Editor_resource_model');
		$this->ci->load->model('Editor_resource_datafile_model');
		$this->ci->load->library('DataUtils');
		$this->ci->load->library('Datafile_export');
		$this->ci->load->library('Datafile_export_zip');
		$this->ci->load->library('Microdata_resource_mapper');
		$this->ci->load->library('Data_dictionary_csv');
	}

	/**
	 * @param int $sid
	 * @param array $options file_ids[], export_format, export_version, zip (bool), overwrite (bool), resource_id (regenerate), user_id, refresh_description (bool), max_wait_seconds
	 * @return array
	 */
	public function generate($sid, array $options = array())
	{
		set_time_limit(0);

		$sid = (int) $sid;
		$project = $this->ci->Editor_model->get_basic_info($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}

		$type = isset($project['type']) ? strtolower((string) $project['type']) : '';
		if ($type !== 'survey' && $type !== 'microdata') {
			throw new Exception('Microdata resource generation is only supported for survey/microdata projects');
		}

		$export_format = strtolower(trim((string) (isset($options['export_format']) ? $options['export_format'] : '')));
		if (!in_array($export_format, self::SUPPORTED_FORMATS, true)) {
			throw new Exception('Unsupported export format: ' . $export_format);
		}

		$export_version = isset($options['export_version']) ? $options['export_version'] : null;
		if ($export_format === 'dta' && ($export_version === null || $export_version === '')) {
			$export_version = 14;
		}

		$use_zip = !isset($options['zip']) || $options['zip'] === true || $options['zip'] === 'true' || $options['zip'] === 1;
		$overwrite = !empty($options['overwrite']);
		$resource_id = isset($options['resource_id']) ? (int) $options['resource_id'] : null;
		$user_id = isset($options['user_id']) ? (int) $options['user_id'] : null;
		$refresh_description = !empty($options['refresh_description']);
		$max_wait = isset($options['max_wait_seconds']) ? (int) $options['max_wait_seconds'] : 900;
		$file_modes = isset($options['file_modes']) && is_array($options['file_modes']) ? $options['file_modes'] : array();

		$all_datafiles = $this->ci->Editor_datafile_model->select_all($sid);
		if (empty($all_datafiles)) {
			throw new Exception('Project has no data files');
		}

		$file_ids = isset($options['file_ids']) && is_array($options['file_ids']) ? $options['file_ids'] : array();
		if (empty($file_ids)) {
			$file_ids = array_keys($all_datafiles);
		}

		$selected = array();
		foreach ($file_ids as $fid) {
			$fid = (string) $fid;
			if (!isset($all_datafiles[$fid])) {
				throw new Exception('Data file not found: ' . $fid);
			}
			$selected[$fid] = $all_datafiles[$fid];
		}

		if (empty($selected)) {
			throw new Exception('No data files selected');
		}

		$existing_resource = null;
		if ($resource_id !== null && $resource_id > 0) {
			$existing_resource = $this->ci->Editor_resource_model->select_single($sid, $resource_id);
			if (!$existing_resource) {
				throw new Exception('Resource not found for regenerate');
			}
			if (!$this->ci->Editor_resource_datafile_model->is_microdata_dctype($existing_resource['dctype'])) {
				throw new Exception('Regenerate is only supported for microdata resources');
			}
		} elseif (!$overwrite) {
			$existing_resource = $this->ci->Editor_resource_datafile_model->find_generated_resource_by_format($sid, $export_format);
			if ($existing_resource) {
				return array(
					'status' => 'exists',
					'message' => 'A generated microdata resource already exists for this format',
					'resource' => $existing_resource,
					'resource_id' => (int) $existing_resource['id'],
				);
			}
		} else {
			$existing_resource = $this->ci->Editor_resource_datafile_model->find_generated_resource_by_format($sid, $export_format);
			if ($existing_resource) {
				$resource_id = (int) $existing_resource['id'];
			}
		}

		$tmp_folder = $this->ci->datafile_export_zip->ensure_tmp_folder($sid);
		$doc_folder = $this->ci->datafile_export_zip->ensure_documentation_folder($sid);

		$exported_files = array();
		foreach ($selected as $fid => $datafile) {
			$mode = $this->resolve_file_export_mode($sid, $fid, $datafile, $export_format, $file_modes);
			if ($mode === 'original') {
				$exported = $this->copy_original_to_tmp(
					$sid,
					$fid,
					$datafile,
					$export_format,
					$tmp_folder
				);
			} else {
				$exported = $this->export_datafile_to_tmp(
					$sid,
					$fid,
					$datafile,
					$export_format,
					$export_version,
					$tmp_folder,
					$max_wait
				);
			}
			if ($export_format === 'csv') {
				$exported = $this->attach_dictionary_csv_export($sid, $fid, $datafile, $tmp_folder, $exported);
			}
			$exported_files[] = $exported;
		}

		$bundle_type = $use_zip ? 'zip' : 'single';
		$artifact_basename = null;
		$old_filename = $existing_resource && !empty($existing_resource['filename'])
			? (string) $existing_resource['filename']
			: null;

		if ($use_zip) {
			$files_to_add = array();
			foreach ($exported_files as $exp) {
				$files_to_add[] = array(
					'full' => $exp['path'],
					'entry' => $exp['zip_entry_name'],
				);
				if ($export_format === 'csv' && !empty($exp['dictionary_path']) && !empty($exp['dictionary_zip_entry'])) {
					$files_to_add[] = array(
						'full' => $exp['dictionary_path'],
						'entry' => $exp['dictionary_zip_entry'],
					);
				}
			}
			$zip_filename = $this->ci->datafile_export_zip->compute_zip_filename($sid, $files_to_add, array(
				'export_format' => $export_format,
				'export_version' => $export_version,
			));
			$this->ci->datafile_export_zip->create_zip($tmp_folder, $files_to_add, $zip_filename);
			$artifact_basename = $zip_filename;
			$source_tmp_path = $tmp_folder . DIRECTORY_SEPARATOR . $zip_filename;
		} else {
			if (count($exported_files) !== 1) {
				throw new Exception('zip=false requires exactly one data file');
			}
			$artifact_basename = $exported_files[0]['zip_entry_name'];
			$source_tmp_path = $exported_files[0]['path'];
		}

		$dest_path = rtrim($doc_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $artifact_basename;
		if (!@copy($source_tmp_path, $dest_path)) {
			throw new Exception('Failed to copy artifact to documentation folder');
		}

		$description = null;
		if ($refresh_description || $resource_id === null) {
			$description = $this->ci->microdata_resource_mapper->build_description(array_values($selected));
		} elseif ($existing_resource && isset($existing_resource['description'])) {
			$description = $existing_resource['description'];
		}

		$resource_fields = $this->ci->microdata_resource_mapper->build_resource_fields($sid, array(
			'export_format' => $export_format,
			'export_version' => $export_version,
			'filename' => $artifact_basename,
			'bundle_type' => $bundle_type,
			'source_type' => 'generated',
			'description' => $description,
		));

		$link_rows = array();
		foreach ($exported_files as $exp) {
			$link_rows[] = $this->ci->Editor_resource_datafile_model->build_generated_link_row(
				$sid,
				$exp['file_id'],
				$exp['export_format'],
				isset($exp['export_version']) ? $exp['export_version'] : $export_version,
				$exp['zip_entry_name'],
				isset($exp['source_mode']) ? $exp['source_mode'] : 'generated'
			);
		}

		$this->ci->db->trans_start();

		if ($resource_id !== null && $resource_id > 0) {
			$this->ci->Editor_resource_model->update($resource_id, $resource_fields);
			$new_resource_id = $resource_id;
		} else {
			$new_resource_id = (int) $this->ci->Editor_resource_model->insert($resource_fields);
		}

		$this->ci->Editor_resource_datafile_model->replace_links_for_resource($sid, $new_resource_id, $link_rows, $user_id);

		$this->ci->db->trans_complete();

		if ($this->ci->db->trans_status() === false) {
			throw new Exception('Database transaction failed while saving microdata resource');
		}

		if ($old_filename !== null && $old_filename !== $artifact_basename) {
			$old_path = rtrim($doc_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($old_filename);
			if (is_file($old_path)) {
				@unlink($old_path);
			}
		}

		$this->cleanup_tmp_files($tmp_folder, $exported_files, $use_zip ? $artifact_basename : null);

		$resource = $this->ci->Editor_resource_model->select_single($sid, $new_resource_id);

		return array(
			'status' => 'success',
			'resource_id' => $new_resource_id,
			'resource' => $resource,
			'links' => $this->ci->Editor_resource_datafile_model->get_by_resource($sid, $new_resource_id),
			'filename' => $artifact_basename,
			'documentation_path' => $dest_path,
		);
	}

	/**
	 * @param int $sid
	 * @param string $file_id
	 * @param array $datafile
	 * @param string $export_format
	 * @param array $file_modes
	 * @return string original|generate
	 */
	private function resolve_file_export_mode($sid, $file_id, array $datafile, $export_format, array $file_modes)
	{
		$export_format = strtolower(trim((string) $export_format));
		if (in_array($export_format, array('json', 'xpt'), true)) {
			return 'generate';
		}

		$requested = isset($file_modes[$file_id]) ? strtolower(trim((string) $file_modes[$file_id])) : 'generate';
		if ($requested !== 'original') {
			return 'generate';
		}

		if ($export_format === 'csv') {
			return $this->ci->Editor_datafile_model->get_file_csv_path($sid, $file_id) ? 'original' : 'generate';
		}

		$source_path = $this->ci->Editor_datafile_model->get_source_physical_path($sid, $file_id);
		if (!$source_path) {
			throw new Exception('Original source file not available for data file: ' . $file_id);
		}

		$ext = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
		if ($ext !== $export_format) {
			throw new Exception(
				'Original source for ' . $file_id . ' is .' . $ext . '; cannot use as .' . $export_format
			);
		}

		return 'original';
	}

	/**
	 * Copy uploaded source or working CSV into tmp for bundling.
	 *
	 * @param int $sid
	 * @param string $file_id
	 * @param array $datafile
	 * @param string $export_format
	 * @param string $tmp_folder_real
	 * @return array
	 */
	private function copy_original_to_tmp($sid, $file_id, array $datafile, $export_format, $tmp_folder_real)
	{
		$export_format = strtolower(trim((string) $export_format));
		$source_path = null;
		$file_export_version = null;

		if ($export_format === 'csv') {
			$source_path = $this->ci->Editor_datafile_model->get_file_csv_path($sid, $file_id);
			if (!$source_path || !is_file($source_path)) {
				throw new Exception('CSV not found for data file: ' . $file_id);
			}
		} else {
			$source_path = $this->ci->Editor_datafile_model->get_source_physical_path($sid, $file_id);
			if (!$source_path || !is_file($source_path)) {
				throw new Exception('Original source file not found for data file: ' . $file_id);
			}
			if ($export_format === 'dta') {
				$release = isset($datafile['source_format_version']) ? $datafile['source_format_version'] : null;
				$mapped = $this->ci->Editor_datafile_model->stata_version_from_release($release);
				if ($mapped !== null) {
					$file_export_version = (string) $mapped;
				} elseif ($release !== null && $release !== '') {
					$file_export_version = (string) $release;
				}
			}
		}

		$zip_entry_name = $this->ci->microdata_resource_mapper->zip_entry_name_for_datafile($datafile, $export_format);
		$dest_path = rtrim($tmp_folder_real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zip_entry_name;

		if (!@copy($source_path, $dest_path)) {
			throw new Exception('Failed to copy original file for data file: ' . $file_id);
		}

		return array(
			'file_id' => (string) $file_id,
			'path' => $dest_path,
			'zip_entry_name' => $zip_entry_name,
			'source_mode' => 'original',
			'export_format' => $export_format,
			'export_version' => $file_export_version,
			'job_id' => null,
		);
	}

	/**
	 * @param int $sid
	 * @param string $file_id
	 * @param array $datafile
	 * @param string $export_format
	 * @param string|int|null $export_version
	 * @param string $tmp_folder_real
	 * @param int $max_wait
	 * @return array file_id, path, zip_entry_name, job_id
	 */
	private function export_datafile_to_tmp($sid, $file_id, array $datafile, $export_format, $export_version, $tmp_folder_real, $max_wait)
	{
		if (!$this->ci->Editor_datafile_model->get_file_csv_path($sid, $file_id)) {
			throw new Exception('CSV not found for data file: ' . $file_id);
		}

		if (in_array($export_format, array('dta', 'sav'), true)) {
			$validation = $this->ci->datafile_export->validate_datafile_export($sid, $file_id, $export_format, true);
			if (empty($validation['valid'])) {
				$msg = 'Export validation failed for ' . $file_id;
				if (!empty($validation['errors'][0]['error'])) {
					$msg = $validation['errors'][0]['error'];
				}
				throw new Exception($msg);
			}
		}

		$export_options = null;
		if ($export_format === 'dta' && $export_version !== null && $export_version !== '') {
			$export_options = array('version' => (int) $export_version);
		}

		$api_response = $this->ci->datautils->export_datafile_queue($sid, $file_id, $export_format, $export_options);
		$status_code = isset($api_response['status_code']) ? (int) $api_response['status_code'] : 0;
		$response = isset($api_response['response']) ? $api_response['response'] : array();

		if ($status_code !== 200 && $status_code !== 202) {
			$message = isset($response['message']) ? $response['message'] : 'Failed to queue export';
			throw new Exception($message);
		}

		$job_id = isset($response['job_id']) ? $response['job_id'] : null;
		if (!$job_id) {
			throw new Exception('FastAPI did not return job_id for export');
		}

		$poll = $this->ci->datautils->poll_fastapi_job($job_id, $max_wait, 3);
		if ($poll['status'] !== 'done') {
			$message = isset($poll['message']) ? $poll['message'] : 'Export job did not complete';
			throw new Exception($message . ' (' . $file_id . ')');
		}

		$basename = $this->ci->microdata_resource_mapper->tmp_export_basename($datafile, $export_format);
		$path = $tmp_folder_real . DIRECTORY_SEPARATOR . $basename;

		if (!is_file($path)) {
			$alt = $this->ci->microdata_resource_mapper->zip_entry_name_for_datafile($datafile, $export_format);
			$path_alt = $tmp_folder_real . DIRECTORY_SEPARATOR . $alt;
			if (is_file($path_alt)) {
				$path = $path_alt;
				$basename = $alt;
			}
		}

		if (!is_file($path)) {
			throw new Exception('Exported file not found in tmp: ' . $basename);
		}

		return array(
			'file_id' => (string) $file_id,
			'path' => $path,
			'zip_entry_name' => $this->ci->microdata_resource_mapper->zip_entry_name_for_datafile($datafile, $export_format),
			'source_mode' => 'generated',
			'export_format' => strtolower((string) $export_format),
			'export_version' => ($export_format === 'dta' && $export_version !== null && $export_version !== '')
				? (string) (int) $export_version
				: null,
			'job_id' => $job_id,
		);
	}

	/**
	 * Write companion dictionary CSV for a data file export (CSV bundles only).
	 *
	 * @param int $sid
	 * @param string $file_id
	 * @param array $datafile
	 * @param string $tmp_folder
	 * @param array $exported
	 * @return array
	 */
	private function attach_dictionary_csv_export($sid, $file_id, array $datafile, $tmp_folder, array $exported)
	{
		$dict_name = $this->ci->data_dictionary_csv->dictionary_filename_for_datafile($datafile);
		$dict_path = rtrim($tmp_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dict_name;
		$this->ci->data_dictionary_csv->export_csv_to_path($sid, $file_id, $dict_path);
		$exported['dictionary_path'] = $dict_path;
		$exported['dictionary_zip_entry'] = $dict_name;
		return $exported;
	}

	/**
	 * @param string $tmp_folder
	 * @param array $exported_files
	 * @param string|null $zip_basename
	 */
	private function cleanup_tmp_files($tmp_folder, array $exported_files, $zip_basename = null)
	{
		foreach ($exported_files as $exp) {
			if (!empty($exp['path']) && is_file($exp['path'])) {
				@unlink($exp['path']);
			}
			if (!empty($exp['dictionary_path']) && is_file($exp['dictionary_path'])) {
				@unlink($exp['dictionary_path']);
			}
		}
		if ($zip_basename !== null) {
			$zip_path = rtrim($tmp_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($zip_basename);
			if (is_file($zip_path)) {
				@unlink($zip_path);
			}
		}
	}
}
