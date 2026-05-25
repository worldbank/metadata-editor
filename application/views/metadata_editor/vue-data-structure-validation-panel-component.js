Vue.component('data-structure-validation-panel', {
    props: {
        structureId: { type: [String, Number], default: null },
        validation: { type: Object, default: null }
    },
    data: function () {
        return {
            loading: false,
            localValidation: null,
            error: null
        };
    },
    computed: {
        numericId: function () {
            var n = parseInt(this.structureId, 10);
            return isNaN(n) ? null : n;
        },
        report: function () {
            return this.validation || this.localValidation;
        },
        isValid: function () {
            return this.report ? !!this.report.valid : null;
        },
        errors: function () {
            return (this.report && this.report.errors) ? this.report.errors : [];
        },
        warnings: function () {
            return (this.report && this.report.warnings) ? this.report.warnings : [];
        },
        roles: function () {
            return (this.report && this.report.roles) ? this.report.roles : [];
        }
    },
    watch: {
        structureId: function () { this.loadIfNeeded(); },
        validation: function (v) {
            if (v) {
                this.localValidation = null;
            } else {
                this.loadIfNeeded();
            }
        }
    },
    mounted: function () {
        this.loadIfNeeded();
    },
    methods: {
        apiBase: function () {
            return ((typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '') + '/api/data_structures';
        },
        loadIfNeeded: function () {
            if (this.validation || !this.numericId) {
                return;
            }
            this.load();
        },
        load: function () {
            var vm = this;
            if (!vm.numericId) {
                return Promise.resolve();
            }
            vm.loading = true;
            vm.error = null;
            return axios.get(vm.apiBase() + '/validate/' + vm.numericId)
                .then(function (res) {
                    vm.loading = false;
                    if (res.data && res.data.status === 'success') {
                        vm.localValidation = res.data.validation || null;
                    }
                })
                .catch(function (err) {
                    vm.loading = false;
                    vm.error = (err.response && err.response.data && err.response.data.message) || 'Validation failed';
                });
        },
        refresh: function () {
            if (this.validation) {
                return Promise.resolve();
            }
            this.localValidation = null;
            return this.load();
        },
        roleColor: function (role) {
            if (role.present) {
                return 'success';
            }
            if (role.tier === 'recommended') {
                return 'warning';
            }
            return 'error';
        },
        roleIcon: function (role) {
            if (role.present) {
                return 'mdi-check-circle';
            }
            if (role.tier === 'recommended') {
                return 'mdi-alert-outline';
            }
            return 'mdi-close-circle';
        }
    },
    template: `
        <v-card outlined class="mb-4">
            <v-card-title class="text-subtitle-1 py-3 d-flex align-center">
                Structure validation
                <v-progress-circular v-if="loading" indeterminate size="16" width="2" class="ml-2"></v-progress-circular>
                <v-spacer></v-spacer>
                <v-chip v-if="isValid === true" small color="success" text-color="white" label>
                    <v-icon left x-small>mdi-check-circle</v-icon> Valid
                </v-chip>
                <v-chip v-else-if="isValid === false" small color="error" text-color="white" label>
                    <v-icon left x-small>mdi-alert-circle</v-icon> Has errors
                </v-chip>
            </v-card-title>
            <v-card-text v-if="error" class="pt-0">
                <v-alert type="error" dense outlined>{{ error }}</v-alert>
            </v-card-text>
            <v-card-text v-else-if="report" class="pt-0">
                <div v-if="roles.length" class="mb-3">
                    <div class="caption grey--text mb-2">Role checklist</div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <v-chip
                            v-for="role in roles"
                            :key="role.type"
                            small
                            :color="roleColor(role)"
                            :outlined="!role.present"
                            label
                        >
                            <v-icon left x-small>{{ roleIcon(role) }}</v-icon>
                            {{ role.label }}
                            <span v-if="role.tier === 'recommended'" class="ml-1 caption">(recommended)</span>
                        </v-chip>
                    </div>
                </div>
                <v-alert v-if="errors.length" type="error" dense outlined class="mb-2">
                    <div class="subtitle-2 mb-1">Errors (block publish and import)</div>
                    <ul class="caption pl-4 mb-0">
                        <li v-for="(e, i) in errors" :key="'e' + i">{{ e }}</li>
                    </ul>
                </v-alert>
                <v-alert v-if="warnings.length" type="warning" dense outlined class="mb-0">
                    <div class="subtitle-2 mb-1">Warnings</div>
                    <ul class="caption pl-4 mb-0">
                        <li v-for="(w, i) in warnings" :key="'w' + i">{{ w }}</li>
                    </ul>
                </v-alert>
                <div v-if="!errors.length && !warnings.length" class="caption grey--text">
                    All required roles are present. Geography is recommended when mapping or charting by region.
                </div>
            </v-card-text>
        </v-card>
    `
});
