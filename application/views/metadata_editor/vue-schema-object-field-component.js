//vue schema object field component
Vue.component('schema-object-field', {
    props:['value', 'field','is_readonly'],
    data: function () {    
        return {
            new_object:{}
        }
    },
    watch: {
        field_data: {
            handler: function (val, oldVal) {                
                if (_.isEmpty(oldVal)){return;}
                this.$emit('input', val);
            },
            deep: true
        }
    },
    
    mounted: function () {
        
    },
    computed: {
        localColumns(){
            return [
                {key: 'key', title: 'Key', type: 'string'},
                {key: 'value', title: 'Value', type: 'string'}
            ];
        },
        field_data:
        {            
            get(){
                return this.value;
            },            
            set(val){
                this.$emit('input', val);
            }
        },
        isReadOnly(){
            if (!this.is_readonly){
                return false;
            }

            return this.is_readonly;
        }
    },
    methods:{
        addObject: function (){

            //check if key already exists
            if (this.field_data[this.new_object.key]){
                alert("Key already exists");
                return;
            }

            if (!this.new_object.key){
                alert("Key is required");
                return;
            }

            if (!this.new_object.value){
                alert("Value is required");
                return;
            }

            this.field_data[this.new_object.key]=this.new_object.value;
            this.new_object={};
            this.$emit('input', JSON.parse(JSON.stringify(this.field_data)));
        },
        OnObjectKeyUpdate: function (newValue, key)
        {
            //check newValue is not already in the object
            if (this.field_data[newValue]){
                alert("Key already exists");
                return;
            }

            //add the key with new value
            this.field_data[newValue]=this.field_data[key];
           
            //remove the key
            Vue.delete(this.field_data, key);
        },
        removeByKey: function (key){
            Vue.delete(this.field_data, key);
        },
        isValidKeValue(){
            if (!this.new_object.key){
                return false;
            }

            if (!this.new_object.value){
                return false;
            }
            else{
                if (this.new_object.value.trim().length==0){
                    return false;
                }
            }

            return true;
        }
        
    },  
    template: `
            <div class="schema-object-field-component">
            
           <table class="table table-striped table-sm border-bottom mb-0">
                <thead class="thead-light">
                <tr>
                    <th></th>
                    <th v-for="(column,idx_col) in localColumns" scope="col">
                        
                            <span v-if="column.title">{{column.title}}</span>
                            <span v-else>{{column.key}}</span>                        
                        
                        <span v-if="column.rules" class="required-label"> * </span>
                    </th>
                    <th scope="col">               
                    </th>
                </tr>
                </thead>

                <!--start-v-for-->
                <tbody>
                <tr  v-for="(item,key,index) in field_data">
                    <td></td>
                    <td>
                        <div>
                            <input type="text"
                                :disabled="true"
                                v-bind:value="key"
                                v-on:input="OnObjectKeyUpdate($event.target.value, key)"
                                class="form-control form-control-sm"                                 
                            >                            
                        </div>
                    </td>
                    <td>
                        <div>
                            <input type="text"
                                :disabled="isReadOnly"
                                v-model="field_data[key]"
                                class="form-control form-control-sm"                                 
                            >
                        </div>
                    </td>
                    <td scope="row">        
                        <div class="mr-1">
                        <v-icon :disabled="isReadOnly" class="v-delete-icon"  v-on:click="removeByKey(key)">mdi-close-circle-outline</v-icon>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <input class="form-control form-control-sm"  type="text" v-model="new_object.key" />
                    </td>
                    <td>
                        <input class="form-control form-control-sm"  type="text" v-model="new_object.value" />                    
                    </td>
                    <td>
                        <v-btn small icon color="green" :disabled="!isValidKeValue()"><v-icon class="v-add-icon"   v-on:click="addObject">mdi-plus-circle-outline</v-icon></v-btn>
                    </td>
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

           

            </div>  `    
});

