/**
 * Unified variables validation page: sync with CSV + invalid variable names.
 * Route: #/variables-validation/:file_id
 */

Vue.component('variables-validation', {
    props: ['file_id'],
    data: function() {
        var fileId = this.file_id || (this.$route && this.$route.params && this.$route.params.file_id);
        return {
            fid: fileId,
            columns_diff: null,
            invalid_names: [],
            loading: false,
            error: '',
            actionLoading: false,
            actionError: '',
            confirmRemove: false,
            removePreview: null,
            removePreviewLoading: false,
            batchPrefix: 'V',
            renameLoading: false,
            activeTab: 0,
            proposedNewNames: {},
            selectedInvalidNames: [],
            applyError: '',
            applyConfirmDialog: false,
            pendingRenamesCount: 0,
            suggestConfirmDialog: false,
            suggestConfirmActionType: null,
            suggestConfirmActionLabel: '',
            suggestConfirmCount: 0,
            suggestConfirmScopeAll: false,
            batchFixExpanded: undefined
        };
    },
    computed: {
        projectId: function() {
            return this.$store.state.project_id;
        },
        fileLabel: function() {
            var files = this.$store.state.data_files || [];
            var f = files.find(function(x) { return x.file_id === this.fid; }.bind(this));
            return (f && f.file_name) ? f.file_name : this.fid;
        },
        columnsInDbNotInCsv: function() {
            return (this.columns_diff && this.columns_diff.columns_in_db_not_in_csv) ? this.columns_diff.columns_in_db_not_in_csv : [];
        },
        columnsInDbNotInCsvCount: function() {
            return this.columnsInDbNotInCsv.length;
        },
        removableCount: function() {
            var p = this.removePreview;
            return (p && p.status === 'ok' && p.removed) ? p.removed.length : 0;
        },
        hasSyncMismatch: function() {
            return this.columns_diff && !this.columns_diff.in_sync;
        },
        invalidNamesCount: function() {
            return this.invalid_names.length;
        },
        hasInvalidNames: function() {
            return this.invalidNamesCount > 0;
        },
        leadingUnderscoreItems: function() {
            return this.invalid_names.filter(function(i) { return i.reason === 'leading_underscore'; });
        },
        invalidCharsItems: function() {
            return this.invalid_names.filter(function(i) { return i.reason === 'invalid_chars'; });
        },
        invalidCharsOrTooLongItems: function() {
            return this.invalid_names.filter(function(i) { return i.reason === 'invalid_chars' || i.reason === 'too_long'; });
        },
        startsWithNumberItems: function() {
            return this.invalid_names.filter(function(i) { return i.reason === 'starts_with_number'; });
        },
        otherInvalidItems: function() {
            return this.invalid_names.filter(function(i) {
                return ['leading_underscore', 'invalid_chars', 'starts_with_number'].indexOf(i.reason) === -1;
            });
        },
        pageTitle: function() {
            var label = this.fileLabel || this.fid || '';
            return (this.$t('variables_validation') || 'Variables validation') + (label ? ' — ' + label : '');
        },
        renamesToApply: function() {
            var vm = this;
            var out = [];
            if (!Array.isArray(this.invalid_names)) return out;
            var selected = vm.selectedInvalidNames || [];
            this.invalid_names.forEach(function(item) {
                if (selected.indexOf(item.name) === -1) return;
                var newName = (vm.proposedNewNames[item.name] && String(vm.proposedNewNames[item.name]).trim()) || '';
                if (newName !== '' && newName !== item.name) {
                    out.push({ old_name: item.name, new_name: newName });
                }
            });
            return out;
        },
        hasPendingRenames: function() {
            return this.renamesToApply.length > 0;
        },
        allInvalidSelected: function() {
            if (!this.invalid_names.length) return false;
            return this.selectedInvalidNames.length >= this.invalid_names.length;
        },
        suggestOptions: function() {
            var opts = [];
            if (this.leadingUnderscoreItems.length > 0) {
                opts.push({ value: 'add_prefix_leading_underscore', text: (this.$t('add_prefix_leading_underscore') || 'Add prefix') + ' (' + this.leadingUnderscoreItems.length + ')' });
                opts.push({ value: 'remove_leading_underscore', text: (this.$t('remove_leading_underscore') || 'Remove leading _') + ' (' + this.leadingUnderscoreItems.length + ')' });
            }
            if (this.startsWithNumberItems.length > 0) {
                opts.push({ value: 'add_prefix_starts_with_number', text: (this.$t('add_prefix_starts_with_number') || 'Add prefix (numbers)') + ' (' + this.startsWithNumberItems.length + ')' });
            }
            if (this.invalidCharsItems.length > 0) {
                opts.push({ value: 'replace_invalid_chars', text: (this.$t('replace_invalid_chars') || 'Replace invalid chars') + ' (' + this.invalidCharsItems.length + ')' });
            }
            return opts;
        },
        hasBatchFixSections: function() {
            return this.leadingUnderscoreItems.length > 0 || this.invalidCharsOrTooLongItems.length > 0 || this.startsWithNumberItems.length > 0;
        }
    },
    watch: {
        invalid_names: {
            handler: function(val) {
                if (val && val.length) {
                    this.initProposedNames();
                } else {
                    this.proposedNewNames = {};
                }
                this.selectedInvalidNames = [];
            },
            deep: false
        },
        file_id: function(newVal) {
            if (newVal) {
                this.fid = newVal;
                this.resetAndFetch();
            }
        },
        '$route.params.file_id': function(newVal) {
            if (newVal && newVal !== this.fid) {
                this.fid = newVal;
                this.resetAndFetch();
            }
        }
    },
    mounted: function() {
        this.fid = this.file_id || (this.$route && this.$route.params && this.$route.params.file_id) || this.fid;
        this.fetchValidation();
    },
    methods: {
        resetAndFetch: function() {
            this.columns_diff = null;
            this.invalid_names = [];
            this.proposedNewNames = {};
            this.selectedInvalidNames = [];
            this.applyError = '';
            this.error = '';
            this.fetchValidation();
        },
        getDefaultSuggestion: function(item) {
            var actionType = 'replace_invalid_chars';
            if (item.reason === 'leading_underscore') actionType = 'add_prefix_leading_underscore';
            else if (item.reason === 'starts_with_number') actionType = 'add_prefix_starts_with_number';
            else if (item.reason === 'invalid_chars' || item.reason === 'too_long') actionType = 'replace_invalid_chars';
            else if (item.reason === 'empty') return '';
            return this.suggestNewName(item, actionType, this.batchPrefix);
        },
        initProposedNames: function() {
            var vm = this;
            vm.proposedNewNames = {};
            vm.invalid_names.forEach(function(item) {
                vm.$set(vm.proposedNewNames, item.name, vm.getDefaultSuggestion(item));
            });
        },
        validateNewNames: function(renames) {
            var vm = this;
            var currentNames = (this.invalid_names || []).map(function(i) { return i.name; });
            var oldNamesInRenames = renames.map(function(r) { return r.old_name; });
            var namesNotBeingRenamed = currentNames.filter(function(n) { return oldNamesInRenames.indexOf(n) === -1; });
            var newNamesSeen = {};
            for (var r = 0; r < renames.length; r++) {
                var n = String(renames[r].new_name).trim();
                if (n.length > 32) return { valid: false, message: vm.$t('too_long') || 'Variable name cannot be longer than 32 characters.' };
                if (!/^[a-zA-Z]/.test(n)) return { valid: false, message: vm.$t('leading_underscore') || 'Variable name must start with a letter.' };
                if (!/^[a-zA-Z0-9_]+$/.test(n)) return { valid: false, message: vm.$t('invalid_chars') || 'Variable name may only contain letters, numbers, and underscores.' };
                if (newNamesSeen[n] && newNamesSeen[n] !== renames[r].old_name) return { valid: false, message: (vm.$t('duplicate_new_name') || 'Duplicate new name: ') + n };
                newNamesSeen[n] = renames[r].old_name;
                if (namesNotBeingRenamed.indexOf(n) !== -1) return { valid: false, message: (vm.$t('name_already_used') || 'Name already in use: ') + n };
            }
            return { valid: true, message: '' };
        },
        setSelectAll: function(checked) {
            this.selectedInvalidNames = checked ? this.invalid_names.map(function(i) { return i.name; }) : [];
        },
        toggleSelectAll: function() {
            this.setSelectAll(!this.allInvalidSelected);
        },
        isSelected: function(name) {
            return this.selectedInvalidNames.indexOf(name) !== -1;
        },
        setSelected: function(name, checked) {
            var i = this.selectedInvalidNames.indexOf(name);
            if (checked && i === -1) this.selectedInvalidNames.push(name);
            else if (!checked && i !== -1) this.selectedInvalidNames.splice(i, 1);
        },
        toggleSelect: function(name) {
            var i = this.selectedInvalidNames.indexOf(name);
            if (i === -1) this.selectedInvalidNames.push(name);
            else this.selectedInvalidNames.splice(i, 1);
        },
        getSuggestAffectedItems: function(actionType, scopeAll) {
            var vm = this;
            if (!actionType) return [];
            return vm.invalid_names.filter(function(i) {
                if (scopeAll || vm.selectedInvalidNames.length === 0) {
                    if (actionType === 'add_prefix_leading_underscore' || actionType === 'remove_leading_underscore') return i.reason === 'leading_underscore';
                    if (actionType === 'add_prefix_starts_with_number') return i.reason === 'starts_with_number';
                    if (actionType === 'replace_invalid_chars') return i.reason === 'invalid_chars' || i.reason === 'too_long';
                }
                return vm.selectedInvalidNames.indexOf(i.name) !== -1;
            });
        },
        openSuggestConfirm: function(opt, scopeAll) {
            this.suggestConfirmActionType = opt.value;
            this.suggestConfirmActionLabel = opt.text;
            this.suggestConfirmScopeAll = !!scopeAll;
            this.suggestConfirmCount = this.getSuggestAffectedItems(opt.value, scopeAll).length;
            this.suggestConfirmDialog = true;
        },
        batchRemoveLeadingUnderscore: function() {
            var n = this.leadingUnderscoreItems.length;
            this.openSuggestConfirm({ value: 'remove_leading_underscore', text: (this.$t('remove_leading_underscore') || 'Remove leading underscore') + ' (' + n + ')' }, true);
        },
        batchAddPrefixLeadingUnderscore: function() {
            var n = this.leadingUnderscoreItems.length;
            this.openSuggestConfirm({ value: 'add_prefix_leading_underscore', text: (this.$t('add_prefix_leading_underscore') || 'Add prefix') + ' (' + n + ')' }, true);
        },
        batchReplaceInvalidChars: function() {
            var n = this.invalidCharsOrTooLongItems.length;
            this.openSuggestConfirm({ value: 'replace_invalid_chars', text: (this.$t('replace_invalid_chars') || 'Replace invalid chars with _') + ' (' + n + ')' }, true);
        },
        batchAddPrefixStartsWithNumber: function() {
            var n = this.startsWithNumberItems.length;
            this.openSuggestConfirm({ value: 'add_prefix_starts_with_number', text: (this.$t('add_prefix_starts_with_number') || 'Add prefix for numbers') + ' (' + n + ')' }, true);
        },
        closeSuggestConfirm: function() {
            this.suggestConfirmDialog = false;
            this.suggestConfirmActionType = null;
            this.suggestConfirmActionLabel = '';
            this.suggestConfirmCount = 0;
            this.suggestConfirmScopeAll = false;
        },
        confirmApplySuggestion: function() {
            var vm = this;
            var actionType = vm.suggestConfirmActionType;
            var scopeAll = vm.suggestConfirmScopeAll;
            vm.closeSuggestConfirm();
            if (!actionType) return;
            var items = vm.getSuggestAffectedItems(actionType, scopeAll);
            var renames = [];
            items.forEach(function(item) {
                var newName = vm.suggestNewName(item, actionType, vm.batchPrefix);
                if (newName && newName !== item.name) {
                    renames.push({ old_name: item.name, new_name: newName });
                }
            });
            if (renames.length > 0) {
                vm.submitRenames(renames);
            } else {
                vm.applySuggestionToSelection(actionType, scopeAll);
            }
        },
        applySuggestionToSelection: function(actionType, scopeAll) {
            var vm = this;
            if (!actionType) return;
            var items = vm.getSuggestAffectedItems(actionType, scopeAll);
            items.forEach(function(item) {
                var newName = vm.suggestNewName(item, actionType, vm.batchPrefix);
                vm.$set(vm.proposedNewNames, item.name, newName);
            });
        },
        updateProposedName: function(name, value) {
            this.$set(this.proposedNewNames, name, value);
        },
        resetRowSuggestion: function(item) {
            this.$set(this.proposedNewNames, item.name, this.getDefaultSuggestion(item));
        },
        openApplyConfirm: function() {
            this.pendingRenamesCount = this.renamesToApply.length;
            this.applyError = '';
            this.applyConfirmDialog = true;
        },
        closeApplyConfirm: function() {
            this.applyConfirmDialog = false;
            this.applyError = '';
        },
        submitRenames: function(renames) {
            var vm = this;
            if (!renames || renames.length === 0) {
                vm.closeApplyConfirm();
                return;
            }
            var valid = vm.validateNewNames(renames);
            if (!valid.valid) {
                vm.applyError = valid.message;
                return;
            }
            vm.renameLoading = true;
            vm.applyError = '';
            var url = CI.base_url + '/api/variables/rename/' + vm.projectId + '/' + encodeURIComponent(vm.fid);
            axios.post(url, { renames: renames })
                .then(function(response) {
                    vm.renameLoading = false;
                    var data = response.data || {};
                    if (data.errors && data.errors.length > 0) {
                        vm.applyError = (data.errors[0].message) || 'Some renames failed';
                        return;
                    }
                    vm.closeApplyConfirm();
                    vm.fetchValidation();
                    if (vm.$store && vm.$store.dispatch) {
                        vm.$store.dispatch('loadVariables', { dataset_id: vm.projectId, fid: vm.fid });
                    }
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', (vm.$t('variables_renamed') || 'Variables renamed.'));
                    }
                })
                .catch(function(err) {
                    vm.renameLoading = false;
                    vm.applyError = (err.response && err.response.data && err.response.data.message) ? err.response.data.message : (vm.$t('failed') || 'Failed');
                });
        },
        applyRenames: function() {
            var vm = this;
            var renames = vm.renamesToApply;
            if (renames.length === 0) {
                vm.closeApplyConfirm();
                return;
            }
            vm.submitRenames(renames);
        },
        goToVariables: function() {
            this.$router.push('/variables/' + this.fid);
        },
        fetchValidation: function() {
            var vm = this;
            if (!vm.projectId || !vm.fid) return;
            vm.loading = true;
            vm.error = '';
            var base = CI.base_url + '/api/datafiles/';
            var sid = vm.projectId;
            var fid = encodeURIComponent(vm.fid);
            Promise.all([
                axios.get(base + 'columns_diff/' + sid + '/' + fid),
                axios.get(base + 'invalid_variable_names/' + sid + '/' + fid)
            ])
                .then(function(responses) {
                    vm.loading = false;
                    var cols = responses[0].data;
                    var names = responses[1].data;
                    if (cols && cols.status === 'success') {
                        vm.columns_diff = cols.columns_diff || null;
                    } else {
                        vm.columns_diff = null;
                    }
                    if (names && names.status === 'success') {
                        vm.invalid_names = names.invalid_names || [];
                    } else {
                        vm.invalid_names = [];
                    }
                    if ((cols && cols.status !== 'success') || (names && names.status !== 'success')) {
                        vm.error = vm.$t('failed') || 'Failed to load';
                    }
                })
                .catch(function(err) {
                    vm.loading = false;
                    vm.error = (err.response && err.response.data && err.response.data.message) ? err.response.data.message : (vm.$t('failed') || 'Failed to load');
                });
        },
        suggestNewName: function(item, actionType, prefix) {
            var name = (item.name && String(item.name).trim()) || '';
            if (!name) return name;
            prefix = prefix || 'V_';
            if (actionType === 'add_prefix_leading_underscore') {
                return prefix + name;
            }
            if (actionType === 'remove_leading_underscore') {
                return name.replace(/^_+/, '') || name;
            }
            if (actionType === 'replace_invalid_chars') {
                var out = name.replace(/[^a-zA-Z0-9_]/g, '_');
                if (!/^[a-zA-Z]/.test(out)) {
                    out = prefix + out;
                }
                return out.substring(0, 32);
            }
            if (actionType === 'add_prefix_starts_with_number') {
                return prefix + name;
            }
            return name;
        },
        removeUnmatchedRequest: function(dryRun) {
            var url = CI.base_url + '/api/datafiles/remove_unmatched_variables/' + this.projectId;
            return axios.post(url, { file_ids: [this.fid], dry_run: dryRun }).then(function(response) {
                var results = (response.data && response.data.results) || [];
                var r = results[0];
                if (!r) throw new Error('No result');
                if (r.status === 'failed') throw new Error(r.message || 'Failed');
                return r;
            });
        },
        errorMessage: function(err) {
            if (err && err.response && err.response.data && err.response.data.message) return err.response.data.message;
            if (err && err.message) return err.message;
            return this.$t('failed') || 'Failed';
        },
        removeFromMetadata: function() {
            var vm = this;
            if (vm.columnsInDbNotInCsvCount === 0) return;
            vm.confirmRemove = true;
            vm.actionError = '';
            vm.removePreview = null;
            vm.removePreviewLoading = true;
            vm.removeUnmatchedRequest(true)
                .then(function(r) {
                    vm.removePreview = r;
                })
                .catch(function(err) {
                    vm.actionError = vm.errorMessage(err);
                })
                .then(function() {
                    vm.removePreviewLoading = false;
                });
        },
        confirmRemoveSubmit: function() {
            var vm = this;
            vm.actionLoading = true;
            vm.actionError = '';
            vm.removeUnmatchedRequest(false)
                .then(function(r) {
                    vm.actionLoading = false;
                    vm.confirmRemove = false;
                    vm.removePreview = null;
                    vm.$store.dispatch('loadVariables', { dataset_id: vm.projectId, fid: vm.fid }).then(function() {
                        vm.fetchValidation();
                    });
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', vm.$t('remove_unmatched_variables_done', { count: r.removed.length }));
                    }
                })
                .catch(function(err) {
                    vm.actionLoading = false;
                    vm.actionError = vm.errorMessage(err);
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', vm.actionError);
                    }
                });
        },
        cancelRemove: function() {
            this.confirmRemove = false;
            this.actionError = '';
            this.removePreview = null;
        },
        reasonLabel: function(reason) {
            var labels = {
                leading_underscore: this.$t('leading_underscore') || 'Leading underscore',
                starts_with_number: this.$t('starts_with_number') || 'Starts with number',
                invalid_chars: this.$t('invalid_chars') || 'Invalid characters',
                too_long: this.$t('too_long') || 'Too long',
                empty: this.$t('empty') || 'Empty'
            };
            return labels[reason] || reason;
        }
    },
    template: `
        <div class="variables-validation-page container-fluid pt-4 pb-5">
            <v-card>
                <v-card-title class="mt-4">
                    <v-btn icon class="mr-2" @click="goToVariables" :title="$t('variables')">
                        <v-icon>mdi-arrow-left</v-icon>
                    </v-btn>
                    <span>{{ pageTitle }}</span>
                </v-card-title>
                <v-card-text class="pt-4">
                    <div v-if="loading" class="d-flex align-center justify-center py-5">
                        <v-progress-circular indeterminate color="primary" size="40"></v-progress-circular>
                        <span class="ml-3">{{ $t('loading') }}...</span>
                    </div>
                    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
                    <template v-else>
                        <v-tabs v-model="activeTab" class="mb-3">
                            <v-tab>
                                {{ $t('metadata_columns_diff') || 'Sync with CSV' }}
                                <v-badge v-if="columnsInDbNotInCsvCount > 0" color="error" inline class="ml-1">
                                    <template v-slot:badge><span class="white--text">{{ columnsInDbNotInCsvCount }}</span></template>
                                </v-badge>
                            </v-tab>
                            <v-tab>
                                {{ $t('invalid_variable_names') || 'Invalid variable names' }}
                                <v-badge v-if="invalidNamesCount > 0" color="error" inline class="ml-1">
                                    <template v-slot:badge><span class="white--text">{{ invalidNamesCount }}</span></template>
                                </v-badge>
                            </v-tab>
                        </v-tabs>
                        <v-tabs-items v-model="activeTab">
                            <v-tab-item>
                                <div v-if="!hasSyncMismatch && columns_diff" class="alert alert-success">
                                    {{ $t('variables_match_csv') || 'Variables match CSV.' }}
                                </div>
                                <div v-else-if="hasSyncMismatch" class="pt-2">
                                    <p class="text-body-2 mb-3">{{ $t('columns_in_db_not_in_csv_help') || 'Variables in metadata that are not in the CSV:' }}</p>
                                    <div v-if="columnsInDbNotInCsvCount > 0" class="mb-3" style="width: fit-content;">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th style="width: 60px;">#</th>
                                                    <th>{{ $t('variable') || 'Variable' }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(name, index) in columnsInDbNotInCsv" :key="'sync-' + name">
                                                    <td>{{ index + 1 }}</td>
                                                    <td>{{ name }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p v-else class="text-body-2 text-secondary">{{ $t('none') || 'None' }}</p>
                                    <v-btn v-if="columnsInDbNotInCsvCount > 0" color="warning" dark @click="removeFromMetadata">
                                        {{ $t('remove') }} ({{ columnsInDbNotInCsvCount }})
                                    </v-btn>
                                </div>
                            </v-tab-item>
                            <v-tab-item>
                                <v-alert v-if="hasInvalidNames" type="warning" outlined dense class="mb-3">
                                    <div class="font-weight-medium mb-2">{{ $t('invalid_variable_names_rules_title') || 'Variable name rules:' }}</div>
                                    <ul class="mb-0 pl-3" style="list-style-type: disc;">
                                        <li>{{ $t('var_rule_start_letter') || 'Must start with a letter (a–z, A–Z)' }}</li>
                                        <li>{{ $t('var_rule_chars') || 'Only letters, numbers, and underscores (no spaces or special characters)' }}</li>
                                        <li>{{ $t('var_rule_max_length') || 'Maximum 32 characters' }}</li>
                                    </ul>
                                </v-alert>
                                <div v-if="!hasInvalidNames" class="alert alert-success">
                                    {{ $t('all_variable_names_valid') || 'All variable names are valid.' }}
                                </div>
                                <div v-else>
                                    <v-expansion-panels v-if="hasBatchFixSections" v-model="batchFixExpanded" flat class="mb-3" style="border: 1px solid rgba(0,0,0,.12); border-radius: 4px;">
                                        <v-expansion-panel>
                                            <v-expansion-panel-header class="py-2">
                                                <div>
                                                    <div class="font-weight-medium">{{ $t('batch_fix') || 'Bulk fix' }}</div>
                                                    <div class="text-caption grey--text text--darken-1 mt-0 pt-0">{{ $t('batch_fix_note') || 'Apply bulk fix to all variables with the same issue.' }}</div>
                                                </div>
                                            </v-expansion-panel-header>
                                            <v-expansion-panel-content class="pt-0">
                                                <v-card v-if="leadingUnderscoreItems.length > 0" outlined class="mb-3">
                                                    <v-card-subtitle class="font-weight-medium pb-1 d-flex align-center">
                                                        <v-chip small class="mr-2" color="grey lighten-2">{{ leadingUnderscoreItems.length }}</v-chip>
                                                        {{ $t('variables_with_leading_underscores') || 'Variables with leading underscores' }}
                                                    </v-card-subtitle>
                                                    <v-card-text class="pt-0">
                                                        <v-row no-gutters class="flex-column">
                                                            <v-col class="pa-3 rounded grey lighten-4 mb-2">
                                                                <div class="text-caption grey--text text--darken-1 mb-1">{{ $t('remove_leading_underscore') || 'Remove leading underscore' }}</div>
                                                                <v-btn small outlined @click="batchRemoveLeadingUnderscore">{{ $t('remove_leading_underscore') || 'Remove underscore' }}</v-btn>
                                                            </v-col>
                                                            <v-col class="pa-3 rounded grey lighten-4">
                                                                <div class="text-caption grey--text text--darken-1 mb-2">{{ $t('add_prefix_leading_underscore') || 'Add prefix' }}</div>
                                                                <v-row align="center" no-gutters>
                                                                    <v-col cols="12" sm="auto" class="pr-2 mb-2 mb-sm-0">
                                                                        <label class="text-body-2 grey--text text--darken-1 mr-2">{{ $t('prefix') || 'Prefix' }}</label>
                                                                        <input type="text" v-model="batchPrefix" class="form-control form-control-sm d-inline-block" style="width: 80px; vertical-align: middle;">
                                                                    </v-col>
                                                                    <v-col cols="12" sm="auto">
                                                                        <v-btn small outlined @click="batchAddPrefixLeadingUnderscore">{{ $t('add_prefix_leading_underscore') || 'Add prefix' }}</v-btn>
                                                                    </v-col>
                                                                </v-row>
                                                            </v-col>
                                                        </v-row>
                                                    </v-card-text>
                                                </v-card>
                                                <v-card v-if="invalidCharsOrTooLongItems.length > 0" outlined class="mb-3">
                                                    <v-card-subtitle class="font-weight-medium pb-1 d-flex align-center">
                                                        <v-chip small class="mr-2" color="grey lighten-2">{{ invalidCharsOrTooLongItems.length }}</v-chip>
                                                        {{ $t('variables_with_invalid_characters') || 'Variables with invalid characters' }}
                                                    </v-card-subtitle>
                                                    <v-card-text class="pt-0">
                                                        <v-btn small outlined @click="batchReplaceInvalidChars">{{ $t('replace_invalid_chars') || 'Replace special characters with underscore' }}</v-btn>
                                                    </v-card-text>
                                                </v-card>
                                                <v-card v-if="startsWithNumberItems.length > 0" outlined>
                                                    <v-card-subtitle class="font-weight-medium pb-1 d-flex align-center">
                                                        <v-chip small class="mr-2" color="grey lighten-2">{{ startsWithNumberItems.length }}</v-chip>
                                                        {{ $t('variables_starting_with_number') || 'Variables starting with a number' }}
                                                    </v-card-subtitle>
                                                    <v-card-text class="pt-0">
                                                        <div class="d-flex flex-wrap align-center">
                                                            <span class="text-body-2 mr-2 mb-1">{{ $t('prefix') || 'Prefix' }}:</span>
                                                            <input type="text" v-model="batchPrefix" class="form-control form-control-sm mr-2 mb-1" style="width: 80px;">
                                                            <v-btn small outlined class="mb-1" @click="batchAddPrefixStartsWithNumber">{{ $t('add_prefix_starts_with_number') || 'Add prefix' }}</v-btn>
                                                        </div>
                                                    </v-card-text>
                                                </v-card>
                                            </v-expansion-panel-content>
                                        </v-expansion-panel>
                                    </v-expansion-panels>
                                    <p class="text-body-2 mb-2 mt-5">{{ $t('edit_new_name_help') || 'Edit the New name column. Check the variables you want to rename, then click Apply rename. Use Bulk fix to apply actions by issue type.' }}</p>
                                    <div v-if="applyError" class="alert alert-danger mb-2">{{ applyError }}</div>
                                    <table class="table table-bordered table-sm mb-3" style="width: 100%; max-width: 900px;">
                                        <thead>
                                            <tr>
                                                <th style="width: 44px;">
                                                    <input type="checkbox" :checked="allInvalidSelected" @change="setSelectAll($event.target.checked)" title="">
                                                </th>
                                                <th style="width: 50px;">#</th>
                                                <th>{{ $t('variable') || 'Variable' }}</th>
                                                <th>{{ $t('issue') || 'Issue' }}</th>
                                                <th style="min-width: 200px;">{{ $t('new_name') || 'New name' }}</th>
                                                <th style="width: 80px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in invalid_names" :key="'inv-' + item.name">
                                                <td>
                                                    <input type="checkbox" :checked="isSelected(item.name)" @change="toggleSelect(item.name)" title="">
                                                </td>
                                                <td>{{ index + 1 }}</td>
                                                <td><code>{{ item.name }}</code></td>
                                                <td>{{ reasonLabel(item.reason) }}</td>
                                                <td>
                                                    <input type="text" :value="proposedNewNames[item.name]" @input="updateProposedName(item.name, $event.target.value)" :placeholder="getDefaultSuggestion(item)" style="width: 200px;" class="form-control form-control-sm">
                                                </td>
                                                <td>
                                                    <v-btn x-small text @click="resetRowSuggestion(item)" :title="$t('reset') || 'Reset'">{{ $t('reset') || 'Reset' }}</v-btn>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <v-btn color="primary" :disabled="!hasPendingRenames" :loading="renameLoading" @click="openApplyConfirm">
                                        {{ $t('apply_rename') || 'Apply rename' }} ({{ renamesToApply.length }})
                                    </v-btn>
                                </div>
                            </v-tab-item>
                        </v-tabs-items>
                    </template>
                </v-card-text>
            </v-card>

            <!-- Confirm remove from metadata -->
            <v-dialog v-model="confirmRemove" max-width="560" persistent scrollable>
                <v-card>
                    <v-card-title class="text-subtitle-1">{{ $t('remove_unmatched_variables') }}</v-card-title>
                    <v-card-text>
                        <div v-if="removePreviewLoading" class="d-flex align-center py-3">
                            <v-progress-circular indeterminate color="primary" size="24"></v-progress-circular>
                            <span class="ml-3">{{ $t('loading') }}...</span>
                        </div>
                        <template v-else-if="removePreview">
                            <p class="text-body-2">{{ $t('remove_unmatched_variables_help') }}</p>
                            <div v-if="removePreview.status === 'skipped'" class="text-body-2 grey--text">
                                {{ removePreview.message === 'no_csv' ? $t('unmatched_no_csv') : $t('unmatched_csv_unreadable') }}
                            </div>
                            <template v-else>
                                <div v-if="removePreview.removed.length" class="text-body-2 mb-2">
                                    <div class="font-weight-medium mb-1">{{ $t('variables_to_remove') }} ({{ removePreview.removed.length }})</div>
                                    <code v-for="v in removePreview.removed" :key="'rm-' + v.uid" class="mr-1">{{ v.name }}</code>
                                </div>
                                <p v-else class="text-body-2">{{ $t('remove_unmatched_variables_none') }}</p>
                                <div v-if="removePreview.skipped_case.length" class="text-caption warning--text mt-2">
                                    {{ $t('unmatched_skipped_case') }}:
                                    <span v-for="v in removePreview.skipped_case" :key="'sc-' + v.name" class="mr-2"><code>{{ v.name }}</code> ≈ <code>{{ v.csv_column }}</code></span>
                                </div>
                                <div v-if="removePreview.skipped_weight.length" class="text-caption warning--text mt-2">
                                    {{ $t('unmatched_skipped_weight') }}:
                                    <code v-for="v in removePreview.skipped_weight" :key="'sw-' + v.uid" class="mr-1">{{ v.name }}</code>
                                </div>
                            </template>
                        </template>
                        <div v-if="actionError" class="alert alert-danger mt-2">{{ actionError }}</div>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="cancelRemove" :disabled="actionLoading">{{ removableCount > 0 ? ($t('cancel') || 'Cancel') : ($t('close') || 'Close') }}</v-btn>
                        <v-btn v-if="!removePreviewLoading && removableCount > 0" color="error" dark depressed
                            :loading="actionLoading" @click="confirmRemoveSubmit">{{ $t('remove_n_variables', { count: removableCount }) }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Suggest confirmation -->
            <v-dialog v-model="suggestConfirmDialog" max-width="440" persistent>
                <v-card>
                    <v-card-title class="text-subtitle-1">{{ $t('apply_suggestion') || 'Apply suggestion' }}</v-card-title>
                    <v-card-text>
                        <p class="text-body-2">{{ $t('confirm_bulk_fix', { count: suggestConfirmCount }) || 'Update ' + suggestConfirmCount + ' variable name(s)? Changes will be applied immediately.' }}</p>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="closeSuggestConfirm">{{ $t('cancel') || 'Cancel' }}</v-btn>
                        <v-btn color="primary" depressed @click="confirmApplySuggestion">{{ $t('apply') || 'Apply' }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Apply renames confirmation -->
            <v-dialog v-model="applyConfirmDialog" max-width="440" persistent>
                <v-card>
                    <v-card-title class="text-subtitle-1">{{ $t('apply_renames') || 'Apply renames' }}</v-card-title>
                    <v-card-text>
                        <p class="text-body-2">{{ $t('confirm_apply_renames', { count: pendingRenamesCount }) || 'Apply ' + pendingRenamesCount + ' rename(s)?' }}</p>
                        <div v-if="applyError" class="alert alert-danger mt-2">{{ applyError }}</div>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="closeApplyConfirm">{{ $t('cancel') || 'Cancel' }}</v-btn>
                        <v-btn color="primary" depressed :loading="renameLoading" @click="applyRenames">{{ $t('apply') || 'Apply' }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
