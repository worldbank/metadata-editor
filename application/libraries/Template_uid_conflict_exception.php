<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Thrown when creating a template whose UID matches a soft-deleted template.
 */
class Template_uid_conflict_exception extends Exception
{
	private $conflict_code;
	private $conflict_data;

	public function __construct($uid, array $deleted_template = array())
	{
		$this->conflict_code = 'TEMPLATE_UID_DELETED';
		$this->conflict_data = array(
			'uid' => $uid,
			'name' => isset($deleted_template['name']) ? $deleted_template['name'] : null,
			'deleted_at' => isset($deleted_template['deleted_at']) ? $deleted_template['deleted_at'] : null,
			'deleted_by' => isset($deleted_template['deleted_by']) ? $deleted_template['deleted_by'] : null,
		);

		$message = "Template with UID '{$uid}' already exists [DELETED]. "
			. "To reuse this UID, restore the deleted template or permanently delete it.";

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
