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

//R OpenCPU API base URL
$config['editor']['r_api_url']='http://localhost:2121/ocpu/library/';

//Folder for R to expore/write files
$config['editor']['r_storage_path']='datafiles/r';



$config['editor']['data_api_url']='http://localhost:8000/';//end with slash

//folder for data to be stored by the data api
$config['editor']['data_storage_path']='datafiles/r';//end with slash