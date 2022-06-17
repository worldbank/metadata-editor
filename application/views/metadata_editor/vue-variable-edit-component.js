///variable edit form
Vue.component('variable-edit', {
    props:['value','index_key'],
    data: function () {    
        return {
            variable: this.value,
            //variable:{},
            form_local:{},
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
            }],
            /*variable_template:{
                "title":"Variable",
                "key":"variable",
                "items": [                
                    {
                        "key": "variable.name",
                        "title": "Name",
                        "type": "text",
                        "class": "required",
                        "required": true,
                        "help_text": "Variable name",
                        "rules":"required|max:80"
                    },
                    {
                        "key": "variable.labl",
                        "title": "Label",
                        "type": "text",
                        "class": "required",
                        "required": true,
                        "help_text": "Variable label",
                        "rules":"required|max:300"
                    },
                    {
                        "key": "variable.var_imputation",
                        "title": "Imputation",
                        "type": "text"
                    },
                    {
                        "key": "variable.var_security",
                        "title": "Security",
                        "type": "text"
                    },
                    {
                        "key": "variable.var_resp_unit",
                        "title": "Responsible unit",
                        "type": "text"
                    },
                    {
                        "key": "variable.var_intrvl",
                        "title": "Interval",
                        "type": "dropdown",
                        "class": "recommended",
                        "enum": {
                            "contin": "Continuous",
                            "other": "Other value"
                        }
                    },
                    {
                        "key": "variable.var_analysis_unit",
                        "title": "Analysis unit",
                        "type": "textarea"
                    }
                ]
            }*/
            
        }        
    },
    watch: { 
    },    
    created: function () {
       /*if (this.variable_.vid){
        this.loadData();
       }else{
           this.variable= Object.assign({}, this.variable_);
       }*/
    },
    computed: {
    },
    methods: {
        /*loadData: function() {   
            vm=this;
            let url=CI.base_url + '/api/datasets/variable/'+this.variable_.sid + '/'+ this.variable_.vid+'?id_format=id';
            axios.get(url)
            .then(function (response) {
                console.log(response);
                vm.variable=[];
                if(response.data.variable.metadata){
                    vm.variable=response.data.variable.metadata;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        }  ,        
        saveForm: function (){    
            this.variable_ = Object.assign({}, this.variable_, this.variable);
            this.$emit('input', this.variable);
            //this.$emit("exit-edit", true);
        },
        cancelForm: function (){
            this.form_local = Object.assign({}, this.field_data);
            this.$emit("exit-edit", false);
        } 
        */
    },
    template: `
        <div class="variable-edit-component">            
        <template>
            <v-tabs>
                <v-tab href="#statistics">Statistics</v-tab>
                <v-tab href="#weights">Weights</v-tab>
                <v-tab href="#documentation">Documentation</v-tab>
                <v-tab href="#json">JSON</v-tab>

                <v-tab-item value="statistics">
                
                    <div style="max-height:400px;overflow-y: scroll;padding:10px;font-size:smaller;">

                    <strong>Summary</strong>
                    <table class="table table-sm">
                        <tr>
                            <th>Value</th>
                            <th>Label</th>
                            <th>Cases</th>
                            <th>Weighted</th>
                        </tr>
                        <tr v-for="catgry in variable.var_catgry">
                            <td>{{catgry.value}}</td>
                            <td>{{catgry.labl}}</td>
                            
                            <!--non-wgt values -->
                            <td>
                            <template v-for="stat in catgry.stats">
                                <template v-if="stat.wgtd!='wgtd'">{{stat.value}}</template>
                            </template>
                            </td>

                            <!--wgt values -->
                            <template v-for="stat in catgry.stats">
                                <td v-if="stat.wgtd=='wgtd'">{{stat.value}}</td>
                            </template>                                
                        </tr>
                    </table>

                    <br/>

                    <table>
                    <template v-for="sumstat in variable.var_sumstat">                            
                            <tr>
                                <td><strong>{{sumstat.type}} <template v-if="sumstat.wgtd=='wgtd'"> (weighted)</template></strong></td>
                                <td>{{sumstat.value}}</td>
                            </tr>                            
                    </template>
                    </table>
                    
                    </div>
                
                </v-tab-item>
                <v-tab-item value="weights">
                    weights
                </v-tab-item>
                <v-tab-item value="json">
                    <pre>{{variable}}</pre>
                </v-tab-item>
                <v-tab-item value="documentation">

                    <div style="max-height:400px;overflow-y: scroll;padding:10px;">
                
                        <div class="form-group form-field">
                        <label>Name</label> 
                        <span><input type="text" class="form-control form-control-sm" v-model="variable.name"/></span> 
                        </div>
            
                        <div class="form-group form-field">
                            <label>Label</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.labl"/></span> 
                        </div>                
                        
                        <div class="form-group form-field">
                            <label>Concept</label> 
                            <table-component v-model="variable.var_concept" :columns="concept_columns"/>
                        </div>

                        <div class="mb-2"><strong class="text-secondary mb-2">Description</strong></div>

                        <div class="form-group form-field">
                            <label>Definition</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_txt"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>Universe</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_universe"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>Source of information</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_resp_unit"/></span> 
                        </div>

                        <div class="mb-2"><strong class="text-secondary mb-2">Question</strong></div>

                        <div class="form-group form-field">
                            <label>Pre-Question text</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_qstn_preqtxt"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>Literal question</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_qstn_qstnlit"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>Post-Question text</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_qstn_postqtxt"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>Interviewer instructions</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_qstn_ivuinstr"/></span> 
                        </div>

                        <div class="mb-2"><strong class="text-secondary mb-2">Imputation and Derivation</strong></div>

                        <div class="form-group form-field">
                            <label>Imputation</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_imputation"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>Recoding and derivation</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.var_codinstr"/></span> 
                        </div>

                                
                    </div>

                </v-tab-item>
            </v-tabs>
        </template>

        
        
        </div>          
        `
});

