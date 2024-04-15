/// Project summary page
Vue.component('summary-component', {
    data () {
        return {
          validation_errors: "",
          dialog_template:false,
          template_idx:-1,
          template_updating:false,
          project_edit_stats:{},
          project_disk_usage:{}
        }
      },
    created: function(){      
        this.validateProject();
        this.getProjectEditStats();
        this.getProjectDiskUsage();
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
        projectTemplateUID(){
            return this.$store.state.formTemplate.uid;
        },
        projectTemplateSelectedIndex: {
            get: function () {
                if (this.template_idx>-1){
                    return this.template_idx;
                }

                let templates=this.ProjectTemplates;
                let idx=-1;
                for(let i=0;i<templates.length;i++){
                    if(templates[i].uid==this.projectTemplateUID){
                        idx=i;
                        break;
                    }
                }                
                return idx;
            },
            set: function (newValue) {
                this.template_idx = newValue;
            }
        },
        ProjectType(state){
            return this.$store.state.project_type;
        },
    },
    methods:{
        momentDate(date) {
            //gmt to utc
            let utc_date = moment(date, "YYYY-MM-DD HH:mm:ss").toDate();
            return moment.utc(utc_date).format("YYYY-MM-DD")
          },
        getProjectEditStats: function() {
            let vm=this;
            let url=CI.base_url + '/api/editor/edit_stats/'+this.ProjectID;

            axios.get(url)
            .then(function (response) {
                if (response.data && response.data.info){
                    vm.project_edit_stats=response.data.info;
                }
            })
            .catch(function (error) {
                console.log("edit_stats_failed",error);
            });
        },
        getProjectDiskUsage: function() {
            let vm=this;
            let url=CI.base_url + '/api/files/size/'+this.ProjectID;

            axios.get(url)
            .then(function (response) {
                if (response.data && response.data.result){
                    vm.project_disk_usage=response.data.result;
                }
            })
            .catch(function (error) {
                console.log("disk_usage_stats_failed",error);
            });
        },
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
            let vm=this;
            let form_data={
                "template_uid":this.ProjectTemplates[this.template_idx]["uid"]
            };

            store.dispatch('loadTemplateByUID',{template_uid:this.ProjectTemplates[this.template_idx]["uid"]}).then(function(){
                //store.dispatch('initTreeItems');
                let url=CI.base_url + '/api/editor/options/'+ vm.ProjectID;

                axios.post( url,
                    form_data,{}
                ).then(function(response){
                    console.log("template updated",response);
                    vm.dialog_template=false;
                    vm.template_updating=false;
                    return false;
                })
                .catch(function(response){
                    vm.errors=response;
                });

            });
        }
    },     
    template: `
            <div class="summary-component mt-3 container-fluid">

                <div class="row">
                    <div class="col-12">
                        <v-card>
                            <v-card-text>
                            <div class="row">
                            <div class="col-3" >
                                <div class="thumbnail-container">
                                    <project-thumbnail/>
                                </div>
                            </div>
                            <div class="col-9" >
                            
                            <!-- project info -->

                            <div class="col-12 bg-light mb-3" >
                                <div>
                                    <div><v-icon style="font-size:25px;">mdi-alpha-t-box</v-icon> {{$t("template")}}</div>
                                    <div class="mt-1">
                                        <v-btn text color="primary" @click="dialog_template=true">
                                            {{ProjectTemplate.name}}
                                        </v-btn>
                                    </div>
                                </div>
                            </div>

                            <div class="project-info-container row">
                                <div class="col-6">

                                    <div class="mb-3">
                                        <strong>{{$t("Project owner")}}:</strong> 
                                        <div class="text-capitalize">{{project_edit_stats.username}}</div>
                                    </div>

                                    <div class="mb-3">
                                        <strong>{{$t("Last changed by")}}:</strong>
                                        <div class="text-capitalize">{{project_edit_stats.username_cr}}</div>
                                    </div>                                    

                                </div>
                                <div class="col-6">
                                
                                    <div class="mb-3">
                                        <strong>{{$t("Created on")}}:</strong>
                                        <div>{{momentDate(project_edit_stats.created)}}</div>
                                    </div>

                                    <div class="mb-3">
                                        <strong>{{$t("Changed on")}}:</strong>
                                        <div>{{momentDate(project_edit_stats.changed)}}</div>
                                    </div>
                                
                                </div>

                            </div>

                            <!-- end -->
                            

                            </div>
                            </div>
                                
                            </v-card-text>                     
                        </v-card>
                    </div>

                    <div class="col-6">
                    <v-card class="project-validation-container">
                            <v-card-text>
                            <h5>{{$t("project_validation")}}</h5>                            

                            <div class="validation-errors mt-2 border" v-if="validation_errors!=''" style="color:red;font-size:small;" >
                                <div class="border-bottom p-2 mb-2"><strong>{{$t("validation_errors")}}</strong></div>
                                <ul v-for="error in validation_errors.errors" class="mb-2 ml-3">
                                    <li><strong>{{error.message}}</strong><br/>
                                    Property: {{error.property}}
                                    </li>                                    
                                </ul>
                            </div>
                            <div class="mt-3 p-2 border" style="color:green" v-else>{{$t("no_validation_errors")}}</div>
                            </v-card-text>
                        </v-card>
                    </div>

                    <div class="col-6" >
                        <v-card>
                            <v-card-text>
                                <div class="d-flex justify-content-between">
                                    <h6>{{$t("Project info")}}</h6>
                                    <div v-if="project_disk_usage.size_formatted">
                                        <span>{{$t("Disk usage")}} </span>
                                        <span class="success--text ml-2">{{project_disk_usage.size_formatted}}</span>
                                    </div>
                                </div>
                                <div class="files-container " v-if="ProjectType!=='timeseries-db'" style="max-height:400px;overflow:auto;">
                                <summary-files></summary-files>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>


                </div>

                <div class="row" >
                    <div class="col-6" >
                        <!-- template dialog -->
                        <template class="project-template">
                            <div class="text-center">
                                <v-dialog
                                style="z-index:5000"
                                v-model="dialog_template"
                                max-width="700px"
                                scrollable
                                >
                                <v-card >
                                    <v-card-title class="text-h5 grey lighten-2">                                    
                                    </v-card-title>

                                    <v-card-text style="max-height:400px;">
                                    <div>
                                    
                                            <!-- list -->
                                            <template>
                                               
                                                    <v-list two-line>
                                                    <v-list-item-group
                                                        v-model="projectTemplateSelectedIndex"
                                                        active-class="pink--text"                                                        
                                                    >
                                                        <template v-for="(item, index) in ProjectTemplates">
                                                        <v-list-item :key="item.uid">
                                                            <template v-slot:default="{ active }">
                                                            <v-list-item-content>
                                                                <v-list-item-title v-text="item.name"></v-list-item-title>
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
                                        {{$t('Close')}}
                                    </v-btn>
                                    <v-btn
                                        color="primary"
                                        text
                                        @click="UpdateTemplate"
                                        :disabled="template_idx==-1 || template_updating"
                                    >
                                        {{$t('Apply')}}
                                    </v-btn>
                                    </v-card-actions>
                                </v-card>
                                </v-dialog>
                            </div>
                            </template>
                        <!-- end template dialog -->


                        


                    </div>
                   
                </div>

            </div>          
            `    
});

