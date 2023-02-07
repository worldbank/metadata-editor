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
            console.log('Prop changed: ', JSON.stringify(newVal), ' | was: ', JSON.stringify(oldVal))
            this.$vueSet (this.$store.state.formData, this.key_path, newVal);
        }
    
    },
    
    mounted: function () {
        //set data to array if empty or not set
        if (!this.field_data){
            this.field_data=[];
            this.field_data.push({});
        }
        console.log("array-mounted  ",this.path,JSON.stringify(this.field_data), JSON.stringify(this.value));
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
                            
                            <input type="text"
                                v-model="field_data[index][column.key]"
                                class="form-control form-control-sm"                                 
                            >
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
        }
    }
})