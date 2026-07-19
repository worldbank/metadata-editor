///vue component for editing KEY field (read-only by default, soft schema validation)
Vue.component('vue-custom-key-field', {
    props:['value','field'],
    data: function () {    
        return {
            validation_errors: [],
            local_value: this.value ? JSON.parse(JSON.stringify(this.value)) : '',
            is_editing: false,
            search: null
        }
    },
    mounted: function(){
      this.ValidateKey();
    },
    
    computed: {
        UserTreeUsedKeys(){
          return this.$store.getters.getUserTreeKeys;
        },
        TemplateIsCustom(){
          return this.$store.state.user_template_info && this.$store.state.user_template_info.data_type=='custom';
        },
        SchemaFieldKeys(){
          return this.$store.state.schema_field_keys || [];
        },
        SchemaKeyAliases(){
          return this.$store.state.schema_key_aliases || {};
        },
        SchemaFieldsLoaded(){
          return this.$store.state.schema_fields_loaded;
        },
        UnusedSchemaFieldKeys(){
          return this.$store.getters.getUnusedSchemaFieldKeys || [];
        },
        IsStructuralNode(){
            if (!this.field || !this.field.type){
                return false;
            }
            return ['section','section_container','template_root','template_description'].indexOf(this.field.type) !== -1;
        },
        HasAdditionalPrefix(){
            return typeof isAdditionalTemplateKey === 'function'
              ? isAdditionalTemplateKey(this.local_value)
              : (typeof this.local_value === 'string' && (this.local_value === 'additional' || this.local_value.indexOf('additional.')===0));
        },
        IsExtensionField(){
            return typeof isExtensionTemplateNode === 'function'
              ? isExtensionTemplateNode(this.field)
              : !!(this.field && this.field.is_additional);
        },
        SchemaAutocompleteItems(){
            let items = this.UnusedSchemaFieldKeys.slice();
            if (this.local_value && items.indexOf(this.local_value) === -1){
                items.unshift(this.local_value);
            }
            return items;
        },
        UseAutocomplete(){
            // Extension/custom fields use free-text keys (often outside schema paths)
            return !this.IsStructuralNode && !this.TemplateIsCustom && !this.IsExtensionField && !this.HasAdditionalPrefix;
        }
    },
    watch: {
        value: function(newVal){
            if (newVal !== this.local_value){
                this.local_value = newVal ? JSON.parse(JSON.stringify(newVal)) : '';
                this.is_editing = false;
                this.ValidateKey();
            }
        },
        local_value: function(){
            this.ValidateKey();
        },
        SchemaFieldKeys: function(){
            this.ValidateKey();
        }
    },
    methods:{
        startEditing: function(){
            this.is_editing = true;
            this.$nextTick(function(){
                let el = this.$el && this.$el.querySelector('input');
                if (el){
                    el.focus();
                }
            }.bind(this));
        },
        stopEditing: function(){
            this.UpdateKeyValue();
            this.is_editing = false;
        },
        UpdateKeyValue: function(){
            // Soft validation: always apply non-empty keys; errors stay visible under the field
            let key = this.local_value == null ? '' : String(this.local_value);
            this.ValidateKey();
            if (key === ''){
                this.local_value = this.value;
                this.ValidateKey();
                return;
            }
            if (key !== this.value){
                this.$emit('input', key);
            }
        },
        onComboboxChange: function(val){
            if (val === null || val === undefined){
                return;
            }
            this.local_value = val;
            this.UpdateKeyValue();
            this.is_editing = false;
        },
        ValidateKey: function()
        {
            this.validation_errors=[];

            let key = this.local_value == null ? '' : String(this.local_value);

            if (key===''){
                this.validation_errors.push(this.$t('key_cannot_be_empty'));
                return false;
            }

            let parts=key.split('.');

            if (parts.indexOf('')!==-1){
                this.validation_errors.push(this.$t('key_must_not_contain_empty_parts'));
            }

            for(let i=0;i<parts.length;i++){
                if (parts[i].match(/^[a-zA-Z0-9:_-]+$/)==null){
                    this.validation_errors.push(this.$t('key_can_only_contain_letters_numbers_and_underscores'));
                    break;
                }
            }

            if (this.UserTreeUsedKeys.indexOf(key)!==-1 && key!=this.value){
                this.validation_errors.push(this.$t('key_already_exists'));
            }

            // Soft schema check — skip structural / custom type / additional.* / is_additional nodes
            // Accepts item-form aliases (variable.* → variables.*)
            if (
                this.validation_errors.length === 0 &&
                !this.IsStructuralNode &&
                !this.TemplateIsCustom &&
                !this.HasAdditionalPrefix &&
                !this.IsExtensionField &&
                this.SchemaFieldsLoaded &&
                this.SchemaFieldKeys.length > 0 &&
                !isAcceptedSchemaKey(key, this.SchemaFieldKeys, this.SchemaKeyAliases)
            ){
                this.validation_errors.push(this.$t('key_unknown_schema_path'));
            }

            return this.validation_errors.length==0;
        }
    },
    template: `
            <div class="vue-key-field">

              <div class="d-flex align-items-center mb-1">
                <label for="key" class="mb-0 mr-2">{{$t("key")}}:</label>
                <v-icon
                    v-if="!is_editing"
                    small
                    color="primary"
                    style="cursor:pointer"
                    :title="$t('key_edit')"
                    @click="startEditing"
                >mdi-pencil</v-icon>
              </div>

                <div v-if="!is_editing" class="mb-1">
                    <div class="border rounded px-3 py-2 bg-light" style="font-family:monospace;font-size:0.875rem;word-break:break-all;">
                        {{local_value}}
                    </div>
                </div>

                <template v-else>
                    <div class="text-secondary font-small mb-2" style="font-size:small">
                        {{$t('key_edit_warning')}}
                    </div>

                    <v-text-field
                        v-if="!UseAutocomplete"
                        id="key"
                        v-model="local_value"
                        outlined
                        dense
                        class="mb-1"
                        hide-details
                        @blur="stopEditing"
                        @keyup.enter="stopEditing"
                        @keyup.esc="is_editing=false; local_value=value; ValidateKey()"
                    ></v-text-field>

                    <v-combobox
                        v-else
                        id="key"
                        v-model="local_value"
                        :items="SchemaAutocompleteItems"
                        :search-input.sync="search"
                        outlined
                        dense
                        class="mb-1"
                        hide-details
                        @change="onComboboxChange"
                        @blur="stopEditing"
                    ></v-combobox>
                </template>

                <div v-if="validation_errors.length" class="font-small mb-3" style="font-size:small">
                    <div v-for="error in validation_errors" class="text-danger">{{error}}</div>
                </div>

            </div>          
            `    
});
