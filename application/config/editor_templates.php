<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Metadata editor core templates
|--------------------------------------------------------------------------
|
| This file should have the template configurations for each data type
| such as survey, geospatial, time series, dublin core, etc.
|
|
| @template - path to the view file 
| 
| @language_translations - language file containing the translations for fields names/labels
|
*/

$config['survey']=array(
        'template' => 'metadata_editor/metadata_editor_templates/survey_form_template.json',
        'lang'=>'en'
); 

$config['timeseries']=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries_form_template.json',
    'lang'=>'en'
); 

$config['timeseries-db']=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries-db_form_template.json',
    'lang'=>'en'
); 


$config['script']=array(
    'template' => 'metadata_editor/metadata_editor_templates/script_form_template.json',
    'lang'=>'en'
); 


//geospatial
$config['geospatial']=array(
        'template' => 'metadata_editor/metadata_editor_templates/geospatial_form_template.json',
        'lang'=>'en'
);

//document
$config['document']=array(
    'template' => 'metadata_editor/metadata_editor_templates/document_form_template.json',
    'lang'=>'en'
);

//table
$config['table']=array(
    'template' => 'metadata_editor/metadata_editor_templates/table_form_template.json',
    'lang'=>'en'
); 

//image
$config['image']=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_form_template.json',
    'language_translations'=>'en'
); 


//visualization
$config['visualization']=array(
    'template' => 'metadata_templates/visualization-template',
    'language_translations'=>'fields_visualization'
); 

//video
$config['video']=array(
    'template' => 'metadata_editor/metadata_editor_templates/video_form_template.json',
    'language_translations'=>'en'
); 
