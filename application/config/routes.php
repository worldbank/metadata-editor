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
$route['api/geospatial-features/global-bounds/(:num)'] = "api/geospatial_features/global_bounds/$1";
$route['api/geospatial-features/(:num)/global-bounds'] = "api/geospatial_features/global_bounds/$1";
$route['api/geospatial-features'] = "api/geospatial_features";
$route['api/geospatial-features/(.*)'] = "api/geospatial_features/$1";

// Jobs API aliases - route job-specific endpoints to their implementations
$route['api/jobs/import_microdata/(:num)'] = "api/data/import_microdata/$1";
$route['api/jobs/hold_all'] = "api/jobs/hold_all";
$route['api/jobs/release_all'] = "api/jobs/release_all";
$route['api/jobs/batch/cancel'] = "api/jobs/batch_cancel";
$route['api/jobs/batch/hold'] = "api/jobs/batch_hold";
$route['api/jobs/batch/release'] = "api/jobs/batch_release";
$route['api/jobs/batch/delete'] = "api/jobs/batch_delete";
$route['api/jobs/batch/retry'] = "api/jobs/batch_retry";
$route['api/jobs/(:any)/hold'] = "api/jobs/hold/$1";
$route['api/jobs/(:any)/release'] = "api/jobs/release/$1";
$route['api/jobs/(:any)/retry'] = "api/jobs/retry/$1";
$route['api/jobs/(:any)/cancel'] = "api/jobs/cancel/$1";
$route['api/jobs/(:any)/delete'] = "api/jobs/delete_job/$1";



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

//schemas page
$route['schemas'] = "schemas/index";
$route['schemas/preview/(:any)'] = "schemas/preview/$1";
$route['api/schemas/detail/(:any)'] = "api/schemas/detail/$1";
$route['api/schemas/update/(:any)'] = "api/schemas/update/$1";
$route['api/schemas/files/(:any)'] = "api/schemas/files/$1";
$route['api/schemas/file/(:any)/(:any)'] = "api/schemas/file/$1/$2";
$route['api/schemas/openapi/(:any)'] = "api/schemas/openapi/$1";
$route['api/schemas/compiled_schema/(:any)'] = "api/schemas/compiled_schema/$1";
$route['api/schemas/fields/(:any)'] = "api/schemas/fields/$1";
$route['api/schemas/regenerate_template/(:any)'] = "api/schemas/regenerate_template/$1";

//tags page
$route['tags'] = 'tags/index';

//tags API
$route['api/tags/remove_unused'] = 'api/tags/remove_unused';
$route['api/tags/delete/(:num)'] = 'api/tags/delete_tag/$1';
$route['api/tags/remove_project_tags/(:num)'] = 'api/tags/remove_project_tags/$1';
$route['api/tags/project/(:num)'] = 'api/tags/project/$1';
$route['api/tags'] = 'api/tags';

//validation API
$route['api/validation/(:num)'] = "api/validation/schema/$1";
$route['api/validation/(:num)/schema'] = "api/validation/schema/$1";
$route['api/validation/(:num)/template'] = "api/validation/template/$1";
$route['api/validation/(:num)/variables'] = "api/validation/variables/$1";
$route['api/validation/(:num)/extra_fields'] = "api/validation/extra_fields/$1";
$route['api/validation/(:num)/template_extra_fields'] = "api/validation/template_extra_fields/$1";
$route['api/validation/(:num)/move_to_additional'] = "api/validation/move_to_additional/$1";
$route['api/validation/(:num)/remove_fields'] = "api/validation/remove_fields/$1";
$route['api/validation/(:num)/fix_array_as_object'] = "api/validation/fix_array_as_object/$1";


/* End of file routes.php */
/* Location: ./system/application/config/routes.php */
