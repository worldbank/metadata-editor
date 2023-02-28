///// form-section
Vue.component('form-section', {
    props:['value','columns','path','title','parentElement'],
    data: function () {    
        return {
            local_data:{}
        }
    },
    watch: { 
    },
    mounted: function () {     
        /*console.log("local value beforex",JSON.stringify(this.parentElement));
        let value= this.parentElement ? this.parentElement : {};

        this.local_data= value;*/
    },
    computed: {
        /*local(){
            //return this.value;
            console.log("local value before",JSON.stringify(this.value));
            let value= this.value ? this.value : {};

            return value;

            if (value.length<1){
                value= [{}];
            }
            console.log("local value after",JSON.stringify(value));
            //console.log("local value",JSON.stringify(value));
            return value;
        },*/
        localColumns(){
            return this.columns;
        },
        formData () {
            return this.$deepModel('formData')
        }
    },
    methods:{
        countRows: function(){
            return this.field_data.length;
        },
       
        localValue: function(key)
        {
            let value= this.parentElement ? this.parentElement : {};

            console.log("sectionsearach for key",key,value, _.get(value,key));
            return _.get(value,key);
        },
        parentValue: function(key){
            console.log("searching for parent value path",key,this.parentElement);
            return _.get(this.parentElement,key);
        },
        update: function (key, value)
        {            
            this.$emit('sectionUpdate', {
                'key': key,
                'value': JSON.parse(JSON.stringify(value))
            });
            console.log("emitting from section",key,value);
        },
        fieldDisplayType(field)
        {
            /*if (field.type=='simple_array'){
                return 'simple_array';
            }*/

            if (field.display_type){
                return field.display_type;
            }

            if (_.includes(['text','string','integer','boolean','number'],field.display_type)){
                return 'text';
            }            
            
            return field.type;
        }
    },
    template: `
            <div class="form-section mt-3" >

                    <template>
                        <v-expansion-panels :value="0">
                            <v-expansion-panel>
                            <v-expansion-panel-header>
                                <span><v-icon>mdi-folder-text-outline</v-icon> {{title}}</span>
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <div v-for="(column,idx_col) in localColumns" scope="row" >
                                    <div v-if="column.type=='section'">
                                    
                                        <nested-section-subsection
                                            :value="localValue(column.key)"
                                            :columns="column.items"
                                            :path="column.key"
                                            :title="column.title"
                                            :parentElement="parentElement"
                                        >
                                        </nested-section-subsection>
                                    </div>
                                    <div v-else>
                                            <form-input
                                                :value=" localValue(column.key)"
                                                :field="column"
                                                @input="update(column.key, $event)"
                                            ></form-input>
                                    </div>
                                </div>  
                            </v-expansion-panel-content>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </template>

            </div>  `
})