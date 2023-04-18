///variable edit form
Vue.component('variable-edit', {
    props:['variable','index_key','multi_key'],
    data: function () {    
        return {
            drawer: true,       
            drawer_mini: true,
            //variable: this.value,            
            //variable:{},
            form_local:{},
            show_weights_dialog:false,
            sum_stats_options:
            {
                'wgt':1,
                'freq':1,
                'missing':1,
                'vald':1,
                'min':1,
                'max':1,
                'mean':1,
                'mean_wgt':1,
                'stdev':1,
                'stdev_wgt':1
            },
            concept_columns:[
                {
                    "key": "title",
                    "title": "Title",
                    "type": "text"
                },
                {
                    "key": "vocab",
                    "title": "Vocabulary",
                    "type": "text"
                },
                {
                    "key": "uri",
                    "title": "Vocabulary URI",
                    "type": "text"
                },
            ],
            catgry_columns:[
            {
                "key": "value",
                "title": "Value",
                "type": "text"
            },
            {
                "key": "labl",
                "title": "Label",
                "type": "text"
            },
            {
                "key": "stats",
                "title": "Stats",
                "type": "table",
                "props":[
                    {
                        "key": "type",
                        "title": "Type",
                        "type": "text"
                    },
                    {
                        "key": "value",
                        "title": "Value",
                        "type": "text"
                    },
                    {
                        "key": "wgtd",
                        "title": "Weighted?",
                        "type": "text"
                    },
                ]
            }]
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
        Variable:{
            get(){
                if (!this.variable.sum_stats_options){              
                    //Vue.set(this.variable, 'sum_stats_options', this.sum_stats_options);
                    this.variable.sum_stats_options=this.sum_stats_options;
                }
                if(!this.variable.sum_stats_options.min){        
                    //Vue.set(this.variable, 'sum_stats_options', this.sum_stats_options);
                    this.variable.sum_stats_options=this.sum_stats_options;
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
        variable_template: function(){
            return this.$store.getters["getVariableDocumentationTemplate"];
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
        VariablesForWeight(){
           let variables = [];
        
            for (var variable in this.Variables){
                if (this.Variables[variable].uid==this.variable.var_wgt_id){
                    variables.push(this.Variables[variable]);
                }
            }
            
            return variables;
        },
        //combine categories and frequencies values
        variableCategoriesAndFrequencies()
        {
            let categories=JSON.parse(JSON.stringify(this.Variable.var_catgry));

            if (!this.Variable.var_catgry_labels){
                return categories;
            }

            for (var i=0;i<categories.length;i++){
                console.log("adding label",categories[i].value);
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
        }
    },
    methods: {
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
                return 'Valid';
            }
            
            if (code=='invd'){
                return 'Invalid';
            }

            return code;
        },
        VariablePercent(variable, catgry){
           if (variable && variable.var_valrng && variable.var_valrng.range && variable.var_valrng.range.count){
                let catgry_freq=this.VariableCategoryFrequency(catgry);
                return (catgry_freq/variable.var_valrng.range.count)*100;                
           }
           return 0;           
        },
        VariableCategoryFrequency(catgry){
            if (catgry && catgry.stats){
                for(i=0;i<catgry.stats.length;i++){
                    if (catgry.stats[i].type=='freq'){
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
        OnWeightVariableSelection: function(e)
        {
            this.variable.var_wgt_id=e;
            this.$emit('input', JSON.parse(JSON.stringify(this.variable)));
        },
        RemoveWeightVariable: function(){
            this.variable.var_wgt_id='';
            this.$emit('input', JSON.parse(JSON.stringify(this.variable)));
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
        }

    },
    template: `
        <div class="variable-edit-component pb-5">
        <template>
            <v-tabs v-model="active_tab">
                <v-tab key="statistics" href="#statistics">Statistics</v-tab>
                <v-tab key="weights" href="#weights">
                    Weights <span v-if="variable.var_wgt_id"><v-icon style="color:green;">mdi-circle-medium</v-icon></span></v-tab>
                <v-tab key="documentation" href="#documentation">Documentation</v-tab>
                <v-tab key="json" href="#json">JSON</v-tab>

                <v-tab-item key="statistics" value="statistics">
                
                    <!-- statistics tab -->
                    <div style="overflow:auto;padding:10px;font-size:smaller;">

                    <div class="row no-gutters">
                        <div class="col-md-3">
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.wgt" value="1" /> Weighted statistics</label></div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.freq" value="1" /> Frequencies</label></div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.missing" value="1"/> List missings</label></div>
                            <div class="mt-3 mb-2 border-bottom w-50 ">Summary statistics:</div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.vald" value="1"/> Valid</label></div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.min" value="1"/> Min</label></div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.max" value="1"/> Max</label></div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.mean" value="1"/> Mean</label></div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.mean_wgt" value="1"/> Weighted mean</label></div>
                            <div><label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.stdev" value="1"/> StdDev</label></div>
                            <div>
                                <label class="text-normal text-small"><input type="checkbox" v-model="Variable.sum_stats_options.stdev_wgt" value="1"/> Weighted StdDev</label>
                            </div>                            
                        </div>
                        <div class="col-md-9">

                            <div v-if="variable.var_catgry && variable.var_catgry.length>0 && variable.sum_stats_options.freq==true">
                            <h5>Frequencies</h5>
                            
                            <table class="table table-sm">
                                <tr>
                                    <th>Value</th>
                                    <th>Label</th>
                                    <th>Cases</th>
                                    <th>Weighted</th>
                                    <th>Percent</th>
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
                                    <td>
                                    <template v-for="stat in catgry.stats">
                                        <template v-if="stat.wgtd=='wgtd'">{{stat.value}}</template>
                                    </template>                                
                                    </td>
                                    <td>
                                        <div v-if="catgry.is_missing==1 || VariableIsMissing(catgry.value)">Missing</div>
                                        <v-progress-linear v-else
                                            v-model="VariablePercent(variable, catgry)"
                                            color="#FFCC80"
                                            height="15"
                                            >
                                            <strong>{{  Math.ceil(VariablePercent(variable, catgry)) }}%</strong>
                                        </v-progress-linear>
                                    </td>
                                </tr>
                                <tr v-if="variableStatsInValidCount(variable)>0 && Variable.sum_stats_options.missing">
                                    <td>Sysmiss</td>
                                    <td></td>                                    
                                    <td>{{variableStatsInValidCount(variable)}}</td>
                                    <td></td>
                                    <td>Missing</td>
                                </tr>
                            </table>
                            </div>

                            <div v-if="variable.var_sumstat" class="pb-4">
                            <h5 class="border-bottom">Summary statistics</h5>                            
                            <table>
                                <template v-for="sumstat in variable.var_sumstat">                            
                                
                                    <tr v-if="variableSumStatEnabled(sumstat.type)">
                                        <td style="width:150px;">
                                            <strong>{{sumStatCodeToLabel(sumstat.type)}} <template v-if="sumstat.wgtd=='wgtd'"> (weighted)</template></strong></td>
                                        <td>{{sumstat.value}}</td>
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

                        <dialog-weight-variable-selection 
                            :variables="Variables" 
                            v-model="show_weights_dialog"
                            @selected="OnWeightVariableSelection"
                        ></dialog-weight-variable-selection>
                        <div v-if="variable.var_wgt==1">
                            <div class="border p-2 m-3 text-center text-danger">Cannot add weights to a weight variable</div>
                        </div>
                        <div v-else>
                            <table class="table table-sm table-bordered" v-if="variable.var_wgt_id">
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
                            </table>
                            <div v-else>
                                <div class="border p-2 m-3 text-center " ><button class="btn btn-sm btn-xs btn-link" @click="show_weights_dialog=true">Select variable</button></div>
                            </div>
                        </div>

                    </div>
                </v-tab-item>
                <v-tab-item key="json" value="json">
                    <pre>{{variable}}</pre>
                </v-tab-item>
                <v-tab-item key="documentation" value="documentation">

                    <div xstyle="overflow-y: scroll;padding:10px;margin-bottom:50px;" class="mb-5">
                    <div class="row mt-1">
                        <div class="col-auto">
                        <template>                        
                                <v-navigation-drawer
                                    v-model="drawer"
                                    :mini-variant.sync="drawer_mini" permanent bottom>
                                    <v-list-item class="px-2">
                                        <v-app-bar-nav-icon></v-app-bar-nav-icon>
                                            <v-list-item-title>Settings</v-list-item-title>                            
                                            <v-btn icon @click.stop="drawer_mini = !drawer_mini">
                                                <v-icon>mdi-chevron-left</v-icon>
                                            </v-btn>
                                    </v-list-item>                            
                                    <v-divider></v-divider>                            
                                    <v-list dense v-if="!drawer_mini">
                                    <v-list-item
                                        v-for="section in variable_template.items" :key="section.key" link>
                                        <v-list-item-content>                            
                                        <v-list-item-title>{{ section.title }}</v-list-item-title>
                                        
                                        <div v-for="subitem in section.items" :key="subitem.key">
                                            <input type="checkbox" v-model="subitem.enabled"/> {{subitem.title}}
                                        </div>
                                        </v-list-item-content>
                                    </v-list-item>
                                    </v-list>
                                </v-navigation-drawer>
                                
                            </template>
                            </div>

                    <div class="col">
                        <template v-for="section in variable_template.items">
                            <div class="mb-2" v-if="sectionEnabled(section)"><strong class="text-secondary mb-2">{{section.title}}</strong></div>
                            <template v-for="var_field in section.items">

                                <div v-if="var_field.enabled" class="form-group form-field">
                                    <label>{{var_field.title}}</label> 
                                    <span v-if="var_field.type!='array'"><textarea class="form-control form-control-sm" v-model="variable[var_field.key]"/></span>
                                    <table-component v-if="var_field.type=='array'" v-model="variable[var_field.key]" :columns="var_field.props"/>
                                </div>

                            </template>
                        </template>
                    </div>
                    </div>                        
                    </div>

                </v-tab-item>
            </v-tabs>
        </template>

        </div>          
        `
});

