//form input component
Vue.component("form-input", {
  props: ["value", "field", "title"],
  data: function () {
    return {};
  },
  mounted: function () {},
  computed: {
    isFieldReadOnly() {
      if (!this.$store.getters.getUserHasEditAccess) {
        return true;
      }

      return this.field.is_readonly;
    },
    local: {
      get: function () {
        return this.value;
      },
      set: function (newValue) {
        this.$emit("input", newValue);
      },
    },
    fieldEnumByCodeMultiple: {
      get: function () {
        // If value is not an array, return empty array to avoid errors
        if (!Array.isArray(this.local)) {
          return [];
        }
        
        //loop this.local and find the code in the enum
        let list = [];
        _.forEach(this.local, (code) => {
          // Only process string values
          if (typeof code === 'string') {
          let enumCode = this.findEnumByCode(this.getEnumCodeFromLabel(code));
          if (enumCode) {
            list.push(enumCode);
          } else {
              list.push(code);
            }
          } else {
            // For non-string values, add as-is
            list.push(code);
          }
        });
        return list;
      },
      set: function (value) {
        let list = [];
        _.forEach(value, (code) => {
          list.push(this.getStoredEnumValue(code));
        });
        this.local = list;
      },
    },
    fieldEnumByCode: {
      get: function () {
        // If value is not a string (array/object), return as-is to avoid errors
        if (typeof this.local !== 'string' && this.local !== null && this.local !== undefined) {
          return this.local;
        }
        
        const enumItem = this.field.enum.find(
          (code) => code.code === this.getEnumCodeFromLabel(this.local)
        );

        // Return the enum object so v-combobox can display the label properly
        // The validation rule will accept enum objects for dropdown-custom fields
        return enumItem || this.local;
      },
      set: function (value) {

        let enum_store_column = this.field.enum_store_column;

        if (!enum_store_column) {
          enum_store_column = "both";
        }

        //if enum_store_column is both, store the code and label
        if (enum_store_column == "both") {
          this.local = this.getStoredEnumValue(value, "both");
        }
        else if (enum_store_column == "code") {
          this.local = this.getStoredEnumValue(value, "code");
        }
        else if (enum_store_column == "label") {
          this.local = this.getStoredEnumValue(value, "label");
        }
        
      },
    },
    formTextFieldStyle() {
      return this.$store.state.formTextFieldStyle;
    },
    ProjectType() {
      return this.$store.state.project_type;
    },
    projectId() {
      return this.$store.state.project_id || null;
    },
    isRequired() {
      return !!(this.field && (this.field.is_required || this.field.required));
    },
  },
  template: `
            <div class="form-input-field mt-3" :class="'form-input-' + field.type"  >

                <div v-if="field.type=='nested_array'">
                    <div class="form-field form-field-table">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + field.key">{{field.title}}</label>
                            <field-issues v-if="projectId && field.key" :field-path="field.key" :project-id="projectId"></field-issues>
                        </div>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
                        
                        <nested-array
                            :key="field.key" 
                            v-model="local"
                            :columns="field.props"
                            :title="field.title"
                            :path="field.key"
                            :field="field"
                            >
                        </nested-array>

                    </div>
                </div>
                <div v-else-if="field.type=='array'">                
                    <div class="form-field form-field-table">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + field.key">{{field.title}}</label>
                            <span v-if="field.help_text" class="small ml-1" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" aria-label="Help"><i class="far fa-question-circle"></i></span>
                            <field-issues v-if="projectId && field.key" :field-path="field.key" :project-id="projectId"></field-issues>
                        </div>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
                        <table-grid-component 
                            v-model="local" 
                            :columns="field.props"
                            :enums="field.enum" 
                            :field="field"
                            class="border elevation-1"
                            >
                        </table-grid-component>
                    </div>
                </div>
                <div v-else-if="field.type=='simple_array'" >
                    <div class="d-flex align-center flex-nowrap">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>
                        <field-issues v-if="projectId && field.key" :field-path="field.key" :project-id="projectId"></field-issues>
                    </div>
                    <div v-if="fieldDisplayType(field)=='text' ||fieldDisplayType(field)=='textarea' " >
                        <repeated-field
                                v-model=" local"
                                :field="field"                            
                            >
                        </repeated-field>
                    </div>
                    <div v-else-if="fieldDisplayType(field)=='dropdown' || fieldDisplayType(field)=='dropdown-custom'">
                        <v-combobox
                            v-model="fieldEnumByCodeMultiple"
                            :items="field.enum"
                            item-text="label"
                            item-value="code"
                            :return-object="false"
                            label=""
                            :multiple="field.type=='simple_array'"
                            small-chips
                            v-bind="formTextFieldStyle"
                            background-color="#FFFFFF"
                            :disabled="isFieldReadOnly"
                        >
                            <template v-slot:append v-if="projectId && field.key">
                                <field-issues :field-path="field.key" :project-id="projectId"></field-issues>
                            </template>
                        </v-combobox>
                        
                    </div>
                                    
                </div>

                <div  v-else-if="fieldDisplayType(field)=='text'">                            
                    <div class="form-field" :class="['field-' + field.key] ">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                                <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                                <span v-if="isRequired" class="required-label"> * </span>
                            </label>
                        </div>

                        <validation-provider 
                            :rules="getValidationRules(field)" 
                            :debounce=500
                            immediate
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >            

                            <v-text-field
                                v-model="local"
                                :disabled="isFieldReadOnly"
                                v-bind="formTextFieldStyle"
                            >
                                <template v-slot:append v-if="projectId && field.key">
                                    <field-issues :field-path="field.key" :project-id="projectId"></field-issues>
                                </template>
                            </v-text-field>
                                                                                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted">{{field.help_text}}</small>

                            <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>

                    </div>                                
                </div>

                <div v-else-if="fieldDisplayType(field)=='number'">
                    <div class="form-field" :class="['field-' + field.key] ">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                                <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                                <span v-if="isRequired" class="required-label"> * </span>
                            </label>
                        </div>

                        <validation-provider
                            :rules="getValidationRules(field)" 
                            :debounce=500
                            immediate
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >            

                            <v-text-field
                                v-model.number="local"
                                type="number"
                                :disabled="isFieldReadOnly"
                                v-bind="formTextFieldStyle"
                            >
<template v-slot:append v-if="projectId && field.key">
                                    <field-issues :field-path="field.key" :project-id="projectId"></field-issues>
                                </template>
                            </v-text-field>
                                                                                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted">{{field.help_text}}</small>

                            <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>

                    </div>                                
                </div>

                <div v-else-if="fieldDisplayType(field)=='textarea'">
                    <div class="form-field-textarea"">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                            <span v-if="isRequired" class="required-label"> * </span>
                        </label>                
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>

                        <validation-provider 
                            :rules="getValidationRules(field)" 
                            :debounce=500
                            immediate
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >   

                            <v-textarea-latex 
                                v-if="field.content_format=='latex'" 
                                v-model="local" 
                                :field="field"
                              ></v-textarea-latex>
                            
                            <v-textarea
                                v-else
                                variant="outlined"
                                :disabled="isFieldReadOnly"
                                v-model="local"
                                v-bind="formTextFieldStyle"
                                class="v-textarea-field"
                                auto-grow
                                clearable
                                rows="2"
                                row-height="40"
                                max-height="200"
                                max-rows="5"                            
                                density="compact"
                            >
                                <template v-slot:append v-if="projectId && field.key">
                                    <field-issues :field-path="field.key" :project-id="projectId"></field-issues>
                                </template>
                            </v-textarea>
                            <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>

                        <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>
                    </div>
                </div> 

                <div v-else-if="fieldDisplayType(field)=='dropdown-custom'">
                    <div class="form-field-dropdown-custom">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                                <span v-if="isRequired" class="required-label"> * </span>
                            </label>
                        </div>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
                        
                        <validation-provider 
                            :rules="getValidationRules(field)" 
                            :debounce=500
                            immediate
                            v-slot="{ errors }"
                            :name="field.title"
                            >
                            <input type="hidden" v-model="local" />
                            <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>
                        
                        <v-combobox
                            v-model="fieldEnumByCode"
                            :items="field.enum"
                            item-text="label"
                            item-value="code"
                            :return-object="false"
                            label=""
                            :multiple="field.type=='simple_array'"
                            v-bind="formTextFieldStyle"
                            background-color="#FFFFFF"                    
                            :disabled="isFieldReadOnly"
                        >
                            <template v-slot:append v-if="projectId && field.key">
                                <field-issues :field-path="field.key" :project-id="projectId"></field-issues>
                            </template>
                        </v-combobox>                        
                        <small class="text-muted">{{field.enum_store_column}} - {{local}}</small>                        
                    </div>
                </div>
                
                <div v-else-if="fieldDisplayType(field)=='dropdown'">
                    <div class="form-field-dropdown">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                                <span v-if="isRequired" class="required-label"> * </span>
                            </label>
                        </div>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>                        
                        
                        <validation-provider 
                            :rules="getValidationRules(field)" 
                            :debounce=500
                            immediate
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >
                            <input type="hidden" v-model="local" />
                            <v-select
                                v-model="fieldEnumByCode"
                                :items="field.enum"  
                                item-text="label"
                                item-value="code"                            
                                label=""
                                outlined
                                dense
                                clearable
                                background-color="#FFFFFF"  
                                :disabled="isFieldReadOnly"
                            >
                                <template v-slot:append v-if="projectId && field.key">
                                    <field-issues :field-path="field.key" :project-id="projectId"></field-issues>
                                </template>
                            </v-select>
                            <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>
                        
                        <small class="text-muted">{{local}}</small>
                    </div>
                </div>

                <div v-else-if="fieldDisplayType(field)=='date'">
                    <div class="form-field-date">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                                <span v-if="isRequired" class="required-label"> * </span>
                            </label>
                            <field-issues v-if="projectId && field.key" :field-path="field.key" :project-id="projectId"></field-issues>
                        </div>
                        <validation-provider 
                            :rules="getValidationRules(field)" 
                            :debounce=500
                            immediate
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >
                            <editor-date-field v-model="local" :field="field"></editor-date-field>
                            <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>
                        <small class="help-text form-text text-muted">{{field.help_text}}</small>                            
                    </div>
                </div>

                <div v-else-if="fieldDisplayType(field)=='bounding_box'">
                    <div class="form-field-bounding-box">
                        <div class="d-flex align-center flex-nowrap">
                            <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                                <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                                <span v-if="isRequired" class="required-label"> * </span>
                            </label>
                            <field-issues v-if="projectId && field.key" :field-path="field.key" :project-id="projectId"></field-issues>
                        </div>
                        <editor-bounding-box-field v-model="local" :field="field"></editor-bounding-box-field>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
                    </div>
                </div>               
                
                

            </div>  `,
  methods: {
    findEnumByCode: function (code) {
      if (code && typeof code === "object") {
        if (Object.prototype.hasOwnProperty.call(code, "code")) {
          code = code.code;
        } else if (Object.prototype.hasOwnProperty.call(code, "value")) {
          code = code.value;
        }
      }

      return _.find(this.field.enum, { code: code });
    },
    getEnumStoreColumn: function () {
      if (this.field.enum_store_column) {
        return this.field.enum_store_column;
      }

      return "both";
    },
    getStoredEnumValue: function (value, enumStoreColumn = null) {
      const storeColumn = enumStoreColumn || this.getEnumStoreColumn();
      const enumCode = this.findEnumByCode(value);

      if (!enumCode) {
        return value;
      }

      if (storeColumn == "code") {
        return enumCode.code;
      }

      if (storeColumn == "label") {
        return enumCode.label;
      }

      return enumCode.label + " [" + enumCode.code + "]";
    },
    getEnumCodeFromLabel: function (label) {
      //code is enclosed in [] e.g. label [code]
      if (!label || label.length == 0) {
        return "";
      }

      // Only process strings - if label is not a string, return as-is
      if (typeof label !== 'string') {
        return label;
      }

      let code = label.match(/\[(.*?)\]/);
      if (code && code.length > 1) {
        return code[1];
      }
      return label;
    },
    update: function (value) {
      this.$emit("input", value);
    },
    fieldDisplayType(field) {
      if (field.display_type) {
        return field.display_type;
      }

      if (
        _.includes(
          ["text", "string", "integer", "boolean", "number"],
          field.display_type
        )
      ) {
        return "text";
      }

      return field.type;
    },
    /**
     * Get validation rules including data type check
     * @param {Object} field - The field object
     * @returns {String} Combined validation rules string
     */
    getValidationRules(field) {
      let rules = field.rules || '';

      if (field.is_required || field.required) {
        rules = rules ? `${rules}|required` : 'required';
      }
      
      // Determine the field type for validation
      const displayType = this.fieldDisplayType(field);
      let validationType = null;
      
      // Add data type validation for simple field types
      const simpleTypes = ['text', 'string', 'textarea', 'number', 'integer'];
      if (simpleTypes.includes(field.type)) {
        validationType = field.type;
      }
      // Skip data type validation for dropdown fields - they have enum validation
      // and v-model may contain enum objects for display purposes
      
      if (validationType) {
        const typeRule = `data_type:${validationType}`;
        rules = rules ? `${rules}|${typeRule}` : typeRule;
      }
      
      return rules;
    },
  },
});
