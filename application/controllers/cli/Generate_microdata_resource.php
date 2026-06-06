<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Generate a dat/micro external resource from project data files (Phase 1 checkpoint).
 *
 * Usage:
 *   php index.php cli/generate_microdata_resource/run --project-id=123 --format=dta --export-version=14
 *   php index.php cli/generate_microdata_resource/run --project-id=123 --format=csv --overwrite
 *   php index.php cli/generate_microdata_resource/run --project-id=123 --format=sav --resource-id=42
 *   php index.php cli/generate_microdata_resource/run --project-id=123 --format=dta --no-zip
 */
class Generate_microdata_resource extends CI_Controller
{
	private $options = array(
		'project-id' => null,
		'format' => 'dta',
		'export-version' => null,
		'overwrite' => false,
		'resource-id' => null,
		'file-ids' => null,
		'no-zip' => false,
		'user-id' => null,
		'max-wait' => 900,
	);

	public function __construct()
	{
		parent::__construct();

		if (php_sapi_name() !== 'cli') {
			show_error('This controller can only be accessed via CLI');
			exit(1);
		}

		$this->load->database();
		$this->load->library('Microdata_resource_generator');
		$this->parse_arguments();
	}

	public function run()
	{
		$sid = (int) $this->options['project-id'];
		if ($sid < 1) {
			fwrite(STDERR, "Missing required --project-id\n");
			exit(1);
		}

		$file_ids = null;
		if ($this->options['file-ids'] !== null && $this->options['file-ids'] !== '') {
			$file_ids = array_map('trim', explode(',', $this->options['file-ids']));
			$file_ids = array_values(array_filter($file_ids));
		}

		$payload = array(
			'export_format' => $this->options['format'],
			'export_version' => $this->options['export-version'],
			'zip' => !$this->options['no-zip'],
			'overwrite' => $this->options['overwrite'],
			'max_wait_seconds' => (int) $this->options['max-wait'],
		);

		if ($file_ids !== null) {
			$payload['file_ids'] = $file_ids;
		}
		if ($this->options['resource-id'] !== null && $this->options['resource-id'] !== '') {
			$payload['resource_id'] = (int) $this->options['resource-id'];
		}
		if ($this->options['user-id'] !== null && $this->options['user-id'] !== '') {
			$payload['user_id'] = (int) $this->options['user-id'];
		}

		try {
			$result = $this->microdata_resource_generator->generate($sid, $payload);
			echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
			exit(isset($result['status']) && $result['status'] === 'success' ? 0 : 2);
		} catch (Exception $e) {
			fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
			exit(1);
		}
	}

	private function parse_arguments()
	{
		global $argv;
		if (!is_array($argv)) {
			return;
		}

		foreach ($argv as $arg) {
			if (strpos($arg, '--') !== 0) {
				continue;
			}
			$pair = explode('=', substr($arg, 2), 2);
			$key = isset($pair[0]) ? $pair[0] : '';
			$value = isset($pair[1]) ? $pair[1] : true;

			if ($key === 'overwrite' || $key === 'no-zip') {
				$value = ($value === true || $value === '1' || $value === 'true');
			}

			if (array_key_exists($key, $this->options)) {
				$this->options[$key] = $value;
			}
		}
	}
}
