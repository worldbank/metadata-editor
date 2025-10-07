<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$menu=array();
$menu[]=array(
			'title'	=>'Dashboard',
			'url'	=>'admin',
		);


$menu[]=array(
	'title'	=>'Users',
	'url'	=>'admin/users',
	'items'	=>array(
		array(
			'title'	=>'All users',
			'url'	=>'admin/users'
		),
		array(
			'title'	=>'Add user',
			'url'	=>'admin/users/add'
		),
		/*array(
			'title'	=>'Impersonate user',
			'url'	=>'admin/users/impersonate'
		)*/
	)
);


$menu[]=array(
	'title'	=>'Settings',
	'url'	=>'admin/configurations',
	'items'	=>array(
		array(
			'title'	=>'Settings',
			'url'	=>'admin/configurations'
		),
		array(
			'type'	=>'divider'
		),
		array(
			'title'	=>'Translate',
			'url'	=>'admin/translate'
		),
		array(
			'type'	=>'divider'
		),
		array(
			'title'	=>'Audit Logs',
			'url'	=>'admin/audit_logs'
		)
	)
);

$config['site_menu']=$menu;
/* End of file site_menu.php */
/* Location: ./system/application/config/site_menu.php */