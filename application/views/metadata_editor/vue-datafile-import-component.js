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
                "DTA": this.$t("Stata (DTA)"),
                "SAV": this.$t("SPSS (SAV)"),
                "CSV": this.$t("CSV")
            },
            allowed_file_types:["dta","sav","csv"],
            /** Cleared after each pick so the list below is the source of truth */
            filePickerModel: null,
            dialog_process:false,
            keep_data:'store', // store | remove — default store
            /** Resumable upload: determinate bar during chunks; otherwise indeterminate */
            show_upload_chunk_progress:false,
            upload_chunk_percent:0,
            current_upload_filename:'',
            import_dialog_phase:'idle',
            current_import_file_index:0,
            total_import_files:0
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
        },
        topLevelErrorText: function () {
            if (!this.errors) {
                return '';
            }
            var e = this.errors;
            if (e instanceof Error) {
                return e.message || String(e);
            }
            if (typeof e === 'string') {
                return e;
            }
            return String(e);
        }
    },
    methods:{

        dialogClose: function(){
            this.dialog_process = false;
            this.import_dialog_phase = 'idle';

            if (this.has_errors==false){
                this.$router.push('/datafiles');
            }
        },

        importReportStatusBadgeStyle: function (report) {
            var ok = report && report.status === 'success';
            return {
                backgroundColor: ok ? '#2E7D32' : '#C62828',
                color: '#FFFFFF',
                borderRadius: '4px',
                fontSize: '11px',
                fontWeight: '600',
                padding: '2px 8px',
                lineHeight: '18px',
                textTransform: 'uppercase',
                display: 'inline-flex',
                alignItems: 'center',
                flexShrink: '0'
            };
        },

        getReportErrorDetail: function (report) {
            if (!report || !report.error) {
                return '';
            }
            var e = report.error;
            if (e.response && e.response.data) {
                var d = e.response.data;
                if (d && typeof d.message === 'string') {
                    return d.message;
                }
                if (typeof d === 'string') {
                    return d;
                }
                try {
                    return JSON.stringify(d);
                } catch (err) {
                    return String(d);
                }
            }
            if (e.message) {
                return e.message;
            }
            if (typeof e === 'string') {
                return e;
            }
            try {
                return JSON.stringify(e);
            } catch (err2) {
                return String(e);
            }
        },

        processImport: async function()
        {
            this.errors='';
            this.has_errors=false;
            this.is_processing=true;
            this.upload_report=[];            
            this.dialog_process=true;
            this.show_upload_chunk_progress=false;
            this.upload_chunk_percent=0;
            this.current_upload_filename='';
            this.import_dialog_phase='working';
            this.total_import_files=this.files.length;
            this.current_import_file_index=0;
            this.update_status='';

            try{
                let service_status=await this.pingDataService();
            }
            catch (error) {
                this.is_processing=false;                
                this.errors = error instanceof Error ? error.message : (error && error.message) ? error.message : String(error);
                this.has_errors=true;
                this.import_dialog_phase='error';
                this.show_upload_chunk_progress=false;
                this.upload_chunk_percent=0;

                this.upload_report.push({
                    'file_name': this.$t("Data service error"),
                    'status': 'error',
                    'error': error
                });
                return false;    
            }

            let csvJobs = [];

            for(i=0;i<this.files.length;){
                this.current_import_file_index = i + 1;
                let result = await this.processFile(i);
                if (result && result.csvJob) {
                    csvJobs.push(result.csvJob);
                }
                i++;
            }

            if (csvJobs.length > 0) {
                this.update_status=this.$t("Waiting for CSV generation to complete...");
                await this.waitForAllCsvJobs(csvJobs);
            }

            this.update_status="completed";
            this.is_processing=false;
            this.show_upload_chunk_progress=false;
            this.upload_chunk_percent=0;
            this.current_upload_filename='';
            this.import_dialog_phase = this.has_errors ? 'partial' : 'success';
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
            try{
                let resp=await this.uploadFile(fileIdx);
                let fileid=resp.result.file_id;
                
                if (!fileid){
                    throw new Error('File upload failed for file ' + this.files[fileIdx].name);
                }

                this.update_status=this.$t("Generating summary statistics and frequencies") + " " + this.files[fileIdx].name;
                let stats_resp=await this.importDataFileSummaryStatistics(fileIdx, fileid);

                let csvJob = null;
                if (this.keep_data=='store'){
                    if (!this.files[fileIdx].type.match('csv.*')) {
                        this.update_status=this.$t("Exporting data to CSV") + " " + this.files[fileIdx].name;
                        csvJob = await this.generateCSV(fileIdx,fileid);                    
                    }
                }

                this.upload_report.push(
                    {
                        'file_name':this.files[fileIdx].name,
                        'status': 'success'
                    }
                );

                return {
                    fileIdx: fileIdx,
                    fileid: fileid,
                    csvJob: csvJob
                };

            } catch (error) {
                this.has_errors=true;
                this.show_upload_chunk_progress=false;
                this.upload_chunk_percent=0;
                this.upload_report.push({
                        'file_name':this.files[fileIdx].name,
                        'status': 'error',
                        'error': error
                });
                return {
                    fileIdx: fileIdx,
                    fileid: null,
                    csvJob: null,
                    error: error
                };
            }
        },
        uploadFile: async function (fileIdx)
        {
            const file = this.files[fileIdx];
            if (typeof ResumableChunkUploader === 'undefined') {
                throw new Error('ResumableChunkUploader is not loaded');
            }

            const vm = this;
            this.current_upload_filename = file.name;
            this.show_upload_chunk_progress = false;
            this.upload_chunk_percent = 0;
            this.update_status =
                this.$t('import') +
                ': ' +
                file.name +
                ' — ' +
                this.$t('data_import_preparing_upload');

            const chunkResult = await ResumableChunkUploader.uploadFileChunks(file, {
                projectId: this.ProjectID,
                fileType: 'data',
                maxRetries: 3,
                retryDelay: 1000,
                exponentialBackoff: true,
                onInitializing: function (isInit) {
                    vm.show_upload_chunk_progress = false;
                    if (isInit) {
                        vm.upload_chunk_percent = 0;
                        vm.update_status =
                            vm.$t('import') +
                            ': ' +
                            file.name +
                            ' — ' +
                            vm.$t('data_import_preparing_upload');
                    }
                },
                onProgress: function (p) {
                    vm.show_upload_chunk_progress = true;
                    vm.upload_chunk_percent = p.progress;
                    vm.update_status =
                        vm.$t('import') +
                        ': ' +
                        file.name +
                        ' — ' +
                        p.uploaded_chunks +
                        '/' +
                        p.total_chunks +
                        ' (' +
                        p.progress +
                        '%)';
                }
            });

            this.show_upload_chunk_progress = true;
            this.upload_chunk_percent = 100;
            this.update_status =
                this.$t('import') +
                ': ' +
                file.name +
                ' — ' +
                this.$t('data_import_saving_file');

            const formData = new FormData();
            formData.append('upload_id', chunkResult.upload_id);
            formData.append('store_data', this.keep_data);
            if (this.overwrite_if_exists) {
                formData.append('overwrite', '1');
            }

            const url = CI.base_url + '/api/data/datafile/' + this.ProjectID;
            const resp = await axios.post(url, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            });

            this.show_upload_chunk_progress = false;
            this.upload_chunk_percent = 0;

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

            if (result.data.job_status === 'failed' || result.data.job_status === 'error') {
                const msg = result.data.message || (typeof result.data.detail === 'string' ? result.data.detail : '') || 'Job failed';
                throw new Error(msg);
            }
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
            return {
                file_id: file_id,
                job_id: result.data.job_id,
                file_name: this.files[fileIdx].name
            };
        },         
        generateCsvQueueStatusCheck: async function(file_id,job_id){
            await this.sleep(this.sleep_time);
            let result=await this.$store.dispatch('generateCsvQueueStatusCheck',{file_id:file_id, job_id:job_id});

            if (result.data.job_status === 'failed' || result.data.job_status === 'error') {
                const msg = result.data.message || (typeof result.data.detail === 'string' ? result.data.detail : '') || 'Job failed';
                throw new Error(msg);
            }
            if (result.data.job_status!=='done'){
                return await this.generateCsvQueueStatusCheck(file_id,job_id);
            }else if (result.data.job_status==='done'){
                return true;
            }                
        },
        waitForAllCsvJobs: async function(csvJobs)
        {
            for (let i = 0; i < csvJobs.length; i++) {
                const job = csvJobs[i];
                
                try {
                    await this.generateCsvQueueStatusCheck(job.file_id, job.job_id);
                } catch (error) {
                    // Continue with other jobs even if one fails
                }
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
            this.files.splice(file_idx, 1);
        },        
        handleFileUpload(event)
        {
            this.file = event.target.files[0];
            this.errors='';
            this.checkFileExists();
        },
        handleMultiFileUpload: function (picked) {
            var source = picked !== undefined && picked !== null ? picked : this.filePickerModel;
            var list = [];
            if (!source) {
                return;
            }
            if (Array.isArray(source)) {
                list = source;
            } else if (source.target && source.target.files) {
                list = Array.prototype.slice.call(source.target.files);
            } else if (source instanceof File) {
                list = [source];
            }
            for (var i = 0; i < list.length; i++) {
                var f = list[i];
                if (f && !this.checkFileDuplicate(f.name) && this.isAllowedFileType(f.name)) {
                    this.files.push(f);
                }
            }
            var vm = this;
            this.$nextTick(function () {
                vm.filePickerModel = null;
            });
        },
        checkFileExists: async function()
        {
            let url=CI.base_url + '/api/datafiles/by_name/'+this.ProjectID+'?filename='+this.file.name;
            this.errors='';
            vm=this;

            try {
                const resp = await axios.get(url);                
                vm.errors=this.$t("File with the name already exists.");
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
            <div class="datafile-import-component mt-3" style="width:100%;max-width:100%;box-sizing:border-box;">
                <v-container fluid class="container-fluid pa-0 pt-4 pb-6 px-4" style="width:100%;max-width:100%;">

                <v-card class="elevation-1" style="width:100%;max-width:100%;">
                    <v-card-title>{{$t("import_data_files")}}</v-card-title>
                    <v-card-text>

                    <div class="form-container-x" style="width:100%;max-width:100%;">

                        <p class="text-body-1">{{$t("upload_one_or_more_data_files")}}</p>

                        <v-card @drop.prevent="addFile" @dragover.prevent outlined class="pa-4 mb-4 grey lighten-5" style="width:100%;max-width:100%;">
                            <v-row align="center" justify="center" no-gutters>
                                <v-col cols="12" class="text-center mb-3">
                                    <v-icon size="48" color="primary">mdi-upload</v-icon>
                                    <div class="text-subtitle-1 font-weight-medium mt-2">{{$t("drag_drop_data_files")}}</div>
                                </v-col>
                                <v-col cols="8">
                                    <v-file-input
                                        v-model="filePickerModel"
                                        multiple
                                        show-size
                                        prepend-icon="mdi-paperclip"
                                        :label="$t('choose_files')"
                                        @change="handleMultiFileUpload"
                                        hide-details="auto"
                                        outlined
                                        dense
                                        clearable
                                    ></v-file-input>
                                </v-col>
                            </v-row>
                        </v-card>

                        <v-card v-if="files.length>0" outlined class="mb-4" style="width:100%;max-width:100%;">
                            <v-card-subtitle class="pb-0">{{files.length}} {{$t("selected")}}</v-card-subtitle>
                            <v-simple-table dense>
                            <template v-slot:default>
                                <thead>
                                    <tr>
                                    <th class="text-left">{{$t("File")}}</th>
                                    <th class="text-left">{{$t("Size")}}</th>
                                    <th class="text-right" style="width:1%">{{$t("Remove")}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(file,file_index) in files" :key="file.name + '-' + file_index">
                                    <td>{{ file.name }}</td>
                                    <td>{{ file.size | kbmb }}</td>
                                    <td class="text-right">
                                        <v-btn icon small :aria-label="$t('Remove')" @click="removeFile(file_index)">
                                            <v-icon small color="error">mdi-delete-outline</v-icon>
                                        </v-btn>
                                    </td>
                                    </tr>
                                </tbody>
                            </template>
                            </v-simple-table>
                        </v-card>

                        <div class="mb-4" v-if="files && files.length>0">
                            <v-alert
                                border="left"
                                colored-border
                                type="warning"
                                elevation="1"
                                prominent
                                >
                                    <div v-html='$t("data_upload_notice")'></div>
                                    <v-radio-group v-model="keep_data" class="mt-2">
                                            <v-radio value="store">
                                                <template v-slot:label>
                                                <span class="text-body-2">{{$t("Store data")}}</span>
                                                </template>
                                            </v-radio>
                                            <v-radio value="remove">
                                            <template v-slot:label>
                                                <span class="text-body-2">{{$t("Do not store data")}}</span>
                                                </template>
                                            </v-radio>
                                    </v-radio-group>
                            </v-alert>
                        </div>

                        <v-checkbox v-model="overwrite_if_exists" hide-details class="mt-0">
                            <template v-slot:label>
                                <span class="text-body-2">{{$t('Overwrite (if file already exists)')}}</span>
                            </template>
                        </v-checkbox>

                        <div class="mt-4">
                            <v-btn color="primary" :disabled="isImportDisabled" @click="processImport">{{$t("import")}}</v-btn>
                        </div>

                    </div>

                    </v-card-text>
                </v-card>

            <v-dialog
                v-model="dialog_process"
                max-width="880"
                persistent
                scrollable
                content-class="datafile-import-dialog"
            >
                <v-card>
                    <v-card-title class="text-h6 font-weight-medium">
                        {{$t("import_data_files")}}
                    </v-card-title>

                    <v-card-text class="pt-4">
                        <div role="status" aria-live="polite">

                        <v-alert
                            v-if="import_dialog_phase === 'error' && topLevelErrorText"
                            type="error"
                            dense
                            outlined
                            prominent
                            class="mb-4"
                        >
                            {{ topLevelErrorText }}
                        </v-alert>

                        <template v-if="import_dialog_phase === 'working' && is_processing">
                            <div class="text-subtitle-1 text-center px-2">{{ update_status }}</div>
                            <div
                                v-if="total_import_files > 1"
                                class="text-caption text-center grey--text text--darken-1 mt-1"
                            >
                                {{ $t('data_import_file_progress', { current: current_import_file_index, total: total_import_files }) }}
                            </div>
                            <v-progress-linear
                                class="mt-4 rounded"
                                color="primary"
                                :indeterminate="is_processing && !show_upload_chunk_progress"
                                :value="show_upload_chunk_progress ? upload_chunk_percent : 0"
                                height="10"
                            >
                                <template v-if="show_upload_chunk_progress" v-slot:default="{ value }">
                                    <strong class="text-caption">{{ Math.ceil(value) }}%</strong>
                                </template>
                            </v-progress-linear>
                            <div
                                v-if="show_upload_chunk_progress && current_upload_filename"
                                class="text-caption text-center grey--text text--darken-1 mt-2 text-truncate"
                            >
                                {{ current_upload_filename }}
                            </div>
                        </template>

                        <div v-if="import_dialog_phase === 'success'" class="text-center py-2">
                            <v-icon
                                size="56"
                                aria-hidden="true"
                                style="color: #2E7D32 !important;"
                            >mdi-check-circle</v-icon>
                            <div class="text-h6 mt-3">{{ $t('import_completed') }}</div>
                        </div>

                        <div v-if="import_dialog_phase === 'partial'" class="text-center py-2">
                            <v-icon
                                size="56"
                                aria-hidden="true"
                                style="color: #EF6C00 !important;"
                            >mdi-alert-circle</v-icon>
                            <div class="text-h6 mt-3">{{ $t('import_completed') }}</div>
                        </div>

                        <div v-if="upload_report.length > 0" class="mt-2">
                            <v-card
                                v-for="(report, ridx) in upload_report"
                                :key="'r-' + ridx"
                                outlined
                                class="mb-2"
                            >
                                <div
                                    class="d-flex flex-wrap align-center py-1 px-2"
                                    style="column-gap:6px;row-gap:2px;"
                                >
                                    <span
                                        class="ma-0"
                                        :style="importReportStatusBadgeStyle(report)"
                                    >{{ report.status }}</span>
                                    <span class="text-body-2">{{ report.file_name }}</span>                                    
                                </div>
                                <template v-if="report.status === 'error'">
                                    <v-divider v-if="getReportErrorDetail(report)" class="my-0"></v-divider>
                                    <div class="px-2 py-1 error--text text-body-2" style="color: red;">
                                        {{ getReportErrorDetail(report) }}
                                    </div>
                                </template>
                            </v-card>
                        </div>

                        </div>
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text :disabled="is_processing" @click="dialogClose()">
                    {{$t("close")}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>

                </v-container>
            </div>
            `    
})