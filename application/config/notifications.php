<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| In-app notification types
|--------------------------------------------------------------------------
|
| Registry for Notification_service. Add a type here when a feature starts
| emitting events. The inbox UI is type-agnostic; unknown types are skipped
| at write time (logged, caller is not failed).
|
| in_app: insert a user_notifications row immediately
| email:  'digest' = include in Phase B daily cron; never send inline
|
| Retention: Site configurations `notifications_retention_days` (default 30).
| Inbox queries only return rows newer than that window. Prune via:
|   php index.php cli/notifications/prune
|
*/

$config['notification_types'] = array(
	'publish.project_ready' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'publish.queue_resolved' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'project.access_granted' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'project.access_updated' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'project.access_revoked' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'project.ownership_transferred' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'collection.access_granted' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'collection.access_updated' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'collection.access_revoked' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'collection.project.access_granted' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'collection.project.access_updated' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'collection.project.access_revoked' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'template.access_granted' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'template.access_updated' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'template.access_revoked' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'admin_metadata.access_granted' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'admin_metadata.access_updated' => array(
		'in_app' => true,
		'email' => 'digest',
	),
	'admin_metadata.access_revoked' => array(
		'in_app' => true,
		'email' => 'digest',
	),
);
