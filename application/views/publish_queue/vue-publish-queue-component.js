Vue.component('vue-publish-queue-component', {
    data: function () {
        return {
            viewTab: 0,
            historyTabSeen: false,
            loading: false,
            error: '',
            rows: [],
            total: 0,
            pagination: {
                page: 1,
                itemsPerPage: 25,
            },
            tableOptions: {
                page: 1,
                itemsPerPage: 25,
                sortBy: [],
                sortDesc: [],
                groupBy: [],
                groupDesc: [],
                multiSort: false,
                mustSort: false,
            },
            filterCatalogId: null,
            detailDialog: false,
            selectedRow: null,
            resolveAction: null,
            resolveNote: '',
            resolveRemoteId: '',
            resolveRemoteUrl: '',
            resolving: false,
            resolveError: '',
            selected: [],
            batchAction: null,
            batchNote: '',
            batchDialog: false,
            batchApplying: false,
            batchError: '',
            batchSuccess: '',
            historyLoading: false,
            historyError: '',
            historyRows: [],
            historyTotal: 0,
            historySearch: '',
            historyTableOptions: {
                page: 1,
                itemsPerPage: 25,
                sortBy: [],
                sortDesc: [],
                groupBy: [],
                groupDesc: [],
                multiSort: false,
                mustSort: false,
            },
            accessPolicyLabels: {
                open: 'Open access',
                direct: 'Direct access',
                public: 'Public use files',
                licensed: 'Licensed data files',
                remote: 'Data accessible only in data enclave',
                enclave: 'Data available from external repository',
                '': 'Data not available',
                data_na: 'Data not available',
            },
            historyDetailDialog: false,
            historyDetailRow: null,
        };
    },
    mounted: function () {
        this.syncTabFromUrl();
        this.loadQueue();
        if (this.viewTab === 1) {
            this.loadHistory();
        }
    },
    computed: {
        apiBase: function () {
            return (CI.site_url || CI.base_url || '').replace(/\/?$/, '/') + 'api/publish_requests';
        },
        editorBase: function () {
            return (CI.site_url || CI.base_url || '').replace(/\/?$/, '/');
        },
        tableHeaders: function () {
            return [
                { text: this.$t('project') || 'Project', value: 'project_title' },
                { text: this.$t('catalog') || 'Catalog', value: 'catalog_title' },
                { text: this.$t('type') || 'Type', value: 'is_update', width: '90px' },
                { text: this.$t('data_access') || 'Data access', value: 'access_policy' },
                { text: this.$t('collection') || 'Collection', value: 'repositoryid' },
                { text: this.$t('submitter') || 'Submitter', value: 'ready_by_username', width: '140px' },
                { text: this.$t('queued_since') || 'Queued since', value: 'ready_at', width: '160px' },
                { text: '', value: 'actions', sortable: false, width: '56px', align: 'end' },
            ];
        },
        queueActionOptions: function () {
            return [
                { value: 'published', text: this.$t('queue_action_published') || 'Published' },
                { value: 'return', text: this.$t('queue_action_return') || 'Return' },
                { value: 'closed', text: this.$t('queue_action_closed') || 'Closed' },
                { value: 'cancel', text: this.$t('queue_action_cancel') || 'Cancel' },
            ];
        },
        canSubmitResolve: function () {
            if (!this.resolveAction || this.resolving) {
                return false;
            }
            return !!(this.resolveNote || '').trim();
        },
        selectedCount: function () {
            return Array.isArray(this.selected) ? this.selected.length : 0;
        },
        canSubmitBatch: function () {
            if (this.batchApplying || this.selectedCount < 1 || !this.batchAction) {
                return false;
            }
            return !!(this.batchNote || '').trim();
        },
        historyHeaders: function () {
            return [
                { text: this.$t('date') || 'Date', value: 'created', width: '160px' },
                { text: this.$t('project') || 'Project', value: 'project_title' },
                { text: 'Catalog', value: 'catalog_title' },
                { text: 'Event', value: 'event', width: '160px' },
                { text: this.$t('queue_history_requester') || 'Requester', value: 'requester_username', width: '140px' },
                { text: this.$t('queue_history_reviewer') || 'Reviewer', value: 'reviewer_username', width: '140px' },
                { text: 'Note', value: 'note', sortable: false },
            ];
        },
    },
    watch: {
        viewTab: function (tab) {
            if (tab === 1) {
                this.historyTabSeen = true;
                if (!this.historyLoading) {
                    this.loadHistory();
                }
            }
            this.updateTabInUrl();
        },
        historyTableOptions: {
            deep: true,
            handler: function () {
                if (this.viewTab === 1) {
                    this.loadHistory();
                }
            },
        },
        tableOptions: {
            deep: true,
            handler: function () {
                this.loadQueue();
            },
        },
        filterCatalogId: function () {
            this.tableOptions.page = 1;
            this.clearSelection();
            this.loadQueue();
        },
        'tableOptions.page': function () {
            this.clearSelection();
        },
    },
    methods: {
        syncTabFromUrl: function () {
            try {
                var params = new URLSearchParams(window.location.search || '');
                if (params.get('tab') === 'history') {
                    this.viewTab = 1;
                    this.historyTabSeen = true;
                }
            } catch (e) {
                // ignore
            }
        },
        updateTabInUrl: function () {
            try {
                var url = new URL(window.location.href);
                if (this.viewTab === 1) {
                    url.searchParams.set('tab', 'history');
                } else {
                    url.searchParams.delete('tab');
                }
                var query = url.searchParams.toString();
                window.history.replaceState({}, '', url.pathname + (query ? '?' + query : ''));
            } catch (e) {
                // ignore
            }
        },
        loadHistory: function () {
            var vm = this;
            vm.historyLoading = true;
            vm.historyError = '';
            var offset = (vm.historyTableOptions.page - 1) * vm.historyTableOptions.itemsPerPage;
            axios.get(vm.apiBase + '/queue/history', {
                params: {
                    limit: vm.historyTableOptions.itemsPerPage,
                    offset: offset,
                },
            })
                .then(function (response) {
                    vm.historyRows = (response.data && response.data.rows) ? response.data.rows : [];
                    vm.historyTotal = response.data && response.data.total ? response.data.total : 0;
                })
                .catch(function (err) {
                    vm.historyError = (err.response && err.response.data && err.response.data.message)
                        ? err.response.data.message
                        : (err.message || 'Failed to load history');
                    vm.historyRows = [];
                    vm.historyTotal = 0;
                })
                .finally(function () {
                    vm.historyLoading = false;
                });
        },
        refreshHistoryIfLoaded: function () {
            if (this.historyTabSeen || this.viewTab === 1) {
                this.loadHistory();
            }
        },
        historyEventLabel: function (row) {
            return window.HistoryEventDisplay.eventLabel(row, this);
        },
        historyEventColor: function (row) {
            return window.HistoryEventDisplay.eventColor(row);
        },
        historyEventNote: function (row) {
            return window.HistoryEventDisplay.tableNoteText(row, this);
        },
        historyEventNotePreview: function (row) {
            return window.HistoryEventDisplay.notePreview(row, this, 120);
        },
        historyEventHasDetail: function (row) {
            var display = window.HistoryEventDisplay;
            return display.isNoteTruncated(row, this, 120)
                || !!display.noteText(row, this)
                || !!display.metadataSummary(row, this);
        },
        openHistoryDetailDialog: function (row) {
            this.historyDetailRow = row;
            this.historyDetailDialog = true;
        },
        closeHistoryDetailDialog: function () {
            this.historyDetailDialog = false;
            this.historyDetailRow = null;
        },
        loadQueue: function () {
            var vm = this;
            vm.loading = true;
            vm.error = '';
            var offset = (vm.tableOptions.page - 1) * vm.tableOptions.itemsPerPage;
            var params = {
                limit: vm.tableOptions.itemsPerPage,
                offset: offset,
            };
            if (vm.filterCatalogId) {
                params.catalog_id = vm.filterCatalogId;
            }
            axios.get(vm.apiBase + '/queue', { params: params })
                .then(function (response) {
                    vm.rows = (response.data && response.data.rows) ? response.data.rows : [];
                    vm.total = response.data && response.data.total ? response.data.total : 0;
                })
                .catch(function (err) {
                    vm.error = (err.response && err.response.data && err.response.data.message)
                        ? err.response.data.message
                        : (err.message || 'Failed to load queue');
                    vm.rows = [];
                    vm.total = 0;
                })
                .finally(function () {
                    vm.loading = false;
                });
        },
        accessPolicyLabel: function (row) {
            var code = row && row.options && row.options.access_policy;
            if (code === undefined || code === null || code === '') {
                return '—';
            }
            var key = String(code);
            if (this.accessPolicyLabels[key]) {
                return this.accessPolicyLabels[key];
            }
            return key;
        },
        rowAccessPolicy: function (row) {
            return this.accessPolicyLabel(row);
        },
        rowRepositoryId: function (row) {
            return row.options && row.options.repositoryid ? row.options.repositoryid : '—';
        },
        rowRemoteDataUrl: function (row) {
            return row.options && row.options.data_remote_url ? row.options.data_remote_url : '—';
        },
        rowTypeLabel: function (row) {
            return row.is_update ? (this.$t('update') || 'Update') : (this.$t('new') || 'New');
        },
        typeChipColor: function (row) {
            return row && row.is_update ? 'blue darken-2' : 'green darken-2';
        },
        projectEditUrl: function (row) {
            row = row || this.selectedRow;
            if (!row || !row.sid) {
                return '#';
            }
            return this.editorBase + 'editor/edit/' + row.sid;
        },
        formatTimestamp: function (ts) {
            if (!ts) {
                return '—';
            }
            if (typeof moment !== 'undefined') {
                return moment.unix(ts).format('YYYY-MM-DD HH:mm');
            }
            return String(ts);
        },
        resetDetailDialogForm: function () {
            this.resolveAction = null;
            this.resolveNote = '';
            this.resolveRemoteId = '';
            this.resolveRemoteUrl = '';
            this.resolveError = '';
            this.resolving = false;
        },
        openDetailDialog: function (row) {
            this.resetDetailDialogForm();
            this.selectedRow = row || null;
            this.detailDialog = !!row;
        },
        closeDetailDialog: function () {
            this.detailDialog = false;
            this.selectedRow = null;
            this.resetDetailDialogForm();
        },
        publishProjectUrl: function (row) {
            row = row || this.selectedRow;
            if (!row || !row.sid) {
                return '#';
            }
            var url = this.editorBase + 'editor/edit/' + row.sid + '#/publish?tab=nada';
            if (row.id) {
                url += '&publication_id=' + encodeURIComponent(row.id);
            }
            return url;
        },
        submitResolve: function () {
            var vm = this;
            var row = vm.selectedRow;
            if (!row || !row.id || !vm.resolveAction) {
                return;
            }
            var note = (vm.resolveNote || '').trim();
            if (!note) {
                vm.resolveError = vm.$t('queue_action_note_required') || 'A note is required.';
                return;
            }
            vm.resolving = true;
            vm.resolveError = '';
            var payload = {
                action: vm.resolveAction,
                note: note,
            };
            if (vm.resolveAction === 'published') {
                payload.status = 'published';
                if ((vm.resolveRemoteId || '').trim()) {
                    payload.remote_id = vm.resolveRemoteId.trim();
                }
                if ((vm.resolveRemoteUrl || '').trim()) {
                    payload.remote_url = vm.resolveRemoteUrl.trim();
                }
            }
            axios.post(vm.apiBase + '/' + row.id + '/resolve', payload)
                .then(function () {
                    vm.closeDetailDialog();
                    vm.loadQueue();
                    vm.refreshHistoryIfLoaded();
                })
                .catch(function (err) {
                    vm.resolveError = (err.response && err.response.data && err.response.data.message)
                        ? err.response.data.message
                        : (err.message || 'Failed to resolve queue item');
                })
                .finally(function () {
                    vm.resolving = false;
                });
        },
        catalogOptions: function () {
            var seen = {};
            var options = [];
            this.rows.forEach(function (row) {
                var id = row.catalog_id;
                if (!id || seen[id]) {
                    return;
                }
                seen[id] = true;
                options.push({
                    value: id,
                    text: row.catalog_title || ('Catalog #' + id),
                });
            });
            return options;
        },
        clearSelection: function () {
            this.selected = [];
            this.batchSuccess = '';
            this.batchError = '';
        },
        openBatchDialog: function () {
            if (this.selectedCount < 1) {
                return;
            }
            this.batchError = '';
            this.batchAction = null;
            this.batchNote = '';
            this.batchDialog = true;
        },
        closeBatchDialog: function () {
            this.batchDialog = false;
            this.batchError = '';
        },
        submitBatch: function () {
            var vm = this;
            if (!vm.canSubmitBatch) {
                vm.batchError = vm.$t('queue_action_note_required') || 'A note is required.';
                return;
            }
            var publicationIds = vm.selected.map(function (row) {
                return row.id;
            }).filter(function (id) {
                return id > 0;
            });
            if (!publicationIds.length) {
                return;
            }
            vm.batchApplying = true;
            vm.batchError = '';
            vm.batchSuccess = '';
            var payload = {
                publication_ids: publicationIds,
                action: vm.batchAction,
                note: (vm.batchNote || '').trim(),
            };
            if (vm.batchAction === 'published') {
                payload.status = 'published';
            }
            axios.post(vm.apiBase + '/resolve_batch', payload)
                .then(function (response) {
                    var data = response.data || {};
                    var succeeded = data.succeeded_count != null ? data.succeeded_count : 0;
                    var failed = data.failed_count != null ? data.failed_count : 0;
                    vm.batchSuccess = (vm.$t('queue_batch_action_done') || '{n} succeeded, {f} failed')
                        .replace('{n}', succeeded)
                        .replace('{f}', failed);
                    vm.closeBatchDialog();
                    vm.clearSelection();
                    vm.batchAction = null;
                    vm.batchNote = '';
                    vm.loadQueue();
                    vm.refreshHistoryIfLoaded();
                })
                .catch(function (err) {
                    vm.batchError = (err.response && err.response.data && err.response.data.message)
                        ? err.response.data.message
                        : (err.message || 'Batch action failed');
                })
                .finally(function () {
                    vm.batchApplying = false;
                });
        },
    },
    template: `
    <div class="publish-queue-page p-3">
        <v-card>
            <v-card-title>{{ $t('publishing_queue') || 'Publishing queue' }}</v-card-title>
            <v-card-subtitle>{{ $t('publishing_queue_page_note') || 'Projects queued for publishing by catalog owners.' }}</v-card-subtitle>
            <v-card-text>
                <v-tabs v-model="viewTab" class="mb-3">
                    <v-tab>{{ $t('publishing_queue_tab') || 'Queue' }}</v-tab>
                    <v-tab>{{ $t('publishing_queue_history_tab') || 'History' }}</v-tab>
                </v-tabs>

                <v-tabs-items v-model="viewTab">
                    <v-tab-item>
                <v-alert v-if="error" type="error" dense outlined class="mb-3">{{ error }}</v-alert>
                <v-alert v-if="batchSuccess" type="success" dense outlined class="mb-3">{{ batchSuccess }}</v-alert>
                <v-alert v-if="batchError && !batchDialog" type="error" dense outlined class="mb-3">{{ batchError }}</v-alert>

                <div v-if="selectedCount > 0" class="d-flex align-center flex-wrap mb-3 pa-2 rounded border">
                    <v-btn
                        small
                        outlined
                        color="primary"
                        class="mr-3"
                        @click="openBatchDialog"
                    >{{ $t('queue_batch_action') || 'Batch action' }}</v-btn>
                    <span class="text-subtitle-2 font-weight-medium">
                        {{ ($t('queue_batch_selected') || '{n} selected').replace('{n}', selectedCount) }}
                    </span>
                </div>

                <v-data-table
                    class="elevation-1"
                    :headers="tableHeaders"
                    :items="rows"
                    item-key="id"
                    :loading="loading"
                    :server-items-length="total"
                    :options.sync="tableOptions"
                    :footer-props="{ 'items-per-page-options': [10, 25, 50, 100] }"
                    show-select
                    v-model="selected"
                    @click:row="openDetailDialog"
                >
                    <template v-slot:item.project_title="{ item }">
                        <div><strong>{{ item.project_title || ('Project #' + item.sid) }}</strong></div>
                        <small v-if="item.project_idno" class="text-muted">{{ item.project_idno }}</small>
                    </template>
                    <template v-slot:item.catalog_title="{ item }">
                        <div>{{ item.catalog_title }}</div>
                        <small v-if="item.catalog_url" class="text-muted">{{ item.catalog_url }}</small>
                    </template>
                    <template v-slot:item.is_update="{ item }">
                        <v-chip x-small :color="typeChipColor(item)" dark>{{ rowTypeLabel(item) }}</v-chip>
                    </template>
                    <template v-slot:item.access_policy="{ item }">
                        {{ rowAccessPolicy(item) }}
                    </template>
                    <template v-slot:item.repositoryid="{ item }">
                        {{ rowRepositoryId(item) }}
                    </template>
                    <template v-slot:item.ready_by_username="{ item }">
                        {{ item.ready_by_username || '—' }}
                    </template>
                    <template v-slot:item.ready_at="{ item }">
                        {{ formatTimestamp(item.ready_at) }}
                    </template>
                    <template v-slot:item.actions="{ item }">
                        <v-btn
                            icon
                            x-small
                            color="primary"
                            :title="$t('view_details') || 'View details'"
                            @click.stop="openDetailDialog(item)"
                        >
                            <v-icon small>mdi-information-outline</v-icon>
                        </v-btn>
                    </template>
                </v-data-table>
                    </v-tab-item>

                    <v-tab-item v-if="historyTabSeen || viewTab === 1">
                        <v-alert v-if="historyError" type="error" dense outlined class="mb-3">{{ historyError }}</v-alert>

                        <v-data-table
                            class="elevation-1 publish-queue-history-table history-event-table"
                            :headers="historyHeaders"
                            :items="historyRows"
                            item-key="id"
                            :loading="historyLoading"
                            :server-items-length="historyTotal"
                            :options.sync="historyTableOptions"
                            :footer-props="{ 'items-per-page-options': [10, 25, 50, 100] }"
                            @click:row="openHistoryDetailDialog"
                        >
                            <template v-slot:item.created="{ item }">
                                {{ formatTimestamp(item.created) }}
                            </template>
                            <template v-slot:item.project_title="{ item }">
                                <div>
                                    <a
                                        v-if="item.sid"
                                        :href="projectEditUrl(item)"
                                        target="_blank"
                                        rel="noopener"
                                        class="font-weight-medium text-decoration-none"
                                        @click.stop
                                    >{{ item.project_title || ('Project #' + item.sid) }}</a>
                                    <span v-else class="font-weight-medium">{{ item.project_title || '—' }}</span>
                                </div>
                                <small v-if="item.project_idno" class="text-muted">{{ item.project_idno }}</small>
                            </template>
                            <template v-slot:item.catalog_title="{ item }">
                                <div>{{ item.catalog_title || ('Catalog #' + item.catalog_id) }}</div>
                                <small v-if="item.catalog_url" class="text-muted">{{ item.catalog_url }}</small>
                            </template>
                            <template v-slot:item.event="{ item }">
                                <v-chip x-small :color="historyEventColor(item)" dark>{{ historyEventLabel(item) }}</v-chip>
                            </template>
                            <template v-slot:item.requester_username="{ item }">
                                {{ item.requester_username || '—' }}
                            </template>
                            <template v-slot:item.reviewer_username="{ item }">
                                {{ item.reviewer_username || '—' }}
                            </template>
                            <template v-slot:item.note="{ item }">
                                <div class="d-flex align-center">
                                    <span class="text-body-2 flex-grow-1">{{ historyEventNotePreview(item) }}</span>
                                    <v-btn
                                        v-if="historyEventHasDetail(item)"
                                        icon
                                        x-small
                                        color="primary"
                                        :title="$t('view_details') || 'View details'"
                                        @click.stop="openHistoryDetailDialog(item)"
                                    >
                                        <v-icon>mdi-information-outline</v-icon>
                                    </v-btn>
                                </div>
                            </template>
                        </v-data-table>

                        <div v-if="!historyLoading && historyRows.length === 0" class="text-muted pa-3">
                            {{ $t('no_publishing_queue_history') || 'No queue history yet.' }}
                        </div>
                    </v-tab-item>
                </v-tabs-items>
            </v-card-text>
        </v-card>

        <v-dialog v-model="detailDialog" max-width="640" scrollable @click:outside="closeDetailDialog">
            <v-card v-if="selectedRow">
                <v-card-title class="text-h6 d-flex align-center py-3">
                    <span>{{ $t('queue_item_details') || 'Queue item details' }}</span>
                    <v-spacer></v-spacer>
                    <v-btn
                        small
                        outlined
                        color="primary"
                        :href="publishProjectUrl(selectedRow)"
                        target="_blank"
                        rel="noopener"
                        class="flex-shrink-0"
                        :title="$t('queue_open_to_publish_tooltip') || 'Opens publish tab with catalog and owner hints prefilled'"
                    >
                        {{ $t('queue_open_publish_tab') || 'Open publish tab' }}
                        <v-icon x-small class="ml-1">mdi-open-in-new</v-icon>
                    </v-btn>
                </v-card-title>
                <v-card-text class="pt-2">
                    <v-card outlined class="mb-4">
                        <v-card-text class="py-3 queue-detail-project-title">
                            <a
                                :href="projectEditUrl(selectedRow)"
                                target="_blank"
                                rel="noopener"
                                class="font-weight-bold d-inline-flex align-start text-decoration-none"
                                style="line-height: 1.4; word-break: break-word;"
                            >
                                <span>{{ selectedRow.project_title || ('Project #' + selectedRow.sid) }}</span>
                                <v-icon x-small class="ml-1 mt-1 flex-shrink-0">mdi-open-in-new</v-icon>
                            </a>
                            <div v-if="selectedRow.project_idno" class="text-caption grey--text mt-1">{{ selectedRow.project_idno }}</div>
                        </v-card-text>
                    </v-card>
                    <v-simple-table dense class="mb-4 queue-detail-table">
                        <tbody>
                            <tr v-if="selectedRow.project_type">
                                <td class="text-muted">{{ $t('project_type') || 'Project type' }}</td>
                                <td>{{ selectedRow.project_type }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ $t('type') || 'Type' }}</td>
                                <td>
                                    <v-chip x-small :color="typeChipColor(selectedRow)" dark>{{ rowTypeLabel(selectedRow) }}</v-chip>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ $t('submitter') || 'Submitter' }}</td>
                                <td>{{ selectedRow.ready_by_username || '—' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ $t('queued_since') || 'Queued since' }}</td>
                                <td>{{ formatTimestamp(selectedRow.ready_at) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ $t('catalog') || 'Catalog' }}</td>
                                <td>{{ selectedRow.catalog_title || '—' }}</td>
                            </tr>
                            <tr v-if="selectedRow.catalog_url">
                                <td class="text-muted">{{ $t('url') || 'URL' }}</td>
                                <td>
                                    <a :href="selectedRow.catalog_url" target="_blank" rel="noopener">{{ selectedRow.catalog_url }}</a>
                                </td>
                            </tr>
                            <tr v-if="selectedRow.remote_url">
                                <td class="text-muted">{{ $t('previously_published') || 'Previously published on catalog' }}</td>
                                <td>
                                    <a :href="selectedRow.remote_url" target="_blank" rel="noopener">{{ selectedRow.remote_id || selectedRow.remote_url }}</a>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ $t('data_access') || 'Data access' }}</td>
                                <td>{{ accessPolicyLabel(selectedRow) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ $t('collection') || 'Collection' }}</td>
                                <td>{{ rowRepositoryId(selectedRow) }}</td>
                            </tr>
                            <tr v-if="selectedRow.options && selectedRow.options.data_remote_url">
                                <td class="text-muted">{{ $t('data_access_link') || 'Remote data URL' }}</td>
                                <td>
                                    <a :href="selectedRow.options.data_remote_url" target="_blank" rel="noopener">{{ selectedRow.options.data_remote_url }}</a>
                                </td>
                            </tr>
                        </tbody>
                    </v-simple-table>

                    <v-card outlined>
                        <v-card-title class="text-subtitle-2 font-weight-medium py-2">{{ $t('queue_action_section_title') || 'Action' }}</v-card-title>
                        <v-card-text class="pt-0 pb-3">
                            <div class="mb-2">
                                <label class="v-label theme--light">{{ $t('queue_action_label') || 'Action' }}</label>
                            </div>
                            <v-select
                                v-model="resolveAction"
                                :items="queueActionOptions"
                                item-text="text"
                                item-value="value"
                                outlined
                                dense
                                clearable
                                hide-details
                                :disabled="resolving"
                                class="mb-3"
                            ></v-select>
                            <template v-if="resolveAction === 'published'">
                                <div class="mb-2">
                                    <label class="v-label theme--light">{{ $t('study_idno') || 'Study ID / remote ID' }}</label>
                                </div>
                                <v-text-field
                                    v-model="resolveRemoteId"
                                    outlined
                                    dense
                                    hide-details
                                    :disabled="resolving"
                                    class="mb-3"
                                ></v-text-field>
                                <div class="mb-2">
                                    <label class="v-label theme--light">{{ $t('url') || 'Catalog URL' }}</label>
                                </div>
                                <v-text-field
                                    v-model="resolveRemoteUrl"
                                    outlined
                                    dense
                                    hide-details
                                    :disabled="resolving"
                                    class="mb-3"
                                ></v-text-field>
                            </template>
                            <v-alert v-if="resolveError" type="error" dense outlined class="mb-3">{{ resolveError }}</v-alert>
                            <div class="mb-2">
                                <label class="v-label theme--light">{{ $t('note') || 'Note' }}</label>
                            </div>
                            <v-textarea
                                v-model="resolveNote"
                                outlined
                                dense
                                rows="3"
                                auto-grow
                                hide-details
                                :disabled="resolving"
                                class="mb-3"
                            ></v-textarea>
                            <v-btn
                                small
                                outlined
                                color="primary"
                                :loading="resolving"
                                :disabled="!canSubmitResolve"
                                @click="submitResolve"
                            >{{ $t('queue_action_submit') || 'Submit action' }}</v-btn>
                        </v-card-text>
                    </v-card>
                </v-card-text>
                <v-card-actions class="pa-3">
                    <v-spacer></v-spacer>
                    <v-btn text @click="closeDetailDialog">{{ $t('close') || 'Close' }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="batchDialog" max-width="560" persistent scrollable>
            <v-card>
                <v-card-title class="text-h6">{{ $t('queue_batch_dialog_title') || 'Batch action' }}</v-card-title>
                <v-card-subtitle class="pb-0">
                    {{ ($t('queue_batch_dialog_subtitle') || '{n} selected on this page').replace('{n}', selectedCount) }}
                </v-card-subtitle>
                <v-card-text class="pt-4">
                    <div class="mb-2">
                        <label class="v-label theme--light">{{ $t('queue_action_label') || 'Action' }}</label>
                    </div>
                    <v-select
                        v-model="batchAction"
                        :items="queueActionOptions"
                        item-text="text"
                        item-value="value"
                        outlined
                        dense
                        clearable
                        hide-details
                        :disabled="batchApplying"
                        class="mb-3"
                    ></v-select>
                    <v-alert v-if="batchError" type="error" dense outlined class="mb-3">{{ batchError }}</v-alert>
                    <div class="mb-2">
                        <label class="v-label theme--light">{{ $t('note') || 'Note' }}</label>
                    </div>
                    <v-textarea
                        v-model="batchNote"
                        outlined
                        dense
                        rows="3"
                        auto-grow
                        hide-details
                        :disabled="batchApplying"
                        class="mb-2"
                    ></v-textarea>
                </v-card-text>
                <v-card-actions class="pa-3">
                    <v-spacer></v-spacer>
                    <v-btn text :disabled="batchApplying" @click="closeBatchDialog">{{ $t('cancel') || 'Cancel' }}</v-btn>
                    <v-btn
                        color="primary"
                        text
                        :loading="batchApplying"
                        :disabled="!canSubmitBatch"
                        @click="submitBatch"
                    >{{ $t('queue_batch_apply') || 'Apply to selected' }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <history-event-detail-dialog
            v-model="historyDetailDialog"
            :row="historyDetailRow"
            variant="global"
        ></history-event-detail-dialog>
    </div>
    `,
});
