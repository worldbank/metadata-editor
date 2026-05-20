<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Import SDMX-ML Structure messages (v2.1 namespaces) into catalogue codelists + data_structures.
 *
 * Supports typical OECD / Eurostat-style messages: structure:Codelists, structure:DataStructures,
 * Dimension / TimeDimension / Attribute / PrimaryMeasure with Enumeration Ref to codelists.
 */
class Sdmx_structure_xml_import {

	const NS_STRUCTURE = 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/structure';
	const NS_COMMON    = 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/common';

	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('Codelists_model');
		$this->CI->load->model('Data_structure_model');
		$this->CI->load->model('Data_structure_component_model');
	}

	/**
	 * @param string $xml
	 * @param array  $options overwrite_codelists (bool), dsd_id (string|null pick DataStructure @id)
	 * @return array { data_structure, components, codelists_created, codelists_reused, codelists_updated, warnings }
	 * @throws Exception
	 */
	public function import_from_xml_string($xml, array $options = [])
	{
		$overwrite = !empty($options['overwrite_codelists']);
		$dsdPick  = isset($options['dsd_id']) ? trim((string) $options['dsd_id']) : '';

		$xml = trim((string) $xml);
		if ($xml === '') {
			throw new Exception('Empty XML.');
		}

		libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE)) {
			$err = libxml_get_last_error();
			libxml_clear_errors();
			throw new Exception('Invalid XML: ' . ($err ? $err->message : 'parse failed'));
		}
		libxml_clear_errors();

		$this->CI->db->trans_begin();

		try {
			$codelistMap = $this->_import_all_codelists($dom, $overwrite);
			$result      = $this->_import_first_data_structure($dom, $codelistMap, $dsdPick);

			if ($this->CI->db->trans_status() === false) {
				throw new Exception('Database transaction failed.');
			}
			$this->CI->db->trans_commit();
			return $result;
		} catch (Exception $e) {
			$this->CI->db->trans_rollback();
			throw $e;
		}
	}

	/**
	 * @return array<string,int> key agency|id|version (lower) -> codelists.id
	 */
	protected function _import_all_codelists(DOMDocument $dom, $overwrite)
	{
		$map      = [];
		$created  = [];
		$reused   = [];
		$updated  = [];
		$warnings = [];

		$list = $dom->getElementsByTagNameNS(self::NS_STRUCTURE, 'Codelist');
		for ($i = 0; $i < $list->length; $i++) {
			/** @var DOMElement $cl */
			$cl = $list->item($i);
			$name    = trim($cl->getAttribute('id'));
			$agency  = trim($cl->getAttribute('agencyID')) !== '' ? trim($cl->getAttribute('agencyID')) : Codelists_model::NADA_DEFAULT_AGENCY;
			$version = trim($cl->getAttribute('version')) !== '' ? trim($cl->getAttribute('version')) : Codelists_model::NADA_DEFAULT_VERSION;
			if ($name === '') {
				$warnings[] = 'Skipped codelist with empty id.';
				continue;
			}

			$description = $this->_first_direct_common_name($cl);
			$codes         = $this->_collect_codes_flat($cl);

			$key = $this->_codelist_key($agency, $name, $version);
			if (isset($map[$key])) {
				continue;
			}

			$existing = $this->CI->Codelists_model->get_by_identity($agency, $name, $version);
			if ($existing) {
				$id = (int) $existing['id'];
				if ($overwrite) {
					$this->CI->Codelists_model->delete_all_items_for_codelist($id);
					$this->_insert_items($id, $codes);
					$updated[] = ['id' => $id, 'name' => $name, 'agency' => $agency, 'version' => $version];
				} else {
					$reused[] = ['id' => $id, 'name' => $name, 'agency' => $agency, 'version' => $version];
				}
				$map[$key] = $id;
				continue;
			}

			$title = $description !== '' ? $description : $name;
			$newId = $this->CI->Codelists_model->create(array(
				'name'        => $name,
				'title'       => $title,
				'agency'      => $agency,
				'version'     => $version,
				'description' => $description !== '' ? $description : null,
			));
			if (!$newId) {
				throw new Exception("Failed to create codelist '{$agency}' / '{$name}' / '{$version}'.");
			}
			$this->_insert_items($newId, $codes);
			$created[] = ['id' => $newId, 'name' => $name, 'agency' => $agency, 'version' => $version];
			$map[$key] = $newId;
		}

		return [
			'map'       => $map,
			'created'   => $created,
			'reused'    => $reused,
			'updated'   => $updated,
			'warnings'  => $warnings,
		];
	}

	protected function _import_first_data_structure(DOMDocument $dom, array $codelistBundle, $dsdPick)
	{
		$map       = $codelistBundle['map'];
		$created   = $codelistBundle['created'];
		$reused    = $codelistBundle['reused'];
		$updated   = $codelistBundle['updated'];
		$warnings  = $codelistBundle['warnings'];

		$xpath = new DOMXPath($dom);
		$xpath->registerNamespace('s', self::NS_STRUCTURE);

		if ($dsdPick !== '') {
			if (!preg_match('/^[A-Za-z0-9._@-]+$/', $dsdPick)) {
				throw new Exception('Invalid dsd_id parameter.');
			}
			$nodes = $xpath->query('//s:DataStructures/s:DataStructure[@id="' . $dsdPick . '"]');
		} else {
			$nodes = $xpath->query('//s:DataStructures/s:DataStructure');
		}
		if (!$nodes || $nodes->length < 1) {
			throw new Exception($dsdPick !== '' ? 'DataStructure not found for id: ' . $dsdPick : 'No DataStructure found in XML.');
		}
		/** @var DOMElement $ds */
		$ds = $nodes->item(0);

		$name    = trim($ds->getAttribute('id'));
		$agency  = trim($ds->getAttribute('agencyID')) !== '' ? trim($ds->getAttribute('agencyID')) : Data_structure_model::DEFAULT_AGENCY;
		$version = trim($ds->getAttribute('version')) !== '' ? trim($ds->getAttribute('version')) : Data_structure_model::DEFAULT_VERSION;
		if ($name === '') {
			throw new Exception('DataStructure has empty id.');
		}
		if ($this->CI->Data_structure_model->get_structure_by_identity($name, $agency, $version)) {
			throw new Exception("Data structure already exists for agency '{$agency}', name '{$name}', version '{$version}'.");
		}

		$title = $this->_first_direct_common_name($ds);
		$meta  = [
			'sdmx_import' => [
				'source' => 'structure_xml',
				'at'     => gmdate('c'),
			],
		];

		$structureId = $this->CI->Data_structure_model->create_structure([
			'name'        => $name,
			'agency'      => $agency,
			'version'     => $version,
			'title'       => $title !== '' ? $title : null,
			'description' => null,
			'status'      => null,
			'metadata'    => $meta,
		]);

		$components = [];
		$order      = 0;

		$dcomp = $xpath->query('./s:DataStructureComponents', $ds)->item(0);
		if (!$dcomp) {
			throw new Exception('DataStructureComponents missing.');
		}

		$dimList = $xpath->query('./s:DimensionList', $dcomp)->item(0);
		if ($dimList) {
			foreach ($this->_element_children($dimList) as $child) {
				$ln = $child->localName;
				if ($ln === 'Dimension') {
					$components[] = $this->_component_from_dimension_like($child, 'Dimension', $map, $order);
					$order++;
				} elseif ($ln === 'TimeDimension') {
					$components[] = $this->_component_from_time_dimension($child, $map, $order);
					$order++;
				}
			}
		}

		$attrList = $xpath->query('./s:AttributeList', $dcomp)->item(0);
		if ($attrList) {
			foreach ($this->_element_children($attrList) as $child) {
				if ($child->localName !== 'Attribute') {
					continue;
				}
				$components[] = $this->_component_from_attribute($child, $map, $order);
				$order++;
			}
		}

		$measList = $xpath->query('./s:MeasureList', $dcomp)->item(0);
		if ($measList) {
			foreach ($this->_element_children($measList) as $child) {
				$mln = $child->localName;
				if ($mln !== 'PrimaryMeasure' && $mln !== 'Measure') {
					continue;
				}
				$components[] = $this->_component_from_primary_measure($child, $order);
				$order++;
			}
		}

		$this->CI->load->library('SDMX/Sdmx_component_semantics');
		$importWarnings = array();
		$this->CI->sdmx_component_semantics->enrich_time_period_metadata($components, $importWarnings);
		$warnings = array_merge($warnings, $importWarnings);

		foreach ($components as $row) {
			$this->CI->Data_structure_component_model->create_component($structureId, $row);
		}

		$row = $this->CI->Data_structure_model->get_structure_by_id($structureId, true);

		return [
			'data_structure'       => $row,
			'codelists_created'    => $created,
			'codelists_reused'     => $reused,
			'codelists_updated'    => $updated,
			'warnings'             => $warnings,
		];
	}

	protected function _component_from_dimension_like(DOMElement $el, $elementType, array $map, $sortBase)
	{
		$compName = trim($el->getAttribute('id'));
		if ($compName === '') {
			throw new Exception('Dimension without id.');
		}
		$label = $this->_first_direct_common_name($el);
		$lr    = $this->_first_child_by_local($el, 'LocalRepresentation');
		$clId  = null;
		$clRef = null;
		if ($lr) {
			$ref = $this->_first_enumeration_codelist_ref($lr);
			if ($ref) {
				$clRef = $ref;
				$key = $this->_codelist_key($ref['agency'], $ref['id'], $ref['version']);
				if (!isset($map[$key])) {
					throw new Exception("Referenced codelist not found in XML: {$ref['agency']} / {$ref['id']} / {$ref['version']}");
				}
				$clId = (int) $map[$key];
			}
		}
		$pos = $el->getAttribute('position');
		$sort = $pos !== '' ? (int) $pos * 10 : $sortBase * 10;

		$this->CI->load->library('SDMX/Sdmx_component_semantics');
		$sem = $this->CI->sdmx_component_semantics;
		$conceptId = $sem->extract_concept_id_from_dom($el);
		$conceptRoles = $sem->extract_concept_roles_from_dom($el);
		$columnType = $sem->resolve_column_type(array(
			'element_type'  => $elementType,
			'concept_id'    => $conceptId ?: $compName,
			'id'            => $compName,
			'concept_roles' => $conceptRoles,
			'codelist_ref'  => $clRef,
		));
		$textType = $sem->extract_text_type_from_dom($el);

		$meta = array(
			'sdmx_concept_id'   => $conceptId,
			'sdmx_element_type' => $elementType,
		);
		if (!empty($conceptRoles)) {
			$meta['sdmx_concept_roles'] = $conceptRoles;
		}

		return [
			'name'                => $compName,
			'label'               => $label !== '' ? $label : null,
			'column_type'         => $columnType,
			'data_type'           => null,
			'codelist_id'         => $clId,
			'sort_order'          => $sort,
			'description'         => null,
			'text_type'           => $textType,
			'metadata'            => $meta,
		];
	}

	protected function _component_from_time_dimension(DOMElement $el, array $map, $sortBase)
	{
		$row = $this->_component_from_dimension_like($el, 'TimeDimension', $map, $sortBase);
		$row['data_type'] = 'string';
		$row['column_type'] = 'time_period';
		return $row;
	}

	protected function _component_from_attribute(DOMElement $el, array $map, $sortBase)
	{
		$row = $this->_component_from_dimension_like($el, 'attribute', $map, $sortBase);
		return $row;
	}

	protected function _component_from_primary_measure(DOMElement $el, $sortBase)
	{
		$compName = trim($el->getAttribute('id'));
		if ($compName === '') {
			throw new Exception('PrimaryMeasure without id.');
		}
		$label = $this->_first_direct_common_name($el);
		$dataType = 'float';
		$lr = $this->_first_child_by_local($el, 'LocalRepresentation');
		if ($lr) {
			$tf = $this->_first_child_by_local($lr, 'TextFormat');
			if ($tf && $tf->hasAttribute('textType')) {
				$tt = strtolower(trim($tf->getAttribute('textType')));
				if ($tt === 'integer') {
					$dataType = 'integer';
				} elseif ($tt === 'string') {
					$dataType = 'string';
				} elseif ($tt === 'double' || $tt === 'float') {
					$dataType = 'float';
				}
			}
		}
		$this->CI->load->library('SDMX/Sdmx_component_semantics');
		$sem = $this->CI->sdmx_component_semantics;
		$conceptId = $sem->extract_concept_id_from_dom($el);

		return [
			'name'         => $compName,
			'label'        => $label !== '' ? $label : null,
			'column_type'  => $sem->resolve_column_type(array(
				'element_type'  => 'Measure',
				'concept_id'    => $conceptId ?: $compName,
				'id'            => $compName,
				'concept_roles' => $sem->extract_concept_roles_from_dom($el),
			)),
			'data_type'    => $dataType,
			'codelist_id'  => null,
			'sort_order'   => 90000 + $sortBase,
			'description'  => null,
			'metadata'     => array(
				'sdmx_concept_id'   => $conceptId,
				'sdmx_element_type' => 'Measure',
			),
		];
	}

	protected function _first_enumeration_codelist_ref(DOMElement $localRep)
	{
		$enum = $this->_first_child_by_local($localRep, 'Enumeration');
		if (!$enum) {
			return null;
		}
		foreach ($enum->childNodes as $ch) {
			if ($ch->nodeType !== XML_ELEMENT_NODE) {
				continue;
			}
			if ($ch->localName !== 'Ref') {
				continue;
			}
			$pkg = strtolower(trim($ch->getAttribute('package')));
			if ($pkg !== '' && $pkg !== 'codelist') {
				continue;
			}
			$id = trim($ch->getAttribute('id'));
			if ($id === '') {
				continue;
			}
			$agency  = trim($ch->getAttribute('agencyID')) !== '' ? trim($ch->getAttribute('agencyID')) : Codelists_model::NADA_DEFAULT_AGENCY;
			$version = trim($ch->getAttribute('version')) !== '' ? trim($ch->getAttribute('version')) : Codelists_model::NADA_DEFAULT_VERSION;
			return ['agency' => $agency, 'id' => $id, 'version' => $version];
		}
		return null;
	}

	protected function _first_child_by_local(DOMElement $parent, $localName)
	{
		foreach ($parent->childNodes as $ch) {
			if ($ch->nodeType === XML_ELEMENT_NODE && $ch->localName === $localName) {
				return $ch;
			}
		}
		return null;
	}

	protected function _element_children(DOMElement $parent)
	{
		$out = [];
		foreach ($parent->childNodes as $ch) {
			if ($ch->nodeType === XML_ELEMENT_NODE) {
				$out[] = $ch;
			}
		}
		return $out;
	}

	protected function _collect_codes_flat(DOMElement $codelistEl)
	{
		$codes = [];
		$ord   = 0;
		foreach ($codelistEl->childNodes as $ch) {
			if ($ch->nodeType !== XML_ELEMENT_NODE || $ch->localName !== 'Code') {
				continue;
			}
			/** @var DOMElement $ch */
			if ($ch->namespaceURI !== self::NS_STRUCTURE) {
				continue;
			}
			$code = trim($ch->getAttribute('id'));
			if ($code === '') {
				continue;
			}
			$title = $this->_first_direct_common_name($ch);
			$codes[] = [
				'code'       => $code,
				'title'      => $title !== '' ? $title : null,
				'sort_order' => $ord++,
			];
		}
		return $codes;
	}

	protected function _first_direct_common_name(DOMElement $el)
	{
		foreach ($el->childNodes as $ch) {
			if ($ch->nodeType === XML_ELEMENT_NODE && $ch->localName === 'Name' && $ch->namespaceURI === self::NS_COMMON) {
				$t = trim($ch->textContent);
				if ($t !== '') {
					return $t;
				}
			}
		}
		return '';
	}

	protected function _insert_items($codelistId, array $codes)
	{
		foreach ($codes as $c) {
			$code = isset($c['code']) ? trim((string) $c['code']) : '';
			if ($code === '') {
				continue;
			}
			$label = '';
			if (isset($c['title']) && $c['title'] !== '') {
				$label = (string) $c['title'];
			}
			$itemId = $this->CI->Codelists_model->add_code((int) $codelistId, array(
				'code' => $code,
				'sort_order' => isset($c['sort_order']) ? (int) $c['sort_order'] : 0,
			));
			if ($itemId && $label !== '') {
				$this->CI->Codelists_model->set_code_label((int) $itemId, 'en', $label, null);
			}
		}
	}

	protected function _codelist_key($agency, $id, $version)
	{
		return strtolower(trim((string) $agency)) . '|' . strtolower(trim((string) $id)) . '|' . strtolower(trim((string) $version));
	}
}
