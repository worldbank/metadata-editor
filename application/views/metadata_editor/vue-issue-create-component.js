/**
 * Issue Create Page Component
 * 
 * Full page for creating new issues
 */
const VueIssueCreate = Vue.component('issue-create', {
    props: {
        projectId: {
            type: Number,
            default: null
        }
    },
    data() {
        return {
            loading: false,
            saving: false,
            newIssue: {
                project_id: null,
                title: '',
                description: '',
                category: '',
                field_path: '',
                severity: 'medium',
                current_metadata: {},
                suggested_metadata: {},
                source: 'manual'
            },
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
            ]
        };
    },
    computed: {
        ProjectID() {
            return this.projectId || this.$root.dataset_id;
        },
        isValid() {
            return this.newIssue.title && this.newIssue.title.trim().length > 0 &&
                   this.newIssue.description && this.newIssue.description.trim().length > 0;
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
        this.newIssue.project_id = this.ProjectID;
    },
    watch: {
        currentMetadataText(val) {
            this.parseMetadataText('current', val);
        },
        suggestedMetadataText(val) {
            this.parseMetadataText('suggested', val);
        },
        'newIssue.field_path'(newPath) {
            // Auto-populate current metadata when field path is selected
            if (newPath && this.$store.state.formData) {
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
        parseMetadataText(type, text) {
            if (!text || text.trim() === '') {
                if (type === 'current') {
                    this.newIssue.current_metadata = {};
                } else {
                    this.newIssue.suggested_metadata = {};
                }
                return;
            }

            try {
                const parsed = JSON.parse(text);
                if (type === 'current') {
                    this.newIssue.current_metadata = parsed;
                } else {
                    this.newIssue.suggested_metadata = parsed;
                }
                this.errors[type + '_metadata'] = null;
            } catch (e) {
                // If not JSON, store as plain value
                if (type === 'current') {
                    this.newIssue.current_metadata = text;
                } else {
                    this.newIssue.suggested_metadata = text;
                }
                this.errors[type + '_metadata'] = null;
            }
        },
        async createIssue() {
            if (!this.isValid) {
                EventBus.$emit('onFail', this.$t('validation_required_fields'));
                return;
            }

            this.saving = true;
            try {
                const url = CI.base_url + '/api/issues';
                const response = await axios.post(url, this.newIssue);

                if (response.data.status === 'success') {
                    EventBus.$emit('onSuccess', this.$t('issue_created'));
                    // Navigate back to issues list
                    this.$router.push('/issues');
                } else {
                    throw new Error(response.data.message || this.$t('error_create_issue'));
                }
            } catch (error) {
                console.error('Error creating issue:', error);
                EventBus.$emit(
                    'onFail',
                    error.response?.data?.message || error.message || this.$t('error_create_issue')
                );
            } finally {
                this.saving = false;
            }
        },
        useSimpleFormat() {
            // Helper is no longer needed since we store values directly
            // This can be removed or repurposed
        },
        cancel() {
            this.$router.push('/issues');
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
        }
    },
    template: `
        <div class="issue-create-page">
            <v-container fluid style="max-width:100%!important;" class="mt-4">
                <v-row>
                    <v-col cols="12">
                        <!-- Page Header -->
                        <div class="d-flex align-center mb-4">
                            <v-btn icon @click="cancel" class="mr-3">
                                <v-icon>mdi-arrow-left</v-icon>
                            </v-btn>
                            <h2 class="text-h5">
                                <v-icon left color="primary">mdi-plus-circle</v-icon>
                                {{ $t('create_issue') }}
                            </h2>
                            <v-spacer></v-spacer>
                            <v-btn
                                color="primary"
                                @click="createIssue"
                                :loading="saving"
                                :disabled="!isValid"
                            >
                                <v-icon left>mdi-content-save</v-icon>
                                {{ $t('action_create_issue') }}
                            </v-btn>
                        </div>

                        <!-- Form Card -->
                        <v-card>
                            <v-card-text class="pa-6">

                                <!-- Title -->
                                <v-row dense>
                                    <v-col cols="12">
                                        <div class="body-2 mb-1">{{ $t('issue_title') }} <span class="error--text">*</span></div>
                                        <v-text-field
                                            v-model="newIssue.title"
                                            outlined
                                            dense
                                            :placeholder="$t('placeholder_issue_title')"
                                            hide-details="auto"
                                            counter="255"
                                        ></v-text-field>
                                    </v-col>
                                </v-row>

                                <!-- Description -->
                                <v-row dense class="mt-4">
                                    <v-col cols="12">
                                        <div class="body-2 mb-1">{{ $t('issue_description') }} <span class="error--text">*</span></div>
                                        <v-textarea
                                            v-model="newIssue.description"
                                            outlined
                                            rows="3"
                                            :placeholder="$t('placeholder_issue_description')"
                                            hide-details="auto"
                                        ></v-textarea>
                                    </v-col>
                                </v-row>

                                <!-- Category and Severity -->
                                <v-row dense class="mt-4">
                                    <v-col cols="12" md="6">
                                        <div class="body-2 mb-1">{{ $t('issue_category') }}</div>
                                        <v-select
                                            v-model="newIssue.category"
                                            :items="categoryOptions"
                                            item-text="text"
                                            item-value="value"
                                            outlined
                                            dense
                                            :placeholder="$t('placeholder_issue_category')"
                                            hide-details="auto"
                                            clearable
                                        ></v-select>
                                    </v-col>
                                    <v-col cols="12" md="6">
                                        <div class="body-2 mb-1">{{ $t('issue_severity') }}</div>
                                        <v-select
                                            v-model="newIssue.severity"
                                            :items="severityOptions"
                                            outlined
                                            dense
                                            hide-details="auto"
                                        ></v-select>
                                    </v-col>
                                </v-row>

                                <!-- Field Path -->
                                <v-row dense class="mt-4">
                                    <v-col cols="12">
                                        <div class="body-2 mb-1">{{ $t('issue_field_path') }}</div>
                                        <v-autocomplete
                                            v-model="newIssue.field_path"
                                            :items="fieldPathOptions"
                                            item-text="text"
                                            item-value="value"
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
                                    </v-col>
                                </v-row>

                                <!-- Current & Suggested Values (shown when field path is set) -->
                                <template v-if="newIssue.field_path">
                                    <v-row dense class="mt-4">
                                        <v-col cols="12" md="6">
                                            <div class="body-2 mb-1">{{ $t('issue_current_value') }}</div>
                                            <v-textarea
                                                v-model="currentMetadataText"
                                                outlined
                                                rows="4"
                                                :placeholder="$t('placeholder_issue_current')"
                                                hide-details="auto"
                                                :error-messages="errors.current_metadata"
                                            ></v-textarea>
                                        </v-col>
                                        <v-col cols="12" md="6">
                                            <div class="body-2 mb-1">{{ $t('issue_suggested_value') }}</div>
                                            <v-textarea
                                                v-model="suggestedMetadataText"
                                                outlined
                                                rows="4"
                                                :placeholder="$t('placeholder_issue_suggested')"
                                                hide-details="auto"
                                                :error-messages="errors.suggested_metadata"
                                            ></v-textarea>
                                        </v-col>
                                    </v-row>
                                </template>


                            </v-card-text>

                            <v-card-actions class="pa-6 pt-2">
                                <v-spacer></v-spacer>
                                <v-btn text @click="cancel" class="mr-2">{{ $t('action_cancel') }}</v-btn>
                                <v-btn
                                    color="primary"
                                    @click="createIssue"
                                    :loading="saving"
                                    :disabled="!isValid"
                                >
                                    <v-icon left>mdi-content-save</v-icon>
                                    {{ $t('action_create_issue') }}
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </div>
    `
});
