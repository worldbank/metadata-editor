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
        variable_template: function(){
            return this.$store.getters["getVariableDocumentationTemplate"];
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
        }
    },
    template: `
        <div class="variable-edit-component pb-5">
        <template>
            <v-tabs v-model="active_tab">
                <v-tab key="statistics" href="#statistics">Statistics</v-tab>
                <v-tab key="weights" href="#weights">Weights</v-tab>
                <v-tab key="documentation" href="#documentation">Documentation</v-tab>
                <v-tab key="json" href="#json">JSON</v-tab>

                <v-tab-item key="statistics" value="statistics">
                
                    <div style="max-height:400px;overflow-y: scroll;padding:10px;font-size:smaller;">

                    <div v-if="variable.var_catgry.length>0 && variable.var_intrvl=='discrete'">
                    <h5>Frequencies</h5>
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
                            <td>
                            <template v-for="stat in catgry.stats">
                                <template v-if="stat.wgtd=='wgtd'">{{stat.value}}</template>
                            </template>                                
                            </td>
                        </tr>
                    </table>
                    </div>

                    <br/>

                    <div v-if="variable.var_sumstat" class="pb-4">
                    <h5 class="border-bottom">Summary statistics</h5>
                    <table>
                        <template v-for="sumstat in variable.var_sumstat">                            
                            <tr>
                                <td style="width:150px;"><strong>{{sumStatCodeToLabel(sumstat.type)}} <template v-if="sumstat.wgtd=='wgtd'"> (weighted)</template></strong></td>
                                <td>{{sumstat.value}}</td>
                            </tr>                            
                        </template>
                    </table>
                    </div>
                    
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


                       
                        
                        
                        </div>
                        </div>
                        
                    </div>

                </v-tab-item>
            </v-tabs>
        </template>

        
        
        </div>          
        `
});

