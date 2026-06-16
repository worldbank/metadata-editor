///variable edit form
Vue.component('variable-edit', {
    props:['variable','index_key','multi_key'],
    data: function () {    
        return {
            drawer: true,       
            drawer_mini: true,
            form_local:{},
            maxCategories: 500, // Maximum number of categories to display in frequencies table
            sum_stats_options:
            {
                'wgt':true,
                'freq':true,
                'missing':true,
                'vald':true,
                'invd':true,
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
        'variable.sum_stats_options': {
            handler(newVal, oldVal) {
                // Ensure reactivity when sum_stats_options changes
                if (newVal && typeof newVal === 'object') {
                    this.$forceUpdate();
                }
            },
            deep: true
        }
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

        var _wid = this.variable.var_wgt_id;
        if (_wid === 0 || _wid === '0' || _wid === null || _wid === undefined || _wid === '' || !(Number(_wid) > 0)) {
            Vue.set(this.variable, 'var_wgt_id', '');
        }

        if (this.variable.var_wgt && Array.isArray(this.variable.var_wgt_id)){
            this.variable.var_wgt_id=this.variable.var_wgt_id[0];
        }

        // Initialize sum_stats_options properly with Vue.set for reactivity
        if (!this.variable.sum_stats_options){
            Vue.set(this.variable, 'sum_stats_options', JSON.parse(JSON.stringify(this.sum_stats_options)));
        } else {
            // Ensure all required properties exist
            const defaultOptions = JSON.parse(JSON.stringify(this.sum_stats_options));
            for (const key in defaultOptions) {
                if (!(key in this.variable.sum_stats_options)) {
                    Vue.set(this.variable.sum_stats_options, key, defaultOptions[key]);
                }
            }
        }
    },
    mounted: function() {
        // Ensure sum_stats_options are properly initialized after component is mounted
        this.ensureSumStatsOptions();
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
                if (!this.variable.sum_stats_options) {
                    Vue.set(this.variable, 'sum_stats_options', JSON.parse(JSON.stringify(this.sum_stats_options)));
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
            const allCategories = this.Variable.var_catgry || [];
            const maxCategories = this.maxCategories;
            
            // Early exit if no categories
            if (allCategories.length === 0) {
                return [];
            }

            // Limit to first 500 categories
            const limitedCount = Math.min(allCategories.length, maxCategories);
            const categoryValueSet = new Set();
            const categories = [];
            
            // Process first 500 categories and build value set
            for (var i = 0; i < limitedCount; i++) {
                const cat = allCategories[i];
                const value = cat.value;
                categoryValueSet.add(String(value));

                const row = {
                    value: value,
                    labl: cat.labl || null,
                    stats: cat.stats || null
                };
                if (cat.is_missing !== undefined && cat.is_missing !== null && cat.is_missing !== '') {
                    row.is_missing = cat.is_missing;
                }
                categories.push(row);
            }

            // If no labels, return early
            const allLabels = this.Variable.var_catgry_labels;
            if (!allLabels || allLabels.length === 0) {
                return categories;
            }

            // Build label map
            const labelMap = {};
            const labelCount = allLabels.length;
            
            // Single pass through labels - only store labels that match our first 500 categories            
            for (var i = 0; i < labelCount; i++) {
                const label = allLabels[i];
                const labelValueStr = String(label.value);

                if (categoryValueSet.has(labelValueStr) && label.labl) {
                    labelMap[label.value] = label.labl;
                }
            }

            // Update labels in categories using the lookup map
            for (var i = 0; i < categories.length; i++) {
                const catValue = categories[i].value;
                if (labelMap[catValue]) {
                    categories[i].labl = labelMap[catValue];
                }
            }

            // Add categories from labels that are not in the frequencies (only until we reach 500 total)
            if (categories.length < maxCategories) {
                for (var i = 0; i < allLabels.length && categories.length < maxCategories; i++) {
                    const label = allLabels[i];
                    const labelValueStr = String(label.value);
                    if (!categoryValueSet.has(labelValueStr)) {
                        categories.push({
                            value: label.value,
                            labl: label.labl || null
                        });
                        categoryValueSet.add(labelValueStr);
                    }
                }
            }

            return categories;
        },
        // Check if variable exceeds max categories (hide frequencies table)
        exceedsMaxCategories: function() {
            const categoriesCount = this.variable.var_catgry ? this.variable.var_catgry.length : 0;
            const labelsCount = this.variable.var_catgry_labels ? this.variable.var_catgry_labels.length : 0;
            return categoriesCount + labelsCount > this.maxCategories;
        },
        categoriesCount: function() {
            const categoriesCount = this.variable.var_catgry ? this.variable.var_catgry.length : 0;
            const labelsCount = this.variable.var_catgry_labels ? this.variable.var_catgry_labels.length : 0;
            return categoriesCount + labelsCount;
        },
        isWeighted(){
            var w = this.Variable['var_wgt_id'];
            return w !== undefined && w !== null && w !== '' && Number(w) > 0;
        },
        hasAssignedWeightVariable(){
            var w = this.variable.var_wgt_id;
            return w !== undefined && w !== null && w !== '' && Number(w) > 0;
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
            var idNum = (e === null || e === undefined || e === '') ? 0 : Number(e);
            if (idNum > 0 && !isNaN(idNum)) {
                if (this.variable.var_format && this.variable.var_format.type){
                    if (this.variable.var_format.type=='character'){
                        Vue.delete(this.variable, 'var_wgt_id');
                        alert(this.$t('character_variable_cannot_be_weighted', {variable_name: this.variable.name}));
                        return;
                    }
                }
                Vue.set(this.variable, 'var_wgt_id', e);
                Vue.set(this.variable, 'update_required', true);
            } else {
                Vue.delete(this.variable, 'var_wgt_id');
            }
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
            if (this.Variable.sum_stats_options && this.Variable.sum_stats_options[stat]){
                return this.Variable.sum_stats_options[stat];
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
            if (!this.Variable.sum_stats_options || !this.Variable.sum_stats_options.wgt){
                return false;
            }

            return value;
        },

        
        ensureSumStatsOptions: function() {
            // Ensure sum_stats_options exists and has all required properties
            if (!this.variable.sum_stats_options) {
                Vue.set(this.variable, 'sum_stats_options', JSON.parse(JSON.stringify(this.sum_stats_options)));
            } else {
                const defaultOptions = JSON.parse(JSON.stringify(this.sum_stats_options));
                for (const key in defaultOptions) {
                    if (!(key in this.variable.sum_stats_options)) {
                        Vue.set(this.variable.sum_stats_options, key, defaultOptions[key]);
                    }
                }
            }
        },
        
        onSumStatsOptionChange: function(option, value) {
            // Handle changes to sum_stats_options with proper reactivity
            Vue.set(this.Variable.sum_stats_options, option, value);
            
            // Special handling for weighted statistics
            if (option === 'wgt' && !value) {
                Vue.set(this.Variable.sum_stats_options, 'mean_wgt', false);
                Vue.set(this.Variable.sum_stats_options, 'stdev_wgt', false);
                if (Object.prototype.hasOwnProperty.call(this.variable, 'var_wgt_id')) {
                    Vue.delete(this.variable, 'var_wgt_id');
                }
            }
            
            // Flag refresh stats only when a change requires re-running the data API:
            // - freq turned from false to true (to get var_catgry again)
            // Display/export-only options (min, max, mean, stdev, vald, invd, missing, mean_wgt, stdev_wgt, wgt)
            // and freq turned to false do not require refresh.
            if (option === 'freq' && value === true) {
                Vue.set(this.variable, 'update_required', true);
            }
        }

    },
    template: `
        <div class="variable-edit-component pb-5">
        <template>
            <v-tabs v-model="active_tab">
                <v-tab key="statistics" href="#statistics">{{$t('statistics')}}</v-tab>
                <v-tab key="weights" href="#weights">
                    {{$t('weights')}} <span v-if="hasAssignedWeightVariable"><v-icon style="color:green;">mdi-circle-medium</v-icon></span></v-tab>
                <v-tab key="documentation" href="#documentation">{{$t('documentation')}}</v-tab>
                <v-tab key="json" href="#json">{{$t('json')}}</v-tab>

                <v-tab-item key="statistics" value="statistics">
                
                    <!-- statistics tab -->
                    <div style="overflow:auto;padding:10px;font-size:smaller;">

                    <div class="row no-gutters" v-if="Variable.sum_stats_options">
                        <div class="col-md-3 v-checkbox-rm-styles v-checkbox-summary-stats">
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('wgt', value)" v-model="Variable.sum_stats_options.wgt" :indeterminate="Variable.sum_stats_options.wgt==null" :label="$t('weighted_statistics')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('freq', value)" v-model="Variable.sum_stats_options.freq" :indeterminate="Variable.sum_stats_options.freq==null" :label="$t('frequencies')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('missing', value)" v-model="Variable.sum_stats_options.missing" :indeterminate="Variable.sum_stats_options.missing==null" :label="$t('list_missings')"></v-checkbox></div>
                            <div class="mt-3 mb-2 border-bottom w-50 ">{{$t('summary_stats')}}:</div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('vald', value)" v-model="Variable.sum_stats_options.vald" :indeterminate="Variable.sum_stats_options.vald==null"  :label="$t('valid')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('invd', value)" v-model="Variable.sum_stats_options.invd" :indeterminate="Variable.sum_stats_options.invd==null"  :label="$t('invalid')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('min', value)" v-model="Variable.sum_stats_options.min" :indeterminate="Variable.sum_stats_options.min==null"  :label="$t('min')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('max', value)" v-model="Variable.sum_stats_options.max" :indeterminate="Variable.sum_stats_options.max==null" :label="$t('max')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('mean', value)" v-model="Variable.sum_stats_options.mean" :indeterminate="Variable.sum_stats_options.mean==null" :label="$t('mean')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('mean_wgt', value)" v-model="Variable.sum_stats_options.mean_wgt" :indeterminate="Variable.sum_stats_options.mean_wgt==null" :label="$t('weighted_mean')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('stdev', value)" v-model="Variable.sum_stats_options.stdev" :indeterminate="Variable.sum_stats_options.stdev==null" :label="$t('stddev')"></v-checkbox></div>
                            <div><v-checkbox @change="(value) => onSumStatsOptionChange('stdev_wgt', value)" v-model="Variable.sum_stats_options.stdev_wgt" :indeterminate="Variable.sum_stats_options.stdev_wgt==null" :label="$t('weighted_stddev')"></v-checkbox></div>                            
                        </div>
                        <div class="col-md-9">

                            <div v-if="variable.var_catgry && variable.var_catgry.length>0 && Variable.sum_stats_options && Variable.sum_stats_options.freq==true">
                            <h5>{{$t('frequencies')}}</h5>
                            
                            <!-- Show message if exceeds max categories (hide table) -->
                            <div v-if="exceedsMaxCategories" class="alert alert-warning mt-3 mb-3">
                                <div class="row no-gutters align-items-center">
                                    <div class="col-auto pr-3">
                                        <v-icon x-large aria-hidden="false" class="var-icon" style="color:orange">mdi-alert</v-icon>
                                    </div>
                                    <div class="col">
                                        <span style="font-size:1.2em;">{{$t('too_many_categories_frequencies_message')}}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Show table only if 500 or fewer categories -->
                            <table v-else class="table table-sm variable-frequencies">
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

                                        <div class="progress" v-if="isWeighted==true && Variable.sum_stats_options && Variable.sum_stats_options.wgt">
                                            <div class="progress-bar progress-bar bg-warning" role="progressbar" :style="'width: ' + VariablePercentWeighted(variable, catgry) + '%'" :aria-valuenow="VariablePercentWeighted(variable, catgry)"  aria-valuemin="0" aria-valuemax="100"></div>
                                            <span class="progress-text">{{VariablePercentWeighted(variable, catgry)}}%</span>
                                        </div>
                                        
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="variableStatsInValidCount(variable)>0 && (Variable.sum_stats_options.missing || Variable.sum_stats_options.invd)">
                                    <td>{{$t('system_missing')}}</td>
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
                            :key="'vw-' + (variable.uid != null ? variable.uid : index_key)" 
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

