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
        //loop this.local and find the code in the enum
        let list = [];
        _.forEach(this.local, (code) => {
          let enumCode = this.findEnumByCode(this.getEnumCodeFromLabel(code));
          if (enumCode) {
            list.push(enumCode);
          } else {
            list.push(code);
          }
        });
        return list;
      },
      set: function (value) {
        let list = [];
        _.forEach(value, (code) => {
          let enumCode = this.findEnumByCode(code);
          if (enumCode) {
            list.push(enumCode.label + " [" + enumCode.code + "]");
          } else {
            list.push(code);
          }
        });
        this.local = list;
      },
    },
    fieldEnumByCode: {
      get: function () {
        const code = this.field.enum.find(
          (code) => code.code === this.getEnumCodeFromLabel(this.local)
        );

        return code || this.local;
      },
      set: function (value) {

        let enum_store_column = this.field.enum_store_column;

        if (!enum_store_column) {
          enum_store_column = "both";
        }

        //if enum_store_column is both, store the code and label
        if (enum_store_column == "both") {
          let code = this.findEnumByCode(value);

          if (code) {
            this.local = code.label + " [" + code.code + "]";
          } else {
            this.local = value;
          }
        }
        else if (enum_store_column == "code") {
          let code = this.findEnumByCode(value);
          if (code) {
            this.local = code.code;
          } else {
            this.local = value;
          }
        }
        else if (enum_store_column == "label") {
          let code = this.findEnumByCode(value);
          if (code) {
            this.local = code.label;
          } else {
            this.local = value;
          }
        }
        
      },
    },
    formTextFieldStyle() {
      return this.$store.state.formTextFieldStyle;
    },
    ProjectType() {
      return this.$store.state.project_type;
    },
  },
  template: `
            <div class="form-input-field mt-3" :class="'form-input-' + field.type"  >

                <div v-if="field.type=='nested_array'">
                    <div class="form-field form-field-table">
                        <label :for="'field-' + field.key">{{field.title}}</label>
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
                        <label :for="'field-' + field.key">{{field.title}}</label>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
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
                    <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>

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
                        ></v-combobox>
                        
                    </div>
                                    
                </div>

                <div  v-else-if="fieldDisplayType(field)=='text'">                            
                    <div class="form-field" :class="['field-' + field.key] ">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}
                            <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                            <span v-if="field.required==true" class="required-label"> * </span>
                        </label> 

                        <validation-provider 
                            :rules="field.rules" 
                            :debounce=500
                            v-slot="{ errors }"                            
                            :name="field.title"
                            >            

                            <v-text-field
                                v-model="local"
                                :disabled="isFieldReadOnly"
                                v-bind="formTextFieldStyle"
                            ></v-text-field>                                                                                        
                            <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted">{{field.help_text}}</small>

                            <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>

                    </div>                                
                </div>

                <div v-else-if="fieldDisplayType(field)=='textarea'">
                    <div class="form-field-textarea"">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>                
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>

                        <validation-provider 
                            :rules="field.rules" 
                            :debounce=500
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
                            ></v-textarea>
                            <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>

                        <span v-if="errors[0]" class="field-error">{{errors[0]}}</span>
                        </validation-provider>
                    </div>
                </div> 

                <div v-else-if="fieldDisplayType(field)=='dropdown-custom'">
                    <div class="form-field-dropdown-custom">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>                
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>
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
                        ></v-combobox>
                        <small class="text-muted">{{field.enum_store_column}} - {{local}}</small>                        
                    </div>
                </div>
                
                <div v-else-if="fieldDisplayType(field)=='dropdown'">
                    <div class="form-field-dropdown">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>
                        <span class="small" v-if="field.help_text" role="button" data-toggle="collapse" :data-target="'#field-toggle-' + normalizeClassID(field.key)" ><i class="far fa-question-circle"></i></span>
                        <small :id="'field-toggle-' + normalizeClassID(field.key)" class="collapse help-text form-text text-muted mb-2">{{field.help_text}}</small>                        
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
                        ></v-select>                        
                        <small class="text-muted">{{local}}</small>
                    </div>
                </div>

                <div v-else-if="fieldDisplayType(field)=='date'">
                    <div class="form-field-date">
                        <label :for="'field-' + normalizeClassID(field.key)">{{field.title}}</label>                            
                        <editor-date-field v-model="local" :field="field"></editor-date-field>
                        <small class="help-text form-text text-muted">{{field.help_text}}</small>                            
                    </div>
                </div>               
                
                

            </div>  `,
  methods: {
    findEnumByCode: function (code) {
      return _.find(this.field.enum, { code: code });
    },
    getEnumCodeFromLabel: function (label) {
      //code is enclosed in [] e.g. label [code]
      if (!label) {
        return "";
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
  },
});
