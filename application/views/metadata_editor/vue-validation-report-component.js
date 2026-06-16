Vue.component('validation-report', {
    data: function () {
        return {
            loading_schema: false,
            loading_template: false,
            loading_extra_fields: false,
            loading_variables: false,
            project_id: project_sid,
            schema_validation: null,
            template_validation: null,
            extra_fields: null,
            variables_validation: null,
            selected_extra_fields: [],
            processing_fields: false,
            error: null,
            show_confirm_remove: false,
            fields_to_remove: [],
            expanded_previews: {} // Track which rows are expanded for preview
        }
    },
    computed: {
        ProjectID() {
            return this.$store.state.project_id || this.project_id;
        },
        ProjectMetadata() {
            return this.$store.state.formData;
        },
        hasSchemaIssues() {
            return this.schema_validation && !this.schema_validation.valid;
        },
        schemaIssuesCount() {
            return this.schema_validation && this.schema_validation.issues ? this.schema_validation.issues.length : 0;
        },
        arrayAsObjectIssues() {
            if (!this.schema_validation || !this.schema_validation.issues) {
                return [];
            }
            return this.schema_validation.issues.filter(issue => issue.type === 'array_as_object');
        },
        hasArrayAsObjectIssues() {
            return this.arrayAsObjectIssues.length > 0;
        },
        arrayAsObjectIssuesCount() {
            return this.arrayAsObjectIssues.length;
        },
        fixableTypeMismatchIssues() {
            if (!this.schema_validation || !this.schema_validation.issues) {
                return [];
            }
            return this.schema_validation.issues.filter(issue => 
                issue.type === 'type_mismatch' && issue.fixable === true
            );
        },
        hasFixableTypeMismatches() {
            return this.fixableTypeMismatchIssues.length > 0;
        },
        fixableTypeMismatchCount() {
            return this.fixableTypeMismatchIssues.length;
        },
        hasTemplateIssues() {
            return this.template_validation && !this.template_validation.valid;
        },
        templateIssuesCount() {
            return this.template_validation && this.template_validation.issues ? this.template_validation.issues.length : 0;
        },
        templateValidationReport() {
            // Only return fields that were actually validated (valid or invalid), exclude skipped
            if (this.template_validation && this.template_validation.validation_report) {
                return this.template_validation.validation_report.filter(item => 
                    item.status === 'valid' || item.status === 'invalid'
                );
            }
            return [];
        },
        templateValidFields() {
            return this.templateValidationReport.filter(item => item.status === 'valid');
        },
        templateInvalidFields() {
            return this.templateValidationReport.filter(item => item.status === 'invalid');
        },
        hasExtraFields() {
            return this.extra_fields && this.extra_fields.extra_fields && this.extra_fields.extra_fields.length > 0;
        },
        extraFieldsCount() {
            return this.extra_fields && this.extra_fields.extra_fields ? this.extra_fields.extra_fields.length : 0;
        },
        loading() {
            return this.loading_schema || this.loading_template || this.loading_extra_fields || this.loading_variables || this.processing_fields;
        },
        hasVariablesIssues() {
            return this.variables_validation && !this.variables_validation.valid;
        },
        variablesIssuesCount() {
            return this.variables_validation && this.variables_validation.issues ? this.variables_validation.issues.length : 0;
        },
        variablesIssuesDisplay() {
            // Return only first 50 issues for display
            if (this.variables_validation && this.variables_validation.issues) {
                return this.variables_validation.issues.slice(0, 50);
            }
            return [];
        },
        hasMoreVariablesErrors() {
            return this.variables_validation && this.variables_validation.has_more_errors === true;
        },
        isMicrodataProject() {
            // Check if project type is microdata or survey
            const projectType = this.$store.state.project_type || this.$store.state.formData?.type;
            return projectType === 'microdata' || projectType === 'survey';
        },
        allExtraFieldsSelected() {
            return this.hasExtraFields && this.selected_extra_fields.length === this.extraFieldsCount;
        },
        someExtraFieldsSelected() {
            return this.selected_extra_fields.length > 0 && this.selected_extra_fields.length < this.extraFieldsCount;
        },
        canManageExtraFields() {
            if (!this.hasExtraFields || this.processing_fields) {
                return false;
            }
            if (!this.$store.getters.getUserHasEditAccess) {
                return false;
            }
            if (this.$store.getters.getProjectIsLocked) {
                return false;
            }
            return true;
        }
    },
    mounted: function() {
        this.loadSchemaValidation();
        this.loadTemplateValidation();
        this.loadExtraFields();

        // Load variables validation only for microdata/survey projects
        if (this.isMicrodataProject) {
            this.loadVariablesValidation();
        }
    },
    methods: {
        loadSchemaValidation: function() {
            this.loading_schema = true;
            this.error = null;
            const vm = this;
            const url = CI.base_url + '/api/validation/' + vm.ProjectID + '/schema';
            
            axios.get(url)
                .then(function (response) {
                    if (response.data && response.data.status === 'success') {
                        vm.schema_validation = response.data.validation;
                    } else {
                        vm.error = 'Failed to load schema validation report';
                    }
                })
                .catch(function (error) {
                    console.error('Schema validation API error:', error);
                    vm.error = error.response && error.response.data && error.response.data.message 
                        ? error.response.data.message 
                        : 'Failed to load schema validation report';
                })
                .then(function () {
                    vm.loading_schema = false;
                });
        },
        refreshReport: function() {
            this.loadSchemaValidation();
            this.loadTemplateValidation();
            this.loadExtraFields();
            if (this.isMicrodataProject) {
                this.loadVariablesValidation();
            }
        },
        loadVariablesValidation: function() {
            this.loading_variables = true;
            this.error = null;
            const vm = this;
            // Limit to 50 errors - backend will stop validation once limit is reached
            const url = CI.base_url + '/api/validation/' + vm.ProjectID + '/variables?limit=50';
            
            axios.get(url)
                .then(function (response) {
                    if (response.data && response.data.status === 'success') {
                        vm.variables_validation = response.data.validation;
                    } else {
                        vm.error = 'Failed to load variables validation report';
                    }
                })
                .catch(function (error) {
                    console.error('Variables validation API error:', error);
                    // Don't show error if project is not microdata type (400 error is expected)
                    if (error.response && error.response.status !== 400) {
                        vm.error = error.response && error.response.data && error.response.data.message 
                            ? error.response.data.message 
                            : 'Failed to load variables validation report';
                    }
                })
                .then(function () {
                    vm.loading_variables = false;
                });
        },
        loadTemplateValidation: function() {
            this.loading_template = true;
            this.error = null;
            const vm = this;
            const url = CI.base_url + '/api/validation/' + vm.ProjectID + '/template';
            
            axios.get(url)
                .then(function (response) {
                    if (response.data && response.data.status === 'success') {
                        vm.template_validation = response.data.validation;
                    } else {
                        vm.error = 'Failed to load template validation report';
                    }
                })
                .catch(function (error) {
                    console.error('Template validation API error:', error);
                    vm.error = error.response && error.response.data && error.response.data.message 
                        ? error.response.data.message 
                        : 'Failed to load template validation report';
                })
                .then(function () {
                    vm.loading_template = false;
                });
        },
        loadExtraFields: function() {
            this.loading_extra_fields = true;
            this.error = null;
            const vm = this;
            const url = CI.base_url + '/api/validation/' + vm.ProjectID + '/extra_fields';
            
            axios.get(url)
                .then(function (response) {
                    if (response.data && response.data.status === 'success') {
                        vm.extra_fields = response.data.result;
                    } else {
                        vm.error = 'Failed to load extra fields report';
                    }
                })
                .catch(function (error) {
                    console.error('Extra fields API error:', error);
                    vm.error = error.response && error.response.data && error.response.data.message 
                        ? error.response.data.message 
                        : 'Failed to load extra fields report';
                })
                .then(function () {
                    vm.loading_extra_fields = false;
                });
        },
        getHelpUrl: function(errorType) {
            // Map error types to help page anchors
            const typeMap = {
                'type_mismatch': '/help/errors#type_mismatch',
                'array_as_object': '/help/errors#array_as_object',
                'validation_error': '/help/errors#validation_error'
            };
            return typeMap[errorType] || '/help/errors';
        },
        navigateToHelp: function(errorType) {
            this.$router.push(this.getHelpUrl(errorType));
        },
        navigateToDatafile: function(fileId) {
            if (!fileId) {
                return;
            }
            
            // Navigate to variables page for the file
            if (this.$router) {
                this.$router.push('/variables/' + fileId);
            }
        },
        navigateToField: function(path) {
            if (!path) {
                return;
            }
            
            // Convert JSON Pointer path to dot notation for editor navigation
            // Remove leading slash and replace slashes with dots
            let dotPath = path.replace(/^\//, '').replace(/\//g, '.');
            
            // Handle array indices in paths - editor cannot navigate directly to array elements
            // We need to find the parent field before the first array index
            // Examples:
            //   study_desc.data_access.dataset_use.contact.uri[0] -> study_desc.data_access.dataset_use.contact
            //   study_desc.some_element[0].dataset_use.contact.uri[0] -> study_desc.some_element
            
            // Find the first occurrence of array index pattern [number]
            const arrayIndexMatch = dotPath.match(/\[\d+\]/);
            if (arrayIndexMatch) {
                // Get everything before the first array index
                const indexPos = dotPath.indexOf(arrayIndexMatch[0]);
                dotPath = dotPath.substring(0, indexPos);
                
                // If the path ends with a field name followed by a dot, remove the last field segment
                // For example: "contact.uri[0]" -> "contact.uri" -> should be "contact" (parent)
                // But for "some_element[0]" -> "some_element" (already the parent)
                
                // If there's still content after removing array index, check if we need to go up one level
                // For paths ending with a field before array index like "contact.uri[0]",
                // we want "contact" not "contact.uri"
                // Find the last dot before the end
                const lastDotIndex = dotPath.lastIndexOf('.');
                if (lastDotIndex !== -1) {
                    // Get parent by removing the last segment after the last dot
                    dotPath = dotPath.substring(0, lastDotIndex);
                }
                // If no dot found, dotPath is already the parent field (e.g., "some_element")
            }
            
            // Clean up: remove trailing dots if any
            dotPath = dotPath.replace(/\.$/, '');
            
            // Check if this is a variable path (starts with "variables" or contains variable-specific patterns)
            // Variable paths typically look like: /variables/{fid}/...
            if (path.startsWith('/variables/') || dotPath.startsWith('variables.')) {
                // Extract datafile ID from path
                // Path format: /variables/{fid}/... or variables.{fid}...
                const parts = dotPath.split('.');
                if (parts.length >= 2 && parts[0] === 'variables') {
                    const datafileId = parts[1];
                    // Navigate to variables page using Vue Router
                    if (this.$router) {
                        this.$router.push('/variables/' + datafileId);
                    }
                }
            } else {
                // For study level metadata, use format: /study/{element-path}
                // e.g., /study/study_desc.title_statement
                if (this.$router) {
                    this.$router.push('/study/' + dotPath);
                }
            }
        },
        toggleExtraField: function(path) {
            const index = this.selected_extra_fields.indexOf(path);
            if (index > -1) {
                this.selected_extra_fields.splice(index, 1);
            } else {
                this.selected_extra_fields.push(path);
            }
        },
        selectAllExtraFields: function() {
            if (this.allExtraFieldsSelected) {
                this.selected_extra_fields = [];
            } else {
                this.selected_extra_fields = this.extra_fields.extra_fields.map(field => field.path);
            }
        },
        /**
         * Convert JSON Pointer path to dot notation for lodash
         * @param {string} path JSON Pointer path (e.g., /path/to/field)
         * @returns {string} Dot notation path (e.g., path.to.field)
         */
        jsonPointerToDot: function(path) {
            // Remove leading slash
            path = path.replace(/^\//, '');
            // Replace slashes with dots
            return path.replace(/\//g, '.');
        },
        /**
         * True if value is null, undefined, empty plain object, or empty array.
         */
        isVacantMetadataSlot: function(value) {
            if (value === null || value === undefined) {
                return true;
            }
            if (typeof value !== 'object') {
                return false;
            }
            if (Array.isArray(value)) {
                return value.length === 0;
            }
            return Object.keys(value).length === 0;
        },
        /**
         * Remove null, undefined, empty {}, and empty [] from an array in place (Vue 2–friendly).
         */
        pruneVacantArraySlots: function(arr) {
            if (!Array.isArray(arr)) {
                return 0;
            }
            let removed = 0;
            for (let i = arr.length - 1; i >= 0; i--) {
                if (this.isVacantMetadataSlot(arr[i])) {
                    arr.splice(i, 1);
                    removed++;
                }
            }
            return removed;
        },
        /**
         * After unset/splice on a JSON Pointer: compact arrays at each numeric index in the path, then remove
         * empty {} ancestors. Arrays are chosen by prefix-before-numeric (e.g. /a/0/b/1 → compact /a and /a/0/b).
         */
        cleanupPathAfterExtraFieldRemoval: function(metadata, pointer) {
            if (!metadata || !pointer) {
                return;
            }
            const p = pointer.charAt(0) === '/' ? pointer : '/' + pointer;
            const segs = p.replace(/^\//, '').split('/').filter(function (s) {
                return s.length > 0;
            });
            if (segs.length === 0) {
                return;
            }
            const vm = this;

            function compactArraysOnPath() {
                for (let i = 0; i < segs.length; i++) {
                    if (!/^\d+$/.test(segs[i])) {
                        continue;
                    }
                    const parentPath = '/' + segs.slice(0, i).join('/');
                    const arr = vm.getValueByPath(metadata, parentPath);
                    if (Array.isArray(arr)) {
                        vm.pruneVacantArraySlots(arr);
                    }
                }
            }

            compactArraysOnPath();
            compactArraysOnPath();

            for (let d = segs.length - 2; d >= 0; d--) {
                const nodePath = '/' + segs.slice(0, d + 1).join('/');
                const node = vm.getValueByPath(metadata, nodePath);
                if (node === null || node === undefined) {
                    continue;
                }
                if (typeof node !== 'object' || Array.isArray(node)) {
                    continue;
                }
                if (Object.keys(node).length > 0) {
                    continue;
                }

                if (d === 0) {
                    vm.$delete(metadata, segs[0]);
                    break;
                }
                const parentPath = '/' + segs.slice(0, d).join('/');
                const parent = vm.getValueByPath(metadata, parentPath);
                const key = segs[d];
                if (parent == null) {
                    break;
                }
                if (Array.isArray(parent) && /^\d+$/.test(key)) {
                    const idx = parseInt(key, 10);
                    if (idx >= 0 && idx < parent.length) {
                        parent.splice(idx, 1);
                    }
                } else if (typeof parent === 'object' && !Array.isArray(parent)) {
                    vm.$delete(parent, key);
                }
                compactArraysOnPath();
            }
        },
        /**
         * Run path cleanup for each removed/moved extra field pointer.
         */
        cleanupAfterExtraFieldRemovals: function(metadata, pointers) {
            if (!metadata || !pointers || pointers.length === 0) {
                return;
            }
            const seen = {};
            const vm = this;
            pointers.forEach(function (ptr) {
                if (!ptr || seen[ptr]) {
                    return;
                }
                seen[ptr] = true;
                vm.cleanupPathAfterExtraFieldRemoval(metadata, ptr);
            });
        },
        /**
         * Get value from metadata using JSON Pointer path
         * @param {object} data Metadata object
         * @param {string} path JSON Pointer path
         * @returns {*} Value or null
         */
        getValueByPath: function(data, path) {
            // Remove leading slash
            path = path.replace(/^\//, '');
            if (!path) {
                return data;
            }

            const parts = path.split('/');
            let current = data;

            for (let i = 0; i < parts.length; i++) {
                const part = parts[i];
                if (current === null || current === undefined) {
                    return null;
                }

                if (/^\d+$/.test(part)) {
                    const index = parseInt(part, 10);
                    if (Array.isArray(current) && index >= 0 && index < current.length) {
                        current = current[index];
                    } else {
                        return null;
                    }
                } else {
                    if (typeof current === 'object' && current !== null && part in current) {
                        current = current[part];
                    } else {
                        return null;
                    }
                }
            }

            return current;
        },
        /**
         * When the leaf is null/undefined (e.g. sparse array hole or explicit null after bad unset),
         * remove that slot: splice for array index, $delete for object key. Returns true if something changed.
         */
        removeVacantSlotAtPointer: function(metadata, pointer) {
            const p = pointer && (pointer.charAt(0) === '/' ? pointer : '/' + pointer);
            if (!metadata || !p) {
                return false;
            }
            const segs = p.replace(/^\//, '').split('/').filter(function (s) {
                return s.length > 0;
            });
            if (segs.length === 0) {
                return false;
            }
            const last = segs[segs.length - 1];
            const parentPath = '/' + segs.slice(0, -1).join('/');
            const parent = this.getValueByPath(metadata, parentPath);
            if (Array.isArray(parent) && /^\d+$/.test(last)) {
                const idx = parseInt(last, 10);
                if (idx >= 0 && idx < parent.length) {
                    parent.splice(idx, 1);
                    return true;
                }
                return false;
            }
            if (parent && typeof parent === 'object' && !Array.isArray(parent) && Object.prototype.hasOwnProperty.call(parent, last)) {
                this.$delete(parent, last);
                return true;
            }
            return false;
        },
        /**
         * Create additional key from JSON Pointer path
         * @param {string} path JSON Pointer path
         * @returns {string} Dot notation key for additional section
         */
        createAdditionalKey: function(path) {
            // Remove leading slash
            path = path.replace(/^\//, '');
            // Replace slashes with dots
            return path.replace(/\//g, '.');
        },
        /**
         * Mark form as dirty to trigger save button
         */
        markFormDirty: function() {
            // Access parent component's is_dirty property
            if (this.$parent && typeof this.$parent.is_dirty !== 'undefined') {
                this.$parent.is_dirty = true;
            }
            // Also trigger Vue reactivity by updating the store
            this.$store.state.formData = Object.assign({}, this.$store.state.formData);
        },
        moveToAdditional: function() {
            if (this.selected_extra_fields.length === 0) {
                this.error = 'Please select at least one field to move';
                return;
            }

            this.processing_fields = true;
            this.error = null;
            const vm = this;
            const metadata = vm.ProjectMetadata;
            const paths_to_process = [...vm.selected_extra_fields]; // Store paths before processing
            let moved_count = 0;
            const errors = [];

            try {
                const removal_paths = [];
                paths_to_process.forEach(function(path) {
                    try {
                        const value = vm.getValueByPath(metadata, path);

                        if (value !== null && value !== undefined) {
                            const additional_key = vm.createAdditionalKey(path);

                            if (!metadata.additional) {
                                vm.$set(metadata, 'additional', {});
                            }

                            _.set(metadata.additional, additional_key, value);

                            const dot_path = vm.jsonPointerToDot(path);
                            _.unset(metadata, dot_path);
                            removal_paths.push(path);

                            moved_count++;
                        } else if (vm.removeVacantSlotAtPointer(metadata, path)) {
                            removal_paths.push(path);
                            moved_count++;
                        }
                    } catch(e) {
                        console.error('Error moving field:', path, e);
                        errors.push({ path: path, error: e.message });
                    }
                });
                vm.cleanupAfterExtraFieldRemovals(metadata, removal_paths);

                // Mark form as dirty
                vm.markFormDirty();
                
                // Remove processed fields from extra_fields list (since they're now in additional)
                if (vm.extra_fields && vm.extra_fields.extra_fields) {
                    vm.extra_fields.extra_fields = vm.extra_fields.extra_fields.filter(
                        field => !paths_to_process.includes(field.path)
                    );
                }
                
                // Clear selection
                vm.selected_extra_fields = [];
                
                if (errors.length > 0) {
                    vm.error = `Moved ${moved_count} field(s), but ${errors.length} error(s) occurred.`;
                }
            } catch(e) {
                console.error('Error in moveToAdditional:', e);
                vm.error = 'Failed to move fields: ' + e.message;
            } finally {
                vm.processing_fields = false;
            }
        },
        confirmRemoveFields: function() {
            if (this.selected_extra_fields.length === 0) {
                this.error = 'Please select at least one field to remove';
                return;
            }

            this.fields_to_remove = [...this.selected_extra_fields];
            this.show_confirm_remove = true;
        },
        removeFields: function() {
            this.show_confirm_remove = false;
            const paths = [...this.fields_to_remove];
            this.fields_to_remove = [];

            this.processing_fields = true;
            this.error = null;
            const vm = this;
            const metadata = vm.ProjectMetadata;
            let removed_count = 0;
            const errors = [];

            try {
                const removed_paths = [];
                paths.forEach(function(path) {
                    try {
                        const value = vm.getValueByPath(metadata, path);

                        if (value !== null && value !== undefined) {
                            const dot_path = vm.jsonPointerToDot(path);
                            _.unset(metadata, dot_path);
                            removed_paths.push(path);
                            removed_count++;
                        } else if (vm.removeVacantSlotAtPointer(metadata, path)) {
                            removed_paths.push(path);
                            removed_count++;
                        }
                    } catch(e) {
                        console.error('Error removing field:', path, e);
                        errors.push({ path: path, error: e.message });
                    }
                });
                vm.cleanupAfterExtraFieldRemovals(metadata, removed_paths);

                // Mark form as dirty
                vm.markFormDirty();
                
                // Remove processed fields from extra_fields list
                if (vm.extra_fields && vm.extra_fields.extra_fields) {
                    vm.extra_fields.extra_fields = vm.extra_fields.extra_fields.filter(
                        field => !paths.includes(field.path)
                    );
                }
                
                // Clear selection
                vm.selected_extra_fields = [];
                
                if (errors.length > 0) {
                    vm.error = `Removed ${removed_count} field(s), but ${errors.length} error(s) occurred.`;
                }
            } catch(e) {
                console.error('Error in removeFields:', e);
                vm.error = 'Failed to remove fields: ' + e.message;
            } finally {
                vm.processing_fields = false;
            }
        },
        cancelRemoveFields: function() {
            this.show_confirm_remove = false;
            this.fields_to_remove = [];
        },
        confirmRemoveSingleField: function(path) {
            if (!path) {
                return;
            }
            this.fields_to_remove = [path];
            this.show_confirm_remove = true;
        },
        /**
         * Check if an object/array has numeric keys that indicate it should be an array
         * @param {*} value Value to check
         * @returns {boolean} True if value is an object/associative array with numeric keys
         */
        isObjectWithNumericKeys: function(value) {
            if (value === null || value === undefined) {
                return false;
            }
            if (typeof value !== 'object') {
                return false;
            }
            
            // Convert to array if needed
            const arr = Array.isArray(value) ? value : Object.values(value);
            const keys = Object.keys(value);
            
            if (keys.length === 0) {
                return false;
            }
            
            // Check if all keys are numeric (as strings or integers)
            let allNumeric = true;
            const numericKeys = [];
            
            for (let i = 0; i < keys.length; i++) {
                const key = keys[i];
                if (/^\d+$/.test(String(key))) {
                    numericKeys.push(parseInt(key, 10));
                } else {
                    allNumeric = false;
                    break;
                }
            }
            
            if (!allNumeric || numericKeys.length === 0) {
                return false;
            }
            
            // Check if keys are sequential
            numericKeys.sort((a, b) => a - b);
            const start = numericKeys[0];
            for (let i = 0; i < numericKeys.length; i++) {
                if (numericKeys[i] !== start + i) {
                    return false;
                }
            }
            
            return true;
        },
        /**
         * Convert an object with numeric keys to an array
         * @param {*} value Value to convert
         * @returns {Array} Converted array
         */
        convertObjectToArray: function(value) {
            if (value === null || value === undefined || typeof value !== 'object') {
                return value;
            }
            
            // Get keys and sort them
            const keys = Object.keys(value);
            const numericKeys = [];
            
            for (let i = 0; i < keys.length; i++) {
                const key = keys[i];
                if (/^\d+$/.test(String(key))) {
                    numericKeys.push(parseInt(key, 10));
                }
            }
            
            // Sort numeric keys
            numericKeys.sort((a, b) => a - b);
            
            // Build new array with sequential indices
            const result = [];
            for (let i = 0; i < numericKeys.length; i++) {
                const numKey = numericKeys[i];
                result.push(value[String(numKey)]);
            }
            
            return result;
        },
        /**
         * Fix a single array-as-object issue
         * @param {string} path JSON Pointer path to the field
         */
        fixArrayAsObject: function(path) {
            if (!path) {
                return;
            }
            
            this.processing_fields = true;
            this.error = null;
            const vm = this;
            const metadata = vm.ProjectMetadata;
            
            try {
                // Get value from path
                const value = vm.getValueByPath(metadata, path);
                
                if (value !== null && value !== undefined) {
                    // Check if it's an object with numeric keys
                    if (vm.isObjectWithNumericKeys(value)) {
                        // Convert to array
                        const converted_value = vm.convertObjectToArray(value);
                        
                        // Update metadata using dot notation path
                        const dot_path = vm.jsonPointerToDot(path);
                        _.set(metadata, dot_path, converted_value);
                        
                        // Mark form as dirty
                        vm.markFormDirty();
                        
                        // Reload schema validation to reflect changes
                        vm.loadSchemaValidation();
                        
                        console.log(`Successfully fixed array-as-object issue at ${path}. Changes will be saved when you save the project.`);
                    } else {
                        vm.error = 'Field is not an object with numeric keys';
                    }
                } else {
                    vm.error = 'Field not found';
                }
            } catch(e) {
                console.error('Error in fixArrayAsObject:', e);
                vm.error = 'Failed to fix field: ' + e.message;
            } finally {
                vm.processing_fields = false;
            }
        },
        /**
         * Toggle preview expansion for an issue
         * @param {string} path JSON Pointer path to identify the issue
         */
        togglePreview: function(path) {
            // Handle both path strings and numeric indices (including 0)
            const key = path !== null && path !== undefined ? String(path) : 'default';
            this.$set(this.expanded_previews, key, !this.expanded_previews[key]);
        },
        /**
         * Check if preview is expanded for an issue
         * @param {string|number} path JSON Pointer path or index to identify the issue
         * @returns {boolean} True if preview is expanded
         */
        isPreviewExpanded: function(path) {
            // Handle both path strings and numeric indices (including 0)
            const key = path !== null && path !== undefined ? String(path) : 'default';
            return !!this.expanded_previews[key];
        },
        /**
         * Get before value (current incorrect value) for preview
         * @param {string} path JSON Pointer path to the field
         * @returns {*} Current value
         */
        getBeforeValue: function(path) {
            if (!path) {
                return null;
            }
            try {
                return this.getValueByPath(this.ProjectMetadata, path);
            } catch(e) {
                console.error('Error getting before value:', e);
                return null;
            }
        },
        /**
         * Get after value (fixed value) for preview
         * @param {string} path JSON Pointer path to the field
         * @returns {*} Fixed value
         */
        getAfterValue: function(path) {
            if (!path) {
                return null;
            }
            try {
                const beforeValue = this.getBeforeValue(path);
                if (beforeValue !== null && beforeValue !== undefined) {
                    if (this.isObjectWithNumericKeys(beforeValue)) {
                        return this.convertObjectToArray(beforeValue);
                    }
                }
                return beforeValue;
            } catch(e) {
                console.error('Error getting after value:', e);
                return null;
            }
        },
        /**
         * Get after value for type mismatch preview
         * @param {string} path JSON Pointer path to the field
         * @param {string} expectedType Expected JSON Schema type
         * @param {string} actualType Current actual type
         * @returns {*} Fixed value
         */
        getAfterValueForTypeMismatch: function(path, expectedType, actualType) {
            if (!path) {
                return null;
            }
            try {
                const beforeValue = this.getBeforeValue(path);
                return this.convertValueToType(beforeValue, expectedType, actualType);
            } catch(e) {
                console.error('Error getting after value for type mismatch:', e);
                return null;
            }
        },
        /**
         * Convert value to expected type
         * @param {*} value Current value
         * @param {string} expectedType Expected JSON Schema type
         * @param {string} actualType Current actual type
         * @returns {*} Converted value
         */
        convertValueToType: function(value, expectedType, actualType) {
            if (value === null || value === undefined) {
                // Null to type: return appropriate default
                switch (expectedType) {
                    case 'string':
                        return '';
                    case 'array':
                        return [];
                    case 'object':
                        return {};
                    case 'number':
                    case 'integer':
                        return 0;
                    case 'boolean':
                        return false;
                    default:
                        return null;
                }
            }
            
            // String to Array: wrap in array
            if (expectedType === 'array' && actualType === 'string') {
                return [value];
            }
            
            // Array to String: convert array to string
            if (expectedType === 'string' && actualType === 'array') {
                if (!Array.isArray(value) || value.length === 0) {
                    return '';
                }
                // If array has one element, use that element (convert to string if needed)
                if (value.length === 1) {
                    const first = value[0];
                    if (typeof first === 'string' || typeof first === 'number' || typeof first === 'boolean') {
                        return String(first);
                    }
                    // If element is object/array, convert to JSON string
                    return JSON.stringify(first);
                }
                // If array has multiple elements, join them (if all are strings/numbers) or use first element
                const allScalars = value.every(item => 
                    typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean'
                );
                if (allScalars) {
                    return value.join(', ');
                }
                // Otherwise, use first element converted to string
                const first = value[0];
                if (typeof first === 'string' || typeof first === 'number' || typeof first === 'boolean') {
                    return String(first);
                }
                return JSON.stringify(first);
            }
            
            // Number/Integer to String
            if (expectedType === 'string' && (actualType === 'number' || actualType === 'integer')) {
                return String(value);
            }
            
            // String to Number
            if (expectedType === 'number' && actualType === 'string') {
                if (!isNaN(value) && value !== '') {
                    return parseFloat(value);
                }
                return value; // Return as-is if not numeric
            }
            
            // String to Integer
            if (expectedType === 'integer' && actualType === 'string') {
                if (!isNaN(value) && value !== '') {
                    return parseInt(value, 10);
                }
                return value; // Return as-is if not numeric
            }
            
            // Boolean to String
            if (expectedType === 'string' && actualType === 'boolean') {
                return value ? 'true' : 'false';
            }
            
            // String to Boolean
            if (expectedType === 'boolean' && actualType === 'string') {
                const lower = String(value).toLowerCase().trim();
                if (['true', '1', 'yes', 'on'].includes(lower)) {
                    return true;
                } else if (['false', '0', 'no', 'off'].includes(lower)) {
                    return false;
                }
                return Boolean(value); // Fallback
            }
            
            return value; // Return as-is if no conversion available
        },
        /**
         * Format value as JSON string for display
         * @param {*} value Value to format
         * @returns {string} Formatted JSON string
         */
        formatValueAsJson: function(value) {
            if (value === null || value === undefined) {
                return 'null';
            }
            try {
                return JSON.stringify(value, null, 2);
            } catch(e) {
                return String(value);
            }
        },
        /**
         * Fix a single type mismatch issue
         * @param {string} path JSON Pointer path to the field
         * @param {string} expectedType Expected JSON Schema type
         * @param {string} actualType Current actual type
         */
        fixTypeMismatch: function(path, expectedType, actualType) {
            if (!path) {
                return;
            }
            
            this.processing_fields = true;
            this.error = null;
            const vm = this;
            const metadata = vm.ProjectMetadata;
            
            try {
                // Get value from path
                const value = vm.getValueByPath(metadata, path);
                
                if (value !== null && value !== undefined) {
                    // Convert to expected type
                    const converted_value = vm.convertValueToType(value, expectedType, actualType);
                    
                    // Update metadata using dot notation path
                    const dot_path = vm.jsonPointerToDot(path);
                    _.set(metadata, dot_path, converted_value);
                    
                    // Mark form as dirty
                    vm.markFormDirty();
                    
                    // Reload schema validation to reflect changes
                    vm.loadSchemaValidation();
                    
                    console.log(`Successfully fixed type mismatch at ${path} (${actualType} → ${expectedType}). Changes will be saved when you save the project.`);
                } else {
                    vm.error = 'Field not found';
                }
            } catch(e) {
                console.error('Error in fixTypeMismatch:', e);
                vm.error = 'Failed to fix field: ' + e.message;
            } finally {
                vm.processing_fields = false;
            }
        },
        /**
         * Fix all type mismatch issues
         */
        fixAllTypeMismatches: function() {
            if (!this.hasFixableTypeMismatches) {
                return;
            }
            
            this.processing_fields = true;
            this.error = null;
            const vm = this;
            let fixed_count = 0;
            const errors = [];
            
            try {
                // Fix all issues
                vm.fixableTypeMismatchIssues.forEach(function(issue) {
                    try {
                        if (issue.path && issue.expected_type && issue.actual_type) {
                            // Get value from path
                            const value = vm.getValueByPath(vm.ProjectMetadata, issue.path);
                            
                            if (value !== null && value !== undefined) {
                                // Convert to expected type
                                const converted_value = vm.convertValueToType(value, issue.expected_type, issue.actual_type);
                                
                                // Update metadata using dot notation path
                                const dot_path = vm.jsonPointerToDot(issue.path);
                                _.set(vm.ProjectMetadata, dot_path, converted_value);
                                
                                fixed_count++;
                            }
                        }
                    } catch(e) {
                        console.error('Error fixing issue:', issue.path, e);
                        errors.push({ path: issue.path, error: e.message });
                    }
                });
                
                // Mark form as dirty
                vm.markFormDirty();
                
                // Reload schema validation to reflect changes
                vm.loadSchemaValidation();
                
                if (errors.length > 0) {
                    vm.error = `Fixed ${fixed_count} issue(s), but ${errors.length} error(s) occurred.`;
                } else {
                    console.log(`Successfully fixed ${fixed_count} type mismatch issue(s). Changes will be saved when you save the project.`);
                }
            } catch(e) {
                console.error('Error in fixAllTypeMismatches:', e);
                vm.error = 'Failed to fix issues: ' + e.message;
            } finally {
                vm.processing_fields = false;
            }
        },
        /**
         * Fix all array-as-object issues
         */
        fixAllArrayAsObject: function() {
            if (!this.hasArrayAsObjectIssues) {
                return;
            }
            
            this.processing_fields = true;
            this.error = null;
            const vm = this;
            let fixed_count = 0;
            const errors = [];
            
            try {
                // Fix all issues
                vm.arrayAsObjectIssues.forEach(function(issue) {
                    try {
                        if (issue.path) {
                            // Get value from path
                            const value = vm.getValueByPath(vm.ProjectMetadata, issue.path);
                            
                            if (value !== null && value !== undefined) {
                                // Check if it's an object with numeric keys
                                if (vm.isObjectWithNumericKeys(value)) {
                                    // Convert to array
                                    const converted_value = vm.convertObjectToArray(value);
                                    
                                    // Update metadata using dot notation path
                                    const dot_path = vm.jsonPointerToDot(issue.path);
                                    _.set(vm.ProjectMetadata, dot_path, converted_value);
                                    
                                    fixed_count++;
                                }
                            }
                        }
                    } catch(e) {
                        console.error('Error fixing issue:', issue.path, e);
                        errors.push({ path: issue.path, error: e.message });
                    }
                });
                
                // Mark form as dirty
                vm.markFormDirty();
                
                // Reload schema validation to reflect changes
                vm.loadSchemaValidation();
                
                if (errors.length > 0) {
                    vm.error = `Fixed ${fixed_count} issue(s), but ${errors.length} error(s) occurred.`;
                } else {
                    console.log(`Successfully fixed ${fixed_count} array-as-object issue(s). Changes will be saved when you save the project.`);
                }
            } catch(e) {
                console.error('Error in fixAllArrayAsObject:', e);
                vm.error = 'Failed to fix issues: ' + e.message;
            } finally {
                vm.processing_fields = false;
            }
        }
    },
    template: `
        <div class="validation-report-page mt-5 p-3">
            <v-card style="background:transparent">
                <v-card-title>
                    {{$t("validation_report")}}
                    <v-spacer></v-spacer>
                    <v-btn 
                        color="primary" 
                        outlined 
                        @click="refreshReport"
                        :loading="loading"
                        :disabled="loading"
                        small
                    >
                        <v-icon left small>mdi-refresh</v-icon>
                        {{$t("refresh")}}
                    </v-btn>
                </v-card-title>
                
                <v-card-text>
                    <!-- Loading State -->
                    <v-progress-linear 
                        v-if="loading" 
                        indeterminate 
                        color="primary"
                        class="mb-4"
                    ></v-progress-linear>

                    <!-- Error State -->
                    <v-alert
                        v-if="error && !loading"
                        type="error"
                        dismissible
                        @input="error = null"
                        class="mb-4"
                    >
                        {{ error }}
                    </v-alert>

                    <!-- Schema Validation Section -->
                        <v-card class="mb-4">
                            <v-card-title class="pb-2">
                                <v-icon class="mr-2">mdi-file-check</v-icon>
                                <span>{{$t("schema_validation")}}</span>
                                <v-chip
                                    v-if="schema_validation"
                                    :color="schema_validation.valid ? 'success' : 'error'"
                                    text-color="white"
                                    small
                                    class="ml-3"
                                >
                                    <v-icon small left>
                                        {{schema_validation.valid ? 'mdi-check-circle' : 'mdi-alert-circle'}}
                                    </v-icon>
                                    {{schema_validation.valid ? $t("valid") : $t("failed")}}
                                </v-chip>
                            </v-card-title>
                            <v-card-text>
                                <div v-if="loading_schema && !schema_validation" class="text-center py-4">
                                    <v-progress-circular indeterminate color="primary"></v-progress-circular>
                                    <p class="mt-2">{{$t("loading_please_wait")}}</p>
                                </div>

                                <div v-else-if="schema_validation">
                                    <!-- Validation Issues -->
                                    <div v-if="hasSchemaIssues">
                                        
                                        <v-simple-table dense>
                                            <thead>
                                                <tr>
                                                    <th style="width: 30%;">{{$t("property")}}</th>
                                                    <th style="width: 12%;">{{$t("type")}}</th>
                                                    <th style="width: 58%;">{{$t("message")}}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template v-for="(issue, index) in schema_validation.issues">
                                                    <tr 
                                                        :key="index"
                                                        :class="{'error-row': issue.type === 'validation_error'}"
                                                    >
                                                        <td>
                                                            <div class="d-flex align-center">
                                                                <code 
                                                                    v-if="issue.path || issue.property"
                                                                    class="text-caption mr-2"
                                                                    style="cursor: pointer; color: #1976d2; text-decoration: underline;"
                                                                    @click.stop="navigateToField(issue.path || issue.property)"
                                                                    :title='$t("click_to_navigate_to_field_in_editor")'
                                                                >
                                                                    {{issue.path || issue.property || '-'}}
                                                                </code>
                                                                <code 
                                                                    v-else
                                                                    class="text-caption mr-2"
                                                                >
                                                                    -
                                                                </code>
                                                                <v-btn
                                                                    v-if="issue.path || issue.property"
                                                                    x-small
                                                                    text
                                                                    icon
                                                                    @click.stop="navigateToField(issue.path || issue.property)"
                                                                    :title='$t("navigate_to_field")'
                                                                    class="ml-1"
                                                                >
                                                                    <v-icon x-small>mdi-open-in-new</v-icon>
                                                                </v-btn>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <v-chip 
                                                                x-small 
                                                                :color="issue.type === 'validation_error' ? 'error' : (issue.type === 'array_as_object' ? 'warning' : 'warning')"
                                                                text-color="white"
                                                            >
                                                                {{issue.type || 'error'}}
                                                            </v-chip>
                                                        </td>
                                                        <td>
                                                            <div class="text-body-2">{{issue.message}}</div>
                                                            <div v-if="issue.constraint" class="text-caption text--secondary">
                                                                {{$t("constraint_colon")}} {{issue.constraint}}
                                                            </div>
                                                            <div v-if="issue.expected_type && issue.actual_type" class="text-caption text--secondary mt-1">
                                                                {{$t("expected")}} {{issue.expected_type}}, {{$t("found")}} {{issue.actual_type}}
                                                            </div>
                                                            <div class="mt-2">
                                                                <v-btn
                                                                    x-small
                                                                    text
                                                                    color="primary"
                                                                    @click.stop="navigateToHelp(issue.type)"
                                                                    :title='$t("view_help_for_this_error_type")'
                                                                >
                                                                    <v-icon x-small left>mdi-help-circle-outline</v-icon>
                                                                    {{$t("help")}}
                                                                </v-btn>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </v-simple-table>
                                    </div>

                                    <!-- No Issues -->
                                    <v-alert
                                        v-else-if="schema_validation.valid"
                                        type="success"
                                        outlined
                                        icon="mdi-check-circle"
                                    >
                                        {{$t("no_validation_issues_found")}}
                                    </v-alert>
                                </div>

                                <div v-else-if="!loading" class="text-center py-4 text--secondary">
                                    {{$t("no_validation_data_available")}}
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Template Validation Section -->
                        <v-card class="mb-4">
                            <v-card-title class="pb-2">
                                <v-icon class="mr-2">mdi-form-select</v-icon>
                                <span>{{$t("template_validation")}}</span>
                                <v-chip
                                    v-if="template_validation"
                                    :color="template_validation.valid ? 'success' : 'error'"
                                    text-color="white"
                                    small
                                    class="ml-3"
                                >
                                    <v-icon small left>
                                        {{template_validation.valid ? 'mdi-check-circle' : 'mdi-alert-circle'}}
                                    </v-icon>
                                    {{template_validation.valid ? $t("valid") : $t("failed")}}
                                </v-chip>
                            </v-card-title>
                            <v-card-text>
                                <div v-if="loading_template && !template_validation" class="text-center py-4">
                                    <v-progress-circular indeterminate color="primary"></v-progress-circular>
                                    <p class="mt-2">{{$t("loading_please_wait")}}</p>
                                </div>

                                <div v-else-if="template_validation">
                                    <!-- Complete Validation Report (All Fields) -->
                                    <div v-if="templateValidationReport.length > 0">                                        

                                        <v-simple-table dense>
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%;">{{$t("field")}}</th>
                                                    <th style="width: 10%;">{{$t("status")}}</th>
                                                    <th style="width: 20%;">{{$t("rules")}}</th>
                                                    <th style="width: 25%;">{{$t("value")}}</th>
                                                    <th style="width: 20%;">{{$t("errors")}}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr 
                                                    v-for="(item, index) in templateValidationReport" 
                                                    :key="index"
                                                    :class="{
                                                        'error-row': item.status === 'invalid',
                                                        'success-row': item.status === 'valid'
                                                    }"
                                                >
                                                    <td>
                                                        <div class="d-flex align-center">
                                                            <div>
                                                                <div class="text-body-2 font-weight-medium">{{item.title || item.field || '-'}}</div>
                                                                <code 
                                                                    v-if="item.path || item.field"
                                                                    class="text-caption"
                                                                    style="cursor: pointer; color: #1976d2; text-decoration: underline;"
                                                                    @click.stop="navigateToField(item.path || item.field)"
                                                                    :title="$t("click_to_navigate_to_field_in_editor")"
                                                                >
                                                                    {{item.path || item.field || '-'}}
                                                                </code>
                                                                <code 
                                                                    v-else
                                                                    class="text-caption"
                                                                >
                                                                    -
                                                                </code>
                                                            </div>
                                                            <v-btn
                                                                v-if="item.path || item.field"
                                                                x-small
                                                                text
                                                                icon
                                                                @click.stop="navigateToField(item.path || item.field)"
                                                                :title='$t("navigate_to_field")'
                                                                class="ml-1"
                                                            >
                                                                <v-icon x-small>mdi-open-in-new</v-icon>
                                                            </v-btn>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <v-chip 
                                                            x-small 
                                                            :color="item.status === 'valid' ? 'success' : 'error'"
                                                            text-color="white"
                                                            class="text-uppercase"
                                                        >
                                                            {{item.status}}
                                                        </v-chip>
                                                    </td>
                                                    <td>
                                                        <div class="text-caption">
                                                            <div v-if="item.rules_applied && item.rules_applied.length > 0">
                                                                <div v-for="(rule, idx) in item.rules_applied" :key="idx" class="mb-1">
                                                                    <code>{{rule}}</code>
                                                                </div>
                                                            </div>
                                                            <span v-else class="text--secondary">-</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="text-caption">
                                                            <span v-if="item.value !== null && item.value !== undefined">
                                                                {{typeof item.value === 'object' ? JSON.stringify(item.value).substring(0, 50) + '...' : String(item.value).substring(0, 50)}}
                                                            </span>
                                                            <span v-else class="text--secondary">-</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div v-if="item.error_count > 0">
                                                            <div v-for="(error, errIdx) in item.errors" :key="errIdx" class="text-caption error-text mb-1">
                                                                {{error}}
                                                            </div>
                                                        </div>
                                                        <span v-else class="text--secondary text-caption">-</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </v-simple-table>
                                    </div>
                                </div>

                                <div v-else-if="!loading" class="text-center py-4 text--secondary">
                                    {{$t("no_template_validation_data_available")}}
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Variables Validation Section (only for microdata/survey projects) -->
                        <v-card v-if="isMicrodataProject" class="mb-4">
                            <v-card-title class="pb-2">
                                <v-icon class="mr-2">mdi-code-braces</v-icon>
                                <span>{{$t("variables_validation")}}</span>
                                <v-spacer></v-spacer>
                                <v-chip 
                                    v-if="variables_validation" 
                                    x-small 
                                    :color="variables_validation.valid ? 'success' : 'error'" 
                                    text-color="white"
                                >
                                    {{variables_validation.variables_checked || 0}} {{$t("checked")}}
                                    <span v-if="variables_validation.variables_with_errors > 0">
                                        , {{variables_validation.variables_with_errors}} {{$t("with_errors")}}
                                    </span>
                                </v-chip>
                            </v-card-title>
                            <v-card-text>
                                <div v-if="loading_variables && !variables_validation" class="text-center py-4">
                                    <v-progress-circular indeterminate color="primary"></v-progress-circular>
                                    <p class="mt-2">{{$t("loading_please_wait")}}</p>
                                </div>

                                <div v-else-if="variables_validation">
                                    <!-- Status Alert -->
                                    <v-alert
                                        v-if="hasVariablesIssues"
                                        type="error"
                                        outlined
                                        icon="mdi-alert-circle"
                                        class="mb-4"
                                    >
                                        Found {{variablesIssuesCount}} validation issue(s) in {{variables_validation.variables_with_errors}} variable(s) out of {{variables_validation.variables_checked}} {{$t("checked")}}.
                                        <span v-if="hasMoreVariablesErrors" class="font-weight-bold">
                                            <br>{{$t("showing_first_50_errors")}}
                                        </span>
                                    </v-alert>

                                    <v-alert
                                        v-else-if="variables_validation.valid"
                                        type="success"
                                        outlined
                                        icon="mdi-check-circle"
                                        class="mb-4"
                                    >
                                        {{$t("all_variables_validated_successfully")}}
                                    </v-alert>

                                    <!-- Validation Issues -->
                                    <div v-if="hasVariablesIssues">
                                        <!-- More errors warning -->
                                        <v-alert
                                            v-if="hasMoreVariablesErrors"
                                            type="warning"
                                            outlined
                                            dense
                                            icon="mdi-information"
                                            class="mb-3"
                                        >
                                            {{$t("showing_first_50_errors_warning")}}
                                        </v-alert>
                                        
                                        <v-simple-table dense>
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%;">{{$t("variable")}}</th>
                                                    <th style="width: 15%;">{{$t("file")}}</th>
                                                    <th style="width: 12%;">{{$t("type")}}</th>
                                                    <th style="width: 48%;">{{$t("message")}}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template v-for="(issue, index) in variablesIssuesDisplay">
                                                    <tr 
                                                        :key="index"
                                                        :class="{'error-row': issue.type === 'validation_error'}"
                                                    >
                                                        <td>
                                                            <code class="text-caption">{{issue.variable_name || $t("unknown")}}</code>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-center">
                                                                <code 
                                                                    v-if="issue.variable_fid"
                                                                    class="text-caption mr-1"
                                                                    style="cursor: pointer; color: #1976d2; text-decoration: underline;"
                                                                    @click="navigateToDatafile(issue.variable_fid)"
                                                                    :title="$t("click_to_navigate_to_variables_page")"
                                                                >
                                                                    {{issue.variable_fid}}
                                                                </code>
                                                                <code v-else class="text-caption">{{$t("unknown")}}</code>
                                                                <v-btn
                                                                    v-if="issue.variable_fid"
                                                                    x-small
                                                                    text
                                                                    icon
                                                                    @click="navigateToDatafile(issue.variable_fid)"
                                                                    :title='$t("navigate_to_variables_page")'
                                                                    class="ml-1"
                                                                >
                                                                    <v-icon x-small>mdi-open-in-new</v-icon>
                                                                </v-btn>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <v-chip 
                                                                x-small 
                                                                :color="issue.type === 'validation_error' ? 'error' : 'warning'"
                                                                text-color="white"
                                                            >
                                                                {{issue.type || 'error'}}
                                                            </v-chip>
                                                        </td>
                                                        <td>
                                                            <div class="text-body-2">{{issue.message}}</div>
                                                            <div v-if="issue.constraint" class="text-caption text--secondary mt-1">
                                                                {{$t("constraint_colon")}} {{issue.constraint}}
                                                            </div>
                                                            <div v-if="issue.path" class="text-caption text--secondary mt-1">
                                                                {{$t("path_colon")}} <code>{{issue.path}}</code>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </v-simple-table>
                                    </div>

                                </div>

                                <div v-else-if="!loading_variables" class="text-center py-4 text--secondary">
                                    {{$t("no_variables_validation_data_available")}}
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Schema: metadata keys not defined in JSON schema (excludes additional subtree) -->
                        <v-card class="mb-4">
                            <v-card-title class="pb-2">
                                <v-icon class="mr-2">mdi-alert-circle-outline</v-icon>
                                <span>{{$t("extra_fields")}}</span>
                                <v-chip
                                    v-if="extra_fields && extraFieldsCount > 0"
                                    small
                                    class="ml-3"
                                    color="warning"
                                    text-color="white"
                                >
                                    {{ extraFieldsCount }}
                                </v-chip>
                            </v-card-title>
                            <v-card-text>
                                <div v-if="loading_extra_fields && !extra_fields" class="text-center py-4">
                                    <v-progress-circular indeterminate color="primary"></v-progress-circular>
                                    <p class="mt-2">{{$t("loading_please_wait")}}</p>
                                </div>

                                <div v-else-if="extra_fields">
                                    <!-- Info Alert -->
                                    <v-alert
                                        type="info"
                                        class="mb-4"
                                        outlined
                                        icon="mdi-information-outline"
                                    >
                                        <div>
                                            <strong>{{$t("fields_not_defined_in_schema")}}</strong>
                                            <div class="text-caption mt-1">
                                                {{$t("schema_fields_description")}}
                                            </div>
                                        </div>
                                    </v-alert>

                                    <!-- Extra Fields List -->
                                    <div v-if="hasExtraFields">
                                        <div class="d-flex justify-space-between align-center mb-3">
                                            <h3 class="mb-0">{{$t("extra_fields")}} ({{extraFieldsCount}})</h3>
                                            <div class="d-flex align-center">
                                                <v-btn
                                                    v-if="canManageExtraFields"
                                                    small
                                                    outlined
                                                    color="primary"
                                                    @click="moveToAdditional"
                                                    :disabled="selected_extra_fields.length === 0"
                                                    class="mr-2"
                                                >
                                                    <v-icon left small>mdi-folder-move</v-icon>
                                                    {{$t("move_selected_to_additional")}}
                                                </v-btn>
                                                <v-btn
                                                    v-if="canManageExtraFields"
                                                    small
                                                    outlined
                                                    color="error"
                                                    @click="confirmRemoveFields"
                                                    :disabled="selected_extra_fields.length === 0"
                                                >
                                                    <v-icon left small>mdi-delete</v-icon>
                                                    {{$t("remove_selected")}}
                                                </v-btn>
                                            </div>
                                        </div>
                                        
                                        <v-simple-table dense>
                                            <thead>
                                                <tr>
                                                    <th v-if="canManageExtraFields" style="width: 5%;">
                                                        <v-checkbox
                                                            :input-value="allExtraFieldsSelected"
                                                            :indeterminate="someExtraFieldsSelected"
                                                            @click.stop="selectAllExtraFields"
                                                            hide-details
                                                            dense
                                                        ></v-checkbox>
                                                    </th>
                                                    <th style="width: 5%;"></th>
                                                    <th style="width: 32%;">{{$t("path")}}</th>
                                                    <th style="width: 12%;">{{$t("type")}}</th>
                                                    <th style="width: 36%;">{{$t("value_preview")}}</th>
                                                    <th v-if="canManageExtraFields" style="width: 10%;">{{$t("actions")}}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr 
                                                    v-for="(field, index) in extra_fields.extra_fields" 
                                                    :key="index"
                                                >
                                                    <td v-if="canManageExtraFields">
                                                        <v-checkbox
                                                            :value="field.path"
                                                            v-model="selected_extra_fields"
                                                            hide-details
                                                            dense
                                                            @click.stop
                                                        ></v-checkbox>
                                                    </td>
                                                    <td>
                                                        <v-btn
                                                            v-if="field.path"
                                                            x-small
                                                            text
                                                            icon
                                                            @click="navigateToField(field.path)"
                                                            :title="$t('navigate_to_field')"
                                                        >
                                                            <v-icon x-small>mdi-open-in-new</v-icon>
                                                        </v-btn>
                                                    </td>
                                                    <td>
                                                        <code class="text-caption">{{field.path || field.field || '-'}}</code>
                                                    </td>
                                                    <td>
                                                        <v-chip 
                                                            x-small 
                                                            outlined
                                                        >
                                                            {{field.type || $t("unknown_type")}}
                                                        </v-chip>
                                                    </td>
                                                    <td>
                                                        <div class="text-body-2 text--secondary">
                                                            {{field.value_preview || '-'}}
                                                        </div>
                                                    </td>
                                                    <td v-if="canManageExtraFields">
                                                        <v-btn
                                                            x-small
                                                            text
                                                            color="error"
                                                            icon
                                                            :title="$t('remove_this_field')"
                                                            @click.stop="confirmRemoveSingleField(field.path)"
                                                        >
                                                            <v-icon x-small>mdi-delete-outline</v-icon>
                                                        </v-btn>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </v-simple-table>
                                    </div>

                                    <!-- No Extra Fields -->
                                    <v-alert
                                        v-else
                                        type="success"
                                        outlined
                                        icon="mdi-check-circle"
                                    >
                                        {{$t("no_extra_fields_found")}}
                                    </v-alert>

                                    <!-- Error State -->
                                    <v-alert
                                        v-if="extra_fields.error"
                                        type="warning"
                                        outlined
                                        class="mt-4"
                                    >
                                        {{extra_fields.error}}
                                    </v-alert>
                                </div>

                                <div v-else-if="!loading_extra_fields" class="text-center py-4 text--secondary">
                                    {{$t("no_extra_fields_data_available")}}
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Confirm Remove Dialog -->
                        <v-dialog
                            v-model="show_confirm_remove"
                            max-width="640"
                            persistent
                        >
                            <v-card>
                                <v-card-title class="headline">
                                    {{$t("confirm_remove_fields")}}
                                </v-card-title>
                                <v-card-text>
                                    <p>{{$t("are_you_sure_permanently_remove")}} <strong>{{fields_to_remove.length}}</strong> field(s)?</p>
                                    <p class="text-caption text--secondary">{{$t("action_cannot_be_undone")}}</p>
                                    <v-alert
                                        type="warning"
                                        outlined
                                        dense
                                        class="mt-3"
                                    >
                                        {{$t("fields_will_be_removed")}}
                                        <div
                                            class="mt-2 pa-2"
                                            style="max-height: 220px; overflow-y: auto; overflow-x: hidden; border-radius: 4px; background: rgba(0, 0, 0, 0.04);"
                                        >
                                            <ul class="mb-0 pl-0" style="list-style: none;">
                                                <li
                                                    v-for="(path, idx) in fields_to_remove"
                                                    :key="idx"
                                                    class="text-caption py-1"
                                                    style="word-break: break-word; overflow-wrap: anywhere;"
                                                >
                                                    <code
                                                        class="text-caption"
                                                        style="display: block; word-break: break-word; overflow-wrap: anywhere; white-space: pre-wrap;"
                                                    >{{path}}</code>
                                                </li>
                                            </ul>
                                        </div>
                                    </v-alert>
                                </v-card-text>
                                <v-card-actions>
                                    <v-spacer></v-spacer>
                                    <v-btn
                                        text
                                        @click="cancelRemoveFields"
                                    >
                                        {{$t("cancel")}}
                                    </v-btn>
                                    <v-btn
                                        color="error"
                                        text
                                        @click="removeFields"
                                    >
                                        {{$t("remove")}}
                                    </v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-dialog>
                </v-card-text>
            </v-card>
        </div>
    `
});

