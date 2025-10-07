<!-- Include Vue.js and Vuetify -->
<link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
<link href="<?php echo base_url(); ?>vue-app/assets/mdi.min.css" rel="stylesheet">
<link href="<?php echo base_url(); ?>vue-app/assets/vuetify.min.css" rel="stylesheet">

<style>
    .audit-logs-component {
        height: calc(100vh - 160px);
        overflow: hidden;
        margin-top: 20px;
        padding-top: 20px;
    }
    
    .audit-logs-component .v-card {
        border-radius: 8px;
    }
    
    .audit-logs-component .sidebar-filters {
        background-color: #f8f9fa;
        border-right: 1px solid #e0e0e0;
    }
    
    .audit-logs-component .sidebar-filters .v-card-title {
        background-color: #e3f2fd;
        border-bottom: 1px solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    
    .audit-logs-component .sidebar-filters .v-card-text {
        padding-bottom: 16px;
    }
    
    .audit-logs-component .v-data-table {
        border-radius: 8px;
    }
    
    .audit-logs-component .metadata-display {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        line-height: 1.4;
    }
    
    /* Ensure proper scrolling behavior */
    .audit-logs-component .v-data-table__wrapper {
        overflow-y: auto !important;
    }
    
    /* Make table rows clickable */
    .audit-logs-component .v-data-table tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    
    .audit-logs-component .v-data-table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.04) !important;
    }
    
    .audit-logs-component .v-data-table tbody tr.v-data-table__expanded__content {
        cursor: default;
    }
    
    /* Disable hover effects on detailed section tables */
    .audit-logs-component .details-table tbody tr:hover {
        background-color: transparent !important;
    }
    
    .audit-logs-component .details-table tbody tr {
        background-color: transparent !important;
    }
    
    .audit-logs-component .details-table tbody tr:nth-child(even) {
        background-color: transparent !important;
    }
    
    .audit-logs-component .details-table tbody tr:nth-child(odd) {
        background-color: transparent !important;
    }
    
    /* Allow expanded content to show all content */
    .audit-logs-component .v-data-table__expanded__content {
        height: auto !important;
        max-height: none !important;
    }
    
    @media (max-width: 960px) {
        .audit-logs-component {
            height: auto;
        }
        
        .audit-logs-component .sidebar-filters {
            margin-bottom: 16px;
            max-height: none !important;
        }
        
        .audit-logs-component .v-card {
            max-height: none !important;
        }
    }
</style>

<script src="<?php echo base_url(); ?>vue-app/assets/vue.min.js"></script>
<script src="<?php echo base_url(); ?>vue-app/assets/vuetify.min.js"></script>
<script src="<?php echo base_url(); ?>vue-app/assets/vue-i18n.min.js"></script>
<script src="<?php echo base_url(); ?>vue-app/assets/moment-with-locales.min.js"></script>

<div id="app" data-app>
    <div class="audit-logs-admin">
        <vue-audit-logs-component></vue-audit-logs-component>
    </div>
</div>

<script>
    // Set up CI base URL
    var CI = {
        'base_url': '<?php echo site_url(); ?>',
        'site_url': '<?php echo site_url(); ?>'
    };

    // Include the Vue component
    <?php include_once("vue-audit-logs-component.js"); ?>

    // Set up translations
    const translation_messages = {
        default: {
            'audit_logs': '<?php echo t('audit_logs'); ?>',
            'date_time': '<?php echo t('date_time'); ?>',
            'user': '<?php echo t('user'); ?>',
            'object_type': '<?php echo t('object_type'); ?>',
            'object_id': '<?php echo t('object_id'); ?>',
            'action': '<?php echo t('action'); ?>',
            'actions': '<?php echo t('actions'); ?>',
            'action_type': '<?php echo t('action_type'); ?>',
            'object_reference_id': '<?php echo t('object_reference_id'); ?>',
            'metadata': '<?php echo t('metadata'); ?>',
            'details': '<?php echo t('details'); ?>',
            'user_email': '<?php echo t('user_email'); ?>',
            'system': '<?php echo t('system'); ?>',
            'filters': '<?php echo t('filters'); ?>',
            'active': '<?php echo t('active'); ?>',
            'apply_filters': '<?php echo t('apply_filters'); ?>',
            'clear_filters': '<?php echo t('clear_filters'); ?>',
            'refresh': '<?php echo t('refresh'); ?>',
            'view_details': '<?php echo t('view_details'); ?>',
            'no_audit_logs_found': '<?php echo t('no_audit_logs_found'); ?>',
            'try_adjusting_filters': '<?php echo t('try_adjusting_filters'); ?>',
            'showing_logs_range': '<?php echo t('showing_logs_range'); ?>',
            'logs_refreshed': '<?php echo t('logs_refreshed'); ?>',
            'error_loading_logs': '<?php echo t('error_loading_logs'); ?>',
            'loading_details': '<?php echo t('loading_details'); ?>',
            'error_loading_details': '<?php echo t('error_loading_details'); ?>',
            'retry': '<?php echo t('retry'); ?>'
        }
    };

    const i18n = new VueI18n({
        locale: 'default',
        messages: translation_messages,
    });

    // Set up Vuetify
    const vuetify = new Vuetify({
        theme: {
            themes: {
                light: {
                    primary: '#526bc7',
                    secondary: '#b0bec5',
                    accent: '#8c9eff',
                    error: '#b71c1c',
                },
            },
        },
    });

    // Initialize Vue app
    new Vue({
        el: "#app",
        i18n,
        vuetify: vuetify,
        data() {
            return {
                // App data if needed
            }
        }
    });
</script>
