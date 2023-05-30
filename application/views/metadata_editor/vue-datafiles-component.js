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
            console.log("length",newVal.length,oldVal.length);
            console.log("data_files changed", JSON.stringify(this.getRowSequence(newVal)), JSON.stringify(this.getRowSequence(oldVal)));

            //update sequence
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
            console.log(this.data_files);
            //let new_idx=this.data_files.push({file_name:""}) -1;
            this.$store.commit('data_files_add',{file_name:'untitled'});
            newIdx=this.data_files.length -1;
            this.edit_item=newIdx;
        },
        saveFile: function(data)
        {
            console.log("saving file",data, this.edit_item);
            //this.$set(this.data_files, this.edit_item, data);
            
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
                console.log("updating",response);
                //vm.$set(vm.data_files, vm.edit_item, JSON.parse(JSON.stringify(data)));
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
            if (!confirm("Are you sure you want to delete the selected files?")){
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

            if (!confirm("Are you sure you want to replace file " + data_file.file_id + "?")){
                return;
            }
            this.dialog_datafile_import_fid=data_file.file_id;
            this.dialog_datafile_import=true;
        },
        exportFile: async function(file_idx,format){
            let data_file=this.data_files[file_idx];

            this.dialog={
                show:true,
                title:'Export file' + '[' + format + ']',
                loading_message:'Please wait while the file is being generated...',
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
                this.dialog.message_error="Failed to generate file: "+e.response.data.message;                
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
                this.dialog.title="Export file";
                this.dialog.loading_message="Please wait while the file is being generated...";
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
                        this.dialog.message_success="Finished exporting file";

                        let download_url=CI.base_url + '/api/datafiles/download_tmp_file/'+this.dataset_id + '/' + file_id + '/' + format;
                        window.open(download_url, '_blank').focus();
                    }
                    
                }catch(e){
                    console.log("failed",e);
                    this.dialog.is_loading=false;
                    this.dialog.message_error="Failed to export file: "+e.response.data.message;
                }
            },

        deleteFile:function(file_idx,confirm_=false)
        {
            let data_file=this.data_files[file_idx];

            if (confirm_==false && !confirm("Are you sure you want to delete file " + data_file.file_id + "?")){
                return;
            }

            vm=this;
            let url=CI.base_url + '/api/datafiles/delete/'+vm.dataset_id + '/'+ data_file.file_id;
            form_data={};

            axios.post(url, 
                form_data
                /*headers: {
                    "name" : "value"
                }*/
            )
            .then(function (response) {
                vm.data_files.splice(file_idx, 1);
            })
            .catch(function (error) {
                console.log(error);
                alert("Failed to delete: "+ error.message);
            })
            .then(function () {
                console.log("request completed");
            });

        },
        exitEditMode: function()
        {
            this.page_action="list";
            this.edit_item=null;
        },
        importSummaryStatistics: async function(file_id){

            if (!confirm("Are you sure you want to import summary statistics for this file? This will overwrite any existing summary statistics.")){
                return;
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
            this.dialog.title="Summary statistics";
            this.dialog.loading_message="Please wait while the summary statistics are being imported...";
            try{
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueue',{file_id:file_id});
                console.log("updated",result);
                this.dialog.loading_message="Queued for import..." + result.data.message;
                this.importSummaryStatisticsQueueStatusCheck(file_id,result.data.job_id);
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to import summary statistics: "+e.response.data.message;
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
            this.dialog.title="Summary statistics job status";
            this.dialog.loading_message="Please wait while the summary statistics are being imported...";
            try{
                await this.sleep(5000);
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueueStatusCheck',{file_id:file_id, job_id:job_id});
                console.log("job updated",result);
                this.dialog.is_loading=true;
                this.dialog.loading_message="Job status: " + result.data.job_status;
                if (result.data.job_status!=='done'){
                    this.importSummaryStatisticsQueueStatusCheck(file_id,job_id);
                }else if (result.data.job_status==='done'){
                    this.dialog.is_loading=false;
                    this.dialog.message_success="Summary statistics imported successfully";                
                }
                
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to import summary statistics: "+e.response.data.message;
            }
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        generateCSV: async function(file_id){

            if (!confirm("Are you sure you want to generate a CSV file for this file? This will overwrite any existing CSV file.")){
                return;
            }

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
                this.generateCsvQueueStatusCheck(file_id,result.data.job_id);
            }catch(e){
                console.log("failed",e);
                this.dialog.is_loading=false;
                this.dialog.message_error="Failed to generate CSV file: "+e.response.data.message;                
            }
        },
        generateCsvQueueStatusCheck: async function(file_id,job_id){

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

            <h3>Data files</h3>
            <div v-show="page_action=='list'">

            <strong>{{data_files.length}}</strong> files

                <v-row>
                    <v-col md="8">
                    <button v-if="selected_files.length>0" type="button" class="btn btn-sm btn-outline-danger" @click="batchDelete">Delete {{selected_files.length}} selected</button>
                    
                    </v-col>
                    <v-col md="4" align="right" class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="addFile">Create file</button>
                        <router-link class="btn btn-sm btn-outline-primary" :to="'datafiles/import'">Import files</router-link> 
                    </v-col>
                </v-row>
                
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th><input type="checkbox" v-model="select_all_files" @change="toggleFilesSelection" /></th>
                        <th><span class="mdi mdi-swap-vertical"></span></th>
                        <th style="width:80px;">File#</th>
                        <th>File name</th>
                        <th>Variables</th>
                        <th>Cases</th>                        
                    </tr>
                    </thead>
                    <tbody is="draggable" :list="data_files" tag="tbody" handle=".handle" >
                    <tr v-for="(data_file, index) in data_files" :key="data_file.file_id">
                        <td><input type="checkbox" v-model="selected_files" :value="data_file.file_id" /></td>
                        <td><span title="Drag to re-order" class="mdi mdi-drag handle"></span></td>
                        <td><i class="far fa-file-alt"></i> {{data_file.file_id}}</td>
                        <td>
                            <div>
                                <button type="button" class="btn btn-sm btn-link ml-0 pl-0" @click="editFile(index)">{{data_file.file_name}}</button>
                                <v-icon style="color:red;margin-top:-4px;" title="Physical file not found" v-if="!data_file.file_info.original">mdi-alert-circle</v-icon></div>
                            <div class="text-secondary text-small" v-if="data_file.file_info.original">
                                <span v-if="data_file.file_info.original.file_exists" class="mr-3">
                                    <span>{{data_file.file_info.original.filename}}</span>
                                    <span>{{data_file.file_info.original.file_size}}</span>
                                </span>
                                <span v-if="data_file.file_info.csv.file_exists" >{{data_file.file_info.csv.filename}} {{data_file.file_info.csv.file_size}}</span>
                            </div>

                            <div class="mt-2 datafile-actions">                                
                                <router-link :to="'/variables/' + data_file.file_id"><button type="button" class="btn btn-sm btn-light"><v-icon>mdi-table</v-icon> Variables</button></router-link>
                                <router-link :to="'/data-explorer/' + data_file.file_id"><button type="button" class="btn btn-sm btn-light"><v-icon>mdi-table-eye</v-icon> Data preview</button></router-link>
                                <span v-if="data_file.file_info.original">
                                <button type="button" class="btn btn-sm btn-light ink ml-0 pl-0" @click="importSummaryStatistics(data_file.file_id)"><v-icon title="Refresh summary statistics" >mdi-update</v-icon>Refresh stats</button>
                                <button type="button" class="btn btn-sm btn-light ink ml-0 pl-0" @click="generateCSV(data_file.file_id)"><v-icon title="Generate CSV" >mdi-database-export</v-icon>Export CSV</button>
                                </span>
                                <button type="button" class="btn btn-sm btn-light ink ml-0 pl-0" @click="deleteFile(index)"><v-icon>mdi-trash-can</v-icon>Remove</button>
                                <button type="button" class="btn btn-sm btn-light ink ml-0 pl-0" @click="replaceFile(index)"><v-icon>mdi-trash-can</v-icon>Replace file</button>
                                
                                        
                                        <v-menu offset-y>
                                            <template v-slot:activator="{ on, attrs }">
                                                <button type="button" class="btn btn-sm btn-light ink ml-0 pl-2"  v-bind="attrs" v-on="on">
                                                    Export <v-icon title="More options">mdi-dots-vertical</v-icon>
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
                                                <v-list-item  @click="exportFile(index,'sas')">
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
                                <router-link :to="'/variables/' + data_file.file_id"><button type="button" class="btn btn-sm btn-link"><i class="fas fa-table"></i> Variables</button></router-link>
                                <router-link :to="'/data-explorer/' + data_file.file_id"><button type="button" class="btn btn-sm btn-link"><i class="fas fa-table"></i> Data</button></router-link>
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

            <dialog-datafile-import v-model="dialog_datafile_import" :file_id="dialog_datafile_import_fid"></dialog-datafile-import>            
        
        </div>
    `
})