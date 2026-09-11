Vue.component('project-translations', {
    data: function () {
        return {
            loading: false,
            savingLanguage: false,
            adding: false,
            projectLanguage: null,
            translations: [],
            languageItems: projectTranslationLanguageItems(),
            selectedProjectLanguage: '',
            selectedTargetLanguage: '',
            languageDialog: false,
            languageDialogMode: 'project',
            languageDialogQuery: '',
            languageDialogSelected: '',
            error: '',
            addError: '',
            validation: null,
            importingOverlay: false,
            showOverlayHelp: false
        };
    },
    mounted: function () {
        this.loadTranslations();
    },
    computed: {
        ProjectID: function () {
            return this.$store.state.project_id;
        },
        canEdit: function () {
            return this.$store.state.user_has_edit_access && !this.$store.state.project_is_locked;
        },
        projectLanguageLabel: function () {
            return this.languageLabel(this.projectLanguage) || this.projectLanguage || '—';
        },
        availableTargets: function () {
            var used = {};
            if (this.projectLanguage) {
                used[this.projectLanguage] = true;
            }
            this.translations.forEach(function (row) {
                used[row.language] = true;
            });
            return this.languageItems.filter(function (lang) {
                return lang.code && !used[lang.code];
            });
        },
        apiBase: function () {
            return CI.base_url + '/api/editor/' + this.ProjectID + '/translations';
        },
        validationIssues: function () {
            return this.validation && this.validation.issues ? this.validation.issues : [];
        },
        hasValidationIssues: function () {
            return this.validationIssues.length > 0;
        },
        languageDialogTitle: function () {
            return this.languageDialogMode === 'target'
                ? this.$t('add_language')
                : this.$t('select_language');
        },
        languageDialogItems: function () {
            if (this.languageDialogMode === 'target') {
                return this.availableTargets;
            }
            var used = {};
            this.translations.forEach(function (row) {
                used[row.language] = true;
            });
            return this.languageItems.filter(function (lang) {
                return lang.code && !used[lang.code];
            });
        },
        languageDialogFiltered: function () {
            var query = (this.languageDialogQuery || '').trim().toLowerCase();
            var items = this.languageDialogItems;
            if (!query) {
                return items;
            }
            return items.filter(function (item) {
                return (item.display && item.display.toLowerCase().indexOf(query) !== -1)
                    || (item.code && item.code.toLowerCase().indexOf(query) !== -1)
                    || (item.name && item.name.toLowerCase().indexOf(query) !== -1);
            });
        },
        languageDialogCustomCode: function () {
            var code = this.selectedLanguageCode(this.languageDialogQuery);
            if (!code) {
                return '';
            }
            var exists = this.languageDialogItems.some(function (item) {
                return item.code === code;
            });
            if (exists) {
                return '';
            }
            if (this.languageDialogMode === 'target') {
                if (code === this.projectLanguage) {
                    return '';
                }
            }
            return code;
        },
        languageDialogCanConfirm: function () {
            return !!(this.languageDialogSelected || this.languageDialogCustomCode);
        }
    },
    methods: {
        languageLabel: function (code) {
            return projectTranslationLanguageLabel(code);
        },
        languageName: function (code) {
            var found = this.languageItems.find(function (item) {
                return item.code === code;
            });
            return found && found.name ? found.name : (code || '');
        },
        languageCodeBadge: function (code) {
            return String(code || '').toUpperCase();
        },
        selectedLanguageCode: function (value) {
            return projectTranslationLanguageCode(value);
        },
        loadTranslations: function () {
            var vm = this;
            vm.loading = true;
            vm.error = '';
            axios.get(vm.apiBase).then(function (res) {
                var result = (res.data && res.data.result) ? res.data.result : {};
                vm.projectLanguage = result.language || null;
                vm.selectedProjectLanguage = vm.projectLanguage || '';
                vm.translations = result.translations || [];
                vm.loadValidation();
            }).catch(function (err) {
                vm.error = vm.apiError(err);
            }).finally(function () {
                vm.loading = false;
            });
        },
        loadValidation: function () {
            var vm = this;
            axios.get(CI.base_url + '/api/validation/' + vm.ProjectID + '/translations').then(function (res) {
                vm.validation = (res.data && res.data.validation) ? res.data.validation : null;
            }).catch(function () {
                vm.validation = null;
            });
        },
        rowIssueCount: function (row) {
            var summaries = this.validation && this.validation.translations ? this.validation.translations : [];
            var i;
            for (i = 0; i < summaries.length; i++) {
                if (summaries[i].language === row.language) {
                    return summaries[i].issue_count || 0;
                }
            }
            return 0;
        },
        translationIssueLabel: function (issue) {
            var labels = {
                not_in_source: this.$t('translated_field_not_in_source'),
                source_empty: this.$t('translated_field_source_empty'),
                not_scalar: this.$t('translated_field_not_scalar'),
                not_study_metadata: this.$t('translated_field_not_study_metadata')
            };
            return labels[issue.type] || issue.message;
        },
        openLanguageDialog: function (mode) {
            this.languageDialogMode = mode;
            this.languageDialogQuery = '';
            this.languageDialogSelected = mode === 'project' ? (this.projectLanguage || '') : '';
            this.languageDialog = true;
        },
        confirmLanguageDialog: function () {
            var language = this.languageDialogSelected || this.languageDialogCustomCode;
            language = this.selectedLanguageCode(language);
            if (!language) {
                return;
            }
            this.languageDialog = false;
            if (this.languageDialogMode === 'target') {
                this.addLanguage(language);
                return;
            }
            this.setProjectLanguage(language);
        },
        setProjectLanguage: function (language) {
            var vm = this;
            language = vm.selectedLanguageCode(language || vm.selectedProjectLanguage);
            if (!language) {
                return;
            }
            vm.savingLanguage = true;
            vm.error = '';
            axios.post(vm.apiBase + '/language', { language: language }).then(function (res) {
                vm.projectLanguage = res.data.result.language;
                vm.selectedProjectLanguage = vm.projectLanguage;
            }).catch(function (err) {
                vm.error = vm.apiError(err);
            }).finally(function () {
                vm.savingLanguage = false;
            });
        },
        addLanguage: function (language) {
            var vm = this;
            language = vm.selectedLanguageCode(language || vm.selectedTargetLanguage);
            if (!language) {
                return;
            }
            vm.adding = true;
            vm.addError = '';
            axios.post(vm.apiBase, { language: language }).then(function () {
                var lang = language;
                vm.selectedTargetLanguage = '';
                vm.$router.push({ name: 'project-translation-edit', params: { language: lang } });
            }).catch(function (err) {
                vm.addError = vm.apiError(err);
            }).finally(function () {
                vm.adding = false;
            });
        },
        deleteLanguage: function (row) {
            var vm = this;
            if (!confirm(vm.$t('delete_translation_confirm') || 'Remove this language and its translations?')) {
                return;
            }
            axios.post(vm.apiBase + '/' + encodeURIComponent(row.language) + '/delete').then(function () {
                vm.loadTranslations();
            }).catch(function (err) {
                vm.error = vm.apiError(err);
            });
        },
        editLanguage: function (row) {
            this.$router.push({ name: 'project-translation-edit', params: { language: row.language } });
        },
        exportLanguage: function (row) {
            window.open(this.apiBase + '/' + encodeURIComponent(row.language) + '/export', '_blank');
        },
        exportOverlay: function (row) {
            window.open(this.apiBase + '/' + encodeURIComponent(row.language) + '/overlay?download=1', '_blank');
        },
        exportSourceOverlay: function () {
            window.open(this.apiBase + '/overlay/source?download=1', '_blank');
        },
        pickOverlayFile: function () {
            if (this.$refs.overlayFile) {
                this.$refs.overlayFile.click();
            }
        },
        importOverlay: function (event) {
            var vm = this;
            var input = event && event.target ? event.target : null;
            var file = input && input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }
            var data = new FormData();
            data.append('file', file);
            vm.importingOverlay = true;
            vm.error = '';
            axios.post(vm.apiBase + '/overlay', data).then(function () {
                if (input) {
                    input.value = '';
                }
                vm.loadTranslations();
            }).catch(function (err) {
                vm.error = vm.apiError(err);
                if (input) {
                    input.value = '';
                }
            }).finally(function () {
                vm.importingOverlay = false;
            });
        },
        apiError: function (err) {
            if (err.response && err.response.data && err.response.data.message) {
                return err.response.data.message;
            }
            return err.message || 'Request failed';
        }
    },
    template: `
        <div class="import-options-component mt-5 p-3">
            <v-card>
                <v-card-title>{{ $t('Translations') }}</v-card-title>
                <v-card-subtitle>{{ $t('translations_help') }}</v-card-subtitle>
                <v-card-text>
                    <v-alert v-if="error" type="error" dense outlined class="mb-3">{{ error }}</v-alert>
                    <v-progress-linear v-if="loading" indeterminate class="mb-3"></v-progress-linear>

                    <v-card elevation="2" class="p-3 mb-3">
                        <div class="form-group-x">
                            <label>{{ $t('project_language') }}</label>
                            <div class="d-flex align-center flex-wrap mt-2">
                                <div class="mr-3">{{ projectLanguageLabel }}</div>
                                <v-btn
                                    color="primary"
                                    small
                                    :disabled="!canEdit || savingLanguage"
                                    :loading="savingLanguage"
                                    @click="openLanguageDialog('project')"
                                >{{ projectLanguage ? $t('change_language') : $t('set_language') }}</v-btn>
                            </div>
                            <div v-if="!projectLanguage" class="text-muted small mt-2">{{ $t('project_language_required') }}</div>
                        </div>
                    </v-card>

                    <v-card elevation="2" class="p-3 mb-3">
                        <div class="d-flex align-center flex-wrap mb-3">
                            <strong>{{ $t('Translations') }}</strong>
                            <v-spacer></v-spacer>
                            <input
                                ref="overlayFile"
                                type="file"
                                accept="application/json,.json"
                                class="d-none"
                                @change="importOverlay"
                            >
                            <v-btn
                                icon
                                x-small
                                class="mr-1"
                                :color="showOverlayHelp ? 'primary' : ''"
                                :title="$t('overlay_help')"
                                @click="showOverlayHelp = !showOverlayHelp"
                            >
                                <v-icon small>mdi-information-outline</v-icon>
                            </v-btn>
                            <v-menu offset-y left>
                                <template v-slot:activator="{ on, attrs }">
                                    <v-btn
                                        small
                                        outlined
                                        class="mr-2"
                                        :loading="importingOverlay"
                                        v-bind="attrs"
                                        v-on="on"
                                    >{{ $t('Overlay') }}</v-btn>
                                </template>
                                <v-list dense>
                                    <v-list-item @click="exportSourceOverlay">
                                        <v-list-item-title>{{ $t('export_source_overlay') }}</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item
                                        v-if="canEdit && projectLanguage"
                                        :disabled="importingOverlay"
                                        :title="$t('overlay_import_help')"
                                        @click="pickOverlayFile"
                                    >
                                        <v-list-item-title>{{ $t('import_overlay') }}</v-list-item-title>
                                    </v-list-item>
                                </v-list>
                            </v-menu>
                            <v-btn
                                v-if="canEdit && projectLanguage"
                                color="primary"
                                small
                                :loading="adding"
                                :disabled="adding"
                                @click="openLanguageDialog('target')"
                            >{{ $t('add_language') }}</v-btn>
                        </div>
                        <div v-if="showOverlayHelp" class="text-muted small mb-3">{{ $t('overlay_help') }}</div>
                        <v-alert v-if="addError" type="error" dense outlined class="mb-3">{{ addError }}</v-alert>
                        <v-simple-table v-if="translations.length">
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                        <th>{{ $t('language') }}</th>
                                        <th>{{ $t('translated') }}</th>
                                        <th>{{ $t('errors') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in translations" :key="row.language">
                                        <td>
                                            <a href="#" class="d-inline-flex align-center" @click.prevent="editLanguage(row)">
                                                <v-chip
                                                    x-small
                                                    label
                                                    color="primary"
                                                    text-color="white"
                                                    class="mr-2 translation-lang-chip"
                                                >{{ languageCodeBadge(row.language) }}</v-chip>
                                                {{ languageName(row.language) }}
                                            </a>
                                        </td>
                                        <td>{{ row.fields_count }}</td>
                                        <td>
                                            <v-chip v-if="rowIssueCount(row)" x-small color="error" text-color="white">{{ rowIssueCount(row) }}</v-chip>
                                            <span v-else class="text-muted">0</span>
                                        </td>
                                        <td class="text-right text-nowrap">
                                            <v-btn small text color="primary" @click="editLanguage(row)">{{ $t('Edit') }}</v-btn>
                                            <v-menu offset-y left>
                                                <template v-slot:activator="{ on, attrs }">
                                                    <v-btn icon small v-bind="attrs" v-on="on">
                                                        <v-icon small>mdi-dots-vertical</v-icon>
                                                    </v-btn>
                                                </template>
                                                <v-list dense>
                                                    <v-list-item @click="exportLanguage(row)">
                                                        <v-list-item-title>{{ $t('view_json') }}</v-list-item-title>
                                                    </v-list-item>
                                                    <v-list-item @click="exportOverlay(row)">
                                                        <v-list-item-title>{{ $t('export_overlay') }}</v-list-item-title>
                                                    </v-list-item>
                                                    <template v-if="canEdit">
                                                        <v-divider></v-divider>
                                                        <v-list-item @click="deleteLanguage(row)">
                                                            <v-list-item-title class="error--text">{{ $t('delete') }}</v-list-item-title>
                                                        </v-list-item>
                                                    </template>
                                                </v-list>
                                            </v-menu>
                                        </td>
                                    </tr>
                                </tbody>
                            </template>
                        </v-simple-table>
                        <div v-else class="text-muted">{{ $t('no_translations') }}</div>
                    </v-card>

                    <v-card v-if="translations.length" elevation="2" class="p-3 mb-3">
                        <div class="d-flex align-center mb-3">
                            <strong>{{ $t('translation_validation') }}</strong>
                            <v-chip
                                v-if="validation"
                                :color="hasValidationIssues ? 'error' : 'success'"
                                text-color="white"
                                x-small
                                class="ml-2"
                            >{{ hasValidationIssues ? $t('failed') : $t('valid') }}</v-chip>
                        </div>
                        <v-alert v-if="hasValidationIssues" type="error" dense outlined class="mb-3">
                            {{ $t('translation_validation_issues_found', { count: validationIssues.length }) }}
                        </v-alert>
                        <v-alert v-else-if="validation" type="success" dense outlined>
                            {{ $t('no_translation_validation_issues') }}
                        </v-alert>
                        <v-simple-table v-if="hasValidationIssues">
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                        <th>{{ $t('language') }}</th>
                                        <th>{{ $t('path') }}</th>
                                        <th>{{ $t('message') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(issue, index) in validationIssues" :key="index">
                                        <td>
                                            <a href="#" @click.prevent="editLanguage({ language: issue.language })">
                                                {{ languageLabel(issue.language) }} ({{ issue.language }})
                                            </a>
                                        </td>
                                        <td><code>{{ issue.path }}</code></td>
                                        <td>{{ translationIssueLabel(issue) }}</td>
                                    </tr>
                                </tbody>
                            </template>
                        </v-simple-table>
                    </v-card>
                </v-card-text>
            </v-card>

            <v-dialog v-model="languageDialog" max-width="480" scrollable>
                <v-card>
                    <v-card-title>{{ languageDialogTitle }}</v-card-title>
                    <v-card-text class="pt-4">
                        <v-text-field
                            v-model="languageDialogQuery"
                            :placeholder="$t('search_languages')"
                            dense
                            outlined
                            hide-details
                            autofocus
                            clearable
                            height="36"
                            class="mb-3 translation-language-search"
                        ></v-text-field>
                        <v-list dense class="translation-language-list">
                            <v-list-item-group v-model="languageDialogSelected" color="primary">
                                <v-list-item
                                    v-if="languageDialogCustomCode"
                                    :value="languageDialogCustomCode"
                                    @dblclick="confirmLanguageDialog"
                                >
                                    <v-list-item-title>
                                        {{ $t('use_language_code', { code: languageDialogCustomCode }) }}
                                    </v-list-item-title>
                                </v-list-item>
                                <v-list-item
                                    v-for="item in languageDialogFiltered"
                                    :key="item.code"
                                    :value="item.code"
                                    @dblclick="confirmLanguageDialog"
                                >
                                    <v-list-item-title>{{ item.display }}</v-list-item-title>
                                </v-list-item>
                            </v-list-item-group>
                        </v-list>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="languageDialog = false">{{ $t('cancel') }}</v-btn>
                        <v-btn
                            color="primary"
                            :disabled="!languageDialogCanConfirm"
                            :loading="savingLanguage || adding"
                            @click="confirmLanguageDialog"
                        >{{ languageDialogMode === 'target' ? $t('add_language') : $t('Save') }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
