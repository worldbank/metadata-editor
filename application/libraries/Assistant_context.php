<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Builds the context bundle the server assistant (FastAPI /assistant/chat/stream) is grounded in.
 *
 * The assistant service never calls back into the editor and holds no credentials, so everything it
 * may read about a project is assembled here, after the caller's session and project ACL have been
 * checked (controllers/api/Assistant.php). Assembled once per turn, from the same models the editor's
 * own API uses:
 *
 *   record   - GET api/editor/json/{id}?exclude_private_fields=1 (variables excluded; the data file
 *              context below carries them for the open file)
 *   fields    - every field of the project's own template: label, section, help, required or
 *               recommended, options of a controlled list, whether a single value can fill it, and
 *               the saved value (same document as api/editor/json, so private fields are absent)
 *   datafiles - the project's data files (name, cases, variables, description)
 *   datafile  - the open data file and its variables, up to 500
 *
 * The assistant's tools can read and propose only the pointers sent here. Without a readable
 * template, the fixed list in config (editor.server_assistant_fields) is used instead.
 */
class Assistant_context
{
	const RECORD_MAX_BYTES = 262144;
	const MAX_VARIABLES = 500;
	// A labelling job reads the whole file in compact form; the service accepts up to 5,000.
	const MAX_LABEL_VARIABLES = 3000;
	const VARIABLE_PAGE = 100;
	// Matches the bundle's own cap (EditorBundle.fields max_length) so a large custom template is not
	// silently truncated below what the service can accept.
	const MAX_FIELDS = 400;
	const MAX_VALUE_CHARS = 4000;
	const MAX_HELP_CHARS = 300;
	const MAX_OPTIONS = 30;
	const MAX_ROW_COLUMNS = 8;
	const ROW_CELL_TYPES = array('string', 'text', 'textarea', 'integer', 'date');
	// Template field types a single text value can fill; the rest are listed read-only.
	const FILLABLE_TYPES = array('string', 'text', 'textarea', 'integer', 'date');
	const LISTED_TYPES = array('string', 'text', 'textarea', 'integer', 'boolean', 'array', 'nested_array');
	// A template section marked is_custom is rendered by its own component (survey: the data-file,
	// variables and variable-groups editors; indicator: the data structure definition; geospatial: the
	// feature catalogue), never by the record form's form-inputs. Such sections are skipped wherever
	// they sit. NON_RECORD_SECTIONS stays as a fallback for older templates that predate the flag.
	const NON_RECORD_SECTIONS = array('data_file', 'variable', 'variable_groups');

	private $ci;
	// Per request (CodeIgniter builds this library once per request): one turn reads the project row
	// and its template from several places.
	private $rows = array();
	private $templates = array();

	function __construct()
	{
		$this->ci =& get_instance();
		$this->ci->load->model('Editor_model');
		$this->ci->load->model('Editor_datafile_model');
		$this->ci->load->model('Editor_variable_model');
		$this->ci->load->model('Editor_template_model');
		$this->ci->load->library('Project_json_writer');
		$this->ci->load->helper('array');
		$this->ci->load->config('editor');
	}

	private function project_row($sid)
	{
		if (!array_key_exists($sid, $this->rows)) {
			$this->rows[$sid] = $this->ci->Editor_model->get_row($sid);
		}
		return $this->rows[$sid];
	}

	/**
	 * @param int    $sid        project id (ACL already checked by the caller)
	 * @param string $file_id    open data file id, or ''
	 * @param string $section    the editor's own description of the current view
	 * @param bool   $can_fill   whether proposals may be offered (the user can edit this project)
	 * @param int    $user_id
	 */
	function bundle($sid, $file_id, $section, $can_fill, $user_id)
	{
		$bundle = array(
			'record_id' => (string)$sid,
			'section' => $section !== '' ? mb_substr($section, 0, 200) : null,
			'file_id' => $file_id !== '' ? $file_id : null,
			'fill' => (bool)$can_fill,
			'fields' => array(),
			'record' => null,
			'datafiles' => array(),
			'datafile' => null,
		);

		$doc = $this->project_document($sid, $user_id);
		if ($doc !== null) {
			$encoded = json_encode($doc);
			// The service caps what it shows the model; this only bounds the request itself.
			if ($encoded !== false && strlen($encoded) <= self::RECORD_MAX_BYTES) {
				$bundle['record'] = $doc;
			}
			$bundle['fields'] = $this->fields($sid, $doc);
		}

		$bundle['datafiles'] = $this->datafiles($sid);
		if ($file_id !== '') {
			$bundle['datafile'] = $this->datafile($sid, $file_id);
		}
		return $bundle;
	}

	/** Project title and type for the panel's context line; null when the project cannot be read. */
	function summary($sid)
	{
		$row = $this->project_row($sid);
		if (!$row) {
			return null;
		}
		return array(
			'sid' => (string)$sid,
			'title' => mb_substr(trim((string)($row['title'] ?? '')), 0, 300),
			'type' => (string)($row['type'] ?? ''),
			'idno' => (string)($row['idno'] ?? ''),
		);
	}

	/**
	 * Pointer => {label, section, fillable, required, recommended} for every field of the project's
	 * template (no values): the panel names fields in its steps and lets the curator pick one to draft.
	 */
	function field_labels($sid)
	{
		$items = $this->template_items($sid);
		$fields = array();
		if ($items !== null) {
			$this->collect_fields($items, array(), '', $fields);
		}
		if (!$fields) {
			$out = array();
			foreach ((array)$this->ci->config->item('server_assistant_fields', 'editor') as $pointer => $label) {
				$out[$pointer] = array('label' => (string)$label, 'section' => '', 'fillable' => true);
			}
			return $out;
		}
		$out = array();
		foreach ($fields as $pointer => $f) {
			$out[$pointer] = array(
				'label' => $f['label'],
				'section' => $f['section'],
				'fillable' => $f['fillable'],
				'required' => $f['required'],
				'recommended' => $f['recommended'],
				'rows' => !empty($f['columns']),
			);
		}
		return $out;
	}

	/**
	 * The record as api/editor/json shapes it (private fields removed, empty values dropped), built in
	 * memory. generate_project_json would write the project's shared export file and read it back:
	 * two turns on one project, or a turn during a download, could read each other's half-written file.
	 */
	private function project_document($sid, $user_id)
	{
		try {
			$project = $this->project_row($sid);
			if (!$project) {
				return null;
			}
			$metadata = (array)$project['metadata'];
			foreach (array('created', 'changed', 'created_by', 'changed_by') as $key) {
				unset($metadata[$key]);
			}
			if (in_array($project['type'], array('indicator', 'timeseries'), true)) {
				unset($metadata['indicator_id_value']);
			}
			$this->ci->project_json_writer->json_remove_private_fields($sid, $metadata);
			array_remove_empty($metadata);
			return array_merge(array('type' => $project['type'], 'idno' => $project['idno']), $metadata);
		} catch (Exception $e) {
			log_message('error', 'Assistant_context::project_document ' . $e->getMessage());
			return null;
		}
	}

	private function fields($sid, $doc)
	{
		$items = $this->template_items($sid);
		if ($items !== null) {
			$out = array();
			$this->collect_fields($items, $doc, '', $out);
			if ($out) {
				return $out;
			}
		}
		return $this->configured_fields($doc);
	}

	/** Depth-first over the template: one entry per keyed field, labelled with its section. */
	private function collect_fields($items, $doc, $section, &$out)
	{
		foreach ((array)$items as $item) {
			if (!is_array($item) || count($out) >= self::MAX_FIELDS) {
				continue;
			}
			$type = (string)($item['type'] ?? '');
			$title = trim((string)($item['title'] ?? ''));
			$key = trim((string)($item['key'] ?? ''));
			// A bespoke component owns this section, whatever the template names it.
			if (!empty($item['is_custom'])) {
				continue;
			}
			// Checked before the section recursion: a section's own key is the first thing to look at,
			// and a leaf's key carries it as its first segment.
			$head = explode('.', $key)[0];
			if (in_array($head, self::NON_RECORD_SECTIONS, true)) {
				continue;
			}
			if (!empty($item['items']) && in_array($type, array('section', 'section_container'), true)) {
				$this->collect_fields($item['items'], $doc, $title !== '' ? $title : $section, $out);
				continue;
			}
			if ($key === '' || !in_array($type, self::LISTED_TYPES, true)) {
				continue;
			}
			$pointer = '/' . str_replace('.', '/', str_replace('~', '~0', str_replace('/', '~1', $key)));
			list($value, $found) = $this->value_at($doc, $pointer);
			$options = array();
			if (!empty($item['enum']) && is_array($item['enum'])) {
				foreach (array_slice($item['enum'], 0, self::MAX_OPTIONS) as $opt) {
					$options[] = is_array($opt) ? (string)($opt['label'] ?? $opt['code'] ?? '') : (string)$opt;
				}
			}
			$help = trim((string)($item['help_text'] ?? ''));
			$suggestions = $this->suggestions_for($pointer, $doc);
			$entry = array(
				'label' => $title !== '' ? $title : $key,
				'section' => $section,
				'type' => $type,
				'value' => $this->bounded($value),
				'found' => $found,
				'help' => $help !== '' ? mb_substr($help, 0, self::MAX_HELP_CHARS) : null,
				'required' => !empty($item['required']) || !empty($item['is_required']),
				'recommended' => !empty($item['recommended']) || !empty($item['is_recommended']),
				'fillable' => in_array($type, self::FILLABLE_TYPES, true),
				'in_template' => true,
			);
			if ($options) {
				$entry['options'] = array_values(array_filter($options, 'strlen'));
			}
			$columns = $this->row_columns($item);
			if ($columns) {
				$entry['columns'] = $columns;
			}
			if ($suggestions) {
				$entry['suggestions'] = $suggestions;
			}
			$out[$pointer] = $entry;
		}
	}

	/**
	 * The columns a list field's rows may fill: plain text and number cells only. A column with a
	 * controlled list is left out, because what the table stores for an option is the editor's own rule
	 * and a row written without it would not match; those cells stay for the curator.
	 */
	private function row_columns($item)
	{
		if ((string)($item['type'] ?? '') !== 'array' || empty($item['props']) || !is_array($item['props'])) {
			return array();
		}
		$out = array();
		foreach ($item['props'] as $prop) {
			if (!is_array($prop) || count($out) >= self::MAX_ROW_COLUMNS) {
				continue;
			}
			$key = trim((string)($prop['key'] ?? ''));
			if ($key === '' || !in_array((string)($prop['type'] ?? ''), self::ROW_CELL_TYPES, true) || !empty($prop['enum'])) {
				continue;
			}
			$title = trim((string)($prop['title'] ?? ''));
			$out[] = array('key' => $key, 'title' => $title !== '' ? $title : $key);
		}
		return $out;
	}

	private function configured_fields($doc)
	{
		$declared = $this->ci->config->item('server_assistant_fields', 'editor');
		$out = array();
		foreach ((array)$declared as $pointer => $label) {
			list($value, $found) = $this->value_at($doc, $pointer);
			// Unknown template: do not claim the field is absent (in_template null).
			$out[$pointer] = array('label' => (string)$label, 'section' => '', 'type' => 'string', 'value' => $this->bounded($value),
				'found' => $found, 'help' => null, 'required' => false, 'recommended' => false, 'fillable' => true, 'in_template' => null);
		}
		return $out;
	}

	private function value_at($doc, $pointer)
	{
		try {
			return array(\Swaggest\JsonDiff\JsonPointer::getByPointer($doc, $pointer), true);
		} catch (Exception $e) {
			return array(null, false);
		}
	}

	// Long text and large lists are cut so one field cannot crowd out the rest of the bundle.
	private function bounded($value)
	{
		if (is_string($value)) {
			return mb_strlen($value) > self::MAX_VALUE_CHARS ? mb_substr($value, 0, self::MAX_VALUE_CHARS) . '…' : $value;
		}
		if (is_array($value)) {
			$json = json_encode($value, JSON_UNESCAPED_UNICODE);
			if ($json !== false && strlen($json) > self::MAX_VALUE_CHARS) {
				return mb_substr($json, 0, self::MAX_VALUE_CHARS) . '…';
			}
		}
		return $value;
	}

	/**
	 * Values a field should be filled from. Configured per pointer, plus one derived suggestion: an
	 * indicator's measurement unit copied from the unit in its own name, so the model reuses the
	 * wording the record already uses instead of inventing a variant.
	 */
	private function suggestions_for($pointer, $doc)
	{
		$configured = $this->ci->config->item('server_assistant_field_suggestions', 'editor');
		$out = array();
		if (is_array($configured) && isset($configured[$pointer]) && is_array($configured[$pointer])) {
			foreach ($configured[$pointer] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$out[] = $value;
				}
			}
		}
		if ($pointer === '/series_description/measurement_unit') {
			list($name) = $this->value_at($doc, '/series_description/name');
			if (is_string($name) && preg_match('/\(([^()]+)\)\s*$/u', $name, $m)) {
				$out[] = trim($m[1]);
			}
		}
		return array_values(array_unique($out));
	}

	private function datafiles($sid)
	{
		try {
			$rows = $this->ci->Editor_datafile_model->select_all($sid);
		} catch (Exception $e) {
			return array();
		}
		$out = array();
		foreach (array_slice((array)$rows, 0, 100) as $row) {
			if (!is_array($row)) {
				continue;
			}
			$out[] = array(
				'file_id' => (string)($row['file_id'] ?? ''),
				'file_name' => mb_substr((string)($row['file_name'] ?? ''), 0, 200),
				'case_count' => isset($row['case_count']) ? (int)$row['case_count'] : null,
				'var_count' => isset($row['var_count']) ? (int)$row['var_count'] : null,
				'description' => mb_substr(trim((string)($row['description'] ?? '')), 0, 300),
			);
		}
		return $out;
	}

	private function template_items($sid)
	{
		if (array_key_exists($sid, $this->templates)) {
			return $this->templates[$sid];
		}
		try {
			$project = $this->project_row($sid);
			$uid = trim((string)($project['template_uid'] ?? ''));
			$template = $uid !== '' ? $this->ci->Editor_template_model->get_template_by_uid($uid) : null;
			$items = (!$template || empty($template['template']['items'])) ? null : $template['template']['items'];
		} catch (Exception $e) {
			$items = null;
		}
		return $this->templates[$sid] = $items;
	}

	/**
	 * The data file's variables in the shape a labelling job reads: uid (what apply writes by), name and
	 * the three attributes the assistant may propose. Far lighter than datafile(): no categories or
	 * summary statistics, so thousands of variables fit one request. Null when the file is not this project's.
	 */
	function label_targets($sid, $file_id)
	{
		if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $file_id)) {
			return null;
		}
		$file = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);
		if (!$file) {
			return null;
		}
		$variables = array();
		for ($offset = 0; $offset < self::MAX_LABEL_VARIABLES; $offset += self::VARIABLE_PAGE * 5) {
			$this->ci->db->select('uid,name,labl,metadata');
			$this->ci->db->where('sid', $sid);
			$this->ci->db->where('fid', $file_id);
			$this->ci->db->order_by('sort_order,uid', 'asc');
			$this->ci->db->limit(self::VARIABLE_PAGE * 5, $offset);
			$rows = $this->ci->db->get('editor_variables')->result_array();
			if (!$rows) {
				break;
			}
			foreach ($rows as $row) {
				$meta = !empty($row['metadata']) ? (array)$this->ci->Editor_model->decode_metadata($row['metadata']) : array();
				$variables[] = array(
					'uid' => (int)$row['uid'],
					'name' => (string)$row['name'],
					'label' => (string)($row['labl'] ?? ''),
					'description' => $this->plain($meta['var_txt'] ?? ''),
					'question' => $this->plain($meta['var_qstn_qstnlit'] ?? ''),
				);
			}
		}
		return array('file_id' => $file_id, 'file_name' => (string)($file['file_name'] ?? ''), 'variables' => $variables);
	}

	private function plain($value)
	{
		return is_scalar($value) ? trim((string)$value) : '';
	}

	/**
	 * Write label, description or question text into variables the caller may edit, one variable at a
	 * time, merged into the variable's own record (a save replaces the whole record, so a partial write
	 * would drop everything else). A change carries `expect`, the value the curator saw; when the
	 * variable has changed since, it is skipped, not overwritten. Returns what was written and the values
	 * it replaced, so the panel can undo.
	 *
	 * @param array $changes [{uid, label?, description?, question?, expect?: {label?, description?, question?}}]
	 */
	function apply_variable_text($sid, $file_id, array $changes)
	{
		$columns = array('label' => 'labl', 'description' => 'var_txt', 'question' => 'var_qstn_qstnlit');
		$limits = array('label' => 255, 'description' => 4000, 'question' => 2000);
		$applied = array();
		$undo = array();
		$skipped = array();
		foreach ($changes as $change) {
			$uid = isset($change['uid']) && is_numeric($change['uid']) ? (int)$change['uid'] : 0;
			$row = $uid > 0 ? $this->ci->Editor_variable_model->variable($sid, $uid, true) : null;
			if (!$row || (string)$row['fid'] !== (string)$file_id) {
				$skipped[] = array('uid' => $uid, 'reason' => 'not a variable of this data file');
				continue;
			}
			$meta = is_array($row['metadata'] ?? null) ? $row['metadata'] : array();
			$before = array();
			$after = array();
			$conflict = null;
			foreach ($columns as $attr => $key) {
				if (!array_key_exists($attr, $change)) {
					continue;
				}
				$value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$change[$attr]));
				if (mb_strlen($value) > $limits[$attr]) {
					$conflict = 'the ' . $attr . ' is longer than ' . $limits[$attr] . ' characters';
					break;
				}
				$current = $attr === 'label' ? trim((string)($row['labl'] ?? '')) : $this->plain($meta[$key] ?? '');
				if (isset($change['expect']) && is_array($change['expect']) && array_key_exists($attr, $change['expect'])
					&& trim((string)$change['expect'][$attr]) !== $current) {
					$conflict = 'the ' . $attr . ' was changed since the suggestion was made';
					break;
				}
				$before[$attr] = $current;
				$after[$attr] = $value;
				if ($value === '') {
					unset($meta[$key]);
				} else {
					$meta[$key] = $value;
				}
			}
			if ($conflict !== null) {
				$skipped[] = array('uid' => $uid, 'name' => (string)$row['name'], 'reason' => $conflict);
				continue;
			}
			if (!$after) {
				continue;
			}
			// The save reads these from the record itself.
			$meta['fid'] = $row['fid'];
			$meta['vid'] = $row['vid'];
			$meta['name'] = $row['name'];
			$meta['labl'] = array_key_exists('label', $after) ? $after['label'] : (string)($row['labl'] ?? '');
			$this->ci->Editor_variable_model->update($sid, $uid, array('metadata' => $meta));
			$applied[] = $uid;
			$undo[] = array_merge(array('uid' => $uid), $before);
		}
		return array('applied' => $applied, 'undo' => $undo, 'skipped' => $skipped);
	}

	/** The open data file and its variables in a compact shape, up to MAX_VARIABLES. */
	private function datafile($sid, $file_id)
	{
		if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $file_id)) {
			return null;
		}
		$file = $this->ci->Editor_datafile_model->data_file_by_id($sid, $file_id);
		if (!$file) {
			return null;
		}
		$short = function ($value, $max) {
			if (!is_scalar($value)) return '';
			$text = trim((string)$value);
			return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '…' : $text;
		};
		$total = (int)$this->ci->Editor_variable_model->count_all($sid, $file_id);
		$variables = array();
		for ($offset = 0; $offset < min($total, self::MAX_VARIABLES); $offset += self::VARIABLE_PAGE) {
			$rows = $this->ci->Editor_variable_model->select_all($sid, $file_id, true, $offset, self::VARIABLE_PAGE);
			if (!$rows) {
				break;
			}
			foreach ($rows as $row) {
				$variable = array(
					'name' => $short($row['name'] ?? '', 100),
					'label' => $short($row['labl'] ?? '', 240),
					'description' => $short($row['var_txt'] ?? '', 360),
					'question' => $short($row['var_qstn_qstnlit'] ?? '', 240),
					'universe' => $short($row['var_universe'] ?? '', 160),
				);
				$concepts = array();
				foreach (array_slice((array)($row['var_concept'] ?? array()), 0, 3) as $concept) {
					$title = is_array($concept) ? ($concept['title'] ?? $concept['vocab'] ?? '') : $concept;
					$title = $short($title, 100);
					if ($title !== '') $concepts[] = $title;
				}
				if ($concepts) $variable['concepts'] = $concepts;
				$variables[] = $variable;
			}
		}
		return array(
			'file_id' => $file_id,
			'file_name' => $short($file['file_name'] ?? '', 200),
			'description' => $short($file['description'] ?? '', 1000),
			'producer' => $short($file['producer'] ?? '', 200),
			'source_format' => $short($file['source_format'] ?? '', 40),
			'case_count' => isset($file['case_count']) ? (int)$file['case_count'] : null,
			'var_count' => $total,
			'variables' => $variables,
			'variables_included' => count($variables),
		);
	}
}
