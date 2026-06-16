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
                { text: 'Description', value: 'description' },
                { text: 'Category', value: 'category', width: '150px' },
                { text: 'Severity', value: 'severity', width: '120px' },
                { text: 'Status', value: 'status', width: '150px' },
                { text: 'Field', value:'field_path', width: '200px' },
                { text: 'Created', value: 'created', width: '120px' },
                { text: 'Actions', value: 'actions', sortable: false, width: '150px' }
            ],
            statusOptions: [
                { text: 'All', value: '' },
                { text: 'Open', value: 'open' },
                { text: 'Accepted', value: 'accepted' },
                { text: 'Fixed', value: 'fixed' },
                { text: 'Rejected', value: 'rejected' },
                { text: 'Dismissed', value: 'dismissed' },
                { text: 'False Positive', value: 'false_positive' }
            ],
            categoryOptions: [
                { text: 'All',             value: '' },
                { text: 'Typo / Wording', value: 'typo_wording' },
                { text: 'Inconsistency',   value: 'inconsistency' },
                { text: 'Missing Data',    value: 'missing_data' },
                { text: 'Format Issue',    value: 'format_issue' },
                { text: 'Completeness',    value: 'completeness' },
                { text: 'Other',           value: 'other' }
            ],
            severityOptions: [
                { text: 'All', value: '' },
                { text: 'Low', value: 'low' },
                { text: 'Medium', value: 'medium' },
                { text: 'High', value: 'high' },
                { text: 'Critical', value: 'critical' }
            ],
            appliedOptions: [
                { text: 'All', value: '' },
                { text: 'Applied', value: '1' },
                { text: 'Not Applied', value: '0' }
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
                    { text: 'All open', value: '' },
                    { text: 'Open', value: 'open' },
                    { text: 'Accepted', value: 'accepted' }
                ];
            }
            if (this.statusScope === 'closed') {
                return [
                    { text: 'All closed', value: '' },
                    { text: 'Fixed', value: 'fixed' },
                    { text: 'Rejected', value: 'rejected' },
                    { text: 'Dismissed', value: 'dismissed' },
                    { text: 'False Positive', value: 'false_positive' }
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
                    throw new Error(response.data.message || 'Failed to load issues');
                }
            } catch (error) {
                console.error('Error loading issues:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || 'Failed to load issues'
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
            if (!confirm('Are you sure you want to delete this issue?')) {
                return;
            }

            try {
                const url = CI.base_url + '/api/issues/delete/' + issue.id;
                const response = await axios.post(url);

                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', 'Issue deleted successfully');
                    this.$emit('issue-deleted', issue);
                    this.loadIssues();
                    this.refreshIssueSummary();
                } else {
                    throw new Error(response.data.message || 'Failed to delete issue');
                }
            } catch (error) {
                console.error('Error deleting issue:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || 'Failed to delete issue'
                );
            }
        },
        async bulkUpdateStatus(status) {
            if (this.selected.length === 0) {
                EventBus.$emit('onFail', 'Please select issues first');
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
                        response.data.affected + ' issue(s) updated'
                    );
                    this.selected = [];
                    this.loadIssues();
                    this.refreshIssueSummary();
                } else {
                    throw new Error(response.data.message || 'Failed to update issues');
                }
            } catch (error) {
                console.error('Error updating issues:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || 'Failed to update issues'
                );
            }
        },
        async bulkDeleteIssues() {
            if (this.selected.length === 0) {
                EventBus.$emit('onFail', 'Please select issues first');
                return;
            }

            const issueIds = this.selected.map(issue => issue.id);
            const shouldDelete = confirm(
                'Are you sure you want to delete ' + issueIds.length + ' selected issue(s)? This action cannot be undone.'
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
                        response.data.affected + ' issue(s) deleted'
                    );
                    this.selected = [];
                    this.loadIssues();
                    this.refreshIssueSummary();
                } else {
                    throw new Error(response.data.message || 'Failed to delete issues');
                }
            } catch (error) {
                console.error('Error deleting issues:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || 'Failed to delete issues'
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
                                placeholder="Status"
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
                                placeholder="Category"
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
                                placeholder="Severity"
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
                                placeholder="Applied"
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
                                Clear
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- Bulk Actions -->
            <v-card flat v-if="hasSelected" class="mb-3">
                <v-card-text class="py-2">
                    <div class="d-flex align-center">
                        <span class="mr-3"><strong>{{ selected.length }}</strong> selected</span>
                        <v-menu offset-y>
                            <template v-slot:activator="{ on, attrs }">
                                <v-btn
                                    color="primary"
                                    outlined
                                    small
                                    v-bind="attrs"
                                    v-on="on"
                                >
                                    Bulk Actions
                                    <v-icon right>mdi-menu-down</v-icon>
                                </v-btn>
                            </template>
                            <v-list dense>
                                <v-list-item @click="bulkUpdateStatus('false_positive')">
                                    <v-list-item-title>Mark as False Positive</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('dismissed')">
                                    <v-list-item-title>Dismiss</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('accepted')">
                                    <v-list-item-title>Accept</v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="bulkUpdateStatus('rejected')">
                                    <v-list-item-title>Reject</v-list-item-title>
                                </v-list-item>
                                <v-divider class="my-1"></v-divider>
                                <v-list-item @click="bulkDeleteIssues()">
                                    <v-list-item-icon class="mr-2">
                                        <v-icon small color="error">mdi-delete</v-icon>
                                    </v-list-item-icon>
                                    <v-list-item-title class="error--text">Delete selected</v-list-item-title>
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
                        {{ item.severity }}
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
                        title="Edit"
                    >
                        <v-icon small>mdi-pencil</v-icon>
                    </v-btn>
                    <v-btn
                        icon
                        small
                        @click="deleteIssue(item)"
                        title="Delete"
                        color="error"
                    >
                        <v-icon small>mdi-delete</v-icon>
                    </v-btn>
                </template>

                <template v-slot:no-data>
                    <div class="text-center pa-5">
                        <v-icon size="64" color="grey lighten-2">mdi-alert-circle-outline</v-icon>
                        <p class="text-h6 mt-3">No issues found</p>
                        <p v-if="Object.values(filters).some(v => v !== '')" class="text--secondary">
                            Try adjusting your filters
                        </p>
                    </div>
                </template>
            </v-data-table>
        </div>
    `
});
