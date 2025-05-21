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

/* End of file config.php */
/* Location: ./system/application/config/config.php */