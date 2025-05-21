//external resources import from RDF/JSON
Vue.component('external-resources-import', {
    props: ['index'],
    data() {
        return {
            file:'',
            errors:[]
        }
    }, 
    created () {
        //this.loadDataFiles();
    },   
    methods: {        
        uploadResources: async function(){
            this.errors=[];
            let formData = new FormData();
            formData.append('file', this.file);

            vm=this;
            let url=CI.base_url + '/api/resources/import/'+ this.ProjectID;

            await axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.$store.dispatch('loadExternalResources',{dataset_id:vm.ProjectID});
                router.push('/external-resources/');
            })
            .catch(function(err){
                if (err.response.data && err.response.data.message){
                    vm.errors.push(err.response.data.message);
                }
                else{
                    vm.errors.push('An error occurred');
                }
                console.log("error",err);
            });
        },
        handleFileUpload( event ){
            this.file = event;            
            this.errors='';            
        },
    },
    computed: {
        ExternalResources()
        {
          return this.$store.state.external_resources;
        },
        ProjectID(){
            return this.$store.state.project_id;
        }
    },
    template: `
        <div class="external-resources-import-component mt-5 p-3">

                        <v-card>
                            <v-card-title>
                                Import external resources
                            </v-card-title>

                            <v-card-text>
                                <v-file-input                            
                                    label=""
                                    outlined
                                    truncate-length="50"
                                    dense
                                    prepend-icon=""
                                    prepend-inner-icon="mdi-paperclip"
                                    @change="handleFileUpload( $event )"                                    
                                    ref="fileUpload"
                                ></v-file-input>
                                <div class="mb-3">
                                    <small>Allowed file types: RDF, JSON</small>
                                </div>

                                <div style="color:red" class="mb-3" v-if="errors.length>0">
                                    <div>Errors:</div>
                                    <div v-for="error in errors">
                                        {{error}}
                                    </div>
                                </div>

                                <v-btn color="primary" @click="uploadResources">Import file</v-btn>

                            </v-card-text>

                            <v-card-actions>                                
                                
                            </v-card-actions>

                        </v-card>
            

        </div>
    `
})


