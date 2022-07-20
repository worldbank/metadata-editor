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
            variable_copy:{},//copy of the variable before any editing
            fid:this.file_id,
            variable_search:'',
            changeCaseDialog: false,
            dialogm1: '',
            changeCaseFields:["name"],
            changeCaseType:"title",
            changeCaseUpdateStatus:'',
            edit_items_multiple:[], //items edited in multiple edit_mode,
            variableMultiple:{
                "name": "NaN",
                "labl": "Multiple selected",
                "var_valrng": {
                  "range": {                    
                  }
                },
                "var_sumstat": [
                  {}
                ],
                "var_catgry": [                  
                  {}
                ],
                "var_format": {                  
                },
                "var_type": "",
                "var_concept": [
                  []
                ],
                "var_txt": "",
                "var_universe": "",
                "time_stamp":0
              },
            variableMultipleUpdateFields: //fields to be updated for multiple selection
            [
                "var_txt",
                "var_universe",
                "var_concept",
                "var_qstn_postqtxt",
                "var_qstn_preqtxt",
                "var_qstn_qstnlit",
                "var_codinstr",
                "var_imputation",
                "var_qstn_ivuinstr",
                "var_resp_unit"
            ],
            showSpreadMetadataDialog: false
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
        /*activeVariable: function(val) {          
            //this.loadData();
            console.log("value changed");
            //this.saveVariable(val);
        },*/

        activeVariable: {            
            deep: true,
            handler(val,oldVal){
                if (this.page_action!="edit"){
                    return;
                }

              if (JSON.stringify(val)==JSON.stringify(this.variable_copy)){
                  console.log("no change detected");
              }
              else{
                console.log("CHANGE DETECTED");
                this.saveVariableDebounce(val);
              }

              console.log("watch changes", this.variable_copy,val);
              window._copy=this.variable_copy;
              window._val=val;
            }
          }
    },
    methods: {        
        clearVariableSearch: function(){
            this.variable_search='';
        },
        scrollToVariableBottom: function(){
            document.getElementById('variables-container').scrollTop= document.getElementById('variables-container').scrollHeight;
        },
        scrollToVariable: function(idx=0){
            var id=this.edit_item;

            if (idx>0){
                id=idx;    
            }

            this.editVariable(id);
            
            /*document.getElementById(id).scrollIntoView({
                behavior: "smooth",
              });
            */

            var myElement = document.getElementById('v-'+id);
            var topPos = myElement.offsetTop - 100;

            document.getElementById('variables-container').scrollTop = topPos;

        },
        varNavigate: function(direction)
        {
            total_vars=this.variables.length-1;

            switch(direction) {
                case 'first':
                    this.edit_item=0;
                  break;
                case 'prev':
                  if (this.edit_item>0){
                    this.edit_item=this.edit_item-1;
                  }
                  break;
                case 'next':
                    if (this.edit_item<total_vars){
                        this.edit_item=this.edit_item+1;
                    }
                    break;  
                case 'last':
                    this.edit_item=total_vars;
                    break;  
              }
            
            this.scrollToVariable();
        },
        /*updateVariable: function(variable) 
        {
            console.log("before local variable",this.variables[this.edit_item]);
            this.$set(this.variables, this.edit_item, variable);
            console.log("after local variable",this.variables[this.edit_item]);
        },*/
        spreadMetadata: function ()
        {
            this.showSpreadMetadataDialog=true;
        },        
        changeCase: function()
        {
            var_count=this.variables.length;
            for(i=0;i<var_count;i++){
                for(f=0;f<this.changeCaseFields.length;f++){
                    field_=this.changeCaseFields[f];
                    if (this.changeCaseType=='title'){
                        this.variables[i][field_]=this.titleCase(this.variables[i][field_]);
                    } else if (this.changeCaseType=='upper'){
                        this.variables[i][field_]=this.variables[i][field_].toUpperCase();
                    } else if (this.changeCaseType=='lower'){
                        this.variables[i][field_]=this.variables[i][field_].toLowerCase();
                    }
                }

                this.changeCaseUpdateStatus="Updating " + (i + 1) + " of " + var_count;
                this.saveVariable(this.variables[i]);
            }

            this.changeCaseUpdateStatus="";
            this.changeCaseDialog=false;
        },
        editVariableMultiple: function(index)
        {
            //if (!this.isVariableSelected(this.edit_item)){
                //this.edit_items_multiple.push(this.edit_item);
            //}
            if (this.edit_item!=-1){
                this.edit_items_multiple.push(this.edit_item);
                this.edit_item=-1;
            }

            if (!this.isVariableSelected(index)){
                this.edit_items_multiple.push(index);                
            }

            this.variableMultiple.time_stamp++;
        },
        isVariableSelected: function(index)
        { 
            if(this.edit_item==index){
                return true;
            }
            
            if (this.edit_items_multiple.includes(index)){
                return true;
            }
            return false;
        },
        editVariable:function(index){
            this.exitEditMode();
            this.$nextTick().then(() => {
                this.page_action="edit";
                this.edit_item=index;
                this.variable_copy=_.cloneDeep(this.variables[index]);
            });
        },
        addVariable:function(){
            this.variable_search="";
            this.page_action="edit";
            this.scrollToVariableBottom();
            //let new_idx=this.variables.push() -1;;
                new_var={
                "vid": "V" + (this.MaxVariableID+1),
                "sid": this.dataset_id,
                "file_id": this.fid,
                "name": "untitled",
                "labl": "untitled",
                "var_format":{
                    "type":null
                },
                "var_catgry": []
              }

            this.$store.commit('variable_add',{fid:this.fid, variable:new_var});
            newIdx=this.variables.length -1;
            this.editVariable(newIdx);            
        },
        saveMultiSelectedVariables: function()
        {
            if(this.edit_items_multiple.length==0){
                return;
            }

            for(i=0;i<this.edit_items_multiple.length;i++){
                variable_=this.variables[this.edit_items_multiple[i]];
                //update key/values
                for(k=0;k<this.variableMultipleUpdateFields.length;k++){
                    field_name=this.variableMultipleUpdateFields[k];
                    //key exists
                    if(this.variableMultiple[field_name]){
                        variable_[field_name]=this.variableMultiple[field_name];
                    }
                }
                this.saveVariable(variable_);
            }
        },
        saveVariableDebounce: _.debounce(function(data) {
            
            //multiple variables selected
            if(this.edit_items_multiple.length>0){
                this.saveMultiSelectedVariables();
                return false;
            }

            //single variable
            this.saveVariable(data);
            this.variable_copy=_.cloneDeep(this.variables[this.edit_item]);
        }, 500),
        saveVariable: function(data){///_.debounce(function(data) {
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
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        }
        //}, 100)
        ,
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
            this.edit_items_multiple=[];
        },
        hasDataChanged: function(){
            return JSON.stringify(this.variables[this.edit_item])!==JSON.stringify(this.variable_copy);
        },
        titleCase: function(str) {
            return str.replace(
              /\w\S*/g,
              function(txt) {
                return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
              }
            );
        },        
        deleteVariable: function()
        {
            alert("Not implemented");
        }

    },
    computed: {
        activeVariable: function(){
            if (this.edit_items_multiple.length>0){                
                return this.variableMultiple;
            }

            return this.variables[this.edit_item];
        },
        selectedVariablesList: function(){
            if (this.edit_items_multiple.length>0){                
                return this.edit_items_multiple;
            }

            return [this.edit_item];
        },
        selectedVariablesDetail: function(){
            let variables=[];
            this.selectedVariablesList.forEach((variable_idx)=>{
                variables.push(this.variables[variable_idx]);
            });
            return variables;
        },
        MaxVariableID(){
            return this.$store.getters["getMaxVariableId"];
        },
        variables(){            
            vars=this.$store.getters.getVariablesByFid(this.fid);
            
            if (vars==undefined){
                return [];
            }

            if (this.variable_search!==''){
                let tmpVars = vars;
        
                tmpVars = tmpVars.filter((item) => {
                    //return (item.name == this.variable_search)
                    return (item.name + item.labl)
                        .toUpperCase()
                        .includes(this.variable_search.toUpperCase())
                })
                
                return tmpVars;
            }

            return vars;

          //  return this.$store.getters.getVariablesAll;
        }
    },
    template: `
        <div style="height: 100vh;margin-top:5px;" >
            <splitpanes class="default-theme" >
            <pane max-size="90" size="70">
                <splitpanes horizontal>
                    <pane min-size="5" >
                    <!--variables-start-->
                    <div style="height:100%;background:white" xstyle="height:40%;overflow-y: scroll;background:white;font-size:small" class="border">
                            <div class="row section-title p-1 bg-primary" style="font-size:small;position:relative;">
                                <div class="col-2">
                                    <strong>Variables</strong>
                                    <span v-if="variables" class="badge badge-light">{{variables.length}}</span>
                                </div>

                                <div class="col-3">
                                    <div class="input-group">
                                        <input type="text" class="bg-light form-control form-control-xs" placeholder="Search variables" v-model="variable_search">
                                        <div class="input-group-append">
                                        <button class="btn btn-secondary btn-sm btn-xs" type="button">
                                            <i class="fa fa-search"></i>
                                        </button>

                                        <button class="btn btn-link-outline btn-sm btn-xs" type="button" v-show="variable_search.length>0" @click="clearVariableSearch">
                                            Clear
                                        </button>
                                        
                                        </div>
                                    </div>
                                </div>


                                <div class="col">
                                
                                    <div class="float-right">

                                        <button type="button" class="btn btn-xs btn-primary" @click="changeCaseDialog=true" title="Change case">
                                            <v-icon aria-hidden="false" style="color:white">mdi-format-letter-case</v-icon>
                                        </button>

                                        <button type="button" class="btn btn-xs btn-primary" @click="spreadMetadata" title="Spread Metadata">
                                            <i class="fas fa-clone"></i>
                                        </button>

                                        <button type="button" class="btn btn-xs btn-primary" @click="addVariable" title="Add new variable">
                                            <i class="fas fa-plus-square" ></i>
                                        </button>


                                        <button type="button" class="btn btn-xs btn-primary" @click="deleteVariable" title="Delete selected variable(s)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        
                                        

                                        
                                        <span class="dropdown dropleft">
                                        <button class="btn btn-primary btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="font-size:small;">
                                            <a class="dropdown-item" href="#"><i class="fas fa-spell-check"></i> Change case</a>
                                            <a class="dropdown-item" href="#"><i class="fas fa-clone"></i> Spread metadata</a>
                                            <a class="dropdown-item" href="#"><i class="fas fa-file-download"></i> Export variable(s)</a>
                                        </div>
                                        </span>
                                    </div>
                                </div>

                            </div>


                            


                            
                            <div style="position:relative;padding-bottom:50px;height:inherit;overflow-y: scroll;background:white;font-size:small" id="variables-container" >

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
                                    <tr v-for="(variable, index) in variables" @click.shift.exact="editVariableMultiple(index)" @click.exact="editVariable(index)" :class="{'activeRow' : isVariableSelected(index)} " :id="'v-'+index" >
                                        <td class="bg-secondary">{{variable.vid}}</td>
                                        <td class="var-name-edit">
                                            <div><input class="var-labl-edit" type="text" v-model="variable.name" /></div>
                                        </td>
                                        <td>
                                            <div><input class="var-labl-edit" type="text" v-model="variable.labl"/></div>
                                        </td>
                                        <td>
                                            {{variable.var_format.type}}
                                        </td>                                        
                                    </tr>
                                    </tbody>
                                </table>

                                <spread-metadata v-if="showSpreadMetadataDialog" v-model="showSpreadMetadataDialog" :variables="selectedVariablesDetail"></spread-metadata>

                            </div>
                        </div>
                    <!--variables-end-->
                    </pane>
                    <pane size="30">
                    <!--documentation-->
                    <div class="container-fluid-x border " style="height: 100%;font-size:small;">

                        <div class="section-title p-1 bg-primary">
                            <div class="row">
                                <div class="col">
                                    <strong>Documentation</strong>
                                </div>
                                <div class="col-6">
                                    <span v-if="activeVariable">{{activeVariable.name}} - {{activeVariable.labl.substring(0,50)}} </span>
                                </div>
                                <div class="col-2">
                                    <div class="float-right">
                                        <button class="btn btn-xs btn-primary" @click="varNavigate('first')"><i class="fas fa-angle-double-left"></i></button>
                                        <button class="btn btn-xs btn-primary" @click="varNavigate('prev')"><i class="fas fa-angle-left"></i></button>
                                        <button class="btn btn-xs btn-primary" @click="varNavigate('next')"><i class="fas fa-angle-right"></i></button>
                                        <button class="btn btn-xs btn-primary" @click="varNavigate('last')"><i class="fas fa-angle-double-right"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                            <div class="p3" style="height:inherit;overflow:auto;background:white;">
                                <div v-show="page_action=='edit' " >
                                    <div v-if="activeVariable">
                                        <variable-edit  :variable="activeVariable" :multi_key="edit_items_multiple"  :index_key="edit_item" ></variable-edit>
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
                    <div style="height:100%;overflow:auto;background:white;" class="border">
                    <variable-categories v-if="variables[edit_item]" :edit_index="edit_item"  :value="variables[edit_item]" />
                    </div>
                    <!--categories-end-->
                </pane>
                <pane size="40" min-size="10">
                    <div style="height:100%;overflow:auto;" >
                        <variable-info v-if="variables[edit_item]" :edit_index="edit_item"  :value="variables[edit_item]" />
                    </div>
                </pane>
                </splitpanes>
            </pane>
            </splitpanes>

            <?php echo $this->load->view("metadata_editor/modal-dialog-changecase",null,true); ?>
        </div>
    `
})