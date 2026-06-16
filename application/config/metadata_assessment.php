<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Metadata Quality Assessment API
|--------------------------------------------------------------------------
|
| Configuration for the external metadata quality assessment service.
| The service accepts project metadata, runs assessment in a job queue,
| and returns results via a stream (SSE) keyed by event_id.
|
| Submit:  POST {base_url}          -> returns event_id
| Result:  GET  {base_url}/{event_id} -> stream (SSE) until completion
|
*/

// Base URL of the assessment API endpoint (no trailing slash).
// Submit and result URLs are: base_url (POST) and base_url/event_id (GET).
$config['metadata_assessment']['base_url'] = getenv('METADATA_ASSESSMENT_API_URL')
	? rtrim(getenv('METADATA_ASSESSMENT_API_URL'), '/')
	: '';

// Connect timeout in seconds for the submit request
$config['metadata_assessment']['submit_connect_timeout'] = (int) (getenv('METADATA_ASSESSMENT_SUBMIT_CONNECT_TIMEOUT') ?: 30);

// Read timeout in seconds for the submit request
$config['metadata_assessment']['submit_read_timeout'] = (int) (getenv('METADATA_ASSESSMENT_SUBMIT_READ_TIMEOUT') ?: 30);

// Read timeout in seconds when reading the result stream (worker may run several minutes)
$config['metadata_assessment']['stream_read_timeout'] = (int) (getenv('METADATA_ASSESSMENT_STREAM_READ_TIMEOUT') ?: 1000);

// Optional: API key or token for authentication (leave empty if not required)
$config['metadata_assessment']['api_key'] = getenv('METADATA_ASSESSMENT_API_KEY') ?: '';

// Optional: HTTP header name for the API key (e.g. 'X-API-Key', 'Authorization')
$config['metadata_assessment']['api_key_header'] = getenv('METADATA_ASSESSMENT_API_KEY_HEADER') ?: '';
