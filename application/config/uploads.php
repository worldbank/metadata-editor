<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Resumable File Upload Configuration
|--------------------------------------------------------------------------
|
| resumable_upload_temp_path - Temporary storage path for chunked uploads
|   Can be relative (to FCPATH) or absolute
|   Default: 'datafiles/tmp/uploads'
|
| resumable_upload_max_size - Maximum file size in bytes (0 = unlimited)
|   Default: 0 (unlimited)
|
| resumable_upload_chunk_size - Recommended chunk size in bytes
|   Default: 10485760 (10MB)
|   Note: Actual maximum is automatically limited by PHP's post_max_size
|         and upload_max_filesize settings (whichever is smaller)
|
| resumable_upload_expiry_hours - Hours before uploads are cleaned up
|   Default: 1
|
| Note: File type validation uses 'allowed_resource_types' from config.php
|
*/
$config['resumable_upload_temp_path'] = 'datafiles/tmp/uploads';
$config['resumable_upload_max_size'] = 0; // 0 = unlimited
$config['resumable_upload_chunk_size'] = 10485760; // 10MB
$config['resumable_upload_expiry_hours'] = 1;

/*
|--------------------------------------------------------------------------
| Microdata ZIP import
|--------------------------------------------------------------------------
|
| microdata_zip_max_entries - Maximum total entries in a ZIP (files + folders)
| microdata_upload_allowed_types - Allowed extensions for resumable data uploads
|
*/
$config['microdata_zip_max_entries'] = 100;
$config['microdata_upload_allowed_types'] = 'dta,sav,csv,zip';
