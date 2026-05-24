<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Export codelists to SDMX-ML Structure messages (SDMX 2.1 and 3.0).
 *
 * Usage (CodeIgniter):
 *   $this->load->library('SDMX/SdmxCodelistExporter');
 *   $xml = $this->sdmxcodelistexporter->export($codelists, '2.1');
 *   $xml = $this->sdmxcodelistexporter->export($codelists, '3.0');
 *
 * Input shape for $codelists (array of one or more):
 *   [
 *     [
 *       'codelist'     => [ id, idno, agency, name, version, title, description, uri, ... ],
 *       'translations' => [ [ language, label, description ], ... ],   // codelist_labels rows
 *       'codes'        => [ [ id, code, parent_id, sort_order, labels => [ [language, label, description] ] ], ... ],
 *     ],
 *   ]
 */
class SdmxCodelistExporter
{
	const VERSION_21 = '2.1';
	const VERSION_30 = '3.0';

	const XML_LANG_NS = 'http://www.w3.org/XML/1998/namespace';
	const XMLNS_NS    = 'http://www.w3.org/2000/xmlns/';

	private static $ns21 = array(
		'mes' => 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/message',
		'str' => 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/structure',
		'com' => 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/common',
	);

	private static $ns30 = array(
		'mes' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/message',
		'str' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/structure',
		'com' => 'http://www.sdmx.org/resources/sdmxml/schemas/v3_0/common',
	);

	public function __construct()
	{
		log_message('debug', 'SdmxCodelistExporter Class Initialized.');
	}

	/**
	 * Build a complete SDMX-ML Structure message containing the given codelists.
	 *
	 * @param array  $codelists Array of [ 'codelist'=>[], 'translations'=>[], 'codes'=>[] ]
	 * @param string $version   '2.1' (default) or '3.0'
	 * @return string UTF-8 XML string
	 */
	public function export(array $codelists, $version = self::VERSION_21)
	{
		$ns = ($version === self::VERSION_30) ? self::$ns30 : self::$ns21;

		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		// Root: mes:Structure
		$root = $doc->createElementNS($ns['mes'], 'mes:Structure');
		$root->setAttributeNS(self::XMLNS_NS, 'xmlns:str', $ns['str']);
		$root->setAttributeNS(self::XMLNS_NS, 'xmlns:com', $ns['com']);
		$doc->appendChild($root);

		// Header
		$this->_appendHeader($doc, $root, $ns);

		// Structures > Codelists
		$structures  = $doc->createElementNS($ns['mes'], 'mes:Structures');
		$root->appendChild($structures);
		$codelistsEl = $doc->createElementNS($ns['str'], 'str:Codelists');
		$structures->appendChild($codelistsEl);

		foreach ($codelists as $entry) {
			$clEl = $this->_buildCodelist($doc, $entry, $ns, $version);
			if ($clEl !== null) {
				$codelistsEl->appendChild($clEl);
			}
		}

		return $doc->saveXML();
	}

	// -------------------------------------------------------------------------

	/**
	 * @param DOMDocument $doc
	 * @param DOMElement  $root
	 * @param array       $ns
	 */
	private function _appendHeader(DOMDocument $doc, DOMElement $root, array $ns)
	{
		$header = $doc->createElementNS($ns['mes'], 'mes:Header');
		$root->appendChild($header);

		$idEl = $doc->createElementNS($ns['mes'], 'mes:ID');
		$idEl->appendChild($doc->createTextNode('METADATA_EDITOR_EXPORT_' . date('YmdHis')));
		$header->appendChild($idEl);

		$testEl = $doc->createElementNS($ns['mes'], 'mes:Test');
		$testEl->appendChild($doc->createTextNode('false'));
		$header->appendChild($testEl);

		$prepEl = $doc->createElementNS($ns['mes'], 'mes:Prepared');
		$prepEl->appendChild($doc->createTextNode(date('Y-m-d\TH:i:s')));
		$header->appendChild($prepEl);

		$sender = $doc->createElementNS($ns['mes'], 'mes:Sender');
		$sender->setAttribute('id', 'METADATA_EDITOR');
		$header->appendChild($sender);
	}

	/**
	 * Build a str:Codelist element with its Name, Description, and Code children.
	 *
	 * @param DOMDocument $doc
	 * @param array       $entry   [ codelist=>, translations=>, codes=> ]
	 * @param array       $ns
	 * @param string      $version
	 * @return DOMElement|null
	 */
	private function _buildCodelist(DOMDocument $doc, array $entry, array $ns, $version)
	{
		$codelist     = isset($entry['codelist'])     ? $entry['codelist']     : array();
		$translations = isset($entry['translations']) ? $entry['translations'] : array();
		$codes        = isset($entry['codes'])        ? $entry['codes']        : array();

		$cid     = isset($codelist['name']) ? (string) $codelist['name'] : '';
		$agency  = isset($codelist['agency'])      ? (string) $codelist['agency']      : '';
		$ver     = isset($codelist['version'])     ? (string) $codelist['version']     : '1.0';
		$uri     = isset($codelist['uri'])         ? (string) $codelist['uri']         : '';

		if ($cid === '') {
			return null;
		}

		$clEl = $doc->createElementNS($ns['str'], 'str:Codelist');
		$clEl->setAttribute('id', $cid);
		$clEl->setAttribute('agencyID', $agency !== '' ? $agency : 'UNKNOWN');
		$clEl->setAttribute('version', $ver !== '' ? $ver : '1.0');
		if ($uri !== '') {
			$clEl->setAttribute('uri', $uri);
		}

		// Codelist-level Name elements (one per language from codelist_labels)
		foreach ($translations as $t) {
			$lang  = isset($t['language']) ? (string) $t['language'] : '';
			$label = isset($t['label'])    ? trim((string) $t['label']) : '';
			if ($label === '') {
				continue;
			}
			$nameEl = $doc->createElementNS($ns['com'], 'com:Name');
			if ($lang !== '') {
				$nameEl->setAttributeNS(self::XML_LANG_NS, 'xml:lang', $lang);
			}
			$nameEl->appendChild($doc->createTextNode($label));
			$clEl->appendChild($nameEl);
		}

		// Codelist-level Description elements
		foreach ($translations as $t) {
			$lang = isset($t['language'])    ? (string) $t['language']         : '';
			$desc = isset($t['description']) ? trim((string) $t['description']) : '';
			if ($desc === '') {
				continue;
			}
			$descEl = $doc->createElementNS($ns['com'], 'com:Description');
			if ($lang !== '') {
				$descEl->setAttributeNS(self::XML_LANG_NS, 'xml:lang', $lang);
			}
			$descEl->appendChild($doc->createTextNode($desc));
			$clEl->appendChild($descEl);
		}

		// Build id→code map for parent resolution
		$codeById = array();
		foreach ($codes as $c) {
			if (isset($c['id'], $c['code'])) {
				$codeById[(string) $c['id']] = (string) $c['code'];
			}
		}

		// Code elements
		foreach ($codes as $c) {
			$codeVal = isset($c['code']) ? (string) $c['code'] : '';
			if ($codeVal === '') {
				continue;
			}

			$codeEl = $doc->createElementNS($ns['str'], 'str:Code');
			$codeEl->setAttribute('id', $codeVal);

			// Code Name labels
			$labels = isset($c['labels']) && is_array($c['labels']) ? $c['labels'] : array();
			foreach ($labels as $l) {
				$lang  = isset($l['language']) ? (string) $l['language'] : '';
				$label = isset($l['label'])    ? trim((string) $l['label']) : '';
				if ($label === '') {
					continue;
				}
				$nameEl = $doc->createElementNS($ns['com'], 'com:Name');
				if ($lang !== '') {
					$nameEl->setAttributeNS(self::XML_LANG_NS, 'xml:lang', $lang);
				}
				$nameEl->appendChild($doc->createTextNode($label));
				$codeEl->appendChild($nameEl);
			}

			// Code Description labels
			foreach ($labels as $l) {
				$lang = isset($l['language'])    ? (string) $l['language']         : '';
				$desc = isset($l['description']) ? trim((string) $l['description']) : '';
				if ($desc === '') {
					continue;
				}
				$descEl = $doc->createElementNS($ns['com'], 'com:Description');
				if ($lang !== '') {
					$descEl->setAttributeNS(self::XML_LANG_NS, 'xml:lang', $lang);
				}
				$descEl->appendChild($doc->createTextNode($desc));
				$codeEl->appendChild($descEl);
			}

			// Parent reference
			$parentId = isset($c['parent_id']) && $c['parent_id'] !== null && $c['parent_id'] !== ''
				? (string) $c['parent_id']
				: null;
			if ($parentId !== null && isset($codeById[$parentId])) {
				$parentCode = $codeById[$parentId];
				if ($version === self::VERSION_30) {
					// SDMX 3.0: com:Parent > com:Ref
					$parentEl = $doc->createElementNS($ns['com'], 'com:Parent');
					$refEl    = $doc->createElementNS($ns['com'], 'com:Ref');
					$refEl->setAttribute('id', $parentCode);
					$parentEl->appendChild($refEl);
				} else {
					// SDMX 2.1: str:Parent > Ref (no namespace on Ref)
					$parentEl = $doc->createElementNS($ns['str'], 'str:Parent');
					$refEl    = $doc->createElement('Ref');
					$refEl->setAttribute('id', $parentCode);
					$parentEl->appendChild($refEl);
				}
				$codeEl->appendChild($parentEl);
			}

			$clEl->appendChild($codeEl);
		}

		return $clEl;
	}
}
