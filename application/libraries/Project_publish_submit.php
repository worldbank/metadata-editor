<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Submit-for-publishing validation and atomic placement updates.
 */
class Project_publish_submit
{
	/** @var CI_Controller */
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Catalog_connections_model');
		$this->ci->load->model('Project_publications_model');
		$this->ci->load->model('Metadata_schemas_model');
		$this->ci->load->model('Editor_template_model');
		$this->ci->load->helper('catalog');
		$this->ci->load->helper('catalog_publish_options');
		$this->ci->load->helper('publish_intake');
		$this->ci->load->library('Project_validation');
	}

	/**
	 * @param int $sid
	 * @param array|null $project
	 * @return array
	 */
	public function validate_study($sid, $project = null)
	{
		if (!is_array($project)) {
			$project = $this->ci->Editor_model->get_row($sid);
		}
		if (!$project) {
			throw new Exception('Project not found');
		}

		$blocking = array();
		$warnings = array();

		$schema_result = $this->validate_schema($project);
		if (!$schema_result['valid']) {
			$blocking[] = array(
				'type' => 'schema',
				'message' => 'Schema validation failed',
				'issues' => $schema_result['issues'],
			);
		}

		$template_result = $this->validate_template($project);
		if (!empty($template_result['error'])) {
			$blocking[] = array(
				'type' => 'template',
				'message' => $template_result['error'],
				'issues' => array(),
			);
		} elseif (empty($template_result['valid'])) {
			$blocking[] = array(
				'type' => 'template',
				'message' => 'Template validation failed',
				'issues' => isset($template_result['issues']) ? $template_result['issues'] : array(),
			);
		}

		$variables_result = $this->validate_variables($sid, $project);
		if ($variables_result !== null && empty($variables_result['valid'])) {
			$blocking[] = array(
				'type' => 'variables',
				'message' => 'Variables validation failed',
				'issues' => isset($variables_result['issues']) ? $variables_result['issues'] : array(),
			);
		}

		return array(
			'valid' => empty($blocking),
			'blocking' => $blocking,
			'warnings' => $warnings,
			'schema' => $schema_result,
			'template' => $template_result,
			'variables' => $variables_result,
		);
	}

	/**
	 * @param int $sid
	 * @param int $user_id
	 * @param array $payload
	 * @return array
	 */
	public function submit($sid, $user_id, $payload)
	{
		if (!is_array($payload)) {
			throw new Exception('Request body must be an object');
		}

		$project = $this->ci->Editor_model->get_row($sid);
		if (!$project) {
			throw new Exception('Project not found');
		}

		$requests_input = isset($payload['requests']) ? $payload['requests'] : null;
		if (!is_array($requests_input) || count($requests_input) < 1) {
			throw new Exception('At least one catalog is required');
		}

		$study_validation = $this->validate_study($sid, $project);
		if (!$study_validation['valid']) {
			throw new Publish_submit_validation_exception('Study validation failed', array(
				'study_validation' => $study_validation,
			));
		}

		$intake = publish_intake_validate(
			isset($payload['intake']) ? $payload['intake'] : array(),
			array('strict' => true, 'context' => 'submit')
		);

		$request_note = isset($payload['request_note']) ? trim((string) $payload['request_note']) : '';
		$official_by_id = $this->official_catalog_map();
		$prepared = array();
		$seen_catalog_ids = array();

		foreach ($requests_input as $index => $request_input) {
			if (!is_array($request_input)) {
				throw new Exception('Each request must be an object');
			}
			$catalog_id = isset($request_input['catalog_id']) ? (int) $request_input['catalog_id'] : 0;
			if ($catalog_id < 1) {
				throw new Exception('Each request requires catalog_id');
			}
			if (isset($seen_catalog_ids[$catalog_id])) {
				throw new Exception('Duplicate catalog in submit request');
			}
			$seen_catalog_ids[$catalog_id] = true;

			if (empty($official_by_id[$catalog_id])) {
				throw new Exception('Catalog is not available for submit: ' . $catalog_id);
			}

			$catalog = $official_by_id[$catalog_id];
			$catalog_type = isset($catalog['type']) ? $catalog['type'] : 'nada';
			$options = isset($request_input['options']) ? $request_input['options'] : array();
			if (catalog_type_is_nada($catalog_type)) {
				$options = catalog_publish_options_validate($catalog_type, $options, array(
					'strict' => true,
					'context' => 'submit',
				));
			} else {
				$options = catalog_publish_options_validate($catalog_type, is_array($options) ? $options : array());
			}

			$existing = $this->ci->Project_publications_model->get_by_project_catalog($sid, $catalog_id);
			$prepared[] = array(
				'catalog' => $catalog,
				'catalog_id' => $catalog_id,
				'catalog_type' => $catalog_type,
				'options' => $options,
				'request' => $this->ci->Project_publications_model->resolve_request_type($existing),
				'existing' => $existing,
			);
		}

		$now = time();
		$this->ci->db->trans_start();
		foreach ($prepared as $item) {
			$this->ci->Project_publications_model->upsert(array(
				'sid' => $sid,
				'catalog_id' => $item['catalog_id'],
				'catalog_type' => $item['catalog_type'],
				'intake' => $intake,
				'intake_params' => array('strict' => true, 'context' => 'submit'),
				'options' => $item['options'],
				'request' => $item['request'],
				'request_note' => $request_note !== '' ? $request_note : null,
				'return_reason' => null,
				'requested_by' => $user_id,
				'requested_at' => $now,
				'updated_by' => $user_id,
				'event' => 'submitted',
			));
		}
		$this->ci->db->trans_complete();

		if ($this->ci->db->trans_status() === false) {
			throw new Exception('Failed to submit publish request');
		}

		$publications = $this->ci->Project_publications_model->list_by_project($sid);
		return array(
			'publications' => $publications,
			'summary' => $this->ci->Project_publications_model->derive_summary($publications),
			'study_validation' => $study_validation,
			'intake' => $intake,
		);
	}

	/**
	 * @param int $sid
	 * @return array
	 */
	public function submit_context($sid)
	{
		$official_catalogs = $this->ci->Catalog_connections_model->get_official_catalogs();
		$publications = $this->ci->Project_publications_model->list_by_project($sid);
		$publications_by_catalog = array();
		foreach ($publications as $placement) {
			$publications_by_catalog[(int) $placement['catalog_id']] = $placement;
		}

		$prefill_intake = array();
		foreach ($publications as $placement) {
			if (!empty($placement['intake']) && is_array($placement['intake'])) {
				$prefill_intake = $placement['intake'];
				break;
			}
		}

		$catalogs = array();
		foreach ($official_catalogs as $catalog) {
			$catalog_id = (int) $catalog['id'];
			$type = isset($catalog['type']) ? $catalog['type'] : 'nada';
			$entry = array(
				'catalog' => $catalog,
				'publish_form' => catalog_publish_form_load($type),
				'existing' => isset($publications_by_catalog[$catalog_id]) ? $publications_by_catalog[$catalog_id] : null,
			);
			$catalogs[] = $entry;
		}

		return array(
			'official_catalogs' => $catalogs,
			'intake_form' => publish_intake_form_load(),
			'prefill_intake' => $prefill_intake,
			'publications' => $publications,
			'summary' => $this->ci->Project_publications_model->derive_summary($publications),
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

	/**
	 * @param array $project
	 * @return array
	 */
	private function validate_schema($project)
	{
		$type = isset($project['type']) ? $project['type'] : '';
		$metadata = isset($project['metadata']) ? $project['metadata'] : array();
		$result = array(
			'valid' => false,
			'issues' => array(),
		);

		try {
			$schema_file = $this->ci->Metadata_schemas_model->get_schema_file_path($type);
		} catch (Exception $e) {
			$result['issues'][] = array(
				'type' => 'schema_not_found',
				'message' => $e->getMessage(),
			);
			return $result;
		}

		$compiled_schema = null;
		try {
			$schema = $this->ci->Metadata_schemas_model->get_by_uid($type);
			if ($schema) {
				$schema_dir = $this->ci->Metadata_schemas_model->resolve_schema_path($schema);
				if (is_dir($schema_dir)) {
					$this->ci->load->library('Schema_registry');
					$actual_filename = basename($schema_file);
					$schema_for_loading = $schema;
					$schema_for_loading['filename'] = $actual_filename;
					$documents = $this->ci->schema_registry->load_schema_documents($schema_for_loading, $schema_dir);
					$compiled_schema = $this->ci->schema_registry->inline_schema($actual_filename, $documents, $schema_dir);
				}
			}
		} catch (Exception $e) {
			// compiled schema is optional
		}

		return $this->ci->project_validation->validate_schema($metadata, $type, $schema_file, $compiled_schema);
	}

	/**
	 * @param array $project
	 * @return array
	 */
	private function validate_template($project)
	{
		$result = array(
			'valid' => null,
			'issues' => array(),
			'error' => null,
		);

		try {
			$template = $this->ci->Editor_template_model->resolve_template_for_project($project);
		} catch (Exception $e) {
			$result['error'] = $e->getMessage();
			return $result;
		}

		if (!$template) {
			$result['error'] = 'Project does not have a template assigned';
			return $result;
		}

		$metadata = isset($project['metadata']) ? $project['metadata'] : array();
		$validation = $this->ci->project_validation->validate_template($metadata, $template);
		$result['valid'] = !empty($validation['valid']);
		$result['issues'] = isset($validation['issues']) ? $validation['issues'] : array();
		$result['template_uid'] = isset($template['uid']) ? $template['uid'] : null;
		return $result;
	}

	/**
	 * @param int $sid
	 * @param array $project
	 * @return array|null
	 */
	private function validate_variables($sid, $project)
	{
		$type = isset($project['type']) ? $project['type'] : '';
		if ($type !== 'microdata' && $type !== 'survey') {
			return null;
		}

		$limit = 50;
		$this->ci->db->select('uid, sid, fid, vid, name, labl');
		$this->ci->db->from('editor_variables');
		$this->ci->db->where('sid', (int) $sid);
		$this->ci->db->where('(labl IS NULL OR TRIM(COALESCE(labl,\'\')) = \'\')', null, false);
		$this->ci->db->limit($limit);
		$this->ci->db->order_by('uid', 'asc');
		$rows = $this->ci->db->get()->result_array();

		$issues = array();
		foreach ($rows as $row) {
			$variable_fid = isset($row['fid']) ? $row['fid'] : 'unknown';
			$variable_name = isset($row['name']) ? $row['name'] : 'unknown';
			$issues[] = array(
				'type' => 'variable_validation_error',
				'property' => 'labl',
				'path' => 'variables/' . $variable_fid,
				'message' => 'The property labl is required',
				'variable_fid' => $variable_fid,
				'variable_name' => $variable_name,
			);
		}

		return array(
			'valid' => empty($issues),
			'issues' => $issues,
		);
	}
}

/**
 * Submit blocked by study validation (schema/template/variables).
 */
class Publish_submit_validation_exception extends Exception
{
	/** @var array */
	private $details;

	public function __construct($message, array $details = array())
	{
		parent::__construct($message);
		$this->details = $details;
	}

	/**
	 * @return array
	 */
	public function getDetails()
	{
		return $this->details;
	}
}
