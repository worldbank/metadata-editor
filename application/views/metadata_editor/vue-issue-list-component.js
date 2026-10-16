/**
 * Issue List Component
 * 
 * Displays the issues for a project scope. Projects have few issues, so the whole scope
 * is loaded once and searched/sorted/paged client-side.
 * 
 * Props:
 *   - projectId: Number - Project ID (required)
 *   - canEdit: Boolean - Whether user can change/delete issues (default: false)
 *   - refreshTrigger: Number - Increment to reload from the server
 *   - statusScope: String - 'open' | 'closed' | 'all'
 * 
 * Events:
 *   - loaded: Emitted with the issue count after each load
 *   - changed: Emitted after issues are updated or deleted; when nobody listens, the list reloads itself
 *   - issue-deleted: Emitted after an issue is deleted
 */
Vue.component('issue-list', {
    props: {
        projectId: {
            type: Number,
            required: true
        },
        canEdit: {
            type: Boolean,
            default: false
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
            loading: false,
            search: '',
            itemsPerPage: 25,
            sortBy: ['severity'],
            sortDesc: [true],
            selected: [],
            maxIssues: 500,
            severityRank: { critical: 4, high: 3, medium: 2, low: 1 },
            categoryLabels: {
                typo_wording: this.$t('category_typo_wording'),
                inconsistency: this.$t('category_inconsistency'),
                missing_data: this.$t('category_missing_data'),
                format_issue: this.$t('category_format_issue'),
                completeness: this.$t('category_completeness'),
                other: this.$t('category_other')
            }
        };
    },
    computed: {
        hasSelected() {
            return this.selected.length > 0;
        },
        isCompact() {
            return this.$vuetify.breakpoint.smAndDown;
        },
        headers() {
            const headers = [
                { text: this.$t('issue_title'), value: 'title' }
            ];
            if (!this.isCompact) {
                headers.push({ text: this.$t('issue_severity'), value: 'severity', width: '120px' });
                headers.push({ text: this.$t('issue_status'), value: 'status', width: '140px' });
            }
            headers.push({ text: '', value: 'actions', sortable: false, width: '48px', align: 'end' });
            return headers;
        },
        filteredIssues() {
            const term = (this.search || '').trim().toLowerCase();
            if (!term) return this.issues;
            return this.issues.filter(issue =>
                (issue.title || '').toLowerCase().includes(term)
                || (issue.field_path || '').toLowerCase().includes(term)
                || String(issue.id) === term.replace(/^#/, '')
            );
        },
        showFooter() {
            const pageSize = this.itemsPerPage === -1 ? 25 : this.itemsPerPage;
            return this.filteredIssues.length > pageSize;
        }
    },
    watch: {
        refreshTrigger() {
            this.loadIssues();
        },
        search() {
            this.selected = [];
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
        afterChange() {
            this.selected = [];
            this.refreshIssueSummary();
            if (this.$listeners.changed) {
                this.$emit('changed');
            } else {
                this.loadIssues();
            }
        },
        async loadIssues() {
            this.loading = true;
            try {
                const params = { limit: this.maxIssues, offset: 0 };
                if (this.statusScope !== 'all') params.scope = this.statusScope;

                const url = CI.base_url + '/api/issues/project/' + this.projectId;
                const response = await axios.get(url, { params });

                if (response.data.status === 'success') {
                    this.issues = response.data.issues || [];
                    this.selected = [];
                    this.$emit('loaded', response.data.total || this.issues.length);
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
        sortIssues(items, sortBy, sortDesc) {
            const key = sortBy[0];
            const desc = sortDesc[0];
            const valueOf = (issue) => {
                if (key === 'severity') return this.severityRank[issue.severity] || 0;
                if (key === 'title') return (issue.title || '').toLowerCase();
                return issue[key] || '';
            };
            return items.slice().sort((a, b) => {
                if (key) {
                    const va = valueOf(a);
                    const vb = valueOf(b);
                    if (va < vb) return desc ? 1 : -1;
                    if (va > vb) return desc ? -1 : 1;
                }
                return (Number(b.created) || 0) - (Number(a.created) || 0) || b.id - a.id;
            });
        },
        formatDate(timestamp) {
            if (!timestamp) return '-';
            return moment.unix(timestamp).format('YYYY-MM-DD HH:mm');
        },
        formatRelativeDate(timestamp) {
            if (!timestamp) return '-';
            return moment.unix(timestamp).fromNow();
        },
        getSeverityColor(severity) {
            const colors = {
                'low': 'blue-grey lighten-1',
                'medium': 'amber darken-1',
                'high': 'deep-orange',
                'critical': 'red darken-2'
            };
            return colors[severity] || 'grey lighten-1';
        },
        async updateIssueStatus(issue, status) {
            try {
                const url = CI.base_url + '/api/issues/bulk_status';
                const response = await axios.post(url, { ids: [issue.id], status: status });
                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('issues_updated').replace(':count', response.data.affected));
                    this.afterChange();
                } else {
                    throw new Error(response.data.message || this.$t('error_update_status'));
                }
            } catch (error) {
                console.error('Error updating issue:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_update_status')
                );
            }
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
                    this.afterChange();
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
                    this.afterChange();
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
                    this.afterChange();
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
        getCategoryLabel(code) {
            return this.categoryLabels[code] || code || '';
        },
        getSeverityLabel(code) {
            const key = 'severity_' + code;
            return this.$te(key) ? this.$t(key) : (code || '');
        }
    },
    template: `
        <div class="issue-list mt-4">
            <!-- Toolbar: search + bulk actions -->
            <div class="d-flex align-center flex-wrap px-3 mb-3" style="gap: 8px;">
                <v-text-field
                    v-model="search"
                    :placeholder="$t('search')"
                    prepend-inner-icon="mdi-magnify"
                    outlined
                    dense
                    hide-details
                    clearable
                    class="issue-list__search"
                    style="max-width: 320px;"
                ></v-text-field>

                <template v-if="canEdit && hasSelected">
                    <v-spacer></v-spacer>
                    <span class="text-body-2">{{ $t('activity_selected').replace(':count', selected.length) }}</span>
                    <v-menu offset-y left>
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
                </template>
            </div>

            <!-- Issues Table -->
            <v-data-table
                v-model="selected"
                :headers="headers"
                :items="filteredIssues"
                :loading="loading"
                :items-per-page.sync="itemsPerPage"
                :sort-by.sync="sortBy"
                :sort-desc.sync="sortDesc"
                :custom-sort="sortIssues"
                :hide-default-footer="!showFooter"
                :footer-props="{
                    'items-per-page-options': [10, 25, 50, -1]
                }"
                :show-select="canEdit"
                item-key="id"
                class="elevation-1"
            >
                <template v-slot:item.title="{ item }">
                    <div class="issue-row">
                        <a
                            href="javascript:void(0)"
                            @click="viewIssue(item)"
                            class="issue-row__title text-decoration-none"
                            :title="item.title"
                        >{{ item.title }}</a>
                        <div class="issue-row__meta text-caption text--secondary">
                            <span>#{{ item.id }}</span>
                            <template v-if="isCompact && item.severity">
                                <span>&middot;</span>
                                <span class="d-inline-flex align-center">
                                    <span class="issue-severity-dot" :class="getSeverityColor(item.severity)"></span>
                                    {{ getSeverityLabel(item.severity) }}
                                </span>
                            </template>
                            <template v-if="isCompact">
                                <span>&middot;</span>
                                <issue-status-badge :status="item.status" small></issue-status-badge>
                            </template>
                            <template v-if="item.category">
                                <span>&middot;</span>
                                <span>{{ getCategoryLabel(item.category) }}</span>
                            </template>
                            <template v-if="item.field_path">
                                <span>&middot;</span>
                                <code class="issue-row__path" :title="item.field_path">{{ item.field_path }}</code>
                            </template>
                            <span>&middot;</span>
                            <span :title="formatDate(item.created)">{{ formatRelativeDate(item.created) }}</span>
                        </div>
                    </div>
                </template>

                <template v-slot:item.severity="{ item }">
                    <span v-if="item.severity" class="d-inline-flex align-center text-body-2">
                        <span class="issue-severity-dot" :class="getSeverityColor(item.severity)"></span>
                        {{ getSeverityLabel(item.severity) }}
                    </span>
                    <span v-else class="text--disabled">-</span>
                </template>

                <template v-slot:item.status="{ item }">
                    <issue-status-badge :status="item.status" small></issue-status-badge>
                </template>

                <template v-slot:item.actions="{ item }">
                    <v-menu offset-y left>
                        <template v-slot:activator="{ on, attrs }">
                            <v-btn icon small v-bind="attrs" v-on="on" :title="$t('actions')">
                                <v-icon small>mdi-dots-vertical</v-icon>
                            </v-btn>
                        </template>
                        <v-list dense>
                            <v-list-item @click="viewIssue(item)">
                                <v-list-item-title>{{ $t('action_view') }}</v-list-item-title>
                            </v-list-item>
                            <template v-if="canEdit">
                                <v-list-item v-if="item.status === 'open'" @click="updateIssueStatus(item, 'accepted')">
                                    <v-list-item-title>{{ $t('action_accept') }}</v-list-item-title>
                                </v-list-item>
                                <v-list-item v-if="item.status === 'open' || item.status === 'accepted'" @click="updateIssueStatus(item, 'dismissed')">
                                    <v-list-item-title>{{ $t('action_dismiss') }}</v-list-item-title>
                                </v-list-item>
                                <v-divider class="my-1"></v-divider>
                                <v-list-item @click="deleteIssue(item)">
                                    <v-list-item-title class="error--text">{{ $t('action_delete') }}</v-list-item-title>
                                </v-list-item>
                            </template>
                        </v-list>
                    </v-menu>
                </template>

                <template v-slot:no-data>
                    <div class="text-center pa-5">
                        <v-icon size="64" color="grey lighten-2">mdi-alert-circle-outline</v-icon>
                        <p class="text-h6 mt-3">{{ $t('no_issues_found') }}</p>
                        <p v-if="search" class="text--secondary">
                            {{ $t('try_adjusting_filters') }}
                        </p>
                    </div>
                </template>
            </v-data-table>
        </div>
    `
});
