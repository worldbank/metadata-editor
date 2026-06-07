<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['editor']['storage_path'] = getenv('EDITOR_STORAGE_PATH') ?: 'datafiles/editor';
$config['editor']['data_api_url'] = getenv('EDITOR_DATA_API_URL') ?: 'http://fastapi:8000/';
