Vue.component('import-report', {
    data: function () {
        return {
            loading: false,
            error: null,
            report: null,
            poll_timer: null,
            retrying_file_id: null
        };
    },
    mounted: function () {
        this.loadReport();
        this.startPolling();
    },
    beforeDestroy: function () {
        this.stopPolling();
    },
    computed: {
        ProjectID: function () {
            return this.$store.state.project_id;
        },
        ProjectType: function () {
            return this.$store.state.project_type;
        },
        hasReport: function () {
            return !!this.report;
        },
        overallStatus: function () {
            return this.report && this.report.overall_status ? this.report.overall_status : '';
        },
        overallColor: function () {
            var status = this.overallStatus;
            if (status === 'complete') {
                return 'success';
            }
            if (status === 'csv_in_progress') {
                return 'info';
            }
            if (status === 'csv_failed') {
                return 'error';
            }
            if (status === 'complete_with_warnings') {
                return 'warning';
            }
            return 'grey';
        },
        microdata: function () {
            return this.report && this.report.extras && this.report.extras.microdata
                ? this.report.extras.microdata
                : null;
        },
        geospatial: function () {
            return this.report && this.report.extras && this.report.extras.geospatial
                ? this.report.extras.geospatial
                : null;
        },
        indicator: function () {
            return this.report && this.report.extras && this.report.extras.indicator
                ? this.report.extras.indicator
                : null;
        },
        hasPendingCsv: function () {
            if (!this.microdata || !this.microdata.files) {
                return false;
            }
            return this.microdata.files.some(function (file) {
                return ['queued', 'generating', 'processing', 'pending'].indexOf(file.csv_status) !== -1;
            });
        },
        dataFileHeaders: function () {
            return [
                { text: this.$t('file_name') || 'File name', value: 'file_name' },
                { text: this.$t('source_file') || 'Source', value: 'physical' },
                { text: this.$t('working_csv') || 'Working CSV', value: 'csv_status' },
                { text: this.$t('actions') || '', value: 'actions', sortable: false, width: '120px' }
            ];
        }
    },
    methods: {
        loadReport: async function () {
            var vm = this;
            vm.loading = true;
            vm.error = null;
            try {
                var response = await axios.get(CI.base_url + '/api/importproject/report/' + this.ProjectID);
                vm.report = response.data && response.data.report ? response.data.report : null;
            } catch (e) {
                vm.error = (e.response && e.response.data && (e.response.data.errors || e.response.data.message))
                    ? (e.response.data.errors || e.response.data.message)
                    : (e.message || String(e));
            } finally {
                vm.loading = false;
            }
        },
        startPolling: function () {
            var vm = this;
            this.stopPolling();
            this.poll_timer = setInterval(function () {
                if (vm.hasPendingCsv) {
                    vm.loadReport();
                }
            }, 4000);
        },
        stopPolling: function () {
            if (this.poll_timer) {
                clearInterval(this.poll_timer);
                this.poll_timer = null;
            }
        },
        retryCsv: async function (file) {
            if (!file || !file.file_id) {
                return;
            }
            var vm = this;
            vm.retrying_file_id = file.file_id;
            try {
                var form = new FormData();
                form.append('file_id', file.file_id);
                var response = await axios.post(CI.base_url + '/api/importproject/retry_csv/' + this.ProjectID, form);
                if (response.data && response.data.report) {
                    vm.report = response.data.report;
                } else {
                    await vm.loadReport();
                }
            } catch (e) {
                vm.error = (e.response && e.response.data && (e.response.data.errors || e.response.data.message))
                    ? (e.response.data.errors || e.response.data.message)
                    : (e.message || String(e));
            } finally {
                vm.retrying_file_id = null;
            }
        },
        momentDate: function (value) {
            if (!value) {
                return '';
            }
            return moment(value).format('YYYY-MM-DD HH:mm:ss');
        },
        statusLabel: function (status) {
            var map = {
                complete: this.$t('import_report_complete') || 'Complete',
                complete_with_warnings: this.$t('import_report_complete_with_warnings') || 'Complete with warnings',
                csv_in_progress: this.$t('import_report_csv_in_progress') || 'Generating working CSV',
                csv_failed: this.$t('import_report_csv_failed') || 'CSV generation failed'
            };
            return map[status] || status;
        },
        csvStatusLabel: function (status) {
            var map = {
                present: this.$t('import_report_csv_present') || 'Present',
                queued: this.$t('import_report_csv_queued') || 'Queued',
                generating: this.$t('import_report_csv_generating') || 'Generating',
                processing: this.$t('import_report_csv_generating') || 'Generating',
                pending: this.$t('import_report_csv_queued') || 'Queued',
                failed: this.$t('import_report_csv_failed') || 'Failed',
                queue_failed: this.$t('import_report_csv_queue_failed') || 'Could not queue',
                unmatched: this.$t('import_report_unmatched') || 'Not found',
                missing: this.$t('import_report_csv_missing') || 'Missing',
                skipped: this.$t('skipped') || 'Skipped'
            };
            return map[status] || status;
        },
        csvStatusColor: function (status) {
            if (status === 'present') {
                return 'success';
            }
            if (status === 'failed' || status === 'queue_failed' || status === 'unmatched') {
                return 'error';
            }
            if (status === 'queued' || status === 'generating' || status === 'processing' || status === 'pending') {
                return 'info';
            }
            return 'grey';
        },
        canRetryCsv: function (file) {
            return file && ['failed', 'queue_failed', 'missing'].indexOf(file.csv_status) !== -1
                && file.physical && file.source_ext && file.source_ext !== 'csv';
        }
    },
    template: `
        <div class="import-report-component m-3 mt-5">
            <h3 class="mb-4">{{ $t('import_report') || 'Import report' }}</h3>

            <v-alert v-if="error" type="error" dense class="mb-4">{{ error }}</v-alert>

            <div v-if="loading && !report" class="text-center py-8">
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
            </div>

            <v-card v-else-if="!hasReport" outlined class="pa-6">
                <div class="text--secondary">
                    {{ $t('import_report_empty') || 'No package import has been recorded for this project.' }}
                </div>
            </v-card>

            <div v-else>
                <v-card outlined class="mb-4">
                    <v-card-text>
                        <div class="d-flex align-center mb-3">
                            <v-chip :color="overallColor" dark small class="mr-3">{{ statusLabel(overallStatus) }}</v-chip>
                            <span class="text--secondary">{{ momentDate(report.imported_at) }}</span>
                        </div>
                        <v-row dense>
                            <v-col cols="12" md="4">
                                <div class="text-caption text--secondary">{{ $t('type') || 'Type' }}</div>
                                <div>{{ report.package && report.package.type ? report.package.type : ProjectType }}</div>
                            </v-col>
                            <v-col cols="12" md="4">
                                <div class="text-caption text--secondary">{{ $t('idno') || 'IDNO' }}</div>
                                <div>{{ report.package && report.package.idno ? report.package.idno : '—' }}</div>
                            </v-col>
                            <v-col cols="12" md="4">
                                <div class="text-caption text--secondary">{{ $t('import_report_source') || 'Source' }}</div>
                                <div>{{ report.source || '—' }}</div>
                            </v-col>
                            <v-col cols="12" md="4">
                                <div class="text-caption text--secondary">{{ $t('import_report_metadata_file') || 'Metadata file' }}</div>
                                <div>{{ report.metadata && (report.metadata.file_path || report.metadata.file_used) ? (report.metadata.file_path || report.metadata.file_used) : '—' }}</div>
                            </v-col>
                            <v-col cols="12" md="4">
                                <div class="text-caption text--secondary">{{ $t('external-resources') || 'Resources' }}</div>
                                <div>{{ report.resources && report.resources.imported != null ? report.resources.imported : 0 }}</div>
                            </v-col>
                            <v-col cols="12" md="4">
                                <div class="text-caption text--secondary">{{ $t('thumbnail') || 'Thumbnail' }}</div>
                                <div>{{ report.thumbnail ? report.thumbnail : '—' }}</div>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <v-card v-if="microdata" outlined class="mb-4">
                    <v-card-title class="subtitle-1">{{ $t('data-files') || 'Data files' }}</v-card-title>
                    <v-card-text>
                        <v-row dense class="mb-3">
                            <v-col cols="6" md="3">{{ $t('import_report_data_files_imported') || 'Data files imported' }}: {{ microdata.data_files_imported || 0 }}</v-col>
                            <v-col cols="6" md="3">{{ $t('import_report_variables_imported') || 'Variables imported' }}: {{ microdata.variables_imported || 0 }}</v-col>
                            <v-col cols="6" md="3">{{ $t('import_report_variables_skipped') || 'Variables skipped' }}: {{ microdata.variables_skipped || 0 }}</v-col>
                            <v-col cols="6" md="3">{{ $t('import_report_unmatched') || 'Unmatched' }}: {{ microdata.unmatched || 0 }}</v-col>
                        </v-row>
                        <v-alert v-if="microdata.fastapi_online === false" type="error" dense class="mb-3">
                            {{ $t('import_report_fastapi_offline') || 'FastAPI is not running. Working CSV was not generated.' }}
                        </v-alert>
                        <v-alert v-else-if="microdata.errors && microdata.errors.length" type="warning" dense class="mb-3">
                            <div v-for="(err, idx) in microdata.errors" :key="'md-err-' + idx">{{ err }}</div>
                        </v-alert>
                        <v-alert v-if="hasPendingCsv" type="info" dense class="mb-3">
                            {{ $t('import_report_csv_in_progress') || 'Generating working CSV' }}
                        </v-alert>
                        <v-data-table
                            :headers="dataFileHeaders"
                            :items="microdata.files || []"
                            :items-per-page="25"
                            hide-default-footer
                            dense
                            class="elevation-0"
                        >
                            <template v-slot:item.physical="{ item }">
                                {{ item.physical || '—' }}
                            </template>
                            <template v-slot:item.csv_status="{ item }">
                                <v-chip x-small :color="csvStatusColor(item.csv_status)" dark>
                                    {{ csvStatusLabel(item.csv_status) }}
                                </v-chip>
                                <div v-if="item.csv_job && item.csv_job.error_message" class="caption error--text">
                                    {{ item.csv_job.error_message }}
                                </div>
                            </template>
                            <template v-slot:item.actions="{ item }">
                                <v-btn
                                    v-if="canRetryCsv(item)"
                                    x-small
                                    text
                                    color="primary"
                                    :loading="retrying_file_id === item.file_id"
                                    @click="retryCsv(item)"
                                >{{ $t('retry') || 'Retry' }}</v-btn>
                            </template>
                        </v-data-table>

                        <div v-if="microdata.variables_skipped_detail && microdata.variables_skipped_detail.length" class="mt-4">
                            <div class="subtitle-2 mb-1">{{ $t('import_report_skipped_variables') || 'Skipped variables' }}</div>
                            <ul class="mb-0">
                                <li v-for="(detail, idx) in microdata.variables_skipped_detail" :key="'var-skip-' + idx">{{ detail }}</li>
                            </ul>
                        </div>
                        <div v-if="microdata.unmatched_on_disk && microdata.unmatched_on_disk.length" class="mt-4">
                            <div class="subtitle-2 mb-1">{{ $t('import_report_unmatched_on_disk') || 'Files in data/ not linked to a data file' }}</div>
                            <ul class="mb-0">
                                <li v-for="(name, idx) in microdata.unmatched_on_disk" :key="'disk-' + idx">{{ name }}</li>
                            </ul>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card v-if="geospatial && (geospatial.features_imported != null || geospatial.characteristics_imported != null)" outlined class="mb-4">
                    <v-card-title class="subtitle-1">{{ $t('feature_catalogue') || 'Feature catalogue' }}</v-card-title>
                    <v-card-text>
                        <div>{{ $t('import_report_features_imported') || 'Features imported' }}: {{ geospatial.features_imported || 0 }}</div>
                        <div>{{ $t('import_report_characteristics_imported') || 'Characteristics imported' }}: {{ geospatial.characteristics_imported || 0 }}</div>
                    </v-card-text>
                </v-card>

                <v-card v-if="indicator" outlined class="mb-4">
                    <v-card-title class="subtitle-1">{{ $t('indicator_data') || 'Indicator data' }}</v-card-title>
                    <v-card-text>
                        <div>{{ $t('import_report_indicator_file') || 'Data file' }}: {{ indicator.data_file_present ? (indicator.data_file || 'yes') : ($t('none') || 'None') }}</div>
                        <div v-if="indicator.note" class="text--secondary mt-2">{{ indicator.note }}</div>
                    </v-card-text>
                </v-card>
            </div>
        </div>
    `
});
