<?php

// ─── Navigation & headings ────────────────────────────────────────────────────
$lang['issues']                         = "Issues";
$lang['issues_list']                    = "Issues list";
$lang['project_issues']                 = "Project issues";
$lang['create_issue']                   = "Create new issue";
$lang['edit_issue']                     = "Edit issue";
$lang['issue_detail']                   = "Issue detail";
$lang['back_to_issues']                 = "Back to issues";
$lang['open']                           = "Open";
$lang['closed']                         = "Closed";
$lang['open_issues']                    = "Open issues";
$lang['closed_issues']                  = "Closed issues";

// ─── Field labels ─────────────────────────────────────────────────────────────
$lang['issue_title']                    = "Title";
$lang['issue_description']             = "Description";
$lang['issue_category']                = "Category";
$lang['issue_severity']                = "Severity";
$lang['issue_status']                  = "Status";
$lang['issue_field_path']              = "Field path";
$lang['issue_current_value']           = "Current value";
$lang['issue_suggested_value']         = "Suggested value";
$lang['issue_notes']                   = "Notes";
$lang['issue_source']                  = "Source";
$lang['issue_project']                 = "Project";
$lang['issue_field_reference']         = "Field reference";
$lang['issue_resolution']              = "Resolution";
$lang['issue_activity']                = "Activity";
$lang['issue_diff']                    = "Diff";
$lang['issue_applied']                 = "Applied";
$lang['issue_not_applied']             = "Not applied";

// ─── Status values ────────────────────────────────────────────────────────────
$lang['status_open']                   = "Open";
$lang['status_accepted']               = "Accepted";
$lang['status_fixed']                  = "Fixed";
$lang['status_rejected']               = "Rejected";
$lang['status_dismissed']              = "Dismissed";
$lang['status_false_positive']         = "False positive";
$lang['status_all']                    = "All";
$lang['status_all_open']               = "All open";
$lang['status_all_closed']             = "All closed";

// ─── Severity values ──────────────────────────────────────────────────────────
$lang['severity_low']                  = "Low";
$lang['severity_medium']               = "Medium";
$lang['severity_high']                 = "High";
$lang['severity_critical']             = "Critical";
$lang['severity_all']                  = "All severities";

// ─── Category values ──────────────────────────────────────────────────────────
$lang['category_typo_wording']         = "Typo / Wording";
$lang['category_inconsistency']        = "Inconsistency";
$lang['category_missing_data']         = "Missing data";
$lang['category_format_issue']         = "Format issue";
$lang['category_completeness']         = "Completeness";
$lang['category_other']                = "Other";
$lang['category_all']                  = "All categories";

// ─── Filter & sort labels ─────────────────────────────────────────────────────
$lang['filter_status']                 = "Status";
$lang['filter_severity']               = "Severity";
$lang['filter_category']               = "Category";
$lang['filter_applied']                = "Applied";
$lang['filter_clear']                  = "Clear filters";
$lang['filter_clear_all']              = "Clear all";
$lang['filter_applied_yes']            = "Applied";
$lang['filter_applied_no']             = "Not applied";
$lang['sort_newest']                   = "Newest first";
$lang['sort_oldest']                   = "Oldest first";
$lang['sort_title_az']                 = "Title A–Z";
$lang['sort_title_za']                 = "Title Z–A";
$lang['sort_severity']                 = "Severity (high first)";

// ─── Placeholders ────────────────────────────────────────────────────────────
$lang['placeholder_search_issues']     = "Search issues...";
$lang['placeholder_issue_title']       = "Short title for the issue";
$lang['placeholder_issue_description'] = "Describe the issue in detail...";
$lang['placeholder_issue_category']    = "Select a category";
$lang['placeholder_issue_field_path']  = "e.g., series_description.methodology";
$lang['placeholder_issue_current']     = "Current value of the field";
$lang['placeholder_issue_suggested']   = "What it should be changed to";
$lang['placeholder_notes']             = "Notes or comments...";
$lang['placeholder_not_set']           = "Not set";

// ─── Hints ───────────────────────────────────────────────────────────────────
$lang['hint_field_path']               = "Identifies the specific metadata field this issue refers to";

// ─── Button / action labels ───────────────────────────────────────────────────
$lang['action_create_issue']           = "Create issue";
$lang['action_save']                   = "Save";
$lang['action_cancel']                 = "Cancel";
$lang['action_delete']                 = "Delete";
$lang['action_edit']                   = "Edit";
$lang['action_view']                   = "View";
$lang['action_accept']                 = "Accept";
$lang['action_reject']                 = "Reject";
$lang['action_dismiss']                = "Dismiss";
$lang['action_mark_fixed']             = "Mark fixed";
$lang['action_false_positive']         = "False positive";
$lang['action_reopen']                 = "Reopen";
$lang['action_apply_to_field']         = "Apply to field";
$lang['action_bulk_actions']           = "Bulk actions";
$lang['action_delete_selected']        = "Delete selected";
$lang['action_maximize']               = "Maximize";
$lang['action_restore']                = "Restore";
$lang['action_close']                  = "Close";

// ─── Bulk action labels ───────────────────────────────────────────────────────
$lang['bulk_accept']                   = "Accept";
$lang['bulk_dismiss']                  = "Dismiss";
$lang['bulk_reject']                   = "Reject";
$lang['bulk_false_positive']           = "Mark as false positive";
$lang['bulk_delete']                   = "Delete";

// ─── Metadata assessment ──────────────────────────────────────────────────────
$lang['assess_metadata']               = "Assess metadata";
$lang['assessment_running']            = "Assessment running";
$lang['assessment_view_status']        = "View status";
$lang['assessment_complete']           = "Assessment complete";
$lang['assessment_cancel']             = "Cancel job";
$lang['assessment_description']        = "This will send the project metadata to the quality assessment service. Detected issues will be added to the issues list and shown next to the relevant fields.";
$lang['assessment_async_note']         = "You do not have to wait for the assessment to finish. You can leave this page and come back later; the issues will appear when the assessment completes.";
$lang['assessment_worker_warning']     = "Worker is not running. The assessment job may not progress until the worker starts.";
$lang['assessment_queued']             = "Assessment queued";
$lang['assessment_queued_view_status'] = "Assessment queued - View status";
$lang['assessment_running_view_status'] = "Assessment running - View status";
$lang['assessment_fastapi_offline']    = "Assessment service is unavailable. Ensure the FastAPI backend is running.";
$lang['assessment_worker_offline']     = "Background worker is not running. Start the worker before running an assessment.";
$lang['assessment_submit_blocked']     = "Assessment cannot be started until all required services are available.";
$lang['assessment_monthly_limit_reached']    = "Monthly assessment limit reached for this site.";
$lang['assessment_monthly_usage']          = "Site usage this month: {used} of {limit}.";

// ─── Success messages ─────────────────────────────────────────────────────────
$lang['issue_created']                 = "Issue created successfully";
$lang['issue_updated']                 = "Issue updated successfully";
$lang['issue_deleted']                 = "Issue deleted successfully";
$lang['issue_saved']                   = "Saved";
$lang['issues_updated']                = ":count issue(s) updated";
$lang['issues_deleted']                = ":count issue(s) deleted";
$lang['changes_applied']               = "Changes applied to project metadata";

// ─── Error messages ───────────────────────────────────────────────────────────
$lang['error_load_issue']              = "Failed to load issue";
$lang['error_issue_not_found']         = "Issue not found";
$lang['error_save_issue']              = "Failed to save";
$lang['error_create_issue']            = "Failed to create issue";
$lang['error_update_issue']            = "Failed to update issue";
$lang['error_delete_issue']            = "Failed to delete issue";
$lang['error_update_status']           = "Failed to update status";
$lang['error_apply_changes']           = "Failed to apply changes";
$lang['error_load_issues']             = "Failed to load issues";
$lang['error_invalid_json']            = "Invalid JSON format or field path not set";
$lang['error_no_field_path']           = "No field path on this issue";
$lang['error_metadata_not_loaded']     = "Project metadata not loaded";
$lang['error_enter_value']             = "Enter a value to apply";

// ─── Validation messages ──────────────────────────────────────────────────────
$lang['validation_required_fields']    = "Please fill in the required fields";
$lang['validation_title_required']     = "Title is required";
$lang['validation_select_issues']      = "Please select issues first";

// ─── Confirmation prompts ─────────────────────────────────────────────────────
$lang['confirm_delete_issue']          = "Are you sure you want to delete this issue?";
$lang['confirm_delete_selected']       = "Delete :count selected issue(s)? This action cannot be undone.";
$lang['confirm_apply_to_field']        = "Apply this value to the project metadata field?";

// ─── Empty & loading states ───────────────────────────────────────────────────
$lang['no_issues_found']               = "No issues found";
$lang['loading_issues']                = "Loading issues...";
$lang['loading_issue']                 = "Loading issue...";
$lang['try_adjusting_filters']         = "Try adjusting your filters";
$lang['issue_is_closed']               = "This issue is closed.";

// ─── Activity / meta ──────────────────────────────────────────────────────────
$lang['activity_created']              = "Created";
$lang['activity_created_by']           = "Created :date by :user";
$lang['activity_resolved']             = "Resolved :date by :user";
$lang['activity_assigned_to']          = "Assigned to :user";
$lang['activity_applied']              = "Applied :date by :user";
$lang['activity_selected']             = ":count selected";
