///// nested-array-component.js
Vue.component('nested-array', {
    props:['value','columns','path','title'],
    data: function () {    
        return {
            field_data: this.value,
            key_path: this.path,
            active_sections:[]
        }
    },
    watch: { 
        /*field_data: function(newVal, oldVal) {
            console.log("watch field_data",this.key_path,newVal, oldVal);
            //this.$vueSet (this.$store.state.formData, this.key_path, newVal);
        }*/
    },
    mounted: function () {
        //console.log("mounted nested array",Array.isArray(this.value),this.path,JSON.stringify(this.value));
    },
    computed: {
        local(){
            let value= this.value ? this.value : [{}];

            if (value.length<1){
                value= [{}];
            }
        
            //console.log("local value",JSON.stringify(value));
            return value;
        },
        localColumns(){
            return this.columns;
        }
        /*formData () {
            return this.$deepModel('formData')
        },*/
    },  
    template: `
            <div class="nested-array" >

                <div class="row">
                <div class="col-md-12">

                <template>
                    <v-expansion-panels :value="0">
                        <v-expansion-panel v-for="(item,index) in local">
                        <v-expansion-panel-header>

                        <v-row>
                            <v-col sm="6" md="8" align="start">
                                <v-icon>mdi-file-tree-outline</v-icon> {{index + 1}} - {{title}}
                            </v-col>
                            <v-col sm="6" md="4" class="text-right">
                                <button type="button" class="btn btn-xs btn-outline-danger" @click="remove(index);return false;">
                                    <span v-if="local.length>1">Remove</span>
                                    <span v-else>Clear</span>
                                    </button>
                            </v-col>
                        </v-row>

                        </v-expansion-panel-header>
                        <v-expansion-panel-content>
                            <template>
                                <div v-for="(column,idx_col) in localColumns" scope="row" >

                                    <div>
                                    <template v-if="!_.includes(['nested_array','array','simple_array','section'],column.type)">
                                        <form-input
                                            :value="local[index][column.key]"
                                            :field="column"                            
                                            @input="update(index,column.key, $event)"
                                        ></form-input>
                                    </template>

                                    <template v-else-if="column.type=='nested_array'">
                                    NESTED_ARRAY                                        
                                        <nested-array
                                            :key="column.key" 
                                            :value="local[index][column.key]"
                                            @input="update(index,column.key, $event)"
                                            :columns="column.props"
                                            :title="column.title"
                                            :path="column.key">
                                        </nested-array> 
                                    </template>

                                    </div>
                                    
                                </div>
                            
                            </template>

                        </v-expansion-panel-content>
                        </v-expansion-panel>
                    </v-expansion-panels>
                    <div class="d-flex justify-content-center m-3">
                                <button type="button" class="btn btn-light btn-sm btn-outline-primary" @click="addRow" >Add section - {{title}}</button>
                            </div>
                </template>

                

                </div>
                <div class="col-md-6" style="display:none;">
                    inside nested array
                            </hr>
                            path:{{path}}
                            </hr>
                            <strong>local:{{local}}</strong>
                            </HR>
                            value:{{value}}   
                            </HR>
                            path:{{path}}
                            </HR>
                            title:{{title}}
                            </hr>
                            <pre>{{columns}}</pre>
                
                </div>
                </div>

            </div>  `,
    methods:{
        countRows: function(){
            return this.local.length;
        },
        addRow: function (){    
            this.local.push({});
            //this.$emit('adding-row', this.field_data);
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
        },
        remove: function (index){
            this.local.splice(index,1);
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
            console.log("after removed", this.local);
        },
        update: function (index, key, value)
        {
            console.log("updating value",index,key,value);
            if (Array.isArray(this.local[index])){
                this.local[index] = {};
            }

            this.local[index][key] = value;
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
        },

        fieldDisplayType(field)
        {
            if (field.display_type){
                return field.display_type;
            }

            if (_.includes(['text','string','integer','boolean','number'],field.display_type)){
                return 'text';
            }            
            
            return field.type;
        }
    }
})