Vue.component('dialog-datafile-export', {
    props: {
        value: { type: Boolean, default: false },
        file_id: { type: [String, Number], default: null },
        file_name: { type: String, default: '' },
        file_physical_name: { type: String, default: '' }
    },
    data() {
        return {
            selected_format: '',
            available_formats: [
                { value: 'csv', label: 'CSV' },
                { value: 'dta', label: 'Stata (DTA)' },
                { value: 'sav', label: 'SPSS (SAV)' },
                { value: 'json', label: 'JSON' },
                { value: 'xpt', label: 'SAS' }
            ],
            /** Stata .dta format version (8-15). Only used when selected_format === 'dta'. */
            selected_stata_version: 14,
            stata_version_options: [8, 9, 10, 11, 12, 13, 14, 15].map(v => ({ value: v, label: 'Stata ' + v })),
            zip_option: true,
            remove_after_zip: true,
            zip_download_url: null,
            zip_creating: false,
            zip_error: null,
            individual_file_removed: false,
            export_dialog: {
                show: false,
                title: '',
                loading_message: '',
                message_success: '',
                message_error: '',
                is_loading: false,
                download_links: []
            },
            validation_dialog: {
                show: false,
                errors: [],
                missing_value_errors: [],
                value_label_errors: [],
                error_summary: {},
                format: ''
            }
        }
    },
    watch: {
        value(val) {
            if (!val) {
                this.zip_download_url = null;
                this.zip_creating = false;
                this.zip_error = null;
                this.individual_file_removed = false;
            }
        }
    },
    mounted: function () {
        
    },      
    methods: {   
        closeDialog: function(){
            this.$emit('input', false);
        },
        
        async exportFile() {
            if (!this.selected_format) {
                return;
            }

            this.export_dialog = {
                show: true,
                title: this.$t('export_file') + '[' + this.selected_format + ']',
                loading_message: this.$t('validating_value_labels'),
                message_success: '',
                message_error: '',
                is_loading: true
            };

            try {
                // First validate export compatibility for STATA/SPSS exports
                if (this.selected_format === 'dta' || this.selected_format === 'sav') {
                    this.export_dialog.loading_message = this.$t('validating_export_compatibility');
                    
                    let validationResult = await this.$store.dispatch('validateExport', {
                        file_id: this.file_id, 
                        format: this.selected_format,
                        show_all_errors: true
                    });
                    
                    if (!validationResult.data.data.validation_passed) {
                        // Show warning dialog with validation errors
                        this.showValidationWarning(validationResult.data.data);
                        return;
                    }
                }

                // Proceed with export
                this.export_dialog.loading_message = this.$t('processing_please_wait');
                let payload = { file_id: this.file_id, format: this.selected_format };
                if (this.selected_format === 'dta' && this.selected_stata_version != null) {
                    payload.export_options = { version: this.selected_stata_version };
                }
                let result = await this.$store.dispatch('exportDatafileQueue', payload);
                console.log("queued for export", result);
                const outputFilename = (result.data && result.data.output_filename) ? result.data.output_filename : null;
                this.exportFileStatusCheck(this.file_id, result.data.job_id, this.selected_format, outputFilename);
            } catch(e) {
                console.log("failed", e);
                this.export_dialog.is_loading = false;
                this.export_dialog.message_error = this.$t("failed") + ": " + e.response.data.message;

                //check if detail is present
                if (e.response.data.detail) {
                    this.export_dialog.message_error += "\n\n" + e.response.data.detail;
                }
            }
        },
        
        showValidationWarning: function(validationData) {
            // Store validation data and show dialog
            this.validation_dialog.errors = validationData.all_errors || [];
            this.validation_dialog.missing_value_errors = validationData.missing_value_errors || [];
            this.validation_dialog.value_label_errors = validationData.value_label_errors || [];
            this.validation_dialog.error_summary = validationData.error_summary || {};
            this.validation_dialog.format = this.selected_format;
            this.validation_dialog.show = true;
        },
        
        async proceedWithExport() {
            this.export_dialog = {
                show: true,
                title: this.$t('export_file') + '[' + this.selected_format + ']',
                loading_message: this.$t('processing_please_wait'),
                message_success: '',
                message_error: '',
                is_loading: true
            };

            try {
                let payload = { file_id: this.file_id, format: this.selected_format };
                if (this.selected_format === 'dta' && this.selected_stata_version != null) {
                    payload.export_options = { version: this.selected_stata_version };
                }
                let result = await this.$store.dispatch('exportDatafileQueue', payload);
                console.log("queued for export", result);
                const outputFilename = (result.data && result.data.output_filename) ? result.data.output_filename : null;
                this.exportFileStatusCheck(this.file_id, result.data.job_id, this.selected_format, outputFilename);
            } catch(e) {
                console.log("failed", e);
                this.export_dialog.is_loading = false;
                this.export_dialog.message_error = this.$t("failed") + ": " + e.response.data.message;

                //check if detail is present
                if (e.response.data.detail) {
                    this.export_dialog.message_error += "\n\n" + e.response.data.detail;
                }
            }
        },
        
        async exportFileStatusCheck(file_id, job_id, format, outputFilename) {
            this.export_dialog = {
                show: true,
                title: '',
                loading_message: '',
                message_success: '',
                message_error: '',
                is_loading: false,
                download_links: []
            };

            this.export_dialog.is_loading = true;
            this.export_dialog.title = this.$t('export_file');
            this.export_dialog.loading_message = this.$t('processing_please_wait');
            
            try {
                await this.sleep(5000);
                let result = await this.$store.dispatch('getJobStatus', {job_id: job_id});
                console.log("export status", result);
                this.export_dialog.is_loading = true;
                this.export_dialog.loading_message = this.$t('job_status') + ": " + result.data.job_status;

                if (result.data.job_status === 'failed' || result.data.job_status === 'error') {
                    const msg = result.data.message || (typeof result.data.detail === 'string' ? result.data.detail : '') || 'Job failed';
                    this.export_dialog.is_loading = false;
                    this.export_dialog.message_error = this.$t("failed") + ": " + msg;
                    return;
                }
                if (result.data.job_status !== 'done') {
                    this.exportFileStatusCheck(file_id, job_id, format, outputFilename);
                } else if (result.data.job_status === 'done') {
                    let download_url = CI.base_url + '/api/datafiles/download_tmp_file/' + this.ProjectID + '/' + file_id + '/' + format;
                    if (outputFilename) {
                        download_url += '?filename=' + encodeURIComponent(outputFilename);
                    }
                    this.export_dialog = Object.assign({}, this.export_dialog, {
                        is_loading: false,
                        message_success: this.$t('file_generated_success'),
                        download_links: [{ url: download_url, format: format }]
                    });
                    if (this.zip_option && this.file_physical_name) {
                        this.createZipSingle(format, outputFilename);
                    }
                }
            } catch(e) {
                console.log("failed", e);
                this.export_dialog.is_loading = false;
                this.export_dialog.message_error = this.$t("failed") + ": " + e.response.data.message;

                //check if detail is present
                if (e.response.data.detail) {
                    this.export_dialog.message_error += "\n\n" + e.response.data.detail;
                }
            }
        },
        
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        filenamePart(name) {
            if (!name) return '';
            const i = name.lastIndexOf('.');
            return i >= 0 ? name.substring(0, i) : name;
        },
        async createZipSingle(format, outputFilename) {
            const base = this.filenamePart(this.file_physical_name);
            if (!base) return;
            const filename = outputFilename || (base + '.' + format);
            const payload = { filenames: [filename] };
            if (format === 'dta' && this.selected_stata_version != null) {
                payload.stata_version = this.selected_stata_version;
            }
            this.zip_creating = true;
            this.zip_error = null;
            try {
                const resp = await this.$store.dispatch('createBatchExportZip', payload);
                const zip_path = resp.data && resp.data.zip_path ? resp.data.zip_path : null;
                if (zip_path) {
                    this.zip_download_url = CI.base_url + '/api/files/download/' + this.ProjectID + '?file=' + encodeURIComponent(zip_path);
                    if (this.remove_after_zip) {
                        await this.removeIndividualExports([filename]);
                        this.individual_file_removed = true;
                    }
                } else {
                    this.zip_error = this.$t('batch_export_zip_failed') || 'Could not create zip';
                }
            } catch (e) {
                this.zip_error = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || 'Could not create zip');
            }
            this.zip_creating = false;
        },
        async removeIndividualExports(filenames) {
            const url = CI.base_url + '/api/files/delete/' + this.ProjectID;
            for (const name of filenames) {
                const relativePath = 'data/tmp/' + name;
                try {
                    const formData = new FormData();
                    formData.append('file', relativePath);
                    await axios.post(url, formData);
                } catch (e) {
                    console.warn('Could not remove tmp file:', name, e);
                }
            }
        },
        confirmValidationWarning: function() {
            // User confirmed, proceed with export
            this.validation_dialog.show = false;
            this.proceedWithExport();
        },
        
        cancelValidationWarning: function() {
            // User cancelled, close all dialogs
            this.validation_dialog.show = false;
            this.export_dialog = {
                show: false,
                title: '',
                loading_message: '',
                message_success: '',
                message_error: '',
                is_loading: false
            };
        }
    },
    computed: {
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        },        
        ProjectID(){
            return this.$store.state.project_id;
        }        
    },
    template: `
        <div class="vue-dialog-datafile-export-component">
            <!-- Export Format Selection Dialog -->
            <v-dialog v-model="dialog" width="500" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$t('export_file')}} - {{file_name}}
                    </v-card-title>

                    <v-card-text>
                        <div class="mt-4">
                            <label class="text-body-1 font-weight-medium mb-2 d-block">{{$t('select_export_format')}}</label>
                            <v-select
                                v-model="selected_format"
                                :items="available_formats"
                                item-text="label"
                                item-value="value"
                                label=""
                                outlined
                                dense
                                required
                            ></v-select>
                        </div>
                        <div class="mt-3" v-if="selected_format === 'dta'">
                            <label class="text-body-1 font-weight-medium mb-2 d-block">{{ $t('stata_version') || 'Stata version' }}</label>
                            <v-select
                                v-model="selected_stata_version"
                                :items="stata_version_options"
                                item-text="label"
                                item-value="value"
                                outlined
                                dense
                                hide-details
                            ></v-select>
                        </div>
                        <div class="mt-3">
                            <v-checkbox
                                v-model="zip_option"
                                :label="$t('single_export_zip_option') || 'Zip the exported file'"
                                hide-details
                                dense
                                class="mt-0"
                            ></v-checkbox>                            
                        </div>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="grey" text small @click="closeDialog">
                            {{$t('cancel')}}
                        </v-btn>
                        <v-btn 
                            color="primary" 
                            small
                            @click="exportFile"
                            :disabled="!selected_format"
                        >
                            {{$t('export')}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Export Progress Dialog -->
            <v-dialog v-model="export_dialog.show" width="600" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{export_dialog.title}}
                    </v-card-title>

                    <v-card-text>
                        <div>
                            <div v-if="export_dialog.is_loading" class="mb-3">
                                <div class="text-body-2 mb-2">{{export_dialog.loading_message}}</div>
                                <v-progress-linear 
                                    indeterminate
                                    color="primary"
                                    height="6"
                                    rounded
                                ></v-progress-linear>
                            </div>

                            <div v-if="export_dialog.message_success" class="text-center">
                                <v-icon color="#4CAF50" size="48" class="mb-3">mdi-check-circle</v-icon>
                                <div class="text-body-1 mb-4">{{export_dialog.message_success}}</div>
                                <div v-if="zip_option" class="mb-3">
                                    <div v-if="zip_creating" class="text-caption text--secondary">
                                        <v-progress-circular indeterminate size="20" width="2" class="mr-2"></v-progress-circular>
                                        {{ $t('batch_export_creating_zip') || 'Creating ZIP...' }}
                                    </div>
                                    <v-btn v-else-if="zip_download_url" color="primary" :href="zip_download_url" target="_blank" download class="mb-2">
                                        <v-icon left>mdi-folder-zip</v-icon>{{ $t('single_export_download_zip') || 'Download (ZIP)' }}
                                    </v-btn>
                                    <div v-else-if="zip_error" class="text-caption error--text">{{ zip_error }}</div>
                                </div>
                                <!-- Direct file download when ZIP is not selected -->
                                <div v-if="(export_dialog.download_links || []).length && !individual_file_removed" class="mb-2">
                                    <v-btn 
                                        v-for="(link, index) in (export_dialog.download_links || [])" 
                                        :key="'dl-' + index"
                                        color="primary" 
                                        block
                                        large
                                        :href="link.url" 
                                        target="_blank"
                                        download
                                        class="mb-2"
                                    >
                                        <v-icon left>mdi-download</v-icon>
                                        [{{(link.format || '').toUpperCase()}}] {{$t('download')}}
                                    </v-btn>
                                </div>
                            </div>

                            <div class="alert alert-danger" v-if="export_dialog.message_error">
                                {{export_dialog.message_error}}
                            </div>
                        </div>
                    </v-card-text>

                    <v-card-actions class="py-3 px-4">
                        <v-spacer></v-spacer>
                        <v-btn color="grey" text small @click="export_dialog.show = false" v-if="export_dialog.is_loading">
                            {{$t('cancel')}}
                        </v-btn>
                        <v-btn color="primary" text small @click="export_dialog.show = false" v-if="!export_dialog.is_loading">
                            {{$t('close')}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Validation Warning Dialog -->
            <v-dialog v-model="validation_dialog.show" width="700" persistent>
                <v-card>
                    <v-card-title class="text-h6 grey lighten-2 py-3">                        
                        {{$t('export_warning')}}
                    </v-card-title>

                    <v-card-text class="py-3">
                        <div class="mb-3" style="color: #e65100; font-weight: 500;">
                            {{$t('export_compatibility_issues')}}
                        </div>
                        
                        <div class="text-caption text--secondary mb-2">
                            {{$t('validation_issues_found')}}: 
                            <span class="font-weight-medium">{{validation_dialog.error_summary.total_errors || 0}} total</span>
                            <span v-if="validation_dialog.error_summary.missing_value_errors > 0" class="ml-2">
                                ({{validation_dialog.error_summary.missing_value_errors}} missing value issues, 
                                {{validation_dialog.error_summary.value_label_errors}} value label issues)
                            </span>
                        </div>
                        
                        <!-- Missing Value Errors -->
                        <div v-if="validation_dialog.missing_value_errors.length > 0" class="mb-3">
                            <div class="text-subtitle-2 font-weight-medium mb-2" style="color: #d32f2f;">
                                {{$t('missing_value_issues')}} ({{validation_dialog.missing_value_errors.length}})
                            </div>
                            <div class="validation-issues-container" style="max-height: 150px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 4px; padding: 8px; background-color: #ffebee;">
                                <ul class="text-body-2 mb-0" style="padding-left: 16px;">
                                    <li 
                                        v-for="(error, index) in validation_dialog.missing_value_errors" 
                                        :key="'missing-' + index"
                                        class="mb-1"
                                    >
                                        <span class="font-weight-medium">{{error.variable_name}}:</span> {{error.error}}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Value Label Errors -->
                        <div v-if="validation_dialog.value_label_errors.length > 0">
                            <div class="text-subtitle-2 font-weight-medium mb-2" style="color: #d32f2f;">
                                {{$t('value_label_issues')}} ({{validation_dialog.value_label_errors.length}})
                            </div>
                            <div class="validation-issues-container" style="max-height: 150px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 4px; padding: 8px; background-color: #fff3e0;">
                                <ul class="text-body-2 mb-0" style="padding-left: 16px;">
                                    <li 
                                        v-for="(error, index) in validation_dialog.value_label_errors" 
                                        :key="'label-' + index"
                                        class="mb-1"
                                    >
                                        <span class="font-weight-medium">{{error.variable_name}}:</span> {{error.error}}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </v-card-text>

                    <v-card-actions class="py-3 px-4">
                        <v-spacer></v-spacer>
                        <v-btn color="grey" text small @click="cancelValidationWarning" class="mr-2">
                            {{$t('cancel')}}
                        </v-btn>
                        <v-btn color="primary" small @click="confirmValidationWarning">
                            {{$t('continue_export')}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});

