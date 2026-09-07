<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared catalog URL / uid helpers (publish destinations).
 */

if (!function_exists('normalize_catalog_url')) {
	/**
	 * Canonical catalog URL for uniqueness: scheme + lowercased host, no default
	 * port, no trailing slash, no trailing /index.php, no query or fragment.
	 *
	 * @param string $url
	 * @return string
	 * @throws Exception
	 */
	function normalize_catalog_url($url)
	{
		$url = trim((string) $url);
		if ($url === '') {
			throw new Exception('Catalog URL is required');
		}

		$parts = parse_url($url);
		if ($parts === false || empty($parts['host'])) {
			throw new Exception('Invalid URL format');
		}

		$scheme = strtolower(isset($parts['scheme']) ? $parts['scheme'] : 'https');
		if ($scheme !== 'http' && $scheme !== 'https') {
			throw new Exception('URL must start with https:// or http://');
		}

		$host = strtolower($parts['host']);
		$port = isset($parts['port']) ? (int) $parts['port'] : null;
		if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
			$port = null;
		}

		$path = isset($parts['path']) ? $parts['path'] : '';
		$path = preg_replace('#/index\.php$#i', '', $path);
		$path = rtrim($path, '/');

		$normalized = $scheme . '://' . $host;
		if ($port) {
			$normalized .= ':' . $port;
		}
		$normalized .= $path;

		return $normalized;
	}
}

if (!function_exists('catalog_display_url')) {
	/**
	 * Store/display URL: normalized form (no trailing slash).
	 *
	 * @param string $url
	 * @return string
	 */
	function catalog_display_url($url)
	{
		return normalize_catalog_url($url);
	}
}

if (!function_exists('catalog_uid_slug')) {
	/**
	 * Slug for catalog uid from a title. Caller must ensure uniqueness.
	 *
	 * @param string $title
	 * @return string
	 */
	function catalog_uid_slug($title)
	{
		$slug = strtolower(trim((string) $title));
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
		$slug = trim($slug, '-');
		if ($slug === '') {
			$slug = 'catalog';
		}
		if (strlen($slug) > 80) {
			$slug = rtrim(substr($slug, 0, 80), '-');
		}
		return $slug;
	}
}

if (!function_exists('catalog_is_official')) {
	/**
	 * @param mixed $value tinyint, bool, or legacy visibility string
	 * @return bool
	 */
	function catalog_is_official($value)
	{
		if ($value === true || $value === 1 || $value === '1') {
			return true;
		}
		if ($value === false || $value === 0 || $value === '0' || $value === null || $value === '') {
			return false;
		}
		$value = strtolower(trim((string) $value));
		return $value === 'official' || $value === 'public' || $value === 'managed' || $value === 'true';
	}
}

if (!function_exists('catalog_type_is_nada')) {
	/**
	 * @param string $type
	 * @return bool
	 */
	function catalog_type_is_nada($type)
	{
		return strtolower(trim((string) $type)) === 'nada';
	}
}
