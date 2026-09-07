<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Validate and normalize shipped publish form field definitions (flat key => value).
 */
class Publish_form_validator
{
	const CONTEXT_SUBMIT = 'submit';
	const CONTEXT_STORAGE = 'storage';
	const CONTEXT_READY = 'ready';

	/**
	 * @param array $form
	 * @param string|null $context
	 * @return array<int, array>
	 */
	public static function fields_for_context(array $form, $context = null)
	{
		if (!isset($form['fields']) || !is_array($form['fields'])) {
			return array();
		}
		$fields = array();
		foreach ($form['fields'] as $field) {
			if (!is_array($field) || empty($field['key'])) {
				continue;
			}
			if ($context === null) {
				$fields[] = $field;
				continue;
			}
			$contexts = isset($field['contexts']) && is_array($field['contexts'])
				? $field['contexts']
				: array(self::CONTEXT_SUBMIT, self::CONTEXT_STORAGE);
			if (in_array($context, $contexts, true)) {
				$fields[] = $field;
			}
		}
		return $fields;
	}

	/**
	 * @param array $form
	 * @return array<int, string>
	 */
	public static function allowed_keys(array $form)
	{
		$keys = array();
		foreach (self::fields_for_context($form, null) as $field) {
			$keys[] = (string) $field['key'];
		}
		return $keys;
	}

	/**
	 * @param array $form
	 * @param array|null $values
	 * @param string $context
	 * @return array
	 */
	public static function normalize(array $form, $values, $context = self::CONTEXT_STORAGE)
	{
		if (!is_array($values)) {
			return array();
		}

		$out = array();
		$fields_by_key = array();
		foreach (self::fields_for_context($form, $context) as $field) {
			$fields_by_key[(string) $field['key']] = $field;
		}

		foreach ($fields_by_key as $key => $field) {
			if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
				continue;
			}
			$value = $values[$key];
			$field_type = isset($field['type']) ? (string) $field['type'] : 'string';

			if ($field_type === 'integer' || $field_type === 'boolean') {
				if ($field_type === 'boolean') {
					$out[$key] = !empty($value) && $value !== '0' && $value !== 'false';
				} else {
					$out[$key] = (int) $value ? 1 : 0;
				}
				continue;
			}
			$out[$key] = is_scalar($value) ? trim((string) $value) : $value;
		}

		return $out;
	}

	/**
	 * @param array $form
	 * @param array|null $values
	 * @param array $params strict, context, error_prefix
	 * @return array
	 * @throws Exception
	 */
	public static function validate(array $form, $values, $params = array())
	{
		if ($values === null || $values === '') {
			$values = array();
		}
		if (!is_array($values)) {
			throw new Exception('Form values must be an object');
		}

		$context = isset($params['context']) ? (string) $params['context'] : self::CONTEXT_STORAGE;
		$strict = !empty($params['strict']);
		$error_prefix = isset($params['error_prefix']) ? (string) $params['error_prefix'] : 'Form values are invalid';
		$fields = self::fields_for_context($form, $context);
		$allowed_keys = self::allowed_keys($form);

		foreach (array_keys($values) as $key) {
			if (!in_array($key, $allowed_keys, true)) {
				throw new Exception($error_prefix . ': ' . $key . ': Unknown field');
			}
		}

		$normalized = self::normalize($form, $values, $context);
		$merged = array_merge($values, $normalized);

		foreach ($fields as $field) {
			$key = (string) $field['key'];
			$title = isset($field['title']) ? (string) $field['title'] : $key;
			$has_value = array_key_exists($key, $merged) && $merged[$key] !== null && $merged[$key] !== '';

			if ($strict && self::field_is_required($field, $merged)) {
				if (!$has_value) {
					throw new Exception($error_prefix . ': ' . $key . ': ' . $title . ' is required');
				}
			}

			if (!$has_value) {
				continue;
			}

			self::validate_field_value($field, $merged[$key], $title, $error_prefix);
		}

		return $normalized;
	}

	/**
	 * @param array $field
	 * @param array $values
	 * @return bool
	 */
	private static function field_is_required(array $field, array $values)
	{
		if (!empty($field['required'])) {
			if (isset($field['show_when']) && is_array($field['show_when'])) {
				foreach ($field['show_when'] as $dep_key => $dep_value) {
					$actual = isset($values[$dep_key]) ? (string) $values[$dep_key] : '';
					if ((string) $dep_value !== $actual) {
						return false;
					}
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * @param array $field
	 * @param mixed $value
	 * @param string $title
	 * @param string $error_prefix
	 * @throws Exception
	 */
	private static function validate_field_value(array $field, $value, $title, $error_prefix)
	{
		$key = (string) $field['key'];
		$rules = isset($field['rules']) ? $field['rules'] : array();

		if (is_string($rules)) {
			$rules = array_filter(array_map('trim', explode('|', $rules)));
		}
		if (is_array($rules) && self::is_associative_array($rules)) {
			if (!empty($rules['required']) && ($value === null || $value === '')) {
				throw new Exception($error_prefix . ': ' . $key . ': ' . $title . ' is required');
			}
			if (!empty($rules['in_list'])) {
				self::assert_in_list($key, $value, (string) $rules['in_list'], $title, $error_prefix);
			}
			if (!empty($rules['max'])) {
				if (is_string($value) && strlen($value) > (int) $rules['max']) {
					throw new Exception($error_prefix . ': ' . $key . ': exceeds maximum length');
				}
			}
		}
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param string $list
	 * @param string $title
	 * @param string $error_prefix
	 * @throws Exception
	 */
	private static function assert_in_list($key, $value, $list, $title, $error_prefix)
	{
		$allowed = array_map('trim', explode(',', $list));
		$str = is_scalar($value) ? trim((string) $value) : '';
		if (!in_array($str, $allowed, true)) {
			throw new Exception($error_prefix . ': ' . $key . ': ' . $title . ' must be one of: ' . $list);
		}
	}

	/**
	 * @param array $arr
	 * @return bool
	 */
	private static function is_associative_array(array $arr)
	{
		if ($arr === array()) {
			return false;
		}
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
}
