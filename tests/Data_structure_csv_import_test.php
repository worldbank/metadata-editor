<?php
/**
 * Lightweight tests for CSV component import helpers.
 * Run: php tests/Data_structure_csv_import_test.php
 */

define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');

require_once APPPATH . 'libraries/Data_structure_csv_import.php';

class Data_structure_csv_import_test_ci
{
	public function __construct() {}
}

function &get_instance()
{
	static $ci;
	if ($ci === null) {
		$ci = new Data_structure_csv_import_test_ci();
	}
	return $ci;
}

function assert_eq($expected, $actual, $label)
{
	if ($expected !== $actual) {
		fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
		exit(1);
	}
	echo "OK {$label}\n";
}

assert_eq('CL_REF_AREA', Data_structure_csv_import::codelist_maintainable_name('REF_AREA'), 'prefix component name');
assert_eq('CL_REF_AREA', Data_structure_csv_import::codelist_maintainable_name('ref_area'), 'uppercase');
assert_eq('CL_AREA', Data_structure_csv_import::codelist_maintainable_name('CL_AREA'), 'no double prefix');
assert_eq('CL_SEX', Data_structure_csv_import::codelist_maintainable_name('SEX'), 'short name');

$csv = sys_get_temp_dir() . '/ds_csv_import_test_' . uniqid('', true) . '.csv';
file_put_contents($csv, "code,label\nA,Alpha\nB,Beta\nA,Alpha\nC,\n");
$pairs = Data_structure_csv_import::extract_distinct_code_label_pairs($csv, ',', 'code', 'label');
@unlink($csv);
assert_eq(3, count($pairs), 'distinct code count');
$by_code = array();
foreach ($pairs as $p) {
	$by_code[$p['code']] = $p['label'];
}
assert_eq('Alpha', $by_code['A'], 'code A label');
assert_eq('Beta', $by_code['B'], 'code B label');
assert_eq('C', $by_code['C'], 'empty label falls back to code');

echo "All Data_structure_csv_import tests passed.\n";
