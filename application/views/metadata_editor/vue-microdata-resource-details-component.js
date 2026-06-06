/**
 * Microdata package panel: provenance, linked data files, publishable file, actions.
 */
Vue.component('microdata-resource-details', {
    props: {
        resourceId: { type: [Number, String], required: true },
        sourceType: { type: String, default: null },
        bundleType: { type: String, default: null },
        filename: { type: String, default: null },
        editableLinks: { type: Boolean, default: false },
        selectedFileIds: { type: Array, default: () => [] },
        canEdit: { type: Boolean, default: false }
    },
    data() {
        return {
            loading: false,
            load_error: null,
            links: [],
            staleness: null,
            export_format: null,
            export_version: null,
            generated_at: null,
            detail_source_type: null,
            detail_bundle_type: null,
            file_exists: null,
            file_info: null
        };
    },
    computed: {
        projectId() {
            return this.$store.state.project_id;
        },
        isGenerated() {
            const st = this.detail_source_type || this.sourceType;
            return st === 'generated';
        },
        panelTitle() {
            return this.isGenerated
                ? (this.$t('microdata_package_generated') || 'Microdata package')
                : (this.$t('microdata_section_manual') || 'Microdata');
        },
        sourceTypeLabel() {
            return this.isGenerated
                ? (this.$t('generated') || 'Generated')
                : (this.$t('manual') || 'Manual');
        },
        formatLabel() {
            if (!this.export_format) {
                return '—';
            }
            const fmt = String(this.export_format).toLowerCase();
            const labels = {
                csv: 'CSV',
                dta: 'Stata (DTA)',
                sav: 'SPSS (SAV)',
                json: 'JSON',
                xpt: 'SAS (XPT)'
            };
            let label = labels[fmt] || fmt.toUpperCase();
            if (fmt === 'dta' && this.export_version) {
                label += ' ' + this.export_version;
            }
            return label;
        },
        bundleTypeLabel() {
            const bt = this.detail_bundle_type || this.bundleType;
            if (!bt) {
                return '—';
            }
            return bt === 'zip'
                ? (this.$t('bundle_type_zip') || 'ZIP')
                : (this.$t('bundle_type_single') || 'Single file');
        },
        stalenessChip() {
            if (!this.staleness || !this.staleness.status) {
                return null;
            }
            const status = this.staleness.status;
            if (status === 'current') {
                return { text: this.$t('microdata_status_current') || 'Current', color: 'success' };
            }
            if (status === 'stale') {
                return { text: this.$t('microdata_status_stale') || 'Stale', color: 'warning' };
            }
            if (status === 'missing_file') {
                return { text: this.$t('microdata_status_missing_file') || 'Missing file', color: 'error' };
            }
            return { text: status, color: 'grey' };
        },
        generatedAtLabel() {
            if (!this.generated_at) {
                return null;
            }
            return moment.unix(this.generated_at).format('YYYY-MM-DD HH:mm');
        },
        downloadUrl() {
            if (!this.resourceId || !this.projectId || !this.filename || this.isUrlFilename) {
                return null;
            }
            return CI.base_url + '/api/resources/download/' + this.projectId + '/' + this.resourceId;
        },
        isUrlFilename() {
            if (!this.filename) {
                return false;
            }
            return /^https?:\/\//i.test(String(this.filename).trim());
        },
        localSelectedFileIds: {
            get() {
                return this.selectedFileIds;
            },
            set(val) {
                this.$emit('update:selectedFileIds', val);
            }
        }
    },
    watch: {
        resourceId: {
            immediate: true,
            handler() {
                this.loadDetails();
            }
        },
        filename() {
            if (this.isGenerated) {
                this.loadFileInfo();
            }
        }
    },
    methods: {
        loadDetails() {
            if (!this.resourceId || !this.projectId) {
                return;
            }
            const vm = this;
            vm.loading = true;
            vm.load_error = null;
            const url = CI.base_url + '/api/resources/datafile_links/' + this.projectId + '/' + this.resourceId;
            axios.get(url)
                .then(function(response) {
                    const data = response.data || {};
                    if (data.status !== 'success') {
                        vm.load_error = data.message || 'Failed to load';
                        return;
                    }
                    vm.links = data.links || [];
                    vm.staleness = data.staleness || null;
                    vm.export_format = data.export_format || null;
                    vm.export_version = data.export_version || null;
                    vm.generated_at = data.generated_at || null;
                    vm.detail_source_type = data.source_type || null;
                    vm.detail_bundle_type = data.bundle_type || null;
                    if (vm.isGenerated) {
                        vm.loadFileInfo();
                    }
                })
                .catch(function(err) {
                    vm.load_error = (err.response && err.response.data && err.response.data.message)
                        ? err.response.data.message
                        : (err.message || 'Failed to load');
                    vm.links = [];
                })
                .finally(function() {
                    vm.loading = false;
                });
        },
        loadFileInfo() {
            if (!this.resourceId || !this.projectId || !this.filename || this.isUrlFilename) {
                this.file_exists = null;
                this.file_info = null;
                return;
            }
            const vm = this;
            const url = CI.base_url + '/api/resources/file/' + this.projectId + '/' + this.resourceId;
            axios.get(url)
                .then(function(response) {
                    if (response.data && response.data.status === 'success' && response.data.file_info) {
                        vm.file_info = response.data.file_info;
                        vm.file_exists = response.data.file_info.exists;
                    } else {
                        vm.file_exists = false;
                        vm.file_info = null;
                    }
                })
                .catch(function() {
                    vm.file_exists = false;
                    vm.file_info = null;
                });
        },
        formatFileSize(bytes) {
            if (!bytes || bytes === 0) {
                return '';
            }
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        dataFileLabel(link) {
            if (link.file_name) {
                return link.file_name;
            }
            const files = this.$store.state.data_files || [];
            const found = files.find(function(f) { return f.file_id === link.file_id; });
            return found ? (found.file_name || link.file_id) : link.file_id;
        },
        linkTypeLabel(linkType) {
            if (linkType === 'generated') {
                return this.$t('link_type_generated') || 'Generated';
            }
            if (linkType === 'manual') {
                return this.$t('link_type_manual') || 'Manual';
            }
            if (linkType === 'associated') {
                return this.$t('link_type_associated') || 'Associated';
            }
            return linkType || '—';
        },
        formatLinkFormat(link) {
            if (!link.export_format) {
                return '—';
            }
            return String(link.export_format).toUpperCase();
        },
        formatLinkVersion(link) {
            if (!link.export_version) {
                return '—';
            }
            return link.export_version;
        },
        goRegenerate() {
            if (this.resourceId) {
                router.push('/external-resources/regenerate/' + this.resourceId);
            }
        }
    },
    template: `
        <v-card class="microdata-resource-details mt-2">
            <v-card-title class="d-flex justify-space-between align-center pb-1">
                <div class="text-subtitle-1" style="font-weight: normal;">{{ panelTitle }}</div>
                <v-chip x-small outlined>{{ sourceTypeLabel }}</v-chip>
            </v-card-title>
            <v-card-text>
                <div v-if="loading" class="text-caption text--secondary py-2">
                    <v-progress-circular indeterminate size="18" width="2" class="mr-2"></v-progress-circular>
                    {{ $t('loading') }}...
                </div>
                <v-alert v-else-if="load_error" type="error" dense>{{ load_error }}</v-alert>
                <template v-else>
                    <div class="d-flex flex-wrap align-center mb-4" style="gap: 8px;">
                        <v-chip v-if="export_format" small outlined>{{ $t('batch_export_format') || 'Format' }}: {{ formatLabel }}</v-chip>
                        <v-chip v-if="detail_bundle_type || bundleType" small outlined>{{ $t('bundle_type') || 'Bundle' }}: {{ bundleTypeLabel }}</v-chip>
                        <v-chip
                            v-if="stalenessChip"
                            small
                            :color="stalenessChip.color"
                            text-color="white"
                        >{{ stalenessChip.text }}</v-chip>
                        <span v-if="generatedAtLabel" class="text-caption text--secondary">
                            {{ $t('generated_at') || 'Generated' }}: {{ generatedAtLabel }}
                        </span>
                    </div>

                    <div v-if="isGenerated" class="mb-4">
                        <div class="text-body-2 font-weight-medium mb-2">{{ $t('microdata_publishable_file') || 'Publishable file' }}</div>
                        <div class="border rounded pa-3 bg-light">
                            <div class="d-flex align-center flex-wrap" style="gap: 8px;">
                                <v-icon
                                    v-if="filename && file_exists === true"
                                    small color="success"
                                >mdi-check-circle</v-icon>
                                <v-icon
                                    v-if="filename && file_exists === false"
                                    small color="error"
                                >mdi-alert-circle</v-icon>
                                <span>{{ filename || ($t('no_file_attached') || 'No file attached') }}</span>
                                <v-btn
                                    v-if="downloadUrl && file_exists"
                                    small
                                    color="primary"
                                    outlined
                                    :href="downloadUrl"
                                    target="_blank"
                                >
                                    <v-icon small left>mdi-download</v-icon>{{ $t('download') }}
                                </v-btn>
                            </div>
                            <div v-if="file_info && file_info.size" class="text-caption text--secondary mt-1">
                                {{ formatFileSize(file_info.size) }}
                                <span v-if="file_info.modified_date"> · {{ file_info.modified_date }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div v-if="!editableLinks" class="text-body-2 font-weight-medium mb-2">
                            {{ isGenerated ? ($t('microdata_included_data_files') || 'Data files included') : ($t('microdata_related_data_files') || 'Related data files') }}
                        </div>
                        <div v-if="editableLinks" class="mb-3">
                            <microdata-resource-datafile-links
                                :value="localSelectedFileIds"
                                @input="localSelectedFileIds = $event"
                                :disabled="!canEdit"
                            ></microdata-resource-datafile-links>
                        </div>
                        <template v-else>
                            <div v-if="links.length === 0" class="text-caption text--secondary border rounded pa-3">
                                {{ $t('microdata_no_linked_files') || 'No related data files are linked to this resource.' }}
                            </div>
                            <div v-else class="border rounded" style="max-height: 240px; overflow-y: auto;">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="bg-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th>{{ $t('microdata_data_files') || 'Data file' }}</th>
                                            <th>{{ $t('id') || 'ID' }}</th>
                                            <th v-if="isGenerated">{{ $t('batch_export_format') || 'Format' }}</th>
                                            <th v-if="isGenerated">{{ $t('version') || 'Version' }}</th>
                                            <th v-if="isGenerated">{{ $t('zip_entry') || 'ZIP entry' }}</th>
                                            <th>{{ $t('link_type') || 'Link' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="link in links" :key="link.id || link.file_id">
                                            <td>{{ dataFileLabel(link) }}</td>
                                            <td class="text-muted">{{ link.file_id }}</td>
                                            <td v-if="isGenerated">{{ formatLinkFormat(link) }}</td>
                                            <td v-if="isGenerated">{{ formatLinkVersion(link) }}</td>
                                            <td v-if="isGenerated" class="text-muted">{{ link.zip_entry_name || '—' }}</td>
                                            <td>{{ linkTypeLabel(link.link_type) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>

                    <div v-if="isGenerated && canEdit" class="pt-2 border-top">
                        <v-btn color="primary" small @click="goRegenerate">
                            <v-icon small left>mdi-refresh</v-icon>{{ $t('regenerate') }}
                        </v-btn>
                    </div>
                </template>
            </v-card-text>
        </v-card>
    `
});
