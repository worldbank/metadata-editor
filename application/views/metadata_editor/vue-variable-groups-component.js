Vue.component('variable-groups', {    
    data() {
        return {            
            project_id:project_sid,
            project_idno:project_idno,
            project_type:project_type,
            showDialog:false,            
            activeItem: null,
            treeActiveItem:[],
            treeItemOpen:['-1'],            
            tree:[],
            conceptColumns:{
                "key": "variable_groups.concepts",
                "title": "Concepts",
                "type": "array",
                "props": [
                    {
                    "key": "concept",
                    "title": "Concept",
                    "type": "string",
                    "prop_key": "study_desc.study_info.keywords.keyword",
                    "help_text": "A keyword (or phrase).",
                    "display_type": "text"
                    },
                    {
                    "key": "vocab",
                    "title": "Vocabulary",
                    "type": "string",
                    "prop_key": "study_desc.study_info.keywords.vocab",
                    "help_text": "The controlled vocabulary from which the keyword is extracted, if any.",
                    "display_type": "text"
                    },
                    {
                    "key": "uri",
                    "title": "URL",
                    "type": "string",
                    "prop_key": "study_desc.study_info.keywords.uri",
                    "help_text": "The URL of the controlled vocabulary from which the keyword is extracted, if any.",
                    "rules": {
                    "is_uri": true
                    },
                    "display_type": "text"
                    }
                ],
        }
    }
    }, 
    mounted: function () {
        this.treeItemOpen.push('-1');
    },  
    watch: {       
        'VariableGroups': {
            deep: true,
            handler(val,oldVal){
                this.saveVariableGroupsDebounce();
            }
        }
    }, 
    methods: {  
        treeClick: function(item){
            console.log("tree item clicked",item);
            this.activeItem = item;
        },
        addGroup: function(){
            console.log("adding group",this.activeItem);
            if (!this.activeItem){
                this.activeItem={
                    "vgid": 'VG'+(parseInt(this.getMaxVgId())+1),
                    "group_type":"pragmatic",
                    "label":"new group"
                };
                
                this.VariableGroups.push(this.activeItem);
                return;
            }

            if (this.activeItem){
                if (!this.activeItem.variable_groups){
                    this.$set(this.activeItem, 'variable_groups', []);
                }
                this.activeItem.variable_groups.push({
                    "vgid": 'VG'+(parseInt(this.getMaxVgId())+1),
                    "group_type":"pragmatic",
                    "label":"new group"
                });

                this.treeItemOpen.push(this.activeItem.vgid);
            }
        },
        removeGroup: function(){
            console.log("removing group",this.activeItem);
            if (this.activeItem){
                this.removeGroupByVGID(this.activeItem.vgid);
                this.activeItem=null;                
            }
        },
        removeGroupByVGID: function(vgid){
            console.log("removing group by vgid",vgid);
            let remove=function(item){
                for(let i=0;i<item.length;i++){
                    console.log("searching", item[i].vgid, vgid, item[i].label, item[i].group_type, item[i].vgid=="VG1");
                    if (item[i].vgid==vgid){
                        console.log("group found, removing", item[i].vgid, vgid, item[i].label, item[i].group_type, item[i].vgid=="VG1");
                        item.splice(i,1);
                        return true;
                    }
                    if (remove(item[i].variable_groups)){
                        return true;
                    }
                }
                if (item.variable_groups){
                    for(let i=0;i<item.variable_groups.length;i++){
                        console.log("searching", item);
                        if (item.variable_groups[i].vgid==vgid){
                            console.log("group found, removing", item.variable_groups[i].vgid, vgid, item.variable_groups[i].label, item.variable_groups[i].group_type, item.variable_groups[i].vgid=="VG1");
                            item.variable_groups.splice(i,1);
                            return true;
                        }
                        if (remove(item.variable_groups[i])){
                            return true;
                        }
                    }
                }
                return false;
            }
            remove(this.VariableGroups);
        },
        removeVariable: function(idx){
            this.activeItem.variables.splice(idx,1);
        },

        getMaxVgId: function(){
            let max=0;
            let findMax=function(item){
                for(let i=0;i<item.length;i++){
                    findMax(item[i]);
                }

                if (item.vgid){                    
                    if (parseInt(item.vgid.substr(2))>max){
                        max=item.vgid.substr(2);
                    }
                }
                if (item.variable_groups){
                    item.variable_groups.forEach(function(child){
                        findMax(child);
                    });
                }
            }
            findMax(this.VariableGroups);
            return max;
        },
        
        saveVariableGroupsDebounce: _.debounce(function(data) {
            console.log("savingto db");
            this.saveVariableGroups();
        }, 500),
        saveVariableGroups: function()
        {
            vm=this;
            let url=CI.base_url + '/api/variable_groups/'+vm.project_id;
            form_data={
                'variable_groups':this.VariableGroups
            }

            axios.post(url, 
                form_data
                /*headers: {
                    "name" : "value"
                }*/
            )
            .then(function (response) {
                console.log("updating",response);
                //vm.$set(vm.data_files, vm.edit_item, JSON.parse(JSON.stringify(data)));
                //vm.$store.dispatch('loadDataFiles',{dataset_id:vm.dataset_id});
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
        OnVariableSelection: function(selected){
            this.showDialog=false;
            if (!this.activeItem.variables){
                this.$set(this.activeItem, 'variables', []);
            }
            this.activeItem.variables.push(...selected);
        }
    },
    computed: {
        treeItems(){
            return [
                {
                    'vgid': -1,
                    'label': 'Variable Groups',
                    'variable_groups': this.VariableGroups
                }
            ];
        },
        VariableGroups(){
            if (this.$store.state.variable_groups)
            {
                return this.$store.state.variable_groups;
            }
            return [];
        },
        ActiveItemVariables(){
            if (this.activeItem){
                return this.activeItem.variables;
            }
            return [];
        },
        Variables(){
            $variablesByFile= this.$store.getters.getVariablesAll;
            if (!$variablesByFile){
                return [];
            }

            if (!this.ActiveItemVariables){
                return [];
            }

            ActiveItemVariables=this.ActiveItemVariables;
            $variables = [];

            for (var $file in $variablesByFile){
                for (var $variable in $variablesByFile[$file]){
                    if (ActiveItemVariables.indexOf($variablesByFile[$file][$variable].uid)>-1){
                        console.log("found", $variablesByFile[$file][$variable].uid);
                        $variables.push($variablesByFile[$file][$variable]);
                    }
                }
            }
            console.log("variables dfdfdfdf", $variables);
            return $variables;
        }

    },
    template: `
        <div class="variable-groups-component">
        <dialog-variable-selection v-if="activeItem" :key="activeItem.vgid" v-model="showDialog" :selected_items="ActiveItemVariables" @selected="OnVariableSelection"></dialog-variable-selection>
        
            <div class="container-fluid mt-5">

            <h3>Variable Groups</h3>

            <div class="row">
                <div class="col-md-4">
                    
                    <div class="float-right" style="width:100px;" v-if="activeItem" >
                        <div><v-icon color="primary" @click="addGroup">mdi-plus</v-icon></div>
                        <div><v-icon color="primary" @click="removeGroup">mdi-minus</v-icon></div>
                        <div><v-icon color="primary" >mdi-arrow-up-thin</v-icon></div>
                        <div><v-icon color="primary" >mdi-arrow-down-thin</v-icon></div>
                    </div>

                    <v-treeview 
                        color="warning" 
                        :items="treeItems" 
                        activatable dense 
                        :active.sync="treeActiveItem"
                        :open.sync="treeItemOpen"
                        item-key="vgid" 
                        item-text="label" 
                        expand-icon="mdi-chevron-down" 
                        indeterminate-icon="mdi-bookmark-minus" 
                        on-icon="mdi-bookmark" 
                        off-icon="mdi-bookmark-outline" 
                        item-children="variable_groups">

                        <template #label="{ item }">
                            <span @click="treeClick(item)" :title="item.label" class="tree-item-label">                            
                                    <span>{{item.label}}</span>
                                </span>
                            </span>
                        </template>

                        <template v-slot:prepend="{ item, open }">
                            <v-icon v-if="item.vgid==-1">
                                {{ open ? 'mdi-dresser' : 'mdi-dresser' }}
                            </v-icon>
                            <v-icon v-else-if="item.type=='section'">
                                {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                            </v-icon>
                            <v-icon v-else>
                                {{ open ? 'mdi-folder-open' : 'mdi-folder' }}
                            </v-icon>
                    </template>
                    </v-treeview>
                </div>
                <div class="col-md-8"> 
                    <div v-if="VariableGroups.length==0">
                        <div class="border text-center text-primary p-3 m-3">You don't have any variable groups. Click on the + to create a new variable group</div>
                    </div>

                    <div v-if="activeItem && activeItem.vgid!=-1">
                        <div class="form-group form-field">
                            <label>Group type</label>
                            <input type="text" class="form-control form-control-sm" v-model="activeItem.group_type">
                        </div>
                        <div class="form-group form-field">
                            <label>Label</label>
                            <input type="text" class="form-control form-control-sm" v-model="activeItem.label">
                        </div>

                        <div class="form-group form-field">
                            <label>Universe</label>
                            <v-textarea
                                variant="outlined"
                                v-model="activeItem.universe"
                                class="v-textarea-field"
                                auto-grow
                                clearable
                                rows="2"
                                row-height="40"
                                max-height="200"
                                max-rows="5"                            
                                density="compact"
                            ></v-textarea>
                        </div>

                        <div class="form-group form-field">
                            <label>Notes</label>
                            <v-textarea
                                variant="outlined"
                                v-model="activeItem.notes"
                                class="v-textarea-field"
                                auto-grow
                                clearable
                                rows="2"
                                row-height="40"
                                max-height="200"
                                max-rows="5"                            
                                density="compact"
                            ></v-textarea>
                        </div>

                        <div class="form-group form-field">
                            <label>Text</label>
                            <v-textarea
                                variant="outlined"
                                v-model="activeItem.txt"
                                class="v-textarea-field"
                                auto-grow
                                clearable
                                rows="2"
                                row-height="40"
                                max-height="200"
                                max-rows="5"                            
                                density="compact"
                            ></v-textarea>
                        </div>

                        <div class="form-group form-field">
                            <label>Definitino</label>
                            <v-textarea
                                variant="outlined"
                                v-model="activeItem.definition"
                                class="v-textarea-field"
                                auto-grow
                                clearable
                                rows="2"
                                row-height="40"
                                max-height="200"
                                max-rows="5"                            
                                density="compact"
                            ></v-textarea>
                        </div>


                        <div class="form-group form-field">
                            <label>Variables</label> <button class="btn btn-sm btn-link" @click="showDialog=true">Select variables</button> 
                            <table class="table table-sm table-xs table-bordered" v-if="Variables.length>0">
                                <thead>
                                    <tr>                                    
                                        <th>FID</th>
                                        <th>Name</th>
                                        <th>Label</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(variable,index) in Variables" :key="index">
                                        <td>{{variable.fid}}</td>
                                        <td>{{variable.name}}</td>
                                        <td>{{variable.labl}}</td>
                                        <td>
                                            <button type="btn btn-primary" v-on:click="removeVariable(index)" ><v-icon color="primary" >mdi-trash-can</v-icon></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else>
                                <p class="text-muted text-secondary border text-center p-2">No variables selected</p>
                            </div>
                        </div>


                        <div class="form-group form-field">
                            <label>Concepts</label>
                            <table-grid-component 
                                v-model="activeItem.concepts" 
                                :columns="conceptColumns.props" 
                                class="border elevation-1"
                                >
                            </table-grid-component>
                        </div>


                    </div>       
                    
                </div>
            </div>


            </div>

            

        </div>
    `
});


