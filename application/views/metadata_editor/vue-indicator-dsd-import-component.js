// Indicator CSV import: upload CSV, pick indicator ID from distinct values, save to binding, import.
Vue.component('indicator-dsd-import', {
    props: {
        embedded: { type: Boolean, default: false }
    },
    data: function() {
        return {
            dataset_id: project_sid,
            file: null,
            loading: false,
            savingIndicatorId: false,
            errors: [],
            binding: null,
            bindingLoading: true,
            csvValidated: false,
            headerError: '',
            missingInCsv: [],
            ignoredColumns: [],
            expectedColumns: [],
            indicatorColumn: '',
            seriesIdno: '',
            savedIndicatorIdValue: '',
            indicatorValues: [],
            indicatorValuesTruncated: false,
            indicatorValuesLimit: 2000,
            seriesIdnoInCsv: false,
            selectedIndicatorId: '',
            impliedFreqCode: '',
            savedImpliedFreqCode: '',
            savingImpliedFreq: false,
            freqCodes: [],
            importStatus: '',
            importProgress: 0,
            /** Resumable upload: determinate bar during chunks */
            show_upload_chunk_progress: false,
            upload_chunk_percent: 0,
            /** false = remove extra CSV columns (default); true = keep in imported data */
            keepExtraCsvColumns: false,
            keepExtraCsvColumnsApplied: false
        };
    },
    created: function() {
        this.loadBinding();
    },
    computed: {
        canUpload: function() {
            return this.binding && this.binding.bound && (this.binding.column_count || 0) > 0;
        },
        structureReady: function() {
            if (this.binding && this.binding.structure_validation) {
                return !!this.binding.structure_validation.valid;
            }
            return false;
        },
        structureBlockedReasons: function() {
            var reasons = (this.binding && this.binding.import_blocked_reasons)
                ? this.binding.import_blocked_reasons.slice()
                : [];
            return reasons.filter(function(r) {
                return r.indexOf('Indicator ID') === -1;
            });
        },
        indicatorValueCodes: function() {
            return (this.indicatorValues || []).map(function(item) {
                return item.value;
            });
        },
        selectedIndicatorIdTrimmed: function() {
            return String(this.selectedIndicatorId || '').trim();
        },
        indicatorSelectionDirty: function() {
            return this.selectedIndicatorIdTrimmed !== String(this.savedIndicatorIdValue || '').trim();
        },
        canValidateCsv: function() {
            return this.structureReady && this.file && this.impliedFreqReady && !this.loading;
        },
        validateCsvBlockedHint: function() {
            if (!this.structureReady) {
                return 'Fix structure validation issues before validating the CSV.';
            }
            if (!this.file) {
                return 'Select a CSV file first.';
            }
            if (this.needsImpliedFreq && !this.impliedFreqReady) {
                return 'Choose series FREQ before validating the CSV.';
            }
            return '';
        },
        needsImpliedFreq: function() {
            return !!(this.binding && this.binding.needs_implied_freq_code);
        },
        impliedFreqReady: function() {
            if (!this.needsImpliedFreq) {
                return true;
            }
            return String(this.impliedFreqCode || '').trim() !== '';
        },
        canImport: function() {
            return this.csvValidated
                && this.selectedIndicatorIdTrimmed !== ''
                && this.impliedFreqReady
                && !this.loading
                && !this.savingIndicatorId
                && !this.savingImpliedFreq;
        },
        globalStructureLabel: function() {
            if (!this.binding || !this.binding.global_structure) {
                return '';
            }
            var ds = this.binding.global_structure;
            return (ds.title || ds.name || ds.idno || 'Data structure');
        },
        indicatorIdColumn: function() {
            if (this.indicatorColumn) {
                return this.indicatorColumn;
            }
            return (this.binding && this.binding.indicator_id_column) ? this.binding.indicator_id_column : '';
        },
        ignoredColumnsPreview: function() {
            var cols = this.ignoredColumns || [];
            if (cols.length <= 12) {
                return cols.join(', ');
            }
            return cols.slice(0, 12).join(', ') + ' … (+' + (cols.length - 12) + ' more)';
        },
        hasExtraCsvColumns: function() {
            return (this.ignoredColumns || []).length > 0;
        }
    },
    methods: {
        formatIndicatorItemLabel: function(value) {
            var item = (this.indicatorValues || []).find(function(row) {
                return row.value === value;
            });
            if (item && item.count != null && item.count !== '') {
                return item.value + ' (' + item.count + ' rows)';
            }
            return value;
        },
        applyBindingContext: function(binding) {
            binding = binding || {};
            this.seriesIdno = binding.series_idno
                || binding.default_indicator_id_value
                || '';
            this.savedIndicatorIdValue = binding.indicator_id_value
                ? String(binding.indicator_id_value).trim()
                : '';
            this.savedImpliedFreqCode = binding.implied_freq_code
                ? String(binding.implied_freq_code).trim()
                : '';
            this.impliedFreqCode = this.savedImpliedFreqCode;
            this.freqCodes = Array.isArray(binding.freq_codes) ? binding.freq_codes : [];
        },
        resetCsvValidation: function() {
            this.csvValidated = false;
            this.indicatorValues = [];
            this.indicatorValuesTruncated = false;
            this.seriesIdnoInCsv = false;
            this.selectedIndicatorId = '';
            this.headerError = '';
            this.missingInCsv = [];
            this.ignoredColumns = [];
            this.expectedColumns = [];
            this.indicatorColumn = '';
        },
        applyPrepareResult: function(data) {
            var vm = this;
            data = data || {};
            vm.csvValidated = true;
            vm.indicatorValues = Array.isArray(data.indicator_values) ? data.indicator_values : [];
            vm.indicatorValuesTruncated = !!data.indicator_values_truncated;
            vm.indicatorValuesLimit = data.indicator_values_limit || 2000;
            vm.seriesIdnoInCsv = !!data.series_idno_in_csv;
            vm.ignoredColumns = Array.isArray(data.ignored_columns)
                ? data.ignored_columns
                : (Array.isArray(data.extra_in_csv) ? data.extra_in_csv : []);
            vm.expectedColumns = data.expected_columns || [];
            vm.indicatorColumn = data.indicator_column || '';
            vm.keepExtraCsvColumnsApplied = !!data.keep_extra_csv_columns;
            vm.applyDefaultIndicatorSelection();
        },
        applyDefaultIndicatorSelection: function() {
            var vm = this;
            var values = vm.indicatorValueCodes;
            var saved = String(vm.savedIndicatorIdValue || '').trim();
            var series = String(vm.seriesIdno || '').trim();

            if (saved && values.indexOf(saved) >= 0) {
                vm.selectedIndicatorId = saved;
            } else if (series && values.indexOf(series) >= 0) {
                vm.selectedIndicatorId = series;
            } else if (values.length === 1) {
                vm.selectedIndicatorId = values[0];
            } else {
                vm.selectedIndicatorId = '';
            }
        },
        loadBinding: function() {
            var vm = this;
            vm.bindingLoading = true;
            axios.get(CI.base_url + '/api/indicator_dsd/binding/' + vm.dataset_id)
                .then(function(res) {
                    vm.binding = res.data || {};
                    vm.applyBindingContext(vm.binding);
                })
                .catch(function() {
                    vm.binding = { bound: false, column_count: 0 };
                    vm.seriesIdno = '';
                    vm.savedIndicatorIdValue = '';
                })
                .then(function() {
                    vm.bindingLoading = false;
                });
        },
        goToDsd: function() {
            this.$router.push('/indicator-dsd-overview');
        },
        onImportComplete: function() {
            if (this.embedded) {
                this.$emit('imported');
                return;
            }
            this.$router.push('/data-explorer/INDICATOR_DATA?tab=browse');
        },
        goToDataStructures: function() {
            window.location.href = CI.base_url + '/data_structures';
        },
        onFileSelected: function(event) {
            var f = null;
            if (!event || event === null || (Array.isArray(event) && event.length === 0)) {
                f = null;
            } else if (event instanceof File) {
                f = event;
            } else if (event && event.target && event.target.files && event.target.files.length > 0) {
                f = event.target.files[0];
            } else if (Array.isArray(event) && event.length > 0 && event[0] instanceof File) {
                f = event[0];
            }
            this.file = f;
            this.errors = [];
            this.show_upload_chunk_progress = false;
            this.upload_chunk_percent = 0;
            this.resetCsvValidation();
        },
        saveImpliedFreqCode: function(value) {
            var vm = this;
            value = String(value != null ? value : vm.impliedFreqCode).trim();
            if (!value) {
                vm.errors = ['Series FREQ is required when the structure has no FREQ column.'];
                return Promise.reject(new Error('empty-freq'));
            }
            vm.savingImpliedFreq = true;
            vm.errors = [];
            return axios.post(CI.base_url + '/api/indicator_dsd/update_binding/' + vm.dataset_id, {
                implied_freq_code: value
            })
                .then(function() {
                    vm.savedImpliedFreqCode = value;
                    vm.impliedFreqCode = value;
                })
                .catch(function(err) {
                    var msg = (err.response && err.response.data && err.response.data.message) || 'Could not save series FREQ';
                    vm.errors = [msg];
                    throw err;
                })
                .then(function() {
                    vm.savingImpliedFreq = false;
                });
        },
        saveIndicatorIdValue: function(value) {
            var vm = this;
            value = String(value != null ? value : vm.selectedIndicatorIdTrimmed).trim();
            if (!value) {
                vm.errors = ['Indicator ID value cannot be empty.'];
                return Promise.reject(new Error('empty'));
            }
            vm.savingIndicatorId = true;
            vm.errors = [];
            var payload = { indicator_id_value: value };
            return axios.post(CI.base_url + '/api/indicator_dsd/update_binding/' + vm.dataset_id, payload)
                .then(function() {
                    vm.savedIndicatorIdValue = value;
                    vm.selectedIndicatorId = value;
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', 'Indicator ID value saved');
                    }
                })
                .catch(function(err) {
                    var msg = (err.response && err.response.data && err.response.data.message) || 'Could not save indicator ID value';
                    vm.errors = [msg];
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', msg);
                    }
                    throw err;
                })
                .then(function() {
                    vm.savingIndicatorId = false;
                });
        },
        ensureIndicatorIdSaved: function() {
            var vm = this;
            var value = vm.selectedIndicatorIdTrimmed;
            var chain = Promise.resolve();
            if (value !== String(vm.savedIndicatorIdValue || '').trim()) {
                chain = chain.then(function() { return vm.saveIndicatorIdValue(value); });
            }
            if (vm.needsImpliedFreq) {
                var freq = String(vm.impliedFreqCode || '').trim();
                if (freq !== String(vm.savedImpliedFreqCode || '').trim()) {
                    chain = chain.then(function() { return vm.saveImpliedFreqCode(freq); });
                }
            }
            return chain;
        },
        validateCsv: function() {
            var vm = this;
            if (vm.validateCsvBlockedHint) {
                vm.errors = [vm.validateCsvBlockedHint];
                return;
            }
            if (!vm.structureReady) {
                vm.errors = vm.structureBlockedReasons.length
                    ? vm.structureBlockedReasons.slice()
                    : ['Fix structure validation issues before importing data.'];
                return;
            }

            if (typeof ResumableChunkUploader === 'undefined') {
                vm.errors = ['Resumable upload is not available. Please reload the page.'];
                return;
            }

            vm.loading = true;
            vm.errors = [];
            vm.resetCsvValidation();
            vm.show_upload_chunk_progress = false;
            vm.upload_chunk_percent = 0;
            vm.importStatus = 'Preparing upload…';
            vm.importProgress = 5;

            var chain = Promise.resolve();
            if (vm.needsImpliedFreq) {
                var freq = String(vm.impliedFreqCode || '').trim();
                if (freq !== String(vm.savedImpliedFreqCode || '').trim()) {
                    chain = chain.then(function() { return vm.saveImpliedFreqCode(freq); });
                }
            }

            chain.then(function() {
                return ResumableChunkUploader.uploadFileChunks(vm.file, {
                    projectId: vm.dataset_id,
                    fileType: 'data',
                    maxRetries: 3,
                    retryDelay: 1000,
                    exponentialBackoff: true,
                    onInitializing: function(isInit) {
                        vm.show_upload_chunk_progress = false;
                        if (isInit) {
                            vm.upload_chunk_percent = 0;
                            vm.importStatus = 'Preparing upload…';
                            vm.importProgress = 5;
                        }
                    },
                    onProgress: function(p) {
                        vm.show_upload_chunk_progress = true;
                        vm.upload_chunk_percent = p.progress;
                        vm.importStatus = 'Uploading: ' + p.uploaded_chunks + '/' + p.total_chunks + ' (' + p.progress + '%)';
                        vm.importProgress = Math.round(p.progress * 0.7);
                    }
                });
            })
                .then(function(chunkResult) {
                    vm.show_upload_chunk_progress = false;
                    vm.importStatus = 'Validating CSV headers…';
                    vm.importProgress = 75;

                    var form = new FormData();
                    form.append('upload_id', chunkResult.upload_id);
                    form.append('keep_extra_csv_columns', vm.keepExtraCsvColumns ? '1' : '0');

                    return axios.post(CI.base_url + '/api/indicator_dsd/data_upload_prepare/' + vm.dataset_id, form, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    });
                })
                .then(function(res) {
                    var d = res.data || {};
                    if (d.status !== 'success') {
                        throw new Error(d.message || 'Validation failed');
                    }
                    vm.applyPrepareResult(d);
                    vm.importProgress = 100;
                    vm.importStatus = '';
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', 'CSV validated. Choose an indicator ID to import.');
                    }
                })
                .catch(function(err) {
                    var d = err.response && err.response.data ? err.response.data : {};
                    vm.headerError = d.message || err.message || 'Upload failed';
                    vm.missingInCsv = d.missing_in_csv || [];
                    vm.ignoredColumns = d.ignored_columns || d.extra_in_csv || [];
                    vm.errors = [vm.headerError];
                    if (vm.missingInCsv.length) {
                        vm.errors.push('Missing in CSV: ' + vm.missingInCsv.join(', '));
                    }
                    if (typeof EventBus !== 'undefined' && vm.headerError) {
                        EventBus.$emit('onFail', vm.headerError);
                    }
                })
                .then(function() {
                    vm.loading = false;
                    vm.show_upload_chunk_progress = false;
                    vm.upload_chunk_percent = 0;
                    if (!vm.csvValidated) {
                        vm.importProgress = 0;
                        vm.importStatus = '';
                    }
                });
        },
        importData: function() {
            var vm = this;
            if (!vm.csvValidated) {
                vm.errors = ['Validate the CSV file before importing.'];
                return;
            }
            if (!vm.selectedIndicatorIdTrimmed) {
                vm.errors = ['Choose an indicator ID from the CSV before importing.'];
                return;
            }

            vm.loading = true;
            vm.errors = [];
            vm.importStatus = 'Saving indicator ID…';
            vm.importProgress = 10;

            vm.ensureIndicatorIdSaved()
                .then(function() {
                    vm.importStatus = 'Importing data…';
                    vm.importProgress = 60;
                    var importBody = {
                        wait: true,
                        keep_extra_csv_columns: !!vm.keepExtraCsvColumns
                    };
                    if (vm.needsImpliedFreq && vm.impliedFreqCode) {
                        importBody.implied_freq_code = String(vm.impliedFreqCode).trim();
                    }
                    return axios.post(CI.base_url + '/api/indicator_dsd/data_upload_import/' + vm.dataset_id, importBody);
                })
                .then(function(res) {
                    var d = res.data || {};
                    if (d.status !== 'success') {
                        throw new Error(d.message || 'Import failed');
                    }
                    vm.importProgress = 100;
                    vm.importStatus = 'Import complete.';
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', 'Indicator data imported successfully');
                    }
                    vm.onImportComplete();
                })
                .catch(function(err) {
                    if (err && err.message === 'empty') {
                        return;
                    }
                    var d = err.response && err.response.data ? err.response.data : {};
                    var msg = d.message || err.message || 'Import failed';
                    vm.errors = [msg];
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', msg);
                    }
                })
                .then(function() {
                    vm.loading = false;
                    if (vm.importProgress < 100) {
                        vm.importStatus = '';
                        vm.importProgress = 0;
                    }
                });
        }
    },
    template: `
        <div class="indicator-dsd-import-component" :class="embedded ? 'pa-0' : 'pa-4'" :style="embedded ? '' : 'max-width: 720px; margin: 0 auto;'">
            <template v-if="!embedded">
                <h4 class="mb-2">{{ $t('import_indicator_data') || 'Import indicator data' }}</h4>
                <p class="caption grey--text mb-4">
                    Upload a CSV whose headers match the bound data structure, then choose which indicator ID to import.
                </p>
            </template>

            <v-progress-linear v-if="bindingLoading" indeterminate color="primary" class="mb-4"></v-progress-linear>

            <v-alert v-else-if="!canUpload" type="warning" dense outlined class="mb-4">
                <div>Attach a data structure before importing data.</div>
                <v-btn small color="primary" class="mt-2 mr-2" @click="goToDsd">Data structure overview</v-btn>
                <v-btn small outlined class="mt-2" @click="goToDataStructures">Data structures registry</v-btn>
            </v-alert>

            <template v-else>
                <v-alert v-if="!embedded" type="info" dense outlined class="mb-4">
                    Bound structure: <strong>{{ globalStructureLabel }}</strong>
                    ({{ binding.column_count }} columns)
                </v-alert>

                <v-alert v-if="!structureReady" type="warning" dense outlined class="mb-4">
                    <div class="subtitle-2 mb-1">Structure issues block import</div>
                    <ul class="caption pl-4 mb-2">
                        <li v-for="(r, i) in structureBlockedReasons" :key="'block-' + i">{{ r }}</li>
                    </ul>
                    <v-btn small color="primary" @click="goToDsd">Review structure</v-btn>
                </v-alert>

                <v-card v-if="structureReady && needsImpliedFreq" :outlined="!embedded" :flat="embedded" class="pa-4 mb-4">
                    <div class="subtitle-2 mb-2">{{ $t('dsd_series_freq_import') || 'Series frequency (FREQ)' }}</div>
                    <p class="caption grey--text mb-3">
                        {{ $t('dsd_series_freq_import_help') || 'This structure has no FREQ column. Choose the frequency code for TIME_PERIOD values in this CSV (e.g. A = annual, M = monthly), then validate the CSV.' }}
                    </p>
                    <v-select
                        v-model="impliedFreqCode"
                        :items="freqCodes"
                        item-text="label"
                        item-value="code"
                        label="FREQ"
                        outlined
                        dense
                        hide-details="auto"
                        style="max-width:320px;"
                        :disabled="loading || savingImpliedFreq"
                    >
                        <template v-slot:item="{ item }">
                            {{ item.label }} ({{ item.code }})
                        </template>
                        <template v-slot:selection="{ item }">
                            {{ item ? item.label + ' (' + item.code + ')' : impliedFreqCode }}
                        </template>
                    </v-select>
                    <div v-if="impliedFreqReady && savedImpliedFreqCode" class="caption green--text mt-2 mb-0">
                        Saved as series FREQ for this project.
                    </div>
                </v-card>

                <v-card v-if="structureReady" :outlined="!embedded" :flat="embedded" class="pa-4 mb-4">
                    <div class="subtitle-2 mb-2">Upload CSV</div>
                    <p class="caption grey--text mb-3">
                        The CSV must include all columns from the bound data structure.
                    </p>
                    <v-row align="center" class="ma-0 indicator-dsd-upload-row">
                        <v-col cols="12" sm class="py-0 pl-0 pr-sm-3">
                            <v-file-input
                                accept=".csv,text/csv"
                                :label="$t('select_csv_file') || 'Select CSV file'"
                                outlined
                                dense
                                clearable
                                show-size
                                hide-details
                                prepend-icon=""
                                prepend-inner-icon="mdi-file-delimited"
                                truncate-length="50"
                                class="mt-0 pt-0 mb-0"
                                :disabled="loading"
                                :value="file"
                                @change="onFileSelected"
                            ></v-file-input>
                        </v-col>
                        <v-col cols="12" sm="auto" class="py-0 px-0">
                            <v-btn
                                color="primary"
                                height="40"
                                :loading="loading && !csvValidated"
                                :disabled="!canValidateCsv"
                                @click="validateCsv"
                            >
                                {{ $t('upload_csv') || 'Upload CSV' }}
                            </v-btn>
                        </v-col>
                    </v-row>
                    <div v-if="validateCsvBlockedHint && file" class="caption grey--text mt-2">
                        {{ validateCsvBlockedHint }}
                    </div>
                </v-card>

                <v-card v-if="csvValidated" :outlined="!embedded" :flat="embedded" class="pa-4 mb-4">
                    <v-card v-if="hasExtraCsvColumns" outlined class="mb-4 pa-3">
                        <div class="subtitle-2 mb-1">
                            {{ ignoredColumns.length }} extra CSV column(s)
                        </div>
                        <p class="caption grey--text mb-2">
                            Not in the data structure: <code>{{ ignoredColumnsPreview }}</code>
                        </p>
                        <p class="caption grey--text mb-2 mb-md-3">
                            The uploaded file is kept on the server. Choose which columns to load into published data:
                        </p>
                        <v-radio-group
                            v-model="keepExtraCsvColumns"
                            row
                            dense
                            hide-details
                            class="mt-0"
                            :disabled="loading"
                        >
                            <v-radio :value="false" :label="$t('csv_extra_remove') || 'Do not import'"></v-radio>
                            <v-radio :value="true" :label="$t('csv_extra_keep') || 'Import into published data'"></v-radio>
                        </v-radio-group>
                    </v-card>

                    <v-alert v-if="needsImpliedFreq && impliedFreqReady" type="info" dense outlined class="mb-4">
                        Series FREQ: <strong>{{ impliedFreqCode }}</strong>
                        <span v-if="savedImpliedFreqCode"> (saved on project)</span>
                    </v-alert>

                    <div class="subtitle-2 mb-2">Indicator ID for import</div>
                    <p class="caption grey--text mb-3">
                        Choose the value from column <code>{{ indicatorIdColumn }}</code>. Only rows with this ID will be imported.
                    </p>

                    <v-alert v-if="seriesIdno && seriesIdnoInCsv" type="info" dense outlined class="mb-3">
                        Series ID from metadata (<strong>{{ seriesIdno }}</strong>) was found in this CSV.
                    </v-alert>

                    <v-alert v-if="seriesIdno && !seriesIdnoInCsv && indicatorValues.length" type="warning" dense outlined class="mb-3">
                        Series ID from metadata (<strong>{{ seriesIdno }}</strong>) was not found in this CSV. Pick a value from the list below.
                    </v-alert>

                    <v-alert v-if="!indicatorValues.length" type="warning" dense outlined class="mb-3">
                        No indicator ID values were found in the CSV column.
                    </v-alert>

                    <v-alert v-if="indicatorValuesTruncated" type="warning" dense outlined class="mb-3">
                        More than {{ indicatorValuesLimit }} distinct indicator IDs were found. Only the first {{ indicatorValuesLimit }} are listed; you can type an ID manually below.
                    </v-alert>

                    <v-combobox
                        v-model="selectedIndicatorId"
                        :items="indicatorValueCodes"
                        :item-text="formatIndicatorItemLabel"
                        label="Indicator ID"
                        hint="Select from the CSV or type an indicator ID"
                        persistent-hint
                        outlined
                        dense
                        clearable
                        hide-details="auto"
                        style="max-width:480px;"
                        :disabled="loading || savingIndicatorId"
                    ></v-combobox>

                    <v-btn
                        color="primary"
                        class="mt-4"
                        :loading="loading"
                        :disabled="!canImport"
                        @click="importData"
                    >
                        Import data
                    </v-btn>
                    <div v-if="!selectedIndicatorIdTrimmed" class="caption grey--text mt-2">
                        Choose an indicator ID before importing.
                    </div>
                    <div v-if="needsImpliedFreq && !impliedFreqReady" class="caption grey--text mt-2">
                        Choose series FREQ before importing.
                    </div>
                </v-card>
            </template>

            <v-alert v-for="(e, i) in errors" :key="'err-' + i" type="error" dense outlined class="mb-2">{{ e }}</v-alert>

            <v-progress-linear
                v-if="show_upload_chunk_progress"
                :value="upload_chunk_percent"
                color="primary"
                class="mt-2"
            ></v-progress-linear>
            <v-progress-linear
                v-else-if="importProgress > 0 && importProgress < 100"
                :value="importProgress"
                color="primary"
                class="mt-2"
            ></v-progress-linear>
            <div v-if="importStatus" class="caption mt-1">{{ importStatus }}</div>
        </div>
    `
});
