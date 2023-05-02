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
        saveFile: function(data)
        {
            console.log("saving file",this.data_files[this.edit_item]);
            this.$set(this.data_files, this.edit_item, data);            
        },
        uploadResources: function(){
            let formData = new FormData();
            formData.append('file', this.file);

            if (this.errors!=''){
                return false;
            }

            vm=this;
            let url=CI.base_url + '/api/resources/import/'+ this.ProjectID;

            axios.post( url,
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
            .catch(function(response){
                vm.errors=response;
            });
        },
        handleFileUpload( event ){
            this.file = event.target.files[0];
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
        <div>
            
            <v-container>

                    <h3>Import external resources</h3>

                    <div class="bg-white p-3" >
                        <div v-if="errors.length>0">{{errors}}</div>
                        <div class="form-container-x" >
                        
                            <div class="file-group form-field mb-3">
                                <label class="l" for="customFile">Choose file</label>
                                <input type="file" class="form-control" id="customFile" @change="handleFileUpload( $event )">
                                <small>Allowed file types: RDF, JSON</small>
                            </div>

                            <button type="button" class="btn btn-primary" @click="uploadResources">Import file</button>

                        </div>
                    </div>
            </v-container>

        </div>
    `
})


