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
            has_errors:false,
            file_type:'',
            sleep_time:500,
            sleep_counter:0,
            overwrite_if_exists:false,
            file_types:{
                "DTA": "Stata (DTA)",
                "SAV": "SPSS (SAV)",
                "CSV": "CSV"
            },
            allowed_file_types:["dta","sav","csv"],
            dialog_process:false,
            keep_data:'' //store, remove
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
            if (!this.files){
                return 0;
            }

            return this.files.length;
        },
        isImportDisabled()
        {
            if (this.FilesCount==0){
                return true;
            }
            if (this.keep_data==''){
                return true;
            }

            return false;
        }
    },
    methods:{

        dialogClose: function(){
            this.dialog_process = false;

            if (this.has_errors==false){
                this.$router.push('/datafiles');
            }
        },

        processImport: async function()
        {
            this.errors='';
            this.has_errors=false;
            this.is_processing=true;
            this.upload_report=[];            
            this.dialog_process=true;

            try{
                let service_status=await this.pingDataService();
            }
            catch (error) {
                this.is_processing=false;                
                this.errors=error;
                this.has_errors=true;

                this.upload_report.push({
                    'file_name': "Data service error",
                    'status': 'error',
                    'error': error
                });
                return false;    
            }


            for(i=0;i<this.files.length;){                
                await this.processFile(i);
                i++;
            }

            //clean up - remove uploaded files that are not stored
            this.update_status="Cleaning up ...";
            let cleanup=await this.cleanUpData();

            this.update_status="completed";
            this.is_processing=false;
            await this.$store.dispatch('loadDataFiles',{dataset_id:this.ProjectID});
            this.$store.dispatch('loadAllVariables',{dataset_id:this.ProjectID});
            this.$store.dispatch('loadVariableGroups',{dataset_id:this.ProjectID}); 
        },
        pingDataService: async function() {
            vm = this;
            let url = CI.base_url + '/api/data/status/';

            let resp = await axios.get(url,{
            headers: {
                'Content-Type': 'application/json'
            }
            });

            if (resp.status !== 200) {
                throw new Error('Failed to ping data service. error: ' + JSON.stringify(resp.data));
            }

            if (resp.data.status === 'failed') {
                throw new Error('Data service returned an error' + JSON.stringify(resp.data));
            }

            return resp.data;
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
            console.log("processing file",fileIdx);
            try{
                //upload file
                let resp=await this.uploadFile(fileIdx);
                let fileid=resp.result.file_id;
                
                if (!fileid){
                    console.log("failed uploading file",resp);
                    throw new Error('File upload failed for file ' + this.files[fileIdx].name);
                }

                //import summary statistics
                this.update_status="Generating summary statistics and frequencies " + this.files[fileIdx].name;
                let stats_resp=await this.importDataFileSummaryStatistics(fileIdx, fileid);

                //generate csv, only if data is stored
                if (this.keep_data=='store'){
                    if (!this.files[fileIdx].type.match('csv.*')) {
                        this.update_status="Exporting data to CSV " + this.files[fileIdx].name;
                        let csv_resp=await this.generateCSV(fileIdx,fileid);                    
                    }
                }

                this.upload_report.push(
                    {
                        'file_name':this.files[fileIdx].name,
                        'status': 'success'
                    }
                );

            } catch (error) {
                this.has_errors=true;
                this.errors="failed uploading file "  + fileIdx + ' with error: ' + JSON.stringify(error) ;
                this.upload_report.push({
                        'file_name':this.files[fileIdx].name,
                        'status': 'error',
                        'error': error
                });
            }
        },
        uploadFile: async function (fileIdx)
        {            
            let formData = new FormData();
            formData.append('file', this.files[fileIdx]);
            formData.append('store_data', this.keep_data);

            if (this.overwrite_if_exists){
                formData.append("overwrite", "1");
            }                           

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
        importDataFileSummaryStatistics:async function(fileIdx,file_id)
        {
            let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueue',{file_id:file_id});
            return await this.importSummaryStatisticsQueueStatusCheck(file_id,result.data.job_id);            
        },
        importSummaryStatisticsQueueStatusCheck: async function(file_id,job_id){
            await this.sleep(this.sleep_time);
            let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueueStatusCheck',{file_id:file_id, job_id:job_id});            
                                
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
            let result=await this.$store.dispatch('generateCsvQueue',{file_id:file_id});
            let status=await this.generateCsvQueueStatusCheck(file_id,result.data.job_id);
            return status;
        },         
        generateCsvQueueStatusCheck: async function(file_id,job_id){
            await this.sleep(this.sleep_time);
            let result=await this.$store.dispatch('generateCsvQueueStatusCheck',{file_id:file_id, job_id:job_id});            

            if (result.data.job_status!=='done'){
                return await this.generateCsvQueueStatusCheck(file_id,job_id);
            }else if (result.data.job_status==='done'){
                return true;
            }                
        },
        cleanUpData:async function()
        {
            let result=await this.$store.dispatch('cleanUpData');
            return result;            
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
                        
                        <v-card @drop.prevent="addFile" @dragover.prevent class="elevation-2 border p-2 mb-2 bg-light text-center">
                            <div class="p-2">
                                    <v-icon x-large>mdi-upload</v-icon>
                                    <strong>{{$t("drag_drop_data_files")}}</strong>
                            </div>
                            
                            <div class="custom-file" style="max-width:300px;">
                                <input type="file" class="custom-file-input" id="customFile" multiple @change="handleMultiFileUpload( $event )" >
                                <label class="custom-file-label" for="customFile">{{$t("choose_files")}}</label>
                            </div>

                        </v-card>

                        <v-card class="files-container mt-3 mb-3 elevation-2" v-if="files.length>0" >
                            <h5 class="mb-1 pt-2 pl-3">{{files.length}} {{$t("selected")}}</h5>
                            <v-simple-table class="table-striped">
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                    <th class="text-left">
                                        File
                                    </th>
                                    <th class="text-left">
                                        Size
                                    </th>
                                    <th>
                                    </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(file,file_index) in files" :key="file.name">
                                    <td>{{ file.name }}</td>
                                    <td>{{ file.size | kbmb }}</td>
                                    <td><button class="float-right" @click="removeFile(file_index)" title="Remove"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                </tbody>
                                </template>
                            
                            </v-simple-table>

                        </v-card>


                        <div class="mt-5 mb-3" v-if="files && files.length>0">
                            <v-alert
                                border="left"
                                colored-border                                
                                type="warning"
                                elevation="2"                                
                                >
                                    <div v-html='$t("data_upload_notice")'></div>
                                    
                                    <template>
                                                                                    
                                            <v-radio-group
                                            v-model="keep_data"
                                            
                                            >
                                            <v-radio
                                                value="store"
                                            >
                                                <template v-slot:label>
                                                <div class="font-weight-regular">{{$t("Store data")}}</div>
                                                </template>
                                            </v-radio>
                                            <v-radio                                                
                                                value="remove"
                                            >
                                            <template v-slot:label>
                                                <div class="font-weight-regular">{{$t("Do not store data")}}</div>
                                                </template>
                                            </v-radio>
                                            </v-radio-group>
                                        
                                        </template>

                            </v-alert>
                           
                        </div>

                        <div>                        
                            <v-checkbox v-model="overwrite_if_exists">
                                <template v-slot:label>
                                <div class="font-weight-regular">{{$t('Overwrite (if file already exists)')}}</div>
                                </template>
                            </v-checkbox>
                        </div>
                                                
                        <div xv-if="update_status==''">
                            <v-btn color="primary" :disabled="isImportDisabled"  @click="processImport">{{$t("import")}}</v-btn>
                        </div>
                        
                    </div>


                    </v-card-text>
                </v-card>



            <v-dialog v-model="dialog_process" width="700" height="600" persistent style="z-index:5000">
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
                                <div class="col">
                                    <div style="color:red;" v-if="report.error && report.error.response && report.error.response.data">
                                        <div v-if="report.error.response.data.message">{{report.error.response.data.message}}</div>
                                        <div v-else>{{report.error.response.data}}</div>
                                    </div>
                                    <div style="color:red;" v-else-if="report.error">{{report.error}}</div>
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