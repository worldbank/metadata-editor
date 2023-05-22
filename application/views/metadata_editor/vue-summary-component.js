/// Project summary page
Vue.component('summary-component', {
    data () {
        return {
          validation_errors: "",
          dialog_template:false,
          template_idx:-1,
          template_updating:false
        }
      },
    created: function(){      
        this.validateProject();
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNo(){
            return this.$store.state.idno;
        },
        ProjectTemplates()
        {
            return this.$store.state.templates;
        },
        ProjectTemplate()
        {
            return this.$store.state.formTemplate;
        },
        ProjectType(state){
            return this.$store.state.project_type;
        },
    },
    methods:{
        validateProject: function() {
            let vm=this;
            let url=CI.base_url + '/api/editor/validate/'+this.ProjectID;

            axios.get(url)
            .then(function (response) {
                if(response.data){                    
                    console.log("validation response",response);
                }
            })
            .catch(function (error) {
                console.log("validation errors",error);                
                vm.validation_errors=error.response.data;
            })
            .then(function () {
                console.log("request completed");
            });
        },
        UpdateTemplate: function(){
            this.template_updating=true;
            console.log("updating template", this.ProjectTemplates[this.template_idx]);
            let form_data={
                "template_uid":this.ProjectTemplates[this.template_idx]["uid"]
            };

            console.log("form_data",form_data);
            
            vm=this;            
            let url=CI.base_url + '/api/editor/options/'+ this.ProjectID;

            axios.post( url,
                form_data,{}
            ).then(function(response){
                console.log("template updated",response);
                vm.dialog_template=false;
                window.location.reload();
                return false;
                //router.push('/');
            })
            .catch(function(response){
                vm.errors=response;
            });
        }
    },     
    template: `
            <div class="summary-component mt-3">

                <div class="row" >
                    <div class="col-6" >
                        <div class="thumbnail-container border bg-white mb-3">
                            <project-thumbnail/>
                        </div>

                        <div class="template-selection-container border mb-3 p-3 bg-white">
                            <h5><v-icon style="font-size:25px;">mdi-alpha-t-box</v-icon> Template</h5>
                            <div class="border p-1">
                                <span class="float-right btn btn-link" @click="dialog_template=true">Switch template</span>
                                <strong>{{ProjectTemplate.name}}</strong> <br/>
                                {{ProjectTemplate.uid}}                                
                            </div>
                        </div>

                        <!-- template dialog -->
                        <template class="project-template">
                            <div class="text-center">
                                <v-dialog
                                v-model="dialog_template"
                                max-width="700px"
                                scrollable
                                >
                                <v-card >
                                    <v-card-title class="text-h5 grey lighten-2">
                                    Template {{template_idx}}
                                    </v-card-title>

                                    <v-card-text style="max-height:400px;">
                                    <div>

                                            <!-- list -->
                                            <template>
                                               
                                                    <v-list two-line>
                                                    <v-list-item-group
                                                        v-model="template_idx"
                                                        active-class="pink--text"                                                        
                                                    >
                                                        <template v-for="(item, index) in ProjectTemplates">
                                                        <v-list-item :key="item.uid">
                                                            <template v-slot:default="{ active }">
                                                            <v-list-item-content>
                                                                <v-list-item-title v-text="item.name"></v-list-item-title>

                                                                <v-list-item-subtitle
                                                                class="text--primary"
                                                                v-text="item.name"
                                                                ></v-list-item-subtitle>

                                                                <v-list-item-subtitle v-text="item.uid"></v-list-item-subtitle>
                                                            </v-list-item-content>

                                                            <v-list-item-action>
                                                                <v-list-item-action-text v-text="item.action"></v-list-item-action-text>

                                                                <v-icon
                                                                v-if="!active"
                                                                color="grey lighten-1"
                                                                >
                                                                mdi-check-outline
                                                                </v-icon>

                                                                <v-icon
                                                                v-else
                                                                color="yellow darken-3"
                                                                >
                                                                mdi-check-bold
                                                                </v-icon>
                                                            </v-list-item-action>
                                                            </template>
                                                        </v-list-item>

                                                        <v-divider
                                                            v-if="index < ProjectTemplates.length - 1"
                                                            :key="index"
                                                        ></v-divider>
                                                        </template>
                                                    </v-list-item-group>
                                                    </v-list>
                                                
                                                </template>
                                            <!-- end list -->                                    
                                            
                                            
                                        </div>
                                    </v-card-text>

                                    <v-divider></v-divider>

                                    <v-card-actions>
                                    <v-spacer></v-spacer>
                                    <v-btn
                                        color="primary"
                                        text
                                        @click="dialog_template = false"
                                    >
                                        Close
                                    </v-btn>
                                    <v-btn
                                        color="primary"
                                        text
                                        @click="UpdateTemplate"
                                        :disabled="template_idx==-1 || template_updating"
                                    >
                                        Apply
                                    </v-btn>
                                    </v-card-actions>
                                </v-card>
                                </v-dialog>
                            </div>
                            </template>
                        <!-- end template dialog -->


                        <div class="project-validation-container border mt-3 p-3 bg-white">
                            <h5>Project validation</h5>                            

                            <div class="validation-errors mt-2 border" v-if="validation_errors!=''" style="color:red;font-size:small;" >
                                <div class="border-bottom p-2 mb-2"><strong>Validation errors</strong></div>
                                <ul v-for="error in validation_errors.errors" class="mb-2 ml-3">
                                    <li><strong>{{error.message}}</strong><br/>
                                    Property: {{error.property}}
                                    </li>                                    
                                </ul>
                            </div>
                            <div class="mt-3 p-2 border" style="color:green" v-else>No issues found</div>
                        </div>


                    </div>
                    <div class="col-6">
                        <div class="files-container border bg-white p-3" v-if="ProjectType!=='timeseries-db'">
                            <summary-files></summary-files>
                        </div>
                    </div>
                </div>

            </div>          
            `    
});

