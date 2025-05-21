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

//Python fastapi server url; url must end with a slash [default - http://localhost:8000/]
$config['editor']['data_api_url']=getenv('EDITOR_DATA_API_URL') ? getenv('EDITOR_DATA_API_URL') : 'http://localhost:8000/';