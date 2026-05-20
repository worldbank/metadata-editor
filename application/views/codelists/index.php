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
        $user=$this->session->userdata('username');
        $this->load->library('Editor_acl');
        
        $has_schema_permission = false;
        $has_codelist_permission = false;
        $has_data_structure_permission = false;
        try {
            $has_schema_permission = $this->editor_acl->has_access('schema', 'view');
        } catch (Exception $e) {
            $has_schema_permission = false;
        }
        try {
            $has_codelist_permission = $this->editor_acl->has_access('codelist', 'view');
        } catch (Exception $e) {
            $has_codelist_permission = false;
        }
        try {
            $has_data_structure_permission = $this->editor_acl->has_access('data_structure', 'view');
        } catch (Exception $e) {
            $has_data_structure_permission = false;
        }

        $user_info=[
            'username'=> $user,
            'is_logged_in'=> !empty($user),
            'is_admin'=> $this->ion_auth->is_admin(),
            'can_access_site_admin'=> $this->ion_auth->can_access_site_admin(),
            'has_schema_permission'=> $has_schema_permission,
            'has_codelist_permission'=> $has_codelist_permission,
            'has_data_structure_permission'=> $has_data_structure_permission,
        ];
        
        ?>
    
    <style>
        .capitalize {
            text-transform: capitalize;
        }

        .text-small {
            font-size: 0.8rem;
        }

        .btn-xs {
            padding: 0.25rem;
            font-size: 0.7rem;
            line-height: 1.4;
            border-radius: 0.2rem;
        }

        .v-tabs-bar{
            background-color: transparent!important;
            margin-bottom:50px;
        }

        /* Items table: rowspan cells must top-align or inputs sit centered between rows */
        .codelist-edit-tables .items-grid tbody td {
            vertical-align: top;
        }

        /* Compact inputs in codelist translation / item tables (headers only in <th>) */
        .codelist-edit-tables .cl-compact-control.v-input {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        .codelist-edit-tables .cl-compact-control.v-text-field--outlined .v-input__control,
        .codelist-edit-tables .cl-compact-control.v-select--outlined .v-input__control {
            min-height: 28px;
        }
        .codelist-edit-tables .cl-compact-control .v-input__slot {
            min-height: 28px !important;
            font-size: 0.8125rem;
        }
        .codelist-edit-tables .cl-compact-control.v-text-field--outlined .v-input__slot {
            min-height: 28px !important;
            height: 28px !important;
        }
        .codelist-edit-tables .cl-compact-control.v-select--outlined .v-input__slot {
            min-height: 28px !important;
            height: 28px !important;
            padding: 0 4px 0 8px !important;
        }
        .codelist-edit-tables .cl-compact-control.v-select .v-select__selections {
            min-height: 28px !important;
            padding: 0 !important;
        }
        .codelist-edit-tables .cl-compact-control.v-select .v-select__selection,
        .codelist-edit-tables .cl-compact-control.v-select .v-select__selection--comma {
            line-height: 28px;
            max-height: 28px;
        }
        .codelist-edit-tables .cl-compact-control.v-select .v-input__append-inner {
            margin-top: 0 !important;
            padding-top: 0 !important;
            align-self: center;
        }
        .codelist-edit-tables .cl-compact-control.v-select .v-input__append-inner .v-icon {
            font-size: 18px !important;
        }
        .codelist-edit-tables .cl-compact-control input,
        .codelist-edit-tables .cl-compact-control .v-select__selection {
            font-size: 0.8125rem;
        }

        .codelist-view-pre {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .codelist-view-items .v-data-table__wrapper td {
            vertical-align: top;
            word-break: break-word;
        }
    </style>
</head>

<body class="layout-top-nav">

    <script>
       var CI = {
        'site_url': '<?php echo site_url(); ?>',
        'base_url': '<?php echo base_url(); ?>',
        'user_info': <?php echo json_encode($user_info); ?>
        };
        window.ISO_LANGUAGES = <?php echo json_encode(isset($iso_languages) ? $iso_languages : array(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    </script>

    <div id="app" data-app>
        <div class="wrapper">
            <v-app>
            <?php //echo $this->load->view('editor_common/global-header', null, true); ?>
            <vue-global-site-header></vue-global-site-header>
            <v-login v-model="login_dialog"></v-login>
            
            <v-container fluid class="pa-4">
                <div class="mb-4">
                    <main-navigation-tabs active-tab="codelists" v-model="navTabsModel"></main-navigation-tabs>
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
        // Initialize EventBus
        window.EventBus = new Vue();

        <?php
            echo $this->load->view("metadata_editor/vue-login-component.js", null, true);
            echo $this->load->view("metadata_editor/vue-toast-component.js", null, true);
            echo $this->load->view("metadata_editor/vue-codelists-component.js", null, true);
            echo $this->load->view("metadata_editor/vue-codelist-edit-component.js", null, true);
            echo $this->load->view("metadata_editor/vue-codelist-view-component.js", null, true);
            echo $this->load->view("editor_common/global-site-header-component.js", null, true);
            echo $this->load->view("editor_common/main-navigation-tabs-component.js", null, true);
        ?>

        // Define route components
        const CodelistsList = {
            template: '<div><codelists></codelists></div>'
        }

        const CodelistEdit = {
            template: '<div><codelist-edit :id="$route.params.id"></codelist-edit></div>'
        }

        const CodelistView = {
            template: '<div><codelist-view :id="$route.params.id"></codelist-view></div>'
        }

        // Define routes
        const routes = [
            { 
                path: '/', 
                component: CodelistsList, 
                name: 'codelists-list'
            },
            {
                path: '/view/:id',
                component: CodelistView,
                name: 'codelist-view',
                props: true
            },
            { 
                path: '/edit', 
                component: CodelistEdit, 
                name: 'codelist-create',
                props: { id: null }
            },
            { 
                path: '/edit/:id', 
                component: CodelistEdit, 
                name: 'codelist-edit',
                props: true
            }
        ]

        // Create router
        const router = new VueRouter({
            routes
        })

        const translation_messages = {
            default: <?php echo isset($translations) ? json_encode($translations,JSON_HEX_APOS) : '{}';?>
        }

        const i18n = new VueI18n({
            locale: 'default', // set locale
            messages: translation_messages, // set locale messages
        })

        const vuetify = new Vuetify({
            theme: {
            themes: {
                light: {
                    primary: '#526bc7',
                    "primary-dark": '#0c1a4d',
                    secondary: '#b0bec5',
                    accent: '#8c9eff',
                    error: '#b71c1c',
                },
            },
            },
        })

        // Use GlobalLoginPlugin for session handling
        if (typeof GlobalLoginPlugin !== 'undefined') {
            Vue.use(GlobalLoginPlugin);
        }

        vue_app = new Vue({
            el: '#app',
            i18n: i18n,
            router: router,
            vuetify: vuetify,
            data: {
                login_dialog: false,
                navTabsModel: 5
            },
            methods: {
                // Event handlers for codelist actions
                handleCodelistEdit: function(codelist) {
                    this.$router.push('/edit/' + codelist.id);
                },
                handleCodelistView: function(codelist) {
                    this.$router.push('/view/' + codelist.id);
                },
                handleCodelistCodes: function(codelist) {
                    this.$router.push('/edit/' + codelist.id + '#codes');
                },
                handleCodelistCreate: function() {
                    this.$router.push('/edit');
                },
                handleCodelistImport: function() {
                    var router = this.$router;
                    var open = function() {
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('codelist-sdmx-open');
                        }
                    };
                    if (router && router.currentRoute && router.currentRoute.path !== '/') {
                        router.push('/').then(function () {
                            Vue.nextTick(open);
                        });
                    } else {
                        open();
                    }
                },
                handleCodelistExport: function(codelist) {
                    if (!codelist || !codelist.id) {
                        return;
                    }
                    var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '';
                    window.open(base + '/api/codelists/export_json/' + codelist.id + '?download=1', '_blank');
                }
            },
            created: function() {
                // Listen for codelist events
                if (typeof EventBus !== 'undefined') {
                    EventBus.$on('codelist-edit', this.handleCodelistEdit);
                    EventBus.$on('codelist-view', this.handleCodelistView);
                    EventBus.$on('codelist-codes', this.handleCodelistCodes);
                    EventBus.$on('codelist-create', this.handleCodelistCreate);
                    EventBus.$on('codelist-import', this.handleCodelistImport);
                    EventBus.$on('codelist-export', this.handleCodelistExport);
                }
            },
            beforeDestroy: function() {
                // Clean up event listeners
                if (typeof EventBus !== 'undefined') {
                    EventBus.$off('codelist-edit', this.handleCodelistEdit);
                    EventBus.$off('codelist-view', this.handleCodelistView);
                    EventBus.$off('codelist-codes', this.handleCodelistCodes);
                    EventBus.$off('codelist-create', this.handleCodelistCreate);
                    EventBus.$off('codelist-import', this.handleCodelistImport);
                    EventBus.$off('codelist-export', this.handleCodelistExport);
                }
            }
        })
    </script>

    <?php $this->load->view('common/analytics'); ?>
</body>

</html>
