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
            //variables:[],
            page_action:'list',
            edit_item:0,
            edit_item_copy:[],//copy of the variable before any editing
            fid:this.file_id,
            //variables:[]
        }
    }, 
    created: async function(){
        this.fid=this.$route.params.file_id;
        //this.editVariable(0);
    },
    mounted: function () {
        //this.loadData();     
        this.editVariable(0);
    },
    watch: {
        /*'fid': function() {
          alert("fid",this.fid);
            //this.loadData();
        },
        '$store.state.variables': function() {
            alert(1);
        }*/
    },
    methods: {
        /*loadData: function() {
            alert("load data");
            vm=this;
            let url=CI.base_url + '/api/datasets/variables/'+vm.dataset_idno + '/'+ this.fid + '?detailed=1';
            axios.get(url)
            .then(function (response) {
                console.log("vars",response.data.variables);
                vm.variables=[];
                if(response.data.variables.length>0){
                    vm.variables=response.data.variables;
                    window.variables_=vm.variables;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
                vm.editVariable(0);
            });
        },*/
        getMaxVariableId: function(){
            return this.getMaxVariableIdGlobal();
            /*var max_var=0;
            for(i=0;i<=this.variables.length -1;i++){
                if(parseInt(this.variables[i].vid.substr(1))>max_var){
                    max_var=this.variables[i].vid.substr(1);
                }else{
                    console.log("not greater",this.variables[i].vid);
                }
            }
            return parseInt(max_var);*/
        },
        getMaxVariableIdGlobal: function(){
            var max_var=0;
            let variables=this.$store.state.variables;
            let datafiles=Object.keys(variables);
            datafiles.forEach(function (fid) {
                variables[fid].forEach(function (variable){
                    if(parseInt(variable.vid.substr(1))>max_var){
                        max_var=variable.vid.substr(1);
                    }
                });
            });
            return parseInt(max_var);
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
                //this.edit_item_copy=_.cloneDeep(this.variables[index]);
            });
        },
        addVariable:function(){
            this.page_action="edit";
            let new_idx=this.variables.push({
                "vid": "V" + (this.getMaxVariableId()+1),
                "sid": this.dataset_id,
                "file_id": this.fid,
                "name": "untitled",
                "labl": "untitled",
                "var_format":{
                    "type":null
                },
                "var_catgry": []
              }) -1;
            this.edit_item=new_idx;
            this.editVariable(new_idx);
        },
        saveVariable: function(data)
        {
            console.log("variable saved in db", data);
            vm=this;
            let url=CI.base_url + '/api/datasets/variables/'+vm.dataset_idno;
            axios.post(url, 
                data
                /*headers: {
                    "xname" : "value"
                }*/
            )
            .then(function (response) {
                console.log("saveVariable", response);
                alert("Your changes were saved");
            })
            .catch(function (error) {
                console.log(error);
                //vm.schema_errors=error.response.data.errors;
                
                /*console.log(error.response.data);
                vm.dialog_box_option.title=error;
                vm.dialog_box_option.errors=error.response.data;
                $('#app_dialog').modal('show');
                */
            })
            .then(function () {
                // always executed
                console.log("request completed");
            });
        },
        exitEditMode: function()
        {
            if (this.edit_item==null){
                return;
            }

            /*if (this.hasDataChanged()==true){
                this.saveVariable(this.variables[this.edit_item]);
            }*/
            
            this.page_action="list";
            this.edit_item=null;
        },
        hasDataChanged: function(){
            return JSON.stringify(this.variables[this.edit_item])!==JSON.stringify(this.edit_item_copy);
        }

    },
    computed: {
        activeVariable: function(){
            return this.variables[this.edit_item];
        },
        variables(){
            //x=JSON.stringify(this.$store.state.variables);
            //console.log("variales vound",x, this.fid);
            return this.$store.getters.getVariablesByFid(this.fid);            
          //  return this.$store.getters.getVariablesAll;
        },
        maxVarID(){
            return this.getMaxVariableIdGlobal();
        }
    },
    template: `
        <div style="height: 100vh;margin-top:5px;" >
            <splitpanes class="default-theme" v-if="variables">
            <pane max-size="90" size="70">
                <splitpanes horizontal>
                    <pane min-size="5" >
                    <!--variables-start-->
                    <div v-if="variables" style="height:100%;background:white" xstyle="height:40%;overflow-y: scroll;background:white;font-size:small" class="border">
                            <div class="section-title p-1 bg-primary" style="font-size:small;" >
                                <strong>Variables</strong>
                                <span class="badge badge-light">{{variables.length}}</span>
                                <div class="pull-right float-right">
                                    <button type="button" >
                                        <i class="fas fa-plus-square mr-2" title="Add new variable" @click="addVariable"></i>
                                    </button>
                                    <button type="button" class="mr-3" title="Refresh variables"><i class="fas fa-sync"></i></button>
                                    <i class="fas fa-ellipsis-v" ></i>
                                </div>
                            </div>
                            <div style="padding-bottom:50px;height:inherit;overflow-y: scroll;background:white;font-size:small">
                                <table class="table table-striped table-bordered table-sm table-hover table-variables">
                                    <?php /*<thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Label</th>
                                            <th>Format</th>
                                        </tr> 
                                    </thead> */ ?>
                                    <tbody is="draggable" :list="variables" tag="tbody">
                                    <tr v-for="(variable, index) in variables" @click="editVariable(index)" :class="{'activeRow' : edit_item == index} ">
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
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <!--variables-end-->
                    </pane>
                    <pane size="30">
                    <!--documentation-->
                    <div class="container-fluid-x border " style="height: 100%;font-size:small;">
                            <div class="section-title p-1 bg-primary"><strong>Documentation</strong></div>
                            <div class="p3" style="height:inherit;overflow:auto;background:white;">
                                <div v-show="page_action=='edit'" >
                                    <div v-if="variables[edit_item]">
                                        <variable-edit  :value="variables[edit_item]" :index_key="edit_item" @updateVariable="updateVariable" ></variable-edit>
                                    </div>
                                </div>                                
                            </div>

                        </div>
                    <!--documentatino-end-->
                    </pane>
                </splitpanes>
            </pane>
            <pane max-size="60" size="30">
                <splitpanes horizontal>
                <pane size="60" min-size="10">
                    <!--categories-->
                    <variable-categories v-if="variables[edit_item]" :edit_index="edit_item"  :value="variables[edit_item]" @updateVariable="updateVariable"/>
                    <!--categories-end-->
                </pane>
                <pane size="40" min-size="10">
                    <variable-info v-if="variables[edit_item]" :edit_index="edit_item"  :value="variables[edit_item]" @updateVariable="updateVariable"/>
                </pane>
                </splitpanes>
            </pane>
            </splitpanes>
        </div>
    `
})