/// datafile data explorer
Vue.component('datafile-data-explorer', {
    props:['file_id','value'],
    data: function () {    
        return {
            field_data: this.value,
            form_local:{},
            fid:this.file_id,
            variable_data:[],            
            errors:[],            
            file:null,
            rows_limit:50,
            data_loading_dialog:false,
            delete_confirm_dialog: false,
            delete_in_progress: false,
            dialog:{
                show:false,
                title:'',
                loading_message:'',
                message_success:'',
                message_error:'',
                is_loading:false
            },
            export_dialog:{
                show:false,
                file_id:null,
                file_name:'',
                file_physical_name:''
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
                vm.data_loading_dialog=false;
                vm.errors=error;
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
        exportFile: function(){
            this.export_dialog.file_id = this.activeDataFile.file_id;
            this.export_dialog.file_name = this.activeDataFile.file_name;
            this.export_dialog.file_physical_name = (this.activeDataFile && this.activeDataFile.file_physical_name) ? this.activeDataFile.file_physical_name : '';
            this.export_dialog.show = true;
        },
        exportDictionaryCsv: function() {
            if (!this.ProjectID || !this.fid) {
                return;
            }
            var url = CI.base_url + '/api/variables/export_csv/' + this.ProjectID + '/' + encodeURIComponent(this.fid) + '?download=1';
            window.location.href = url;
        },
        confirmDeleteData: function(){
            this.delete_confirm_dialog = true;
        },
        deleteData: async function(){
            if (!this.ProjectID || !this.fid) return;
            this.delete_in_progress = true;
            const vm = this;            
            // Delete only the physical CSV file; keep the datafile definition
            const url = CI.base_url + '/api/datafiles/delete_file/' + this.ProjectID + '/' + encodeURIComponent(this.fid);
            try {
                const res = await axios.post(url);
                if (res.data && res.data.status === 'success') {
                    vm.delete_confirm_dialog = false;
                    if (vm.$store && vm.$store.dispatch) {
                        await vm.$store.dispatch('loadDataFiles', { dataset_id: vm.ProjectID });
                    }
                    vm.variable_data = [];
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onSuccess', vm.$t('csv_data_deleted') || 'CSV data deleted successfully.');
                    }
                } else {
                    if (typeof EventBus !== 'undefined') {
                        EventBus.$emit('onFail', (res.data && res.data.message) || 'Failed to delete CSV data.');
                    }
                }
            } catch (err) {
                const msg = (err.response && err.response.data && err.response.data.message) || err.message;
                if (typeof EventBus !== 'undefined') EventBus.$emit('onFail', msg);
            } finally {
                vm.delete_in_progress = false;
            }
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
    },  
    template: `
            <div class="datafile-component mt-5 pt-3 m-3">
            <template v-if="activeDataFile">
            <v-card>
                <v-card-title>
                    {{$t('Data')}}
                </v-card-title>
                <v-card-text style="min-height:200px;">

                    <div class="float-right d-flex align-center" style="gap: 8px;">
                        <v-btn v-if="activeDataFile" color="primary" outlined small @click="exportDictionaryCsv">
                            <v-icon>mdi-book-open-variant</v-icon> {{$t("export_data_dictionary")}}
                        </v-btn>
                        <template v-if="variable_data.records">
                            <v-btn color="primary" outlined small @click="exportFile">
                                <v-icon>mdi-export</v-icon> {{$t("export")}}
                            </v-btn>
                        </template>
                        <v-btn color="error" outlined small @click="confirmDeleteData" :disabled="delete_in_progress">
                            <v-icon small>mdi-delete</v-icon> {{$t("delete_data") || "Delete data"}}
                        </v-btn>
                    </div>
                    <br/>

                    <template>
                        <div v-if="data_loading_dialog==true">
                            <div class="pt-4 ">    
                                <div>{{$t('loading_please_wait')}}</div>
                                <v-progress-linear
                                    indeterminate
                                    color="teal"
                                ></v-progress-linear>
                            </div>
                        </div>                
                    </template>

                    
                    <div v-if="!variable_data.records" class="text-center m-3 p-3" >                        
                        <v-alert
                        text
                        outlined
                        color="deep-orange"
                        icon="mdi-fire"
                        >
                        {{$t('no_data_available')}}
                        </v-alert>
                    </div>

                    <template v-if="variable_data.records" >

                    <div class="row mt-2" >
                        <div class="col-md-3">
                            <div class="mt-2">{{$t('showing_records_range', {start: PageOffset+1, end: PageOffset+variable_data.records.length, total: PaginationTotalRecords})}}</div>
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
                        {{$t('no_data_available')}}
                    </div>

                </v-card-text>
            </v-card>


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
                                {{$t('close')}}
                            </v-btn>
                            </v-card-actions>
                        </v-card>
                        </v-dialog>
                    <!-- end dialog -->

            <!-- Export Dialog -->
            <dialog-datafile-export 
                v-model="export_dialog.show" 
                :file_id="export_dialog.file_id"
                :file_name="export_dialog.file_name"
                :file_physical_name="export_dialog.file_physical_name || ''">
            </dialog-datafile-export>
            </template>
            <v-card v-else class="mt-5 pt-3 m-3">
                <v-card-title>{{$t('Data')}}</v-card-title>
                <v-card-text class="text-center py-8">
                    <v-icon size="64" color="grey lighten-1">mdi-database-off</v-icon>
                    <p class="mt-3 mb-0">{{$t('no_data_file') || 'No data file found.'}}</p>                    
                </v-card-text>
            </v-card>

            <!-- Delete data confirmation -->
            <v-dialog v-model="delete_confirm_dialog" max-width="400" persistent>
                <v-card>
                    <v-card-title class="text-subtitle-1">{{ $t("delete_data") || "Delete data" }}</v-card-title>
                    <v-card-text>
                        {{ $t("confirm_remove_data")  }}
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text @click="delete_confirm_dialog = false" :disabled="delete_in_progress">{{ $t("cancel") || "Cancel" }}</v-btn>
                        <v-btn color="error" text @click="deleteData" :loading="delete_in_progress">{{ $t("delete") || "Delete" }}</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
            
            </div>          
            `    
});

