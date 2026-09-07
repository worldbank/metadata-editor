<?php

/**
 * Global in-app inbox. One row per recipient per event; read_at NULL = unread.
 */
class User_notifications_model extends CI_Model {

	/**
	 * @param int $user_id recipient
	 * @param string $type
	 * @param array $payload
	 * @param int|null $actor_user_id
	 * @return int|false notification id or false when table unavailable
	 */
	function notify($user_id, $type, $payload = array(), $actor_user_id = null)
	{
		if (!$this->table_ready()) {
			return false;
		}

		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return false;
		}

		$type = trim((string) $type);
		if ($type === '') {
			return false;
		}

		$this->db->insert('user_notifications', array(
			'user_id' => $user_id,
			'type' => $type,
			'payload' => json_encode(is_array($payload) ? $payload : array()),
			'actor_user_id' => $actor_user_id !== null ? (int) $actor_user_id : null,
			'read_at' => null,
			'email_sent_at' => null,
			'created' => time(),
		));

		return (int) $this->db->insert_id();
	}

	/**
	 * @param int $user_id
	 * @param array $params limit, offset, unread, type
	 * @return array{rows: array, total: int, limit: int, offset: int}
	 */
	function list_for_user($user_id, $params = array())
	{
		$user_id = (int) $user_id;
		$limit = isset($params['limit']) ? (int) $params['limit'] : 25;
		if ($limit < 1) {
			$limit = 25;
		}
		if ($limit > 100) {
			$limit = 100;
		}
		$offset = isset($params['offset']) ? (int) $params['offset'] : 0;
		if ($offset < 0) {
			$offset = 0;
		}

		$empty = array(
			'rows' => array(),
			'total' => 0,
			'limit' => $limit,
			'offset' => $offset,
		);
		if ($user_id < 1 || !$this->table_ready()) {
			return $empty;
		}

		$this->apply_user_filters($user_id, $params);
		$total = (int) $this->db->count_all_results('user_notifications');

		$this->apply_user_filters($user_id, $params);
		$this->db->order_by('created', 'DESC');
		$this->db->order_by('id', 'DESC');
		$this->db->limit($limit, $offset);
		$query = $this->db->get('user_notifications');
		$rows = ($query && $query !== false) ? $query->result_array() : array();

		$rendered = array();
		foreach ($rows as $row) {
			$rendered[] = $this->render_row($row);
		}

		return array(
			'rows' => $rendered,
			'total' => $total,
			'limit' => $limit,
			'offset' => $offset,
		);
	}

	/**
	 * @param int $user_id
	 * @return int
	 */
	function unread_count($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return 0;
		}

		$this->db->where('user_id', $user_id);
		$this->db->where('read_at IS NULL', null, false);
		$this->apply_retention_filter();
		return (int) $this->db->count_all_results('user_notifications');
	}

	/**
	 * @param int $id
	 * @param int $user_id
	 * @return array|false rendered row
	 */
	function get_for_user($id, $user_id)
	{
		$id = (int) $id;
		$user_id = (int) $user_id;
		if ($id < 1 || $user_id < 1 || !$this->table_ready()) {
			return false;
		}

		$row = $this->db->where('id', $id)
			->where('user_id', $user_id)
			->get('user_notifications')
			->row_array();
		if (!$row || $this->row_expired($row)) {
			return false;
		}
		return $this->render_row($row);
	}

	/**
	 * @param int $id
	 * @param int $user_id
	 * @return array|false rendered row after update
	 */
	function mark_read($id, $user_id)
	{
		$id = (int) $id;
		$user_id = (int) $user_id;
		if ($id < 1 || $user_id < 1 || !$this->table_ready()) {
			return false;
		}

		$row = $this->db->where('id', $id)
			->where('user_id', $user_id)
			->get('user_notifications')
			->row_array();
		if (!$row) {
			return false;
		}

		if (empty($row['read_at'])) {
			$this->db->where('id', $id)
				->where('user_id', $user_id)
				->update('user_notifications', array('read_at' => time()));
			$row['read_at'] = time();
		}

		return $this->render_row($row);
	}

	/**
	 * @param int $user_id
	 * @return int rows updated
	 */
	function mark_all_read($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return 0;
		}

		$this->db->where('user_id', $user_id);
		$this->db->where('read_at IS NULL', null, false);
		$this->apply_retention_filter();
		$this->db->update('user_notifications', array('read_at' => time()));
		return (int) $this->db->affected_rows();
	}

	/**
	 * Server-rendered inbox fields. Unknown types get a generic fallback.
	 *
	 * @param array $row
	 * @return array
	 */
	function render_row($row)
	{
		$this->lang->load('general');

		$row = is_array($row) ? $row : array();
		$payload = $this->decode_payload(isset($row['payload']) ? $row['payload'] : null);
		$type = isset($row['type']) ? trim((string) $row['type']) : '';
		$actor_label = $this->actor_label($row, $payload);
		$action = isset($payload['action']) ? trim((string) $payload['action']) : '';
		if ($action === '' && isset($payload['permission'])) {
			$action = trim((string) $payload['permission']);
		}
		$action_label = $this->action_label($type, $action);

		$rendered = $this->render_type($type, $payload, $actor_label, $action_label);
		$read_at = !empty($row['read_at']) ? (int) $row['read_at'] : null;
		$this->load->helper('notification');
		$family = notification_family($type);
		$family_labels = array(
			'publish' => array('notification_family_publish', 'Publish'),
			'sharing' => array('notification_family_sharing', 'Sharing'),
			'ownership' => array('notification_family_ownership', 'Ownership'),
			'collection' => array('notification_family_collection', 'Collection'),
			'template' => array('notification_family_template', 'Template'),
			'other' => array('notifications', 'Notifications'),
		);
		$family_copy = isset($family_labels[$family]) ? $family_labels[$family] : $family_labels['other'];
		$subject = $this->payload_string($payload, 'template_title', '');
		if ($subject === '') {
			$subject = $this->payload_string($payload, 'collection_title', '');
		}
		if ($subject === '') {
			$subject = $this->payload_string($payload, 'project_title', '');
		}
		if ($subject === '') {
			$subject = $this->line_or('untitled', 'Untitled');
		}

		return array(
			'id' => isset($row['id']) ? (int) $row['id'] : 0,
			'type' => $type,
			'family' => $family,
			'family_label' => $this->line_or($family_copy[0], $family_copy[1]),
			'subject' => $subject,
			'title' => $rendered['title'],
			'body' => $rendered['body'],
			'href' => $rendered['href'],
			'action_label' => $action_label,
			'actor_label' => $actor_label,
			'actor_user_id' => isset($row['actor_user_id']) && $row['actor_user_id'] !== null
				? (int) $row['actor_user_id'] : null,
			'read_at' => $read_at,
			'is_unread' => $read_at === null,
			'created' => isset($row['created']) ? (int) $row['created'] : 0,
			'payload' => $payload,
		);
	}

	/**
	 * @return bool
	 */
	private function table_ready()
	{
		return $this->db->table_exists('user_notifications');
	}

	/**
	 * @param int $user_id
	 * @param array $params
	 * @return void
	 */
	private function apply_user_filters($user_id, $params)
	{
		$this->db->where('user_id', (int) $user_id);
		$this->apply_retention_filter();
		if (!empty($params['unread'])) {
			$this->db->where('read_at IS NULL', null, false);
		}
		if (isset($params['type']) && trim((string) $params['type']) !== '') {
			$this->db->where('type', trim((string) $params['type']));
		}
		$family = isset($params['family']) ? trim((string) $params['family']) : '';
		if ($family !== '' && $family !== 'all') {
			$this->apply_family_filter($family);
		}
	}

	/**
	 * Limit inbox queries to the configured retention window.
	 *
	 * @return void
	 */
	private function apply_retention_filter()
	{
		$this->load->helper('notification');
		$this->db->where('created >=', notification_retention_cutoff());
	}

	/**
	 * @param array $row
	 * @return bool
	 */
	private function row_expired($row)
	{
		$this->load->helper('notification');
		$created = isset($row['created']) ? (int) $row['created'] : 0;
		return $created < notification_retention_cutoff();
	}

	/**
	 * @param string $family
	 * @return void
	 */
	private function apply_family_filter($family)
	{
		$this->load->helper('notification');
		$match = notification_family_match($family);
		$clauses = array();
		foreach ($match['exact'] as $type) {
			$clauses[] = 'type = ' . $this->db->escape($type);
		}
		foreach ($match['prefixes'] as $prefix) {
			$clauses[] = 'type LIKE ' . $this->db->escape($prefix . '%');
		}
		if ($clauses === array()) {
			$this->db->where('1 =', 0, false);
			return;
		}
		$this->db->where('(' . implode(' OR ', $clauses) . ')', null, false);
	}

	/**
	 * Delete rows older than cutoff. Used by the prune CLI.
	 *
	 * @param int $cutoff unix time
	 * @return int
	 */
	function prune_older_than($cutoff)
	{
		$cutoff = (int) $cutoff;
		if ($cutoff < 1 || !$this->table_ready()) {
			return 0;
		}
		$this->db->where('created <', $cutoff);
		$this->db->delete('user_notifications');
		return (int) $this->db->affected_rows();
	}

	/**
	 * @param mixed $payload
	 * @return array
	 */
	private function decode_payload($payload)
	{
		if (is_array($payload)) {
			return $payload;
		}
		if (!is_string($payload) || $payload === '') {
			return array();
		}
		$decoded = json_decode($payload, true);
		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * @param array $row
	 * @param array $payload
	 * @return string
	 */
	private function actor_label($row, $payload)
	{
		if (!empty($payload['actor_username'])) {
			return trim((string) $payload['actor_username']);
		}
		$actor_id = isset($row['actor_user_id']) ? (int) $row['actor_user_id'] : 0;
		if ($actor_id < 1 || !$this->db->table_exists('users')) {
			return '';
		}
		$user = $this->db->select('username')->where('id', $actor_id)->get('users')->row_array();
		return ($user && !empty($user['username'])) ? (string) $user['username'] : '';
	}

	/**
	 * @param string $type
	 * @param string $action
	 * @return string
	 */
	private function action_label($type, $action)
	{
		$action = strtolower(trim($action));
		if ($type === 'publish.project_ready' && $action === '') {
			$action = 'ready';
		}
		$map = array(
			'published' => array('notification_action_published', 'Publish'),
			'return' => array('notification_action_return', 'Return'),
			'closed' => array('notification_action_closed', 'Close'),
			'cancel' => array('notification_action_cancel', 'Cancel'),
			'ready' => array('notification_action_ready', 'Ready'),
			'view' => array('notification_permission_view', 'View'),
			'edit' => array('notification_permission_edit', 'Edit'),
			'admin' => array('notification_permission_admin', 'Admin'),
		);
		if (!isset($map[$action])) {
			return $action;
		}
		return $this->line_or($map[$action][0], $map[$action][1]);
	}

	/**
	 * @param string $type
	 * @param array $payload
	 * @param string $actor_label
	 * @param string $action_label
	 * @return array{title: string, body: string, href: string}
	 */
	private function render_type($type, $payload, $actor_label, $action_label)
	{
		$actor = $actor_label !== '' ? $actor_label : $this->line_or('notification_unknown_actor', 'Someone');
		$project_title = $this->payload_string($payload, 'project_title', $this->line_or('untitled', 'Untitled'));
		$project_idno = $this->payload_string($payload, 'project_idno', '');
		$catalog_title = $this->payload_string($payload, 'catalog_title', '');
		$sid = isset($payload['sid']) ? (int) $payload['sid'] : 0;
		$catalog_id = isset($payload['catalog_id']) ? (int) $payload['catalog_id'] : 0;

		if ($type === 'publish.project_ready') {
			$href = 'publish-queue';
			if ($catalog_id > 0) {
				$href .= '?catalog_id=' . $catalog_id;
			}
			return array(
				'title' => $this->line_or('notification_publish_project_ready_title', 'Publish request'),
				'body' => $this->fill_template(
					$this->line_or(
						'notification_publish_project_ready_body',
						'{actor} submitted a publish request for {project_title} ({project_idno}) to {catalog_title}.'
					),
					array(
						'actor' => $actor,
						'project_title' => $project_title,
						'project_idno' => $project_idno !== '' ? $project_idno : '—',
						'catalog_title' => $catalog_title !== '' ? $catalog_title : '—',
					)
				),
				'href' => $href,
			);
		}

		if ($type === 'publish.queue_resolved') {
			$href = $sid > 0 ? ('editor/edit/' . $sid . '#/publish?tab=history') : 'notifications';
			return array(
				'title' => $this->fill_template(
					$this->line_or('notification_publish_queue_resolved_title', 'Publish request: {action}'),
					array('action' => $action_label)
				),
				'body' => $this->fill_template(
					$this->line_or(
						'notification_publish_queue_resolved_body',
						'{actor} resolved your publish request ({action}) for {project_title} on {catalog_title}.'
					),
					array(
						'actor' => $actor,
						'action' => $action_label,
						'project_title' => $project_title,
						'catalog_title' => $catalog_title !== '' ? $catalog_title : '—',
					)
				),
				'href' => $href,
			);
		}

		if ($type === 'project.ownership_transferred') {
			$href = $sid > 0 ? ('editor/edit/' . $sid) : 'notifications';
			$audience = $this->payload_string($payload, 'audience', 'new');
			$body_key = $audience === 'previous'
				? 'notification_ownership_transferred_previous_body'
				: 'notification_ownership_transferred_new_body';
			$body_fallback = $audience === 'previous'
				? '{actor} transferred ownership of {project_title} to {new_owner}.'
				: '{actor} transferred ownership of {project_title} to you.';
			return array(
				'title' => $this->line_or('notification_ownership_transferred_title', 'Ownership transferred'),
				'body' => $this->fill_template(
					$this->line_or($body_key, $body_fallback),
					array(
						'actor' => $actor,
						'project_title' => $project_title,
						'new_owner' => $this->payload_string($payload, 'new_owner', '—'),
					)
				),
				'href' => $href,
			);
		}

		$access = $this->render_access_type($type, $payload, $actor, $action_label, $sid, $project_title);
		if ($access !== null) {
			return $access;
		}

		return array(
			'title' => $type !== '' ? $type : $this->line_or('notifications', 'Notifications'),
			'body' => $project_title !== $this->line_or('untitled', 'Untitled') ? $project_title : $type,
			'href' => 'notifications',
		);
	}

	/**
	 * @param string $type
	 * @param array $payload
	 * @param string $actor
	 * @param string $action_label
	 * @param int $sid
	 * @param string $project_title
	 * @return array|null
	 */
	private function render_access_type($type, $payload, $actor, $action_label, $sid, $project_title)
	{
		$event = null;
		if (substr($type, -15) === '.access_granted') {
			$event = 'granted';
		} elseif (substr($type, -15) === '.access_updated') {
			$event = 'updated';
		} elseif (substr($type, -15) === '.access_revoked') {
			$event = 'revoked';
		}
		if ($event === null) {
			return null;
		}

		$resource = substr($type, 0, strpos($type, '.access_'));
		$collection_id = isset($payload['collection_id']) ? (int) $payload['collection_id'] : 0;
		$template_uid = $this->payload_string($payload, 'template_uid', '');
		$target = $project_title;
		$href = $sid > 0 ? ('editor/edit/' . $sid) : 'notifications';

		if ($resource === 'collection.project') {
			$target = $this->payload_string($payload, 'collection_title', $this->line_or('untitled', 'Untitled'));
			$href = 'editor';
			$copy = array(
				'granted' => array(
					'notification_collection_project_access_granted_title',
					'Project access granted',
					'notification_collection_project_access_granted_body',
					'{actor} gave you {permission} access to projects in {target}.',
				),
				'updated' => array(
					'notification_collection_project_access_updated_title',
					'Project access updated',
					'notification_collection_project_access_updated_body',
					'{actor} changed your project access in {target} to {permission}.',
				),
				'revoked' => array(
					'notification_collection_project_access_revoked_title',
					'Project access removed',
					'notification_collection_project_access_revoked_body',
					'{actor} removed your access to projects in {target}.',
				),
			);
			return array(
				'title' => $this->line_or($copy[$event][0], $copy[$event][1]),
				'body' => $this->fill_template(
					$this->line_or($copy[$event][2], $copy[$event][3]),
					array(
						'actor' => $actor,
						'permission' => $action_label !== '' ? $action_label : $this->payload_string($payload, 'permission', ''),
						'target' => $target,
					)
				),
				'href' => $href,
			);
		}

		if ($resource === 'collection') {
			$target = $this->payload_string($payload, 'collection_title', $this->line_or('untitled', 'Untitled'));
			$href = $collection_id > 0 ? ('collections#/manage-users/' . $collection_id) : 'collections';
		} elseif ($resource === 'template' || $resource === 'admin_metadata') {
			$target = $this->payload_string($payload, 'template_title', $this->line_or('untitled', 'Untitled'));
			$href = $template_uid !== '' ? ('templates/' . rawurlencode($template_uid)) : 'templates';
		}

		$copy = array(
			'granted' => array(
				'notification_access_granted_title',
				'Access granted',
				'notification_access_granted_body',
				'{actor} gave you {permission} access to {target}.',
			),
			'updated' => array(
				'notification_access_updated_title',
				'Access updated',
				'notification_access_updated_body',
				'{actor} changed your access on {target} to {permission}.',
			),
			'revoked' => array(
				'notification_access_revoked_title',
				'Access removed',
				'notification_access_revoked_body',
				'{actor} removed your access to {target}.',
			),
		);

		return array(
			'title' => $this->line_or($copy[$event][0], $copy[$event][1]),
			'body' => $this->fill_template(
				$this->line_or($copy[$event][2], $copy[$event][3]),
				array(
					'actor' => $actor,
					'permission' => $action_label !== '' ? $action_label : $this->payload_string($payload, 'permission', ''),
					'target' => $target,
				)
			),
			'href' => $href,
		);
	}

	/**
	 * @param array $payload
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	private function payload_string($payload, $key, $default = '')
	{
		if (!isset($payload[$key]) || trim((string) $payload[$key]) === '') {
			return $default;
		}
		return trim((string) $payload[$key]);
	}

	/**
	 * @param string $key
	 * @param string $fallback
	 * @return string
	 */
	private function line_or($key, $fallback)
	{
		$line = $this->lang->line($key);
		if ($line === false || $line === '' || $line === $key) {
			return $fallback;
		}
		return $line;
	}

	/**
	 * @param string $template
	 * @param array $vars
	 * @return string
	 */
	private function fill_template($template, $vars)
	{
		foreach ($vars as $key => $value) {
			$template = str_replace('{' . $key . '}', (string) $value, $template);
		}
		return $template;
	}
}
