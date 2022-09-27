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
            is_processing:false
        }
    },
    created: async function(){
    },
    methods:{
        importDDI: function(){
            let formData = new FormData();
            formData.append('file', this.file);

            vm=this;
            this.errors='';
            let url=CI.base_url + '/api/editor/import_ddi/'+ this.ProjectID;

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.$store.dispatch('loadProject',{dataset_id:vm.ProjectID});
                vm.$store.dispatch('initData',{dataset_id:vm.ProjectID});
            })
            .catch(function(response){
                vm.errors=response;
            }); 
        },
        handleFileUpload( event ){
            this.file = event.target.files[0];
        }
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        }
    },  
    template: `
            <div class="import-options-component">
            
                <v-container>

                <h3>Import Metadata</h3>

                <div class="bg-white p-3" >

                    <h4>Import DDI</h4>                    
                    <div class="form-container-x" >

                        <div class="text-primary mb-3">Import metadata from DDI. This will overwrite any existing study level metadata.</div>

                        <div class="file-group form-field mb-3">
                            <label class="l" for="customFile">Choose file (DDI/XML)</label>
                            <input type="file" class="form-control" id="customFile" @change="handleFileUpload( $event )">                
                        </div>
                        
                    
                        <button type="button" class="btn btn-primary" @click="importDDI">Import file</button>
                        <button type="button" class="btn btn-danger" >Cancel</button>

                    </div>

                    <div v-if="errors" class="p-3" style="color:red">
                        <div><strong>Errors</strong></div>
                        {{errors}}
                        <div v-if="errors.response">{{errors.response.data.message}}</div>
                    </div>

                    <v-row class="mt-3 text-center" v-if="update_status=='completed' && errors==''">
                        <v-col class="text-center" >
                            <i class="far fa-check-circle" style="font-size:24px;color:green;"></i> Update completed,
                            <router-link :to="'/variables/' + file_id">view variables</router-link>
                        </v-col>
                    </v-row>
            

                    <div v-if="update_status!='completed' && errors=='' ">                    
                        <v-row v-if="is_processing"
                        class="fill-height"
                        align-content="center"
                        justify="center"
                        >
                        <v-col
                            class="text-subtitle-1 text-center"
                            cols="12"
                        >
                        {{update_status}}
                        </v-col>
                        <v-col cols="12">
                            <v-app>
                            <v-progress-linear 
                            color="deep-purple accent-4"
                            indeterminate
                            rounded
                            height="6"
                            ></v-progress-linear>
                            </v-app>
                        </v-col>
                        </v-row>
                    </div>


                </div>


            </v-container>

            </div>          
            `    
});

