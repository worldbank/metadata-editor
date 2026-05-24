// Read-only preview of registry (global) codelist codes for indicator DSD edit panel.
// GET /api/codelists/codes/{global_codelist_id}?compact=1&search=&offset=&limit=
Vue.component('indicator-dsd-global-codelist-preview', {
    props: {
        registryCodelistId: {
            default: null,
            validator: function(v) {
                return v === null || v === undefined || v === '' || typeof v === 'number' || typeof v === 'string';
            }
        },
        codelistName: {
            type: String,
            default: ''
        }
    },
    data: function() {
        return {
            loading: false,
            loadError: null,
            displayRows: [],
            search: '',
            pageSize: 50,
            currentOffset: 0,
            totalCount: null,
            fetchSeq: 0,
            isDestroyed: false,
        };
    },
    computed: {
        codelistUrl: function() {
            var root = (typeof CI !== 'undefined' && CI.base_url) ? String(CI.base_url).replace(/\/?$/, '') : '';
            return root + '/codelists#/view/' + parseInt(this.registryCodelistId, 10);
        },
        hasRegistryId: function() {
            var rid = this.registryCodelistId;
            var n = rid != null && rid !== '' ? parseInt(rid, 10) : NaN;
            return !isNaN(n) && n > 0;
        },
        hasPrev: function() {
            return this.currentOffset > 0;
        },
        hasMore: function() {
            return this.totalCount !== null && (this.currentOffset + this.displayRows.length) < this.totalCount;
        },
        pageRangeStart: function() {
            return this.displayRows.length > 0 ? this.currentOffset + 1 : 0;
        },
        pageRangeEnd: function() {
            return this.currentOffset + this.displayRows.length;
        }
    },
    watch: {
        registryCodelistId: function() {
            this.resetAndFetch();
        }
    },
    created: function() {
        this.searchTimer = null; // non-reactive, no re-render on assignment
        this.resetAndFetch();
    },
    beforeDestroy: function() {
        this.isDestroyed = true;
        this.fetchSeq++;
        this.loading = false;
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
            this.searchTimer = null;
        }
    },
    methods: {
        resetAndFetch: function() {
            this.search = '';
            this.currentOffset = 0;
            this.totalCount = null;
            this.displayRows = [];
            this.loadError = null;
            this.fetchPage(0, '');
        },
        onSearchInput: function() {
            var vm = this;
            if (vm.searchTimer) {
                clearTimeout(vm.searchTimer);
            }
            vm.searchTimer = setTimeout(function() {
                vm.searchTimer = null;
                vm.currentOffset = 0;
                vm.totalCount = null;
                vm.fetchPage(0, vm.search);
            }, 350);
        },
        clearSearch: function() {
            this.search = '';
            this.currentOffset = 0;
            this.totalCount = null;
            this.fetchPage(0, '');
        },
        prevPage: function() {
            var newOffset = Math.max(0, this.currentOffset - this.pageSize);
            this.currentOffset = newOffset;
            this.fetchPage(newOffset, this.search);
        },
        nextPage: function() {
            var newOffset = this.currentOffset + this.pageSize;
            this.currentOffset = newOffset;
            this.fetchPage(newOffset, this.search);
        },
        fetchPage: function(offset, search) {
            var vm = this;
            vm.loadError = null;
            if (!vm.hasRegistryId) {
                vm.loading = false;
                vm.displayRows = [];
                return;
            }
            var seq = ++vm.fetchSeq;
            vm.loading = true;
            var registryNum = parseInt(vm.registryCodelistId, 10);
            var root = (typeof CI !== 'undefined' && CI.base_url) ? String(CI.base_url).replace(/\/?$/, '') : '';
            var params = { compact: 1, limit: vm.pageSize, offset: offset };
            if (search && String(search).trim() !== '') {
                params.search = String(search).trim();
            }

            axios.get(root + '/api/codelists/codes/' + registryNum, { params: params, timeout: 30000 })
                .then(function(res) {
                    try {
                        if (vm.isDestroyed || seq !== vm.fetchSeq) {
                            return;
                        }
                        var payload = res && res.data;
                        if (typeof payload === 'string') {
                            try { payload = JSON.parse(payload); } catch(e) { payload = {}; }
                        }
                        var data = (payload && typeof payload === 'object') ? payload : {};
                        var st = data.status;
                        if (st === 'failed' || st === false || st === 0) {
                            throw new Error(data.message || data.error || 'Could not load codes');
                        }
                        var rawCodes = data.codes;
                        if (rawCodes == null && data.data && typeof data.data === 'object') {
                            rawCodes = data.data.codes;
                        }
                        vm.displayRows = vm.normalizeRows(vm.coerceToArray(rawCodes));
                        vm.totalCount = parseInt(data.total, 10) || 0;
                        vm.currentOffset = parseInt(data.offset, 10) || 0;
                        vm.pageSize = parseInt(data.limit, 10) || vm.pageSize;
                    } finally {
                        if (!vm.isDestroyed && seq === vm.fetchSeq) {
                            vm.loading = false;
                        }
                    }
                })
                .catch(function(err) {
                    try {
                        if (vm.isDestroyed || seq !== vm.fetchSeq) {
                            return;
                        }
                        vm.displayRows = [];
                        var body = err.response && err.response.data;
                        vm.loadError = (body && (body.message || body.error))
                            || err.message
                            || (vm.$t('global_codelist_preview_load_failed') || 'Could not load codes');
                    } finally {
                        if (!vm.isDestroyed && seq === vm.fetchSeq) {
                            vm.loading = false;
                        }
                    }
                });
        },
        /** Normalize compact ({code, label}) and legacy ({code, labels:[{label}]}) shapes. */
        normalizeRows: function(raw) {
            var rows = [];
            for (var i = 0; i < raw.length; i++) {
                var cr = raw[i];
                if (!cr || typeof cr !== 'object') { continue; }
                var rawCode = cr.code != null ? cr.code : cr.Code;
                if (rawCode == null || rawCode === '') {
                    rawCode = cr.value != null ? cr.value : cr.item_code;
                }
                var code = rawCode != null ? String(rawCode).trim() : '';
                if (code === '') { continue; }
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
        coerceToArray: function(codes) {
            if (Array.isArray(codes)) { return codes; }
            if (codes && typeof codes === 'object') {
                var keys = Object.keys(codes).sort(function(a, b) {
                    var na = parseInt(a, 10), nb = parseInt(b, 10);
                    return (!isNaN(na) && !isNaN(nb)) ? na - nb : (a < b ? -1 : a > b ? 1 : 0);
                });
                return keys.map(function(k) { return codes[k]; });
            }
            return [];
        }
    },
    template: `
        <div class="indicator-dsd-global-codelist-preview border rounded p-2 bg-white mt-2">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap: 8px;">
                <a
                    v-if="hasRegistryId"
                    :href="codelistUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="small font-weight-medium"
                    style="text-decoration: none;"
                >
                    {{ codelistName || ('#' + registryCodelistId) }}
                    <v-icon x-small style="vertical-align: middle; margin-left: 2px;" color="primary">mdi-open-in-new</v-icon>
                </a>
                <span v-else class="small font-weight-medium text-muted">{{ $t('global_codelist_preview_title') || 'Registry codes' }}</span>
                <v-btn
                    icon x-small outlined
                    class="flex-shrink-0"
                    @click="resetAndFetch"
                    :disabled="loading || !hasRegistryId"
                    :title="$t('refresh') || 'Refresh'"
                    :aria-label="$t('refresh') || 'Refresh'"
                >
                    <v-icon dense small>mdi-refresh</v-icon>
                </v-btn>
            </div>
            <div v-if="!hasRegistryId" class="small text-muted py-2">{{ $t('global_codelist_preview_no_registry') || 'Select a standard codelist above to preview codes.' }}</div>
            <template v-else>
                <!-- Search -->
                <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 4px;">
                    <div class="position-relative d-inline-block flex-grow-1" style="min-width: 0; max-width: 22rem;">
                        <span
                            class="position-absolute d-flex align-items-center justify-content-center"
                            style="left: 0; top: 0; bottom: 0; width: 1.65rem; pointer-events: none; z-index: 1;"
                            aria-hidden="true"
                        >
                            <v-icon x-small dense color="grey">mdi-magnify</v-icon>
                        </span>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            style="height: 28px; padding: 2px 1.65rem 2px 1.65rem; font-size: 0.75rem;"
                            v-model="search"
                            @input="onSearchInput"
                            @keyup.esc="clearSearch"
                            :placeholder="$t('search_codes_or_labels') || 'Search code or label…'"
                        />
                        <span
                            v-if="search"
                            class="position-absolute d-flex align-items-center justify-content-center"
                            style="right: 0; top: 0; bottom: 0; width: 1.65rem; cursor: pointer; z-index: 1;"
                            @click="clearSearch"
                            :title="$t('clear') || 'Clear'"
                            aria-label="Clear search"
                        >
                            <v-icon x-small dense color="grey">mdi-close</v-icon>
                        </span>
                    </div>
                </div>

                <!-- Loading / error -->
                <div v-if="loading" class="small text-muted py-2">{{ $t('loading') || 'Loading…' }}</div>
                <div v-else-if="loadError" class="small text-danger py-2">{{ loadError }}</div>
                <template v-else>
                    <!-- Summary row -->
                    <div class="d-flex align-items-center justify-content-between mb-1" style="gap: 8px; min-height: 24px;">
                        <span class="small text-muted">
                            <template v-if="displayRows.length > 0">
                                {{ $t('showing') || 'Showing' }} {{ pageRangeStart }}–{{ pageRangeEnd }}<template v-if="totalCount !== null"> {{ $t('of') || 'of' }} {{ totalCount }}</template>
                            </template>
                            <template v-else-if="search">
                                {{ $t('global_codelist_preview_no_results') || 'No matching codes.' }}
                            </template>
                        </span>
                        <!-- Pagination controls -->
                        <div class="d-flex align-items-center" style="gap: 2px;">
                            <v-btn icon x-small :disabled="!hasPrev || loading" @click="prevPage" :title="$t('prev_page') || 'Previous page'">
                                <v-icon small>mdi-chevron-left</v-icon>
                            </v-btn>
                            <v-btn icon x-small :disabled="!hasMore || loading" @click="nextPage" :title="$t('next_page') || 'Next page'">
                                <v-icon small>mdi-chevron-right</v-icon>
                            </v-btn>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive mt-1" style="max-height: 280px; overflow: auto;">
                        <table class="table table-sm table-striped mb-0" style="font-size: 0.8125rem;">
                            <thead class="thead-light">
                                <tr>
                                    <th class="align-middle py-2">{{ $t('code') || 'Code' }}</th>
                                    <th class="align-middle py-2">{{ $t('label') || 'Label' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, gcIdx) in displayRows" :key="'gc-' + gcIdx + '-' + row.code">
                                    <td><code>{{ row.code }}</code></td>
                                    <td>{{ row.label }}</td>
                                </tr>
                                <tr v-if="displayRows.length === 0">
                                    <td colspan="2" class="text-muted text-center py-3">{{ $t('global_codelist_preview_empty') || 'No codes in this codelist.' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </template>
        </div>
    `
});
