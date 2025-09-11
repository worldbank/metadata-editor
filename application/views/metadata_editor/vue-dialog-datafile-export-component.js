Vue.component('dialog-datafile-export', {
    props: ['value', 'file_id', 'file_name'],
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
                format: ''
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
                // First validate value labels for STATA/SPSS exports
                if (this.selected_format === 'dta' || this.selected_format === 'sav') {
                    this.export_dialog.loading_message = this.$t('validating_value_labels');
                    
                    let validationResult = await this.$store.dispatch('validateValueLabels', {
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
                let result = await this.$store.dispatch('exportDatafileQueue', {
                    file_id: this.file_id, 
                    format: this.selected_format
                });
                console.log("queued for export", result);
                this.exportFileStatusCheck(this.file_id, result.data.job_id, this.selected_format);
            } catch(e) {
                console.log("failed", e);
                this.export_dialog.is_loading = false;
                this.export_dialog.message_error = this.$t("failed") + ": " + e.response.data.message;
            }
        },
        
        showValidationWarning: function(validationData) {
            // Store validation data and show dialog
            this.validation_dialog.errors = validationData.errors || [];
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
                let result = await this.$store.dispatch('exportDatafileQueue', {
                    file_id: this.file_id, 
                    format: this.selected_format
                });
                console.log("queued for export", result);
                this.exportFileStatusCheck(this.file_id, result.data.job_id, this.selected_format);
            } catch(e) {
                console.log("failed", e);
                this.export_dialog.is_loading = false;
                this.export_dialog.message_error = this.$t("failed") + ": " + e.response.data.message;
            }
        },
        
        async exportFileStatusCheck(file_id, job_id, format) {
            this.export_dialog = {
                show: true,
                title: '',
                loading_message: '',
                message_success: '',
                message_error: '',
                is_loading: false
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
                
                if (result.data.job_status !== 'done') {
                    this.exportFileStatusCheck(file_id, job_id, format);
                } else if (result.data.job_status === 'done') {
                    this.export_dialog.is_loading = false;
                    let download_url = CI.base_url + '/api/datafiles/download_tmp_file/' + this.ProjectID + '/' + file_id + '/' + format;
                    this.export_dialog.message_success = this.$t('file_generated_success');
                    this.export_dialog.download_links = [];
                    this.export_dialog.download_links.push({
                        url: download_url,
                        format: format
                    });
                }
            } catch(e) {
                console.log("failed", e);
                this.export_dialog.is_loading = false;
                this.export_dialog.message_error = this.$t("failed") + ": " + e.response.data.message;
            }
        },
        
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
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
                                
                                <v-btn 
                                    v-for="(link, index) in export_dialog.download_links" 
                                    :key="index"
                                    color="primary" 
                                    block
                                    large
                                    :href="link.url" 
                                    target="_blank"
                                    download
                                    class="mb-2"
                                >
                                    <v-icon left>mdi-download</v-icon>
                                    [{{link.format.toUpperCase()}}] {{$t('download')}}
                                </v-btn>
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
                            {{$t('value_labels_not_exportable')}}
                        </div>
                        
                        <div class="text-caption text--secondary mb-2">{{$t('validation_issues_found')}}:</div>
                        
                        <div class="validation-issues-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 4px; padding: 8px;">
                            <ul class="text-body-2 mb-0" style="padding-left: 16px;">
                                <li 
                                    v-for="(error, index) in validation_dialog.errors" 
                                    :key="index"
                                    class="mb-1"
                                >
                                    <span class="font-weight-medium">{{error.variable_name}}:</span> {{error.error}}
                                </li>
                            </ul>
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

