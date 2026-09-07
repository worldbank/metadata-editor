<?php

/**
 * Project × catalog publication ledger.
 */
class Project_publications_model extends CI_Model {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('catalog');
		$this->load->helper('catalog_publish_options');
		$this->load->helper('publish_intake');
	}

	/**
	 * @param int $sid
	 * @param int $catalog_id
	 * @return array|false
	 */
	function get_by_project_catalog($sid, $catalog_id)
	{
		$row = $this->db
			->where('sid', (int) $sid)
			->where('catalog_id', (int) $catalog_id)
			->get('project_publications')
			->row_array();
		return $row ? $this->decode_row($row) : false;
	}

	/**
	 * @param int $publication_id
	 * @return array|false
	 */
	function get_by_id($publication_id)
	{
		if (!$this->db->table_exists('project_publications')) {
			return false;
		}

		$publication_id = (int) $publication_id;
		if ($publication_id < 1) {
			return false;
		}

		$this->db->select('pp.*, ec.title AS catalog_title, ec.type AS catalog_type, ec.url AS catalog_url, ec.is_official');
		$this->db->from('project_publications pp');
		$this->db->join('editor_catalogs ec', 'ec.id = pp.catalog_id', 'left');
		$this->db->where('pp.id', $publication_id);
		$row = $this->db->get()->row_array();
		if (!$row) {
			return false;
		}

		$row = $this->decode_row($row);
		$row['is_update'] = $this->placement_is_published($row) ? 1 : 0;
		$row['is_official'] = catalog_is_official(isset($row['is_official']) ? $row['is_official'] : 0) ? 1 : 0;
		return $row;
	}

	/**
	 * @param int $publication_id
	 * @return array
	 */
	function get_for_publish_prefill($publication_id)
	{
		$row = $this->get_by_id($publication_id);
		if (!$row) {
			throw new Exception('Publication placement not found');
		}

		return array(
			'id' => (int) $row['id'],
			'sid' => (int) $row['sid'],
			'catalog_id' => (int) $row['catalog_id'],
			'catalog_title' => isset($row['catalog_title']) ? (string) $row['catalog_title'] : '',
			'catalog_type' => isset($row['catalog_type']) ? (string) $row['catalog_type'] : 'nada',
			'catalog_url' => isset($row['catalog_url']) ? (string) $row['catalog_url'] : '',
			'options' => isset($row['options']) && is_array($row['options']) ? $row['options'] : array(),
			'ready_at' => !empty($row['ready_at']) ? (int) $row['ready_at'] : null,
			'ready_by' => !empty($row['ready_by']) ? (int) $row['ready_by'] : null,
			'is_update' => !empty($row['is_update']) ? 1 : 0,
		);
	}

	/**
	 * Curator rejects a queued placement; clears ready state and stores return_reason when kept.
	 *
	 * @param int $publication_id
	 * @param int $curator_user_id
	 * @param string $note
	 * @return array
	 */
	function reject_ready($publication_id, $curator_user_id, $note)
	{
		return $this->resolve_ready_queue_item($publication_id, $curator_user_id, 'return', array(
			'note' => $note,
		));
	}

	/**
	 * Curator resolves a queued placement (published manually, return, closed, cancel).
	 *
	 * @param int $publication_id
	 * @param int $curator_user_id
	 * @param string $action published|return|closed|cancel
	 * @param array $payload note, status, remote_id, remote_url
	 * @return array
	 */
	function resolve_ready_queue_item($publication_id, $curator_user_id, $action, $payload = array())
	{
		$publication_id = (int) $publication_id;
		$curator_user_id = (int) $curator_user_id;
		$action = strtolower(trim((string) $action));
		$allowed = array('published', 'return', 'closed', 'cancel');
		if (!in_array($action, $allowed, true)) {
			throw new Exception('Invalid queue action');
		}

		if (!is_array($payload)) {
			$payload = array();
		}

		$note = isset($payload['note']) ? trim((string) $payload['note']) : '';
		if ($note === '') {
			throw new Exception('Note is required');
		}
		if (strlen($note) > 5000) {
			throw new Exception('Note is too long');
		}

		$placement = $this->get_by_id($publication_id);
		if (!$placement) {
			throw new Exception('Publication placement not found');
		}
		if (empty($placement['ready_at'])) {
			throw new Exception('This item is not in the publishing queue');
		}

		$sid = (int) $placement['sid'];
		$catalog_id = (int) $placement['catalog_id'];
		$catalog_type = isset($placement['catalog_type']) ? $placement['catalog_type'] : 'nada';
		$options = isset($placement['options']) && is_array($placement['options']) ? $placement['options'] : array();
		$recipient_id = !empty($placement['ready_by']) ? (int) $placement['ready_by'] : 0;

		$this->load->model('Editor_model');
		$project = $this->Editor_model->get_basic_info($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}
		if ($recipient_id < 1 && !empty($project['created_by'])) {
			$recipient_id = (int) $project['created_by'];
		}

		$this->db->trans_start();

		if ($action === 'published') {
			$status = isset($payload['status']) ? trim((string) $payload['status']) : 'published';
			if (!in_array($status, array('published', 'draft'), true)) {
				throw new Exception('Invalid publication status');
			}

			$remote_id = isset($payload['remote_id']) ? trim((string) $payload['remote_id']) : '';
			$remote_url = isset($payload['remote_url']) ? trim((string) $payload['remote_url']) : '';
			if ($remote_url === '' && $remote_id !== '' && !empty($placement['catalog_url'])) {
				$remote_url = rtrim((string) $placement['catalog_url'], '/') . '/index.php/catalog/' . rawurlencode($remote_id);
			}

			$this->upsert(array(
				'sid' => $sid,
				'catalog_id' => $catalog_id,
				'catalog_type' => $catalog_type,
				'options' => $options,
				'options_prevalidated' => true,
				'status' => $status,
				'remote_id' => $remote_id !== '' ? $remote_id : null,
				'remote_url' => $remote_url !== '' ? $remote_url : null,
				'source' => 'manual',
				'ready_at' => null,
				'ready_by' => null,
				'updated_by' => $curator_user_id,
				'requester_user_id' => $recipient_id > 0 ? $recipient_id : null,
				'event' => 'published',
				'resolution_note' => $note,
				'resolution_action' => $action,
			));
		} elseif ($action === 'return') {
			$this->upsert(array(
				'sid' => $sid,
				'catalog_id' => $catalog_id,
				'catalog_type' => $catalog_type,
				'options' => $options,
				'options_prevalidated' => true,
				'ready_at' => null,
				'ready_by' => null,
				'return_reason' => $note,
				'updated_by' => $curator_user_id,
				'requester_user_id' => $recipient_id > 0 ? $recipient_id : null,
				'event' => 'returned',
				'resolution_note' => $note,
				'resolution_action' => $action,
			));
		} elseif ($action === 'closed') {
			$this->upsert(array(
				'sid' => $sid,
				'catalog_id' => $catalog_id,
				'catalog_type' => $catalog_type,
				'options' => $options,
				'options_prevalidated' => true,
				'ready_at' => null,
				'ready_by' => null,
				'updated_by' => $curator_user_id,
				'requester_user_id' => $recipient_id > 0 ? $recipient_id : null,
				'event' => 'queue_closed',
				'resolution_note' => $note,
				'resolution_action' => $action,
			));
		} elseif ($action === 'cancel') {
			$this->upsert(array(
				'sid' => $sid,
				'catalog_id' => $catalog_id,
				'catalog_type' => $catalog_type,
				'options' => $options,
				'options_prevalidated' => true,
				'ready_at' => null,
				'ready_by' => null,
				'updated_by' => $curator_user_id,
				'requester_user_id' => $recipient_id > 0 ? $recipient_id : null,
				'event' => 'queue_cancelled',
				'resolution_note' => $note,
				'resolution_action' => $action,
			));
		}

		$this->db->trans_complete();

		if ($this->db->trans_status() === false) {
			throw new Exception('Failed to resolve queue item');
		}

		return array(
			'action' => $action,
			'publication_id' => $publication_id,
			'sid' => $sid,
			'catalog_id' => $catalog_id,
			'recipient_user_id' => $recipient_id,
			'project_title' => isset($project['title']) ? (string) $project['title'] : '',
			'project_idno' => isset($project['idno']) ? (string) $project['idno'] : '',
			'catalog_title' => isset($placement['catalog_title']) ? (string) $placement['catalog_title'] : '',
			'note' => $note,
		);
	}

	/**
	 * @param int $sid
	 * @return array<int, array>
	 */
	function list_by_project($sid)
	{
		if (!$this->db->table_exists('project_publications')) {
			return array();
		}

		$rows = $this->db
			->select('pp.*, ec.title AS catalog_title, ec.type AS catalog_type, ec.url AS catalog_url, ec.is_official')
			->from('project_publications pp')
			->join('editor_catalogs ec', 'ec.id = pp.catalog_id', 'left')
			->where('pp.sid', (int) $sid)
			->order_by('ec.title', 'ASC')
			->get()
			->result_array();

		$out = array();
		foreach ($rows as $row) {
			$row = $this->decode_row($row);
			$row['is_official'] = catalog_is_official(isset($row['is_official']) ? $row['is_official'] : 0) ? 1 : 0;
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @param array<int, array> $placements
	 * @return array
	 */
	function derive_summary($placements)
	{
		$ready = array();
		$placed = array();

		foreach ($placements as $placement) {
			$catalog_label = !empty($placement['catalog_title'])
				? (string) $placement['catalog_title']
				: ('Catalog #' . (int) $placement['catalog_id']);

			if (!empty($placement['ready_at'])) {
				$ready[] = $catalog_label;
			}
			if ($this->placement_is_published($placement)) {
				$placed[] = $catalog_label;
			}
		}

		$ready = array_values(array_unique($ready));
		$placed = array_values(array_unique($placed));

		if (!empty($ready)) {
			$label = 'Queued';
			if (!empty($placed)) {
				$label = 'Published · queued on ' . count($ready) . ' catalog(s)';
			}
			return array(
				'chip' => 'ready',
				'label' => $label,
				'ready_catalogs' => $ready,
				'placed_catalogs' => $placed,
				'ready_count' => count($ready),
			);
		}
		if (!empty($placed)) {
			return array(
				'chip' => 'published',
				'label' => 'Published',
				'ready_catalogs' => array(),
				'placed_catalogs' => $placed,
				'ready_count' => 0,
			);
		}

		return array(
			'chip' => 'none',
			'label' => '',
			'ready_catalogs' => array(),
			'placed_catalogs' => array(),
			'ready_count' => 0,
		);
	}

	/**
	 * @param array $placement
	 * @return bool
	 */
	function placement_is_published($placement)
	{
		if (!empty($placement['remote_id'])) {
			return true;
		}
		$status = isset($placement['status']) ? (string) $placement['status'] : '';
		return in_array($status, array('draft', 'published'), true);
	}

	/**
	 * Rows shown on the owner publication queue (queued and/or published history).
	 *
	 * @param array<int, array> $placements
	 * @return array<int, array>
	 */
	function filter_for_publication_queue($placements)
	{
		$out = array();
		foreach ($placements as $placement) {
			if (!empty($placement['ready_at']) || $this->placement_is_published($placement)) {
				$out[] = $placement;
			}
		}
		return $out;
	}

	/**
	 * Rows with an active ready state (owner queue tab — pending only).
	 *
	 * @param array<int, array> $placements
	 * @return array<int, array>
	 */
	function filter_for_pending_queue($placements)
	{
		$out = array();
		foreach ($placements as $placement) {
			if (!empty($placement['ready_at'])) {
				$out[] = $placement;
			}
		}
		return $out;
	}

	/**
	 * Official catalog placements shown on the owner project publish tab.
	 *
	 * @param array<int, array> $placements
	 * @return array<int, array>
	 */
	function filter_for_owner_publications($placements)
	{
		$out = array();
		foreach ($placements as $placement) {
			if (!empty($placement['is_official'])) {
				$out[] = $placement;
			}
		}
		return $out;
	}

	/**
	 * Attach latest queue outcome metadata for owner publication rows.
	 *
	 * @param array<int, array> $placements
	 * @return array<int, array>
	 */
	function enrich_owner_publications($placements)
	{
		if ($placements === array()) {
			return $placements;
		}

		$publication_ids = array();
		foreach ($placements as &$placement) {
			if (!empty($placement['return_reason'])) {
				$placement['queue_outcome'] = 'returned';
				$placement['queue_outcome_note'] = (string) $placement['return_reason'];
				continue;
			}
			if (!empty($placement['id'])) {
				$publication_ids[] = (int) $placement['id'];
			}
		}
		unset($placement);

		if ($publication_ids === array() || !$this->db->table_exists('project_publication_events')) {
			return $placements;
		}

		$outcome_events = array('returned', 'queue_closed', 'queue_cancelled', 'published');
		$this->db->select('e.publication_id, e.event, e.payload, e.created');
		$this->db->from('project_publication_events e');
		$this->db->where_in('e.publication_id', $publication_ids);
		$this->db->where_in('e.event', $outcome_events);
		$this->db->order_by('e.created', 'DESC');
		$this->db->order_by('e.id', 'DESC');
		$query = $this->db->get();
		$latest = array();
		if ($query !== false) {
			foreach ($query->result_array() as $row) {
				$publication_id = (int) $row['publication_id'];
				if (!isset($latest[$publication_id])) {
					$latest[$publication_id] = $row;
				}
			}
		}

		foreach ($placements as &$placement) {
			if (!empty($placement['queue_outcome'])) {
				continue;
			}

			$publication_id = !empty($placement['id']) ? (int) $placement['id'] : 0;
			if ($publication_id < 1 || !isset($latest[$publication_id])) {
				continue;
			}

			$row = $latest[$publication_id];
			$placement['queue_outcome'] = (string) $row['event'];
			$payload = isset($row['payload']) ? json_decode($row['payload'], true) : null;
			if (is_array($payload) && !empty($payload['note'])) {
				$placement['queue_outcome_note'] = (string) $payload['note'];
			}
		}
		unset($placement);

		return $placements;
	}

	/**
	 * @param int $sid
	 * @return array<int, array>
	 */
	function owner_publications_for_project($sid)
	{
		$placements = $this->filter_for_owner_publications($this->list_by_project($sid));
		return $this->enrich_owner_publications($placements);
	}

	/**
	 * Official catalog placements returned by a curator (not currently queued).
	 *
	 * @param array<int, array> $placements
	 * @return array<int, array>
	 */
	function filter_returned_publications($placements)
	{
		$out = array();
		foreach ($placements as $placement) {
			if (empty($placement['is_official'])) {
				continue;
			}
			if (!empty($placement['ready_at'])) {
				continue;
			}
			if (empty($placement['return_reason'])) {
				continue;
			}
			$out[] = array(
				'catalog_id' => (int) $placement['catalog_id'],
				'catalog_title' => !empty($placement['catalog_title'])
					? (string) $placement['catalog_title']
					: ('Catalog #' . (int) $placement['catalog_id']),
				'return_reason' => (string) $placement['return_reason'],
			);
		}
		return $out;
	}

	/**
	 * Owner queue tab payload: pending rows, returned summary, project chip.
	 *
	 * @param int $sid
	 * @return array{publications:array, returned_catalogs:array, summary:array}
	 */
	function queue_context_for_project($sid)
	{
		$all_placements = $this->list_by_project($sid);
		return array(
			'publications' => $this->filter_for_pending_queue($all_placements),
			'returned_catalogs' => $this->filter_returned_publications($all_placements),
			'summary' => $this->derive_summary($all_placements),
		);
	}

	/**
	 * @param int $publication_id
	 * @return bool
	 */
	function delete_by_id($publication_id)
	{
		$publication_id = (int) $publication_id;
		if ($publication_id < 1) {
			return false;
		}
		$this->db->where('id', $publication_id)->delete('project_publications');
		return $this->db->affected_rows() > 0;
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param array|null $catalog_ids
	 * @return array
	 */
	function clear_ready($sid, $user_id, $catalog_ids = null)
	{
		$placements = $this->list_by_project($sid);
		$target_ids = array();
		if (is_array($catalog_ids) && $catalog_ids !== array()) {
			foreach ($catalog_ids as $catalog_id) {
				$target_ids[(int) $catalog_id] = true;
			}
		}

		$cleared = array();
		$this->db->trans_start();
		foreach ($placements as $placement) {
			if (empty($placement['ready_at'])) {
				continue;
			}
			$catalog_id = (int) $placement['catalog_id'];
			if ($target_ids !== array() && empty($target_ids[$catalog_id])) {
				continue;
			}

			if ($this->placement_is_published($placement)) {
				$this->upsert(array(
					'sid' => $sid,
					'catalog_id' => $catalog_id,
					'catalog_type' => isset($placement['catalog_type']) ? $placement['catalog_type'] : 'nada',
					'options' => isset($placement['options']) ? $placement['options'] : array(),
					'options_prevalidated' => true,
					'ready_at' => null,
					'ready_by' => null,
					'updated_by' => $user_id,
					'event' => 'ready_cleared',
				));
			} else {
				$this->delete_by_id((int) $placement['id']);
			}
			$cleared[] = $catalog_id;
		}
		$this->db->trans_complete();

		if ($this->db->trans_status() === false) {
			throw new Exception('Failed to clear ready state');
		}

		$context = $this->queue_context_for_project($sid);
		return array(
			'cleared_catalog_ids' => $cleared,
			'publications' => $context['publications'],
			'returned_catalogs' => $context['returned_catalogs'],
			'summary' => $context['summary'],
		);
	}

	/**
	 * Event types shown in per-project publication history (owner + editors).
	 * Excludes ready / ready_cleared — pending state lives on the Queue tab.
	 *
	 * @return array<int, string>
	 */
	function publication_history_event_types()
	{
		return array(
			'returned',
			'queue_closed',
			'queue_cancelled',
			'published',
		);
	}

	/**
	 * Chronological publication activity for a project (from project_publication_events).
	 *
	 * @param int $sid
	 * @param array $params limit, offset
	 * @return array{total:int, rows:array<int, array>}
	 */
	function list_events_by_project($sid, $params = array())
	{
		if (!$this->db->table_exists('project_publication_events')) {
			return array('total' => 0, 'rows' => array());
		}

		$sid = (int) $sid;
		$limit = isset($params['limit']) ? (int) $params['limit'] : 100;
		$offset = isset($params['offset']) ? (int) $params['offset'] : 0;
		if ($limit < 1) {
			$limit = 100;
		}
		if ($limit > 500) {
			$limit = 500;
		}
		if ($offset < 0) {
			$offset = 0;
		}

		$event_filter = null;
		if (isset($params['events']) && is_array($params['events']) && $params['events'] !== array()) {
			$event_filter = array_values(array_unique(array_map('strval', $params['events'])));
		}

		$this->db->from('project_publication_events e');
		$this->db->join('project_publications pp', 'pp.id = e.publication_id');
		$this->db->where('pp.sid', $sid);
		if ($event_filter !== null) {
			$this->db->where_in('e.event', $event_filter);
		}
		$total = (int) $this->db->count_all_results();

		$this->db->select('e.id, e.publication_id, e.event, e.actor_user_id, e.payload, e.created, pp.catalog_id, pp.options AS publication_options, ec.title AS catalog_title, ec.url AS catalog_url, ec.is_official');
		$this->db->from('project_publication_events e');
		$this->db->join('project_publications pp', 'pp.id = e.publication_id');
		$this->db->join('editor_catalogs ec', 'ec.id = pp.catalog_id', 'left');
		$this->db->where('pp.sid', $sid);
		if ($event_filter !== null) {
			$this->db->where_in('e.event', $event_filter);
		}
		$this->db->order_by('e.created', 'DESC');
		$this->db->order_by('e.id', 'DESC');
		$this->db->limit($limit, $offset);
		$query = $this->db->get();
		if ($query === false) {
			$db_error = $this->db->error();
			$message = 'Failed to load publication history';
			if (!empty($db_error['message'])) {
				$message .= ': ' . $db_error['message'];
			}
			throw new Exception($message);
		}
		$rows = $query->result_array();

		$actor_names = $this->resolve_user_usernames($rows, 'actor_user_id');
		$viewer_user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
		$viewer_is_admin = !empty($params['is_admin']);
		$out = array();
		foreach ($rows as $row) {
			$decoded = $this->decode_event_row($row);
			$actor_id = $decoded['actor_user_id'];
			if ($actor_id && isset($actor_names[$actor_id])) {
				$decoded['actor_username'] = $actor_names[$actor_id];
			}
			$decoded['can_delete'] = $this->can_delete_published_event($decoded, $viewer_user_id, $viewer_is_admin);
			$decoded['publication_options'] = $this->decode_publication_options(isset($row['publication_options']) ? $row['publication_options'] : null);
			$out[] = $decoded;
		}

		$out = $this->enrich_history_rows_with_request_context($out);

		return array(
			'total' => $total,
			'rows' => $out,
		);
	}

	/**
	 * @param array<int, array> $rows
	 * @param string $field user id column on each row
	 * @return array<int, string> user_id => username
	 */
	private function resolve_user_usernames($rows, $field = 'actor_user_id')
	{
		$user_ids = array();
		foreach ($rows as $row) {
			if (!empty($row[$field])) {
				$user_ids[(int) $row[$field]] = true;
			}
		}
		if ($user_ids === array() || !$this->db->table_exists('users')) {
			return array();
		}

		$query = $this->db->select('id, username')
			->where_in('id', array_keys($user_ids))
			->get('users');
		if ($query === false) {
			return array();
		}

		$map = array();
		foreach ($query->result_array() as $user) {
			$map[(int) $user['id']] = (string) $user['username'];
		}
		return $map;
	}

	/**
	 * @param array<int, array> $rows
	 * @return array<int, string> user_id => username
	 */
	private function resolve_actor_usernames($rows)
	{
		return $this->resolve_user_usernames($rows, 'actor_user_id');
	}

	/**
	 * @param array $event_row decoded event row with actor_user_id, is_official
	 * @param int $user_id
	 * @param bool $is_admin
	 * @return bool
	 */
	function can_delete_published_event($event_row, $user_id, $is_admin = false)
	{
		if (!is_array($event_row) || (isset($event_row['event']) && $event_row['event'] !== 'published')) {
			return false;
		}
		if ($is_admin) {
			return true;
		}
		$user_id = (int) $user_id;
		if ($user_id < 1 || empty($event_row['actor_user_id']) || (int) $event_row['actor_user_id'] !== $user_id) {
			return false;
		}
		if (!empty($event_row['is_official'])) {
			return false;
		}
		return true;
	}

	/**
	 * Delete a published history event when permitted; reconcile placement row.
	 *
	 * @param int $event_id
	 * @param int $sid
	 * @param int $user_id
	 * @param bool $is_admin
	 * @return array
	 */
	function delete_published_event($event_id, $sid, $user_id, $is_admin = false)
	{
		if (!$this->db->table_exists('project_publication_events')) {
			throw new Exception('Publication history is not available');
		}

		$event_id = (int) $event_id;
		$sid = (int) $sid;
		if ($event_id < 1 || $sid < 1) {
			throw new Exception('Invalid publication history entry');
		}

		$this->db->select('e.id, e.publication_id, e.event, e.actor_user_id, pp.sid, pp.catalog_id, ec.is_official');
		$this->db->from('project_publication_events e');
		$this->db->join('project_publications pp', 'pp.id = e.publication_id');
		$this->db->join('editor_catalogs ec', 'ec.id = pp.catalog_id', 'left');
		$this->db->where('e.id', $event_id);
		$this->db->where('pp.sid', $sid);
		$row = $this->db->get()->row_array();
		if (!$row) {
			throw new Exception('Publication history entry not found');
		}

		$event_row = $this->decode_event_row($row);
		if (!$this->can_delete_published_event($event_row, $user_id, $is_admin)) {
			throw new Exception('You cannot delete this publication history entry');
		}

		$publication_id = (int) $row['publication_id'];
		$this->db->trans_start();
		$this->db->where('id', $event_id)->delete('project_publication_events');
		$this->reconcile_placement_after_history_change($publication_id);
		$this->db->trans_complete();

		if ($this->db->trans_status() === false) {
			throw new Exception('Failed to delete publication history entry');
		}

		return $this->list_events_by_project($sid, array(
			'events' => $this->publication_history_event_types(),
			'user_id' => $user_id,
			'is_admin' => $is_admin,
		));
	}

	/**
	 * @param int $publication_id
	 */
	private function reconcile_placement_after_history_change($publication_id)
	{
		$publication_id = (int) $publication_id;
		if ($publication_id < 1) {
			return;
		}

		$placement = $this->db->where('id', $publication_id)->get('project_publications')->row_array();
		if (!$placement) {
			return;
		}

		$latest = $this->db
			->where('publication_id', $publication_id)
			->where('event', 'published')
			->order_by('created', 'DESC')
			->order_by('id', 'DESC')
			->limit(1)
			->get('project_publication_events')
			->row_array();

		if ($latest) {
			$payload = array();
			if (!empty($latest['payload'])) {
				$decoded = json_decode($latest['payload'], true);
				$payload = is_array($decoded) ? $decoded : array();
			}
			$update = array(
				'status' => isset($payload['status']) ? $payload['status'] : null,
				'remote_id' => isset($payload['remote_id']) ? $payload['remote_id'] : null,
				'remote_url' => isset($payload['remote_url']) ? $payload['remote_url'] : null,
				'source' => isset($payload['source']) ? $payload['source'] : null,
				'changed' => time(),
			);
			if (isset($payload['options']) && is_array($payload['options'])) {
				$update['options'] = json_encode($payload['options']);
			}
			$this->db->where('id', $publication_id)->update('project_publications', $update);
			return;
		}

		if (!empty($placement['ready_at'])) {
			$this->db->where('id', $publication_id)->update('project_publications', array(
				'status' => null,
				'remote_id' => null,
				'remote_url' => null,
				'source' => null,
				'changed' => time(),
			));
			return;
		}

		$this->delete_by_id($publication_id);
	}

	/**
	 * @param array $params catalog_id, limit, offset
	 * @return array
	 */
	function list_ready_queue($params = array())
	{
		if (!$this->db->table_exists('project_publications')) {
			return array('total' => 0, 'rows' => array());
		}

		$catalog_id = isset($params['catalog_id']) ? (int) $params['catalog_id'] : 0;
		$limit = isset($params['limit']) ? (int) $params['limit'] : 50;
		$offset = isset($params['offset']) ? (int) $params['offset'] : 0;
		if ($limit < 1) {
			$limit = 50;
		}
		if ($limit > 200) {
			$limit = 200;
		}
		if ($offset < 0) {
			$offset = 0;
		}

		$this->db->from('project_publications pp');
		$this->db->join('editor_catalogs ec', 'ec.id = pp.catalog_id', 'left');
		$this->db->join('editor_projects ep', 'ep.id = pp.sid', 'left');
		$this->db->where('pp.ready_at IS NOT NULL', null, false);
		if ($catalog_id > 0) {
			$this->db->where('pp.catalog_id', $catalog_id);
		}

		$total = (int) $this->db->count_all_results('', false);

		$this->db->select('pp.*, ec.title AS catalog_title, ec.type AS catalog_type, ec.url AS catalog_url, ec.is_official, ep.title AS project_title, ep.idno AS project_idno, ep.type AS project_type');
		$this->db->order_by('pp.ready_at', 'DESC');
		$this->db->limit($limit, $offset);
		$rows = $this->db->get()->result_array();

		$ready_by_names = $this->resolve_user_usernames($rows, 'ready_by');
		$out = array();
		foreach ($rows as $row) {
			$row = $this->decode_row($row);
			$row['is_update'] = $this->placement_is_published($row) ? 1 : 0;
			$row['is_official'] = catalog_is_official(isset($row['is_official']) ? $row['is_official'] : 0) ? 1 : 0;
			$ready_by = !empty($row['ready_by']) ? (int) $row['ready_by'] : 0;
			if ($ready_by > 0 && isset($ready_by_names[$ready_by])) {
				$row['ready_by_username'] = $ready_by_names[$ready_by];
			} else {
				$row['ready_by_username'] = null;
			}
			$out[] = $row;
		}

		return array(
			'total' => $total,
			'limit' => $limit,
			'offset' => $offset,
			'rows' => $out,
		);
	}

	/**
	 * Global curator queue history (resolved items and publishes).
	 *
	 * @param array $params catalog_id, limit, offset
	 * @return array{total:int, limit:int, offset:int, rows:array<int, array>}
	 */
	function list_queue_history($params = array())
	{
		if (!$this->db->table_exists('project_publication_events')) {
			return array('total' => 0, 'limit' => 50, 'offset' => 0, 'rows' => array());
		}

		$catalog_id = isset($params['catalog_id']) ? (int) $params['catalog_id'] : 0;
		$limit = isset($params['limit']) ? (int) $params['limit'] : 50;
		$offset = isset($params['offset']) ? (int) $params['offset'] : 0;
		if ($limit < 1) {
			$limit = 50;
		}
		if ($limit > 200) {
			$limit = 200;
		}
		if ($offset < 0) {
			$offset = 0;
		}

		$history_events = array('returned', 'queue_closed', 'queue_cancelled', 'published');

		$this->db->from('project_publication_events e');
		$this->db->join('project_publications pp', 'pp.id = e.publication_id');
		if ($catalog_id > 0) {
			$this->db->where('pp.catalog_id', $catalog_id);
		}
		$this->db->where_in('e.event', $history_events);
		$total = (int) $this->db->count_all_results();

		$this->db->select('e.id, e.publication_id, e.event, e.actor_user_id, e.payload, e.created, pp.sid, pp.catalog_id, pp.options AS publication_options, ec.title AS catalog_title, ec.url AS catalog_url, ep.title AS project_title, ep.idno AS project_idno, ep.created_by AS project_created_by');
		$this->db->from('project_publication_events e');
		$this->db->join('project_publications pp', 'pp.id = e.publication_id');
		$this->db->join('editor_catalogs ec', 'ec.id = pp.catalog_id', 'left');
		$this->db->join('editor_projects ep', 'ep.id = pp.sid', 'left');
		if ($catalog_id > 0) {
			$this->db->where('pp.catalog_id', $catalog_id);
		}
		$this->db->where_in('e.event', $history_events);
		$this->db->order_by('e.created', 'DESC');
		$this->db->order_by('e.id', 'DESC');
		$this->db->limit($limit, $offset);
		$query = $this->db->get();
		if ($query === false) {
			$db_error = $this->db->error();
			$message = 'Failed to load queue history';
			if (!empty($db_error['message'])) {
				$message .= ': ' . $db_error['message'];
			}
			throw new Exception($message);
		}

		$rows = $query->result_array();
		$requester_ids = $this->resolve_queue_history_requester_ids($rows);
		$user_ids = array();
		foreach ($rows as $row) {
			if (!empty($row['actor_user_id'])) {
				$user_ids[(int) $row['actor_user_id']] = true;
			}
			$publication_id = (int) $row['publication_id'];
			if (isset($requester_ids[$publication_id])) {
				$user_ids[$requester_ids[$publication_id]] = true;
			}
		}
		$username_rows = array();
		foreach ($user_ids as $user_id => $_) {
			$username_rows[] = array('actor_user_id' => $user_id);
		}
		$user_names = $this->resolve_user_usernames($username_rows, 'actor_user_id');
		$out = array();
		foreach ($rows as $row) {
			$decoded = $this->decode_event_row($row);
			$decoded['sid'] = isset($row['sid']) ? (int) $row['sid'] : null;
			$decoded['project_title'] = isset($row['project_title']) ? (string) $row['project_title'] : '';
			$decoded['project_idno'] = isset($row['project_idno']) ? (string) $row['project_idno'] : '';
			$publication_id = (int) $row['publication_id'];
			$requester_id = isset($requester_ids[$publication_id]) ? (int) $requester_ids[$publication_id] : 0;
			$reviewer_id = !empty($decoded['actor_user_id']) ? (int) $decoded['actor_user_id'] : 0;
			$decoded['requester_user_id'] = $requester_id > 0 ? $requester_id : null;
			$decoded['reviewer_user_id'] = $reviewer_id > 0 ? $reviewer_id : null;
			if ($requester_id > 0 && isset($user_names[$requester_id])) {
				$decoded['requester_username'] = $user_names[$requester_id];
			}
			if ($reviewer_id > 0 && isset($user_names[$reviewer_id])) {
				$decoded['reviewer_username'] = $user_names[$reviewer_id];
			}
			$decoded['publication_options'] = $this->decode_publication_options(isset($row['publication_options']) ? $row['publication_options'] : null);
			$out[] = $decoded;
		}

		$out = $this->enrich_history_rows_with_request_context($out);

		return array(
			'total' => $total,
			'limit' => $limit,
			'offset' => $offset,
			'rows' => $out,
		);
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param array $payload
	 * @return array
	 */
	function mark_ready($sid, $user_id, $payload)
	{
		$this->load->library('Project_publish_ready');
		return $this->project_publish_ready->mark_ready($sid, $user_id, $payload);
	}

	/**
	 * @param array|false $existing
	 * @return string publish|update
	 */
	function resolve_request_type($existing)
	{
		if (!is_array($existing)) {
			return 'publish';
		}
		if (!empty($existing['remote_id'])) {
			return 'update';
		}
		$status = isset($existing['status']) ? (string) $existing['status'] : '';
		if (in_array($status, array('draft', 'published', 'withdrawn'), true)) {
			return 'update';
		}
		return 'publish';
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param array $payload intake, request_note, requests[{catalog_id, options}]
	 * @return array
	 */
	function submit_for_publishing($sid, $user_id, $payload)
	{
		$this->load->model('Catalog_connections_model');
		$this->load->library('Project_publish_submit');

		return $this->project_publish_submit->submit($sid, $user_id, $payload);
	}

	/**
	 * Clear open publish requests for selected catalogs (or all open on project).
	 *
	 * @param int $sid
	 * @param int $user_id
	 * @param array|null $catalog_ids
	 * @return array
	 */
	function withdraw_requests($sid, $user_id, $catalog_ids = null)
	{
		$placements = $this->list_by_project($sid);
		$target_ids = array();
		if (is_array($catalog_ids) && $catalog_ids !== array()) {
			foreach ($catalog_ids as $catalog_id) {
				$target_ids[(int) $catalog_id] = true;
			}
		}

		$withdrawn = array();
		$this->db->trans_start();
		foreach ($placements as $placement) {
			$catalog_id = (int) $placement['catalog_id'];
			$request = isset($placement['request']) ? $placement['request'] : null;
			if (!in_array($request, array('publish', 'update', 'changes_requested'), true)) {
				continue;
			}
			if ($target_ids !== array() && empty($target_ids[$catalog_id])) {
				continue;
			}

			$this->upsert(array(
				'sid' => $sid,
				'catalog_id' => $catalog_id,
				'catalog_type' => isset($placement['catalog_type']) ? $placement['catalog_type'] : 'nada',
				'options' => isset($placement['options']) ? $placement['options'] : array(),
				'request' => null,
				'return_reason' => null,
				'updated_by' => $user_id,
				'event' => 'withdrawn',
			));
			$withdrawn[] = $catalog_id;
		}
		$this->db->trans_complete();

		if ($this->db->trans_status() === false) {
			throw new Exception('Failed to withdraw publish request');
		}

		return array(
			'withdrawn_catalog_ids' => $withdrawn,
			'publications' => $this->list_by_project($sid),
			'summary' => $this->derive_summary($this->list_by_project($sid)),
		);
	}

	/**
	 * Upsert current placement and append an event.
	 *
	 * @param array $data
	 * @return int publication id
	 */
	function upsert($data)
	{
		$sid = isset($data['sid']) ? (int) $data['sid'] : 0;
		$catalog_id = isset($data['catalog_id']) ? (int) $data['catalog_id'] : 0;
		if ($sid < 1 || $catalog_id < 1) {
			throw new Exception('sid and catalog_id are required');
		}

		$catalog_type = isset($data['catalog_type']) ? $data['catalog_type'] : 'nada';
		if (!empty($data['options_prevalidated'])) {
			$options = isset($data['options']) && is_array($data['options']) ? $data['options'] : array();
		} else {
			$options = isset($data['options']) ? $data['options'] : array();
			$options = catalog_publish_options_validate($catalog_type, $options);
		}

		$intake = null;
		if (array_key_exists('intake', $data)) {
			$intake_params = isset($data['intake_params']) && is_array($data['intake_params'])
				? $data['intake_params']
				: array();
			$intake = publish_intake_validate($data['intake'], $intake_params);
		}

		$now = time();
		$existing = $this->get_by_project_catalog($sid, $catalog_id);

		$row = array(
			'options' => json_encode($options),
			'updated_by' => isset($data['updated_by']) ? (int) $data['updated_by'] : null,
			'updated_at' => $now,
			'changed' => $now,
		);

		$patch_fields = array(
			'status',
			'request',
			'remote_id',
			'remote_url',
			'source',
			'request_note',
			'return_reason',
			'requested_by',
			'requested_at',
			'ready_at',
			'ready_by',
		);
		foreach ($patch_fields as $field) {
			if (array_key_exists($field, $data)) {
				$row[$field] = $data[$field];
			}
		}

		if ($intake !== null) {
			$row['intake'] = json_encode($intake);
		}

		if (array_key_exists('request_note', $data)) {
			$row['request_note'] = $data['request_note'];
		}
		if (array_key_exists('return_reason', $data)) {
			$row['return_reason'] = $data['return_reason'];
		}
		if (array_key_exists('requested_by', $data)) {
			$row['requested_by'] = $data['requested_by'];
		}
		if (array_key_exists('requested_at', $data)) {
			$row['requested_at'] = $data['requested_at'];
		}

		if ($existing) {
			$this->db->where('id', (int) $existing['id'])->update('project_publications', $row);
			$publication_id = (int) $existing['id'];
		} else {
			$row['sid'] = $sid;
			$row['catalog_id'] = $catalog_id;
			$row['created'] = $now;
			$this->db->insert('project_publications', $row);
			$publication_id = (int) $this->db->insert_id();
		}

		$event = isset($data['event']) ? (string) $data['event'] : '';
		$resolution_note = array_key_exists('resolution_note', $data) ? trim((string) $data['resolution_note']) : '';
		$resolution_action = array_key_exists('resolution_action', $data) ? (string) $data['resolution_action'] : null;

		if ($event === 'published') {
			$event_payload = array(
				'status' => array_key_exists('status', $data) ? $data['status'] : ($existing ? $existing['status'] : null),
				'request' => array_key_exists('request', $data) ? $data['request'] : ($existing ? $existing['request'] : null),
				'remote_id' => array_key_exists('remote_id', $data) ? $data['remote_id'] : ($existing ? $existing['remote_id'] : null),
				'remote_url' => array_key_exists('remote_url', $data) ? $data['remote_url'] : ($existing ? $existing['remote_url'] : null),
				'source' => array_key_exists('source', $data) ? $data['source'] : ($existing ? $existing['source'] : null),
				'options' => $options,
				'intake' => $this->intake_for_payload($intake, $existing),
			);
			if ($resolution_note !== '') {
				$event_payload['note'] = $resolution_note;
			}
			if ($resolution_action !== null && $resolution_action !== '') {
				$event_payload['action'] = $resolution_action;
			}
			$this->append_requester_to_event_payload($event_payload, $data, $existing);
			$this->add_event($publication_id, 'published', array(
				'actor_user_id' => isset($data['updated_by']) ? $data['updated_by'] : null,
				'payload' => $event_payload,
			));
		} elseif ($event !== '') {
			$event_payload = array();
			if ($resolution_note !== '') {
				$event_payload['note'] = $resolution_note;
			}
			if ($resolution_action !== null && $resolution_action !== '') {
				$event_payload['action'] = $resolution_action;
			}
			$this->append_requester_to_event_payload($event_payload, $data, $existing);
			$this->add_event($publication_id, $event, array(
				'actor_user_id' => isset($data['updated_by']) ? $data['updated_by'] : null,
				'payload' => $event_payload !== array() ? $event_payload : null,
			));
		}

		return $publication_id;
	}

	/**
	 * Record a successful built-in NADA publish. Does not write editor_projects.status.
	 *
	 * @param int $sid
	 * @param int $user_id
	 * @param array $catalog from require_connection
	 * @param array $publish_options form options
	 * @param array $project basic project row
	 * @param mixed $nada_response
	 * @return int|false
	 */
	function record_nada_publish($sid, $user_id, $catalog, $publish_options, $project, $nada_response = null)
	{
		$options = catalog_publish_options_from_publish_form($publish_options);
		$catalog_type = isset($catalog['type']) ? $catalog['type'] : 'nada';
		$remote_id = $this->resolve_remote_id($project, $nada_response);
		$catalog_url = isset($catalog['url']) ? rtrim((string) $catalog['url'], '/') : '';
		$remote_url = ($catalog_url !== '' && $remote_id !== null && $remote_id !== '')
			? $catalog_url . '/index.php/catalog/' . rawurlencode($remote_id)
			: null;

		return $this->upsert(array(
			'sid' => $sid,
			'catalog_id' => (int) $catalog['id'],
			'catalog_type' => $catalog_type,
			'status' => catalog_publication_status_from_nada_options($options),
			'request' => null,
			'ready_at' => null,
			'ready_by' => null,
			'remote_id' => $remote_id,
			'remote_url' => $remote_url,
			'source' => 'nada_publish',
			'options' => $options,
			'updated_by' => $user_id,
			'event' => 'published',
		));
	}

	/**
	 * @param int $publication_id
	 * @param string $event
	 * @param array $data
	 * @return int
	 */
	function add_event($publication_id, $event, $data = array())
	{
		$this->db->insert('project_publication_events', array(
			'publication_id' => (int) $publication_id,
			'event' => $event,
			'actor_user_id' => isset($data['actor_user_id']) ? $data['actor_user_id'] : null,
			'payload' => isset($data['payload']) ? json_encode($data['payload']) : null,
			'created' => time(),
		));
		return (int) $this->db->insert_id();
	}

	private function resolve_remote_id($project, $nada_response)
	{
		if (is_array($nada_response)) {
			foreach (array('idno', 'dataset_idno') as $key) {
				if (!empty($nada_response[$key])) {
					return (string) $nada_response[$key];
				}
			}
			if (!empty($nada_response['dataset']) && is_array($nada_response['dataset']) && !empty($nada_response['dataset']['idno'])) {
				return (string) $nada_response['dataset']['idno'];
			}
		}
		if (is_array($project)) {
			if (!empty($project['study_idno'])) {
				return (string) $project['study_idno'];
			}
			if (!empty($project['idno'])) {
				return (string) $project['idno'];
			}
		}
		return null;
	}

	/**
	 * @param array $event_payload
	 * @param array $data upsert input
	 * @param array|false $existing placement before update
	 */
	private function append_requester_to_event_payload(&$event_payload, $data, $existing)
	{
		if (array_key_exists('requester_user_id', $data) && $data['requester_user_id'] !== null && $data['requester_user_id'] !== '') {
			$requester_id = (int) $data['requester_user_id'];
			if ($requester_id > 0) {
				$event_payload['requester_user_id'] = $requester_id;
			}
			return;
		}
		if (is_array($existing) && !empty($existing['ready_by'])) {
			$event_payload['requester_user_id'] = (int) $existing['ready_by'];
		}
	}

	/**
	 * @param array<int, array> $rows history query rows
	 * @return array<int, int> publication_id => requester user id
	 */
	private function resolve_queue_history_requester_ids($rows)
	{
		$requester_ids = array();
		$lookup_publication_ids = array();

		foreach ($rows as $row) {
			$publication_id = (int) $row['publication_id'];
			$payload = array();
			if (isset($row['payload']) && is_string($row['payload']) && $row['payload'] !== '') {
				$decoded = json_decode($row['payload'], true);
				$payload = is_array($decoded) ? $decoded : array();
			}
			if (!empty($payload['requester_user_id'])) {
				$requester_ids[$publication_id] = (int) $payload['requester_user_id'];
				continue;
			}
			if (!empty($row['project_created_by'])) {
				$requester_ids[$publication_id] = (int) $row['project_created_by'];
				continue;
			}
			$lookup_publication_ids[$publication_id] = true;
		}

		if ($lookup_publication_ids !== array() && $this->db->table_exists('project_publication_events')) {
			$this->db->select('publication_id, actor_user_id, created');
			$this->db->from('project_publication_events');
			$this->db->where_in('publication_id', array_keys($lookup_publication_ids));
			$this->db->where('event', 'ready');
			$this->db->order_by('created', 'DESC');
			$query = $this->db->get();
			if ($query !== false) {
				foreach ($query->result_array() as $ready_row) {
					$publication_id = (int) $ready_row['publication_id'];
					if (isset($requester_ids[$publication_id]) || empty($ready_row['actor_user_id'])) {
						continue;
					}
					$requester_ids[$publication_id] = (int) $ready_row['actor_user_id'];
				}
			}
		}

		return $requester_ids;
	}

	/**
	 * Attach the publish request (ready event) that preceded each history outcome.
	 *
	 * @param array<int, array> $rows decoded history rows
	 * @return array<int, array>
	 */
	function enrich_history_rows_with_request_context($rows)
	{
		if ($rows === array() || !$this->db->table_exists('project_publication_events')) {
			return $rows;
		}

		$publication_ids = array();
		foreach ($rows as $row) {
			if (!empty($row['publication_id'])) {
				$publication_ids[(int) $row['publication_id']] = true;
			}
		}
		if ($publication_ids === array()) {
			return $rows;
		}

		$timeline_events = array('ready', 'returned', 'queue_closed', 'queue_cancelled', 'published', 'ready_cleared');
		$this->db->select('id, publication_id, event, actor_user_id, payload, created');
		$this->db->from('project_publication_events');
		$this->db->where_in('publication_id', array_keys($publication_ids));
		$this->db->where_in('event', $timeline_events);
		$this->db->order_by('created', 'ASC');
		$this->db->order_by('id', 'ASC');
		$query = $this->db->get();
		$timelines = array();
		if ($query !== false) {
			foreach ($query->result_array() as $event_row) {
				$publication_id = (int) $event_row['publication_id'];
				if (!isset($timelines[$publication_id])) {
					$timelines[$publication_id] = array();
				}
				$timelines[$publication_id][] = $event_row;
			}
		}

		$request_actor_ids = array();
		$paired = array();
		$outcome_events = array('returned', 'queue_closed', 'queue_cancelled', 'published');
		foreach ($rows as $row) {
			$publication_id = (int) $row['publication_id'];
			$event_id = (int) $row['id'];
			$created = isset($row['created']) ? (int) $row['created'] : 0;
			$ready_event = $this->find_ready_event_for_outcome(
				isset($timelines[$publication_id]) ? $timelines[$publication_id] : array(),
				$event_id,
				$created,
				$outcome_events
			);
			$paired[$event_id] = $ready_event;
			if ($ready_event && !empty($ready_event['actor_user_id'])) {
				$request_actor_ids[(int) $ready_event['actor_user_id']] = true;
			}
		}

		$username_rows = array();
		foreach ($request_actor_ids as $user_id => $_) {
			$username_rows[] = array('actor_user_id' => $user_id);
		}
		$request_usernames = $this->resolve_user_usernames($username_rows, 'actor_user_id');

		foreach ($rows as &$row) {
			$event_id = (int) $row['id'];
			$ready_event = isset($paired[$event_id]) ? $paired[$event_id] : null;
			$row['request'] = $this->build_history_request_context($row, $ready_event, $request_usernames);
		}
		unset($row);

		return $rows;
	}

	/**
	 * @param array<int, array> $timeline events for one publication, ascending
	 * @param int $outcome_event_id
	 * @param int $outcome_created
	 * @param array<int, string> $outcome_events
	 * @return array|null
	 */
	private function find_ready_event_for_outcome($timeline, $outcome_event_id, $outcome_created, $outcome_events)
	{
		$matched_ready = null;
		foreach ($timeline as $event_row) {
			$event_id = (int) $event_row['id'];
			$created = (int) $event_row['created'];
			if ($created > $outcome_created || ($created === $outcome_created && $event_id > $outcome_event_id)) {
				break;
			}

			$event = isset($event_row['event']) ? (string) $event_row['event'] : '';
			if ($event === 'ready') {
				$matched_ready = $event_row;
				continue;
			}
			if (in_array($event, $outcome_events, true)) {
				if ($event_id === $outcome_event_id) {
					return $matched_ready;
				}
				$matched_ready = null;
			}
		}

		return null;
	}

	/**
	 * @param array $row decoded history row
	 * @param array|null $ready_event
	 * @param array<int, string> $request_usernames
	 * @return array|null
	 */
	private function build_history_request_context($row, $ready_event, $request_usernames)
	{
		if (!$ready_event) {
			return null;
		}

		$submitted_by = !empty($ready_event['actor_user_id']) ? (int) $ready_event['actor_user_id'] : null;
		$options = array();
		if (!empty($row['payload']['options']) && is_array($row['payload']['options'])) {
			$options = $row['payload']['options'];
		} elseif (!empty($row['publication_options']) && is_array($row['publication_options'])) {
			$options = $row['publication_options'];
		}

		$request = array(
			'submitted_at' => !empty($ready_event['created']) ? (int) $ready_event['created'] : null,
			'submitted_by' => $submitted_by,
			'submitted_by_username' => ($submitted_by && isset($request_usernames[$submitted_by]))
				? $request_usernames[$submitted_by]
				: null,
			'options' => $options,
		);

		if (!empty($row['requester_user_id'])) {
			$request['submitted_by'] = (int) $row['requester_user_id'];
		}
		if (!empty($row['requester_username'])) {
			$request['submitted_by_username'] = (string) $row['requester_username'];
		}

		return $request;
	}

	/**
	 * @param mixed $options
	 * @return array
	 */
	private function decode_publication_options($options)
	{
		if (is_array($options)) {
			return $options;
		}
		if (is_string($options) && $options !== '') {
			$decoded = json_decode($options, true);
			return is_array($decoded) ? $decoded : array();
		}
		return array();
	}

	private function decode_row($row)
	{
		if (isset($row['options']) && is_string($row['options']) && $row['options'] !== '') {
			$decoded = json_decode($row['options'], true);
			$row['options'] = is_array($decoded) ? $decoded : array();
		}
		if (isset($row['intake']) && is_string($row['intake']) && $row['intake'] !== '') {
			$decoded = json_decode($row['intake'], true);
			$row['intake'] = is_array($decoded) ? $decoded : array();
		} elseif (!isset($row['intake']) || $row['intake'] === null) {
			$row['intake'] = array();
		}
		return $row;
	}

	private function decode_event_row($row)
	{
		$payload = array();
		if (isset($row['payload']) && is_string($row['payload']) && $row['payload'] !== '') {
			$decoded = json_decode($row['payload'], true);
			$payload = is_array($decoded) ? $decoded : array();
		}
		unset($row['payload']);

		return array(
			'id' => (int) $row['id'],
			'publication_id' => (int) $row['publication_id'],
			'event' => isset($row['event']) ? (string) $row['event'] : '',
			'created' => isset($row['created']) ? (int) $row['created'] : null,
			'catalog_id' => isset($row['catalog_id']) ? (int) $row['catalog_id'] : null,
			'catalog_title' => isset($row['catalog_title']) ? (string) $row['catalog_title'] : '',
			'catalog_url' => isset($row['catalog_url']) ? (string) $row['catalog_url'] : '',
			'is_official' => catalog_is_official(isset($row['is_official']) ? $row['is_official'] : 0) ? 1 : 0,
			'actor_user_id' => !empty($row['actor_user_id']) ? (int) $row['actor_user_id'] : null,
			'actor_username' => !empty($row['actor_username']) ? (string) $row['actor_username'] : null,
			'payload' => $payload,
		);
	}

	/**
	 * @param array|null $intake validated intake from this upsert, or null when unchanged
	 * @param array|false $existing
	 * @return array
	 */
	private function intake_for_payload($intake, $existing)
	{
		if (is_array($intake)) {
			return $intake;
		}
		if (is_array($existing) && isset($existing['intake']) && is_array($existing['intake'])) {
			return $existing['intake'];
		}
		return array();
	}
}
