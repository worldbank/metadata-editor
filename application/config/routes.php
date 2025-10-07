<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'projects';
$route['404_override'] = 'page';
$route['translate_uri_dashes'] = FALSE;

$route['switch_language/(.*)'] = "page/switch_language/$1";
$route['home'] = "page/home";
$route['about'] = "page/about";

$route['editor'] = "projects";
$route['editor/(.*)'] = "projects/$1";
$route['api/geospatial-features'] = "api/geospatial_features";
$route['api/geospatial-features/(.*)'] = "api/geospatial_features/$1";


//admin paths
$route['admin'] = "admin/admin";
$route['admin/permissions/(:num)'] = "admin/permissions/index/$1";

$route['api/admin-metadata'] = "api/admin_metadata";
//$route['api/admin-metadata/type'] = "api/metadata/type";
$route['api/admin-metadata/(.*)'] = "api/admin_metadata/$1";

//versions
$route['api/editor/versions'] = "api/versions";
$route['api/editor/(.*)/versions'] = "api/versions/$1";
$route['api/editor/(.*)/versions/(.*)'] = "api/versions/index/$1/$2";
$route['api/editor/versions/(.*)'] = "api/versions/$1";

//project comparison
$route['api/editor/compare'] = "api/compare";
$route['api/editor/compare/(.*)'] = "api/compare/$1";


/* End of file routes.php */
/* Location: ./system/application/config/routes.php */
