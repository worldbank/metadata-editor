Vue.component('project-translation-edit', {
    props: ['language'],
    data: function () {
        return {
            loading: false,
            saving: false,
            error: '',
            values: {},
            projectLanguage: null,
            dirty: false,
            validationIssues: []
        };
    },
    mounted: function () {
        this.loadTranslation();
    },
    watch: {
        language: function () {
            this.loadTranslation();
        }
    },
    computed: {
        ProjectID: function () {
            return this.$store.state.project_id;
        },
        canEdit: function () {
            return this.$store.state.user_has_edit_access && !this.$store.state.project_is_locked;
        },
        formData: function () {
            return this.$store.state.formData || {};
        },
        templateItems: function () {
            var template = this.$store.state.formTemplate;
            if (template && template.template && Array.isArray(template.template.items)) {
                return template.template.items;
            }
            return [];
        },
        contentSections: function () {
            var vm = this;
            return vm.templateItems.filter(function (item) {
                return vm.fieldHasContent(item);
            });
        },
        apiBase: function () {
            return CI.base_url + '/api/editor/' + this.ProjectID + '/translations';
        },
        sourceLabel: function () {
            return this.languageLabel(this.projectLanguage) || this.projectLanguage || this.$t('source');
        },
        targetLabel: function () {
            return this.languageLabel(this.language) || this.language;
        },
        completion: function () {
            var done = 0;
            var total = 0;
            var vm = this;
            this.contentSections.forEach(function (section) {
                var count = vm.countWork(section);
                done += count.done;
                total += count.total;
            });
            return {
                done: done,
                total: total,
                percent: total ? Math.round((done / total) * 100) : 0
            };
        }
    },
    methods: {
        languageLabel: function (code) {
            return projectTranslationLanguageLabel(code);
        },
        loadTranslation: function () {
            var vm = this;
            vm.loading = true;
            vm.error = '';
            axios.get(vm.apiBase + '/' + encodeURIComponent(vm.language)).then(function (res) {
                var result = (res.data && res.data.result) ? res.data.result : {};
                vm.values = vm.flattenMetadata(result.metadata || {});
                vm.dirty = false;
                return axios.get(vm.apiBase);
            }).then(function (res) {
                if (res && res.data && res.data.result) {
                    vm.projectLanguage = res.data.result.language || null;
                }
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
                var issues = res.data && res.data.validation && res.data.validation.issues
                    ? res.data.validation.issues
                    : [];
                var language = vm.language;
                vm.validationIssues = issues.filter(function (issue) {
                    return issue.language === language;
                });
            }).catch(function () {
                vm.validationIssues = [];
            });
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
        saveTranslation: function () {
            var vm = this;
            vm.saving = true;
            vm.error = '';
            axios.post(vm.apiBase + '/' + encodeURIComponent(vm.language), {
                metadata: vm.unflattenMetadata(vm.values)
            }).then(function (res) {
                var result = (res.data && res.data.result) ? res.data.result : {};
                vm.values = vm.flattenMetadata(result.metadata || {});
                vm.dirty = false;
                vm.loadValidation();
            }).catch(function (err) {
                vm.error = vm.apiError(err);
            }).finally(function () {
                vm.saving = false;
            });
        },
        exportTranslation: function () {
            window.open(this.apiBase + '/' + encodeURIComponent(this.language) + '/export', '_blank');
        },
        exportOverlay: function () {
            window.open(this.apiBase + '/' + encodeURIComponent(this.language) + '/overlay?download=1', '_blank');
        },
        apiError: function (err) {
            if (err.response && err.response.data && err.response.data.message) {
                return err.response.data.message;
            }
            return err.message || 'Request failed';
        },
        flattenMetadata: function (node, prefix) {
            var out = {};
            var key;
            if (!node || typeof node !== 'object') {
                return out;
            }
            prefix = prefix || '';
            for (key in node) {
                if (!Object.prototype.hasOwnProperty.call(node, key)) {
                    continue;
                }
                var path = prefix + '/' + key;
                var value = node[key];
                if (value !== null && typeof value === 'object') {
                    Object.assign(out, this.flattenMetadata(value, path));
                } else if (value !== null && value !== undefined && String(value).trim() !== '') {
                    out[path] = String(value);
                }
            }
            return out;
        },
        unflattenMetadata: function (map) {
            var tree = {};
            Object.keys(map || {}).forEach(function (path) {
                var value = map[path];
                if (value === null || value === undefined || String(value).trim() === '') {
                    return;
                }
                var parts = String(path).replace(/^\/+/, '').split('/');
                var ref = tree;
                var i;
                for (i = 0; i < parts.length; i++) {
                    var key = parts[i];
                    var isIndex = key !== '' && String(parseInt(key, 10)) === key;
                    if (isIndex) {
                        key = parseInt(key, 10);
                    }
                    if (i === parts.length - 1) {
                        ref[key] = String(value);
                        return;
                    }
                    var next = parts[i + 1];
                    var nextIsIndex = next !== '' && String(parseInt(next, 10)) === next;
                    if (!ref[key] || typeof ref[key] !== 'object') {
                        ref[key] = nextIsIndex ? [] : {};
                    }
                    ref = ref[key];
                }
            });
            return tree;
        },
        toPointer: function (path) {
            if (!path) {
                return '';
            }
            path = String(path);
            if (path.charAt(0) === '/') {
                return path;
            }
            path = path.replace(/\[(\d+)\]/g, '/$1').replace(/\./g, '/');
            return '/' + path.replace(/^\/+/, '');
        },
        cellPointer: function (base, index, propKey) {
            return this.toPointer(base) + '/' + index + '/' + propKey;
        },
        sourceValue: function (key) {
            return _.get(this.formData, key);
        },
        hasText: function (value) {
            if (value === null || value === undefined) {
                return false;
            }
            if (typeof value === 'object') {
                return false;
            }
            return String(value).trim() !== '';
        },
        isScalarField: function (field) {
            if (!field) {
                return false;
            }
            var type = field.display_type || field.type;
            return _.includes(['text', 'string', 'textarea', 'dropdown', 'dropdown-custom', 'integer', 'number', 'date'], type);
        },
        isTextarea: function (field) {
            var type = field.display_type || field.type;
            return type === 'textarea';
        },
        scalarColumns: function (field) {
            var props = field.props || field.items || [];
            var vm = this;
            return props.filter(function (prop) {
                return prop.type !== 'nested_array' && prop.type !== 'section' && prop.type !== 'array';
            }).filter(function (prop) {
                return vm.isScalarField(prop) || !prop.type || prop.type === 'string' || prop.type === 'text';
            });
        },
        childArrayFields: function (field) {
            var props = field.props || field.items || [];
            return props.filter(function (prop) {
                return prop.type === 'array' || prop.type === 'nested_array';
            });
        },
        fieldHasContent: function (field) {
            if (!field) {
                return false;
            }
            if (field.type === 'section' || field.type === 'section_container') {
                var items = field.items || [];
                var i;
                for (i = 0; i < items.length; i++) {
                    if (this.fieldHasContent(items[i])) {
                        return true;
                    }
                }
                return false;
            }
            if (this.isScalarField(field)) {
                return this.hasText(this.sourceValue(field.key));
            }
            if (field.type === 'simple_array' || field.type === 'array' || field.type === 'nested_array') {
                return this.sourceRows(field.key).length > 0;
            }
            return false;
        },
        sectionAnchor: function (field, index) {
            return 'tr-sec-' + (field.key || index);
        },
        scrollToSection: function (anchor) {
            var el = document.getElementById(anchor);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
        getCell: function (path) {
            return this.values[path] || '';
        },
        setCell: function (path, value) {
            this.$set(this.values, path, value);
            this.dirty = true;
        },
        sourceRows: function (key) {
            var value = this.sourceValue(key);
            return Array.isArray(value) ? value : [];
        },
        simpleArrayRows: function (key) {
            var value = this.sourceValue(key);
            if (!Array.isArray(value)) {
                return [];
            }
            return value.filter(function (item) {
                return item !== null && item !== undefined && String(item).trim() !== '';
            });
        },
        rowHasScalarSource: function (row, columns) {
            var i;
            for (i = 0; i < columns.length; i++) {
                var val = row ? row[columns[i].key] : '';
                if (this.hasText(val)) {
                    return true;
                }
            }
            return false;
        },
        countWork: function (field) {
            var total = 0;
            var done = 0;
            var vm = this;
            var walk = function (item) {
                if (!item) {
                    return;
                }
                if (item.type === 'section' || item.type === 'section_container') {
                    (item.items || []).forEach(walk);
                    return;
                }
                if (vm.isScalarField(item) && vm.hasText(vm.sourceValue(item.key))) {
                    total += 1;
                    if (vm.hasText(vm.getCell(vm.toPointer(item.key)))) {
                        done += 1;
                    }
                    return;
                }
                if (item.type === 'simple_array') {
                    vm.sourceRows(item.key).forEach(function (value, ri) {
                        if (!vm.hasText(value)) {
                            return;
                        }
                        total += 1;
                        if (vm.hasText(vm.getCell(vm.toPointer(item.key) + '/' + ri))) {
                            done += 1;
                        }
                    });
                    return;
                }
                if (item.type === 'array' || item.type === 'nested_array') {
                    var cols = vm.scalarColumns(item);
                    vm.sourceRows(item.key).forEach(function (row, ri) {
                        cols.forEach(function (col) {
                            if (!row || !vm.hasText(row[col.key])) {
                                return;
                            }
                            total += 1;
                            if (vm.hasText(vm.getCell(vm.cellPointer(item.key, ri, col.key)))) {
                                done += 1;
                            }
                        });
                        vm.childArrayFields(item).forEach(function (child) {
                            walk({
                                key: item.key + '.' + ri + '.' + child.key,
                                title: child.title,
                                type: child.type,
                                props: child.props,
                                display_type: child.display_type
                            });
                        });
                    });
                }
            };
            walk(field);
            return { done: done, total: total };
        }
    },
    template: `
        <div class="container-fluid px-3 py-3 translation-edit">
            <div class="translation-toolbar mb-3">
                <div class="d-flex align-center flex-wrap">
                    <v-btn text small @click="$router.push({ name: 'project-translations' })">
                        <v-icon left small>mdi-arrow-left</v-icon>{{ $t('Translations') }}
                    </v-btn>
                    <h5 class="mb-0">{{ targetLabel }}</h5>
                    <v-spacer></v-spacer>
                    <v-menu offset-y left>
                        <template v-slot:activator="{ on, attrs }">
                            <v-btn icon small class="mr-2" v-bind="attrs" v-on="on">
                                <v-icon small>mdi-dots-vertical</v-icon>
                            </v-btn>
                        </template>
                        <v-list dense>
                            <v-list-item @click="exportTranslation">
                                <v-list-item-title>{{ $t('view_json') }}</v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="exportOverlay">
                                <v-list-item-title>{{ $t('export_overlay') }}</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                    <v-btn color="primary" small :disabled="!canEdit || saving" :loading="saving" @click="saveTranslation">
                        {{ $t('Save') }}
                    </v-btn>
                </div>
                <div v-if="!loading && completion.total" class="translation-complete">
                    <div class="translation-complete-track" :title="completion.done + ' / ' + completion.total">
                        <div class="translation-complete-fill" :style="{width: completion.percent + '%'}"></div>
                    </div>
                    <div class="translation-complete-meta">
                        {{ completion.done }}/{{ completion.total }}
                        <span>{{ completion.percent }}%</span>
                    </div>
                </div>
            </div>

            <v-alert v-if="error" type="error" dense class="mb-3">{{ error }}</v-alert>
            <v-alert v-if="!loading && validationIssues.length" type="error" dense outlined class="mb-3">
                {{ $t('translation_validation_issues_found', { count: validationIssues.length }) }}
                <ul class="mb-0 mt-2 pl-4">
                    <li v-for="(issue, index) in validationIssues" :key="index">
                        <code>{{ issue.path }}</code> — {{ translationIssueLabel(issue) }}
                    </li>
                </ul>
            </v-alert>
            <v-progress-linear v-if="loading" indeterminate class="mb-3"></v-progress-linear>

            <div v-if="!loading && !contentSections.length" class="text-muted py-4">
                {{ $t('no_source_text_to_translate') || 'No study metadata text to translate.' }}
            </div>

            <template v-else-if="contentSections.length">
                <div class="translation-board">
                    <div class="translation-grid-head translation-pair">
                        <div class="translation-col">{{ sourceLabel }}</div>
                        <div class="translation-col">{{ targetLabel }}</div>
                    </div>

                    <div v-for="(field, idx) in contentSections" :key="field.key || idx">
                        <h6 :id="sectionAnchor(field, idx)" class="translation-section-title">{{ field.title }}</h6>
                        <project-translation-fields
                            :fields="(field.type==='section' || field.type==='section_container') ? (field.items || []) : [field]"
                            :values="values"
                            :form-data="formData"
                            :can-edit="canEdit"
                            @update="setCell($event.path, $event.value)"
                        ></project-translation-fields>
                    </div>
                </div>
            </template>
        </div>
    `
});

Vue.component('project-translation-fields', {
    props: ['fields', 'values', 'formData', 'canEdit'],
    methods: {
        toPointer: function (path) {
            if (!path) {
                return '';
            }
            path = String(path);
            if (path.charAt(0) === '/') {
                return path;
            }
            path = path.replace(/\[(\d+)\]/g, '/$1').replace(/\./g, '/');
            return '/' + path.replace(/^\/+/, '');
        },
        sourceValue: function (key) {
            return _.get(this.formData, key);
        },
        hasText: function (value) {
            if (value === null || value === undefined) {
                return false;
            }
            if (typeof value === 'object') {
                return false;
            }
            return String(value).trim() !== '';
        },
        isScalarField: function (field) {
            if (!field) {
                return false;
            }
            var type = field.display_type || field.type;
            return _.includes(['text', 'string', 'textarea', 'dropdown', 'dropdown-custom', 'integer', 'number', 'date'], type);
        },
        isTextarea: function (field) {
            return (field.display_type || field.type) === 'textarea';
        },
        scalarColumns: function (field) {
            var props = field.props || [];
            var vm = this;
            return props.filter(function (prop) {
                return prop.type !== 'nested_array' && prop.type !== 'section' && prop.type !== 'array';
            }).filter(function (prop) {
                return vm.isScalarField(prop) || !prop.type || prop.type === 'string' || prop.type === 'text';
            });
        },
        childArrayFields: function (field) {
            return (field.props || []).filter(function (prop) {
                return prop.type === 'array' || prop.type === 'nested_array';
            });
        },
        sourceRows: function (key) {
            var value = this.sourceValue(key);
            return Array.isArray(value) ? value : [];
        },
        getCell: function (path) {
            return this.values[path] || '';
        },
        emitCell: function (path, value) {
            this.$emit('update', { path: path, value: value });
        },
        cellPointer: function (base, index, propKey) {
            return this.toPointer(base) + '/' + index + '/' + propKey;
        },
        simplePointer: function (base, index) {
            return this.toPointer(base) + '/' + index;
        },
        rowHasText: function (row, columns) {
            var i;
            if (!row) {
                return false;
            }
            for (i = 0; i < columns.length; i++) {
                if (this.hasText(row[columns[i].key])) {
                    return true;
                }
            }
            return false;
        },
        fieldHasContent: function (field) {
            if (!field) {
                return false;
            }
            if (field.type === 'section' || field.type === 'section_container') {
                var items = field.items || [];
                var i;
                for (i = 0; i < items.length; i++) {
                    if (this.fieldHasContent(items[i])) {
                        return true;
                    }
                }
                return false;
            }
            if (this.isScalarField(field)) {
                return this.hasText(this.sourceValue(field.key));
            }
            if (field.type === 'simple_array' || field.type === 'array' || field.type === 'nested_array') {
                return this.sourceRows(field.key).length > 0;
            }
            return false;
        },
        sourceTextareaRows: function (field) {
            var text = String(this.sourceValue(field.key) || '');
            var lines = text.split(/\r?\n/);
            var wrapped = 0;
            lines.forEach(function (line) {
                wrapped += Math.max(1, Math.ceil(line.length / 68));
            });
            return Math.max(this.isTextarea(field) ? 4 : 2, Math.min(18, wrapped));
        }
    },
    template: `
        <div>
            <div v-for="(field, idx) in fields" :key="field.key || idx">
                <template v-if="(field.type==='section' || field.type==='section_container') && fieldHasContent(field)">
                    <div class="translation-field-title">{{ field.title }}</div>
                    <project-translation-fields
                        :fields="field.items || []"
                        :values="values"
                        :form-data="formData"
                        :can-edit="canEdit"
                        @update="$emit('update', $event)"
                    ></project-translation-fields>
                </template>

                <div v-else-if="isScalarField(field) && hasText(sourceValue(field.key))" class="translation-field">
                    <div class="translation-field-title">{{ field.title }}</div>
                    <div class="translation-pair">
                        <div class="translation-col">
                            <div class="translation-source" :class="{'translation-source--area': isTextarea(field)}">{{ sourceValue(field.key) }}</div>
                        </div>
                        <div class="translation-col">
                            <textarea
                                v-if="isTextarea(field)"
                                class="form-control form-control-sm"
                                :rows="sourceTextareaRows(field)"
                                :disabled="!canEdit"
                                :value="getCell(toPointer(field.key))"
                                @input="emitCell(toPointer(field.key), $event.target.value)"
                            ></textarea>
                            <input
                                v-else
                                type="text"
                                class="form-control form-control-sm"
                                :disabled="!canEdit"
                                :value="getCell(toPointer(field.key))"
                                @input="emitCell(toPointer(field.key), $event.target.value)"
                            />
                        </div>
                    </div>
                </div>

                <div v-else-if="field.type==='simple_array' && sourceRows(field.key).length" class="translation-field translation-field--table">
                    <div class="translation-field-title">{{ field.title }}</div>
                    <div class="translation-pair">
                        <div class="translation-col">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>#</th><th>{{ field.title }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="(item, ri) in sourceRows(field.key)" :key="'s-'+field.key+'-'+ri">
                                        <td>{{ ri + 1 }}</td>
                                        <td><div class="translation-source">{{ item }}</div></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="translation-col">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>#</th><th>{{ field.title }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="(item, ri) in sourceRows(field.key)" :key="'t-'+field.key+'-'+ri">
                                        <td>{{ ri + 1 }}</td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                :disabled="!canEdit"
                                                :value="getCell(simplePointer(field.key, ri))"
                                                @input="emitCell(simplePointer(field.key, ri), $event.target.value)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-else-if="(field.type==='array' || field.type==='nested_array') && sourceRows(field.key).length" class="translation-field translation-field--table">
                    <div class="translation-field-title">{{ field.title }}</div>
                    <div class="translation-pair" v-if="scalarColumns(field).length">
                        <div class="translation-col">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th v-for="col in scalarColumns(field)" :key="'sh-'+col.key">{{ col.title }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, ri) in sourceRows(field.key)" :key="'sr-'+field.key+'-'+ri">
                                        <td>{{ ri + 1 }}</td>
                                        <td v-for="col in scalarColumns(field)" :key="'sc-'+col.key"><div class="translation-source">{{ row[col.key] }}</div></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="translation-col">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th v-for="col in scalarColumns(field)" :key="'th-'+col.key">{{ col.title }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, ri) in sourceRows(field.key)" :key="'tr-'+field.key+'-'+ri">
                                        <td>{{ ri + 1 }}</td>
                                        <td v-for="col in scalarColumns(field)" :key="'tc-'+col.key">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                :disabled="!canEdit"
                                                :value="getCell(cellPointer(field.key, ri, col.key))"
                                                @input="emitCell(cellPointer(field.key, ri, col.key), $event.target.value)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-for="(row, ri) in sourceRows(field.key)" :key="'nested-'+field.key+'-'+ri">
                        <div v-for="child in childArrayFields(field)" :key="child.key + '-' + ri" class="mt-2" v-if="Array.isArray(row[child.key]) && row[child.key].length">
                            <div class="translation-field-title">{{ ri + 1 }}. {{ child.title }}</div>
                            <project-translation-fields
                                :fields="[{ key: field.key + '.' + ri + '.' + child.key, title: child.title, type: child.type, props: child.props, display_type: child.display_type }]"
                                :values="values"
                                :form-data="formData"
                                :can-edit="canEdit"
                                @update="$emit('update', $event)"
                            ></project-translation-fields>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `
});
