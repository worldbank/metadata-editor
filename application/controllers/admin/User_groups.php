<?php
/**
 * Legacy NADA "user groups" routes. This application uses the roles tables
 * (roles, user_roles, role_permissions) managed under admin/permissions.
 */
class User_groups extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->lang->load('general');
		$this->lang->load('user_groups');
	}

	public function _remap($method, $params = array())
	{
		if ($method === 'edit' && !empty($params[0]) && is_numeric($params[0])) {
			redirect('admin/permissions/edit_role/' . $params[0]);
			return;
		}

		if ($method === 'delete' && !empty($params[0]) && is_numeric($params[0])) {
			redirect('admin/permissions/delete_role/' . $params[0]);
			return;
		}

		redirect('admin/permissions/roles');
	}
}
