Vue.component('dialog-datafile-export', {
    props:['value', 'files'],
    data() {
        return {
        }
    }, 
    mounted: function () {
        
    },      
    methods: {   
        closeDialog: function(){
            this.dialog = false;
        },
        handleFileUpload(event)
        {
            this.file = event;
            this.errors='';
            //this.checkFileExists();
        },
        exportFile(){            
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
        <div class="vue-dialog-datafile-export-component">

            <!-- dialog -->
            <v-dialog v-model="dialog" width="700" height="400" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        Export data
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <!-- card text -->

                        <pre>{{files}}</pre>
                                            
                        <div>
                            <button type="button" class="btn btn-primary" @click="exportFile">Export</button>
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

