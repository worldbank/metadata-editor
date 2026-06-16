/**
 * Issue Detail View Component
 *
 * Full-page view of a single issue using the two-column layout from the project editor dialog.
 * Main (md=8): title, description, field reference (collapsible), resolution
 * Sidebar (md=4): status badge, severity, category, project, activity
 *
 * Auto-saves title, description, severity, category on change.
 * Resolution notes + status action buttons mirror the dialog component.
 *
 * Props:
 *   - issueId: Number (required) - Issue ID to load
 *
 * Used on: issues/edit/{id} page
 */
Vue.component('issue-detail-view', {
    props: {
        issueId: {
            type: Number,
            required: true
        }
    },
    data() {
        return {
            localIssue: null,
            loading: true,
            saving: false,
            loadError: null,
            resolutionNotes: '',
            advancedPanel: undefined,
            diffRoot: null,
            severityOptions: [
                { text: 'Low',      value: 'low' },
                { text: 'Medium',   value: 'medium' },
                { text: 'High',     value: 'high' },
                { text: 'Critical', value: 'critical' }
            ],
            categoryOptions: [
                { text: 'Typo / Wording', value: 'typo_wording' },
                { text: 'Inconsistency',   value: 'inconsistency' },
                { text: 'Missing Data',    value: 'missing_data' },
                { text: 'Format Issue',    value: 'format_issue' },
                { text: 'Completeness',    value: 'completeness' },
                { text: 'Other',           value: 'other' }
            ]
        };
    },
    computed: {
        baseUrl() {
            return (typeof CI !== 'undefined' && CI.site_url ? CI.site_url : '').replace(/\/?$/, '');
        },
        apiBase() {
            return this.baseUrl + '/api/issues';
        },
        issuesListUrl() {
            return this.baseUrl + '/issues';
        },
        projectUrl() {
            if (!this.localIssue || !this.localIssue.project_id) return '#';
            return this.baseUrl + '/editor/edit/' + this.localIssue.project_id;
        },
        isEditable() {
            return this.localIssue &&
                (this.localIssue.status === 'open' || this.localIssue.status === 'accepted');
        },
        hasCurrentMetadata() {
            return this.localIssue && this.localIssue.current_metadata != null &&
                   typeof this.localIssue.current_metadata === 'object' &&
                   Object.keys(this.localIssue.current_metadata).length > 0;
        },
        hasSuggestedMetadata() {
            return this.localIssue && this.localIssue.suggested_metadata != null &&
                   typeof this.localIssue.suggested_metadata === 'object' &&
                   Object.keys(this.localIssue.suggested_metadata).length > 0;
        },
        hasAnyMetadata() {
            return this.localIssue &&
                (this.localIssue.current_metadata != null || this.localIssue.suggested_metadata != null);
        }
    },
    watch: {
        issueId: {
            immediate: true,
            handler(id) { if (id) this.fetchIssue(); }
        },
        advancedPanel(val) {
            if (val !== undefined) {
                this.$nextTick(() => {
                    setTimeout(() => this.$nextTick(() => this.renderMetadataDiff()), 200);
                });
            }
        }
    },
    beforeDestroy() {
        if (this.diffRoot && this.diffRoot.unmount) {
            try { this.diffRoot.unmount(); } catch (e) {}
            this.diffRoot = null;
        }
    },
    methods: {
        getCategoryLabel(code) {
            const opt = this.categoryOptions.find(o => o.value === code);
            return opt ? opt.text : (code || '');
        },
        showAlert(message, type) {
            if (typeof EventBus !== 'undefined') {
                EventBus.$emit(type === 'error' ? 'onFail' : 'onSuccess', message);
            }
        },
        fetchIssue() {
            this.loading = true;
            this.loadError = null;
            this.localIssue = null;
            axios.get(this.apiBase + '/' + this.issueId)
                .then(res => {
                    if (res.data && res.data.status === 'success' && res.data.issue) {
                        this.localIssue = res.data.issue;
                        this.$nextTick(() => this.renderMetadataDiff());
                    } else {
                        this.loadError = (res.data && res.data.message) || 'Failed to load issue';
                    }
                })
                .catch(err => {
                    this.loadError = (err.response && err.response.status === 404)
                        ? 'Issue not found'
                        : ((err.response && err.response.data && err.response.data.message) || err.message || 'Failed to load issue');
                })
                .finally(() => { this.loading = false; });
        },
        async saveField(field, value) {
            if (!this.localIssue) return;
            try {
                const payload = {};
                payload[field] = value;
                const res = await axios.put(this.apiBase + '/' + this.localIssue.id, payload);
                if (res.data && res.data.status === 'success') {
                    Vue.set(this.localIssue, field, value);
                    this.showAlert('Saved', 'success');
                } else {
                    throw new Error((res.data && res.data.message) || 'Failed to save');
                }
            } catch (err) {
                this.showAlert(
                    (err.response && err.response.data && err.response.data.message) || err.message || 'Failed to save',
                    'error'
                );
            }
        },
        async resolve(newStatus) {
            if (!this.localIssue) return;
            this.saving = true;
            try {
                if (this.resolutionNotes.trim()) {
                    await axios.put(this.apiBase + '/' + this.localIssue.id, { notes: this.resolutionNotes });
                    Vue.set(this.localIssue, 'notes', this.resolutionNotes);
                }
                const res = await axios.post(this.baseUrl + '/api/issues/status/' + this.localIssue.id, { status: newStatus });
                if (res.data && res.data.status === 'success') {
                    if (res.data.issue) {
                        this.localIssue = res.data.issue;
                    } else {
                        Vue.set(this.localIssue, 'status', newStatus);
                    }
                    this.resolutionNotes = '';
                    this.showAlert('Issue updated', 'success');
                } else {
                    throw new Error((res.data && res.data.message) || 'Failed to update status');
                }
            } catch (err) {
                this.showAlert(
                    (err.response && err.response.data && err.response.data.message) || err.message || 'Failed to update issue',
                    'error'
                );
            } finally {
                this.saving = false;
            }
        },
        formatDate(timestamp) {
            if (!timestamp) return '-';
            return typeof moment !== 'undefined'
                ? moment.unix(timestamp).format('MMM D, YYYY')
                : new Date(timestamp * 1000).toLocaleString();
        },
        formatMetadata(metadata) {
            if (metadata == null || metadata === '') return '';
            if (typeof metadata === 'string') return metadata;
            if (typeof metadata === 'object') {
                const fieldPath = this.localIssue && this.localIssue.field_path;
                if (fieldPath && !Array.isArray(metadata) && Object.prototype.hasOwnProperty.call(metadata, fieldPath)) {
                    const val = metadata[fieldPath];
                    if (val == null) return '';
                    if (typeof val === 'object') return JSON.stringify(val, null, 2);
                    return String(val);
                }
                return JSON.stringify(metadata, null, 2);
            }
            return String(metadata);
        },
        parseMetadataForDiff(val) {
            if (val == null) return null;
            const fieldPath = this.localIssue && this.localIssue.field_path;
            let value = val;
            if (fieldPath && typeof val === 'object' && !Array.isArray(val) &&
                Object.prototype.hasOwnProperty.call(val, fieldPath)) {
                value = val[fieldPath];
            }
            if (value == null) return null;
            if (typeof value === 'object') return value;
            if (typeof value === 'string') {
                try { return JSON.parse(value); } catch (e) { return { value: value }; }
            }
            return { value: String(value) };
        },
        renderMetadataDiff() {
            if (!this.localIssue) return;
            const container = this.$refs && this.$refs.metadataDiffContainer;
            if (!container || !document.contains(container)) return;
            if (typeof JsonDiffKit === 'undefined') return;
            const current = this.parseMetadataForDiff(this.localIssue.current_metadata);
            const suggested = this.parseMetadataForDiff(this.localIssue.suggested_metadata);
            const before = current !== null ? current : {};
            const after = suggested !== null ? suggested : {};
            try {
                if (this.diffRoot) {
                    try { this.diffRoot.unmount(); } catch (e) {}
                    this.diffRoot = null;
                }
                container.innerHTML = '';
                const differ = new JsonDiffKit.Differ({
                    detectCircular: true, showModifications: true, arrayDiffMethod: 'lcs'
                });
                const diff = differ.diff(before, after);
                const viewer = new JsonDiffKit.Viewer({
                    diff, indent: 2, lineNumbers: true, highlightInlineDiff: true,
                    inlineDiffOptions: { mode: 'word', wordSeparator: ' ' }, syntaxHighlight: true
                });
                this.diffRoot = JsonDiffKit.ReactDOM.createRoot(container);
                this.diffRoot.render(JsonDiffKit.React.createElement(viewer.render));
            } catch (err) {
                console.error('Metadata diff render error:', err);
            }
        },
        formatStatus(status) {
            return (status || '').replace(/_/g, ' ');
        },
        getSeverityColor(severity) {
            const colors = { low: 'blue', medium: 'orange', high: 'deep-orange', critical: 'red' };
            return colors[severity] || 'grey';
        },
        getStatusColor(status) {
            const colors = {
                open: 'primary', accepted: 'blue', fixed: 'success',
                rejected: 'error', dismissed: 'grey', false_positive: 'warning'
            };
            return colors[status] || 'grey';
        }
    },
    template: `
        <v-card>
            <v-progress-linear v-if="loading" indeterminate color="primary"></v-progress-linear>

            <v-card-text v-else-if="loadError" class="pa-6">
                <v-alert type="error" class="mb-4">{{ loadError }}</v-alert>
                <v-btn :href="issuesListUrl" outlined>
                    <v-icon left>mdi-arrow-left</v-icon>
                    Back to Issues
                </v-btn>
            </v-card-text>

            <v-card-text v-else-if="localIssue" class="pa-6">

                <!-- Breadcrumb -->
                <div class="d-flex align-center mb-4">
                    <v-btn text small :href="issuesListUrl" class="pl-0">
                        <v-icon left small>mdi-arrow-left</v-icon>
                        Issues
                    </v-btn>
                    <v-icon small class="mx-1 text--disabled">mdi-chevron-right</v-icon>
                    <span class="text-caption text--secondary">#{{ localIssue.id }}</span>
                </div>

                <!-- Title -->
                <h2 class="text-h6 font-weight-medium mb-4">#{{ localIssue.id }} — {{ localIssue.title }}</h2>

                <!-- Two-column layout -->
                <v-row>

                    <!-- Main content -->
                    <v-col cols="12" md="8">

                        <!-- Description + Field Reference card -->
                        <v-card outlined class="mb-4">
                            <v-card-text class="pa-5">

                                <div class="text-caption text--secondary mb-1">Description</div>
                                <div class="body-2 mb-2" style="white-space: pre-wrap;">{{ localIssue.description || '—' }}</div>

                                <!-- Field Reference collapsible -->
                                <template v-if="localIssue.field_path || hasAnyMetadata">
                                    <v-divider class="my-4"></v-divider>
                                    <v-expansion-panels v-model="advancedPanel" elevation-1>
                                        <v-expansion-panel>
                                            <v-expansion-panel-header>
                                                <div style="width: 100%; text-align: left;">
                                                    <v-icon left small>mdi-code-tags</v-icon>
                                                    <span class="text-subtitle-2">Field Reference</span>
                                                    <code v-if="localIssue.field_path" class="ml-2 text-caption">{{ localIssue.field_path }}</code>
                                                </div>
                                            </v-expansion-panel-header>
                                            <v-expansion-panel-content>

                                                <v-row v-if="hasAnyMetadata" class="mt-1">
                                                    <v-col cols="12" md="6">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon left small>mdi-file-document-outline</v-icon>
                                                            Current Value
                                                        </div>
                                                        <pre style="background-color:#f5f5f5;padding:10px;border-radius:4px;overflow:auto;font-size:12px;line-height:1.5;max-height:220px;white-space:pre-wrap;word-wrap:break-word;">{{ formatMetadata(localIssue.current_metadata) }}</pre>
                                                    </v-col>
                                                    <v-col cols="12" md="6">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon left small color="primary">mdi-file-document-edit-outline</v-icon>
                                                            Suggested Value
                                                            <v-chip v-if="localIssue.applied" x-small color="success" class="ml-1">Applied</v-chip>
                                                        </div>
                                                        <pre style="background-color:#f5f5f5;padding:10px;border-radius:4px;overflow:auto;font-size:12px;line-height:1.5;max-height:220px;white-space:pre-wrap;word-wrap:break-word;">{{ formatMetadata(localIssue.suggested_metadata) }}</pre>
                                                    </v-col>
                                                </v-row>

                                                <v-row v-if="hasAnyMetadata" class="mt-3">
                                                    <v-col cols="12">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon left small>mdi-compare</v-icon>
                                                            Diff
                                                        </div>
                                                        <div ref="metadataDiffContainer" style="min-height:100px;max-height:350px;overflow:auto;background-color:#fafafa;border-radius:4px;padding:8px;"></div>
                                                    </v-col>
                                                </v-row>

                                            </v-expansion-panel-content>
                                        </v-expansion-panel>
                                    </v-expansion-panels>
                                </template>

                            </v-card-text>
                        </v-card>

                        <!-- Resolution card -->
                        <v-card outlined>
                            <v-card-title class="text-subtitle-1 pb-0">Resolution</v-card-title>
                            <v-card-text class="pt-3">

                                <div class="body-2 mb-1">Notes</div>
                                <v-textarea
                                    v-model="resolutionNotes"
                                    outlined
                                    rows="2"
                                    placeholder="Notes or comments..."
                                    hide-details
                                    class="mb-4"
                                ></v-textarea>

                                <!-- Open -->
                                <template v-if="localIssue.status === 'open'">
                                    <v-btn outlined small color="success" class="mr-2 mb-2" @click="resolve('accepted')" :loading="saving">
                                        <v-icon left small>mdi-check</v-icon>Accept
                                    </v-btn>
                                    <v-btn outlined small color="success" class="mr-2 mb-2" @click="resolve('fixed')" :loading="saving">
                                        <v-icon left small>mdi-wrench</v-icon>Mark Fixed
                                    </v-btn>
                                    <v-btn outlined small color="error" class="mr-2 mb-2" @click="resolve('rejected')" :loading="saving">
                                        <v-icon left small>mdi-close</v-icon>Reject
                                    </v-btn>
                                    <v-btn outlined small class="mr-2 mb-2" @click="resolve('dismissed')" :loading="saving">
                                        <v-icon left small>mdi-minus-circle</v-icon>Dismiss
                                    </v-btn>
                                    <v-btn outlined small class="mr-2 mb-2" @click="resolve('false_positive')" :loading="saving">
                                        <v-icon left small>mdi-alert-remove</v-icon>False Positive
                                    </v-btn>
                                </template>

                                <!-- Accepted -->
                                <template v-else-if="localIssue.status === 'accepted'">
                                    <v-btn outlined small color="success" class="mr-2 mb-2" @click="resolve('fixed')" :loading="saving">
                                        <v-icon left small>mdi-wrench</v-icon>Mark Fixed
                                    </v-btn>
                                    <v-btn outlined small color="error" class="mr-2 mb-2" @click="resolve('rejected')" :loading="saving">
                                        <v-icon left small>mdi-close</v-icon>Reject
                                    </v-btn>
                                    <v-btn outlined small class="mr-2 mb-2" @click="resolve('dismissed')" :loading="saving">
                                        <v-icon left small>mdi-minus-circle</v-icon>Dismiss
                                    </v-btn>
                                    <v-btn outlined small class="mr-2 mb-2" @click="resolve('open')" :loading="saving">
                                        <v-icon left small>mdi-refresh</v-icon>Reopen
                                    </v-btn>
                                </template>

                                <!-- Closed -->
                                <template v-else>
                                    <div class="text-caption text--secondary mb-3">This issue is closed.</div>
                                    <v-btn outlined small class="mr-2 mb-2" @click="resolve('open')" :loading="saving">
                                        <v-icon left small>mdi-refresh</v-icon>Reopen
                                    </v-btn>
                                </template>

                            </v-card-text>
                        </v-card>

                    </v-col>

                    <!-- Sidebar -->
                    <v-col cols="12" md="4">
                        <v-card outlined>
                            <v-card-text class="pa-3">

                                <!-- Status -->
                                <div class="text-caption text--secondary mb-1">Status</div>
                                <div class="mb-3">
                                    <issue-status-badge :status="localIssue.status" small></issue-status-badge>
                                </div>

                                <v-divider class="mb-3"></v-divider>

                                <!-- Severity -->
                                <div class="text-caption text--secondary mb-1">Severity</div>
                                <div class="mb-3">
                                    <v-select
                                        :value="localIssue.severity"
                                        :items="severityOptions"
                                        outlined dense hide-details clearable
                                        placeholder="Not set"
                                        :disabled="!isEditable"
                                        style="font-size: 13px;"
                                        @change="saveField('severity', $event)"
                                    ></v-select>
                                </div>

                                <v-divider class="mb-3"></v-divider>

                                <!-- Category -->
                                <div class="text-caption text--secondary mb-1">Category</div>
                                <div class="mb-3">
                                    <v-select
                                        :value="localIssue.category"
                                        :items="categoryOptions"
                                        item-text="text"
                                        item-value="value"
                                        outlined dense hide-details clearable
                                        placeholder="Not set"
                                        :disabled="!isEditable"
                                        style="font-size: 13px;"
                                        @change="saveField('category', $event)"
                                    ></v-select>
                                </div>

                                <v-divider class="mb-3"></v-divider>

                                <!-- Project -->
                                <div class="text-caption text--secondary mb-1">Project</div>
                                <div class="mb-3">
                                    <a :href="projectUrl" target="_blank" class="body-2 d-flex align-center text-decoration-none">
                                        <v-icon x-small class="mr-1">mdi-open-in-new</v-icon>
                                        {{ localIssue.project_title || ('Project ' + localIssue.project_id) }}
                                    </a>
                                </div>

                                <v-divider class="mb-3"></v-divider>

                                <!-- Activity -->
                                <div class="text-caption text--secondary">
                                    <div v-if="localIssue.created" class="mb-1">
                                        Created {{ formatDate(localIssue.created) }}<span v-if="localIssue.created_by_username"> by {{ localIssue.created_by_username }}</span>
                                    </div>
                                    <div v-if="localIssue.assigned_to_username" class="mb-1">
                                        Assigned to {{ localIssue.assigned_to_username }}
                                    </div>
                                    <div v-if="localIssue.resolved" class="mb-1">
                                        Resolved {{ formatDate(localIssue.resolved) }}<span v-if="localIssue.resolved_by_username"> by {{ localIssue.resolved_by_username }}</span>
                                    </div>
                                    <div v-if="localIssue.applied_on" class="mb-1">
                                        Applied {{ formatDate(localIssue.applied_on) }}<span v-if="localIssue.applied_by_username"> by {{ localIssue.applied_by_username }}</span>
                                    </div>
                                    <div v-if="localIssue.source" class="mt-2">
                                        Source: <span class="text-capitalize">{{ localIssue.source }}</span>
                                    </div>
                                </div>

                            </v-card-text>
                        </v-card>
                    </v-col>

                </v-row>
            </v-card-text>
        </v-card>
    `
});
