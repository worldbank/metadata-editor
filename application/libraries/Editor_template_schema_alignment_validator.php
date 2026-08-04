<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Compares core editor template dropdown codes to JSON Schema enum values.
 */
class Editor_template_schema_alignment_validator
{
	/**
	 * @param string $data_type Registry data type (e.g. geospatial, microdata)
	 * @param array $template Decoded template JSON (must include items)
	 * @param string $template_label Human-readable label for messages (uid or filename)
	 * @return array{errors: string[], warnings: string[], issues: array<int, array>}
	 */
	public static function validate_template($data_type, array $template, $template_label)
	{
		$schema_path = self::resolve_schema_file_path($data_type);
		if ($schema_path === null) {
			return array(
				'errors' => array(),
				'warnings' => array("Template '{$template_label}': no schema file for data type '{$data_type}'; skipped enum alignment."),
				'issues' => array(),
			);
		}

		return self::validate_template_against_schema_file($schema_path, $template, $template_label);
	}

	/**
	 * Structured enum alignment issues for UI (template manager, API).
	 *
	 * @return array{issues: array<int, array>, warnings: string[]}
	 */
	public static function collect_enum_alignment_issues($data_type, array $template, $template_label)
	{
		$result = self::validate_template($data_type, $template, $template_label);
		return array(
			'issues' => isset($result['issues']) ? $result['issues'] : array(),
			'warnings' => isset($result['warnings']) ? $result['warnings'] : array(),
		);
	}

	/**
	 * @return array{errors: string[], warnings: string[], issues: array<int, array>}
	 */
	public static function validate_template_against_schema_file($schema_path, array $template, $template_label)
	{
		$errors = array();
		$warnings = array();
		$issues = array();

		$schema_root = self::load_schema_root($schema_path);
		if ($schema_root === null) {
			$errors[] = "Template '{$template_label}': could not load schema at {$schema_path}.";
			return array('errors' => $errors, 'warnings' => $warnings, 'issues' => $issues);
		}

		$context = array(
			'root' => $schema_root,
			'dir' => dirname($schema_path),
			'documents' => array(
				realpath($schema_path) => $schema_root,
			),
		);

		if (!isset($template['items']) || !is_array($template['items'])) {
			return array('errors' => $errors, 'warnings' => $warnings, 'issues' => $issues);
		}

		self::walk_template_nodes($template['items'], function ($field) use (&$context, $template_label, &$errors, &$issues) {
			if (!self::field_has_schema_enum_check($field)) {
				return;
			}

			$path = self::field_schema_path($field);
			if ($path === '') {
				return;
			}

			$schema_node = self::resolve_schema_node($context, $path);
			if ($schema_node === null) {
				return;
			}

			$schema_enums = self::schema_enum_values($schema_node, $context);
			if ($schema_enums === null) {
				return;
			}

			$template_codes = self::template_enum_codes($field);
			if ($template_codes === null || $template_codes === array()) {
				return;
			}

			$schema_lookup = array_flip($schema_enums);
			foreach ($template_codes as $code) {
				if (!isset($schema_lookup[$code])) {
					$allowed = implode(', ', array_map(function ($v) {
						return "'{$v}'";
					}, $schema_enums));
					$path = self::field_schema_path($field);
					$select_key = self::field_select_key($field);
					$title = isset($field['title']) && $field['title'] !== '' ? $field['title'] : $path;
					$message = "Enum code '{$code}' is not in schema enum [{$allowed}].";
					$errors[] = "Template '{$template_label}': enum code '{$code}' at '{$path}' is not in schema enum [{$allowed}].";
					$issues[] = array(
						'type' => 'enum_mismatch',
						'key' => $path,
						'select_key' => $select_key,
						'title' => $title,
						'prop_key' => $path,
						'code' => $code,
						'allowed' => $schema_enums,
						'message' => $message,
					);
				}
			}
		});

		return array('errors' => $errors, 'warnings' => $warnings, 'issues' => $issues);
	}

	/**
	 * @param string $data_type
	 * @return string|null Absolute path to main schema file
	 */
	public static function resolve_schema_file_path($data_type)
	{
		$schema_type_map = array(
			'microdata' => 'survey',
			'indicator' => 'timeseries',
			'indicator-db' => 'timeseries-db',
		);

		$schema_type = isset($schema_type_map[$data_type]) ? $schema_type_map[$data_type] : $data_type;
		$candidate = self::join_path(APPPATH . 'schemas', $schema_type . '-schema.json');

		if (is_file($candidate)) {
			return $candidate;
		}

		return null;
	}

	private static function load_schema_root($schema_path)
	{
		$json = @file_get_contents($schema_path);
		if ($json === false) {
			return null;
		}

		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : null;
	}

	private static function field_has_schema_enum_check(array $field)
	{
		if (!isset($field['enum']) || !is_array($field['enum']) || $field['enum'] === array()) {
			return false;
		}

		$store = isset($field['enum_store_column']) ? $field['enum_store_column'] : 'both';
		if ($store === 'label') {
			return false;
		}

		return true;
	}

	private static function field_schema_path(array $field)
	{
		if (!empty($field['prop_key']) && is_string($field['prop_key'])) {
			return $field['prop_key'];
		}

		if (!empty($field['key']) && is_string($field['key']) && strpos($field['key'], '.') !== false) {
			return $field['key'];
		}

		return '';
	}

	private static function field_select_key(array $field)
	{
		if (!empty($field['prop_key']) && is_string($field['prop_key'])) {
			return $field['prop_key'];
		}

		if (!empty($field['key']) && is_string($field['key'])) {
			return $field['key'];
		}

		return '';
	}

	/**
	 * @return string[]|null
	 */
	private static function template_enum_codes(array $field)
	{
		$codes = array();

		foreach ($field['enum'] as $item) {
			if (is_array($item) && isset($item['code'])) {
				$codes[] = (string)$item['code'];
			} elseif (is_string($item)) {
				$codes[] = $item;
			}
		}

		return $codes;
	}

	/**
	 * @return string[]|null
	 */
	private static function schema_enum_values(array $node, array &$context)
	{
		$node = self::deref_node($node, $context);

		if (isset($node['enum']) && is_array($node['enum']) && $node['enum'] !== array()) {
			return array_map('strval', $node['enum']);
		}

		return null;
	}

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

	private static function resolve_schema_node(array &$context, $dot_path)
	{
		$segments = explode('.', $dot_path);
		$node = array(
			'type' => 'object',
			'properties' => isset($context['root']['properties']) ? $context['root']['properties'] : array(),
		);

		foreach ($segments as $segment) {
			$node = self::deref_node($node, $context);

			if (isset($node['type']) && $node['type'] === 'array' && isset($node['items'])) {
				$node = self::deref_node($node['items'], $context);
			}

			if (!isset($node['properties'][$segment])) {
				return null;
			}

			$node = $node['properties'][$segment];
		}

		return self::deref_node($node, $context);
	}

	private static function deref_node($node, array &$context)
	{
		if (!is_array($node)) {
			return $node;
		}

		if (!isset($node['$ref']) || !is_string($node['$ref'])) {
			return $node;
		}

		return self::resolve_ref($node['$ref'], $context);
	}

	private static function resolve_ref($ref, array &$context)
	{
		if (strpos($ref, '#/') === 0) {
			$parts = explode('/', substr($ref, 2));
			$target = $context['root'];
			foreach ($parts as $part) {
				if ($part === '') {
					continue;
				}
				if (!is_array($target) || !array_key_exists($part, $target)) {
					return array();
				}
				$target = $target[$part];
			}
			return self::deref_node($target, $context);
		}

		$filename = $ref;
		if (strpos($ref, '#') !== false) {
			$filename = substr($ref, 0, strpos($ref, '#'));
		}

		if ($filename === '') {
			return array();
		}

		$absolute = self::join_path($context['dir'], $filename);
		$resolved = realpath($absolute);
		if ($resolved === false || !is_file($resolved)) {
			return array();
		}

		if (!isset($context['documents'][$resolved])) {
			$json = @file_get_contents($resolved);
			$decoded = is_string($json) ? json_decode($json, true) : null;
			$context['documents'][$resolved] = is_array($decoded) ? $decoded : array();
		}

		$doc = $context['documents'][$resolved];

		if (strpos($ref, '#') !== false) {
			$fragment = substr($ref, strpos($ref, '#') + 1);
			if (strpos($fragment, '#/') === 0) {
				$parts = explode('/', substr($fragment, 2));
				$target = $doc;
				foreach ($parts as $part) {
					if ($part === '') {
						continue;
					}
					if (!is_array($target) || !array_key_exists($part, $target)) {
						return array();
					}
					$target = $target[$part];
				}
				return self::deref_node($target, $context);
			}
		}

		return self::deref_node($doc, $context);
	}

	private static function join_path($base, $relative)
	{
		return rtrim(str_replace('\\', '/', $base), '/') . '/' . ltrim(str_replace('\\', '/', $relative), '/');
	}
}
