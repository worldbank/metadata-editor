/**
 * Field Issues Component
 *
 * Shows an issues trigger (icon + optional badge) next to a field. On click opens a popover
 * with list of issues for this field_path and "New issue" to open create dialog.
 * Uses create-issue-dialog and issue-detail-dialog for create/view without leaving the page.
 *
 * Props:
 *   - fieldPath: String - Dot path for the field (e.g. study_desc.title_statement.title)
 *   - projectId: Number - Project ID for API calls
 */
Vue.component('field-issues', {
    props: {
        fieldPath: {
            type: String,
            required: true
        },
        projectId: {
            type: Number,
            required: true
        }
    },
    data() {
        return {
            menuOpen: false,
            issues: [],
            loading: false,
            showCreateDialog: false,
            showDetailDialog: false,
            selectedIssue: null,
            loadingFullIssue: false,
            _onProjectIssuesRefreshed: null
        };
    },
    computed: {
        issuesFromSummary() {
            const summary = this.$store.getters.getOpenIssuesSummary || [];
            if (!this.fieldPath || !summary.length) return [];
            return summary.filter(function(i) { return i.field_path === this.fieldPath; }, this);
        },
        issueCount() {
            return this.issues && this.issues.length ? this.issues.length : this.issuesFromSummary.length;
        },
        hasIssues() {
            return this.issueCount > 0;
        },
        displayIssues() {
            return this.issues.length ? this.issues : this.issuesFromSummary;
        }
    },
    mounted() {
        var self = this;
        if (typeof EventBus !== 'undefined') {
            self._onProjectIssuesRefreshed = function(projectId) {
                if (projectId === self.projectId) {
                    self.issues = [];
                }
            };
            EventBus.$on('project-issues-refreshed', self._onProjectIssuesRefreshed);
        }
    },
    beforeDestroy() {
        if (typeof EventBus !== 'undefined' && this._onProjectIssuesRefreshed) {
            EventBus.$off('project-issues-refreshed', this._onProjectIssuesRefreshed);
        }
    },
    watch: {
        menuOpen(val) {
            if (val && this.projectId) {
                if (this.issuesFromSummary.length > 0) {
                    this.issues = [];
                } else {
                    this.loadIssues();
                }
            }
        },
        '$store.state.openFieldIssuesMenuFieldPath': function(fieldPath) {
            if (fieldPath === this.fieldPath) {
                this.menuOpen = true;
                this.$store.commit('setOpenFieldIssuesMenuFieldPath', null);
            }
        }
    },
    methods: {
        async loadIssues() {
            this.loading = true;
            this.issues = [];
            try {
                const url = CI.base_url + '/api/issues/project/' + this.projectId;
                const response = await axios.get(url, {
                    params: { field_path: this.fieldPath, limit: 50 }
                });
                if (response.data.status === 'success') {
                    this.issues = response.data.issues || [];
                }
            } catch (e) {
                console.error('Field issues load error:', e);
                if (this.$root.$refs.toast) {
                    this.$root.$refs.toast.showAlert(
                        e.response?.data?.message || e.message || 'Failed to load issues',
                        'error'
                    );
                }
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.menuOpen = false;
            this.showCreateDialog = true;
        },
        onIssueCreated() {
            this.$store.dispatch('fetchOpenIssuesSummary', { projectId: this.projectId });
            this.issues = [];
            this.loadIssues();
        },
        async viewIssue(issue) {
            this.menuOpen = false;
            if (issue && !issue.description && issue.id) {
                this.loadingFullIssue = true;
                try {
                    const url = CI.base_url + '/api/issues/' + issue.id;
                    const response = await axios.get(url);
                    if (response.data.status === 'success' && response.data.issue) {
                        this.selectedIssue = response.data.issue;
                        this.showDetailDialog = true;
                    } else {
                        this.selectedIssue = issue;
                        this.showDetailDialog = true;
                    }
                } catch (e) {
                    console.error('Failed to load issue detail', e);
                    this.selectedIssue = issue;
                    this.showDetailDialog = true;
                } finally {
                    this.loadingFullIssue = false;
                }
            } else {
                this.selectedIssue = issue;
                this.showDetailDialog = true;
            }
        },
        onIssueUpdated() {
            this.$store.dispatch('fetchOpenIssuesSummary', { projectId: this.projectId });
            this.issues = [];
            this.loadIssues();
        },
        onDetailClose() {
            this.selectedIssue = null;
        },
        getSeverityColor(severity) {
            const map = { low: 'grey', medium: 'warning', high: 'orange', critical: 'error' };
            return map[severity] || 'grey';
        },
        truncate(str, len) {
            if (!str) return '';
            return str.length <= len ? str : str.substring(0, len) + '…';
        }
    },
    template: `
        <span class="field-issues d-inline-flex align-center ml-2">
            <v-menu
                v-model="menuOpen"
                :close-on-content-click="false"
                offset-y
                left
                max-width="360"
                content-class="field-issues-menu"
            >
                <template v-slot:activator="{ on, attrs }">
                    <span class="d-inline-flex align-center" v-bind="attrs" v-on="on">
                        <v-btn
                            v-if="hasIssues"
                            icon
                            x-small
                            title="Issues for this field"
                            class="field-issues-trigger mr-0"
                        >
                            <v-icon small color="warning">mdi-comment-alert</v-icon>
                        </v-btn>
                        <span v-if="hasIssues" class="text-caption warning--text mr-1">{{ issueCount }}</span>
                        <v-btn
                            icon
                            x-small
                            title="Issues for this field -s"
                            class="field-issues-trigger"
                        >
                            <v-icon small>mdi-dots-vertical</v-icon>
                        </v-btn>
                    </span>
                </template>
                <v-card min-width="320" class="pa-4">
                    <div class="text-caption mb-0 px-2 pt-3" style="font-weight: bold;">
                        <v-icon small color="warning">mdi-comment-alert</v-icon>
                        Issues
                    </div>
                    <v-divider class="mb-2"></v-divider>
                    <v-progress-linear v-if="loading || loadingFullIssue" indeterminate color="primary" class="mb-2"></v-progress-linear>
                    <div v-else-if="displayIssues.length === 0" class="text-body-2 text--secondary pa-2 text-center">
                        No issues for this field.
                    </div>
                    <v-list v-else dense class="py-0" style="max-height: 240px; overflow-y: auto;">
                        <v-list-item
                            v-for="issue in displayIssues"
                            :key="issue.id"
                            @click="viewIssue(issue)"
                            class="cursor-pointer"
                        >
                            <v-list-item-content>
                                <v-list-item-title class="text-body-2">{{ truncate(issue.title || issue.description, 50) }}</v-list-item-title>
                                <v-list-item-subtitle class="d-flex align-center mt-1">
                                    <issue-status-badge :status="issue.status" small class="mr-1"></issue-status-badge>
                                    <v-chip v-if="issue.severity" x-small :color="getSeverityColor(issue.severity)" dark class="mr-1">{{ issue.severity }}</v-chip>
                                </v-list-item-subtitle>
                            </v-list-item-content>
                            <v-list-item-action>
                                <v-icon small>mdi-chevron-right</v-icon>
                            </v-list-item-action>
                        </v-list-item>
                    </v-list>
                    <v-divider class="mt-1"></v-divider>
                    <div style="padding: 5px;padding-top:0px;padding-bottom: 10px; display: flex; justify-content: center; align-items: center;">
                        <v-btn block small color="primary" @click="openCreate">
                            <v-icon left small>mdi-plus</v-icon>
                            New issue
                        </v-btn>
                    </div>
                </v-card>
            </v-menu>

            <create-issue-dialog
                v-model="showCreateDialog"
                :project-id="projectId"
                :initial-field-path="fieldPath"
                @issue-created="onIssueCreated"
            ></create-issue-dialog>

            <issue-detail-dialog
                v-model="showDetailDialog"
                :issue="selectedIssue"
                :project-id="projectId"
                @issue-updated="onIssueUpdated"
                @issue-applied="onIssueUpdated"
                @input="onDetailClose"
            ></issue-detail-dialog>
        </span>
    `
});
