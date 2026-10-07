/**
 * Issue Edit Page Component
 * 
 * Full page for viewing and editing existing issues
 */
const VueIssueEdit = Vue.component('issue-edit', {
    props: {
        issueId: {
            type: [String, Number],
            required: true
        },
        projectId: {
            type: Number,
            default: null
        }
    },
    data() {
        return {
            loading: false,
            saving: false,
            applying: false,
            issue: null,
            editedIssue: {},
            editMode: false,
            resolutionNotes: '',
            currentMetadataText: '',
            suggestedMetadataText: '',
            errors: {},
            categoryOptions: [
                { text: this.$t('category_typo_wording'), value: 'typo_wording' },
                { text: this.$t('category_inconsistency'), value: 'inconsistency' },
                { text: this.$t('category_missing_data'), value: 'missing_data' },
                { text: this.$t('category_format_issue'), value: 'format_issue' },
                { text: this.$t('category_completeness'), value: 'completeness' },
                { text: this.$t('category_other'), value: 'other' }
            ],
            severityOptions: [
                { text: this.$t('severity_low'), value: 'low' },
                { text: this.$t('severity_medium'), value: 'medium' },
                { text: this.$t('severity_high'), value: 'high' },
                { text: this.$t('severity_critical'), value: 'critical' }
            ],
            statusOptions: [
                { text: this.$t('status_open'), value: 'open' },
                { text: this.$t('status_accepted'), value: 'accepted' },
                { text: this.$t('status_fixed'), value: 'fixed' },
                { text: this.$t('status_rejected'), value: 'rejected' },
                { text: this.$t('status_dismissed'), value: 'dismissed' },
                { text: this.$t('status_false_positive'), value: 'false_positive' }
            ],
            diffRoot: null,
            advancedPanel: [0]
        };
    },
    computed: {
        ProjectID() {
            return this.projectId || this.$root.dataset_id;
        },
        hasCurrentMetadata() {
            return this.issue && this.issue.current_metadata != null && typeof this.issue.current_metadata === 'object' && Object.keys(this.issue.current_metadata).length > 0;
        },
        hasSuggestedMetadata() {
            return this.issue && this.issue.suggested_metadata != null && typeof this.issue.suggested_metadata === 'object' && Object.keys(this.issue.suggested_metadata).length > 0;
        },
        hasAnyMetadata() {
            return this.issue && (this.issue.current_metadata != null || this.issue.suggested_metadata != null);
        },
        canApply() {
            return this.hasSuggestedMetadata && !this.issue.is_applied;
        },
        isEditable() {
            return this.issue && (this.issue.status === 'open' || this.issue.status === 'accepted');
        },
        UserHasEditAccess() {
            return this.$root.UserHasEditAccess;
        },
        fieldPathOptions() {
            const formData = this.$store.state.formData;
            if (!formData || typeof formData !== 'object') {
                return [];
            }
            return this.flattenFormData(formData);
        }
    },
    mounted() {
        this.loadIssue();
    },
    updated() {
        this.$nextTick(() => this.$nextTick(() => this.renderMetadataDiff()));
    },
    beforeDestroy() {
        if (this.diffRoot && this.diffRoot.unmount) {
            this.diffRoot.unmount();
            this.diffRoot = null;
        }
    },
    watch: {
        advancedPanel: {
            handler(val) {
                if (val && val.length > 0) {
                    this.$nextTick(() => this.$nextTick(() => this.renderMetadataDiff()));
                }
            },
            deep: true
        },
        editMode() {
            this.$nextTick(() => this.$nextTick(() => this.renderMetadataDiff()));
        },
        currentMetadataText(val) {
            if (this.editMode) {
                this.parseMetadataText('current', val);
            }
        },
        suggestedMetadataText(val) {
            if (this.editMode) {
                this.parseMetadataText('suggested', val);
            }
        },
        'editedIssue.field_path'(newPath) {
            // Auto-populate current metadata when field path is changed in edit mode
            if (this.editMode && newPath && this.$store.state.formData) {
                const currentValue = this.getValueByPath(this.$store.state.formData, newPath);
                if (currentValue !== undefined && currentValue !== null) {
                    // Store the value directly, not as {path: value}
                    if (typeof currentValue === 'object') {
                        this.currentMetadataText = JSON.stringify(currentValue, null, 2);
                    } else {
                        this.currentMetadataText = String(currentValue);
                    }
                }
            }
        }
    },
    methods: {
        getSeverityLabel(code) {
            const key = 'severity_' + code;
            return this.$te(key) ? this.$t(key) : (code || '');
        },
        getCategoryLabel(code) {
            const opt = this.categoryOptions.find(o => o.value === code);
            return opt ? opt.text : (code || '');
        },
        async loadIssue() {
            this.loading = true;
            try {
                const url = CI.base_url + '/api/issues/' + this.issueId;
                const response = await axios.get(url);

                if (response.data.status === 'success') {
                    this.issue = response.data.issue;
                    this.editedIssue = { ...this.issue };
                    this.$nextTick(() => {
                        setTimeout(() => {
                            this.$nextTick(() => this.renderMetadataDiff());
                        }, 200);
                    });
                } else {
                    throw new Error(response.data.message || this.$t('error_load_issue'));
                }
            } catch (error) {
                console.error('Error loading issue:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_load_issue')
                );
                this.$router.push('/issues');
            } finally {
                this.loading = false;
            }
        },
        toggleEditMode() {
            if (!this.editMode) {
                // Entering edit mode
                this.editedIssue = { ...this.issue };
                this.currentMetadataText = this.getMetadataFieldValue(this.issue.current_metadata, this.issue.field_path);
                this.suggestedMetadataText = this.getMetadataFieldValue(this.issue.suggested_metadata, this.issue.field_path);
            }
            this.editMode = !this.editMode;
        },
        parseMetadataText(type, text) {
            if (!text || text.trim() === '') {
                if (type === 'current') {
                    this.editedIssue.current_metadata = {};
                } else {
                    this.editedIssue.suggested_metadata = {};
                }
                return;
            }

            try {
                const parsed = JSON.parse(text);
                const fieldPath = this.editedIssue.field_path || this.issue.field_path;
                if (type === 'current') {
                    this.editedIssue.current_metadata = fieldPath
                        ? { [fieldPath]: parsed }
                        : parsed;
                } else {
                    // Allow suggested metadata to be a full JSON object; if not keyed, keep as-is
                    this.editedIssue.suggested_metadata = parsed;
                }
                this.errors[type + '_metadata'] = null;
            } catch (e) {
                const fieldPath = this.editedIssue.field_path || this.issue.field_path;
                if (fieldPath) {
                    const obj = {};
                    obj[fieldPath] = text;
                    if (type === 'current') {
                        this.editedIssue.current_metadata = obj;
                    } else {
                        this.editedIssue.suggested_metadata = obj;
                    }
                    this.errors[type + '_metadata'] = null;
                } else {
                    this.errors[type + '_metadata'] = this.$t('error_invalid_json');
                }
            }
        },
        getMetadataFieldValue(metadata, fieldPath) {
            if (metadata == null) return '';
            // If metadata is an object keyed by field path, extract just that value
            if (
                fieldPath &&
                typeof metadata === 'object' &&
                !Array.isArray(metadata) &&
                Object.prototype.hasOwnProperty.call(metadata, fieldPath)
            ) {
                const value = metadata[fieldPath];
                if (value === undefined || value === null) {
                    return '';
                }
                if (typeof value === 'object') {
                    return JSON.stringify(value, null, 2);
                }
                return String(value);
            }
            // Fallbacks: show raw JSON or string
            if (typeof metadata === 'object') {
                return JSON.stringify(metadata, null, 2);
            }
            return String(metadata);
        },
        async saveChanges() {
            const title = (this.editedIssue.title !== undefined && this.editedIssue.title !== null ? String(this.editedIssue.title) : '').trim();
            if (!title) {
                EventBus.$emit('onFail', this.$t('validation_title_required'));
                return;
            }
            this.saving = true;
            try {
                const url = CI.base_url + '/api/issues/' + this.issueId;
                const response = await axios.put(url, this.editedIssue);

                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('issue_updated'));
                    this.issue = response.data.issue;
                    this.editedIssue = { ...this.issue };
                    this.editMode = false;
                } else {
                    throw new Error(response.data.message || this.$t('error_update_issue'));
                }
            } catch (error) {
                console.error('Error updating issue:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_update_issue')
                );
            } finally {
                this.saving = false;
            }
        },
        async applyChanges() {
            if (!confirm(this.$t('confirm_apply_to_field'))) {
                return;
            }

            this.applying = true;
            try {
                const url = CI.base_url + '/api/issues/apply/' + this.issueId;
                const response = await axios.post(url);

                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('changes_applied'));
                    this.loadIssue();
                } else {
                    throw new Error(response.data.message || this.$t('error_apply_changes'));
                }
            } catch (error) {
                console.error('Error applying changes:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_apply_changes')
                );
            } finally {
                this.applying = false;
            }
        },
        async updateStatus(newStatus) {
            try {
                const url = CI.base_url + '/api/issues/status/' + this.issueId;
                const response = await axios.post(url, { status: newStatus });

                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('issue_updated'));
                    this.issue.status = newStatus;
                    this.editedIssue.status = newStatus;
                } else {
                    throw new Error(response.data.message || this.$t('error_update_status'));
                }
            } catch (error) {
                console.error('Error updating status:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_update_status')
                );
            }
        },
        async resolve(newStatus) {
            this.saving = true;
            try {
                if (this.resolutionNotes.trim()) {
                    await axios.put(CI.base_url + '/api/issues/' + this.issueId, { notes: this.resolutionNotes });
                }
                const response = await axios.post(CI.base_url + '/api/issues/status/' + this.issueId, { status: newStatus });
                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('issue_updated'));
                    this.issue.status = newStatus;
                    this.editedIssue.status = newStatus;
                    if (this.resolutionNotes.trim()) {
                        this.issue.notes = this.resolutionNotes;
                        this.editedIssue.notes = this.resolutionNotes;
                    }
                    this.resolutionNotes = '';
                } else {
                    throw new Error(response.data.message || this.$t('error_update_status'));
                }
            } catch (error) {
                EventBus.$emit('onFail', error.response?.data?.message || error.message || this.$t('error_update_issue'));
            } finally {
                this.saving = false;
            }
        },
        async applyWithNotes() {
            if (!confirm(this.$t('confirm_apply_to_field'))) {
                return;
            }
            this.applying = true;
            try {
                if (this.resolutionNotes.trim()) {
                    await axios.put(CI.base_url + '/api/issues/' + this.issueId, { notes: this.resolutionNotes });
                }
                const response = await axios.post(CI.base_url + '/api/issues/apply/' + this.issueId);
                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('changes_applied'));
                    this.resolutionNotes = '';
                    this.loadIssue();
                } else {
                    throw new Error(response.data.message || this.$t('error_apply_changes'));
                }
            } catch (error) {
                EventBus.$emit('onFail', error.response?.data?.message || error.message || this.$t('error_apply_changes'));
            } finally {
                this.applying = false;
            }
        },
        async deleteIssue() {
            if (!confirm(this.$t('confirm_delete_issue'))) {
                return;
            }

            try {
                const url = CI.base_url + '/api/issues/delete/' + this.issueId;
                const response = await axios.post(url);

                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('issue_deleted'));
                    this.$router.push('/issues');
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
        formatDate(timestamp) {
            if (!timestamp) return 'N/A';
            return moment.unix(timestamp).format('MMM D, YYYY');
        },
        formatMetadata(metadata) {
            if (metadata == null || (typeof metadata === 'object' && Object.keys(metadata).length === 0)) {
                return 'No data';
            }
            const fieldPath = this.issue && this.issue.field_path;
            if (
                fieldPath &&
                typeof metadata === 'object' &&
                !Array.isArray(metadata) &&
                Object.prototype.hasOwnProperty.call(metadata, fieldPath)
            ) {
                const value = metadata[fieldPath];
                if (value === undefined || value === null) {
                    return '';
                }
                if (typeof value === 'object') {
                    return JSON.stringify(value, null, 2);
                }
                return String(value);
            }
            if (typeof metadata === 'object') {
                return JSON.stringify(metadata, null, 2);
            }
            return String(metadata);
        },
        cancel() {
            if (this.editMode) {
                this.editMode = false;
                this.editedIssue = { ...this.issue };
            } else {
                this.$router.push('/issues');
            }
        },
        flattenFormData(obj, parentPath = '') {
            let fields = [];
            
            if (!obj || typeof obj !== 'object') {
                return fields;
            }
            
            if (Array.isArray(obj)) {
                // Handle arrays - add each item with index
                obj.forEach((item, index) => {
                    const currentPath = parentPath ? `${parentPath}[${index}]` : `[${index}]`;
                    
                    if (item && typeof item === 'object') {
                        // Recurse into array item
                        const nestedFields = this.flattenFormData(item, currentPath);
                        fields = fields.concat(nestedFields);
                    } else if (item !== null && item !== undefined) {
                        // Add primitive array item
                        fields.push({
                            text: currentPath,
                            value: currentPath
                        });
                    }
                });
            } else {
                // Handle objects
                Object.keys(obj).forEach(key => {
                    const value = obj[key];
                    const currentPath = parentPath ? `${parentPath}.${key}` : key;
                    
                    // Skip null or undefined values
                    if (value === null || value === undefined) {
                        return;
                    }
                    
                    if (typeof value === 'object') {
                        // Add the path itself for objects/arrays
                        fields.push({
                            text: currentPath,
                            value: currentPath
                        });
                        // Recurse into nested structure
                        const nestedFields = this.flattenFormData(value, currentPath);
                        fields = fields.concat(nestedFields);
                    } else {
                        // Add primitive value
                        fields.push({
                            text: currentPath,
                            value: currentPath
                        });
                    }
                });
            }
            
            return fields;
        },
        getValueByPath(obj, path) {
            // Extract value from nested object using dot notation and array indices
            if (!path || !obj) return undefined;
            
            let current = obj;
            // Split by dots but preserve array bracket notation
            const parts = path.split('.');
            
            for (let part of parts) {
                // Check if this part contains array access like "items[0]"
                const arrayMatch = part.match(/^([^\[]+)\[(\d+)\]$/);
                if (arrayMatch) {
                    const key = arrayMatch[1];
                    const index = parseInt(arrayMatch[2]);
                    if (current && typeof current === 'object' && key in current && Array.isArray(current[key])) {
                        current = current[key][index];
                    } else {
                        return undefined;
                    }
                } else if (part.match(/^\[(\d+)\]$/)) {
                    // Handle pure array access like "[0]"
                    const index = parseInt(part.match(/^\[(\d+)\]$/)[1]);
                    if (Array.isArray(current) && index < current.length) {
                        current = current[index];
                    } else {
                        return undefined;
                    }
                } else {
                    // Regular object key access
                    if (current && typeof current === 'object' && part in current) {
                        current = current[part];
                    } else {
                        return undefined;
                    }
                }
            }
            
            return current;
        },
        parseMetadataForDiff(val) {
            if (val == null) return null;

            // If metadata is stored as { "field_path": value }, extract just the value
            const fieldPath = this.issue && this.issue.field_path;
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
            if (!this.issue) return;
            const container = this.$refs.metadataDiffContainer;
            if (!container) return;
            if (typeof JsonDiffKit === 'undefined') return;
            if (!document.contains(container)) return;

            const current = this.parseMetadataForDiff(this.issue.current_metadata);
            const suggested = this.parseMetadataForDiff(this.issue.suggested_metadata);
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
        }
    },
    template: `
        <div class="issue-edit-page">
            <v-container fluid v-if="loading" style="max-width: 100% !important;">
                <v-row>
                    <v-col cols="12" class="text-center py-12">
                        <v-progress-circular indeterminate color="primary"></v-progress-circular>
                        <div class="mt-4">{{ $t('loading_issue') }}</div>
                    </v-col>
                </v-row>
            </v-container>

            <v-container fluid v-else-if="issue" style="max-width: 100% !important;" class="mt-4">

                <!-- Page Header -->
                <v-row>
                    <v-col cols="12">
                        <div class="d-flex align-center mb-4">
                            <v-btn icon @click="cancel" class="mr-3">
                                <v-icon>mdi-arrow-left</v-icon>
                            </v-btn>
                            <h2 class="text-h5">#{{ issue.id }} &mdash; {{ issue.title }}</h2>
                            <v-spacer></v-spacer>
                            <v-btn
                                v-if="!editMode && UserHasEditAccess && isEditable"
                                color="primary"
                                outlined
                                @click="toggleEditMode"
                                class="mr-2"
                            >
                                <v-icon left>mdi-pencil</v-icon>
                                {{ $t('action_edit') }}
                            </v-btn>
                            <v-btn
                                v-if="editMode"
                                color="primary"
                                @click="saveChanges"
                                :loading="saving"
                                class="mr-2"
                            >
                                <v-icon left>mdi-content-save</v-icon>
                                {{ $t('action_save') }}
                            </v-btn>
                            <v-btn v-if="editMode" text @click="cancel" class="mr-2">{{ $t('action_cancel') }}</v-btn>
                            <v-btn v-if="UserHasEditAccess && !editMode" icon @click="deleteIssue">
                                <v-icon color="error">mdi-delete</v-icon>
                            </v-btn>
                        </div>
                    </v-col>
                </v-row>

                <!-- Two-column layout -->
                <v-row>

                    <!-- Main content -->
                    <v-col cols="12" md="8">

                        <!-- Content card -->
                        <v-card class="mb-4">
                            <v-card-text class="pa-6">

                                <!-- Title -->
                                <div class="body-2 mb-1">{{ $t('issue_title') }} <span v-if="editMode" class="error--text">*</span></div>
                                <v-text-field
                                    v-if="editMode"
                                    v-model="editedIssue.title"
                                    outlined
                                    dense
                                    hide-details="auto"
                                    class="mb-4"
                                ></v-text-field>
                                <div v-else class="body-1 mb-4">{{ issue.title }}</div>

                                <!-- Description -->
                                <div class="body-2 mb-1">{{ $t('issue_description') }}</div>
                                <v-textarea
                                    v-if="editMode"
                                    v-model="editedIssue.description"
                                    outlined
                                    rows="4"
                                    hide-details="auto"
                                    class="mb-4"
                                ></v-textarea>
                                <div v-else class="body-2 mb-4" style="white-space: pre-wrap;">{{ issue.description }}</div>

                                <!-- Field Reference -->
                                <template v-if="issue.field_path || hasAnyMetadata || editMode">
                                    <v-divider class="mb-4"></v-divider>
                                    <v-expansion-panels v-model="advancedPanel" elevation-1>
                                        <v-expansion-panel>
                                            <v-expansion-panel-header>
                                                <div>
                                                    <v-icon left small>mdi-code-tags</v-icon>
                                                    <span class="text-subtitle-2">{{ $t('issue_field_reference') }}</span>
                                                    <code v-if="issue.field_path && !editMode" class="ml-2 text-caption">{{ issue.field_path }}</code>
                                                </div>
                                            </v-expansion-panel-header>
                                            <v-expansion-panel-content>
                                                <!-- Field Path -->
                                                <v-row dense v-if="issue.field_path || editMode" class="mt-2">
                                                    <v-col cols="12">
                                                        <v-autocomplete
                                                            v-if="editMode"
                                                            v-model="editedIssue.field_path"
                                                            :items="fieldPathOptions"
                                                            item-text="text"
                                                            item-value="value"
                                                            :label="$t('issue_field_path')"
                                                            outlined
                                                            dense
                                                            clearable
                                                            :placeholder="$t('placeholder_select_field')"
                                                            :hint="$t('hint_field_path')"
                                                            persistent-hint
                                                        >
                                                            <template v-slot:item="{ item }">
                                                                <v-list-item-content>
                                                                    <v-list-item-title>
                                                                        <code style="font-size: 12px;">{{ item.value }}</code>
                                                                    </v-list-item-title>
                                                                </v-list-item-content>
                                                            </template>
                                                        </v-autocomplete>
                                                        <div v-else>
                                                            <div class="text-caption text--secondary mb-1">{{ $t('issue_field_path') }}</div>
                                                            <code>{{ issue.field_path }}</code>
                                                        </div>
                                                    </v-col>
                                                </v-row>

                                                <!-- Current / Suggested -->
                                                <v-row v-if="hasAnyMetadata || editMode" class="mt-3">
                                                    <v-col cols="12" md="6">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon left small>mdi-file-document-outline</v-icon>
                                                            {{ $t('issue_current_value') }}
                                                        </div>
                                                        <v-textarea
                                                            v-if="editMode"
                                                            v-model="currentMetadataText"
                                                            outlined
                                                            rows="8"
                                                            :error-messages="errors.current_metadata"
                                                            style="max-height: 250px; overflow-y: auto;"
                                                        ></v-textarea>
                                                        <pre v-else style="background-color: #f5f5f5; padding: 12px; border-radius: 4px; overflow: auto; font-size: 12px; line-height: 1.5; max-height: 250px;">{{ formatMetadata(issue.current_metadata) }}</pre>
                                                    </v-col>
                                                    <v-col cols="12" md="6">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon left small color="primary">mdi-file-document-edit-outline</v-icon>
                                                            {{ $t('issue_suggested_value') }}
                                                        </div>
                                                        <v-textarea
                                                            v-if="editMode"
                                                            v-model="suggestedMetadataText"
                                                            outlined
                                                            rows="8"
                                                            :error-messages="errors.suggested_metadata"
                                                            style="max-height: 250px; overflow-y: auto;"
                                                        ></v-textarea>
                                                        <pre v-else style="background-color: #f5f5f5; padding: 12px; border-radius: 4px; overflow: auto; font-size: 12px; line-height: 1.5; max-height: 250px;">{{ formatMetadata(issue.suggested_metadata) }}</pre>
                                                    </v-col>
                                                </v-row>

                                                <!-- Diff -->
                                                <v-row v-if="hasAnyMetadata || editMode" class="mt-3">
                                                    <v-col cols="12">
                                                        <div class="text-subtitle-2 mb-2">
                                                            <v-icon left small>mdi-compare</v-icon>
                                                            {{ $t('issue_diff') }}
                                                        </div>
                                                        <div ref="metadataDiffContainer" style="min-height: 120px; max-height: 400px; overflow: auto; background-color: #fafafa; border-radius: 4px; padding: 8px;"></div>
                                                    </v-col>
                                                </v-row>
                                            </v-expansion-panel-content>
                                        </v-expansion-panel>
                                    </v-expansion-panels>
                                </template>

                            </v-card-text>
                        </v-card>

                        <!-- Resolution -->
                        <v-card v-if="!editMode && UserHasEditAccess">
                            <v-card-title class="text-subtitle-1">{{ $t('issue_resolution') }}</v-card-title>
                            <v-card-text>
                                <div class="body-2 mb-1">{{ $t('issue_notes') }}</div>
                                <v-textarea
                                    v-model="resolutionNotes"
                                    outlined
                                    rows="2"
                                    :placeholder="$t('placeholder_notes')"
                                    hide-details
                                    class="mb-4"
                                ></v-textarea>

                                <!-- Open -->
                                <template v-if="issue.status === 'open'">
                                    <v-btn v-if="canApply" color="success" class="mr-2 mb-2" @click="applyWithNotes" :loading="applying">
                                        <v-icon left small>mdi-check-circle</v-icon>
                                        {{ $t('action_apply_to_field') }}
                                    </v-btn>
                                    <v-btn outlined color="success" class="mr-2 mb-2" @click="resolve('accepted')" :loading="saving">
                                        <v-icon left small>mdi-check</v-icon>
                                        {{ $t('action_accept') }}
                                    </v-btn>
                                    <v-btn outlined color="success" class="mr-2 mb-2" @click="resolve('fixed')" :loading="saving">
                                        <v-icon left small>mdi-wrench</v-icon>
                                        {{ $t('action_mark_fixed') }}
                                    </v-btn>
                                    <v-btn outlined color="error" class="mr-2 mb-2" @click="resolve('rejected')" :loading="saving">
                                        <v-icon left small>mdi-close</v-icon>
                                        {{ $t('action_reject') }}
                                    </v-btn>
                                    <v-btn outlined class="mr-2 mb-2" @click="resolve('dismissed')" :loading="saving">
                                        <v-icon left small>mdi-minus-circle</v-icon>
                                        {{ $t('action_dismiss') }}
                                    </v-btn>
                                    <v-btn outlined class="mr-2 mb-2" @click="resolve('false_positive')" :loading="saving">
                                        <v-icon left small>mdi-alert-remove</v-icon>
                                        {{ $t('action_false_positive') }}
                                    </v-btn>
                                </template>

                                <!-- Accepted -->
                                <template v-else-if="issue.status === 'accepted'">
                                    <v-btn v-if="canApply" color="success" class="mr-2 mb-2" @click="applyWithNotes" :loading="applying">
                                        <v-icon left small>mdi-check-circle</v-icon>
                                        {{ $t('action_apply_to_field') }}
                                    </v-btn>
                                    <v-btn outlined color="success" class="mr-2 mb-2" @click="resolve('fixed')" :loading="saving">
                                        <v-icon left small>mdi-wrench</v-icon>
                                        {{ $t('action_mark_fixed') }}
                                    </v-btn>
                                    <v-btn outlined color="error" class="mr-2 mb-2" @click="resolve('rejected')" :loading="saving">
                                        <v-icon left small>mdi-close</v-icon>
                                        {{ $t('action_reject') }}
                                    </v-btn>
                                    <v-btn outlined class="mr-2 mb-2" @click="resolve('dismissed')" :loading="saving">
                                        <v-icon left small>mdi-minus-circle</v-icon>
                                        {{ $t('action_dismiss') }}
                                    </v-btn>
                                    <v-btn outlined class="mr-2 mb-2" @click="resolve('open')" :loading="saving">
                                        <v-icon left small>mdi-refresh</v-icon>
                                        {{ $t('action_reopen') }}
                                    </v-btn>
                                </template>

                                <!-- Closed -->
                                <template v-else>
                                    <v-btn outlined class="mr-2 mb-2" @click="resolve('open')" :loading="saving">
                                        <v-icon left small>mdi-refresh</v-icon>
                                        {{ $t('action_reopen') }}
                                    </v-btn>
                                </template>
                            </v-card-text>
                        </v-card>

                    </v-col>

                    <!-- Sidebar -->
                    <v-col cols="12" md="4">
                        <v-card>
                            <v-card-text class="pa-4">

                                <!-- Status -->
                                <div class="text-caption text--secondary mb-1">{{ $t('issue_status') }}</div>
                                <div class="mb-4">
                                    <v-select
                                        v-if="editMode"
                                        v-model="editedIssue.status"
                                        :items="statusOptions"
                                        outlined
                                        dense
                                        hide-details
                                    ></v-select>
                                    <issue-status-badge v-else :status="issue.status"></issue-status-badge>
                                </div>

                                <v-divider class="mb-4"></v-divider>

                                <!-- Severity -->
                                <div class="text-caption text--secondary mb-1">{{ $t('issue_severity') }}</div>
                                <div class="mb-4">
                                    <v-select
                                        v-if="editMode"
                                        v-model="editedIssue.severity"
                                        :items="severityOptions"
                                        outlined
                                        dense
                                        hide-details
                                        clearable
                                    ></v-select>
                                    <div v-else>
                                        <v-chip v-if="issue.severity" small :color="issue.severity === 'critical' ? 'error' : issue.severity === 'high' ? 'warning' : 'default'">
                                            {{ getSeverityLabel(issue.severity) }}
                                        </v-chip>
                                        <span v-else class="text--secondary text-caption">—</span>
                                    </div>
                                </div>

                                <v-divider class="mb-4"></v-divider>

                                <!-- Category -->
                                <div class="text-caption text--secondary mb-1">{{ $t('issue_category') }}</div>
                                <div class="mb-4">
                                    <v-select
                                        v-if="editMode"
                                        v-model="editedIssue.category"
                                        :items="categoryOptions"
                                        item-text="text"
                                        item-value="value"
                                        outlined
                                        dense
                                        hide-details
                                        clearable
                                    ></v-select>
                                    <span v-else class="body-2">{{ getCategoryLabel(issue.category) || '—' }}</span>
                                </div>

                                <v-divider class="mb-4"></v-divider>

                                <!-- Notes -->
                                <div class="text-caption text--secondary mb-1">{{ $t('issue_notes') }}</div>
                                <div class="mb-4">
                                    <v-textarea
                                        v-if="editMode"
                                        v-model="editedIssue.notes"
                                        outlined
                                        rows="3"
                                        hide-details
                                    ></v-textarea>
                                    <div v-else-if="issue.notes" class="body-2" style="white-space: pre-wrap;">{{ issue.notes }}</div>
                                    <span v-else class="text--secondary text-caption">—</span>
                                </div>

                                <v-divider class="mb-3"></v-divider>

                                <!-- Activity -->
                                <div class="text-caption text--secondary">
                                    <div v-if="issue.created" class="mb-1">
                                        Created {{ formatDate(issue.created) }}<span v-if="issue.created_by_username"> by {{ issue.created_by_username }}</span>
                                    </div>
                                    <div v-if="issue.assigned_to_username" class="mb-1">
                                        Assigned to {{ issue.assigned_to_username }}
                                    </div>
                                    <div v-if="issue.resolved" class="mb-1">
                                        Resolved {{ formatDate(issue.resolved) }}<span v-if="issue.resolved_by_username"> by {{ issue.resolved_by_username }}</span>
                                    </div>
                                    <div v-if="issue.applied_on" class="mb-1">
                                        Applied {{ formatDate(issue.applied_on) }}<span v-if="issue.applied_by_username"> by {{ issue.applied_by_username }}</span>
                                    </div>
                                </div>

                            </v-card-text>
                        </v-card>
                    </v-col>

                </v-row>
            </v-container>
        </div>
    `
});
