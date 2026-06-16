// Indicator DSD edit form component
Vue.component('indicator-dsd-edit', {
    props: {
        column: { type: Object, required: true },
        projectSid: { type: [Number, String], required: true },
        index_key: { default: null },
        dictionaries: {
            type: Object,
            default: function() {
                return { freq_codes: [] };
            }
        },
        /** All DSD columns for this project (detect FREQ / periodicity row). */
        allColumns: {
            type: Array,
            default: function() {
                return [];
            }
        },
        /** Global codelists list fetched once by the parent component. */
        globalCodelistsList: {
            type: Array,
            default: function() {
                return [];
            }
        },
        globalCodelistsLoading: {
            type: Boolean,
            default: false
        },
        readOnly: {
            type: Boolean,
            default: false
        }
    },
    data: function() {
        return {
            data_types: {
                "string": this.$t("string") || "String",
                "integer": this.$t("integer") || "Integer",
                "float": this.$t("float") || "Float",
                "double": this.$t("double") || "Double",
                "date": this.$t("date") || "Date",
                "boolean": this.$t("boolean") || "Boolean"
            },
            column_types: {
                "dimension": this.$t("dimension") || "Dimension",
                "time_period": this.$t("time_period") || "Time Period",
                "measure": this.$t("measure") || "Measure",
                "attribute": this.$t("attribute") || "Attribute",
                "indicator_id": this.$t("indicator_id") || "Indicator ID",
                "indicator_name": this.$t("indicator_name") || "Indicator Name",
                "annotation": this.$t("annotation") || "Annotation",
                "geography": this.$t("geography") || "Geography",
                "observation_value": this.$t("observation_value") || "Observation Value",
                "periodicity": this.$t("periodicity") || "Periodicity"
            },
            /** Display label when global_codelist_id is not in the first page of /api/codelists. */
            linkedGlobalDisplayName: '',
            globalResolveLoading: false,
            globalResolveSeq: 0
        }
    },
    created: function() {
        if (!this.column.code_list) {
            Vue.set(this.column, 'code_list', []);
        }

        // Initialize code_list_reference if not present
        if (!this.column.code_list_reference) {
            Vue.set(this.column, 'code_list_reference', {
                id: '',
                name: '',
                version: '',
                uri: '',
                note: ''
            });
        }

        // Initialize metadata if not present
        if (!this.column.metadata) {
            Vue.set(this.column, 'metadata', {});
        }
        var _ctInit = this.column.column_type;
        if (_ctInit === 'time_period' || _ctInit === 'observation_value') {
            if (this.column.metadata.hasOwnProperty('value_label_column')) {
                Vue.delete(this.column.metadata, 'value_label_column');
            }
            var selfInit = this;
            this.$nextTick(function() {
                selfInit.OnValueUpdate();
            });
        } else if (!this.column.metadata.hasOwnProperty('value_label_column')) {
            Vue.set(this.column.metadata, 'value_label_column', this.column.metadata.value_label_column || '');
        }
        if (this.column.metadata.hasOwnProperty('freq')) {
            Vue.delete(this.column.metadata, 'freq');
        }
        if (this.column.metadata.hasOwnProperty('import_freq_code')) {
            Vue.delete(this.column.metadata, 'import_freq_code');
        }
        if (this.column.hasOwnProperty('time_period_format')) {
            Vue.delete(this.column, 'time_period_format');
        }
        this.initSdmxAttributeFields();
        if (!this.column.codelist_type || this.column.codelist_type === 'local') {
            Vue.set(this.column, 'codelist_type', 'none');
        }
        if (this.column.global_codelist_id === undefined) {
            Vue.set(this.column, 'global_codelist_id', null);
        }
        this.resolveLinkedGlobalCodelist();
    },
    watch: {
        column: {
            handler: function(newVal) {
                this.$emit('input', newVal);
            },
            deep: true
        },
        'column.codelist_type': function() {
            this.resolveLinkedGlobalCodelist();
        },
        'column.global_codelist_id': function() {
            this.resolveLinkedGlobalCodelist();
        },
        'column.id': function() {
            this.resolveLinkedGlobalCodelist();
        }
    },
    methods: {
        OnValueUpdate: function() {
            var self = this;
            this.$nextTick(function() {
                self.$emit('input', self.column);
            });
        },
        onValueLabelColumnInput: function(value) {
            var raw = value == null ? '' : String(value);
            var val = raw ? raw.toUpperCase() : '';
            if (!this.column.metadata) {
                Vue.set(this.column, 'metadata', {});
            }
            Vue.set(this.column.metadata, 'value_label_column', val);
            // Emit both so parent can sync and save; dedicated event ensures value_label_column is never missed
            this.$emit('value-label-column-change', val);
            var self = this;
            this.$nextTick(function() {
                self.$emit('input', self.column);
            });
        },
        clearCodeListReference: function() {
            Vue.set(this.column, 'code_list_reference', {
                id: '',
                name: '',
                version: '',
                uri: '',
                note: ''
            });
            this.OnValueUpdate();
        },
        /** Human-readable label for a registry codelist row. */
        globalCodelistLabel: function(cl) {
            if (!cl) {
                return '';
            }
            var title = cl.title && String(cl.title).trim();
            if (title) {
                return title;
            }
            var agency = String(cl.agency || '');
            var name = String(cl.name || '');
            return agency && name ? agency + ':' + name : (name || agency || String(cl.id || ''));
        },
        /** SDMX identity suffix for picker options, e.g. (WB:CL_FREQ). */
        globalCodelistIdentitySuffix: function(cl) {
            if (!cl) {
                return '';
            }
            var agency = String(cl.agency || '');
            var name = String(cl.name || '');
            return agency && name ? '(' + agency + ':' + name + ')' : '';
        },
        /** Resolve display name for column.global_codelist_id (registry PK). */
        resolveLinkedGlobalCodelist: function() {
            var vm = this;
            var seq = ++vm.globalResolveSeq;
            if (vm.column.codelist_type !== 'global') {
                vm.linkedGlobalDisplayName = '';
                vm.globalResolveLoading = false;
                return;
            }
            var pkRaw = vm.column.global_codelist_id;
            var pk = pkRaw != null && pkRaw !== '' ? parseInt(pkRaw, 10) : NaN;
            if (isNaN(pk) || pk <= 0) {
                vm.linkedGlobalDisplayName = '';
                vm.globalResolveLoading = false;
                return;
            }
            vm.globalResolveLoading = true;
            var row = (vm.globalCodelistsList || []).find(function(x) {
                return String(x.id) === String(pk);
            });
            if (row) {
                vm.linkedGlobalDisplayName = vm.globalCodelistLabel(row);
                if (seq === vm.globalResolveSeq) {
                    vm.globalResolveLoading = false;
                }
                return;
            }
            axios.get(CI.base_url + '/api/codelists/single/' + pk)
                .then(function(res) {
                    if (seq !== vm.globalResolveSeq) {
                        return;
                    }
                    var data = res.data || {};
                    var cl = data.codelist;
                    if (cl && cl.id != null) {
                        vm.linkedGlobalDisplayName = vm.globalCodelistLabel(cl);
                    } else {
                        vm.linkedGlobalDisplayName = '';
                    }
                })
                .catch(function() {
                    if (seq !== vm.globalResolveSeq) {
                        return;
                    }
                    vm.linkedGlobalDisplayName = '';
                })
                .then(function() {
                    if (seq === vm.globalResolveSeq) {
                        vm.globalResolveLoading = false;
                    }
                });
        },
        metaSelectValue: function(key) {
            var v = this.column.metadata && this.column.metadata[key];
            return v == null || v === '' ? '' : String(v);
        },
        setMetaInt: function(key, raw) {
            if (!this.column.metadata) {
                Vue.set(this.column, 'metadata', {});
            }
            var v = raw === '' || raw === null ? null : parseInt(raw, 10);
            if (v === null || isNaN(v)) {
                Vue.delete(this.column.metadata, key);
            } else {
                Vue.set(this.column.metadata, key, v);
            }
            this.OnValueUpdate();
        },
        setMetaString: function(key, raw) {
            if (!this.column.metadata) {
                Vue.set(this.column, 'metadata', {});
            }
            var v = raw === '' || raw === null ? '' : String(raw).trim();
            if (v === '') {
                Vue.delete(this.column.metadata, key);
            } else {
                Vue.set(this.column.metadata, key, v);
            }
            this.OnValueUpdate();
        },
        validateColumnName: function() {
            var n = this.column.name != null ? String(this.column.name) : '';
            if (n.length > 0 && n.charAt(0) === '_') {
                if (typeof EventBus !== 'undefined' && EventBus.$emit) {
                    EventBus.$emit('onFail', this.$t('dsd_name_reserved_underscore') || 'Column names cannot start with underscore (_); reserved for system fields.');
                }
            }
        },
        stripValueLabelColumnForDisallowedTypes: function() {
            var t = this.column.column_type;
            if (t !== 'time_period' && t !== 'observation_value') {
                return;
            }
            if (!this.column.metadata) {
                Vue.set(this.column, 'metadata', {});
            }
            if (this.column.metadata.hasOwnProperty('value_label_column')) {
                Vue.delete(this.column.metadata, 'value_label_column');
            }
        },
        onColumnTypeChange: function() {
            this.stripValueLabelColumnForDisallowedTypes();
            this.initSdmxAttributeFields();
            this.OnValueUpdate();
        },
        initSdmxAttributeFields: function() {
            if (!this.column.metadata) {
                Vue.set(this.column, 'metadata', {});
            }
        },
        onCodelistTypeChange: function(ev) {
            var val = ev && ev.target ? String(ev.target.value) : String(ev);
            Vue.set(this.column, 'codelist_type', val);
            if (val !== 'global') {
                Vue.set(this.column, 'global_codelist_id', null);
                this.linkedGlobalDisplayName = '';
            }
            this.OnValueUpdate();
        },
        globalDimensionCodelistSelectValue: function() {
            var g = this.column.global_codelist_id;
            var gNum = g != null && g !== '' ? parseInt(g, 10) : NaN;
            if (!isNaN(gNum) && gNum > 0) {
                return String(gNum);
            }
            return '';
        },
        onGlobalDimensionCodelistChange: function(raw) {
            if (!raw) {
                Vue.set(this.column, 'global_codelist_id', null);
                this.linkedGlobalDisplayName = '';
                this.OnValueUpdate();
                return;
            }
            var cl = (this.globalCodelistsList || []).find(function(x) { return String(x.id) === String(raw); });
            if (cl) {
                var idNum = parseInt(cl.id, 10);
                Vue.set(this.column, 'global_codelist_id', (!isNaN(idNum) && idNum > 0) ? idNum : null);
                this.linkedGlobalDisplayName = this.globalCodelistLabel(cl);
            }
            this.OnValueUpdate();
        }
    },
    computed: {
        /** First DSD row typed as periodicity (FREQ from file). */
        freqColumnRow: function() {
            var cols = this.allColumns || [];
            for (var i = 0; i < cols.length; i++) {
                var c = cols[i];
                if (c && c.column_type === 'periodicity' && c.name && String(c.name).trim() !== '') {
                    return c;
                }
            }
            return null;
        },
        projectHasFreqColumn: function() {
            return this.freqColumnRow != null;
        },
        freqColumnDisplayName: function() {
            return this.freqColumnRow ? String(this.freqColumnRow.name) : '';
        },
        freqCodeOptions: function() {
            var d = this.dictionaries && this.dictionaries.freq_codes;
            return Array.isArray(d) ? d : [];
        },
        /** True when global type and a registry codelist PK is set. */
        columnHasGlobalCodelistIdentity: function() {
            if (!this.column || this.column.codelist_type !== 'global') {
                return false;
            }
            var g = this.column.global_codelist_id;
            var gNum = g != null && g !== '' ? parseInt(g, 10) : NaN;
            return !isNaN(gNum) && gNum > 0;
        },
        /** Registry PK for code preview. */
        effectiveGlobalRegistryPk: function() {
            var g = this.column && this.column.global_codelist_id;
            var gNum = g != null && g !== '' ? parseInt(g, 10) : NaN;
            return !isNaN(gNum) && gNum > 0 ? gNum : null;
        },
        /** Whether the resolved registry id appears in the picker list (first page). */
        globalCodelistPickerIncludesLinked: function() {
            var id = this.effectiveGlobalRegistryPk;
            if (!id) {
                return true;
            }
            var list = this.globalCodelistsList || [];
            return list.some(function(cl) {
                return String(cl.id) === String(id);
            });
        },
        showValueLabelColumnField: function() {
            var t = this.column.column_type;
            return t !== 'time_period' && t !== 'observation_value';
        },
        /** DSD columns typed as attribute (for value label source); excludes the row being edited. */
        valueLabelAttributeOptions: function() {
            var cols = this.allColumns || [];
            var cur = this.column;
            var currentId = cur && cur.id != null ? cur.id : null;
            var currentName = cur && cur.name != null ? String(cur.name).trim() : '';
            var out = [];
            for (var i = 0; i < cols.length; i++) {
                var c = cols[i];
                if (!c || c.column_type !== 'attribute') {
                    continue;
                }
                var n = c.name != null ? String(c.name).trim() : '';
                if (!n) {
                    continue;
                }
                if (currentId != null && c.id != null && String(c.id) === String(currentId)) {
                    continue;
                }
                if (currentId == null && currentName && n === currentName) {
                    continue;
                }
                var lab = (c.label != null && String(c.label).trim() !== '') ? String(c.label).trim() : n;
                out.push({ name: n, label: lab });
            }
            out.sort(function(a, b) {
                return a.name.localeCompare(b.name);
            });
            return out;
        },
        valueLabelColumnSelectValue: function() {
            var v = this.column && this.column.metadata && this.column.metadata.value_label_column;
            // Normalize to uppercase so the value always matches DSD column names (which are uppercase).
            return v == null || v === '' ? '' : String(v).toUpperCase();
        },
        /** Saved value_label_column that does not match any attribute name (legacy or typo). */
        valueLabelOrphanName: function() {
            var v = this.valueLabelColumnSelectValue;
            if (!v) {
                return '';
            }
            var vUpper = v.toUpperCase();
            var opts = this.valueLabelAttributeOptions;
            for (var i = 0; i < opts.length; i++) {
                if (opts[i].name.toUpperCase() === vUpper) {
                    return '';
                }
            }
            return v;
        }
    },
    template: `
        <div class="indicator-dsd-edit-component" style="height:100vh" v-if="column">
            <v-alert v-if="readOnly" type="info" dense text class="mb-2">Read-only (bound data structure)</v-alert>
            <fieldset :disabled="readOnly" style="border:0;margin:0;padding:0;min-width:0;">
            <div style="font-size:small;" class="mb-2">

                <div class="p-2">
                    <!-- Name -->
                    <div class="form-group form-field">
                        <label>{{$t("name") || "Name"}} <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            class="form-control form-control-sm" 
                            v-model="column.name" 
                            @input="OnValueUpdate"
                            @blur="validateColumnName"
                            :pattern="'^[a-zA-Z0-9_]*$'"
                            maxlength="100"
                            required
                        />                        
                    </div>

                    <!-- Label -->
                    <div class="form-group form-field">
                        <label>{{$t("label") || "Label"}}</label>
                        <input 
                            type="text" 
                            class="form-control form-control-sm" 
                            v-model="column.label" 
                            @input="OnValueUpdate"
                        />
                    </div>

                    <!-- Description -->
                    <div class="form-group form-field">
                        <label>{{$t("description") || "Description"}}</label>
                        <textarea 
                            class="form-control form-control-sm" 
                            v-model="column.description" 
                            @input="OnValueUpdate"
                            rows="3"
                        ></textarea>
                    </div>

                    <!-- Data Type -->
                    <div class="form-group form-field">
                        <label>{{$t("data_type") || "Data Type"}} <span class="text-danger">*</span></label>
                        <select 
                            v-model="column.data_type" 
                            @change="OnValueUpdate"
                            class="form-control form-control-sm form-field-dropdown"
                            required
                        >
                            <option value="">-</option>
                            <option v-for="(label, value) in data_types" :key="value" :value="value">
                                {{label}}
                            </option>
                        </select>
                    </div>

                    <!-- Column Type -->
                    <div class="form-group form-field">
                        <label>{{$t("column_type") || "Column Type"}} <span class="text-danger">*</span></label>
                        <select 
                            v-model="column.column_type" 
                            @change="onColumnTypeChange"
                            class="form-control form-control-sm form-field-dropdown"
                            required
                        >
                            <option value="">-</option>
                            <option v-for="(label, value) in column_types" :key="value" :value="value">
                                {{label}}
                            </option>
                        </select>
                    </div>

                    <!-- Periodicity (FREQ column): inline help after column type -->
                    <template v-if="column.column_type === 'periodicity'">
                        <p class="text-muted small mb-2">
                            {{$t('dsd_freq_column_intro') || 'This field type marks the CSV column that contains FREQ codes (e.g. A, M, Q) per row. Pair it with a Time period column: you do not set a time period format on the time row when this column exists.'}}
                        </p>
                        <details class="mb-3" v-if="freqCodeOptions.length">
                            <summary class="small" style="cursor:pointer;">{{$t('dsd_freq_code_reference') || 'FREQ codes (reference from config)'}}</summary>
                            <ul class="small text-muted pl-3 mb-0 mt-1" style="max-height: 12rem; overflow-y: auto;">
                                <li v-for="f in freqCodeOptions" :key="'ref-' + f.code"><code>{{ f.code }}</code> — {{ f.label }}</li>
                            </ul>
                            <small class="form-text text-muted d-block mt-1">{{$t('dsd_freq_codes_hint') || 'Map CSV values to these FREQ codes.'}}</small>
                        </details>
                    </template>

                    <!-- Time period (SDMX TIME_PERIOD): paired with FREQ column or set at import -->
                    <template v-else-if="column.column_type === 'time_period'">
                        <div v-if="projectHasFreqColumn" class="alert alert-info py-2 small mb-3" role="alert">
                            <strong>{{$t('dsd_time_mode_freq_from_data') || 'FREQ from data'}}</strong>
                            — {{$t('dsd_time_mode_freq_from_data_body') || 'A FREQ column is defined in this DSD:'}}
                            <code class="mx-1">{{ freqColumnDisplayName }}</code>.
                            {{$t('dsd_time_mode_freq_from_data_tail') || 'Frequency comes from that column; TIME_PERIOD values are validated for each FREQ.'}}
                        </div>
                        <div v-else class="alert alert-info py-2 small mb-3" role="alert">
                            {{$t('dsd_time_period_import_freq_help') || 'No FREQ column in this structure. Set the series FREQ on the Import data screen before publishing CSV data.'}}
                        </div>                        </template>
                    </template>

                    <!-- Value label column: attribute DSD field whose values provide labels -->
                    <div class="form-group form-field" v-if="showValueLabelColumnField">
                        <label>{{$t("value_label_column") || "Value label column"}}</label>
                        <select
                            class="form-control form-control-sm form-field-dropdown"
                            :value="valueLabelColumnSelectValue"
                            @change="onValueLabelColumnInput($event.target.value)"
                        >
                            <option value="">{{ $t('select_none') || '— None —' }}</option>
                            <option
                                v-for="opt in valueLabelAttributeOptions"
                                :key="'vlab-' + opt.name"
                                :value="opt.name"
                            >
                                {{ opt.label }} ({{ opt.name }})
                            </option>
                            <option
                                v-if="valueLabelOrphanName"
                                :value="valueLabelOrphanName"
                            >
                                {{ valueLabelOrphanName }} — {{ $t('value_label_column_not_in_dsd') || 'not listed as attribute' }}
                            </option>
                        </select>
                        <small class="form-text text-muted" v-if="valueLabelAttributeOptions.length === 0 && !valueLabelOrphanName">
                            {{ $t('value_label_column_no_attributes') || 'No Attribute columns in this structure. Clear this field to use none.' }}
                        </small>
                    </div>

                    <!-- Attachment level + value presence (attribute / annotation only) -->
                    <template v-if="column.column_type === 'attribute' || column.column_type === 'annotation'">
                        <div class="form-group form-field">
                            <label>{{$t('dsd_attachment_level') || 'Applies at'}}</label>
                            <select
                                class="form-control form-control-sm form-field-dropdown"
                                :value="metaSelectValue('attachment_level')"
                                @change="setMetaString('attachment_level', $event.target.value)"
                            >
                                <option value="">— not set —</option>
                                <option value="Observation">{{$t('dsd_attachment_observation') || 'Observation'}}</option>
                                <option value="Series">{{$t('dsd_attachment_series') || 'Series'}}</option>
                                <option value="DataSet">{{$t('dsd_attachment_dataset') || 'DataSet'}}</option>
                            </select>
                            <small class="form-text text-muted">{{$t('dsd_attachment_level_hint') || 'Observation = once per data row; Series = once per series; DataSet = once for the whole file. Defaults to Observation if not set.'}}</small>
                        </div>
                        <div class="form-group form-field">
                            <label>{{$t('dsd_assignment_status') || 'Value presence'}}</label>
                            <select
                                class="form-control form-control-sm form-field-dropdown"
                                :value="metaSelectValue('assignment_status')"
                                @change="setMetaString('assignment_status', $event.target.value)"
                            >
                                <option value="">— not set —</option>
                                <option value="Conditional">{{$t('dsd_assignment_conditional') || 'Conditional'}}</option>
                                <option value="Mandatory">{{$t('dsd_assignment_mandatory') || 'Mandatory'}}</option>
                            </select>
                            <small class="form-text text-muted">{{$t('dsd_assignment_status_hint') || 'Mandatory = a value must always be present in exported data; Conditional = value can be absent. Defaults to Conditional if not set.'}}</small>
                        </div>
                    </template>

                    <!-- Codelist type: none / global registry -->
                    <div class="form-group form-field">
                        <label>{{$t('dsd_vocabulary') || 'Codelist type'}}</label>
                        <select
                            class="form-control form-control-sm form-field-dropdown"
                            :value="column.codelist_type || 'none'"
                            @change="onCodelistTypeChange($event)"
                        >
                            <option value="none">{{$t('dsd_vocab_none') || 'None'}}</option>
                            <option value="global">{{$t('dsd_vocab_global') || 'Standard codelist'}}</option>
                        </select>
                    </div>
                    <div class="form-group form-field" v-if="column.codelist_type === 'global'">
                        <label>{{$t('dsd_global_codelist_pick') || 'Standard codelist'}}</label>
                        <select
                            class="form-control form-control-sm form-field-dropdown"
                            :disabled="globalCodelistsLoading || globalResolveLoading"
                            :value="globalDimensionCodelistSelectValue()"
                            @change="onGlobalDimensionCodelistChange($event.target.value)"
                        >
                            <option value="">{{ $t('select_none') || '— None —' }}</option>
                            <option
                                v-if="effectiveGlobalRegistryPk && !globalCodelistPickerIncludesLinked"
                                :key="'gvocab-linked-' + effectiveGlobalRegistryPk"
                                :value="String(effectiveGlobalRegistryPk)"
                            >
                                {{ linkedGlobalDisplayName || ('#' + effectiveGlobalRegistryPk) }}
                            </option>
                            <option v-for="cl in globalCodelistsList" :key="'gvocab-' + cl.id" :value="String(cl.id)">
                                {{ globalCodelistLabel(cl) }} <template v-if="globalCodelistIdentitySuffix(cl)"> {{ globalCodelistIdentitySuffix(cl) }}</template>
                            </option>
                        </select>
                        <div v-if="globalResolveLoading" class="small text-muted mt-1">{{ $t('loading') || 'Loading…' }}</div>
                        <indicator-dsd-global-codelist-preview
                            v-if="columnHasGlobalCodelistIdentity"
                            :registry-codelist-id="effectiveGlobalRegistryPk"
                            :codelist-name="linkedGlobalDisplayName"
                        ></indicator-dsd-global-codelist-preview>
                    </div>

                </div>
            </div>
            </fieldset>
        </div>
    `
})
