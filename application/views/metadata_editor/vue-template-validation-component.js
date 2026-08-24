/// Template validation component
Vue.component('template-validation-component', {
    data () {
        return {
          validation_errors: "",
          schema_validation: null,
          variables_validation: null,
          variables_validation_errors: "",
          template_idx:-1,
          template_validation:[],
          validation_report:[],
          validation_delay_ms: 2000,
          validation_debounce_ms: 600
        }
      },
    watch:{
        ProjectMetadata: {
            immediate: true,
            handler: function (val, oldVal) {
                var vm = this;
                vm._validationDelayTimer && clearTimeout(vm._validationDelayTimer);
                vm._validationDelayTimer = setTimeout(function () {
                    vm._validationDelayTimer = null;
                    if (typeof requestIdleCallback === 'function') {
                        requestIdleCallback(function () { vm.runValidationDebounced(); }, { timeout: 1500 });
                    } else {
                        vm.runValidationDebounced();
                    }
                }, vm.validation_delay_ms);
            }
        }
    },
    created: function() {
        var vm = this;
        var debounceMs = vm.validation_debounce_ms || 600;
        this.runValidationDebounced = typeof _.debounce === 'function'
            ? _.debounce(function () {
                vm.validateProject();
                vm.projectValidationReport();
            }, debounceMs)
            : function () {
                vm.validateProject();
                vm.projectValidationReport();
            };
    },
    beforeDestroy: function() {
        if (this._validationDelayTimer) {
            clearTimeout(this._validationDelayTimer);
        }
    },
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
        ProjectIDNo(){
            return this.$store.state.idno;
        },
        ProjectTemplates()
        {
            return this.$store.state.templates;
        },
        ProjectTemplate()
        {
            return this.$store.state.formTemplate;
        },
        projectTemplateUID(){
            return this.$store.state.formTemplate.uid;
        },
        projectTemplateSelectedIndex: {
            get: function () {
                if (this.template_idx>-1){
                    return this.template_idx;
                }

                let templates=this.ProjectTemplates;
                let idx=-1;
                for(let i=0;i<templates.length;i++){
                    if(templates[i].uid==this.projectTemplateUID){
                        idx=i;
                        break;
                    }
                }                
                return idx;
            },
            set: function (newValue) {
                this.template_idx = newValue;
            }
        },
        ProjectType(state){
            return this.$store.state.project_type;
        },
        ProjectMetadata(){
            return this.$store.state.formData;
        },
        TemplateValidationErrors(){
            let errors=[];
            //check validation_report for errors
            for(let i=0;i<this.validation_report.length;i++){
                if (!this.validation_report[i].result.valid){
                    errors.push(this.validation_report[i]);
                }
            }

            return errors;
        },
        isMicrodataProject() {
            // Check if project type is microdata or survey
            const projectType = this.ProjectType || this.$store.state.formData?.type;
            return projectType === 'microdata' || projectType === 'survey';
        }
    },
    methods:{
        RefreshValidation: function() {
            this.validateProject();
            this.projectValidationReport();
        },
        fieldValidationRules: function(item) {
            if (!item) {
                return {};
            }
            if (typeof FieldValidationRulesUtil !== 'undefined') {
                return FieldValidationRulesUtil.normalize(item, { addDataType: false });
            }
            var rules = item.rules;
            if (!rules || (Array.isArray(rules) && rules.length === 0)) {
                rules = {};
            } else if (typeof rules === 'object' && !Array.isArray(rules)) {
                rules = Object.assign({}, rules);
            }
            if (item.is_required || item.required) {
                if (typeof rules === 'string') {
                    if (rules.indexOf('required') === -1) {
                        rules = rules ? ('required|' + rules) : 'required';
                    }
                } else if (Array.isArray(rules)) {
                    if (rules.indexOf('required') === -1) {
                        rules = ['required'].concat(rules);
                    }
                } else {
                    rules.required = true;
                }
            }
            return rules;
        },
        hasFieldValidationRules: function(item) {
            var rules = this.fieldValidationRules(item);
            if (!rules) {
                return false;
            }
            if (typeof rules === 'string') {
                return rules.trim() !== '';
            }
            if (Array.isArray(rules)) {
                return rules.length > 0;
            }
            return Object.keys(rules).length > 0;
        },
        navigateToValidationReport: function() {
            this.$router.push('/validation-report');
        },
        navigateToFullValidationReport: function() {
            this.$router.push('/validation-report');
        },
        navigateToError: function(key, variableFid){
            let vm=this;
            let key_parts=key.split("[");

            //variables
            if (key.startsWith("variables") || variableFid){
                // Use variable_fid if provided, otherwise extract from path
                let fileId = variableFid;
                
                if (!fileId && key.startsWith("variables")) {
                    // Extract file ID from path (format: variables/{fid}/... or /variables/{fid}/...)
                    let pathParts = key.split('/');
                    
                    // Find the file ID (F1, F2, etc.) in the path
                    for (let i = 0; i < pathParts.length; i++) {
                        // File IDs typically start with 'F' followed by a number
                        if (pathParts[i] && /^F\d+$/.test(pathParts[i])) {
                            fileId = pathParts[i];
                            break;
                        }
                    }
                }
                
                if (fileId) {
                    store.commit('tree_active_node_path', 'variables/' + fileId);
                    this.$router.push('/variables/' + fileId);
                } else {
                    // Fallback: use the original key if we can't extract file ID
                    store.commit('tree_active_node_path', key);
                    this.$router.push(key);
                }
                return;
            }

            store.commit('tree_active_node_path',key_parts[0]);
            this.$router.push('/study/' + key_parts[0]);
        },
        projectValidationReport: async function() 
        {
            let vm=this;
            let validation_report=[];
            this.validation_report=[];

            //recursively walk through template items and validate
            async function walkTemplate(item, metadata){
                
                if (item.hasOwnProperty("is_custom")){
                    return;
                }

                if(vm.hasFieldValidationRules(item)){
                    let value=_.get(metadata, item.key, null);

                    VeeValidate.validate(value, vm.fieldValidationRules(item), {name:item.title}).then(result => {
                        if (item.prop_key){
                            validation_report[item.prop_key]=result;
                            vm.validation_report.push({key:item.prop_key, item:item, result:result, value:value});
                        }else{
                            validation_report[item.key]=result;
                            vm.validation_report.push({key:item.key, item:item, result:result, value:value});
                        }
                        //console.log("validation-report",item.key,validation_report);
                      });
                }

                if(item.hasOwnProperty("items")){
                    for(let i=0;i<item.items.length;i++){
                        walkTemplate(item.items[i], metadata);                        
                    }
                }

                if (item.hasOwnProperty("props")){
                    let itemMetadata=_.get(metadata, item.key, null);

                    if (itemMetadata==null){
                        return;
                    }

                    for (let k=0;k<itemMetadata.length;k++){
                        for(let i=0;i<item.props.length;i++){
                            let propMetadata=_.get(itemMetadata[k], item.props[i].key, null);
                            walkTemplateProp(item.props[i], propMetadata, item.key+"["+k+"]");
                        }
                    }
                }
            }

            function walkTemplateProp(item, metadata, item_path=null){
                if(vm.hasFieldValidationRules(item)){
                    //for props metadata is single prop value
                    let value=metadata;                    
                    
                    VeeValidate.validate(value, vm.fieldValidationRules(item),{name:item.title}).then(result => {
                        if (item.prop_key){
                            if (item_path!=null){

                                vm.validation_report.push({
                                    key:item_path + "." + item.key,
                                    item:item,
                                    result:result,
                                    value:JSON.stringify(value)
                                });

                                validation_report[item_path]={
                                    result:result,
                                    item:item,
                                    value:JSON.stringify(value)};
                            }else{
                                validation_report[item.prop_key]=result;
                                vm.validation_report.push({
                                    key:item.prop_key, 
                                    item:item,
                                    result:result,
                                    value:JSON.stringify(value)
                                });
                            }
                        }else{
                            validation_report[item.key]=result;
                            vm.validation_report.push({
                                key:item.key, 
                                item:item,
                                result:result,
                                value:JSON.stringify(value)
                            });
                        }
                        //console.log("validation-report-prop",item.key,validation_report);
                      });
                }

                if(item.hasOwnProperty("items")){
                    for(let i=0;i<item.items.length;i++){
                        walkTemplate(item.items[i], metadata);                        
                    }
                }

                if (item.hasOwnProperty("props")){
                    let itemMetadata=metadata;                    

                    if (itemMetadata==null){                        
                        return;
                    }

                    for (let k=0;k<itemMetadata.length;k++){
                        for(let i=0;i<item.props.length;i++){
                            let propMetadata=_.get(itemMetadata[k], item.props[i].key, null);
                            walkTemplateProp(item.props[i], propMetadata, item_path + "." + item.props[i].key + "["+k+"]");
                        }
                    }

                }
            }
            
            //validate
            walkTemplate(this.ProjectTemplate.template, this.ProjectMetadata);
        },        
        validateProject: function() {
            let vm=this;
            this.validation_errors="";
            this.schema_validation = null;
            this.variables_validation = null;
            this.variables_validation_errors = "";
            
            // Always load schema validation
            let schemaUrl = CI.base_url + '/api/validation/'+this.ProjectID+'/schema';
            axios.get(schemaUrl)
            .then(function (response) {
                if(response.data && response.data.status === 'success') {
                    const validation = response.data.validation;
                    vm.schema_validation = validation;
                    
                    // Convert validation.issues to the format expected by the template
                    if (!validation.valid && validation.issues && validation.issues.length > 0) {
                        vm.validation_errors = {
                            errors: validation.issues.map(issue => {
                                // Convert JSON Pointer path to dot notation for editor navigation
                                // e.g., /study_desc/title_statement/title -> study_desc.title_statement.title
                                let propertyPath = issue.path || issue.property || '';
                                if (propertyPath && propertyPath.startsWith('/')) {
                                    // Remove leading slash and replace slashes with dots
                                    propertyPath = propertyPath.substring(1).replace(/\//g, '.');
                                }
                                
                                return {
                                    property: propertyPath,
                                    message: issue.message || '',
                                    type: issue.type || 'validation_error',
                                    constraint: issue.constraint || null,
                                    expected_type: issue.expected_type || null,
                                    actual_type: issue.actual_type || null
                                };
                            })
                        };
                    } else {
                        // No errors - clear validation_errors
                        vm.validation_errors = "";
                    }
                    console.log("schema validation response", response);
                } else {
                    vm.validation_errors = "";
                }
            })
            .catch(function (error) {
                console.log("schema validation errors", error);
                // Handle error response from API
                if (error.response && error.response.data) {
                    // If API returns structured error, use it
                    vm.validation_errors = error.response.data;
                } else {
                    // Generic error
                    vm.validation_errors = {
                        errors: [{
                            property: '',
                            message: error.message || 'Failed to load schema validation'
                        }]
                    };
                }
            });
            
            if (this.isMicrodataProject) {
                let variablesUrl = CI.base_url + '/api/validation/'+this.ProjectID+'/variables?limit=5&mode=light';
                
                axios.get(variablesUrl)
                .then(function (response) {
                    if(response.data && response.data.status === 'success') {
                        const validation = response.data.validation;
                        vm.variables_validation = validation;
                        
                        // Convert validation.issues to the format expected by the template
                        if (!validation.valid && validation.issues && validation.issues.length > 0) {
                            vm.variables_validation_errors = {
                                errors: validation.issues.map(issue => {
                                    // For variables, path is like 'variables/{fid}/{property}'
                                    let propertyPath = issue.path || issue.property || '';
                                    
                                    return {
                                        property: propertyPath,
                                        message: issue.message || '',
                                        type: issue.type || 'validation_error',
                                        constraint: issue.constraint || null,
                                        variable_fid: issue.variable_fid || null,
                                        variable_name: issue.variable_name || null
                                    };
                                })
                            };
                        } else {
                            // No errors - clear variables_validation_errors
                            vm.variables_validation_errors = "";
                        }
                        console.log("variables validation response", response);
                    } else {
                        vm.variables_validation_errors = "";
                    }
                })
                .catch(function (error) {
                    console.log("variables validation errors", error);
                    // Don't show error if project is not microdata type (400 error is expected for non-microdata)
                    if (error.response && error.response.status !== 400) {
                        vm.variables_validation_errors = {
                            errors: [{
                                property: '',
                                message: error.response?.data?.message || error.message || 'Failed to load variables validation'
                            }]
                        };
                    }
                });
            }
        }
    },     
    template: `
            <div class="summary-template-validation-component">

                <v-card>
                    <v-card-title class="d-flex justify-space-between">
                        <h6>{{$t("project_validation")}}</h6>
                        <div class="d-flex">                           
                            <v-btn icon title="Re-run validation" @click="RefreshValidation">
                                <v-icon small>mdi-refresh</v-icon>
                            </v-btn>
                        </div>
                    </v-card-title>
                    
                    <v-card-text>
                    <div style="overflow:auto;max-height:400px;">

                    <!-- Schema validation (shown for all projects) -->
                    <div>
                        <div>{{$t("Schema validation")}} <v-icon small :title="$t('Requires project to be saved')" >mdi-information-outline</v-icon></div>
                        <div class="validation-errors mt-2" v-if="validation_errors!=''" style="color:red;font-size:small;" >
                            
                            <v-list dense>
                                <template v-for="error in validation_errors.errors" >
                                    <v-list-item @click="navigateToError(error.property)">
                                        <v-list-item-icon>
                                            <v-icon color="red">mdi-alert-circle</v-icon>
                                        </v-list-item-icon>
                                        <v-list-item-content>
                                            <v-list-item-title color="red">
                                                <div style="font-weight:bold;color:red">{{error.message}}</div>                                            
                                            </v-list-item-title>
                                            <v-list-item-subtitle >
                                                <div style="font-weight:normal">
                                                    {{error.property}}
                                                </div>
                                            </v-list-item-subtitle>
                                        </v-list-item-content>                                                                                            
                                    </v-list-item>
                                </template>
                            </v-list>

                        </div>
                        <div class="mt-3 p-2 border" style="color:green" v-else-if="schema_validation && schema_validation.valid">{{$t("no_validation_errors")}}</div>
                        <div class="mt-3 p-2 border" style="color:green" v-else-if="schema_validation && validation_errors==''">{{$t("no_validation_errors")}}</div>
                    </div>

                    <!-- Variables validation (only for microdata projects) -->
                    <div v-if="isMicrodataProject" class="mt-4">
                        <div>{{$t("variables_validation")}} <v-icon small :title="$t('Requires project to be saved')" >mdi-information-outline</v-icon></div>
                        
                        <div class="validation-errors mt-2" v-if="variables_validation_errors!=''" style="color:red;font-size:small;" >
                            
                            <v-list dense>
                                <template v-for="error in variables_validation_errors.errors" >
                                    <v-list-item @click="navigateToError(error.property, error.variable_fid)">
                                        <v-list-item-icon>
                                            <v-icon color="red">mdi-alert-circle</v-icon>
                                        </v-list-item-icon>
                                        <v-list-item-content>
                                            <v-list-item-title color="red">
                                                <div style="font-weight:bold;color:red">{{error.message}}</div>                                            
                                            </v-list-item-title>
                                            <v-list-item-subtitle >
                                                <div style="font-weight:normal">
                                                    <span v-if="error.variable_name">{{error.variable_name}} - </span>
                                                    <span v-if="error.variable_fid">{{$t("file")}}: {{error.variable_fid}}</span>
                                                    <span v-if="!error.variable_name && !error.variable_fid">{{error.property}}</span>
                                                </div>
                                            </v-list-item-subtitle>
                                        </v-list-item-content>                                                                                            
                                    </v-list-item>
                                </template>
                            </v-list>

                        </div>
                        <div class="mt-3 p-2 border" style="color:green" v-else-if="variables_validation && variables_validation.valid">{{$t("no_validation_errors")}}</div>
                        <div class="mt-3 p-2 border" style="color:green" v-else-if="variables_validation && variables_validation_errors==''">{{$t("no_validation_errors")}}</div>
                    </div>

                    <div class="mt-3">{{$t("Template validation")}}</div>
                                        
                    <div>
                        <v-list dense>                            
                            <template v-for="(item, i) in TemplateValidationErrors" >
                                <v-list-item v-if="!item.result.valid" :key="i" @click="navigateToError(item.key)">
                                    <v-list-item-icon>
                                        <v-icon color="red">mdi-alert-circle</v-icon>
                                    </v-list-item-icon>
                                    <v-list-item-content>
                                        <v-list-item-title color="red">
                                            <div v-for="error in item.result.errors" style="font-weight:bold;color:red">{{error}}</div>                                            
                                        </v-list-item-title>
                                        <v-list-item-subtitle >
                                            <div style="font-weight:normal">
                                                {{item.item.title}} - {{item.key}}
                                            </div>
                                        </v-list-item-subtitle>
                                    </v-list-item-content>                                                                                            
                                </v-list-item>
                            </template>
                        </v-list>
                    </div>

                    <div v-if="TemplateValidationErrors.length==0" class="p-2 border" style="color:green">{{$t("no_validation_errors")}}</div>

                    </div>
                    </v-card-text>
                    
                    <v-card-actions class="pt-0">
                        <v-spacer></v-spacer>
                        <v-btn 
                            text 
                            small 
                            @click="navigateToFullValidationReport"
                            class="text-caption"
                        >
                            <v-icon small left>mdi-clipboard-list</v-icon>
                            {{$t("view_full_validation_report")}}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </div>          
            `    
});

