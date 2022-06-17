//variables
Vue.component('variables', {
    props:['file_id'],
    data() {
        return {
            dataset_id:project_sid,
            dataset_idno:project_idno,
            dataset_type:project_type,
            form_errors:[],
            schema_errors:[],
            data_files:[],
            variables:[],
            page_action:'list',
            edit_item:null,
            fid:this.file_id
        }
    }, 
    created: function(){
        this.fid=this.$route.params.file_id;
    },
    mounted: function () {
        //this.loadData();
    },
    watch: {
        'fid': function() {
            this.loadData();
        }
    },
    methods: {
        loadData: function() {
            vm=this;
            let url=CI.base_url + '/api/datasets/variables/'+vm.dataset_idno + '/'+ this.fid + '?detailed=1';
            axios.get(url)
            .then(function (response) {
                console.log("vars",response.data.variables);
                vm.variables=[];
                if(response.data.variables.length>0){
                    vm.variables=response.data.variables;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        updateVariable: function(variable) {
            console.log("before local variable",this.variables[this.edit_item]);
            this.$set(this.variables, this.edit_item, variable);
            console.log("after local variable",this.variables[this.edit_item]);
        },
        editVariable:function(index){
            this.exitEditMode();
            this.$nextTick().then(() => {
                this.page_action="edit";
                this.edit_item=index;
            });
        },
        addVariable:function(){
            this.page_action="edit";
            let new_idx=this.variables.push({                
                "sid": "268",
                "fid": "F2",                
                "name": "untitled",
                "labl": "untitled",
                "var_format":{
                    "type":null
                }
              }) -1;
            this.edit_item=new_idx;
        },
        saveFile: function(data)
        {
            console.log("saving file",this.variables[this.edit_item]);
            this.$set(this.variables, this.edit_item, data);            
        },
        exitEditMode: function()
        {
            this.page_action="list";
            this.edit_item=null;
        }
    },
    computed: {
        activeVariable: function()
        {
            return this.variables[this.edit_item];
        }
    },
    template: `
        <div>
            <span style="font-size:small;" class="text-secondary">Data files / {{fid}} </span>
            <div>                            
                <div class="row" style="height: 100vh;">

                    <div class="col-md-9" style="height: 100%;overflow-y: scroll;">
                            
                            <div style="height:40%;overflow-y: scroll;background:white;font-size:small" class="border">
                                <div class="section-title p-2 bg-primary" >
                                    <strong>Variables</strong>
                                    <span class="badge badge-light">{{variables.length}}</span>
                                    <div class="pull-right float-right">
                                        <button type="button" >
                                            <i class="fas fa-plus-square mr-2" title="Add new variable" @click="addVariable"></i>
                                        </button>
                                        <button type="button" @click="loadData" class="mr-3" title="Refresh variables"><i class="fas fa-sync"></i></button>
                                        <i class="fas fa-ellipsis-v" ></i>
                                    </div>
                                </div>
                                <div class="table-responsive table-responsive-sticky">
                                    <table class="table table-striped table-bordered table-sm table-variables">
                                        <?php /*<thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Label</th>
                                                <th>Format</th>
                                            </tr> 
                                        </thead> */ ?>
                                        <tr v-for="(variable, index) in variables">
                                            <td class="bg-secondary">{{variable.vid}}</td>
                                            <td>
                                                <div class="text-link" @click="editVariable(index)">{{variable.name}}</div>                                                
                                            </td>
                                            <td>
                                                <div>{{variable.labl}}</div>
                                            </td>
                                            <td>
                                                {{variable.var_format.type}}
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>


                            <div class="container-fluid-x border mt-2" style="height: 60%;overflow-y: scroll;font-size:small;">
                                <div class="section-title p-2 bg-primary"><strong>Documentation</strong></div>
                                <div class="p3">
                                    <div v-show="page_action=='edit'" >
                                        <div v-if="variables[edit_item]">
                                            <variable-edit :value="variables[edit_item]" :index_key="edit_item" @updateVariable="updateVariable" ></variable-edit>
                                        </div>
                                    </div>                                
                                </div>

                            </div>

                        
                    </div>

                    <div class="col-md-3 bg-light" style="height: 100%;overflow-y: scroll;">

                    <variable-categories v-if="variables[edit_item]" :edit_index="edit_item"  :value="variables[edit_item]" @updateVariable="updateVariable"/>

                    </div>

                </div>

            </div>

        </div>
    `
})