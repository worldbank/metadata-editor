Vue.component('datafiles', {
    data() {
        return {
            showChildren: true,
            dataset_id:project_sid,
            dataset_idno:project_idno,
            dataset_type:project_type,
            form_errors:[],
            schema_errors:[],
            data_files:[],
            page_action:'list',
            edit_item:null
        }
    }, 
    mounted: function () {
        this.loadDataFiles();
    },   
    methods: {
        loadDataFiles: function() {
            vm=this;
            let url=CI.base_url + '/api/datasets/datafiles/'+vm.dataset_idno;
            axios.get(url)
            .then(function (response) {
                console.log(response);
                vm.data_files=[];
                if(response.data.datafiles){
                    //vm.data_files=response.data.datafiles;
                    window._files=response.data.datafiles;
                    //this.$store.state.data_files=response.data.datafiles;
                    Object.keys(response.data.datafiles).forEach(function(element, index) { 
                        vm.data_files.push(response.data.datafiles[element]);
                    })
                    vm.$store.state.data_files=vm.data_files;
                    console.log(vm.data_files);
                    console.log(vm.data_files);
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        editFile:function(file_id){
            this.page_action="edit";
            this.edit_item=file_id;
        },
        addFile:function(){
            this.page_action="edit";
            console.log(this.data_files);
            let new_idx=this.data_files.push({file_name:""}) -1;
            this.edit_item=new_idx;
        },
        saveFile: function(data)
        {
            console.log("saving file",this.data_files[this.edit_item]);
            this.$set(this.data_files, this.edit_item, data);            
        },
        exitEditMode: function()
        {
            this.page_action="list";
            this.edit_item=null;
        }
    },
    computed: {
    },
    template: `
        <div>
            <h1>Data files</h1>            
            <div v-show="page_action=='list'">


            <v-row>
                <v-col md="8"><strong>{{data_files.length}}</strong> files </v-col>
                <v-col md="4" class="d-flex justify-end">
                    <button type="button" class="btn btn-link" @click="addFile">Add file</button> | 
                    <button type="button" class="btn btn-link" @click="loadDataFiles">Refresh page</button>                    
                </v-col>
            </v-row>

                

                <v-simple-table class="table table-striped table-bordered">
                <template v-slot:default>
                <body>
                <tr v-for="(data_file, index) in data_files">
                    <td>
                        <button type="button" class="btn btn-link" @click="editFile(index)">Edit</button>
                        <button type="button" class="btn btn-link">Delete</button>
                        <router-link :to="'/variables/' + data_file.file_id">Variables</router-link>
                    </td>
                    <td>
                        <div class="font-weight-bold">{{data_file.file_name}}</div>
                        <div>{{data_file.description}}</div>                        
                    </td>                    
                </tr>
                </body>
                </template>
                </v-simple-table>
            </div>

            <div v-show="page_action=='edit'" >
                <div v-if="data_files[edit_item]">
                    <datafile-edit :value="data_files[edit_item]" @input="saveFile" @exit-edit="exitEditMode"></datafile-edit>                
                </div>
            </div>

            <pre>
            {{data_files}}
            </pre>

        </div>
    `
})