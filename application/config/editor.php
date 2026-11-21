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

//Path for storing user-defined schemas
$config['editor']['user_schema_path']=rtrim($config['editor']['storage_path'],'/').'/user-schemas';

//Python fastapi server url; url must end with a slash [default - http://localhost:8000/]
$config['editor']['data_api_url']=getenv('EDITOR_DATA_API_URL') ? getenv('EDITOR_DATA_API_URL') : 'http://localhost:8000/';
//Path for core editor form templates (JSON, shipped with the application)
$config['editor']['core_template_path']=APPPATH.'editor_templates';

//Server assistant: a chat panel that answers questions about the editor and the open project, drafts
//field values from the project's documents and reviews a record. It runs on the FastAPI service
//(data_api_url) through api/assistant/*; needs the assistant installed there (README-assistant.md).
//Nothing is saved without the curator: a draft is typed into the form, unsaved. [default - false]
$config['editor']['server_assistant_enabled']=false;

//Chat turns per minute per session (0 = no limit). The service has its own concurrency limit.
$config['editor']['server_assistant_rate_per_minute']=20;

//Per-field preferred values: JSON Pointer => list of values. A field listed here may only be filled
//with one of its values (this list wins over template options). Left empty, one suggestion is derived
//for an indicator's measurement unit from the unit written in the indicator name, e.g. "(% of population)".
$config['editor']['server_assistant_field_suggestions']=array(
);

//Fields the assistant may read and propose values for when a project's template cannot be read,
//by JSON Pointer => label. With a readable template, every field of the template is used instead.
$config['editor']['server_assistant_fields']=array(
	'/study_desc/title_statement/title'=>'Study title',
	'/study_desc/study_info/abstract'=>'Abstract',
	'/study_desc/study_info/universe'=>'Universe',
	'/study_desc/study_info/analysis_unit'=>'Analysis unit',
	'/study_desc/study_info/geog_coverage'=>'Geographic coverage',
	'/doc_desc/description'=>'Document description',
	'/series_description/name'=>'Indicator name',
	'/series_description/display_name'=>'Indicator display name',
	'/series_description/definition_short'=>'Indicator definition (short)',
	'/series_description/definition_long'=>'Indicator definition (long)',
	'/series_description/measurement_unit'=>'Indicator measurement unit',
	'/series_description/universe'=>'Indicator universe',
	'/series_description/statistical_concept'=>'Indicator statistical concept',
);
