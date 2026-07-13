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

        // Link data files for microdata projects
        $this->link_data_files($sid, $project_path);

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

        return array(
            'project_imported' => $metadata_result,
            'resources_imported' => $resources_imported,
            'thumbnail' => $thumbnail,
            'project_info' => $project_info
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
            $json_path = $project_path . '/' . $project_info['json_file'];
            if (file_exists($json_path)){
                $metadata_file_path = $json_path;
                $file_source = 'json_file';
                log_message('info', "Using JSON metadata file: " . $json_path);
            }
            else {
                log_message('info', "JSON file specified but not found: " . $json_path);
            }
        }

        // Fallback to XML if JSON not available
        if (!$metadata_file_path && !empty($project_info['xml_file'])){
            $xml_path = $project_path . '/' . $project_info['xml_file'];
            if (file_exists($xml_path)){
                $metadata_file_path = $xml_path;
                $file_source = 'xml_file';
                log_message('info', "Falling back to XML metadata file: " . $xml_path);
            }
            else {
                log_message('info', "XML file specified but not found: " . $xml_path);
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
            $rdf_json_path = $project_path . '/' . $project_info['rdf_json_file'];
            if (file_exists($rdf_json_path)){
                $rdf_file_path = $rdf_json_path;
                $file_type = 'json';
                log_message('info', "Using RDF JSON file: " . $rdf_json_path);
            }
            else {
                log_message('info', "RDF JSON file specified but not found: " . $rdf_json_path);
            }
        }

        // Fallback to RDF XML if RDF JSON not available
        if (!$rdf_file_path && !empty($project_info['rdf_xml_file'])){
            $rdf_xml_path = $project_path . '/' . $project_info['rdf_xml_file'];
            if (file_exists($rdf_xml_path)){
                $rdf_file_path = $rdf_xml_path;
                $file_type = 'xml';
                log_message('info', "Falling back to RDF XML file: " . $rdf_xml_path);
            }
            else {
                log_message('info', "RDF XML file specified but not found: " . $rdf_xml_path);
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
            $this->ci->Editor_model->set_project_options($sid, array(
                'thumbnail' => $thumbnail
            ));
            log_message('info', "Set project thumbnail: " . $thumbnail);
        }

        return $thumbnail;
    }


    /**
     * 
     * Link data files with actual files in project-folder/data folder
     * 
     * This function:
     * 1. Reads all data file names from project-folder/data folder
     * 2. Sanitizes file_name to remove file extension
     * 3. Looks in project-folder/data folder for .CSV, .DTA, and .SAV files in that order
     * 4. Updates the file_name field in project-folder/data_files table if a match is found
     * 
     * @param int $sid - Project ID
     * @param string $project_path - Path to project folder (optional, will be retrieved if not provided)
     * @return array - Results of linking operation
     * 
     */
    public function link_data_files($sid, $project_path = null)
    {
        // Get project type - only link for microdata/survey projects
        $project = $this->ci->Editor_model->get_basic_info($sid);
        if (!$project) {
            log_message('info', "Project not found for linking data files: " . $sid);
            return array('linked' => 0, 'skipped' => 0, 'errors' => array());
        }

        $project_type = isset($project['type']) ? $project['type'] : '';
        if (!in_array($project_type, array('microdata', 'survey'))) {
            log_message('info', "Skipping data file linking for project type: " . $project_type);
            return array('linked' => 0, 'skipped' => 0, 'errors' => array());
        }

        // Get project folder path if not provided
        if (!$project_path) {
            $project_path = $this->ci->Editor_model->get_project_folder($sid);
        }

        $data_folder = $project_path . '/data/';

        // Check if data folder exists
        if (!file_exists($data_folder) || !is_dir($data_folder)) {
            return array('linked' => 0, 'skipped' => 0, 'errors' => array('Data folder not found'));
        }

        // Get all data files from database
        $data_files = $this->ci->Editor_datafile_model->select_all($sid);
        
        if (empty($data_files)) {
            return array('linked' => 0, 'skipped' => 0, 'errors' => array());
        }

        $linked_count = 0;
        $skipped_count = 0;
        $errors = array();

        // Get list of files in data folder
        $data_folder_files = array();
        if (is_dir($data_folder) && ($handle = opendir($data_folder))) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && is_file($data_folder . $entry)) {
                    $data_folder_files[] = $entry;
                }
            }
            closedir($handle);
        }

        // Process each data file from database
        foreach ($data_files as $file_id => $data_file) {
            $current_file_name = isset($data_file['file_name']) ? $data_file['file_name'] : '';
            
            if (empty($current_file_name)) {
                $skipped_count++;
                continue;
            }

            // Sanitize file_name to remove extension (if present)
            $sanitized_name = $this->ci->Editor_datafile_model->filename_part($current_file_name);

            // Look for matching files in order: .CSV, .DTA, .SAV
            $extensions = array('csv', 'dta', 'sav');
            $found_file = null;
            $found_extension = null;

            foreach ($extensions as $ext) {
                $search_filename = $sanitized_name . '.' . $ext;
                
                // Case-insensitive search
                foreach ($data_folder_files as $folder_file) {
                    if (strcasecmp($folder_file, $search_filename) == 0) {
                        $found_file = $folder_file;
                        $found_extension = $ext;
                        break 2; // Break out of both loops
                    }
                }
            }

            // If file found, update file_name and file_physical_name if needed
            if ($found_file) {
                $update_data = array();
                $needs_update = false;

                // Update file_name if it differs from sanitized name
                if ($sanitized_name != $current_file_name) {
                    $update_data['file_name'] = $sanitized_name;
                    $needs_update = true;
                }

                // Update file_physical_name if it's empty or doesn't match found file
                $current_physical_name = isset($data_file['file_physical_name']) ? $data_file['file_physical_name'] : '';
                if (empty($current_physical_name) || strcasecmp($current_physical_name, $found_file) != 0) {
                    $update_data['file_physical_name'] = $found_file;
                    $needs_update = true;
                }

                if ($needs_update) {
                    try {
                        // Update the database
                        $this->ci->Editor_datafile_model->update($data_file['id'], $update_data);
                        
                        $update_msg = "Linked data file: {$current_file_name} -> {$sanitized_name} (found: {$found_file})";
                        $linked_count++;
                    }
                    catch (Exception $e) {
                        $error_msg = "Failed to update file_name for file_id {$file_id}: " . $e->getMessage();
                        $errors[] = $error_msg;
                    }
                }
                else {
                    // File already correctly linked
                    $skipped_count++;
                }
            }
            else {
                // File not found in data folder
                $skipped_count++;
            }
        }

        return array(
            'linked' => $linked_count,
            'skipped' => $skipped_count,
            'errors' => $errors
        );
    }

}

