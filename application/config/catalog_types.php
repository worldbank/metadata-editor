<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Catalog type registry (shipped publish forms + capabilities)
|--------------------------------------------------------------------------
|
| publish_form: path relative to APPPATH, or null when type has no options.
| Future addons register new types here and ship a publish-form.json.
|
*/

$config['catalog_types'] = array(
	'nada' => array(
		'label' => 'NADA',
		'publish_form' => 'catalog_types/nada/publish-form.json',
		'builtin_push' => true,
		'requires_url' => true,
		'requires_credential' => true,
	),
	'other' => array(
		'label' => 'Other',
		'publish_form' => null,
		'builtin_push' => false,
		'requires_url' => false,
		'requires_credential' => false,
	),
);
