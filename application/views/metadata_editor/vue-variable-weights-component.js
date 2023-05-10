//vue variable weights edit component
Vue.component('variable-weights-component', {
    props:['value','variables'],
    data: function () {    
        return {
            show_weights_dialog:false
        }
    },
    watch: {
    },
    
    mounted: function () {   

    },
    computed: {
        Variables(){
            return variables;
        },
        var_wgt_id:
        {
            get(){
                return this.value;
            },
            set(val){
                this.$emit('input', val);
            }
        },
        VariablesForWeight(){
            let variables = [];
         
             for (var variable in this.Variables){
                 if (this.Variables[variable].uid==this.var_wgt_id){
                     variables.push(this.Variables[variable]);
                 }
             }
             
             return variables;
         }
    },
    methods:{
        OnWeightVariableSelection: function(e)
        {
            this.var_wgt_id=e;
        },
        RemoveWeightVariable: function(){
            this.var_wgt_id='';            
        }        
    },  
    template: `
            <div class="variable-weights-component">

                <dialog-weight-variable-selection 
                    :variables="Variables" 
                    v-model="show_weights_dialog"
                    @selected="OnWeightVariableSelection"
                ></dialog-weight-variable-selection>

                        
                <div>
                    <table class="table table-sm table-bordered" v-if="var_wgt_id">
                        <tr>
                            <th>Name</th>
                            <th>Label</th>
                            <td><button class="btn btn-sm btn-xs btn-link" @click="RemoveWeightVariable">Remove</button></td>
                        </tr>
                        <tr v-for="var_ in VariablesForWeight" :key="var_.uid">
                            <td>{{var_.name}}</td>
                            <td>{{var_.labl}}</td>
                            <td>
                                <button class="btn btn-sm btn-xs btn-link" @click="RemoveWeightVariable">Remove</button>
                                <button class="btn btn-sm btn-xs btn-link" @click="show_weights_dialog=true">Change</button>
                            </td>
                        </tr>
                        <tr v-if="VariablesForWeight.length<1">
                            <td>{{var_wgt_id}}</td>
                            <td>NA</td>
                            <td>
                                <button class="btn btn-sm btn-xs btn-link" @click="RemoveWeightVariable">Remove</button>
                                <button class="btn btn-sm btn-xs btn-link" @click="show_weights_dialog=true">Change</button>
                            </td>
                        </tr>
                    </table>
                    <div v-else>
                        <div class="border p-2 m-3 text-center " ><button class="btn btn-sm btn-xs btn-link" @click="show_weights_dialog=true">Select variable</button></div>
                    </div>
                </div>

            </div>  `    
});

