<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure helpers for comparing a data file's variables (metadata) with the
 * columns in its CSV, and planning which variables can be safely removed.
 *
 * No database or file access here; callers pass in the data.
 */
class Datafile_columns_sync
{
	/**
	 * Compact summary used by the data files listing.
	 *
	 * @param array $diff Result of Editor_datafile_model::get_columns_out_of_sync()
	 * @return array csv_exists, csv_readable, missing_count, extra_count, case_only_count
	 */
	public static function summarize(array $diff)
	{
		$missing = isset($diff['columns_in_db_not_in_csv']) ? $diff['columns_in_db_not_in_csv'] : array();
		$extra = isset($diff['columns_to_remove_from_csv']) ? $diff['columns_to_remove_from_csv'] : array();
		$case_matches = self::case_only_matches($missing, $extra);

		return array(
			'csv_exists' => !empty($diff['csv_exists']),
			'csv_readable' => !isset($diff['csv_readable']) || !empty($diff['csv_readable']),
			'missing_count' => count($missing),
			'extra_count' => count($extra),
			'case_only_count' => count($case_matches),
		);
	}

	/**
	 * Map missing variable names to a CSV column that differs only by letter case.
	 * Only extra CSV columns (not already matched to a variable) are considered.
	 *
	 * @param array $missing_names Variable names not found in the CSV
	 * @param array $extra_columns CSV columns not found among variables
	 * @return array variable name => CSV column name
	 */
	public static function case_only_matches(array $missing_names, array $extra_columns)
	{
		$extra_by_lower = array();
		foreach ($extra_columns as $col) {
			$key = strtolower((string) $col);
			if (!isset($extra_by_lower[$key])) {
				$extra_by_lower[$key] = $col;
			}
		}

		$matches = array();
		foreach ($missing_names as $name) {
			$key = strtolower((string) $name);
			if (isset($extra_by_lower[$key])) {
				$matches[$name] = $extra_by_lower[$key];
			}
		}
		return $matches;
	}

	/**
	 * Decide which variables to remove for one data file.
	 *
	 * @param array $variables Rows with at least uid, name, var_wgt_id for every variable in the file
	 * @param array $missing_names Variable names not found in the CSV
	 * @param array $extra_columns CSV columns not found among variables
	 * @param array $weight_ref_uids uid => list of uids (any file in the project) that use it as weight
	 * @return array remove [{uid,name}], skipped_case [{name,csv_column}], skipped_weight [{uid,name}]
	 */
	public static function plan_removal(array $variables, array $missing_names, array $extra_columns, array $weight_ref_uids)
	{
		$case_matches = self::case_only_matches($missing_names, $extra_columns);

		$missing_lookup = array();
		foreach ($missing_names as $name) {
			$missing_lookup[(string) $name] = true;
		}

		$candidates = array();
		$skipped_case = array();
		$seen_case = array();
		foreach ($variables as $v) {
			$name = isset($v['name']) ? (string) $v['name'] : '';
			if (!isset($missing_lookup[$name])) {
				continue;
			}
			if (isset($case_matches[$name])) {
				if (!isset($seen_case[$name])) {
					$skipped_case[] = array('name' => $name, 'csv_column' => $case_matches[$name]);
					$seen_case[$name] = true;
				}
				continue;
			}
			$candidates[(int) $v['uid']] = $name;
		}

		// A variable used as a weight can go only if every variable that
		// references it is also being removed. Repeat until stable so that
		// chains (A weights B, B weights C) resolve correctly.
		$skipped_weight = array();
		do {
			$changed = false;
			foreach ($candidates as $uid => $name) {
				if (empty($weight_ref_uids[$uid])) {
					continue;
				}
				foreach ($weight_ref_uids[$uid] as $ref_uid) {
					$ref_uid = (int) $ref_uid;
					if ($ref_uid !== $uid && !isset($candidates[$ref_uid])) {
						$skipped_weight[] = array('uid' => $uid, 'name' => $name);
						unset($candidates[$uid]);
						$changed = true;
						break;
					}
				}
			}
		} while ($changed);

		$remove = array();
		foreach ($candidates as $uid => $name) {
			$remove[] = array('uid' => $uid, 'name' => $name);
		}

		return array(
			'remove' => $remove,
			'skipped_case' => $skipped_case,
			'skipped_weight' => $skipped_weight,
		);
	}
}
