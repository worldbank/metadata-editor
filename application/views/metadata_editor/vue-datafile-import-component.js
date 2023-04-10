/// datafile import form
Vue.component('datafile-import', {
    data: function () {    
        return {
            files:[],
            file:'',
            file_id:'',
            file_exists:false,
            data_dictionary:{},
            is_processing:false,
            update_status:'',
            errors:'',
            upload_report:[],
            file_type:'',
            file_types:{
                "DTA": "Stata (DTA)",
                "SAV": "SPSS (SAV)",
                "CSV": "CSV"
            },
            allowed_file_types:["dta","sav","csv"],
            dialog_process:false
        }
    },
    watch: { 
        MaxFileID(newVal){
            if ('F' + newVal==this.file_id){
                return;
            }
            
            this.file_id='F'+(newVal+1);
        }
    },    
    mounted: function () {
        if (this.file_id==''){
            this.file_id='F'+(this.MaxFileID+1);
        }        
    },   
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        MaxFileID(){
            return this.$store.getters["getMaxFileId"];
        },
        MaxVariableID(){
            return this.$store.getters["getMaxVariableId"];
        },
        FilesCount: function(){            
            return this.files.length;
        },
    },
    methods:{

        dialogClose: function(){
            this.dialog_process = false,
            this.$router.push('/datafiles');
        },

        //on button import click
        processImport: async function()
        {
            this.errors='';
            this.is_processing=true;
            this.upload_report=[];
            this.dialog_process=true;

            for(i=0;i<this.files.length;)
            {
                await this.processFile(i);
                i++;

                /*if (this.errors!=''){
                    return false;
                }*/
            }

            this.update_status="completed";
            this.is_processing=false;
            this.$store.dispatch('initData',{dataset_id:this.ProjectID});
        },

        /**
         * 
         *  - Upload file
         *  - Generate data dictionary
         *  - Generete CSV
         *  - import data dictionary
         *  - import csv
         */
        processFile: async function(fileIdx)
        {
            try{                
                //upload file
                console.log("processing file",fileIdx);
                let resp=await this.uploadFile(fileIdx);
                console.log("finished uploading file",fileIdx,resp);

                let fileid=resp.result.file_id;
                
                if (!fileid){
                    throw new Error('File upload failed for file ' + this.files[fileIdx].name);
                }

                //import basic metadata availabe as meta
                this.update_status="Importing data dictionary " + this.files[fileIdx].name;
                let import_resp=await this.importDataFile(fileIdx, fileid);
                console.log("finished importing file",fileIdx,import_resp);

                //import summary statistics
                this.update_status="Generating summary statistics and frequencies " + this.files[fileIdx].name;
                let stats_resp=await this.importDataFileSummaryStatistics(fileIdx, fileid);
                console.log("finished summary statistics",fileIdx,import_resp);

                this.update_status="Exporting data to CSV " + this.files[fileIdx].name;
                let csv_resp=await this.generateCSV(fileIdx,fileid);
                console.log("finished generating csv file",fileIdx,csv_resp);

                this.upload_report.push(
                    {
                        'file_name':this.files[fileIdx].name,
                        'status': 'success'
                    }
                );

            } catch (error) {
                console.log(error);
                this.errors="failed uploading file "  + fileIdx + ' with error: ' +error + ' message: ' + error.response.data.message;
                this.upload_report.push({
                        'file_name':this.files[fileIdx].name,
                        'status': 'error',
                        'error':error
                });
            }
        },
        uploadFile: async function (fileIdx)
        {            
            let formData = new FormData();
            formData.append('file', this.files[fileIdx]);

            /*if (this.errors!=''){
                return false;
            }*/

            let vm=this;
            let url=CI.base_url + '/api/data/datafile/'+ this.ProjectID;

            const resp=await axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            );

            return resp.data;
        },
        importDataFile: async function(fileIdx,file_id)
        {
            let formData = {
                "filename":this.files[fileIdx].name
            }
            
            vm=this;            
            let url=CI.base_url + '/api/data/import_file_meta/'+this.ProjectID + '/' + file_id;
            
            let resp = await axios.get(url, formData,{
                headers: {
                    'Content-Type': 'application/json'
                    }
            });

            return resp.data;                
        },
        importDataFileSummaryStatistics:async function(fileIdx,file_id)
        {
            let resp=await this.$store.dispatch('importDataFileSummaryStatistics',{file_id:file_id});
            return resp.data;                
        },
        generateCSV: async function(fileIdx,file_id)
        {
            let resp=await this.$store.dispatch('generateCSV',{file_id:file_id});
            return resp.data; 
        }, 

        validateFilename: function(file){
            return /^[a-zA-Z0-9\.\-_ ()]*$/.test(file.name);
        },   
        checkFileDuplicate(name){
            for(i=0;i<this.files.length;i++){
                if (this.files[i].name==name){
                    return true;
                }
            }
            return false;
        }, 
        addFile(e) {
            let droppedFiles = e.dataTransfer.files;
            if(!droppedFiles) return;
            // this tip, convert FileList to array, credit: https://www.smashingmagazine.com/2018/01/drag-drop-file-uploader-vanilla-js/
            ([...droppedFiles]).forEach(f => {
                if (!this.checkFileDuplicate(f.name) && this.isAllowedFileType(f.name)){
                    this.files.push(f);
                }
            });
        },
        isAllowedFileType(name){
            if (_.includes(this.allowed_file_types,this.fileExtension(name))){
                return true;
            }

            return false;
        },
        removeFile(file_idx){
            /*this.files = this.files.filter(f => {
              return f != file;
            });*/
            Vue.delete(this.files,file_idx);      
            this.files.splice(file_idx, 0);
        },
        /*processImport: async function(){
            await this.uploadDatafiles();

            this.update_status="completed";
            this.is_processing=false;
            this.$store.dispatch('initData',{dataset_id:this.ProjectID});
        },
        uploadDatafiles: async function(){
            this.errors='';

            for(i=0;i<this.files.length;)
            {
                await this.uploadDataFile(i);
                i++;

                if (this.errors!=''){
                    return false;
                }
            }
        },
        uploadDataFile: async function (fileIdx)
        {            
            let formData = new FormData();
            formData.append('file', this.files[fileIdx]);

            if (this.errors!=''){
                return false;
            }

            let vm=this;
            let url=CI.base_url + '/api/editor/files/'+ this.ProjectID + '/data';

            await axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.importDataFile(fileIdx);
                return true;
            })
            .catch(function(response){
                vm.errors=response;
                return false;
            });            
        }, 
        importDataFile_old: async function(fileIdx){
            this.is_processing=true;
            this.update_status="Importing data dictionary " + this.files[fileIdx].name;

            let formData = {
                "filename":this.files[fileIdx].name
            }
            
            vm=this;            
            let url=CI.base_url + '/api/R/import_data_file/'+this.ProjectID;
            
            try{
                let resp = await axios.post(url, formData,{
                    headers: {
                        'Content-Type': 'application/json'
                      }
                });

                this.update_status="";

            }catch(error){
                vm.errors=error;
                console.log(Object.keys(error), error.message);
            }
        },
        generateCSVFromR: async function (file)
        {
            let formData = new FormData();
            //formData.append('fileid', this.file_id);
            formData.append("filename",file.name)

            vm=this;
            this.update_status="Generating CSV...";
            let url=CI.base_url + '/api/R/generate_csv/'+this.ProjectID + '/' +  file.name;

            axios.get( url,formData,{}
            ).then(function(response){
                console.log('SUCCESS!!',response);
                vm.update_status="CSV file generated...";
            })
            .catch(function(){
                console.log('FAILURE!!');
            });            
        },  */
        handleFileUpload(event)
        {
            this.file = event.target.files[0];
            this.errors='';
            this.checkFileExists();
        },
        handleMultiFileUpload(event)
        {
            let files = event.target.files;
            for(let i = 0; i<files.length; i++)
            {
                if (!this.checkFileDuplicate(files[i].name) && this.isAllowedFileType(files[i].name)){
                    this.files.push(files[i]);
                }
            }
        },
        checkFileExists: async function()
        {
            let url=CI.base_url + '/api/editor/datafile_by_name/'+this.ProjectID+'?filename='+this.file.name;
            this.errors='';
            vm=this;

            try {
                const resp = await axios.get(url);
                console.log(resp.data);
                vm.errors="File with the name already exists.";
                return false;
            } catch (err) {
                console.error(err);
                return true;
            }
        },
        fileExtension: function(name){
            return name.split(".").pop().toLowerCase();
        }
    },
    template: `
            <div class="datafile-import-component">
            <v-container>

                <h3>Import data files</h3>                
                <div class="bg-white p-3" >

                    <div class="form-container-x" >

                        <p>Upload one or more data files. Supported file types are: Stata(.dta), SPSS(.sav) and CSV</p>

                        <div @drop.prevent="addFile" @dragover.prevent class="border p-2 mb-2 bg-light text-center">
                            <div class="p-4"><i style="font-size:40px;color:gray;" class="fas fa-upload"></i></div>
                            <strong>Drag and drop data files here</strong>

                            <div class="mt-3">or</div>
                            
                            <div class="custom-file" style="max-width:300px;">
                                <input type="file" class="custom-file-input" id="customFile" multiple @change="handleMultiFileUpload( $event )">
                                <label class="custom-file-label" for="customFile">Choose files</label>
                            </div>

                        </div>

                        <div class="files-container mt-3 mb-3 p-2" v-if="files.length>0" >
                            <h5 class="mb-1">{{files.length}} files selected</h5>
                            <div class="border row mt-2" v-for="(file,file_index) in files" :key="file.name">
                                <div class="col-10" style="font-size:small;"><strong>{{ file.name }}</strong>  <div class="text-secondary">{{ file.size | kbmb }}</div> {{file.type}}</div>
                                <div class="col-2"><button class="float-right" @click="removeFile(file_index)" title="Remove"><i class="fas fa-trash"></i></button> </div>
                            </div>

                        </div>
                                                
                        <div xv-if="update_status==''">
                            <button type="button" :disabled="!FilesCount>0" class="btn btn-primary" @click="processImport">Import files</button>
                        </div>
                        
                    </div>

                    <div v-if="errors" class="p-3" style="color:red">
                        <div><strong>Errors</strong></div>
                        {{errors}}
                        <div v-if="errors.response">{{errors.response.data.message}}</div>
                    </div>

                    


                </div>


            </v-container>


            <v-dialog v-model="dialog_process" width="700" height="600" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    Importing data files
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <!-- card text -->

                        <v-row class="mt-3 text-center" v-if="update_status=='completed' && errors==''">
                            <v-col class="text-center" >
                                <i class="far fa-check-circle" style="font-size:24px;color:green;"></i> Data import completed                                
                            </v-col>
                        </v-row>
                
                        <v-container v-if="update_status!='completed' && errors=='' ">                    
                            <v-row v-if="is_processing"                            
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
                                <v-app class="border">
                                <v-progress-linear 
                                color="deep-purple accent-4"
                                indeterminate
                                rounded
                                height="6"
                                ></v-progress-linear>
                                </v-app>
                            </v-col>
                            </v-row>
                        </v-container>

                        <div v-if="upload_report">
                            <div v-for="report in upload_report" class="row border-top">
                                <div class="col-md-3">{{report.file_name}}</div>
                                <div class="col-md-2">{{report.status}}</div>
                                <div class="col-md-auto">
                                    <div style="color:red;" v-if="report.error">{{report.error.response.data.message}}</div>
                                </div>                                
                                <hr/>
                            </div>
                        </div>

                        <!-- end card text -->

                    </div>
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="dialogClose()">
                        Close
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>

            </div>          
            `    
})