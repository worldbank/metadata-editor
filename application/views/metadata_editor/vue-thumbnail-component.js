/// thumbnail
Vue.component('project-thumbnail', {
    data: function () {    
        return {
            show_dialog:false,
            file:'',
            thumbnail:'',
            image_error:false,
            errors:[],
            base_asset_url: CI.base_asset_url,
            placeholder_thumbnail: CI.base_asset_url + '/files/placeholder.png'
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
        RemoveThumbnail: function(){
            let url=CI.base_url + '/api/files/delete_thumbnail/'+ this.ProjectID;
            vm=this;
            axios.post(url)
            .then(function(response){
                vm.thumbnail=vm.placeholder_thumbnail
                vm.show_dialog=false;
            })
            .catch(function(response){
                vm.errors=response;
                alert("Failed to remove thumbnail", response.data.message);
            });
        },
        UploadThumbnail: function(){
            let formData = new FormData();
            formData.append('file', this.file);

            vm=this;
            this.errors='';
            let url=CI.base_url + '/api/files/'+ this.ProjectID + '/thumbnail';

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.image_error=false;
                vm.thumbnail=CI.base_url + '/api/editor/thumbnail/'+ vm.ProjectID + '/?_r=' + Date.now();
                vm.show_dialog=false;
            })
            .catch(function(response){
                vm.errors=response;
                alert("Failed to upload thumbnail", response.data.message);
            });
        }   
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        }
    },  
    template: `
            <div class="thumbnail-component">

                <div class="row">
                    <div class="col-12" @click="show_dialog=true">
                        <div>
                            <div class="text-center text-no-wrap rounded-xl" style="background:#4b6dce;">
                                <v-img
                                    :lazy-src="base_asset_url + '/files/placeholder.png'" 
                                    max-height="200"
                                    border
                                    contain                               
                                    :src="thumbnail" 
                                    class="rounded-xl"                                                                   
                                >
                                <v-btn                                 
                                    style="position:absolute; right:10px; bottom:10px;"
                                    fab x-small color="white" @click="show_dialog=true" ><v-icon>mdi-pencil</v-icon></v-btn>
                                </v-img>                                
                            </div>

                        </div>
                        
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
                            {{$t("upload_thumbnail")}}
                            </v-card-title>

                            <v-card-text>
                                <div class="xcustom-file">
                                    <input type="file" class="border p-1" style="width:100%;" id="xcustomFile" @change="handleFileUpload( $event )">
                                </div>
                                
                                <div class="text-secondary">{{$t("allowed_file_types")}}: jpg, jpeg, gif, png</div>

                                <div v-if="errors.length>0">{{errors}}</div>
                            </v-card-text>

                            <v-divider></v-divider>

                            <v-card-actions>
                                <v-btn 
                                    color="secondary"
                                    text
                                    @click="RemoveThumbnail"
                                >
                                {{$t("remove")}}
                                </v-btn>
                                <v-spacer></v-spacer>
                                <v-btn 
                                    color="secondary"
                                    text
                                    @click="show_dialog=false"
                                >
                                {{$t("cancel")}}
                                </v-btn>
                                <v-btn :disabled="!file"
                                    color="primary"
                                    text
                                    @click="UploadThumbnail()"
                                >
                                    {{$t("upload")}}
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

