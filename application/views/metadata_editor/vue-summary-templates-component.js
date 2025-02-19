/// Project templates + admin metadata templates
Vue.component('summary-templates-component', {
    data () {
        return {
          validation_errors: "",
          dialog_template:false,
          template_idx:-1,
          template_updating:false,
          dialog_admin_metadata:false
        }
      },
    mounted: function(){
        this.loadAdminMetadataTemplates();
    },
    computed: {
        isProjectEditable(){
            return this.$store.getters.getUserHasEditAccess;
        },
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
        ProjectMetadata(){
            return this.$store.state.formData;
        },
        AdminMetadataTemplates(){
            return this.$store.getters.getAdminMetadataTemplates;   
        }
    },
    methods:{        
        momentDate(date) {
            return moment.utc(date).local().format("YYYY-MM-DD HH:mm:ss");
          },
        selectProjectTemplate: function(){
            this.dialog_template=true;
            //loadTemplates();
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
        },
        loadTemplates: async function(){
            await store.dispatch('loadTemplatesList',{});
        },
        loadAdminMetadataTemplates: async function(){
            await store.dispatch('loadAdminMetadataTemplates',{});
        },
    },     
    template: `
            <div class="summary-templates-component">

                <v-card class="project-template-selection mb-3" >
                    <v-card-title>
                        <h6>{{$t("Template")}}</h6>
                    </v-card-title>

                    <v-card-text>
                        <div class="font-weight-bold">Project template:</div>
                                                
                        <v-btn class="m-0 p-0" text  color="primary" @click="selectProjectTemplate" :disabled="!isProjectEditable">
                             <span class="plx-2">  {{ProjectTemplate.name}} - {{ProjectTemplate.version}}</span>
                        </v-btn>

                        <v-divider></v-divider>

                        <!--admin metadata-->
                        <div style="position:relative;">
                            <div class="mt-2 font-weight-bold">Administrative metadata templates:</div>
                            <v-btn style="position:absolute;top:0;right:0" text small color="primary" @click="dialog_admin_metadata=true" :disabled="!isProjectEditable">
                                <v-icon>mdi-cog</v-icon>
                            </v-btn>
                        </div>
                        <div>
                            <template v-for="template in AdminMetadataTemplates">
                                <v-chip small color="#dce3f7"  v-if="template.is_active" class="m-1 mr-2" @click="dialog_admin_metadata=true">
                                    {{template.name}}
                                </v-chip>
                            </template>
                            <div v-if="AdminMetadataTemplates.length==0">
                                <v-chip small color="gray"  class="m-1 mr-2">None</v-chip>
                            </div>
                        </div>
                        
                        <vue-dialog-admin-metadata-component v-model="dialog_admin_metadata" v-on:dialog-close="loadAdminMetadataTemplates" ></vue-dialog-admin-metadata-component>
                        <!--end admin metadata-->

                    </v-card-text>
                </v-card>


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
                            {{$t('Template')}}
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
                                                        <v-list-item-title><strong>{{item.name}}</strong></v-list-item-title>
                                                        <v-list-item-subtitle>
                                                            {{item.uid}}
                                                            <span v-if="item.version">| Version: {{item.version}}</span>  
                                                            <span v-if="item.lang">| Language: {{item.lang}}</span>
                                                        </v-list-item-subtitle>                                                                
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
                                {{$t('close')}}
                            </v-btn>
                            <v-btn
                                color="primary"
                                text
                                @click="UpdateTemplate"
                                :disabled="template_idx==-1 || template_updating"
                            >
                                {{$t('apply')}}
                            </v-btn>
                            </v-card-actions>
                        </v-card>
                        </v-dialog>
                    </div>
                    </template>
                <!-- end template dialog -->


            </div>          
            `    
});

