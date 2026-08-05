<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Extract global codelist bindings from editor template JSON (field nodes).
 */
class Editor_template_codelist_util
{
	/**
	 * @param array|string|null $template_payload Decoded template or JSON string
	 * @return array<int, array{field_path: string, codelist_id: int}>
	 */
	public static function collect_global_codelist_refs($template_payload)
	{
		$decoded = self::decode_template_payload($template_payload);
		if ($decoded === null) {
			return array();
		}

		$items = self::resolve_template_items($decoded);
		if ($items === null) {
			return array();
		}

		$refs = array();
		self::walk_template_nodes($items, function ($field) use (&$refs) {
			$codelist_id = self::field_global_codelist_id($field);
			if ($codelist_id <= 0) {
				return;
			}
			$field_path = self::field_path($field);
			if ($field_path === '') {
				return;
			}
			$refs[$field_path] = array(
				'field_path' => $field_path,
				'codelist_id' => $codelist_id,
			);
		});

		return array_values($refs);
	}

	/**
	 * @param array $field Template field node
	 * @return int
	 */
	public static function field_global_codelist_id(array $field)
	{
		$source = isset($field['vocabulary_source']) ? strtolower(trim((string) $field['vocabulary_source'])) : '';
		if ($source !== 'global') {
			return 0;
		}

		return isset($field['global_codelist_id']) ? (int) $field['global_codelist_id'] : 0;
	}

	/**
	 * @param array $field
	 * @return string
	 */
	public static function field_path(array $field)
	{
		if (!empty($field['prop_key']) && is_string($field['prop_key'])) {
			return trim($field['prop_key']);
		}

		if (!empty($field['key']) && is_string($field['key']) && strpos($field['key'], '.') !== false) {
			return trim($field['key']);
		}

		return '';
	}

	/**
	 * Map compact registry codes to template object-array enum row shape (metadata editor / tests).
	 *
	 * @param array<int, array{code?: string, label?: string}> $codes
	 * @param array{code: string, label: string}             $map Prop keys for code and label
	 * @return array<int, array<string, string>>
	 */
	public static function map_registry_codes_to_enum_rows(array $codes, array $map)
	{
		$rows = array();
		$code_key = isset($map['code']) ? trim((string) $map['code']) : '';
		$label_key = isset($map['label']) ? trim((string) $map['label']) : '';
		if ($code_key === '' || $label_key === '') {
			return $rows;
		}

		foreach ($codes as $cr) {
			if (!is_array($cr)) {
				continue;
			}
			$code_val = isset($cr['code']) ? trim((string) $cr['code']) : '';
			if ($code_val === '') {
				continue;
			}
			$label_val = isset($cr['label']) ? trim((string) $cr['label']) : $code_val;
			$rows[] = array(
				$code_key => $code_val,
				$label_key => $label_val,
			);
		}

		return $rows;
	}

	/**
	 * @param array|string|null $template_payload
	 * @return array|null
	 */
	private static function decode_template_payload($template_payload)
	{
		if ($template_payload === null || $template_payload === '') {
			return null;
		}

		if (is_string($template_payload)) {
			$decoded = json_decode($template_payload, true);
			return is_array($decoded) ? $decoded : null;
		}

		return is_array($template_payload) ? $template_payload : null;
	}

	/**
	 * @param array $decoded
	 * @return array|null
	 */
	private static function resolve_template_items(array $decoded)
	{
		if (isset($decoded['items']) && is_array($decoded['items'])) {
			return $decoded['items'];
		}

		if (isset($decoded['template']['items']) && is_array($decoded['template']['items'])) {
			return $decoded['template']['items'];
		}

		return null;
	}

	/**
	 * @param array    $nodes
	 * @param callable $callback
	 */
	private static function walk_template_nodes(array $nodes, $callback)
	{
		foreach ($nodes as $node) {
			if (!is_array($node)) {
				continue;
			}

			$callback($node);

			foreach (array('items', 'props') as $child_key) {
				if (isset($node[$child_key]) && is_array($node[$child_key])) {
					self::walk_template_nodes($node[$child_key], $callback);
				}
			}
		}
	}
}
