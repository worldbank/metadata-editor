<?php

require(APPPATH . '/libraries/MY_REST_Controller.php');

/**
 * Study-metadata project translations.
 *
 * GET  /api/editor/{sid}/translations
 * POST /api/editor/{sid}/translations
 * POST /api/editor/{sid}/translations/language
 * GET  /api/editor/{sid}/translations/{language}
 * POST /api/editor/{sid}/translations/{language}
 * GET  /api/editor/{sid}/translations/{language}/export
 * GET  /api/editor/{sid}/translations/{language}/overlay
 * GET  /api/editor/{sid}/translations/overlay/source
 * POST /api/editor/{sid}/translations/overlay
 * POST /api/editor/{sid}/translations/{language}/delete
 */
class Translations extends MY_REST_Controller
{
	private $user;
	private $user_id;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('date');
		$this->load->model('Editor_model');
		$this->load->model('Project_translations_model');
		$this->load->library('Editor_acl');
		$this->load->library('Audit_log');
		$this->is_authenticated_or_die();
		$this->user = $this->api_user();
		$this->user_id = $this->get_api_user_id();
	}

	function _auth_override_check()
	{
		if ($this->session->userdata('user_id')) {
			return true;
		}
		parent::_auth_override_check();
	}

	/**
	 * GET /api/translations/project/{sid}
	 */
	public function project_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'view', $this->user);

			$response = array(
				'status' => 'success',
				'result' => $this->Project_translations_model->list_translations($sid),
			);
			$this->set_response($response, REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * POST /api/translations/project/{sid}
	 * Body: { "language": "es" }
	 */
	public function project_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'edit', $this->user);

			$options = $this->raw_json_input();
			if (!is_array($options)) {
				$options = array();
			}
			if (!isset($options['language'])) {
				throw new Exception('LANGUAGE_REQUIRED');
			}

			$result = $this->Project_translations_model->add_translation($sid, $options['language'], $this->user_id);
			$this->audit_log->log_event('project', $sid, 'translation_add', array(
				'language' => $result['language'],
			), $this->user_id);

			$this->set_response(array(
				'status' => 'success',
				'result' => $result,
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * POST /api/translations/language/{sid}
	 * Body: { "language": "en" }
	 */
	public function language_post($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'edit', $this->user);

			$options = $this->raw_json_input();
			if (!is_array($options)) {
				$options = array();
			}
			if (!isset($options['language'])) {
				throw new Exception('LANGUAGE_REQUIRED');
			}

			$language = $this->Project_translations_model->set_project_language(
				$sid,
				$options['language'],
				$this->user_id
			);
			$this->audit_log->log_event('project', $sid, 'translation_language', array(
				'language' => $language,
			), $this->user_id);

			$this->set_response(array(
				'status' => 'success',
				'result' => array(
					'language' => $language,
				),
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * GET /api/translations/fields/{sid}/{language}
	 */
	public function fields_get($sid = null, $language = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'view', $this->user);

			if ($language === null || $language === '') {
				throw new Exception('LANGUAGE_REQUIRED');
			}

			$this->set_response(array(
				'status' => 'success',
				'result' => $this->Project_translations_model->get_translation($sid, $language),
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * POST /api/translations/fields/{sid}/{language}
	 * Body: { "metadata": { "study_desc": { ... } } }
	 * Also accepts { "fields": [ { "field_path": "...", "value": "..." } ] }
	 */
	public function fields_post($sid = null, $language = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'edit', $this->user);

			if ($language === null || $language === '') {
				throw new Exception('LANGUAGE_REQUIRED');
			}

			$options = $this->raw_json_input();
			if (!is_array($options)) {
				$options = array();
			}

			if (isset($options['metadata']) && is_array($options['metadata'])) {
				$metadata = $options['metadata'];
			} elseif (isset($options['fields']) && is_array($options['fields'])) {
				$metadata = $this->Project_translations_model->tree_from_fields($options['fields']);
			} else {
				throw new Exception('METADATA_REQUIRED');
			}

			$result = $this->Project_translations_model->save_metadata(
				$sid,
				$language,
				$metadata,
				$this->user_id
			);
			$this->audit_log->log_event('project', $sid, 'translation_save', array(
				'language' => $result['language'],
				'fields_count' => $result['fields_count'],
			), $this->user_id);

			$this->set_response(array(
				'status' => 'success',
				'result' => $result,
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * GET /api/translations/export/{sid}/{language}
	 * JSON with only translated study fields. Arrays stay arrays.
	 * Query: download=1 — attachment; otherwise inline in the browser.
	 */
	public function export_get($sid = null, $language = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'view', $this->user);

			if ($language === null || $language === '') {
				throw new Exception('LANGUAGE_REQUIRED');
			}

			$export = $this->Project_translations_model->export_translation_document($sid, $language);
			$json = json_encode($export['document'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			if ($json === false) {
				throw new Exception('FAILED_TO_ENCODE_TRANSLATION');
			}

			$download = filter_var($this->input->get('download'), FILTER_VALIDATE_BOOLEAN);
			$disposition = $download ? 'attachment' : 'inline';

			header('Content-Type: application/json; charset=utf-8');
			header('Content-Disposition: ' . $disposition . '; filename="' . $export['filename'] . '"');
			echo $json;
			die();
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * GET /api/translations/overlay_source/{sid}
	 * Source metadata as overlay interchange JSON. Query: download=1 — attachment.
	 */
	public function overlay_source_get($sid = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'view', $this->user);

			$export = $this->Project_translations_model->export_source_overlay_document($sid);
			$json = json_encode($export['document'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			if ($json === false) {
				throw new Exception('FAILED_TO_ENCODE_OVERLAY');
			}

			$download = filter_var($this->input->get('download'), FILTER_VALIDATE_BOOLEAN);
			$disposition = $download ? 'attachment' : 'inline';

			header('Content-Type: application/json; charset=utf-8');
			header('Content-Disposition: ' . $disposition . '; filename="' . $export['filename'] . '"');
			echo $json;
			die();
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * GET /api/translations/overlay/{sid}/{language}
	 * Overlay interchange JSON. Query: download=1 — attachment.
	 */
	public function overlay_get($sid = null, $language = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'view', $this->user);

			if ($language === null || $language === '') {
				throw new Exception('LANGUAGE_REQUIRED');
			}

			$export = $this->Project_translations_model->export_overlay_document($sid, $language);
			$json = json_encode($export['document'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			if ($json === false) {
				throw new Exception('FAILED_TO_ENCODE_OVERLAY');
			}

			$download = filter_var($this->input->get('download'), FILTER_VALIDATE_BOOLEAN);
			$disposition = $download ? 'attachment' : 'inline';

			header('Content-Type: application/json; charset=utf-8');
			header('Content-Disposition: ' . $disposition . '; filename="' . $export['filename'] . '"');
			echo $json;
			die();
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	/**
	 * POST /api/translations/overlay/{sid}
	 * Import an overlay interchange file (JSON body or uploaded file).
	 */
	public function overlay_post($sid = null, $language = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'edit', $this->user);

			$document = $this->read_overlay_document();
			if ($language !== null && $language !== '') {
				$file_language = isset($document['language']) ? $document['language'] : '';
				if ($file_language !== '' &&
					$this->Project_translations_model->normalize_language_code($file_language)
					!== $this->Project_translations_model->normalize_language_code($language)
				) {
					throw new Exception('OVERLAY_LANGUAGE_MISMATCH');
				}
				if ($file_language === '') {
					$document['language'] = $language;
				}
			}

			$result = $this->Project_translations_model->import_overlay($sid, $document, $this->user_id);
			$this->audit_log->log_event('project', $sid, 'translation_overlay_import', array(
				'language' => $result['language'],
				'fields_count' => $result['fields_count'],
			), $this->user_id);

			$this->set_response(array(
				'status' => 'success',
				'result' => $result,
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	private function read_overlay_document()
	{
		if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
			$raw = file_get_contents($_FILES['file']['tmp_name']);
			$document = json_decode($raw, true);
			if (!is_array($document)) {
				throw new Exception('INVALID_OVERLAY_JSON');
			}
			return $document;
		}

		$document = $this->raw_json_input();
		if (!is_array($document)) {
			throw new Exception('INVALID_OVERLAY_JSON');
		}
		return $document;
	}

	/**
	 * POST /api/translations/delete/{sid}/{language}
	 */
	public function delete_post($sid = null, $language = null)
	{
		try {
			$sid = $this->get_sid($sid);
			$this->editor_acl->user_has_project_access($sid, 'edit', $this->user);

			if ($language === null || $language === '') {
				$options = $this->raw_json_input();
				if (is_array($options) && isset($options['language'])) {
					$language = $options['language'];
				}
			}
			if ($language === null || $language === '') {
				throw new Exception('LANGUAGE_REQUIRED');
			}

			$this->Project_translations_model->delete_translation($sid, $language);
			$this->audit_log->log_event('project', $sid, 'translation_delete', array(
				'language' => $language,
			), $this->user_id);

			$this->set_response(array(
				'status' => 'success',
			), REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->error_response($e);
		}
	}

	private function error_response(Exception $e)
	{
		$this->set_response(array(
			'status' => 'failed',
			'message' => $e->getMessage(),
		), REST_Controller::HTTP_BAD_REQUEST);
	}
}
