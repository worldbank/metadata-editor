<?php

/**
 * Geospatial Processor Library
 * 
 * Handles ZIP file validation, extraction, and geospatial file processing
 */
class Geospatial_processor {

    private $allowed_extensions = array(
        'shp', 'shx', 'dbf', 'prj', 'xml', 'cpg', 'txt',  // Shapefile components
        'gpkg',                      // GeoPackage
        'geojson', 'json',           // GeoJSON
        'kml', 'kmz',               // KML/KMZ
        'gpx',                      // GPS Exchange Format
        'csv',                      // CSV with coordinates
        'tiff', 'geotiff', 'tif'    // GeoTIFF
    );

    private $required_shapefile_extensions = array('shp', 'shx', 'dbf');
    
    private $shapefile_associated_extensions = array('shx', 'dbf', 'prj', 'xml', 'cpg', 'sbn', 'sbx', 'shp.xml');

    function __construct()
    {
        // Initialize any required dependencies
    }

    /**
     * Check if a file is a shapefile associated file (should be skipped during processing)
     * 
     * @param string $file_name File name to check
     * @return bool True if file should be skipped
     */
    private function is_shapefile_associated_file($file_name)
    {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        return in_array($extension, $this->shapefile_associated_extensions);
    }

    /**
     * Validate ZIP file contents against whitelist
     * 
     * @param string $zip_path Path to ZIP file
     * @return array Result with validation status and details
     */
    public function validate_zip_contents($zip_path)
    {
        $result = array(
            'valid' => false,
            'files' => array(),
            'errors' => array(),
            'has_folders' => false,
            'missing_required' => array()
        );

        if (!file_exists($zip_path)) {
            $result['errors'][] = 'ZIP file does not exist';
            return $result;
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== TRUE) {
            $result['errors'][] = 'Cannot open ZIP file';
            return $result;
        }

        $file_count = $zip->numFiles;
        $shapefile_components = array();

        for ($i = 0; $i < $file_count; $i++) {
            $file_info = $zip->statIndex($i);
            $file_name = $file_info['name'];
            
            // Check for folders/directories
            if (substr($file_name, -1) === '/') {
                $result['has_folders'] = true;
                $result['errors'][] = 'ZIP contains folders/directories';
                continue;
            }

            // Get file extension
            $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Check if extension is allowed
            if (!in_array($extension, $this->allowed_extensions)) {
                $result['errors'][] = "File '{$file_name}' has disallowed extension '{$extension}'";
                continue;
            }

            $result['files'][] = array(
                'name' => $file_name,
                'extension' => $extension,
                'size' => $file_info['size']
            );

            // Track shapefile components
            if (in_array($extension, $this->required_shapefile_extensions)) {
                $base_name = pathinfo($file_name, PATHINFO_FILENAME);
                $shapefile_components[$base_name][] = $extension;
            }
        }

        $zip->close();

        // Validate shapefile completeness
        foreach ($shapefile_components as $base_name => $components) {
            $missing = array_diff($this->required_shapefile_extensions, $components);
            if (!empty($missing)) {
                $result['missing_required'][] = "Shapefile '{$base_name}' missing: " . implode(', ', $missing);
            }
        }

        // Determine overall validity
        $result['valid'] = empty($result['errors']) && 
                          !$result['has_folders'] && 
                          empty($result['missing_required']) &&
                          !empty($result['files']);

        return $result;
    }

    /**
     * Extract ZIP file to destination directory
     * 
     * @param string $zip_path Path to ZIP file
     * @param string $destination Destination directory
     * @return array Result with extraction status
     */
    public function extract_zip($zip_path, $destination)
    {
        $result = array(
            'success' => false,
            'extracted_files' => array(),
            'errors' => array()
        );

        // Create destination directory if it doesn't exist
        if (!file_exists($destination)) {
            if (!mkdir($destination, 0777, true)) {
                $result['errors'][] = 'Cannot create destination directory';
                return $result;
            }
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== TRUE) {
            $result['errors'][] = 'Cannot open ZIP file';
            return $result;
        }

        // Extract all files
        if ($zip->extractTo($destination)) {
            $file_count = $zip->numFiles;
            for ($i = 0; $i < $file_count; $i++) {
                $file_info = $zip->statIndex($i);
                $file_name = $file_info['name'];
                
                // Skip directories
                if (substr($file_name, -1) !== '/') {
                    $result['extracted_files'][] = $file_name;
                }
            }
            $result['success'] = true;
        } else {
            $result['errors'][] = 'Failed to extract ZIP file';
        }

        $zip->close();
        return $result;
    }

    /**
     * Process uploaded files (validate ZIP, extract if needed)
     * 
     * @param array $uploaded_files Array of uploaded file information
     * @param string $project_folder Project folder path
     * @return array Processing result
     */
    public function process_uploaded_files($uploaded_files, $project_folder)
    {
        $result = array(
            'success' => false,
            'processed_files' => array(),
            'errors' => array(),
            'extracted_files' => array()
        );

        foreach ($uploaded_files as $file_info) {
            $file_path = $file_info['path'];
            $file_name = $file_info['name'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Debug logging
            error_log("Processing file: {$file_name}, extension: {$file_extension}, path: {$file_path}");
            error_log("File exists: " . (file_exists($file_path) ? 'YES' : 'NO'));

            if ($file_extension === 'zip') {
                // Debug logging
                error_log("Processing ZIP file: {$file_name} at {$file_path}");
                
                // Validate ZIP contents
                $validation = $this->validate_zip_contents($file_path);
                
                // Debug logging
                error_log("ZIP validation result: " . json_encode($validation));
                
                if (!$validation['valid']) {
                    $result['errors'][] = "ZIP file '{$file_name}' validation failed: " . implode(', ', $validation['errors']);
                    // Delete invalid ZIP file
                    unlink($file_path);
                    continue;
                }

                // Extract ZIP to project folder
                $extract_destination = $project_folder . '/geospatial';
                error_log("Extracting ZIP to: {$extract_destination}");
                
                $extraction = $this->extract_zip($file_path, $extract_destination);
                
                // Debug logging
                error_log("ZIP extraction result: " . json_encode($extraction));
                
                if (!$extraction['success']) {
                    $result['errors'][] = "Failed to extract ZIP '{$file_name}': " . implode(', ', $extraction['errors']);
                    unlink($file_path);
                    continue;
                }

                // Delete original ZIP file after successful extraction
                unlink($file_path);
                
                // Add extracted files to result (filter out shapefile associated files)
                foreach ($extraction['extracted_files'] as $extracted_file) {
                    // Skip shapefile associated files (shx, dbf, prj, etc.)
                    if ($this->is_shapefile_associated_file($extracted_file)) {
                        error_log("Skipping shapefile associated file: {$extracted_file}");
                        continue;
                    }
                    
                    $result['extracted_files'][] = array(
                        'original_zip' => $file_name,
                        'extracted_file' => $extracted_file,
                        'path' => $extract_destination . '/' . $extracted_file
                    );
                }

            } else {
                // Non-ZIP file - check if it's a shapefile associated file
                if ($this->is_shapefile_associated_file($file_name)) {
                    error_log("Skipping shapefile associated file: {$file_name}");
                    // Delete the file since we don't want to process it
                    unlink($file_path);
                    continue;
                }
                
                // Non-ZIP file - move directly to project folder
                $destination = $project_folder . '/geospatial/' . $file_name;
                $geospatial_folder = dirname($destination);
                
                if (!file_exists($geospatial_folder)) {
                    mkdir($geospatial_folder, 0777, true);
                }
                
                if (rename($file_path, $destination)) {
                    $result['processed_files'][] = array(
                        'original_name' => $file_name,
                        'path' => $destination
                    );
                } else {
                    $result['errors'][] = "Failed to move file '{$file_name}' to project folder";
                }
            }
        }

        $result['success'] = empty($result['errors']);
        return $result;
    }

    /**
     * Get file type information for geospatial files
     * 
     * @param string $file_path Path to geospatial file
     * @return array File type information
     */
    public function get_file_type_info($file_path)
    {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $type_info = array(
            'extension' => $extension,
            'type' => 'unknown',
            'is_multi_layer' => false,
            'description' => ''
        );

        switch ($extension) {
            case 'gpkg':
                $type_info['type'] = 'geopackage';
                $type_info['is_multi_layer'] = true;
                $type_info['description'] = 'GeoPackage - Modern geospatial format supporting multiple layers';
                break;
                
            case 'shp':
                $type_info['type'] = 'shapefile';
                $type_info['is_multi_layer'] = false;
                $type_info['description'] = 'Shapefile - Vector data format (requires .shx, .dbf, .prj files)';
                break;
                
            case 'geojson':
            case 'json':
                $type_info['type'] = 'geojson';
                $type_info['is_multi_layer'] = false;
                $type_info['description'] = 'GeoJSON - JSON-based geospatial data format';
                break;
                
            case 'kml':
                $type_info['type'] = 'kml';
                $type_info['is_multi_layer'] = true;
                $type_info['description'] = 'KML - Keyhole Markup Language for geographic data';
                break;
                
            case 'kmz':
                $type_info['type'] = 'kmz';
                $type_info['is_multi_layer'] = true;
                $type_info['description'] = 'KMZ - Compressed KML file';
                break;
                
            case 'gpx':
                $type_info['type'] = 'gpx';
                $type_info['is_multi_layer'] = false;
                $type_info['description'] = 'GPX - GPS Exchange Format';
                break;
                
            case 'csv':
                $type_info['type'] = 'csv';
                $type_info['is_multi_layer'] = false;
                $type_info['description'] = 'CSV - Comma-separated values with coordinate data';
                break;
                
            case 'tiff':
            case 'geotiff':
            case 'tif':
                $type_info['type'] = 'geotiff';
                $type_info['is_multi_layer'] = false;
                $type_info['description'] = 'GeoTIFF - Georeferenced raster image format';
                break;
        }

        return $type_info;
    }

    /**
     * Clean up temporary files and directories
     * 
     * @param string $temp_path Path to temporary directory
     * @return bool Success status
     */
    public function cleanup_temp_files($temp_path)
    {
        if (!file_exists($temp_path)) {
            return true;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        return rmdir($temp_path);
    }
}
