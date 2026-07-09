<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Attach an original source file (Stata/SPSS/CSV) to an existing datafile
 * for legacy projects where the source was deleted after CSV conversion.
 *
 * Validates column names against DB variables via FastAPI /name-labels.
 * Does not regenerate CSV or change variables by default.
 */
class Datafile_attach_source
{
	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->library('DataUtils');
		$this->ci->load->model('Editor_datafile_model');
		$this->ci->load->model('Editor_variable_model');
		$this->ci->load->model('Editor_model');
	}

	/**
	 * @param int $sid
	 * @param string $file_id
	 * @param string $uploaded_path Absolute path to temp upload
	 * @param int|null $user_id
	 * @param bool $allow_extra_columns If true, extra columns in file are OK (DB vars must still be present)
	 * @return array
	 */
	function attach($sid, $file_id, $uploaded_path, $user_id = null, $allow_extra_columns = false, $original_client_name = null)
	{
		if (!$uploaded_path || !file_exists($uploaded_path)) {
			throw new Exception("Uploaded file not found");
		}

		$datafile = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);
		if (!$datafile) {
			throw new Exception("Data file not found: " . $file_id);
		}

		$file_ext = strtolower(pathinfo($uploaded_path, PATHINFO_EXTENSION));
		if (!in_array($file_ext, array('dta', 'sav', 'csv'), true)) {
			throw new Exception("Unsupported source format: ." . $file_ext . " (allowed: dta, sav, csv)");
		}

		// Attach is for restoring Stata/SPSS originals; do not replace the working CSV.
		if ($file_ext === 'csv') {
			throw new Exception("Attach original accepts Stata (.dta) or SPSS (.sav) files only. Use Replace file to update CSV.");
		}

		$expected = $this->ci->Editor_variable_model->get_variable_names_by_file($sid, $file_id);
		if (!$expected || !is_array($expected) || count($expected) === 0) {
			throw new Exception("No variables found for data file; cannot validate source columns");
		}

		$name_labels = $this->ci->datautils->get_file_name_labels($uploaded_path, array(
			'expected_columns' => $expected,
			'include_file_info' => true,
			'include_comparison' => true,
			'columns_only' => true,
		));

		$comparison = isset($name_labels['comparison']) && is_array($name_labels['comparison'])
			? $name_labels['comparison']
			: null;
		$file_info = isset($name_labels['file_info']) && is_array($name_labels['file_info'])
			? $name_labels['file_info']
			: array('format' => $file_ext, 'format_version' => null);

		if ($comparison) {
			$missing = isset($comparison['missing_in_file']) ? $comparison['missing_in_file'] : array();
			$extra = isset($comparison['extra_in_file']) ? $comparison['extra_in_file'] : array();
			if (count($missing) > 0) {
				throw new Exception(
					"Source file is missing required columns: " . substr(implode(", ", $missing), 0, 200)
				);
			}
			if (!$allow_extra_columns && count($extra) > 0) {
				throw new Exception(
					"Source file has unexpected columns: " . substr(implode(", ", $extra), 0, 200)
				);
			}
		} else {
			// Fallback without comparison payload
			if (!isset($name_labels['variables']) || !is_array($name_labels['variables'])) {
				throw new Exception("No variables found in source file");
			}
			$imported = array_column($name_labels['variables'], 'name');
			$missing = array_diff($expected, $imported);
			if (count($missing) > 0) {
				throw new Exception(
					"Source file is missing required columns: " . substr(implode(", ", $missing), 0, 200)
				);
			}
			if (!$allow_extra_columns) {
				$extra = array_diff($imported, $expected);
				if (count($extra) > 0) {
					throw new Exception(
						"Source file has unexpected columns: " . substr(implode(", ", $extra), 0, 200)
					);
				}
			}
		}

		$data_folder = $this->ci->Editor_model->get_project_folder($sid) . '/data/';
		if (!file_exists($data_folder)) {
			mkdir($data_folder, 0777, true);
		}

		$target_basename = $datafile['file_name'] . '.' . $file_ext;
		$target_filepath = $data_folder . $target_basename;

		// If an older source with a different extension exists, leave CSV alone;
		// only replace the physical source path we are about to write.
		$old_physical = isset($datafile['file_physical_name']) ? $datafile['file_physical_name'] : '';
		if ($old_physical !== '' && strtolower(pathinfo($old_physical, PATHINFO_EXTENSION)) !== 'csv') {
			$old_path = $data_folder . $old_physical;
			if (file_exists($old_path) && is_file($old_path) && realpath($old_path) !== realpath($target_filepath)) {
				@unlink($old_path);
			}
		}

		if (!rename($uploaded_path, $target_filepath)) {
			if (!copy($uploaded_path, $target_filepath)) {
				throw new Exception("Failed to store source file");
			}
			@unlink($uploaded_path);
		}

		if ($original_client_name === null || $original_client_name === '') {
			$original_client_name = basename($uploaded_path);
		}
		$source_update = $this->ci->Editor_datafile_model->build_source_fields_from_path(
			$target_filepath,
			$original_client_name
		);
		$source_update = array_merge(
			$source_update,
			$this->ci->Editor_datafile_model->source_fields_from_file_info($file_info)
		);
		$now = date('U');
		$source_update['source_status'] = 'present';
		$source_update['source_attached_at'] = $now;
		if ($user_id !== null && $user_id !== '') {
			$source_update['source_attached_by'] = (int) $user_id;
		}

		$update_fields = array_merge(array(
			'file_physical_name' => $target_basename,
		), $source_update);

		$this->ci->Editor_datafile_model->update($datafile['id'], $update_fields);

		$updated = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);

		return array(
			'status' => 'success',
			'datafile' => $updated,
			'file_info' => $file_info,
			'comparison' => $comparison,
			'target_filepath' => $target_filepath,
			'message' => 'Source file attached; working CSV and variables unchanged',
		);
	}
}
