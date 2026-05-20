/**
 * Data explorer for indicator projects: reads published rows from DuckDB project_{sid}.timeseries.
 * DSD metadata (column_type, data_type) is merged for rich headers.
 * Columns are displayed in DSD order: indicator → geography → time + FREQ + _ts_* → value → other.
 */
Vue.component('indicator-timeseries-data-explorer', {
    props: {
        file_id: { type: String, default: '' },
        embedded: { type: Boolean, default: false },
        hideToolbar: { type: Boolean, default: false }
    },
    data: function() {
        return {
            fid: this.file_id,
            rowsLimit: 50,
            data_loading: false,
            errors: [],
            dsdColumns: [],
            dsdLoading: false,
            pagePayload: null,
            /** @type {Object.<string, string[]>} physical column name -> selected values (server IN filter) */
            columnValueFilters: {},
            /** column whose filter menu is currently open */
            filterMenuColumn: null,
            /** pending value selection inside the open menu */
            filterMenuValues: [],
            filterDistinctItems: [],
            filterDistinctLoading: false,
            filterDistinctError: null,
            deleteDataLoading: false,
            deleteDataDialog: false,
            /** True when DuckDB timeseries table does not exist yet (not a hard error). */
            noPublishedData: false
        };
    },
    mounted: function() {
        this.fid = this.$route.params.file_id || this.file_id;
        this.loadDsd();
        this.loadPage(0);
    },
    watch: {
        '$route.params.file_id': function(n) {
            this.fid = n;
            this.loadDsd();
            this.loadPage(0);
        },
        filterMenuColumn: function(col) {
            this.filterDistinctItems = [];
            this.filterDistinctError = null;
            if (col) {
                this.loadDistinctForFilterColumn(col);
            }
        }
    },
    computed: {
        ProjectID: function() {
            return this.$store.state.project_id;
        },
        activeDataFile: function() {
            var fromStore = this.$store.getters.getDataFileById(this.fid);
            if (fromStore) {
                return fromStore;
            }
            // DuckDB indicator data can exist without an editor_data_files row (e.g. legacy
            // file_id, or import path that skipped the virtual file). Still show the explorer.
            var t = this.$store.getters.getProjectType;
            if ((t === 'indicator' || t === 'timeseries') && this.fid === 'INDICATOR_DATA') {
                return { file_id: 'INDICATOR_DATA', file_name: 'indicator_data' };
            }
            return null;
        },
        dsdByUpperName: function() {
            var map = {};
            (this.dsdColumns || []).forEach(function(c) {
                if (c && c.name) {
                    map[String(c.name).trim().toUpperCase()] = c;
                }
            });
            return map;
        },
        tableColumns: function() {
            if (!this.pagePayload || !this.pagePayload.columns) {
                return [];
            }
            return this.pagePayload.columns;
        },
        /**
         * DSD-aware column order for browsing (default product rule):
         * indicator_id → indicator_name → geography → time_period → periodicity (FREQ col)
         * → _ts_year → _ts_freq → observation_value → measure → dimensions → attributes → annotations
         * → unknown / extra physical columns (stable DuckDB order within same tier).
         */
        displayTableColumns: function() {
            var cols = this.tableColumns || [];
            if (!cols.length) {
                return [];
            }
            var dsdByUp = this.dsdByUpperName || {};
            var tierFor = function(col, origIdx) {
                if (!col || !col.name) {
                    return [9000, origIdx];
                }
                var up = String(col.name).trim().toUpperCase();
                if (up === '_TS_YEAR') {
                    return [55, origIdx];
                }
                if (up === '_TS_FREQ') {
                    return [56, origIdx];
                }
                var dsd = dsdByUp[up];
                if (!dsd || !dsd.column_type) {
                    return [900, origIdx];
                }
                var ct = dsd.column_type;
                var base = {
                    indicator_id: 10,
                    indicator_name: 20,
                    geography: 30,
                    time_period: 40,
                    periodicity: 50,
                    observation_value: 60,
                    measure: 65,
                    dimension: 70,
                    attribute: 80,
                    annotation: 85
                };
                var t = base[ct];
                if (t === undefined) {
                    return [800, origIdx];
                }
                return [t, origIdx];
            };
            var decorated = cols.map(function(c, i) {
                return { col: c, key: tierFor(c, i) };
            });
            decorated.sort(function(a, b) {
                if (a.key[0] !== b.key[0]) {
                    return a.key[0] - b.key[0];
                }
                return a.key[1] - b.key[1];
            });
            return decorated.map(function(d) {
                return d.col;
            });
        },
        records: function() {
            return (this.pagePayload && this.pagePayload.rows) ? this.pagePayload.rows : [];
        },
        totalRows: function() {
            return this.pagePayload && typeof this.pagePayload.total_row_count === 'number'
                ? this.pagePayload.total_row_count
                : 0;
        },
        pageOffset: function() {
            return this.pagePayload && typeof this.pagePayload.offset === 'number'
                ? this.pagePayload.offset
                : 0;
        },
        paginationPages: function() {
            if (this.totalRows <= 0) {
                return 1;
            }
            return Math.ceil(this.totalRows / this.rowsLimit);
        },
        currentPage: function() {
            if (this.rowsLimit <= 0) {
                return 1;
            }
            return Math.floor(this.pageOffset / this.rowsLimit) + 1;
        },
        exportUrl: function() {
            return CI.base_url + '/api/indicator_dsd/data_export/' + this.ProjectID;
        },
        showEmptyState: function() {
            if (this.data_loading || this.errors.length > 0) {
                return false;
            }
            if (this.noPublishedData) {
                return true;
            }
            if (!this.pagePayload) {
                return false;
            }
            if (this.totalRows > 0) {
                return false;
            }
            var hasFilters = Object.keys(this.columnValueFilters || {}).length > 0;
            return !hasFilters;
        },
        physicalColumnNames: function() {
            return (this.tableColumns || []).map(function(c) {
                return c && c.name ? String(c.name) : '';
            }).filter(Boolean);
        },
        hasActiveColumnFilters: function() {
            var o = this.columnValueFilters || {};
            return Object.keys(o).some(function(k) {
                return (o[k] && o[k].length);
            });
        },
        activeFilterEntries: function() {
            var o = this.columnValueFilters || {};
            return Object.keys(o).map(function(k) {
                return { col: k, vals: o[k] || [] };
            }).filter(function(e) {
                return e.vals.length;
            });
        }
    },
    methods: {
        loadDsd: function() {
            var vm = this;
            this.dsdLoading = true;
            axios.get(CI.base_url + '/api/indicator_dsd/' + this.ProjectID)
                .then(function(res) {
                    vm.dsdColumns = (res.data && res.data.columns) ? res.data.columns : [];
                })
                .catch(function() {
                    vm.dsdColumns = [];
                })
                .then(function() {
                    vm.dsdLoading = false;
                });
        },
        buildFiltersPayload: function() {
            var out = {};
            var o = this.columnValueFilters || {};
            Object.keys(o).forEach(function(k) {
                var arr = (o[k] || []).map(function(x) {
                    return String(x).trim();
                }).filter(function(x) {
                    return x !== '';
                });
                if (arr.length) {
                    out[k] = arr;
                }
            });
            return out;
        },
        loadDistinctForFilterColumn: function(colName) {
            var vm = this;
            if (!colName) {
                this.filterDistinctItems = [];
                return;
            }
            this.filterDistinctLoading = true;
            this.filterDistinctError = null;
            axios.get(
                CI.base_url + '/api/indicator_dsd/data_values/' + this.ProjectID,
                { params: { code_column: colName, limit: 3000 } }
            )
                .then(function(res) {
                    var items = (res.data && res.data.data && res.data.data.items) ? res.data.data.items : [];
                    vm.filterDistinctItems = items.map(function(it) {
                        var code = it.code != null ? String(it.code) : '';
                        var lb = it.label != null ? String(it.label) : code;
                        return {
                            value: code,
                            text: lb !== code ? code + ' — ' + lb : code
                        };
                    });
                })
                .catch(function(err) {
                    vm.filterDistinctItems = [];
                    vm.filterDistinctError = (err.response && err.response.data && err.response.data.message)
                        || err.message
                        || 'Could not load distinct values';
                })
                .then(function() {
                    vm.filterDistinctLoading = false;
                });
        },
        onFilterMenuToggle: function(colName, isOpen) {
            if (isOpen) {
                this.filterMenuColumn = colName;
                this.filterMenuValues = (this.columnValueFilters[colName] || []).slice();
            } else {
                this.filterMenuColumn = null;
                this.filterMenuValues = [];
                this.filterDistinctItems = [];
                this.filterDistinctError = null;
            }
        },
        applyColumnFilterFromMenu: function(colName) {
            var vals = (this.filterMenuValues || []).map(function(x) {
                return String(x).trim();
            }).filter(function(x) {
                return x !== '';
            });
            if (vals.length) {
                this.$set(this.columnValueFilters, colName, vals);
            } else {
                this.$delete(this.columnValueFilters, colName);
            }
            this.filterMenuColumn = null;
            this.filterMenuValues = [];
            this.filterDistinctItems = [];
            this.loadPage(0);
        },
        clearColumnFilterFromMenu: function(colName) {
            this.$delete(this.columnValueFilters, colName);
            this.filterMenuColumn = null;
            this.filterMenuValues = [];
            this.filterDistinctItems = [];
            this.loadPage(0);
        },
        removeFilterColumn: function(colName) {
            this.$delete(this.columnValueFilters, colName);
            this.loadPage(0);
        },
        clearAllColumnFilters: function() {
            this.columnValueFilters = {};
            this.filterMenuColumn = null;
            this.filterMenuValues = [];
            this.filterDistinctItems = [];
            this.loadPage(0);
        },
        isMissingTimeseriesError: function(msg, httpStatus) {
            if (httpStatus === 404) {
                return true;
            }
            var s = String(msg || '').toLowerCase();
            return s.indexOf('timeseries table not found') >= 0
                || s.indexOf('table not found') >= 0
                || s.indexOf('not found for this project') >= 0;
        },
        applyEmptyNoData: function() {
            this.noPublishedData = true;
            this.pagePayload = { columns: [], rows: [], total_row_count: 0, offset: 0 };
            this.errors = [];
            this.emitDataState();
        },
        emitDataState: function() {
            var hasData = this.totalRows > 0 || this.records.length > 0;
            this.$emit('data-state', { hasData: hasData, totalRows: this.totalRows });
        },
        handleDataLoadFailure: function(msg, httpStatus) {
            if (this.isMissingTimeseriesError(msg, httpStatus)) {
                this.applyEmptyNoData();
                return true;
            }
            return false;
        },
        onEmptyImportClick: function() {
            if (this.embedded) {
                this.$emit('go-import');
                return;
            }
            if (this.$router) {
                this.$router.push({ path: '/data-explorer/INDICATOR_DATA', query: { tab: 'import' } });
            }
        },
        loadPage: function(offset) {
            var vm = this;
            this.data_loading = true;
            this.errors = [];
            this.noPublishedData = false;
            var params = { offset: offset, limit: this.rowsLimit };
            var fp = this.buildFiltersPayload();
            if (Object.keys(fp).length) {
                params.filters = JSON.stringify(fp);
            }
            axios.get(
                CI.base_url + '/api/indicator_dsd/data_rows/' + this.ProjectID,
                { params: params }
            )
                .then(function(res) {
                    if (res.data && res.data.status === 'success' && res.data.data) {
                        vm.pagePayload = res.data.data;
                        vm.noPublishedData = false;
                    } else {
                        var failMsg = (res.data && res.data.message) || 'Could not load timeseries';
                        var failStatus = res.status || 0;
                        if (!vm.handleDataLoadFailure(failMsg, failStatus)) {
                            vm.pagePayload = null;
                            vm.errors.push(failMsg);
                        }
                    }
                })
                .catch(function(err) {
                    var msg = (err.response && err.response.data && err.response.data.message)
                        || err.message
                        || 'Could not load timeseries';
                    var status = err.response && err.response.status;
                    if (!vm.handleDataLoadFailure(msg, status)) {
                        vm.pagePayload = null;
                        vm.errors.push(msg);
                    }
                })
                .then(function() {
                    vm.data_loading = false;
                    vm.$nextTick(function() {
                        vm.emitDataState();
                    });
                });
        },
        navigatePage: function(page) {
            var p = parseInt(page, 10);
            if (isNaN(p) || p < 1) {
                p = 1;
            }
            this.loadPage((p - 1) * this.rowsLimit);
        },
        columnChipColor: function(physicalName) {
            var n = String(physicalName || '').toUpperCase();
            if (n === '_TS_YEAR' || n === '_TS_FREQ') {
                return 'info';
            }
            return 'secondary';
        },
        columnRoleLabel: function(physicalName) {
            var up = String(physicalName || '').trim().toUpperCase();
            if (up === '_TS_YEAR') {
                return this.$t('dsd_role_ts_year') || 'Computed · period year';
            }
            if (up === '_TS_FREQ') {
                return this.$t('dsd_role_ts_freq') || 'Computed · frequency';
            }
            var dsd = this.dsdByUpperName[up];
            if (!dsd || !dsd.column_type) {
                return this.$t('dsd_role_unknown') || 'Not in DSD';
            }
            var ct = dsd.column_type;
            var map = {
                indicator_id: this.$t('dsd_role_indicator') || 'Dimension · indicator',
                geography: this.$t('dsd_role_geography') || 'Dimension · geography',
                time_period: this.$t('dsd_role_time') || 'Dimension · time',
                observation_value: this.$t('dsd_role_measure') || 'Measure · observation',
                attribute: this.$t('dsd_role_attribute') || 'Attribute'
            };
            return map[ct] || ct;
        },
        exportCsv: function() {
            window.location.assign(this.exportUrl);
        },
        confirmDeleteAllData: function() {
            this.deleteDataDialog = true;
        },
        deleteAllData: function() {
            var vm = this;
            this.deleteDataDialog = false;
            this.deleteDataLoading = true;
            this.errors = [];
            axios.post(CI.base_url + '/api/indicator_dsd/data_delete/' + this.ProjectID)
                .then(function(res) {
                    var rows = res.data && res.data.row_count != null ? res.data.row_count : 0;
                    var msg = rows > 0
                        ? rows.toLocaleString() + ' rows deleted.'
                        : 'Data table removed.';
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', msg);
                    }
                    vm.applyEmptyNoData();
                    vm.columnValueFilters = {};
                    vm.filterMenuColumn = null;
                    vm.filterMenuValues = [];
                    vm.filterDistinctItems = [];
                })
                .catch(function(err) {
                    var msg = (err.response && err.response.data && err.response.data.message)
                        || err.message
                        || 'Could not delete data';
                    vm.errors.push(msg);
                })
                .then(function() {
                    vm.deleteDataLoading = false;
                });
        }
    },
    template: `
        <div class="indicator-ts-explorer" :class="embedded ? '' : 'mt-5 pt-3 m-3'">
            <!-- Confirm delete dialog -->
            <v-dialog v-model="deleteDataDialog" max-width="420" persistent>
                <v-card>
                    <v-card-title class="text-h6">
                        <v-icon color="error" left>mdi-alert</v-icon>
                        {{ $t('delete_data_title') || 'Delete data?' }}
                    </v-card-title>
                    <v-card-text>
                        {{ $t('delete_data_confirm') || 'All data will be removed.' }}
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="deleteDataDialog = false">{{ $t('cancel') || 'Cancel' }}</v-btn>
                        <v-btn color="error" dark @click="deleteAllData">
                            <v-icon left small>mdi-delete</v-icon>
                            {{ $t('delete_data') || 'Delete data' }}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <template v-if="activeDataFile">
                <v-card :flat="embedded" :outlined="!embedded" class="mb-0">
                    <v-card-title v-if="!embedded && !hideToolbar" class="d-flex flex-wrap align-center justify-space-between">
                        <span>{{ $t('data_view') || 'Data' }}</span>
                        <div class="d-flex align-center flex-wrap" style="gap: 8px;">
                            <v-btn
                                v-if="records.length || totalRows > 0"
                                color="primary"
                                outlined
                                small
                                @click="exportCsv"
                            >
                                <v-icon left small>mdi-download</v-icon>
                                {{ $t('export_csv') || 'Export CSV' }}
                            </v-btn>
                            <v-btn
                                v-if="records.length || totalRows > 0"
                                color="error"
                                outlined
                                small
                                :loading="deleteDataLoading"
                                @click="confirmDeleteAllData"
                            >
                                <v-icon left small>mdi-delete</v-icon>
                                {{ $t('delete_data') || 'Delete data' }}
                            </v-btn>
                        </div>
                    </v-card-title>
                    <v-card-text :class="embedded ? 'pa-0' : ''" style="min-height: 200px;">
                        <v-alert v-for="(err, i) in errors" :key="i" type="error" dense outlined class="mb-2">
                            {{ err }}
                        </v-alert>

                        <div v-if="data_loading" class="pt-2">
                            <v-progress-linear indeterminate color="primary"></v-progress-linear>
                            <div class="caption grey--text mt-1">{{ $t('loading_please_wait') }}</div>
                        </div>

                        <template v-else-if="pagePayload && displayTableColumns.length">
                            <div v-if="hasActiveColumnFilters" class="d-flex flex-wrap align-center mb-2" style="gap: 4px;">
                                <v-chip
                                    v-for="e in activeFilterEntries"
                                    :key="e.col"
                                    x-small
                                    close
                                    outlined
                                    color="primary"
                                    @click:close="removeFilterColumn(e.col)"
                                >
                                    {{ e.col }} · {{ e.vals.length }}
                                </v-chip>
                                <v-btn text x-small color="primary" @click="clearAllColumnFilters">
                                    {{ $t('clear_all') || 'Clear all' }}
                                </v-btn>
                            </div>
                            <div class="d-flex flex-wrap justify-space-between align-center mb-3" style="gap: 8px;">
                                <div class="caption grey--text text--darken-1">
                                    {{ $t('showing_records_range', {
                                        start: pageOffset + 1,
                                        end: pageOffset + records.length,
                                        total: totalRows
                                    }) }}
                                </div>
                                <v-pagination
                                    v-if="paginationPages > 1"
                                    :value="currentPage"
                                    :length="paginationPages"
                                    :total-visible="8"
                                    @input="navigatePage"
                                ></v-pagination>
                            </div>

                            <div class="table-responsive bg-white indicator-ts-explorer-table" style="overflow-x: auto; font-size: 12px;">
                                <table class="table table-sm table-bordered table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-right text-muted" style="width:52px;">#</th>
                                            <th v-for="col in displayTableColumns" :key="col.name" class="align-top text-left" style="min-width: 140px;">
                                                <div class="d-flex align-start justify-space-between" style="gap: 2px;">
                                                    <div class="flex-grow-1">
                                                        <div class="font-weight-medium">{{ col.name }}</div>
                                                        <v-chip x-small outlined class="mt-1" :color="columnChipColor(col.name)">
                                                            {{ columnRoleLabel(col.name) }}
                                                        </v-chip>
                                                    </div>
                                                    <v-menu
                                                        :value="filterMenuColumn === col.name"
                                                        @input="function(v){ onFilterMenuToggle(col.name, v); }"
                                                        :close-on-content-click="false"
                                                        offset-y
                                                        left
                                                        min-width="280"
                                                        max-width="340"
                                                    >
                                                        <template v-slot:activator="{ on, attrs }">
                                                            <v-btn
                                                                icon x-small
                                                                v-bind="attrs" v-on="on"
                                                                :color="columnValueFilters[col.name] && columnValueFilters[col.name].length ? 'primary' : ''"
                                                                class="mt-1 flex-shrink-0"
                                                                :title="$t('filter') || 'Filter'"
                                                            >
                                                                <v-icon x-small>{{ columnValueFilters[col.name] && columnValueFilters[col.name].length ? 'mdi-filter' : 'mdi-filter-outline' }}</v-icon>
                                                            </v-btn>
                                                        </template>
                                                        <v-card>
                                                            <v-card-text class="pa-3 pb-1">
                                                                <div class="caption font-weight-medium mb-2">{{ col.name }}</div>
                                                                <v-autocomplete
                                                                    v-if="filterMenuColumn === col.name"
                                                                    v-model="filterMenuValues"
                                                                    :items="filterDistinctItems"
                                                                    item-value="value"
                                                                    item-text="text"
                                                                    multiple
                                                                    chips
                                                                    small-chips
                                                                    deletable-chips
                                                                    dense
                                                                    outlined
                                                                    hide-details
                                                                    autofocus
                                                                    :loading="filterDistinctLoading"
                                                                    :no-data-text="filterDistinctLoading ? ($t('loading_please_wait') || 'Loading...') : ($t('no_data') || 'No data')"
                                                                ></v-autocomplete>
                                                                <v-alert v-if="filterDistinctError" type="error" dense text class="mt-2 mb-0">
                                                                    {{ filterDistinctError }}
                                                                </v-alert>
                                                            </v-card-text>
                                                            <v-card-actions class="pa-2">
                                                                <v-btn text x-small color="error" @click="clearColumnFilterFromMenu(col.name)">{{ $t('clear') || 'Clear' }}</v-btn>
                                                                <v-spacer></v-spacer>
                                                                <v-btn color="primary" x-small depressed @click="applyColumnFilterFromMenu(col.name)">{{ $t('apply_filter') || 'Apply' }}</v-btn>
                                                            </v-card-actions>
                                                        </v-card>
                                                    </v-menu>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in records" :key="pageOffset + idx">
                                            <td class="text-right text-muted">{{ pageOffset + idx + 1 }}</td>
                                            <td v-for="col in displayTableColumns" :key="col.name">
                                                <span class="d-inline-block text-truncate" style="max-width: 220px;" :title="row[col.name]">
                                                    {{ row[col.name] }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <div v-else-if="showEmptyState" class="text-center py-6">
                            <v-icon size="48" color="grey lighten-1">mdi-table-off</v-icon>
                            <p class="mt-3 mb-2 grey--text">{{ $t('indicator_data_empty') || 'No published indicator data yet.' }}</p>
                            <p class="caption grey--text mb-0">Import a CSV that matches the project data structure using Import data above.</p>
                        </div>
                    </v-card-text>
                </v-card>
            </template>
            <v-card v-else class="mt-5 pt-3 m-3">
                <v-card-title>{{ $t('data_view') || 'Data' }}</v-card-title>
                <v-card-text class="text-center py-8">
                    <v-icon size="64" color="grey lighten-1">mdi-database-off</v-icon>
                    <p class="mt-3 mb-0">{{ $t('no_data_file') || 'No data file found.' }}</p>
                </v-card-text>
            </v-card>
        </div>
    `
});
