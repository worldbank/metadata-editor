///variable edit form
Vue.component('variable-edit', {
    props:['variable','index_key','multi_key'],
    data: function () {    
        return {
            drawer: true,       
            drawer_mini: true,
            form_local:{},
            sum_stats_options:
            {
                'wgt':true,
                'freq':true,
                'missing':true,
                'vald':true,
                'min':true,
                'max':true,
                'mean':true,
                'mean_wgt':true,
                'stdev':true,
                'stdev_wgt':true
            }            
        }        
    },
    watch: {          
        
    },    
    created: function () {
       if (this.variable.var_concept==undefined){
           this.variable.var_concept=[{}];
       }

       if (!this.variable.var_valrng){
            this.variable.var_valrng={
                "range":{
                    "min":null,
                    "max":null
                }
            };
        }

        if (!this.variable.var_invalrng){
            this.variable.var_invalrng={
                "values":[]
            };            
        }

        if (!this.variable.var_wgt_id){
            this.variable.var_wgt_id=''
        }

        if (this.variable.var_wgt && Array.isArray(this.variable.var_wgt_id)){
            this.variable.var_wgt_id=this.variable.var_wgt_id[0];
        }

        //Vue.set(this.variable, 'sum_stats_options', JSON.parse(JSON.stringify(this.sum_stats_options)));
        /*if (!this.variable.sum_stats_options){              
            Vue.set(this.variable, 'sum_stats_options', this.sum_stats_options);
        }
        if(!this.variable.sum_stats_options.min){        
            Vue.set(this.variable, 'sum_stats_options', this.sum_stats_options);
        }*/

        
        
    },
    computed: {
        localVariable: {
            get(){
                let variable_= { 
                    'variable':this.variable
                }
    
                return variable_;
            }
        },
        Variable:{
            get(){
                if (!this.variable.sum_stats_options){              
                    this.variable.sum_stats_options=JSON.parse(JSON.stringify(this.sum_stats_options));
                }
                return this.variable;
            },
            set(newValue){
                this.variable=newValue;                
            }
        },
        active_tab: {
            get() {
              return this.$store.getters["getVariablesActiveTab"];
            },
            set(newValue) {
              return this.$store.commit("variables_active_tab", newValue);
            },
          },        
                
        Variables(){
            variablesByFile= this.$store.getters.getVariablesAll;
            if (!variablesByFile){
                return [];
            }

            variables = variablesByFile[this.VariableFileID];
            return variables;
        },
        VariableFileID()
        {
            return this.Variable.fid;
        },
        
        //combine categories and frequencies values
        variableCategoriesAndFrequencies()
        {
            let categories=JSON.parse(JSON.stringify(this.Variable.var_catgry));

            if (!this.Variable.var_catgry_labels){
                return categories;
            }

            for (var i=0;i<categories.length;i++){
                categories[i].labl=this.VariableLabelByValue(categories[i].value);
            }

            //add categories that are not in the frequencies
            for (var i=0;i<this.Variable.var_catgry_labels.length;i++){
                let found=false;
                for (var j=0;j<categories.length;j++){
                    if (categories[j].value==this.Variable.var_catgry_labels[i].value){
                        found=true;
                        break;
                    }
                }

                if (!found){
                    categories.push({
                        value:this.Variable.var_catgry_labels[i].value,
                        labl:this.Variable.var_catgry_labels[i].labl
                    });
                }
            }

            return categories;
        },
        isWeighted(){
            if (this.Variable['var_wgt_id']){
                return true;
            }
            return false;
        },        
        WeightedValidRangeCount(){
            let count=0;
            if (!this.Variable.var_catgry){
                return 0;
            }

            for(i=0;i<this.Variable.var_catgry.length;i++){                
                let catgry=this.Variable.var_catgry[i]; 
                
                if (!catgry.stats){
                    continue;
                }

                //skip missing values
                /*if (catgry.value && this.VariableIsMissing(catgry.value)){
                    continue;
                }*/

                if (catgry.is_missing && catgry.is_missing=='1'){
                    continue;
                }

                for(j=0;j<catgry.stats.length;j++){
                    if (catgry.stats[j]['type'] && catgry.stats[j].type=='freq' && (catgry.stats[j]['wgtd'] && catgry.stats[j].wgtd=='wgtd') ){
                        if (catgry.stats[j]['value']){
                            count=count+ Number(catgry.stats[j].value);
                        }
                    }
                }
            }
            return count;
        },
        ValidRangeCount(){
            let count=0;
            if (!this.Variable.var_catgry){
                return 0;
            }

            for(i=0;i<this.Variable.var_catgry.length;i++){                
                let catgry=this.Variable.var_catgry[i]; 
                
                if (!catgry.stats){
                    continue;
                }

                if (catgry.is_missing && catgry.is_missing=='1'){
                    continue;
                }

                for(j=0;j<catgry.stats.length;j++){
                    if (catgry.stats[j]['wgtd'] && catgry.stats[j].wgtd=='wgtd'){
                        continue;
                    }
                    if (catgry.stats[j]['type'] && catgry.stats[j].type=='freq'  ){
                        if (catgry.stats[j]['value']){
                            count=count + Number(catgry.stats[j].value);
                        }
                    }
                }
            }            
            return count;
        },
    },
    methods: {

        update: function (key, value)
        {
            key=key.replace('variable.','');
            if (key.indexOf(".") !== -1 && this.variable[key]){
                delete this.variable[key];
            }
            _.set(this.variable,key,value);
        },
        updateSection: function (obj)
        {
            this.update(obj.key,obj.value);
        },

        localValue: function(key)
        {
            //remove 'variable.' from key
            key=key.replace('variable.','');
            return _.get(this.variable,key);
        },
        RoundNumbers(value, decimals){
            if (!value){
                return value;
            }

            value=Number(value);
            return value.toFixed(decimals);
        },
        OnVariableWeightChange(e){
            this.variable.update_required=true;
        },
        sectionEnabled: function(section){
            
            if (section.items==undefined){
                return false;
            }

            for(i=0;i<section.items.length;i++){
                if (section.items[i].enabled){
                    return true;
                }
            }
            return false;
        },
        sumStatCodeToLabel: function(code)
        {
            if (code=='vald'){
                return this.$t('valid');
            }
            
            if (code=='invd'){
                return this.$t('invalid');
            }

            return this.$t(code);
        },
        VariablePercent(variable, catgry){
           if (variable && variable.var_valrng && variable.var_valrng.range){
                let catgry_freq=this.VariableCategoryFrequency(catgry);
                let percent=(catgry_freq/this.ValidRangeCount)*100;

                if (!Number.isInteger(percent)){
                    return percent.toFixed(1);
                }
                return percent;
           }
           return 0;           
        },
        VariablePercentWeighted(variable, catgry){
            if (variable && variable.var_valrng && variable.var_valrng.range){
                 let catgry_freq=this.VariableCategoryFrequencyWeighted(catgry);
                 let percent= (catgry_freq/this.WeightedValidRangeCount)*100;                

                if (!Number.isInteger(percent)){
                    return percent.toFixed(1);
                }
                return percent;
            }
            return 0;           
         },        
        VariableCategoryFrequency(catgry){
            if (catgry && catgry.stats){
                for(i=0;i<catgry.stats.length;i++){
                    if (catgry.stats[i]['wgtd'] && catgry.stats[i].wgtd=='wgtd'){
                        continue;
                    }
                    if (catgry.stats[i].type=='freq'){
                        return catgry.stats[i].value;
                    }
                }
            }
            return 0;
        },
        VariableCategoryFrequencyWeighted(catgry){
            if (catgry && catgry.stats){
                for(i=0;i<catgry.stats.length;i++){
                    if (catgry.stats[i].type=='freq' && (catgry.stats[i]['wgtd'] && catgry.stats[i].wgtd=='wgtd') ){
                        return catgry.stats[i].value;
                    }
                }
            }
            return 0;
        },
        variableStatsInValidCount: function(variable){
            if (variable.var_sumstat){
                for(i=0;i<variable.var_sumstat.length;i++){
                    if (variable.var_sumstat[i].type=='invd'){
                        return variable.var_sumstat[i].value;
                    }
                }
            }
            return 0;
        },
        variableSumStatEnabled: function (stat){
            if (this.variable.sum_stats_options[stat]){
                return this.variable.sum_stats_options[stat];
            }
            return false;
        },
                
        VariableLabelByValue: function(value){
            if (this.variable.var_catgry_labels){
                for(i=0;i<this.variable.var_catgry_labels.length;i++){
                    if (this.variable.var_catgry_labels[i].value==value && this.variable.var_catgry_labels[i].labl){
                        return this.variable.var_catgry_labels[i].labl;
                    }
                }
            }
        },
        VariableIsMissing: function(value){
            if (this.variable.var_invalrng && this.variable.var_invalrng.values){
                for(i=0;i<this.variable.var_invalrng.values.length;i++){
                    if (this.variable.var_invalrng.values[i]==value){
                        return true;
                    }
                }
            }
            return false;
        },
        getWgtFieldValue: function(field,value)
        {
            if (!this.Variable.sum_stats_options.wgt){
                return false;
            }

            return value;
        },
        OnWghtdStatsChange: function(e)
        {
            if (!this.Variable.sum_stats_options.wgt){
                this.Variable.sum_stats_options.mean_wgt=false;
                this.Variable.sum_stats_options.stdev_wgt=false
            }
        }

    },
    template: `
        <div class="variable-edit-component pb-5">
        <template>
            <v-tabs v-model="active_tab">
                <v-tab key="statistics" href="#statistics">{{$t('statistics')}}</v-tab>
                <v-tab key="weights" href="#weights">
                    Weights <span v-if="variable.var_wgt_id"><v-icon style="color:green;">mdi-circle-medium</v-icon></span></v-tab>
                <v-tab key="documentation" href="#documentation">{{$t('documentation')}}</v-tab>
                <v-tab key="json" href="#json">JSON</v-tab>

                <v-tab-item key="statistics" value="statistics">
                
                    <!-- statistics tab -->
                    <div style="overflow:auto;padding:10px;font-size:smaller;">

                    <div class="row no-gutters">
                        <div class="col-md-3 v-checkbox-rm-styles v-checkbox-summary-stats">
                            <div><v-checkbox @change="OnWghtdStatsChange" v-model="Variable.sum_stats_options.wgt" :indeterminate="Variable.sum_stats_options.wgt==null" :label="$t('weighted_statistics')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.freq" :indeterminate="Variable.sum_stats_options.freq==null" :label="$t('frequencies')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.missing" :indeterminate="Variable.sum_stats_options.missing==null" :label="$t('list_missings')"></v-checkbox></div>
                            <div class="mt-3 mb-2 border-bottom w-50 ">{{$t('summary_stats')}}:</div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.vald" :indeterminate="Variable.sum_stats_options.vald==null"  :label="$t('valid')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.min" :indeterminate="Variable.sum_stats_options.min==null"  :label="$t('min')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.max" :indeterminate="Variable.sum_stats_options.max==null" :label="$t('max')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.mean" :indeterminate="Variable.sum_stats_options.mean==null" :label="$t('mean')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.mean_wgt" :indeterminate="Variable.sum_stats_options.mean_wgt==null" :label="$t('weighted_mean')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.stdev" :indeterminate="Variable.sum_stats_options.stdev==null" :label="$t('stddev')"></v-checkbox></div>
                            <div><v-checkbox v-model="Variable.sum_stats_options.stdev_wgt" :indeterminate="Variable.sum_stats_options.stdev_wgt==null" :label="$t('weighted_stddev')"></v-checkbox></div>                            
                        </div>
                        <div class="col-md-9">

                            <div v-if="variable.var_catgry && variable.var_catgry.length>0 && variable.sum_stats_options.freq==true">
                            <h5>{{$t('frequencies')}}</h5>
                            
                            <table class="table table-sm variable-frequencies">
                                <tr>
                                    <th>{{$t('value')}}</th>
                                    <th>{{$t('label')}}</th>
                                    <th>{{$t('cases')}}</th>
                                    <th v-if="isWeighted">{{$t('weighted')}}</th>
                                    <th>&nbsp;</th>
                                </tr>
                                <tr v-for="catgry in variableCategoriesAndFrequencies">
                                    <td>{{catgry.value}}</td>
                                    <td>{{catgry.labl}}</td>
                                    
                                    <!--non-wgt values -->
                                    <td>
                                    <template v-for="stat in catgry.stats">
                                        <template v-if="stat.wgtd!='wgtd'">{{stat.value}}</template>
                                    </template>
                                    </td>

                                    <!--wgt values -->
                                    <td v-if="isWeighted">
                                    <template v-for="stat in catgry.stats">
                                        <template v-if="stat.wgtd=='wgtd'">{{stat.value}}</template>
                                    </template>                                
                                    </td>
                                    <td style="min-width:80px;">
                                        <div v-if="catgry.is_missing==1 || VariableIsMissing(catgry.value)">{{$t('missing')}}</div>
                                        <div v-else>
                                        
                                        <div class="progress" v-if="isWeighted==false">
                                            <div class="progress-bar progress-bar bg-warning" role="progressbar" :style="'width: ' + VariablePercent(variable, catgry) + '%'" :aria-valuenow="VariablePercent(variable, catgry)"  aria-valuemin="0" aria-valuemax="100"></div>
                                            <span class="progress-text">{{VariablePercent(variable, catgry)}}%</span>
                                        </div>

                                        <div class="progress" v-if="isWeighted==true && Variable.sum_stats_options.wgt">
                                            <div class="progress-bar progress-bar bg-warning" role="progressbar" :style="'width: ' + VariablePercentWeighted(variable, catgry) + '%'" :aria-valuenow="VariablePercentWeighted(variable, catgry)"  aria-valuemin="0" aria-valuemax="100"></div>
                                            <span class="progress-text">{{VariablePercentWeighted(variable, catgry)}}%</span>
                                        </div>
                                        
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="variableStatsInValidCount(variable)>0 && Variable.sum_stats_options.missing">
                                    <td>{{$t('sysmiss')}}</td>
                                    <td></td>                                    
                                    <td>{{variableStatsInValidCount(variable)}}</td>
                                    <td v-if="isWeighted"></td>
                                    <td>{{$t('missing')}}</td>
                                </tr>
                            </table>
                            </div>

                            <div v-if="variable.var_sumstat" class="pb-4">
                            <h5 class="border-bottom">{{$t('summary_stats')}}</h5>                            
                            <table>
                                <template v-for="sumstat in variable.var_sumstat">                            
                                
                                    <tr v-if="variableSumStatEnabled(sumstat.type) && !sumstat.wgtd">
                                        <td style="width:150px;">
                                            <strong> {{sumStatCodeToLabel(sumstat.type)}}</strong>
                                        </td>
                                        <td>{{RoundNumbers(sumstat.value,2)}}</td>
                                    </tr>
                                    <tr v-else-if="variableSumStatEnabled(sumstat.type + '_wgt') && (sumstat.wgtd && sumstat.wgtd=='wgtd') ">
                                        <td style="width:150px;">
                                            <strong> {{sumStatCodeToLabel(sumstat.type)}} (weighted)</strong>
                                        </td>
                                        <td>{{RoundNumbers(sumstat.value,2)}}</td>
                                    </tr>
                                                               
                                </template>
                                <!-- range -->
                                <!--
                                <template v-for="(range_value, range_key) in variable.var_valrng.range">                            
                                    <tr>
                                        <td style="width:150px;"><strong>{{sumStatCodeToLabel(range_key)}}</strong></td>
                                        <td>{{range_value}}</td>
                                    </tr>                            
                                </template>
                                -->
                            </table>
                            </div>

                            </div>
                            </div>
                    
                    </div>
                    <!-- end statistics tab -->
                
                </v-tab-item>
                <v-tab-item key="weights" value="weights">
                    <div class="p-3">
                        <variable-weights-component
                            :key="variable.var_wgt_id" 
                            v-model="variable.var_wgt_id"
                            @input="OnVariableWeightChange"
                            :variables="Variables">
                        </variable-weights-component>
                    </div>
                </v-tab-item>
                <v-tab-item key="json" value="json">
                    <pre>{{variable}}</pre>
                </v-tab-item>
                <v-tab-item key="documentation" value="documentation">
                    
                    <variable-edit-documentation
                        :variable="variable"
                    ></variable-edit-documentation>    

                </v-tab-item>
            </v-tabs>
        </template>

        </div>          
        `
});

