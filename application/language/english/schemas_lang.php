<?php

// Basic schema terms
$lang['schemas']="Schemas";
$lang['core']="Core";
$lang['custom']="Custom";
$lang['core_schema']="Core schema";
$lang['schema_uid']="Schema UID";
$lang['alias']="Alias";
$lang['schema_details']="Details";
$lang['schema_files_tab']="Schema files";
$lang['schema_files']="Schema files";
$lang['schema_icon']="Icon";

// Schema management
$lang['create_schema']="Create schema";
$lang['edit_schema']="Edit schema";
$lang['schema_created']="Schema created successfully.";
$lang['schema_updated']="Schema updated successfully.";
$lang['schema_deleted']="Schema deleted successfully.";
$lang['delete_schema_confirm']="Are you sure you want to delete this schema?";
$lang['schema_not_found']="Schema not found.";
$lang['failed_to_load_schema']="Failed to load schema.";
$lang['core_schema_edit_forbidden']="Core schemas cannot be edited.";
$lang['include_core_schemas']="Include core schemas";
$lang['exclude_core_schemas']="Exclude core schemas";

// Schema files
$lang['main_schema_file']="Main schema file";
$lang['related_schema_files']="Related schema files";
$lang['main_schema']="Main";
$lang['main_schema_hint']="Upload the main JSON schema file (required).";
$lang['related_schema_hint']="Upload additional schema files referenced via \$ref (optional).";
$lang['main_schema_required']="Main schema file is required.";
$lang['schema_files_loading']="Loading schema files...";
$lang['schema_files_updated']="Schema files updated successfully.";
$lang['schema_main_replaced']="Main schema file replaced successfully.";
$lang['schema_related_added']="Related schema files uploaded successfully.";
$lang['schema_file_deleted']="Schema file deleted successfully.";
$lang['schema_file_update_failed']="Schema file operation failed.";
$lang['failed_to_load_schema_files']="Failed to load schema files.";
$lang['delete_schema_file_confirm']="Delete schema file {filename}?";
$lang['replace_main_schema']="Replace main schema";
$lang['add_related_schema_files']="Add related schema files";
$lang['no_schema_files_found']="No schema files found.";
$lang['main_schema_label']="Main schema file";
$lang['related_schema_label']="Related schema file";
$lang['schema_file_upload_label']="Schema file uploads";
$lang['schema_file_upload_hint_create']="Select all JSON schema files to upload. One file must be marked as the main schema.";
$lang['schema_file_upload_hint_edit']="Add new JSON schema files. Choose a main file only if you wish to replace the current one.";
$lang['select_main_schema']="Select main schema file";
$lang['pending_main_indicator']="Pending main schema";
$lang['current_main_indicator']="Current main schema";
$lang['pending_related_indicator']="Pending related schema";
$lang['current_related_indicator']="Existing related schema";
$lang['main_selection_optional_edit']="Leave the selection blank to keep the current main schema.";
$lang['upload_selected_files']="Upload selected files";
$lang['clear_selection']="Clear selection";
$lang['existing_schema_files']="Existing schema files";
$lang['schema_files_required']="At least one schema file is required.";

// Schema UID
$lang['invalid_uid']="UID must be unique and use 3-64 characters (letters, numbers, dash, underscore).";
$lang['uid_hint']="UID must be unique. Allowed characters: letters, numbers, dash and underscore.";

// Core field mappings
$lang['edit_core_mappings']="Edit core mappings";
$lang['core_field']="Core field";
$lang['mapped_field']="Mapped field";
$lang['core_field_idno']="Core field: Identifier";
$lang['core_field_title']="Core field: Title";
$lang['core_field_idno_hint']="JSON pointer to the identifier field (e.g. metadata/idno)";
$lang['core_field_title_hint']="JSON pointer to the title field (e.g. metadata/title)";
$lang['core_field_mappings']="Core field mappings";
$lang['core_field_mappings_hint']="Maps schema fields to core project fields used in project listings, search, and filtering.";
$lang['core_mapping_status']="Mappings";
$lang['mapping_complete']="Mapped";
$lang['mapping_missing']="Not mapped";
$lang['save_mappings']="Save mappings";
$lang['schema_title_required']="Schema title is required to save mappings.";
$lang['schema_mappings_updated']="Schema mappings updated successfully.";
$lang['schema_mappings_update_failed']="Failed to update schema mappings.";
$lang['back_to_schemas']="Back to schemas";

// Attribute mappings
$lang['attribute_key']="Attribute key";
$lang['add_attribute']="Add attribute";
$lang['attribute_key_exists']="Attribute key already exists";
$lang['idno_required']="IDNO is required";
$lang['title_required']="Title is required";

// Schema preview
$lang['preview_schema']="Preview schema";
$lang['preview_schema_tree']="Preview schema (Tree)";
$lang['redoc_not_available']="Schema preview is unavailable (Redoc script not loaded).";
$lang['redoc_failed']="Failed to render schema preview.";
$lang['openapi_json']="OpenAPI (JSON)";
$lang['openapi_yaml']="OpenAPI (YAML)";

// Template regeneration
$lang['generated']="Generated";
$lang['regenerate_template']="Regenerate template";
$lang['regenerate_template_confirm']="Regenerate the template for this schema? The generated default template will be replaced.";
$lang['schema_template_regenerated']="Schema template regenerated successfully.";
$lang['regenerate_template_failed']="Failed to regenerate template.";
$lang['generated_template_locked']="Generated templates are read-only. Duplicate the template to customize it.";

// Reserved root properties (custom schemas)
$lang['schema_has_issues']="Schema has issues. Open it to view the validation report.";
$lang['reserved_root_properties_report_title']="Reserved root-level properties detected";
$lang['reserved_root_properties_report_intro']="These root-level properties cannot be saved in project metadata:";
$lang['reserved_root_properties_report_fix']="Nest them under an object grouping, then replace the main schema file.";

// Miscellaneous
$lang['not_implemented']="Not implemented yet.";

/* End of file schemas_lang.php */
/* Location: ./application/language/english/schemas_lang.php */

