<?php
/**
 * Indicator / timeseries DSD — feature-specific copy only.
 * Shared labels (Structure, Validation, errors, pagination, etc.) live in general_lang.php.
 */

$lang['dsd_time_period_empty_hint'] = 'Attach a data structure that includes a time period column.';
$lang['column_name_empty'] = 'Column name is empty';
$lang['resize_panels'] = 'Drag to resize panels';
$lang['validate'] = 'Run validation';
$lang['dsd_validation_tab_empty'] = 'Run validation to see structure and data checks.';
$lang['dsd_validation_structure_ok'] = 'Column roles and cardinality match the rules for this project.';
$lang['dsd_validation_section_data'] = 'Data validation';
$lang['dsd_validation_rows_with_value'] = 'Rows with value';
$lang['dsd_validation_unique_observations'] = 'Unique keys';
$lang['dsd_validation_rows_scanned'] = 'Counted';
$lang['dsd_validation_observation_key_truncated'] = 'Counts may be incomplete (scan truncated).';

$lang['dsd_name_reserved_underscore'] = 'Column names cannot start with underscore (_); reserved for system fields.';
$lang['dsd_freq_column_intro'] = 'This field type marks the CSV column that contains FREQ codes (e.g. A, M, Q) per row. Pair it with a Time period column: you do not set a time period format on the time row when this column exists.';
$lang['dsd_freq_code_reference'] = 'FREQ codes (reference from config)';
$lang['dsd_freq_codes_hint'] = 'Map CSV values to these FREQ codes.';
$lang['dsd_time_mode_freq_from_data'] = 'FREQ from data';
$lang['dsd_time_mode_freq_from_data_body'] = 'A FREQ column is defined in this DSD:';
$lang['dsd_time_mode_freq_from_data_tail'] = 'Frequency comes from that column; TIME_PERIOD values are validated for each FREQ.';
$lang['dsd_time_period_import_freq_help'] = 'No FREQ column in this structure. Set the series FREQ on the Import data screen before publishing CSV data.';
$lang['dsd_series_freq_import'] = 'Series frequency (FREQ)';
$lang['dsd_series_freq_import_help'] = 'This structure has no FREQ column. Choose the frequency code for TIME_PERIOD values in this CSV (e.g. A = annual, M = monthly).';
$lang['value_label_column_not_in_dsd'] = 'not listed as attribute';
$lang['value_label_column_no_attributes'] = 'No Attribute columns in this structure. Clear this field to use none.';
$lang['dsd_vocabulary'] = 'Codelist type';
$lang['dsd_vocab_none'] = 'None';
$lang['dsd_vocab_global'] = 'Standard codelist';
$lang['dsd_global_codelist_pick'] = 'Standard codelist';
$lang['global_codelist_preview_title'] = 'Registry codes';
$lang['global_codelist_preview_hint'] = 'Used for validation and charts. To add or change codes, use the site codelist registry.';
$lang['global_codelist_preview_truncated'] = 'Preview shows at most 500 rows. Use search to narrow, or open the codelist in the registry for the full list.';
$lang['global_codelist_preview_empty'] = 'No codes in this codelist.';
$lang['global_codelist_preview_no_results'] = 'No matching codes.';
$lang['global_codelist_preview_load_failed'] = 'Could not load codes.';
$lang['global_codelist_preview_no_registry'] = 'Select a standard codelist above to preview codes.';

$lang['dimension'] = 'Dimension';
$lang['measure'] = 'Measure';
$lang['attribute'] = 'Attribute';
$lang['indicator_name'] = 'Indicator Name';
$lang['annotation'] = 'Annotation';
$lang['periodicity'] = 'Periodicity';

$lang['select_at_least_one_dimension_filter'] = 'Select at least one value in geography or another dimension filter.';
$lang['viz_facets_core_sdmx'] = 'Core';
$lang['geography_codes_combobox'] = 'Enter geography codes (no codelist on DSD)';
$lang['select_geography'] = 'Select geography';
$lang['field_freq'] = 'Periodicity';
$lang['select_freq_codes'] = 'Select frequency codes';
$lang['viz_facets_dimensions_and_measures'] = 'Dimensions';
$lang['dimension_codes_combobox'] = 'Type or paste codes (no codelist on DSD)';
$lang['select_codes'] = 'Select codes';
$lang['viz_facets_attributes'] = 'Attributes';
$lang['viz_facets_annotations'] = 'Annotations';
$lang['viz_facet_hint'] = 'Select filters, then Apply.';
$lang['select_dimension_filters_to_view_chart'] = 'Select at least one geography or dimension filter to view the chart';
$lang['apply_filters_or_import_data'] = 'Apply filters or import data';

$lang['dsd_role_ts_year'] = 'Computed · period year';
$lang['dsd_role_ts_freq'] = 'Computed · frequency';
$lang['dsd_role_unknown'] = 'Not in DSD';
$lang['dsd_role_indicator'] = 'Dimension · indicator';
$lang['dsd_role_geography'] = 'Dimension · geography';
$lang['dsd_role_time'] = 'Dimension · time';
$lang['dsd_role_measure'] = 'Measure · observation';
$lang['dsd_role_attribute'] = 'Attribute';
$lang['dsd_editor_type_label'] = 'DSD type';
$lang['duckdb_timeseries_empty'] = 'No published timeseries table in DuckDB yet. Import and promote data first.';
$lang['no_data_file'] = 'No data file found.';

$lang['dsd_attachment_level'] = 'Applies at';
$lang['dsd_attachment_level_hint'] = 'Observation = once per data row; Series = once per series; DataSet = once for the whole file. Defaults to Observation if not set.';
$lang['dsd_attachment_observation'] = 'Observation';
$lang['dsd_attachment_series'] = 'Series';
$lang['dsd_attachment_dataset'] = 'DataSet';
$lang['dsd_assignment_status'] = 'Value presence';
$lang['dsd_assignment_status_hint'] = 'Mandatory = a value must always be present in exported data; Conditional = value can be absent. Defaults to Conditional if not set.';
$lang['dsd_assignment_mandatory'] = 'Mandatory';
$lang['dsd_assignment_conditional'] = 'Conditional';

$lang['delete_data'] = 'Delete data';
$lang['delete_data_title'] = 'Delete data?';
$lang['delete_data_confirm'] = 'All data will be removed.';

// Generic UI labels not covered by general_lang
$lang['continue'] = 'Continue';
$lang['reset'] = 'Reset';
$lang['of'] = 'of';
$lang['rows'] = 'rows';
$lang['columns'] = 'columns';
$lang['fields'] = 'fields';
$lang['role'] = 'Role';
$lang['mapping'] = 'Mapping';
$lang['field_label'] = 'Field label';
$lang['status'] = 'Status';
$lang['source'] = 'Source';
$lang['value'] = 'Value';
$lang['errors'] = 'Errors';
$lang['warnings'] = 'Warnings';
$lang['skipped'] = 'Skipped';
$lang['structure'] = 'Structure';
$lang['validation'] = 'Validation';
$lang['data_errors'] = 'Data errors';
$lang['data_warnings'] = 'Data warnings';
$lang['validation_passed'] = 'Passed';
$lang['validation_failed'] = 'Failed';
$lang['could_not_save'] = 'Could not save';
$lang['request_failed'] = 'Request failed.';
$lang['optional_lowercase'] = 'optional';
$lang['type_to_search'] = 'Type to search...';

$lang['select_csv_file'] = 'Select CSV file';
$lang['geography'] = 'Geography';
$lang['observation_value'] = 'Observation value';
