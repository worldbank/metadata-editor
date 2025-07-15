///// nested-array-component.js
Vue.component('nested-array', {
    props:['value','columns','path','title','field'],
    data: function () {    
        return {
            //local_data: [],
            key_path: this.path,
            active_sections:[]
        }
    },
    computed: {
        local_data(){
            let value= this.value ? this.value : [{}];

            if (value.length<1){
                value= [{}];
            }
            return value;
        },
        localColumns(){
            return this.columns;
        },
        isFieldReadOnly() {
            if (!this.$store.getters.getUserHasEditAccess) {
              return true;
            }

            if (!this.field){
                return false;
            }
      
            return this.field.is_readonly;
          }
        
    },
    methods:{
        localValueToString(item)
        {
            return this.nestedArrayToStringValue(item);
        },
        countRows: function(){
            return this.local_data.length;
        },
        addRow: function (){    
            this.local_data.push({});
            this.$emit('input', JSON.parse(JSON.stringify(this.local_data)));
        },
        remove: function (index){
            this.local_data.splice(index,1);
            this.$emit('input', JSON.parse(JSON.stringify(this.local_data)));
        },
        localValue: function(index,key)
        {
            return _.get(this.local_data[index],key);
        },
        update: function (index, key, value)
        {
            if (Array.isArray(this.local_data[index])){
                this.local_data[index] = {};
            }

            if (key.indexOf(".") !== -1 && this.local_data[index][key]){
                delete this.local_data[index][key];
            }

            _.set(this.local_data[index],key,value);
            this.$emit('input', JSON.parse(JSON.stringify(this.local_data)));
        },
        updateSection: function (index, key, value)
        {
            this.local_data[index] = value;
            this.$emit('input', JSON.parse(JSON.stringify(this.local_data)));
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
    },
    template: `
            <div class="nested-array" >

                <template>                
                    <v-expansion-panels :value="0" :multiple="true" :disabled="isFieldReadOnly">
                        <draggable tag="v-expansion-panel" :list="local_data" handle=".handle">
                        <v-expansion-panel v-for="(item,index) in local_data">
                        <v-expansion-panel-header>

                        <v-row class="handle">
                            <v-col sm="6" md="8" align="start">
                                <div class="float-left mr-2"><v-icon>mdi-file-tree-outline</v-icon> {{index + 1}} - {{title}}</div>
                                <div class="float-left text-wrap text-truncate text-muted text-normal" style="max-width: 300px;">{{localValueToString(item)}}</div>
                            </v-col>
                            <v-col sm="6" md="4" class="text-right">
                                <button type="button" class="btn btn-xs btn-outline-danger" @click="remove(index);return false;">
                                    <span v-if="local_data.length>1">{{$t("remove")}}</span>
                                    <span v-else>Clear</span>
                                    </button>
                            </v-col>
                        </v-row>

                        </v-expansion-panel-header>
                        <v-expansion-panel-content>
                            <template>
                                <div v-for="(column,idx_col) in localColumns" scope="row" :key="column.key" >

                                    <div>
                                    
                                    <template v-if="!_.includes(['nested_array','section'],column.type)">                                    
                                        <form-input
                                            :value="localValue(index,column.key)"
                                            :field="column"                            
                                            @input="update(index,column.key, $event)"
                                        ></form-input>
                                    </template>
                                    <template v-else-if="column.type=='section'">
                                        <!-- section -->                                        
                                        <nested-section-subsection
                                            :key="column.key"
                                            :parentElement="local_data[index]"
                                            :value="local_data[index][column.key+'section']"
                                            @input="updateSection(index,column.key+'section', $event)"
                                            :value="localValue(index,column.key)"
                                            @input="updateSection(index,column.key, $event)"
                                            :columns="column.props"
                                            :title="column.title"
                                            :path="column.key">
                                        </nested-section-subsection>
                                        <!-- end section -->
                                    </template>

                                    <template v-else-if="column.type=='nested_array'">
                                        <nested-array
                                            :key="column.key" 
                                            :value="localValue(index,column.key)"
                                            @input="update(index,column.key, $event)"
                                            :columns="column.props"
                                            :title="column.title"
                                            :path="column.key"
                                            :field="column">
                                        </nested-array> 
                                    </template>

                                    </div>
                                    
                                </div>
                            
                            </template>

                        </v-expansion-panel-content>
                        </v-expansion-panel>
                        </draggable>
                    </v-expansion-panels>
                    <div class="d-flex justify-content-center m-3">
                        <button type="button" class="btn btn-light btn-sm btn-outline-primary" @click="addRow" >Add section - {{title}}</button>
                    </div>
                
                </template>


            </div>  `    
})