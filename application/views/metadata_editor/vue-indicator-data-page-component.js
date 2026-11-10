/**
 * Indicator project data hub: browse published rows; import via button (no tabs).
 */
Vue.component('indicator-data-page', {
    props: {
        file_id: { type: String, default: 'INDICATOR_DATA' }
    },
    data: function() {
        return {
            showImport: false,
            binding: null,
            bindingLoading: true,
            hasPublishedData: false,
            showNotesImport: false,
            /** data_notes report from the last data import (notes read from the data CSV + checks) */
            dataImportNotes: null
        };
    },
    watch: {
        '$route.query.tab': function() {
            this.syncViewFromRoute();
        }
    },
    created: function() {
        this.loadBinding();
    },
    methods: {
        loadBinding: function() {
            var vm = this;
            var sid = vm.$store.state.project_id;
            vm.bindingLoading = true;
            axios.get(CI.base_url + '/api/indicator_dsd/binding/' + sid)
                .then(function(res) {
                    vm.binding = res.data || {};
                })
                .catch(function() {
                    vm.binding = { bound: false, column_count: 0 };
                })
                .then(function() {
                    vm.bindingLoading = false;
                    vm.syncViewFromRoute();
                });
        },
        syncViewFromRoute: function() {
            this.showImport = this.$route.query.tab === 'import';
        },
        showImportView: function() {
            this.showImport = true;
            if (this.$route.query.tab !== 'import') {
                this.$router.replace({
                    path: this.$route.path,
                    query: Object.assign({}, this.$route.query, { tab: 'import' })
                }).catch(function() {});
            }
        },
        showBrowseView: function() {
            this.showImport = false;
            var q = Object.assign({}, this.$route.query);
            delete q.tab;
            this.$router.replace({ path: this.$route.path, query: q }).catch(function() {});
        },
        onImported: function(result) {
            var vm = this;
            vm.dataImportNotes = result && result.data_notes ? result.data_notes : null;
            vm.loadBinding();
            vm.showBrowseView();
            vm.$nextTick(function() {
                var explorer = vm.$refs.explorer;
                if (explorer && typeof explorer.loadPage === 'function') {
                    explorer.loadPage(0);
                }
            });
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onSuccess', 'Indicator data imported');
            }
        },
        onNotesImported: function() {
            this.dataImportNotes = null;
            var explorer = this.$refs.explorer;
            if (explorer && typeof explorer.loadNotes === 'function') {
                explorer.loadNotes();
            }
        },
        onExplorerDataState: function(state) {
            this.hasPublishedData = !!(state && state.hasData);
        },
        exportData: function() {
            var explorer = this.$refs.explorer;
            if (explorer && typeof explorer.exportCsv === 'function') {
                explorer.exportCsv();
            }
        },
        deleteData: function() {
            var explorer = this.$refs.explorer;
            if (explorer && typeof explorer.confirmDeleteAllData === 'function') {
                explorer.confirmDeleteAllData();
            }
        }
    },
    template: `
        <div class="pa-4 mt-5 indicator-data-page" style="max-width: 100%; background:#fff; margin:10px; border-radius:4px;">

            <template v-if="showImport">
                <div class="d-flex align-center mb-3" style="gap:8px;">
                    <v-btn text small class="px-0" @click="showBrowseView">
                        <v-icon left small>mdi-arrow-left</v-icon>
                        {{ $t('back') || 'Back' }}
                    </v-btn>
                </div>
                <h4 class="text-h6 font-weight-medium mb-1">{{ $t('import_indicator_data') || 'Import indicator data' }}</h4>
                <p class="caption grey--text mb-4">
                    Upload a CSV whose headers match the bound data structure, then choose which indicator ID to import.
                </p>
                <indicator-dsd-import
                    embedded
                    @imported="onImported"
                ></indicator-dsd-import>
            </template>

            <template v-else>
                <div class="d-flex flex-wrap align-start justify-space-between mb-4" style="gap:12px;">
                    <div style="flex:1 1 200px; min-width:0;">
                        <h4 class="text-h6 font-weight-medium mb-0">{{ $t('indicator_data') || 'Data' }}</h4>
                    </div>
                    <div class="d-flex flex-wrap justify-end align-center flex-shrink-0" style="gap:8px;">
                        <v-btn color="primary" small :disabled="bindingLoading" @click="showImportView">
                            <v-icon left small>mdi-upload</v-icon>
                            {{ $t('import_indicator_data') || 'Import data' }}
                        </v-btn>
                        <v-btn color="primary" outlined small :disabled="bindingLoading" @click="showNotesImport = true">
                            <v-icon left small>mdi-note-text-outline</v-icon>
                            {{ $t('import_data_notes') || 'Import notes' }}
                        </v-btn>
                        <v-btn
                            v-if="hasPublishedData"
                            color="primary"
                            outlined
                            small
                            :disabled="bindingLoading"
                            @click="exportData"
                        >
                            <v-icon left small>mdi-download</v-icon>
                            {{ $t('export_csv') || 'Download data' }}
                        </v-btn>
                        <v-btn
                            v-if="hasPublishedData"
                            color="error"
                            outlined
                            small
                            :disabled="bindingLoading"
                            @click="deleteData"
                        >
                            <v-icon left small>mdi-delete</v-icon>
                            {{ $t('delete_data') || 'Delete data' }}
                        </v-btn>
                    </div>
                </div>

                <div v-if="dataImportNotes" class="mb-3" style="position:relative;">
                    <indicator-notes-report
                        :summary="dataImportNotes.from_data_csv"
                        :checks="dataImportNotes.checks"
                        :notes-count="typeof dataImportNotes.notes_count === 'number' ? dataImportNotes.notes_count : null"
                        :warning="dataImportNotes.warning || ''"
                        :generated="dataImportNotes.generated_ids || null"
                        source="data_csv"
                    ></indicator-notes-report>
                    <v-btn icon x-small style="position:absolute; top:6px; right:6px;" @click="dataImportNotes = null" title="Close">
                        <v-icon x-small>mdi-close</v-icon>
                    </v-btn>
                </div>

                <v-progress-linear v-if="bindingLoading" indeterminate color="primary" class="mb-3"></v-progress-linear>
                <indicator-timeseries-data-explorer
                    v-if="!bindingLoading"
                    ref="explorer"
                    :file_id="file_id"
                    embedded
                    hide-toolbar
                    @data-state="onExplorerDataState"
                    @go-import="showImportView"
                ></indicator-timeseries-data-explorer>
            </template>

            <indicator-notes-import v-model="showNotesImport" @imported="onNotesImported"></indicator-notes-import>
        </div>
    `
});
