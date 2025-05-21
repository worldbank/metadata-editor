<?php

defined('BASEPATH') OR exit('No direct script access allowed');


/*
|--------------------------------------------------------------------------
| Authentication providers
|--------------------------------------------------------------------------
|
| List of supported authentication providers
|
|
|
*/
$config['authentication_drivers'] = array(
    'DefaultAuth'   => 'application/libraries/Auth/DefaultAuth.php',
    'SsoAuth'       => 'application/libraries/Auth/SsoAuth.php',
    'AzureAuth'     => 'application/libraries/Auth/AzureAuth.php'        
);


/*
|--------------------------------------------------------------------------
| Set active authentication
|--------------------------------------------------------------------------
|
| Set authentication provider to use
|
*/
$config['authentication_driver'] = 'DefaultAuth';



/*
|--------------------------------------------------------------------------
| AzureAuth Config options
|--------------------------------------------------------------------------
|
| Configurations for AzureAuth
|

* OAuth 2.0 Endpoints  
*
* Authorization endpoint (v1) 
* https://login.microsoftonline.com/{tenent-id}/oauth2/authorize  
* 
* Token endpoint (v1) 
* https://login.microsoftonline.com/{tenent-id}/oauth2/token 
*
* Logout endpoint (v1) 
* https://login.microsoftonline.com/{tenent-id}/oauth2/logout 
*
* Authorization endpoint (v2) 
* https://login.microsoftonline.com/{tenent-id}/oauth2/v2.0/authorize  
*
*
* Token endpoint (v2) 
* https://login.microsoftonline.com/{tenent-id}/oauth2/v2.0/token 
*
* Microsoft Graph API endpoint 
* https://graph.microsoft.com 

* Login request format
* https://login.microsoftonline.com/{tenant-id}/oauth2/authorize?client_id={client-id}&response_mode=form_post&response_type=code%20id_token&nonce=any-random-value


*/

$config['azure_auth']['client_id']='';
$config['azure_auth']['tenant_id']='';
$config['azure_auth']['authorize_endpoint']='https://login.microsoftonline.com/'.$config['azure_auth']['tenant_id'].'/oauth2/authorize';
$config['azure_auth']['token_endpoint'] = 'https://login.microsoftonline.com/'.$config['azure_auth']['tenant_id'].'/oauth2/token';
$config['azure_auth']['logout_endpoint'] = 'https://login.microsoftonline.com/'.$config['azure_auth']['tenant_id'].'/oauth2/logout';
