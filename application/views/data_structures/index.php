<!DOCTYPE html>
<html>
<head>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <link href="<?php echo base_url();?>vue-app/assets/mdi.min.css" rel="stylesheet">
    <link href="<?php echo base_url();?>vue-app/assets/vuetify.min.css" rel="stylesheet">
    <link href="<?php echo base_url();?>vue-app/assets/styles.css" rel="stylesheet">
    <script src="<?php echo base_url();?>vue-app/assets/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/lodash.min.js"></script>
    <script src="<?php echo base_url();?>vue-app/assets/bootstrap.bundle.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/moment-with-locales.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/vue-i18n.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
    <?php
        $user = $this->session->userdata('username');
        $this->load->library('Editor_acl');
        $user_info = array_merge(array(
            'username' => $user,
            'is_logged_in' => !empty($user),
            'is_admin' => $this->ion_auth->is_admin(),
            'can_access_site_admin' => $this->ion_auth->can_access_site_admin(),
        ), registry_acl_user_info_flags());
    ?>
    <style>
        .ds-edit-form .ds-header-control.v-input,
        .ds-edit-form .ds-compact-control.v-input {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .ds-edit-form .ds-header-control.v-text-field--outlined:not(.v-textarea) .v-input__control,
        .ds-edit-form .ds-header-control.v-select--outlined .v-input__control {
            min-height: 32px !important;
            height: 32px !important;
        }
        .ds-edit-form .ds-header-control:not(.v-textarea) .v-input__slot {
            min-height: 32px !important;
            font-size: 0.8125rem;
        }
        .ds-edit-form .ds-header-control.v-text-field--outlined:not(.v-textarea) .v-input__slot {
            min-height: 32px !important;
        }
        .ds-edit-form .ds-header-control.v-select--outlined .v-input__slot {
            min-height: 32px !important;
            height: 32px !important;
            padding: 0 6px 0 8px !important;
        }
        .ds-edit-form .ds-compact-control.v-text-field--outlined:not(.v-textarea) .v-input__control,
        .ds-edit-form .ds-compact-control.v-select--outlined .v-input__control {
            min-height: 28px;
        }
        .ds-edit-form .ds-compact-control:not(.v-textarea) .v-input__slot {
            min-height: 28px !important;
            font-size: 0.8125rem;
        }
        .ds-edit-form .ds-compact-control.v-text-field--outlined:not(.v-textarea) .v-input__slot {
            min-height: 28px !important;
            height: 28px !important;
        }
        .ds-edit-form .ds-header-control.v-select .v-select__selections {
            min-height: 32px !important;
            max-height: 32px !important;
            height: 32px !important;
            padding: 0 !important;
            align-items: center;
        }
        .ds-edit-form .ds-header-control.v-select .v-select__selection,
        .ds-edit-form .ds-header-control.v-select .v-select__selection--comma {
            line-height: 32px !important;
            max-height: 32px !important;
            margin: 0 !important;
        }
        .ds-edit-form .ds-header-control.v-select .v-input__append-inner {
            margin-top: 0 !important;
            padding-top: 0 !important;
            align-self: center;
        }
        .ds-edit-form .ds-header-control.v-select .v-input__append-inner .v-icon {
            font-size: 20px !important;
        }
        .ds-edit-form .ds-header-control input,
        .ds-edit-form .ds-header-control .v-select__selection {
            font-size: 0.8125rem;
        }
        .ds-edit-form .ds-compact-control.v-select--outlined .v-input__slot {
            min-height: 28px !important;
            height: 28px !important;
            padding: 0 4px 0 8px !important;
        }
        .ds-edit-form .ds-compact-control.v-select .v-select__selections {
            min-height: 28px !important;
            padding: 0 !important;
        }
        .ds-edit-form .ds-compact-control.v-select .v-select__selection,
        .ds-edit-form .ds-compact-control.v-select .v-select__selection--comma {
            line-height: 28px;
            max-height: 28px;
        }
        .ds-edit-form .ds-compact-control.v-select .v-input__append-inner {
            margin-top: 0 !important;
            padding-top: 0 !important;
            align-self: center;
        }
        .ds-edit-form .ds-compact-control.v-select .v-input__append-inner .v-icon {
            font-size: 18px !important;
        }
        .ds-edit-form .ds-compact-control input,
        .ds-edit-form .ds-compact-control .v-select__selection {
            font-size: 0.8125rem;
        }
        .ds-edit-form .ds-header-control.v-textarea .v-input__control,
        .ds-edit-form .ds-compact-control.v-textarea .v-input__control,
        .ds-edit-form .ds-field-stack.v-textarea .v-input__control {
            min-height: auto !important;
            height: auto !important;
        }
        .ds-edit-form .ds-header-control.v-textarea .v-input__slot,
        .ds-edit-form .ds-compact-control.v-textarea .v-input__slot,
        .ds-edit-form .ds-field-stack.v-textarea .v-input__slot {
            min-height: auto !important;
            height: auto !important;
            padding: 4px 8px !important;
        }
        .ds-edit-form .ds-header-control.v-textarea textarea,
        .ds-edit-form .ds-compact-control.v-textarea textarea,
        .ds-edit-form .ds-field-stack.v-textarea textarea {
            font-size: 0.8125rem;
            line-height: 1.35;
            padding: 2px 0 !important;
            margin-top: 0 !important;
            min-height: 4.5em;
        }
        .ds-edit-form .ds-edit-components tbody td {
            vertical-align: top;
            padding: 2px 4px !important;
        }
        .ds-edit-form .ds-edit-components thead th {
            padding: 4px !important;
            font-size: 0.75rem;
        }
        .ds-edit-form .v-card__text.ds-edit-card-body {
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .ds-edit-form .ds-field-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: rgba(0, 0, 0, 0.6);
            line-height: 1.25;
            margin-bottom: 4px;
        }
        .ds-edit-form .ds-field-hint {
            font-size: 0.7rem;
            color: rgba(0, 0, 0, 0.45);
            margin-top: 2px;
            line-height: 1.3;
        }
        .ds-edit-form .ds-field-stack.v-text-field--outlined:not(.v-textarea) .v-input__control,
        .ds-edit-form .ds-field-stack.v-select--outlined .v-input__control,
        .ds-edit-form .ds-field-stack.v-autocomplete--outlined .v-input__control {
            min-height: 32px !important;
            height: 32px !important;
        }
        .ds-edit-form .ds-field-stack.v-text-field--outlined:not(.v-textarea) .v-input__slot,
        .ds-edit-form .ds-field-stack.v-select--outlined .v-input__slot,
        .ds-edit-form .ds-field-stack.v-autocomplete--outlined .v-input__slot {
            min-height: 32px !important;
            height: 32px !important;
        }
        .ds-edit-form .ds-field-stack.v-select--outlined .v-input__slot {
            padding: 0 6px 0 8px !important;
        }
        .ds-edit-form .ds-components-split {
            display: flex;
            align-items: flex-start;
            border: 1px solid rgba(0, 0, 0, 0.12);
            border-radius: 4px;
            overflow: hidden;
            background: #fafafa;
        }
        .ds-edit-form .ds-components-list {
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(0, 0, 0, 0.12);
            background: #fff;
            align-self: flex-start;
        }
        .ds-edit-form .ds-comp-list-search {
            flex-shrink: 0;
            padding: 12px 8px 4px;
        }
        .ds-edit-form .ds-comp-list-search .v-input {
            margin-top: 4px !important;
            margin-bottom: 0 !important;
        }
        .ds-edit-form .ds-comp-list-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
            padding: 4px 8px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.12);
            background: #fff;
        }
        .ds-edit-form .ds-comp-list-actions .v-input--selection-controls {
            margin-top: 0;
            padding-top: 0;
        }
        .ds-edit-form .ds-comp-list-actions-count {
            margin-left: auto;
            white-space: nowrap;
        }
        .ds-edit-form .ds-comp-list-item .v-input--selection-controls {
            margin-top: 0;
            padding-top: 0;
        }
        .ds-edit-form .ds-comp-list-scroll {
            flex: 0 1 auto;
            overflow-y: auto;
            max-height: min(420px, calc(100vh - 320px));
        }
        .ds-edit-form .ds-comp-list-item {
            padding: 5px 8px;
            cursor: pointer;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            font-size: 0.8125rem;
        }
        .ds-edit-form .ds-comp-list-item .ds-comp-list-name {
            font-size: 0.75rem;
            line-height: 1.25;
            font-weight: 500;
        }
        .ds-edit-form .ds-comp-list-item .ds-comp-list-label {
            font-size: 0.65rem;
            line-height: 1.25;
        }
        .ds-edit-form .ds-comp-list-item:hover {
            background: #f5f5f5;
        }
        .ds-edit-form .ds-comp-list-item--active {
            background: #e8eaf6 !important;
        }
        .ds-edit-form .ds-comp-list-item--dirty {
            border-left: 3px solid #ffb300;
        }
        .ds-edit-form .ds-components-detail {
            display: flex;
            flex-direction: column;
            background: #fff;
            min-width: 0;
        }
        .ds-edit-form .ds-components-detail-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.12);
            background: #f5f5f5;
        }
        .ds-edit-form .ds-comp-codelist-preview-search {
            flex: 0 1 220px;
            min-width: 160px;
            max-width: 260px;
        }
        .ds-edit-form .ds-comp-codelist-preview-search .v-input {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }
        .ds-edit-form .ds-comp-codelist-preview-table {
            max-height: 280px;
            overflow-y: auto;
            background: #fff;
            border-radius: 4px;
        }
        .ds-edit-form .ds-comp-codelist-preview-table-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .ds-edit-form .ds-comp-codelist-preview-table-grid th,
        .ds-edit-form .ds-comp-codelist-preview-table-grid td {
            font-size: 0.8125rem !important;
            padding: 6px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        .ds-edit-form .ds-comp-codelist-preview-table-grid th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #fafafa;
            font-weight: 600;
        }
        .ds-edit-form .ds-comp-codelist-preview-table-grid td code {
            font-size: 0.8125rem;
        }
        .ds-edit-form .ds-comp-codelist-preview-pager {
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }
        .ds-edit-form .ds-header-card-actions {
            border-top: 1px solid rgba(0, 0, 0, 0.12);
            justify-content: flex-end;
            gap: 8px;
        }
        .ds-csv-bootstrap .ds-csv-stepper .v-stepper__header {
            box-shadow: none;
        }
        .ds-csv-bootstrap-page {
            width: 100%;
        }
        .ds-csv-bootstrap-actions {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 1px solid rgba(0, 0, 0, 0.12);
            z-index: 2;
        }
        .ds-csv-mapping-wrap {
            width: 100%;
            border: 1px solid rgba(0, 0, 0, 0.12);
            border-radius: 4px;
        }
        .ds-csv-map-check-col {
            width: 44px;
            padding-left: 8px !important;
            padding-right: 4px !important;
        }
        .ds-csv-map-check-col .v-input--selection-controls {
            margin-top: 0;
            padding-top: 0;
        }
        .ds-csv-mapping-row--excluded {
            opacity: 0.55;
        }
        .ds-csv-mapping-row--excluded td {
            background: #fafafa;
        }
        .ds-csv-mapping-table th {
            white-space: nowrap;
            font-size: 0.75rem !important;
        }
        .ds-csv-mapping-table td {
            font-size: 0.8125rem;
            vertical-align: top;
        }
        .ds-csv-mapping-table td,
        .ds-csv-mapping-table th {
            padding: 3px 6px !important;
        }
        .ds-csv-map-col-role { min-width: 140px; max-width: 180px; }
        .ds-csv-map-col-name { min-width: 110px; max-width: 160px; }
        .ds-csv-map-col-codelist { min-width: 180px; }
        .ds-csv-map-stack {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .ds-csv-map-stack .text-caption {
            line-height: 1.2;
            margin-top: 0 !important;
        }
        .ds-csv-sample-cell {
            min-width: 100px;
            max-width: 220px;
            word-break: break-word;
        }
        .ds-csv-global-codelist-pick {
            gap: 2px;
            min-width: 0;
        }
        .ds-csv-global-codelist-field {
            flex: 1 1 auto;
            min-width: 0;
            cursor: pointer;
        }
        .ds-csv-global-codelist-field input {
            cursor: pointer;
        }
        .ds-csv-codelist-pick-table tbody tr {
            cursor: pointer;
        }
        .ds-csv-codelist-pick-table tbody tr:hover {
            background: #f5f5f5;
        }
        .ds-csv-codelist-pick-table tbody tr.ds-csv-codelist-pick-row--selected {
            background: #e3f2fd;
        }
        .ds-csv-codelist-pick-table th,
        .ds-csv-codelist-pick-table td {
            font-size: 0.8125rem !important;
        }
        .ds-csv-codelist-picker-search {
            overflow: visible;
            flex-shrink: 0;
        }
        .ds-csv-codelist-picker-search .v-input {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }
        .ds-csv-codelist-picker-count {
            margin-bottom: 12px;
        }
        .ds-csv-codelist-picker-card {
            display: flex;
            flex-direction: column;
            max-height: 85vh;
        }
        .ds-csv-codelist-picker-body {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            padding: 20px;
        }
        .ds-csv-codelist-picker-table-area {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }
        .ds-csv-codelist-picker-table-wrap {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 4px;
            background: #fff;
        }
        .ds-csv-codelist-picker-table-wrap .ds-csv-codelist-pick-table.v-data-table {
            box-shadow: none !important;
        }
        .ds-csv-codelist-picker-table-wrap .v-data-table__wrapper {
            overflow: visible !important;
        }
        .ds-csv-codelist-picker-table-wrap .ds-csv-codelist-pick-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fafafa;
        }
        .ds-copy-component-card {
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        .ds-copy-component-body {
            flex: 1 1 auto;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 16px 20px 8px;
            min-height: 0;
        }
        .ds-copy-component-search .v-input {
            margin-top: 0 !important;
        }
        .ds-copy-component-table-area {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .ds-copy-component-count {
            margin-bottom: 8px;
        }
        .ds-copy-component-table-wrap {
            flex: 1 1 auto;
            min-height: 200px;
            max-height: 52vh;
            overflow: auto;
        }
        .ds-copy-component-table-wrap .v-data-table__wrapper {
            overflow: visible !important;
        }
        .ds-copy-component-pick-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fafafa;
        }
        .ds-copy-component-pick-table tbody tr:hover {
            background: rgba(0, 0, 0, 0.04);
        }
        .ds-copy-component-pager {
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }
        .ds-csv-bootstrap .ds-csv-map-control.v-input,
        .ds-csv-bootstrap .ds-csv-step-control.v-input {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            font-size: 0.75rem;
        }
        .ds-csv-bootstrap .ds-csv-map-control.v-text-field--outlined:not(.v-textarea) .v-input__control,
        .ds-csv-bootstrap .ds-csv-map-control.v-select--outlined .v-input__control,
        .ds-csv-bootstrap .ds-csv-map-control.v-autocomplete--outlined .v-input__control,
        .ds-csv-bootstrap .ds-csv-step-control.v-text-field--outlined:not(.v-textarea) .v-input__control,
        .ds-csv-bootstrap .ds-csv-step-control.v-select--outlined .v-input__control {
            min-height: 26px !important;
            height: 26px !important;
        }
        .ds-csv-bootstrap .ds-csv-map-control:not(.v-textarea) .v-input__slot,
        .ds-csv-bootstrap .ds-csv-step-control:not(.v-textarea) .v-input__slot {
            min-height: 26px !important;
            height: 26px !important;
            font-size: 0.75rem;
        }
        .ds-csv-bootstrap .ds-csv-map-control.v-text-field--outlined:not(.v-textarea) .v-input__slot,
        .ds-csv-bootstrap .ds-csv-map-control.v-select--outlined .v-input__slot,
        .ds-csv-bootstrap .ds-csv-map-control.v-autocomplete--outlined .v-input__slot,
        .ds-csv-bootstrap .ds-csv-step-control.v-text-field--outlined:not(.v-textarea) .v-input__slot,
        .ds-csv-bootstrap .ds-csv-step-control.v-select--outlined .v-input__slot {
            min-height: 26px !important;
            height: 26px !important;
            padding: 0 4px 0 6px !important;
        }
        .ds-csv-bootstrap .ds-csv-map-control.v-select--outlined .v-input__slot,
        .ds-csv-bootstrap .ds-csv-map-control.v-autocomplete--outlined .v-input__slot,
        .ds-csv-bootstrap .ds-csv-step-control.v-select--outlined .v-input__slot {
            padding: 0 2px 0 6px !important;
        }
        .ds-csv-bootstrap .ds-csv-map-control input,
        .ds-csv-bootstrap .ds-csv-map-control .v-select__selection,
        .ds-csv-bootstrap .ds-csv-map-control .v-select__selection--comma,
        .ds-csv-bootstrap .ds-csv-step-control input,
        .ds-csv-bootstrap .ds-csv-step-control .v-select__selection {
            font-size: 0.75rem;
            line-height: 1.2;
        }
        .ds-csv-bootstrap .ds-csv-map-control.v-select .v-select__selections,
        .ds-csv-bootstrap .ds-csv-map-control.v-autocomplete .v-select__selections,
        .ds-csv-bootstrap .ds-csv-step-control.v-select .v-select__selections {
            min-height: 26px !important;
            max-height: 26px !important;
            height: 26px !important;
            padding: 0 !important;
            align-items: center;
        }
        .ds-csv-bootstrap .ds-csv-map-control.v-select .v-select__selection,
        .ds-csv-bootstrap .ds-csv-map-control.v-select .v-select__selection--comma,
        .ds-csv-bootstrap .ds-csv-step-control.v-select .v-select__selection {
            line-height: 26px !important;
            max-height: 26px !important;
            margin: 0 !important;
        }
        .ds-csv-bootstrap .ds-csv-map-control .v-input__append-inner,
        .ds-csv-bootstrap .ds-csv-map-control .v-input__prepend-inner,
        .ds-csv-bootstrap .ds-csv-step-control .v-input__append-inner {
            margin-top: 0 !important;
            padding-top: 0 !important;
            align-self: center;
        }
        .ds-csv-bootstrap .ds-csv-map-control .v-input__append-inner .v-icon,
        .ds-csv-bootstrap .ds-csv-step-control .v-input__append-inner .v-icon {
            font-size: 16px !important;
        }
        .ds-csv-bootstrap .ds-csv-map-control fieldset,
        .ds-csv-bootstrap .ds-csv-step-control fieldset {
            top: 0 !important;
        }
        .ds-csv-bootstrap .ds-csv-map-control .v-label,
        .ds-csv-bootstrap .ds-csv-step-control .v-label {
            font-size: 0.75rem;
        }
        .ds-csv-bootstrap .ds-csv-step-control.v-file-input .v-input__control,
        .ds-csv-bootstrap .ds-csv-step-control.v-file-input:not(.v-textarea) .v-input__slot {
            min-height: 36px !important;
            height: auto !important;
        }
        .ds-csv-payload-preview {
            font-size: 0.7rem;
            line-height: 1.35;
            max-height: 240px;
            overflow: auto;
            background: #f5f5f5;
            padding: 12px;
            border-radius: 4px;
            margin: 0;
        }
    </style>
</head>
<body class="layout-top-nav">
    <script>
        var CI = {
            site_url: '<?php echo site_url(); ?>',
            base_url: '<?php echo base_url(); ?>',
            user_info: <?php echo json_encode($user_info); ?>
        };
    </script>
    <div id="app" data-app>
        <div class="wrapper">
            <v-app>
            <vue-global-site-header></vue-global-site-header>
            <v-login v-model="login_dialog"></v-login>
            <v-container fluid class="pa-4">
                <div class="mb-4">
                    <main-navigation-tabs active-tab="data_structures" v-model="navTabsModel"></main-navigation-tabs>
                </div>
                <router-view></router-view>
                <v-toast></v-toast>
            </v-container>
            </v-app>
        </div>
    </div>
    <script src="<?php echo base_url();?>vue-app/assets/vue.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/vue-router.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/axios.min.js"></script>
    <script src="<?php echo base_url();?>vue-app/assets/vuetify.min.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/session_channel.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/global-session-handler.js"></script>
    <script src="<?php echo base_url(); ?>vue-app/assets/global-login-plugin.js"></script>
    <script>
        window.EventBus = new Vue();
        <?php
            echo $this->load->view('metadata_editor/vue-login-component.js', null, true);
            echo $this->load->view('metadata_editor/vue-toast-component.js', null, true);
            echo $this->load->view('metadata_editor/vue-data-structures-component.js', null, true);
            echo $this->load->view('metadata_editor/vue-data-structure-projects-component.js', null, true);
            echo $this->load->view('metadata_editor/vue-data-structure-validation-panel-component.js', null, true);
            echo $this->load->view('metadata_editor/vue-data-structure-view-component.js', null, true);
            echo $this->load->view('metadata_editor/vue-data-structure-edit-component.js', null, true);
            echo $this->load->view('metadata_editor/vue-data-structure-csv-bootstrap-component.js', null, true);
            echo $this->load->view('editor_common/global-site-header-component.js', null, true);
            echo $this->load->view('editor_common/main-navigation-tabs-component.js', null, true);
        ?>
        const DataStructuresList = { template: '<div><data-structures></data-structures></div>' };
        const DataStructureView = { template: '<div><data-structure-view :id="$route.params.id"></data-structure-view></div>', props: true };
        const DataStructureEdit = {
            template: '<div><data-structure-edit :id="$route.params.id"></data-structure-edit></div>',
            props: true
        };
        const DataStructureProjects = {
            template: '<div><data-structure-projects :id="$route.params.id"></data-structure-projects></div>',
            props: true
        };
        const DataStructureCsvBootstrap = {
            template: '<div><data-structure-csv-bootstrap :id="$route.params.id"></data-structure-csv-bootstrap></div>',
            props: true
        };
        const routes = [
            { path: '/', component: DataStructuresList, name: 'ds-list' },
            { path: '/view/:id', component: DataStructureView, name: 'ds-view', props: true },
            { path: '/projects/:id', component: DataStructureProjects, name: 'ds-projects', props: true },
            { path: '/edit', component: DataStructureEdit, name: 'ds-create', props: { id: null } },
            { path: '/edit/:id/bootstrap-csv', component: DataStructureCsvBootstrap, name: 'ds-bootstrap-csv', props: true },
            { path: '/edit/:id', component: DataStructureEdit, name: 'ds-edit', props: true }
        ];
        const router = new VueRouter({ routes });
        const translation_messages = { default: <?php echo isset($translations) ? json_encode($translations, JSON_HEX_APOS) : '{}';?> };
        const i18n = new VueI18n({ locale: 'default', messages: translation_messages });
        const vuetify = new Vuetify({ theme: { themes: { light: { primary: '#526bc7', "primary-dark": '#0c1a4d' } } } });
        if (typeof GlobalLoginPlugin !== 'undefined') { Vue.use(GlobalLoginPlugin); }
        new Vue({
            el: '#app',
            i18n: i18n,
            router: router,
            vuetify: vuetify,
            data: { login_dialog: false, navTabsModel: 6 }
        });
    </script>
    <?php $this->load->view('common/analytics'); ?>
</body>
</html>
