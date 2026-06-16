<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * SDMX TIME_PERIOD string rules keyed by FREQ (CL_FREQ codes).
 * Used at import/promote (time_spec) and optional data validation — not stored on DSD components.
 */
class Sdmx_time_period
{
	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->config->load('indicator_dsd', true);
	}

	/**
	 * FREQ code => internal time_period_format token (for FastAPI time_spec).
	 *
	 * @return array<string, string>
	 */
	public function freq_to_format_map()
	{
		$map = $this->CI->config->item('dsd_freq_to_time_period_format', 'indicator_dsd');
		if (!is_array($map)) {
			$map = array();
		}

		return $map;
	}

	/**
	 * @param string|null $freq SDMX FREQ code
	 * @return string|null e.g. YYYY, YYYY-Qn
	 */
	public function format_for_freq($freq)
	{
		if ($freq === null || trim((string) $freq) === '') {
			return null;
		}
		$code = trim((string) $freq);
		$map = $this->freq_to_format_map();

		return isset($map[$code]) ? (string) $map[$code] : null;
	}

	/**
	 * Default FREQ → format map for FastAPI backward compatibility (format → freq inverted).
	 *
	 * @return array<string, string>
	 */
	public function default_freq_by_format_map()
	{
		$map = $this->CI->config->item('dsd_default_freq_by_time_period_format', 'indicator_dsd');
		if (!is_array($map)) {
			return array();
		}

		return $map;
	}

	/**
	 * Validate a TIME_PERIOD value against an SDMX FREQ code.
	 *
	 * @param string      $timePeriod
	 * @param string|null $freq
	 * @return array{valid: bool, message: string|null}
	 */
	public function validate_value_for_freq($timePeriod, $freq)
	{
		$tp = trim((string) $timePeriod);
		if ($tp === '') {
			return array('valid' => false, 'message' => 'TIME_PERIOD is empty');
		}
		if ($freq === null || trim((string) $freq) === '') {
			return array('valid' => true, 'message' => null);
		}

		$f = trim((string) $freq);
		$pattern = $this->regex_for_freq($f);
		if ($pattern === null) {
			return array('valid' => true, 'message' => null);
		}
		if (preg_match($pattern, $tp) === 1) {
			return array('valid' => true, 'message' => null);
		}

		$fmt = $this->format_for_freq($f);
		$hint = $fmt !== null ? " (expected format {$fmt})" : '';

		return array(
			'valid' => false,
			'message' => "TIME_PERIOD '{$tp}' does not match FREQ '{$f}'{$hint}",
		);
	}

	/**
	 * @param string $freq
	 * @return string|null PCRE pattern with delimiters
	 */
	public function regex_for_freq($freq)
	{
		$f = trim((string) $freq);
		$patterns = array(
			'A'   => '/^\d{4}$/',
			'S'   => '/^\d{4}-S[12]$/',
			'Q'   => '/^\d{4}-Q[1-4]$/',
			'M'   => '/^\d{4}-(0[1-9]|1[0-2])$/',
			'W'   => '/^\d{4}-W\d{2}$/',
			'D'   => '/^\d{4}-\d{2}-\d{2}$/',
			'H'   => '/^\d{4}-\d{2}-\d{2}T\d{2}$/',
			'I'   => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
		);

		if (isset($patterns[$f])) {
			return $patterns[$f];
		}

		return null;
	}

	/**
	 * Allowed SDMX FREQ codes from config.
	 *
	 * @return string[]
	 */
	public function allowed_freq_codes()
	{
		$out = array();
		foreach ((array) $this->CI->config->item('dsd_freq_codes', 'indicator_dsd') as $row) {
			if (!empty($row['code'])) {
				$out[] = (string) $row['code'];
			}
		}

		return $out;
	}

	/**
	 * @param string|null $freq
	 * @return bool
	 */
	public function is_allowed_freq_code($freq)
	{
		if ($freq === null || trim((string) $freq) === '') {
			return false;
		}
		$code = trim((string) $freq);

		return in_array($code, $this->allowed_freq_codes(), true);
	}
}
