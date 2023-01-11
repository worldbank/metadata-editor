/// thumbnail
Vue.component('project-thumbnail', {
    data: function () {    
        return {
            show_dialog:false,
            file:'',
            thumbnail:'',
            image_error:false,
            errors:[]
        }
    },
    created: function(){
        this.thumbnail=CI.base_url + '/api/editor/thumbnail/'+ this.ProjectID;
    },
    methods:{
        handleFileUpload( event ){
            this.file = event.target.files[0];
        },
        imageLoadError: function()
        {
            this.image_error=true;
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
                vm.image_error=false;
                vm.thumbnail=CI.base_url + '/api/editor/thumbnail/'+ vm.ProjectID + '/?_r=' + Date.now();
                vm.show_dialog=false;
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
                    <div class="col-4" @click="show_dialog=true">
                        <div v-if="image_error==true">
                            <i class="far fa-image" style="font-size:125px;"></i>                            
                        </div>
                        <div v-if="image_error==false"><img class="img-fluid" style="max-width:150px;max-height:150px;" :src="thumbnail" @error="imageLoadError"/></div>
                        <button type="button"  @click="show_dialog=true" class="btn btn-link">Change image</button>
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
                                <div class="xcustom-file">
                                    <input type="file" class="border p-1" style="width:100%;" id="xcustomFile" @change="handleFileUpload( $event )">
                                </div>
                                
                                <div class="text-secondary">Allowed file types: jpg, jpeg, gif, png</div>

                                <div v-if="errors.length>0">{{errors}}</div>
                            </v-card-text>

                            <v-divider></v-divider>

                            <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn 
                                color="secondary"
                                text
                                @click="show_dialog=false"
                            >
                                Cancel
                            </v-btn>
                            <v-btn :disabled="!file"
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

