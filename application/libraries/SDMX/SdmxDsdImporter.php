<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Import a DataStructureDefinition (DSD) from an SDMX-ML structure message (SDMX 2.1 and 3.0).
 *
 * Accepts either a pure DSD message or a Dataflow message. When a Dataflow is detected and
 * no inline DataStructure is present, {@link parseUrl} automatically follows through to fetch
 * the referenced DSD URL. {@link parseFile} and {@link parseString} return an actionable error
 * in that case (callers should use {@link parseUrl} when starting from a Dataflow URL).
 *
 * Also parses any inline {@code Codelist} artefacts bundled in the same message (e.g. fetched
 * with {@code ?references=codelists}), resolving them to local codelist codes per component.
 *
 * Usage (CodeIgniter):
 *   $this->load->library('SDMX/SdmxDsdImporter');
 *   $result = $this->sdmxdsdimporter->parseFile($path);
 *   $result = $this->sdmxdsdimporter->parseString($xml);
 *   $result = $this->sdmxdsdimporter->parseUrl($url);  // works for both DSD and Dataflow URLs
 *
 * Return shape (success):
 *   [
 *     'status'          => 'success',
 *     'sdmx_version'    => '2.1'|'3.0',
 *     'input_type'      => 'dsd'|'dataflow',   // what the original URL/file contained
 *     'dsd' => [
 *       'id'       => string,
 *       'agency'   => string,
 *       'version'  => string,
 *       'name'     => string,           // preferred English label
 *       'names'    => [ 'en' => '...' ],
 *       'components' => [
 *         [
 *           'id'                => string,
 *           'element_type'      => 'Dimension'|'TimeDimension'|'Measure'|'Attribute',
 *           'position'          => int|null,
 *           'concept_id'        => string|null,
 *           'data_type'         => string|null,   // 'string'|'integer'|'float'|'double'|'date'|'boolean'
 *           'codelist_agency'   => string|null,
 *           'codelist_id'       => string|null,
 *           'codelist_version'  => string|null,
 *           'codes'             => [ ['code'=>string,'label'=>string,'sort_order'=>int], ... ],
 *           'names'             => [ 'en' => '...' ],
 *         ],
 *         ...
 *       ],
 *     ],
 *     'message'  => string (on error),
 *     'warnings' => string[],
 *   ]
 */
class SdmxDsdImporter
{
	const VERSION_21 = '2.1';
	const VERSION_30 = '3.0';

	const XML_LANG = 'http://www.w3.org/XML/1998/namespace';

	/** @var string[] */
	private $warnings = array();

	/** @var string[] */
	private static $nsHints21 = array(
		'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message',
		'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/structure',
		'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/common',
	);

	/** @var string[] */
	private static $nsHints30 = array(
		'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/message',
		'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/structure',
		'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/common',
	);

	/**
	 * SDMX TextFormat textType → our data_type values.
	 * Unmapped / time-period types default to 'string' (handled in _mapTextType).
	 *
	 * @var string[]
	 */
	private static $TEXT_TYPE_MAP = array(
		// String-like
		'String'           => 'string',
		'Alpha'            => 'string',
		'AlphaNumeric'     => 'string',
		'XHTML'            => 'string',
		'URI'              => 'string',
		// Integer-like
		'Integer'          => 'integer',
		'Long'             => 'integer',
		'Short'            => 'integer',
		'BigInteger'       => 'integer',
		'Count'            => 'integer',
		// Float
		'Float'            => 'float',
		// Double / numeric
		'Double'           => 'double',
		'Decimal'          => 'double',
		'Numeric'          => 'double',
		'Number'           => 'double',
		// Boolean
		'Boolean'          => 'boolean',
		// Date / time — mapped to 'date'; time-period strings stay 'string' (see _mapTextType)
		'Date'             => 'date',
		'DateTime'         => 'date',
		'GregorianDay'     => 'date',
		'GregorianYearMonth' => 'date',
		'GregorianYear'    => 'date',
	);

	public function __construct()
	{
		log_message('debug', 'SdmxDsdImporter Class Initialized.');
	}

	/** @return string[] */
	public function get_warnings()
	{
		return $this->warnings;
	}

	/**
	 * @param string $path Absolute or relative file path
	 * @return array
	 */
	public function parseFile($path)
	{
		$this->warnings = array();
		if (!is_readable($path)) {
			return $this->_error('File not readable: ' . $path);
		}
		$xml = file_get_contents($path);
		if ($xml === false) {
			return $this->_error('Could not read file: ' . $path);
		}
		return $this->parseString($xml);
	}

	/**
	 * Fetch XML from a URL and parse it. Supports both DSD and Dataflow URLs:
	 * if a Dataflow is detected, the referenced DSD is fetched automatically.
	 *
	 * @param string $url
	 * @param int    $timeout Seconds per request
	 * @return array
	 */
	public function parseUrl($url, $timeout = 30)
	{
		$this->warnings = array();
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return $this->_error('Invalid URL: ' . $url);
		}

		$xml = $this->_fetchUrl($url, $timeout);
		if ($xml === false) {
			return $this->_error('Could not fetch URL: ' . $url);
		}

		$result = $this->_parseXml($xml);

		// Dataflow without an inline DSD → follow through to the DSD URL
		if (isset($result['_type']) && $result['_type'] === 'dataflow_ref') {
			$dsd_ref = $result['dsd_ref'];
			$dsd_url = $this->_build_dsd_url($url, $dsd_ref);
			if ($dsd_url === null) {
				return $this->_error(
					'Dataflow references DSD ' . $dsd_ref['agency'] . ':' . $dsd_ref['id']
					. '(' . $dsd_ref['version'] . ') but could not construct DSD URL from: ' . $url
				);
			}
			$this->warnings[] = 'Dataflow URL detected; fetching DSD from ' . $dsd_url;
			$dsd_xml = $this->_fetchUrl($dsd_url, $timeout);
			if ($dsd_xml === false) {
				return $this->_error('Could not fetch DSD URL: ' . $dsd_url);
			}
			$result = $this->_parseXml($dsd_xml);
			if (isset($result['status']) && $result['status'] === 'success') {
				$result['input_type'] = 'dataflow';
			}
		}

		return $result;
	}

	/**
	 * @param string $xml UTF-8 SDMX-ML
	 * @return array
	 */
	public function parseString($xml)
	{
		$this->warnings = array();
		$result = $this->_parseXml($xml);

		// Surface a helpful error when a Dataflow file is uploaded without URL context
		if (isset($result['_type']) && $result['_type'] === 'dataflow_ref') {
			$ref = $result['dsd_ref'];
			return $this->_error(
				'This file contains a Dataflow (not a DataStructure). '
				. 'Provide the Dataflow URL instead of uploading the file so the DSD can be fetched automatically. '
				. 'DSD reference in this Dataflow: ' . $ref['agency'] . ':' . $ref['id'] . '(' . $ref['version'] . ')'
			);
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Core parsing
	// -------------------------------------------------------------------------

	/**
	 * Parse raw XML string into a result array (or internal 'dataflow_ref' marker).
	 *
	 * @param string $xml
	 * @return array
	 */
	private function _parseXml($xml)
	{
		if (!is_string($xml) || trim($xml) === '') {
			return $this->_error('Empty XML string');
		}

		$prev   = libxml_use_internal_errors(true);
		$doc    = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$loaded = $doc->loadXML($xml, LIBXML_NONET);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($prev);

		if (!$loaded) {
			$msg = 'Invalid XML';
			if (!empty($errors)) {
				$msg .= ': ' . trim($errors[0]->message);
			}
			return $this->_error($msg);
		}

		return $this->_parseDocument($doc);
	}

	/** @param DOMDocument $doc */
	private function _parseDocument(DOMDocument $doc)
	{
		$version = $this->_detectVersion($doc);
		if ($version === null) {
			return $this->_error('Could not detect SDMX 2.1 or 3.0 namespaces in document');
		}

		// Try DataStructure first
		$dsdElements = $this->_elementsByLocalName($doc, 'DataStructure');
		$dsdEl = null;
		for ($i = 0; $i < $dsdElements->length; $i++) {
			$el = $dsdElements->item($i);
			if ($el instanceof DOMElement) {
				$dsdEl = $el;
				break;
			}
		}

		if ($dsdEl !== null) {
			// Found a DataStructure — parse it directly
			if ($dsdElements->length > 1) {
				$this->warnings[] = 'Multiple DataStructure elements found; only the first will be imported';
			}
			$inlineCodelists = $this->_parseInlineCodelists($doc);
			$dsd = $this->_parseDataStructure($dsdEl, $inlineCodelists);
			if ($dsd === null) {
				return $this->_error('Failed to parse DataStructure element');
			}
			return array(
				'status'       => 'success',
				'sdmx_version' => $version,
				'input_type'   => 'dsd',
				'dsd'          => $dsd,
				'warnings'     => $this->warnings,
			);
		}

		// No DataStructure — check for Dataflow
		$flowRef = $this->_extractDataflowDsdRef($doc);
		if ($flowRef !== null) {
			// Internal marker; parseUrl will follow through; parseString will surface an error
			return array(
				'_type'        => 'dataflow_ref',
				'sdmx_version' => $version,
				'dsd_ref'      => $flowRef,
				'warnings'     => $this->warnings,
			);
		}

		return $this->_error('No DataStructure or Dataflow element found in the document');
	}

	// -------------------------------------------------------------------------
	// Dataflow handling
	// -------------------------------------------------------------------------

	/**
	 * Find the first Dataflow element and extract its referenced DSD identity.
	 *
	 * SDMX 2.1:
	 *   <str:Dataflow id="WDI" agencyID="WB" version="1.0">
	 *     <str:Structure>
	 *       <Ref agencyID="WB" id="WDI" version="1.0" class="DataStructure"/>
	 *     </str:Structure>
	 *   </str:Dataflow>
	 *
	 * SDMX 3.0:
	 *   <str:Dataflow id="WDI" agencyID="WB" version="1.0">
	 *     <str:Structure>
	 *       <Ref urn="urn:sdmx:org.sdmx.infomodel.datastructure.DataStructure=WB:WDI(1.0)"/>
	 *     </str:Structure>
	 *   </str:Dataflow>
	 *
	 * @param DOMDocument $doc
	 * @return array|null [ 'agency'=>string, 'id'=>string, 'version'=>string ] or null
	 */
	private function _extractDataflowDsdRef(DOMDocument $doc)
	{
		$flows = $this->_elementsByLocalName($doc, 'Dataflow');
		for ($i = 0; $i < $flows->length; $i++) {
			$flow = $flows->item($i);
			if (!($flow instanceof DOMElement)) {
				continue;
			}
			// Look for Structure > Ref child
			$structList = $this->_elementsByLocalName($flow, 'Structure');
			if ($structList->length === 0) {
				continue;
			}
			$struct = $structList->item(0);
			if (!($struct instanceof DOMElement)) {
				continue;
			}
			$refList = $this->_elementsByLocalName($struct, 'Ref');
			if ($refList->length === 0) {
				// Direct urn on Structure element (some 3.0 styles)
				$urn = $this->_pickAttr($struct, array('urn'));
				if ($urn !== null) {
					$parsed = $this->_parseCodelistUrn($urn);
					if ($parsed !== null) {
						return $parsed;
					}
				}
				continue;
			}
			$ref = $refList->item(0);
			if (!($ref instanceof DOMElement)) {
				continue;
			}
			// Direct attributes
			$agency  = $this->_pickAttr($ref, array('agencyID', 'agencyId'));
			$id      = $this->_pickAttr($ref, array('id'));
			$version = $this->_pickAttr($ref, array('version'));
			if ($id !== null && $id !== '') {
				return array(
					'agency'  => $agency ?: 'UNKNOWN',
					'id'      => $id,
					'version' => $version ?: '1.0',
				);
			}
			// URN fallback
			$urn = $this->_pickAttr($ref, array('urn'));
			if ($urn !== null) {
				$parsed = $this->_parseCodelistUrn($urn);
				if ($parsed !== null) {
					return $parsed;
				}
			}
		}
		return null;
	}

	/**
	 * Build a DSD URL from a Dataflow URL and a DSD ref.
	 *
	 * Replaces the /dataflow/{agency}/{id}/{version} path segment with
	 * /datastructure/{ref.agency}/{ref.id}/{ref.version}?references=codelists.
	 *
	 * @param string $dataflow_url
	 * @param array  $dsd_ref
	 * @return string|null
	 */
	private function _build_dsd_url($dataflow_url, array $dsd_ref)
	{
		// Strip existing query string
		$base = preg_replace('/\?.*$/', '', $dataflow_url);

		// Replace /dataflow/... path tail
		if (preg_match('#^(.+?)/dataflow(?:/[^/]*){0,3}$#i', $base, $m)) {
			return $m[1]
				. '/datastructure/'
				. rawurlencode($dsd_ref['agency']) . '/'
				. rawurlencode($dsd_ref['id']) . '/'
				. rawurlencode($dsd_ref['version'])
				. '?references=codelists';
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// DataStructure parsing
	// -------------------------------------------------------------------------

	/**
	 * Parse a single DataStructure element into the normalised return shape.
	 *
	 * @param DOMElement $el
	 * @param array      $inlineCodelists [ 'AGENCY:ID(VERSION)' => [ ['code'=>…,'label'=>…,'sort_order'=>…] ] ]
	 * @return array|null
	 */
	private function _parseDataStructure(DOMElement $el, array $inlineCodelists)
	{
		$id      = $this->_pickAttr($el, array('id'));
		$agency  = $this->_pickAttr($el, array('agencyID', 'agencyId'));
		$version = $this->_pickAttr($el, array('version'));

		if ($id === null || $id === '') {
			$this->warnings[] = 'DataStructure missing id attribute';
			$id = 'UNKNOWN';
		}
		if ($agency === null || $agency === '') {
			$agency = 'UNKNOWN';
		}
		if ($version === null || $version === '') {
			$version = '1.0';
		}

		$names = $this->_collectLangMap($el, array('Name'));
		$name  = isset($names['en']) ? $names['en'] : (reset($names) ?: $id);

		$components = array();
		$position   = 0;

		// DimensionList
		$dimLists = $this->_elementsByLocalName($el, 'DimensionList');
		if ($dimLists->length > 0) {
			$dimList = $dimLists->item(0);
			if ($dimList instanceof DOMElement) {
				foreach ($this->_childElements($dimList) as $child) {
					$lname = strtolower($child->localName);
					if ($lname === 'dimension') {
						$position++;
						$components[] = $this->_parseComponent($child, 'Dimension', $position, $inlineCodelists);
					} elseif ($lname === 'timedimension') {
						$position++;
						$components[] = $this->_parseComponent($child, 'TimeDimension', $position, $inlineCodelists);
					}
				}
			}
		}

		// AttributeList
		$attrLists = $this->_elementsByLocalName($el, 'AttributeList');
		if ($attrLists->length > 0) {
			$attrList = $attrLists->item(0);
			if ($attrList instanceof DOMElement) {
				foreach ($this->_childElements($attrList) as $child) {
					if (strtolower($child->localName) === 'attribute') {
						$components[] = $this->_parseComponent($child, 'Attribute', null, $inlineCodelists);
					}
				}
			}
		}

		// MeasureList — SDMX 2.1: PrimaryMeasure; SDMX 3.0: Measure (one or more)
		$measureLists = $this->_elementsByLocalName($el, 'MeasureList');
		if ($measureLists->length > 0) {
			$measureList = $measureLists->item(0);
			if ($measureList instanceof DOMElement) {
				foreach ($this->_childElements($measureList) as $child) {
					$lname = strtolower($child->localName);
					if ($lname === 'primarymeasure' || $lname === 'measure') {
						$components[] = $this->_parseComponent($child, 'Measure', null, $inlineCodelists);
					}
				}
			}
		}

		return array(
			'id'         => $id,
			'agency'     => $agency,
			'version'    => $version,
			'name'       => $name,
			'names'      => $names,
			'components' => $components,
		);
	}

	/**
	 * Parse a single component (Dimension / TimeDimension / Measure / Attribute).
	 *
	 * @param DOMElement $el
	 * @param string     $elementType  'Dimension'|'TimeDimension'|'Measure'|'Attribute'
	 * @param int|null   $position
	 * @param array      $inlineCodelists
	 * @return array
	 */
	private function _parseComponent(DOMElement $el, $elementType, $position, array $inlineCodelists)
	{
		$id = $this->_pickAttr($el, array('id'));
		if ($id === null || $id === '') {
			$id = 'UNKNOWN_' . $elementType . '_' . ($position ?: rand(1, 9999));
			$this->warnings[] = $elementType . ' missing id; assigned placeholder ' . $id;
		}

		$names       = $this->_collectLangMap($el, array('Name'));
		$conceptId   = $this->_extractConceptId($el);
		$conceptRoles = $this->_extractConceptRoles($el);
		$textType    = $this->_extractTextType($el);
		$clRef       = $this->_extractCodelistRef($el);
		$dataType    = $this->_extractDataType($el, $elementType, $clRef !== null);

		// Resolve inline codes if we have a codelist reference
		$codes = array();
		if ($clRef !== null) {
			$key = $clRef['agency'] . ':' . $clRef['id'] . '(' . $clRef['version'] . ')';
			if (isset($inlineCodelists[$key])) {
				$codes = $inlineCodelists[$key];
			} else {
				// Try matching by id only (ignoring version)
				foreach ($inlineCodelists as $clKey => $clCodes) {
					if (strpos($clKey, ':' . $clRef['id'] . '(') !== false) {
						$codes = $clCodes;
						break;
					}
				}
			}
		}

		return array(
			'id'               => $id,
			'element_type'     => $elementType,
			'position'         => $position,
			'concept_id'       => $conceptId ?: $id,
			'concept_roles'    => $conceptRoles,
			'text_type'        => $textType,
			'data_type'        => $dataType,
			'codelist_agency'  => $clRef !== null ? $clRef['agency']  : null,
			'codelist_id'      => $clRef !== null ? $clRef['id']      : null,
			'codelist_version' => $clRef !== null ? $clRef['version'] : null,
			'codes'            => $codes,
			'names'            => $names,
		);
	}

	/**
	 * SDMX 3 ConceptRole refs (e.g. FREQ, GEO).
	 *
	 * @param DOMElement $componentEl
	 * @return string[]
	 */
	private function _extractConceptRoles(DOMElement $componentEl)
	{
		$CI = get_instance();
		$CI->load->library('SDMX/Sdmx_component_semantics');

		return $CI->sdmx_component_semantics->extract_concept_roles_from_dom($componentEl);
	}

	/**
	 * TextFormat/@textType from LocalRepresentation or CoreRepresentation.
	 *
	 * @param DOMElement $componentEl
	 * @return string|null
	 */
	private function _extractTextType(DOMElement $componentEl)
	{
		$CI = get_instance();
		$CI->load->library('SDMX/Sdmx_component_semantics');

		return $CI->sdmx_component_semantics->extract_text_type_from_dom($componentEl);
	}

	// -------------------------------------------------------------------------
	// Data type extraction
	// -------------------------------------------------------------------------

	/**
	 * Extract and map data_type from LocalRepresentation/TextFormat/@textType.
	 *
	 * Falls back to sensible defaults when TextFormat is absent:
	 *   - Measure               → 'double'  (OBS_VALUE is almost always numeric)
	 *   - Dimension with codes  → 'string'  (coded values are strings)
	 *   - TimeDimension         → 'string'  (time period format handled separately)
	 *   - Attribute             → 'string'
	 *   - Dimension (no codes)  → 'string'
	 *
	 * @param DOMElement $componentEl
	 * @param string     $elementType
	 * @param bool       $hasCodelistRef True when component references a codelist
	 * @return string
	 */
	private function _extractDataType(DOMElement $componentEl, $elementType, $hasCodelistRef)
	{
		// Look in LocalRepresentation first, then CoreRepresentation (SDMX 3.0)
		foreach (array('LocalRepresentation', 'CoreRepresentation') as $reprName) {
			$reprList = $this->_elementsByLocalName($componentEl, $reprName);
			if ($reprList->length === 0) {
				continue;
			}
			$repr = $reprList->item(0);
			if (!($repr instanceof DOMElement)) {
				continue;
			}
			$tfList = $this->_elementsByLocalName($repr, 'TextFormat');
			if ($tfList->length === 0) {
				continue;
			}
			$tf = $tfList->item(0);
			if (!($tf instanceof DOMElement)) {
				continue;
			}
			$textType = $this->_pickAttr($tf, array('textType', 'TextType'));
			if ($textType !== null && $textType !== '') {
				return $this->_mapTextType($textType);
			}
		}

		// Fallback defaults
		if ($elementType === 'Measure') {
			return 'double';
		}
		return 'string';
	}

	/**
	 * Map an SDMX textType string to one of our data_type enum values.
	 *
	 * @param string $textType
	 * @return string
	 */
	private function _mapTextType($textType)
	{
		if (isset(self::$TEXT_TYPE_MAP[$textType])) {
			return self::$TEXT_TYPE_MAP[$textType];
		}
		// Time-period types (BasicTimePeriod, ObservationalTimePeriod, ReportingYear, etc.)
		// contain actual period strings (e.g. "2023", "2023-Q1") — store as string
		if (stripos($textType, 'Period') !== false
			|| stripos($textType, 'Reporting') !== false
			|| stripos($textType, 'Standard') !== false
		) {
			return 'string';
		}
		// Unknown types default to string
		$this->warnings[] = 'Unknown SDMX textType "' . $textType . '"; defaulting to string';
		return 'string';
	}

	// -------------------------------------------------------------------------
	// Component sub-extractors
	// -------------------------------------------------------------------------

	/**
	 * Extract the concept id from a ConceptIdentity child.
	 *
	 * SDMX 2.1: <ConceptIdentity><Ref agencyID="…" maintainableParentID="…" id="REF_AREA"/></ConceptIdentity>
	 * SDMX 3.0: <ConceptIdentity><Ref urn="urn:sdmx:…ConceptScheme=…:ConceptScheme.REF_AREA"/></ConceptIdentity>
	 *
	 * @param DOMElement $componentEl
	 * @return string|null
	 */
	private function _extractConceptId(DOMElement $componentEl)
	{
		$ciList = $this->_elementsByLocalName($componentEl, 'ConceptIdentity');
		if ($ciList->length === 0) {
			return null;
		}
		$ci = $ciList->item(0);
		if (!($ci instanceof DOMElement)) {
			return null;
		}

		$refList = $this->_elementsByLocalName($ci, 'Ref');
		if ($refList->length > 0) {
			$ref = $refList->item(0);
			if ($ref instanceof DOMElement) {
				$id = $this->_pickAttr($ref, array('id'));
				if ($id !== null && $id !== '') {
					return $id;
				}
				// SDMX 3.0 URN: …Concept=WB:CONCEPTS(1.0).REF_AREA
				$urn = $this->_pickAttr($ref, array('urn'));
				if ($urn !== null && strpos($urn, '.') !== false) {
					$parts = explode('.', $urn);
					return end($parts);
				}
			}
		}

		return null;
	}

	/**
	 * Extract codelist reference from LocalRepresentation > Enumeration > Ref.
	 *
	 * SDMX 2.1:
	 *   <LocalRepresentation>
	 *     <Enumeration><Ref agencyID="WB" class="Codelist" id="CL_AREA" version="1.0"/></Enumeration>
	 *   </LocalRepresentation>
	 *
	 * SDMX 3.0:
	 *   <LocalRepresentation>
	 *     <Enumeration><Ref urn="urn:sdmx:org.sdmx.infomodel.codelist.Codelist=WB:CL_AREA(1.0)"/></Enumeration>
	 *   </LocalRepresentation>
	 *
	 * @param DOMElement $componentEl
	 * @return array|null [ 'agency'=>string, 'id'=>string, 'version'=>string ] or null
	 */
	private function _extractCodelistRef(DOMElement $componentEl)
	{
		$lrList = $this->_elementsByLocalName($componentEl, 'LocalRepresentation');
		if ($lrList->length === 0) {
			return null;
		}
		$lr = $lrList->item(0);
		if (!($lr instanceof DOMElement)) {
			return null;
		}

		$enumList = $this->_elementsByLocalName($lr, 'Enumeration');
		if ($enumList->length === 0) {
			return null;
		}
		$enum = $enumList->item(0);
		if (!($enum instanceof DOMElement)) {
			return null;
		}

		$refList = $this->_elementsByLocalName($enum, 'Ref');
		if ($refList->length === 0) {
			$urn = $this->_pickAttr($enum, array('urn'));
			if ($urn !== null) {
				return $this->_parseCodelistUrn($urn);
			}
			return null;
		}

		$ref = $refList->item(0);
		if (!($ref instanceof DOMElement)) {
			return null;
		}

		$agency  = $this->_pickAttr($ref, array('agencyID', 'agencyId'));
		$id      = $this->_pickAttr($ref, array('id'));
		$version = $this->_pickAttr($ref, array('version'));

		if ($id !== null && $id !== '') {
			return array(
				'agency'  => $agency ?: 'UNKNOWN',
				'id'      => $id,
				'version' => $version ?: '1.0',
			);
		}

		$urn = $this->_pickAttr($ref, array('urn'));
		if ($urn !== null) {
			return $this->_parseCodelistUrn($urn);
		}

		return null;
	}

	/**
	 * Parse a codelist / DSD URN: …=AGENCY:ID(VERSION)
	 *
	 * @param string $urn
	 * @return array|null
	 */
	private function _parseCodelistUrn($urn)
	{
		if (preg_match('/=([^:]+):([^\(]+)\(([^\)]+)\)/', $urn, $m)) {
			return array(
				'agency'  => $m[1],
				'id'      => $m[2],
				'version' => $m[3],
			);
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Inline codelist extraction
	// -------------------------------------------------------------------------

	/**
	 * Parse all Codelist elements in the document and index codes by 'AGENCY:ID(VERSION)'.
	 *
	 * @param DOMDocument $doc
	 * @return array
	 */
	private function _parseInlineCodelists(DOMDocument $doc)
	{
		$out  = array();
		$list = $this->_elementsByLocalName($doc, 'Codelist');

		for ($i = 0; $i < $list->length; $i++) {
			$cl = $list->item($i);
			if (!($cl instanceof DOMElement)) {
				continue;
			}
			$agency  = $this->_pickAttr($cl, array('agencyID', 'agencyId')) ?: 'UNKNOWN';
			$id      = $this->_pickAttr($cl, array('id'));
			$version = $this->_pickAttr($cl, array('version')) ?: '1.0';

			if ($id === null || $id === '') {
				continue;
			}

			$codes = array();
			$order = 0;
			foreach ($this->_childElements($cl) as $child) {
				if (strcasecmp($child->localName, 'Code') !== 0) {
					continue;
				}
				$code = $this->_pickAttr($child, array('id', 'value'));
				if ($code === null || $code === '') {
					continue;
				}
				$order++;
				$cNames = $this->_collectLangMap($child, array('Name'));
				$label  = isset($cNames['en']) ? $cNames['en'] : (reset($cNames) ?: $code);
				$codes[] = array(
					'code'       => $code,
					'label'      => $label,
					'sort_order' => $order,
				);
			}

			$key       = $agency . ':' . $id . '(' . $version . ')';
			$out[$key] = $codes;
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Version detection
	// -------------------------------------------------------------------------

	/** @param DOMDocument $doc */
	private function _detectVersion(DOMDocument $doc)
	{
		$uris  = $this->_collectNamespaceUris($doc);
		$has21 = false;
		$has30 = false;
		foreach ($uris as $u) {
			if (strpos($u, 'v2_1') !== false || strpos($u, 'v2.1') !== false) {
				$has21 = true;
			}
			if (strpos($u, 'v3_0') !== false || strpos($u, 'v3.0') !== false) {
				$has30 = true;
			}
		}
		if ($has30 && !$has21) {
			return self::VERSION_30;
		}
		if ($has21 && !$has30) {
			return self::VERSION_21;
		}
		if ($has30 && $has21) {
			$n30 = $this->_countArtefactsForNamespaces($doc, self::$nsHints30);
			$n21 = $this->_countArtefactsForNamespaces($doc, self::$nsHints21);
			return ($n21 > $n30) ? self::VERSION_21 : self::VERSION_30;
		}

		// No recognised NS hints — try element count heuristic (DataStructure or Dataflow)
		$n30 = $this->_countArtefactsForNamespaces($doc, self::$nsHints30);
		$n21 = $this->_countArtefactsForNamespaces($doc, self::$nsHints21);
		if ($n30 === 0 && $n21 === 0) {
			// Last resort: inspect namespaceURI of first structural element
			foreach (array('DataStructure', 'Dataflow') as $localName) {
				$any = $this->_elementsByLocalName($doc, $localName);
				if ($any->length > 0) {
					$first = $any->item(0);
					if ($first instanceof DOMElement && $first->namespaceURI) {
						if (strpos($first->namespaceURI, 'v2_1') !== false) {
							return self::VERSION_21;
						}
						if (strpos($first->namespaceURI, 'v3_0') !== false) {
							return self::VERSION_30;
						}
					}
					$this->warnings[] = 'SDMX version inferred as 3.0 (no v2_1/v3_0 namespace matched)';
					return self::VERSION_30;
				}
			}
			return null;
		}
		return $n30 >= $n21 ? self::VERSION_30 : self::VERSION_21;
	}

	/** @return string[] */
	private function _collectNamespaceUris(DOMDocument $doc)
	{
		$out  = array();
		$root = $doc->documentElement;
		if ($root && $root->namespaceURI) {
			$out[$root->namespaceURI] = true;
		}
		$xpath = new DOMXPath($doc);
		$nodes = $xpath->query('//*');
		if ($nodes) {
			foreach ($nodes as $n) {
				if ($n instanceof DOMElement && $n->namespaceURI) {
					$out[$n->namespaceURI] = true;
				}
			}
		}
		return array_keys($out);
	}

	/**
	 * Count DataStructure + Dataflow elements whose namespace matches one of the hint URIs.
	 *
	 * @param DOMDocument $doc
	 * @param string[]    $namespaceUris
	 * @return int
	 */
	private function _countArtefactsForNamespaces(DOMDocument $doc, array $namespaceUris)
	{
		$count = 0;
		foreach (array('DataStructure', 'Dataflow') as $localName) {
			$list = $this->_elementsByLocalName($doc, $localName);
			for ($i = 0; $i < $list->length; $i++) {
				$el = $list->item($i);
				if (!($el instanceof DOMElement)) {
					continue;
				}
				$u = $el->namespaceURI;
				if ($u && in_array($u, $namespaceUris, true)) {
					$count++;
				} elseif (!$u || $u === '') {
					$count++;
				}
			}
		}
		return $count;
	}

	// -------------------------------------------------------------------------
	// DOM helpers
	// -------------------------------------------------------------------------

	/** @return DOMNodeList */
	private function _elementsByLocalName($ctx, $local)
	{
		$xpath = $ctx instanceof DOMDocument
			? new DOMXPath($ctx)
			: new DOMXPath($ctx->ownerDocument);

		if ($ctx instanceof DOMDocument) {
			// Absolute path — search from document root
			return $xpath->query('//*[local-name()="' . $local . '"]');
		}
		// Relative path — search only within descendants of the context element.
		// Using .//* instead of //* is critical: //* is an absolute XPath that
		// ignores the context node and always starts from the document root.
		return $xpath->query('.//*[local-name()="' . $local . '"]', $ctx);
	}

	/** @return DOMElement[] */
	private function _childElements(DOMElement $el)
	{
		$out = array();
		foreach ($el->childNodes as $child) {
			if ($child instanceof DOMElement) {
				$out[] = $child;
			}
		}
		return $out;
	}

	/**
	 * Return value of the first matching attribute from a list of candidate names.
	 *
	 * @param DOMElement $el
	 * @param string[]   $names
	 * @return string|null
	 */
	private function _pickAttr(DOMElement $el, array $names)
	{
		foreach ($names as $name) {
			if ($el->hasAttribute($name)) {
				$v = $el->getAttribute($name);
				if ($v !== '') {
					return $v;
				}
			}
		}
		return null;
	}

	/**
	 * Collect xml:lang → text map for child elements matching any of $localNames.
	 *
	 * @param DOMElement $parent
	 * @param string[]   $localNames e.g. ['Name']
	 * @return array [ 'en' => '...' ]
	 */
	private function _collectLangMap(DOMElement $parent, array $localNames)
	{
		$out = array();
		foreach ($this->_childElements($parent) as $child) {
			foreach ($localNames as $ln) {
				if (strcasecmp($child->localName, $ln) !== 0) {
					continue;
				}
				$lang = $child->getAttributeNS(self::XML_LANG, 'lang');
				if ($lang === '') {
					$lang = $child->getAttribute('xml:lang');
				}
				if ($lang === '') {
					$lang = 'en';
				}
				if (!isset($out[$lang])) {
					$out[$lang] = trim($child->textContent);
				}
			}
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// HTTP fetch helper
	// -------------------------------------------------------------------------

	/**
	 * @param string $url
	 * @param int    $timeout
	 * @return string|false
	 */
	private function _fetchUrl($url, $timeout)
	{
		$ctx = stream_context_create(array(
			'http' => array(
				'timeout' => $timeout,
				'header'  => "Accept: application/vnd.sdmx.structure+xml;version=3.0.0, application/xml, text/xml\r\n",
			),
			'https' => array(
				'timeout' => $timeout,
				'header'  => "Accept: application/vnd.sdmx.structure+xml;version=3.0.0, application/xml, text/xml\r\n",
			),
		));
		return @file_get_contents($url, false, $ctx);
	}

	// -------------------------------------------------------------------------

	/** @return array */
	private function _error($message)
	{
		return array(
			'status'   => 'error',
			'message'  => $message,
			'warnings' => $this->warnings,
		);
	}
}
