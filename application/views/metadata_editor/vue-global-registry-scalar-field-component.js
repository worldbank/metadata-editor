Vue.component('global-registry-scalar-field', {
    props: {
        value: {
            default: null
        },
        field: {
            type: Object,
            required: true
        },
        disabled: {
            type: Boolean,
            default: false
        },
        projectId: {
            default: null
        },
        allowCustom: {
            type: Boolean,
            default: false
        }
    },
    data: function () {
        return {
            pickerDialogOpen: false
        };
    },
    computed: {
        registryPickerColumns: function () {
            return [
                { key: 'code', title: 'Code', type: 'text' },
                { key: 'label', title: 'Label', type: 'text' }
            ];
        },
        displayText: function () {
            if (this.value === null || this.value === undefined || this.value === '') {
                return '';
            }
            return String(this.value);
        },
        pickCodelistTitle: function () {
            return this.$t('pick_from_codelist') || 'Pick from codelist';
        },
        clearTitle: function () {
            return this.$t('clear') || 'Clear';
        },
        fieldClickOpensPicker: function () {
            if (this.disabled || this.allowCustom) {
                return false;
            }
            return true;
        },
        customFieldPlaceholder: function () {
            return this.$t('global_codelist_custom_placeholder') || '';
        },
        customFieldHint: function () {
            return this.$t('global_codelist_custom_hint') || '';
        }
    },
    methods: {
        openPicker: function () {
            if (this.disabled) {
                return;
            }
            this.pickerDialogOpen = true;
        },
        onFieldClick: function () {
            if (!this.fieldClickOpensPicker) {
                return;
            }
            this.openPicker();
        },
        clearValue: function () {
            if (this.disabled) {
                return;
            }
            this.$emit('input', '');
        },
        onPickerSelection: function (items) {
            if (!Array.isArray(items) || items.length === 0) {
                return;
            }
            var picked = items[0];
            var enumItem = {
                code: picked.code != null ? String(picked.code).trim() : '',
                label: picked.label != null ? String(picked.label).trim() : ''
            };
            if (!enumItem.label && enumItem.code) {
                enumItem.label = enumItem.code;
            }
            if (typeof formatScalarEnumStoredValue === 'function') {
                this.$emit('input', formatScalarEnumStoredValue(this.field, enumItem, picked));
            } else if (enumItem.code) {
                this.$emit('input', enumItem.code);
            }
        },
        onTextInput: function (val) {
            this.$emit('input', val != null ? val : '');
        }
    },
    template: `
        <div class="global-registry-scalar-field">
            <template v-if="allowCustom">
                <v-text-field
                    :value="displayText"
                    @input="onTextInput"
                    :disabled="disabled"
                    dense
                    outlined
                    hide-details
                    class="global-registry-scalar-field-input"
                    background-color="#FFFFFF"
                    :placeholder="customFieldPlaceholder"
                >
                    <template v-slot:append>
                        <span class="d-inline-flex align-center">
                            <v-btn
                                icon
                                x-small
                                :disabled="disabled"
                                :aria-label="pickCodelistTitle"
                                :title="pickCodelistTitle"
                                @click.stop="openPicker"
                            >
                                <v-icon small>mdi-form-dropdown</v-icon>
                            </v-btn>
                            <field-issues
                                v-if="projectId && field.key"
                                :field-path="field.key"
                                :project-id="projectId"
                            ></field-issues>
                        </span>
                    </template>
                </v-text-field>
                <div v-if="customFieldHint" class="text-muted font-small mt-1 global-registry-scalar-field-hint">
                    {{ customFieldHint }}
                </div>
            </template>
            <v-text-field
                v-else
                :value="displayText"
                readonly
                dense
                outlined
                hide-details
                :class="['global-registry-scalar-field-input', 'global-registry-scalar-field-input--picker']"
                background-color="#FFFFFF"
                :disabled="disabled"
                :clearable="!disabled && !!displayText"
                :placeholder="pickCodelistTitle"
                @click="onFieldClick"
                @click:clear="clearValue"
            >
                <template v-slot:append>
                    <span class="d-inline-flex align-center" @click.stop>
                        <v-btn
                            icon
                            x-small
                            :disabled="disabled"
                            :aria-label="pickCodelistTitle"
                            :title="pickCodelistTitle"
                            @click.stop="openPicker"
                        >
                            <v-icon small>mdi-form-dropdown</v-icon>
                        </v-btn>
                        <field-issues
                            v-if="projectId && field.key"
                            :field-path="field.key"
                            :project-id="projectId"
                        ></field-issues>
                    </span>
                </template>
            </v-text-field>
            <vue-dialog-enum-selection-component
                v-model="pickerDialogOpen"
                :field="field"
                :columns="registryPickerColumns"
                :enums="[]"
                @selection="onPickerSelection"
            ></vue-dialog-enum-selection-component>
        </div>
    `
});
