<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Thrown when creating a template whose UID matches an active template or core UID.
 */
class Template_uid_active_conflict_exception extends Exception
{
	private $conflict_code;
	private $conflict_data;

	public function __construct($uid)
	{
		$this->conflict_code = 'TEMPLATE_UID_ACTIVE';
		$this->conflict_data = array(
			'uid' => $uid,
			'status' => 'active',
		);

		$message = "Template with UID '{$uid}' already exists.";

		parent::__construct($message);
	}

	public function get_conflict_code()
	{
		return $this->conflict_code;
	}

	public function get_conflict_data()
	{
		return $this->conflict_data;
	}
}
