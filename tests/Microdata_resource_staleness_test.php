<?php
/**
 * Lightweight tests for microdata resource staleness status resolution.
 * Run: php tests/Microdata_resource_staleness_test.php
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

/**
 * Mirror of Editor_resource_datafile_model::get_resource_staleness status resolution.
 */
function resolve_staleness_status(array $reasons)
{
	$status = 'current';
	if (!empty($reasons)) {
		$status = 'stale';
		foreach ($reasons as $r) {
			if ($r['code'] === 'missing_file') {
				$status = 'missing_file';
				break;
			}
		}
	}

	return $status;
}

assert_eq('current', resolve_staleness_status(array()), 'no reasons is current');
assert_eq('stale', resolve_staleness_status(array(
	array('code' => 'data_file_changed', 'file_id' => 'F1'),
)), 'data change is stale');
assert_eq('missing_file', resolve_staleness_status(array(
	array('code' => 'missing_file', 'file_id' => null),
)), 'missing file takes priority');
assert_eq('missing_file', resolve_staleness_status(array(
	array('code' => 'data_file_changed', 'file_id' => 'F1'),
	array('code' => 'missing_file', 'file_id' => null),
)), 'missing file wins over stale reasons');

echo "OK ({$assertions} assertions)\n";
