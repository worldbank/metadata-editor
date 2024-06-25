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
            sleep_time:500,
            sleep_counter:0,
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

        processImport: async function()
        {
            this.errors='';
            this.is_processing=true;
            this.upload_report=[];
            this.dialog_process=true;

            for(i=0;i<this.files.length;){
                await this.processFile(i);
                i++;
            }

            this.update_status="completed";
            this.is_processing=false;
            this.$store.dispatch('initData',{dataset_id:this.ProjectID});
        },

        /**
         * 
         *  - Upload file
         *  - Generate data dictionary
         *  - Generate CSV
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
                //this.update_status="Importing data dictionary " + this.files[fileIdx].name;
                //let import_resp=await this.importDataFile(fileIdx, fileid);
                //console.log("finished importing file",fileIdx,import_resp);

                //import summary statistics
                this.update_status="Generating summary statistics and frequencies " + this.files[fileIdx].name;
                let stats_resp=await this.importDataFileSummaryStatistics(fileIdx, fileid);
                console.log("finished summary statistics",fileIdx,stats_resp);

                if (!this.files[fileIdx].type.match('csv.*')) {
                    this.update_status="Exporting data to CSV " + this.files[fileIdx].name;
                    let csv_resp=await this.generateCSV(fileIdx,fileid);
                    console.log("finished generating csv file",fileIdx,csv_resp);
                }

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
            //let resp=await this.$store.dispatch('importDataFileSummaryStatistics',{file_id:file_id});
            //return resp.data;

            let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueue',{file_id:file_id});
            console.log("sumstats queued",result);
            return await this.importSummaryStatisticsQueueStatusCheck(file_id,result.data.job_id);
            
        },
        importSummaryStatisticsQueueStatusCheck: async function(file_id,job_id){
            await this.sleep(this.sleep_time);
            let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueueStatusCheck',{file_id:file_id, job_id:job_id});
            console.log("job updated",result);
                                
            if (result.data.job_status!=='done'){
                return await this.importSummaryStatisticsQueueStatusCheck(file_id,job_id);
            }else if (result.data.job_status==='done'){
                //await this.reloadDataFileVariables();
                return true;
            }                
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        generateCSV: async function(fileIdx,file_id)
        {
            //let resp=await this.$store.dispatch('generateCSV',{file_id:file_id});
            //return resp.data; 

            let result=await this.$store.dispatch('generateCsvQueue',{file_id:file_id});
            console.log("updated",result);
            let status=await this.generateCsvQueueStatusCheck(file_id,result.data.job_id);
            return status;
        },         
        generateCsvQueueStatusCheck: async function(file_id,job_id){
            await this.sleep(this.sleep_time);
            let result=await this.$store.dispatch('generateCsvQueueStatusCheck',{file_id:file_id, job_id:job_id});
            console.log("csv updated",result);

            if (result.data.job_status!=='done'){
                return await this.generateCsvQueueStatusCheck(file_id,job_id);
            }else if (result.data.job_status==='done'){
                return true;
            }                
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
            let url=CI.base_url + '/api/datafiles/by_name/'+this.ProjectID+'?filename='+this.file.name;
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
            <div class="datafile-import-component container-fluid mt-5 p-3">

                <v-card>
                    <v-card-title>{{$t("import_data_files")}}</v-card-title>
                    <v-card-text>                

                    <div class="form-container-x" >

                        <p>{{$t("upload_one_or_more_data_files")}}</p>

                        <div @drop.prevent="addFile" @dragover.prevent class="border p-2 mb-2 bg-light text-center">
                            <div class="p-4"><i style="font-size:40px;color:gray;" class="fas fa-upload"></i></div>
                            <strong>{{$t("drag_drop_data_files")}}</strong>

                            <div class="mt-3">{{$t("or")}}</div>
                            
                            <div class="custom-file" style="max-width:300px;">
                                <input type="file" class="custom-file-input" id="customFile" multiple @change="handleMultiFileUpload( $event )" >
                                <label class="custom-file-label" for="customFile">{{$t("choose_files")}}</label>
                            </div>

                        </div>

                        <div class="files-container mt-3 mb-3 p-2" v-if="files.length>0" >
                            <h5 class="mb-1">{{files.length}} {{$t("selected")}}</h5>
                            <div class="border row mt-2" v-for="(file,file_index) in files" :key="file.name">
                                <div class="col-10" style="font-size:small;"><strong>{{ file.name }}</strong>  <div class="text-secondary">{{ file.size | kbmb }}</div> {{file.type}}</div>
                                <div class="col-2"><button class="float-right" @click="removeFile(file_index)" title="Remove"><i class="fas fa-trash"></i></button> </div>
                            </div>

                        </div>
                                                
                        <div xv-if="update_status==''">
                            <v-btn color="primary" :disabled="!FilesCount>0"  @click="processImport">{{$t("import")}}</v-btn>
                        </div>
                        
                    </div>

                    <div v-if="errors" class="p-3" style="color:red">
                        <div><strong>{{$t("errors")}}</strong></div>
                        {{errors}}
                        <div v-if="errors.response">{{errors.response.data.message}}</div>
                    </div>

                    </v-card-text>
                </v-card>



            <v-dialog v-model="dialog_process" width="700" height="600" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    {{$t("import_data_files")}}
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <!-- card text -->

                        <v-row class="mt-3 text-center" v-if="update_status=='completed' && errors==''">
                            <v-col class="text-center" >
                                <i class="far fa-check-circle" style="font-size:24px;color:green;"></i> {{$t("import_completed")}}                               
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
                    {{$t("close")}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>

            </div>          
            `    
})