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
            data_loading_dialog:false
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
        CurrentPage(){
            currentPage = Math.ceil(this.variable_data.offset / this.rows_limit);

            if (currentPage<=0){
                return 1;
            }

            return currentPage+1;
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
            let url=CI.base_url + '/api/R/read_csv/'+this.ProjectID+'/'+this.fid+'?offset='+offset+'&limit='+limit;            
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
        }
    },  
    template: `
            <div class="datafile-component m-3" v-if="activeDataFile">

            <div class="float-right"">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="show_dialog=true">Import Data</button>
                <button type="button" class="btn btn-sm btn-outline-primary">Export Data</button>
            </div>

            <h2>Data</h2>

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
            <template v-else>
                No data is available
            </template>



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
            
            </div>          
            `    
});

