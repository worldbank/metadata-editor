// Read-only paginated grid of codes for a registry codelist.
// GET /api/codelists/codes/{id}?compact=1&search=&offset=&limit=

function globalCodelistCodesApiRoot() {
    if (typeof globalCodelistsApiBase === 'function') {
        return globalCodelistsApiBase();
    }
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

Vue.component('global-codelist-codes-grid', {
    props: {
        registryCodelistId: {
            default: null
        },
        title: {
            type: String,
            default: ''
        },
        dense: {
            type: Boolean,
            default: true
        },
        maxTableHeight: {
            type: String,
            default: ''
        },
        pageSizeDefault: {
            type: Number,
            default: 25
        },
        showOpenLink: {
            type: Boolean,
            default: true
        },
        language: {
            type: String,
            default: ''
        }
    },
    data: function () {
        return {
            search: '',
            page: 1,
            perPage: this.pageSizeDefault,
            rows: [],
            total: 0,
            loading: false,
            loadError: null,
            fetchSeq: 0,
            searchDebounced: null
        };
    },
    computed: {
        registryIdNum: function () {
            var rid = this.registryCodelistId;
            var n = rid != null && rid !== '' ? parseInt(rid, 10) : NaN;
            return (!isNaN(n) && n > 0) ? n : null;
        },
        headers: function () {
            return [
                { text: 'Code', value: 'code', sortable: false, width: '140px' },
                { text: 'Label', value: 'label', sortable: false }
            ];
        },
        footerProps: function () {
            return {
                'items-per-page-options': [10, 25, 50, 100]
            };
        },
        offset: function () {
            return (this.page - 1) * this.perPage;
        },
        codelistViewUrl: function () {
            if (!this.registryIdNum) {
                return '';
            }
            var root = (typeof CI !== 'undefined' && CI.base_url) ? String(CI.base_url).replace(/\/?$/, '') : '';
            return root + '/codelists#/view/' + this.registryIdNum;
        },
        headerTitle: function () {
            if (this.title && String(this.title).trim()) {
                return String(this.title).trim();
            }
            if (this.registryIdNum) {
                return 'Codelist #' + this.registryIdNum;
            }
            return 'Codes';
        },
        tableWrapStyle: function () {
            var h = (this.maxTableHeight || '').trim();
            if (!h || h === 'none' || h === 'auto') {
                return {};
            }
            return { maxHeight: h, overflow: 'auto' };
        }
    },
    watch: {
        registryCodelistId: function () {
            this.resetAndLoad();
        }
    },
    mounted: function () {
        var vm = this;
        vm.searchDebounced = _.debounce(function () {
            vm.page = 1;
            vm.loadCodes();
        }, 350);
        if (vm.registryIdNum) {
            vm.loadCodes();
        }
    },
    beforeDestroy: function () {
        this.fetchSeq++;
        if (this.searchDebounced && this.searchDebounced.cancel) {
            this.searchDebounced.cancel();
        }
    },
    methods: {
        codesApiUrl: function () {
            return globalCodelistCodesApiRoot() + '/codes/' + this.registryIdNum;
        },
        resetAndLoad: function () {
            this.search = '';
            this.page = 1;
            this.perPage = this.pageSizeDefault;
            this.rows = [];
            this.total = 0;
            this.loadError = null;
            if (this.registryIdNum) {
                this.loadCodes();
            }
        },
        onSearchInput: function () {
            if (this.searchDebounced) {
                this.searchDebounced();
            } else {
                this.page = 1;
                this.loadCodes();
            }
        },
        onPageChange: function () {
            this.loadCodes();
        },
        onPerPageChange: function () {
            this.page = 1;
            this.loadCodes();
        },
        refresh: function () {
            this.loadCodes();
        },
        coerceToArray: function (codes) {
            if (Array.isArray(codes)) {
                return codes;
            }
            if (codes && typeof codes === 'object') {
                var keys = Object.keys(codes).sort(function (a, b) {
                    var na = parseInt(a, 10);
                    var nb = parseInt(b, 10);
                    return (!isNaN(na) && !isNaN(nb)) ? na - nb : (a < b ? -1 : a > b ? 1 : 0);
                });
                return keys.map(function (k) { return codes[k]; });
            }
            return [];
        },
        normalizeRows: function (raw) {
            var rows = [];
            for (var i = 0; i < raw.length; i++) {
                var cr = raw[i];
                if (!cr || typeof cr !== 'object') {
                    continue;
                }
                var rawCode = cr.code != null ? cr.code : cr.Code;
                if (rawCode == null || rawCode === '') {
                    rawCode = cr.value != null ? cr.value : cr.item_code;
                }
                var code = rawCode != null ? String(rawCode).trim() : '';
                if (code === '') {
                    continue;
                }
                var label = code;
                var flatLabel = cr.label != null ? cr.label : cr.Label;
                if (flatLabel != null && String(flatLabel).trim() !== '') {
                    label = String(flatLabel).trim();
                } else {
                    var labels = cr.labels || cr.Labels;
                    if (labels && Array.isArray(labels)) {
                        for (var j = 0; j < labels.length; j++) {
                            var lb = labels[j];
                            var lbText = lb && (lb.label != null ? lb.label : lb.Label);
                            if (lbText != null && String(lbText).trim() !== '') {
                                label = String(lbText).trim();
                                break;
                            }
                        }
                    }
                }
                rows.push({ code: code, label: label });
            }
            return rows;
        },
        loadCodes: function () {
            var vm = this;
            if (!vm.registryIdNum) {
                vm.rows = [];
                vm.total = 0;
                vm.loading = false;
                return;
            }
            var seq = ++vm.fetchSeq;
            vm.loading = true;
            vm.loadError = null;
            var params = {
                compact: 1,
                limit: vm.perPage,
                offset: vm.offset
            };
            var q = (vm.search || '').trim();
            if (q.length >= 1) {
                params.search = q;
            }
            if (vm.language && String(vm.language).trim()) {
                params.language = String(vm.language).trim();
            }
            axios.get(vm.codesApiUrl(), { params: params, timeout: 30000 })
                .then(function (res) {
                    if (seq !== vm.fetchSeq) {
                        return;
                    }
                    vm.loading = false;
                    var payload = res && res.data;
                    if (typeof payload === 'string') {
                        try {
                            payload = JSON.parse(payload);
                        } catch (e) {
                            payload = {};
                        }
                    }
                    var data = (payload && typeof payload === 'object') ? payload : {};
                    var st = data.status;
                    if (st === 'failed' || st === false || st === 0) {
                        vm.rows = [];
                        vm.total = 0;
                        vm.loadError = data.message || data.error || 'Could not load codes.';
                        return;
                    }
                    var rawCodes = data.codes;
                    if (rawCodes == null && data.data && typeof data.data === 'object') {
                        rawCodes = data.data.codes;
                    }
                    vm.rows = vm.normalizeRows(vm.coerceToArray(rawCodes));
                    vm.total = parseInt(data.total, 10);
                    if (isNaN(vm.total)) {
                        vm.total = vm.rows.length;
                    }
                })
                .catch(function (err) {
                    if (seq !== vm.fetchSeq) {
                        return;
                    }
                    vm.loading = false;
                    vm.rows = [];
                    vm.total = 0;
                    var body = err.response && err.response.data;
                    vm.loadError = (body && (body.message || body.error))
                        || err.message
                        || 'Could not load codes.';
                });
        }
    },
    template: `
    <div class="global-codelist-codes-grid border rounded bg-white">
        <div class="d-flex align-center flex-wrap mb-2" style="gap:8px;">
            <div class="text-subtitle-2 font-weight-medium">
                <a
                    v-if="showOpenLink && registryIdNum && codelistViewUrl"
                    :href="codelistViewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="global-codelist-codes-grid-title-link"
                >{{ headerTitle }}<v-icon x-small class="ml-1">mdi-open-in-new</v-icon></a>
                <span v-else>{{ headerTitle }}</span>
            </div>
            <v-spacer></v-spacer>
            <v-btn icon x-small :disabled="loading || !registryIdNum" @click="refresh" title="Refresh">
                <v-icon small>mdi-refresh</v-icon>
            </v-btn>
        </div>
        <div v-if="!registryIdNum" class="text-caption grey--text py-2">No codelist selected.</div>
        <template v-else>
            <div class="global-codelist-codes-grid-search mb-2">
                <v-text-field
                    v-model="search"
                    dense outlined hide-details clearable
                    prepend-inner-icon="mdi-magnify"
                    placeholder="Search code or label…"
                    @input="onSearchInput"
                    @click:clear="page = 1; loadCodes()"
                ></v-text-field>
            </div>
            <div v-if="loadError" class="text-caption error--text mb-2">{{ loadError }}</div>
            <div class="global-codelist-codes-grid-table-wrap elevation-1" :style="tableWrapStyle">
                <v-data-table
                    :headers="headers"
                    :items="rows"
                    :loading="loading"
                    :server-items-length="total"
                    :page.sync="page"
                    :items-per-page.sync="perPage"
                    :footer-props="footerProps"
                    item-key="code"
                    :dense="dense"
                    disable-sort
                    mobile-breakpoint="0"
                    class="global-codelist-codes-grid-table"
                    @update:page="onPageChange"
                    @update:items-per-page="onPerPageChange"
                >
                    <template v-slot:item.code="{ item }">
                        <code class="text-caption">{{ item.code }}</code>
                    </template>
                    <template v-slot:item.label="{ item }">
                        <span class="text-caption">{{ item.label }}</span>
                    </template>
                    <template v-slot:no-data>
                        <div class="text-caption grey--text pa-4 text-center">
                            {{ search ? 'No matching codes.' : 'No codes in this codelist.' }}
                        </div>
                    </template>
                </v-data-table>
            </div>
        </template>
    </div>
    `
});
