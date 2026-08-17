<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 
 * Import project package from ZIP file
 * 
 * Handles:
 * - ZIP extraction
 * - Reading info.json manifest
 * - Metadata import (JSON/XML with fallback)
 * - Resource import (RDF JSON/XML with fallback)
 * - Thumbnail and collection management
 * 
 */
class ImportPackage
{
    private $ci;

    /**
     * Constructor
     */
    function __construct()
    {
        log_message('debug', "ImportPackage Class Initialized.");
        $this->ci =& get_instance();

        $this->ci->load->model("Editor_model");
        $this->ci->load->model("Editor_resource_model");
        $this->ci->load->model("Editor_datafile_model");
        $this->ci->load->library("ImportJsonMetadata");
    }

    /**
     * 
     * Import a complete project package from ZIP file
     * 
     * @param int $sid - Project ID
     * @param string $zip_path - Path to ZIP file
     * @return array - Import results
     * 
     */
    public function import($sid, $zip_path, $options = array())
    {
        $skip_idno_validation = !empty($options['skip_idno_validation']);
        $preserve_project_idno = !empty($options['preserve_project_idno']);

        // Validate ZIP file exists
        if (!file_exists($zip_path)){
            throw new Exception("ZIP file not found: " . $zip_path);
        }

        // Extract ZIP to project folder
        $project_path = $this->extract_zip($sid, $zip_path);

        // Read and validate info.json
        $project_info = $this->read_project_info($project_path);

        // Validate IDNO - check if it already exists (for different project)
        if (!$skip_idno_validation) {
            $this->validate_idno($sid, $project_info);
        }

        // Import metadata (JSON or XML)
        $metadata_import_options = array();
        if (!empty($options['metadata_import_options']) && is_array($options['metadata_import_options'])) {
            $metadata_import_options = $options['metadata_import_options'];
        }
        $metadata_result = $this->import_metadata($sid, $project_path, $project_info, $metadata_import_options);
        $metadata_stats = array();
        if (isset($this->ci->importjsonmetadata) && is_object($this->ci->importjsonmetadata)
            && method_exists($this->ci->importjsonmetadata, 'get_import_stats')) {
            $metadata_stats = $this->ci->importjsonmetadata->get_import_stats();
        }

        $user_id = null;
        if (isset($metadata_import_options['created_by'])) {
            $user_id = $metadata_import_options['created_by'];
        } elseif (isset($metadata_import_options['changed_by'])) {
            $user_id = $metadata_import_options['changed_by'];
        }

        // Link data files for microdata projects; generate working CSV when only native source is present
        $link_result = $this->link_data_files($sid, $project_path, array(
            'enqueue_csv' => true,
            'user_id' => $user_id,
        ));

        // Import external resources (RDF JSON or XML)
        $resources_imported = $this->import_resources($sid, $project_path, $project_info);

        // Set thumbnail if available
        $thumbnail = $this->set_thumbnail($sid, $project_info);

        // Update project IDNO if provided in package (skip when preserving generated idno)
        if (!$preserve_project_idno && !empty($project_info['idno'])){
            $this->ci->Editor_model->set_project_options($sid, array(
                'idno' => $project_info['idno']
            ));
            log_message('info', "Updated project IDNO to: " . $project_info['idno']);
        }

        $import_report = $this->build_zip_import_report(
            $sid,
            $project_path,
            $project_info,
            $metadata_result,
            $metadata_stats,
            $link_result,
            $resources_imported,
            $thumbnail,
            $user_id
        );
        try {
            $this->save_import_report($sid, $import_report);
        }
        catch (Exception $e) {
            log_message('error', 'Failed to write import report: ' . $e->getMessage());
        }

        return array(
            'project_imported' => $metadata_result,
            'resources_imported' => $resources_imported,
            'thumbnail' => $thumbnail,
            'project_info' => $project_info,
            'import_report' => $import_report,
        );
    }


    /**
     * Read info.json from a ZIP package without extracting the archive.
     *
     * @param string $zip_path
     * @return array Parsed info.json manifest
     */
    public function peek_info_json($zip_path)
    {
        if (!file_exists($zip_path)) {
            throw new Exception("ZIP file not found: " . $zip_path);
        }

        $this->validate_zip_entries($zip_path);

        $zipFile = new \PhpZip\ZipFile();
        try {
            $zipFile->openFile($zip_path);
            $info_content = $zipFile->getEntryContents('info.json');
        }
        catch (\PhpZip\Exception\ZipException $e) {
            throw new Exception("Project info.json not found in package archive");
        }
        finally {
            $zipFile->close();
        }

        if ($info_content === false || $info_content === '') {
            throw new Exception("Project info.json not found in package archive");
        }

        $project_info = json_decode($info_content, true);
        if ($project_info === null) {
            throw new Exception("Invalid JSON in info.json: " . json_last_error_msg());
        }

        return $project_info;
    }


    /**
     * Summarize package manifest fields used by import preview.
     *
     * @param array $project_info
     * @return array
     */
    public function summarize_package_info($project_info)
    {
        return array(
            'idno' => isset($project_info['idno']) ? trim((string) $project_info['idno']) : '',
            'type' => isset($project_info['type']) ? trim((string) $project_info['type']) : '',
            'title' => isset($project_info['title']) ? trim((string) $project_info['title']) : '',
        );
    }


    /**
     * 
     * Extract ZIP file to project folder
     * 
     * @param int $sid - Project ID
     * @param string $zip_path - Path to ZIP file
     * @return string - Extracted project folder path
     * 
     */
    private function extract_zip($sid, $zip_path)
    {
        $project_folder_path = $this->ci->Editor_model->get_project_folder($sid);

        if (!file_exists($project_folder_path)){
            throw new Exception("Project folder not found: " . $project_folder_path);
        }

        $this->validate_zip_entries($zip_path);

        // Extract ZIP using PhpZip
        $zipFile = new \PhpZip\ZipFile();
        try {
            $zipFile
                ->openFile($zip_path)
                ->extractTo($project_folder_path);
        }
        catch(\PhpZip\Exception\ZipException $e){
            throw new Exception("Failed to extract ZIP file: " . $e->getMessage());
        }
        finally {
            $zipFile->close();
        }

        return $project_folder_path;
    }


    /**
     * Normalize a package-relative path from info.json.
     * Rejects absolute paths and parent-directory segments.
     *
     * @param string $relative
     * @return string Unix-style relative path
     */
    private function normalize_package_relative_path($relative)
    {
        if (!is_string($relative)) {
            throw new Exception("Unsafe path in package info.json");
        }

        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '') {
            throw new Exception("Unsafe path in package info.json");
        }

        if ($relative[0] === '/' || preg_match('#^[A-Za-z]:/#', $relative)) {
            throw new Exception("Unsafe path in package info.json: " . $relative);
        }

        $parts = explode('/', $relative);
        $normalized = array();
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new Exception("Unsafe path in package info.json: " . $relative);
            }
            $normalized[] = $part;
        }

        if (empty($normalized)) {
            throw new Exception("Unsafe path in package info.json: " . $relative);
        }

        return implode('/', $normalized);
    }

    /**
     * Resolve a package-relative file so it cannot escape $base_dir.
     * Missing files return null (callers may fall back). Unsafe paths throw.
     *
     * @param string $base_dir Extracted project folder
     * @param string $relative Path from info.json
     * @return string|null Absolute path if the file exists inside $base_dir
     */
    private function resolve_package_file($base_dir, $relative)
    {
        if (!is_string($relative) || trim($relative) === '') {
            return null;
        }

        $relative = $this->normalize_package_relative_path($relative);

        $base_real = realpath($base_dir);
        if ($base_real === false) {
            throw new Exception("Project folder not found");
        }

        $candidate = $base_real . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            return null;
        }

        $prefix = rtrim(str_replace('\\', '/', $base_real), '/') . '/';
        $resolved_unix = str_replace('\\', '/', $resolved);
        if (strpos($resolved_unix, $prefix) !== 0) {
            throw new Exception("Unsafe path in package info.json: " . $relative);
        }

        return $resolved;
    }

    /**
     * Reject unsafe ZIP entry paths before extraction.
     *
     * @param string $zip_path
     */
    private function validate_zip_entries($zip_path)
    {
        $zipFile = new \PhpZip\ZipFile();
        try {
            $zipFile->openFile($zip_path);
            $entries = $zipFile->getListFiles();

            foreach ($entries as $entry_name) {
                $entry_name = str_replace('\\', '/', (string) $entry_name);

                if ($entry_name === '' || $entry_name[0] === '/' || preg_match('/^[A-Za-z]:\\//', $entry_name)) {
                    throw new Exception("Unsafe path in package archive: " . $entry_name);
                }

                if (strpos($entry_name, '../') !== false || substr($entry_name, -3) === '/..') {
                    throw new Exception("Unsafe path in package archive: " . $entry_name);
                }
            }
        }
        catch (\PhpZip\Exception\ZipException $e) {
            throw new Exception("Failed to read ZIP file: " . $e->getMessage());
        }
        finally {
            $zipFile->close();
        }
    }


    /**
     * 
     * Read and validate info.json from extracted package
     * 
     * @param string $project_path - Path to extracted project folder
     * @return array - Project info data
     * 
     */
    private function read_project_info($project_path)
    {
        $info_path = $project_path . '/info.json';

        if (!file_exists($info_path)){
            throw new Exception("Project info.json not found: " . $info_path);
        }

        $info_content = file_get_contents($info_path);
        if ($info_content === false){
            throw new Exception("Failed to read info.json: " . $info_path);
        }

        $project_info = json_decode($info_content, true);
        if ($project_info === null){
            throw new Exception("Invalid JSON in info.json: " . json_last_error_msg());
        }

        return $project_info;
    }


    /**
     * 
     * Validate IDNO from package doesn't conflict with existing projects
     * 
     * @param int $sid - Current project ID being imported into
     * @param array $project_info - Project info from info.json
     * @throws Exception if IDNO already exists for a different project
     * 
     */
    private function validate_idno($sid, $project_info)
    {
        // Check if IDNO is provided
        if (empty($project_info['idno'])){
            log_message('info', "Package info.json missing 'idno' field");
            return; // Allow import to continue without IDNO validation
        }

        $idno = $project_info['idno'];

        // Validate IDNO format
        try {
            $this->ci->Editor_model->validate_idno_format($idno);
        }
        catch (Exception $e) {
            throw new Exception("Invalid IDNO format in package: " . $idno . ". " . $e->getMessage());
        }

        // Check if IDNO already exists for a different project
        if ($this->ci->Editor_model->idno_exists($idno, $sid)){
            throw new Exception("Project IDNO already exists: " . $idno . ". Cannot import package with duplicate IDNO.");
        }

        log_message('info', "IDNO validation passed: " . $idno);
    }


    /**
     * 
     * Import metadata from package (tries JSON first, falls back to XML)
     * 
     * @param int $sid - Project ID
     * @param string $project_path - Path to extracted project folder
     * @param array $project_info - Project info from info.json
     * @return mixed - Import result
     * 
     */
    private function import_metadata($sid, $project_path, $project_info, $import_options = array())
    {
        $metadata_file_path = null;
        $file_source = null;

        // Try JSON first
        if (!empty($project_info['json_file'])){
            $json_path = $this->resolve_package_file($project_path, $project_info['json_file']);
            if ($json_path){
                $metadata_file_path = $json_path;
                $file_source = 'json_file';
                log_message('info', "Using JSON metadata file: " . $json_path);
            }
            else {
                log_message('info', "JSON file specified but not found: " . $project_info['json_file']);
            }
        }

        // Fallback to XML if JSON not available
        if (!$metadata_file_path && !empty($project_info['xml_file'])){
            $xml_path = $this->resolve_package_file($project_path, $project_info['xml_file']);
            if ($xml_path){
                $metadata_file_path = $xml_path;
                $file_source = 'xml_file';
                log_message('info', "Falling back to XML metadata file: " . $xml_path);
            }
            else {
                log_message('info', "XML file specified but not found: " . $project_info['xml_file']);
            }
        }

        // No metadata file found
        if (!$metadata_file_path){
            throw new Exception("No metadata file found in package. Expected json_file or xml_file to exist.");
        }

        // Import using ImportJsonMetadata (handles both JSON and XML)
        $result = $this->ci->importjsonmetadata->import($sid, $metadata_file_path, $validate=false, $import_options);

        return array(
            'result' => $result,
            'file_used' => $file_source,
            'file_path' => $metadata_file_path
        );
    }


    /**
     * 
     * Import external resources from package (tries RDF JSON first, falls back to RDF XML)
     * 
     * @param int $sid - Project ID
     * @param string $project_path - Path to extracted project folder
     * @param array $project_info - Project info from info.json
     * @return int - Number of resources imported
     * 
     */
    private function import_resources($sid, $project_path, $project_info)
    {
        $rdf_file_path = null;
        $file_type = null;

        // Try RDF JSON first
        if (!empty($project_info['rdf_json_file'])){
            $rdf_json_path = $this->resolve_package_file($project_path, $project_info['rdf_json_file']);
            if ($rdf_json_path){
                $rdf_file_path = $rdf_json_path;
                $file_type = 'json';
                log_message('info', "Using RDF JSON file: " . $rdf_json_path);
            }
            else {
                log_message('info', "RDF JSON file specified but not found: " . $project_info['rdf_json_file']);
            }
        }

        // Fallback to RDF XML if RDF JSON not available
        if (!$rdf_file_path && !empty($project_info['rdf_xml_file'])){
            $rdf_xml_path = $this->resolve_package_file($project_path, $project_info['rdf_xml_file']);
            if ($rdf_xml_path){
                $rdf_file_path = $rdf_xml_path;
                $file_type = 'xml';
                log_message('info', "Falling back to RDF XML file: " . $rdf_xml_path);
            }
            else {
                log_message('info', "RDF XML file specified but not found: " . $project_info['rdf_xml_file']);
            }
        }

        // Import resources if file found
        $resources_imported = 0;
        if ($rdf_file_path){
            try {
                if ($file_type == 'json'){
                    $result = $this->ci->Editor_resource_model->import_json($sid, $rdf_file_path);
                    if ($result && isset($result['added'])){
                        $resources_imported = $result['added'];
                        log_message('info', "Imported {$resources_imported} resources from RDF JSON (skipped: {$result['skipped']})");
                    }
                }
                else if ($file_type == 'xml'){
                    // Import RDF XML using import_rdf method
                    $result = $this->ci->Editor_resource_model->import_rdf($sid, $rdf_file_path);
                    if ($result && isset($result['added'])){
                        $resources_imported = $result['added'];
                        log_message('info', "Imported {$resources_imported} resources from RDF XML (skipped: {$result['skipped']})");
                    }
                }
            }
            catch (Exception $e) {
                log_message('error', 'Failed to import resources: ' . $e->getMessage());
            }
        }
        else {
            log_message('info', "No RDF file found in package. Skipping resource import.");
        }

        return $resources_imported;
    }


    /**
     * 
     * Set project thumbnail if available
     * 
     * @param int $sid - Project ID
     * @param array $project_info - Project info from info.json
     * @return string|null - Thumbnail filename or null
     * 
     */
    private function set_thumbnail($sid, $project_info)
    {
        $thumbnail = isset($project_info['thumbnail']) ? $project_info['thumbnail'] : null;

        if ($thumbnail){
            $thumbnail = $this->normalize_package_relative_path($thumbnail);
            $this->ci->Editor_model->set_project_options($sid, array(
                'thumbnail' => $thumbnail
            ));
            log_message('info', "Set project thumbnail: " . $thumbnail);
        }

        return $thumbnail;
    }


    /**
     * Match a data-file basename against files in data/.
     * Native source (.dta / .sav) wins for file_physical_name; CSV is the working copy.
     *
     * @param string $sanitized_name Basename without extension
     * @param array $folder_files Filenames in data/
     * @return array{physical:?string,source_ext:?string,csv:?string}
     */
    public function match_physical_data_file($sanitized_name, $folder_files)
    {
        $physical = null;
        $source_ext = null;
        $csv = $this->find_folder_file($folder_files, $sanitized_name . '.csv');

        foreach (array('dta', 'sav') as $ext) {
            $found = $this->find_folder_file($folder_files, $sanitized_name . '.' . $ext);
            if ($found) {
                $physical = $found;
                $source_ext = $ext;
                break;
            }
        }

        if (!$physical && $csv) {
            $physical = $csv;
            $source_ext = 'csv';
        }

        return array(
            'physical' => $physical,
            'source_ext' => $source_ext,
            'csv' => $csv,
        );
    }

    /**
     * Link data files with actual files in project-folder/data folder.
     *
     * Native source files (.dta / .sav) are preferred for file_physical_name.
     * Working CSV is detected separately. When enqueue_csv is set and a native
     * source exists without CSV, FastAPI generate-csv-queue is called.
     *
     * @param int $sid
     * @param string|null $project_path
     * @param array $options enqueue_csv (bool), user_id (int|null)
     * @return array
     */
    public function link_data_files($sid, $project_path = null, $options = array())
    {
        $enqueue_csv = !empty($options['enqueue_csv']);

        $empty = array(
            'linked' => 0,
            'skipped' => 0,
            'unmatched' => 0,
            'csv_queued' => 0,
            'fastapi_online' => null,
            'errors' => array(),
            'files' => array(),
            'unmatched_on_disk' => array(),
        );

        $project = $this->ci->Editor_model->get_basic_info($sid);
        if (!$project) {
            log_message('info', "Project not found for linking data files: " . $sid);
            return $empty;
        }

        $project_type = isset($project['type']) ? $project['type'] : '';
        if (!in_array($project_type, array('microdata', 'survey'))) {
            log_message('info', "Skipping data file linking for project type: " . $project_type);
            return $empty;
        }

        if (!$project_path) {
            $project_path = $this->ci->Editor_model->get_project_folder($sid);
        }

        $data_folder = $project_path . '/data/';

        if (!file_exists($data_folder) || !is_dir($data_folder)) {
            $empty['errors'][] = 'Data folder not found';
            return $empty;
        }

        $data_files = $this->ci->Editor_datafile_model->select_all($sid);
        if (empty($data_files)) {
            return $empty;
        }

        $data_folder_files = array();
        if (is_dir($data_folder) && ($handle = opendir($data_folder))) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && is_file($data_folder . $entry)) {
                    $data_folder_files[] = $entry;
                }
            }
            closedir($handle);
        }

        $linked_count = 0;
        $skipped_count = 0;
        $unmatched_count = 0;
        $csv_queued = 0;
        $errors = array();
        $file_rows = array();
        $matched_disk = array();
        $fastapi_online = null;

        if ($enqueue_csv) {
            $fastapi_online = $this->fastapi_is_online();
            if (!$fastapi_online) {
                $errors[] = 'FastAPI is not running. Working CSV was not generated.';
            }
        }

        foreach ($data_files as $file_id => $data_file) {
            $logical_file_id = isset($data_file['file_id']) ? $data_file['file_id'] : $file_id;
            $current_file_name = isset($data_file['file_name']) ? $data_file['file_name'] : '';

            $row = array(
                'file_id' => $logical_file_id,
                'file_name' => $current_file_name,
                'physical' => isset($data_file['file_physical_name']) ? $data_file['file_physical_name'] : '',
                'source_ext' => null,
                'csv' => null,
                'csv_status' => 'missing',
                'csv_job' => null,
                'linked' => false,
            );

            if (empty($current_file_name)) {
                $skipped_count++;
                $row['csv_status'] = 'skipped';
                $file_rows[] = $row;
                continue;
            }

            $sanitized_name = $this->ci->Editor_datafile_model->filename_part($current_file_name);
            $match = $this->match_physical_data_file($sanitized_name, $data_folder_files);
            $row['file_name'] = $sanitized_name;
            $row['physical'] = $match['physical'];
            $row['source_ext'] = $match['source_ext'];
            $row['csv'] = $match['csv'];

            if ($match['physical']) {
                $matched_disk[] = $match['physical'];
            }
            if ($match['csv'] && $match['csv'] !== $match['physical']) {
                $matched_disk[] = $match['csv'];
            }

            if (!$match['physical']) {
                $unmatched_count++;
                $row['csv_status'] = 'unmatched';
                $file_rows[] = $row;
                continue;
            }

            $update_data = array();
            if ($sanitized_name != $current_file_name) {
                $update_data['file_name'] = $sanitized_name;
            }

            $current_physical_name = isset($data_file['file_physical_name']) ? $data_file['file_physical_name'] : '';
            if (empty($current_physical_name) || strcasecmp($current_physical_name, $match['physical']) != 0) {
                $update_data['file_physical_name'] = $match['physical'];
            }

            $current_source_format = isset($data_file['source_format']) ? $data_file['source_format'] : '';
            if ($current_source_format === '' || $current_source_format === null) {
                $source_fields = $this->ci->Editor_datafile_model->build_source_fields_from_path($match['physical']);
                $update_data = array_merge($update_data, $source_fields);
            } elseif (empty($data_file['source_status']) || $data_file['source_status'] === 'unknown') {
                if ($match['source_ext'] === 'csv') {
                    $update_data['source_status'] = 'not_applicable';
                } else {
                    $update_data['source_status'] = 'present';
                }
            }

            if (!empty($update_data)) {
                try {
                    $this->ci->Editor_datafile_model->update($data_file['id'], $update_data);
                    $linked_count++;
                    $row['linked'] = true;
                }
                catch (Exception $e) {
                    $errors[] = "Failed to update file {$logical_file_id}: " . $e->getMessage();
                }
            } else {
                $skipped_count++;
                $row['linked'] = true;
            }

            if ($match['csv']) {
                $row['csv_status'] = 'present';
            } elseif (in_array($match['source_ext'], array('dta', 'sav'), true) && $enqueue_csv) {
                if ($fastapi_online === false) {
                    $row['csv_status'] = 'queue_failed';
                    $row['csv_job'] = array(
                        'job_id' => null,
                        'status' => 'failed',
                        'error_message' => 'FastAPI is not running',
                    );
                } else {
                    try {
                        $job_info = $this->enqueue_generate_working_csv($sid, $logical_file_id);
                        $row['csv_status'] = 'queued';
                        $row['csv_job'] = $job_info;
                        $csv_queued++;
                    }
                    catch (Exception $e) {
                        $row['csv_status'] = 'queue_failed';
                        $row['csv_job'] = array(
                            'job_id' => null,
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        );
                        $errors[] = "Failed to queue CSV generation for {$logical_file_id}: " . $e->getMessage();
                        log_message('error', $e->getMessage());
                    }
                }
            } else {
                $row['csv_status'] = 'missing';
            }

            $file_rows[] = $row;
        }

        $unmatched_on_disk = array();
        foreach ($data_folder_files as $disk_file) {
            $already = false;
            foreach ($matched_disk as $matched) {
                if (strcasecmp($matched, $disk_file) == 0) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $unmatched_on_disk[] = $disk_file;
            }
        }

        return array(
            'linked' => $linked_count,
            'skipped' => $skipped_count,
            'unmatched' => $unmatched_count,
            'csv_queued' => $csv_queued,
            'fastapi_online' => $fastapi_online,
            'errors' => $errors,
            'files' => $file_rows,
            'unmatched_on_disk' => $unmatched_on_disk,
        );
    }

    /**
     * Persist a sidecar import report in the project folder.
     *
     * @param int $sid
     * @param array $report
     * @return string Path written
     */
    public function save_import_report($sid, $report)
    {
        $path = $this->import_report_path($sid);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            throw new Exception("Project folder not found");
        }

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception("Failed to encode import report: " . json_last_error_msg());
        }

        if (file_put_contents($path, $json) === false) {
            throw new Exception("Failed to write import report: " . $path);
        }

        return $path;
    }

    /**
     * Load persisted import report, merging live CSV job status.
     *
     * @param int $sid
     * @return array|null
     */
    public function load_import_report($sid)
    {
        $report = $this->read_import_report($sid);
        if ($report === null) {
            return null;
        }

        return $this->enrich_import_report($sid, $report);
    }

    private function read_import_report($sid)
    {
        $path = $this->import_report_path($sid);
        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $report = json_decode($raw, true);
        if (!is_array($report)) {
            throw new Exception("Invalid JSON in import_report.json: " . json_last_error_msg());
        }

        return $report;
    }

    /**
     * Record a non-ZIP create-from-file import (JSON / JSONL / XML).
     *
     * @param int $sid
     * @param string $source json|jsonl|xml
     * @param string $metadata_file_basename
     * @param int|null $user_id
     * @return array
     */
    public function write_basic_import_report($sid, $source, $metadata_file_basename, $user_id = null)
    {
        $project = $this->ci->Editor_model->get_basic_info($sid);
        $stats = array();
        if (isset($this->ci->importjsonmetadata) && is_object($this->ci->importjsonmetadata)
            && method_exists($this->ci->importjsonmetadata, 'get_import_stats')) {
            $stats = $this->ci->importjsonmetadata->get_import_stats();
        }

        $link_result = array('files' => array(), 'unmatched' => 0, 'unmatched_on_disk' => array(), 'csv_queued' => 0, 'errors' => array());
        $type = isset($project['type']) ? $project['type'] : '';
        if (in_array($type, array('microdata', 'survey'), true)) {
            $link_result = $this->link_data_files($sid, null, array('enqueue_csv' => false, 'user_id' => $user_id));
        }

        $report = array(
            'version' => 1,
            'imported_at' => date('c'),
            'imported_by' => $user_id,
            'source' => $source,
            'package' => array(
                'idno' => isset($project['idno']) ? $project['idno'] : '',
                'type' => $type,
                'title' => isset($project['title']) ? $project['title'] : '',
            ),
            'metadata' => array(
                'status' => 'imported',
                'file_used' => $source,
                'file_path' => $metadata_file_basename,
            ),
            'resources' => array(
                'imported' => 0,
            ),
            'thumbnail' => isset($project['thumbnail']) ? $project['thumbnail'] : null,
            'extras' => $this->build_report_extras($sid, $type, $stats, $link_result, $this->ci->Editor_model->get_project_folder($sid)),
        );
        $report['overall_status'] = $this->compute_overall_status($report);
        $this->save_import_report($sid, $report);
        return $report;
    }

    /**
     * Queue FastAPI generate-csv-queue for a linked native data file.
     *
     * @param int $sid
     * @param string $file_id Logical file id (F1, ...)
     * @return array
     */
    public function enqueue_generate_working_csv($sid, $file_id, $user_id = null)
    {
        $this->ci->load->library('DataUtils');

        $datafile_path = $this->ci->Editor_datafile_model->get_file_path($sid, $file_id);
        if (!$datafile_path || !file_exists($datafile_path)) {
            throw new Exception("Source data file not found: {$file_id}");
        }

        try {
            $api_response = $this->ci->datautils->generate_csv_queue($datafile_path);
        } catch (Exception $e) {
            throw new Exception("FastAPI is not running: " . $e->getMessage());
        }
        $status_code = isset($api_response['status_code']) ? (int) $api_response['status_code'] : 500;
        if ($status_code < 200 || $status_code >= 300) {
            throw new Exception("FastAPI generate-csv-queue failed with status {$status_code}: " . json_encode($api_response['response']));
        }

        $fastapi_job_id = $this->extract_fastapi_job_id(isset($api_response['response']) ? $api_response['response'] : array());
        if ($fastapi_job_id === null) {
            throw new Exception("FastAPI response missing job_id: " . json_encode($api_response['response']));
        }

        return array(
            'job_id' => $fastapi_job_id,
            'status' => 'queued',
            'error_message' => null,
        );
    }

    /**
     * Retry CSV generation for one file and update the stored report.
     *
     * @param int $sid
     * @param string $file_id
     * @param int|null $user_id
     * @return array Updated report
     */
    public function retry_generate_working_csv($sid, $file_id, $user_id = null)
    {
        $report = $this->read_import_report($sid);
        if (!is_array($report)) {
            $report = array('version' => 1, 'extras' => array('microdata' => array('files' => array())));
        }

        if (!$this->fastapi_is_online()) {
            $job_info = array(
                'job_id' => null,
                'status' => 'failed',
                'error_message' => 'FastAPI is not running',
            );
            $csv_status = 'queue_failed';
        } else {
            $job_info = $this->enqueue_generate_working_csv($sid, $file_id);
            $csv_status = 'queued';
        }

        if (!isset($report['extras']['microdata']['files']) || !is_array($report['extras']['microdata']['files'])) {
            $report['extras']['microdata']['files'] = array();
        }

        $updated = false;
        foreach ($report['extras']['microdata']['files'] as &$file_row) {
            if (isset($file_row['file_id']) && (string) $file_row['file_id'] === (string) $file_id) {
                $file_row['csv_status'] = $csv_status;
                $file_row['csv_job'] = $job_info;
                $updated = true;
            }
        }
        unset($file_row);

        if (!$updated) {
            $report['extras']['microdata']['files'][] = array(
                'file_id' => $file_id,
                'csv_status' => $csv_status,
                'csv_job' => $job_info,
                'linked' => true,
            );
        }

        $report['overall_status'] = $this->compute_overall_status($report);
        $this->save_import_report($sid, $report);
        return $this->enrich_import_report($sid, $report);
    }

    private function find_folder_file($folder_files, $search_filename)
    {
        foreach ($folder_files as $folder_file) {
            if (strcasecmp($folder_file, $search_filename) == 0) {
                return $folder_file;
            }
        }
        return null;
    }

    private function import_report_path($sid)
    {
        $project_folder = $this->ci->Editor_model->get_project_folder($sid);
        return rtrim($project_folder, '/\\') . '/import_report.json';
    }

    private function build_zip_import_report(
        $sid,
        $project_path,
        $project_info,
        $metadata_result,
        $metadata_stats,
        $link_result,
        $resources_imported,
        $thumbnail,
        $user_id
    ) {
        $type = isset($project_info['type']) ? $project_info['type'] : '';
        $project = $this->ci->Editor_model->get_basic_info($sid);
        if ($project && $type === '') {
            $type = isset($project['type']) ? $project['type'] : '';
        }

        $file_used = isset($metadata_result['file_used']) ? $metadata_result['file_used'] : null;
        $file_path = isset($metadata_result['file_path']) ? basename($metadata_result['file_path']) : null;

        $report = array(
            'version' => 1,
            'imported_at' => date('c'),
            'imported_by' => $user_id,
            'source' => 'zip',
            'package' => array(
                'idno' => isset($project_info['idno']) ? $project_info['idno'] : '',
                'type' => $type,
                'title' => isset($project_info['title']) ? $project_info['title'] : (isset($project['title']) ? $project['title'] : ''),
                'package_format' => isset($project_info['package_format']) ? $project_info['package_format'] : null,
                'json_file' => isset($project_info['json_file']) ? $project_info['json_file'] : null,
                'xml_file' => isset($project_info['xml_file']) ? $project_info['xml_file'] : null,
            ),
            'metadata' => array(
                'status' => 'imported',
                'file_used' => $file_used,
                'file_path' => $file_path,
            ),
            'resources' => array(
                'imported' => (int) $resources_imported,
            ),
            'thumbnail' => $thumbnail,
            'extras' => $this->build_report_extras($sid, $type, $metadata_stats, $link_result, $project_path),
        );
        $report['overall_status'] = $this->compute_overall_status($report);
        return $report;
    }

    private function build_report_extras($sid, $type, $metadata_stats, $link_result, $project_path)
    {
        $extras = array();
        $canonical = $this->ci->Editor_model->resolve_canonical_type($type) ?: $type;

        if ($canonical === 'microdata' || $canonical === 'survey' || $type === 'microdata' || $type === 'survey') {
            $files = isset($link_result['files']) && is_array($link_result['files']) ? $link_result['files'] : array();
            $extras['microdata'] = array(
                'data_files_seen' => isset($metadata_stats['data_files_seen']) ? (int) $metadata_stats['data_files_seen'] : count($files),
                'data_files_imported' => isset($metadata_stats['data_files_imported']) ? (int) $metadata_stats['data_files_imported'] : count($files),
                'data_files_skipped' => isset($metadata_stats['data_files_skipped']) ? (int) $metadata_stats['data_files_skipped'] : 0,
                'data_files_skipped_detail' => isset($metadata_stats['data_files_skipped_detail']) ? $metadata_stats['data_files_skipped_detail'] : array(),
                'variables_seen' => isset($metadata_stats['variables_seen']) ? (int) $metadata_stats['variables_seen'] : 0,
                'variables_imported' => isset($metadata_stats['variables_imported']) ? (int) $metadata_stats['variables_imported'] : 0,
                'variables_skipped' => isset($metadata_stats['variables_skipped']) ? (int) $metadata_stats['variables_skipped'] : 0,
                'variables_skipped_detail' => isset($metadata_stats['variables_skipped_detail']) ? $metadata_stats['variables_skipped_detail'] : array(),
                'variable_groups' => !empty($metadata_stats['variable_groups']),
                'linked' => isset($link_result['linked']) ? (int) $link_result['linked'] : 0,
                'unmatched' => isset($link_result['unmatched']) ? (int) $link_result['unmatched'] : 0,
                'csv_queued' => isset($link_result['csv_queued']) ? (int) $link_result['csv_queued'] : 0,
                'fastapi_online' => array_key_exists('fastapi_online', $link_result) ? $link_result['fastapi_online'] : null,
                'files' => $files,
                'unmatched_on_disk' => isset($link_result['unmatched_on_disk']) ? $link_result['unmatched_on_disk'] : array(),
                'errors' => isset($link_result['errors']) ? $link_result['errors'] : array(),
            );
        }

        if ($canonical === 'geospatial' || $type === 'geospatial') {
            $extras['geospatial'] = isset($metadata_stats['geospatial']) && is_array($metadata_stats['geospatial'])
                ? $metadata_stats['geospatial']
                : array();
        }

        if (in_array($canonical, array('indicator', 'timeseries', 'timeseries-db', 'indicator-db'), true)
            || in_array($type, array('indicator', 'timeseries', 'timeseries-db', 'indicator-db'), true)) {
            $extras['indicator'] = $this->describe_indicator_package_data($project_path);
        }

        return $extras;
    }

    private function describe_indicator_package_data($project_path)
    {
        $candidates = array(
            'data/indicator_data.csv',
            'data/indicator_staging_upload.csv',
        );
        $found = null;
        foreach ($candidates as $relative) {
            if (is_file($project_path . '/' . $relative)) {
                $found = $relative;
                break;
            }
        }

        return array(
            'data_file_present' => $found !== null,
            'data_file' => $found,
            'loaded' => false,
            'note' => 'Observation data in the package is stored with the project; it is not loaded into the indicator store during package import.',
        );
    }

    private function enrich_import_report($sid, $report)
    {
        if (isset($report['extras']['microdata']['files']) && is_array($report['extras']['microdata']['files'])) {
            foreach ($report['extras']['microdata']['files'] as &$file_row) {
                $file_id = isset($file_row['file_id']) ? $file_row['file_id'] : null;
                if ($file_id) {
                    try {
                        $csv_path = $this->ci->Editor_datafile_model->get_file_csv_path($sid, $file_id);
                        if ($csv_path && file_exists($csv_path)) {
                            $file_row['csv'] = basename($csv_path);
                            $file_row['csv_status'] = 'present';
                        }
                    } catch (Exception $e) {
                        // File may have been removed since import
                    }
                }

                $job_id = isset($file_row['csv_job']['job_id']) ? $file_row['csv_job']['job_id'] : null;
                if ($job_id && (!isset($file_row['csv_status']) || $file_row['csv_status'] !== 'present')) {
                    $this->apply_fastapi_csv_job_status($sid, $file_row);
                }
            }
            unset($file_row);
        }

        $report['overall_status'] = $this->compute_overall_status($report);
        return $report;
    }

    private function compute_overall_status($report)
    {
        $has_warning = false;
        $has_progress = false;
        $has_failed = false;

        if (isset($report['extras']['microdata']) && is_array($report['extras']['microdata'])) {
            $md = $report['extras']['microdata'];
            if (!empty($md['unmatched']) || !empty($md['variables_skipped']) || !empty($md['data_files_skipped'])) {
                $has_warning = true;
            }
            if (!empty($md['unmatched_on_disk'])) {
                $has_warning = true;
            }
            if (!empty($md['errors'])) {
                $has_warning = true;
            }
            if (isset($md['files']) && is_array($md['files'])) {
                foreach ($md['files'] as $file_row) {
                    $csv_status = isset($file_row['csv_status']) ? $file_row['csv_status'] : '';
                    if (in_array($csv_status, array('queued', 'generating', 'processing', 'pending'), true)) {
                        $has_progress = true;
                    }
                    if (in_array($csv_status, array('failed', 'queue_failed'), true)) {
                        $has_failed = true;
                    }
                    if ($csv_status === 'unmatched' || $csv_status === 'missing') {
                        $has_warning = true;
                    }
                }
            }
        }

        if ($has_progress) {
            return 'csv_in_progress';
        }
        if ($has_failed) {
            return 'csv_failed';
        }
        if ($has_warning) {
            return 'complete_with_warnings';
        }
        return 'complete';
    }

    private function fastapi_is_online()
    {
        $this->ci->load->library('DataUtils');
        try {
            $status = $this->ci->datautils->status();
        } catch (Exception $e) {
            return false;
        }

        return is_array($status) && isset($status['status']) && $status['status'] === 'ok';
    }

    private function extract_fastapi_job_id($response)
    {
        if (!is_array($response)) {
            return null;
        }
        if (isset($response['job_id']) && $response['job_id'] !== '' && $response['job_id'] !== null) {
            return $response['job_id'];
        }
        if (isset($response['id']) && $response['id'] !== '' && $response['id'] !== null) {
            return $response['id'];
        }
        return null;
    }

    private function apply_fastapi_csv_job_status($sid, array &$file_row)
    {
        $job_id = isset($file_row['csv_job']['job_id']) ? $file_row['csv_job']['job_id'] : null;
        if ($job_id === null || $job_id === '') {
            return;
        }

        $this->ci->load->library('DataUtils');
        try {
            $status_response = $this->ci->datautils->get_job_status($job_id);
        } catch (Exception $e) {
            $file_row['csv_job']['error_message'] = $e->getMessage();
            return;
        }

        $http = isset($status_response['status_code']) ? (int) $status_response['status_code'] : 500;
        $body = isset($status_response['response']) && is_array($status_response['response'])
            ? $status_response['response'] : array();
        $fastapi_status = isset($body['status']) ? $body['status'] : '';

        $file_row['csv_job']['status'] = $fastapi_status !== '' ? $fastapi_status : $file_row['csv_job']['status'];

        if ($http !== 200) {
            $file_row['csv_status'] = 'failed';
            $file_row['csv_job']['error_message'] = isset($body['message']) ? $body['message'] : ('FastAPI HTTP ' . $http);
            return;
        }

        if ($fastapi_status === 'done' || $fastapi_status === 'completed') {
            try {
                $csv_path = $this->ci->Editor_datafile_model->check_csv_exists($sid, $file_row['file_id']);
            } catch (Exception $e) {
                $csv_path = false;
            }
            if ($csv_path) {
                $file_row['csv'] = basename($csv_path);
                $file_row['csv_status'] = 'present';
            } else {
                $file_row['csv_status'] = 'failed';
                $file_row['csv_job']['error_message'] = 'CSV generation finished but working CSV was not found';
            }
            return;
        }

        if ($fastapi_status === 'failed' || $fastapi_status === 'error') {
            $file_row['csv_status'] = 'failed';
            $file_row['csv_job']['error_message'] = isset($body['message']) && $body['message'] !== ''
                ? $body['message']
                : (isset($body['detail']) && is_string($body['detail']) ? $body['detail'] : 'FastAPI job failed');
            return;
        }

        if (in_array($fastapi_status, array('processing', 'running'), true)) {
            $file_row['csv_status'] = 'generating';
            return;
        }

        $file_row['csv_status'] = 'queued';
    }

}

