<?php

defined('BASEPATH') OR exit('No direct script access allowed');


/*
||--------------------------------------------------------------------------
|| Authentication providers
||--------------------------------------------------------------------------
||
|| List of supported authentication providers
||
||
||
*/
$config['authentication_drivers'] = array(
    'DefaultAuth'   => 'application/libraries/Auth/DefaultAuth.php',
    'OidcAuth'      => 'application/libraries/Auth/OidcAuth.php',
    'OidcAuthSpa'   => 'application/libraries/Auth/OidcAuthSpa.php',
    'ZeroAuth'      => 'application/libraries/Auth/ZeroAuth.php',
);


/*
||--------------------------------------------------------------------------
|| Set active authentication
||--------------------------------------------------------------------------
||
|| Set authentication provider to use
||
*/
$config['authentication_driver'] = 'DefaultAuth';


/*
||--------------------------------------------------------------------------
|| OIDC Authentication Config options for OidcAuth and OidcAuthSpa drivers
||--------------------------------------------------------------------------
||
|| Configurations for OIDC (OpenID Connect) authentication
||
|| When OidcAuth driver is used, these settings control the authentication
|| behavior and UI display options.
||
*/

/*
||--------------------------------------------------------------------------
|| ZeroAuth – local/desktop mode (no password, one-click login)
||--------------------------------------------------------------------------
||
|| Set authentication_driver to ZeroAuth and enabled to true for local/desktop
|| builds only. Login is one-click; admin_email is an internal bootstrap key.
||
*/
$config['zero_auth'] = array(
    'enabled'       => false,
    'admin_name'    => 'Local Administrator',
    'admin_email'   => 'local-admin@localhost',
    'allowed_hosts' => array('localhost', '127.0.0.1'),
);

// Load OIDC configuration file - config/auth_oidc.php
$auth_oidc_file = APPPATH . 'config/auth_oidc.php';
if (file_exists($auth_oidc_file)) {
    require $auth_oidc_file;
}