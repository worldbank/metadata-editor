<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Import codelists from SDMX-ML structure messages (SDMX 2.1 and 3.0).
 *
 * Parses flat {@link https://sdmx.org/ SDMX} {@code Codelist} and {@code HierarchicalCodelist}
 * artefacts from uploaded or fetched XML. DSD / MSD import is out of scope for this class.
 *
 * Usage (CodeIgniter):
 *   $this->load->library('SDMX/SdmxCodelistImporter');
 *   $result = $this->sdmxcodelistimporter->parseFile($path);
 *   $result = $this->sdmxcodelistimporter->parseString($xml);
 *
 * Return shape:
 *   [
 *     'status' => 'success'|'error',
 *     'sdmx_version' => '2.1'|'3.0'|null,
 *     'codelists' => [
 *       [
 *         'agency' => string,
 *         'name' => string,              // SDMX maintainable id
 *         'version' => string,
 *         'urn' => string|null,
 *         'uri' => string|null,
 *         'is_final' => bool|null,
 *         'title' => string,             // preferred language label for header
 *         'description' => string|null,
 *         'names' => [ 'en' => '...', ... ],
 *         'descriptions' => [ 'en' => '...', ... ],
 *         'codes' => [
 *           [
 *             'code' => string,
 *             'parent_code' => string|null,
 *             'sort_order' => int,
 *             'urn' => string|null,
 *             'uri' => string|null,
 *             'names' => [ lang => label ],
 *             'descriptions' => [ lang => text ],
 *           ],
 *         ],
 *       ],
 *     ],
 *     'message' => string (on error),
 *     'warnings' => string[],
 *   ]
 */
class SdmxCodelistImporter
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

	// -------------------------------------------------------------------------
	public function __construct()
	{
		log_message('debug', 'SdmxCodelistImporter Class Initialized.');
	}

	/**
	 * @return string[]
	 */
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
	 * @param string $xml UTF-8 SDMX-ML
	 * @return array
	 */
	public function parseString($xml)
	{
		$this->warnings = array();
		if (!is_string($xml) || trim($xml) === '') {
			return $this->_error('Empty XML string');
		}

		$prev = libxml_use_internal_errors(true);
		$doc = new DOMDocument();
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

	// -------------------------------------------------------------------------
	/**
	 * @param DOMDocument $doc
	 * @return array
	 */
	private function _parseDocument(DOMDocument $doc)
	{
		$version = $this->_detectVersion($doc);
		if ($version === null) {
			return $this->_error('Could not detect SDMX 2.1 or 3.0 namespaces in document');
		}

		$codelists = array();
		foreach ($this->_elementsByLocalName($doc, 'Codelist') as $el) {
			if (!($el instanceof DOMElement)) {
				continue;
			}
			// Skip if nested inside another Codelist (should not happen)
			if ($this->_hasAncestorLocalName($el, 'Codelist')) {
				continue;
			}
			$parsed = $this->_parseFlatCodelist($el);
			if ($parsed !== null) {
				$codelists[] = $parsed;
			}
		}

		foreach ($this->_elementsByLocalName($doc, 'HierarchicalCodelist') as $el) {
			if (!($el instanceof DOMElement)) {
				continue;
			}
			if ($this->_hasAncestorLocalName($el, 'HierarchicalCodelist')) {
				continue;
			}
			$parsed = $this->_parseHierarchicalCodelist($el);
			if ($parsed !== null) {
				$codelists[] = $parsed;
			}
		}

		if (count($codelists) === 0) {
			$this->warnings[] = 'No Codelist or HierarchicalCodelist elements found';
		}

		return array(
			'status' => 'success',
			'sdmx_version' => $version,
			'codelists' => $codelists,
			'warnings' => $this->warnings,
		);
	}

	/**
	 * Prefer document namespaces; fall back to counting candidate nodes per version.
	 *
	 * @param DOMDocument $doc
	 * @return string|null self::VERSION_21, self::VERSION_30, or null
	 */
	private function _detectVersion(DOMDocument $doc)
	{
		$uris = $this->_collectNamespaceUris($doc);
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
			$n30 = $this->_countCodelistsForNamespaces($doc, self::$nsHints30);
			$n21 = $this->_countCodelistsForNamespaces($doc, self::$nsHints21);
			if ($n30 > $n21) {
				return self::VERSION_30;
			}
			if ($n21 > $n30) {
				return self::VERSION_21;
			}
			return self::VERSION_30;
		}

		// No known NS hints: try content-based guess
		$n30 = $this->_countCodelistsForNamespaces($doc, self::$nsHints30);
		$n21 = $this->_countCodelistsForNamespaces($doc, self::$nsHints21);
		if ($n30 === 0 && $n21 === 0) {
			// Generic: any namespace Codelist
			$any = $this->_elementsByLocalName($doc, 'Codelist');
			if ($any->length > 0) {
				$first = $any->item(0);
				if ($first instanceof DOMElement) {
					$u = $first->namespaceURI;
					if ($u && strpos($u, 'v2_1') !== false) {
						return self::VERSION_21;
					}
					if ($u && strpos($u, 'v3_0') !== false) {
						return self::VERSION_30;
					}
				}
				$this->warnings[] = 'SDMX version inferred as 3.0 (no v2_1/v3_0 namespace matched)';
				return self::VERSION_30;
			}
			return null;
		}
		return $n30 >= $n21 ? self::VERSION_30 : self::VERSION_21;
	}

	/**
	 * @param DOMDocument $doc
	 * @return string[]
	 */
	private function _collectNamespaceUris(DOMDocument $doc)
	{
		$out = array();
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
	 * @param DOMDocument $doc
	 * @param string[]    $namespaceUris
	 * @return int
	 */
	private function _countCodelistsForNamespaces(DOMDocument $doc, array $namespaceUris)
	{
		$count = 0;
		$list = $this->_elementsByLocalName($doc, 'Codelist');
		for ($i = 0; $i < $list->length; $i++) {
			$el = $list->item($i);
			if (!($el instanceof DOMElement)) {
				continue;
			}
			$u = $el->namespaceURI;
			if ($u && in_array($u, $namespaceUris, true)) {
				$count++;
				continue;
			}
			if (!$u || $u === '') {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * @param DOMDocument|DOMElement $ctx
	 * @param string                 $local
	 * @return DOMNodeList
	 */
	private function _elementsByLocalName($ctx, $local)
	{
		$xpath = $ctx instanceof DOMDocument ? new DOMXPath($ctx) : new DOMXPath($ctx->ownerDocument);
		return $xpath->query('//*[local-name()="' . $local . '"]', $ctx instanceof DOMDocument ? null : $ctx);
	}

	/**
	 * @param DOMElement $el
	 * @param string     $local
	 * @return bool
	 */
	private function _hasAncestorLocalName(DOMElement $el, $local)
	{
		$p = $el->parentNode;
		while ($p) {
			if ($p instanceof DOMElement && strcasecmp($p->localName, $local) === 0) {
				return true;
			}
			$p = $p->parentNode;
		}
		return false;
	}

	/**
	 * Flat Codelist → codes from child Code elements.
	 *
	 * @param DOMElement $cl
	 * @return array|null
	 */
	private function _parseFlatCodelist(DOMElement $cl)
	{
		$agency = $this->_pickAttr($cl, array('agencyID', 'agencyId', 'maintainableParentID'));
		$id = $this->_pickAttr($cl, array('id', 'codelistID'));
		$version = $this->_pickAttr($cl, array('version', 'codelistVersion'));
		if ($id === null || $id === '') {
			$this->warnings[] = 'Skipped Codelist without id';
			return null;
		}
		if ($agency === null || $agency === '') {
			$agency = 'UNKNOWN';
			$this->warnings[] = 'Codelist ' . $id . ': missing agencyID, using UNKNOWN';
		}
		if ($version === null || $version === '') {
			$version = '1.0';
			$this->warnings[] = 'Codelist ' . $id . ': missing version, using 1.0';
		}

		$headerNames = $this->_collectLangMap($cl, array('Name'));
		$headerDesc = $this->_collectLangMap($cl, array('Description'));

		$codes = array();
		$order = 0;
		foreach ($this->_childElements($cl) as $child) {
			if (strcasecmp($child->localName, 'Code') !== 0) {
				continue;
			}
			$codeId = $this->_pickAttr($child, array('id', 'value'));
			if ($codeId === null || $codeId === '') {
				continue;
			}
			$order++;
			$cNames = $this->_collectLangMap($child, array('Name'));
			$cDesc = $this->_collectLangMap($child, array('Description'));
			$codes[] = array(
				'code' => $codeId,
				'parent_code' => null,
				'sort_order' => $order,
				'urn' => $this->_pickAttr($child, array('urn')),
				'uri' => $this->_pickAttr($child, array('uri', 'structureURL')),
				'names' => $cNames,
				'descriptions' => $cDesc,
			);
		}

		return $this->_assembleCodelistRow(
			$agency,
			$id,
			$version,
			$cl,
			$headerNames,
			$headerDesc,
			$codes
		);
	}

	/**
	 * HierarchicalCodelist → codes from HierarchicalCode.
	 *
	 * @param DOMElement $hcl
	 * @return array|null
	 */
	private function _parseHierarchicalCodelist(DOMElement $hcl)
	{
		$agency = $this->_pickAttr($hcl, array('agencyID', 'agencyId', 'maintainableParentID'));
		$id = $this->_pickAttr($hcl, array('id', 'codelistID'));
		$version = $this->_pickAttr($hcl, array('version', 'codelistVersion'));
		if ($id === null || $id === '') {
			$this->warnings[] = 'Skipped HierarchicalCodelist without id';
			return null;
		}
		if ($agency === null || $agency === '') {
			$agency = 'UNKNOWN';
			$this->warnings[] = 'HierarchicalCodelist ' . $id . ': missing agencyID, using UNKNOWN';
		}
		if ($version === null || $version === '') {
			$version = '1.0';
		}

		$headerNames = $this->_collectLangMap($hcl, array('Name'));
		$headerDesc = $this->_collectLangMap($hcl, array('Description'));

		$codes = array();
		$order = 0;
		foreach ($this->_childElements($hcl) as $child) {
			if (strcasecmp($child->localName, 'HierarchicalCode') !== 0) {
				continue;
			}
			$order++;
			$codeId = $this->_hierarchicalCodeId($child);
			if ($codeId === null || $codeId === '') {
				continue;
			}
			$parentCode = $this->_hierarchicalParentCode($child);
			$cNames = $this->_collectLangMap($child, array('Name'));
			$cDesc = $this->_collectLangMap($child, array('Description'));
			$codes[] = array(
				'code' => $codeId,
				'parent_code' => $parentCode,
				'sort_order' => $order,
				'urn' => $this->_pickAttr($child, array('urn')),
				'uri' => $this->_pickAttr($child, array('uri', 'structureURL')),
				'names' => $cNames,
				'descriptions' => $cDesc,
			);
		}

		return $this->_assembleCodelistRow(
			$agency,
			$id,
			$version,
			$hcl,
			$headerNames,
			$headerDesc,
			$codes
		);
	}

	/**
	 * @param string              $agency
	 * @param string              $id
	 * @param string              $version
	 * @param DOMElement          $el
	 * @param array<string,string> $headerNames
	 * @param array<string,string> $headerDesc
	 * @param array                $codes
	 * @return array
	 */
	private function _assembleCodelistRow($agency, $id, $version, DOMElement $el, array $headerNames, array $headerDesc, array $codes)
	{
		$prefName = $this->_preferredLangString($headerNames);
		$prefDesc = $this->_preferredLangString($headerDesc);
		if ($prefName === null) {
			$prefName = $id;
		}

		$isFinal = $this->_parseBoolAttr($el, array('isFinal', 'isfinal'));

		return array(
			'agency' => $agency,
			'name' => $id,
			'version' => $version,
			'urn' => $this->_pickAttr($el, array('urn')),
			'uri' => $this->_pickAttr($el, array('uri', 'structureURL')),
			'is_final' => $isFinal,
			'title' => $prefName,
			'description' => $prefDesc,
			'names' => $headerNames,
			'descriptions' => $headerDesc,
			'codes' => $codes,
		);
	}

	/**
	 * @param DOMElement $hc HierarchicalCode element
	 * @return string|null
	 */
	private function _hierarchicalCodeId(DOMElement $hc)
	{
		$direct = $this->_pickAttr($hc, array('id', 'codeID'));
		if ($direct !== null && $direct !== '') {
			return $direct;
		}
		foreach ($this->_childElements($hc) as $ch) {
			if (strcasecmp($ch->localName, 'Code') !== 0) {
				continue;
			}
			$ref = $this->_firstChildByLocalName($ch, 'Ref');
			if ($ref instanceof DOMElement) {
				$rid = $this->_pickAttr($ref, array('id', 'maintainableParentID'));
				if ($rid !== null && $rid !== '') {
					return $rid;
				}
			}
			$id = $this->_pickAttr($ch, array('id'));
			if ($id !== null && $id !== '') {
				return $id;
			}
		}
		return null;
	}

	/**
	 * @param DOMElement $hc
	 * @return string|null
	 */
	private function _hierarchicalParentCode(DOMElement $hc)
	{
		foreach ($this->_childElements($hc) as $ch) {
			if (strcasecmp($ch->localName, 'Parent') !== 0) {
				continue;
			}
			foreach ($this->_childElements($ch) as $pc) {
				if (strcasecmp($pc->localName, 'Code') !== 0) {
					continue;
				}
				$ref = $this->_firstChildByLocalName($pc, 'Ref');
				if ($ref instanceof DOMElement) {
					$rid = $this->_pickAttr($ref, array('id'));
					if ($rid !== null && $rid !== '') {
						return $rid;
					}
				}
				$id = $this->_pickAttr($pc, array('id'));
				if ($id !== null && $id !== '') {
					return $id;
				}
			}
		}
		return null;
	}

	/**
	 * @param DOMElement $parent
	 * @param string     $local
	 * @return DOMElement|null
	 */
	private function _firstChildByLocalName(DOMElement $parent, $local)
	{
		foreach ($this->_childElements($parent) as $ch) {
			if (strcasecmp($ch->localName, $local) === 0) {
				return $ch;
			}
		}
		return null;
	}

	/**
	 * @param DOMElement $parent  Search direct children only for i18n fields
	 * @param string[]   $locals  e.g. Name, Description
	 * @return array<string,string> lang => text
	 */
	private function _collectLangMap(DOMElement $parent, array $locals)
	{
		$map = array();
		foreach ($this->_childElements($parent) as $ch) {
			$hit = false;
			foreach ($locals as $loc) {
				if (strcasecmp($ch->localName, $loc) === 0) {
					$hit = true;
					break;
				}
			}
			if (!$hit) {
				continue;
			}
			$lang = $this->_elementLang($ch);
			$text = $this->_elementText($ch);
			if ($text === '') {
				continue;
			}
			if ($lang === '') {
				$lang = 'und';
			}
			$map[$lang] = $text;
		}
		return $map;
	}

	/**
	 * @param DOMElement $el
	 * @return string
	 */
	private function _elementLang(DOMElement $el)
	{
		$l = $el->getAttributeNS(self::XML_LANG, 'lang');
		if ($l !== '') {
			return $this->_normalizeLang($l);
		}
		$l = $el->getAttribute('lang');
		if ($l !== '') {
			return $this->_normalizeLang($l);
		}
		return '';
	}

	/**
	 * @param DOMElement $el
	 * @return string
	 */
	private function _elementText(DOMElement $el)
	{
		$t = trim($el->textContent);
		return $t;
	}

	/**
	 * Prefer en, then first key.
	 *
	 * @param array<string,string> $map
	 * @return string|null
	 */
	private function _preferredLangString(array $map)
	{
		if (empty($map)) {
			return null;
		}
		if (isset($map['en'])) {
			return $map['en'];
		}
		foreach ($map as $v) {
			return $v;
		}
		return null;
	}

	/**
	 * @param string $lang
	 * @return string
	 */
	private function _normalizeLang($lang)
	{
		$lang = strtolower(str_replace('_', '-', trim($lang)));
		if (strpos($lang, '-') !== false) {
			$parts = explode('-', $lang, 2);
			return $parts[0];
		}
		return $lang;
	}

	/**
	 * @param DOMElement $el
	 * @param string[]   $attrs
	 * @return string|null
	 */
	private function _pickAttr(DOMElement $el, array $attrs)
	{
		foreach ($attrs as $a) {
			if ($el->hasAttribute($a)) {
				$v = trim($el->getAttribute($a));
				if ($v !== '') {
					return $v;
				}
			}
		}
		return null;
	}

	/**
	 * @param DOMElement $el
	 * @param string[]   $attrs
	 * @return bool|null
	 */
	private function _parseBoolAttr(DOMElement $el, array $attrs)
	{
		foreach ($attrs as $a) {
			if (!$el->hasAttribute($a)) {
				continue;
			}
			$v = strtolower(trim($el->getAttribute($a)));
			if ($v === 'true' || $v === '1') {
				return true;
			}
			if ($v === 'false' || $v === '0') {
				return false;
			}
		}
		return null;
	}

	/**
	 * @param DOMElement $el
	 * @return DOMElement[]
	 */
	private function _childElements(DOMElement $el)
	{
		$out = array();
		foreach ($el->childNodes as $n) {
			if ($n instanceof DOMElement) {
				$out[] = $n;
			}
		}
		return $out;
	}

	/**
	 * @param string $message
	 * @return array
	 */
	private function _error($message)
	{
		return array(
			'status' => 'error',
			'sdmx_version' => null,
			'codelists' => array(),
			'message' => $message,
			'warnings' => $this->warnings,
		);
	}
}
