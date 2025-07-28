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
            variable_copy:{},//copy of the variable before any editing
            fid:this.file_id,
            variable_search:'',
            changeCaseDialog: false,
            dialogm1: '',
            changeCaseFields:["name"],
            changeCaseType:"title",
            changeCaseUpdateStatus:'',
            edit_item:0,
            edit_items:[],    
            variableMultipleTemplate:{
                "name": "NaN",
                "fid":"",
                "labl": this.$t("multiple_selected"),
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
                "var_wgt_id":"",
                "var_wgt":false,
                "is_key":false,
                "update_required":false,
                "var_type": "",
                "var_concept": [],
                "var_txt": "",
                "var_universe": "",
                "var_std_catgry": [],
                "sum_stats_options":{
                    "wgt": true,
                    "freq": true,
                    "missing": true,
                    "vald": true,
                    "min": true,
                    "max": true,
                    "mean": true,
                    "mean_wgt": true,
                    "stdev": true,
                    "stdev_wgt": true
                },
                "time_stamp":0
              },
            variableMultiple:{},
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
                "var_resp_unit",
                "sum_stats_options",
                "var_wgt_id",
                "var_wgt",
                "var_std_catgry",
                "update_required"
            ],
            showSpreadMetadataDialog: false,
            summaryStatsDialog:{
                show:false                
            },
            has_clicked_edit:true, //to ignore watch from triggering on editVariable click
            is_initializing_multi:false //to ignore watch from triggering during multi-variable initialization
            
        }
    }, 
    created: async function(){
        this.fid=this.$route.params.file_id;
        //this.editVariable(0);
    },
    mounted: function () {
        setTimeout(() => {
            this.editVariable(0);
        }, 2000);
        //this.initializeMultiVariable();
    },
    watch: {
        activeVariable: {            
            deep: true,
            handler(val,oldVal){
                if (this.page_action!="edit"){
                    return;
                }

                if (this.has_clicked_edit){
                    this.has_clicked_edit=false;
                    return;
                }

                //don't save initializing multi-variable selection
                if (this.is_initializing_multi){
                    return;
                }

                //don't save if val is null/undefined
                if (!val){
                    return;
                }

                if (this.variableSelectedCount()>1){
                    // For multi-variable, check if any actual changes were made
                    if (this.hasMultiVariableDataChanged(val)) {
                        //console.log("multi-variable CHANGE DETECTED");
                        this.saveVariableDebounce(val);
                    } else {
                        //console.log("multi-variable no change detected");
                    }
                    return;
                }

              // For single variable, compare with the original copy
              if (JSON.stringify(val)==JSON.stringify(this.variable_copy)){
                  //console.log("no change detected for single variable");
              }
              else{                
                this.saveVariableDebounce(val);                
              }
            }
          }
    },
    methods: {        
        clearVariableSearch: function(){
            this.variable_search='';
        },
        scrollToVariableBottom: function(){            
            document.getElementById('variables-table').scrollTop= document.getElementById('variables-table').scrollHeight;
        },
        scrollToVariable: function(idx=0){
            var id=this.edit_items[0];

            if (idx>0){
                id=idx;    
            }

            this.editVariable(id);
            
            var myElement = document.getElementById('v-'+id);
            var topPos = myElement.offsetTop - 100;

            document.getElementById('variables-rows').scrollTop = topPos;
            console.log("scroll to variable",id, myElement, topPos);
        },
        varNavigate: function(direction)
        {
            total_vars=this.variables.length-1;

            switch(direction) {
                case 'first':
                    this.edit_items=[0];
                  break;
                case 'prev':
                  if (this.edit_items[0]>0){
                    this.edit_items[0]=this.edit_items[0]-1;
                  }
                  break;
                case 'next':
                    if (this.edit_items[0]<total_vars){
                        this.edit_items[0]=this.edit_items[0]+1;
                    }
                    break;  
                case 'last':
                    this.edit_items[0]=total_vars;
                    break;  
              }
            
            this.scrollToVariable();
        },
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

                this.changeCaseUpdateStatus=this.$t("updating_progress", {current: i + 1, total: var_count});
                this.saveVariable(this.variables[i]);
            }

            this.changeCaseUpdateStatus="";
            this.changeCaseDialog=false;
        },
        editVariableMultiple: function(index,isShift=0)
        {
            if (isShift==1){
                start=this.getSelectionLastVariableIndex;
                end=index;

                if (start>end){
                    start=index;
                    end=this.getSelectionLastVariableIndex;
                }

                for(i=start;i<=end;i++){
                    this.edit_items.push(i);
                }

                //remove duplicates
                this.edit_items = [...new Set(this.edit_items)];
            }else{
                if (!this.isVariableSelected(index)){
                    this.edit_items.push(index);                
                }else{
                    if (this.edit_items.length>1){
                        this.removeVariableFromSelection(index);
                    }
                }
            }
            if(this.edit_items.length>1){
                this.page_action="edit";
                this.has_clicked_edit=true;
                this.initializeMultiVariable();
            }
        },
        removeVariableFromSelection: function(item_idx)
        {
            const item = this.edit_items.indexOf(item_idx);
            if (item > -1) {
                this.edit_items.splice(item, 1);
            }
        },
        isVariableSelected: function(index)
        { 
            if (this.edit_items.includes(index)){
                return true;
            }
            return false;
        },
        editVariable:function(index)
        {
            this.exitEditMode();
            this.$nextTick().then(() => {
                this.page_action="edit";
                this.has_clicked_edit=true;
                this.edit_items=[index];
                this.variable_copy=_.cloneDeep(this.variables[index]);
            });
        },
        addVariable:function()
        {
            this.variable_search="";
            this.page_action="edit";

            let url=CI.base_url + '/api/variables/create/'+vm.dataset_id;
            let new_var={
                    "vid": "V" + (this.MaxVariableID+1),
                    "sid": this.dataset_id,
                    "file_id": this.fid,
                    "fid":this.fid,
                    "name": this.$t("untitled"),
                    "labl": this.$t("untitled"),
                    "var_format":{
                        "type":''
                    },
                    "var_catgry": []
              }

            axios.post(url, 
                {
                    "variable": new_var
                }
            )
            .then(function (response) {
                variable=response.data.variable;
                new_var.uid=variable.uid;
                
                vm.scrollToVariableBottom();
                vm.$store.commit('variable_add',{fid:vm.fid, variable:new_var});
                newIdx=vm.variables.length -1;
                vm.editVariable(newIdx);
            })
            .catch(function (error) {
                console.log("error deleting variables",error);
            });
        },
        saveMultiSelectedVariables: function()
        {
            if(this.edit_items.length<=1){
                return;
            }

            for(i=0;i<this.edit_items.length;i++){
                variable_=this.variables[this.edit_items[i]];
                variable_copy=_.cloneDeep(variable_);
                //update key/values
                for(k=0;k<this.variableMultipleUpdateFields.length;k++){
                    field_name=this.variableMultipleUpdateFields[k];

                    //key exists and is not null
                    if(this.variableMultiple[field_name]!=null){

                        if (field_name=='sum_stats_options'){
                            // Skip if the entire sum_stats_options is null (means all variables have different values)
                            if (this.variableMultiple[field_name] === null) {
                                continue;
                            }
                            let sum_stats_options_={}
                            sum_stats_keys=Object.keys(this.variableMultiple[field_name]);
                            
                            // Initialize with existing values from the variable
                            if (variable_.sum_stats_options) {
                                sum_stats_options_ = JSON.parse(JSON.stringify(variable_.sum_stats_options));
                            } else {
                                // If variable doesn't have sum_stats_options, initialize with template
                                sum_stats_options_ = JSON.parse(JSON.stringify(this.variableMultipleTemplate.sum_stats_options));
                            }
                            
                            for(s=0;s<sum_stats_keys.length;s++){
                                sum_stats_key=sum_stats_keys[s];
                                // Only update values that are not null (null means the values differ across variables)
                                if (this.variableMultiple[field_name][sum_stats_key] !== null){
                                    sum_stats_options_[sum_stats_key]=this.variableMultiple[field_name][sum_stats_key];
                                }
                            }
                            Vue.set(variable_,'sum_stats_options',sum_stats_options_);
                        }
                        else{

                            //don't apply weights to string variables
                            if (variable_.var_format.type=='character'){
                                if (field_name=='var_wgt' || field_name=='var_wgt_id'){
                                    continue;
                                }
                            }

                            //variable_[field_name]=JSON.parse(JSON.stringify(this.variableMultiple[field_name]));
                            Vue.set(variable_,field_name,JSON.parse(JSON.stringify(this.variableMultiple[field_name])));
                        }
                    }
                }

                //skip if no changes
                if (JSON.stringify(variable_copy)==JSON.stringify(variable_)){                    
                    console.log("No changes detected for variable", variable_.name);
                    continue;
                }
                
                this.saveVariable(variable_);
            }
        },
        saveVariableDebounce: _.debounce(function(data) {
            
            //multiple variables selected
            if(this.edit_items.length>1){
                this.saveMultiSelectedVariables();
                return false;
            }

            //single variable
            this.saveVariable(data);
        }, 500),
        saveVariable: function(data){///_.debounce(function(data) {
            vm=this;
            let url=CI.base_url + '/api/variables/'+vm.dataset_id;
            axios.post(url, 
                data
            )
            .then(function (response) {
                EventBus.$emit('onSuccess', 'Variable saved!');
                // Update variable_copy only after successful save
                if (vm.edit_items.length === 1) {
                    vm.variable_copy = _.cloneDeep(vm.variables[vm.edit_items[0]]);
                }
            })
            .catch(function (error) {
                console.log(error);
                EventBus.$emit('onFail', 'Failed to save variable');
            })
            .then(function () {
                //console.log("request completed");
            });
        }
        //}, 100)
        ,
        exitEditMode: function()
        {
            if (!this.edit_items[0]){
                return;
            }

            this.page_action="list";
            this.edit_items=[];
        },
        hasDataChanged: function(){
            if (this.edit_items.length === 0) return false;
            return JSON.stringify(this.variables[this.edit_items[0]])!==JSON.stringify(this.variable_copy);
        },
        hasMultiVariableDataChanged: function(val) {
            // Check if any of the editable fields have been modified from template
            for (let field of this.variableMultipleUpdateFields) {
                if (val[field] !== null && val[field] !== undefined && 
                    JSON.stringify(val[field]) !== JSON.stringify(this.variableMultipleTemplate[field])) {
                    return true;
                }
            }
            return false;
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
            if (!confirm(this.$t("confirm_delete_variables"))){
                return;
            }

            let vm=this;
            let url=CI.base_url + '/api/variables/delete/'+vm.ProjectID;
            let var_uid_list=[];

            this.edit_items.forEach((item) => {
                var_uid_list.push(this.variables[item].uid);
            });

            //delete variables
            axios.post(url, 
                {
                    "uid": var_uid_list
                }
            )
            .then(function (response) {
                //need to sort in descending order to delete from the end of the array
                let edit_items_descending=vm.edit_items.sort(function(a, b){return b-a});

                edit_items_descending.forEach((item) => {
                    vm.$store.commit('variable_remove',{fid:vm.fid, idx:item});
                });
                vm.edit_items=[];

                //select item before the deleted item or after the deleted item
                let newIdx=edit_items_descending[0]-1;
                if (newIdx<0){
                    newIdx=edit_items_descending[0]+1;
                }
                vm.editVariable(newIdx);
            })
            .catch(function (error) {
                if (error.response && error.response.data && error.response.data.message){
                    alert(vm.$t("error_deleting_variables") + ": " + error.response.data.message);
                }else{
                    alert(vm.$t("error_deleting_variables"));
                }
                console.log("error deleting variables",error);
            });
        },
        
        variableSelectedCount: function()
        {
            return this.edit_items.length;
        },
        variableSelectedNames: function()
        {
            let var_names=[];
            this.edit_items.forEach((item) => {
                var_names.push(this.variables[item].name);
            });

            return var_names;
        },
        //on selection of multiple variables
        initializeMultiVariable: function(){
            console.log("Initializing multi-variable selection");
            this.is_initializing_multi = true;
            
            this.variableMultiple=JSON.parse(JSON.stringify(this.variableMultipleTemplate));

            //initialize variableMultiple using the first variable from the selection
            let fields=Object.keys(this.variableMultipleTemplate);
            let first_variable=this.selectedVariables[0];            

            //loop through editable properties
            // fill values from the first variable
            for(i=0;i<fields.length;i++){

                let field=fields[i];
                //check if property is set for the first variable
                if (first_variable[field]){
                    if (field === "sum_stats_options") {
                        // Deep copy sum_stats_options to avoid reference issues
                        if (first_variable[field]) {
                            this.variableMultiple[field] = JSON.parse(JSON.stringify(first_variable[field]));
                        } else {
                            // If first variable doesn't have sum_stats_options, use template
                            this.variableMultiple[field] = JSON.parse(JSON.stringify(this.variableMultipleTemplate[field]));
                        }
                    } else {
                        this.variableMultiple[field] = first_variable[field];
                    }
                }        
            }

            let sum_stats_props=Object.keys(this.variableMultipleTemplate.sum_stats_options);

            //loop all variables
            //compare with first_variable
            //if different, set to null
            for(i=1;i<this.selectedVariables.length;i++){
                let variable=this.selectedVariables[i];
                for(k=0;k<fields.length;k++){
                    let field=fields[k];
                    console.log("field",field);
                    if (field=="sum_stats_options"){
                        // Check if both variables have sum_stats_options
                        if (variable[field] && this.variableMultiple[field]) {
                            for(p=0;p<sum_stats_props.length;p++){
                                let prop_name = sum_stats_props[p];
                                // Compare values, handling undefined/null cases
                                let current_val = this.variableMultiple[field][prop_name];
                                let compare_val = variable[field][prop_name];
                                
                                if (current_val !== compare_val){
                                    this.variableMultiple[field][prop_name] = null;
                                }
                            }
                        } else if (!variable[field] && this.variableMultiple[field]) {
                            // If current variable doesn't have sum_stats_options but template does, 
                            // compare with template values
                            for(p=0;p<sum_stats_props.length;p++){
                                let prop_name = sum_stats_props[p];
                                let template_val = this.variableMultipleTemplate[field][prop_name];
                                let current_val = this.variableMultiple[field][prop_name];
                                
                                if (current_val !== template_val){
                                    this.variableMultiple[field][prop_name] = null;
                                }
                            }
                        } else {
                            // If one doesn't have sum_stats_options, set to null
                            this.variableMultiple[field] = null;
                        }
                    }
                    else {
                        if (JSON.stringify(variable[field])!==JSON.stringify(this.variableMultiple[field])){
                            this.variableMultiple[field]=null;
                        }
                    }
                }
            }

            // Clear the flag after initialization is complete
            this.$nextTick(() => {
                console.log("Multi-variable initialization complete");
                this.is_initializing_multi = false;
            });

        },        
        variableActiveClass: function(idx,variable_name)
        {
            if (!variable_name){
                return;
            }

            let classes=[];
            variable_name=variable_name.toLowerCase().trim();

            //check for duplicate variable names
            if (this.duplicateVariableNames[variable_name]){
                classes.push('variable-name-duplicate bg-warning');
            }

            if (variable_name.trim()==''){
                classes.push('variable-name-empty bg-warning');
            }

            if (this.isVariableSelected(idx)){
                classes.push('activeRow');
            }

            return classes.join(' ');
        },        
        refreshSummaryStats: async function(){

            if (!confirm(this.$t("confirm_import_summary_statistics"))){
                return;
            }

            this.summaryStatsDialog={
                show:true,
                title:this.$t("summary_stats"),
                loading_message:this.$t("processing_please_wait"),
                message_success:'',
                message_error:'',
                is_loading:true
            }

            try{
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueue',{file_id:this.fid});
                console.log("sumstats queued",result);
                this.importSummaryStatisticsQueueStatusCheck(this.fid,result.data.job_id);
            }catch(e){
                console.log("failed",e);
                this.summaryStatsDialog.is_loading=false;
                this.summaryStatsDialog.message_error=this.$t("failed_to_import_stats") + ": " + e.response.data.message;
            }
        },
        importSummaryStatisticsQueueStatusCheck: async function(file_id,job_id){
            this.summaryStatsDialog.is_loading=true;
            this.summaryStatsDialog.loading_message=this.$t("processing_please_wait");
            try{
                await this.sleep(5000);
                let result=await this.$store.dispatch('importDataFileSummaryStatisticsQueueStatusCheck',{file_id:file_id, job_id:job_id});
                console.log("job updated",result);
                                
                if (result.data.job_status!=='done'){
                    this.importSummaryStatisticsQueueStatusCheck(file_id,job_id);
                }else if (result.data.job_status==='done'){
                    await this.reloadDataFileVariables();
                    this.summaryStatsDialog.is_loading=false;
                    this.summaryStatsDialog.message_success=this.$t("sum_stats_imported_success");
                }
                
            }catch(e){
                console.log("failed",e);
                this.summaryStatsDialog.is_loading=false;
                this.summaryStatsDialog.message_error=this.$t("failed") + ": " + e.response.data.message;
            }
        },
        reloadDataFileVariables: async function(){
            return await this.$store.dispatch('loadVariables',{dataset_id:this.ProjectID, fid:this.fid});
        },
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        getVariableNameByUID: function(uid)
        {
            let variable=this.getVariableByUID(uid);
            if (variable){
                return variable.name;
            }
        },
        getVariableByUID: function(uid)
        {
            let variable=null;
            this.variables.forEach((item) => {
                if (item.uid==uid){
                    variable=item;
                }
            });

            return variable;
        },
        getVariableIndexByUID: function(uid)
        {
            let idx=-1;
            this.variables.forEach((item, index) => {
                if (item.uid==uid){
                    idx=index;
                }
            });

            return idx;
        },
        OnVariableUpdate: function(variable)
        {
            if (this.edit_items.length<1){
                return;
            }

            for (let i=0;i<this.edit_items.length;i++){
                Vue.set (this.variables, this.edit_items[i], variable);
            }
        },
        onVariableKeydown: function(event,idx,field_name){
            let UP = 38;
            let DOWN = 40;

            if (idx<0 || idx>=this.variables.length){
                return;
            }

            let mv_idx=idx;

            if (event.keyCode==UP){
                mv_idx=idx-1;
            }else if (event.keyCode==DOWN){
                mv_idx=idx+1;
            }
            
            let el =this.$refs[field_name][mv_idx];
            if (el){
                this.editVariable(mv_idx);
                el.focus();
            }
        },
        onVariableDrag: function(event)
        {
            console.log("onVariableDrag",event);

            this.editVariable(event.newIndex);
            
            let sorted_variables=[];
            let vm=this;
                        
            this.variables.forEach((item, index) => {
                sorted_variables.push(item.uid);                
            });

            console.log("sorted_variables",JSON.stringify(sorted_variables));
            this.updateVariablesOrder(sorted_variables);
        },
        updateVariablesOrder: function(sorted_variables)
        {
            let vm=this;
            let formData = {
                "sorted_uid":sorted_variables
            }

            let url=CI.base_url + '/api/variables/order/'+this.ProjectID + '/' + this.fid;
            axios.post(url, formData,{
                headers: {
                    'Content-Type': 'application/json'
                    }
            }).then(function(response) {
                console.log("variables order updated",response);                
            })
            .catch(function(response) {
                alert("Error updateVariablesOrder");
                console.log("updateVariablesOrder error",response);
            });
        }

    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        isSingleVariableSelected: function()
        {
            if (this.edit_items.length==1){
                return true;
            }
            return false;
        },
        getSelectionLastVariableIndex: function(){
            return this.edit_items[this.edit_items.length -1];
        },
        SingleVariableIndex: function()
        {
            if (this.edit_items.length==1){
                return this.edit_items[0];
            }
        },
        activeVariable: function()
        {

            if (this.edit_items.length>1){
                return this.variableMultiple;
            }

            //for single variable selected
            if (this.edit_items.length==1){
                let variable_= this.variables[this.edit_items[0]];
                if (variable_ && !variable_.var_invalrng){
                    Vue.set(variable_, 'var_invalrng', {
                        "values":[]
                    });
                }
                return variable_;
            }
        },        
        selectedVariables: function(){
            let variables=[];
            this.edit_items.forEach((variable_idx)=>{
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
                    return (item.name + item.labl)
                        .toUpperCase()
                        .includes(this.variable_search.toUpperCase())
                })
                
                return tmpVars;
            }

            if (!Array.isArray(vars)){
                return [];
            }

            return vars;
        },
        duplicateVariableNamesCount(){
            return Object.keys(this.duplicateVariableNames).length;
        },
        duplicateVariableNames(){
            let names={};
            
            if (Array.isArray(this.variables)==false){
                return names;
            }

            this.variables.forEach((variable)=>{
                let name=variable.name;

                if (!name){
                    return;
                }

                //lowercase variable names and trim
                name=name.toLowerCase().trim();

                if (names[name]){
                    names[name]++;
                }else{
                names[name]=1;
                }
            });

            //only return names that are duplicated
            for (let name in names){
                if (names[name]==1){
                    delete names[name];
                }
            }
            return names;
        }
    },
    template: `
        <div style="margin-top:20px;" class="variable-list-component" >
            <splitpanes class="default-theme" >
            <pane max-size="90" size="70">
                <splitpanes horizontal>
                    <pane min-size="5" >
                    <!--variables-start-->
                    <div  class="border section-list-container ">
                        <!--variables-header-->
                        <div class="section-list-header">
                            <div class="row no-gutters section-title p-1 bg-variable" style="font-size:small;position:relative;">
                                <div class="col-2">
                                    <div class="p-1">
                                    <strong>{{$t("variables")}}</strong>
                                    <span v-if="variables" class="badge badge-light">{{variables.length}}</span>                                    
                                    </div>
                                </div>

                                <div class="col-3">
                                    <div class="input-group">
                                        <input type="text" class="bg-light form-control form-control-xs" :placeholder="$t('search')" v-model="variable_search">
                                        <div class="input-group-append">
                                        <button class="btn btn-secondary btn-sm btn-xs" type="button">
                                            <i class="fa fa-search"></i>
                                        </button>

                                        <button class="btn btn-link-outline btn-sm btn-xs" type="button" v-show="variable_search.length>0" @click="clearVariableSearch">
                                        {{$t("clear")}}
                                        </button>
                                        
                                        </div>
                                    </div>
                                </div>


                                <div class="col">
                                
                                    <div class="float-right" >
                                    
                                        <span v-if="duplicateVariableNamesCount>0">
                                            <v-icon :title="$t('duplicate_variables_count', {count: duplicateVariableNamesCount})" aria-hidden="false" class="var-icon" style="color:red">mdi-alert-box</v-icon>                                            
                                        </span>

                                        <span v-show="edit_items.length>0">
                                            <span @click="refreshSummaryStats" :title="$t('refresh_stats')">
                                                <v-icon aria-hidden="false" class="var-icon">mdi-database-sync</v-icon>
                                            </span>

                                            <span @click="changeCaseDialog=true" :title="$t('change_case')">
                                                <v-icon aria-hidden="false" class="var-icon">mdi-format-letter-case</v-icon>
                                            </span>

                                            <span @click="spreadMetadata" :title="$t('spread_metadata')">
                                                <v-icon aria-hidden="false" class="var-icon">mdi-content-copy</v-icon>
                                            </span>

                                            <span @click="deleteVariable" :title="$t('delete_selection')">
                                                <v-icon aria-hidden="false" class="var-icon">mdi-trash-can-outline</v-icon>
                                            </span>
                                        </span>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <!--variables-header-->


                            
                            <div class="section-list-body" id="variables-container" >
                                <div id="variables-rows" class="section-rows variable-rows">
                                <table id="variables-table" class="table table-striped table-bordered table-sm table-hover table-variables">                                    
                                    <tbody is="draggable" :list="variables" tag="tbody" handle=".handle" @end="onVariableDrag">
                                    <tr v-for="(variable, index) in variables"  
                                        @click.alt.exact="editVariableMultiple(index)" 
                                        @click.ctrl.exact="editVariableMultiple(index)" 
                                        @click.shift.exact="editVariableMultiple(index,1)" 
                                        @click.exact="editVariable(index)"                                         
                                        
                                        :class="variableActiveClass(index,variable.name)"                                         
                                        :id="'v-'+index"
                                    >
                                        <td class="var-vid-td bg-secondary handle">V{{index+1}}</td>

                                        <td class="var-name-edit">
                                            <!-- <div><input vonkeydown="onVariableKeydown($event,index,'var_name')" class="var-labl-edit" type="text" v-model="variable.name" ref="var_name" /></div> -->
                                            {{variable.name}}
                                        </td>
                                        <td>
                                            <div><input vonkeydown="onVariableKeydown($event,index,'var_labl')"  class="var-labl-edit" type="text" v-model="variable.labl" ref="var_labl"/></div>
                                        </td>
                                        <td>
                                            <span v-if="variable.var_format && variable.var_format.type">
                                                <span v-if="variable.var_format.type=='character' || variable.var_format.type=='fixed'" :title="$t(variable.var_format.type)">
                                                    <v-icon aria-hidden="false" class="vdar-icon">mdi-alpha-a-box-outline</v-icon>
                                                </span>
                                                <span v-if="variable.var_format.type=='numeric'" :title="$t(variable.var_format.type)">
                                                    <v-icon aria-hidden="false" class="vdar-icon">mdi-numeric-1-box-outline</v-icon>
                                                </span>                                                

                                            </span>
                                            <span v-if="variable.var_catgry && variable.var_catgry.length>0" :title="variable.var_catgry.length">
                                                <v-icon aria-hidden="false" class="vdar-icon">mdi-format-list-numbered</v-icon>
                                            </span>
                                            <v-icon :title="$t('weight_variable')" v-if="variable.var_wgt==1" aria-hidden="false" class="vdar-icon">mdi-alpha-w</v-icon>
                                            <v-icon :title="$t('weighted')" v-if="variable.var_wgt_id && variable.var_wgt_id.length>0" aria-hidden="false" class="vdar-icon">mdi-scale-balance</v-icon>
                                            <v-icon :title="$t('require_stats_update')" v-if="variable.update_required" aria-hidden="false" class="vdar-icon text-danger">mdi-sync-alert</v-icon>
                                            <v-icon :title="$t('variable_is_a_key')" class="vdar-cion" v-if="variable.is_key && variable.is_key==1">mdi-key-outline</v-icon>
                                        </td>                                        
                                    </tr>
                                    </tbody>
                                </table>
                                

                                <spread-metadata v-if="showSpreadMetadataDialog" v-model="showSpreadMetadataDialog" :variables="selectedVariables"></spread-metadata>
                                </div>

                            </div>
                        </div>
                    <!--variables-end-->
                    </pane>
                    <pane size="30">
                    <!--documentation-->
                    <div class="section-list-container variable-documentation-container" >

                        <div class="section-title p-1 bg-variable">
                            <div class="row no-gutters">
                                <div class="col">
                                    <div class="pt-1" v-if="activeVariable">{{activeVariable.name}} - <span v-if="activeVariable.labl">{{activeVariable.labl.substring(0,50)}}</span> </div>
                                </div>
                                <div class="col-3 pr-3">
                                    <div class="float-right">
                                        <span @click="varNavigate('first')"><v-icon aria-hidden="false" class="var-icon">mdi-chevron-double-left</v-icon></span>
                                        <span @click="varNavigate('prev')"><v-icon aria-hidden="false" class="var-icon">mdi-chevron-left</v-icon></span>
                                        <span @click="varNavigate('next')"><v-icon aria-hidden="false" class="var-icon">mdi-chevron-right</v-icon></span>
                                        <span @click="varNavigate('last')"><v-icon aria-hidden="false" class="var-icon">mdi-chevron-double-right</v-icon></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                            <div class="p-1 border" style="height:inherit;overflow:auto;background:white;">
                                <div v-show="page_action=='edit' " >
                                    <div v-if="activeVariable">
                                        <variable-edit  :variable="activeVariable" @input="OnVariableUpdate" :multi_key="edit_items"  :index_key="SingleVariableIndex" ></variable-edit>
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
                    <div style="height:100%;" class="border">
                    <variable-categories v-if="isSingleVariableSelected" :edit_index="SingleVariableIndex"  :value="activeVariable" ></variable-categories>
                    </div>
                    <!--categories-end-->
                </pane>
                <pane size="40" min-size="10">
                    <div style="height:100%;overflow:auto;" >
                        <variable-info v-if="isSingleVariableSelected" :edit_index="SingleVariableIndex"  :value="activeVariable" />
                    </div>
                </pane>
                </splitpanes>
            </pane>
            </splitpanes>

            <?php echo $this->load->view("metadata_editor/modal-dialog-changecase",null,true); ?>
            
            <vue-dialog-component v-model="summaryStatsDialog"></vue-dialog-component> 
        </div>
    `
})