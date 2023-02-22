//vue grid component
Vue.component('grid-component', {
    props:['value','columns','path', 'field'],
    data: function () {    
        return {
            field_data: this.value,
            key_path: this.path,
            store: this.$store
        }
    },
    watch: { 
        field_data: function(newVal, oldVal) {
            this.$vueSet (this.$store.state.formData, this.key_path, newVal);
        }
    
    },
    
    mounted: function () {
        if (!this.field_data){
            this.field_data=[];
            this.field_data.push({});
        }        
    },
    computed: {
        localColumns(){
            return this.columns;
        }
    },  
    template: `
            <!--vuejs template for grid -->
            <div class="grid-component border">

            <table class="table table-striped table-sm">
                <thead class="thead-light">
                <tr>
                    <th v-for="(column,idx_col) in columns" scope="col">
                        {{column.title}}
                        <span v-if="column.rules" class="required-label"> * </span>

                        <v-tooltip v-if="column.help_text" 
                            top 
                            max-width="300"
                        >
                        <template v-slot:activator="{ on, attrs }">
                            <i v-bind="attrs"
                            v-on="on" class="far fa-question-circle"></i>        
                        </template>
                        <span>{{column.help_text}}</span>
                        </v-tooltip>
                    </th>
                    <th scope="col">               
                    </th>
                </tr>
                </thead>

                <!--start-v-for-->
                <tbody>
                <tr  v-for="(item,index) in field_data">
                    <td v-for="(column,idx_col) in localColumns" scope="row">
                        <div>

                        <validation-provider 
                                :rules="column.rules" 
                                :name="columnName(column,path)"
                                v-slot="{ errors }"                                
                                >
                            
                            <div v-if="fieldDisplayType(column)=='textarea'">                                
                                <textarea
                                    :max-height="350"
                                    v-model="field_data[index][column.key]"        
                                    class="form-control form-field-textarea"
                                ></textarea>
                            </div>

                            <div v-else-if="fieldDisplayType(column)=='dropdown-custom'">
                                    <v-combobox
                                        v-model="field_data[index][column.key]"
                                        :items="column.enum"
                                        label=""                
                                        outlined
                                        dense
                                        clearable
                                        background-color="#FFFFFF"
                                        item-text="label"
                                        item-value="code"
                                        :return-object="false"
                                        class="form-field-dropdown-custom"
                                    ></v-combobox>
                            </div>
                            
                            <div v-else-if="fieldDisplayType(column)=='dropdown'">
                                <select 
                                    v-model="field_data[index][column.key]" 
                                    class="form-control form-control-sm form-field-dropdown"
                                    :id="field_data[index][column.key]" 
                                    style="min-width: 100px;"
                                >
                                    <option value="">Select</option>
                                    <option v-for="enum_ in column.enum" v-bind:key="enum_.code" :value="enum_.code">
                                        {{ enum_.label }}
                                    </option>
                                </select>
                            </div>
                            
                            <div v-else>
                                <input type="text"
                                    v-model="field_data[index][column.key]"
                                    class="form-control form-control-sm"                                 
                                >
                            </div>
                            


                            <span v-if="errors[0]" class="error"><small>{{ errors[0] }}</small></span>
                        </validation-provider>
                            
                        </div>
                    </td>
                    <td scope="row">        
                        <button type="button"  class="btn btn-sm btn-danger grid-button-delete float-right" v-on:click="remove(index)"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>
                <!--end-v-for -->
                </tbody>
            </table>

            <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-outline-primary btn-sm" @click="addRow" ><i class="fas fa-plus-square"></i> Add row</button>    
            </div>

            </div>  `,
    methods:{
        countRows: function(){
            return this.field_data.length;
        },
        addRow: function (){    
            this.field_data.push({});
            this.$emit('adding-row', this.field_data);
        },
        remove: function (index){
            this.field_data.splice(index,1);
        },
        columnName: function(column,path)
        {
            if (typeof column.name ==='undefined'){
                return path + '.' + column.title;
            }else{
                return column.name
            }
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