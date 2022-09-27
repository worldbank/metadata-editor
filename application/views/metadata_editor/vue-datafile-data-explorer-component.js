/// datafile data explorer
Vue.component('datafile-data-explorer', {
    props:['file_id','value'],
    data: function () {    
        return {
            field_data: this.value,
            form_local:{},
            fid:this.file_id,
            variable_data:[]
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
    },
    methods:{
        loadData: function() {
            vm=this;
            let url=CI.base_url + '/api/R/read_csv/'+this.ProjectID+'/'+this.fid;
            axios.get(url)
            .then(function (response) {
                if(response.data){                    
                    vm.variable_data=response.data;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
    },  
    template: `
            <div class="datafile-component" v-if="activeDataFile">
            {{fid}}

            <h5>Data</h5>

            <div>
                Offset: {{variable_data.offset}} 
                Limit: {{variable_data.limit}} 
                Rows: {{variable_data.total}} 
            </div>

            <div class="table-responsive bg-white" style="font-size:smaller;">
            <table class="table table-sm table-striped" v-if="variable_data">
                <tr v-for="row_first in variable_data.records.slice(0,1)">
                    <td v-for="(column_key,column_value)  in row_first">{{column_value}}</td>
                </tr>
                <tr v-for="(row,index) in variable_data.records">
                    <td>{{index +1}}</td>
                    <td v-for="(column_key,column_value)  in row">{{column_key}}</td>
                </tr>
            </table>
            </div>
            
            </div>          
            `    
});

