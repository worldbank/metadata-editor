///vue component for editing custom KEY field
Vue.component('vue-custom-key-field', {
    props:['value','field'],
    data: function () {    
        return {
            template: this.value,
            validation_errors: [],
            local_value: JSON.parse(JSON.stringify(this.value)),
        }
    },
    mounted: function(){
      this.validation_errors=[];
    },
    
    computed: {
        isFieldLocked(){
            if (this.field && this.field.is_locked){
                return this.field.is_locked==true;
            }
            
        },
        coreTemplateParts(){
          return this.$store.state.core_template_parts;
        },
        TemplateActiveNode(){
          return this.$store.state.active_node;
        },
        ActiveCoreNode(){
          return this.$store.state.active_core_node;
        },
        UserTreeUsedKeys(){
          return this.$store.getters.getUserTreeKeys;
        },
        CoreTemplate(){
          return this.$store.state.core_template;
        },
        UserTemplate(){
          return this.$store.state.user_template;
        },
        CoreTreeItems(){
          return this.$store.state.core_tree_items;
        },
        UserTreeItems(){
          return this.$store.state.user_tree_items;
        },
        HasAdditionalPrefix(){
            return this.local_value.indexOf('additional.')==0;
        },
        isKeyValid(){
            // key must be unique
            // key must not contain spaces
            // key can only contain dot, letters, numbers, and underscores
            // key cannot be empty
            
            let key=this.local_value;

            //break key into parts using dot as separator
            let parts=key.split('.');

            //check if key has any empty parts
            if (parts.indexOf('')!==-1){
                return false;
            }

            //check all parts only contain letters, numbers, and underscores, dashes
            for(let i=1;i<parts.length;i++){
                if (parts[i].match(/^[a-zA-Z0-9_-]+$/)==null){
                    return false;
                }
            }

            //check if key is unique            
            if (this.UserTreeUsedKeys.indexOf(this.local_value)!==-1 && this.local_value!=this.value){
                return false;
            }

            return true;
        }
    },
    watch: {
        local_value: function(newVal, oldVal){
            //run validation
            this.ValidateKey();
        }
    },
    methods:{
        UpdateKeyValue: function(){
            this.validation_errors=[];
            
            if (!this.ValidateKey()){
                return;
            }
            
            this.$emit('input', this.local_value);

        },
        ValidateKey: function()
        {
            // key must be unique
            // key must not contain spaces
            // key can only contain dot, letters, numbers, and underscores
            // key cannot be empty

            this.validation_errors=[];

            let key=this.local_value;

            if (key==''){
                this.validation_errors.push(this.$t('key_cannot_be_empty'));
            }

            //break key into parts using dot as separator
            let parts=key.split('.');

            //check if key has any empty parts
            if (parts.indexOf('')!==-1){
                this.validation_errors.push(this.$t('key_must_not_contain_empty_parts'));
            }

            //check all parts only contain letters, numbers, dash, and underscores
            for(let i=1;i<parts.length;i++){
                if (parts[i].match(/^[a-zA-Z0-9_-]+$/)==null){
                    this.validation_errors.push(this.$t('key_can_only_contain_letters_numbers_and_underscores'));
                    break;
                }
            }

            //check if key is unique            
            if (this.UserTreeUsedKeys.indexOf(this.local_value)!==-1 && this.local_value!=this.value){
                this.validation_errors.push(this.$t('key_already_exists'));
            }

            return this.validation_errors.length==0;
        }
    },
    template: `
            <div class="vue-key-field">

              <div><label for="key">{{$t("key")}}:</label></div>

                <div class="form-group">
                    <input type="text" class="form-control" id="key" placeholder="Key" v-model="local_value" v-on:blur="UpdateKeyValue" :disabled="isFieldLocked">
                    <div class="text-secondary font-small" style="font-size:small">{{this.value}}</div>
                </div>

                <div class="text-secondary font-small" style="margin-bottom:15px;font-size:small">                    
                    <div v-for="error in validation_errors" class="text-danger">{{error}}</div>
                </div>  

            </div>          
            `    
});

