Vue.component('dialog-datafile-import', {
    props:['value','file_id'],
    data() {
        return {
            selection:[],
            file:null,
            import_options:{
                data_update:'replace',
            },
        }
    }, 
    mounted: function () {
        
    },      
    methods: {   
        closeDialog: function(){
            this.dialog = false;
            this.$emit('selected', this.selection);
            this.selection = [];
        },
        handleFileUpload(event)
        {
            this.file = event;
            this.errors='';
            //this.checkFileExists();
        },
        importFile(){            
            let formData = new FormData();
            formData.append('file', this.file);

            let vm=this;
            let url=CI.base_url + '/api/data/replace_datafile/'+ this.ProjectID + '/' + this.file_id;

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function (response) {
                console.log(response);
                //vm.$emit('imported', response.data);
                //vm.closeDialog();
                alert("File imported successfully");
            })
            .catch(function (error) {
                console.log(error);
                alert("Failed to import: "+ error.message);
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
        <div class="vue-dialog-datafile-import-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" width="700" height="400" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        Import data file
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <!-- card text -->

                        <div class="">

                        <label class="" for="customFile">Select a file (CSV, DTA, SAV, JSON)</label>
                        <v-file-input
                            accept=".csv,.json,.dta,.sav"
                            label=""
                            outlined
                            truncate-length="50"
                            dense
                            prepend-icon=""
                            @change="handleFileUpload( $event )"                            
                         ></v-file-input>

                        </div>
                        

                        <div class="files-container mt-3 mb-3 p-2 v-labels-remove-space">

                            <div><strong>Options:</strong></div>
                            <v-radio-group
                                v-model="import_options.data_update"
                                column
                                >
                                <v-radio
                                    label="Replace existing data?"
                                    value="replace"
                                ></v-radio>
                                <v-radio
                                    label="Append to existing data?"
                                    value="append"
                                ></v-radio>
                                </v-radio-group>
                                <hr>

                            {{import_options}}

                        </div>
                                            
                        <div xv-if="update_status==''">
                            <button type="button" :disabled="!file" class="btn btn-primary" @click="importFile">Import file</button>
                        </div> 
                        

                        <!-- end card text -->
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
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

