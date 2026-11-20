<?php

/**
 * Geospatial Processor Library
 * 
 * Handles ZIP file validation, extraction, and geospatial file processing
 */
class Geospatial_processor {

    /**
     * Raster formats that can be processed standalone (opened with GDAL).
     */
    private $raster_extensions = array(
        'tif', 'tiff', 'geotiff',           // GeoTIFF
        'jpg', 'jpeg', 'png', 'jp2',        // Image formats, JPEG2000
        'img', 'ecw', 'sid',                // ERDAS, ECW, MrSID
        'nc', 'nc4',                        // NetCDF
        'hdf', 'hdf5', 'h5', 'he5',         // HDF4/HDF5, HDF-EOS5
        'grib', 'grb',                      // GRIB (meteorological)
        'asc', 'dem',                       // ASCII Grid, DEM
        'bil', 'bip', 'bsq',                // Band interleaved (need .hdr)
        'dt0', 'dt1', 'dt2',                // DTED
        'vrt',                              // GDAL virtual raster
    );

    /**
     * Vector formats that can be processed standalone (opened with OGR/Fiona).
     */
    private $vector_extensions = array(
        'shp',                              // Shapefile
        'gpkg',                             // GeoPackage
        'geojson', 'json', 'topojson',      // GeoJSON / TopoJSON
        'kml', 'kmz',                       // KML/KMZ
        'gpx',                              // GPS Exchange Format
        'csv',                              // CSV with coordinates
        'gml',                              // GML
        'fgb',                              // FlatGeobuf
        'tab', 'mif',                       // MapInfo TAB / MIF
        'dxf',                              // AutoCAD DXF
        'osm', 'pbf',                       // OpenStreetMap
    );

    /**
     * Companion files: kept next to a primary file, never processed on their own.
     */
    private $companion_extensions = array(
        // Shapefile
        'shx', 'dbf', 'prj', 'cpg', 'cst', 'sbn', 'sbx',
        'qix', 'fix', 'atx', 'aih', 'ain', 'ixs', 'mxs',
        // Raster headers, world files, overviews, auxiliary data
        'hdr', 'tfw', 'tifw', 'tiffw', 'jgw', 'jpgw', 'pgw', 'pngw',
        'j2w', 'sdw', 'eww', 'wld', 'aux', 'rrd', 'ovr', 'ige',
        'msk', 'clr', 'stx',
        // MapInfo
        'dat', 'map', 'id', 'ind', 'mid',
        // GML schema
        'xsd',
        // Metadata (e.g. .shp.xml, .aux.xml), QGIS metadata/projection
        'xml', 'qmd', 'qpj',
        // Styles
        'sld', 'qml', 'lyr', 'lyrx', 'xsl',
    );

    /**
     * Documentation that often ships with geospatial data (readmes, codebooks).
     * Extracted but never processed. HTML is deliberately excluded because
     * project files live under the web root.
     */
    private $documentation_extensions = array(
        'txt', 'md', 'pdf', 'rtf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods',
    );

    private $required_shapefile_extensions = array('shp', 'shx', 'dbf');

    function __construct()
    {
        // Initialize any required dependencies
    }

    /**
     * Lower-cased last extension of a file name.
     */
    private function get_extension($file_name)
    {
        return strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    }

    /**
     * Whether a file may be extracted from an uploaded archive.
     * Anything not on this allowlist is skipped (never written to disk).
     *
     * @param string $file_name File name to check
     * @return bool
     */
    public function is_allowed_file($file_name)
    {
        $extension = $this->get_extension($file_name);
        return $this->is_primary_geospatial_file($file_name)
            || in_array($extension, $this->companion_extensions)
            || in_array($extension, $this->documentation_extensions);
    }

    /**
     * Whether a file is a raster format (by extension).
     *
     * @param string $file_name File name to check
     * @return bool
     */
    public function is_raster_file($file_name)
    {
        return in_array($this->get_extension($file_name), $this->raster_extensions);
    }

    /**
     * Archive entry name reduced to a safe flat file name: folders removed
     * (both / and \ separators), empty and dot names rejected.
     *
     * @param string $entry_name Name as stored in the ZIP
     * @return string|false Flat file name, or false if unusable
     */
    private function flat_entry_name($entry_name)
    {
        $name = basename(str_replace('\\', '/', $entry_name));
        if ($name === '' || $name === '.' || $name === '..') {
            return false;
        }
        return $name;
    }

    /**
     * Whether an archive entry is a directory or OS metadata to ignore silently
     * (directories, __MACOSX/, AppleDouble ._ files, .DS_Store, Thumbs.db).
     */
    private function is_ignored_entry($entry_name)
    {
        $normalized = str_replace('\\', '/', $entry_name);
        if (substr($normalized, -1) === '/') {
            return true;
        }
        $base = basename($normalized);
        return strpos($normalized, '__MACOSX/') === 0
            || strpos($base, '._') === 0
            || $base === '.DS_Store'
            || strcasecmp($base, 'Thumbs.db') === 0;
    }

    /**
     * Check if a file is a primary geospatial format that can be processed standalone
     * 
     * @param string $file_name File name to check
     * @return bool True if file is a primary geospatial format
     */
    public function is_primary_geospatial_file($file_name)
    {
        $extension = $this->get_extension($file_name);
        return in_array($extension, $this->raster_extensions)
            || in_array($extension, $this->vector_extensions);
    }

    /**
     * Validate ZIP file contents against the allowlist.
     *
     * Files with extensions not on the allowlist are not an error: they are
     * listed in 'skipped' and later left out of extraction. The archive is
     * only invalid when it can't be read, has name conflicts, has an
     * incomplete shapefile, or contains no processable geospatial file.
     * Allows any folder structure - files will be extracted flat.
     *
     * @param string $zip_path Path to ZIP file
     * @return array Result with validation status and details
     */
    public function validate_zip_contents($zip_path)
    {
        $result = array(
            'valid' => false,
            'files' => array(),
            'skipped' => array(),
            'errors' => array(),
            'has_folders' => false,
            'missing_required' => array(),
            'filename_conflicts' => array()
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
        $seen_basenames = array(); // Track basenames to detect conflicts
        $has_primary = false;

        for ($i = 0; $i < $file_count; $i++) {
            $file_info = $zip->statIndex($i);
            $file_name = $file_info['name'];

            if (strpos(str_replace('\\', '/', $file_name), '/') !== false) {
                $result['has_folders'] = true;
            }

            if ($this->is_ignored_entry($file_name)) {
                continue;
            }

            $flat_filename = $this->flat_entry_name($file_name);
            if ($flat_filename === false) {
                $result['skipped'][] = $file_name;
                continue;
            }

            // Unknown extension: skip it (it will not be extracted)
            if (!$this->is_allowed_file($flat_filename)) {
                $result['skipped'][] = $file_name;
                continue;
            }

            // Check for filename conflicts after flattening (allowed files only)
            $conflict_key = strtolower($flat_filename);
            if (isset($seen_basenames[$conflict_key])) {
                $result['filename_conflicts'][] = "Duplicate filename after flattening: '{$flat_filename}' (from '{$seen_basenames[$conflict_key]}' and '{$file_name}')";
            } else {
                $seen_basenames[$conflict_key] = $file_name;
            }

            $extension = $this->get_extension($flat_filename);
            $result['files'][] = array(
                'name' => $file_name,
                'flat_name' => $flat_filename,
                'extension' => $extension,
                'size' => $file_info['size']
            );

            if ($this->is_primary_geospatial_file($flat_filename)) {
                $has_primary = true;
            }

            // Track shapefile components (use flat filename for base name)
            if (in_array($extension, $this->required_shapefile_extensions)) {
                $base_name = pathinfo($flat_filename, PATHINFO_FILENAME);
                $shapefile_components[$base_name][] = $extension;
            }
        }

        $zip->close();

        // Add filename conflicts to errors
        if (!empty($result['filename_conflicts'])) {
            $result['errors'] = array_merge($result['errors'], $result['filename_conflicts']);
        }

        // Validate shapefile completeness
        foreach ($shapefile_components as $base_name => $components) {
            $missing = array_diff($this->required_shapefile_extensions, $components);
            if (!empty($missing)) {
                $result['missing_required'][] = "Shapefile '{$base_name}' missing: " . implode(', ', $missing);
            }
        }
        if (!empty($result['missing_required'])) {
            $result['errors'] = array_merge($result['errors'], $result['missing_required']);
        }

        if (!$has_primary) {
            $message = 'No supported geospatial files found in the archive';
            if (!empty($result['skipped'])) {
                $message .= ' (ignored unsupported files: ' . implode(', ', array_map('basename', $result['skipped'])) . ')';
            }
            $result['errors'][] = $message;
        }

        $result['valid'] = empty($result['errors']);

        return $result;
    }

    /**
     * Extract ZIP file to destination directory.
     * All files are extracted flat (folder structure is removed). Only files
     * on the allowlist are written; everything else is reported in 'skipped'.
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
            'skipped' => array(),
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

        $file_count = $zip->numFiles;
        for ($i = 0; $i < $file_count; $i++) {
            $file_info = $zip->statIndex($i);
            $file_name = $file_info['name'];

            if ($this->is_ignored_entry($file_name)) {
                continue;
            }

            $flat_filename = $this->flat_entry_name($file_name);
            if ($flat_filename === false || !$this->is_allowed_file($flat_filename)) {
                $result['skipped'][] = $file_name;
                continue;
            }

            $target_path = $destination . '/' . $flat_filename;

            // Stream the entry to avoid memory issues with large files
            $fp_in = $zip->getStream($file_name);
            if ($fp_in === false) {
                $result['errors'][] = "Failed to open stream for file: {$file_name}";
                continue;
            }

            $fp_out = fopen($target_path, 'wb');
            if ($fp_out === false) {
                fclose($fp_in);
                $result['errors'][] = "Failed to create file: {$flat_filename}";
                continue;
            }

            $bytes_written = stream_copy_to_stream($fp_in, $fp_out);

            fclose($fp_in);
            fclose($fp_out);

            // Empty files (e.g. a blank .cpg or readme) are valid
            if ($bytes_written === false || $bytes_written < (int) $file_info['size']) {
                $result['errors'][] = "Failed to write file: {$flat_filename}";
                @unlink($target_path);
                continue;
            }

            $result['extracted_files'][] = $flat_filename;
        }

        $result['success'] = !empty($result['extracted_files']) && empty($result['errors']);

        $zip->close();
        return $result;
    }

    /**
     * Process uploaded files (validate ZIP, extract if needed)
     * 
     * @param array $uploaded_files Array of uploaded file information
     * @param string $project_folder Project folder path
     * @param bool $keep_files Whether to keep files after extraction (default: false)
     * @return array Processing result
     */
    public function process_uploaded_files($uploaded_files, $project_folder, $keep_files = false)
    {
        $result = array(
            'success' => false,
            'processed_files' => array(),
            'errors' => array(),
            'extracted_files' => array(),
            'skipped_files' => array()
        );

        foreach ($uploaded_files as $file_info) {
            $file_path = $file_info['path'];
            $file_name = $file_info['name'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Debug logging
            error_log("Processing file: {$file_name}, extension: {$file_extension}, path: {$file_path}");
            error_log("File exists: " . (file_exists($file_path) ? 'YES' : 'NO'));

            if ($file_extension === 'zip' || $file_extension === 'kmz') {
                // Debug logging
                $archive_type = strtoupper($file_extension);
                error_log("Processing {$archive_type} file: {$file_name} at {$file_path}");
                
                // Validate ZIP/KMZ contents (KMZ is a ZIP file)
                $validation = $this->validate_zip_contents($file_path);
                
                // Debug logging
                error_log("{$archive_type} validation result: " . json_encode($validation));
                
                if (!$validation['valid']) {
                    $result['errors'][] = "{$archive_type} file '{$file_name}' validation failed: " . implode(', ', $validation['errors']);
                    // Delete invalid archive file
                    unlink($file_path);
                    continue;
                }

                // Extract ZIP/KMZ to project folder
                $extract_destination = $project_folder . '/geospatial';
                error_log("Extracting {$archive_type} to: {$extract_destination} (all files will be flattened)");
                
                // Extract with flattened structure (all folder paths removed)
                $extraction = $this->extract_zip($file_path, $extract_destination);
                
                // Debug logging
                error_log("{$archive_type} extraction result: " . json_encode($extraction));
                
                if (!$extraction['success']) {
                    $result['errors'][] = "Failed to extract {$archive_type} '{$file_name}': " . implode(', ', $extraction['errors']);
                    unlink($file_path);
                    continue;
                }

                // Delete files after extraction                
                unlink($file_path);

                // Report files left out because their type is not supported
                foreach ($extraction['skipped'] as $skipped_entry) {
                    $result['skipped_files'][] = array(
                        'original_zip' => $file_name,
                        'file' => $skipped_entry
                    );
                }
                
                // Add extracted files to result (filter out non-primary geospatial files)
                foreach ($extraction['extracted_files'] as $extracted_file) {
                    // Only include primary geospatial formats (skip supporting files like .txt, .xml, .prj)
                    if (!$this->is_primary_geospatial_file($extracted_file)) {
                        error_log("Skipping non-primary geospatial file: {$extracted_file}");
                        continue;
                    }
                    
                    // Build absolute path
                    $file_path = $extract_destination . '/' . $extracted_file;
                    $absolute_path = realpath($file_path);
                    if (!$absolute_path) {
                        error_log("Warning: Could not resolve absolute path for: {$file_path}");
                        $absolute_path = $file_path; // Fallback to constructed path
                    }
                    
                    $result['extracted_files'][] = array(
                        'original_zip' => $file_name,
                        'extracted_file' => $extracted_file,
                        'path' => $absolute_path,
                        'keep_files' => $keep_files
                    );
                }

            } else {
                // Non-ZIP file - check if it's a primary geospatial file
                if (!$this->is_primary_geospatial_file($file_name)) {
                    error_log("Skipping non-primary geospatial file: {$file_name}");
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
                    // Get absolute path for the moved file
                    $absolute_path = realpath($destination);
                    if (!$absolute_path) {
                        error_log("Warning: Could not resolve absolute path for: {$destination}");
                        $absolute_path = $destination; // Fallback to constructed path
                    }
                    
                    $result['processed_files'][] = array(
                        'original_name' => $file_name,
                        'path' => $absolute_path,
                        'keep_files' => $keep_files
                    );
                } else {
                    $result['errors'][] = "Failed to move file '{$file_name}' to project folder";
                }
            }
        }

        $result['success'] = empty($result['errors']);
        $result['keep_files'] = $keep_files; // Store preference for later use
        return $result;
    }

    /**
     * Delete files after metadata extraction if keep_files is false
     * 
     * @param array $files Array of file paths to potentially delete
     * @param bool $keep_files Whether to keep files
     * @return array Result with deletion status
     */
    public function cleanup_files_after_extraction($files, $keep_files = false)
    {
        $result = array(
            'deleted' => array(),
            'kept' => array(),
            'errors' => array()
        );
        
        if ($keep_files) {
            // Files should be kept
            foreach ($files as $file_path) {
                $result['kept'][] = $file_path;
            }
            return $result;
        }
        
        // Delete files and related files
        foreach ($files as $file_path) {
            if (!file_exists($file_path)) {
                continue;
            }
            
            $file_name = basename($file_path);
            $folder_path = dirname($file_path);
            
            // Delete main file
            if (unlink($file_path)) {
                $result['deleted'][] = $file_path;
                log_message('info', "Deleted file after extraction: {$file_path}");
            } else {
                $result['errors'][] = "Failed to delete file: {$file_path}";
            }
            
            // Delete related files (for any file type)
            $this->delete_related_files_for_any_type($folder_path, $file_name);
        }
        
        return $result;
    }

    /**
     * Delete related files for any file type (not just shapefiles)
     * 
     * @param string $folder_path Folder containing the files
     * @param string $file_name File name
     */
    private function delete_related_files_for_any_type($folder_path, $file_name)
    {
        try {
            $base_name = pathinfo($file_name, PATHINFO_FILENAME);
            
            // Find all files with the same base name
            $pattern = $folder_path . '/' . $base_name . '.*';
            $related_files = glob($pattern);
            
            foreach ($related_files as $related_file) {
                // Skip the main file itself (already deleted)
                if (basename($related_file) === $file_name) {
                    continue;
                }
                
                // Skip if file doesn't exist
                if (!file_exists($related_file)) {
                    continue;
                }
                
                // Delete related file
                if (unlink($related_file)) {
                    log_message('info', "Deleted related file: {$related_file}");
                }
            }
            
        } catch (Exception $e) {
            log_message('error', "Error deleting related files for {$file_name}: " . $e->getMessage());
        }
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
