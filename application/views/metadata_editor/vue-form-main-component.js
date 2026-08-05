//vue-main-form-component ///////////////////////////////////////////////////
Vue.component('form-main', {
    props: ['title', 'items', 'depth', 'css_class','path'],
    data() {
        return {
        }
    },
    methods:{
        activeFormFieldDisplayType()
        {
            const field = this.activeSection;
            if (!field) {
                return '';
            }
            if (field.display_type){
                return field.display_type;
            }

            if (_.includes(['text','string','integer','boolean','number'],field.display_type)){
                return 'text';
            }            
            
            return field.type;
        },
        localValue: function(key)
        {
            return _.get(this.formData,key);
        },
        update: function (key, value)
        {
            if (key.indexOf(".") !== -1 && this.formData[key]){
                delete this.formData[key];
            }

            _.set(this.formData,key,value);
        },
        updateSection: function (obj)
        {
            this.update(obj.key,obj.value);
        }
    },
    computed: {
        formData () {
            return this.$deepModel('formData')
        },
        activeSection()
        {
            return this.$store.state.treeActiveNode;
        },
        formField()
        {
            return this.activeSection;
        },
        localColumns(){
            const field = this.activeSection;
            return field && field.items ? field.items : [];
        },
        
        formTextFieldStyle(){            
            return this.$store.state.formTextFieldStyle;
        },
        
        
    },
    template: `
        <div class="metadata-form p-3 pt-5 mb-3" >

            <div v-if="!activeSection" class="text-muted font-small py-3">
                {{ $t('select_form_section') || 'Select a section from the tree.' }}
            </div>

            <template v-else>
            <!-- form-section -->
            <div v-if="activeFormFieldDisplayType()=='section_container'"  class="form-section m-3" >
                <v-form-preview                         
                        :items="formField.items" 
                        :title="formField.title"
                        :path="formField.key"
                        :field="formField"
                    >
                </v-form-preview>
            </div>
            <!-- end-form-section -->

            <!-- form-section -->
            <div v-if="activeFormFieldDisplayType()=='section'"  class="form-section" >        
                <h5 class="mt-3">{{formField.title}}</h5>
                <div v-for="(column,idx_col) in localColumns" scope="row" :key="column.key" >
                    <template v-if="column.type=='section'">
                        <form-section
                            :parentElement="formData"
                            :value="localValue(column.key)"
                            :columns="column.items"
                            :title="column.title"
                            :path="column.key"
                            :field="column"                            
                            @sectionUpdate="updateSection($event)"
                        ></form-section>                        
                    </template>
                    <template v-if="!_.includes(['section'],column.type)">
                        <form-input
                            :value="localValue(column.key)"
                            :field="column"                            
                            @input="update(column.key, $event)"
                        ></form-input>                    
                    </template>
                </div>
            </div>
            <!-- end-form-section -->


            <div v-if="activeFormFieldDisplayType()!='section'" class="mt-2 mb-3">
                <form-input
                    :value="localValue(formField.key)"
                    :field="formField"
                    @input="update(formField.key, $event)"
                ></form-input>   
            </div>

            </template>

        </div>
    `
});



