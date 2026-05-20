// Indicator Data Structure Definition (DSD) component
Vue.component('indicator-dsd', {
    props: [],
    data() {
        return {
            dataset_id: project_sid,
            dataset_idno: project_idno,
            dataset_type: project_type,
            columns: [],
            loading: false,
            column_search: '',
            page_action: 'list',
            edit_item: null,
            validationResult: null,
            isValidating: false,
            /** 0 = Structure (editor), 1 = Validation report */
            dsdMainTab: 0,
            sortOrder: 'asc',
            globalCodelistsList: [],
            globalCodelistsLoading: false,
            bindGlobalDialog: false,
            globalStructuresList: [],
            globalStructuresLoading: false,
            bindStructureId: null,
            bindingGlobal: false,
            dsdBinding: {
                bound: false,
                read_only: false,
                column_count: 0,
                global_structure: null,
                data_structure_reference: null
            },
            dsdDictionaries: {
                freq_codes: []
            },
            dsdSplitListWidth: 280
        }
    },
    created: async function() {
        try {
            var s = localStorage.getItem('indicator_dsd_list_width_px');
            if (s) {
                var n = parseInt(s, 10);
                if (!isNaN(n) && n >= 220 && n <= 1200) {
                    this.dsdSplitListWidth = n;
                }
            }
        } catch (e) { /* ignore */ }
        this.fetchGlobalCodelists();
        await this.loadBinding();
        await this.loadColumns();
        if (this.columns.length > 0) {
            this.validateDSDDebounce();
        }
    },
    methods: {
        clearColumnSearch: function() {
            this.column_search = '';
        },
        formatObservationKeyColumns: function(cols) {
            if (!cols || !cols.length) {
                return '';
            }
            return cols.map(function(kc) {
                var nm = (kc && kc.dsd_name) ? String(kc.dsd_name) : '';
                var tp = (kc && kc.column_type) ? String(kc.column_type) : '';
                return tp ? (nm + ' (' + tp + ')') : nm;
            }).join(', ');
        },
        dsdSplitClampWidth: function(w) {
            var root = this.$refs.dsdSplitRoot;
            var rootW = (root && root.getBoundingClientRect().width) || 960;
            var gutter = 8;
            var minW = 220;
            var maxW = Math.max(minW + 100, Math.floor(rootW * 0.62) - gutter);
            return Math.min(maxW, Math.max(minW, w));
        },
        dsdSplitDragEnd: function(persist) {
            if (this._dsdSplitMove) {
                document.removeEventListener('mousemove', this._dsdSplitMove);
            }
            if (this._dsdSplitUp) {
                document.removeEventListener('mouseup', this._dsdSplitUp);
            }
            this._dsdSplitMove = null;
            this._dsdSplitUp = null;
            if (document.body) {
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
            if (persist) {
                try {
                    localStorage.setItem('indicator_dsd_list_width_px', String(this.dsdSplitListWidth));
                } catch (err) { /* ignore */ }
            }
        },
        onDsdSplitMouseDown: function(e) {
            if (e.button !== 0) {
                return;
            }
            e.preventDefault();
            this.dsdSplitDragEnd(false);
            var vm = this;
            var startX = e.clientX;
            var startW = vm.dsdSplitListWidth;
            var onMove = function(ev) {
                vm.dsdSplitListWidth = vm.dsdSplitClampWidth(startW + (ev.clientX - startX));
            };
            var onUp = function() {
                vm.dsdSplitDragEnd(true);
            };
            vm._dsdSplitMove = onMove;
            vm._dsdSplitUp = onUp;
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        },
        nudgeDsdSplit: function(delta) {
            this.dsdSplitListWidth = this.dsdSplitClampWidth(this.dsdSplitListWidth + delta);
            try {
                localStorage.setItem('indicator_dsd_list_width_px', String(this.dsdSplitListWidth));
            } catch (err) { /* ignore */ }
        },
        fetchGlobalCodelists: function() {
            var vm = this;
            vm.globalCodelistsLoading = true;
            axios.get(CI.base_url + '/api/codelists', { params: { limit: 500, order_by: 'name', order_dir: 'ASC', exclude_archived: 1 } })
                .then(function(res) {
                    var data = res.data || {};
                    vm.globalCodelistsList = Array.isArray(data.codelists) ? data.codelists : [];
                })
                .catch(function() {
                    vm.globalCodelistsList = [];
                })
                .then(function() {
                    vm.globalCodelistsLoading = false;
                });
        },
        openBindGlobalDialog: function() {
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
        submitBindGlobal: function() {
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
                        EventBus.$emit('onSuccess', 'Bound to data structure');
                    }
                    return vm.loadBinding().then(function() {
                        return vm.loadColumns();
                    });
                })
                .then(function() {
                    if (vm.columns.length > 0) {
                        vm.validateDSDDebounce();
                    }
                })
                .catch(function(err) {
                    vm.bindingGlobal = false;
                    var m = (err.response && err.response.data && err.response.data.message) || 'Bind failed';
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', m);
                    }
                });
        },
        globalStructureLabel: function(ds) {
            if (!ds) {
                return '';
            }
            var t = ds.title || ds.name || '';
            var id = ds.idno || ((ds.agency || '') + ':' + (ds.name || ''));
            return t + ' (' + id + ')';
        },
        loadBinding: async function() {
            const vm = this;
            try {
                const response = await axios.get(CI.base_url + '/api/indicator_dsd/binding/' + vm.dataset_id);
                if (response.data) {
                    vm.dsdBinding = {
                        bound: !!response.data.bound,
                        read_only: !!response.data.read_only,
                        column_count: response.data.column_count || 0,
                        global_structure: response.data.global_structure || null,
                        data_structure_reference: response.data.data_structure_reference || null
                    };
                }
            } catch (e) {
                console.log('binding load failed', e);
            }
        },
        loadColumns: async function() {
            this.loading = true;
            const vm = this;
            let url = CI.base_url + '/api/indicator_dsd/' + vm.dataset_id + '?detailed=1';

            try {
                let response = await axios.get(url);
                if (response.data && response.data.columns) {
                    vm.columns = response.data.columns;
                }
                if (response.data && response.data.dictionaries) {
                    var d = response.data.dictionaries;
                    vm.dsdDictionaries = {
                        freq_codes: Array.isArray(d.freq_codes) ? d.freq_codes : []
                    };
                }
            } catch (error) {
                console.log("Error loading columns", error);
                EventBus.$emit('onFail', 'Failed to load columns');
            } finally {
                this.loading = false;
            }
        },
        editColumn: function(index) {
            this.page_action = "edit";
            this.edit_item = index;
        },
        editColumnByColumn: function(column) {
            // Find the index of the column in the original columns array
            const index = this.columns.findIndex(col => col.id === column.id);
            if (index !== -1) {
                this.editColumn(index);
            }
        },
        toggleSortOrder: function() {
            this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
        },
        exitEditMode: function() {
            if (this.edit_item === null) {
                return;
            }

            this.page_action = "list";
            this.edit_item = null;
        },
        colNavigate: function(direction) {
            const total_cols = this.columns.length - 1;

            switch (direction) {
                case 'first':
                    this.edit_item = 0;
                    break;
                case 'prev':
                    if (this.edit_item > 0) {
                        this.edit_item = this.edit_item - 1;
                    }
                    break;
                case 'next':
                    if (this.edit_item < total_cols) {
                        this.edit_item = this.edit_item + 1;
                    }
                    break;
                case 'last':
                    this.edit_item = total_cols;
                    break;
            }

            this.page_action = "edit";
        },
        columnActiveClass: function(idx, column) {
            if (!column || !column.id) {
                return '';
            }

            let classes = [];
            
            // Check if this column is the active one by comparing IDs
            if (this.isColumnActive(column)) {
                classes.push('activeRow');
            }

            return classes.join(' ');
        },
        getActiveColumnId: function() {
            if (this.edit_item !== null && this.columns[this.edit_item]) {
                return this.columns[this.edit_item].id;
            }
            return null;
        },
        isColumnActive: function(column) {
            if (!column || !column.id) {
                return false;
            }
            const activeId = this.getActiveColumnId();
            return activeId !== null && activeId === column.id;
        },
        hasEmptyName: function(column) {
            return !column || !column.name || String(column.name).trim() === '';
        },
        getRowStyle: function(column) {
            let style = 'cursor: pointer; border-bottom: 1px solid #e0e0e0;';
            
            // Check if this column is the active one by comparing IDs
            if (this.isColumnActive(column)) {
                style += ' background-color: #1f2d3d !important; color: white !important;';
            }
            
            return style;
        },
        validateDSDDebounce: _.debounce(function() {
            if (this.columns.length > 0) {
                this.validateDSD();
            }
        }, 1000),
        validateDSD: async function(autoExpand) {
            if (autoExpand === undefined) {
                autoExpand = false;
            }
            this.isValidating = true;
            this.validationResult = null;
            const vm = this;
            let url = CI.base_url + '/api/indicator_dsd/validate/' + vm.dataset_id;

            try {
                let response = await axios.get(url);
                // Handle success response (200) - validation always returns 200
                if (response.data) {
                    vm.validationResult = response.data;
                    if (autoExpand) {
                        vm.$nextTick(function() {
                            vm.dsdMainTab = 1;
                        });
                    }
                }
            } catch (error) {
                // This is an actual error (network, server error, etc.)
                console.error("Validation error:", error);
                var errMsg = (error.response && error.response.data && error.response.data.message) || error.message;
                EventBus.$emit('onFail', 'Failed to validate data structure: ' + errMsg);
            } finally {
                this.isValidating = false;
            }
        },
        importDataOnly: function() {
            this.$router.push({ path: '/data-explorer/INDICATOR_DATA', query: { tab: 'import' } });
        },
        getColumnTypeColor: function(type) {
            const colors = {
                'geography': 'blue darken-1',
                'time_period': 'green darken-1',
                'indicator_id': 'deep-purple',
                'observation_value': 'purple darken-1',
                'dimension': 'teal darken-1',
                'attribute': 'cyan darken-1',
                'annotation': 'blue-grey',
                'periodicity': 'amber darken-2',
                'indicator_name': 'indigo',
                'measure': 'brown'
            };
            return colors[type] || 'blue-grey darken-1';
        }
    },
    computed: {
        isDsdReadOnly: function() {
            return true;
        },
        boundGlobalLabel: function() {
            if (!this.dsdBinding || !this.dsdBinding.global_structure) {
                return '';
            }
            var ds = this.dsdBinding.global_structure;
            return ds.title || ds.name || ds.idno || 'Data structure';
        },
        dataStructuresRegistryUrl: function() {
            return CI.base_url + '/data_structures';
        },
        ProjectID() {
            return this.$store.state.project_id;
        },
        activeColumn: function() {
            if (this.edit_item !== null && this.columns[this.edit_item]) {
                return this.columns[this.edit_item];
            }
            return null;
        },
        filteredColumns: function() {
            let filtered = this.columns;
            if (this.column_search !== '') {
                filtered = filtered.filter((item) => {
                    return (item.name + (item.label || ''))
                        .toUpperCase()
                        .includes(this.column_search.toUpperCase());
                });
            }
            return filtered;
        },
        /** SDMX-oriented list groups (see docs/dsd-codelists-model.md §11) */
        groupedColumns: function() {
            var list = this.filteredColumns;
            var orderIndicator = ['indicator_id', 'indicator_name'];
            var orderTime = ['time_period', 'periodicity'];
            var orderCore = ['observation_value', 'geography'];
            var indicator = [], time = [], core = [], dimensions = [], attributes = [], annotations = [], others = [];
            list.forEach(function(col) {
                var t = col.column_type;
                if (orderIndicator.indexOf(t) >= 0) indicator.push(col);
                else if (orderTime.indexOf(t) >= 0) time.push(col);
                else if (orderCore.indexOf(t) >= 0) core.push(col);
                else if (t === 'dimension' || t === 'measure') dimensions.push(col);
                else if (t === 'attribute') attributes.push(col);
                else if (t === 'annotation') annotations.push(col);
                else others.push(col);
            });
            function sortByOrder(arr, order) {
                arr.sort(function(a, b) {
                    return order.indexOf(a.column_type) - order.indexOf(b.column_type);
                });
            }
            sortByOrder(indicator, orderIndicator);
            sortByOrder(time, orderTime);
            sortByOrder(core, orderCore);
            var groups = [];
            var t = this.$t.bind(this);
            if (indicator.length) {
                groups.push({ groupKey: 'indicator', groupLabel: t('dsd_group_indicator') || 'Indicator', columns: indicator });
            }
            groups.push({
                groupKey: 'time',
                groupLabel: t('dsd_group_time_period') || 'Time period',
                columns: time,
                showEmptyHint: time.length === 0
            });
            if (core.length) {
                groups.push({ groupKey: 'core', groupLabel: t('dsd_group_core') || 'Core fields', columns: core });
            }
            if (dimensions.length) {
                groups.push({ groupKey: 'dimensions', groupLabel: t('dsd_group_dimensions') || 'Dimensions', columns: dimensions });
            }
            if (attributes.length) {
                groups.push({ groupKey: 'attributes', groupLabel: t('dsd_group_attributes') || 'Attributes', columns: attributes });
            }
            if (annotations.length) {
                groups.push({ groupKey: 'annotations', groupLabel: t('dsd_group_annotations') || 'Annotations', columns: annotations });
            }
            if (others.length) {
                groups.push({ groupKey: 'others', groupLabel: t('dsd_group_others') || 'Others', columns: others });
            }
            return groups;
        },
        hasGroupedColumns: function() {
            return this.filteredColumns.length > 0;
        },
        validationStatus: function() {
            if (!this.validationResult) {
                return null;
            }

            const hasErrors = this.validationResult.errors && this.validationResult.errors.length > 0;
            const hasWarnings = this.validationResult.warnings && this.validationResult.warnings.length > 0;

            if (hasErrors) {
                return { color: 'error', icon: 'mdi-alert-circle', status: 'error' };
            } else if (hasWarnings) {
                return { color: 'warning', icon: 'mdi-alert', status: 'warning' };
            } else {
                return { color: 'success', icon: 'mdi-check', status: 'success' };
            }
        },
        validationTabErrorCount: function() {
            if (!this.validationResult || !Array.isArray(this.validationResult.errors)) {
                return 0;
            }
            return this.validationResult.errors.length;
        },
        validationTabWarningCount: function() {
            if (!this.validationResult || !Array.isArray(this.validationResult.warnings)) {
                return 0;
            }
            return this.validationResult.warnings.length;
        },
        /** One-line column summary after validation (e.g. "Columns: 11 · geography: 1, time_period: 1") */
        validationSummaryLine: function() {
            var vr = this.validationResult;
            if (!vr || !vr.summary) {
                return '';
            }
            var n = vr.summary.total_columns;
            var by = vr.summary.by_type;
            var t = this.$t.bind(this);
            var base = (t('columns') || 'Columns') + ': ' + (n != null ? n : '—');
            if (!by || typeof by !== 'object') {
                return base;
            }
            var parts = [];
            Object.keys(by).sort().forEach(function(k) {
                parts.push(k + ': ' + by[k]);
            });
            return parts.length ? base + ' · ' + parts.join(', ') : base;
        },
        validationStructureSection: function() {
            var vr = this.validationResult;
            if (!vr || !vr.structure) {
                return null;
            }
            return vr.structure;
        },
        validationDataSection: function() {
            var vr = this.validationResult;
            if (!vr || !vr.data_validation) {
                return null;
            }
            return vr.data_validation;
        }
    },
    beforeDestroy: function() {
        this.dsdSplitDragEnd(false);
    },
    template: `
        <div class="indicator-dsd-component" style="display: flex; flex-direction: column; height: calc(100vh - 120px); min-height: 0;overflow:hidden;padding:10px;background:white;">
            <!-- Page Title and Actions -->
            <v-card class="mb-2 m-2 p-2" flat>
                <v-card-title class="d-flex justify-space-between align-center">
                    <div class="d-flex align-center">
                        <div>
                            <h4 class="mb-0 d-inline">{{$t("data_structure_definition") || "Data Structure Definition"}}</h4>
                            <small class="text-muted ml-2" v-if="columns.length > 0">{{columns.length}} {{$t("columns") || "columns"}}</small>
                        </div>
                        <!-- Validation status indicator from API -->
                        <v-icon 
                            v-if="validationStatus"
                            :color="validationStatus.color"
                            class="ml-3"
                            small
                        >
                            {{validationStatus.icon}}
                        </v-icon>
                    </div>
                    <div>
                        <v-btn color="primary" class="ml-2" outlined small @click="importDataOnly">
                            <v-icon left small>mdi-upload</v-icon>
                            {{ $t('import_data') || 'Import data' }}
                        </v-btn>
                        <v-btn color="primary" class="ml-2" outlined small @click="openBindGlobalDialog">
                            <v-icon left small>mdi-sitemap</v-icon>
                            Attach data structure
                        </v-btn>
                    </div>
                </v-card-title>
            </v-card>

            <v-alert v-if="isDsdReadOnly" type="info" dense outlined class="mx-2 mb-2">
                Structure is read-only (bound to data structure: <strong>{{ boundGlobalLabel }}</strong>).
                <a :href="dataStructuresRegistryUrl" target="_blank" rel="noopener">Edit in Data Structures</a>
                or change binding below.
            </v-alert>

            <v-tabs v-model="dsdMainTab" class="flex-shrink-0 mx-2 mt-1" background-color="transparent" show-arrows>
                <v-tab>{{ $t('structure') || 'Structure' }}</v-tab>
                <v-tab>
                    <span class="d-flex align-center">
                        {{ $t('validation') || 'Validation' }}
                        <v-chip
                            v-if="validationTabErrorCount > 0"
                            small
                            label
                            class="ml-2"
                            color="error"
                            text-color="white"
                        >{{ validationTabErrorCount }}</v-chip>
                        <v-chip
                            v-else-if="validationTabWarningCount > 0"
                            small
                            label
                            class="ml-2"
                            color="warning"
                            text-color="white"
                        >{{ validationTabWarningCount }}</v-chip>
                        <v-icon v-else-if="validationResult && validationResult.valid" small class="ml-1" color="success">mdi-check</v-icon>
                    </span>
                </v-tab>
            </v-tabs>

            <v-tabs-items v-model="dsdMainTab" class="flex-grow-1 dsd-main-tabs-items fill-height" style="min-height: 0; overflow: hidden; display: flex; flex-direction: column;">
                <v-tab-item class="dsd-tab-structure fill-height" eager>
                    <div class="d-flex flex-column flex-grow-1 fill-height" style="min-height: 0;">
            <!-- Two Column Layout (resizable split) -->
            <div ref="dsdSplitRoot" style="display: flex; flex: 1; min-height: 0; gap: 0; overflow: hidden; background: rgb(240 240 240);" class="m-2 elevation-2 indicator-dsd-split-root">
                <!-- Left Column: column list -->
                <div :style="{ flex: '0 0 ' + dsdSplitListWidth + 'px', width: dsdSplitListWidth + 'px', minWidth: dsdSplitListWidth + 'px', maxWidth: dsdSplitListWidth + 'px', display: 'flex', flexDirection: 'column', border: '1px solid #e0e0e0', borderRadius: '4px', overflow: 'hidden' }">
                    <!-- Search Header -->
                    <div class="pa-1" style="border-bottom: 1px solid #e0e0e0; background: #fff;">
                        <div class="d-flex align-center" style="gap: 8px;">
                            <!-- Search Box -->
                            <v-text-field
                                v-model="column_search"
                                :placeholder="$t('search') || 'Search...'"
                                prepend-inner-icon="mdi-magnify"
                                clearable
                                dense
                                single-line
                                hide-details
                                flat
                                solo
                                style="flex: 1; background-color: transparent !important;"
                                class="mt-0"
                            ></v-text-field>
                            
                            
                            
                        </div>
                    </div>
                    
                    <!-- Actions Header Row -->
                    <div class="pa-1" style="border-bottom: 1px solid #e0e0e0; background: #fff;">
                        <div class="d-flex align-center justify-end" style="gap: 8px;">
                            <v-btn
                                icon
                                small
                                @click="loadColumns"
                                :loading="loading"
                                class="mt-0"
                            >
                                <v-icon small>mdi-refresh</v-icon>
                            </v-btn>
                        </div>
                    </div>

                    <!-- Columns List -->
                    <div style="flex: 1; overflow-y: auto; background: white;padding-bottom:50px;">
                        <div v-if="loading" class="pa-4 text-center">
                            <v-progress-circular indeterminate color="primary"></v-progress-circular>
                            <div class="mt-2">{{$t("loading") || "Loading"}}...</div>
                        </div>
                        <div v-else-if="!hasGroupedColumns" class="pa-4 text-center text-muted">
                            {{$t("no_columns_found") || "No columns found"}}
                        </div>
                        <v-list v-else dense>
                            <template v-for="group in groupedColumns">
                                <v-subheader :key="'h-' + group.groupKey" class="font-weight-bold text-uppercase" style="height: 36px;">
                                    {{group.groupLabel}}
                                </v-subheader>
                                <div
                                    v-if="group.groupKey === 'time' && group.showEmptyHint"
                                    :key="'hint-' + group.groupKey + '-empty'"
                                    class="px-4 pb-2 text-caption text--secondary"
                                >
                                    {{ $t('dsd_time_period_empty_hint') || 'Attach a data structure that includes a time period column.' }}
                                </div>
                                <v-list-item
                                    v-for="column in group.columns"
                                    :key="column.id"
                                    @click="editColumnByColumn(column)"
                                    :class="columnActiveClass(0, column)"
                                    :style="getRowStyle(column)"
                                >
                                <v-list-item-avatar size="32" class="mr-2">
                                    <v-icon 
                                        v-if="column.data_type=='string'"
                                        color="primary"
                                    >mdi-alpha-a-box-outline</v-icon>
                                    <v-icon 
                                        v-else-if="column.data_type=='integer' || column.data_type=='float' || column.data_type=='double'"
                                        color="primary"
                                    >mdi-numeric-1-box-outline</v-icon>
                                    <v-icon 
                                        v-else-if="column.data_type=='date'"
                                        color="primary"
                                    >mdi-calendar</v-icon>
                                    <v-icon 
                                        v-else-if="column.data_type=='boolean'"
                                        color="primary"
                                    >mdi-checkbox-marked</v-icon>
                                    <v-icon 
                                        v-else
                                        color="grey"
                                    >mdi-help-circle</v-icon>
                                </v-list-item-avatar>
                                <v-list-item-content>
                                    <v-list-item-title class="d-flex flex-wrap align-center" style="gap: 6px;">
                                        <span class="font-weight-medium">{{ column.name }}</span>
                                    </v-list-item-title>
                                    <v-list-item-subtitle v-if="column.label">
                                        {{ column.label }}
                                    </v-list-item-subtitle>
                                </v-list-item-content>
                                <v-list-item-action v-if="hasEmptyName(column)">
                                    <v-icon 
                                        color="warning" 
                                        small
                                        :title="$t('column_name_empty') || 'Column name is empty'"
                                    >
                                        mdi-alert
                                    </v-icon>
                                </v-list-item-action>
                                <v-list-item-action>
                                    <v-chip
                                        x-small
                                        label
                                        class="ma-0 font-weight-medium white--text text-capitalize"
                                        :color="getColumnTypeColor(column.column_type)"
                                    >
                                        {{ column.column_type }}
                                    </v-chip>
                                </v-list-item-action>
                                </v-list-item>
                            </template>
                        </v-list>
                    </div>
                </div>

                <div
                    class="indicator-dsd-split-gutter"
                    role="separator"
                    aria-orientation="vertical"
                    :aria-valuenow="dsdSplitListWidth"
                    tabindex="0"
                    :title="$t('resize_panels') || 'Drag to resize panels'"
                    @mousedown="onDsdSplitMouseDown"
                    @keydown.left.prevent="nudgeDsdSplit(-16)"
                    @keydown.right.prevent="nudgeDsdSplit(16)"
                    style="flex: 0 0 6px; width: 6px; cursor: col-resize; background: linear-gradient(to right, #dadada, #ececec); align-self: stretch; min-height: 0; outline: none;"
                ></div>

                <!-- Right Column: Edit Form -->
                <div style="flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; background: white;">
                    <!-- Edit Header -->
                    <div class="pa-2" style="border-bottom: 1px solid #e0e0e0; background: #f5f5f5;">
                        <div class="d-flex justify-space-between align-center">
                            <div>
                                <strong v-if="activeColumn">{{activeColumn.name}}</strong>                                
                            </div>
                            <div v-if="activeColumn">
                                <v-btn 
                                    icon 
                                    small 
                                    @click="colNavigate('first')"
                                    :disabled="edit_item === 0"
                                >
                                    <v-icon small>mdi-chevron-double-left</v-icon>
                                </v-btn>
                                <v-btn 
                                    icon 
                                    small 
                                    @click="colNavigate('prev')"
                                    :disabled="edit_item === 0"
                                >
                                    <v-icon small>mdi-chevron-left</v-icon>
                                </v-btn>
                                <v-btn 
                                    icon 
                                    small 
                                    @click="colNavigate('next')"
                                    :disabled="edit_item >= columns.length - 1"
                                >
                                    <v-icon small>mdi-chevron-right</v-icon>
                                </v-btn>
                                <v-btn 
                                    icon 
                                    small 
                                    @click="colNavigate('last')"
                                    :disabled="edit_item >= columns.length - 1"
                                >
                                    <v-icon small>mdi-chevron-double-right</v-icon>
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Form Content -->
                    <div style="flex: 1; overflow-y: auto; padding: 16px;padding-bottom:50px;">
                        <div v-if="!activeColumn" class="text-center pa-8 text-muted">
                            <v-icon size="64" color="grey lighten-1">mdi-table-column</v-icon>
                            <div class="mt-4">{{$t("no_column_selected") || "No column selected"}}</div>
                        </div>
                        <div v-else>
                            <indicator-dsd-edit 
                                :key="activeColumn && activeColumn.id ? ('dsd-edit-' + activeColumn.id) : 'dsd-edit-new'"
                                :column="activeColumn" 
                                :project-sid="dataset_id"
                                :dictionaries="dsdDictionaries"
                                :all-columns="columns"
                                :global-codelists-list="globalCodelistsList"
                                :global-codelists-loading="globalCodelistsLoading"
                                :read-only="isDsdReadOnly"
                                :index_key="edit_item"
                            ></indicator-dsd-edit>
                        </div>
                    </div>
                </div>
            </div>
                    </div>
                </v-tab-item>
                <v-tab-item eager class="dsd-tab-validation">
                    <div
                        class="d-flex flex-column pa-3 ma-2"
                        style="max-height: calc(100vh - 300px); overflow-y: auto; overflow-x: hidden; background: #f5f5f5; border-radius: 8px; -webkit-overflow-scrolling: touch;"
                    >
                        <div class="d-flex align-center justify-space-between flex-wrap flex-shrink-0 mb-3" style="gap: 8px;">
                            <span class="text-h6 font-weight-medium">{{ $t('validation') || 'Validation' }}</span>
                            <v-btn
                                color="primary"
                                depressed
                                :loading="isValidating"
                                :disabled="columns.length === 0"
                                small
                                @click="validateDSD(false)"
                            >
                                <v-icon left small>mdi-refresh</v-icon>
                                {{ $t('validate') || 'Run validation' }}
                            </v-btn>
                        </div>

                        <div v-if="!validationResult && !isValidating" class="text-center pa-10">
                            <v-icon size="48" color="grey lighten-1">mdi-clipboard-text-outline</v-icon>
                            <p class="mt-3 mb-0 text-body-2 text--secondary">{{ $t('dsd_validation_tab_empty') || 'Run validation to see structure and data checks.' }}</p>
                        </div>
                        <div v-else-if="isValidating" class="text-center pa-10">
                            <v-progress-circular indeterminate color="primary" size="40"></v-progress-circular>
                            <div class="mt-3 text-body-2 text--secondary">{{ $t('loading') || 'Loading' }}…</div>
                        </div>

                        <div v-else-if="validationResult" class="d-flex flex-column" style="gap: 16px;">
                            <!-- Overall -->
                            <div
                                class="pa-3 rounded d-flex align-start"
                                style="gap: 12px; border: 1px solid rgba(0,0,0,.08);"
                                :style="validationResult.valid ? 'background: #e8f5e9; border-color: rgba(46,125,50,.25);' : 'background: #ffebee; border-color: rgba(211,47,47,.22);'"
                            >
                                <v-icon :color="validationResult.valid ? 'success' : 'error'" class="flex-shrink-0 mt-0">
                                    {{ validationResult.valid ? 'mdi-check-circle' : 'mdi-alert-circle' }}
                                </v-icon>
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <div class="subtitle-1 font-weight-medium" :class="validationResult.valid ? 'success--text' : 'error--text'">
                                        {{ validationResult.valid ? ($t('validation_passed') || 'All checks passed') : ($t('validation_failed') || 'Some checks failed') }}
                                    </div>
                                    <div v-if="validationSummaryLine" class="text-caption text--secondary mt-1 text-wrap">
                                        {{ validationSummaryLine }}
                                    </div>
                                </div>
                            </div>

                            <!-- 1. Structure -->
                            <v-card v-if="validationStructureSection" outlined flat class="white" style="border-radius: 8px;">
                                <v-card-title class="py-3 subtitle-1 font-weight-medium d-flex align-center flex-wrap" style="gap: 8px; border-bottom: 1px solid rgba(0,0,0,.06);">
                                    <v-icon color="primary" class="mr-1">mdi-file-tree-outline</v-icon>
                                    {{ $t('structure') || 'Structure' }}
                                    <v-spacer></v-spacer>
                                    <v-chip
                                        small
                                        label
                                        :color="validationStructureSection.valid ? 'success' : 'error'"
                                        text-color="white"
                                        class="font-weight-medium"
                                    >
                                        <v-icon left color="white">{{ validationStructureSection.valid ? 'mdi-check-circle' : 'mdi-close-circle' }}</v-icon>
                                        {{ validationStructureSection.valid ? ($t('validation_passed') || 'Passed') : ($t('validation_failed') || 'Failed') }}
                                    </v-chip>
                                </v-card-title>
                                <v-card-text class="pt-3 pb-3">
                                    <template v-if="validationStructureSection.errors && validationStructureSection.errors.length">
                                        <div class="text-overline text--secondary mb-2">{{ $t('errors') || 'Errors' }}</div>
                                        <div
                                            v-for="(error, idx) in validationStructureSection.errors"
                                            :key="'vse-' + idx"
                                            class="d-flex align-start text-body-2 error--text mb-2"
                                            style="gap: 8px;"
                                        >
                                            <v-icon color="error" small class="flex-shrink-0 mt-1">mdi-close-circle</v-icon>
                                            <span class="text-wrap" style="min-width: 0;">{{ error }}</span>
                                        </div>
                                    </template>
                                    <template v-if="validationStructureSection.warnings && validationStructureSection.warnings.length">
                                        <div class="text-overline text--secondary mb-2 mt-3">{{ $t('warnings') || 'Warnings' }}</div>
                                        <div
                                            v-for="(warning, idx) in validationStructureSection.warnings"
                                            :key="'vsw-' + idx"
                                            class="d-flex align-start text-body-2 warning--text mb-2"
                                            style="gap: 8px;"
                                        >
                                            <v-icon color="warning" small class="flex-shrink-0 mt-1">mdi-alert</v-icon>
                                            <span class="text-wrap" style="min-width: 0;">{{ warning }}</span>
                                        </div>
                                    </template>
                                    <div
                                        v-if="validationStructureSection.valid && (!validationStructureSection.errors || !validationStructureSection.errors.length) && (!validationStructureSection.warnings || !validationStructureSection.warnings.length)"
                                        class="text-body-2 text--secondary"
                                    >
                                        {{ $t('dsd_validation_structure_ok') || 'Column roles and cardinality match the rules for this project.' }}
                                    </div>
                                </v-card-text>
                            </v-card>

                            <!-- 2. Data -->
                            <v-card v-if="validationDataSection" outlined flat class="white" style="border-radius: 8px;">
                                <v-card-title class="py-3 subtitle-1 font-weight-medium d-flex align-center flex-wrap" style="gap: 8px; border-bottom: 1px solid rgba(0,0,0,.06);">
                                    <v-icon color="primary" class="mr-1">mdi-database-check-outline</v-icon>
                                    {{ $t('dsd_validation_section_data') || 'Data validation' }}
                                    <v-spacer></v-spacer>
                                    <v-chip
                                        v-if="validationDataSection.skipped"
                                        small
                                        label
                                        color="grey"
                                        text-color="white"
                                        class="font-weight-medium"
                                    >
                                        <v-icon left color="white">mdi-minus-circle-outline</v-icon>
                                        {{ $t('skipped') || 'Skipped' }}
                                    </v-chip>
                                    <v-chip
                                        v-else
                                        small
                                        label
                                        :color="validationDataSection.valid ? 'success' : 'error'"
                                        text-color="white"
                                        class="font-weight-medium"
                                    >
                                        <v-icon left color="white">{{ validationDataSection.valid ? 'mdi-check-circle' : 'mdi-close-circle' }}</v-icon>
                                        {{ validationDataSection.valid ? ($t('validation_passed') || 'Passed') : ($t('validation_failed') || 'Failed') }}
                                    </v-chip>
                                </v-card-title>
                                <v-card-text class="pt-3 pb-3">
                                    <div v-if="validationDataSection.skipped" class="text-body-2 text--secondary">
                                        {{ validationDataSection.reason }}
                                    </div>
                                    <template v-else>
                                        <div v-if="validationDataSection.source || validationDataSection.row_count != null" class="text-caption text--secondary mb-3">
                                            <span v-if="validationDataSection.source">{{ $t('source') || 'Source' }}: <strong class="text--primary">{{ validationDataSection.source }}</strong></span>
                                            <span v-if="validationDataSection.row_count != null">
                                                <span v-if="validationDataSection.source"> · </span>{{ validationDataSection.row_count }} {{ $t('rows') || 'rows' }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="validationDataSection.observation_key"
                                            class="pa-3 mb-3 rounded"
                                            style="background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.06);"
                                        >
                                            <div v-if="validationDataSection.observation_key.skipped" class="text-body-2 text--secondary">
                                                {{ validationDataSection.observation_key.reason }}
                                            </div>
                                            <template v-else>
                                                <div v-if="validationDataSection.observation_key.key_columns && validationDataSection.observation_key.key_columns.length" class="text-body-2 mb-2">
                                                    <span class="text--secondary">{{ $t('key') || 'Key' }}: </span>
                                                    <span class="text-wrap">{{ formatObservationKeyColumns(validationDataSection.observation_key.key_columns) }}</span>
                                                </div>
                                                <div v-if="validationDataSection.observation_key.value_column" class="text-body-2 mb-2">
                                                    <span class="text--secondary">{{ $t('value') || 'Value' }}: </span>
                                                    {{ validationDataSection.observation_key.value_column.dsd_name }}
                                                    <span class="text--secondary"> — {{ validationDataSection.observation_key.value_column.physical_name }}</span>
                                                </div>
                                                <div class="text-body-2">
                                                    <span v-if="validationDataSection.observation_key.rows_with_observation_value != null">
                                                        {{ $t('dsd_validation_rows_with_value') || 'Rows with value' }}: <strong>{{ validationDataSection.observation_key.rows_with_observation_value }}</strong>
                                                    </span>
                                                    <span v-if="validationDataSection.observation_key.unique_observation_count != null" class="ml-2">
                                                        · {{ $t('dsd_validation_unique_observations') || 'Unique keys' }}: <strong>{{ validationDataSection.observation_key.unique_observation_count }}</strong>
                                                    </span>
                                                    <span v-if="validationDataSection.observation_key.table_rows_read != null" class="ml-2 text--secondary">
                                                        · {{ $t('dsd_validation_rows_scanned') || 'Counted' }}: {{ validationDataSection.observation_key.table_rows_read }}
                                                    </span>
                                                </div>
                                                <div v-if="validationDataSection.observation_key.scan_truncated" class="text-caption warning--text mt-2">
                                                    {{ $t('dsd_validation_observation_key_truncated') || 'Counts may be incomplete (scan truncated).' }}
                                                </div>
                                            </template>
                                        </div>

                                        <template v-if="validationDataSection.errors && validationDataSection.errors.length">
                                            <div class="text-overline text--secondary mb-2">{{ $t('data_errors') || 'Data errors' }}</div>
                                            <div
                                                v-for="(error, idx) in validationDataSection.errors"
                                                :key="'vde-' + idx"
                                                class="d-flex align-start text-body-2 error--text mb-2"
                                                style="gap: 8px;"
                                            >
                                                <v-icon color="error" small class="flex-shrink-0 mt-1">mdi-close-circle</v-icon>
                                                <span class="text-wrap" style="min-width: 0;">{{ error }}</span>
                                            </div>
                                        </template>
                                        <template v-if="validationDataSection.warnings && validationDataSection.warnings.length">
                                            <div class="text-overline text--secondary mb-2 mt-3">{{ $t('data_warnings') || 'Data warnings' }}</div>
                                            <div
                                                v-for="(warning, idx) in validationDataSection.warnings"
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
                        </div>
                    </div>
                </v-tab-item>
            </v-tabs-items>

            <v-dialog v-model="bindGlobalDialog" max-width="560" persistent>
                <v-card>
                    <v-card-title>{{ isDsdReadOnly ? 'Change data structure' : 'Attach data structure' }}</v-card-title>
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
                            outlined dense
                            :loading="globalStructuresLoading"
                            clearable
                        ></v-select>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text :disabled="bindingGlobal" @click="bindGlobalDialog = false">Cancel</v-btn>
                        <v-btn color="primary" :loading="bindingGlobal" :disabled="!bindStructureId" @click="submitBindGlobal">Attach</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
})
