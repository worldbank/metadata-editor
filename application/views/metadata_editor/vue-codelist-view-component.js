// Read-only preview of a codelist (metadata, header translations, items + labels).
Vue.component('codelist-view', {
    props: {
        id: { type: [String, Number], required: true }
    },
    data: function () {
        return {
            loading: false,
            codelist: null,
            translations: [],
            loadError: null,
            // codes + server-side pagination/search
            codes: [],
            codesLoading: false,
            codesTotal: 0,
            codesPage: 1,
            codesPerPage: 50,
            codesSearchInput: '',
            codesSearch: ''
        };
    },
    computed: {
        numericId: function () {
            var n = parseInt(this.id, 10);
            return isNaN(n) ? null : n;
        },
        codesOffset: function () {
            return (this.codesPage - 1) * this.codesPerPage;
        },
        codeById: function () {
            var m = {};
            (this.codes || []).forEach(function (c) {
                m[c.id] = c.code || '';
            });
            return m;
        },
        itemRows: function () {
            var vm = this;
            return (this.codes || []).map(function (c) {
                var pid = c.parent_id != null && c.parent_id !== '' ? parseInt(c.parent_id, 10) : null;
                var parentCode = '';
                if (pid && vm.codeById[pid]) {
                    parentCode = vm.codeById[pid];
                } else if (pid) {
                    parentCode = '#' + pid;
                }
                var labels = c.labels || [];
                var labelParts = labels.map(function (l) {
                    var s = (l.language || '') + ': ' + (l.label || '');
                    if (l.description) {
                        s += ' — ' + l.description;
                    }
                    return s;
                });
                return {
                    id: c.id,
                    code: c.code || '',
                    sort_order: c.sort_order != null && c.sort_order !== '' ? c.sort_order : '—',
                    parent_code: parentCode || '—',
                    labels_text: labelParts.length ? labelParts.join(' · ') : '—'
                };
            });
        },
        itemHeaders: function () {
            return [
                { text: 'Code', value: 'code', sortable: false },
                { text: 'Sort', value: 'sort_order', sortable: false, width: '80px' },
                { text: 'Parent', value: 'parent_code', sortable: false, width: '120px' },
                { text: 'Labels', value: 'labels_text', sortable: false }
            ];
        },
        translationHeaders: function () {
            return [
                { text: 'Language', value: 'language', width: '100px' },
                { text: 'Label', value: 'label' },
                { text: 'Description', value: 'description' }
            ];
        }
    },
    watch: {
        id: function () {
            this.loadAll();
        },
        '$route.params.id': function () {
            this.loadAll();
        }
    },
    mounted: function () {
        this.loadAll();
    },
    methods: {
        apiBase: function () {
            var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '';
            return base + '/api/codelists';
        },
        notifyFail: function (err) {
            var m = 'Request failed';
            if (err.response && err.response.data && err.response.data.message) {
                m = err.response.data.message;
            }
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onFail', m);
            } else {
                alert(m);
            }
        },
        loadAll: function () {
            var vm = this;
            vm.loadError = null;
            if (!vm.numericId) {
                vm.loadError = 'Invalid codelist id';
                return;
            }
            vm.loading = true;
            vm.codelist = null;
            vm.translations = [];
            vm.codes = [];
            vm.codesTotal = 0;
            vm.codesPage = 1;
            vm.codesSearch = '';
            vm.codesSearchInput = '';
            var base = vm.apiBase();
            var id = vm.numericId;
            Promise.all([
                axios.get(base + '/single/' + id),
                axios.get(base + '/codelist_translations/' + id)
            ])
                .then(function (results) {
                    vm.loading = false;
                    if (results[0].data && results[0].data.status === 'success' && results[0].data.codelist) {
                        vm.codelist = results[0].data.codelist;
                    } else {
                        vm.loadError = 'Codelist not found';
                        return;
                    }
                    if (results[1].data && results[1].data.status === 'success') {
                        vm.translations = results[1].data.translations || [];
                    }
                    vm.loadCodes();
                })
                .catch(function (err) {
                    vm.loading = false;
                    vm.loadError = 'Could not load codelist';
                    vm.notifyFail(err);
                });
        },
        loadCodes: function () {
            var vm = this;
            if (!vm.numericId) return;
            vm.codesLoading = true;
            var params = [
                'offset=' + vm.codesOffset,
                'limit=' + vm.codesPerPage
            ];
            if (vm.codesSearch) {
                params.push('search=' + encodeURIComponent(vm.codesSearch));
            }
            axios.get(vm.apiBase() + '/codes/' + vm.numericId + '?' + params.join('&'))
                .then(function (res) {
                    vm.codesLoading = false;
                    if (res.data && res.data.status === 'success') {
                        vm.codes = res.data.codes || [];
                        vm.codesTotal = res.data.total != null ? res.data.total : vm.codes.length;
                    }
                })
                .catch(function (err) {
                    vm.codesLoading = false;
                    vm.notifyFail(err);
                });
        },
        onCodesSearchInput: function () {
            var vm = this;
            if (vm._searchTimer) clearTimeout(vm._searchTimer);
            vm._searchTimer = setTimeout(function () {
                vm.codesSearch = (vm.codesSearchInput || '').trim();
                vm.codesPage = 1;
                vm.loadCodes();
            }, 400);
        },
        onCodesSearchSubmit: function () {
            if (this._searchTimer) clearTimeout(this._searchTimer);
            this.codesSearch = (this.codesSearchInput || '').trim();
            this.codesPage = 1;
            this.loadCodes();
        },
        onCodesSearchClear: function () {
            if (this._searchTimer) clearTimeout(this._searchTimer);
            this.codesSearchInput = '';
            this.codesSearch = '';
            this.codesPage = 1;
            this.loadCodes();
        },
        prevPage: function () {
            if (this.codesPage > 1) {
                this.codesPage--;
                this.loadCodes();
            }
        },
        nextPage: function () {
            if (this.codesOffset + this.codesPerPage < this.codesTotal) {
                this.codesPage++;
                this.loadCodes();
            }
        },
        sdmxExportUrl: function (version) {
            var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '';
            return base + '/api/codelists/export_sdmx/' + this.numericId + '?version=' + version;
        },
        exportJsonUrl: function () {
            var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '';
            return base + '/api/codelists/export_json/' + this.numericId + '?download=1';
        },
        backToList: function () {
            this.$router.push('/');
        },
        goEdit: function () {
            if (this.numericId) {
                this.$router.push('/edit/' + this.numericId);
            }
        }
    },
    template: `
        <div>
            <div class="d-flex flex-wrap align-center mb-3">
                <v-btn text class="mr-2" @click="backToList">
                    <v-icon left>mdi-arrow-left</v-icon> Back to list
                </v-btn>
                <v-spacer></v-spacer>
                <v-btn small outlined class="mr-2" :disabled="!numericId" tag="a"
                    :href="exportJsonUrl()" title="Download JSON">
                    <v-icon left small>mdi-code-json</v-icon> Export JSON
                </v-btn>
                <v-btn small outlined class="mr-2" :disabled="!numericId" tag="a"
                    :href="sdmxExportUrl('2.1')" title="Download SDMX 2.1 Structure XML">
                    <v-icon left small>mdi-download</v-icon> SDMX 2.1
                </v-btn>
                <v-btn small outlined class="mr-2" :disabled="!numericId" tag="a"
                    :href="sdmxExportUrl('3.0')" title="Download SDMX 3.0 Structure XML">
                    <v-icon left small>mdi-download</v-icon> SDMX 3.0
                </v-btn>
                <v-btn color="primary" outlined small :disabled="!numericId" @click="goEdit">
                    <v-icon left small>mdi-pencil</v-icon> Edit
                </v-btn>
            </div>

            <v-alert v-if="loadError && !loading" type="error" dense outlined>{{ loadError }}</v-alert>

            <v-progress-linear v-if="loading" indeterminate class="mb-4"></v-progress-linear>

            <template v-if="codelist && !loading">
                <v-card class="mb-4">
                    <v-card-title class="text-h6">Codelist</v-card-title>
                    <v-card-text>
                        <v-simple-table dense>
                            <tbody>
                                <tr><td class="grey--text" style="width: 160px;">Idno</td><td>{{ codelist.idno }}</td></tr>
                                <tr><td class="grey--text">Agency</td><td>{{ codelist.agency }}</td></tr>
                                <tr><td class="grey--text">Name</td><td>{{ codelist.name }}</td></tr>
                                <tr><td class="grey--text">Version</td><td>{{ codelist.version }}</td></tr>
                                <tr><td class="grey--text">Title</td><td>{{ codelist.title }}</td></tr>
                                <tr v-if="codelist.description"><td class="grey--text">Description</td><td class="codelist-view-pre">{{ codelist.description }}</td></tr>
                                <tr v-if="codelist.uri"><td class="grey--text">URI</td><td><a :href="codelist.uri" target="_blank" rel="noopener">{{ codelist.uri }}</a></td></tr>
                                <tr><td class="grey--text">Database ID</td><td>{{ codelist.id }}</td></tr>
                            </tbody>
                        </v-simple-table>
                    </v-card-text>
                </v-card>

                <v-card class="mb-4">
                    <v-card-title class="text-subtitle-1">Header translations</v-card-title>
                    <v-card-text class="pa-0">
                        <v-data-table
                            v-if="translations.length"
                            :headers="translationHeaders"
                            :items="translations"
                            :items-per-page="translations.length"
                            hide-default-footer
                            dense
                            class="elevation-0"
                        ></v-data-table>
                        <p v-else class="px-4 pb-4 grey--text text-body-2 mb-0">No translation rows.</p>
                    </v-card-text>
                </v-card>

                <v-card>
                    <v-card-title class="d-flex flex-wrap align-center">
                        <span>Items ({{ codesTotal }})</span>
                        <v-spacer></v-spacer>
                        <v-text-field
                            v-model="codesSearchInput"
                            append-icon="mdi-magnify"
                            label="Search codes"
                            single-line
                            hide-details
                            dense
                            outlined
                            clearable
                            style="max-width: 260px;"
                            @input="onCodesSearchInput"
                            @keyup.enter="onCodesSearchSubmit"
                            @click:clear="onCodesSearchClear"
                        ></v-text-field>
                    </v-card-title>
                    <v-card-text class="pa-0">
                        <v-progress-linear v-if="codesLoading" indeterminate></v-progress-linear>
                        <v-data-table
                            v-if="itemRows.length || codesLoading"
                            :headers="itemHeaders"
                            :items="itemRows"
                            :loading="codesLoading"
                            :items-per-page="-1"
                            hide-default-footer
                            dense
                            class="elevation-0 codelist-view-items"
                        ></v-data-table>
                        <p v-else-if="!codesLoading" class="px-4 py-4 grey--text text-body-2 mb-0">
                            {{ codesSearch ? 'No codes match \u201c' + codesSearch + '\u201d.' : 'No items in this codelist.' }}
                        </p>
                        <div v-if="codesTotal > codesPerPage || codesPage > 1"
                            class="d-flex align-center justify-end pa-2"
                            style="border-top: 1px solid rgba(0,0,0,.12);">
                            <span class="text-caption grey--text mr-3">
                                {{ codesOffset + 1 }}–{{ Math.min(codesOffset + codesPerPage, codesTotal) }} of {{ codesTotal }}
                            </span>
                            <v-btn icon small :disabled="codesPage <= 1 || codesLoading" @click="prevPage">
                                <v-icon>mdi-chevron-left</v-icon>
                            </v-btn>
                            <v-btn icon small :disabled="codesOffset + codesPerPage >= codesTotal || codesLoading" @click="nextPage">
                                <v-icon>mdi-chevron-right</v-icon>
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </template>
        </div>
    `
});
