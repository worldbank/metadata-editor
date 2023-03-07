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

$config['survey'][]=array(
        'template' => 'metadata_editor/metadata_editor_templates/survey_form_template.json',
        'lang'=>'en',        
        'uid'=>'microdata-system-en',
        'name'=>'Microdata DDI 2.5 EN'
);

$config['timeseries'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries_form_template.json',
    'lang'=>'en',
    'uid'=>'timeseries-system-en',
    'name'=>'Indicator IHSN Schema 1.0 EN',
    'version'=>'1.0',
    'description'=>'This template contains all elements available in the metadata schema developed by the IHSN for the documentation of indicators and time series of indicators. It can be used as basis for the production of user templates consisting of a subset of the available elements, with customized labels, instructions, ordering and grouping of metadata elements, controlled vocabularies, validation rules, and default values.'
); 

$config['timeseries-db'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/timeseries-db_form_template.json',
    'lang'=>'en',
    'uid'=>'timeseries-db-system-en',
    'name'=>'Database IHSN Schema 1.0 EN'
); 


$config['script'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/script_form_template.json',
    'lang'=>'en',
    'uid'=>'script-system-en',
    'name'=>'Script IHSN Schema 1.0 EN'
); 


//geospatial
$config['geospatial'][]=array(
        'template' => 'metadata_editor/metadata_editor_templates/geospatial_form_template.json',
        'lang'=>'en',
        'uid'=>'geospatial-system-en',
        'name'=>'Geospatial schema'
);

//document
$config['document'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/document_form_template.json',
    'lang'=>'en',
    'uid'=>'document-system-en',
    'name'=>'Document IHSN Schema 1.0 EN'
);

//table
$config['table'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/table_form_template.json',
    'lang'=>'en',
    'uid'=>'table-system-en',
    'name'=>'Table IHSN Schema 1.0 EN'
); 

//image
$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_form_template.json',
    'lang'=>'en',
    'uid'=>'image-system-en',
    'name'=> 'Image IHSN Schema (DCMI and IPTC)'
); 

$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_dcmi_form_template.json',
    'lang'=>'en',
    'uid'=>'image-system-dcmi',
    'name'=> 'Image IHSN Schema (DCMI option) 1.0 EN'
); 

$config['image'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/image_iptc_form_template.json',
    'lang'=>'en',
    'uid'=>'image-system-iptc',
    'name'=> 'Image IHSN Schema (IPTC option) 1.0 EN'
); 



//visualization
/*$config['visualization']=array(
    'template' => 'metadata_templates/visualization-template',
    'language_translations'=>'fields_visualization'
);*/ 

//video
$config['video'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/video_form_template.json',
    'lang'=>'en',
    'uid'=>'video-system-en',
    'name'=>'Video IHSN Schema 1.0 EN'
); 


$config['resource'][]=array(
    'template' => 'metadata_editor/metadata_editor_templates/resource_form_template.json',
    'lang'=>'en',
    'uid'=>'resource-system-en',
    'name'=>'resource-system-en'

); 
