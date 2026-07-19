///vue component for prop key field (read-only by default, soft schema validation)
Vue.component('vue-prop-key-field', {
    props:['value', 'parent', 'field'],
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
        IsExtensionField(){
            if (typeof isExtensionTemplateNode === 'function') {
                return isExtensionTemplateNode(this.field) || isExtensionTemplateNode(this.parent);
            }
            return !!(this.field && this.field.is_additional) || !!(this.parent && this.parent.is_additional);
        },
        ParentPath(){
            if (!this.parent){
                return '';
            }
            if (this.parent.prop_key){
                return this.parent.prop_key;
            }
            return this.parent.key || '';
        },
        AbsoluteKey(){
            if (!this.local_value){
                return this.ParentPath;
            }
            return this.ParentPath ? (this.ParentPath + '.' + this.local_value) : this.local_value;
        },
        SiblingKeys(){
            if (!this.parent || !this.parent.props){
                return [];
            }
            return this.parent.props.map(function(p){ return p.key; }).filter(Boolean);
        },
        SchemaRelativeKeyItems(){
            if (!this.ParentPath || !this.SchemaFieldKeys.length){
                return this.local_value ? [this.local_value] : [];
            }
            const aliases = this.SchemaKeyAliases;
            const parentSchema = resolveTemplateKeyToSchema(this.ParentPath, aliases);
            const prefix = parentSchema + '.';
            const used = this.SiblingKeys.filter(k => k !== this.value);
            const items = [];
            const seen = {};

            this.SchemaFieldKeys.forEach(function(fullKey){
                if (fullKey.indexOf(prefix) !== 0){
                    return;
                }
                const rest = fullKey.substring(prefix.length);
                const relative = rest.split('.')[0];
                if (!relative || seen[relative] || used.indexOf(relative) !== -1){
                    return;
                }
                seen[relative] = true;
                items.push(relative);
            });

            if (this.local_value && items.indexOf(this.local_value) === -1){
                items.unshift(this.local_value);
            }
            return items;
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
            let key = this.local_value == null ? '' : String(this.local_value).trim();
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

            let key = this.local_value == null ? '' : String(this.local_value).trim();

            if (key===''){
                this.validation_errors.push(this.$t('key_cannot_be_empty'));
                return false;
            }

            if (key.indexOf('.') !== -1 || key.match(/^[a-zA-Z0-9:_-]+$/)==null){
                this.validation_errors.push(this.$t('key_can_only_contain_letters_numbers_and_underscores'));
            }

            for (let i=0;i<this.SiblingKeys.length;i++){
                if (this.SiblingKeys[i] === key && key !== this.value){
                    this.validation_errors.push(this.$t('key_already_exists'));
                    break;
                }
            }

            const absoluteKey = this.ParentPath ? (this.ParentPath + '.' + key) : key;
            if (
                this.validation_errors.length === 0 &&
                !this.TemplateIsCustom &&
                !this.IsExtensionField &&
                this.SchemaFieldsLoaded &&
                this.SchemaFieldKeys.length > 0 &&
                this.ParentPath &&
                !(typeof isAdditionalTemplateKey === 'function' && (isAdditionalTemplateKey(absoluteKey) || isAdditionalTemplateKey(this.ParentPath))) &&
                !isAcceptedSchemaKey(absoluteKey, this.SchemaFieldKeys, this.SchemaKeyAliases)
            ){
                this.validation_errors.push(this.$t('key_unknown_schema_path'));
            }

            return this.validation_errors.length==0;
        }
    },
    template: `
        <div class="vue-prop-key-field">

            <div class="d-flex align-items-center mb-1">
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
                <div class="border rounded px-3 py-2 bg-light" style="font-family:monospace;font-size:0.875rem;">
                    {{local_value}}
                </div>
            </div>

            <template v-else>
                <div class="text-secondary font-small mb-2" style="font-size:small">
                    {{$t('key_edit_warning')}}
                </div>
                <v-combobox
                    id="key"
                    v-model="local_value"
                    :items="SchemaRelativeKeyItems"
                    :search-input.sync="search"
                    outlined
                    dense
                    hide-details
                    @change="onComboboxChange"
                    @blur="stopEditing"
                ></v-combobox>
            </template>
                
            <div v-if="validation_errors.length" class="font-small mt-1" style="font-size:small">
                <div v-for="error in validation_errors" class="text-danger">{{error}}</div>
            </div>

        </div>          
            `    
});
