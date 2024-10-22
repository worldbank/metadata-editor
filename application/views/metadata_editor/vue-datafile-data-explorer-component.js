/// datafile data explorer
Vue.component('datafile-data-explorer', {
    props:['file_id','value'],
    data: function () {    
        return {
            field_data: this.value,
            form_local:{},
            fid:this.file_id,
            variable_data:[],
            show_dialog:false,
            errors:[],
            data_import_options:'replace',
            file:null,
            rows_limit:50,
            data_loading_dialog:false,
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
    mounted: function(){
        this.fid=this.$route.params.file_id;
        this.loadData();
    },
    
    computed: {
        dataFiles(){
            return this.$store.getters.getDataFiles;
        },
        activeDataFile(){
            return this.$store.getters.getDataFileById(this.fid);
        },
        ProjectID(){
            return this.$store.state.project_id;
        },

        /*
        Offset: {{variable_data.offset}} 
                Limit: {{variable_data.limit}} 
                Rows: {{variable_data.total}} 
        */

        PageOffset(){
            return this.variable_data.offset;
        },
        CurrentPage:{
                get: function () {
                    currentPage_ = Math.ceil(this.variable_data.offset / this.rows_limit);

                    if (currentPage_<=0){
                        return 1;
                    }
        
                    return currentPage_+1;
                },
                set: function (newValue) {
                    
                }
        },
        
        PaginationTotalRecords()
        {
            return this.variable_data.total;
        },
        PaginationPageSize()
        {
            return this.rows_limit;
        },
        PaginationPages()
        {
            return Math.ceil((this.variable_data.total) / this.rows_limit);            
        },
    },
    methods:{
        handleFileUpload( event ){
            this.file = event.target.files[0];
        },
        UploadData: function(){
            let formData = new FormData();
            formData.append('file', this.file);

            vm=this;
            this.errors='';
            let url=CI.base_url + '/api/editor/import_data/'+ this.ProjectID + '/f1/0';

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                console.log("data uploaded",response);
                vm.show_dialog=false;
                vm.$router.go()
            })
            .catch(function(response){
                vm.errors=response;
            });
        },
        loadData: function(offset=0,limit=50) {
            this.data_loading_dialog=true;
            vm=this;
            let url=CI.base_url + '/api/data/read_csv/'+this.ProjectID+'/'+this.fid+'?offset='+offset+'&limit='+limit;            
            axios.get(url)
            .then(function (response) {
                if(response.data){                    
                    vm.variable_data=response.data;
                    vm.data_loading_dialog=false;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
                vm.data_loading_dialog=false;
            });
        },
        navigatePage: function(page)
        {
            page_offset=(page - 1) * this.PaginationPageSize;
            this.loadData(page_offset, this.PaginationPageSize);
        },
        exportFile: async function(format){
            let data_file=this.activeDataFile;

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

                        let download_url=CI.base_url + '/api/datafiles/download_tmp_file/'+this.ProjectID + '/' + file_id + '/' + format;
                        window.open(download_url, '_blank').focus();
                    }
                    
                }catch(e){
                    console.log("failed",e);
                    this.dialog.is_loading=false;
                    this.dialog.message_error=this.$t("failed")+": "+e.response.data.message;
                }
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    },  
    template: `
            <div class="datafile-component mt-5 pt-3 m-3" v-if="activeDataFile">

            <div class="float-right"">
                <v-btn color="primary" outlined small @click="show_dialog=true">Import Data</v-btn>                
                <v-menu offset-y>
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn color="primary" outlined small v-bind="attrs" v-on="on">
                            <v-icon title="More options">mdi-export</v-icon> {{$t("export")}} <v-icon title="More options">mdi-dots-vertical</v-icon>
                        </v-btn>
                    </template>
                    <v-list>
                        <v-list-item @click="exportFile('sav')">
                            <v-list-item-title>SPSS</v-list-item-title>
                        </v-list-item>
                        <v-list-item  @click="exportFile('dta')">
                            <v-list-item-title>Stata</v-list-item-title>
                        </v-list-item>
                        <v-list-item  @click="exportFile('csv')">
                            <v-list-item-title>CSV</v-list-item-title>
                        </v-list-item>
                        <v-list-item  @click="exportFile('json')">
                            <v-list-item-title>JSON</v-list-item-title>
                        </v-list-item>
                        <v-list-item  @click="exportFile('xpt')">
                            <v-list-item-title>SAS</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </div>

            <h2>Data</h2>

            <template>
                <div v-if="data_loading_dialog==true">
                    <div class="pt-4 ">    
                        <div>Loading, please wait ...</div>
                        <v-progress-linear
                            indeterminate
                            color="teal"
                        ></v-progress-linear>
                    </div>
                </div>                
            </template>

            <template v-if="variable_data.records" >

            <div class="row mt-2" >
                <div class="col-md-3">
                    <div class="mt-2">Showing records <strong>{{PageOffset+1}}</strong> - <strong>{{PageOffset+variable_data.records.length}}</strong> of <strong>{{PaginationTotalRecords}}</strong></div>
                </div>
                <div class="col-md-9">
                <template>                
                    <div class="float-right">
                        <v-pagination
                            v-model="CurrentPage"
                            :length="PaginationPages"
                            :total-visible="8"
                            @input="navigatePage"
                        ></v-pagination>                    
                    </div>
                </template>
                </div>
            </div>
            


            <div class="table-responsive bg-white" style="font-size:smaller;">
                <table class="table table-hover table-sm table-striped" >
                    <thead>
                    <tr v-for="row_first in variable_data.records.slice(0,1)">
                        <th>#</th>
                        <th v-for="(column_key,column_value)  in row_first">{{column_value}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(row,index) in variable_data.records">
                        <td>{{PageOffset + index +1}}</td>
                        <td v-for="(column_key,column_value)  in row">
                        <span class="d-inline-block text-truncate" style="max-width: 150px;">
                        {{column_key}}
                        </span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            </template>
            

            <div v-if="!data_loading_dialog && !variable_data" class="row mt-2" >
                No data is avaiable
            </div>



            <!--dialog-->
                <template>
                    <div class="text-center">
                        <v-dialog
                        v-model="show_dialog"
                        width="500"
                        >                        

                        <v-card>
                            <v-card-title class="text-h5 grey lighten-2">
                            Import Data
                            </v-card-title>

                            <v-card-text>
                                <div>
                                    <input type="file" class="border p-1" style="width:100%;"  @change="handleFileUpload( $event )">
                                </div>
                                
                                <div class="text-secondary">Supported file types: CSV</div>

                                <div class="mt-2">                                
                                    <div class="form-check">
                                    <input class="form-check-input" type="radio" name="data_import" id="data_import1" v-model="data_import_options" value="replace" checked>
                                    <label class="form-check-label" for="data_import1">
                                        Replace data
                                    </label>
                                    </div>
                                    <div class="form-check">
                                    <input class="form-check-input" type="radio" name="data_import" id="data_import2" v-model="data_import_options" value="append" disabled="disabled">
                                    <label class="form-check-label" for="data_import2">
                                        Append to existing data
                                    </label>
                                    </div>
                                </div>

                                <div v-if="errors.length>0">{{errors}}</div>
                            </v-card-text>

                            <v-divider></v-divider>

                            <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn 
                                color="secondary"
                                text
                                @click="show_dialog=false"
                            >
                                Cancel
                            </v-btn>
                            <v-btn :disabled="!file"
                                color="primary"
                                text
                                @click="UploadData()"
                            >
                                Upload
                            </v-btn>
                            </v-card-actions>
                        </v-card>
                        </v-dialog>
                    </div>
                    </template>
                <!--end dialog -->

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
            
            </div>          
            `    
});

