/**
 * Issue Detail Dialog Component
 *
 * Modal dialog for viewing and resolving issue details.
 * Uses a two-column layout: main content (title, description, field reference, resolution)
 * and a right sidebar (status, severity, category, notes, activity).
 *
 * Props:
 *   - value: Boolean - v-model for dialog visibility
 *   - issue: Object - Issue object to display
 *   - projectId: Number - Project ID
 *
 * Events:
 *   - input: v-model update
 *   - issue-updated: Emitted after issue is updated
 *   - issue-applied: Emitted after changes are applied to Vuex formData
 */
Vue.component('issue-detail-dialog', {
    props: {
        value: {
            type: Boolean,
            default: false
        },
        issue: {
            type: Object,
            default: null
        },
        projectId: {
            type: Number,
            required: true
        }
    },
    data() {
        return {
            loading: false,
            saving: false,
            applying: false,
            localIssue: null,
            resolutionNotes: '',
            applyValueToApply: '',
            isMaximized: false,
            diffRoot: null,
            advancedPanel: undefined,
            errors: {},
            severityOptions: [
                { text: 'Low', value: 'low' },
                { text: 'Medium', value: 'medium' },
                { text: 'High', value: 'high' },
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
        dialogVisible: {
            get() { return this.value; },
            set(val) { this.$emit('input', val); }
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
        },
        canApply() {
            return this.hasSuggestedMetadata && !Number(this.localIssue && this.localIssue.applied);
        },
        isEditable() {
            return this.localIssue &&
                   (this.localIssue.status === 'open' || this.localIssue.status === 'accepted');
        }
    },
    watch: {
        issue(newVal) {
            if (newVal) {
                this.localIssue = JSON.parse(JSON.stringify(newVal));
                this.resolutionNotes = '';
                this.applyValueToApply = this.getMetadataFieldValue(newVal.suggested_metadata, newVal.field_path);
                this.$nextTick(() => this.renderMetadataDiff());
            }
        },
        value(newVal) {
            if (newVal) {
                if (this.issue) {
                    this.localIssue = JSON.parse(JSON.stringify(this.issue));
                    this.resolutionNotes = '';
                    this.applyValueToApply = this.getMetadataFieldValue(this.issue.suggested_metadata, this.issue.field_path);
                }
                this.$nextTick(() => {
                    setTimeout(() => this.$nextTick(() => this.renderMetadataDiff()), 200);
                });
            } else {
                this.isMaximized = false;
                if (this.diffRoot && this.diffRoot.unmount) {
                    try { this.diffRoot.unmount(); } catch (e) { /* ignore */ }
                    this.diffRoot = null;
                }
                if (this.$refs && this.$refs.metadataDiffContainer) {
                    this.$refs.metadataDiffContainer.innerHTML = '';
                }
            }
        },
        advancedPanel(val) {
            if (val !== undefined) {
                this.$nextTick(() => {
                    setTimeout(() => this.$nextTick(() => this.renderMetadataDiff()), 200);
                });
            }
        }
    },
    methods: {
        formatDate(timestamp) {
            if (!timestamp) return '-';
            return moment.unix(timestamp).format('MMM D, YYYY');
        },
        formatMetadata(metadata) {
            if (metadata == null || (typeof metadata === 'object' && Object.keys(metadata).length === 0)) {
                return '';
            }
            const fieldPath = this.localIssue && this.localIssue.field_path;
            if (
                fieldPath &&
                typeof metadata === 'object' &&
                !Array.isArray(metadata) &&
                Object.prototype.hasOwnProperty.call(metadata, fieldPath)
            ) {
                const value = metadata[fieldPath];
                if (value === undefined || value === null) return '';
                if (typeof value === 'object') return JSON.stringify(value, null, 2);
                return String(value);
            }
            if (typeof metadata === 'object') return JSON.stringify(metadata, null, 2);
            return String(metadata);
        },
        getMetadataFieldValue(metadata, fieldPath) {
            if (metadata == null) return '';
            if (
                fieldPath &&
                typeof metadata === 'object' &&
                !Array.isArray(metadata) &&
                Object.prototype.hasOwnProperty.call(metadata, fieldPath)
            ) {
                const value = metadata[fieldPath];
                if (value === undefined || value === null) return '';
                if (typeof value === 'object') return JSON.stringify(value, null, 2);
                return String(value);
            }
            if (typeof metadata === 'object') return JSON.stringify(metadata, null, 2);
            return String(metadata);
        },
        parseMetadataForDiff(val) {
            if (val == null) return null;
            const fieldPath = this.localIssue && this.localIssue.field_path;
            let value = val;
            if (
                fieldPath &&
                typeof val === 'object' &&
                !Array.isArray(val) &&
                Object.prototype.hasOwnProperty.call(val, fieldPath)
            ) {
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
            if (!container) return;
            if (typeof JsonDiffKit === 'undefined') return;
            if (!document.contains(container)) return;

            const current = this.parseMetadataForDiff(this.localIssue.current_metadata);
            const suggested = this.parseMetadataForDiff(this.localIssue.suggested_metadata);
            const before = current !== null ? current : {};
            const after = suggested !== null ? suggested : {};

            try {
                if (this.diffRoot) {
                    try { this.diffRoot.unmount(); } catch (e) { /* ignore */ }
                    this.diffRoot = null;
                }
                container.innerHTML = '';
                const differ = new JsonDiffKit.Differ({
                    detectCircular: true,
                    showModifications: true,
                    arrayDiffMethod: 'lcs'
                });
                const diff = differ.diff(before, after);
                const viewer = new JsonDiffKit.Viewer({
                    diff: diff,
                    indent: 2,
                    lineNumbers: true,
                    highlightInlineDiff: true,
                    inlineDiffOptions: { mode: 'word', wordSeparator: ' ' },
                    syntaxHighlight: true
                });
                this.diffRoot = JsonDiffKit.ReactDOM.createRoot(container);
                this.diffRoot.render(JsonDiffKit.React.createElement(viewer.render));
            } catch (err) {
                console.error('Error rendering metadata diff:', err);
            }
        },
        showToast(message, type) {
            if (this.$root.$refs && this.$root.$refs.toast && typeof this.$root.$refs.toast.showAlert === 'function') {
                this.$root.$refs.toast.showAlert(message, type);
            } else {
                console.warn('[issue-detail-dialog] Toast not available:', message, type);
            }
        },
        async saveField(field, value) {
            if (!this.localIssue) return;
            try {
                const payload = {};
                payload[field] = value;
                const response = await axios.put(CI.base_url + '/api/issues/' + this.localIssue.id, payload);
                if (response.data.status === 'success') {
                    Vue.set(this.localIssue, field, value);
                    this.showToast('Saved', 'success');
                    this.$emit('issue-updated', { ...this.localIssue });
                } else {
                    throw new Error(response.data.message || 'Failed to save');
                }
            } catch (error) {
                this.showToast(error.response?.data?.message || error.message || 'Failed to save', 'error');
            }
        },
        async resolve(newStatus) {
            if (!this.localIssue) return;
            this.saving = true;
            try {
                if (this.resolutionNotes.trim()) {
                    await axios.put(CI.base_url + '/api/issues/' + this.localIssue.id, { notes: this.resolutionNotes });
                }
                const response = await axios.post(CI.base_url + '/api/issues/status/' + this.localIssue.id, { status: newStatus });
                if (response.data.status === 'success') {
                    this.showToast('Issue updated', 'success');
                    Vue.set(this.localIssue, 'status', newStatus);
                    if (this.resolutionNotes.trim()) {
                        Vue.set(this.localIssue, 'notes', this.resolutionNotes);
                    }
                    this.resolutionNotes = '';
                    this.$emit('issue-updated', { ...this.localIssue });
                } else {
                    throw new Error(response.data.message || 'Failed to update status');
                }
            } catch (error) {
                this.showToast(error.response?.data?.message || error.message || 'Failed to update issue', 'error');
            } finally {
                this.saving = false;
            }
        },
        getValueToApplyPayload() {
            const text = (this.applyValueToApply != null && String(this.applyValueToApply).trim())
                ? String(this.applyValueToApply).trim()
                : '';
            if (!text) return null;
            try { return JSON.parse(text); } catch (e) { return text; }
        },
        setValueByPath(obj, path, value) {
            if (!path || !obj || typeof obj !== 'object') return;
            const parts = path.split('.');
            let current = obj;
            for (let i = 0; i < parts.length - 1; i++) {
                const key = parts[i];
                if (!(key in current) || current[key] == null) {
                    Vue.set(current, key, {});
                }
                current = current[key];
            }
            Vue.set(current, parts[parts.length - 1], value);
        },
        applyChanges() {
            const valueToApply = this.getValueToApplyPayload();
            if (valueToApply === null && (!this.applyValueToApply || !String(this.applyValueToApply).trim())) {
                this.showToast('Enter a value to apply', 'warning');
                return;
            }
            if (!confirm('Apply this value to the project metadata field?')) return;
            const fieldPath = this.localIssue && this.localIssue.field_path;
            if (!fieldPath) {
                this.showToast('No field path on this issue', 'error');
                return;
            }
            const formData = this.$store.state.formData;
            if (!formData || typeof formData !== 'object') {
                this.showToast('Project metadata not loaded', 'error');
                return;
            }
            this.loading = true;
            try {
                const value = valueToApply !== null ? valueToApply : String(this.applyValueToApply || '').trim();
                this.setValueByPath(formData, fieldPath, value);
                this.showToast('Changes applied to project metadata', 'success');
                this.$emit('issue-applied', this.localIssue);
            } catch (error) {
                this.showToast(error.message || 'Failed to apply changes', 'error');
            } finally {
                this.loading = false;
            }
        },
        close() {
            this.dialogVisible = false;
        }
    },
    template: `
        <v-dialog
            v-model="dialogVisible"
            :max-width="isMaximized ? undefined : '1100px'"
            :fullscreen="isMaximized"
            scrollable
            transition="dialog-transition"
            content-class="issue-detail-dialog"
        >
            <v-card v-if="localIssue" class="d-flex flex-column" :class="{ 'fill-height': isMaximized }">

                <!-- Header -->
                <v-card-title class="headline grey lighten-2 flex-shrink-0">
                    <v-icon left>mdi-alert-circle-outline</v-icon>
                    <span class="font-weight-medium text-truncate" style="max-width: calc(100% - 120px);">
                        #{{ localIssue.id }} &mdash; {{ localIssue.title }}
                    </span>
                    <v-spacer></v-spacer>
                    <v-btn icon @click="isMaximized = !isMaximized" :title="isMaximized ? 'Restore' : 'Maximize'">
                        <v-icon>{{ isMaximized ? 'mdi-window-restore' : 'mdi-window-maximize' }}</v-icon>
                    </v-btn>
                    <v-btn icon @click="close">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>

                <v-card-text class="pt-4 flex-grow-1 overflow-auto" style="min-height: 320px;">
                    <v-container fluid class="pa-0">
                        <v-row>

                            <!-- Main content -->
                            <v-col cols="12" md="8">

                                <!-- Content card -->
                                <v-card outlined class="mb-4">
                                    <v-card-text class="pa-5">

                                        <!-- Description -->
                                        <div class="text-caption text--secondary mb-1">Description</div>
                                        <div class="body-2 mb-2" style="white-space: pre-wrap;">{{ localIssue.description }}</div>

                                        <!-- Field Reference -->
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

                                                        <!-- Current / Suggested -->
                                                        <v-row v-if="hasAnyMetadata" class="mt-1">
                                                            <v-col cols="12" md="6">
                                                                <div class="text-subtitle-2 mb-2">
                                                                    <v-icon left small>mdi-file-document-outline</v-icon>
                                                                    Current Value
                                                                </div>
                                                                <pre style="background-color: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; font-size: 12px; line-height: 1.5; max-height: 220px; white-space: pre-wrap; word-wrap: break-word;">{{ formatMetadata(localIssue.current_metadata) }}</pre>
                                                            </v-col>
                                                            <v-col cols="12" md="6">
                                                                <div class="text-subtitle-2 mb-2">
                                                                    <v-icon left small color="primary">mdi-file-document-edit-outline</v-icon>
                                                                    Suggested Value
                                                                    <v-chip v-if="localIssue.applied" x-small color="success" class="ml-1">Applied</v-chip>
                                                                </div>
                                                                <v-textarea
                                                                    v-if="canApply"
                                                                    v-model="applyValueToApply"
                                                                    outlined
                                                                    dense
                                                                    rows="4"
                                                                    hide-details
                                                                    style="font-size: 12px;"
                                                                ></v-textarea>
                                                                <pre v-else style="background-color: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; font-size: 12px; line-height: 1.5; max-height: 220px; white-space: pre-wrap; word-wrap: break-word;">{{ formatMetadata(localIssue.suggested_metadata) }}</pre>
                                                                <v-btn
                                                                    v-if="canApply"
                                                                    color="primary"
                                                                    small
                                                                    outlined
                                                                    class="mt-2"
                                                                    @click="applyChanges"
                                                                    :loading="loading"
                                                                >
                                                                    <v-icon small left>mdi-check</v-icon>
                                                                    Apply to field
                                                                </v-btn>
                                                            </v-col>
                                                        </v-row>

                                                        <!-- Diff -->
                                                        <v-row v-if="hasAnyMetadata" class="mt-3">
                                                            <v-col cols="12">
                                                                <div class="text-subtitle-2 mb-2">
                                                                    <v-icon left small>mdi-compare</v-icon>
                                                                    Diff
                                                                </div>
                                                                <div ref="metadataDiffContainer" style="min-height: 100px; max-height: 350px; overflow: auto; background-color: #fafafa; border-radius: 4px; padding: 8px;"></div>
                                                            </v-col>
                                                        </v-row>

                                                    </v-expansion-panel-content>
                                                </v-expansion-panel>
                                            </v-expansion-panels>
                                        </template>

                                    </v-card-text>
                                </v-card>

                                <!-- Resolution -->
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
                                                <v-icon left small>mdi-check</v-icon>
                                                Accept
                                            </v-btn>
                                            <v-btn outlined small color="success" class="mr-2 mb-2" @click="resolve('fixed')" :loading="saving">
                                                <v-icon left small>mdi-wrench</v-icon>
                                                Mark Fixed
                                            </v-btn>
                                            <v-btn outlined small color="error" class="mr-2 mb-2" @click="resolve('rejected')" :loading="saving">
                                                <v-icon left small>mdi-close</v-icon>
                                                Reject
                                            </v-btn>
                                            <v-btn outlined small class="mr-2 mb-2" @click="resolve('dismissed')" :loading="saving">
                                                <v-icon left small>mdi-minus-circle</v-icon>
                                                Dismiss
                                            </v-btn>
                                            <v-btn outlined small class="mr-2 mb-2" @click="resolve('false_positive')" :loading="saving">
                                                <v-icon left small>mdi-alert-remove</v-icon>
                                                False Positive
                                            </v-btn>
                                        </template>

                                        <!-- Accepted -->
                                        <template v-else-if="localIssue.status === 'accepted'">
                                            <v-btn outlined small color="success" class="mr-2 mb-2" @click="resolve('fixed')" :loading="saving">
                                                <v-icon left small>mdi-wrench</v-icon>
                                                Mark Fixed
                                            </v-btn>
                                            <v-btn outlined small color="error" class="mr-2 mb-2" @click="resolve('rejected')" :loading="saving">
                                                <v-icon left small>mdi-close</v-icon>
                                                Reject
                                            </v-btn>
                                            <v-btn outlined small class="mr-2 mb-2" @click="resolve('dismissed')" :loading="saving">
                                                <v-icon left small>mdi-minus-circle</v-icon>
                                                Dismiss
                                            </v-btn>
                                            <v-btn outlined small class="mr-2 mb-2" @click="resolve('open')" :loading="saving">
                                                <v-icon left small>mdi-refresh</v-icon>
                                                Reopen
                                            </v-btn>
                                        </template>

                                        <!-- Closed -->
                                        <template v-else>
                                            <div class="text-caption text--secondary mb-3">This issue is closed.</div>
                                            <v-btn outlined small class="mr-2 mb-2" @click="resolve('open')" :loading="saving">
                                                <v-icon left small>mdi-refresh</v-icon>
                                                Reopen
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
                                                outlined
                                                dense
                                                hide-details
                                                clearable
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
                                                outlined
                                                dense
                                                hide-details
                                                clearable
                                                placeholder="Not set"
                                                :disabled="!isEditable"
                                                style="font-size: 13px;"
                                                @change="saveField('category', $event)"
                                            ></v-select>
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
                    </v-container>
                </v-card-text>

            </v-card>
        </v-dialog>
    `,
    created() {
        this._styleEl = null;
    },
    beforeDestroy() {
        if (this._styleEl && this._styleEl.parentNode) {
            this._styleEl.parentNode.removeChild(this._styleEl);
        }
        if (this.diffRoot && this.diffRoot.unmount) {
            try { this.diffRoot.unmount(); } catch (e) { /* ignore */ }
        }
    }
});
