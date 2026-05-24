<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 *
 * Project package import and export
 *
 */
class ProjectPackage
{
	const PACKAGE_FORMAT = 'metadata-editor-project/1';

	const INDICATOR_DATA_FILENAME = 'indicator_data.csv';

	const LEGACY_INDICATOR_UPLOAD_FILENAME = 'indicator_staging_upload.csv';

	const INDICATOR_FILE_ID = 'INDICATOR_DATA';

	/** @var CI_Controller */
	private $ci;

	function __construct()
	{
		log_message('debug', "ProjectPackage Class Initialized.");
		$this->ci =& get_instance();

		$this->ci->load->model("Editor_model");
		$this->ci->load->model("Editor_resource_model");
		$this->ci->load->model("Collection_model");
		$this->ci->load->library("Project_json_writer");
	}


	/**
	 * Full export pipeline: metadata stages, manifest, allowlisted zip.
	 *
	 * @param int   $sid
	 * @param array $options Optional export options (e.g. json_options)
	 * @return string Absolute path to zip file
	 */
	function prepare_package($sid, $options = array())
	{
		set_time_limit(0);

		try {
			$this->run_export_stages($sid, $options);
			return $this->generate_zip($sid, $options);
		}
		catch (Exception $e) {
			throw new Exception("Failed to export package: " . $e->getMessage());
		}
	}


	/**
	 * Write info.json and create zip from the current project folder state.
	 *
	 * @param int   $sid
	 * @param array $options
	 * @return string Absolute path to zip file
	 */
	function generate_zip($sid, $options = array())
	{
		set_time_limit(0);

		$project = $this->ci->Editor_model->get_basic_info($sid);
		$project_folder = $this->ci->Editor_model->get_project_folder($sid);

		if (!$project) {
			throw new Exception("Project not found");
		}
		if (!$project_folder || !file_exists($project_folder)) {
			throw new Exception("Project folder not found");
		}

		if ($this->is_indicator_type($project['type'])) {
			try {
				$this->ci->load->model('Indicator_dsd_model');
				$user_id = isset($options['user_id']) ? (int) $options['user_id'] : null;
				$this->ci->Indicator_dsd_model->ensure_indicator_data_csv($sid, $user_id);
			} catch (Exception $e) {
				log_message('error', 'ProjectPackage: ensure indicator_data.csv failed for sid ' . $sid . ': ' . $e->getMessage());
			}
		}

		$basename = $this->package_basename($project);
		$zip_path = $project_folder . '/' . $basename . '.zip';

		if (file_exists($zip_path)) {
			unlink($zip_path);
		}

		$file_list = $this->build_file_list($sid, $options);
		$this->create_info_json($sid, $file_list, $options);

		$info_path = 'info.json';
		if (!in_array($info_path, $file_list, true)) {
			$file_list[] = $info_path;
		}

		$this->create_zip_from_list($sid, $file_list, $zip_path);

		return $zip_path;
	}


	/**
	 * Run all export stages appropriate for the project type.
	 *
	 * @param int   $sid
	 * @param array $options
	 * @return array Stage name => relative path(s)
	 */
	function run_export_stages($sid, $options = array())
	{
		$project = $this->ci->Editor_model->get_basic_info($sid);
		if (!$project) {
			throw new Exception("Project not found");
		}

		$output = array();
		foreach ($this->get_export_stages($project['type']) as $stage) {
			$output[$stage] = $this->run_stage($sid, $stage, $options);
		}

		return $output;
	}


	/**
	 * Run a single export stage.
	 *
	 * @param int    $sid
	 * @param string $stage json|ddi|resources_json|resources_rdf
	 * @param array  $options
	 * @return string|null Relative path created, or null when stage skipped
	 */
	function run_stage($sid, $stage, $options = array())
	{
		set_time_limit(0);

		$project = $this->ci->Editor_model->get_basic_info($sid);
		if (!$project) {
			throw new Exception("Project not found");
		}

		switch ($stage) {
			case 'json':
				$json_options = isset($options['json_options']) ? $options['json_options'] : array();
				$this->ci->project_json_writer->generate_project_json($sid, $json_options);
				return $this->metadata_path($project, 'json');

			case 'ddi':
				if (!$this->is_microdata_type($project['type'])) {
					return null;
				}
				$this->ci->Editor_model->generate_project_ddi($sid);
				return $this->metadata_path($project, 'xml');

			case 'resources_json':
				$this->ci->Editor_resource_model->write_json($sid);
				return $this->metadata_path($project, 'rdf.json');

			case 'resources_rdf':
				$this->ci->Editor_resource_model->write_rdf($sid);
				return $this->metadata_path($project, 'rdf');

			default:
				throw new Exception("Unknown export stage: " . $stage);
		}
	}


	/**
	 * Build allowlisted relative paths for the package (excludes info.json).
	 *
	 * @param int   $sid
	 * @param array $options
	 * @return string[]
	 */
	function build_file_list($sid, $options = array())
	{
		$project = $this->ci->Editor_model->get_basic_info($sid);
		$project_folder = $this->ci->Editor_model->get_project_folder($sid);

		if (!$project || !$project_folder || !file_exists($project_folder)) {
			throw new Exception("Project folder not found");
		}

		$basename = $this->package_basename($project);
		$paths = array();

		$this->append_existing_paths($paths, $this->resolve_core_metadata_paths($project, $basename, $project_folder));
		$this->append_existing_paths($paths, $this->resolve_thumbnail_path($sid, $project_folder));
		$this->append_existing_paths($paths, $this->resolve_microdata_paths($sid, $project, $project_folder));
		$this->append_existing_paths($paths, $this->resolve_indicator_data_path($project, $project_folder));
		$this->append_existing_paths($paths, $this->resolve_resource_attachment_paths($sid, $project_folder));
		$this->append_existing_paths($paths, $this->resolve_geospatial_paths($sid, $project_folder));

		$paths = $this->filter_package_paths($paths, $basename);

		return array_values($paths);
	}


	/**
	 * @param int          $sid
	 * @param string[]     $file_list Relative paths (without info.json)
	 * @param array        $options
	 */
	function create_info_json($sid, $file_list, $options = array())
	{
		$project = $this->ci->Editor_model->get_basic_info($sid);
		if (!$project) {
			throw new Exception("Project not found");
		}

		$project_folder = $this->ci->Editor_model->get_project_folder($sid);
		$basename = $this->package_basename($project);
		$file_set = array_flip($file_list);

		$exported_at = date('c');
		$info = array(
			'package_format' => self::PACKAGE_FORMAT,
			'exported_at' => $exported_at,
			'created' => $exported_at,
			'idno' => $project['idno'],
			'type' => $project['type'],
			'thumbnail' => $this->ci->Editor_model->get_thumbnail($sid),
			'json_file' => isset($file_set[$basename . '.json']) ? $basename . '.json' : null,
			'xml_file' => isset($file_set[$basename . '.xml']) ? $basename . '.xml' : null,
			'rdf_json_file' => isset($file_set[$basename . '.rdf.json']) ? $basename . '.rdf.json' : null,
			'rdf_xml_file' => isset($file_set[$basename . '.rdf']) ? $basename . '.rdf' : null,
			'collections' => $this->ci->Collection_model->get_collection_by_project($sid),
		);

		$indicator_data_path = $this->resolve_indicator_data_path($project, $project_folder);
		if (!empty($indicator_data_path)) {
			$info['indicator_data_file'] = $indicator_data_path[0];
			$info['indicator_data_imported'] = $this->indicator_data_imported($sid);
		}
		else {
			$info['indicator_data_file'] = null;
			$info['indicator_data_imported'] = false;
		}

		file_put_contents(
			$project_folder . '/info.json',
			json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
		);
	}


	/**
	 * @param int      $sid
	 * @param string[] $file_list Relative paths inside the project folder
	 * @param string   $zip_path  Absolute path for output zip
	 */
	function create_zip_from_list($sid, $file_list, $zip_path)
	{
		$project_folder = $this->ci->Editor_model->get_project_folder($sid);
		if (!$project_folder || !file_exists($project_folder)) {
			throw new Exception("Project folder not found");
		}

		$zipFile = new \PhpZip\ZipFile();
		try {
			foreach ($file_list as $relative_path) {
				$relative_path = $this->normalize_package_path($relative_path);
				$full_path = $project_folder . '/' . $relative_path;

				if (!file_exists($full_path) || !is_file($full_path)) {
					throw new Exception("Package file not found: " . $relative_path);
				}

				$zipFile->addFile($full_path, $relative_path);
			}

			$zipFile->saveAsFile($zip_path);
		}
		catch (\PhpZip\Exception\ZipException $e) {
			throw new Exception("Failed to generate zip file: " . $e->getMessage());
		}
		finally {
			$zipFile->close();
		}
	}


	/**
	 * @param array $project Row from get_basic_info
	 * @return string
	 */
	function package_basename($project)
	{
		$idno = isset($project['idno']) ? trim((string) $project['idno']) : '';
		if ($idno !== '') {
			return $idno;
		}

		$id = isset($project['id']) ? $project['id'] : null;
		if ($id === null && isset($project['sid'])) {
			$id = $project['sid'];
		}

		return nada_hash($id);
	}


	/**
	 * @param string $type
	 * @return string[]
	 */
	function get_export_stages($type)
	{
		$stages = array('json', 'resources_json', 'resources_rdf');
		if ($this->is_microdata_type($type)) {
			array_splice($stages, 1, 0, array('ddi'));
		}

		return $stages;
	}


	private function is_microdata_type($type)
	{
		return in_array($type, array('survey', 'microdata'), true);
	}


	private function is_indicator_type($type)
	{
		return in_array($type, array('indicator', 'timeseries', 'timeseries-db', 'indicator-db'), true);
	}


	private function metadata_path($project, $extension)
	{
		return $this->package_basename($project) . '.' . $extension;
	}


	private function resolve_core_metadata_paths($project, $basename, $project_folder)
	{
		$candidates = array(
			$basename . '.json',
			$basename . '.rdf.json',
		);

		if ($this->is_microdata_type($project['type'])) {
			$candidates[] = $basename . '.xml';
		}

		$candidates[] = $basename . '.rdf';

		$paths = array();
		foreach ($candidates as $relative_path) {
			if (is_file($project_folder . '/' . $relative_path)) {
				$paths[] = $relative_path;
			}
		}

		return $paths;
	}


	private function resolve_thumbnail_path($sid, $project_folder)
	{
		$thumbnail = $this->ci->Editor_model->get_thumbnail($sid);
		if (!$thumbnail) {
			return array();
		}

		$relative_path = $this->normalize_package_path($thumbnail);
		if ($relative_path === '' || !is_file($project_folder . '/' . $relative_path)) {
			return array();
		}

		return array($relative_path);
	}


	private function resolve_microdata_paths($sid, $project, $project_folder)
	{
		if (!$this->is_microdata_type($project['type'])) {
			return array();
		}

		$this->ci->load->model('Editor_datafile_model');
		$data_files = $this->ci->Editor_datafile_model->select_all($sid);
		$paths = array();

		foreach ($data_files as $data_file) {
			if (!empty($data_file['file_id']) && $data_file['file_id'] === self::INDICATOR_FILE_ID) {
				continue;
			}

			$physical = isset($data_file['file_physical_name']) ? trim((string) $data_file['file_physical_name']) : '';
			if ($physical === '') {
				continue;
			}

			$relative_path = 'data/' . $this->normalize_package_path($physical);
			if (is_file($project_folder . '/' . $relative_path)) {
				$paths[] = $relative_path;
			}
		}

		return $paths;
	}


	private function resolve_indicator_data_path($project, $project_folder)
	{
		if (!$this->is_indicator_type($project['type'])) {
			return array();
		}

		$candidates = array(
			'data/' . self::INDICATOR_DATA_FILENAME,
			'data/' . self::LEGACY_INDICATOR_UPLOAD_FILENAME,
		);

		foreach ($candidates as $relative_path) {
			if (is_file($project_folder . '/' . $relative_path)) {
				return array($relative_path);
			}
		}

		return array();
	}


	private function resolve_resource_attachment_paths($sid, $project_folder)
	{
		$this->ci->load->helper('file');
		$resources = $this->ci->Editor_resource_model->select_all($sid);
		$paths = array();

		foreach ($resources as $resource) {
			if (empty($resource['filename']) || is_url($resource['filename'])) {
				continue;
			}

			$filename = $this->normalize_package_path(basename($resource['filename']));
			if ($filename === '') {
				continue;
			}

			foreach (array('documentation', 'data') as $folder) {
				$relative_path = $folder . '/' . $filename;
				if (is_file($project_folder . '/' . $relative_path)) {
					$paths[] = $relative_path;
					break;
				}
			}
		}

		return $paths;
	}


	private function resolve_geospatial_paths($sid, $project_folder)
	{
		$this->ci->load->model('Geospatial_features_model');
		$features = $this->ci->Geospatial_features_model->select_by_project($sid);
		$paths = array();
		$seen = array();

		foreach ($features as $feature) {
			foreach (array('file_name', 'data_file') as $field) {
				if (empty($feature[$field])) {
					continue;
				}

				$name = $this->normalize_package_path(basename((string) $feature[$field]));
				if ($name === '' || isset($seen[$name])) {
					continue;
				}

				$relative_path = 'geospatial/' . $name;
				if (is_file($project_folder . '/' . $relative_path)) {
					$paths[] = $relative_path;
					$seen[$name] = true;
				}
			}
		}

		return $paths;
	}


	private function indicator_data_imported($sid)
	{
		if (!$this->ci->db->table_exists('editor_project_dsd')) {
			return false;
		}

		$this->ci->load->model('Editor_project_dsd_model');
		$binding = $this->ci->Editor_project_dsd_model->get_by_sid($sid);

		return is_array($binding) && !empty($binding['has_published_data']);
	}


	private function append_existing_paths(&$paths, $new_paths)
	{
		foreach ($new_paths as $path) {
			$path = $this->normalize_package_path($path);
			if ($path === '') {
				continue;
			}
			if (!in_array($path, $paths, true)) {
				$paths[] = $path;
			}
		}
	}


	private function filter_package_paths($paths, $basename)
	{
		$filtered = array();

		foreach ($paths as $path) {
			$path = $this->normalize_package_path($path);
			if ($path === '' || $this->is_excluded_package_path($path, $basename)) {
				continue;
			}
			if (!in_array($path, $filtered, true)) {
				$filtered[] = $path;
			}
		}

		return $filtered;
	}


	private function is_excluded_package_path($path, $basename)
	{
		if ($path === $basename . '.zip' || substr($path, -4) === '.zip') {
			return true;
		}

		if (strpos($path, 'data/tmp/') === 0 || $path === 'data/tmp') {
			return true;
		}

		if (strpos($path, 'tmp/') === 0 || $path === 'tmp') {
			return true;
		}

		$basename_only = basename($path);
		if ($basename_only === 'indicator_staging_import.csv') {
			return true;
		}

		if ($basename_only === '.DS_Store' || $basename_only === 'Thumbs.db') {
			return true;
		}

		if (strpos($path, '__MACOSX/') === 0) {
			return true;
		}

		return false;
	}


	private function normalize_package_path($path)
	{
		$path = str_replace('\\', '/', (string) $path);
		$path = ltrim($path, '/');

		if ($path === '' || strpos($path, '../') !== false || strpos($path, '/..') !== false) {
			return '';
		}

		return $path;
	}
}
