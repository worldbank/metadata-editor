<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Zip exported data files in project data/tmp and compute NADA-friendly archive names.
 */
class Datafile_export_zip
{
	/** @var CI_Controller */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
	}

	/**
	 * @param int $sid
	 * @param array $files_to_add [['full' => abs path, 'entry' => basename], ...]
	 * @param array $options export_format, export_version (optional)
	 * @return string zip basename including .zip
	 */
	public function compute_zip_filename($sid, array $files_to_add, array $options = array())
	{
		$ext_to_format = array(
			'dta' => 'dta',
			'csv' => 'csv',
			'sav' => 'sav',
			'json' => 'json',
			'xpt' => 'xpt',
		);
		$format_to_label = array(
			'dta' => 'DTA',
			'csv' => 'CSV',
			'sav' => 'SPSS',
			'json' => 'JSON',
			'xpt' => 'SAS',
		);

		$formats = array();
		foreach ($files_to_add as $f) {
			$ext = strtolower(pathinfo($f['entry'], PATHINFO_EXTENSION));
			if (isset($ext_to_format[$ext])) {
				$formats[$ext_to_format[$ext]] = true;
			}
		}
		$formats = array_keys($formats);
		$format_count = count($formats);

		$idno = $this->ci->Editor_model->get_project_primary_idno($sid);
		if ($idno === null || $idno === '') {
			$idno = 'batch_export_' . date('Ymd_His');
		} else {
			$idno = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $idno);
			if ($idno === '') {
				$idno = 'batch_export_' . date('Ymd_His');
			}
		}

		$export_version = null;
		if (isset($options['export_version']) && $options['export_version'] !== null && $options['export_version'] !== '') {
			$v = (int) $options['export_version'];
			if ($v >= 8 && $v <= 15) {
				$export_version = $v;
			}
		}

		if ($format_count > 1) {
			return $idno . '.zip';
		}
		if ($format_count === 1) {
			$format = $formats[0];
			if ($format === 'dta' && $export_version !== null) {
				return $idno . '_STATA' . $export_version . '.zip';
			}
			return $idno . '_' . $format_to_label[$format] . '.zip';
		}

		if (!empty($options['export_format'])) {
			$fmt = strtolower((string) $options['export_format']);
			if ($fmt === 'dta' && $export_version !== null) {
				return $idno . '_STATA' . $export_version . '.zip';
			}
			if (isset($format_to_label[$fmt])) {
				return $idno . '_' . $format_to_label[$fmt] . '.zip';
			}
		}

		return $idno . '.zip';
	}

	/**
	 * @param string $tmp_folder_real Absolute path to data/tmp
	 * @param array $files_to_add
	 * @param string $zip_filename Basename only
	 * @return string Absolute path to created zip
	 */
	public function create_zip($tmp_folder_real, array $files_to_add, $zip_filename)
	{
		$zip_filename = basename($zip_filename);
		if ($zip_filename === '' || strpos($zip_filename, '..') !== false) {
			throw new Exception('Invalid zip filename');
		}

		$zip_path_full = rtrim($tmp_folder_real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zip_filename;

		if (extension_loaded('zip')) {
			$zip = new ZipArchive();
			if ($zip->open($zip_path_full, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
				throw new Exception('Could not create zip file');
			}
			foreach ($files_to_add as $f) {
				$zip->addFile($f['full'], $f['entry']);
			}
			$zip->close();
		} else {
			$zipFile = new \PhpZip\ZipFile();
			try {
				foreach ($files_to_add as $f) {
					$zipFile->addFile($f['full'], $f['entry']);
				}
				$zipFile->saveAsFile($zip_path_full)->close();
			} catch (\PhpZip\Exception\ZipException $e) {
				throw new Exception('Could not create zip file: ' . $e->getMessage());
			} finally {
				$zipFile->close();
			}
		}

		return $zip_path_full;
	}

	/**
	 * @param int $sid
	 * @return string Absolute path to data/tmp (created if missing)
	 */
	public function ensure_tmp_folder($sid)
	{
		$project_folder = $this->ci->Editor_model->get_project_folder($sid);
		if (!$project_folder) {
			throw new Exception('Project folder not found');
		}

		$tmp_folder = rtrim($project_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
		if (!is_dir($tmp_folder)) {
			@mkdir($tmp_folder, 0777, true);
		}

		$real = realpath($tmp_folder);
		if ($real === false || !is_dir($real)) {
			throw new Exception('Project tmp folder not found');
		}

		return $real;
	}

	/**
	 * @param int $sid
	 * @return string Absolute path to documentation (created if missing)
	 */
	public function ensure_documentation_folder($sid)
	{
		$project_folder = $this->ci->Editor_model->get_project_folder($sid);
		if (!$project_folder) {
			throw new Exception('Project folder not found');
		}

		$doc_folder = rtrim($project_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'documentation' . DIRECTORY_SEPARATOR;
		if (!is_dir($doc_folder)) {
			@mkdir($doc_folder, 0777, true);
		}

		if (!is_dir($doc_folder)) {
			throw new Exception('Documentation folder not found');
		}

		return $doc_folder;
	}
}
