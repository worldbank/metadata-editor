Vue.component('indicator-dsd-overview', {
    data() {
        return {
            dataset_id: project_sid,
            loading: false,
            componentsLoading: false,
            error: null,
            report: null,
            binding: null,
            components: [],
            bindGlobalDialog: false,
            globalStructuresList: [],
            globalStructuresLoading: false,
            bindStructureId: null,
            bindingGlobal: false,
            removeDialog: false,
            removing: false,
            isValidating: false,
            compHeaders: [
                { text: 'Order', value: 'sort_order', width: '72px' },
                { text: 'Name', value: 'name' },
                { text: 'Label', value: 'label' },
                { text: 'Column type', value: 'column_type' },
                { text: 'Data type', value: 'data_type' },
                { text: 'Codelist', value: 'codelist_ref' }
            ]
        };
    },

    mounted() {
        this.loadAll();
    },

    computed: {
        structure() {
            return this.report && this.report.structure ? this.report.structure : null;
        },
        summary() {
            return this.structure && this.structure.summary ? this.structure.summary : null;
        },
        totalColumns() {
            if (this.binding && this.binding.column_count) {
                return this.binding.column_count;
            }
            return this.summary ? (this.summary.total_columns || 0) : 0;
        },
        structureValid() {
            return this.structure ? this.structure.valid : null;
        },
        structureErrors() {
            return this.structure ? (this.structure.errors || []) : [];
        },
        structureWarnings() {
            return this.structure ? (this.structure.warnings || []) : [];
        },
        hasValidationIssues() {
            if (this.structureErrors.length > 0 || this.structureWarnings.length > 0) {
                return true;
            }
            var dv = this.dataValidation;
            if (this.hasPublishedData && dv && !dv.skipped) {
                if (Array.isArray(dv.errors) && dv.errors.length > 0) {
                    return true;
                }
                if (Array.isArray(dv.warnings) && dv.warnings.length > 0) {
                    return true;
                }
            }
            return false;
        },
        hasPublishedData() {
            return !!(this.binding && this.binding.has_published_data);
        },
        dataValidation() {
            return (this.report && this.report.data_validation) ? this.report.data_validation : null;
        },
        overallValid() {
            return this.report ? this.report.valid : null;
        },
        isBound() {
            return !!(this.binding && this.binding.bound);
        },
        globalStructure() {
            return (this.binding && this.binding.global_structure) ? this.binding.global_structure : null;
        },
        globalStructureTitle() {
            var ds = this.globalStructure;
            if (!ds) {
                return 'Data structure';
            }
            return ds.title || ds.name || ds.idno || 'Data structure';
        },
        globalStructureDetailUrl() {
            var ds = this.globalStructure;
            if (!ds || !ds.id) {
                return null;
            }
            var root = (typeof CI !== 'undefined' && CI.base_url) ? String(CI.base_url).replace(/\/?$/, '') : '';
            return root + '/data_structures#/view/' + parseInt(ds.id, 10);
        },
        canEditProjectDsd() {
            if (typeof this.$store === 'undefined' || !this.$store.getters.getUserHasEditAccess) {
                return false;
            }
            return this.$store.getters.getUserHasEditAccess && !this.$store.state.project_is_locked;
        },
        canRemoveStructure() {
            return this.canEditProjectDsd && (this.isBound || this.totalColumns > 0);
        },
        removeConfirmMessage() {
            return 'Remove the data structure from this project?';
        }
    },

    methods: {
        async loadAll() {
            this.loading = true;
            this.error = null;
            try {
                await Promise.all([this.loadReport(), this.loadBinding()]);
                await this.loadComponents();
            } catch (e) {
                this.error = (e.response && e.response.data && e.response.data.message) || e.message || 'Could not load DSD summary';
            } finally {
                this.loading = false;
            }
        },

        async loadReport() {
            this.isValidating = true;
            try {
                const res = await axios.get(CI.base_url + '/api/indicator_dsd/validate/' + this.dataset_id);
                this.report = res.data || null;
            } finally {
                this.isValidating = false;
            }
        },

        refreshValidation() {
            return this.loadReport();
        },

        formatObservationKeyColumns(cols) {
            if (!cols || !cols.length) {
                return '';
            }
            return cols.map(function(kc) {
                var nm = (kc && kc.dsd_name) ? String(kc.dsd_name) : '';
                var tp = (kc && kc.column_type) ? String(kc.column_type) : '';
                return tp ? (nm + ' (' + tp + ')') : nm;
            }).join(', ');
        },

        async loadBinding() {
            try {
                const res = await axios.get(CI.base_url + '/api/indicator_dsd/binding/' + this.dataset_id);
                this.binding = res.data || {};
            } catch (e) {
                this.binding = { bound: false, column_count: 0 };
            }
        },

        async loadComponents() {
            this.components = [];
            this.componentsLoading = true;
            try {
                var ds = this.globalStructure;
                if (ds && ds.id) {
                    const res = await axios.get(
                        CI.base_url + '/api/data_structures/single/' + parseInt(ds.id, 10) + '?with_components=1'
                    );
                    if (res.data && res.data.status === 'success' && res.data.data_structure) {
                        var structure = res.data.data_structure;
                        this.components = (structure.components || []).map(function(c) {
                            var ref = '';
                            if (c.codelist_reference && c.codelist_reference.idno) {
                                ref = c.codelist_reference.idno;
                            } else if (c.codelist_reference && c.codelist_reference.name) {
                                ref = (c.codelist_reference.agency || '') + ':' + c.codelist_reference.name;
                            } else if (c.codelist_type && c.codelist_type !== 'none') {
                                ref = c.codelist_type;
                            }
                            return Object.assign({}, c, { codelist_ref: ref });
                        });
                        return;
                    }
                }

                if (this.totalColumns > 0) {
                    const res = await axios.get(CI.base_url + '/api/indicator_dsd/' + this.dataset_id, {
                        params: { detailed: 1 }
                    });
                    var cols = Array.isArray(res.data && res.data.columns) ? res.data.columns : [];
                    this.components = cols.map(function(c, idx) {
                        var ref = '';
                        if (c.codelist_type === 'global' && c.global_codelist_id) {
                            ref = 'registry #' + c.global_codelist_id;
                        }
                        return {
                            sort_order: c.sort_order != null ? c.sort_order : (idx + 1),
                            name: c.name || '',
                            label: c.label || '',
                            column_type: c.column_type || '',
                            data_type: c.data_type || '',
                            codelist_ref: ref
                        };
                    });
                }
            } catch (e) {
                // Non-fatal: validation summary still useful without component rows
            } finally {
                this.componentsLoading = false;
            }
        },

        openBindGlobalDialog() {
            var vm = this;
            vm.bindStructureId = null;
            vm.bindGlobalDialog = true;
            vm.globalStructuresLoading = true;
            axios.get(CI.base_url + '/api/data_structures', { params: { page: 1, per_page: 200 } })
                .then(function(res) {
                    var data = res.data || {};
                    vm.globalStructuresList = Array.isArray(data.data_structures) ? data.data_structures : [];
                })
                .catch(function() {
                    vm.globalStructuresList = [];
                })
                .then(function() {
                    vm.globalStructuresLoading = false;
                });
        },

        submitBindGlobal() {
            var vm = this;
            if (!vm.bindStructureId) {
                return;
            }
            vm.bindingGlobal = true;
            axios.post(CI.base_url + '/api/indicator_dsd/bind_global/' + vm.dataset_id, {
                data_structure_id: parseInt(vm.bindStructureId, 10)
            })
                .then(function() {
                    vm.bindingGlobal = false;
                    vm.bindGlobalDialog = false;
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', vm.isBound ? 'Data structure updated' : 'Data structure attached');
                    }
                    if (typeof vm.$store !== 'undefined') {
                        vm.$store.dispatch('loadProject', { dataset_id: vm.dataset_id });
                    }
                    return vm.loadAll();
                })
                .catch(function(err) {
                    vm.bindingGlobal = false;
                    var m = (err.response && err.response.data && err.response.data.message) || 'Attach failed';
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', m);
                    }
                });
        },

        submitRemoveStructure() {
            var vm = this;
            vm.removing = true;
            axios.post(CI.base_url + '/api/indicator_dsd/unbind/' + vm.dataset_id)
                .then(function(res) {
                    vm.removing = false;
                    vm.removeDialog = false;
                    var d = res.data || {};
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', 'Data structure removed');
                        if (Array.isArray(d.warnings) && d.warnings.length) {
                            EventBus.$emit('onFail', d.warnings.join(' '));
                        }
                    }
                    return vm.loadAll();
                })
                .catch(function(err) {
                    vm.removing = false;
                    var m = (err.response && err.response.data && err.response.data.message) || 'Remove failed';
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', m);
                    }
                });
        },

        globalStructureLabel(ds) {
            if (!ds) {
                return '';
            }
            var t = ds.title || ds.name || '';
            var id = ds.idno || ((ds.agency || '') + ':' + (ds.name || ''));
            return t + ' (' + id + ')';
        },

        formatNumber(n) {
            if (n == null) return '—';
            return Number(n).toLocaleString();
        }
    },

    template: `
<div class="pa-4 indicator-dsd-overview" style="max-width: 100%; background:#fff; margin:10px; border-radius:4px;">

    <h4 class="text-h6 font-weight-medium mb-1">{{ $t('data_structure_definition') || 'Data structure definition' }}</h4>
    <p class="caption grey--text mb-4">
        Attach a data structure for this project. Import and browse indicator data from the <strong>Data</strong> item in the sidebar.
    </p>

    <div v-if="loading" class="d-flex align-center" style="gap:10px; min-height:120px;">
        <v-progress-circular indeterminate color="primary" size="24" width="2"></v-progress-circular>
        <span class="body-2 grey--text">Loading data structure…</span>
    </div>

    <v-alert v-else-if="error" type="error" dense outlined class="mb-4">{{ error }}</v-alert>

    <template v-else-if="!loading && totalColumns === 0">
        <div class="text-center py-8">
            <v-icon size="48" color="grey lighten-1">mdi-table-off</v-icon>
            <div class="subtitle-1 grey--text mt-3">No data structure attached</div>
            <div class="caption grey--text mt-1 mb-4">
                Attach a data structure from the registry. You can import indicator data later from <strong>Data</strong> in the sidebar.
            </div>
            <v-btn v-if="canEditProjectDsd" small color="primary" @click="openBindGlobalDialog">
                <v-icon left small>mdi-sitemap</v-icon> Attach data structure
            </v-btn>
        </div>
    </template>

    <template v-else>

        <v-card outlined class="mb-4">
            <v-card-text class="pa-4">
                <div class="d-flex align-start justify-space-between flex-wrap" style="gap:12px;">
                    <div>
                        <div class="text-subtitle-1 font-weight-medium">{{ globalStructureTitle }}</div>
                        <div v-if="globalStructure" class="caption grey--text mt-1">
                            <span v-if="globalStructure.agency">{{ globalStructure.agency }}</span>
                            <span v-if="globalStructure.name"> · {{ globalStructure.name }}</span>
                            <span v-if="globalStructure.version"> · v{{ globalStructure.version }}</span>
                        </div>
                        <div class="d-flex align-center flex-wrap mt-2" style="gap:8px;">
                            <span class="body-2">
                                <strong>{{ totalColumns }}</strong>
                                <span class="grey--text">{{ totalColumns === 1 ? ' component' : ' components' }}</span>
                            </span>
                            <v-chip v-if="overallValid === true && !hasValidationIssues" small color="success" text-color="white" label>
                                <v-icon left x-small>mdi-check-circle</v-icon> Valid
                            </v-chip>
                            <v-chip v-else-if="overallValid === false" small color="error" text-color="white" label>
                                <v-icon left x-small>mdi-alert-circle</v-icon>
                                {{ (report && report.errors ? report.errors.length : structureErrors.length) }}
                                {{ (report && report.errors && report.errors.length === 1) ? 'error' : 'errors' }}
                            </v-chip>
                            <v-chip v-if="hasPublishedData && binding.published_row_count != null" small outlined label class="ml-1">
                                {{ formatNumber(binding.published_row_count) }} {{ $t('rows') || 'rows' }}
                            </v-chip>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <v-btn
                            v-if="globalStructureDetailUrl"
                            small
                            outlined
                            color="primary"
                            :href="globalStructureDetailUrl"
                            target="_blank"
                            rel="noopener"
                        >
                            <v-icon left small>mdi-open-in-new</v-icon> View in registry
                        </v-btn>
                        <v-btn v-if="canEditProjectDsd" small outlined @click="openBindGlobalDialog">
                            <v-icon left small>mdi-swap-horizontal</v-icon> Change structure
                        </v-btn>
                        <v-btn
                            v-if="canRemoveStructure"
                            small
                            outlined
                            color="error"
                            @click="removeDialog = true"
                        >
                            <v-icon left small>mdi-link-off</v-icon> Remove structure
                        </v-btn>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-expansion-panels v-if="structureErrors.length > 0" flat class="mb-4">
            <v-expansion-panel style="border:1px solid #ffccbc; border-radius:4px;">
                <v-expansion-panel-header class="body-2 error--text py-2 px-3" style="min-height:40px;">
                    <span>
                        <v-icon small color="error" class="mr-1">mdi-alert-circle</v-icon>
                        {{ structureErrors.length }} structure {{ structureErrors.length === 1 ? 'issue' : 'issues' }}
                    </span>
                </v-expansion-panel-header>
                <v-expansion-panel-content>
                    <ul class="caption pl-4 my-1">
                        <li v-for="(e, i) in structureErrors" :key="i" class="mb-1">{{ e }}</li>
                    </ul>
                </v-expansion-panel-content>
            </v-expansion-panel>
        </v-expansion-panels>

        <v-expansion-panels v-if="structureWarnings.length > 0" flat class="mb-4">
            <v-expansion-panel style="border:1px solid #ffe0b2; border-radius:4px;">
                <v-expansion-panel-header class="body-2 orange--text text--darken-2 py-2 px-3" style="min-height:40px;">
                    <span>
                        <v-icon small color="warning" class="mr-1">mdi-alert-outline</v-icon>
                        {{ structureWarnings.length }} {{ structureWarnings.length === 1 ? 'warning' : 'warnings' }}
                    </span>
                </v-expansion-panel-header>
                <v-expansion-panel-content>
                    <ul class="caption pl-4 my-1">
                        <li v-for="(w, i) in structureWarnings" :key="'w' + i" class="mb-1">{{ w }}</li>
                    </ul>
                </v-expansion-panel-content>
            </v-expansion-panel>
        </v-expansion-panels>

        <v-card v-if="hasPublishedData && dataValidation" outlined class="mb-4">
            <v-card-title class="text-subtitle-1 py-3 d-flex align-center flex-wrap" style="gap: 8px;">
                <v-icon color="primary" class="mr-1">mdi-database-check-outline</v-icon>
                {{ $t('dsd_validation_section_data') || 'Data validation' }}
                <v-spacer></v-spacer>
                <v-chip
                    v-if="dataValidation.skipped"
                    small
                    label
                    color="grey"
                    text-color="white"
                >
                    <v-icon left x-small>mdi-minus-circle-outline</v-icon>
                    {{ $t('skipped') || 'Skipped' }}
                </v-chip>
                <v-chip
                    v-else
                    small
                    label
                    :color="dataValidation.valid ? 'success' : 'error'"
                    text-color="white"
                >
                    <v-icon left x-small>{{ dataValidation.valid ? 'mdi-check-circle' : 'mdi-close-circle' }}</v-icon>
                    {{ dataValidation.valid ? ($t('validation_passed') || 'Passed') : ($t('validation_failed') || 'Failed') }}
                </v-chip>
                <v-btn
                    small
                    text
                    color="primary"
                    :loading="isValidating"
                    @click="refreshValidation"
                >
                    <v-icon left small>mdi-refresh</v-icon>
                    {{ $t('validate') || 'Re-run' }}
                </v-btn>
            </v-card-title>
            <v-card-text class="pt-0 pb-3">
                <div v-if="dataValidation.skipped" class="text-body-2 text--secondary">
                    {{ dataValidation.reason }}
                </div>
                <template v-else>
                    <div v-if="dataValidation.source || dataValidation.row_count != null" class="text-caption text--secondary mb-3">
                        <span v-if="dataValidation.source">{{ $t('source') || 'Source' }}: <strong class="text--primary">{{ dataValidation.source }}</strong></span>
                        <span v-if="dataValidation.row_count != null">
                            <span v-if="dataValidation.source"> · </span>{{ formatNumber(dataValidation.row_count) }} {{ $t('rows') || 'rows' }}
                        </span>
                    </div>

                    <div
                        v-if="dataValidation.observation_key"
                        class="pa-3 mb-3 rounded"
                        style="background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.06);"
                    >
                        <div v-if="dataValidation.observation_key.skipped" class="text-body-2 text--secondary">
                            {{ dataValidation.observation_key.reason }}
                        </div>
                        <template v-else>
                            <div v-if="dataValidation.observation_key.key_columns && dataValidation.observation_key.key_columns.length" class="text-body-2 mb-2">
                                <span class="text--secondary">{{ $t('key') || 'Key' }}: </span>
                                <span class="text-wrap">{{ formatObservationKeyColumns(dataValidation.observation_key.key_columns) }}</span>
                            </div>
                            <div v-if="dataValidation.observation_key.value_column" class="text-body-2 mb-2">
                                <span class="text--secondary">{{ $t('value') || 'Value' }}: </span>
                                {{ dataValidation.observation_key.value_column.dsd_name }}
                                <span class="text--secondary"> — {{ dataValidation.observation_key.value_column.physical_name }}</span>
                            </div>
                            <div class="text-body-2">
                                <span v-if="dataValidation.observation_key.rows_with_observation_value != null">
                                    {{ $t('dsd_validation_rows_with_value') || 'Rows with value' }}: <strong>{{ dataValidation.observation_key.rows_with_observation_value }}</strong>
                                </span>
                                <span v-if="dataValidation.observation_key.unique_observation_count != null" class="ml-2">
                                    · {{ $t('dsd_validation_unique_observations') || 'Unique keys' }}: <strong>{{ dataValidation.observation_key.unique_observation_count }}</strong>
                                </span>
                                <span v-if="dataValidation.observation_key.table_rows_read != null" class="ml-2 text--secondary">
                                    · {{ $t('dsd_validation_rows_scanned') || 'Counted' }}: {{ dataValidation.observation_key.table_rows_read }}
                                </span>
                            </div>
                            <div v-if="dataValidation.observation_key.scan_truncated" class="text-caption warning--text mt-2">
                                {{ $t('dsd_validation_observation_key_truncated') || 'Counts may be incomplete (scan truncated).' }}
                            </div>
                        </template>
                    </div>

                    <template v-if="dataValidation.errors && dataValidation.errors.length">
                        <div class="text-overline text--secondary mb-2">{{ $t('data_errors') || 'Data errors' }}</div>
                        <div
                            v-for="(error, idx) in dataValidation.errors"
                            :key="'vde-' + idx"
                            class="d-flex align-start text-body-2 error--text mb-2"
                            style="gap: 8px;"
                        >
                            <v-icon color="error" small class="flex-shrink-0 mt-1">mdi-close-circle</v-icon>
                            <span class="text-wrap" style="min-width: 0;">{{ error }}</span>
                        </div>
                    </template>
                    <template v-if="dataValidation.warnings && dataValidation.warnings.length">
                        <div class="text-overline text--secondary mb-2 mt-3">{{ $t('data_warnings') || 'Data warnings' }}</div>
                        <div
                            v-for="(warning, idx) in dataValidation.warnings"
                            :key="'vdw-' + idx"
                            class="d-flex align-start text-body-2 warning--text mb-2"
                            style="gap: 8px;"
                        >
                            <v-icon color="warning" small class="flex-shrink-0 mt-1">mdi-alert</v-icon>
                            <span class="text-wrap" style="min-width: 0;">{{ warning }}</span>
                        </div>
                    </template>
                </template>
            </v-card-text>
        </v-card>

        <v-card outlined class="mb-4">
            <v-card-title class="text-subtitle-1 py-3">
                Components
                <v-progress-circular v-if="componentsLoading" indeterminate size="16" width="2" class="ml-2"></v-progress-circular>
            </v-card-title>
            <v-data-table
                :headers="compHeaders"
                :items="components"
                :items-per-page="50"
                dense
                class="elevation-0"
                :loading="componentsLoading"
                loading-text="Loading components…"
                no-data-text="No components found"
            ></v-data-table>
        </v-card>

    </template>

    <v-dialog v-model="bindGlobalDialog" max-width="560" persistent>
        <v-card>
            <v-card-title>{{ isBound ? 'Change data structure' : 'Attach data structure' }}</v-card-title>
            <v-card-text>
                <p class="text-body-2 grey--text text--darken-1">
                    Link this project to a data structure from the registry.
                </p>
                <v-select
                    v-model="bindStructureId"
                    :items="globalStructuresList"
                    :item-text="globalStructureLabel"
                    item-value="id"
                    label="Data structure"
                    outlined
                    dense
                    :loading="globalStructuresLoading"
                    clearable
                ></v-select>
            </v-card-text>
            <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn text :disabled="bindingGlobal" @click="bindGlobalDialog = false">Cancel</v-btn>
                <v-btn color="primary" :loading="bindingGlobal" :disabled="!bindStructureId" @click="submitBindGlobal">
                    {{ isBound ? 'Replace' : 'Attach' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="removeDialog" max-width="480" persistent>
        <v-card>
            <v-card-title class="error--text">Remove data structure</v-card-title>
            <v-card-text>
                <p class="text-body-2">{{ removeConfirmMessage }}</p>
            </v-card-text>
            <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn text :disabled="removing" @click="removeDialog = false">Cancel</v-btn>
                <v-btn color="error" dark depressed :loading="removing" @click="submitRemoveStructure">Remove</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

</div>
    `
});
