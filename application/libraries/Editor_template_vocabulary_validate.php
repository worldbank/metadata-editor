<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Validate vocabulary_source / global codelist settings on template field nodes.
 */
class Editor_template_vocabulary_validate
{
	/**
	 * @param array $field Template field node
	 * @return array<int, string> Error messages
	 */
	public static function validate_field(array $field)
	{
		$errors = array();
		$source = isset($field['vocabulary_source']) ? strtolower(trim((string) $field['vocabulary_source'])) : '';
		if ($source !== 'global') {
			return $errors;
		}

		$codelist_id = isset($field['global_codelist_id']) ? (int) $field['global_codelist_id'] : 0;
		if ($codelist_id <= 0) {
			$path = Editor_template_codelist_util::field_path($field);
			$errors[] = $path !== ''
				? "Field \"{$path}\": registry codelist is selected but global_codelist_id is missing."
				: 'A field uses registry vocabulary but global_codelist_id is missing.';
		}

		if (!self::is_flat_object_array_field($field)) {
			return $errors;
		}

		$prop_keys = self::flat_array_prop_keys($field);
		$code_key = isset($field['global_codelist_map_code']) ? trim((string) $field['global_codelist_map_code']) : '';
		$label_key = isset($field['global_codelist_map_label']) ? trim((string) $field['global_codelist_map_label']) : '';

		if ($code_key === '' || $label_key === '') {
			$path = Editor_template_codelist_util::field_path($field);
			$errors[] = $path !== ''
				? "Field \"{$path}\": global codelist mapping (code and label columns) is required for array fields."
				: 'Global codelist mapping is required for array fields.';
			return $errors;
		}

		if (!in_array($code_key, $prop_keys, true)) {
			$path = Editor_template_codelist_util::field_path($field);
			$errors[] = "Field \"{$path}\": global_codelist_map_code \"{$code_key}\" is not a prop on this array.";
		}
		if (!in_array($label_key, $prop_keys, true)) {
			$path = Editor_template_codelist_util::field_path($field);
			$errors[] = "Field \"{$path}\": global_codelist_map_label \"{$label_key}\" is not a prop on this array.";
		}
		if ($code_key !== '' && $code_key === $label_key) {
			$path = Editor_template_codelist_util::field_path($field);
			$errors[] = "Field \"{$path}\": code and label mapping must point to different props.";
		}

		return $errors;
	}

	/**
	 * @param array|string|null $template_payload
	 * @return array<int, string>
	 */
	public static function validate_template($template_payload)
	{
		$decoded = self::decode_template_payload($template_payload);
		if ($decoded === null) {
			return array();
		}
		$items = self::resolve_template_items($decoded);
		if ($items === null) {
			return array();
		}
		$errors = array();
		self::walk_template_nodes($items, function ($field) use (&$errors) {
			if (!is_array($field)) {
				return;
			}
			$errors = array_merge($errors, self::validate_field($field));
		});
		return $errors;
	}

	/**
	 * @param array|string|null $template_payload
	 * @throws Exception
	 */
	public static function assert_valid_template_or_throw($template_payload)
	{
		$errors = self::validate_template($template_payload);
		if ($errors === array()) {
			return;
		}
		if (count($errors) === 1) {
			throw new Exception($errors[0]);
		}
		throw new Exception(
			"Template global vocabulary validation failed:\n- " . implode("\n- ", $errors)
		);
	}

	/**
	 * @param array $field
	 * @return bool
	 */
	public static function is_flat_object_array_field(array $field)
	{
		if (!isset($field['type']) || $field['type'] !== 'array') {
			return false;
		}
		if (empty($field['props']) || !is_array($field['props'])) {
			return false;
		}
		foreach ($field['props'] as $prop) {
			if (!is_array($prop)) {
				continue;
			}
			if (!empty($prop['props']) && is_array($prop['props'])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array $field
	 * @return array<int, string>
	 */
	private static function flat_array_prop_keys(array $field)
	{
		$keys = array();
		if (empty($field['props']) || !is_array($field['props'])) {
			return $keys;
		}
		foreach ($field['props'] as $prop) {
			if (is_array($prop) && !empty($prop['key']) && is_string($prop['key'])) {
				$keys[] = $prop['key'];
			}
		}
		return $keys;
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
