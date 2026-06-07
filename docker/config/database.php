<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$docker_bool = function ($name, $default = false) {
    $value = getenv($name);

    if ($value === false || $value === '') {
        return $default;
    }

    return in_array(strtolower($value), array('1', 'true', 'yes', 'on'), true);
};

$docker_env = function ($name, $default = '') {
    $value = getenv($name);

    return ($value === false || $value === '') ? $default : $value;
};

$active_group = 'default';
$query_builder = TRUE;

$db_host = $docker_env('DB_HOST', 'db');
$db_port = $docker_env('DB_PORT', '');

if ($db_port !== '') {
    $db_host .= ':' . $db_port;
}

$db['default'] = array(
    'dsn'       => '',
    'hostname'  => $db_host,
    'username'  => $docker_env('DB_USERNAME', 'metadata_editor'),
    'password'  => $docker_env('DB_PASSWORD', 'metadata_editor'),
    'database'  => $docker_env('DB_DATABASE', 'metadata_editor'),
    'dbdriver'  => $docker_env('DB_DRIVER', 'mysqli'),
    'dbprefix'  => '',
    'pconnect'  => FALSE,
    'db_debug'  => $docker_bool('DB_DEBUG', false),
    'cache_on'  => FALSE,
    'cachedir'  => '',
    'char_set'  => 'utf8mb4',
    'dbcollat'  => 'utf8mb4_unicode_ci',
    'swap_pre'  => '',
    'encrypt'   => FALSE,
    'compress'  => FALSE,
    'stricton'  => FALSE,
    'failover'  => array(),
    'save_queries' => TRUE
);
