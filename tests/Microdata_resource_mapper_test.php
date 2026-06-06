<?php
/**
 * Lightweight tests for Microdata_resource_mapper (run: php tests/Microdata_resource_mapper_test.php)
 */

define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');

require_once APPPATH . 'libraries/Microdata_resource_mapper.php';

$mapper = new Microdata_resource_mapper();
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

assert_eq('Data in Stata 14 format', $mapper->build_title('dta', 14), 'stata title with version');
assert_eq('Data in CSV format', $mapper->build_title('csv'), 'csv title');
assert_eq('application/zip', $mapper->dcformat_code('dta', true), 'zip dcformat');
assert_eq('application/x-stata', $mapper->dcformat_code('dta', false), 'stata dcformat');
assert_eq('From description', $mapper->build_description(array(
	array('description' => 'From description', 'notes' => 'ignored'),
)), 'description from datafile');
assert_eq('From notes', $mapper->build_description(array(
	array('description' => '', 'notes' => 'From notes'),
)), 'notes fallback');
assert_eq('household.dta', $mapper->zip_entry_name_for_datafile(array(
	'file_name' => 'household',
	'file_id' => 'F1',
), 'dta'), 'zip entry name');

echo "OK ({$assertions} assertions)\n";
