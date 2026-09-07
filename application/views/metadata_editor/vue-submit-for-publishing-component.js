Vue.component('catalog-publications', {
    props: {
        embeddedPanel: {
            type: String,
            default: '',
            validator: function (value) {
                return value === '' || value === 'queue' || value === 'history';
            },
        },
        panelActive: {
            type: Boolean,
            default: true,
        },
    },
    data: function () {
        return {
            loading: false,
            saving: false,
            clearingCatalogId: null,
            error: null,
            validationWarnings: null,
            notifyWarnings: [],
            context: null,
            markDialog: false,
            dialogCatalogId: null,
            dialogOptions: {},
            dialogLockCatalog: false,
            search: '',
            activeTab: 0,
            historyLoading: false,
            historyLoaded: false,
            history: null,
            historyError: null,
            historySearch: '',
            deleteHistoryDialog: false,
            deleteHistoryTarget: null,
            deletingHistoryId: null,
            historyDetailDialog: false,
            historyDetailRow: null,
        };
    },
    computed: {
        projectId: function () {
            return this.$store.state.project_id;
        },
        publishApiBase: function () {
            return (CI.site_url || CI.base_url || '').replace(/\/?$/, '/') + 'api/publish_requests';
        },
        StudyIDNO: function () {
            if (this.$store.state.metadata_idno) {
                return this.$store.state.metadata_idno;
            }
            return this.$store.state.idno || '';
        },
        canEdit: function () {
            return this.$store.getters.getUserHasEditAccess;
        },
        officialCatalogs: function () {
            return this.context && Array.isArray(this.context.official_catalogs)
                ? this.context.official_catalogs
                : [];
        },
        publications: function () {
            return this.context && Array.isArray(this.context.publications)
                ? this.context.publications
                : [];
        },
        pendingQueue: function () {
            return this.publications;
        },
        returnedCatalogs: function () {
            return this.context && Array.isArray(this.context.returned_catalogs)
                ? this.context.returned_catalogs
                : [];
        },
        returnedCalloutText: function () {
            var items = this.returnedCatalogs;
            if (items.length === 1) {
                var title = items[0].catalog_title || ('Catalog #' + items[0].catalog_id);
                return (this.$t('publication_returned_callout_one') || '“{catalog}” was returned by a curator. See History for details.').replace('{catalog}', title);
            }
            if (items.length > 1) {
                var template = this.$t('publication_returned_callout_many') || '{count} catalog connections were returned. See History for details.';
                return template.replace('{count}', String(items.length));
            }
            return '';
        },
        summary: function () {
            return this.context ? this.context.summary : null;
        },
        hasOfficialCatalogs: function () {
            return this.officialCatalogs.length > 0;
        },
        tableHeaders: function () {
            return [
                { text: this.$t('catalog') || 'Catalog', value: 'catalog_title', sortable: false },
                { text: this.$t('status') || 'Status', value: 'state', sortable: false, width: '130px' },
                { text: this.$t('publication_type') || 'Type', value: 'request_type', sortable: false, width: '100px' },
                { text: this.$t('data_access') || 'Data access', value: 'access_policy', sortable: false, width: '140px' },
                { text: this.$t('collection') || 'Collection', value: 'repositoryid', sortable: false, width: '120px' },
                { text: this.$t('publish_request_submitted') || 'Submitted', value: 'ready_at', sortable: false, width: '150px' },
                { text: this.$t('actions') || 'Actions', value: 'actions', sortable: false, width: '120px', align: 'end' },
            ];
        },
        catalogSelectItems: function () {
            var vm = this;
            return vm.officialCatalogs.map(function (entry) {
                var catalog = entry.catalog || {};
                var label = catalog.title || ('Catalog #' + catalog.id);
                if (catalog.url) {
                    label += ' — ' + catalog.url;
                }
                return {
                    text: label,
                    value: String(catalog.id),
                    entry: entry,
                };
            });
        },
        dialogCatalogEntry: function () {
            if (!this.dialogCatalogId) {
                return null;
            }
            var targetId = String(this.dialogCatalogId);
            for (var i = 0; i < this.officialCatalogs.length; i++) {
                var entry = this.officialCatalogs[i];
                if (entry.catalog && String(entry.catalog.id) === targetId) {
                    return entry;
                }
            }
            return null;
        },
        dialogPublishFields: function () {
            var entry = this.dialogCatalogEntry;
            if (!entry || !entry.publish_form || !Array.isArray(entry.publish_form.fields)) {
                return [];
            }
            return entry.publish_form.fields.filter(function (field) {
                return !field.contexts || field.contexts.indexOf('ready') !== -1;
            });
        },
        dialogIsNada: function () {
            var entry = this.dialogCatalogEntry;
            if (!entry || !entry.catalog) {
                return false;
            }
            return String(entry.catalog.type || 'nada').toLowerCase() === 'nada';
        },
        dialogCanSubmit: function () {
            return !!this.dialogCatalogId && !this.saving;
        },
        historyRows: function () {
            return this.history && Array.isArray(this.history.rows) ? this.history.rows : [];
        },
        historyHeaders: function () {
            var headers = [
                { text: this.$t('date') || 'Date', value: 'created', sortable: false, width: '160px' },
                { text: 'Catalog', value: 'catalog_title', sortable: false },
                { text: 'Event', value: 'event', sortable: false, width: '180px' },
                { text: this.$t('user') || 'User', value: 'actor_username', sortable: false, width: '140px' },
                { text: 'Note', value: 'note', sortable: false },
            ];
            if (this.historyRows.some(function (row) { return row.can_delete; }) || this.canEdit) {
                headers.push({ text: this.$t('actions') || 'Actions', value: 'actions', sortable: false, width: '80px', align: 'end' });
            }
            return headers;
        },
    },
    watch: {
        activeTab: function (tab) {
            if (!this.embeddedPanel && tab === 1 && !this.historyLoading) {
                this.loadHistory();
            }
        },
        panelActive: function (active) {
            if (this.embeddedPanel === 'history' && active && !this.historyLoading) {
                this.loadHistory();
            }
        },
    },
    mounted: function () {
        if (this.embeddedPanel === 'history') {
            if (this.panelActive) {
                this.loadHistory();
            }
            return;
        }
        this.loadContext();
    },
    methods: {
        loadContext: function () {
            var vm = this;
            vm.loading = true;
            vm.error = null;
            axios.get(vm.publishApiBase + '/project/' + vm.projectId + '/context')
                .then(function (response) {
                    vm.context = response.data;
                })
                .catch(function (err) {
                    vm.error = vm.extractError(err);
                })
                .finally(function () {
                    vm.loading = false;
                });
        },
        loadHistory: function () {
            var vm = this;
            vm.historyLoading = true;
            vm.historyError = null;
            axios.get(vm.publishApiBase + '/project/' + vm.projectId + '/history')
                .then(function (response) {
                    vm.history = response.data;
                    vm.historyLoaded = true;
                })
                .catch(function (err) {
                    vm.historyError = vm.extractError(err);
                })
                .finally(function () {
                    vm.historyLoading = false;
                });
        },
        invalidateHistory: function () {
            this.historyLoaded = false;
            this.$emit('history-changed');
            if (this.embeddedPanel === 'history' && this.panelActive) {
                this.loadHistory();
            } else if (!this.embeddedPanel && this.activeTab === 1) {
                this.loadHistory();
            }
        },
        defaultOptions: function () {
            return { access_policy: 'open', repositoryid: '', data_remote_url: '' };
        },
        openMarkDialog: function (catalogId) {
            this.error = null;
            this.dialogLockCatalog = false;
            this.dialogCatalogId = catalogId ? String(catalogId) : null;
            this.dialogOptions = Object.assign({}, this.defaultOptions());
            if (this.dialogCatalogId) {
                this.applyDialogDefaultsFromExisting(this.dialogCatalogId);
            }
            this.markDialog = true;
        },
        openEditDialog: function (placement) {
            this.error = null;
            this.dialogLockCatalog = true;
            this.dialogCatalogId = String(placement.catalog_id);
            this.dialogOptions = Object.assign({}, this.defaultOptions(), placement.options || {});
            this.markDialog = true;
        },
        applyDialogDefaultsFromExisting: function (catalogId) {
            var entry = this.findOfficialEntry(catalogId);
            if (!entry) {
                return;
            }
            var existing = entry.existing || null;
            if (existing && existing.options) {
                this.dialogOptions = Object.assign({}, this.defaultOptions(), existing.options);
            }
        },
        onDialogCatalogChange: function () {
            this.dialogOptions = Object.assign({}, this.defaultOptions());
            if (this.dialogCatalogId) {
                this.applyDialogDefaultsFromExisting(this.dialogCatalogId);
            }
        },
        findOfficialEntry: function (catalogId) {
            var targetId = String(catalogId);
            for (var i = 0; i < this.officialCatalogs.length; i++) {
                var entry = this.officialCatalogs[i];
                if (entry.catalog && String(entry.catalog.id) === targetId) {
                    return entry;
                }
            }
            return null;
        },
        closeMarkDialog: function () {
            this.markDialog = false;
            this.dialogCatalogId = null;
            this.dialogOptions = {};
            this.dialogLockCatalog = false;
        },
        openPlacementDialog: function (placement) {
            if (!this.canEdit || !this.hasOfficialCatalogs) {
                return;
            }
            if (this.placementIsReady(placement)) {
                this.openEditDialog(placement);
                return;
            }
            this.openMarkDialog(placement.catalog_id);
        },
        fieldVisible: function (field, values) {
            if (!field.show_when) {
                return true;
            }
            var keys = Object.keys(field.show_when);
            for (var i = 0; i < keys.length; i++) {
                var key = keys[i];
                var expected = String(field.show_when[key]);
                var actual = values && values[key] !== undefined && values[key] !== null ? String(values[key]) : '';
                if (expected !== actual) {
                    return false;
                }
            }
            return true;
        },
        fieldLabel: function (field) {
            var title = field.title || field.key;
            return field.required ? title + ' *' : title;
        },
        submitMarkReady: function () {
            var vm = this;
            if (!vm.dialogCatalogId) {
                vm.error = vm.$t('publish_ready_select_catalog') || 'Select a catalog.';
                return;
            }
            vm.saving = true;
            vm.error = null;
            vm.validationWarnings = null;
            vm.notifyWarnings = [];
            axios.put(vm.publishApiBase + '/project/' + vm.projectId, {
                requests: [{
                    catalog_id: Number(vm.dialogCatalogId),
                    options: vm.dialogOptions || {},
                }],
            })
                .then(function (response) {
                    if (response.data && response.data.study_validation && !response.data.study_validation.valid) {
                        vm.validationWarnings = response.data.study_validation;
                    }
                    if (response.data && Array.isArray(response.data.warnings)) {
                        vm.notifyWarnings = response.data.warnings;
                    }
                    vm.closeMarkDialog();
                    vm.loadContext();
                    vm.invalidateHistory();
                })
                .catch(function (err) {
                    vm.error = vm.extractError(err);
                })
                .finally(function () {
                    vm.saving = false;
                });
        },
        clearReadyForRow: function (placement) {
            var vm = this;
            vm.clearingCatalogId = placement.catalog_id;
            vm.error = null;
            axios.post(vm.publishApiBase + '/project/' + vm.projectId + '/clear', {
                catalog_ids: [Number(placement.catalog_id)],
            })
                .then(function () {
                    vm.loadContext();
                    vm.invalidateHistory();
                })
                .catch(function (err) {
                    vm.error = vm.extractError(err);
                })
                .finally(function () {
                    vm.clearingCatalogId = null;
                });
        },
        placementIsReady: function (placement) {
            return !!placement.ready_at;
        },
        placementIsPublished: function (placement) {
            if (placement.remote_id) {
                return true;
            }
            var status = placement.status ? String(placement.status) : '';
            return status === 'published' || status === 'draft';
        },
        placementStateChip: function (placement) {
            return { label: this.$t('publish_request_pending') || 'Pending', color: 'orange', textColor: 'white' };
        },
        publicationTypeLabel: function (placement) {
            if (placement.remote_id) {
                return this.$t('publication_type_update') || 'Update';
            }
            if (this.placementIsPublished(placement)) {
                return this.$t('publication_type_update') || 'Update';
            }
            return this.$t('publication_type_new') || 'New';
        },
        accessPolicyLabel: function (placement) {
            var code = placement.options && placement.options.access_policy;
            if (!code) {
                return '—';
            }
            var entry = this.findOfficialEntry(placement.catalog_id);
            if (entry && entry.publish_form && Array.isArray(entry.publish_form.fields)) {
                var field = entry.publish_form.fields.find(function (f) { return f.key === 'access_policy'; });
                if (field && Array.isArray(field.enum)) {
                    var match = field.enum.find(function (item) { return String(item.code) === String(code); });
                    if (match && match.label) {
                        return match.label;
                    }
                }
            }
            return String(code);
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
        publishedTimestamp: function (placement) {
            if (placement.published_at) {
                return this.formatTimestamp(placement.published_at);
            }
            if (this.placementIsPublished(placement) && placement.updated_at) {
                return this.formatTimestamp(placement.updated_at);
            }
            return '—';
        },
        extractError: function (err) {
            if (err.response && err.response.data && err.response.data.message) {
                return err.response.data.message;
            }
            return err.message || 'Request failed';
        },
        openValidationReport: function () {
            if (this.$router) {
                this.$router.push('/validation-report');
            }
        },
        goToPublish: function () {
            if (this.$router) {
                this.$router.push({ path: '/publish', query: { tab: 'nada' } });
            }
        },
        openRemoteUrl: function (placement) {
            if (placement.remote_url) {
                window.open(placement.remote_url, '_blank');
            }
        },
        goToHistoryTab: function () {
            if (this.$router) {
                this.$router.replace({ path: '/publish', query: { tab: 'history' } }).catch(function () {});
            }
        },
        ensureContextLoaded: function (callback) {
            var vm = this;
            if (vm.context && vm.officialCatalogs.length > 0) {
                callback();
                return;
            }
            vm.loading = true;
            axios.get(vm.publishApiBase + '/project/' + vm.projectId + '/context')
                .then(function (response) {
                    vm.context = response.data;
                    callback();
                })
                .catch(function (err) {
                    vm.error = vm.extractError(err);
                })
                .finally(function () {
                    vm.loading = false;
                });
        },
        requeueFromHistory: function (row) {
            var vm = this;
            if (!row || !row.catalog_id) {
                return;
            }
            vm.ensureContextLoaded(function () {
                vm.openMarkDialog(row.catalog_id);
            });
        },
        historyEventLabel: function (row) {
            return window.HistoryEventDisplay.eventLabel(row, this);
        },
        historyEventColor: function (row) {
            return window.HistoryEventDisplay.eventColor(row);
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
        historyEventNote: function (row) {
            return window.HistoryEventDisplay.tableNoteText(row, this);
        },
        historyEventDetails: function (row) {
            return window.HistoryEventDisplay.metadataSummary(row, this);
        },
        openDeleteHistoryDialog: function (row) {
            this.deleteHistoryTarget = row;
            this.deleteHistoryDialog = true;
        },
        closeDeleteHistoryDialog: function () {
            this.deleteHistoryDialog = false;
            this.deleteHistoryTarget = null;
        },
        confirmDeleteHistory: function () {
            var vm = this;
            var row = vm.deleteHistoryTarget;
            if (!row || !row.id) {
                return;
            }
            vm.deletingHistoryId = row.id;
            vm.historyError = null;
            axios.post(vm.publishApiBase + '/project/' + vm.projectId + '/history/' + row.id + '/delete')
                .then(function (response) {
                    vm.history = response.data;
                    vm.historyLoaded = true;
                    vm.closeDeleteHistoryDialog();
                    vm.$emit('history-changed');
                })
                .catch(function (err) {
                    vm.historyError = vm.extractError(err);
                })
                .finally(function () {
                    vm.deletingHistoryId = null;
                });
        },
    },
    template: `
    <div class="catalog-publications-panel">

        <v-alert v-if="error" type="error" dense outlined class="mb-3">{{ error }}</v-alert>

        <template v-if="embeddedPanel === 'queue'">
            <div class="d-flex align-center flex-wrap mb-3">
                <div class="flex-grow-1 pr-2 text-subtitle-2 grey--text text--darken-1">
                    {{ $t('publication_queue_note') || 'Submit publish requests to shared connections. Curators are notified and publish the project.' }}
                </div>
                <v-btn
                    v-if="canEdit && hasOfficialCatalogs"
                    small
                    outlined
                    color="primary"
                    @click="openMarkDialog()"
                >
                    {{ $t('add_to_queue') || 'Submit request…' }}
                </v-btn>
            </div>

            <v-alert v-if="validationWarnings && !validationWarnings.valid" type="warning" dense outlined class="mb-3">
                <div>{{ $t('publish_queue_validation_warning') || 'Study validation has issues. You can still submit a publish request; curators may ask for fixes before publishing.' }}</div>
                <v-btn text small color="primary" class="mt-2 px-0" @click="openValidationReport">
                    {{ $t('view_full_validation_report') || 'View full validation report' }}
                </v-btn>
            </v-alert>

            <v-alert v-if="notifyWarnings.length" type="warning" dense outlined class="mb-3">
                <div v-for="(warn, idx) in notifyWarnings" :key="'notify-warn-' + idx" :class="idx > 0 ? 'mt-2' : ''">
                    {{ warn.message }}
                </div>
            </v-alert>

            <v-alert v-if="!loading && !hasOfficialCatalogs" type="info" dense outlined class="mb-3">
                {{ $t('publish_submit_no_official_catalogs') || 'No shared connections are configured. Contact a site administrator.' }}
            </v-alert>

            <v-alert v-if="!loading && returnedCatalogs.length > 0" type="warning" dense outlined class="mb-3">
                <div>{{ returnedCalloutText }}</div>
                <v-btn text small color="primary" class="mt-2 px-0" @click="goToHistoryTab">
                    {{ $t('view_publication_history') || 'View history' }}
                </v-btn>
            </v-alert>

            <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3"></v-progress-linear>

            <v-card v-if="!loading && pendingQueue.length > 0" elevation="2" class="mb-3">
                <div class="p-3 pb-0">
                    <v-text-field
                        v-model="search"
                        :placeholder="$t('search') || 'Search'"
                        dense
                        solo
                        flat
                        hide-details
                        clearable
                        prepend-inner-icon="mdi-magnify"
                        style="max-width:220px;"
                    ></v-text-field>
                </div>
                <v-data-table
                    :headers="tableHeaders"
                    :items="pendingQueue"
                    :items-per-page="25"
                    :search="search"
                    class="elevation-0 catalog-publications-table"
                    hide-default-footer
                >
                    <template v-slot:item.catalog_title="{ item }">
                        <div>
                            <div
                                class="font-weight-medium"
                                :class="{ 'primary--text text-decoration-underline': canEdit && hasOfficialCatalogs, 'catalog-publications-title-clickable': canEdit && hasOfficialCatalogs }"
                                :style="canEdit && hasOfficialCatalogs ? 'cursor:pointer;' : ''"
                                @click="openPlacementDialog(item)"
                            >{{ item.catalog_title || ('Catalog #' + item.catalog_id) }}</div>
                            <a
                                v-if="item.catalog_url"
                                :href="item.catalog_url"
                                target="_blank"
                                rel="noopener"
                                class="text-caption grey--text text--darken-1"
                                @click.stop
                            >{{ item.catalog_url }}</a>
                        </div>
                    </template>

                    <template v-slot:item.state="{ item }">
                        <v-chip x-small :color="placementStateChip(item).color" :text-color="placementStateChip(item).textColor">
                            {{ placementStateChip(item).label }}
                        </v-chip>
                    </template>

                    <template v-slot:item.request_type="{ item }">
                        {{ publicationTypeLabel(item) }}
                    </template>

                    <template v-slot:item.access_policy="{ item }">
                        {{ accessPolicyLabel(item) }}
                    </template>

                    <template v-slot:item.repositoryid="{ item }">
                        {{ (item.options && item.options.repositoryid) || '—' }}
                    </template>

                    <template v-slot:item.ready_at="{ item }">
                        {{ formatTimestamp(item.ready_at) }}
                    </template>

                    <template v-slot:item.actions="{ item }">
                        <div class="d-flex justify-end flex-wrap" style="gap:2px;">
                            <v-btn
                                v-if="canEdit && placementIsReady(item)"
                                icon
                                x-small
                                color="primary"
                                :title="$t('edit_queue_options') || 'Edit queue options'"
                                @click.stop="openEditDialog(item)"
                            >
                                <v-icon>mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                v-if="canEdit && placementIsReady(item)"
                                icon
                                x-small
                                color="secondary"
                                :loading="clearingCatalogId === item.catalog_id"
                                :title="$t('remove_from_queue') || 'Withdraw request'"
                                @click.stop="clearReadyForRow(item)"
                            >
                                <v-icon>mdi-close-circle-outline</v-icon>
                            </v-btn>
                        </div>
                    </template>
                </v-data-table>
            </v-card>

            <div v-else-if="!loading" class="text-muted pa-3 mb-3">
                {{ $t('no_queue_entries_yet') || 'No publish requests yet.' }}
            </div>
        </template>

        <template v-else-if="embeddedPanel === 'history'">
            <v-alert v-if="historyError" type="error" dense outlined class="mb-3">{{ historyError }}</v-alert>

            <v-progress-linear v-if="historyLoading" indeterminate color="primary" class="mb-3"></v-progress-linear>

            <v-card v-if="!historyLoading && historyRows.length > 0" elevation="2" class="mb-3">
                <div class="p-3 pb-0">
                    <v-text-field
                        v-model="historySearch"
                        :placeholder="$t('search') || 'Search'"
                        dense
                        solo
                        flat
                        hide-details
                        clearable
                        prepend-inner-icon="mdi-magnify"
                        style="max-width:220px;"
                    ></v-text-field>
                </div>
                <v-data-table
                    :headers="historyHeaders"
                    :items="historyRows"
                    :items-per-page="50"
                    :search="historySearch"
                    class="elevation-0 catalog-publications-history-table history-event-table"
                    hide-default-footer
                    @click:row="openHistoryDetailDialog"
                >
                    <template v-slot:item.created="{ item }">
                        {{ formatTimestamp(item.created) }}
                    </template>

                    <template v-slot:item.catalog_title="{ item }">
                        <div>
                            <div class="font-weight-medium">{{ item.catalog_title || ('Catalog #' + item.catalog_id) }}</div>
                            <a
                                v-if="item.catalog_url"
                                :href="item.catalog_url"
                                target="_blank"
                                rel="noopener"
                                class="text-caption grey--text text--darken-1"
                                @click.stop
                            >{{ item.catalog_url }}</a>
                        </div>
                    </template>

                    <template v-slot:item.event="{ item }">
                        <v-chip x-small :color="historyEventColor(item)" text-color="white">
                            {{ historyEventLabel(item) }}
                        </v-chip>
                    </template>

                    <template v-slot:item.actor_username="{ item }">
                        {{ item.actor_username || '—' }}
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

                    <template v-slot:item.actions="{ item }">
                        <v-btn
                            v-if="canEdit && item.event === 'returned'"
                            icon
                            x-small
                            color="primary"
                            :title="$t('add_to_queue_again') || 'Submit again'"
                            @click.stop="requeueFromHistory(item)"
                        >
                            <v-icon>mdi-send-check</v-icon>
                        </v-btn>
                        <v-btn
                            v-if="item.can_delete"
                            icon
                            x-small
                            color="secondary"
                            :loading="deletingHistoryId === item.id"
                            :title="$t('delete') || 'Delete'"
                            @click.stop="openDeleteHistoryDialog(item)"
                        >
                            <v-icon>mdi-delete-outline</v-icon>
                        </v-btn>
                    </template>
                </v-data-table>
            </v-card>

            <div v-else-if="!historyLoading && historyLoaded" class="text-muted pa-3 mb-3">
                {{ $t('no_publication_activity_yet') || 'No publication activity yet.' }}
            </div>

            <v-dialog v-if="embeddedPanel === 'history'" v-model="deleteHistoryDialog" max-width="480">
                <v-card>
                    <v-card-title class="text-h6">{{ $t('delete_publication_history_entry') || 'Delete publish record?' }}</v-card-title>
                    <v-card-text>
                        {{ $t('delete_publication_history_confirm') || 'Remove this publish record from history? This does not delete the study from the catalog.' }}
                    </v-card-text>
                    <v-card-actions class="pa-3">
                        <v-spacer></v-spacer>
                        <v-btn text :disabled="deletingHistoryId !== null" @click="closeDeleteHistoryDialog">{{ $t('cancel') || 'Cancel' }}</v-btn>
                        <v-btn color="error" text :loading="deletingHistoryId !== null" @click="confirmDeleteHistory">{{ $t('delete') || 'Delete' }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <history-event-detail-dialog
                v-model="historyDetailDialog"
                :row="historyDetailRow"
                variant="project"
            ></history-event-detail-dialog>
        </template>

        <v-dialog v-if="embeddedPanel === 'queue' || embeddedPanel === 'history'" v-model="markDialog" max-width="640" scrollable persistent>
            <v-card>
                <v-card-title class="text-h6">
                    {{ $t('add_to_publication_queue') || 'Submit publish request' }}
                </v-card-title>
                <v-card-subtitle class="pb-0">
                    {{ $t('add_to_queue_dialog_note') || 'Curators for the selected catalog will be notified. This does not publish the project.' }}
                </v-card-subtitle>
                <v-card-text class="pt-4">
                    <div class="mb-4">
                        <label class="d-block text-body-2 font-weight-medium mb-1">{{ $t('select_catalog') || 'Select catalog connection' }}</label>
                        <v-select
                            v-model="dialogCatalogId"
                            :items="catalogSelectItems"
                            item-text="text"
                            item-value="value"
                            :disabled="dialogLockCatalog || saving"
                            outlined
                            dense
                            hide-details
                            @change="onDialogCatalogChange"
                        ></v-select>
                    </div>

                    <template v-if="dialogCatalogId && dialogIsNada && dialogPublishFields.length">
                        <v-divider class="mb-4"></v-divider>
                        <div v-for="field in dialogPublishFields" :key="'dlg-' + dialogCatalogId + '-' + field.key">
                            <template v-if="fieldVisible(field, dialogOptions)">
                                <div class="mb-3">
                                    <label class="d-block text-body-2 font-weight-medium mb-1">{{ fieldLabel(field) }}</label>
                                    <v-select
                                        v-if="field.display_type === 'dropdown' && field.enum"
                                        v-model="dialogOptions[field.key]"
                                        :items="field.enum"
                                        item-text="label"
                                        item-value="code"
                                        outlined
                                        dense
                                        hide-details
                                        clearable
                                    ></v-select>
                                    <v-combobox
                                        v-else-if="field.display_type === 'dropdown-custom' && field.enum"
                                        v-model="dialogOptions[field.key]"
                                        :items="field.enum.map(function(item){ return item.code; })"
                                        outlined
                                        dense
                                        hide-details
                                        clearable
                                    ></v-combobox>
                                    <v-text-field
                                        v-else
                                        v-model="dialogOptions[field.key]"
                                        outlined
                                        dense
                                        hide-details
                                        clearable
                                    ></v-text-field>
                                    <div v-if="field.help_text" class="text-caption grey--text mt-1">{{ field.help_text }}</div>
                                </div>
                            </template>
                        </div>
                    </template>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn text :disabled="saving" @click="closeMarkDialog">{{ $t('cancel') || 'Cancel' }}</v-btn>
                    <v-btn color="primary" :loading="saving" :disabled="!dialogCanSubmit" @click="submitMarkReady">
                        {{ $t('add_to_queue_confirm') || 'Submit request' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </div>
    `,
});

Vue.component('publish-ready', {
    template: '<publish-hub></publish-hub>',
});
