// Global codelists listing (used by codelists/index.php). Remove uses POST /api/codelists/delete/{id} (not HTTP DELETE).
Vue.component('codelists', {
    props: [],
    data: function () {
        return {
            loading: false,
            deletingId: null,
            codelists: [],
            total: 0,
            search: '',
            selected: [],
            expanded: [],
            versionsByHead: {},
            versionsLoading: {},
            batchDeleting: false,
            importDialog: false,
            importFormat: 'sdmx',
            importSource: 'file',
            importFile: null,
            importUrl: '',
            importDryRun: false,
            importReplace: false,
            importing: false,
            headers: [
                { text: '', value: 'data-table-expand', sortable: false, width: '52px' },
                { text: 'Title', value: 'title', sortable: false },
                { text: 'Name', value: 'name', sortable: false, width: '140px' },
                { text: 'Agency', value: 'agency', sortable: false, width: '110px' },
                { text: 'Version', value: 'version', sortable: false, width: '100px' },
                { text: 'Versions', value: 'versions_count', sortable: false, align: 'center', width: '120px' },
                { text: 'Items', value: 'item_count', sortable: false, align: 'end', width: '88px' },
                { text: 'DSD', value: 'dsd_component_count', sortable: false, align: 'end', width: '80px' },
                { text: 'Status', value: 'status', sortable: false, width: '110px' },
                { text: '', value: 'actions', sortable: false, align: 'end', width: '52px' }
            ],
            statusUpdatingId: null
        };
    },
    watch: {
        expanded: function (ids) {
            this.onExpand(ids);
        }
    },
    mounted: function () {
        var vm = this;
        this.loadCodelists();
        if (typeof EventBus !== 'undefined') {
            EventBus.$on('codelist-sdmx-open', vm.openImportDialog);
        }
    },
    beforeDestroy: function () {
        if (typeof EventBus !== 'undefined') {
            EventBus.$off('codelist-sdmx-open', this.openImportDialog);
        }
    },
    computed: {
        importCanSubmit: function () {
            if (this.importFormat === 'json') {
                return !!this.importFile;
            }
            if (this.importSource === 'url') {
                return (this.importUrl || '').trim().length > 0;
            }
            return !!this.importFile;
        },
        isAdmin: function () {
            return CI && CI.user_info && CI.user_info.is_admin === true;
        },
        canEditCodelist: function () {
            return CI && CI.user_info && CI.user_info.can_edit_codelist === true;
        },
        canImportCodelist: function () {
            return CI && CI.user_info && CI.user_info.can_import_codelist === true;
        },
        canDeleteCodelist: function () {
            return CI && CI.user_info && CI.user_info.can_delete_codelist === true;
        }
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
        notifySuccess: function (msg) {
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('onSuccess', msg);
            }
        },
        loadCodelists: function () {
            var vm = this;
            vm.loading = true;
            vm.expanded = [];
            vm.versionsByHead = {};
            vm.selected = [];
            var url = vm.apiBase() + '?with_counts=1';
            if (vm.search) {
                url += '&search=' + encodeURIComponent(vm.search);
            }
            axios.get(url)
                .then(function (res) {
                    vm.loading = false;
                    if (res.data && res.data.status === 'success') {
                        vm.codelists = res.data.codelists || [];
                        vm.total = res.data.total != null ? res.data.total : vm.codelists.length;
                    } else {
                        vm.codelists = [];
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
            vm.expanded = rows || [];
            (rows || []).forEach(function (id) {
                var head = vm.codelists.find(function (c) { return c.id === id; });
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
            if (!head || !head.name) {
                return;
            }
            var headId = head.id;
            vm.$set(vm.versionsLoading, headId, true);
            var url = vm.apiBase() + '/versions/' + encodeURIComponent(head.name) + '?with_counts=1';
            if (head.agency) {
                url += '&agency=' + encodeURIComponent(head.agency);
            }
            axios.get(url)
                .then(function (res) {
                    vm.$set(vm.versionsLoading, headId, false);
                    if (res.data && res.data.status === 'success') {
                        vm.$set(vm.versionsByHead, headId, res.data.codelists || []);
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
        goCreate: function () {
            this.$router.push('/edit');
        },
        goView: function (row) {
            this.$router.push('/view/' + row.id);
        },
        exportJsonUrl: function (row) {
            return this.apiBase() + '/export_json/' + row.id + '?download=1';
        },
        sdmxExportUrl: function (row, version) {
            return this.apiBase() + '/export_sdmx/' + row.id + '?version=' + version;
        },
        goEdit: function (row) {
            this.$router.push('/edit/' + row.id);
        },
        deleteCodelist: function (row) {
            var vm = this;
            var label = (row.title && String(row.title).trim()) || row.name || row.idno || row.id;
            if (!confirm('Delete codelist "' + label + '" and all its items? This cannot be undone.')) {
                return;
            }
            vm.deletingId = row.id;
            axios.post(vm.apiBase() + '/delete/' + row.id)
                .then(function () {
                    vm.deletingId = null;
                    vm.notifySuccess('Codelist deleted');
                    vm.loadCodelists();
                })
                .catch(function (err) {
                    vm.deletingId = null;
                    vm.notifyFail(err);
                });
        },
        openImportDialog: function (format) {
            this.importFormat = format === 'json' ? 'json' : 'sdmx';
            this.importSource = 'file';
            this.importFile = null;
            this.importUrl = '';
            this.importDryRun = false;
            this.importReplace = false;
            this.importDialog = true;
        },
        closeImportDialog: function () {
            if (this.importing) {
                return;
            }
            this.importDialog = false;
            this.importFile = null;
            this.importUrl = '';
        },
        submitImport: function () {
            if (this.importFormat === 'json') {
                this.submitJsonImport();
            } else {
                this.submitSdmxImport();
            }
        },
        submitJsonImport: function () {
            var vm = this;
            if (!vm.importFile) {
                vm.notifyFail({
                    response: { data: { message: 'Choose a .json file' } }
                });
                return;
            }
            var url = vm.apiBase() + '/import_json';
            var q = [];
            if (vm.importDryRun) {
                q.push('dry_run=1');
            }
            if (vm.importReplace) {
                q.push('replace=1');
            }
            if (q.length) {
                url += '?' + q.join('&');
            }
            var fd = new FormData();
            fd.append('file', vm.importFile);
            vm.importing = true;
            axios.post(url, fd)
                .then(function (res) {
                    vm.importing = false;
                    var d = res.data || {};
                    var msg = vm._formatJsonImportSummary(d);
                    if (d.warnings && d.warnings.length) {
                        msg += ' — ' + d.warnings.slice(0, 4).join(' · ');
                    }
                    if (d.status === 'failed') {
                        vm.notifyFail({ response: { data: { message: msg || 'Import failed' } } });
                        return;
                    }
                    vm.notifySuccess(msg || 'Import finished');
                    if (!vm.importDryRun) {
                        vm.loadCodelists();
                    }
                    vm.closeImportDialog();
                })
                .catch(function (err) {
                    vm.importing = false;
                    vm.notifyFail(err);
                });
        },
        submitSdmxImport: function () {
            var vm = this;
            if (!vm.importCanSubmit) {
                vm.notifyFail({
                    response: {
                        data: {
                            message: vm.importSource === 'url'
                                ? 'Enter a URL to SDMX-ML (https://…)'
                                : 'Choose an SDMX-ML (.xml) file'
                        }
                    }
                });
                return;
            }
            var url = vm.apiBase() + '/import_sdmx';
            var q = [];
            if (vm.importDryRun) {
                q.push('dry_run=1');
            }
            if (vm.importReplace) {
                q.push('replace=1');
            }
            if (q.length) {
                url += '?' + q.join('&');
            }
            vm.importing = true;
            var req;
            if (vm.importSource === 'url') {
                req = axios.post(url, { url: (vm.importUrl || '').trim() }, {
                    headers: { 'Content-Type': 'application/json' }
                });
            } else {
                var fd = new FormData();
                fd.append('file', vm.importFile);
                req = axios.post(url, fd);
            }
            req
                .then(function (res) {
                    vm.importing = false;
                    var d = res.data || {};
                    var msg = vm._formatImportSummary(d);
                    if (d.warnings && d.warnings.length) {
                        msg += ' — ' + d.warnings.slice(0, 4).join(' · ');
                        if (d.warnings.length > 4) {
                            msg += '…';
                        }
                    }
                    if (d.status === 'failed') {
                        vm.notifyFail({ response: { data: { message: msg || 'Import failed' } } });
                        return;
                    }
                    vm.notifySuccess(msg || 'Import finished');
                    if (!vm.importDryRun) {
                        vm.loadCodelists();
                    }
                    vm.closeImportDialog();
                })
                .catch(function (err) {
                    vm.importing = false;
                    vm.notifyFail(err);
                });
        },
        _formatJsonImportSummary: function (d) {
            if (!d || typeof d !== 'object') {
                return '';
            }
            var row = (d.imported && d.imported[0]) ? d.imported[0] : null;
            if (d.dry_run && row) {
                return 'Preview: ' + (row.planned_action || row.action || 'ok')
                    + ' — ' + (row.codes_count != null ? row.codes_count : 0) + ' codes (nothing saved)';
            }
            if (!row) {
                return 'Done';
            }
            if (row.action === 'skipped') {
                return 'Skipped (already exists; enable Replace to overwrite items)';
            }
            if (row.action === 'created') {
                return 'Created codelist ' + (row.name || row.id)
                    + ' with ' + (row.codes_imported != null ? row.codes_imported : 0) + ' codes';
            }
            if (row.action === 'updated') {
                return 'Updated codelist ' + (row.name || row.id)
                    + ' — ' + (row.codes_imported != null ? row.codes_imported : 0) + ' codes';
            }
            return 'Import finished';
        },
        _formatImportSummary: function (d) {
            if (!d || typeof d !== 'object') {
                return '';
            }
            if (d.dry_run) {
                var n = 0;
                (d.imported || []).forEach(function (x) {
                    n += x.codes_count != null ? x.codes_count : 0;
                });
                return 'Preview: ' + (d.imported || []).length + ' codelist(s), ~' + n + ' codes (nothing saved)';
            }
            var parts = [];
            if ((d.imported || []).length) {
                parts.push('Imported ' + d.imported.length + ' list(s)');
            }
            if ((d.skipped || []).length) {
                parts.push('Skipped ' + d.skipped.length + ' (already exist)');
            }
            if ((d.failed || []).length) {
                parts.push('Failed ' + d.failed.length);
            }
            if (d.sdmx_version) {
                parts.push('SDMX ' + d.sdmx_version);
            }
            var s = parts.join(' · ') || 'Done';
            if (d.status === 'partial') {
                s = 'Partial: ' + s;
            }
            return s;
        },
        statusLabel: function (status) {
            var s = (status || 'active').toLowerCase();
            var key = 'codelist_status_' + s;
            var t = this.$t(key);
            if (t && t !== key) {
                return t;
            }
            return s.charAt(0).toUpperCase() + s.slice(1);
        },
        statusColor: function (status) {
            var s = (status || 'active').toLowerCase();
            if (s === 'draft') return 'grey';
            if (s === 'locked') return 'orange darken-2';
            if (s === 'archived') return 'blue-grey';
            return 'green';
        },
        isContentMutable: function (item) {
            var s = (item && item.status) ? String(item.status).toLowerCase() : 'active';
            return s === 'draft' || s === 'active';
        },
        isRowSelectable: function (item) {
            if (!item || !item.id) {
                return false;
            }
            if (!this.isContentMutable(item)) {
                return false;
            }
            if (item.dsd_component_count != null && Number(item.dsd_component_count) > 0) {
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
        deleteSelectedCodelists: function () {
            var vm = this;
            var rows = (vm.selected || []).filter(function (item) {
                return vm.isRowSelectable(item);
            });
            if (!rows.length) {
                return;
            }
            var labels = rows.map(function (r) {
                return (r.title && String(r.title).trim()) || r.name || r.idno || r.id;
            });
            var preview = labels.slice(0, 3).join(', ');
            if (labels.length > 3) {
                preview += '…';
            }
            if (!confirm('Delete ' + rows.length + ' codelist(s)?\n\n' + preview + '\n\nThis cannot be undone.')) {
                return;
            }
            vm.batchDeleting = true;
            var chain = Promise.resolve();
            var failed = 0;
            rows.forEach(function (row) {
                chain = chain.then(function () {
                    return axios.post(vm.apiBase() + '/delete/' + row.id).catch(function () {
                        failed++;
                    });
                });
            });
            chain.then(function () {
                vm.batchDeleting = false;
                vm.clearSelection();
                if (failed) {
                    vm.notifyFail({
                        response: {
                            data: {
                                message: failed + ' of ' + rows.length + ' could not be deleted (DSD references or locked status).'
                            }
                        }
                    });
                } else {
                    vm.notifySuccess('Deleted ' + rows.length + ' codelist(s)');
                }
                vm.loadCodelists();
            });
        },
        setCodelistStatus: function (item, newStatus) {
            var vm = this;
            if (!item || !item.id) return;
            vm.statusUpdatingId = item.id;
            axios.post(vm.apiBase() + '/update/' + item.id, { status: newStatus })
                .then(function () {
                    vm.statusUpdatingId = null;
                    vm.notifySuccess('Status updated');
                    vm.loadCodelists();
                })
                .catch(function (err) {
                    vm.statusUpdatingId = null;
                    vm.notifyFail(err);
                });
        }
    },
    template: `
        <div>
            <v-card class="mb-4">
                <v-card-title class="d-flex flex-wrap align-center">
                    <span class="text-h6">{{ $t('codelists') || 'Codelists' }}</span>
                    <v-spacer></v-spacer>
                    <v-btn v-if="canImportCodelist" color="primary" outlined class="mr-2" @click="openImportDialog('json')">Import JSON</v-btn>
                    <v-btn v-if="canImportCodelist" color="primary" outlined class="mr-2" @click="openImportDialog('sdmx')">Import SDMX</v-btn>
                    <v-btn v-if="canEditCodelist" color="primary" outlined @click="goCreate">New codelist</v-btn>
                </v-card-title>
                <v-card-text class="pt-0 pb-2">
                    <v-text-field
                        v-model="search"
                        label="Search codelists"
                        single-line
                        hide-details
                        dense
                        outlined
                        clearable
                        class="mr-4"
                        style="max-width: 480px;"
                        @keyup.enter="loadCodelists"
                        @click:clear="search = ''; loadCodelists()"
                    >
                        <template v-slot:append>
                            <v-btn icon small :loading="loading" @click="loadCodelists" title="Search">
                                <v-icon small>mdi-magnify</v-icon>
                            </v-btn>
                        </template>
                    </v-text-field>
                    <div v-if="canDeleteCodelist && selected.length > 0" class="d-flex align-center flex-wrap mt-2 py-2 px-3 grey lighten-4 rounded">
                        <span class="text-body-2 font-weight-medium mr-3">{{ selected.length }} selected</span>
                        <v-btn
                            color="error"
                            small
                            depressed
                            class="mr-2"
                            :loading="batchDeleting"
                            :disabled="batchDeleting"
                            @click="deleteSelectedCodelists"
                        >
                            <v-icon small left>mdi-delete</v-icon>
                            Delete selected
                        </v-btn>
                        <v-btn text small :disabled="batchDeleting" @click="clearSelection">Clear</v-btn>
                    </div>
                </v-card-text>
                <v-data-table
                    v-model="selected"
                    :headers="headers"
                    :items="codelists"
                    :loading="loading"
                    loading-text="Loading..."
                    hide-default-footer
                    disable-sort
                    class="elevation-0"
                    :show-select="canDeleteCodelist"
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
                    <template v-slot:item.versions_count="{ item }">
                        <span v-if="item.versions_count > 1">{{ item.versions_count }}</span>
                        <span v-else class="grey--text">—</span>
                    </template>
                    <template v-slot:item.item_count="{ item }">
                        {{ item.item_count != null ? item.item_count : '—' }}
                    </template>
                    <template v-slot:item.dsd_component_count="{ item }">
                        <span :class="item.dsd_component_count > 0 ? 'orange--text text--darken-2' : ''">
                            {{ item.dsd_component_count != null ? item.dsd_component_count : '—' }}
                        </span>
                    </template>
                    <template v-slot:expanded-item="{ headers, item }">
                        <td :colspan="headers.length" class="pa-0 grey lighten-5">
                            <v-progress-linear v-if="versionsLoading[item.id]" indeterminate></v-progress-linear>
                            <v-simple-table v-else dense class="elevation-0 transparent">
                                <thead>
                                    <tr>
                                        <th class="text-left">Version</th>
                                        <th class="text-left">Status</th>
                                        <th class="text-left">Title</th>
                                        <th class="text-end">Items</th>
                                        <th class="text-end">DSD</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="ver in versionRowsFor(item)" :key="ver.id">
                                        <td>{{ ver.version }}</td>
                                        <td>
                                            <v-chip x-small :color="statusColor(ver.status)" dark class="text-capitalize">
                                                {{ statusLabel(ver.status) }}
                                            </v-chip>
                                        </td>
                                        <td>{{ ver.title || ver.name }}</td>
                                        <td class="text-end">{{ ver.item_count != null ? ver.item_count : '—' }}</td>
                                        <td class="text-end">{{ ver.dsd_component_count != null ? ver.dsd_component_count : '—' }}</td>
                                        <td class="text-end">
                                            <v-btn x-small text color="primary" @click="goEdit(ver)">Open</v-btn>
                                        </td>
                                    </tr>
                                </tbody>
                            </v-simple-table>
                        </td>
                    </template>
                    <template v-slot:item.title="{ item }">
                        <a href="#" class="text-decoration-none d-inline-flex align-center" @click.prevent="canEditCodelist && isContentMutable(item) ? goEdit(item) : goView(item)">
                            <v-icon small class="mr-2 grey--text text--darken-1">mdi-format-list-bulleted-type</v-icon>
                            <span>{{ item.title || item.name }}</span>
                        </a>
                    </template>
                    <template v-slot:item.status="{ item }">
                        <v-chip x-small :color="statusColor(item.status)" dark class="text-capitalize">
                            {{ statusLabel(item.status) }}
                        </v-chip>
                    </template>
                    <template v-slot:item.actions="{ item }">
                        <v-menu offset-y left>
                            <template v-slot:activator="{ on, attrs }">
                                <v-btn icon small v-bind="attrs" v-on="on" :loading="deletingId === item.id">
                                    <v-icon small>mdi-dots-vertical</v-icon>
                                </v-btn>
                            </template>
                            <v-list dense>
                                <v-list-item @click="goView(item)">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-eye-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title>View</v-list-item-title>
                                </v-list-item>
                                <v-list-item v-if="canEditCodelist && isContentMutable(item)" @click="goEdit(item)">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-pencil-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title>Edit</v-list-item-title>
                                </v-list-item>
                                <template v-if="isAdmin">
                                    <v-divider></v-divider>
                                    <v-list-item v-if="isContentMutable(item)" @click="setCodelistStatus(item, 'locked')" :disabled="statusUpdatingId === item.id">
                                        <v-list-item-icon class="mr-2"><v-icon small>mdi-lock-outline</v-icon></v-list-item-icon>
                                        <v-list-item-title>{{ $t('codelist_lock') || 'Lock' }}</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item v-if="item.status === 'locked'" @click="setCodelistStatus(item, 'active')" :disabled="statusUpdatingId === item.id">
                                        <v-list-item-icon class="mr-2"><v-icon small>mdi-lock-open-outline</v-icon></v-list-item-icon>
                                        <v-list-item-title>{{ $t('codelist_unlock') || 'Unlock' }}</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item v-if="item.status !== 'archived'" @click="setCodelistStatus(item, 'archived')" :disabled="statusUpdatingId === item.id">
                                        <v-list-item-icon class="mr-2"><v-icon small>mdi-archive-outline</v-icon></v-list-item-icon>
                                        <v-list-item-title>{{ $t('codelist_archive') || 'Archive' }}</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item v-if="item.status === 'archived'" @click="setCodelistStatus(item, 'active')" :disabled="statusUpdatingId === item.id">
                                        <v-list-item-icon class="mr-2"><v-icon small>mdi-archive-arrow-up-outline</v-icon></v-list-item-icon>
                                        <v-list-item-title>Restore</v-list-item-title>
                                    </v-list-item>
                                </template>
                                <v-divider></v-divider>
                                <v-list-item :href="exportJsonUrl(item)" target="_blank" rel="noopener noreferrer">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-code-json</v-icon></v-list-item-icon>
                                    <v-list-item-title>Export JSON</v-list-item-title>
                                </v-list-item>
                                <v-list-item :href="sdmxExportUrl(item, '2.1')">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-download-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title>SDMX 2.1</v-list-item-title>
                                </v-list-item>
                                <v-list-item :href="sdmxExportUrl(item, '3.0')">
                                    <v-list-item-icon class="mr-2"><v-icon small>mdi-download-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title>SDMX 3.0</v-list-item-title>
                                </v-list-item>
                                <v-divider v-if="canDeleteCodelist"></v-divider>
                                <v-list-item v-if="canDeleteCodelist && isContentMutable(item)" @click="deleteCodelist(item)" :disabled="deletingId != null && deletingId !== item.id">
                                    <v-list-item-icon class="mr-2"><v-icon small color="error">mdi-delete-outline</v-icon></v-list-item-icon>
                                    <v-list-item-title class="error--text">Delete</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </template>
                </v-data-table>
                <v-card-text v-if="!loading && total > codelists.length" class="text-caption grey--text">
                    Total: {{ total }} (showing {{ codelists.length }} — use API pagination for more)
                </v-card-text>
            </v-card>

            <v-dialog v-model="importDialog" max-width="560" @click:outside="closeImportDialog">
                <v-card>
                    <v-card-title>{{ importFormat === 'json' ? 'Import codelist (JSON)' : 'Import codelists (SDMX-ML)' }}</v-card-title>
                    <v-card-text>
                        <template v-if="importFormat === 'json'">
                            <v-file-input
                                v-model="importFile"
                                dense
                                outlined
                                accept=".json,application/json"
                                label="JSON file"
                                prepend-icon="mdi-code-json"
                                :disabled="importing"
                                show-size
                            ></v-file-input>
                        </template>
                        <template v-else>
                            <p class="text-body-2 grey--text text--darken-1 mb-2">
                                SDMX structure message (2.1 or 3.0) with <code>Codelist</code> or <code>HierarchicalCodelist</code> elements.
                            </p>
                            <v-radio-group v-model="importSource" row hide-details dense class="mt-0 mb-3">
                                <v-radio label="Upload file" value="file" :disabled="importing"></v-radio>
                                <v-radio label="From URL" value="url" :disabled="importing"></v-radio>
                            </v-radio-group>
                            <v-file-input
                                v-show="importSource === 'file'"
                                v-model="importFile"
                                dense
                                outlined
                                accept=".xml,text/xml,application/xml"
                                label="SDMX-ML file"
                                prepend-icon="mdi-file-xml-box"
                                :disabled="importing"
                                show-size
                            ></v-file-input>
                            <v-text-field
                                v-show="importSource === 'url'"
                                v-model="importUrl"
                                dense
                                outlined
                                clearable
                                label="URL to SDMX-ML"
                                placeholder="https://example.org/structure.xml"
                                prepend-inner-icon="mdi-link"
                                :disabled="importing"
                                hint="Server fetches this URL (http/https only; private networks blocked)."
                                persistent-hint
                            ></v-text-field>
                        </template>
                        <v-checkbox v-if="importFormat !== 'json'" v-model="importDryRun" hide-details dense class="mt-0" label="Preview only (dry run — do not save)" :disabled="importing"></v-checkbox>
                        <v-checkbox v-model="importReplace" hide-details dense class="mt-0"
                            :label="importFormat === 'json' ? 'Overwrite if already exists?' : 'Replace existing lists with the same agency, id, and version'" :disabled="importing"></v-checkbox>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text :disabled="importing" @click="closeImportDialog">Cancel</v-btn>
                        <v-btn color="primary" :loading="importing" :disabled="!importCanSubmit" @click="submitImport">Import</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
