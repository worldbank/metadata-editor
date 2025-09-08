Vue.component('datafiles', {
    data() {
        return {
            showChildren: true,
            dataset_id:project_sid,
            dataset_idno:project_idno,
            dataset_type:project_type,
            dialog_datafile_import:false,
            dialog_datafile_import_fid:null,
            form_errors:[],
            schema_errors:[],
            page_action:'list',
            edit_item:null,
            selected_files:[],
            select_all_files:false,
            dialog:{
                show:false,
                title:'',
                loading_message:'',
                message_success:'',
                message_success_html:'',
                download_links:[],
                message_error:'',
                is_loading:false
            },
            export_dialog:{
                show:false,
                file_index:null,
                selected_format:''
            },
            attrs: {}
            
        }
    }, 
    mounted: function () {
    },  
    watch: {
        'data_files': function(newVal,oldVal) {
            if (oldVal.length<1){
                return;
            }
            this.updateDataFilesWeight();
        },
    }, 
    methods: {
        momentDate(date) {
            return moment.unix(date).format("YYYY-MM-DD")
        },
        removeData: async function(data_file){
            if (!confirm(this.$t("confirm_remove_data"))){
                data_file.store_data=1;
                return false;
            }

            //save file, set store_data to 0
            data_file.store_data=0;
            let result=await this.saveFile(data_file);

            vm=this;
            let url=CI.base_url + '/api/datafiles/cleanup/'+ vm.dataset_id;
            let formData=new FormData();            

            await axios.post( url, formData,
            ).then(function(response){                
            })
            .catch(function(response){
                console.log(response);
                alert(vm.$t("failed")+": "+ response.message);
            });
        },

        editFile:function(file_id){
            //this.page_action="edit";
            //this.edit_item=file_id;
            let file_=this.data_files[file_id];

            if (!file_) return;

            router.push('/datafile/'+file_.file_id);
        },
        addFile:function(){
            this.page_action="edit";
            this.$store.commit('data_files_add',{file_name:'untitled'});
            newIdx=this.data_files.length -1;
            this.edit_item=newIdx;
        },
        saveFile: async function(data)
        {            
            vm=this;
            let url=CI.base_url + '/api/datafiles/'+vm.dataset_id;
            form_data=data;

            axios.post(url, 
                form_data
            )
            .then(function (response) {
                vm.$store.dispatch('loadDataFiles',{dataset_id:vm.dataset_id});
            })
            .catch(function (error) {
                console.log(error);
                let message='';
                if (error.response.data.message){
                    message=error.response.data.message;
                }else{
                    message=error.message;
                }
                alert(vm.$t("failed")+": "+ message);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        updateDataFilesWeight: function()
        {
            vm=this;
            let url=CI.base_url + '/api/datafiles/sequence/'+vm.dataset_id;
            let form_data={};
            form_data.options=this.getRowSequence(this.data_files);

            axios.post(url, 
                form_data
            )
            .then(function (response) {
                console.log("updating",response);
            })
            .catch(function (error) {
                console.log("failed to update datafiles sequence",error);
                alert(vm.$t("failed")+": "+ error.message);
            })            
        },
        getRowSequence: function(rows){
            let seq=[];
            for (let i=0;i<rows.length;i++){
                seq.push(
                    {
                       'id': rows[i]['id'],
                        'wght': i
                    });
            }
            return seq;
        },        
        replaceFile:function(file_idx){
            let data_file=this.data_files[file_idx];
            this.dialog_datafile_import_fid=data_file.file_id;
            this.dialog_datafile_import=true;
        },
        exportFile: async function(file_idx,format){
            let data_file=this.data_files[file_idx];

            this.dialog={
                show:true,
                title:this.$t('export_file') + '[' + format + ']',
                loading_message:this.$t('processing_please_wait'),
                message_success:'',
                message_error:'',
                is_loading:true
            }

            try{
                //add to queue
                let result=await this.$store.dispatch('exportDatafileQueue',{file_id:data_file.file_id, format:format});
                console.log("queued for export",result);
                this.exportFileStatusCheck(data_file.file_id,result.data.job_id,format);
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error=this.$t("failed")+": "+e.response.data.message;                
            }
        },        
        exportFileStatusCheck: async function(file_id,job_id,format){
                this.dialog={
                    show:true,
                    title:'',
                    loading_message:'',
                    message_success:'',
                    message_error:'',
                    is_loading:false
                }
    
                this.dialog.is_loading=true;
                this.dialog.title=this.$t('export_file');
                this.dialog.loading_message=this.$t('processing_please_wait');
                try{
                    await this.sleep(5000);
                    let result=await this.$store.dispatch('getJobStatus',{job_id:job_id});
                    
                    this.dialog.is_loading=true;
                    this.dialog.loading_message="Job status: " + result.data.job_status;
                    if (result.data.job_status!=='done'){
                        this.exportFileStatusCheck(file_id,job_id,format);
                    }else if (result.data.job_status==='done'){
                        this.dialog.is_loading=false;                        
                        let download_url=CI.base_url + '/api/datafiles/download_tmp_file/'+this.dataset_id + '/' + file_id + '/' + format;
                        this.dialog.message_success=this.$t('file_generated_success');
                        this.dialog.download_links=[];
                        this.dialog.download_links.push({url:download_url,title:this.$t('download_file') + ' [' + format + ']'});
                        //window.open(download_url, '_blank').focus();
                    }
                    
                }catch(e){
                    console.log("failed",e);
                    this.dialog.is_loading=false;
                    this.dialog.message_error=this.$t("failed")+": "+e.response.data.message;
                }
        },
        batchDelete: async function() {
            if (!confirm(this.$t("confirm_delete_selected"))) {
                return;
            }
        
            let deletionPromises = this.selected_files.map(async (file_id) => {                
                try {
                    await this.deleteFileByFileId(file_id);
                } catch (error) {
                    console.error(`Error deleting file with ID ${file_id}:`, error);
                    // Optionally, handle the error, e.g., by notifying the user
                }
            });
        
            await Promise.all(deletionPromises);
            this.reloadDataFiles();
            this.selected_files = [];
        },
        deleteFileByFileId: async function(file_id)
        {
            vm=this;
            let url=CI.base_url + '/api/datafiles/delete/'+vm.dataset_id + '/'+ file_id;
            form_data={};

            try {
                await axios.post(url, form_data);
                return true;
            } catch (error) {
                console.log(error);
                return error; // Return the error object in case of failure
            }
        },
        deleteFile:function(file_idx,confirm_=false)
        {
            let data_file=this.data_files[file_idx];

            if (confirm_==false && !confirm(this.$t("confirm_delete") + " " + data_file.file_id + "?")){
                return;
            }

            vm=this;
            let url=CI.base_url + '/api/datafiles/delete/'+vm.dataset_id + '/'+ data_file.file_id;
            form_data={};

            axios.post(url, 
                form_data
            )
            .then(function (response) {
                vm.data_files.splice(file_idx, 1);
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("failed")+": "+ error.message);
            });                        
        },
        exitEditMode: function()
        {
            this.page_action="list";
            this.edit_item=null;
        },
        hasCsvFile: function (file_id){            
            for (let i=0;i<this.data_files.length;i++){
                if (this.data_files[i].file_id==file_id){
                    let file_=this.data_files[i];

                    if (file_.file_info && file_.file_info.csv && file_.file_info.csv.file_exists && file_.file_info.csv.file_exists==true){
                        return true;
                    }
                }
            }
            return false;
        },
        importSummaryStatistics: async function(file_id)
        {                        
            if (!confirm(this.$t("confirm_import_summary_statistics"))){
                return;
            }

            if (!this.hasCsvFile(file_id)){
                await this.generateCSV(file_id);
            }

            this.dialog={
                show:true,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            }

            this.dialog.is_loading=true;
            this.dialog.title=this.$t("summary_stats");
            this.dialog.loading_message=this.$t("processing_please_wait");
            try{
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueue',{file_id:file_id});
                console.log("updated",result);
                this.dialog.loading_message="Queued for import..." + result.data.message;
                this.importSummaryStatisticsQueueStatusCheck(file_id,result.data.job_id);
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error=this.$t("failed") +  ": " + e.response.data.message;
            }
        },
        importSummaryStatisticsQueueStatusCheck: async function(file_id,job_id){

            this.dialog={
                show:true,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            }

            this.dialog.is_loading=true;
            this.dialog.title=this.$t("summary_stats");
            this.dialog.loading_message=this.$t("processing_please_wait");
            try{
                await this.sleep(5000);
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueueStatusCheck',{file_id:file_id, job_id:job_id});
                console.log("job updated",result);
                this.dialog.is_loading=true;
                this.dialog.loading_message="Job status: " + result.data.job_status;
                if (result.data.job_status!=='done'){
                    this.importSummaryStatisticsQueueStatusCheck(file_id,job_id);
                }else if (result.data.job_status==='done'){
                    await this.reloadDataFileVariables(file_id);
                    await this.reloadDataFiles();
                    this.dialog.is_loading=false;
                    this.dialog.message_success=this.$t("sum_stats_imported_success");
                }
                
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to import summary statistics: "+e.response.data.message;
            }
        },
        reloadDataFileVariables: async function(file_id){
            return await this.$store.dispatch('loadVariables',{dataset_id:this.dataset_id, fid:file_id});
        },
        reloadDataFiles: async function(){
            await store.dispatch('loadDataFiles',{dataset_id:this.dataset_id});
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        generateCSV: async function(file_id)
        {
            this.dialog={
                show:true,
                title:this.$t('generate_csv_file'),
                loading_message:this.$t('processing_please_wait'),
                message_success:'',
                message_error:'',
                is_loading:true
            }

            try{
                let result=await this.$store.dispatch('generateCsvQueue',{file_id:file_id});
                console.log("updated",result);
                await this.generateCsvQueueStatusCheck(file_id,result.data.job_id);
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to generate CSV file: "+e.response.data.message;                
            }
        },
        generateCsvQueueStatusCheck: async function(file_id,job_id)
        {
            this.dialog={
                show:true,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            }

            this.dialog.is_loading=true;
            this.dialog.title=this.$t('generate_csv_file');
            this.dialog.loading_message=this.$t('processing_please_wait');
            try{
                await this.sleep(5000);
                let result=await this.$store.dispatch('generateCsvQueueStatusCheck',{file_id:file_id, job_id:job_id});
                console.log("csv updated",result);
                this.dialog.is_loading=true;
                this.dialog.loading_message="Job status: " + result.data.job_status;
                if (result.data.job_status!=='done'){
                    this.generateCsvQueueStatusCheck(file_id,job_id);
                }else if (result.data.job_status==='done'){
                    this.dialog.is_loading=false;
                    this.dialog.message_success=this.$t('csv_generated_success');                
                }
                
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error=this.$t("failed")+": "+e.response.data.message;
            }
        },
        toggleFilesSelection: function()
        {
            this.selected_files = [];
          if (this.select_all_files == true) {
            for (i = 0; i < this.data_files.length; i++) {
              this.selected_files.push(this.data_files[i].file_id);
            }
          }
        },
        onDatafileReplaced: function(event){
            this.dialog_datafile_import=false;            
            this.importSummaryStatistics(this.dialog_datafile_import_fid);
        },
        openExportDialog: function(file_index){
            this.export_dialog.file_index = file_index;
            this.export_dialog.selected_format = '';
            this.export_dialog.show = true;
        },
        confirmExport: function(){
            if (!this.export_dialog.selected_format) {
                return;
            }
            this.exportFile(this.export_dialog.file_index, this.export_dialog.selected_format);
            this.export_dialog.show = false;
        }
    },
    computed: {
        data_files(){
            return this.$store.state.data_files;
          },
    },
    template: `
        <div class="datfiles-component">
        
            <div class="container-fluid pt-5 mt-5 mb-5 pb-5">

            <v-card>
                <v-card-title>{{$t("data-files")}}</v-card-title>
                <v-card-text>
            <div v-show="page_action=='list'">

            <strong>{{data_files.length}}</strong> {{$t("files")}}

                <v-row>
                    <v-col md="8">
                    <button v-if="selected_files.length>0" type="button" class="btn btn-sm btn-outline-danger" @click="batchDelete">{{$t("Delete")}} {{selected_files.length}} {{$t("selected")}}</button>
                    
                    </v-col>
                    <v-col md="4" align="right" class="mb-2">
                        <v-btn color="primary" :to="'datafiles/import'" outlined small>{{$t("import_files")}}</v-btn>
                    </v-col>
                </v-row>

                
                <table class="table table-striped" v-if="data_files.length>0">
                    <thead>
                    <tr>
                        <th><input type="checkbox" v-model="select_all_files" @change="toggleFilesSelection" /></th>
                        <th><span class="mdi mdi-swap-vertical"></span></th>
                        <th style="width:80px;">{{$t("file")}}#</th>
                        <th>{{$t("file_name")}}</th>
                        <th>{{$t("variables")}}</th>
                        <th>{{$t("cases")}}</th>
                        <th>{{$t("Modified")}}</th>
                        <th style="width: 50px;">{{$t("Actions")}}</th>
                    </tr>
                    </thead>
                    <tbody is="draggable" :list="data_files" tag="tbody" handle=".handle" >
                    <tr v-for="(data_file, index) in data_files" :key="data_file.file_id">
                        <td><input type="checkbox" v-model="selected_files" :value="data_file.file_id" /></td>
                        <td><v-icon class="handle">mdi-drag</v-icon></td>
                        <td><v-icon color="primary" >mdi-file-document</v-icon> {{data_file.file_id}}</td>
                        <td>
                            <div>
                                <div class="d-flex align-center">
                                    <div v-if="hasCsvFile(data_file.file_id) || data_file.store_data==1" 
                                         class="mr-2" 
                                         style="width: 8px; height: 8px; border-radius: 50%; background-color: #4CAF50;"
                                         :title="$t('Has data')">
                                    </div>
                                    <div v-else 
                                         class="mr-2" 
                                         style="width: 8px; height: 8px; border-radius: 50%; background-color: #9E9E9E;"
                                         :title="$t('No data')">
                                    </div>
                                    <div style="cursor:pointer;color:#0D47A1;font-weight:500" @click="editFile(index)">
                                        {{data_file.file_name}}
                                    </div>
                                </div>
                                <div class="text-secondary text-small mt-1" v-if="data_file.file_info.original">                                                                
                                    <span v-if="hasCsvFile(data_file.file_id)" >
                                    <v-chip small outlined>{{data_file.file_info.csv.filename}} {{data_file.file_info.csv.file_size}}</v-chip>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>{{data_file.var_count}}</td>
                        <td>{{data_file.case_count}}</td>                       
                        <td>{{momentDate(data_file.changed)}}</td>
                        <td>
                            <div class="zxaction-buttons-hover">
                                <v-menu offset-y>
                                    <template v-slot:activator="{ on, attrs }">                                        
                                            <v-btn small icon v-on="on" v-bind="attrs" 
                                                   :title="$t('More options')" 
                                                   color="primary">
                                                <v-icon>mdi-dots-vertical</v-icon>
                                            </v-btn>
                                    </template>
                                                                    
                                    <v-list dense>
                                        <!-- View/Edit Options -->
                                        <v-list-item @click="editFile(index)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-edit</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("edit")}}</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-list-item 
                                            :to="'/variables/' + data_file.file_id"
                                            :title="$t('Variables')">
                                            <v-list-item-icon>
                                                <v-icon>mdi-table</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("Variables")}}</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-list-item v-if="hasCsvFile(data_file.file_id) || data_file.store_data==1" 
                                            :to="'/data-explorer/' + data_file.file_id"
                                            :title="$t('data')">
                                            <v-list-item-icon>
                                                <v-icon>mdi-table-eye</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("data")}}</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-list-item v-else disabled
                                            :title="$t('data')">
                                            <v-list-item-icon>
                                                <v-icon>mdi-table-eye</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("data")}} ({{$t("No data")}})</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-divider></v-divider>
                                        
                                        <!-- Data Management -->
                                        <v-list-item v-if="hasCsvFile(data_file.file_id) || data_file.store_data==1" 
                                            @click="removeData(data_file)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-database-remove</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("clear_data")}}</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-list-item v-else disabled>
                                            <v-list-item-icon>
                                                <v-icon>mdi-database-remove</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("clear_data")}} ({{$t("No data")}})</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-list-item @click="importSummaryStatistics(data_file.file_id)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-update</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("Refresh summary statistics")}}</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-list-item @click="replaceFile(index)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-upload-outline</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("Replace file")}}</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-divider></v-divider>
                                        
                                        <!-- Export Options -->
                                        <v-list-item v-if="hasCsvFile(data_file.file_id) || data_file.store_data==1" 
                                            @click="openExportDialog(index)">
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-export</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("export")}}</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-list-item v-else disabled>
                                            <v-list-item-icon>
                                                <v-icon>mdi-file-export</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title>{{$t("export")}} ({{$t("No data")}})</v-list-item-title>
                                        </v-list-item>
                                        
                                        <v-divider></v-divider>
                                        
                                        <!-- Delete Option -->
                                        <v-list-item @click="deleteFile(index)" class="red--text">
                                            <v-list-item-icon>
                                                <v-icon color="red">mdi-delete-outline</v-icon>
                                            </v-list-item-icon>
                                            <v-list-item-title class="red--text">{{$t("Delete")}}</v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-menu>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
                
                
            </div>

            <div v-show="page_action=='edit'" >
                <div v-if="data_files[edit_item]">
                    <datafile-edit :value="data_files[edit_item]" @input="saveFile" @exit-edit="exitEditMode"></datafile-edit>                
                </div>
            </div>

            </v-card-text>
            </v-card>

            </div>

            <!-- dialog -->
            <v-dialog v-model="dialog.show" width="500" height="300" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{dialog.title}}
                    </v-card-title>

                    <v-card-text>
                    <div>
                        <!-- card text -->
                        <div v-if="dialog.is_loading">{{dialog.loading_message}}</div>
                        <v-app>
                        <v-progress-linear v-if="dialog.is_loading"
                            indeterminate
                            color="green"
                            ></v-progress-linear>
                        </v-app>

                        <div class="alert alert-success" v-if="dialog.message_success" type="success">
                            {{dialog.message_success}}
                        </div>

                        <div v-html="dialog.message_success_html" v-if="dialog.message_success_html"></div>

                        <div v-if="dialog.download_links">
                            <div v-for="link in dialog.download_links">
                                <v-btn color="primary" outlined block text><a :href="link.url">{{link.title}}</a></v-btn>                                
                            </div>
                        </div>

                        <div class="alert alert-danger" v-if="dialog.message_error" type="error">
                            {{dialog.message_error}}
                        </div>

                        <!-- end card text -->
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="dialog.show=false" v-if="dialog.is_loading==false">
                        {{$t("close")}}
                    </v-btn>
                    </v-card-actions>
                </v-card>
                </v-dialog>
            <!-- end dialog -->           

            <dialog-datafile-replace 
                v-model="dialog_datafile_import" 
                :file_id="dialog_datafile_import_fid"
                v-on:file-replaced="onDatafileReplaced"
            ></dialog-datafile-replace>

            <!-- Export Dialog -->
            <v-dialog v-model="export_dialog.show" width="400" persistent>
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$t("export")}}
                    </v-card-title>

                    <v-card-text>
                        <div class="mb-4">
                            <p>{{$t("select_export_format")}}</p>
                        </div>
                        
                        <v-radio-group v-model="export_dialog.selected_format" mandatory>
                            <v-radio value="sav" label="SPSS (.sav)"></v-radio>
                            <v-radio value="dta" label="Stata (.dta)"></v-radio>
                            <v-radio value="csv" :label="$t('export_csv')"></v-radio>
                            <v-radio value="json" label="JSON"></v-radio>
                            <v-radio value="xpt" label="SAS (.xpt)"></v-radio>
                        </v-radio-group>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="grey" text @click="export_dialog.show=false">
                            {{$t("cancel")}}
                        </v-btn>
                        <v-btn color="primary" text @click="confirmExport" :disabled="!export_dialog.selected_format">
                            {{$t("export")}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        
        </div>
    `
})