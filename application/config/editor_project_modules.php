<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Project editor modules (template manager + project editor UI)
|--------------------------------------------------------------------------
|
| Catalog of optional editor surfaces per project data type. Templates store
| sparse overrides under template.editor_modules[module id]. Missing entry
| or missing show_in_editor => enabled. Only show_in_editor: false hides.
|
| Module ids are stable product keys (feature_catalogue, geospatial_gallery,
| external_resources, …). Field layouts stay in template.items; this config
| only controls project editor navigation / dedicated screens.
|
*/

$config['editor_project_modules'] = array(
	'modules' => array(
		array(
			'id' => 'feature_catalogue',
			'data_types' => array('geospatial'),
			'label_key' => 'editor_module_feature_catalogue',
		),
		array(
			'id' => 'geospatial_gallery',
			'data_types' => array('geospatial'),
			'label_key' => 'editor_module_geospatial_gallery',
		),
	),
);
