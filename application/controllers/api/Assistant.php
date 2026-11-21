<?php

require(APPPATH.'/libraries/MY_REST_Controller.php');

use GuzzleHttp\Client;

/**
 * Server assistant proxy: the browser's only way to the FastAPI assistant (which is internal and has
 * no auth of its own).
 *
 *   GET  api/assistant/status?sid={id}   panel bootstrap: enabled, models, retrieval, project summary
 *   POST api/assistant/chat              one turn; responds with the service's Server-Sent Events
 *   POST api/assistant/review            start a whole-record review job, or (kind=document) a fill of
 *                                        every fillable field from the attached document, or (kind=variables)
 *                                        labelling of the open data file's variables from it; returns job_id
 *   POST api/assistant/labels            write accepted variable labels, descriptions and question text
 *                                        (edit permission; conflicts skipped; returns the values to undo)
 *   GET  api/assistant/review/{jobid}    the job's status, and its result once done
 *   DELETE api/assistant/review/{jobid}  cancel it
 *   POST api/assistant/extract           text of an attachment under the editor's storage folder
 *   GET  api/assistant/sources?sid=      the project's knowledge sources (External resources and chat
 *                                        attachments) with their index state; README-assistant.md
 *   POST api/assistant/attach            upload a document: indexed as a chat attachment, or (save=1) stored as an
 *                                        External resource and indexed as that; a file already a resource is reused
 *   DELETE api/assistant/sources/{id}    forget a chat attachment (a resource is removed on the Resources page)
 *   POST api/assistant/suggest           one-shot draft (datafile description, one field), optional document
 *
 * Every turn checks the session and the project's `view` permission, then builds the context bundle
 * here (libraries/Assistant_context.php). Proposals are offered only to users who may edit the
 * project, and even then nothing is written: a proposal is a card the curator fills into the form.
 */
class Assistant extends MY_REST_Controller
{
	const PROTOCOL = 'pslm-server/1';
	const MAX_QUESTION = 4000;
	const MAX_MESSAGE = 20000;
	const MAX_HISTORY = 12;

	private $api_user;

	public function __construct()
	{
		parent::__construct();
		$this->load->config('editor');
		$this->load->library('Editor_acl');
		$this->is_authenticated_or_die();
		$this->api_user = $this->api_user();
	}

	//override authentication to support both session authentication + api keys
	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')){
			return true;
		}
		parent::_auth_override_check();
	}

	private function enabled()
	{
		return (bool)$this->config->item('server_assistant_enabled', 'editor');
	}

	private function api_base()
	{
		return rtrim((string)$this->config->item('data_api_url', 'editor'), '/') . '/';
	}

	private function can($sid, $permission)
	{
		try {
			$this->editor_acl->user_has_project_access($sid, $permission, $this->api_user);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	private function keep_local($sid)
	{
		$this->load->model('Assistant_settings_model');
		return $this->Assistant_settings_model->keep_local($sid);
	}

	/**
	 * Per-project switch "keep this project's data on this server" (only a local model may answer).
	 * Needs the admin permission on the project (owner or administrator collaborator).
	 * Body: {"sid": ..., "keep_local": true|false}
	 */
	function settings_post()
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$input = (array)$this->raw_json_input();
			$sid = $this->get_sid((string)($input['sid'] ?? ''));
			try {
				$this->editor_acl->user_has_project_access($sid, 'admin', $this->api_user);
			} catch (Exception $e) {
				throw new Exception('Only a project administrator can change this setting.', 403);
			}
			if (!isset($input['keep_local']) || !is_bool($input['keep_local'])) {
				throw new Exception('keep_local must be true or false.', 400);
			}
			$this->load->model('Assistant_settings_model');
			$this->Assistant_settings_model->set_keep_local($sid, $input['keep_local'], $this->get_api_user_id());
			return $this->set_response(array('status' => 'success', 'keep_local' => $input['keep_local']), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}
	}

	function status_get()
	{
		if (!$this->enabled()) {
			return $this->set_response(array('status' => 'success', 'enabled' => false), REST_Controller::HTTP_OK);
		}
		$out = array('status' => 'success', 'enabled' => true, 'service' => null, 'models' => array(), 'project' => null, 'can_edit' => false,
			'fields' => (object)(array)$this->config->item('server_assistant_fields', 'editor'));
		try {
			$sid_param = (string)$this->input->get('sid');
			if ($sid_param !== '') {
				$sid = $this->get_sid($sid_param);
				$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
				$this->load->library('Assistant_context');
				$out['project'] = $this->assistant_context->summary($sid);
				$out['fields'] = (object)$this->assistant_context->field_labels($sid);
				$out['can_edit'] = $this->can($sid, 'edit');
				$out['can_admin'] = $this->can($sid, 'admin');
				$out['keep_local'] = $this->keep_local($sid);
			}
		} catch (Exception $e) {
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), REST_Controller::HTTP_FORBIDDEN);
		}

		$client = new Client(array('base_uri' => $this->api_base(), 'http_errors' => false, 'connect_timeout' => 3, 'timeout' => 8));
		try {
			$status = $client->request('GET', 'assistant/status');
			$out['service'] = json_decode((string)$status->getBody(), true);
			if ($status->getStatusCode() === 200) {
				$models = $client->request('GET', 'assistant/models');
				$decoded = json_decode((string)$models->getBody(), true);
				$out['models'] = isset($decoded['models']) ? $decoded['models'] : array();
				if (!empty($out['keep_local'])) {
					// A project kept on this server is offered the local models only.
					$out['models'] = array_values(array_filter($out['models'], function ($m) {
						return !empty($m['local']);
					}));
				}
				$out['local_only'] = !empty($decoded['local_only']);
			}
		} catch (Exception $e) {
			$out['service'] = array('enabled' => false, 'error' => 'The assistant service is not reachable.');
		}
		return $this->set_response($out, REST_Controller::HTTP_OK);
	}

	function chat_post()
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$input = $this->raw_json_input();
			if (!is_array($input)) {
				throw new Exception('Expected a JSON body.', 400);
			}
			$question = trim((string)($input['question'] ?? ''));
			if ($question === '' || mb_strlen($question) > self::MAX_QUESTION) {
				throw new Exception('A question of 1 to ' . self::MAX_QUESTION . ' characters is required.', 400);
			}
			$messages = array();
			foreach (array_slice((array)($input['messages'] ?? array()), -self::MAX_HISTORY) as $m) {
				if (!is_array($m) || !in_array($m['role'] ?? '', array('user', 'assistant'), true)) {
					continue;
				}
				$content = (string)($m['content'] ?? '');
				if ($content === '') {
					continue;
				}
				$messages[] = array('role' => $m['role'], 'content' => mb_substr($content, 0, self::MAX_MESSAGE));
			}
			$model = isset($input['model']) && is_string($input['model']) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $input['model'])
				? $input['model'] : null;
			$section = trim(mb_substr((string)($input['section'] ?? ''), 0, 200));
			$file_id = (string)($input['file_id'] ?? '');
			if ($file_id !== '' && !preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $file_id)) {
				$file_id = '';
			}

			$this->rate_limit();

			$bundle = array('section' => $section !== '' ? $section : null);
			$sources = array();
			$sid_param = (string)($input['sid'] ?? '');
			if ($sid_param !== '') {
				$sid = $this->get_sid($sid_param);
				try {
					$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
				} catch (Exception $e) {
					throw new Exception($e->getMessage(), 403);
				}
				$this->load->library('Assistant_context');
				$bundle = $this->assistant_context->bundle($sid, $file_id, $section, $this->can($sid, 'edit'), $this->get_api_user_id());
				$sources = $this->resolve_sources($sid, $input['sources'] ?? array());
			}

			$payload = array(
				'protocol' => self::PROTOCOL,
				'question' => $question,
				'messages' => $messages,
				'mode' => isset($bundle['record_id']) ? 'record' : 'app',
				'bundle' => $bundle,
			);
			if ($model !== null) {
				$payload['model'] = $model;
			}
			if (isset($sid) && $this->keep_local($sid)) {
				$payload['keep_local'] = true;
			}
			$document = (string)($input['document'] ?? '');
			if ($sources) {
				// Indexed sources replace pasted text: the service retrieves passages instead of reading it whole.
				$payload['sid'] = $this->source_scope($sid);
				$payload['sources'] = $sources;
			} elseif ($document !== '') {
				$payload['document'] = mb_substr($document, 0, 60000);
			}
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404, 409, 429), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}

		$this->stream($payload);
	}


	/**
	 * Whole-record review as a job. The turn runs on the service (many tool rounds), so it is started
	 * there, polled through `review_get` and cancelled through `review_delete`; the panel keeps the
	 * conversation.
	 */
	function review_post()
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$input = (array)$this->raw_json_input();
			$sid_param = (string)($input['sid'] ?? '');
			$sid = $this->get_sid($sid_param);
			$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
			$can_fill = $this->can($sid, 'edit');
			$this->rate_limit();
			$this->load->library('Assistant_context');
			$section = trim(mb_substr((string)($input['section'] ?? ''), 0, 200));
			$file_id = (string)($input['file_id'] ?? '');
			if ($file_id !== '' && !preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $file_id)) {
				$file_id = '';
			}
			$kind = (string)($input['kind'] ?? '');
			if ($kind === 'variables') {
				// Variables are written back, so only someone who may edit gets to start the job.
				if (!$can_fill) {
					throw new Exception('You can view this project but not edit it.', 403);
				}
				$targets = $file_id !== '' ? $this->assistant_context->label_targets($sid, $file_id) : null;
				if ($targets === null) {
					throw new Exception('Open a data file of this project first.', 400);
				}
				$bundle = array('record_id' => (string)$sid, 'file_id' => $file_id, 'datafile' => $targets, 'fill' => true);
			} else {
				$bundle = $this->assistant_context->bundle($sid, $file_id, $section, $can_fill, $this->get_api_user_id());
			}
			$payload = array(
				'protocol' => self::PROTOCOL,
				'bundle' => $bundle,
				// Named by a hash: the service caps one person's running jobs and never learns who they are.
				'owner' => $this->owner_hash(),
			);
			if ($this->keep_local($sid)) {
				$payload['keep_local'] = true;
			}
			$model = isset($input['model']) && is_string($input['model']) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $input['model']) ? $input['model'] : null;
			if ($model !== null) {
				$payload['model'] = $model;
			}
			$document = (string)($input['document'] ?? '');
			$sources = $this->resolve_sources($sid, $input['sources'] ?? array());
			if ($sources) {
				$payload['sid'] = $this->source_scope($sid);
				$payload['sources'] = $sources;
			} elseif ($document !== '') {
				$payload['document'] = mb_substr($document, 0, 60000);
			}
			if ($kind === 'document' || $kind === 'variables') {
				if (!$sources && $document === '') {
					throw new Exception(empty($input['sources'])
						? 'Add a source to fill from it.'
						: 'None of the ticked sources is available any more. Add one or tick another.', 400);
				}
				$payload['kind'] = $kind;
			}
			if ($kind === 'variables') {
				$attrs = array_values(array_intersect(array('label', 'description', 'question'), array_map('strval', (array)($input['attrs'] ?? array('label')))));
				$payload['attrs'] = $attrs ? $attrs : array('label');
			}
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404, 409, 429), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}
		list($code, $body) = $this->call_service('POST', 'assistant/review/jobs', $payload, 30);
		if ($code === 200 && isset($body['job_id']) && is_string($body['job_id'])) {
			// Only the session that started a job may read or cancel it: its result is this project's
			// record, and job ids are visible in the browser.
			$jobs = array_slice(array_values(array_filter((array)$this->session->userdata('assistant_jobs'), 'is_string')), -19);
			$jobs[] = $body['job_id'];
			$this->session->set_userdata('assistant_jobs', $jobs);
		}
		return $this->respond_service($code, $body);
	}

	/**
	 * Write the variable text a curator accepted in the panel (labels, descriptions, question text). An
	 * explicit action on a list the curator ticked, never something the model does by itself. The same
	 * endpoint undoes it: send the `undo` values it returned.
	 */
	function labels_post()
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$input = (array)$this->raw_json_input();
			$sid = $this->get_sid((string)($input['sid'] ?? ''));
			try {
				$this->editor_acl->user_has_project_access($sid, 'edit', $this->api_user);
			} catch (Exception $e) {
				throw new Exception($e->getMessage(), 403);
			}
			$file_id = (string)($input['file_id'] ?? '');
			if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $file_id)) {
				throw new Exception('A data file is required.', 400);
			}
			$changes = array_values(array_filter((array)($input['changes'] ?? array()), 'is_array'));
			if (!$changes || count($changes) > 600) {
				throw new Exception('Send between 1 and 600 changes at a time.', 400);
			}
			$this->load->library('Assistant_context');
			session_write_close();
			$this->db->trans_start();
			$result = $this->assistant_context->apply_variable_text($sid, $file_id, $changes);
			$this->db->trans_complete();
			if ($this->db->trans_status() === false) {
				throw new Exception('The labels could not be saved. Nothing was changed.', 500);
			}
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404, 500), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}
		return $this->set_response(array('status' => 'success') + $result, REST_Controller::HTTP_OK);
	}

	/** A job id this session started, or null (also null for anything that is not a job id). */
	private function own_job($jobid)
	{
		if (!is_string($jobid) || !preg_match('/^job-[0-9.]+(-[0-9a-f]{12})?$/D', $jobid)) {
			return null;
		}
		return in_array($jobid, (array)$this->session->userdata('assistant_jobs'), true) ? $jobid : null;
	}

	function review_get($jobid = null)
	{
		if (!$this->enabled()) {
			return $this->set_response(array('status' => 'failed', 'message' => 'The server assistant is not enabled.'), 404);
		}
		$jobid = $this->own_job($jobid);
		session_write_close(); // polled every second or two; never hold the session meanwhile
		if ($jobid === null) {
			return $this->set_response(array('status' => 'failed', 'message' => 'This run was not found. Start it again.'), 404);
		}
		return $this->relay('GET', 'jobs/' . rawurlencode($jobid));
	}

	function review_delete($jobid = null)
	{
		if (!$this->enabled()) {
			return $this->set_response(array('status' => 'failed', 'message' => 'The server assistant is not enabled.'), 404);
		}
		$jobid = $this->own_job($jobid);
		session_write_close();
		if ($jobid === null) {
			return $this->set_response(array('status' => 'failed', 'message' => 'This review was not found.'), 404);
		}
		return $this->relay('DELETE', 'jobs/' . rawurlencode($jobid));
	}

	/**
	 * Text of an attachment for a task that reads it (a resource description drafted from a PDF). The
	 * path must resolve inside the editor's storage folder: the service only reads files there.
	 */
	function extract_post()
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$input = (array)$this->raw_json_input();
			$sid = $this->get_sid((string)($input['sid'] ?? ''));
			$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
			$file_path = (string)($input['file_path'] ?? '');
			$root = $this->storage_root();
			$real = $file_path !== '' ? realpath($file_path) : false;
			if (!$root || !$real || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
				throw new Exception('The file is not in the editor storage directory.', 400);
			}
			// Permission was checked on THIS project, so the file must belong to it: another project's
			// folder is another project's data, however the path was spelled.
			$this->load->model('Editor_model');
			$project_folder = $this->Editor_model->get_project_folder($sid);
			$project_folder = $project_folder ? realpath($project_folder) : false;
			if (!$project_folder || strpos($real, $project_folder . DIRECTORY_SEPARATOR) !== 0) {
				throw new Exception('The file is not in this project\'s folder.', 400);
			}
			$payload = array('protocol' => self::PROTOCOL, 'file_path' => $real);
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404, 409, 429), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}
		return $this->relay('POST', 'assistant/documents/extract', $payload);
	}


	const MAX_ATTACH_BYTES = 8388608;
	const ATTACH_EXTENSIONS = array('pdf', 'txt', 'md');

	/**
	 * One-shot draft: a data-file description or one field, optionally grounded in an attached
	 * document's text. Runs the service's non-streaming task endpoint and returns {suggestion, reason}.
	 */
	function suggest_post()
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$input = (array)$this->raw_json_input();
			$sid = $this->get_sid((string)($input['sid'] ?? ''));
			$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
			$task = (string)($input['task'] ?? '');
			if (!in_array($task, array('datafile-description', 'resource-field'), true)) {
				throw new Exception('Unsupported task.', 400);
			}
			$field = $task === 'resource-field' ? trim((string)($input['field'] ?? '')) : null;
			if ($task === 'resource-field' && ($field === '' || $field[0] !== '/')) {
				throw new Exception('A field pointer is required for this task.', 400);
			}
			$file_id = (string)($input['file_id'] ?? '');
			if ($file_id !== '' && !preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $file_id)) {
				$file_id = '';
			}
			$this->rate_limit();
			$this->load->library('Assistant_context');
			$payload = array(
				'protocol' => self::PROTOCOL,
				'task' => $task,
				'bundle' => $this->assistant_context->bundle($sid, $file_id, '', $this->can($sid, 'edit'), $this->get_api_user_id()),
			);
			if ($field !== null) {
				$payload['field'] = mb_substr($field, 0, 300);
			}
			$document = (string)($input['document'] ?? '');
			$sources = $this->resolve_sources($sid, $input['sources'] ?? array());
			if ($sources) {
				$payload['sid'] = $this->source_scope($sid);
				$payload['sources'] = $sources;
			} elseif ($document !== '') {
				$payload['document'] = mb_substr($document, 0, 60000);
			}
			$model = isset($input['model']) && is_string($input['model']) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $input['model']) ? $input['model'] : null;
			if ($model !== null) {
				$payload['model'] = $model;
			}
			if ($this->keep_local($sid)) {
				$payload['keep_local'] = true;
			}
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404, 409, 429), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}
		// A draft waits for a free model slot and then generates: release the session so this user's
		// other requests (saving the form, polling a review) are not queued behind it.
		session_write_close();
		set_time_limit(0);
		return $this->relay('POST', 'assistant/suggest', $payload, 180);
	}

	/**
	 * The project's knowledge sources: External resources with a readable file, and the chat attachments
	 * registered for this project. Every call syncs, so it doubles as the index-status poll while the
	 * service embeds. Resources it cannot read (links, missing files, other types) come back in `skipped`.
	 */
	function sources_get()
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$sid = $this->get_sid((string)$this->input->get('sid'));
			try {
				$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
			} catch (Exception $e) {
				throw new Exception($e->getMessage(), 403);
			}
			session_write_close(); // first indexing of a long PDF takes seconds
			set_time_limit(0);
			list($files, $skipped) = $this->resource_files($sid);
			$sources = array();
			if ($files) {
				list($code, $body) = $this->call_service('POST', 'assistant/knowledge/sync', array(
					'protocol' => self::PROTOCOL, 'sid' => $this->source_scope($sid), 'files' => $files,
				), 120);
				if ($code !== 200 || !isset($body['sources'])) {
					return $this->respond_service($code, $body);
				}
				foreach ($body['sources'] as $source) {
					$sources[] = $this->describe_source($source);
				}
			}
			list($code, $body) = $this->call_service('POST', 'assistant/knowledge/list', array(
				'protocol' => self::PROTOCOL, 'sid' => $this->source_scope($sid),
			), 30);
			if ($code !== 200 || !isset($body['sources'])) {
				return $this->respond_service($code, $body);
			}
			// An attachment with the same content as a resource is that resource: list it once.
			$have = array();
			foreach ($sources as $source) {
				if (!empty($source['hash'])) {
					$have[$source['hash']] = true;
				}
			}
			foreach ($body['sources'] as $source) {
				if (!empty($source['hash']) && isset($have[$source['hash']])) {
					continue;
				}
				$sources[] = $this->describe_source($source);
			}
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}
		return $this->set_response(array('status' => 'success', 'sources' => $sources, 'skipped' => $skipped), REST_Controller::HTTP_OK);
	}

	/**
	 * Forget a chat attachment (it is no longer searchable from this project). A resource is the
	 * project's own file: it leaves the index when the editor deletes it.
	 */
	function sources_delete($id = null)
	{
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$sid = $this->get_sid((string)$this->input->get('sid'));
			try {
				$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
			} catch (Exception $e) {
				throw new Exception($e->getMessage(), 403);
			}
			$id = (string)$id;
			if (preg_match('/^res-[0-9]{1,12}$/D', $id)) {
				throw new Exception('This is one of the project\'s External resources. Remove it on the Resources page.', 400);
			}
			if (!preg_match('/^att-[0-9a-f]{12}$/D', $id)) {
				throw new Exception('Unknown source.', 400);
			}
			session_write_close();
			$attachments = $this->attachment_map($sid);
			if (!isset($attachments[$id])) {
				throw new Exception('This source was not found. It may already have been removed.', 404);
			}
			list($code, $body) = $this->call_service('POST', 'assistant/knowledge/remove', array(
				'protocol' => self::PROTOCOL, 'sid' => $this->source_scope($sid), 'hashes' => array($attachments[$id]['hash']),
			), 30);
			if ($code !== 200) {
				return $this->respond_service($code, $body);
			}
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		}
		return $this->set_response(array('status' => 'success', 'id' => $id), REST_Controller::HTTP_OK);
	}

	/**
	 * A document the curator picked, indexed for retrieval; nothing is read whole afterwards. PDF, text
	 * or Markdown, 8 MB. A file identical to one of the project's External resources is not indexed twice:
	 * that resource comes back with `duplicate_of`. With save=1 (edit permission) the file is stored as a
	 * new External resource and indexed as that; otherwise the upload is deleted once indexed and only a
	 * hash and the extracted passages stay on the service.
	 */
	function attach_post()
	{
		$stored = null;
		try {
			if (!$this->enabled()) {
				throw new Exception('The server assistant is not enabled.', 404);
			}
			$sid = $this->get_sid((string)$this->input->post('sid'));
			$this->editor_acl->user_has_project_access($sid, 'view', $this->api_user);
			$save = (string)$this->input->post('save') === '1';
			if ($save && !$this->can($sid, 'edit')) {
				throw new Exception('You can view this project but not edit it, so the file cannot be saved to its External resources.', 403);
			}
			session_write_close(); // extracting a long PDF takes seconds; nothing below writes the session
			set_time_limit(0);
			$file = isset($_FILES['file']) ? $_FILES['file'] : null;
			if (!is_array($file) || !isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				throw new Exception('Choose a file to attach.', 400);
			}
			if ((int)$file['size'] > self::MAX_ATTACH_BYTES) {
				throw new Exception('The file is larger than 8 MB.', 400);
			}
			$name = (string)($file['name'] ?? '');
			$ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
			if (!in_array($ext, self::ATTACH_EXTENSIONS, true)) {
				throw new Exception('Attach a PDF, text or Markdown file.', 400);
			}
			$name = mb_substr(basename(str_replace('\\', '/', $name)), 0, 200);

			// The same bytes as an External resource of this project: use that one (size first, it is cheap).
			$hash = hash_file('sha256', $file['tmp_name']);
			list($resources) = $this->resource_files($sid);
			foreach ($resources as $resource) {
				if ((int)@filesize($resource['path']) === (int)$file['size'] && @hash_file('sha256', $resource['path']) === $hash) {
					$source = $this->sync_one($sid, $resource);
					return $this->set_response(array(
						'status' => 'success', 'source' => $this->describe_source($source), 'duplicate_of' => $resource['id'], 'saved' => true,
					), REST_Controller::HTTP_OK);
				}
			}

			// The FastAPI service reads the file. Installed with its deploy scripts it runs as its own user
			// that shares a group with the web server; so: group-readable, closed to everyone else, setgid so
			// files keep the shared group (a plain chmod would drop it). On Windows chmod changes nothing and
			// the folder inherits the ACL the install script set on the storage folder.
			$root = $this->storage_root();
			if ($root === null) {
				throw new Exception('The assistant cannot store the attachment.', 500);
			}
			$dir = $root . DIRECTORY_SEPARATOR . 'assistant-tmp';
			if (!is_dir($dir) && !@mkdir($dir, 02750, true) && !is_dir($dir)) {
				throw new Exception('The assistant cannot store the attachment.', 500);
			}
			@chmod($dir, 02750);
			$stored = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
			if (!move_uploaded_file($file['tmp_name'], $stored)) {
				$stored = null;
				throw new Exception('The upload failed.', 500);
			}
			@chmod($stored, 0640); // uploads arrive 0600, which only the web server can read
			$source = $this->sync_one($sid, array(
				'id' => 'att-' . substr($hash, 0, 12), 'kind' => 'attachment', 'name' => $name, 'path' => $stored,
			));
			if ($save) {
				$resource = $this->save_as_resource($sid, $stored, $name);
				$stored = null; // moved into the project
				// The same content is now a resource: drop the attachment registration first, so the
				// resource entry is the only one for it.
				$this->call_service('POST', 'assistant/knowledge/remove', array(
					'protocol' => self::PROTOCOL, 'sid' => $this->source_scope($sid), 'hashes' => array(isset($source['hash']) ? $source['hash'] : $hash),
				), 30);
				$source = $this->sync_one($sid, $resource);
			}
		} catch (Exception $e) {
			$code = in_array($e->getCode(), array(400, 403, 404, 429, 500), true) ? $e->getCode() : 400;
			return $this->set_response(array('status' => 'failed', 'message' => $e->getMessage()), $code);
		} finally {
			if ($stored !== null && is_file($stored)) {
				@unlink($stored);
			}
		}
		return $this->set_response(array('status' => 'success', 'source' => $this->describe_source($source), 'saved' => $save), REST_Controller::HTTP_OK);
	}

	const KNOWLEDGE_EXTENSIONS = array('pdf', 'txt', 'md', 'csv', 'tsv', 'json', 'xml', 'html', 'htm');
	const MAX_SOURCES = 20;

	/** A service Source as the panel receives it: ticked for use by default, `saved` when it is a project resource. */
	private function describe_source($source)
	{
		$source = (array)$source;
		$source['selected'] = true;
		$source['saved'] = ($source['kind'] ?? '') === 'resource';
		return $source;
	}

	/** A short stable name for the signed-in person; the service never learns who they are. */
	private function owner_hash()
	{
		return substr(hash('sha256', 'assistant|' . (string)$this->get_api_user_id()), 0, 16);
	}

	/**
	 * The key the service registers a person's chat attachments under: this project AND this person
	 * ("12.ab34..."), so a file attached by one user is neither listed to nor removable by another user of
	 * the same project. The service treats it as an opaque string; resources are read by path and are not
	 * affected.
	 */
	private function source_scope($sid)
	{
		return (string)$sid . '.' . $this->owner_hash();
	}

	/** The editor's storage root, the only place the service may read files from. */
	private function storage_root()
	{
		$this->load->model('Editor_model');
		$root = realpath($this->Editor_model->get_storage_path());
		if ($root === false) {
			$root = realpath(FCPATH . 'datafiles');
		}
		return $root === false ? null : $root;
	}

	/**
	 * This project's External resources the service can read: [files, skipped]. A file is
	 * {id: res-<resource id>, kind, name, path} with a real path inside the storage root; the id comes
	 * from the database, never from the browser. `skipped` lists the others with the reason.
	 */
	private function resource_files($sid)
	{
		$this->load->model('Editor_resource_model');
		$root = $this->storage_root();
		$files = array();
		$skipped = array();
		foreach ($this->Editor_resource_model->select_all_with_path($sid) as $row) {
			$id = 'res-' . (int)$row['id'];
			$filename = (string)($row['filename'] ?? '');
			$label = $filename !== '' ? $filename : (string)($row['title'] ?? $id);
			if ($filename === '' || empty($row['path'])) {
				$skipped[] = array('id' => $id, 'name' => $label, 'reason' => 'A link, not a file.');
				continue;
			}
			$ext = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
			if (!in_array($ext, self::KNOWLEDGE_EXTENSIONS, true)) {
				$skipped[] = array('id' => $id, 'name' => $label, 'reason' => 'The assistant cannot read ' . ($ext !== '' ? '.' . $ext : 'this') . ' files.');
				continue;
			}
			$real = realpath($row['path']);
			if ($real === false || !is_file($real)) {
				$skipped[] = array('id' => $id, 'name' => $label, 'reason' => 'The file is missing on the server.');
				continue;
			}
			if ($root === null || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
				$skipped[] = array('id' => $id, 'name' => $label, 'reason' => 'The file is outside the editor storage.');
				continue;
			}
			$files[] = array('id' => $id, 'kind' => 'resource', 'name' => mb_substr($filename, 0, 200), 'path' => $real);
		}
		return array($files, $skipped);
	}

	/** The attachments registered for this project, keyed by id: [id => Source]. Empty when the service cannot say. */
	private function attachment_map($sid)
	{
		list($code, $body) = $this->call_service('POST', 'assistant/knowledge/list', array(
			'protocol' => self::PROTOCOL, 'sid' => $this->source_scope($sid),
		), 30);
		$map = array();
		if ($code === 200 && isset($body['sources']) && is_array($body['sources'])) {
			foreach ($body['sources'] as $source) {
				if (is_array($source) && isset($source['id'], $source['hash']) && is_string($source['id'])) {
					$map[$source['id']] = $source;
				}
			}
		}
		return $map;
	}

	/** Index one file under this project and return its Source; throws with the service's own reason. */
	private function sync_one($sid, array $file)
	{
		list($code, $body) = $this->call_service('POST', 'assistant/knowledge/sync', array(
			'protocol' => self::PROTOCOL, 'sid' => $this->source_scope($sid), 'files' => array($file),
		), 120);
		if ($code !== 200 || !is_array($body) || empty($body['sources'][0])) {
			$detail = is_array($body) && isset($body['detail']) ? $body['detail'] : 'The document could not be read.';
			throw new Exception(is_string($detail) ? $detail : 'The document could not be read.', 400);
		}
		return $body['sources'][0];
	}

	/**
	 * Store an uploaded file as a new External resource of the project, the way the Resources page does
	 * (documentation folder, normalised file name, a resources row), without overwriting anything.
	 * Returns the file as resource_files() lists it.
	 */
	private function save_as_resource($sid, $stored, $name)
	{
		$this->load->model('Editor_model');
		$this->load->model('Editor_resource_model');
		$folder = $this->Editor_model->get_project_folder($sid);
		if (!$folder) {
			$this->Editor_model->create_project_folder($sid);
			$folder = $this->Editor_model->get_project_folder($sid);
		}
		if (!$folder) {
			throw new Exception('The project folder could not be found.', 500);
		}
		$dir = $folder . '/documentation';
		if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
			throw new Exception('The project\'s documentation folder could not be created.', 500);
		}
		$base = $this->Editor_resource_model->normalize_filename($name);
		$stem = (string)pathinfo($base, PATHINFO_FILENAME);
		$ext = (string)pathinfo($base, PATHINFO_EXTENSION);
		$filename = $base;
		for ($n = 1; is_file($dir . '/' . $filename) || $this->Editor_resource_model->check_filename_in_use($sid, $filename); $n++) {
			if ($n > 200) {
				throw new Exception('A free file name could not be found.', 500);
			}
			$filename = $stem . '-' . $n . ($ext !== '' ? '.' . $ext : '');
		}
		$target = $dir . '/' . $filename;
		if (!@rename($stored, $target)) {
			if (!@copy($stored, $target)) {
				throw new Exception('The file could not be saved to the project.', 500);
			}
			@unlink($stored);
		}
		@chmod($target, 0644);
		try {
			$id = $this->Editor_resource_model->insert(array(
				'sid' => $sid,
				'dctype' => 'doc/oth',
				'title' => mb_substr($name, 0, 255),
				'filename' => $filename,
			));
		} catch (Exception $e) {
			@unlink($target);
			throw $e;
		}
		if (!$id) {
			@unlink($target);
			throw new Exception('The resource could not be created.', 500);
		}
		return array('id' => 'res-' . (int)$id, 'kind' => 'resource', 'name' => mb_substr($filename, 0, 200), 'path' => realpath($target));
	}

	/**
	 * The ticked source ids the browser sent, resolved here: a resource id only if it is a readable
	 * resource of THIS project (its path comes from the database), an attachment id only if the service
	 * has it registered under this project. Unknown ids are dropped; the browser never supplies a path.
	 */
	private function resolve_sources($sid, $ids)
	{
		$ids = array_values(array_unique(array_filter(array_map('strval', is_array($ids) ? $ids : array()), function ($id) {
			return (bool)preg_match('/^(res-[0-9]{1,12}|att-[0-9a-f]{12})$/D', $id);
		})));
		$ids = array_slice($ids, 0, self::MAX_SOURCES);
		$refs = array();
		$resources = null;
		$attachments = null;
		foreach ($ids as $id) {
			if (strpos($id, 'res-') === 0) {
				if ($resources === null) {
					$resources = array();
					list($files) = $this->resource_files($sid);
					foreach ($files as $file) {
						$resources[$file['id']] = $file;
					}
				}
				if (isset($resources[$id])) {
					$refs[] = $resources[$id];
				}
			} else {
				if ($attachments === null) {
					$attachments = $this->attachment_map($sid);
				}
				if (isset($attachments[$id])) {
					$refs[] = array('kind' => 'attachment', 'id' => $id, 'name' => (string)($attachments[$id]['name'] ?? ''), 'hash' => $attachments[$id]['hash']);
				}
			}
		}
		return $refs;
	}

	/** Send one JSON request to the service and return its JSON answer unchanged. */
	private function relay($method, $path, $payload = null, $timeout = 30)
	{
		list($code, $body) = $this->call_service($method, $path, $payload, $timeout);
		return $this->respond_service($code, $body);
	}

	/** [status, decoded body]; status 0 when the service could not be reached, body null when unreadable. */
	private function call_service($method, $path, $payload, $timeout)
	{
		$client = new Client(array('base_uri' => $this->api_base(), 'http_errors' => false, 'connect_timeout' => 5, 'timeout' => $timeout));
		try {
			$options = array('headers' => array('Accept' => 'application/json'));
			if ($payload !== null) {
				$options['json'] = $payload;
			}
			$response = $client->request($method, $path, $options);
		} catch (Exception $e) {
			log_message('error', 'Assistant::relay ' . $e->getMessage());
			return array(0, null);
		}
		$body = json_decode((string)$response->getBody(), true);
		return array($response->getStatusCode(), is_array($body) ? $body : null);
	}

	private function respond_service($code, $body)
	{
		if ($code === 0) {
			return $this->set_response(array('status' => 'failed', 'message' => 'The assistant service is not reachable.'), REST_Controller::HTTP_BAD_GATEWAY);
		}
		if ($body === null) {
			return $this->set_response(array('status' => 'failed', 'message' => 'The assistant service returned an unreadable answer.'), REST_Controller::HTTP_BAD_GATEWAY);
		}
		// The service reports errors as {detail}; the panel reads {message}. Relay both.
		if (!isset($body['message']) && isset($body['detail'])) {
			$body['message'] = is_string($body['detail']) ? $body['detail'] : 'The assistant service rejected the request.';
		}
		return $this->set_response($body, $code);
	}

	/**
	 * Per-session limit on turns per minute (editor.server_assistant_rate_per_minute, 0 = off). The
	 * service has its own concurrency limit; this keeps one tab from monopolising it.
	 */
	private function rate_limit()
	{
		$limit = (int)$this->config->item('server_assistant_rate_per_minute', 'editor');
		if ($limit <= 0) {
			return;
		}
		$now = time();
		$recent = array_values(array_filter((array)$this->session->userdata('assistant_turns'), function ($t) use ($now) {
			return is_int($t) && $t > $now - 60;
		}));
		if (count($recent) >= $limit) {
			throw new Exception('Too many questions in the last minute. Wait a moment and try again.', 429);
		}
		$recent[] = $now;
		$this->session->set_userdata('assistant_turns', $recent);
	}

	/**
	 * Relay the service's event stream as it arrives. The session is released first: a turn can take a
	 * minute, and a locked session would stall every other request from this user meanwhile.
	 */
	private function stream($payload)
	{
		session_write_close();
		set_time_limit(0);

		$client = new Client(array('base_uri' => $this->api_base(), 'http_errors' => false, 'connect_timeout' => 5, 'read_timeout' => 300));
		try {
			// HTTP/1.0 on purpose: with `stream`, Guzzle reads through PHP's http:// wrapper, whose filter for
			// HTTP/1.1 chunked responses holds the whole body until the end, so the curator saw the answer only
			// when it was complete (measured: every byte at 3.6 s instead of tokens from 0.8 s). An HTTP/1.0
			// response is the raw body, read as it arrives. The service is local; nothing else depends on 1.1.
			$response = $client->request('POST', 'assistant/chat/stream', array(
				'json' => $payload,
				'stream' => true,
				'version' => '1.0',
				'headers' => array('Accept' => 'text/event-stream'),
			));
		} catch (Exception $e) {
			log_message('error', 'Assistant::stream ' . $e->getMessage());
			return $this->set_response(array('status' => 'failed', 'message' => 'The assistant service is not reachable.'), REST_Controller::HTTP_BAD_GATEWAY);
		}

		$code = $response->getStatusCode();
		if ($code !== 200) {
			$decoded = json_decode((string)$response->getBody(), true);
			$message = is_array($decoded) && isset($decoded['detail']) ? (is_string($decoded['detail']) ? $decoded['detail'] : 'Invalid request.') : 'The assistant service failed.';
			$out = in_array($code, array(400, 409, 429, 503), true) ? $code : 502;
			return $this->set_response(array('status' => 'failed', 'message' => $message), $out);
		}

		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		if (function_exists('apache_setenv')) {
			@apache_setenv('no-gzip', '1');
		}
		@ini_set('zlib.output_compression', '0');
		header('Content-Type: text/event-stream; charset=utf-8');
		header('Cache-Control: no-cache, no-transform');
		header('X-Accel-Buffering: no');

		$body = $response->getBody();
		while (!$body->eof()) {
			$chunk = $body->read(8192);
			if ($chunk === '') {
				continue;
			}
			echo $chunk;
			flush();
			if (connection_aborted()) {
				break; // closing the upstream read tells the service to stop generating
			}
		}
		$body->close();
		exit;
	}
}
