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

/*
|--------------------------------------------------------------------------
| Org email domain equivalence (global — all auth drivers)
|--------------------------------------------------------------------------
|
| During phased email-domain / UPN migration, treat listed domains as the
| same organization when resolving a login email to a user account. The
| local-part (before @) must match; only the domain may differ.
|
| Applies to password login, forgot password, self-registration, and OIDC SSO when
| linking by email. 
|
| Parameters:
|
|   domains
|     Symmetric list of org domains (e.g. demo.org, example.com). Cross-domain
|     matching only considers users whose email domain is in this list.
|
|   local_part_cross_domain
|     If true, when exact email fails, find users with the same local-part
|     and a domain in domains. 
|
|   require_unique_local_part
|     If true, cross-domain match requires exactly one user; otherwise login
|     fails (ambiguous duplicate accounts). Recommended when duplicates exist.
|
| Cross-domain matching applies whether or not the account is already linked
| via OIDC, so password login with an old org domain still resolves after SSO.
|
*/
$config['email_domain_equivalence'] = array(
    'enabled' => false,
    'domains' => array(
        // 'demo.org',
        // 'example.com',
    ),
    'local_part_cross_domain' => true,
    'require_unique_local_part' => true,
);

// Load OIDC configuration file - config/auth_oidc.php
$auth_oidc_file = APPPATH . 'config/auth_oidc.php';
if (file_exists($auth_oidc_file)) {
    require $auth_oidc_file;
}