<?php
/**
 * Legacy ReDoc entry point.
 *
 * Dispatches through CodeIgniter so servers/$ref URLs use site_url()/base_url()
 * (correct HTTPS behind TLS-terminating proxies).
 */

$fcpath = dirname(__DIR__, 2);
chdir($fcpath);

$script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/api-documentation/editor/openapi.php';
$prefix = preg_replace('#/api-documentation/editor/openapi\.php$#', '', $script);
if ($prefix === null) {
    $prefix = '';
}

$_SERVER['SCRIPT_NAME'] = $prefix . '/index.php';
$_SERVER['PHP_SELF'] = $prefix . '/index.php/schema_openapi/spec';
$_SERVER['PATH_INFO'] = '/schema_openapi/spec';
$_SERVER['REQUEST_URI'] = $prefix . '/index.php/schema_openapi/spec'
    . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

require $fcpath . '/index.php';
