<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'libraries/Project_publish_submit.php';

/**
 * Mark-ready workflow (Phase 1): per-catalog readiness without submit/request.
 */
class Project_publish_ready
{
	/** @var CI_Controller */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Catalog_connections_model');
		$this->ci->load->model('Project_publications_model');
		$this->ci->load->helper('catalog');
		$this->ci->load->helper('catalog_publish_options');
		$this->ci->load->library('Project_publish_submit');
	}

	/**
	 * @param int $sid
	 * @return array
	 */
	public function ready_context($sid)
	{
		$official_catalogs = $this->ci->Catalog_connections_model->get_official_catalogs();
		$all_placements = $this->ci->Project_publications_model->list_by_project($sid);
		$publications_by_catalog = array();
		foreach ($all_placements as $placement) {
			$publications_by_catalog[(int) $placement['catalog_id']] = $placement;
		}
		$queue_context = $this->ci->Project_publications_model->queue_context_for_project($sid);

		$catalogs = array();
		foreach ($official_catalogs as $catalog) {
			$catalog_id = (int) $catalog['id'];
			$type = isset($catalog['type']) ? $catalog['type'] : 'nada';
			$catalogs[] = array(
				'catalog' => $catalog,
				'publish_form' => catalog_publish_form_load($type),
				'existing' => isset($publications_by_catalog[$catalog_id]) ? $publications_by_catalog[$catalog_id] : null,
			);
		}

		return array(
			'official_catalogs' => $catalogs,
			'publications' => $queue_context['publications'],
			'returned_catalogs' => $queue_context['returned_catalogs'],
			'summary' => $queue_context['summary'],
		);
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param array $payload requests[{catalog_id, options}]
	 * @return array
	 */
	public function mark_ready($sid, $user_id, $payload)
	{
		if (!is_array($payload)) {
			throw new Exception('Request body must be an object');
		}

		if (!$this->ci->db->table_exists('project_publications')) {
			throw new Exception('Publication ledger is not available');
		}
		if (!$this->ci->db->field_exists('ready_at', 'project_publications')) {
			throw new Exception('Database migration required: run migrations to add project_publications.ready_at');
		}

		$project = $this->ci->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}

		$requests_input = isset($payload['requests']) ? $payload['requests'] : null;
		if (!is_array($requests_input) || count($requests_input) < 1) {
			throw new Exception('At least one catalog is required');
		}

		$study_validation = $this->ci->project_publish_submit->validate_study($sid, $project);
		$official_by_id = $this->official_catalog_map();
		$prepared = array();
		$seen_catalog_ids = array();

		foreach ($requests_input as $request_input) {
			if (!is_array($request_input)) {
				throw new Exception('Each request must be an object');
			}
			$catalog_id = isset($request_input['catalog_id']) ? (int) $request_input['catalog_id'] : 0;
			if ($catalog_id < 1) {
				throw new Exception('Each request requires catalog_id');
			}
			if (isset($seen_catalog_ids[$catalog_id])) {
				throw new Exception('Duplicate catalog in mark-ready request');
			}
			$seen_catalog_ids[$catalog_id] = true;

			if (empty($official_by_id[$catalog_id])) {
				throw new Exception('Catalog is not available for mark ready: ' . $catalog_id);
			}

			$catalog = $official_by_id[$catalog_id];
			$catalog_type = isset($catalog['type']) ? $catalog['type'] : 'nada';
			$options = isset($request_input['options']) ? $request_input['options'] : array();
			if (catalog_type_is_nada($catalog_type)) {
				$options = catalog_publish_options_validate($catalog_type, $options, array(
					'strict' => true,
					'context' => 'ready',
				));
			} else {
				$options = catalog_publish_options_validate($catalog_type, is_array($options) ? $options : array());
			}

			$existing = $this->ci->Project_publications_model->get_by_project_catalog($sid, $catalog_id);
			$prepared[] = array(
				'catalog_id' => $catalog_id,
				'catalog_type' => $catalog_type,
				'catalog' => $catalog,
				'options' => $options,
				'was_ready' => ($existing && !empty($existing['ready_at'])),
			);
		}

		$now = time();
		$this->ci->db->trans_start();
		foreach ($prepared as $index => $item) {
			$prepared[$index]['publication_id'] = $this->ci->Project_publications_model->upsert(array(
				'sid' => $sid,
				'catalog_id' => $item['catalog_id'],
				'catalog_type' => $item['catalog_type'],
				'options' => $item['options'],
				'options_prevalidated' => true,
				'ready_at' => $now,
				'ready_by' => $user_id,
				'return_reason' => null,
				'updated_by' => $user_id,
				'event' => 'ready',
			));
		}
		$this->ci->db->trans_complete();

		if ($this->ci->db->trans_status() === false) {
			$db_error = $this->ci->db->error();
			$message = 'Failed to mark project ready';
			if (!empty($db_error['message'])) {
				$message .= ': ' . $db_error['message'];
			}
			throw new Exception($message);
		}

		$warnings = array();
		foreach ($prepared as $item) {
			$warning = $this->notify_project_ready($sid, $user_id, $project, $item, $now);
			if ($warning) {
				$warnings[] = $warning;
			}
		}

		$queue_context = $this->ci->Project_publications_model->queue_context_for_project($sid);
		return array(
			'publications' => $queue_context['publications'],
			'returned_catalogs' => $queue_context['returned_catalogs'],
			'summary' => $queue_context['summary'],
			'study_validation' => $study_validation,
			'warnings' => $warnings,
		);
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param array|null $catalog_ids
	 * @return array
	 */
	public function clear_ready($sid, $user_id, $catalog_ids = null)
	{
		return $this->ci->Project_publications_model->clear_ready($sid, $user_id, $catalog_ids);
	}

	/**
	 * @param array $params catalog_id, limit, offset
	 * @return array
	 */
	public function ready_queue($params = array())
	{
		return $this->ci->Project_publications_model->list_ready_queue($params);
	}

	/**
	 * @param array $params catalog_id, limit, offset
	 * @return array
	 */
	public function queue_history($params = array())
	{
		return $this->ci->Project_publications_model->list_queue_history($params);
	}

	/**
	 * @param int $publication_id
	 * @param int $curator_user_id
	 * @param string $note
	 * @return array
	 */
	public function reject_ready($publication_id, $curator_user_id, $note)
	{
		return $this->resolve_ready($publication_id, $curator_user_id, 'return', array(
			'note' => $note,
		));
	}

	/**
	 * @param int $publication_id
	 * @param int $curator_user_id
	 * @param string $action published|return|closed|cancel
	 * @param array $payload
	 * @return array
	 */
	public function resolve_ready($publication_id, $curator_user_id, $action, $payload = array())
	{
		$result = $this->ci->Project_publications_model->resolve_ready_queue_item(
			$publication_id,
			$curator_user_id,
			$action,
			$payload
		);

		$recipient_id = isset($result['recipient_user_id']) ? (int) $result['recipient_user_id'] : 0;
		$curator_user_id = (int) $curator_user_id;

		if ($recipient_id > 0) {
			$notify_payload = array(
				'sid' => (int) $result['sid'],
				'publication_id' => (int) $result['publication_id'],
				'catalog_id' => (int) $result['catalog_id'],
				'catalog_title' => isset($result['catalog_title']) ? $result['catalog_title'] : '',
				'project_title' => isset($result['project_title']) ? $result['project_title'] : '',
				'project_idno' => isset($result['project_idno']) ? $result['project_idno'] : '',
				'action' => isset($result['action']) ? $result['action'] : $action,
				'note' => isset($result['note']) ? $result['note'] : '',
			);
			if ($action === 'return') {
				$notify_payload['return_reason'] = $notify_payload['note'];
			}
			try {
				$this->ci->load->library('Notification_service');
				$this->ci->notification_service->notify(
					'publish.queue_resolved',
					array($recipient_id),
					$notify_payload,
					$curator_user_id
				);
			} catch (Exception $e) {
				log_message('error', 'Project_publish_ready: queue_resolved notify failed: ' . $e->getMessage());
			}
		}

		return $result;
	}

	/**
	 * @param int $curator_user_id
	 * @param array $publication_ids
	 * @param string $action published|return|closed|cancel
	 * @param array $payload
	 * @return array
	 */
	public function resolve_ready_batch($curator_user_id, $publication_ids, $action, $payload = array())
	{
		if (!is_array($publication_ids) || count($publication_ids) < 1) {
			throw new Exception('At least one queue item is required');
		}
		if (count($publication_ids) > 100) {
			throw new Exception('Too many queue items in one batch (maximum 100)');
		}

		$action = strtolower(trim((string) $action));
		$allowed = array('published', 'return', 'closed', 'cancel');
		if (!in_array($action, $allowed, true)) {
			throw new Exception('Invalid queue action');
		}

		$results = array();
		$succeeded = 0;
		$failed = 0;

		foreach ($publication_ids as $publication_id) {
			$publication_id = (int) $publication_id;
			if ($publication_id < 1) {
				$results[] = array(
					'publication_id' => $publication_id,
					'status' => 'failed',
					'message' => 'Invalid publication id',
				);
				$failed++;
				continue;
			}

			try {
				$this->resolve_ready($publication_id, $curator_user_id, $action, $payload);
				$results[] = array(
					'publication_id' => $publication_id,
					'status' => 'success',
				);
				$succeeded++;
			} catch (Exception $e) {
				$results[] = array(
					'publication_id' => $publication_id,
					'status' => 'failed',
					'message' => $e->getMessage(),
				);
				$failed++;
			}
		}

		return array(
			'action' => $action,
			'succeeded_count' => $succeeded,
			'failed_count' => $failed,
			'results' => $results,
		);
	}

	/**
	 * Notify catalog curators of a new publish request. Skips re-notify when
	 * the placement was already queued. Returns an owner warning when nobody
	 * else will be notified.
	 *
	 * @param int $sid
	 * @param int $user_id
	 * @param array $project
	 * @param array $item prepared placement
	 * @param int $ready_at
	 * @return array|null warning
	 */
	private function notify_project_ready($sid, $user_id, $project, $item, $ready_at)
	{
		$catalog_id = isset($item['catalog_id']) ? (int) $item['catalog_id'] : 0;
		$catalog = isset($item['catalog']) && is_array($item['catalog']) ? $item['catalog'] : array();
		$catalog_title = isset($catalog['title']) ? (string) $catalog['title'] : '';
		$user_id = (int) $user_id;

		$curator_ids = $this->curator_user_ids($catalog_id);
		$other_ids = array();
		foreach ($curator_ids as $curator_id) {
			if ($curator_id !== $user_id) {
				$other_ids[] = $curator_id;
			}
		}

		$warning = null;
		if ($other_ids === array()) {
			$warning = $this->no_curators_warning(
				$catalog_id,
				$catalog_title,
				count($curator_ids) > 0 ? 'only_submitter' : 'no_curators'
			);
		}

		if (!empty($item['was_ready'])) {
			return $warning;
		}

		if ($other_ids === array()) {
			return $warning;
		}

		$options = isset($item['options']) && is_array($item['options']) ? $item['options'] : array();
		$context = array(
			'sid' => (int) $sid,
			'publication_id' => isset($item['publication_id']) ? (int) $item['publication_id'] : 0,
			'catalog_id' => $catalog_id,
			'catalog_title' => $catalog_title,
			'catalog_url' => isset($catalog['url']) ? (string) $catalog['url'] : '',
			'project_title' => isset($project['title']) ? (string) $project['title'] : '',
			'project_idno' => $this->project_idno($project),
			'access_policy' => isset($options['access_policy']) ? $options['access_policy'] : '',
			'repositoryid' => isset($options['repositoryid']) ? $options['repositoryid'] : '',
			'ready_at' => (int) $ready_at,
		);

		try {
			$this->ci->load->library('Notification_service');
			$this->ci->notification_service->notify(
				'publish.project_ready',
				$curator_ids,
				$context,
				$user_id
			);
		} catch (Exception $e) {
			log_message('error', 'Project_publish_ready: project_ready notify failed: ' . $e->getMessage());
		}

		return $warning;
	}

	/**
	 * @param int $catalog_id
	 * @return int[]
	 */
	private function curator_user_ids($catalog_id)
	{
		$ids = array();
		foreach ($this->ci->Catalog_connections_model->get_curators($catalog_id) as $curator) {
			$user_id = isset($curator['user_id']) ? (int) $curator['user_id'] : 0;
			if ($user_id > 0) {
				$ids[$user_id] = $user_id;
			}
		}
		return array_values($ids);
	}

	/**
	 * @param array $project
	 * @return string
	 */
	private function project_idno($project)
	{
		if (!empty($project['idno'])) {
			return (string) $project['idno'];
		}
		if (!empty($project['study_idno'])) {
			return (string) $project['study_idno'];
		}
		return '';
	}

	/**
	 * @param int $catalog_id
	 * @param string $catalog_title
	 * @param string $type no_curators|only_submitter
	 * @return array
	 */
	private function no_curators_warning($catalog_id, $catalog_title, $type)
	{
		$this->ci->lang->load('general');
		$label = $catalog_title !== '' ? $catalog_title : ('#' . $catalog_id);
		if ($type === 'only_submitter') {
			$template = $this->ci->lang->line('publish_ready_only_submitter');
			if (!$template) {
				$template = 'Request queued for “{catalog}”, but you are the only curator. Nobody else will be notified.';
			}
		} else {
			$template = $this->ci->lang->line('publish_ready_no_curators');
			if (!$template) {
				$template = 'Request queued for “{catalog}”, but no curators are assigned. Nobody will be notified.';
			}
		}

		return array(
			'type' => $type,
			'catalog_id' => (int) $catalog_id,
			'catalog_title' => $catalog_title,
			'message' => str_replace('{catalog}', $label, $template),
		);
	}

	/**
	 * @return array<int, array>
	 */
	private function official_catalog_map()
	{
		$map = array();
		foreach ($this->ci->Catalog_connections_model->get_official_catalogs() as $catalog) {
			$map[(int) $catalog['id']] = $catalog;
		}
		return $map;
	}
}
