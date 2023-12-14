Vue.component('dialog-datafile-replace', {
    props:['value','file_id'],
    data() {
        return {
            selection:[],
            file:null,
            import_options:{
                data_update:'replace',
            },
            errors:[],
            success:'',
            is_processing:false
        }
    }, 
    mounted: function () {
        
    },      
    methods: {   
        closeDialog: function(){
            this.dialog = false;
            this.$emit('selected', this.selection);
            this.selection = [];
            //this.$refs.fileUpload.value=null;
            this.errors=[];
            this.success='';
        },
        handleFileUpload(event)
        {
            this.file = event;
            this.errors='';
            //this.checkFileExists();
        },
        replaceFile(){            
            let formData = new FormData();
            formData.append('file', this.file);

            let vm=this;
            let url=CI.base_url + '/api/data/replace_datafile/'+ this.ProjectID + '/' + this.file_id;
            this.errors=[];
            this.is_processing=true;
            this.success='';

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function (response) {
                console.log(response);
                vm.success="File replaced successfully";
                vm.$emit('file-replaced', 
                {
                    'status':'success',
                    'file_id':this.file_id
                });
                console.log("event triggered");
                
                vm.is_processing=false;
            })
            .catch(function (error) {
                console.log(error);
                vm.errors.push(error.response.data.message);
                vm.is_processing=false;
            })            
        }
    },
    computed: {
        dialog: {
            get () {
                return this.value
            },
            set (val) {
                this.$emit('input', val)
            }
        },        
        ProjectID(){
            return this.$store.state.project_id;
        }        
    },
    template: `
        <div class="vue-dialog-datafile-replace-component" style="z-index:6000">

            <!-- dialog -->
            <v-dialog v-model="dialog" width="700" height="400" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        Replace data file
                    </v-card-title>

                    <v-card-text>

                    <p class="text-secondary" >Replace the data file with a new file. The new file must have the same structure as the original file.</p>

                    <div>
                        <!-- card text -->

                        <div class="">

                        <label class="" for="customFile">Select a file (CSV, DTA, SAV)</label>
                        <v-file-input
                            accept=".csv,.dta,.sav"
                            label=""
                            outlined
                            truncate-length="50"
                            dense
                            clearable
                            prepend-icon=""
                            prepend-inner-icon="mdi-paperclip"
                            @change="handleFileUpload( $event )"
                            ref="fileUpload"
                         ></v-file-input>

                        </div>
                        
                                            
                        <div v-if="errors && errors.length>0" class="mt-3">
                            <div class="alert alert-danger" role="alert" style="max-height:300px;overflow:auto;">
                                <div v-for="error in errors">{{error}}</div>
                            </div>    
                        </div> 

                        <div v-if="success" class="mt-3">
                            <div class="alert alert-success" role="alert" style="max-height:300px;overflow:auto;">
                                {{success}}
                            </div>
                        </div>
                        

                        <!-- end card text -->
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                      
                        <v-progress-circular v-if="is_processing==true" style="width:16px;height:16px;"                        
                        color="red"
                        indeterminate
                      ></v-progress-circular>

                        
                        <v-btn color="primary" text :disabled="!file || is_processing==true"  @click="replaceFile">Replace file</v-btn>

                        <v-btn color="primary" text @click="closeDialog" >
                            Close
                        </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->
        
        </div>
    `
});

