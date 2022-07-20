//spread metadata for variables
Vue.component('spread-metadata', {
    props:['variables','value'],
    data: function () {    
        return {
            //field_data: this.value,
            //dialog:this.value,
            dialogm1: '',
            variable_matches:[],
            options:['documentation','question','categories'],
            options_fields:{
                'documentation':[
                    'var_txt',
                    'var_universe',
                    'var_resp_unit',
                    'var_imputation',
                    'var_codinstr'
                ],
                'question':[
                    "var_qstn_preqtxt",
                    "var_qstn_qstnlit",
                    "var_qstn_postqtxt",
                    "var_qstn_ivuinstr"
                ],
                'categories':[
                    'var_catgry'
                ]
            }
        }
    },
    created: function(){
        //this.fid=this.$route.params.file_id;
        this.spreadMetadataBatchSearch();
    },
    methods:{
        spreadMetadata: function()//apply metadata to selected variables
        {
            this.variable_matches.forEach((match, index) => {
                console.log(match.selected);
                if(match.selected==true){
                    console.log("spreading metadata",match);
                    this.updateVariable(match.metadata);
                }
            });
            this.$emit('input', false);
        },
        updateVariable: function(targetVariable){

            //get source variable
            sourceVariable=this.sourceVariable(targetVariable.name);

            if (!sourceVariable){
                alert("SourceVariable not found");
                return false;
            }

            //update target variable fields
            this.options.forEach((option_, index) => {
                this.options_fields[option_].forEach((option_field, index_) => {
                    if(option_field=='var_catgry'){
                        targetVariable[option_field]=this.getVariableCategories(sourceVariable);
                    }else{
                        targetVariable[option_field]=sourceVariable[option_field];
                    }
                });
            });
            
            this.saveVariable(targetVariable);
        },
        getVariableCategories: function(variable){
            let categories=[];
            if (variable.var_catgry){
                variable.var_catgry.forEach((category,index)=>{
                    categories.push({
                        'value':category.value,
                        'labl': category.labl
                    });
                });
            }
            return categories;
        },
        saveVariable: function(data)
        {
            console.log("variable saved in db", data);
            vm=this;
            let url=CI.base_url + '/api/datasets/variables/'+vm.IDNO;
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
        },
        sourceVariable: function(variableName)
        {
            for(v=0;v<this.variables.length;v++){
                if (this.variables[v].name==variableName){
                    return this.variables[v];
                }
            }
            return false;
        },
        spreadMetadataBatchSearch: function()
        {
            this.variables.forEach((variable, index) => {
                this.spreadMetadataSearch(variable);
            });
        },
        spreadMetadataSearch: function(selected_variable)
        {
            let variables=this.$store.getters.getVariablesAll;
            let datafile_names=Object.keys(variables);

            datafile_names.forEach((fid, index) => {
                
                if (fid==selected_variable.fid){return;}
                if (!variables[fid].length){return;}

                variables[fid].forEach((variable, index) => {
                    if(variable.name==selected_variable.name){
                        console.log("match found",fid,variable.name);
                        datafilename=this.dataFilesDictionary[variable.fid];
                        this.variable_matches.push({
                            'vid':variable.vid,
                            'name':variable.name,
                            'fid': variable.fid,
                            'filename': datafilename,
                            'var_type':variable.var_type,
                            'type_match':variable.var_format.type==selected_variable.var_format.type,
                            'selected':false,
                            'metadata':variable
                        });
                    }
                });
           
            });
        }
    },
    
    computed: {
        IDNO(){
            return this.$store.getters["getIDNO"];
        },
        dataFiles(){
            return this.$store.getters.getDataFiles;
        },
        dataFilesDictionary()
        {
            let dict={};
            for(i=0;i<this.dataFiles.length;i++){
                dict[this.dataFiles[i].file_id]=this.dataFiles[i].file_name;
            }

            return dict;
        }
    },  
    template: `
            <div class="spread-metadata-component">

            <template>
                <v-layout row justify-center>
                    <v-dialog
                    v-model="value" persistent 
                    scrollable                    
                    max-width="650px"
                    >
                    
                    <v-card>
                        <v-card-title style="m-0 p-1">
                            <div>Spread metadata <span v-if="variable_matches.length>0">[{{variable_matches.length}} matches]</span> </div>
                            <v-spacer></v-spacer>
                            <v-btn right text color="red" @click.native="$emit('input', false)">Close</v-btn>
                        </v-card-title>
                        <v-divider class="m-0 p-1"></v-divider>
                        <v-card-text>

                        <div style="height:200px;overflow:auto;">

                        <div v-if="variable_matches.length==0">No matches found</div>
                        <table class="table table-sm table-bordered" v-if="variable_matches.length>0" style="font-size:12px;">
                            <thead>
                            <tr>
                                <td></td>
                                <td>FID</td>
                                <td>Dataset</td>
                                <td>Variable</td>
                                <td>Type</td>
                                <td>Type match</td>
                            </tr>
                            </thead>
                            <tr v-for="match in variable_matches">
                                <td><input type="checkbox" v-model="match.selected"/></td>
                                <td>{{match.fid}}</td>
                                <td>{{match.filename}}</td>
                                <td>{{match.name}}</td>
                                <td>{{match.var_type}}</td>
                                <td>{{match.type_match}}</td>
                            </tr>
                        </table>

                        </div>

                        <div>
                            <div class="border-bottom"><strong>Spread metadata</strong></div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="info" id="info" v-model="options">
                                <label class="form-check-label" for="info">
                                    Variable information
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="documentation" id="documentation" v-model="options">
                                <label class="form-check-label" for="documentation">
                                    Variable documentation
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="categories" id="categories" v-model="options">
                                <label class="form-check-label" for="categories">
                                    Categories
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="question" id="question" v-model="options">
                                <label class="form-check-label" for="question">
                                    Question texts
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="weight" id="weights" v-model="options">
                                <label class="form-check-label" for="weights">
                                    Weights
                                </label>
                            </div>
                        </div>

                        
                        </v-card-text>
                        <v-divider></v-divider>
                        <v-card-actions>
                        <v-btn text color="red" @click="spreadMetadata">Spread metadata</v-btn>
                        <v-btn text color="red" @click.native="$emit('input', false)">Close</v-btn>
                        
                        </v-card-actions>
                    </v-card>
                    </v-dialog>
                
                    </v-layout>
                    </template>
            </div>          
            `    
});

