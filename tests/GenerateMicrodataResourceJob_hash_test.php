<?php
/**
 * Lightweight tests for generate_microdata_resource job hash stability.
 * Run: php tests/GenerateMicrodataResourceJob_hash_test.php
 */

$assertions = 0;

function assert_eq($expected, $actual, $label)
{
	global $assertions;
	$assertions++;
	if ($expected !== $actual) {
		fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
		exit(1);
	}
}

function generate_microdata_resource_job_hash(array $payload)
{
	$file_ids = array();
	if (!empty($payload['file_ids']) && is_array($payload['file_ids'])) {
		$file_ids = array_map('strval', $payload['file_ids']);
		sort($file_ids);
	}

	$hash_data = array(
		'job_type' => 'generate_microdata_resource',
		'project_id' => (int) $payload['project_id'],
		'export_format' => strtolower(trim((string) $payload['export_format'])),
		'export_version' => isset($payload['export_version']) ? (string) $payload['export_version'] : null,
		'file_ids' => $file_ids,
		'overwrite' => !empty($payload['overwrite']) ? 1 : 0,
		'resource_id' => isset($payload['resource_id']) ? (int) $payload['resource_id'] : null,
	);

	ksort($hash_data);
	return hash('sha256', json_encode($hash_data));
}

$payload = array(
	'project_id' => 42,
	'export_format' => 'DTA',
	'export_version' => '14',
	'file_ids' => array('F2', 'F1'),
	'overwrite' => true,
);

$hash_a = generate_microdata_resource_job_hash($payload);
$hash_b = generate_microdata_resource_job_hash(array(
	'project_id' => 42,
	'export_format' => 'dta',
	'export_version' => '14',
	'file_ids' => array('F1', 'F2'),
	'overwrite' => 1,
));
assert_eq($hash_a, $hash_b, 'hash is stable for equivalent payloads');

$hash_c = generate_microdata_resource_job_hash(array(
	'project_id' => 42,
	'export_format' => 'csv',
	'file_ids' => array('F1', 'F2'),
	'overwrite' => 1,
));
assert_eq(true, $hash_a !== $hash_c, 'hash changes when format changes');

echo "OK ({$assertions} assertions)\n";
