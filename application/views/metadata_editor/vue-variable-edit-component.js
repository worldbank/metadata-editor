///variable edit form
Vue.component('variable-edit', {
    props:['value','index_key'],
    data: function () {    
        return {
            drawer: true,       
            drawer_mini: true,
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
            variable_template:{
                "title":"Variable",
                "key":"variable",
                "items":[
                    {
                        "type": "section",
                        "key": "variable_description",
                        "title": "Description",
                        "expanded": true,
                        "items": [
                            {
                                "key": "var_txt",
                                "title": "Definition",
                                "type": "textarea",
                                "class": "required",
                                "required": false,
                                "help_text": "Definition help text",
                                "rules":"max:1000",
                                "enabled":true
                            },
                            {
                                "key": "var_universe",
                                "title": "Universe",
                                "type": "text",
                                "class": "required",
                                "required": false,
                                "help_text": "Universe help text",
                                "rules":"max:3000",
                                "enabled": true
                            },                            
                            {
                                "key": "var_concept",
                                "title": "Concepts",
                                "type": "array",
                                "class": "required",
                                "props": {
                                    "title": {
                                        "key": "title",
                                        "title": "Title",
                                        "type": "text",
                                        "rules":"required",
                                        "name": "Concept title"
                                    },
                                    "vocab": {
                                        "key": "vocab",
                                        "title": "Vocabulary",
                                        "type": "text"
                                    },
                                    "uri": {
                                        "key": "uri",
                                        "title": "Vocabulary URI",
                                        "type": "text"
                                    }
                                }
                            },
                        ]
                    },
                    {
                        "type": "section",
                        "key": "variable_question",
                        "title": "Question",
                        "expanded": true,
                        "items": [
                            {
                                "key": "var_qstn_preqtxt",
                                "title": "Pre-Question text",
                                "type": "text"
                            },
                            {
                                "key": "var_qstn_qstnlit",
                                "title": "Literal question",
                                "type": "text"
                            },
                            {
                                "key": "var_qstn_postqtxt",
                                "title": "Post-Question text",
                                "type": "text"
                            },
                            {
                                "key": "variable.var_qstn_ivuinstr",
                                "title": "Interviewer instructions",
                                "type": "text"
                            }
                        ]
                    },
                    {
                        "type": "section",
                        "key": "variable_imputation",
                        "title": "Imputation and derivation",
                        "expanded": true,
                        "items": [
                            {
                                "key": "variable.var_resp_unit",
                                "title": "Source of information",
                                "type": "textarea"
                            },                            
                            {
                                "key": "variable.var_imputation",
                                "title": "Imputation",
                                "type": "text"
                            },
                            {
                                "key": "variable.var_codinstr",
                                "title": "Recoding and derivation",
                                "type": "text"
                            }
                        ]
                    }                    
                ]
            }
            
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
       if (this.variable.var_concept==undefined){
           this.variable.var_concept=[{}];
       }
    },
    computed: {
        active_tab: {
            get() {
              return this.$store.getters["getVariablesActiveTab"];
            },
            set(newValue) {
              return this.$store.commit("variables_active_tab", newValue);
            },
          },
    },
    methods: {
        sectionEnabled: function(section){
            console.log("section",section);
            
            if (section.items==undefined){
                return false;
            }

            for(i=0;i<section.items.length;i++){
                if (section.items[i].enabled){
                    return true;
                }
            }
            return false;
        }
    },
    template: `
        <div class="variable-edit-component">            
        <template>
            <v-tabs v-model="active_tab">
                <v-tab key="statistics" href="#statistics">Statistics</v-tab>
                <v-tab key="weights" href="#weights">Weights</v-tab>
                <v-tab key="documentation" href="#documentation">Documentation</v-tab>
                <v-tab key="json" href="#json">JSON</v-tab>

                <v-tab-item key="statistics" value="statistics">
                
                    <div style="max-height:400px;overflow-y: scroll;padding:10px;font-size:smaller;">

                    <strong>Summary</strong>
                    <table class="table table-sm" v-if="variable.var_catgry">
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
                <v-tab-item key="weights" value="weights">
                    weights
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
                        :mini-variant.sync="drawer_mini"
                        permanent                        
                        bottom
                        
                      >
                        <v-list-item class="px-2">
                        <v-app-bar-nav-icon></v-app-bar-nav-icon>

                  
                          <v-list-item-title>Settings</v-list-item-title>
                  
                          <v-btn
                            icon
                            @click.stop="drawer_mini = !drawer_mini"
                          >
                            <v-icon>mdi-chevron-left</v-icon>
                          </v-btn>
                        </v-list-item>
                  
                        <v-divider></v-divider>
                  
                        <v-list dense v-if="!drawer_mini">
                          <v-list-item
                            v-for="section in variable_template.items"
                            :key="section.key"
                            link
                          >
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



                    <?php /*
                
                        <div class="form-group form-field">
                            <label>Variable ID</label> 
                            <span><input disabled="disabled" type="text" class="form-control form-control-sm" v-model="variable.vid"/></span> 
                        </div>
                        
                        <div class="form-group form-field">
                            <label>Name</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.name"/></span> 
                        </div>
            
                        <div class="form-group form-field">
                            <label>Label</label> 
                            <span><input type="text" class="form-control form-control-sm" v-model="variable.labl"/></span> 
                        </div>
                        */ ?>                
                        
                        <?php /*
                        <div class="mb-2"><strong class="text-secondary mb-2">Description</strong></div>

                        <div class="form-group form-field">
                            <label>Definition</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_txt"/></span>
                        </div>

                        <div class="form-group form-field">
                            <label>Universe</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_universe"/></span>
                        </div>

                        <div class="form-group form-field">
                            <label>Source of information</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_resp_unit"/></span>
                        </div>

                        <div class="mb-2"><strong class="text-secondary mb-2">Question</strong></div>

                        <div class="form-group form-field">
                            <label>Pre-Question text</label>
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_qstn_preqtxt"/></span>
                        </div>

                        <div class="form-group form-field">
                            <label>Literal question</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_qstn_qstnlit"/></span>
                        </div>

                        <div class="form-group form-field">
                            <label>Post-Question text</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_qstn_postqtxt"/></span>  
                        </div>

                        <div class="form-group form-field">
                            <label>Interviewer instructions</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_qstn_ivuinstr"/></span> 
                        </div>

                        <div class="mb-2"><strong class="text-secondary mb-2">Imputation and Derivation</strong></div>

                        <div class="form-group form-field">
                            <label>Imputation</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_imputation"/></span> 
                        </div>

                        <div class="form-group form-field">
                            <label>Recoding and derivation</label> 
                            <span><textarea class="form-control form-control-sm" v-model="variable.var_codinstr"/></span>                            
                        </div>

                        <div class="form-group form-field">
                            <label>Concept</label> 
                            <table-component v-model="variable.var_concept" :columns="concept_columns"/>
                        </div>  
                        
                        */ ?>
                        
                        
                        </div>
                        </div>
                        
                    </div>

                </v-tab-item>
            </v-tabs>
        </template>

        
        
        </div>          
        `
});

