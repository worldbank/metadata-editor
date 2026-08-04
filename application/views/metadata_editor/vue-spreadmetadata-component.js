//spread metadata for variables
Vue.component('spread-metadata', {
    props:['variables','value'],
    data: function () {    
        return {
            //field_data: this.value,
            //dialog:this.value,
            dialogm1: '',
            variable_matches:[],
            filterFid: '',
            chk_select_all:false,
            options:['info','documentation','question','categories'],
            options_fields:{
                'info':[
                    'labl'
                ],
                'documentation':[
                    'var_txt',
                    'var_notes',
                    'var_universe',
                    'var_resp_unit',
                    'var_imputation',
                    'var_codinstr'
                ],
                'question':[
                    "var_qstn_preqtxt",
                    "var_qstn_qstnlit",
                    "var_qstn_postqtxt",
                    "var_qstn_ivulnstr"
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
            let url=CI.base_url + '/api/variables/'+vm.ProjectID;
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
            if (!variableName) return false;
            var nameLower = variableName.toLowerCase();
            for(v=0;v<this.variables.length;v++){
                if (this.variables[v].name && this.variables[v].name.toLowerCase()===nameLower){
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
            if (!variables || typeof variables !== 'object') { return; }
            let datafile_names=Object.keys(variables);

            datafile_names.forEach((fid, index) => {
                
                if (fid==selected_variable.fid){return;}
                if (!Array.isArray(variables[fid]) || !variables[fid].length){return;}

                variables[fid].forEach((variable, index) => {
                    if(variable.name && selected_variable.name && variable.name.toLowerCase()===selected_variable.name.toLowerCase()){
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
        },
        toggleSelection: function(){
            var list = this.filteredVariableMatches;
            var allSelected = list.length > 0 && list.every(function(m){ return m.selected; });
            list.forEach(function(m){ m.selected = !allSelected; });
        }
    },
    
    computed: {
        IDNO(){
            return this.$store.getters["getIDNO"];
        },
        ProjectID(){
            return this.$store.getters["getProjectID"];            
        },
        dataFiles(){
            return this.$store.getters.getDataFiles;
        },
        dataFilesDictionary()
        {
            var dict={};
            for(var i=0;i<this.dataFiles.length;i++){
                dict[this.dataFiles[i].file_id]=this.dataFiles[i].file_name;
            }
            return dict;
        },
        sortedVariableMatches: function(){
            return this.variable_matches.slice().sort(function(a,b){
                return (a.fid || '').localeCompare(b.fid || '');
            });
        },
        filteredVariableMatches: function(){
            var list = this.sortedVariableMatches;
            if (!this.filterFid) return list;
            return list.filter(function(m){ return m.fid === this.filterFid; }.bind(this));
        },
        filterFidOptions: function(){
            var fids = [];
            var seen = {};
            this.sortedVariableMatches.forEach(function(m){
                if (!seen[m.fid]) { seen[m.fid] = true; fids.push(m.fid); }
            });
            var dict = this.dataFilesDictionary;
            var options = [{ text: this.$t('all'), value: '' }];
            fids.forEach(function(fid){
                var label = dict[fid] ? fid + ' - ' + dict[fid] : fid;
                options.push({ text: label, value: fid });
            });
            return options;
        },
        allFilteredSelected: function(){
            var list = this.filteredVariableMatches;
            return list.length > 0 && list.every(function(m){ return m.selected; });
        },
        someFilteredSelected: function(){
            var list = this.filteredVariableMatches;
            return list.some(function(m){ return m.selected; }) && !this.allFilteredSelected;
        },
        currentFilterLabel: function(){
            var opt = this.filterFidOptions.find(function(o){ return o.value === this.filterFid; }.bind(this));
            return opt ? opt.text : this.$t('all');
        }
    },  
    template: `
            <div class="spread-metadata-component">
                <v-dialog
                    v-model="value"
                    persistent
                    max-width="1200"
                    width="90vw"
                    content-class="spread-metadata-dialog"
                >
                    <v-card class="d-flex flex-column" style="max-height: 85vh; height: 100%;">
                        <v-toolbar flat dense class="flex-grow-0">
                            <v-toolbar-title>
                                {{ $t('spread_metadata') }}
                                <span v-if="variable_matches.length > 0" class="grey--text text--darken-1 font-weight-regular">[{{ variable_matches.length }} {{ $t('matches') }}]</span>
                            </v-toolbar-title>
                            <v-spacer></v-spacer>
                            <v-btn small icon @click="$emit('input', false)">
                                <v-icon>mdi-close</v-icon>
                            </v-btn>
                        </v-toolbar>
                        <v-divider></v-divider>
                        <v-card-text class="flex-grow-1 overflow-auto pa-0">
                            <v-container fluid class="fill-height pa-4" style="max-width: 100%;!important">
                                <v-row class="fill-height">
                                    <v-col cols="12" md="4" lg="3" class="d-flex flex-column">
                                        <div class="subtitle-2 mb-3 v-font-weight-bold">{{ $t('spread_metadata_options')}}</div>
                                        
                                            <v-checkbox v-model="options" value="info" hide-details class="mt-0 v-font-weight-normal">
                                                <template v-slot:label><span class="body-2">{{ $t('variable_label') }}</span></template>
                                            </v-checkbox>                                            
                                        
                                        <v-tooltip bottom max-width="280">
                                            <template v-slot:activator="{ on }">
                                                <div v-on="on" class="d-inline-block">
                                                    <v-checkbox v-model="options" value="documentation" hide-details class="mt-0 v-font-weight-normal">
                                                        <template v-slot:label>
                                                            <span class="body-2">{{ $t('variable_documentation') }}</span> <v-icon small color="info">mdi-information-outline</v-icon>
                                                        </template>
                                                    </v-checkbox>
                                                </div>
                                            </template>
                                            <span>{{ $t('variable_documentation_tooltip') }}</span>
                                        </v-tooltip>
                                        <v-tooltip bottom max-width="280">
                                            <template v-slot:activator="{ on }">
                                                <div v-on="on" class="d-inline-block">
                                                    <v-checkbox v-model="options" value="categories" hide-details class="mt-0 v-font-weight-normal">
                                                        <template v-slot:label>
                                                            <span class="body-2">{{ $t('categories') }}</span> <v-icon small color="info">mdi-information-outline</v-icon>
                                                        </template>
                                                    </v-checkbox>
                                                </div>
                                            </template>
                                            <span>{{ $t('categories_tooltip') }}</span>
                                        </v-tooltip>
                                        <v-tooltip bottom max-width="280">
                                            <template v-slot:activator="{ on }">
                                                <div v-on="on" class="d-inline-block">
                                                    <v-checkbox v-model="options" value="question" hide-details class="mt-0 v-font-weight-normal">
                                                        <template v-slot:label>
                                                            <span class="body-2">{{ $t('question_and_instructions') }}</span> <v-icon small color="info">mdi-information-outline</v-icon>
                                                        </template>
                                                    </v-checkbox>
                                                </div>
                                            </template>
                                            <span>{{ $t('question_and_instructions_tooltip') }}</span>
                                        </v-tooltip>                                        
                                    </v-col>
                                    <v-col cols="12" md="8" lg="9" class="d-flex flex-column overflow-hidden">
                                        <div class="d-flex align-center mb-2" v-if="variable_matches.length > 0">
                                            <span class="body-2 mr-2">{{ $t('filter_by_fid')}}:</span>
                                            <v-menu offset-y left>
                                                <template v-slot:activator="{ on, attrs }">
                                                    <v-btn x-small depressed v-bind="attrs" v-on="on" class="text-capitalize">
                                                        {{ currentFilterLabel }}
                                                        <v-icon right small>mdi-chevron-down</v-icon>
                                                    </v-btn>
                                                </template>
                                                <v-list dense>
                                                    <v-list-item v-for="opt in filterFidOptions" :key="opt.value" @click="filterFid = opt.value">
                                                        <v-list-item-title class="body-2">{{ opt.text }}</v-list-item-title>
                                                    </v-list-item>
                                                </v-list>
                                            </v-menu>
                                            <span class="body-2 grey--text ml-2" v-if="filterFid">({{ filteredVariableMatches.length }} {{ $t('matches') }})</span>
                                        </div>
                                        <div v-if="filteredVariableMatches.length === 0" class="body-2 grey--text">
                                            {{ variable_matches.length === 0 ? $t('no_matches_found') : $t('no_matches_for_filter') }}
                                        </div>
                                        <v-simple-table v-else dense class="elevation-1" style="font-size: 0.875rem; min-height: 0;">
                                            <thead>
                                                <tr>
                                                    <th class="text-left" style="width: 48px;">
                                                        <v-checkbox
                                                            :input-value="allFilteredSelected"
                                                            :indeterminate="someFilteredSelected"
                                                            @change="toggleSelection"
                                                            hide-details
                                                            class="mt-0 pt-0"
                                                        ></v-checkbox>
                                                    </th>
                                                    <th class="text-left">{{ $t('fid') }}</th>
                                                    <th class="text-left">{{ $t('dataset') }}</th>
                                                    <th class="text-left">{{ $t('variable') }}</th>
                                                    <th class="text-left">{{ $t('type') }}</th>
                                                    <th class="text-left">{{ $t('type_match') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="match in filteredVariableMatches" :key="match.vid + '-' + match.fid">
                                                    <td>
                                                        <v-checkbox
                                                            v-model="match.selected"
                                                            hide-details
                                                            class="mt-0 pt-0"
                                                        ></v-checkbox>
                                                    </td>
                                                    <td>{{ match.fid }}</td>
                                                    <td>{{ match.filename }}</td>
                                                    <td>{{ match.name }}</td>
                                                    <td><span v-if="match.metadata && match.metadata.var_format">{{match.metadata.var_format.type}}</span></td>
                                                    <td>
                                                        <v-icon v-if="match.type_match" small color="success">mdi-check-circle</v-icon>
                                                        <v-icon v-else small color="grey">mdi-close-circle</v-icon>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </v-simple-table>
                                    </v-col>
                                </v-row>
                            </v-container>
                        </v-card-text>
                        <v-divider></v-divider>
                        <v-card-actions class="pa-4 flex-grow-0">
                            <v-btn small color="primary" @click="spreadMetadata">
                                {{ $t('spread_metadata') }}
                            </v-btn>
                            <v-btn small text @click="$emit('input', false)">
                                {{ $t('cancel') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </div>
            `    
});

