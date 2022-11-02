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
    'template' => 'metadata_templates/timeseries-template',
    'language_translations'=>'fields_timeseries'
); 

$config['timeseriesdb']=array(
    'template' => 'metadata_templates/timeseriesdb-template',
    'language_translations'=>'fields_timeseriesdb'
); 


$config['script']=array(
    'template' => 'metadata_templates/script-template',
    'language_translations'=>'fields_scripts'
); 


//geospatial template/view
$config['geospatial']=array(
        'template' => 'metadata_templates/geospatial-iso19139',
        'language_translations'=>'iso19139_fields'
);

//document
$config['document']=array(
    'template' => 'metadata_templates/document-template',
    'language_translations'=>'fields_document'
); 

//table
$config['table']=array(
    'template' => 'metadata_templates/table-template',
    'language_translations'=>'fields_table'
); 

//image
$config['image']=array(
    'template' => 'metadata_templates/image-template',
    'language_translations'=>'fields_image'
); 


//visualization
$config['visualization']=array(
    'template' => 'metadata_templates/visualization-template',
    'language_translations'=>'fields_visualization'
); 

//video
$config['video']=array(
    'template' => 'metadata_templates/video-template',
    'language_translations'=>'fields_video'
); 
