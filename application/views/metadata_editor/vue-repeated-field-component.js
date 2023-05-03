//vue repeated field - simple_array
Vue.component('repeated-field', {
    props:['value', 'field'],
    data: function () {    
        return {
        }
    },
    watch: {
    },
    
    mounted: function () {        
    },
    computed: {
        local(){
            let value= this.value ? this.value : [];

            if (!Array.isArray(value) || value.length<1){
                value= [""];
            }
        
            return value;
        }
    },
    methods:{
        update: function (index, value)
        {
            /*if (Array.isArray(this.local[index])){
                this.local[index] = {};
            }*/

            this.local[index] = value;
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));            
        },
        countRows: function(){
            return this.local.length;
        },
        addRow: function (){    
            this.local.push("");
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
        },
        remove: function (index){
            this.local.splice(index,1);
            this.$emit('input', JSON.parse(JSON.stringify(this.local)));
        }
    },  
    template: `
    <div class="simple-array-component bg-white p-2 border" >
            
        <table class="table table-striped table-sm">
            <!--start-v-for-->
            <tbody>
            <tr  v-for="(item,index) in local">
                <td scope="row">
                    <div>

                    <validation-provider 
                            :rules="field.rules" 
                            :name="field.name"
                            v-slot="{ errors }"                                
                            >
                        
                        <input type="text"
                                :value="local[index]"
                                @input="update(index,$event.target.value)"
                                class="form-control form-control-sm"                                 
                            >

                        <span v-if="errors[0]" class="error">{{ errors[0] }}</span>
                    </validation-provider>
                        
                    </div>
                </td>
                <td scope="row">
                    <div class="mr-1">
                        <v-icon class="v-delete-icon"  v-on:click="remove(index)">mdi-close-circle-outline</v-icon>
                    </div>                    
                </td>
            </tr>
            <!--end-v-for -->
            </tbody>
        </table>

        <div class="d-flex justify-content-center">
            <button type="button" class="btn btn-link btn-block btn-sm" @click="addRow" ><i class="fas fa-plus-square"></i> Add row</button>    
        </div>

        </div>`    
});

