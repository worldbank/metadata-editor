<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Notification maintenance.
 *
 * Usage:
 *   php index.php cli/notifications/prune
 */
class Notifications extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

		if (php_sapi_name() !== 'cli') {
			show_error('This controller can only be accessed via CLI');
			exit(1);
		}

		$this->load->database();
		$this->load->helper('notification');
		$this->load->model('User_notifications_model');
	}

	/**
	 * Delete in-app notifications older than the site retention window.
	 */
	public function prune()
	{
		$days = notification_retention_days();
		$cutoff = notification_retention_cutoff();
		$deleted = $this->User_notifications_model->prune_older_than($cutoff);
		echo 'Pruned ' . $deleted . ' notification(s) older than ' . $days . ' day(s).' . PHP_EOL;
	}
}
