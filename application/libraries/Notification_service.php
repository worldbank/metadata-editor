<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Global in-app notifications. Callers resolve recipients; this service
 * validates the type, excludes the actor, enriches snapshots, and inserts
 * one user_notifications row per recipient. Email is Phase B (email_sent_at stays NULL).
 */
class Notification_service
{
	/** @var CI_Controller */
	private $ci;

	/** @var array<string, array> */
	private $types = array();

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->helper('notification');
		$this->ci->config->load('notifications', true);
		$types = $this->ci->config->item('notification_types', 'notifications');
		$this->types = is_array($types) ? $types : array();
		$this->ci->load->model('User_notifications_model');
	}

	/**
	 * @param string $type
	 * @param array $recipient_user_ids
	 * @param array $context payload fields from the caller
	 * @param int|null $actor_user_id
	 * @return array{ok: bool, type: string, recipient_ids: int[], inserted_ids: int[], skipped: string|null}
	 */
	public function notify($type, array $recipient_user_ids, array $context = array(), $actor_user_id = null)
	{
		$type = trim((string) $type);
		$actor_user_id = $actor_user_id !== null ? (int) $actor_user_id : null;
		if ($actor_user_id !== null && $actor_user_id < 1) {
			$actor_user_id = null;
		}

		$result = array(
			'ok' => false,
			'type' => $type,
			'recipient_ids' => array(),
			'inserted_ids' => array(),
			'skipped' => null,
		);

		if ($type === '' || !$this->is_known_type($type)) {
			$result['skipped'] = 'unknown_type';
			log_message('error', 'Notification_service: unknown type "' . $type . '" — skipped');
			return $result;
		}

		$type_config = $this->type_config($type);
		if (empty($type_config['in_app'])) {
			$result['skipped'] = 'in_app_disabled';
			return $result;
		}

		$recipient_ids = $this->normalize_recipients($recipient_user_ids, $actor_user_id);
		$result['recipient_ids'] = $recipient_ids;
		if ($recipient_ids === array()) {
			$result['ok'] = true;
			$result['skipped'] = 'no_recipients';
			return $result;
		}

		try {
			$payload = $this->enrich_payload($context, $actor_user_id);
		} catch (Exception $e) {
			log_message('error', 'Notification_service: payload enrich failed for ' . $type . ': ' . $e->getMessage());
			$payload = is_array($context) ? $context : array();
		}

		foreach ($recipient_ids as $user_id) {
			try {
				$inserted = $this->ci->User_notifications_model->notify(
					$user_id,
					$type,
					$payload,
					$actor_user_id
				);
				if ($inserted) {
					$result['inserted_ids'][] = (int) $inserted;
				}
			} catch (Exception $e) {
				log_message('error', 'Notification_service: insert failed for user ' . $user_id . ' type ' . $type . ': ' . $e->getMessage());
			}
		}

		$result['ok'] = true;
		return $result;
	}

	/**
	 * Emit granted / updated / revoked for a named resource. Skips unchanged
	 * permissions and never fails the caller.
	 *
	 * @param string $resource project|collection|template|admin_metadata
	 * @param int $recipient_user_id
	 * @param string|null $previous_permission
	 * @param string|null $new_permission
	 * @param array $context
	 * @param int|null $actor_user_id
	 * @return array
	 */
	public function notify_permission_change($resource, $recipient_user_id, $previous_permission, $new_permission, array $context = array(), $actor_user_id = null)
	{
		$event = notification_access_event($previous_permission, $new_permission);
		if ($event === null) {
			return array(
				'ok' => true,
				'type' => '',
				'recipient_ids' => array(),
				'inserted_ids' => array(),
				'skipped' => 'unchanged',
			);
		}

		$permission = notification_normalize_permission($new_permission);
		if ($permission === '') {
			$permission = notification_normalize_permission($previous_permission);
		}
		if ($permission !== '') {
			$context['permission'] = $permission;
		}
		$previous = notification_normalize_permission($previous_permission);
		if ($previous !== '') {
			$context['previous_permission'] = $previous;
		}

		return $this->notify_safe(
			trim((string) $resource) . '.access_' . $event,
			array($recipient_user_id),
			$context,
			$actor_user_id
		);
	}

	/**
	 * @param string $type
	 * @param array $recipient_user_ids
	 * @param array $context
	 * @param int|null $actor_user_id
	 * @return array
	 */
	public function notify_safe($type, array $recipient_user_ids, array $context = array(), $actor_user_id = null)
	{
		try {
			return $this->notify($type, $recipient_user_ids, $context, $actor_user_id);
		} catch (Exception $e) {
			log_message('error', 'Notification_service: notify_safe failed for ' . $type . ': ' . $e->getMessage());
			return array(
				'ok' => false,
				'type' => $type,
				'recipient_ids' => array(),
				'inserted_ids' => array(),
				'skipped' => 'exception',
			);
		}
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	public function is_known_type($type)
	{
		return isset($this->types[trim((string) $type)]);
	}

	/**
	 * @return array<string, array>
	 */
	public function known_types()
	{
		return $this->types;
	}

	/**
	 * @param string $type
	 * @return array
	 */
	public function type_config($type)
	{
		$type = trim((string) $type);
		return isset($this->types[$type]) && is_array($this->types[$type])
			? $this->types[$type]
			: array();
	}

	/**
	 * @param array $recipient_user_ids
	 * @param int|null $actor_user_id
	 * @return int[]
	 */
	private function normalize_recipients(array $recipient_user_ids, $actor_user_id)
	{
		$ids = array();
		foreach ($recipient_user_ids as $user_id) {
			$user_id = (int) $user_id;
			if ($user_id < 1) {
				continue;
			}
			if ($actor_user_id !== null && $user_id === $actor_user_id) {
				continue;
			}
			$ids[$user_id] = $user_id;
		}
		return array_values($ids);
	}

	/**
	 * Fill missing snapshots from ids in context. Caller-supplied values win.
	 *
	 * @param array $context
	 * @param int|null $actor_user_id
	 * @return array
	 */
	private function enrich_payload(array $context, $actor_user_id)
	{
		$payload = $context;

		$sid = isset($payload['sid']) ? (int) $payload['sid'] : 0;
		if ($sid > 0 && $this->payload_blank($payload, array('project_title', 'project_idno'))) {
			$this->apply_project_snapshot($payload, $sid);
		}

		$catalog_id = isset($payload['catalog_id']) ? (int) $payload['catalog_id'] : 0;
		if ($catalog_id > 0 && $this->payload_blank($payload, array('catalog_title', 'catalog_url'))) {
			$this->apply_catalog_snapshot($payload, $catalog_id);
		}

		if ($actor_user_id !== null && $this->payload_blank($payload, array('actor_username'))) {
			$username = $this->lookup_username($actor_user_id);
			if ($username !== '') {
				$payload['actor_username'] = $username;
			}
		}

		$collection_id = isset($payload['collection_id']) ? (int) $payload['collection_id'] : 0;
		if ($collection_id > 0 && $this->payload_blank($payload, array('collection_title'))) {
			$this->apply_collection_snapshot($payload, $collection_id);
		}

		$template_uid = isset($payload['template_uid']) ? trim((string) $payload['template_uid']) : '';
		$template_id = isset($payload['template_id']) ? (int) $payload['template_id'] : 0;
		if (($template_uid !== '' || $template_id > 0) && $this->payload_blank($payload, array('template_title', 'template_uid'))) {
			$this->apply_template_snapshot($payload, $template_uid, $template_id);
		}

		$new_owner_id = isset($payload['new_owner_id']) ? (int) $payload['new_owner_id'] : 0;
		if ($new_owner_id > 0 && $this->payload_blank($payload, array('new_owner'))) {
			$username = $this->lookup_username($new_owner_id);
			if ($username !== '') {
				$payload['new_owner'] = $username;
			}
		}

		return $payload;
	}

	/**
	 * @param array $payload
	 * @param string[] $keys
	 * @return bool true if any key is missing or empty
	 */
	private function payload_blank(array $payload, array $keys)
	{
		foreach ($keys as $key) {
			if (!isset($payload[$key]) || trim((string) $payload[$key]) === '') {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $payload
	 * @param int $sid
	 * @return void
	 */
	private function apply_project_snapshot(array &$payload, $sid)
	{
		$this->ci->load->model('Editor_model');
		$project = $this->ci->Editor_model->get_basic_info($sid);
		if (!is_array($project)) {
			return;
		}
		if ($this->payload_blank($payload, array('project_title')) && isset($project['title'])) {
			$payload['project_title'] = (string) $project['title'];
		}
		if ($this->payload_blank($payload, array('project_idno'))) {
			$idno = '';
			if (!empty($project['idno'])) {
				$idno = (string) $project['idno'];
			} elseif (!empty($project['study_idno'])) {
				$idno = (string) $project['study_idno'];
			}
			if ($idno !== '') {
				$payload['project_idno'] = $idno;
			}
		}
	}

	/**
	 * @param array $payload
	 * @param int $catalog_id
	 * @return void
	 */
	private function apply_catalog_snapshot(array &$payload, $catalog_id)
	{
		$this->ci->load->model('Catalog_connections_model');
		$catalog = $this->ci->Catalog_connections_model->get_catalog($catalog_id);
		if (!is_array($catalog)) {
			return;
		}
		if ($this->payload_blank($payload, array('catalog_title')) && isset($catalog['title'])) {
			$payload['catalog_title'] = (string) $catalog['title'];
		}
		if ($this->payload_blank($payload, array('catalog_url')) && !empty($catalog['url'])) {
			$payload['catalog_url'] = (string) $catalog['url'];
		}
	}

	/**
	 * @param array $payload
	 * @param int $collection_id
	 * @return void
	 */
	private function apply_collection_snapshot(array &$payload, $collection_id)
	{
		$this->ci->load->model('Collection_model');
		$collection = $this->ci->Collection_model->select_single($collection_id);
		if (!is_array($collection) || empty($collection['title'])) {
			return;
		}
		$payload['collection_title'] = (string) $collection['title'];
	}

	/**
	 * @param array $payload
	 * @param string $template_uid
	 * @param int $template_id
	 * @return void
	 */
	private function apply_template_snapshot(array &$payload, $template_uid, $template_id)
	{
		if (!$this->ci->db->table_exists('editor_templates')) {
			return;
		}
		$this->ci->db->select('uid, name');
		if ($template_uid !== '') {
			$this->ci->db->where('uid', $template_uid);
		} else {
			$this->ci->db->where('id', (int) $template_id);
		}
		$row = $this->ci->db->get('editor_templates')->row_array();
		if (!$row) {
			return;
		}
		if ($this->payload_blank($payload, array('template_uid')) && !empty($row['uid'])) {
			$payload['template_uid'] = (string) $row['uid'];
		}
		if ($this->payload_blank($payload, array('template_title')) && !empty($row['name'])) {
			$payload['template_title'] = (string) $row['name'];
		}
	}

	/**
	 * @param int $user_id
	 * @return string
	 */
	private function lookup_username($user_id)
	{
		if (!$this->ci->db->table_exists('users')) {
			return '';
		}
		$row = $this->ci->db->select('username')
			->where('id', (int) $user_id)
			->get('users')
			->row_array();
		return ($row && !empty($row['username'])) ? (string) $row['username'] : '';
	}
}
