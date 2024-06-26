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
                    'document_description':'Document description',
                    'study_description':'Study description',
                    'data_files':'File description',
                    'variable_info':'Variable information',
                    'variable_documentation':'Variable documentation',
                    'variable_categories':'Variable categories',
                    'variable_questions':'Variable questions',
                    'variable_weights':'Variable weights',                    
                    'variable_groups':'Variable groups'
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
                for (let opt in this.import_options[this.ProjectType]){
                    console.log(opt);
                    this.import_options_selected.push(opt);
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
        handleFileUpload( event ){
            this.file = event.target.files[0];
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
                        Import project metadata
                    </v-card-title>
                    <v-card-text>

                

                    <div class="form-container-x" >

                        <div class="file-group form-field mb-3" style="max-width:600px;">
                            <label class="l" for="customFile">
                                <span v-if="ProjectType=='survey'">Choose DDI/XML or a JSON file</span></label>
                                <span v-if="ProjectType!='survey'">Choose a JSON file</span></label>
                            <input type="file" class="form-control" id="customFile" @change="handleFileUpload( $event )">                
                        </div>

                        <div>

                            <strong>Options</strong>

                            <div v-if="ProjectType=='survey'" class="ml-2">
                                <div class="form-group form-check mb-0" v-for="(opt,opt_key) in import_options.survey" :key="opt_key">
                                    <input type="checkbox" class="form-check-input" :id="opt_key" :value="opt_key" v-model="import_options_selected">
                                    <label class="form-check-label" :for="opt_key">{{opt}}</label>
                                </div>                                
                            </div>
                        
                        </div>
                        
                        <div v-if="!is_processing" class="mt-5" >
                        <button type="button" :disabled="!file" class="btn btn-sm btn-primary" @click="importDDI">Import file</button>
                        <button type="button" :disabled="!file" class="btn btn-sm btn-danger" @click="onCancel">Cancel</button>
                        </div>

                    </div>

                    <div v-if="errors" class="p-3 mt-3 border" style="color:red">
                        <div><strong>Errors</strong></div>
                        {{errors}}
                        <div v-if="errors.response">{{errors.response.data.message}}</div>
                    </div>

                    <v-row class="mt-3 text-center" v-if="update_status=='completed' && errors==''">
                        <v-col class="text-center" >
                            <i class="far fa-check-circle" style="font-size:24px;color:green;"></i> Update completed,
                            <router-link :to="'/study/study_description/'">view documentation</router-link>
                        </v-col>
                    </v-row>
            

                    <div class="mt-5" v-if="is_processing">
                    
                        <v-progress-circular
                            indeterminate
                            width="4"
                            :size="20"
                            color="primary"
                            ></v-progress-circular>
                            Processing, please wait... 

                    </div>

                </v-card-text>
            </v-card>

            </div>          
            `    
});

