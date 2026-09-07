Vue.component('vue-notifications-component', {
    data: function () {
        return {
            notifications: [],
            loading: false,
            marking_all: false,
            error_message: '',
            filter_mode: 'all',
            family_mode: 'all',
            unread_count: 0,
            filter_panel: [0, 1],
            pagination: {
                total: 0
            },
            tableOptions: {
                page: 1,
                itemsPerPage: 25,
                sortBy: [],
                sortDesc: [],
                groupBy: [],
                groupDesc: [],
                multiSort: false,
                mustSort: false
            },
            detail_dialog: false,
            selected_notification: null
        };
    },
    mounted: function () {
        this.loadNotifications();
        this.openFromQuery();
    },
    computed: {
        apiBase: function () {
            return (CI.site_url || '').replace(/\/?$/, '/') + 'api/notifications';
        },
        pageTitle: function () {
            return this.$t('notifications') || 'Notifications';
        },
        table_headers: function () {
            return [
                { text: '', value: 'is_unread', sortable: false, width: '36px', align: 'center' },
                { text: this.$t('notification_type') || 'Type', value: 'family', sortable: false, width: '18%' },
                { text: this.$t('notifications') || 'Summary', value: 'summary', sortable: false },
                { text: this.$t('user') || 'Actor', value: 'actor_label', sortable: false, width: '12%' },
                { text: this.$t('created') || 'Created', value: 'created', sortable: false, width: '14%', cellClass: 'text-no-wrap' },
                { text: '', value: 'actions', sortable: false, align: 'end', width: '48px' }
            ];
        },
        status_options: function () {
            return [
                { value: 'all', text: this.$t('notification_all') || 'All' },
                { value: 'unread', text: this.$t('notification_unread') || 'Unread' }
            ];
        },
        family_options: function () {
            return [
                { value: 'all', text: this.$t('notification_all') || 'All' },
                { value: 'publish', text: this.$t('notification_family_publish') || 'Publish' },
                { value: 'sharing', text: this.$t('notification_family_sharing') || 'Sharing' },
                { value: 'ownership', text: this.$t('notification_family_ownership') || 'Ownership' },
                { value: 'collection', text: this.$t('notification_family_collection') || 'Collection' },
                { value: 'template', text: this.$t('notification_family_template') || 'Template' }
            ];
        },
        unread_only: function () {
            return this.filter_mode === 'unread';
        },
        emptyMessage: function () {
            if (this.unread_only) {
                return this.$t('notifications_empty_unread') || 'No unread notifications.';
            }
            return this.$t('notifications_empty') || 'You have no notifications.';
        },
        hasActiveFilters: function () {
            return this.filter_mode === 'unread' || this.family_mode !== 'all';
        }
    },
    watch: {
        tableOptions: {
            deep: true,
            handler: function () {
                this.loadNotifications();
            }
        },
        filter_mode: function () {
            this.resetToFirstPage();
        },
        family_mode: function () {
            this.resetToFirstPage();
        }
    },
    methods: {
        loadNotifications: function () {
            var vm = this;
            vm.loading = true;
            vm.error_message = '';
            var page = vm.tableOptions.page || 1;
            var perPage = vm.tableOptions.itemsPerPage || 25;
            var offset = (page - 1) * perPage;
            var params = {
                limit: perPage,
                offset: offset
            };
            if (vm.unread_only) {
                params.unread = 1;
            }
            if (vm.family_mode && vm.family_mode !== 'all') {
                params.family = vm.family_mode;
            }
            axios.get(vm.apiBase, { params: params })
                .then(function (response) {
                    var data = response.data || {};
                    vm.notifications = data.notifications || [];
                    vm.pagination.total = data.total != null ? data.total : 0;
                    if (typeof data.unread_count === 'number') {
                        vm.unread_count = data.unread_count;
                    }
                })
                .catch(function (err) {
                    vm.error_message = vm.extractError(err);
                })
                .finally(function () {
                    vm.loading = false;
                });
        },
        refresh: function () {
            this.loadNotifications();
        },
        resetToFirstPage: function () {
            if (this.tableOptions.page !== 1) {
                this.tableOptions.page = 1;
                return;
            }
            this.loadNotifications();
        },
        applyFilters: function () {
            this.resetToFirstPage();
        },
        clearFilters: function () {
            this.filter_mode = 'all';
            this.family_mode = 'all';
        },
        onRowClick: function (item) {
            this.openNotification(item);
        },
        openFromQuery: function () {
            var params = new URLSearchParams(window.location.search || '');
            var id = parseInt(params.get('id'), 10);
            if (!id) {
                return;
            }
            var vm = this;
            axios.get(vm.apiBase + '/' + id)
                .then(function (response) {
                    if (response.data && response.data.notification) {
                        vm.openNotification(response.data.notification);
                    }
                })
                .catch(function () {});
        },
        openNotification: function (item) {
            if (!item) {
                return;
            }
            this.selected_notification = item;
            this.detail_dialog = true;
            this.markRead(item);
        },
        closeDetail: function () {
            this.detail_dialog = false;
            this.selected_notification = null;
        },
        markRead: function (item) {
            var vm = this;
            if (!item || item.read_at) {
                return;
            }
            axios.post(vm.apiBase + '/' + item.id + '/read')
                .then(function (response) {
                    if (response.data && typeof response.data.unread_count === 'number') {
                        vm.unread_count = response.data.unread_count;
                    }
                    var readAt = (response.data && response.data.notification)
                        ? response.data.notification.read_at
                        : Math.floor(Date.now() / 1000);
                    item.read_at = readAt;
                    item.is_unread = false;
                    if (vm.selected_notification && vm.selected_notification.id === item.id) {
                        vm.selected_notification.read_at = readAt;
                        vm.selected_notification.is_unread = false;
                    }
                })
                .catch(function () {});
        },
        markAllRead: function () {
            var vm = this;
            if (vm.marking_all || vm.unread_count < 1) {
                return;
            }
            vm.marking_all = true;
            axios.post(vm.apiBase + '/read_all')
                .then(function () {
                    vm.unread_count = 0;
                    vm.loadNotifications();
                    if (vm.selected_notification) {
                        vm.selected_notification.read_at = Math.floor(Date.now() / 1000);
                        vm.selected_notification.is_unread = false;
                    }
                })
                .catch(function (err) {
                    vm.error_message = vm.extractError(err);
                })
                .finally(function () {
                    vm.marking_all = false;
                });
        },
        openRelated: function (item) {
            if (!item || !item.href) {
                return;
            }
            window.location.href = CI.site_url + '/' + item.href;
        },
        relatedLabel: function (item) {
            if (!item || !item.href) {
                return '';
            }
            if (item.type === 'publish.project_ready' || String(item.href).indexOf('publish-queue') === 0) {
                return this.$t('publishing_queue') || 'Publishing queue';
            }
            if (item.type && String(item.type).indexOf('collection.project.') === 0) {
                return this.$t('my_projects') || 'My projects';
            }
            if (String(item.href).indexOf('collections') === 0) {
                return this.$t('open_collection') || 'Open collection';
            }
            if (String(item.href).indexOf('templates') === 0) {
                return this.$t('open_template') || 'Open template';
            }
            return this.$t('open_project') || 'Open project';
        },
        payloadValue: function (item, key) {
            if (!item || !item.payload || item.payload[key] === undefined || item.payload[key] === null || item.payload[key] === '') {
                return '';
            }
            return String(item.payload[key]);
        },
        permissionLabel: function (value) {
            var key = String(value || '').toLowerCase();
            var map = {
                view: this.$t('notification_permission_view') || 'View',
                edit: this.$t('notification_permission_edit') || 'Edit',
                admin: this.$t('notification_permission_admin') || 'Admin'
            };
            return map[key] || value;
        },
        isAccessNotification: function (item) {
            return !!(item && item.type && String(item.type).indexOf('.access_') !== -1);
        },
        detailAction: function (item) {
            if (!item) {
                return '';
            }
            if (item.family === 'publish' && item.action_label) {
                return item.action_label;
            }
            return item.title || '';
        },
        detailRows: function (item) {
            var rows = [];
            if (!item) {
                return rows;
            }
            var push = function (label, value, extra) {
                if (value === undefined || value === null || String(value).trim() === '') {
                    return;
                }
                rows.push({
                    label: label,
                    value: String(value),
                    extra: extra ? String(extra) : ''
                });
            };

            push(this.$t('notification_type') || 'Type', item.family_label || item.family);
            push(this.$t('notification_action') || 'Action', this.detailAction(item));
            push(this.$t('project') || 'Project', this.payloadValue(item, 'project_title'), this.payloadValue(item, 'project_idno'));
            push(this.$t('catalog') || 'Catalog', this.payloadValue(item, 'catalog_title'), this.payloadValue(item, 'catalog_url'));
            push(this.$t('collection') || 'Collection', this.payloadValue(item, 'collection_title') || this.payloadValue(item, 'repositoryid'));
            push(this.$t('template') || 'Template', this.payloadValue(item, 'template_title'), this.payloadValue(item, 'template_uid'));
            push(this.$t('data_access') || 'Data access', this.payloadValue(item, 'access_policy'));

            if (this.isAccessNotification(item)) {
                var permission = item.action_label || this.permissionLabel(this.payloadValue(item, 'permission'));
                push(this.$t('notification_permission') || 'Permission', permission);
                var previous = this.payloadValue(item, 'previous_permission');
                if (previous) {
                    push(this.$t('notification_previous_permission') || 'Previous permission', this.permissionLabel(previous));
                }
            }

            push(this.$t('notification_new_owner') || 'New owner', this.payloadValue(item, 'new_owner'));
            push(this.$t('user') || 'User', item.actor_label || '');
            push(this.$t('date') || 'Date', this.formatTime(item.created));
            var noteLabel = this.$t('note') || 'Note';
            if (noteLabel.slice(-1) === ':') {
                noteLabel = noteLabel.slice(0, -1);
            }
            push(noteLabel, this.payloadValue(item, 'note') || this.payloadValue(item, 'return_reason'));
            return rows;
        },
        formatTime: function (value) {
            if (!value) {
                return '—';
            }
            if (typeof moment !== 'undefined') {
                return moment.unix(value).format('YYYY-MM-DD HH:mm:ss');
            }
            return value;
        },
        formatRelativeTime: function (value) {
            if (!value) {
                return '—';
            }
            if (typeof moment !== 'undefined') {
                return moment.unix(value).fromNow();
            }
            return this.formatTime(value);
        },
        familyColor: function (item) {
            var family = item && item.family ? item.family : 'other';
            var colors = {
                publish: 'primary',
                sharing: 'blue-grey',
                ownership: 'teal',
                collection: 'indigo',
                template: 'purple',
                other: 'grey'
            };
            return colors[family] || colors.other;
        },
        extractError: function (err) {
            if (err && err.response && err.response.data && err.response.data.message) {
                return err.response.data.message;
            }
            return (this.$t('unknown_error') || 'Unknown error');
        },
        rowClass: function (item) {
            return item && item.is_unread ? 'notification-row--unread' : '';
        }
    },
    template: `
        <div class="notifications-page jobs-page">
            <div class="row">
                <div class="sidebar col-md-3 col-sm-4">
                    <div class="mr-4 mt-5">
                        <v-expansion-panels v-model="filter_panel" multiple>
                            <v-expansion-panel>
                                <v-expansion-panel-header class="capitalize">
                                    {{ $t('status') || 'Status' }}
                                </v-expansion-panel-header>
                                <v-expansion-panel-content>
                                    <v-select
                                        v-model="filter_mode"
                                        :items="status_options"
                                        item-text="text"
                                        item-value="value"
                                        label=""
                                        background-color="white"
                                        dense
                                        outlined
                                        hide-details
                                        class="jobs-filter-select mt-1"
                                        @change="applyFilters"
                                    ></v-select>
                                </v-expansion-panel-content>
                            </v-expansion-panel>
                            <v-expansion-panel>
                                <v-expansion-panel-header class="capitalize">
                                    {{ $t('notification_type') || 'Type' }}
                                </v-expansion-panel-header>
                                <v-expansion-panel-content>
                                    <v-select
                                        v-model="family_mode"
                                        :items="family_options"
                                        item-text="text"
                                        item-value="value"
                                        label=""
                                        background-color="white"
                                        dense
                                        outlined
                                        hide-details
                                        class="jobs-filter-select mt-1"
                                        @change="applyFilters"
                                    ></v-select>
                                </v-expansion-panel-content>
                            </v-expansion-panel>
                        </v-expansion-panels>

                        <v-btn
                            small
                            outlined
                            color="primary"
                            block
                            class="mt-3 jobs-clear-filters-btn"
                            :disabled="!hasActiveFilters"
                            @click="clearFilters"
                        >
                            <v-icon left small>mdi-filter-off-outline</v-icon>
                            {{ $t('clear_filters') || 'Clear filters' }}
                        </v-btn>
                    </div>
                </div>

                <div class="col-md-9 col-sm-8">
                    <div class="mt-5 mb-5">
                        <div class="d-flex">
                            <div class="flex-grow-1 flex-shrink-0 mr-auto">
                                <h3 class="mt-3">{{ pageTitle }}</h3>
                            </div>
                            <div class="d-flex align-center" style="gap: 8px;">
                                <v-btn
                                    small
                                    outlined
                                    color="primary"
                                    :disabled="unread_count < 1 || marking_all"
                                    :loading="marking_all"
                                    @click="markAllRead"
                                >
                                    {{ $t('mark_all_read') || 'Mark all as read' }}
                                </v-btn>
                                <v-btn small outlined color="primary" :loading="loading" @click="refresh">
                                    <v-icon left small>mdi-refresh</v-icon>
                                    {{ $t('refresh') || 'Refresh' }}
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 p-3 border text-danger" v-if="error_message">
                        <div><strong>{{ $t('error') || 'Error' }}:</strong> {{ error_message }}</div>
                    </div>

                    <div class="bg-white shadow rounded p-3 pt-1 mt-2">
                        <v-data-table
                            :headers="table_headers"
                            :items="notifications"
                            :server-items-length="pagination.total"
                            :options.sync="tableOptions"
                            :loading="loading || marking_all"
                            :footer-props="{ 'items-per-page-options': [10, 25, 50, 100] }"
                            item-key="id"
                            dense
                            class="jobs-table table-jobs notifications-table elevation-0"
                            :item-class="rowClass"
                            @click:row="onRowClick"
                        >
                            <template v-slot:no-data>
                                <div class="mt-5 mb-3 p-3 text-center text-muted">
                                    {{ emptyMessage }}
                                </div>
                            </template>
                            <template v-slot:item.is_unread="{ item }">
                                <span
                                    class="notification-unread-dot"
                                    :class="{ 'notification-unread-dot--on': item.is_unread }"
                                    :title="item.is_unread ? ($t('notification_unread') || 'Unread') : ($t('notification_read') || 'Read')"
                                ></span>
                            </template>
                            <template v-slot:item.family="{ item }">
                                <v-chip x-small outlined :color="familyColor(item)" class="notification-type-chip">
                                    {{ item.family_label || item.family }}
                                </v-chip>
                            </template>
                            <template v-slot:item.summary="{ item }">
                                <div class="notification-title">{{ item.subject || item.title }}</div>
                                <v-chip x-small class="notification-action-chip mt-1" :color="familyColor(item)" outlined>
                                    {{ item.title }}
                                </v-chip>
                            </template>
                            <template v-slot:item.actor_label="{ item }">
                                {{ item.actor_label || '—' }}
                            </template>
                            <template v-slot:item.created="{ item }">
                                <span :title="formatTime(item.created)">{{ formatRelativeTime(item.created) }}</span>
                            </template>
                            <template v-slot:item.actions="{ item }">
                                <v-btn icon x-small @click.stop="openNotification(item)" :title="$t('view_details') || 'View details'">
                                    <v-icon small>mdi-eye</v-icon>
                                </v-btn>
                            </template>
                        </v-data-table>
                    </div>
                </div>
            </div>

            <v-dialog v-model="detail_dialog" max-width="640" scrollable @input="function(v) { if (!v) closeDetail(); }">
                <v-card v-if="selected_notification">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <span>{{ $t('notification_details') || 'Notification details' }}</span>
                        <v-btn icon small @click="closeDetail"><v-icon>mdi-close</v-icon></v-btn>
                    </v-card-title>
                    <v-divider></v-divider>
                    <v-card-text class="pt-0" style="max-height: 70vh;">
                        <v-simple-table dense class="notification-detail-table">
                            <tbody>
                                <tr v-for="(row, index) in detailRows(selected_notification)" :key="index">
                                    <td class="notification-detail-label">{{ row.label }}</td>
                                    <td>
                                        <div class="notification-detail-value">{{ row.value }}</div>
                                        <div v-if="row.extra" class="notification-detail-extra">{{ row.extra }}</div>
                                    </td>
                                </tr>
                            </tbody>
                        </v-simple-table>
                    </v-card-text>
                    <v-divider></v-divider>
                    <v-card-actions class="pa-3">
                        <v-btn
                            v-if="selected_notification.href"
                            small
                            color="primary"
                            outlined
                            @click="openRelated(selected_notification)"
                        >
                            {{ relatedLabel(selected_notification) }}
                        </v-btn>
                        <v-spacer></v-spacer>
                        <v-btn small text @click="closeDetail">{{ $t('close') || 'Close' }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
