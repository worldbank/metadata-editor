<?php
/**
 * Dynamic OpenAPI YAML wrapper.
 *
 * Serves openapi.yaml with servers resolved for this install and external schema
 * $ref values rewritten to the openapi_schema/ controller so schemas load from
 * application/schemas/.
 */

$openapi_yaml = file_get_contents(__DIR__ . '/openapi.yaml');

if ($openapi_yaml === false) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error: openapi.yaml not found');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$app_path = preg_replace('#/api-documentation/.*$#', '/', $_SERVER['REQUEST_URI'] ?? '/');
$base_url = $scheme . '://' . $host . $app_path . 'index.php/api/';
$schemas_base = $scheme . '://' . $host . rtrim($app_path, '/') . '/index.php/openapi_schema/';

$servers_block = "servers:\n  - url: " . $base_url . "\n    description: API Server\n";

$openapi_yaml = preg_replace('/^servers:.*?(?=^\S)/ms', $servers_block . "\n", $openapi_yaml);

$openapi_yaml = preg_replace_callback(
    '/^(\s*)(-\s*)?(\$ref:\s*)(.+)$/m',
    function ($m) use ($schemas_base) {
        $indent = $m[1];
        $list = isset($m[2]) ? $m[2] : '';
        $prefix = $m[3];
        $raw = trim($m[4]);
        if ($raw === '' || $raw[0] === '#') {
            return $m[0];
        }

        $value = $raw;
        if ($value[0] === '"' || $value[0] === "'") {
            if (substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            }
        }

        if ($value === '' || $value[0] === '#') {
            return $m[0];
        }

        if (!preg_match('/^(?:[^\/]+\/)*([a-zA-Z0-9_-]+\.json)(#.*)?$/', $value, $parts)) {
            return $m[0];
        }

        $url = $schemas_base . $parts[1] . (isset($parts[2]) ? $parts[2] : '');
        $quoted = "'" . str_replace("'", "''", $url) . "'";

        return $indent . $list . $prefix . $quoted;
    },
    $openapi_yaml
);

header('Content-Type: application/x-yaml; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo $openapi_yaml;
