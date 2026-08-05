Vue.component('vue-dialog-enum-selection-component', {
    props: ['value', 'enums', 'columns', 'selected_enum', 'field'],
    data: function () {
        return {
            selected: [],
            headers: [],
            search: '',
            table_options: {
                itemsPerPage: -1
            },
            registryPage: 1,
            registryPerPage: 25,
            registryTotal: 0,
            registryRows: [],
            registryLoading: false,
            registryLoadError: null,
            registryFetchSeq: 0,
            registryTableOptions: {
                page: 1,
                itemsPerPage: 25
            }
        };
    },
    mounted: function () {
        this.initColumnHeaders();
        var vm = this;
        vm.registrySearchDebounced = _.debounce(function () {
            vm.registryPage = 1;
            vm.registryTableOptions = Object.assign({}, vm.registryTableOptions, { page: 1 });
            vm.loadRegistryPage();
        }, 350);
    },
    beforeDestroy: function () {
        this.registryFetchSeq += 1;
        if (this.registrySearchDebounced && this.registrySearchDebounced.cancel) {
            this.registrySearchDebounced.cancel();
        }
    },
    watch: {
        dialog: function (open) {
            if (open && this.useRegistryPicker) {
                this.resetRegistryPicker();
                this.initColumnHeaders();
                this.loadRegistryPage();
            }
            if (!open) {
                this.selected = [];
            }
        },
        columns: {
            deep: true,
            handler: function () {
                this.initColumnHeaders();
            }
        },
        registryTableOptions: {
            deep: true,
            handler: function (opts) {
                if (!this.useRegistryPicker || !this.dialog) {
                    return;
                }
                var page = opts && opts.page ? opts.page : 1;
                var perPage = opts && opts.itemsPerPage ? opts.itemsPerPage : this.registryPerPage;
                if (perPage === -1) {
                    perPage = 25;
                }
                if (page !== this.registryPage || perPage !== this.registryPerPage) {
                    this.registryPage = page;
                    this.registryPerPage = perPage;
                    this.loadRegistryPage();
                }
            }
        }
    },
    methods: {
        initColumnHeaders: function () {
            this.headers = [];
            var cols = this.registryTableColumns;
            if (!cols || !cols.length) {
                return;
            }
            for (var i = 0; i < cols.length; i++) {
                var col = cols[i];
                this.headers.push({ text: col.title || col.key, value: col.key });
            }
        },
        resetRegistryPicker: function () {
            this.search = '';
            this.registryPage = 1;
            this.registryPerPage = 25;
            this.registryTotal = 0;
            this.registryRows = [];
            this.registryLoadError = null;
            this.registryTableOptions = { page: 1, itemsPerPage: 25 };
        },
        onRegistrySearchInput: function () {
            if (this.registrySearchDebounced) {
                this.registrySearchDebounced();
            } else {
                this.registryPage = 1;
                this.loadRegistryPage();
            }
        },
        loadRegistryPage: function () {
            var vm = this;
            if (!vm.useRegistryPicker || !vm.field) {
                return;
            }
            var seq = ++vm.registryFetchSeq;
            vm.registryLoading = true;
            vm.registryLoadError = null;
            var opts = {
                offset: (vm.registryPage - 1) * vm.registryPerPage,
                limit: vm.registryPerPage,
                search: vm.search
            };
            var done = function (result) {
                if (seq !== vm.registryFetchSeq) {
                    return;
                }
                vm.registryLoading = false;
                vm.registryTotal = result.total || 0;
                vm.registryRows = result.rows || result.items || [];
            };
            var fail = function () {
                if (seq !== vm.registryFetchSeq) {
                    return;
                }
                vm.registryLoading = false;
                vm.registryRows = [];
                vm.registryTotal = 0;
                vm.registryLoadError = 'Could not load codes.';
            };
            if (vm.registryPickerMode === 'scalar' && typeof fetchGlobalScalarEnumPage === 'function') {
                fetchGlobalScalarEnumPage(vm.field, opts).then(function (result) {
                    done({ total: result.total, rows: result.items });
                }).catch(fail);
                return;
            }
            if (typeof fetchGlobalEnumRowsPage === 'function') {
                fetchGlobalEnumRowsPage(vm.field, opts).then(done).catch(fail);
                return;
            }
            fail();
        },
        addSelection: function () {
            var items = this.selectedItems;
            this.$emit('selection', items);
            this.dialog = false;
            this.selected = [];
        }
    },
    computed: {
        dialog: {
            get: function () {
                return this.value;
            },
            set: function (val) {
                this.$emit('input', val);
            }
        },
        registryPickerMode: function () {
            if (typeof fieldVocabularySourceGlobal !== 'function' || !this.field) {
                return null;
            }
            if (!fieldVocabularySourceGlobal(this.field)) {
                return null;
            }
            var registryId = typeof fieldGlobalCodelistRegistryId === 'function'
                ? fieldGlobalCodelistRegistryId(this.field)
                : null;
            if (!registryId) {
                return null;
            }
            if (typeof isFlatObjectArrayField === 'function' && isFlatObjectArrayField(this.field)) {
                var map = typeof getGlobalCodelistPropMap === 'function'
                    ? getGlobalCodelistPropMap(this.field)
                    : {};
                return map.code && map.label ? 'array' : null;
            }
            if (typeof fieldUsesGlobalScalarCodelist === 'function' && fieldUsesGlobalScalarCodelist(this.field)) {
                return 'scalar';
            }
            return null;
        },
        useRegistryPicker: function () {
            return this.registryPickerMode === 'array' || this.registryPickerMode === 'scalar';
        },
        registrySingleSelect: function () {
            return this.registryPickerMode === 'scalar';
        },
        registryScalarColumns: function () {
            return [
                { key: 'code', title: 'Code' },
                { key: 'label', title: 'Label' }
            ];
        },
        registryTableColumns: function () {
            if (this.useRegistryPicker && this.registryPickerMode === 'scalar') {
                return this.registryScalarColumns;
            }
            return this.columns;
        },
        items: function () {
            var items = [];
            if (this.useRegistryPicker) {
                return this.registryRows;
            }
            if (this.enums == null) {
                return items;
            }
            for (var i = 0; i < this.enums.length; i++) {
                var item = this.enums[i];
                if (!item || typeof item !== 'object') {
                    continue;
                }
                var copy = JSON.parse(JSON.stringify(item));
                copy.index__ = i;
                if (!copy.__rowKey) {
                    copy.__rowKey = 'inline-' + i;
                }
                items.push(copy);
            }
            return items;
        },
        selectedItems: function () {
            var items = [];
            for (var i = 0; i < this.selected.length; i++) {
                var item = this.selected[i];
                if (typeof stripEnumPickerMeta === 'function') {
                    items.push(stripEnumPickerMeta(item));
                } else {
                    var copy = JSON.parse(JSON.stringify(item));
                    delete copy.index__;
                    delete copy.__rowKey;
                    items.push(copy);
                }
            }
            return items;
        },
        registryFooterProps: function () {
            return { 'items-per-page-options': [10, 25, 50, 100] };
        }
    },
    template: `
        <div class="vue-dialog-enum-selection-component">

            <v-dialog v-model="dialog" max-width="700" persistent scrollable content-class="vue-dialog-enum-selection-dialog">
                <v-card>
                    <v-card-title class="text-subtitle-1 py-3 grey lighten-4">
                        {{ useRegistryPicker ? ($t('pick_from_codelist') || 'Pick from codelist') : ($t('pick_from_list') || 'Pick from list') }}
                    </v-card-title>
                    <v-card-text class="pt-3 pb-0">
                        <v-text-field
                            v-model="search"
                            append-icon="mdi-magnify"
                            :label="$t('search') || 'Search'"
                            single-line
                            dense
                            outlined
                            hide-details
                            class="mb-3"
                            @input="useRegistryPicker ? onRegistrySearchInput() : null"
                        ></v-text-field>

                        <v-data-table
                            v-if="useRegistryPicker"
                            v-model="selected"
                            :headers="headers"
                            :items="items"
                            :server-items-length="registryTotal"
                            :options.sync="registryTableOptions"
                            :loading="registryLoading"
                            :footer-props="registryFooterProps"
                            :single-select="registrySingleSelect"
                            item-key="__rowKey"
                            show-select
                            class="elevation-0 enum-selection-registry-table"
                            fixed-header
                            height="320px"
                        ></v-data-table>

                        <v-data-table
                            v-else
                            v-model="selected"
                            :headers="headers"
                            :items="items"
                            item-key="__rowKey"
                            show-select
                            class="elevation-1"
                            hide-default-header
                            hide-default-footer
                            :search="search"
                            :options="table_options"
                            fixed-header
                            height="320px"
                        ></v-data-table>

                        <div v-if="registryLoadError" class="text-caption error--text mt-2">{{ registryLoadError }}</div>
                    </v-card-text>

                    <v-card-actions class="pa-3">
                        <v-spacer></v-spacer>
                        <v-btn text small @click="dialog = false">
                            {{ $t('cancel') || 'Cancel' }}
                        </v-btn>
                        <v-btn color="primary" text small @click="addSelection" :disabled="!selected.length">
                            {{ $t('apply') || 'Apply' }}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

        </div>
    `
});
