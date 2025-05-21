<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
|--------------------------------------------------------------------------
| Active template group
|--------------------------------------------------------------------------
|
| The $template['active_template'] setting lets you choose which template
| group to make active.  By default there is only one group (the
| "default" group).
|
*/
$template['active_template'] = 'default';
$template['theme_name']='nada52';


/*
|--------------------------------------------------------------------------
| Multi site hosting [DO NOT EDIT SETTTINGS BELOW]
|--------------------------------------------------------------------------
|
| These settings are used for sharing single code base for multi site
| hosting, for more details on how to setup multiple sites using single
| instance of nada code:
|
| --todo -- put link to the documentation here
*/

//set active theme name to use
$theme_name=defined('THEME_NAME') ? THEME_NAME : $template['theme_name'] ;

//set base url for JS and CSS files
$template['base_url']=defined('JS_BASE_URL') ? JS_BASE_URL : base_url();




/*
|--------------------------------------------------------------------------
| Explanation of template group variables
|--------------------------------------------------------------------------
|
| ['template'] The filename of your master template file in the Views folder.
|   Typically this file will contain a full XHTML skeleton that outputs your
|   full template or region per region. Include the file extension if other
|   than ".php"
| ['regions'] Places within the template where your content may land.
|   You may also include default markup, wrappers and attributes here
|   (though not recommended). Region keys must be translatable into variables
|   (no spaces or dashes, etc)
| ['parser'] The parser class/library to use for the parse_view() method
|   NOTE: See http://codeigniter.com/forums/viewthread/60050/P0/ for a good
|   Smarty Parser that works perfectly with Template
| ['parse_template'] FALSE (default) to treat master template as a View. TRUE
|   to user parser (see above) on the master template
|
| Region information can be extended by setting the following variables:
| ['content'] Must be an array! Use to set default region content
| ['name'] A string to identify the region beyond what it is defined by its key.
| ['wrapper'] An HTML element to wrap the region contents in. (We
|   recommend doing this in your template file.)
| ['attributes'] Multidimensional array defining HTML attributes of the
|   wrapper. (We recommend doing this in your template file.)
|
| Example:
| $template['default']['regions'] = array(
|    'header' => array(
|       'content' => array('<h1>Welcome</h1>','<p>Hello World</p>'),
|       'name' => 'Page Header',
|       'wrapper' => '<div>',
|       'attributes' => array('id' => 'header', 'class' => 'clearfix')
|    )
| );
|
*/

/*
|--------------------------------------------------------------------------
| Admin Template Configuration
|--------------------------------------------------------------------------
*/
//installer template file with only the body and no layout
$template['installer']['template'] = '../../themes/nada52/installer';
$template['installer']['theme_folder'] = 'nada52';


//blank template file with only the body and no layout
$template['blank']['template'] = '../../themes/nada52/blank';
$template['blank']['theme_folder'] = 'nada52';

$template['blank_iframe']['template'] =  '../../themes/wb2/blank';
$template['blank_iframe']['theme_folder'] = 'wb2';


//box template file with only the body and no layout
$template['box']['template'] = '../../themes/opendata/box';
$template['box']['theme_folder'] = 'opendata';


//admin template file
$template['admin']['template'] = '../../themes/adminbt/index';
$template['admin']['theme_folder'] = 'adminbt';

//admin template file
$template['admin5']['template'] = '../../themes/adminbt4/index';
$template['admin5']['theme_folder'] = 'adminbt4';


//blank admin template with all stylesheets included
$template['admin_blank']['template'] = '../../themes/adminbt4/blank';
$template['admin_blank']['theme_folder'] = 'adminbt4';


//ddi browser template file
$template['ddibrowser']['template'] = '../../themes/ddibrowser/layout';
$template['ddibrowser']['theme_folder'] = 'ddibrowser';

//for site members
$template['member']['template'] = '../../themes/admin/member';
$template['member']['theme_folder'] = 'admin';

//regions for the admin template
$template['admin']['regions'] = array(
  'header'=>array('content' => array('<h1>heading 1</h1>')),
  'tabs',
  'title',
  'content'=>array('content' => array('<div>content not set</div>')),
  'sidebar',//=>array('content' => array($sidebar_admin)),
  'footer',
);

//ddi browser regions
$template['ddibrowser']['regions'] = array(
  'header',
  'title',
  'content',
  'variable_contents',
  'sidebar',//=>array('content' => array($sidebar_admin)),
  'footer',
  'survey_title',
  'section_url'
);

$template['admin']['parser'] = 'parser';
$template['admin']['parser_method'] = 'parse';
$template['admin']['parse_template'] = FALSE;

/*
|--------------------------------------------------------------------------
| Site Template Configuration
|--------------------------------------------------------------------------
*/

//datadeposit
$template['datadeposit']['template'] = '../../themes/datadeposit/layout';
$template['datadeposit']['theme_folder'] = 'datadeposit';

//default site template
$template['default']['template'] = '../../themes/'.$theme_name.'/layout';
$template['default']['theme_folder'] = $theme_name;

//regions for the site template
$template['default']['regions'] = array(
  'header'=>array('content' => array('<h1>heading 1</h1>')),
  'title',
  'content'=>array('content' => array('<div>content not set</div>')),
  'sidebar',
  'search_filters',
  'footer',
  'breadcrumb',
  'survey_title',
  'section_url',
  'ddi_sidebar'
);

$template['default']['parser'] = 'parser';
$template['default']['parser_method'] = 'parse';
$template['default']['parse_template'] = FALSE;

//documentation: http://williamsconcepts.com/ci/codeigniter/libraries/template/reference.html

/* End of file template.php */
/* Location: ./system/application/config/template.php */
