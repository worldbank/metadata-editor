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
                message_error:'',
                is_loading:false
            }
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
        editFile:function(file_id){
            this.page_action="edit";
            this.edit_item=file_id;
        },
        addFile:function(){
            this.page_action="edit";
            this.$store.commit('data_files_add',{file_name:'untitled'});
            newIdx=this.data_files.length -1;
            this.edit_item=newIdx;
        },
        saveFile: function(data)
        {            
            vm=this;
            let url=CI.base_url + '/api/datafiles/'+vm.dataset_id;
            form_data=data;

            axios.post(url, 
                form_data
                /*headers: {
                    "name" : "value"
                }*/
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
                alert("Failed: "+ message);
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
                alert("Failed: "+ error.message);
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
        batchDelete: function()
        {
            if (!confirm(this.$t("confirm_delete_selected"))){
                return;
            }
            
            let vm=this;
            
            this.selected_files.forEach(function(file_id){
                let file_idx=vm.data_files.findIndex(function(item){return item.file_id==file_id});
                vm.deleteFile(file_idx,true);
            });

            this.selected_files=[];
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
                    console.log("export status",result);
                    this.dialog.is_loading=true;
                    this.dialog.loading_message="Job status: " + result.data.job_status;
                    if (result.data.job_status!=='done'){
                        this.exportFileStatusCheck(file_id,job_id,format);
                    }else if (result.data.job_status==='done'){
                        this.dialog.is_loading=false;
                        this.dialog.message_success=this.$t('finished_processing');

                        let download_url=CI.base_url + '/api/datafiles/download_tmp_file/'+this.dataset_id + '/' + file_id + '/' + format;
                        window.open(download_url, '_blank').focus();
                    }
                    
                }catch(e){
                    console.log("failed",e);
                    this.dialog.is_loading=false;
                    this.dialog.message_error=this.$t("failed")+": "+e.response.data.message;
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
                alert("Failed to delete: "+ error.message);
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
                title:'Generate CSV file',
                loading_message:'Please wait while the CSV file is being generated...',
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
            this.dialog.title="Generate CSV file";
            this.dialog.loading_message="Please wait while the CSV file is being generated...";
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
                    this.dialog.message_success="Finished generating CSV file";                
                }
                
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to generate CSV file: "+e.response.data.message;
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
                    <button v-if="selected_files.length>0" type="button" class="btn btn-sm btn-outline-danger" @click="batchDelete">Delete {{selected_files.length}} selected</button>
                    
                    </v-col>
                    <v-col md="4" align="right" class="mb-2">
                        <v-btn color="primary" :to="'datafiles/import'" outlined small>{{$t("import_files")}}</v-btn>
                    </v-col>
                </v-row>
                
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th><input type="checkbox" v-model="select_all_files" @change="toggleFilesSelection" /></th>
                        <th><span class="mdi mdi-swap-vertical"></span></th>
                        <th style="width:80px;">{{$t("file")}}#</th>
                        <th>{{$t("file_name")}}</th>
                        <th>{{$t("variables")}}</th>
                        <th>{{$t("cases")}}</th>                        
                    </tr>
                    </thead>
                    <tbody is="draggable" :list="data_files" tag="tbody" handle=".handle" >
                    <tr v-for="(data_file, index) in data_files" :key="data_file.file_id">
                        <td><input type="checkbox" v-model="selected_files" :value="data_file.file_id" /></td>
                        <td><span title="Drag to re-order" class="mdi mdi-drag handle"></span></td>
                        <td><i class="far fa-file-alt"></i> {{data_file.file_id}}</td>
                        <td>
                            <div>
                                <h5 style="cursor:pointer;color:#0D47A1"  @click="editFile(index)">{{data_file.file_name}}</h5>
                                <v-icon style="color:red;margin-top:-4px;" title="Physical file not found" v-if="!data_file.file_info.original">mdi-alert-circle</v-icon></div>
                                <div class="text-secondary text-small" v-if="data_file.file_info.original">                            
                                    <!-- <span v-if="data_file.file_info.original.file_exists" class="mr-3">
                                        <span>{{data_file.file_info.original.filename}}</span>
                                        <span>{{data_file.file_info.original.file_size}}</span> -->
                                    </span>
                                    <span v-if="data_file.file_info.csv.file_exists" >{{data_file.file_info.csv.filename}} {{data_file.file_info.csv.file_size}}</span>
                                </div>

                            <div class="mt-2 datafile-actions">                                
                                <router-link :to="'/variables/' + data_file.file_id"><button type="button" class="btn btn-sm btn-default"><v-icon>mdi-table</v-icon> {{$t("variables")}}</button></router-link>
                                <router-link :to="'/data-explorer/' + data_file.file_id"><button type="button" class="btn btn-sm btn-default"><v-icon>mdi-table-eye</v-icon> {{$t("preview")}}</button></router-link>
                                <span v-if="data_file.file_info.original">
                                <button type="button" class="btn btn-sm btn-link ink ml-0 pl-0" @click="importSummaryStatistics(data_file.file_id)"><v-icon title="Refresh summary statistics" >mdi-update</v-icon> {{$t("refresh_stats")}}</button>
                                <!-- <button type="button" class="btn btn-sm btn-link ink ml-0 pl-0" @click="generateCSV(data_file.file_id)"><v-icon title="Generate CSV" >mdi-file-delimited-outline</v-icon> {{$t("export_csv")}}</button> -->
                                </span>
                                <button type="button" class="btn btn-sm btn-link ink ml-0 pl-0" @click="deleteFile(index)"><v-icon>mdi-delete-outline</v-icon>{{$t("remove")}}</button>
                                <button type="button" class="btn btn-sm btn-link ink ml-0 pl-0" @click="replaceFile(index)"><v-icon>mdi-file-upload-outline</v-icon>{{$t("replace_file")}}</button>
                                                                    
                                <v-menu offset-y>
                                    <template v-slot:activator="{ on, attrs }">
                                        <button type="button" class="btn btn-sm btn-link ink ml-0 pl-2"  v-bind="attrs" v-on="on">
                                            <v-icon title="More options">mdi-export</v-icon> {{$t("export")}} <v-icon title="More options">mdi-dots-vertical</v-icon>
                                        </button>                                                
                                    </template>
                                    <v-list>
                                        <v-list-item @click="exportFile(index,'sav')">
                                            <v-list-item-title>SPSS</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item  @click="exportFile(index,'dta')">
                                            <v-list-item-title>Stata</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item  @click="exportFile(index,'csv')">
                                            <v-list-item-title>CSV</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item  @click="exportFile(index,'json')">
                                            <v-list-item-title>JSON</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item  @click="exportFile(index,'xpt')">
                                            <v-list-item-title>SAS</v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-menu>

                            </div>
                        </td>
                        <td>{{data_file.var_count}}</td>
                        <td>{{data_file.case_count}}</td>
                        <td style="display:none;">
                            <div>                                
                                <button type="button" class="btn btn-sm btn-link" @click="deleteFile(index)"><i class="fas fa-trash-alt" title="Delete"></i></button>
                                <router-link :to="'/variables/' + data_file.file_id"><button type="button" class="btn btn-sm btn-link"><i class="fas fa-table"></i> {{$t("variables")}}</button></router-link>
                                <router-link :to="'/data-explorer/' + data_file.file_id"><button type="button" class="btn btn-sm btn-link"><i class="fas fa-table"></i> {{$t("data")}}</button></router-link>
                                <v-icon title="Refresh summary statistics" @click="importSummaryStatistics(data_file.file_id)">mdi-update</v-icon>
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

                        <div class="alert alert-danger" v-if="dialog.message_error" type="error">
                            {{dialog.message_error}}
                        </div>

                        <!-- end card text -->
                    </div>
                    </v-card-text>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="dialog.show=false" v-if="dialog.is_loading==false">
                        Close
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
        
        </div>
    `
})