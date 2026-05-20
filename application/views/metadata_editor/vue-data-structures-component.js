// Global data structures registry listing (NADA-aligned catalogue, Vue 2).
Vue.component('data-structures', {
    data: function () {
        return {
            loading: false,
            structures: [],
            total: 0,
            search: '',
            searchDebounced: '',
            statusFilter: null,
            selected: [],
            expanded: [],
            versionsByHead: {},
            versionsLoading: {},
            batchDeleting: false,
            deletingId: null,
            duplicatingId: null,
            deleteDialog: false,
            deleteTarget: null,
            batchDeleteDialog: false,
            importDialog: false,
            importFormat: 'json',
            importFile: null,
            importDsdId: '',
            importOverwriteCodelists: false,
            importing: false,
            tableOptions: { page: 1, itemsPerPage: 25 },
            statusFilterItems: [
                { text: 'Draft', value: 'draft' },
                { text: 'Review', value: 'review' },
                { text: 'Published', value: 'published' },
                { text: 'Deprecated', value: 'deprecated' },
                { text: 'Archived', value: 'archived' }
            ],
            headers: [
                { text: '', value: 'data-table-expand', sortable: false, width: '52px' },
                { text: 'ID', value: 'id', sortable: false, width: '72px' },
                { text: 'Title', value: 'title', sortable: false },
                { text: 'Name', value: 'name', sortable: false, width: '140px' },
                { text: 'Agency', value: 'agency', sortable: false, width: '110px' },
                { text: 'Version', value: 'version', sortable: false, width: '100px' },
                { text: 'Versions', value: 'versions_count', sortable: false, align: 'center', width: '120px' },
                { text: 'Projects', value: 'projects_count', sortable: false, align: 'center', width: '100px' },
                { text: 'Idno', value: 'idno', sortable: false, width: '180px' },
                { text: 'Status', value: 'status', sortable: false, width: '110px' },
                { text: 'Updated', value: 'updated', sortable: false, width: '100px' },
                { text: '', value: 'actions', sortable: false, align: 'end', width: '52px' }
            ],
            _searchDebounceTimer: null
        };
    },
    watch: {
        expanded: function (ids) {
            this.onExpand(ids);
        },
        tableOptions: {
            deep: true,
            handler: function (opts) {
                if (!opts) {
                    return;
                }
                this.loadStructures();
            }
        },
        statusFilter: function () {
            this.tableOptions = Object.assign({}, this.tableOptions, { page: 1 });
            this.loadStructures();
        }
    },
    mounted: function () {
        var vm = this;
        vm.loadStructures();
    },
    computed: {
        importCanSubmit: function () {
            return !!this.importFile;
        },
        hasSearch: function () {
            return String(this.searchDebounced || '').trim().length > 0;
        },
        canEditDataStructure: function () {
            return CI && CI.user_info && CI.user_info.can_edit_data_structure === true;
        },
        canImportDataStructure: function () {
            return CI && CI.user_info && CI.user_info.can_import_data_structure === true;
        },
        canDeleteDataStructure: function () {
            return CI && CI.user_info && CI.user_info.can_delete_data_structure === true;
        }
    },
    methods: {
        apiBase: function () {
            var base = (typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '';
            return base + '/api/data_structures';
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
        notifySuccess: function (msg) {
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onSuccess', msg);
            }
        },
        onSearchInput: function () {
            var vm = this;
            clearTimeout(vm._searchDebounceTimer);
            vm._searchDebounceTimer = setTimeout(function () {
                vm.searchDebounced = (vm.search || '').trim();
                vm.tableOptions = Object.assign({}, vm.tableOptions, { page: 1 });
                vm.loadStructures();
            }, 400);
        },
        clearSearch: function () {
            clearTimeout(this._searchDebounceTimer);
            this.search = '';
            this.searchDebounced = '';
            this.tableOptions = Object.assign({}, this.tableOptions, { page: 1 });
            this.loadStructures();
        },
        loadStructures: function () {
            var vm = this;
            vm.loading = true;
            vm.expanded = [];
            vm.versionsByHead = {};
            vm.selected = [];
            var page = (vm.tableOptions && vm.tableOptions.page) ? vm.tableOptions.page : 1;
            var perPage = (vm.tableOptions && vm.tableOptions.itemsPerPage) ? vm.tableOptions.itemsPerPage : 25;
            var params = {
                page: page,
                per_page: perPage
            };
            if (vm.searchDebounced) {
                params.search = vm.searchDebounced;
            }
            if (vm.statusFilter) {
                params.status = vm.statusFilter;
            }
            axios.get(vm.apiBase(), { params: params })
                .then(function (res) {
                    vm.loading = false;
                    if (res.data && res.data.status === 'success') {
                        vm.structures = res.data.data_structures || [];
                        vm.total = res.data.total != null ? res.data.total : vm.structures.length;
                    } else {
                        vm.structures = [];
                        vm.total = 0;
                    }
                })
                .catch(function (err) {
                    vm.loading = false;
                    vm.notifyFail(err);
                });
        },
        onExpand: function (rows) {
            var vm = this;
            (rows || []).forEach(function (id) {
                var head = vm.structures.find(function (s) { return s.id === id; });
                if (head && (!head.versions_count || head.versions_count <= 1)) {
                    return;
                }
                if (!vm.versionsByHead[id] && !vm.versionsLoading[id]) {
                    vm.loadVersions(head);
                }
            });
        },
        loadVersions: function (head) {
            var vm = this;
            if (!head || !head.id) {
                return;
            }
            var headId = head.id;
            vm.$set(vm.versionsLoading, headId, true);
            axios.get(vm.apiBase() + '/versions/' + headId)
                .then(function (res) {
                    vm.$set(vm.versionsLoading, headId, false);
                    if (res.data && res.data.status === 'success') {
                        vm.$set(vm.versionsByHead, headId, res.data.data_structures || []);
                    }
                })
                .catch(function (err) {
                    vm.$set(vm.versionsLoading, headId, false);
                    vm.notifyFail(err);
                });
        },
        versionRowsFor: function (head) {
            if (!head) {
                return [];
            }
            var loaded = this.versionsByHead[head.id];
            if (loaded && loaded.length) {
                return loaded;
            }
            return [head];
        },
        showExpand: function (item) {
            return item && (item.versions_count == null || item.versions_count > 1);
        },
        statusMeta: function (status) {
            var s = (status || 'draft').toString().toLowerCase();
            if (s === 'draft') return { label: 'Draft', color: 'grey' };
            if (s === 'review') return { label: 'Review', color: 'info' };
            if (s === 'published') return { label: 'Published', color: 'green' };
            if (s === 'deprecated') return { label: 'Deprecated', color: 'orange' };
            if (s === 'archived') return { label: 'Archived', color: 'blue-grey' };
            return { label: s, color: 'grey' };
        },
        formatUpdated: function (val) {
            if (val == null || val === '') {
                return '—';
            }
            var n = Number(val);
            if (Number.isFinite(n) && n > 0) {
                var ms = n > 1e12 ? n : n * 1000;
                var d = new Date(ms);
                if (!Number.isNaN(d.getTime())) {
                    return d.toLocaleDateString(undefined, { dateStyle: 'short' });
                }
            }
            var d2 = new Date(val);
            if (!Number.isNaN(d2.getTime())) {
                return d2.toLocaleDateString(undefined, { dateStyle: 'short' });
            }
            return '—';
        },
        isRowLocked: function (row) {
            var s = (row && row.status) ? String(row.status).toLowerCase() : 'draft';
            return s === 'published' || s === 'archived';
        },
        isRowSelectable: function (item) {
            if (!item || !item.id) {
                return false;
            }
            if (this.isRowLocked(item)) {
                return false;
            }
            if (item.projects_count != null && Number(item.projects_count) > 0) {
                return false;
            }
            return true;
        },
        rowDisabled: function (item) {
            return !this.isRowSelectable(item);
        },
        clearSelection: function () {
            this.selected = [];
        },
        confirmDelete: function (row) {
            this.deleteTarget = row;
            this.deleteDialog = true;
        },
        doDelete: function () {
            var vm = this;
            var row = vm.deleteTarget;
            if (!row) {
                return;
            }
            vm.deletingId = row.id;
            axios.post(vm.apiBase() + '/delete/' + row.id)
                .then(function () {
                    vm.deletingId = null;
                    vm.deleteDialog = false;
                    vm.deleteTarget = null;
                    vm.notifySuccess('Data structure deleted');
                    vm.loadStructures();
                })
                .catch(function (err) {
                    vm.deletingId = null;
                    vm.notifyFail(err);
                });
        },
        confirmBatchDelete: function () {
            var rows = (this.selected || []).filter(this.isRowSelectable);
            if (!rows.length) {
                return;
            }
            this.batchDeleteDialog = true;
        },
        doBatchDelete: function () {
            var vm = this;
            var rows = (vm.selected || []).filter(function (item) {
                return vm.isRowSelectable(item);
            });
            if (!rows.length) {
                vm.batchDeleteDialog = false;
                return;
            }
            var ids = rows.map(function (r) { return r.id; });
            vm.batchDeleting = true;
            axios.post(vm.apiBase() + '/batch_delete', { ids: ids })
                .then(function (res) {
                    vm.batchDeleting = false;
                    vm.batchDeleteDialog = false;
                    vm.clearSelection();
                    var d = res.data || {};
                    var failed = d.failed_count || 0;
                    if (failed > 0) {
                        vm.notifyFail({
                            response: {
                                data: {
                                    message: 'Deleted ' + (d.deleted_count || 0) + ', failed ' + failed
                                        + ' (locked, in use by projects, or other constraint).'
                                }
                            }
                        });
                    } else {
                        vm.notifySuccess('Deleted ' + (d.deleted_count || ids.length) + ' data structure(s)');
                    }
                    vm.loadStructures();
                })
                .catch(function (err) {
                    vm.batchDeleting = false;
                    vm.batchDeleteDialog = false;
                    vm.notifyFail(err);
                });
        },
        goView: function (row) {
            this.$router.push('/view/' + row.id);
        },
        goCreate: function () {
            this.$router.push('/edit');
        },
        goEdit: function (row) {
            this.$router.push('/edit/' + row.id);
        },
        duplicateStructure: function (row) {
            var vm = this;
            if (!row || !row.id) {
                return;
            }
            var label = row.title || row.name || ('#' + row.id);
            if (!window.confirm('Duplicate data structure "' + label + '"? A new draft copy will be created.')) {
                return;
            }
            vm.duplicatingId = row.id;
            axios.post(vm.apiBase() + '/duplicate/' + row.id)
                .then(function (res) {
                    vm.duplicatingId = null;
                    if (res.data && res.data.status === 'success' && res.data.id) {
                        vm.notifySuccess('Data structure duplicated');
                        vm.$router.push('/edit/' + res.data.id);
                    } else {
                        vm.loadStructures();
                    }
                })
                .catch(function (err) {
                    vm.duplicatingId = null;
                    vm.notifyFail(err);
                });
        },
        goProjects: function (row) {
            this.$router.push('/projects/' + row.id);
        },
        exportUrl: function (row) {
            return this.apiBase() + '/export/' + row.id + '?download=1';
        },
        openImport: function (fmt) {
            this.importFormat = fmt === 'sdmx' ? 'sdmx' : 'json';
            this.importFile = null;
            this.importDsdId = '';
            this.importOverwriteCodelists = false;
            this.importDialog = true;
        },
        closeImport: function () {
            if (!this.importing) {
                this.importDialog = false;
            }
        },
        submitImport: function () {
            var vm = this;
            if (!vm.importCanSubmit) {
                return;
            }
            vm.importing = true;
            var url = vm.apiBase() + (vm.importFormat === 'json' ? '/import_json' : '/import_sdmx');
            var fd = new FormData();
            fd.append('file', vm.importFile);
            if (vm.importFormat === 'sdmx' && vm.importDsdId) {
                fd.append('dsd_id', vm.importDsdId);
            }
            if (vm.importOverwriteCodelists) {
                fd.append('overwrite_codelists', '1');
            }
            axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
                .then(function (res) {
                    vm.importing = false;
                    vm.importDialog = false;
                    vm.notifySuccess('Import completed');
                    vm.loadStructures();
                    var id = null;
                    if (res.data && res.data.summary && res.data.summary.data_structure) {
                        id = res.data.summary.data_structure.id;
                    } else if (res.data && res.data.result && res.data.result.data_structure) {
                        id = res.data.result.data_structure.id;
                    }
                    if (id) {
                        vm.$router.push('/edit/' + id);
                    }
                })
                .catch(function (err) {
                    vm.importing = false;
                    vm.notifyFail(err);
                });
        }
    },
    template: `
        <div>
            <v-card class="mb-4">
                <v-card-title class="d-flex flex-wrap align-center">
                    <span class="text-h6">Data structures</span>
                    <v-spacer></v-spacer>
                    <v-chip v-if="!loading" small class="mr-3">{{ total }} {{ total === 1 ? 'structure' : 'structures' }}</v-chip>
                    <v-btn v-if="canEditDataStructure" color="primary" class="mr-2" @click="goCreate">New data structure</v-btn>
                    <v-btn v-if="canImportDataStructure" color="primary" outlined class="mr-2" @click="openImport('json')">Import JSON</v-btn>
                    <v-btn v-if="canImportDataStructure" color="primary" outlined class="mr-2" @click="openImport('sdmx')">Import SDMX</v-btn>
                </v-card-title>
                <v-card-text class="pt-0 pb-2">
                    <div class="d-flex flex-wrap align-center">
                        <v-text-field
                            v-model="search"
                            label="Search"
                            single-line
                            hide-details
                            dense
                            outlined
                            clearable
                            class="mr-4"
                            style="max-width: 480px;"
                            @keyup.enter="onSearchInput"
                            @click:clear="clearSearch"
                        >
                            <template v-slot:append>
                                <v-btn icon small :loading="loading" @click="onSearchInput" title="Search">
                                    <v-icon small>mdi-magnify</v-icon>
                                </v-btn>
                            </template>
                        </v-text-field>
                        <v-select
                            v-model="statusFilter"
                            :items="statusFilterItems"
                            label="Status"
                            dense
                            outlined
                            hide-details
                            clearable
                            style="max-width: 200px;"
                            class="mr-4"
                        ></v-select>
                    </div>
                    <div v-if="canDeleteDataStructure && selected.length > 0" class="d-flex align-center flex-wrap mt-2 py-2 px-3 grey lighten-4 rounded">
                        <span class="text-body-2 font-weight-medium mr-3">{{ selected.length }} selected</span>
                        <v-btn color="error" small depressed class="mr-2" :loading="batchDeleting" @click="confirmBatchDelete">
                            <v-icon small left>mdi-delete</v-icon>
                            Delete selected
                        </v-btn>
                        <v-btn text small :disabled="batchDeleting" @click="clearSelection">Clear</v-btn>
                    </div>
                </v-card-text>
                <v-data-table
                    v-model="selected"
                    :headers="headers"
                    :items="structures"
                    :loading="loading"
                    loading-text="Loading..."
                    :options.sync="tableOptions"
                    :server-items-length="total"
                    :footer-props="{ 'items-per-page-options': [10, 25, 50, 100] }"
                    disable-sort
                    class="elevation-0"
                    :show-select="canDeleteDataStructure"
                    show-expand
                    :expanded.sync="expanded"
                    item-key="id"
                    :item-disabled="rowDisabled"
                    single-expand
                >
                    <template v-slot:item.data-table-expand="{ item, expand, isExpanded }">
                        <v-btn v-if="showExpand(item)" icon small @click="expand(!isExpanded)">
                            <v-icon small>{{ isExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</v-icon>
                        </v-btn>
                    </template>
                    <template v-slot:item.id="{ item }">
                        <code class="text-caption">{{ item.id }}</code>
                    </template>
                    <template v-slot:item.title="{ item }">
                        <a href="#" class="text-decoration-none" @click.prevent="goView(item)">{{ item.title || item.name }}</a>
                    </template>
                    <template v-slot:item.name="{ item }">
                        <a v-if="canEditDataStructure" href="#" class="text-decoration-none" @click.prevent="goEdit(item)">{{ item.name }}</a>
                        <a v-else href="#" class="text-decoration-none" @click.prevent="goView(item)">{{ item.name }}</a>
                    </template>
                    <template v-slot:item.version="{ item }">
                        <code class="text-caption">{{ item.version || '—' }}</code>
                    </template>
                    <template v-slot:item.versions_count="{ item }">
                        <v-chip v-if="item.versions_count > 1" x-small color="primary" outlined>{{ item.versions_count }}</v-chip>
                        <span v-else class="grey--text">—</span>
                    </template>
                    <template v-slot:item.projects_count="{ item }">
                        <v-btn
                            v-if="item.projects_count > 0"
                            x-small
                            text
                            color="indigo"
                            @click="goProjects(item)"
                        >{{ item.projects_count }}</v-btn>
                        <span v-else class="grey--text">0</span>
                    </template>
                    <template v-slot:item.idno="{ item }">
                        <code v-if="item.idno" class="text-caption">{{ item.idno }}</code>
                        <span v-else class="grey--text">—</span>
                    </template>
                    <template v-slot:item.status="{ item }">
                        <v-chip x-small :color="statusMeta(item.status).color" dark class="text-capitalize">
                            {{ statusMeta(item.status).label }}
                        </v-chip>
                    </template>
                    <template v-slot:item.updated="{ item }">
                        <span class="text-caption grey--text text--darken-1">{{ formatUpdated(item.updated) }}</span>
                    </template>
                    <template v-slot:expanded-item="{ headers, item }">
                        <td :colspan="headers.length" class="pa-0 grey lighten-5">
                            <v-progress-linear v-if="versionsLoading[item.id]" indeterminate></v-progress-linear>
                            <v-simple-table v-else dense class="elevation-0 transparent">
                                <thead>
                                    <tr>
                                        <th class="text-left">Seq</th>
                                        <th class="text-left">Version</th>
                                        <th class="text-left">Status</th>
                                        <th class="text-left">IDNO</th>
                                        <th class="text-left">ID</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="ver in versionRowsFor(item)" :key="ver.id">
                                        <td>{{ ver.version_seq != null ? ver.version_seq : '—' }}</td>
                                        <td><code class="text-caption">{{ ver.version }}</code></td>
                                        <td>
                                            <v-chip x-small :color="statusMeta(ver.status).color" dark class="text-capitalize">
                                                {{ statusMeta(ver.status).label }}
                                            </v-chip>
                                        </td>
                                        <td><code class="text-caption">{{ ver.idno || '—' }}</code></td>
                                        <td><code class="text-caption">{{ ver.id }}</code></td>
                                        <td class="text-end">
                                            <v-btn x-small text color="primary" @click="goEdit(ver)">Open</v-btn>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-simple-table>
                        </td>
                    </template>
                    <template v-slot:item.actions="{ item }">
                        <v-menu offset-y left>
                            <template v-slot:activator="{ on, attrs }">
                                <v-btn icon small v-bind="attrs" v-on="on" :loading="deletingId === item.id || duplicatingId === item.id">
                                    <v-icon small>mdi-dots-vertical</v-icon>
                                </v-btn>
                            </template>
                            <v-list dense>
                                <v-list-item @click="goView(item)">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-eye-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title>View</v-list-item-title>
                                </v-list-item>
                                <v-list-item v-if="canEditDataStructure" @click="goEdit(item)">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-pencil-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title>Edit</v-list-item-title>
                                </v-list-item>
                                <v-list-item v-if="canEditDataStructure" @click="duplicateStructure(item)">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-content-duplicate</v-icon></v-list-item-icon>
                                    <v-list-item-title>Duplicate</v-list-item-title>
                                </v-list-item>
                                <v-list-item v-if="item.projects_count > 0" @click="goProjects(item)">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-folder-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title>Projects</v-list-item-title>
                                </v-list-item>
                                <v-divider></v-divider>
                                <v-list-item :href="exportUrl(item)" target="_blank" rel="noopener noreferrer">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-code-json</v-icon></v-list-item-icon>
                                    <v-list-item-title>Export JSON</v-list-item-title>
                                </v-list-item>
                                <v-divider v-if="canDeleteDataStructure"></v-divider>
                                <v-list-item v-if="canDeleteDataStructure" :disabled="isRowLocked(item) || (item.projects_count > 0)" @click="confirmDelete(item)">
                                    <v-list-item-icon class="mr-2"><v-icon small color="error">mdi-delete-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title class="error--text">Delete</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </template>
                </v-data-table>
            </v-card>

            <v-dialog v-model="deleteDialog" max-width="440" persistent>
                <v-card>
                    <v-card-title>Delete data structure?</v-card-title>
                    <v-card-text v-if="deleteTarget">
                        Delete <strong>{{ deleteTarget.name }}</strong>
                        ({{ deleteTarget.agency }} / {{ deleteTarget.version }})? Components are removed with it.
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="deleteDialog = false">Cancel</v-btn>
                        <v-btn color="error" dark depressed :loading="deletingId != null" @click="doDelete">Delete</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-dialog v-model="batchDeleteDialog" max-width="480" persistent>
                <v-card>
                    <v-card-title>Delete selected data structures?</v-card-title>
                    <v-card-text>
                        This will delete the selected rows. Published or archived definitions, or structures linked to projects, cannot be removed.
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text :disabled="batchDeleting" @click="batchDeleteDialog = false">Cancel</v-btn>
                        <v-btn color="error" dark depressed :loading="batchDeleting" @click="doBatchDelete">Delete</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-dialog v-model="importDialog" max-width="560" @click:outside="closeImport">
                <v-card>
                    <v-card-title>{{ importFormat === 'json' ? 'Import JSON' : 'Import SDMX structure' }}</v-card-title>
                    <v-card-text>
                        <v-file-input v-model="importFile" dense outlined
                            :accept="importFormat === 'json' ? '.json' : '.xml'"
                            :label="importFormat === 'json' ? 'JSON file' : 'SDMX-ML XML file'"
                            :disabled="importing" show-size></v-file-input>
                        <v-text-field v-if="importFormat === 'sdmx'" v-model="importDsdId" dense outlined
                            label="DataStructure @id (optional)" hint="When the file has multiple DSDs" persistent-hint class="mt-2"></v-text-field>
                        <v-checkbox v-if="importFormat === 'sdmx'" v-model="importOverwriteCodelists" hide-details dense
                            label="Overwrite existing codelists (same agency, name, version)"></v-checkbox>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text :disabled="importing" @click="closeImport">Cancel</v-btn>
                        <v-btn color="primary" :loading="importing" :disabled="!importCanSubmit" @click="submitImport">Import</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
