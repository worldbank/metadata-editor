// Full-page wizard: import DSD components from a CSV sample.
Vue.component('data-structure-csv-bootstrap', {
    props: {
        id: { type: [String, Number], required: true }
    },
    data: function () {
        return {
            wizardStep: 1,
            structureLoading: false,
            structureTitle: '',
            structureName: '',
            file: null,
            fileName: '',
            parseError: '',
            parsing: false,
            delimiter: ',',
            delimiterItems: [
                { value: ',', text: 'Comma (,)' },
                { value: ';', text: 'Semicolon (;)' },
                { value: '\t', text: 'Tab' }
            ],
            previewRowLimit: 2,
            previewByteLimit: 128 * 1024,
            headers: [],
            sampleRows: [],
            columnMappings: [],
            codelistCache: {},
            codelistPickerDialog: false,
            codelistPickerMappingColumn: null,
            codelistPickerSearch: '',
            codelistPickerList: [],
            codelistPickerLoading: false,
            codelistPickerTotal: 0,
            codelistPickerRequestSeq: 0,
            applyPayloadPreview: null,
            applyLoading: false,
            componentNameMaxLength: 100
        };
    },
    computed: {
        numericStructureId: function () {
            var n = parseInt(this.id, 10);
            return isNaN(n) ? null : n;
        },
        pageSubtitle: function () {
            if (this.structureTitle) {
                return this.structureTitle;
            }
            if (this.structureName) {
                return this.structureName;
            }
            if (this.numericStructureId) {
                return 'Data structure #' + this.numericStructureId;
            }
            return '';
        },
        roleItems: function () {
            var vm = this;
            return Object.keys(vm.columnTypes).map(function (k) {
                return { value: k, text: vm.columnTypes[k] };
            });
        },
        columnTypes: function () {
            return {
                dimension: this.$t('dimension') || 'Dimension',
                time_period: this.$t('time_period') || 'Time Period',
                measure: this.$t('measure') || 'Measure',
                attribute: this.$t('attribute') || 'Attribute',
                indicator_id: this.$t('indicator_id') || 'Indicator ID',
                indicator_name: this.$t('indicator_name') || 'Indicator Name',
                annotation: this.$t('annotation') || 'Annotation',
                geography: this.$t('geography') || 'Geography',
                observation_value: this.$t('observation_value') || 'Observation Value',
                periodicity: this.$t('periodicity') || 'Periodicity'
            };
        },
        codelistModeItems: function () {
            return [
                { value: 'none', text: 'None' },
                { value: 'from_csv', text: 'Create from CSV' },
                { value: 'global', text: 'Link global codelist' }
            ];
        },
        rolesWithoutCodelist: function () {
            return ['indicator_id', 'time_period', 'observation_value'];
        },
        rolesExcludedFromCodelistLabelField: function () {
            return [
                'dimension',
                'geography',
                'time_period',
                'observation_value',
                'indicator_id',
                'periodicity',
                'indicator_name'
            ];
        },
        mappableColumnMappings: function () {
            return this.columnMappings.filter(function (m) { return !m.is_label_only; });
        },
        labelOnlyColumnsSummary: function () {
            var vm = this;
            var out = [];
            vm.columnMappings.forEach(function (m) {
                if (!m.is_label_only) {
                    return;
                }
                var usedBy = vm.columnMappings.filter(function (c) {
                    return c.codelist_label_column === m.csv_column && c.codelist_mode === 'from_csv';
                }).map(function (c) { return c.csv_column; });
                out.push({ column: m.csv_column, used_by: usedBy });
            });
            return out;
        },
        mappedComponents: function () {
            return this.columnMappings.filter(function (m) {
                return m.included && !m.is_label_only && m.role;
            });
        },
        includedMappingCount: function () {
            return this.mappedComponents.length;
        },
        allMappingsIncluded: function () {
            var mappable = this.mappableColumnMappings;
            return mappable.length > 0 && mappable.every(function (m) { return m.included; });
        },
        someMappingsIncluded: function () {
            var mappable = this.mappableColumnMappings;
            var n = mappable.filter(function (m) { return m.included; }).length;
            return n > 0 && n < mappable.length;
        },
        validationErrors: function () {
            return this.buildValidationReport().errors;
        },
        validationWarnings: function () {
            return this.buildValidationReport().warnings;
        },
        roleChecklist: function () {
            return this.buildValidationReport().roles;
        },
        mappingValid: function () {
            return this.validationErrors.length === 0 && this.mappedComponents.length > 0;
        },
        canGoToMapping: function () {
            return !this.parsing && !this.parseError && this.headers.length > 0;
        },
        canGoToReview: function () {
            return this.mappingValid;
        },
        applySummary: function () {
            var vm = this;
            var components = [];
            var codelistsFromCsv = [];
            var codelistsGlobal = [];
            vm.mappedComponents.forEach(function (m) {
                var entry = {
                    csv_column: m.csv_column,
                    name: (m.component_name || '').trim() || vm.normalizeComponentName(m.csv_column),
                    label: (m.component_label || '').trim() || m.csv_column,
                    column_type: m.role
                };
                if (vm.hasCodelistConfigured(m)) {
                    if (m.codelist_mode === 'global' && m.codelist_id) {
                        entry.codelist = { mode: 'global', codelist_id: m.codelist_id };
                        codelistsGlobal.push({ column: m.csv_column, codelist_id: m.codelist_id, label: m.codelist_display || '' });
                    } else if (m.codelist_mode === 'from_csv') {
                        var labelCol = (m.codelist_label_column || '').trim();
                        var samplePairs = vm.sampleDistinctPairs(m.csv_column, labelCol);
                        entry.codelist = {
                            mode: 'from_csv',
                            code_column: m.csv_column,
                            label_column: labelCol || null
                        };
                        codelistsFromCsv.push({
                            column: m.csv_column,
                            label_column: labelCol,
                            sample_code_count: samplePairs.length
                        });
                    }
                }
                components.push(entry);
            });
            return {
                component_count: components.length,
                components: components,
                codelists_from_csv: codelistsFromCsv,
                codelists_global: codelistsGlobal,
                columns: vm.columnMappings.map(function (m) {
                    return {
                        csv_column: m.csv_column,
                        included: !!m.included,
                        is_label_only: !!m.is_label_only,
                        role: m.role,
                        component_name: m.component_name,
                        codelist_mode: m.codelist_mode,
                        codelist_id: m.codelist_id,
                        codelist_label_column: m.codelist_label_column || null
                    };
                }),
                excluded_columns: vm.columnMappings.filter(function (m) { return !m.included && !m.is_label_only; }).map(function (m) { return m.csv_column; }),
                label_only_columns: vm.labelOnlyColumnsSummary
            };
        },
        codelistPickerHeaders: function () {
            return [
                { text: 'Title', value: 'title', sortable: false },
                { text: 'Name', value: 'name', sortable: false, width: '140px' },
                { text: 'Agency', value: 'agency', sortable: false, width: '100px' },
                { text: 'Version', value: 'version', sortable: false, width: '90px' }
            ];
        },
        codelistPickerSubtitle: function () {
            var mapping = this.getCodelistPickerMapping();
            return mapping ? mapping.csv_column : '';
        }
    },
    watch: {
        id: function () {
            this.bootstrapPage();
        },
        '$route.params.id': function () {
            this.bootstrapPage();
        },
        delimiter: function () {
            if (this.file) {
                this.parseSelectedFile();
            }
        }
    },
    mounted: function () {
        var vm = this;
        vm.loadCodelistPickerDebounced = _.debounce(function () {
            vm.loadCodelistPickerList();
        }, 300);
        vm.bootstrapPage();
    },
    beforeDestroy: function () {
        if (this.loadCodelistPickerDebounced && this.loadCodelistPickerDebounced.cancel) {
            this.loadCodelistPickerDebounced.cancel();
        }
    },
    methods: {
        apiBase: function () {
            return ((typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '') + '/api/data_structures';
        },
        codelistsApiBase: function () {
            return ((typeof CI !== 'undefined' && CI.site_url) ? CI.site_url.replace(/\/$/, '') : '') + '/api/codelists';
        },
        bootstrapPage: function () {
            this.resetWizard();
            this.loadStructure();
        },
        loadStructure: function () {
            var vm = this;
            if (!vm.numericStructureId) {
                vm.goBackToEdit();
                return;
            }
            vm.structureLoading = true;
            axios.get(vm.apiBase() + '/single/' + vm.numericStructureId)
                .then(function (res) {
                    vm.structureLoading = false;
                    if (res.data && res.data.status === 'success' && res.data.data_structure) {
                        var ds = res.data.data_structure;
                        vm.structureTitle = ds.title || '';
                        vm.structureName = ds.name || '';
                    }
                })
                .catch(function () {
                    vm.structureLoading = false;
                });
        },
        goBackToEdit: function () {
            if (this.numericStructureId) {
                this.$router.push('/edit/' + this.numericStructureId);
            } else {
                this.$router.push('/');
            }
        },
        resetWizard: function () {
            this.wizardStep = 1;
            this.file = null;
            this.fileName = '';
            this.parseError = '';
            this.parsing = false;
            this.headers = [];
            this.sampleRows = [];
            this.columnMappings = [];
            this.applyPayloadPreview = null;
            this.closeCodelistPicker();
        },
        onFileSelected: function (event) {
            var f = null;
            if (!event || event === null || (Array.isArray(event) && event.length === 0)) {
                f = null;
            } else if (event instanceof File) {
                f = event;
            } else if (event && event.target && event.target.files && event.target.files.length > 0) {
                f = event.target.files[0];
            } else if (Array.isArray(event) && event.length > 0 && event[0] instanceof File) {
                f = event[0];
            } else if (event && event.length && event[0] instanceof File) {
                f = event[0];
            }
            this.file = f;
            this.fileName = f ? f.name : '';
            this.parseError = '';
            if (f) {
                this.parseSelectedFile();
            } else {
                this.headers = [];
                this.sampleRows = [];
                this.columnMappings = [];
            }
        },
        parseSelectedFile: function () {
            var vm = this;
            if (!vm.file) {
                return;
            }
            vm.parsing = true;
            vm.parseError = '';
            var blob = vm.file.size > vm.previewByteLimit
                ? vm.file.slice(0, vm.previewByteLimit)
                : vm.file;
            var reader = new FileReader();
            reader.onload = function (e) {
                try {
                    var text = String(e.target.result || '');
                    var parsed = vm.parseCsvText(text, vm.delimiter, vm.previewRowLimit + 1);
                    if (!parsed.headers.length) {
                        throw new Error('No column headers found in CSV.');
                    }
                    vm.headers = parsed.headers;
                    vm.sampleRows = parsed.rows.slice(0, vm.previewRowLimit);
                    vm.columnMappings = vm.buildInitialMappings(parsed.headers);
                    vm.wizardStep = 1;
                } catch (err) {
                    vm.parseError = err.message || 'Failed to parse CSV';
                    vm.headers = [];
                    vm.sampleRows = [];
                    vm.columnMappings = [];
                } finally {
                    vm.parsing = false;
                }
            };
            reader.onerror = function () {
                vm.parsing = false;
                vm.parseError = 'Could not read the selected file.';
            };
            reader.readAsText(blob);
        },
        parseCsvText: function (text, delimiter, maxRows) {
            text = text.replace(/^\uFEFF/, '');
            var rows = [];
            var row = [];
            var field = '';
            var inQuotes = false;
            var i = 0;
            var d = delimiter || ',';
            var dLen = d.length;

            function pushField() {
                row.push(field);
                field = '';
            }
            function pushRow() {
                if (row.length === 1 && row[0] === '' && rows.length === 0) {
                    row = [];
                    return;
                }
                rows.push(row);
                row = [];
            }

            while (i < text.length && rows.length < maxRows) {
                if (inQuotes) {
                    if (text.charAt(i) === '"') {
                        if (text.charAt(i + 1) === '"') {
                            field += '"';
                            i += 2;
                            continue;
                        }
                        inQuotes = false;
                        i++;
                        continue;
                    }
                    field += text.charAt(i);
                    i++;
                    continue;
                }
                if (text.substr(i, dLen) === d) {
                    pushField();
                    i += dLen;
                    continue;
                }
                if (text.charAt(i) === '"') {
                    inQuotes = true;
                    i++;
                    continue;
                }
                if (text.charAt(i) === '\r') {
                    pushField();
                    pushRow();
                    i++;
                    if (text.charAt(i) === '\n') {
                        i++;
                    }
                    continue;
                }
                if (text.charAt(i) === '\n') {
                    pushField();
                    pushRow();
                    i++;
                    continue;
                }
                field += text.charAt(i);
                i++;
            }
            if (rows.length < maxRows && (field !== '' || row.length > 0)) {
                pushField();
                pushRow();
            }

            if (!rows.length) {
                return { headers: [], rows: [] };
            }
            var headers = rows[0].map(function (h) { return String(h || '').trim(); });
            var dataRows = rows.slice(1).filter(function (r) {
                return r.some(function (c) { return String(c || '').trim() !== ''; });
            });
            return { headers: headers, rows: dataRows };
        },
        normalizeComponentName: function (name) {
            var vm = this;
            var maxLen = vm.componentNameMaxLength;
            var n = String(name || '').trim();
            n = n.replace(/[^A-Za-z0-9_]+/g, '_');
            n = n.replace(/_+/g, '_');
            n = n.replace(/^_+|_+$/g, '');
            if (n === '') {
                n = 'column';
            }
            if (n.length > maxLen) {
                n = n.substring(0, maxLen).replace(/_+$/, '');
                if (n === '') {
                    n = 'column';
                }
            }
            return n;
        },
        sanitizeComponentNameInput: function (value) {
            var maxLen = this.componentNameMaxLength;
            var n = String(value || '');
            n = n.replace(/[^A-Za-z0-9_]/g, '');
            n = n.replace(/^_+/, '');
            if (n.length > maxLen) {
                n = n.substring(0, maxLen);
            }
            return n;
        },
        componentNameValidationMessage: function (name) {
            var maxLen = this.componentNameMaxLength;
            var n = String(name || '').trim();
            if (!n) {
                return 'Required';
            }
            if (n.length > maxLen) {
                return 'Max ' + maxLen + ' characters';
            }
            if (!/^(?!_)[A-Za-z0-9_]+$/.test(n)) {
                return 'SDMX ID: letters, digits, underscore only; must not start with _';
            }
            return '';
        },
        componentNameFieldError: function (mapping) {
            if (!mapping || !mapping.included) {
                return '';
            }
            return this.componentNameValidationMessage(mapping.component_name);
        },
        onComponentNameInput: function (mapping) {
            if (!mapping) {
                return;
            }
            var sanitized = this.sanitizeComponentNameInput(mapping.component_name);
            if (sanitized !== mapping.component_name) {
                mapping.component_name = sanitized;
            }
        },
        guessRoleForHeader: function (header) {
            var u = String(header || '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '_');
            if (/^(INDICATOR(_ID)?|INDICATORID|SERIES(_ID)?|INDICATOR_CODE)$/.test(u)) {
                return 'indicator_id';
            }
            if (/^(INDICATOR(_NAME)?|INDICATORNAME|SERIES(_NAME)?)$/.test(u)) {
                return 'indicator_name';
            }
            if (/^(TIME(_)?PERIOD|TIME|YEAR|DATE|PERIOD)$/.test(u)) {
                return 'time_period';
            }
            if (/^(OBS(_)?VALUE|OBSERVATION(_)?VALUE|VALUE|OBS)$/.test(u)) {
                return 'observation_value';
            }
            if (/^(REF(_)?AREA|GEO(GRAPHY)?|COUNTRY|LOCATION|AREA)$/.test(u)) {
                return 'geography';
            }
            if (/^FREQ(UENCY)?$/.test(u)) {
                return 'periodicity';
            }
            return 'attribute';
        },
        isLikelyLabelOnlyColumn: function (header, headers) {
            if (!/(_LABEL|_NAME|_TITLE)$/i.test(String(header || ''))) {
                return false;
            }
            return !!this.guessLabelForColumn(header, headers);
        },
        isAutoIncludedRole: function (role, header, headers) {
            if (this.isLikelyLabelOnlyColumn(header, headers)) {
                return false;
            }
            if (role === 'attribute') {
                return false;
            }
            return true;
        },
        guessLabelForColumn: function (header, headers) {
            var base = String(header || '').replace(/(_LABEL|_NAME|_TITLE)$/i, '');
            if (!base) {
                return '';
            }
            for (var i = 0; i < headers.length; i++) {
                if (headers[i] === base) {
                    return base;
                }
            }
            return '';
        },
        buildInitialMappings: function (headers) {
            var vm = this;
            var mappings = headers.map(function (h) {
                var isLabelOnly = vm.isLikelyLabelOnlyColumn(h, headers);
                if (isLabelOnly) {
                    return {
                        csv_column: h,
                        included: false,
                        is_label_only: true,
                        role: 'attribute',
                        component_name: '',
                        component_label: h,
                        codelist_mode: 'none',
                        codelist_id: null,
                        codelist_display: '',
                        codelist_label_column: ''
                    };
                }
                var role = vm.guessRoleForHeader(h);
                var defaultCodelist = (role === 'dimension' || role === 'geography') ? 'from_csv' : 'none';
                var included = vm.isAutoIncludedRole(role, h, headers);
                return {
                    csv_column: h,
                    included: included,
                    is_label_only: false,
                    role: role,
                    component_name: vm.normalizeComponentName(h),
                    component_label: h,
                    codelist_mode: defaultCodelist,
                    codelist_id: null,
                    codelist_display: '',
                    codelist_label_column: ''
                };
            });
            mappings.forEach(function (m) {
                if (m.is_label_only || m.codelist_mode !== 'from_csv') {
                    return;
                }
                m.codelist_label_column = vm.findSuggestedLabelColumn(m.csv_column, mappings);
            });
            vm.syncLabelOnlyFlags(mappings);
            return mappings;
        },
        syncLabelOnlyFlags: function (mappings) {
            var vm = this;
            var list = mappings || vm.columnMappings;
            list.forEach(function (m) {
                if (vm.isLikelyLabelOnlyColumn(m.csv_column, vm.headers)) {
                    m.is_label_only = true;
                    m.included = false;
                    m.codelist_mode = 'none';
                    m.codelist_id = null;
                    m.codelist_display = '';
                }
            });
            list.forEach(function (m) {
                if (vm.isLikelyLabelOnlyColumn(m.csv_column, vm.headers)) {
                    return;
                }
                var referenced = list.some(function (c) {
                    return c.codelist_mode === 'from_csv' && c.codelist_label_column === m.csv_column;
                });
                if (!referenced) {
                    m.is_label_only = false;
                }
            });
            list.forEach(function (m) {
                if (m.codelist_mode !== 'from_csv' || !m.codelist_label_column) {
                    return;
                }
                var labelCol = list.find(function (x) { return x.csv_column === m.codelist_label_column; });
                if (labelCol) {
                    labelCol.is_label_only = true;
                    labelCol.included = false;
                    labelCol.codelist_mode = 'none';
                    labelCol.codelist_id = null;
                    labelCol.codelist_display = '';
                }
            });
        },
        toggleSelectAllIncluded: function (checked) {
            var include = checked !== false;
            this.mappableColumnMappings.forEach(function (m) {
                m.included = include;
            });
        },
        onIncludeChange: function (mapping) {
            if (!mapping) {
                return;
            }
            if (mapping.is_label_only) {
                mapping.included = false;
                return;
            }
            if (mapping.included) {
                return;
            }
            mapping.codelist_id = null;
            mapping.codelist_display = '';
            this.clearLabelOnlyUsageOf(mapping.csv_column);
        },
        clearLabelOnlyUsageOf: function (csvColumn) {
            var col = String(csvColumn || '');
            if (!col) {
                return;
            }
            this.columnMappings.forEach(function (m) {
                if (m.codelist_label_column === col) {
                    m.codelist_label_column = '';
                }
            });
        },
        findSuggestedLabelColumn: function (codeColumn, mappings) {
            var vm = this;
            var base = String(codeColumn || '');
            var candidates = [
                base + '_label',
                base + '_LABEL',
                base + '_name',
                base + '_NAME',
                base + '_title',
                base + '_TITLE'
            ];
            for (var i = 0; i < mappings.length; i++) {
                var m = mappings[i];
                if (m.csv_column === codeColumn) {
                    continue;
                }
                if (candidates.indexOf(m.csv_column) >= 0) {
                    return m.csv_column;
                }
            }
            return '';
        },
        isRoleExcludedFromCodelistLabelField: function (role) {
            return this.rolesExcludedFromCodelistLabelField.indexOf(role) >= 0;
        },
        isEligibleCodelistLabelField: function (mapping) {
            if (!mapping || !mapping.csv_column) {
                return false;
            }
            if (mapping.is_label_only) {
                return true;
            }
            if (mapping.included && this.isRoleExcludedFromCodelistLabelField(mapping.role)) {
                return false;
            }
            return true;
        },
        supportsCodelistRole: function (role) {
            return role && this.rolesWithoutCodelist.indexOf(role) < 0;
        },
        showCodelistOptions: function (mapping) {
            return mapping && mapping.included && !mapping.is_label_only
                && this.supportsCodelistRole(mapping.role);
        },
        hasCodelistConfigured: function (mapping) {
            return mapping && (mapping.codelist_mode === 'global' || mapping.codelist_mode === 'from_csv');
        },
        onRoleChange: function (mapping) {
            if (!mapping || mapping.is_label_only) {
                return;
            }
            mapping.included = true;
            if (!this.supportsCodelistRole(mapping.role)) {
                mapping.codelist_mode = 'none';
                mapping.codelist_id = null;
                mapping.codelist_display = '';
                mapping.codelist_label_column = '';
            } else if (mapping.codelist_mode === 'from_csv' && !mapping.codelist_label_column) {
                mapping.codelist_label_column = this.findSuggestedLabelColumn(mapping.csv_column, this.columnMappings);
            }
            this.syncLabelOnlyFlags();
        },
        onCodelistModeChange: function (mapping) {
            if (!mapping) {
                return;
            }
            if (mapping.codelist_mode === 'none') {
                mapping.codelist_id = null;
                mapping.codelist_display = '';
                mapping.codelist_label_column = '';
                this.syncLabelOnlyFlags();
                return;
            }
            if (mapping.codelist_mode === 'global') {
                mapping.codelist_label_column = '';
            } else if (mapping.codelist_mode === 'from_csv') {
                mapping.codelist_id = null;
                mapping.codelist_display = '';
                mapping.codelist_label_column = this.findSuggestedLabelColumn(mapping.csv_column, this.columnMappings);
            }
            this.syncLabelOnlyFlags();
        },
        onCodelistLabelColumnChange: function (mapping) {
            this.syncLabelOnlyFlags();
        },
        sampleValuesForColumn: function (csvColumn) {
            var idx = this.headers.indexOf(csvColumn);
            if (idx < 0) {
                return [];
            }
            var seen = {};
            var out = [];
            this.sampleRows.forEach(function (row) {
                var v = row[idx] != null ? String(row[idx]).trim() : '';
                if (v === '' || seen[v]) {
                    return;
                }
                seen[v] = true;
                out.push(v);
            });
            return out;
        },
        sampleDistinctPairs: function (codeColumn, labelColumn) {
            var vm = this;
            var codeIdx = vm.headers.indexOf(codeColumn);
            if (codeIdx < 0) {
                return [];
            }
            var labelIdx = labelColumn ? vm.headers.indexOf(labelColumn) : -1;
            var map = {};
            vm.sampleRows.forEach(function (row) {
                var code = row[codeIdx] != null ? String(row[codeIdx]).trim() : '';
                if (code === '') {
                    return;
                }
                var label = labelIdx >= 0 && row[labelIdx] != null ? String(row[labelIdx]).trim() : '';
                if (!map[code]) {
                    map[code] = label || code;
                } else if (label && map[code] !== label) {
                    map[code] = label;
                }
            });
            return Object.keys(map).map(function (code) {
                return { code: code, label: map[code] };
            });
        },
        labelColumnItemsFor: function (mapping) {
            var vm = this;
            var items = [{ value: '', text: '(none — code as label)' }];
            vm.columnMappings.forEach(function (m) {
                if (m.csv_column === mapping.csv_column) {
                    return;
                }
                if (!vm.isEligibleCodelistLabelField(m)) {
                    return;
                }
                var text = m.csv_column;
                if (m.is_label_only) {
                    text += ' (label column)';
                } else if (!m.included) {
                    text += ' (not imported)';
                }
                items.push({ value: m.csv_column, text: text });
            });
            return items;
        },
        codelistItemText: function (cl) {
            if (!cl) {
                return '';
            }
            var title = (cl.title && String(cl.title).trim()) || '';
            var agency = String(cl.agency || '');
            var name = String(cl.name || '');
            var suffix = agency && name ? ' (' + agency + ':' + name + ')' : '';
            return (title || name || String(cl.id)) + suffix;
        },
        cacheCodelist: function (cl) {
            if (!cl || cl.id == null) {
                return;
            }
            Vue.set(this.codelistCache, cl.id, cl);
        },
        getCodelistPickerMapping: function () {
            var col = this.codelistPickerMappingColumn;
            if (!col) {
                return null;
            }
            for (var i = 0; i < this.columnMappings.length; i++) {
                if (this.columnMappings[i].csv_column === col) {
                    return this.columnMappings[i];
                }
            }
            return null;
        },
        openCodelistPicker: function (mapping) {
            if (!mapping) {
                return;
            }
            if (this.loadCodelistPickerDebounced && this.loadCodelistPickerDebounced.cancel) {
                this.loadCodelistPickerDebounced.cancel();
            }
            this.codelistPickerMappingColumn = mapping.csv_column;
            this.codelistPickerSearch = '';
            this.codelistPickerList = [];
            this.codelistPickerTotal = 0;
            this.codelistPickerLoading = false;
            this.codelistPickerDialog = true;
            this.loadCodelistPickerList();
        },
        closeCodelistPicker: function () {
            if (this.loadCodelistPickerDebounced && this.loadCodelistPickerDebounced.cancel) {
                this.loadCodelistPickerDebounced.cancel();
            }
            this.codelistPickerRequestSeq += 1;
            this.codelistPickerDialog = false;
            this.codelistPickerMappingColumn = null;
            this.codelistPickerSearch = '';
            this.codelistPickerList = [];
            this.codelistPickerTotal = 0;
            this.codelistPickerLoading = false;
        },
        onCodelistPickerSearchInput: function () {
            if (!this.codelistPickerDialog) {
                return;
            }
            if (this.loadCodelistPickerDebounced) {
                this.loadCodelistPickerDebounced();
            } else {
                this.loadCodelistPickerList();
            }
        },
        loadCodelistPickerList: function () {
            var vm = this;
            var requestId = ++vm.codelistPickerRequestSeq;
            vm.codelistPickerLoading = true;
            var params = {
                page: 1,
                per_page: 200,
                order_by: 'name',
                order_dir: 'ASC',
                exclude_archived: 1
            };
            var q = (vm.codelistPickerSearch || '').trim();
            if (q.length >= 1) {
                params.search = q;
            }
            axios.get(vm.codelistsApiBase(), { params: params, timeout: 30000 })
                .then(function (res) {
                    vm.codelistPickerLoading = false;
                    if (requestId !== vm.codelistPickerRequestSeq || !vm.codelistPickerDialog) {
                        return;
                    }
                    if (res.data && res.data.status === 'success') {
                        vm.codelistPickerList = res.data.codelists || [];
                        vm.codelistPickerTotal = res.data.total != null ? res.data.total : vm.codelistPickerList.length;
                        vm.codelistPickerList.forEach(function (cl) { vm.cacheCodelist(cl); });
                    } else {
                        vm.codelistPickerList = [];
                        vm.codelistPickerTotal = 0;
                        var msg = (res.data && res.data.message) ? res.data.message : 'Could not load codelists.';
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('onFail', msg);
                        }
                    }
                })
                .catch(function (err) {
                    vm.codelistPickerLoading = false;
                    if (requestId !== vm.codelistPickerRequestSeq) {
                        return;
                    }
                    vm.codelistPickerList = [];
                    vm.codelistPickerTotal = 0;
                    var msg = 'Could not load codelists.';
                    if (err.code === 'ECONNABORTED') {
                        msg = 'Codelist search timed out. Try a shorter search term.';
                    } else if (err.response && err.response.data && err.response.data.message) {
                        msg = err.response.data.message;
                    }
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', msg);
                    }
                });
        },
        selectCodelistFromPicker: function (cl) {
            var mapping = this.getCodelistPickerMapping();
            if (!mapping || !cl || cl.id == null) {
                return;
            }
            mapping.codelist_id = cl.id;
            mapping.codelist_display = this.codelistItemText(cl);
            this.cacheCodelist(cl);
            this.closeCodelistPicker();
        },
        clearGlobalCodelist: function (mapping) {
            if (!mapping) {
                return;
            }
            mapping.codelist_id = null;
            mapping.codelist_display = '';
        },
        codelistPickerRowClass: function (item) {
            var mapping = this.getCodelistPickerMapping();
            if (mapping && mapping.codelist_id && item && item.id === mapping.codelist_id) {
                return 'ds-csv-codelist-pick-row--selected';
            }
            return '';
        },
        buildValidationReport: function () {
            var vm = this;
            var errors = [];
            var warnings = [];
            var byType = {};
            if (vm.mappedComponents.length === 0) {
                errors.push('Select at least one column to import and assign it a component role.');
            }
            vm.mappedComponents.forEach(function (m) {
                var t = m.role;
                if (!byType[t]) {
                    byType[t] = [];
                }
                byType[t].push(m);
            });

            ['indicator_id', 'time_period', 'observation_value'].forEach(function (type) {
                var count = byType[type] ? byType[type].length : 0;
                var label = vm.columnTypes[type] || type;
                if (count === 0) {
                    errors.push('Required role missing: ' + label + ' (exactly one column).');
                } else if (count > 1) {
                    errors.push('Too many columns mapped as ' + label + ' (max 1).');
                }
            });

            ['geography', 'periodicity', 'indicator_name'].forEach(function (type) {
                var count = byType[type] ? byType[type].length : 0;
                var label = vm.columnTypes[type] || type;
                if (type === 'geography' && count === 0) {
                    warnings.push('Recommended role not set: ' + label + '.');
                }
                if (count > 1) {
                    errors.push('Too many columns mapped as ' + label + ' (max 1).');
                }
            });

            vm.mappedComponents.forEach(function (m) {
                var name = (m.component_name || '').trim();
                var nameErr = vm.componentNameValidationMessage(name);
                if (nameErr) {
                    errors.push('Component name for column "' + m.csv_column + '": ' + nameErr + '.');
                }
                if (vm.hasCodelistConfigured(m)) {
                    if (m.codelist_mode === 'global' && !m.codelist_id) {
                        errors.push('Select a global codelist for "' + m.csv_column + '" or choose None.');
                    }
                    if (m.codelist_mode === 'from_csv') {
                        var labelCol = (m.codelist_label_column || '').trim();
                        if (labelCol && vm.headers.indexOf(labelCol) < 0) {
                            errors.push('Label column "' + labelCol + '" for "' + m.csv_column + '" was not found.');
                        } else if (labelCol) {
                            var labelMapping = vm.columnMappings.find(function (c) { return c.csv_column === labelCol; });
                            if (!labelMapping) {
                                errors.push('Label field "' + labelCol + '" for "' + m.csv_column + '" was not found.');
                            } else if (labelMapping.included) {
                                errors.push('Label field "' + labelCol + '" for "' + m.csv_column + '" must not be imported as a component.');
                            } else if (!vm.isEligibleCodelistLabelField(labelMapping)) {
                                errors.push('Label field "' + labelCol + '" cannot be used for "' + m.csv_column + '" (structural roles such as dimension or time period cannot be label fields).');
                            }
                        }
                    }
                }
            });

            var names = {};
            vm.mappedComponents.forEach(function (m) {
                var n = (m.component_name || '').trim();
                if (!n) {
                    return;
                }
                var key = n.toLowerCase();
                if (names[key]) {
                    errors.push('Duplicate component name "' + n + '" (case-insensitive match with "' + names[key] + '").');
                    return;
                }
                names[key] = n;
            });

            var roles = [
                { type: 'indicator_id', label: 'Indicator ID', tier: 'required' },
                { type: 'time_period', label: 'Time period', tier: 'required' },
                { type: 'observation_value', label: 'Observation value', tier: 'required' },
                { type: 'geography', label: 'Geography', tier: 'recommended' },
                { type: 'periodicity', label: 'Periodicity', tier: 'optional' },
                { type: 'indicator_name', label: 'Indicator name', tier: 'optional' }
            ].map(function (role) {
                var cols = byType[role.type] || [];
                return {
                    type: role.type,
                    label: role.label,
                    tier: role.tier,
                    present: cols.length > 0,
                    columns: cols.map(function (c) { return c.component_name || c.csv_column; })
                };
            });

            return { errors: errors, warnings: warnings, roles: roles };
        },
        goToStep: function (step) {
            if (step === 2 && !this.canGoToMapping) {
                return;
            }
            if (step === 3 && !this.canGoToReview) {
                return;
            }
            if (step === 3) {
                this.applyPayloadPreview = this.buildApplyPayloadPreview();
            }
            this.wizardStep = step;
        },
        buildApplyPayloadPreview: function () {
            return {
                structure_id: this.numericStructureId,
                file_name: this.fileName,
                delimiter: this.delimiter,
                components: this.applySummary.components
            };
        },
        buildApplyRequestPayload: function () {
            return {
                delimiter: this.delimiter,
                dry_run: false,
                overwrite: false,
                components: this.applySummary.components
            };
        },
        onApplyClick: function () {
            var vm = this;
            if (!vm.mappingValid || !vm.file || !vm.numericStructureId) {
                return;
            }
            vm.applyPayloadPreview = vm.buildApplyPayloadPreview();
            vm.applyLoading = true;
            var fd = new FormData();
            fd.append('file', vm.file, vm.fileName || 'upload.csv');
            fd.append('payload', JSON.stringify(vm.buildApplyRequestPayload()));
            axios.post(vm.apiBase() + '/import_components_csv/' + vm.numericStructureId, fd, {
                timeout: 300000
            })
                .then(function (res) {
                    if (res.data && res.data.status === 'success') {
                        var summary = res.data.summary || {};
                        var created = (summary.components_created || []).length;
                        var msg = created === 1
                            ? '1 component created.'
                            : created + ' components created.';
                        if (typeof EventBus !== 'undefined') {
                            EventBus.$emit('onSuccess', msg);
                        }
                        vm.$router.push('/edit/' + vm.numericStructureId);
                        return;
                    }
                    var failMsg = (res.data && res.data.message) ? res.data.message : 'Import failed.';
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', failMsg);
                    }
                })
                .catch(function (err) {
                    var msg = 'Import failed.';
                    if (err.response && err.response.data && err.response.data.message) {
                        msg = err.response.data.message;
                    }
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', msg);
                    }
                })
                .finally(function () {
                    vm.applyLoading = false;
                });
        },
        columnTypeColor: function (type) {
            var colors = {
                geography: 'green darken-1',
                time_period: 'green darken-1',
                indicator_id: 'deep-purple',
                observation_value: 'purple darken-1',
                dimension: 'blue darken-1',
                attribute: 'cyan darken-1',
                annotation: 'blue-grey',
                periodicity: 'amber darken-2',
                indicator_name: 'indigo',
                measure: 'brown'
            };
            return colors[type] || 'blue-grey darken-1';
        },
        formatSamplePreview: function (values) {
            if (!values || !values.length) {
                return '—';
            }
            return values.join(', ');
        },
        roleColor: function (role) {
            if (role.present) {
                return 'success';
            }
            if (role.tier === 'recommended') {
                return 'orange';
            }
            if (role.tier === 'required') {
                return 'error';
            }
            return 'grey';
        }
    },
    template: `
        <div class="ds-csv-bootstrap-page">
            <div class="d-flex align-center flex-wrap mb-3" style="gap:8px;">
                <v-btn text small class="px-0" @click="goBackToEdit">
                    <v-icon left small>mdi-arrow-left</v-icon>
                    Back to edit
                </v-btn>
                <v-spacer></v-spacer>
                <v-progress-circular v-if="structureLoading" indeterminate size="20" width="2" color="primary"></v-progress-circular>
            </div>
            <v-card class="ds-csv-bootstrap">
                <v-card-title class="d-flex flex-wrap align-center py-3">
                    <v-icon left color="primary" large>mdi-file-delimited</v-icon>
                    <div>
                        <div class="text-h6">Import components from CSV</div>
                        <div v-if="pageSubtitle" class="text-caption grey--text text--darken-1">{{ pageSubtitle }}</div>
                    </div>
                </v-card-title>
                <v-divider></v-divider>
                <v-card-text class="pa-0">
                    <v-stepper v-model="wizardStep" alt-labels flat class="ds-csv-stepper">
                        <v-stepper-header>
                            <v-stepper-step :complete="wizardStep > 1" step="1" editable @click="goToStep(1)">Select file</v-stepper-step>
                            <v-divider></v-divider>
                            <v-stepper-step :complete="wizardStep > 2" step="2" :editable="canGoToMapping" @click="goToStep(2)">Map columns</v-stepper-step>
                            <v-divider></v-divider>
                            <v-stepper-step step="3" :editable="canGoToReview" @click="goToStep(3)">Review</v-stepper-step>
                        </v-stepper-header>
                        <v-stepper-items>
                            <v-stepper-content step="1" class="pa-6">
                                <p class="text-body-2 grey--text text--darken-1 mb-3">
                                    Choose a CSV file. Only the header and first {{ previewRowLimit }} data row(s) are read locally for column mapping hints.
                                    The full file will be uploaded after you confirm mappings (server step — not yet implemented).
                                </p>
                                <v-row dense align="center">
                                    <v-col cols="12" sm="8">
                                        <v-file-input
                                            :value="file"
                                            accept=".csv,text/csv"
                                            label="CSV file"
                                            prepend-icon="mdi-paperclip"
                                            show-size clearable
                                            dense outlined hide-details
                                            class="ds-csv-step-control"
                                            :loading="parsing"
                                            @change="onFileSelected"
                                        ></v-file-input>
                                    </v-col>
                                    <v-col cols="12" sm="4">
                                        <v-select
                                            v-model="delimiter"
                                            :items="delimiterItems"
                                            item-value="value" item-text="text"
                                            label="Delimiter"
                                            dense outlined hide-details
                                            class="ds-csv-step-control"
                                        ></v-select>
                                    </v-col>
                                </v-row>
                                <v-alert v-if="parseError" type="error" dense text class="mt-3">{{ parseError }}</v-alert>
                                <v-alert v-if="file && !parseError && headers.length" type="info" dense text class="mt-3">
                                    <strong>{{ headers.length }}</strong> columns detected
                                    <span v-if="sampleRows.length"> · {{ sampleRows.length }} sample row(s) loaded</span>
                                </v-alert>
                            </v-stepper-content>

                            <v-stepper-content step="2" class="pa-6">
                                <p class="text-body-2 grey--text text--darken-1 mb-3">
                                    Check the columns to import and assign each a component role.
                                    For codelists created from CSV, pick a <strong>Label field</strong> on the code column
                                    (e.g. <code>ref_area</code> → label field <code>ref_area_label</code>).
                                    Label-only columns are detected automatically and are not imported as components.
                                    Component names follow SDMX ID rules (A–Z, a–z, 0–9, underscore; max 100; no leading _).
                                </p>
                                <div class="text-caption grey--text mb-3">
                                    {{ includedMappingCount }} of {{ mappableColumnMappings.length }} columns selected for import
                                </div>
                                <v-alert v-for="(err, i) in validationErrors" :key="'e'+i" type="error" dense text class="mb-2">{{ err }}</v-alert>
                                <v-alert v-for="(w, i) in validationWarnings" :key="'w'+i" type="warning" dense text class="mb-2">{{ w }}</v-alert>
                                <div class="ds-csv-mapping-wrap">
                                    <v-simple-table dense class="ds-csv-mapping-table">
                                        <thead>
                                            <tr>
                                                <th class="ds-csv-map-check-col">
                                                    <v-checkbox
                                                        :input-value="allMappingsIncluded"
                                                        :indeterminate="someMappingsIncluded"
                                                        hide-details dense class="ma-0 pa-0"
                                                        title="Select all columns"
                                                        @change="toggleSelectAllIncluded"
                                                    ></v-checkbox>
                                                </th>
                                                <th>CSV column</th>
                                                <th>Sample values</th>
                                                <th>Role</th>
                                                <th>Component name</th>
                                                <th>Codelist</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(mapping, idx) in mappableColumnMappings" :key="mapping.csv_column"
                                                :class="{ 'ds-csv-mapping-row--excluded': !mapping.included }">
                                                <td class="ds-csv-map-check-col">
                                                    <v-checkbox
                                                        v-model="mapping.included"
                                                        hide-details dense class="ma-0 pa-0"
                                                        @change="onIncludeChange(mapping)"
                                                    ></v-checkbox>
                                                </td>
                                                <td class="font-weight-medium text-caption">{{ mapping.csv_column }}</td>
                                                <td class="text-caption grey--text text--darken-1 ds-csv-sample-cell">
                                                    {{ formatSamplePreview(sampleValuesForColumn(mapping.csv_column)) }}
                                                </td>
                                                <td class="ds-csv-map-col-role">
                                                    <div class="ds-csv-map-stack">
                                                        <v-select
                                                            v-model="mapping.role"
                                                            :items="roleItems"
                                                            item-value="value" item-text="text"
                                                            dense outlined hide-details
                                                            class="ds-csv-map-control"
                                                            :disabled="!mapping.included"
                                                            @change="onRoleChange(mapping)"
                                                        ></v-select>
                                                    </div>
                                                </td>
                                                <td class="ds-csv-map-col-name">
                                                    <v-text-field
                                                        v-if="mapping.included"
                                                        v-model="mapping.component_name"
                                                        dense outlined
                                                        :hide-details="!componentNameFieldError(mapping)"
                                                        :error-messages="componentNameFieldError(mapping) ? [componentNameFieldError(mapping)] : []"
                                                        class="ds-csv-map-control"
                                                        :maxlength="componentNameMaxLength"
                                                        title="SDMX component ID: letters, digits, underscore; must not start with _ (max 100)"
                                                        @input="onComponentNameInput(mapping)"
                                                    ></v-text-field>
                                                    <span v-else class="grey--text text-caption">—</span>
                                                </td>
                                                <td class="ds-csv-map-col-codelist">
                                                    <template v-if="showCodelistOptions(mapping)">
                                                        <div class="ds-csv-map-stack">
                                                            <v-select
                                                                v-model="mapping.codelist_mode"
                                                                :items="codelistModeItems"
                                                                item-value="value" item-text="text"
                                                                dense outlined hide-details
                                                                class="ds-csv-map-control"
                                                                @change="onCodelistModeChange(mapping)"
                                                            ></v-select>
                                                            <div v-if="mapping.codelist_mode === 'global'" class="ds-csv-global-codelist-pick d-flex align-center">
                                                                <v-text-field
                                                                    :value="mapping.codelist_display"
                                                                    readonly
                                                                    dense outlined hide-details
                                                                    placeholder="Choose codelist…"
                                                                    title="Choose codelist"
                                                                    class="ds-csv-map-control ds-csv-global-codelist-field"
                                                                    @click.stop="openCodelistPicker(mapping)"
                                                                ></v-text-field>
                                                                <v-btn icon x-small class="ml-1" title="Choose codelist"
                                                                    @click.stop="openCodelistPicker(mapping)">
                                                                    <v-icon small>mdi-magnify</v-icon>
                                                                </v-btn>
                                                                <v-btn v-if="mapping.codelist_id" icon x-small class="ml-1" title="Clear"
                                                                    @click="clearGlobalCodelist(mapping)">
                                                                    <v-icon small>mdi-close</v-icon>
                                                                </v-btn>
                                                            </div>
                                                            <v-select
                                                                v-if="mapping.codelist_mode === 'from_csv'"
                                                                v-model="mapping.codelist_label_column"
                                                                :items="labelColumnItemsFor(mapping)"
                                                                item-value="value" item-text="text"
                                                                placeholder="Label field"
                                                                dense outlined hide-details
                                                                class="ds-csv-map-control"
                                                                @change="onCodelistLabelColumnChange(mapping)"
                                                            ></v-select>
                                                        </div>
                                                    </template>
                                                    <span v-else class="grey--text text-caption">—</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </v-simple-table>
                                </div>
                                <v-alert v-if="labelOnlyColumnsSummary.length" type="info" dense text class="mt-3">
                                    <div class="text-caption font-weight-medium mb-1">Label-only columns (not imported as components)</div>
                                    <div v-for="item in labelOnlyColumnsSummary" :key="item.column" class="text-caption">
                                        <code>{{ item.column }}</code>
                                        <span v-if="item.used_by.length"> → used by {{ item.used_by.join(', ') }}</span>
                                        <span v-else class="grey--text"> — unused</span>
                                    </div>
                                </v-alert>
                            </v-stepper-content>

                            <v-stepper-content step="3" class="pa-6">
                                <v-alert v-if="!mappingValid" type="error" dense text class="mb-3">
                                    Fix mapping errors before applying.
                                </v-alert>
                                <div class="text-subtitle-2 mb-2">Structure roles</div>
                                <div class="d-flex flex-wrap mb-4" style="gap:8px;">
                                    <v-chip v-for="role in roleChecklist" :key="role.type" small
                                        :color="roleColor(role)" :text-color="role.present ? 'white' : undefined"
                                        :outlined="!role.present">
                                        {{ role.label }}
                                        <span v-if="role.present" class="ml-1">✓</span>
                                    </v-chip>
                                </div>
                                <v-row dense>
                                    <v-col cols="12" sm="3">
                                        <v-card outlined class="pa-3 text-center">
                                            <div class="text-h5 primary--text">{{ applySummary.component_count }}</div>
                                            <div class="text-caption">Components</div>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="12" sm="3">
                                        <v-card outlined class="pa-3 text-center">
                                            <div class="text-h5 primary--text">{{ applySummary.codelists_from_csv.length }}</div>
                                            <div class="text-caption">Codelists from CSV</div>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="12" sm="3">
                                        <v-card outlined class="pa-3 text-center">
                                            <div class="text-h5 primary--text">{{ applySummary.codelists_global.length }}</div>
                                            <div class="text-caption">Linked global codelists</div>
                                        </v-card>
                                    </v-col>
                                    <v-col cols="12" sm="3">
                                        <v-card outlined class="pa-3 text-center">
                                            <div class="text-h5 grey--text text--darken-1">{{ applySummary.excluded_columns.length }}</div>
                                            <div class="text-caption">Excluded columns</div>
                                        </v-card>
                                    </v-col>
                                </v-row>
                                <div class="mt-4">
                                    <div class="text-subtitle-2 mb-2">Components to create</div>
                                    <v-simple-table dense>
                                        <thead>
                                            <tr><th>Name</th><th>Role</th><th>CSV column</th><th>Codelist</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="c in applySummary.components" :key="c.name">
                                                <td><code class="text-caption">{{ c.name }}</code></td>
                                                <td>
                                                    <v-chip x-small :color="columnTypeColor(c.column_type)" dark class="text-capitalize">
                                                        {{ columnTypes[c.column_type] || c.column_type }}
                                                    </v-chip>
                                                </td>
                                                <td class="text-caption">{{ c.csv_column }}</td>
                                                <td class="text-caption">
                                                    <span v-if="c.codelist && c.codelist.mode === 'global'">Global #{{ c.codelist.codelist_id }}</span>
                                                    <span v-else-if="c.codelist && c.codelist.mode === 'from_csv'">
                                                        From CSV<span v-if="c.codelist.label_column"> + {{ c.codelist.label_column }}</span>
                                                    </span>
                                                    <span v-else>—</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </v-simple-table>
                                </div>
                                <v-expansion-panels v-if="applyPayloadPreview" flat class="mt-4">
                                    <v-expansion-panel>
                                        <v-expansion-panel-header class="text-caption">Apply payload preview (for developers)</v-expansion-panel-header>
                                        <v-expansion-panel-content>
                                            <pre class="ds-csv-payload-preview">{{ JSON.stringify(applyPayloadPreview, null, 2) }}</pre>
                                        </v-expansion-panel-content>
                                    </v-expansion-panel>
                                </v-expansion-panels>
                                <v-alert type="info" dense text class="mt-3">
                                    Apply creates components on this data structure and global codelists (prefixed with <code>CL_</code>) from CSV columns where configured.
                                </v-alert>
                            </v-stepper-content>
                        </v-stepper-items>
                    </v-stepper>
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions class="pa-4 ds-csv-bootstrap-actions">
                    <v-btn text @click="goBackToEdit">Cancel</v-btn>
                    <v-spacer></v-spacer>
                    <v-btn v-if="wizardStep > 1" text @click="goToStep(wizardStep - 1)">Back</v-btn>
                    <v-btn v-if="wizardStep === 1" color="primary" depressed
                        :disabled="!canGoToMapping" @click="goToStep(2)">
                        Next: Map columns
                    </v-btn>
                    <v-btn v-if="wizardStep === 2" color="primary" depressed
                        :disabled="!canGoToReview" @click="goToStep(3)">
                        Next: Review
                    </v-btn>
                    <v-btn v-if="wizardStep === 3" color="primary" depressed
                        :disabled="!mappingValid || applyLoading" :loading="applyLoading"
                        @click="onApplyClick">
                        Apply
                    </v-btn>
                </v-card-actions>
            </v-card>

            <v-dialog v-model="codelistPickerDialog" max-width="760" persistent content-class="ds-csv-codelist-picker-dialog">
                <v-card class="ds-csv-codelist-picker-card">
                    <v-card-title class="text-subtitle-1 py-3 flex-shrink-0">
                        Link global codelist
                        <span v-if="codelistPickerSubtitle" class="text-caption grey--text ml-2">({{ codelistPickerSubtitle }})</span>
                        <v-spacer></v-spacer>
                        <v-btn icon small @click="closeCodelistPicker"><v-icon>mdi-close</v-icon></v-btn>
                    </v-card-title>
                    <v-divider></v-divider>
                    <div class="ds-csv-codelist-picker-body">
                        <div class="ds-csv-codelist-picker-search mb-4">
                            <v-text-field
                                v-model="codelistPickerSearch"
                                dense outlined hide-details clearable
                                prepend-inner-icon="mdi-magnify"
                                append-icon="mdi-arrow-right"
                                placeholder="Search by title, name, or agency…"
                                @keyup.enter="loadCodelistPickerList()"
                                @click:append="loadCodelistPickerList()"
                                @click:clear="loadCodelistPickerList()"
                                @input="onCodelistPickerSearchInput"
                            ></v-text-field>
                        </div>
                        <div class="ds-csv-codelist-picker-table-area">
                            <div class="ds-csv-codelist-picker-count text-caption grey--text">
                                <span v-if="codelistPickerLoading">Loading…</span>
                                <span v-else>
                                    Showing {{ codelistPickerList.length }}<span v-if="codelistPickerTotal > codelistPickerList.length"> of {{ codelistPickerTotal }}</span> codelist(s)
                                </span>
                            </div>
                            <div class="ds-csv-codelist-picker-table-wrap elevation-1">
                                <v-data-table
                                    :headers="codelistPickerHeaders"
                                    :items="codelistPickerList"
                                    :items-per-page="-1"
                                    item-key="id"
                                    dense
                                    hide-default-footer
                                    disable-sort
                                    mobile-breakpoint="0"
                                    class="ds-csv-codelist-pick-table"
                                    @click:row="selectCodelistFromPicker"
                                >
                                    <template v-slot:item.title="{ item }">
                                        <span class="text-caption">{{ item.title || item.name || '—' }}</span>
                                    </template>
                                    <template v-slot:item.name="{ item }">
                                        <code class="text-caption">{{ item.name }}</code>
                                    </template>
                                    <template v-slot:item.agency="{ item }">
                                        <span class="text-caption">{{ item.agency || '—' }}</span>
                                    </template>
                                    <template v-slot:item.version="{ item }">
                                        <span class="text-caption">{{ item.version || '—' }}</span>
                                    </template>
                                    <template v-slot:no-data>
                                        <div class="text-caption grey--text pa-4 text-center">No codelists found.</div>
                                    </template>
                                </v-data-table>
                            </div>
                        </div>
                    </div>
                    <v-divider></v-divider>
                    <v-card-actions class="pa-3 flex-shrink-0">
                        <v-spacer></v-spacer>
                        <v-btn text small @click="closeCodelistPicker">Cancel</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    `
});
