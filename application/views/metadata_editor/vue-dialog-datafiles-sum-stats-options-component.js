/**
 * Data Files page: set summary statistics display options (by variable interval
 * type - discrete or continuous) across one or more data files at once.
 *
 * Works uniformly whether triggered from a single row action or a multi-file
 * checkbox selection - `selectedFiles` is always an array (length 1 or more).
 * Options are applied per file via the existing single-file endpoint
 * (POST /api/variables/batch_sum_stats_options/{sid}/{fid}), looped
 * sequentially, mirroring dialog-batch-export's config -> running -> done flow.
 */
Vue.component('dialog-datafiles-sum-stats-options', {
    props: {
        value: { type: Boolean, default: false },
        selectedFiles: { type: Array, default: () => [] }  // [{ file_id, file_name }]
    },
    data() {
        return {
            interval_type: 'discrete',
            sum_stats_options: {
                wgt: false,
                freq: true,
                missing: true,
                vald: true,
                invd: true,
                min: true,
                max: true,
                mean: false,
                mean_wgt: false,
                stdev: false,
                stdev_wgt: false
            },
            state: 'config',  // 'config' | 'running' | 'done'
            results: []  // { file_id, file_name, status: 'pending'|'done'|'failed', updated, error }
        };
    },
    computed: {
        dialog: {
            get() { return this.value; },
            set(val) { this.$emit('input', val); }
        },
        projectId() {
            return this.$store.state.project_id;
        },
        optionKeys() {
            return ['wgt', 'freq', 'missing', 'vald', 'invd', 'min', 'max', 'mean', 'mean_wgt', 'stdev', 'stdev_wgt'];
        },
        optionLabels() {
            return {
                wgt: this.$t('weighted_statistics'),
                freq: this.$t('frequencies'),
                missing: this.$t('list_missings'),
                vald: this.$t('valid'),
                invd: this.$t('invalid'),
                min: this.$t('min'),
                max: this.$t('max'),
                mean: this.$t('mean'),
                mean_wgt: this.$t('weighted_mean'),
                stdev: this.$t('stddev'),
                stdev_wgt: this.$t('weighted_stddev')
            };
        },
        doneCount() {
            return this.results.filter(r => r.status === 'done' || r.status === 'failed').length;
        },
        successCount() {
            return this.results.filter(r => r.status === 'done').length;
        },
        failedCount() {
            return this.results.filter(r => r.status === 'failed').length;
        },
        totalUpdated() {
            return this.results.reduce((sum, r) => sum + (r.status === 'done' ? (r.updated || 0) : 0), 0);
        }
    },
    watch: {
        value(val) {
            if (val) {
                this.resetForm();
            } else {
                this.resetState();
            }
        },
        interval_type() {
            this.applyDefaultsForIntervalType();
        }
    },
    methods: {
        _defaultOptionsForIntervalType(intervalType) {
            const keys = ['wgt', 'freq', 'missing', 'vald', 'invd', 'min', 'max', 'mean', 'mean_wgt', 'stdev', 'stdev_wgt'];
            if (intervalType === 'discrete') {
                const opts = {};
                keys.forEach(k => { opts[k] = ['freq', 'missing', 'vald', 'invd', 'min', 'max'].indexOf(k) >= 0; });
                return opts;
            }
            // contin: only min, max selected
            const opts = {};
            keys.forEach(k => { opts[k] = k === 'min' || k === 'max'; });
            return opts;
        },
        applyDefaultsForIntervalType() {
            const defaults = this._defaultOptionsForIntervalType(this.interval_type);
            Object.keys(defaults).forEach(k => {
                this.$set(this.sum_stats_options, k, defaults[k]);
            });
        },
        resetForm() {
            this.interval_type = 'discrete';
            this.applyDefaultsForIntervalType();
        },
        resetState() {
            this.state = 'config';
            this.results = [];
        },
        closeDialog() {
            this.dialog = false;
        },
        closeAndClear() {
            this.resetState();
            this.closeDialog();
            this.$emit('applied');
        },
        async startApply() {
            if (this.selectedFiles.length === 0) return;

            this.results = this.selectedFiles.map(f => ({
                file_id: f.file_id,
                file_name: f.file_name,
                status: 'pending',
                updated: 0,
                error: null
            }));

            this.state = 'running';

            const url_base = CI.base_url + '/api/variables/batch_sum_stats_options/' + this.projectId + '/';
            for (let i = 0; i < this.results.length; i++) {
                const r = this.results[i];
                try {
                    const resp = await axios.post(url_base + encodeURIComponent(r.file_id), {
                        interval_type: this.interval_type,
                        sum_stats_options: this.sum_stats_options
                    });
                    r.status = 'done';
                    r.updated = (resp.data && resp.data.updated != null) ? resp.data.updated : 0;
                } catch (e) {
                    r.status = 'failed';
                    r.error = (e.response && e.response.data && e.response.data.message) ? e.response.data.message : (e.message || this.$t('failed'));
                }
            }

            this.state = 'done';
        }
    },
    template: `
        <div class="vue-dialog-datafiles-sum-stats-options-component">
            <v-dialog v-model="dialog" width="480" persistent @input="function(v){ if(!v) resetState(); }">
                <v-card>
                    <v-card-title class="text-h6 grey lighten-2">
                        {{ $t('summary_stats_options') }}
                    </v-card-title>
                    <v-card-text class="pt-4">

                        <!-- Config: interval type + stat options -->
                        <template v-if="state === 'config'">
                            <div class="text-caption text--secondary mb-3">{{ $t('summary_stats_options_help') }}</div>

                            <div class="mb-3">
                                <label class="text-body-2 font-weight-medium d-block mb-2">{{ $t('files') }} ({{ selectedFiles.length }})</label>
                                <ul class="text-caption pl-3 mb-0" style="max-height: 100px; overflow-y: auto;">
                                    <li v-for="f in selectedFiles" :key="f.file_id">{{ f.file_name || f.file_id }}</li>
                                </ul>
                            </div>

                            <v-divider class="mb-3"></v-divider>

                            <div class="text-caption text--secondary mb-2">{{ $t('apply_to_interval_type') }}</div>
                            <v-radio-group v-model="interval_type" hide-details class="mt-0 mb-3">
                                <v-radio :label="$t('discrete')" value="discrete"></v-radio>
                                <v-radio :label="$t('contin')" value="contin"></v-radio>
                            </v-radio-group>

                            <div class="text-caption text--secondary mb-2">{{ $t('summary_stats') }}</div>
                            <div class="v-checkbox-rm-styles">
                                <div v-for="key in optionKeys" :key="key" class="mb-1">
                                    <v-checkbox v-model="sum_stats_options[key]" :label="optionLabels[key]" hide-details dense></v-checkbox>
                                </div>
                            </div>
                        </template>

                        <!-- Running: progress -->
                        <template v-if="state === 'running'">
                            <div class="mb-3">
                                <div class="text-body-2 mb-2">{{ $t('processing_please_wait') }}</div>
                                <v-progress-linear
                                    :value="results.length > 0 ? (doneCount / results.length) * 100 : 0"
                                    color="primary"
                                    height="8"
                                    rounded
                                ></v-progress-linear>
                                <div class="text-caption text--secondary mt-1">{{ doneCount }} / {{ results.length }} {{ $t('files') }}</div>
                            </div>
                        </template>

                        <!-- Done: per-file results -->
                        <template v-if="state === 'done'">
                            <div class="text-body-2 mb-3">
                                <span v-if="successCount > 0" class="success--text">{{ totalUpdated }} {{ $t('variables') }} ({{ successCount }} {{ $t('files') }})</span>
                                <span v-if="failedCount > 0" class="error--text ml-2">{{ failedCount }} {{ $t('failed') }}</span>
                            </div>
                            <div style="max-height: 220px; overflow-y: auto;">
                                <div v-for="(r, idx) in results" :key="idx" class="d-flex align-center py-1">
                                    <v-icon small :color="r.status === 'done' ? 'success' : 'error'" class="mr-2">
                                        {{ r.status === 'done' ? 'mdi-check-circle' : 'mdi-alert-circle' }}
                                    </v-icon>
                                    <span class="text-body-2 flex-grow-1">{{ r.file_name || r.file_id }}</span>
                                    <span v-if="r.status === 'done'" class="text-caption text--secondary">{{ r.updated }} {{ $t('variables') }}</span>
                                    <span v-else class="text-caption error--text">{{ r.error || $t('failed') }}</span>
                                </div>
                            </div>
                        </template>

                    </v-card-text>
                    <v-divider></v-divider>
                    <v-card-actions class="px-4 pb-4 pt-3">
                        <v-spacer></v-spacer>
                        <template v-if="state === 'config'">
                            <v-btn text small @click="closeDialog">{{ $t('close') }}</v-btn>
                            <v-btn color="primary" small :disabled="selectedFiles.length === 0" @click="startApply">
                                {{ $t('apply') }}
                            </v-btn>
                        </template>
                        <template v-if="state === 'running'">
                            <v-btn color="grey" text small disabled>{{ $t('close') }}</v-btn>
                        </template>
                        <template v-if="state === 'done'">
                            <v-btn color="primary" text small @click="closeAndClear">{{ $t('close') }}</v-btn>
                        </template>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
