Vue.component('datafiles', {
    data() {
        return {
            showChildren: true,
            dataset_id:project_sid,
            dataset_idno:project_idno,
            dataset_type:project_type,
            form_errors:[],
            schema_errors:[],
            page_action:'list',
            edit_item:null
        }
    }, 
    mounted: function () {
        //this.loadDataFiles();
    },   
    methods: {
        /*loadDataFiles: function() {
            vm=this;
            let url=CI.base_url + '/api/datasets/datafiles/'+vm.dataset_idno;
            axios.get(url)
            .then(function (response) {
                console.log(response);
                vm.data_files=[];
                if(response.data.datafiles){
                    Object.keys(response.data.datafiles).forEach(function(element, index) { 
                        vm.data_files.push(response.data.datafiles[element]);
                    })
                    vm.$store.state.data_files=vm.data_files;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },*/
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
        data_files(){
            return this.$store.state.data_files;
          },
    },
    template: `
        <div>

        <v-container>

            <h1>Data files</h1>            
            <div v-show="page_action=='list'">


            <v-row>
                <v-col md="8"><strong>{{data_files.length}}</strong> files </v-col>
                <v-col md="4" align="right" class="mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="addFile">Create file</button>
                    <router-link class="btn btn-sm btn-outline-primary" :to="'datafiles/import'">Import file</router-link> 
                </v-col>
            </v-row>

            <v-container>
            <v-row v-for="(data_file, index) in data_files" class="bg-white mb-3">
                <v-col md="1" align="center">                
                <v-icon x-large color="grey lighten-1">mdi-file-table-outline</v-icon>                
                </v-col>
                <v-col md="11">
                        <div><h3>{{data_file.file_name}}</h3></div>
                        <div class="subtitle-1 mb-3">{{data_file.file_id}}</div>
                        <div class="subtitle-2">{{data_file.description}}</div>
                        
                        <div class="mt-2 pt-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="editFile(index)"><i class="far fa-edit" title="Edit"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary"><i class="fas fa-trash-alt" title="Delete"></i></button>
                            <router-link :to="'/variables/' + data_file.file_id"><button type="button" class="btn btn-sm btn-outline-primary"><i class="fas fa-table"></i> Variables</button></router-link>
                        </div>
                </v-col>
            </v-row>
            </v-container>
                
                

                


            </div>



            <div v-show="page_action=='edit'" >
                <div v-if="data_files[edit_item]">
                    <datafile-edit :value="data_files[edit_item]" @input="saveFile" @exit-edit="exitEditMode"></datafile-edit>                
                </div>
            </div>

        </v-container>
        </div>
    `
})