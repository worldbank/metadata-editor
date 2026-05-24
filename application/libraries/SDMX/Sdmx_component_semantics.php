<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Map SDMX DSD components to editor column_type; optional SDMX textType hints in metadata.
 *
 * Used by SdmxDsdImporter, Sdmx_structure_xml_import, and global data structure import.
 * and Data_structure_util::component_to_indicator_dsd_row (resolver shape).
 */
class Sdmx_component_semantics
{
	/** Concept / dimension id (uppercase) → column_type */
	const CONCEPT_COLUMN_TYPE_MAP = array(
		'TIME_PERIOD'      => 'time_period',
		'TIME'             => 'time_period',
		'YEAR'             => 'time_period',
		'REF_AREA'         => 'geography',
		'GEO'              => 'geography',
		'GEOGRAPHY'        => 'geography',
		'REF_GEOGRAPHY'    => 'geography',
		'COUNTRY'          => 'geography',
		'REGION'           => 'geography',
		'AREA'             => 'geography',
		'FREQ'             => 'periodicity',
		'FREQUENCY'        => 'periodicity',
		'PERIODICITY'      => 'periodicity',
		'TIME_FORMAT'      => 'attribute',
		'OBS_VALUE'        => 'observation_value',
		'OBSVALUE'         => 'observation_value',
		'VALUE'            => 'observation_value',
		'OBS'              => 'observation_value',
		'INDICATOR'        => 'indicator_id',
		'INDICATOR_ID'     => 'indicator_id',
		'SERIES'           => 'indicator_id',
		'SERIES_CODE'      => 'indicator_id',
		'SERIES_ID'        => 'indicator_id',
		'INDICATOR_CODE'   => 'indicator_id',
		'INDICATOR_NAME'   => 'indicator_name',
		'SERIES_NAME'      => 'indicator_name',
	);

	/** SDMX 3 concept role id → column_type */
	const ROLE_COLUMN_TYPE_MAP = array(
		'FREQ'         => 'periodicity',
		'TIME_PERIOD'  => 'time_period',
		'GEO'          => 'geography',
		'REF_AREA'     => 'geography',
		'MEASURE'      => 'measure',
		'PRIMARY'      => 'observation_value',
	);

	/** Codelist maintainable id (uppercase) → column_type when not already periodicity */
	const CODELIST_COLUMN_TYPE_MAP = array(
		'CL_FREQ'      => 'periodicity',
		'FREQ'         => 'periodicity',
	);

	/**
	 * Fallback: conventional SDMX dimension ids on the DSD (physical column / component name).
	 * Applied when concept/role/codelist did not resolve (matches .Stat / registry conventions).
	 */
	const DSD_STANDARD_COLUMN_ID_FALLBACK = array(
		'TIME_PERIOD' => 'time_period',
		'TIME'        => 'time_period',
		'YEAR'        => 'time_period',
		'FREQ'        => 'periodicity',
		'FREQUENCY'   => 'periodicity',
	);

	/** SDMX TextFormat textType → editor time_period_format code */
	const TEXT_TYPE_TIME_FORMAT_MAP = array(
		'GregorianYear'        => 'YYYY',
		'ReportingYear'        => 'YYYY',
		'GregorianYearMonth'   => 'YYYY-MM',
		'GregorianDay'         => 'YYYY-MM-DD',
		'Date'                 => 'YYYY-MM-DD',
		'DateTime'             => 'YYYY-MM-DDTHH:mm:ss',
	);

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/**
	 * @param array $component keys: element_type, concept_id, id, concept_roles (string[]),
	 *                         codelist_id (optional), text_type (optional)
	 * @return string column_type
	 */
	public function resolve_column_type(array $component)
	{
		$elementType = isset($component['element_type']) ? (string) $component['element_type'] : '';
		if ($elementType === 'TimeDimension') {
			return 'time_period';
		}
		if ($elementType === 'Measure') {
			return 'observation_value';
		}
		if ($elementType === 'Attribute') {
			return 'attribute';
		}

		$roles = isset($component['concept_roles']) && is_array($component['concept_roles'])
			? $component['concept_roles'] : array();
		foreach ($roles as $role) {
			$key = strtoupper(trim((string) $role));
			if ($key !== '' && isset(self::ROLE_COLUMN_TYPE_MAP[$key])) {
				return self::ROLE_COLUMN_TYPE_MAP[$key];
			}
		}

		$conceptId = strtoupper(trim(isset($component['concept_id']) ? (string) $component['concept_id'] : ''));
		$compId    = $this->_normalized_component_name($component);

		if ($conceptId !== '' && isset(self::CONCEPT_COLUMN_TYPE_MAP[$conceptId])) {
			return self::CONCEPT_COLUMN_TYPE_MAP[$conceptId];
		}

		$codelistKey = $this->_codelist_identity_key($component);
		if ($codelistKey !== null && isset(self::CODELIST_COLUMN_TYPE_MAP[$codelistKey])) {
			return self::CODELIST_COLUMN_TYPE_MAP[$codelistKey];
		}

		// Fallback: physical DSD column id (e.g. TIME_PERIOD, FREQ) when concept/role were absent or non-standard
		$fromId = $this->column_type_from_standard_dsd_column_id($compId);
		if ($fromId !== null) {
			return $fromId;
		}

		if ($compId !== '' && isset(self::CONCEPT_COLUMN_TYPE_MAP[$compId])) {
			return self::CONCEPT_COLUMN_TYPE_MAP[$compId];
		}

		return 'dimension';
	}

	/**
	 * Map conventional SDMX dimension id on the DSD to column_type (physical name only).
	 *
	 * @param string $columnId component id / physical column name
	 * @return string|null column_type or null if not a known standard id
	 */
	public function column_type_from_standard_dsd_column_id($columnId)
	{
		$key = strtoupper(trim((string) $columnId));
		if ($key === '') {
			return null;
		}

		return isset(self::DSD_STANDARD_COLUMN_ID_FALLBACK[$key])
			? self::DSD_STANDARD_COLUMN_ID_FALLBACK[$key]
			: null;
	}

	/**
	 * Re-classify components still marked as dimension when the DSD column name is TIME_PERIOD or FREQ.
	 *
	 * @param array[] $components rows with name and/or id, column_type
	 * @param string[] $warnings
	 */
	public function apply_standard_column_name_fallbacks(array &$components, array &$warnings)
	{
		foreach ($components as &$c) {
			$name = $this->_normalized_component_name($c);
			if ($name === '') {
				continue;
			}

			$fallback = $this->column_type_from_standard_dsd_column_id($name);
			if ($fallback === null) {
				continue;
			}

			$current = isset($c['column_type']) ? (string) $c['column_type'] : 'dimension';
			if ($current === $fallback) {
				continue;
			}

			$protected = array('time_period', 'periodicity', 'observation_value', 'measure');
			if (in_array($current, $protected, true) && $current !== 'dimension') {
				continue;
			}

			if ($current !== 'dimension' && $current !== '') {
				continue;
			}

			$label = isset($c['name']) ? $c['name'] : $name;
			$c['column_type'] = $fallback;
			$warnings[] = "Column '{$label}': applied column_type '{$fallback}' from standard DSD id '{$name}'.";
		}
		unset($c);
	}

	/**
	 * Physical component name: prefer name (registry component), else id (parsed SDMX).
	 *
	 * @param array $component
	 * @return string uppercase trimmed
	 */
	protected function _normalized_component_name(array $component)
	{
		$name = '';
		if (!empty($component['name'])) {
			$name = (string) $component['name'];
		} elseif (!empty($component['id'])) {
			$name = (string) $component['id'];
		}

		return strtoupper(trim($name));
	}

	/**
	 * @param string|null $textType SDMX TextFormat @textType
	 * @return string|null editor time_period_format code
	 */
	public function infer_time_period_format_from_text_type($textType)
	{
		if ($textType === null || trim((string) $textType) === '') {
			return null;
		}
		$tt = trim((string) $textType);
		if (isset(self::TEXT_TYPE_TIME_FORMAT_MAP[$tt])) {
			return self::TEXT_TYPE_TIME_FORMAT_MAP[$tt];
		}
		if (stripos($tt, 'Year') !== false && stripos($tt, 'Month') === false && stripos($tt, 'Day') === false) {
			return 'YYYY';
		}
		if (stripos($tt, 'Month') !== false) {
			return 'YYYY-MM';
		}
		if (stripos($tt, 'Day') !== false || stripos($tt, 'Date') !== false) {
			return 'YYYY-MM-DD';
		}
		if (stripos($tt, 'Period') !== false) {
			return 'YYYY';
		}

		return null;
	}

	/**
	 * Default constant FREQ for a time_period_format (from indicator_dsd config).
	 *
	 * @param string|null $time_period_format
	 * @return string|null
	 */
	public function default_freq_for_time_period_format($time_period_format)
	{
		if ($time_period_format === null || trim((string) $time_period_format) === '') {
			return null;
		}
		$this->CI->config->load('indicator_dsd', true);
		$map = $this->CI->config->item('dsd_default_freq_by_time_period_format', 'indicator_dsd');
		if (!is_array($map)) {
			return null;
		}
		$tf = trim((string) $time_period_format);

		return isset($map[$tf]) ? (string) $map[$tf] : null;
	}

	/**
	 * Apply standard column id fallbacks and persist SDMX textType on time_period metadata (no format/freq on DSD).
	 *
	 * @param array[] $components rows with column_type, text_type (optional), metadata (array)
	 * @param string[] $warnings list to append inference notes to
	 */
	public function enrich_time_period_metadata(array &$components, array &$warnings)
	{
		$this->apply_standard_column_name_fallbacks($components, $warnings);

		$warn = array();
		foreach ($components as &$c) {
			if (!isset($c['column_type']) || $c['column_type'] !== 'time_period') {
				continue;
			}
			$name = isset($c['name']) ? $c['name'] : '?';
			$textType = isset($c['text_type']) ? $c['text_type'] : null;
			if ($textType !== null && trim((string) $textType) !== '') {
				if (!isset($c['metadata']) || !is_array($c['metadata'])) {
					$c['metadata'] = array();
				}
				if (empty($c['metadata']['sdmx_text_type'])) {
					$c['metadata']['sdmx_text_type'] = trim((string) $textType);
					$warn[] = "Time period column '{$name}': stored SDMX textType in metadata.";
				}
			}
			unset($c['time_period_format']);
			if (isset($c['metadata']) && is_array($c['metadata'])) {
				unset($c['metadata']['freq'], $c['metadata']['import_freq_code']);
			}
		}
		unset($c);

		$warnings = array_merge($warnings, $warn);
	}

	/**
	 * @param array $component
	 * @return string|null uppercase codelist id e.g. CL_FREQ
	 */
	protected function _codelist_identity_key(array $component)
	{
		if (!empty($component['codelist_id']) && is_string($component['codelist_id'])) {
			return strtoupper(trim($component['codelist_id']));
		}
		if (!empty($component['codelist_ref']) && is_array($component['codelist_ref'])) {
			$id = isset($component['codelist_ref']['id']) ? trim((string) $component['codelist_ref']['id']) : '';
			if ($id !== '') {
				return strtoupper($id);
			}
		}

		return null;
	}

	/**
	 * Extract concept id from ConceptIdentity/Ref (SDMX 2.1 id or 3.0 URN tail).
	 *
	 * @param DOMElement $componentEl
	 * @return string|null
	 */
	public function extract_concept_id_from_dom(DOMElement $componentEl)
	{
		$xpath = new DOMXPath($componentEl->ownerDocument);
		$refs = $xpath->query('.//*[local-name()="ConceptIdentity"]/*[local-name()="Ref"]', $componentEl);
		if (!$refs || $refs->length === 0) {
			return null;
		}
		$ref = $refs->item(0);
		if (!($ref instanceof DOMElement)) {
			return null;
		}
		$id = trim($ref->getAttribute('id'));
		if ($id !== '') {
			return $id;
		}
		$urn = trim($ref->getAttribute('urn'));
		if ($urn !== '' && strpos($urn, '.') !== false) {
			$parts = explode('.', $urn);

			return end($parts);
		}

		return null;
	}

	/**
	 * @param DOMElement $componentEl
	 * @return string[] role concept ids (uppercase)
	 */
	public function extract_concept_roles_from_dom(DOMElement $componentEl)
	{
		$roles = array();
		$xpath = new DOMXPath($componentEl->ownerDocument);
		$refs = $xpath->query('.//*[local-name()="ConceptRole"]/*[local-name()="Ref"]', $componentEl);
		if (!$refs) {
			return $roles;
		}
		for ($i = 0; $i < $refs->length; $i++) {
			$ref = $refs->item($i);
			if (!($ref instanceof DOMElement)) {
				continue;
			}
			$id = trim($ref->getAttribute('id'));
			if ($id === '') {
				$urn = trim($ref->getAttribute('urn'));
				if ($urn !== '' && strpos($urn, '.') !== false) {
					$parts = explode('.', $urn);
					$id = end($parts);
				}
			}
			if ($id !== '') {
				$roles[] = strtoupper($id);
			}
		}

		return $roles;
	}

	/**
	 * @param DOMElement $componentEl
	 * @return string|null textType attribute
	 */
	public function extract_text_type_from_dom(DOMElement $componentEl)
	{
		$xpath = new DOMXPath($componentEl->ownerDocument);
		foreach (array('LocalRepresentation', 'CoreRepresentation') as $reprName) {
			$reprList = $xpath->query('.//*[local-name()="' . $reprName . '"]', $componentEl);
			if (!$reprList || $reprList->length === 0) {
				continue;
			}
			$repr = $reprList->item(0);
			if (!($repr instanceof DOMElement)) {
				continue;
			}
			$tfList = $xpath->query('.//*[local-name()="TextFormat"]', $repr);
			if (!$tfList || $tfList->length === 0) {
				continue;
			}
			$tf = $tfList->item(0);
			if ($tf instanceof DOMElement) {
				$textType = trim($tf->getAttribute('textType'));
				if ($textType === '') {
					$textType = trim($tf->getAttribute('TextType'));
				}
				if ($textType !== '') {
					return $textType;
				}
			}
		}

		return null;
	}
}
