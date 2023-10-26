<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Configurations for Metadata editor
|--------------------------------------------------------------------------
|
|
*/

//Storage root folder for editor
$config['editor']['storage_path']='datafiles/editor';

//Python fastapi server url
$config['editor']['data_api_url']='http://localhost:8000/';//end with slash

//todo remove
$config['editor']['data_storage_path']='datafiles/r';//end with slash