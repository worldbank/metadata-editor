Vue.component('template-controlled-vocabulary', {
    props: {
        fieldNode: {
            type: Object,
            required: true
        },
        schemaField: {
            type: Object,
            default: null
        },
        dataType: {
            type: String,
            default: ''
        },
        disabled: {
            type: Boolean,
            default: false
        }
    },
    data: function () {
        return {
            enum_store_options: [
                { value: 'both', label: 'Label with code' },
                { value: 'code', label: 'Code' },
                { value: 'label', label: 'Label' }
            ],
            switchToGlobalConfirmDialog: false,
            vocabularyTypeSelectKey: 0
        };
    },
    computed: {
        globalRegistryOptionDisabled: function () {
            return this.schemaHasFixedEnum || !this.fieldPathLabel;
        },
        schemaHasFixedEnum: function () {
            var sf = this.schemaField;
            return !!(sf && sf.enum && Array.isArray(sf.enum) && sf.enum.length > 0);
        },
        vocabularySource: function () {
            var src = this.fieldNode && this.fieldNode.vocabulary_source
                ? String(this.fieldNode.vocabulary_source).toLowerCase()
                : '';
            if (src === 'global') {
                return 'global';
            }
            return 'inline';
        },
        vocabularyTypeSelectItems: function () {
            var globalDisabled = this.globalRegistryOptionDisabled && this.vocabularySource !== 'global';
            return [
                { value: 'inline', text: this.$t('vocabulary_type_custom') },
                { value: 'global', text: this.$t('vocabulary_type_registry'), disabled: globalDisabled }
            ];
        },
        globalCodelistId: {
            get: function () {
                var id = this.fieldNode && this.fieldNode.global_codelist_id;
                if (id === null || id === undefined || id === '') {
                    return null;
                }
                var n = parseInt(id, 10);
                return (!isNaN(n) && n > 0) ? n : null;
            },
            set: function (val) {
                var n = val != null && val !== '' ? parseInt(val, 10) : NaN;
                if (!isNaN(n) && n > 0) {
                    Vue.set(this.fieldNode, 'global_codelist_id', n);
                } else {
                    Vue.set(this.fieldNode, 'global_codelist_id', null);
                }
                this.notifyChange();
            }
        },
        enumStoreColumn: {
            get: function () {
                if (this.fieldNode && this.fieldNode.enum_store_column) {
                    return this.fieldNode.enum_store_column;
                }
                return 'both';
            },
            set: function (val) {
                Vue.set(this.fieldNode, 'enum_store_column', val);
                this.notifyChange();
            }
        },
        inlineEnum: {
            get: function () {
                if (!this.fieldNode || !this.fieldNode.enum) {
                    return [];
                }
                if (this.fieldNode.enum.length > 0 && typeof this.fieldNode.enum[0] === 'string') {
                    var enum_list = [];
                    this.fieldNode.enum.forEach(function (item) {
                        enum_list.push({ code: item, label: item });
                    });
                    Vue.set(this.fieldNode, 'enum', enum_list);
                    return enum_list;
                }
                return this.fieldNode.enum;
            },
            set: function (newValue) {
                Vue.set(this.fieldNode, 'enum', newValue);
                this.notifyChange();
            }
        },
        simpleControlledVocabColumns: function () {
            return [
                { type: 'text', key: 'code', title: 'Code' },
                { type: 'text', key: 'label', title: 'Label' }
            ];
        },
        schemaEnumAllowedLabel: function () {
            if (!this.schemaField || !this.schemaField.enum) {
                return '';
            }
            return this.schemaField.enum.join(', ');
        },
        propEnumSchemaWarnings: function () {
            if (!this.schemaHasFixedEnum || !this.fieldNode || !this.fieldNode.enum || !Array.isArray(this.fieldNode.enum)) {
                return [];
            }
            if (this.enumStoreColumn === 'label') {
                return [];
            }
            var allowed = {};
            this.schemaField.enum.forEach(function (v) {
                allowed[String(v)] = true;
            });
            var warnings = [];
            this.fieldNode.enum.forEach(function (row) {
                if (!row || row.code === undefined || row.code === null || row.code === '') {
                    return;
                }
                var code = String(row.code);
                if (!allowed[code]) {
                    warnings.push(code);
                }
            });
            return warnings;
        },
        fieldPathLabel: function () {
            if (!this.fieldNode) {
                return '';
            }
            return this.fieldNode.prop_key || this.fieldNode.key || '';
        },
        isObjectArrayField: function () {
            return typeof isFlatObjectArrayField === 'function' && isFlatObjectArrayField(this.fieldNode);
        },
        objectArrayInlineColumns: function () {
            if (!this.isObjectArrayField || !this.fieldNode || !Array.isArray(this.fieldNode.props)) {
                return [];
            }
            return this.fieldNode.props;
        },
        arrayPropSelectItems: function () {
            var items = [];
            if (!this.isObjectArrayField || !this.fieldNode || !Array.isArray(this.fieldNode.props)) {
                return items;
            }
            this.fieldNode.props.forEach(function (p) {
                if (!p || !p.key) {
                    return;
                }
                items.push({
                    value: p.key,
                    text: p.title ? String(p.title) : String(p.key)
                });
            });
            return items;
        },
        globalCodelistMapCode: {
            get: function () {
                if (!this.fieldNode || !this.fieldNode.global_codelist_map_code) {
                    return '';
                }
                return String(this.fieldNode.global_codelist_map_code);
            },
            set: function (val) {
                Vue.set(this.fieldNode, 'global_codelist_map_code', val ? String(val) : '');
                this.notifyChange();
            }
        },
        globalCodelistMapLabel: {
            get: function () {
                if (!this.fieldNode || !this.fieldNode.global_codelist_map_label) {
                    return '';
                }
                return String(this.fieldNode.global_codelist_map_label);
            },
            set: function (val) {
                Vue.set(this.fieldNode, 'global_codelist_map_label', val ? String(val) : '');
                this.notifyChange();
            }
        }
    },
    mounted: function () {
        if (this.vocabularySource === 'global' && this.isObjectArrayField && typeof ensureGlobalCodelistPropMapOnField === 'function') {
            ensureGlobalCodelistPropMapOnField(this.fieldNode);
        }
    },
    methods: {
        notifyChange: function () {
            this.$emit('change');
        },
        onGlobalCodelistInput: function (id) {
            this.globalCodelistId = id;
        },
        hasInlineEnumContent: function () {
            if (typeof fieldHasInlineEnumContent === 'function') {
                return fieldHasInlineEnumContent(this.fieldNode);
            }
            return false;
        },
        onVocabularyTypeChange: function (val) {
            val = val === 'global' ? 'global' : 'inline';
            if (val === this.vocabularySource) {
                return;
            }
            if (val === 'inline') {
                this.setVocabularySource('inline');
                return;
            }
            if (this.globalRegistryOptionDisabled) {
                this.vocabularyTypeSelectKey += 1;
                return;
            }
            if (this.hasInlineEnumContent()) {
                this.switchToGlobalConfirmDialog = true;
                return;
            }
            this.setVocabularySource('global');
        },
        confirmSwitchToGlobal: function () {
            this.switchToGlobalConfirmDialog = false;
            this.setVocabularySource('global');
        },
        cancelSwitchToGlobal: function () {
            this.switchToGlobalConfirmDialog = false;
            this.vocabularyTypeSelectKey += 1;
        },
        setVocabularySource: function (val) {
            val = val === 'global' ? 'global' : 'inline';
            if (val === 'global' && (this.schemaHasFixedEnum || !this.fieldPathLabel)) {
                return;
            }
            if (val === 'global') {
                Vue.set(this.fieldNode, 'vocabulary_source', 'global');
                Vue.set(this.fieldNode, 'enum', []);
                if (this.fieldNode.global_codelist_id === undefined) {
                    Vue.set(this.fieldNode, 'global_codelist_id', null);
                }
                if (this.isObjectArrayField && typeof ensureGlobalCodelistPropMapOnField === 'function') {
                    ensureGlobalCodelistPropMapOnField(this.fieldNode);
                }
            } else {
                Vue.delete(this.fieldNode, 'vocabulary_source');
                Vue.delete(this.fieldNode, 'global_codelist_id');
                if (typeof clearGlobalCodelistPropMapOnField === 'function') {
                    clearGlobalCodelistPropMapOnField(this.fieldNode);
                }
                if (!this.fieldNode.enum) {
                    Vue.set(this.fieldNode, 'enum', []);
                }
            }
            this.notifyChange();
        },
        onInlineEnumUpdate: function (e) {
            if (Array.isArray(e)) {
                Vue.set(this.fieldNode, 'enum', e);
            }
            if (!this.fieldNode.enum) {
                Vue.set(this.fieldNode, 'enum', []);
            }
            this.notifyChange();
        }
    },
    template: `
    <div class="template-controlled-vocabulary font-small">
        <div class="mb-3 template-cv-top-row">
            <v-row align="start" dense>
                <v-col cols="12" md="6" class="template-cv-type-col">
                    <div class="template-cv-field-label mb-1">
                        {{ $t('vocabulary_type') }}
                    </div>
                    <v-select
                        :key="'vocab-type-' + vocabularyTypeSelectKey"
                        :value="vocabularySource"
                        @input="onVocabularyTypeChange"
                        :items="vocabularyTypeSelectItems"
                        item-text="text"
                        item-value="value"
                        dense
                        outlined
                        hide-details
                        class="template-cv-type-select"
                        :disabled="disabled"
                    ></v-select>
                    <div class="text-muted template-cv-source-state-hint mt-1">
                        <template v-if="vocabularySource === 'global'">
                            {{ $t('vocabulary_source_mode_registry') }}
                        </template>
                        <template v-else>
                            {{ $t('vocabulary_source_mode_custom') }}
                        </template>
                    </div>
                    <div v-if="globalRegistryOptionDisabled && vocabularySource !== 'global'" class="text-muted template-cv-source-hint mt-1">
                        <template v-if="schemaHasFixedEnum">
                            {{ $t('vocabulary_source_schema_enum_only') }}
                        </template>
                        <template v-else-if="!fieldPathLabel">
                            {{ $t('vocabulary_source_path_required') }}
                        </template>
                    </div>
                </v-col>
                <v-col v-if="!isObjectArrayField" cols="12" md="6" class="template-cv-store-col">
                    <div class="template-cv-field-label mb-1">
                        {{ $t('enum_store_options_label') }}
                    </div>
                    <v-select
                        v-model="enumStoreColumn"
                        :items="enum_store_options"
                        :item-text="item => item.label"
                        :item-value="item => item.value"
                        dense
                        outlined
                        clearable
                        hide-details
                        class="template-cv-store-select"
                        :disabled="disabled"
                    ></v-select>
                </v-col>
            </v-row>
            <div v-if="isObjectArrayField && vocabularySource === 'inline'" class="text-muted template-cv-source-hint mt-1">
                {{ $t('global_codelist_array_store_hint') }}
            </div>
        </div>

        <template v-if="vocabularySource === 'global'">
            <global-codelist-link-field
                class="mb-3"
                :value="globalCodelistId"
                :subtitle="fieldPathLabel"
                :show-path-warning="!fieldPathLabel"
                :disabled="disabled"
                @input="onGlobalCodelistInput"
                @change="notifyChange"
            ></global-codelist-link-field>
            <div v-if="isObjectArrayField && globalCodelistId" class="mb-3">
                <div class="template-cv-field-label mb-2">
                    {{ $t('global_codelist_column_mappings') }}
                </div>
                <v-row align="start" dense>
                    <v-col cols="12" md="6">
                        <div class="template-cv-field-label mb-1">
                            {{ $t('global_codelist_map_registry_code') }}
                        </div>
                        <v-select
                            v-model="globalCodelistMapCode"
                            :items="arrayPropSelectItems"
                            item-text="text"
                            item-value="value"
                            dense
                            outlined
                            hide-details
                            :disabled="disabled"
                        ></v-select>
                    </v-col>
                    <v-col cols="12" md="6">
                        <div class="template-cv-field-label mb-1">
                            {{ $t('global_codelist_map_registry_label') }}
                        </div>
                        <v-select
                            v-model="globalCodelistMapLabel"
                            :items="arrayPropSelectItems"
                            item-text="text"
                            item-value="value"
                            dense
                            outlined
                            hide-details
                            :disabled="disabled"
                        ></v-select>
                    </v-col>
                </v-row>
                <div class="text-muted font-small mt-1">
                    {{ $t('global_codelist_array_map_hint') }}
                </div>
            </div>
        </template>

        <template v-else>
            <table-grid-component
                v-if="fieldNode && fieldNode.key"
                :key="fieldNode.key + '-enum'"
                :columns="isObjectArrayField ? objectArrayInlineColumns : simpleControlledVocabColumns"
                v-model="inlineEnum"
                @update:value="onInlineEnumUpdate"
                class="border pb-2 template-cv-inline-grid"
            ></table-grid-component>
            <div class="mx-0 mb-0 mt-2 template-cv-schema-hints">
                <div class="text-muted font-small" v-if="schemaHasFixedEnum">
                    <strong>{{ $t('schema_enum_hint_title') || 'Schema enum' }}:</strong>
                    <span class="ml-1">{{ schemaEnumAllowedLabel }}</span>
                </div>
                <div v-if="propEnumSchemaWarnings.length" class="mt-2">
                    <div v-for="badCode in propEnumSchemaWarnings" :key="'enum-warn-' + badCode" class="text-danger font-small">
                        {{ $t('enum_code_not_in_schema', { code: badCode, allowed: schemaEnumAllowedLabel }) }}
                    </div>
                </div>
            </div>
        </template>

        <v-dialog v-model="switchToGlobalConfirmDialog" max-width="480" persistent content-class="template-cv-switch-global-dialog">
            <v-card>
                <v-card-title class="text-subtitle-1 py-3">
                    {{ $t('vocabulary_switch_to_global_title') || 'Switch to registry codelist?' }}
                </v-card-title>
                <v-card-text class="text-body-2">
                    {{ $t('vocabulary_switch_to_global_message') || 'The custom code list for this field will be removed from the template. Codes will come from the registry codelist you link. Continue?' }}
                </v-card-text>
                <v-card-actions class="pa-3">
                    <v-spacer></v-spacer>
                    <v-btn text small class="template-cv-switch-global-cancel-btn" @click="cancelSwitchToGlobal">
                        {{ $t('cancel') || 'Cancel' }}
                    </v-btn>
                    <v-btn
                        small
                        color="primary"
                        depressed
                        class="template-cv-switch-global-confirm-btn white--text"
                        @click="confirmSwitchToGlobal"
                    >
                        {{ $t('continue') || 'Continue' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
    `
});
