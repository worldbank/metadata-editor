/**
 * Batch set summary stats options by interval type (discrete or continuous).
 * Applies to all variables in the current file that match the selected type.
 */
Vue.component('dialog-batch-sum-stats-options', {
    props: {
        value: { type: Boolean, default: false },
        file_id: { type: [String, Number], default: null },
        project_id: { type: [String, Number], default: null },
        variables: { type: Array, default: function () { return []; } }
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
            applying: false,
            message_success: '',
            message_error: ''
        };
    },
    computed: {
        dialogVisible: {
            get() { return this.value; },
            set(val) { this.$emit('input', val); }
        },
        discreteCount() {
            return this.variables.filter(v => this._varIntervalType(v) === 'discrete').length;
        },
        continCount() {
            return this.variables.filter(v => this._varIntervalType(v) === 'contin').length;
        },
        matchCount() {
            return this.interval_type === 'discrete' ? this.discreteCount : this.continCount;
        },
        canApply() {
            return this.matchCount > 0 && !this.applying;
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
        }
    },
    watch: {
        value(val) {
            if (val) {
                this.message_success = '';
                this.message_error = '';
                this.applyDefaultsForIntervalType();
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
        _varIntervalType(v) {
            return (v && (v.var_intrvl || v.interval_type)) || null;
        },
        apply() {
            if (!this.canApply) return;
            const vm = this;
            vm.applying = true;
            vm.message_success = '';
            vm.message_error = '';

            const url = (typeof CI !== 'undefined' ? CI.base_url : '') + '/api/variables/batch_sum_stats_options/' + vm.project_id + '/' + vm.file_id;
            axios.post(url, {
                interval_type: vm.interval_type,
                sum_stats_options: vm.sum_stats_options
            })
                .then(function (response) {
                    const data = response.data || {};
                    const n = data.updated != null ? data.updated : 0;
                    vm.message_success = vm.$t('batch_sum_stats_applied', { count: n }) || (n + ' variable(s) updated.');
                    EventBus.$emit('onSuccess', vm.message_success);
                    vm.$emit('applied');
                })
                .catch(function (error) {
                    const msg = (error.response && error.response.data && error.response.data.message) ? error.response.data.message : (error.message || 'Request failed');
                    vm.message_error = msg;
                    EventBus.$emit('onFail', msg);
                })
                .then(function () {
                    vm.applying = false;
                });
        },
        closeDialog() {
            this.$emit('input', false);
        }
    },
    template: `
        <v-dialog v-model="dialogVisible" max-width="480" persistent content-class="batch-sum-stats-options-dialog">
            <v-card>
                <v-card-title class="text-subtitle-1 font-weight-medium">
                    {{ $t('batch_sum_stats_options') }}
                </v-card-title>
                <v-card-text>
                    <div class="text-caption text--secondary mb-3">{{ $t('batch_sum_stats_options_help') }}</div>
                    <v-divider class="mb-3"></v-divider>

                    <div class="text-caption text--secondary mb-2">{{ $t('apply_to_interval_type') }}</div>
                    <v-radio-group v-model="interval_type" hide-details class="mt-0 mb-3">
                        <v-radio :label="$t('discrete') + ' (' + discreteCount + ')'" value="discrete"></v-radio>
                        <v-radio :label="$t('contin') + ' (' + continCount + ')'" value="contin"></v-radio>
                    </v-radio-group>

                    <div class="text-caption text--secondary mb-2">{{ $t('summary_stats') }}</div>
                    <div class="v-checkbox-rm-styles">
                        <div v-for="key in optionKeys" :key="key" class="mb-1">
                            <v-checkbox v-model="sum_stats_options[key]" :label="optionLabels[key]" hide-details dense></v-checkbox>
                        </div>
                    </div>

                    <v-alert v-if="matchCount === 0" type="warning" dense outlined class="mt-3 mb-0">
                        {{ $t('batch_sum_stats_no_match') }}
                    </v-alert>
                    <v-alert v-else type="info" dense outlined class="mt-3 mb-0">
                        {{ $t('batch_sum_stats_will_apply', { count: matchCount }) }}
                    </v-alert>

                    <v-alert v-if="message_success" type="success" dense outlined class="mt-3 mb-0">
                        {{ message_success }}
                    </v-alert>
                    <v-alert v-if="message_error" type="error" dense outlined class="mt-3 mb-0">
                        {{ message_error }}
                    </v-alert>
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions class="px-4 pb-4 pt-3">
                    <v-spacer></v-spacer>
                    <v-btn text @click="closeDialog" :disabled="applying">{{ $t('close') }}</v-btn>
                    <v-btn color="primary" :disabled="!canApply" :loading="applying" @click="apply">
                        {{ $t('apply') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    `
});
