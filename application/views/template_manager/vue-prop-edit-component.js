///prop edit componennt
Vue.component('prop-edit', {
    props:['value','parent'],
    data: function () {    
        return {          
          field_data_types: [
            "string",
            "number",
            "integer",
            "boolean"
          ],
          field_display_types: [
            "text",
            "textarea",
            "date",
            "dropdown",
            "dropdown-custom"
          ],
          enum_store_options:[
            {
              "value":"both",
              "label":"Label with code"
            },
            {
              "value":"code",
              "label":"Code"
            },
            {
              "value":"label",
              "label":"Label"
            }            
          ]
        }
    },
    mounted: function(){
      // Set prop_key only when missing (never overwrite — avoids doubling section prefixes onto row-local keys).
      if (this.prop && this.prop.key && this.parent && !this.prop.prop_key) {
        const schemaKeys = this.$store.state.schema_field_keys || [];
        const aliases = this.$store.state.schema_key_aliases || {};
        this.prop.prop_key = computeTemplatePropKey(
          this.prop,
          this.parent,
          this.$store.state.user_tree_items || [],
          schemaKeys,
          aliases
        );
      }
    },    
    computed: {        
        prop:{           
            get(){
              return this.value;
            },
            set(val){           
              this.$emit('input:value', val);
            }
        },
        SimpleControlledVocabColumns: function(){
          return [
            {
              'type':'text',
              'key':'code',
              'title':'Code'
            },
            {
              'type':'text',
              'key':'label',
              'title':'Label'
            }
          ]
        },
        PropEnum: {
          get() {
            if (!this.prop.enum) {
              return [{}];
            }

            if (this.prop.enum && this.prop.enum.length>0 && typeof(this.prop.enum[0]) =='string')
            {
              let enum_list=[];
              this.prop.enum.forEach(function(item){
                enum_list.push({
                  'code':item,
                  'label':item
                });
              });
              Vue.set(this.prop,"enum",enum_list);
              return enum_list;
            }
            return this.prop.enum;
          },
          set(newValue) {
            Vue.set(this.prop,"enum",newValue);
          }
        },
        PropEnumStoreColumn:{
          get: function(){
            if (this.prop.enum_store_column){
              return this.prop.enum_store_column;
            }
            return 'both';
          },
          set: function(newValue){
            Vue.set(this.prop, "enum_store_column", newValue);
          }
        },
        schemaFieldForProp: function(){
          if (!this.prop || this.TemplateDataType() === 'custom') {
            return null;
          }
          const schemaKeys = this.$store.state.schema_field_keys || [];
          const aliases = this.$store.state.schema_key_aliases || {};
          const path = typeof resolveTemplatePropSchemaPath === 'function'
            ? resolveTemplatePropSchemaPath(
                this.prop,
                this.$store.state.user_tree_items || [],
                schemaKeys,
                aliases
              )
            : (this.prop.prop_key || this.prop.key);
          if (!path) {
            return null;
          }
          return this.$store.getters.getSchemaFieldByDottedKey(path);
        },
        propEnumSchemaWarnings: function(){
          const schemaField = this.schemaFieldForProp;
          if (!schemaField || !schemaField.enum || !Array.isArray(schemaField.enum) || schemaField.enum.length === 0) {
            return [];
          }
          if (!this.prop || !this.prop.enum || !Array.isArray(this.prop.enum)) {
            return [];
          }
          if (this.PropEnumStoreColumn === 'label') {
            return [];
          }
          const allowed = {};
          schemaField.enum.forEach(function(v) {
            allowed[String(v)] = true;
          });
          const warnings = [];
          this.prop.enum.forEach(function(row) {
            if (!row || row.code === undefined || row.code === null || row.code === '') {
              return;
            }
            const code = String(row.code);
            if (!allowed[code]) {
              warnings.push(code);
            }
          });
          return warnings;
        },
        schemaEnumAllowedLabel: function(){
          const schemaField = this.schemaFieldForProp;
          if (!schemaField || !schemaField.enum) {
            return '';
          }
          return schemaField.enum.join(', ');
        },
    },
    methods:{
      TemplateDataType(){
        return this.$store.state.user_template_info.data_type;
      },
      isAdminMetaTemplate(){
        return this.$store.state.user_template_info.data_type=='admin_meta';
      },
      updatePropKey: function(e)
      {
        const cleanedKey = (e || '').trim();
        this.prop.key = cleanedKey;
        const schemaKeys = this.$store.state.schema_field_keys || [];
        const aliases = this.$store.state.schema_key_aliases || {};
        this.prop.prop_key = computeTemplatePropKey(
          this.prop,
          this.parent,
          this.$store.state.user_tree_items || [],
          schemaKeys,
          aliases
        );
      },    
      isField: function(field_type){
        let field_types= [
          "text",
          "string",
          "number",
          "textarea",
          "dropdown",
          "date",
          "boolean",
          "integer"
        ];
        return field_types.includes(field_type);
      },
      isArrayField: function(prop){
        let array_types=['array', 'nested_array', 'simple_array'];

        if (array_types.includes(prop.type) && !prop.prop){
          return true;
        }

        return false;
      },
      isFlatObjectArrayProp: function (prop) {
        return typeof isFlatObjectArrayField === 'function' && isFlatObjectArrayField(prop);
      },
      EnumListUpdate: function(e) {
        if (Array.isArray(e)){
          this.$set(this.prop, "enum", e);
        }
        if (!this.prop.enum) {
          this.$set(this.prop, "enum", []);
        }
      },
      DefaultUpdate: function (e){
        if (Array.isArray(e)){
          this.$set(this.prop, "default", e);
        }
        if (!this.prop.default) {
          this.$set(this.prop, "default", []);
        }
      },
      RulesUpdate: function (e)
      {
        this.$set(this.prop, "rules", e);
      },
      HasAdditionalPrefix(value){
        return value.indexOf('additional.')==0;
      },
    },
    template: `<?php require_once 'vue-prop-edit-component-template.php';?>`    
});

