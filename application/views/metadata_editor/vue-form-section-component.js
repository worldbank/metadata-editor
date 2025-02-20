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
    computed: {
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
            return _.get(value,key);
        },
        parentValue: function(key){
            return _.get(this.parentElement,key);
        },
        update: function (key, value, column_data_type)
        {
            if (column_data_type=='number' || column_data_type=='integer'){
                let value_=Number(value);
                if (String(value_)==value){
                    value=value_;
                }
            }
            else if (column_data_type=='boolean'){
                let value_=String(value).toLowerCase();
                if (value_=='true'){
                    value=true;
                }
                else if (value_=='false'){
                    value=false;
                }
            }
            
            this.$emit('sectionUpdate', {
                'key': key,
                'value': JSON.parse(JSON.stringify(value))
            });
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
                                                @input="update(column.key, $event, column.type)"
                                            ></form-input>
                                    </div>
                                </div>  
                            </v-expansion-panel-content>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </template>

            </div>  `
})