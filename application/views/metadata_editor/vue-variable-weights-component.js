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
            return Array.isArray(this.variables) ? this.variables : [];
        },
        /** Only treat positive UIDs as an assigned weight (not 0 / "0"). */
        hasAssignedWeight(){
            var v = this.value;
            if (v === null || v === undefined || v === '') {
                return false;
            }
            var n = Number(v);
            return !isNaN(n) && n > 0;
        },
        var_wgt_id:
        {
            get(){
                if (!this.hasAssignedWeight){
                    return '';
                }
                return this.value;
            },
            set(val){
                this.$emit('input', val);
            }
        },
        VariablesForWeight(){
            let list = this.Variables;
            let out = [];
            if (!this.hasAssignedWeight) {
                return out;
            }
            for (var i = 0; i < list.length; i++) {
                if (list[i].uid == this.value) {
                    out.push(list[i]);
                }
            }
            return out;
         }
    },
    methods:{
        OnWeightVariableSelection: function(e)
        {
            var n = (e === null || e === undefined || e === '') ? 0 : Number(e);
            if (n > 0 && !isNaN(n)) {
                this.var_wgt_id = e;
            }
        },
        RemoveWeightVariable: function(){
            this.var_wgt_id='';            
        }        
    },  
    template: `
            <div class="variable-weights-component">

                <dialog-weight-variable-selection 
                    :key="var_wgt_id"
                    :variables="Variables" 
                    v-model="show_weights_dialog"
                    @selected="OnWeightVariableSelection"
                ></dialog-weight-variable-selection>

                        
                <div>
                    <table class="table table-sm table-bordered" v-if="hasAssignedWeight" :key="var_wgt_id || 'none'">
                        <tr>
                            <th>{{$t('name')}}</th>
                            <th>{{$t('label')}}</th>
                            <td></td>
                        </tr>
                        <tr v-for="var_ in VariablesForWeight" :key="var_.uid">
                            <td>{{var_.name}}</td>
                            <td>{{var_.labl}}</td>
                            <td>
                                <button class="btn btn-sm btn-xs btn-link" @click="RemoveWeightVariable">{{$t('remove')}}</button>
                                <button class="btn btn-sm btn-xs btn-link" @click="show_weights_dialog=true">{{$t('change')}}</button>
                            </td>
                        </tr>
                        <tr v-if="VariablesForWeight.length<1">
                            <td>{{value}}</td>
                            <td>{{$t('na')}}</td>
                            <td>
                                <button class="btn btn-sm btn-xs btn-link" @click="RemoveWeightVariable">{{$t('remove')}}</button>
                                <button class="btn btn-sm btn-xs btn-link" @click="show_weights_dialog=true">{{$t('change')}}</button>
                            </td>
                        </tr>
                    </table>
                    <div v-else>
                        <div class="border p-2 m-3 text-center " ><button class="btn btn-sm btn-xs btn-link" @click="show_weights_dialog=true">{{$t('select_weight_variable')}}</button></div>
                    </div>
                </div>

            </div>  `    
});

