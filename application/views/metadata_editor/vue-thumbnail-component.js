/// thumbnail
Vue.component('project-thumbnail', {
    data: function () {    
        return {
            show_dialog:false,
            file:'',
            errors:[]
        }
    },
    created: function(){
        
    },
    methods:{
        handleFileUpload( event ){
            this.file = event.target.files[0];
        },
        UploadThumbnail: function(){
            let formData = new FormData();
            formData.append('file', this.file);

            vm=this;
            this.errors='';
            let url=CI.base_url + '/api/editor/files/'+ this.ProjectID + '/thumbnail';

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                console.log("thumbnail uploaded",response);
                alert(done);
                //router.push('/');
            })
            .catch(function(response){
                vm.errors=response;
            });
        } 
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        }
    },  
    template: `
            <div class="thumbnail-component p-3">
                <h5>Project thumbnail</h5>
                <div class="row">
                    <div class="col-4">
                        <div><i class="far fa-image" style="font-size:125px;"></i></div>
                        <button type="button"  @click="show_dialog=true" class="btn btn-default">Upload image</button>
                    </div>
                    <div class="col-auto">
                        
                    </div>
                </div>


                <!--dialog-->
                <template>
                    <div class="text-center">
                        <v-dialog
                        v-model="show_dialog"
                        width="500"
                        >                        

                        <v-card>
                            <v-card-title class="text-h5 grey lighten-2">
                            Upload project thumbnail
                            </v-card-title>

                            <v-card-text>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="customFile" @change="handleFileUpload( $event )">
                                    <label class="custom-file-label" for="customFile">Choose file</label>
                                </div>
                                <div class="text-secondary">Allowed file types: jpg, jpeg, gif, png</div>

                                {{errors}}
                            </v-card-text>

                            <v-divider></v-divider>

                            <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn
                                color="primary"
                                text
                                @click="UploadThumbnail()"
                            >
                                Upload
                            </v-btn>
                            </v-card-actions>
                        </v-card>
                        </v-dialog>
                    </div>
                    </template>
                <!--end dialog -->




            </div>          
            `    
});

