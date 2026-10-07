/**
 * Issue List Component
 * 
 * Displays a paginated, filterable table of issues for a project
 * 
 * Props:
 *   - projectId: Number - Project ID (required)
 *   - refreshTrigger: Number - Used to trigger refresh from parent
 * 
 * Events:
 *   - issue-selected: Emitted when an issue is clicked
 *   - issue-deleted: Emitted after an issue is deleted
 */
Vue.component('issue-list', {
    props: {
        projectId: {
            type: Number,
            required: true
        },
        refreshTrigger: {
            type: Number,
            default: 0
        },
        statusScope: {
            type: String,
            default: 'all' // 'open' | 'closed' | 'all'
        }
    },
    data() {
        return {
            issues: [],
            total: 0,
            loading: false,
            options: {
                page: 1,
                itemsPerPage: 10,
                sortBy: ['created'],
                sortDesc: [true]
            },
            filters: {
                status: '',
                category: '',
                severity: '',
                applied: ''
            },
            selected: [],
            headers: [
                { text: '', value: 'select', sortable: false, width: '50px' },
                { text: this.$t('issue_description'), value: 'description' },
                { text: this.$t('issue_category'), value: 'category', width: '150px' },
                { text: this.$t('issue_severity'), value: 'severity', width: '120px' },
                { text: this.$t('issue_status'), value: 'status', width: '150px' },
                { text: this.$t('field'), value:'field_path', width: '200px' },
                { text: this.$t('activity_created'), value: 'created', width: '120px' },
                { text: this.$t('actions'), value: 'actions', sortable: false, width: '150px' }
            ],
            statusOptions: [
                { text: this.$t('status_all'), value: '' },
                { text: this.$t('status_open'), value: 'open' },
                { text: this.$t('status_accepted'), value: 'accepted' },
                { text: this.$t('status_fixed'), value: 'fixed' },
                { text: this.$t('status_rejected'), value: 'rejected' },
                { text: this.$t('status_dismissed'), value: 'dismissed' },
                { text: this.$t('status_false_positive'), value: 'false_positive' }
            ],
            categoryOptions: [
                { text: this.$t('status_all'), value: '' },
                { text: this.$t('category_typo_wording'), value: 'typo_wording' },
                { text: this.$t('category_inconsistency'), value: 'inconsistency' },
                { text: this.$t('category_missing_data'), value: 'missing_data' },
                { text: this.$t('category_format_issue'), value: 'format_issue' },
                { text: this.$t('category_completeness'), value: 'completeness' },
                { text: this.$t('category_other'), value: 'other' }
            ],
            severityOptions: [
                { text: this.$t('status_all'), value: '' },
                { text: this.$t('severity_low'), value: 'low' },
                { text: this.$t('severity_medium'), value: 'medium' },
                { text: this.$t('severity_high'), value: 'high' },
                { text: this.$t('severity_critical'), value: 'critical' }
            ],
            appliedOptions: [
                { text: this.$t('status_all'), value: '' },
                { text: this.$t('issue_applied'), value: '1' },
                { text: this.$t('issue_not_applied'), value: '0' }
            ]
        };
    },
    computed: {
        hasSelected() {
            return this.selected.length > 0;
        },
        scopedStatusOptions() {
            if (this.statusScope === 'open') {
                return [
                    { text: this.$t('status_all_open'), value: '' },
                    { text: this.$t('status_open'), value: 'open' },
                    { text: this.$t('status_accepted'), value: 'accepted' }
                ];
            }
            if (this.statusScope === 'closed') {
                return [
                    { text: this.$t('status_all_closed'), value: '' },
                    { text: this.$t('status_fixed'), value: 'fixed' },
                    { text: this.$t('status_rejected'), value: 'rejected' },
                    { text: this.$t('status_dismissed'), value: 'dismissed' },
                    { text: this.$t('status_false_positive'), value: 'false_positive' }
                ];
            }
            return this.statusOptions;
        }
    },
    watch: {
        options: {
            handler() {
                this.loadIssues();
            },
            deep: true
        },
        filters: {
            handler() {
                this.options.page = 1;
                this.loadIssues();
            },
            deep: true
        },
        refreshTrigger() {
            this.loadIssues();
        }
    },
    mounted() {
        this.loadIssues();
    },
    methods: {
        refreshIssueSummary() {
            if (this.$store && this.projectId) {
                this.$store.dispatch('fetchOpenIssuesSummary', { projectId: this.projectId });
            }
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit('project-issues-refreshed', this.projectId);
            }
        },
        async loadIssues() {
            this.loading = true;
            try {
                const { page, itemsPerPage, sortBy, sortDesc } = this.options;
                const offset = (page - 1) * itemsPerPage;
                
                let params = {
                    limit: itemsPerPage,
                    offset: offset
                };

                // Add sorting
                if (sortBy.length > 0) {
                    params.sort_by = sortBy[0];
                    params.sort_order = sortDesc[0] ? 'DESC' : 'ASC';
                }

                // Add filters
                if (this.statusScope !== 'all') params.scope = this.statusScope;
                if (this.filters.status) params.status = this.filters.status;
                if (this.filters.category) params.category = this.filters.category;
                if (this.filters.severity) params.severity = this.filters.severity;
                if (this.filters.applied !== '') params.applied = this.filters.applied;

                const url = CI.base_url + '/api/issues/project/' + this.projectId;
                const response = await axios.get(url, { params });

                if (response.data.status === 'success') {
                    this.issues = response.data.issues || [];
                    this.total = response.data.total || 0;
                    this.selected = [];
                } else {
                    throw new Error(response.data.message || this.$t('error_load_issues'));
                }
            } catch (error) {
                console.error('Error loading issues:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_load_issues')
                );
            } finally {
                this.loading = false;
            }
        },
        formatDate(timestamp) {
            if (!timestamp) return '-';
            return moment.unix(timestamp).format('YYYY-MM-DD');
        },
        getSeverityColor(severity) {
            const colors = {
                'low': 'grey',
                'medium': 'warning',
                'high': 'orange',
                'critical': 'error'
            };
            return colors[severity] || 'grey';
        },
        truncateText(text, length = 80) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        },
        viewIssue(issue) {
            this.$router.push('/issues/' + issue.id);
        },
        async deleteIssue(issue) {
            if (!confirm(this.$t('confirm_delete_issue'))) {
                return;
            }

            try {
                const url = CI.base_url + '/api/issues/delete/' + issue.id;
                const response = await axios.post(url);

                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('issue_deleted'));
                    this.$emit('issue-deleted', issue);
                    this.loadIssues();
                    this.refreshIssueSummary();
                } else {
                    throw new Error(response.data.message || this.$t('error_delete_issue'));
                }
            } catch (error) {
                console.error('Error deleting issue:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_delete_issue')
                );
            }
        },
        async bulkUpdateStatus(status) {
            if (this.selected.length === 0) {
                EventBus.$emit('onFail', this.$t('validation_select_issues'));
                return;
            }

            const issueIds = this.selected.map(issue => issue.id);
            
            try {
                const url = CI.base_url + '/api/issues/bulk_status';
                const response = await axios.post(url, {
                    ids: issueIds,
                    status: status
                });

                if (response.data.status === 'success') {
                    EventBus.$emit(
                        'onSuccess',
                        this.$t('issues_updated').replace(':count', response.data.affected)
                    );
                    this.selected = [];
                    this.loadIssues();
                    this.refreshIssueSummary();
                } else {
                    throw new Error(response.data.message || this.$t('error_update_status'));
                }
            } catch (error) {
                console.error('Error updating issues:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_update_status')
                );
            }
        },
        async bulkDeleteIssues() {
            if (this.selected.length === 0) {
                EventBus.$emit('onFail', this.$t('validation_select_issues'));
                return;
            }

            const issueIds = this.selected.map(issue => issue.id);
            const shouldDelete = confirm(
                this.$t('confirm_delete_selected').replace(':count', issueIds.length)
            );
            if (!shouldDelete) {
                return;
            }

            try {
                const url = CI.base_url + '/api/issues/bulk_delete';
                const response = await axios.post(url, { ids: issueIds });

                if (response.data.status === 'success') {
                    EventBus.$emit(
                        'onSuccess',
                        this.$t('issues_deleted').replace(':count', response.data.affected)
                    );
                    this.selected = [];
                    this.loadIssues();
                    this.refreshIssueSummary();
                } else {
                    throw new Error(response.data.message || this.$t('error_delete_issue'));
                }
            } catch (error) {
                console.error('Error deleting issues:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_delete_issue')
                );
            }
        },
        clearFilters() {
            this.filters = {
                status: '',
                category: '',
                severity: '',
                applied: ''
            };
        },
        getCategoryLabel(code) {
            const opt = this.categoryOptions.find(o => o.value === code);
            return opt ? opt.text : (code || '');
        },
        getSeverityLabel(code) {
            const key = 'severity_' + code;
            return this.$te(key) ? this.$t(key) : (code || '');
        }
    },
    template: `
        <div class="issue-list mt-4">
            <!-- Filters -->
            <v-card flat class="mb-3">
                <v-card-text class="py-2">
                    <v-row dense align="center">
                        <v-col cols="auto">
                            <v-select
                                v-model="filters.status"
                                :items="scopedStatusOptions"
                                :placeholder="$t('filter_status')"
                                outlined
                                dense
                                hide-details
                                style="min-width: 130px;"
                            ></v-select>
                        </v-col>
                        <v-col cols="auto">
                            <v-select
                                v-model="filters.category"
                                :items="categoryOptions"
                                :placeholder="$t('filter_category')"
                                outlined
                                dense
                                hide-details
                                style="min-width: 150px;"
                            ></v-select>
                        </v-col>
                        <v-col cols="auto">
                            <v-select
                                v-model="filters.severity"
                                :items="severityOptions"
                                :placeholder="$t('filter_severity')"
                                outlined
                                dense
                                hide-details
                                style="min-width: 120px;"
                            ></v-select>
                        </v-col>
                        <v-col cols="auto">
                            <v-select
                                v-model="filters.applied"
                                :items="appliedOptions"
                                :placeholder="$t('filter_applied')"
                                outlined
                                dense
                                hide-details
                                style="min-width: 120px;"
                            ></v-select>
                        </v-col>
                        <v-col cols="12" sm="2" class="d-flex align-center">
                            <v-btn
                                @click="clearFilters"
                                outlined
                                small
                            >
                                <v-icon left small>mdi-filter-off</v-icon>
                                {{ $t('filter_clear') }}
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- Bulk Actions -->
            <v-card flat v-if="hasSelected" class="mb-3">
                <v-card-text class="py-2">
                    <div class="d-flex align-center">
                        <span class="mr-3">{{ $t('activity_selected').replace(':count', selected.length) }}</span>
                        <v-menu offset-y>
                            <template v-slot:activator="{ on, attrs }">
                                <v-btn
                                    color="primary"
                                    outlined
                                    small
                                    v-bind="attrs"
                                    v-on="on"
                                >
                                    {{ $t('action_bulk_actions') }}
                                    <v-icon right>mdi-menu-down</v-icon>
                                </v-btn>
                            </template>
                            <v-list dense>
                                <v-list-item @click="bulkUpdateStatus('false_positive')">
                                    <v-list-item-title>{{ $t('bulk_false_positive') }}</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('dismissed')">
                                    <v-list-item-title>{{ $t('action_dismiss') }}</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('accepted')">
                                    <v-list-item-title>{{ $t('action_accept') }}</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('rejected')">
                                    <v-list-item-title>{{ $t('action_reject') }}</v-list-item-title>
                                </v-list-item>
                                <v-divider class="my-1"></v-divider>
                                <v-list-item @click="bulkDeleteIssues()">
                                    <v-list-item-icon class="mr-2">
                                        <v-icon small color="error">mdi-delete</v-icon>
                                    </v-list-item-icon>
                                    <v-list-item-title class="error--text">{{ $t('action_delete_selected') }}</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </div>
                </v-card-text>
            </v-card>

            <!-- Issues Table -->
            <v-data-table
                v-model="selected"
                :headers="headers"
                :items="issues"
                :options.sync="options"
                :server-items-length="total"
                :loading="loading"
                :footer-props="{
                    'items-per-page-options': [5, 10, 25, 50]
                }"
                show-select
                item-key="id"
                class="elevation-1"
            >
                <template v-slot:item.description="{ item }">
                    <a 
                        href="javascript:void(0)" 
                        @click="viewIssue(item)"
                        class="text-decoration-none"
                        :title="item.description"
                    >
                        {{ truncateText(item.description, 60) }}
                    </a>
                </template>

                <template v-slot:item.category="{ item }">
                    <v-chip small outlined v-if="item.category">{{ getCategoryLabel(item.category) }}</v-chip>
                    <span v-else class="text--disabled">-</span>
                </template>

                <template v-slot:item.severity="{ item }">
                    <v-chip
                        v-if="item.severity"
                        small
                        :color="getSeverityColor(item.severity)"
                        outlined
                        class="text-capitalize"
                    >
                        {{ getSeverityLabel(item.severity) }}
                    </v-chip>
                    <span v-else class="text--disabled">-</span>
                </template>

                <template v-slot:item.status="{ item }">
                    <issue-status-badge :status="item.status" small></issue-status-badge>
                </template>

                <template v-slot:item.field_path="{ item }">
                    <code v-if="item.field_path" class="text-caption">{{ item.field_path }}</code>
                    <span v-else class="text--disabled">-</span>
                </template>

                <template v-slot:item.created="{ item }">
                    <span class="text-caption">{{ formatDate(item.created) }}</span>
                </template>

                <template v-slot:item.actions="{ item }">
                    <v-btn
                        icon
                        small
                        @click="viewIssue(item)"
                        :title="$t('action_edit')"
                    >
                        <v-icon small>mdi-pencil</v-icon>
                    </v-btn>
                    <v-btn
                        icon
                        small
                        @click="deleteIssue(item)"
                        :title="$t('action_delete')"
                        color="error"
                    >
                        <v-icon small>mdi-delete</v-icon>
                    </v-btn>
                </template>

                <template v-slot:no-data>
                    <div class="text-center pa-5">
                        <v-icon size="64" color="grey lighten-2">mdi-alert-circle-outline</v-icon>
                        <p class="text-h6 mt-3">{{ $t('no_issues_found') }}</p>
                        <p v-if="Object.values(filters).some(v => v !== '')" class="text--secondary">
                            {{ $t('try_adjusting_filters') }}
                        </p>
                    </div>
                </template>
            </v-data-table>
        </div>
    `
});
