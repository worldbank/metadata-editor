<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Configurations values to store in the DB
|--------------------------------------------------------------------------
|
| This file lists all the required configuration settings that must be stored 
| in the database. If a setting is not in the DB, it will be created automatically if 
| included in this file
|
*/

$config['catalog_root']='datafiles';
$config['ddi_import_folder']='imports';

//default cache expiration in seconds
$config['cache_default_expires'] = 60*60*2;//2 hours

//To disable cache set value to 1
$config['cache_disabled'] = 1;

//site's default language
$config['language'] = 'english';

//enabled languages (JSON array)
$config['supported_languages'] = json_encode(array(
    array('folder' => 'english', 'code' => 'en', 'display' => 'English',  'direction' => 'ltr'),
    array('folder' => 'french',  'code' => 'fr', 'display' => 'Français', 'direction' => 'ltr'),
    array('folder' => 'spanish', 'code' => 'es', 'display' => 'Español',  'direction' => 'ltr'),
    array('folder' => 'uzbek',   'code' => 'uz', 'display' => 'Uzbek',    'direction' => 'ltr'),
));

// Default roles for newly registered / activated users (JSON array of role names)
$config['default_user_roles'] = json_encode(array('User', 'Editor'));

// Allow project owners to share projects with other users ('1' = enabled, '0' = disabled)
$config['project_sharing'] = '1';

// Metadata assessment (Issues → Assess metadata); requires FastAPI review routes and background worker ('1' = enabled, '0' = disabled)
$config['metadata_assessment_enabled'] = '0';

// Site-wide monthly limit for metadata assessments (0 = unlimited, no limit enforced)
$config['metadata_assessment_monthly_limit'] = '200';

/* End of file config.php */
/* Location: ./system/application/config/config.php */