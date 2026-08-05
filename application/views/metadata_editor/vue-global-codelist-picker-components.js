// Reusable global (registry) codelist picker: dialog grid + compact link field.
// GET /api/codelists — search, page, per_page, exclude_archived, flat, with_counts
// GET /api/codelists/single/{id} — resolve linked row for display

function globalCodelistsApiBase() {
    var base = '';
    if (typeof CI !== 'undefined') {
        if (CI.base_url) {
            base = String(CI.base_url).replace(/\/$/, '');
        } else if (CI.site_url) {
            base = String(CI.site_url).replace(/\/$/, '');
        }
    }
    return base + '/api/codelists';
}

function globalCodelistItemText(cl) {
    if (!cl) {
        return '';
    }
    var title = (cl.title && String(cl.title).trim()) || '';
    var agency = String(cl.agency || '');
    var name = String(cl.name || '');
    var suffix = agency && name ? ' (' + agency + ':' + name + ')' : '';
    return (title || name || String(cl.id)) + suffix;
}

Vue.component('global-codelist-picker-dialog', {
    props: {
        value: {
            type: Boolean,
            default: false
        },
        subtitle: {
            type: String,
            default: ''
        },
        highlightId: {
            default: null
        },
        flat: {
            type: Boolean,
            default: false
        },
        perPageDefault: {
            type: Number,
            default: 50
        },
        titleText: {
            type: String,
            default: 'Link global codelist'
        }
    },
    data: function () {
        return {
            search: '',
            page: 1,
            perPage: this.perPageDefault,
            items: [],
            total: 0,
            loading: false,
            loadError: null,
            requestSeq: 0,
            searchDebounced: null,
            pendingItem: null
        };
    },
    computed: {
        dialogVisible: {
            get: function () {
                return this.value;
            },
            set: function (v) {
                this.$emit('input', v);
            }
        },
        headers: function () {
            return [
                { text: 'Title', value: 'title', sortable: false },
                { text: 'Name', value: 'name', sortable: false, width: '140px' },
                { text: 'Agency', value: 'agency', sortable: false, width: '100px' },
                { text: 'Version', value: 'version', sortable: false, width: '90px' },
                { text: 'Items', value: 'item_count', sortable: false, width: '72px', align: 'end' }
            ];
        },
        pageCount: function () {
            if (!this.total || !this.perPage) {
                return 0;
            }
            return Math.ceil(this.total / this.perPage);
        },
        footerProps: function () {
            return {
                'items-per-page-options': [25, 50, 100, 200]
            };
        },
        pendingSelectionLabel: function () {
            if (!this.pendingItem) {
                return '';
            }
            return globalCodelistItemText(this.pendingItem);
        },
        canConfirm: function () {
            return !!(this.pendingItem && this.pendingItem.id != null);
        }
    },
    watch: {
        value: function (open) {
            if (open) {
                this.onOpen();
            }
        }
    },
    mounted: function () {
        var vm = this;
        vm.searchDebounced = _.debounce(function () {
            if (!vm.value) {
                return;
            }
            vm.page = 1;
            vm.loadList();
        }, 300);
    },
    beforeDestroy: function () {
        if (this.searchDebounced && this.searchDebounced.cancel) {
            this.searchDebounced.cancel();
        }
    },
    methods: {
        onOpen: function () {
            this.search = '';
            this.page = 1;
            this.perPage = this.perPageDefault;
            this.items = [];
            this.total = 0;
            this.loadError = null;
            this.pendingItem = null;
            this.loadList();
        },
        close: function () {
            this.dialogVisible = false;
            this.$emit('cancel');
        },
        onSearchInput: function () {
            if (this.searchDebounced) {
                this.searchDebounced();
            } else {
                this.page = 1;
                this.loadList();
            }
        },
        onPageChange: function () {
            this.loadList();
        },
        onPerPageChange: function () {
            this.page = 1;
            this.loadList();
        },
        loadList: function () {
            var vm = this;
            var requestId = ++vm.requestSeq;
            vm.loading = true;
            vm.loadError = null;
            var params = {
                page: vm.page,
                per_page: vm.perPage,
                order_by: 'name',
                order_dir: 'ASC',
                exclude_archived: 1,
                with_counts: 1
            };
            if (vm.flat) {
                params.flat = 1;
            }
            var q = (vm.search || '').trim();
            if (q.length >= 1) {
                params.search = q;
            }
            axios.get(globalCodelistsApiBase(), { params: params, timeout: 30000 })
                .then(function (res) {
                    if (requestId !== vm.requestSeq || !vm.value) {
                        return;
                    }
                    vm.loading = false;
                    if (res.data && res.data.status === 'success') {
                        vm.items = res.data.codelists || [];
                        vm.total = res.data.total != null ? parseInt(res.data.total, 10) : vm.items.length;
                        if (isNaN(vm.total)) {
                            vm.total = vm.items.length;
                        }
                        vm.syncPendingFromHighlight();
                    } else {
                        vm.items = [];
                        vm.total = 0;
                        vm.loadError = (res.data && res.data.message) ? res.data.message : 'Could not load codelists.';
                    }
                })
                .catch(function (err) {
                    if (requestId !== vm.requestSeq) {
                        return;
                    }
                    vm.loading = false;
                    vm.items = [];
                    vm.total = 0;
                    var msg = 'Could not load codelists.';
                    if (err.code === 'ECONNABORTED') {
                        msg = 'Codelist search timed out. Try a shorter search term.';
                    } else if (err.response && err.response.data && err.response.data.message) {
                        msg = err.response.data.message;
                    }
                    vm.loadError = msg;
                });
        },
        syncPendingFromHighlight: function () {
            if (this.pendingItem) {
                return;
            }
            var hid = this.highlightId;
            if (hid == null || hid === '') {
                return;
            }
            var found = null;
            for (var i = 0; i < this.items.length; i++) {
                if (String(this.items[i].id) === String(hid)) {
                    found = this.items[i];
                    break;
                }
            }
            this.pendingItem = found || { id: hid };
        },
        rowClass: function (item) {
            if (!item || !this.pendingItem || this.pendingItem.id == null) {
                return '';
            }
            if (String(item.id) === String(this.pendingItem.id)) {
                return 'global-codelist-picker-row--selected';
            }
            return '';
        },
        onRowClick: function (item) {
            if (!item || item.id == null) {
                return;
            }
            this.pendingItem = item;
        },
        confirmSelection: function () {
            if (!this.canConfirm) {
                return;
            }
            var id = this.pendingItem.id;
            var full = this.pendingItem;
            for (var i = 0; i < this.items.length; i++) {
                if (String(this.items[i].id) === String(id)) {
                    full = this.items[i];
                    break;
                }
            }
            this.$emit('select', full);
            this.dialogVisible = false;
        }
    },
    template: `
    <v-dialog v-model="dialogVisible" max-width="820" persistent content-class="global-codelist-picker-dialog">
        <v-card class="global-codelist-picker-card d-flex flex-column">
            <v-card-title class="text-subtitle-1 py-3 flex-shrink-0">
                {{ titleText }}
                <span v-if="subtitle" class="text-caption grey--text ml-2">({{ subtitle }})</span>
                <v-spacer></v-spacer>
                <v-btn icon small @click="close"><v-icon>mdi-close</v-icon></v-btn>
            </v-card-title>
            <v-divider></v-divider>
            <div class="global-codelist-picker-body d-flex flex-column flex-grow-1">
                <div class="global-codelist-picker-search flex-shrink-0">
                    <v-text-field
                        v-model="search"
                        dense outlined hide-details clearable
                        prepend-inner-icon="mdi-magnify"
                        append-icon="mdi-arrow-right"
                        placeholder="Search by title, name, or agency…"
                        class="global-codelist-picker-search-field mb-0"
                        @keyup.enter="page = 1; loadList()"
                        @click:append="page = 1; loadList()"
                        @click:clear="page = 1; loadList()"
                        @input="onSearchInput"
                    ></v-text-field>
                </div>
                <div class="global-codelist-picker-content px-4 pb-2 d-flex flex-column flex-grow-1 min-height-0">
                <div v-if="loadError" class="text-caption error--text mb-2 flex-shrink-0">{{ loadError }}</div>
                <div class="text-caption grey--text mb-2 flex-shrink-0">
                    <span v-if="loading">Loading…</span>
                    <span v-else-if="total === 0">No codelists found.</span>
                    <span v-else>
                        {{ total }} codelist(s)
                        <span v-if="pageCount > 1"> · page {{ page }} of {{ pageCount }}</span>
                    </span>
                </div>
                <div class="global-codelist-picker-table-wrap elevation-1">
                    <v-data-table
                        :headers="headers"
                        :items="items"
                        :loading="loading"
                        :server-items-length="total"
                        :page.sync="page"
                        :items-per-page.sync="perPage"
                        :footer-props="footerProps"
                        item-key="id"
                        dense
                        mobile-breakpoint="0"
                        class="global-codelist-picker-table"
                        :item-class="rowClass"
                        @update:page="onPageChange"
                        @update:items-per-page="onPerPageChange"
                        @click:row="onRowClick"
                    >
                        <template v-slot:item.title="{ item }">
                            <span class="text-caption">{{ item.title || item.name || '—' }}</span>
                        </template>
                        <template v-slot:item.name="{ item }">
                            <code class="text-caption">{{ item.name || '—' }}</code>
                        </template>
                        <template v-slot:item.agency="{ item }">
                            <span class="text-caption">{{ item.agency || '—' }}</span>
                        </template>
                        <template v-slot:item.version="{ item }">
                            <span class="text-caption">{{ item.version || '—' }}</span>
                        </template>
                        <template v-slot:item.item_count="{ item }">
                            <span class="text-caption">{{ item.item_count != null ? item.item_count : '—' }}</span>
                        </template>
                        <template v-slot:no-data>
                            <div class="text-caption grey--text pa-4 text-center">No codelists found.</div>
                        </template>
                    </v-data-table>
                </div>
                </div>
            </div>
            <v-divider class="flex-shrink-0"></v-divider>
            <v-card-actions class="pa-3 flex-shrink-0 global-codelist-picker-actions">
                <div v-if="pendingSelectionLabel" class="text-caption grey--text text-truncate global-codelist-picker-pending-label">
                    Selected: {{ pendingSelectionLabel }}
                </div>
                <v-spacer></v-spacer>
                <v-btn text small class="global-codelist-picker-cancel-btn" @click="close">Cancel</v-btn>
                <v-btn
                    small
                    class="global-codelist-picker-confirm-btn white--text"
                    color="primary"
                    :disabled="!canConfirm"
                    @click="confirmSelection"
                >
                    Link codelist
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    `
});

Vue.component('global-codelist-link-field', {
    props: {
        value: {
            default: null
        },
        disabled: {
            type: Boolean,
            default: false
        },
        subtitle: {
            type: String,
            default: ''
        },
        showPathWarning: {
            type: Boolean,
            default: false
        },
        label: {
            type: String,
            default: ''
        },
        codesMaxTableHeight: {
            type: String,
            default: ''
        }
    },
    data: function () {
        return {
            pickerOpen: false,
            linkedRow: null,
            resolveLoading: false,
            resolveError: null,
            resolveSeq: 0
        };
    },
    computed: {
        registryId: function () {
            var v = this.value;
            if (v === null || v === undefined || v === '') {
                return null;
            }
            var n = parseInt(v, 10);
            return (!isNaN(n) && n > 0) ? n : null;
        },
        hasLink: function () {
            return this.registryId != null;
        },
        displayLabel: function () {
            if (this.linkedRow) {
                return globalCodelistItemText(this.linkedRow);
            }
            if (this.registryId != null) {
                return 'Codelist #' + this.registryId;
            }
            return '';
        },
        identitySuffix: function () {
            var cl = this.linkedRow;
            if (!cl) {
                return '';
            }
            var agency = String(cl.agency || '');
            var name = String(cl.name || '');
            if (agency && name) {
                return agency + ':' + name;
            }
            return name || '';
        },
        itemCountLabel: function () {
            var cl = this.linkedRow;
            if (!cl || cl.item_count == null) {
                return '';
            }
            return String(cl.item_count) + ' items';
        }
    },
    watch: {
        registryId: {
            immediate: true,
            handler: function () {
                this.resolveLinked();
            }
        }
    },
    methods: {
        openPicker: function () {
            if (this.disabled) {
                return;
            }
            this.pickerOpen = true;
        },
        onPickerSelect: function (cl) {
            if (!cl || cl.id == null) {
                return;
            }
            var n = parseInt(cl.id, 10);
            if (isNaN(n) || n <= 0) {
                return;
            }
            this.linkedRow = cl;
            this.resolveError = null;
            this.$emit('input', n);
            this.$emit('change');
        },
        removeLink: function () {
            if (this.disabled) {
                return;
            }
            this.linkedRow = null;
            this.resolveError = null;
            this.$emit('input', null);
            this.$emit('change');
        },
        resolveLinked: function () {
            var vm = this;
            var seq = ++vm.resolveSeq;
            var pk = vm.registryId;
            if (pk == null) {
                vm.linkedRow = null;
                vm.resolveLoading = false;
                vm.resolveError = null;
                return;
            }
            if (vm.linkedRow && String(vm.linkedRow.id) === String(pk)) {
                vm.resolveLoading = false;
                return;
            }
            vm.resolveLoading = true;
            vm.resolveError = null;
            axios.get(globalCodelistsApiBase() + '/single/' + pk, { timeout: 30000 })
                .then(function (res) {
                    if (seq !== vm.resolveSeq) {
                        return;
                    }
                    var cl = res.data && res.data.codelist;
                    if (cl && cl.id != null) {
                        vm.linkedRow = cl;
                    } else {
                        vm.linkedRow = { id: pk, title: '', agency: '', name: '' };
                    }
                })
                .catch(function () {
                    if (seq !== vm.resolveSeq) {
                        return;
                    }
                    vm.linkedRow = { id: pk, title: '', agency: '', name: '' };
                    vm.resolveError = 'Could not load codelist details.';
                })
                .then(function () {
                    if (seq === vm.resolveSeq) {
                        vm.resolveLoading = false;
                    }
                });
        }
    },
    template: `
    <div class="global-codelist-link-field">
        <label v-if="label" class="mb-1 d-block">{{ label }}</label>
        <div v-if="hasLink" class="global-codelist-link-summary border rounded pa-2 mb-2 bg-white d-flex align-center">
            <div class="global-codelist-link-summary-text flex-grow-1 min-width-0 pr-2">
                <div v-if="resolveLoading" class="text-caption grey--text">Loading codelist…</div>
                <template v-else>
                    <div class="font-weight-medium text-body-2 text-truncate">{{ displayLabel }}</div>
                    <div v-if="identitySuffix || itemCountLabel" class="text-caption grey--text text-truncate">
                        <span v-if="identitySuffix">{{ identitySuffix }}</span>
                        <span v-if="identitySuffix && itemCountLabel"> · </span>
                        <span v-if="itemCountLabel">{{ itemCountLabel }}</span>
                    </div>
                    <div v-if="resolveError" class="text-caption error--text">{{ resolveError }}</div>
                </template>
            </div>
            <div class="global-codelist-link-summary-actions d-flex flex-shrink-0 align-center">
                <v-btn small outlined color="primary" :disabled="disabled" @click="openPicker">
                    Change
                </v-btn>
                <v-btn small text color="error" :disabled="disabled" class="ml-1" @click="removeLink">
                    Remove
                </v-btn>
            </div>
        </div>
        <div v-else class="mb-2">
            <v-btn small color="primary" depressed :disabled="disabled" @click="openPicker">
                Choose codelist
            </v-btn>
        </div>
        <div v-if="showPathWarning" class="text-warning font-small mt-2">
            Set a valid schema path (prop_key) on this field so the codelist link can be saved.
        </div>
        <global-codelist-picker-dialog
            v-model="pickerOpen"
            :subtitle="subtitle"
            :highlight-id="registryId"
            @select="onPickerSelect"
        ></global-codelist-picker-dialog>
        <global-codelist-codes-grid
            v-if="hasLink && registryId"
            class="mt-3 global-codelist-link-field-codes"
            :registry-codelist-id="registryId"
            :title="displayLabel"
            :max-table-height="codesMaxTableHeight"
            :page-size-default="25"
        ></global-codelist-codes-grid>
    </div>
    `
});
