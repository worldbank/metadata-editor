/**
 * Generate or regenerate a dat/micro external resource (full page).
 */
const VueExternalResourcesGenerateMicrodata = Vue.component('external-resources-generate-microdata', {
    props: {
        resource_id: { type: [Number, String], default: null }
    },
    data() {
        return {
            export_format: 'dta',
            stata_version: 14,
            stata_version_options: [8, 9, 10, 11, 12, 13, 14, 15].map(v => ({ value: v, label: 'Stata ' + v })),
            available_formats: [
                { value: 'csv', label: 'CSV' },
                { value: 'dta', label: 'Stata (DTA)' },
                { value: 'sav', label: 'SPSS (SAV)' },
                { value: 'json', label: 'JSON' },
                { value: 'xpt', label: 'SAS (XPT)' }
            ],
            selected_file_ids: [],
            zip_option: true,
            overwrite: false,
            refresh_description: false,
            state: 'config',
            progress_message: '',
            error_message: null,
            info_message: null,
            exists_resource: null,
            result: null,
            prefill_loaded: false
        };
    },
    created() {
        this.initializeForm();
    },
    watch: {
        resource_id() {
            this.initializeForm();
        },
        dataFiles() {
            this.syncSelectedWithAvailableFiles();
        },
        selected_file_ids() {
            this.applyZipDefaultForSelection();
        }
    },
    computed: {
        projectId() {
            return this.$store.state.project_id;
        },
        dataFiles() {
            return this.$store.state.data_files || [];
        },
        isRegenerate() {
            return this.resource_id !== null && this.resource_id !== '' && Number(this.resource_id) > 0;
        },
        pageTitle() {
            return this.isRegenerate
                ? (this.$t('regenerate_microdata_resource') || 'Regenerate microdata resource')
                : (this.$t('generate_microdata_resource') || 'Generate microdata resource');
        },
        isProjectEditable() {
            return this.$store.getters.getUserHasEditAccess;
        },
        allFilesSelected() {
            return this.dataFiles.length > 0
                && this.selected_file_ids.length === this.dataFiles.length;
        },
        someFilesSelected() {
            return this.selected_file_ids.length > 0
                && this.selected_file_ids.length < this.dataFiles.length;
        },
        multipleFilesSelected() {
            return this.selected_file_ids.length > 1;
        },
        zipRequired() {
            return this.multipleFilesSelected;
        },
        effectiveZip() {
            return this.zipRequired || this.zip_option;
        },
        showZipOption() {
            return this.selected_file_ids.length > 0;
        },
        zipCheckboxLabel() {
            if (this.zipRequired) {
                return this.$t('batch_export_zip_option') || 'Zip all exported files into a single ZIP';
            }
            return this.$t('microdata_zip_single_recommended') || 'Zip exported file (recommended)';
        }
    },
    methods: {
        initializeForm() {
            this.state = 'config';
            this.progress_message = '';
            this.error_message = null;
            this.info_message = null;
            this.exists_resource = null;
            this.result = null;
            this.export_format = 'dta';
            this.stata_version = 14;
            this.zip_option = true;
            this.overwrite = false;
            this.refresh_description = !!this.isRegenerate;
            this.selected_file_ids = this.dataFiles.map(f => f.file_id);
            this.prefill_loaded = false;

            if (this.isRegenerate) {
                this.loadRegeneratePrefill();
            }
        },
        loadRegeneratePrefill() {
            const vm = this;
            const url = CI.base_url + '/api/resources/microdata_status/' + this.projectId;
            axios.get(url)
                .then(function(response) {
                    if (!response.data || response.data.status !== 'success') {
                        return;
                    }
                    const entry = (response.data.microdata_resources || []).find(function(row) {
                        return row.resource && String(row.resource.id) === String(vm.resource_id);
                    });
                    if (!entry) {
                        return;
                    }
                    if (entry.export_format) {
                        vm.export_format = entry.export_format;
                    }
                    if (entry.export_version != null && entry.export_version !== '') {
                        vm.stata_version = Number(entry.export_version) || 14;
                    }
                    const fileIds = (entry.links || [])
                        .filter(function(link) { return link.link_type === 'generated' && link.file_id; })
                        .map(function(link) { return link.file_id; });
                    if (fileIds.length > 0) {
                        vm.selected_file_ids = fileIds;
                    }
                    vm.prefill_loaded = true;
                })
                .catch(function() {
                    vm.prefill_loaded = true;
                });
        },
        cancel() {
            if (this.state === 'running') {
                return;
            }
            router.push('/external-resources');
        },
        goToResource() {
            if (this.result && this.result.resource_id) {
                router.push('/external-resources/' + this.result.resource_id);
                return;
            }
            if (this.result && this.result.resource && this.result.resource.id) {
                router.push('/external-resources/' + this.result.resource.id);
                return;
            }
            router.push('/external-resources');
        },
        goToExistingResource() {
            if (this.exists_resource && this.exists_resource.id) {
                router.push('/external-resources/' + this.exists_resource.id);
            }
        },
        syncSelectedWithAvailableFiles() {
            const available = this.dataFiles.map(f => f.file_id);
            this.selected_file_ids = this.selected_file_ids.filter(function(id) {
                return available.indexOf(id) !== -1;
            });
            if (this.selected_file_ids.length === 0 && available.length > 0 && !this.isRegenerate) {
                this.selected_file_ids = available.slice();
            }
        },
        toggleSelectAllFiles() {
            if (this.allFilesSelected) {
                this.selected_file_ids = [];
            } else {
                this.selected_file_ids = this.dataFiles.map(f => f.file_id);
            }
        },
        applyZipDefaultForSelection() {
            if (this.zipRequired) {
                this.zip_option = true;
            }
        },
        async startGenerate() {
            if (!this.isProjectEditable) {
                return;
            }
            if (this.dataFiles.length === 0) {
                this.error_message = this.$t('microdata_no_data_files') || 'No data files in this project.';
                return;
            }

            const file_ids = this.selected_file_ids.filter(Boolean);

            if (!file_ids || file_ids.length === 0) {
                this.error_message = this.$t('microdata_select_at_least_one_file') || 'Select at least one data file.';
                return;
            }

            const payload = {
                export_format: this.export_format,
                zip: this.effectiveZip,
                max_wait_seconds: 900
            };

            if (this.export_format === 'dta') {
                payload.export_version = this.stata_version;
            }
            payload.file_ids = file_ids;
            if (!this.isRegenerate && this.overwrite) {
                payload.overwrite = true;
            }
            if (this.isRegenerate && this.refresh_description) {
                payload.refresh_description = true;
            }

            this.state = 'running';
            this.error_message = null;
            this.info_message = null;
            this.exists_resource = null;
            this.progress_message = this.$t('microdata_generating') || 'Exporting data files and creating resource…';

            const url = this.isRegenerate
                ? CI.base_url + '/api/resources/regenerate/' + this.projectId + '/' + this.resource_id
                : CI.base_url + '/api/resources/generate_microdata/' + this.projectId;

            try {
                const response = await axios.post(url, payload);
                const data = response.data || {};

                if (data.status === 'failed') {
                    throw new Error(data.message || 'Generation failed');
                }

                if (data.status === 'exists') {
                    this.state = 'config';
                    this.exists_resource = data.resource || null;
                    this.info_message = this.$t('microdata_resource_exists_hint') || (
                        (data.message || this.$t('microdata_resource_exists'))
                        + ' '
                        + (this.$t('microdata_resource_exists_action') || 'Select another format, or check Replace existing resource for this format.')
                    );
                    return;
                }

                this.state = 'done';
                this.result = data;
                this.progress_message = this.$t('microdata_generate_success') || 'Microdata resource created successfully.';
                await this.$store.dispatch('loadExternalResources', { dataset_id: this.projectId });
            } catch (e) {
                this.state = 'config';
                this.error_message = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : (e.message || 'Generation failed');
            }
        }
    },
    template: `
        <div class="external-resources-generate-microdata container-fluid pt-5 mt-5">
            <v-card>
                <v-card-title class="d-flex justify-space-between align-center pb-0">
                    <div>{{ pageTitle }}</div>
                    <div>
                        <v-btn v-if="state !== 'running'" small text @click="cancel">{{ $t('cancel') }}</v-btn>
                    </div>
                </v-card-title>
                <v-card-subtitle class="pt-3 pb-4" style="line-height: 1.5;">
                    {{ $t('microdata_generate_page_description') }}
                </v-card-subtitle>

                <v-card-text>
                    <v-alert v-if="dataFiles.length === 0" type="warning" dense outlined>
                        {{ $t('microdata_no_data_files') || 'No data files in this project.' }}
                    </v-alert>

                    <template v-if="state === 'config' && dataFiles.length > 0">
                        <v-alert v-if="info_message" type="info" dense outlined class="mb-4">
                            <div>{{ info_message }}</div>
                            <v-btn
                                v-if="exists_resource && exists_resource.id"
                                small
                                text
                                color="primary"
                                class="mt-2 px-0"
                                @click="goToExistingResource"
                            >{{ $t('view_existing_resource') || 'View existing resource' }}</v-btn>
                        </v-alert>
                        <v-alert v-if="error_message" type="error" dense class="mb-4">{{ error_message }}</v-alert>

                        <div class="mb-4">
                            <label class="text-body-1 font-weight-medium d-block mb-2">
                                {{ $t('microdata_data_files') || 'Data files' }}
                                <span class="text-caption text--secondary ml-2">({{ selected_file_ids.length }} / {{ dataFiles.length }} {{ $t('selected') || 'selected' }})</span>
                            </label>
                            <div class="border rounded" style="max-height: 280px; overflow-y: auto;">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="bg-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th style="width: 48px;">
                                                <v-checkbox
                                                    :value="allFilesSelected"
                                                    :indeterminate="someFilesSelected"
                                                    @change="toggleSelectAllFiles"
                                                    :disabled="!isProjectEditable"
                                                    hide-details dense class="mt-0"
                                                ></v-checkbox>
                                            </th>
                                            <th>{{ $t('file') || 'File' }}</th>
                                            <th>{{ $t('id') || 'ID' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="file in dataFiles" :key="file.file_id">
                                            <td>
                                                <v-checkbox
                                                    v-model="selected_file_ids"
                                                    :value="file.file_id"
                                                    :disabled="!isProjectEditable"
                                                    hide-details dense class="mt-0"
                                                ></v-checkbox>
                                            </td>
                                            <td>{{ file.file_name || file.file_id }}</td>
                                            <td class="text-muted">{{ file.file_id }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mb-4 d-flex flex-wrap align-center" style="gap: 16px;">
                            <div style="min-width: 200px; flex: 1; max-width: 320px;">
                                <label class="text-body-2 font-weight-medium d-block mb-1">{{ $t('batch_export_format') || 'Format' }}</label>
                                <v-select
                                    v-model="export_format"
                                    :items="available_formats"
                                    item-text="label"
                                    item-value="value"
                                    :disabled="!isProjectEditable"
                                    outlined
                                    dense
                                    hide-details
                                ></v-select>
                            </div>
                            <div v-if="export_format === 'dta'" style="min-width: 140px; max-width: 180px;">
                                <label class="text-body-2 font-weight-medium d-block mb-1">{{ $t('stata_version') || 'Stata version' }}</label>
                                <v-select
                                    v-model="stata_version"
                                    :items="stata_version_options"
                                    item-text="label"
                                    item-value="value"
                                    :disabled="!isProjectEditable"
                                    outlined
                                    dense
                                    hide-details
                                ></v-select>
                            </div>
                        </div>

                        <v-checkbox
                            v-if="showZipOption"
                            v-model="zip_option"
                            :label="zipCheckboxLabel"
                            :disabled="!isProjectEditable || zipRequired"
                            hide-details dense class="mt-0 mb-2 v-font-weight-normal"
                        ></v-checkbox>

                        <v-checkbox
                            v-if="!isRegenerate"
                            v-model="overwrite"
                            :label="$t('microdata_overwrite_existing') || 'Replace existing resource for this format'"
                            :disabled="!isProjectEditable"
                            hide-details dense class="mt-0 mb-2 v-font-weight-normal"
                        ></v-checkbox>

                        <v-checkbox
                            v-if="isRegenerate"
                            v-model="refresh_description"
                            :label="$t('microdata_refresh_description') || 'Refresh description from data files'"
                            :disabled="!isProjectEditable"
                            hide-details dense class="mt-0 mb-2 v-font-weight-normal"
                        ></v-checkbox>

                        <div class="mt-4">
                            <v-btn
                                color="primary"
                                :disabled="!isProjectEditable || dataFiles.length === 0"
                                @click="startGenerate"
                            >
                                {{ isRegenerate ? ($t('regenerate') || 'Regenerate') : ($t('generate') || 'Generate') }}
                            </v-btn>
                            <v-btn class="ml-2" text @click="cancel">{{ $t('cancel') }}</v-btn>
                        </div>
                    </template>

                    <template v-if="state === 'running'">
                        <div class="text-center py-8">
                            <v-progress-circular indeterminate color="primary" size="48" class="mb-4"></v-progress-circular>
                            <div class="text-body-1">{{ progress_message }}</div>
                            <div class="text-caption text--secondary mt-2">{{ $t('microdata_generate_wait') || 'This may take several minutes.' }}</div>
                        </div>
                    </template>

                    <template v-if="state === 'done'">
                        <v-alert :type="result && result.status === 'exists' ? 'info' : 'success'" dense class="mb-4">
                            {{ progress_message }}
                        </v-alert>
                        <div v-if="result && result.resource" class="text-body-1 mb-4">
                            <strong>{{ result.resource.title }}</strong>
                            <div class="text-caption text--secondary">{{ result.resource.filename }}</div>
                        </div>
                        <v-btn color="primary" @click="goToResource">
                            {{ $t('view_resource') || 'View resource' }}
                        </v-btn>
                        <v-btn class="ml-2" text @click="cancel">{{ $t('back_to_list') || 'Back to list' }}</v-btn>
                    </template>
                </v-card-text>
            </v-card>
        </div>
    `
});
