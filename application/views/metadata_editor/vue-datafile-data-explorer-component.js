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
                file_name:''
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
            this.export_dialog.show = true;
        },        
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
    },  
    template: `
            <div class="datafile-component mt-5 pt-3 m-3" v-if="activeDataFile">

            <v-card>
                <v-card-title>
                    {{$t('Data')}}
                </v-card-title>
                <v-card-text style="min-height:200px;">

                    <div class="float-right" v-if="variable_data.records">
                                      
                        <v-btn color="primary" outlined small @click="exportFile">
                            <v-icon>mdi-export</v-icon> {{$t("export")}}
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
                :file_name="export_dialog.file_name">
            </dialog-datafile-export>
            
            </div>          
            `    
});

