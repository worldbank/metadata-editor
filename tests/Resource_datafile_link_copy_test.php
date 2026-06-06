<?php
/**
 * Lightweight tests for resource data-file link remapping on project version copy.
 * Run: php tests/Resource_datafile_link_copy_test.php
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
 * Mirror of Project_versions::copy_resource_datafile_links row-building logic.
 */
function build_copied_resource_datafile_rows(array $source_links, $target_sid, array $resource_id_map)
{
	$link_columns = array(
		'file_id',
		'export_format',
		'export_version',
		'zip_entry_name',
		'link_type',
		'data_file_changed',
		'source_csv_mtime',
		'generated_at',
		'created',
		'created_by',
	);

	$rows = array();
	foreach ($source_links as $link) {
		$old_resource_id = (int) $link['resource_id'];
		if (!isset($resource_id_map[$old_resource_id])) {
			continue;
		}

		$row = array(
			'sid' => (int) $target_sid,
			'resource_id' => (int) $resource_id_map[$old_resource_id],
		);
		foreach ($link_columns as $col) {
			if (array_key_exists($col, $link)) {
				$row[$col] = $link[$col];
			}
		}
		$rows[] = $row;
	}

	return $rows;
}

$source_links = array(
	array(
		'id' => 1,
		'sid' => 10,
		'resource_id' => 100,
		'file_id' => 'F1',
		'export_format' => 'dta',
		'export_version' => '14',
		'zip_entry_name' => 'household.dta',
		'link_type' => 'generated',
		'data_file_changed' => 1700000000,
		'source_csv_mtime' => 1700000001,
		'generated_at' => 1700000100,
		'created' => 1700000100,
		'created_by' => 5,
	),
	array(
		'id' => 2,
		'sid' => 10,
		'resource_id' => 101,
		'file_id' => 'F2',
		'link_type' => 'manual',
		'created' => 1700000200,
		'created_by' => 5,
	),
	array(
		'id' => 3,
		'sid' => 10,
		'resource_id' => 999,
		'file_id' => 'F3',
		'link_type' => 'manual',
	),
);

$rows = build_copied_resource_datafile_rows($source_links, 20, array(100 => 200, 101 => 201));
assert_eq(2, count($rows), 'copies mapped links only');
assert_eq(20, $rows[0]['sid'], 'target sid');
assert_eq(200, $rows[0]['resource_id'], 'remapped generated resource');
assert_eq('F1', $rows[0]['file_id'], 'preserves file_id');
assert_eq('dta', $rows[0]['export_format'], 'preserves export_format');
assert_eq('14', $rows[0]['export_version'], 'preserves export_version');
assert_eq('generated', $rows[0]['link_type'], 'preserves link_type');
assert_eq(201, $rows[1]['resource_id'], 'remapped manual resource');
assert_eq('manual', $rows[1]['link_type'], 'manual link type');

echo "OK ({$assertions} assertions)\n";
