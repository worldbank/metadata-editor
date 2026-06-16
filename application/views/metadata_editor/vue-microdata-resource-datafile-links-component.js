/**
 * Optional related data-file selection for dat/micro external resources.
 */
Vue.component('microdata-resource-datafile-links', {
    props: {
        value: { type: Array, default: () => [] },
        disabled: { type: Boolean, default: false },
        readonly: { type: Boolean, default: false }
    },
    computed: {
        dataFiles() {
            return this.$store.state.data_files || [];
        },
        selectedIds: {
            get() {
                return Array.isArray(this.value) ? this.value : [];
            },
            set(val) {
                this.$emit('input', val);
            }
        },
        selectionDisabled() {
            return this.disabled || this.readonly;
        },
        allFilesSelected() {
            return this.dataFiles.length > 0
                && this.selectedIds.length === this.dataFiles.length;
        },
        someFilesSelected() {
            return this.selectedIds.length > 0
                && this.selectedIds.length < this.dataFiles.length;
        }
    },
    methods: {
        toggleSelectAllFiles() {
            if (this.selectionDisabled) {
                return;
            }
            if (this.allFilesSelected) {
                this.selectedIds = [];
            } else {
                this.selectedIds = this.dataFiles.map(function(f) { return f.file_id; });
            }
        }
    },
    template: `
        <div class="microdata-resource-datafile-links">
            <div class="text-body-2 font-weight-medium mb-2">
                {{ $t('microdata_related_data_files') || 'Related data files' }}
                <span v-if="dataFiles.length > 0" class="text-caption text--secondary ml-2">
                    ({{ selectedIds.length }} / {{ dataFiles.length }} {{ $t('selected') || 'selected' }})
                </span>
            </div>
            <div v-if="dataFiles.length === 0" class="text-caption text--secondary">
                {{ $t('microdata_no_data_files') || 'No data files in this project.' }}
            </div>
            <div v-else class="border rounded" style="max-height: 240px; overflow-y: auto;">
                <table class="table table-sm table-striped mb-0">
                    <thead class="bg-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th style="width: 48px;">
                                <v-checkbox
                                    :value="allFilesSelected"
                                    :indeterminate="someFilesSelected"
                                    @change="toggleSelectAllFiles"
                                    :disabled="selectionDisabled"
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
                                    v-model="selectedIds"
                                    :value="file.file_id"
                                    :disabled="selectionDisabled"
                                    hide-details dense class="mt-0"
                                ></v-checkbox>
                            </td>
                            <td>{{ file.file_name || file.file_id }}</td>
                            <td class="text-muted">{{ file.file_id }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="text-caption text--secondary mt-1">
                {{ $t('microdata_related_data_files_hint') || 'Optional. Links this resource to editor data files for publish status tracking.' }}
            </div>
        </div>
    `
});
