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
            let url=CI.base_url + '/api/editor/datafiles/'+vm.dataset_id;
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
            let url=CI.base_url + '/api/editor/datafiles_sequence/'+vm.dataset_id;
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
        deleteFile:function(file_idx)
        {
            let data_file=this.data_files[file_idx];
            alert("Are you sure you want to delete file " + data_file.file_id + "?");

            vm=this;
            let url=CI.base_url + '/api/editor/datafiles_delete/'+vm.dataset_id + '/'+ data_file.file_id;
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
        }
    },
    computed: {
        data_files(){
            return this.$store.state.data_files;
          },
    },
    template: `
        <div class="datfiles-component">

        
            <div class="container-fluid mt-5">

            <h3>Data files</h3>
            <div v-show="page_action=='list'">

                <v-row>
                    <v-col md="8"><strong>{{data_files.length}}</strong> files </v-col>
                    <v-col md="4" align="right" class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="addFile">Create file</button>
                        <router-link class="btn btn-sm btn-outline-primary" :to="'datafiles/import'">Import file</router-link> 
                    </v-col>
                </v-row>

                
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th><span class="mdi mdi-swap-vertical"></span></th>
                        <th>File ID</th>
                        <th>File name</th>
                        <th>Variables</th>
                        <th>&nbsp;</th>
                    </tr>
                    </thead>
                    <tbody is="draggable" :list="data_files" tag="tbody" handle=".handle" >
                    <tr v-for="(data_file, index) in data_files">
                        <td><span title="Drag to re-order" class="mdi mdi-drag handle"></span></td>
                        <td><i class="far fa-file-alt"></i> {{data_file.file_id}}</td>
                        <td>{{data_file.file_name}}</td>
                        <td>{{data_file.var_count}}</td>
                        <td>
                            <div>
                                <button type="button" class="btn btn-sm btn-link" @click="editFile(index)"><i class="far fa-edit" title="Edit"></i></button>
                                <button type="button" class="btn btn-sm btn-link" @click="deleteFile(index)"><i class="fas fa-trash-alt" title="Delete"></i></button>
                                <router-link :to="'/variables/' + data_file.file_id"><button type="button" class="btn btn-sm btn-link"><i class="fas fa-table"></i> Variables</button></router-link>
                                <router-link :to="'/data-explorer/' + data_file.file_id"><button type="button" class="btn btn-sm btn-link"><i class="fas fa-table"></i> Data</button></router-link>
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
        
        </div>
    `
})