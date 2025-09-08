/// import options
Vue.component('import-options', {
    props:['value'],
    data: function () {    
        return {
            field_data: this.value,
            form_local:{},
            file:'',
            update_status:'',
            errors:'',
            is_processing:false,
            import_options:{
                "survey":{
                    'project_metadata': {
                        'title': 'project_level_metadata',
                        'options': {
                            'document_description':'document_description',
                            'study_description':'study_description'
                        }
                    },
                    'data_files': {
                        'title': 'data_files',
                        'options': {
                            'data_files':'file_description'
                        }
                    },
                    'variable_info': {
                        'title': 'variable_information',
                        'options': {
                            'variable_info':'variable_information',
                            'variable_documentation':'variable_documentation',
                            'variable_categories':'variable_categories',
                            'variable_questions':'variable_questions',
                            'variable_weights':'variable_weights',
                            'variable_groups':'variable_groups'
                        }
                    }
                }
            },
            import_options_selected:[]
        }
    },
    created: async function(){
        this.defaultOptionsSelection();
    },
    methods:{
        defaultOptionsSelection: function(){
            this.import_options_selected=[];
            if (this.import_options[this.ProjectType]){
                // Iterate through each group
                for (let groupKey in this.import_options[this.ProjectType]){
                    let group = this.import_options[this.ProjectType][groupKey];
                    if (group.options) {
                        // Add all options from each group by default
                        for (let opt in group.options){
                            this.import_options_selected.push(opt);
                        }
                    }
                }
            }
        },
        importDDI: function(){            
            let formData = new FormData();
            formData.append('file', this.file);
            formData.append('options', this.import_options_selected.join(','));

            vm=this;
            this.errors='';
            let url=CI.base_url + '/api/import_metadata/'+ this.ProjectID;
            this.is_processing=true;

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.is_processing=false;
                vm.$store.dispatch('loadProject',{dataset_id:vm.ProjectID});
                vm.$store.dispatch('initData',{dataset_id:vm.ProjectID});
                router.push('/study/study_desc');
            })
            .catch(function(response){
                vm.is_processing=false;
                vm.errors=response;
                console.log("failed, response:",response)
            }); 
        },
        onCancel: function(){
            router.push('/study/study_desc');
        },
        handleFileUpload( file ){
            this.file = file;
        }
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectType()
        {
            return this.$store.state.project_type;
        }
    },  
    template: `
            <div class="import-options-component import-project-metadata p-3 mt-5">
            
                <v-card>
                    <v-card-title>
                        {{$t("import_project_metadata")}}
                    </v-card-title>
                    <v-card-text>

                

                    <div class="form-container-x" >

                        <div class="file-group mb-3" style="max-width:600px;">
                            <label class="form-label mb-2">
                                <span v-if="ProjectType=='survey'">{{$t("choose_ddi_xml_or_json_file")}}</span>
                                <span v-if="ProjectType!='survey'">{{$t("choose_json_file")}}</span>
                            </label>
                            <v-file-input
                                v-model="file"
                                label=""
                                accept=".xml,.json"                                
                                append-icon="mdi-file-upload"
                                @change="handleFileUpload"
                                outlined
                                dense
                            ></v-file-input>
                        </div>

                        <div v-if="ProjectType=='survey'">

                            <strong>{{$t("import_options")}}</strong>

                            <div class="mt-3">
                                <div v-for="(group, groupKey) in import_options.survey" :key="groupKey" class="mb-4">
                                    <h6 class="mb-2">{{$t(group.title)}}</h6>
                                    <p v-if="group.description" class="text-muted small mb-2 ml-3">{{$t(group.description)}}</p>
                                    <ul class="list-unstyled ml-3">
                                        <li class="form-group form-check mb-2" v-for="(opt, opt_key) in group.options" :key="opt_key">
                                            <input type="checkbox" class="form-check-input" :id="opt_key" :value="opt_key" v-model="import_options_selected">
                                            <label class="form-check-label" :for="opt_key">
                                                {{$t(opt)}}
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        
                        </div>
                        
                        <div v-if="!is_processing" class="mt-5 d-flex" >
                            <v-btn
                                color="primary"
                                :disabled="!file"
                                @click="importDDI"
                                small
                                class="mr-3"
                            >
                                {{$t("import_file")}}
                            </v-btn>
                            <v-btn
                                color="error"
                                :disabled="!file"
                                @click="onCancel"
                                small
                                outlined
                            >
                                {{$t("cancel")}}
                            </v-btn>
                        </div>

                    </div>

                    <div v-if="errors" class="p-3 mt-3 border" style="color:red">
                        <div><strong>{{$t("errors")}}</strong></div>
                        {{errors}}
                        <div v-if="errors.response">{{errors.response.data.message}}</div>
                    </div>

                    <v-row class="mt-3 text-center" v-if="update_status=='completed' && errors==''">
                        <v-col class="text-center" >
                            <i class="far fa-check-circle" style="font-size:24px;color:green;"></i> {{$t("update_completed")}},
                            <router-link :to="'/study/study_description/'">{{$t("view_documentation")}}</router-link>
                        </v-col>
                    </v-row>
            

                    <div class="mt-5" v-if="is_processing">
                    
                        <v-progress-circular
                            indeterminate
                            width="4"
                            :size="20"
                            color="primary"
                            ></v-progress-circular>
                            {{$t("processing_please_wait")}} 

                    </div>

                </v-card-text>
            </v-card>

            </div>          
            `    
});

