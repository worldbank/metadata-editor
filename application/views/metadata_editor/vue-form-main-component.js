//vue-main-form-component ///////////////////////////////////////////////////
Vue.component('form-main', {
    props: ['title', 'items', 'depth', 'css_class','path'],
    data() {
        return {
        }
    },
    created() {        
        this.field=this.activeSection;
    },
    methods:{
        activeFormFieldDisplayType()
        {
            if (this.field.display_type){
                return this.field.display_type;
            }

            if (_.includes(['text','string','integer','boolean','number'],this.field.display_type)){
                return 'text';
            }            
            
            return this.field.type;
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
            return this.field;
        },
        formTextFieldStyle(){            
            return this.$store.state.formTextFieldStyle;
        },
        localColumns(){
            return this.field.items;
        },
        
        
    },
    template: `
        <div class="metadata-form mt-3" >

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
                <label :for="'field-' + normalizeClassID(formField.key)">{{formField.title}}</label>
                <form-input
                    :value="localValue(formField.key)"
                    :field="formField"
                    @input="update(formField.key, $event)"
                ></form-input>   
            </div>

        </div>
    `
});



