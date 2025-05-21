//vue grid component
Vue.component('simple-array-component', {
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
        /*if (!this.field_data){
            this.field_data=[];
            this.field_data.push("");
            //this.field_data.push(null);
        }*/
        if (!Array.isArray(this.field_data)){
            this.field_data=[];
        }
    },
    computed: {
        localColumns(){
            return this.columns;
        }
    },  
    template: `
            <!--vuejs template for simple-array -->
            <div class="simple-array-component bg-white p-2 border" >
            
            <table class="table table-striped table-sm">
                <!--start-v-for-->
                <tbody>
                <tr  v-for="(item,index) in field_data">
                    <td scope="row">
                        <div>

                        <validation-provider 
                                :rules="field.rules" 
                                :name="field.name"
                                v-slot="{ errors }"                                
                                >
                            
                            <input type="text"
                                v-model="field_data[index]"
                                class="form-control form-control-sm"                                 
                            >
                            <span v-if="errors[0]" class="error">{{ errors[0] }}</span>
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
                <button type="button" class="btn btn-link btn-block btn-sm" @click="addRow" ><i class="fas fa-plus-square"></i> Add row</button>    
            </div>

            </div>  `,
    methods:{
        countRows: function(){
            return this.field_data.length;
        },
        addRow: function (){    
            this.field_data.push("");
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