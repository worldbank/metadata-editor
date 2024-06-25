<script>
        Vue.use(Vuex)
        Vue.use(VueDeepSet)     

        window.bus = new Vue();//todo remove?
        const EventBus = new Vue();

        Vue.mixin({
            methods: {
                normalizeClassID: function(class_id){
                    return class_id.replace(/\./g, "-");
                },
                nestedArrayToStringValue: function(arr, output='') 
                {
                    let vm=this;
                    if (Array.isArray(arr)) {
                        arr.forEach(function (item) {
                        output = vm.nestedArrayToStringValue(item, output);
                        });
                    } else {
                        if (typeof arr === 'object') {
                        let keys = Object.keys(arr);
                        keys.forEach(function (key) {
                            if (typeof arr[key] === 'object'){
                            output = vm.nestedArrayToStringValue(arr[key], output);
                            }else{
                            output+= " " + arr[key];
                            }            
                        });
                        }
                    }
                    return output.trim();
                },
                copyToClipBoard: function(textToCopy){
                    const tmpTextField = document.createElement("textarea")
                    tmpTextField.textContent = textToCopy
                    tmpTextField.setAttribute("style","position:absolute; right:200%;")
                    document.body.appendChild(tmpTextField)
                    tmpTextField.select()
                    tmpTextField.setSelectionRange(0, 99999) /*For mobile devices*/
                    document.execCommand("copy")
                    tmpTextField.remove();
                },

                pasteFromClipBoard: async function() 
                {
                    const text = await navigator.clipboard.readText();
                    return text;                    
                },
                CSVToArray: function ( strData, strDelimiter )
                {
                    //source: https://gist.github.com/bennadel/9753411#file-code-1-htm
                    
                    // Check to see if the delimiter is defined. If not,
                    // then default to comma.
                    strDelimiter = (strDelimiter || ",");

                    // Create a regular expression to parse the CSV values.
                    var objPattern = new RegExp(
                        (
                            // Delimiters.
                            "(\\" + strDelimiter + "|\\r?\\n|\\r|^)" +

                            // Quoted fields.
                            "(?:\"([^\"]*(?:\"\"[^\"]*)*)\"|" +

                            // Standard fields.
                            "([^\"\\" + strDelimiter + "\\r\\n]*))"
                        ),
                        "gi"
                        );


                    // Create an array to hold our data. Give the array
                    // a default empty first row.
                    var arrData = [[]];

                    // Create an array to hold our individual pattern
                    // matching groups.
                    var arrMatches = null;


                    // Keep looping over the regular expression matches
                    // until we can no longer find a match.
                    while (arrMatches = objPattern.exec( strData )){

                        // Get the delimiter that was found.
                        var strMatchedDelimiter = arrMatches[ 1 ];

                        // Check to see if the given delimiter has a length
                        // (is not the start of string) and if it matches
                        // field delimiter. If id does not, then we know
                        // that this delimiter is a row delimiter.
                        if (
                            strMatchedDelimiter.length &&
                            strMatchedDelimiter !== strDelimiter
                            ){

                            // Since we have reached a new row of data,
                            // add an empty row to our data array.
                            arrData.push( [] );

                        }

                        var strMatchedValue;

                        // Now that we have our delimiter out of the way,
                        // let's check to see which kind of value we
                        // captured (quoted or unquoted).
                        if (arrMatches[ 2 ]){

                            // We found a quoted value. When we capture
                            // this value, unescape any double quotes.
                            strMatchedValue = arrMatches[ 2 ].replace(
                                new RegExp( "\"\"", "g" ),
                                "\""
                                );

                        } else {

                            // We found a non-quoted value.
                            strMatchedValue = arrMatches[ 3 ];

                        }


                        // Now that we have our value string, let's add
                        // it to the data array.
                        arrData[ arrData.length - 1 ].push( strMatchedValue );
                    }

                    // Return the parsed data.
                    return( arrData );
                }
            }
        })
        
        <?php 
            echo $this->load->view("metadata_editor/vue-project-export-json-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-template-validation-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-template-apply-defaults-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-toast-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-login-component.js",null,true);
            echo $this->load->view("metadata_editor/fields/vue-field-date.js",null,true);

            echo $this->load->view("metadata_editor/vue-spreadmetadata-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-main-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-preview-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-nested-section-preview-component.js",null,true);
            
            //echo $this->load->view("metadata_editor/vue-files-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-external-resources-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-external-resources-edit-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafiles-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-edit-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-import-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-data-explorer-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-variable-edit-documentation-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variables-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-edit-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-weights-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-categories-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-info-edit-component.js",null,true);

            //tree view component
            echo $this->load->view("metadata_editor/vue-form-tree.js",null,true);

            //metadata grid component
            echo $this->load->view("metadata_editor/vue-grid-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-grid-preview-component.js",null,true);

            //nested
            echo $this->load->view("metadata_editor/vue-nested-section-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-input-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-nested-array-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-simple-array-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-table-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-geospatial-identification-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-import-options-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-publish-options-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-project-package-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-external-resources-import-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-configure-catalog-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-summary-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-summary-files-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-thumbnail-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-table-grid-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-nested-section-subsection-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-repeated-field-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-section-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-generate-pdf-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-groups-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-dialog-variable-selection-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-dialog-weight-variable-selection-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-dialog-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-dialog-datafile-replace-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-dialog-enum-selection-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-geospatial-feature-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-page-preview-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-geospatial-gallery-component.js",null,true);
        ?>

        <?php if (empty($metadata)):?>
            var project_metadata={};
        <?php else:?>
            var project_metadata={}<?php //echo json_encode($metadata);?>;
        <?php endif;?>

        <?php if (empty($sid)):?>
            var project_sid=null;
        <?php else:?>
            var project_sid=<?php echo $sid;?>;
        <?php endif;?>


        let project_idno='<?php echo isset($idno) ? $idno : '';?>';
        let project_type='<?php echo isset($type) ? $type : '';?>';

        //Define route components
        const main = {props:['element_id'],template: '<div><form-main/></div>' }
        const Home = { template: '<div><summary-component/> </div>' }
        const PublishProject = { template: '<div><publish-options/> </div>' }
        const ProjectPackage = { template: '<div><project-package/> </div>' }
        const ProjectPdf = { template: '<div><generate-pdf/> </div>' }
        const ConfigureCatalog = { template: '<div><configure-catalog/> </div>' }
        const ImportOptions = { template: '<div><import-options/> </div>' }
        const _main = {props: ['active_section'],template: '<div><study-metadata/></div>' }
        const Datafiles ={template: '<div><datafiles/></div>'}
        const Datafile = {props: ['file_id'],template: '<div><datafile/></div>' }
        const DatafileExplorer = {props: ['file_id'],template: '<div><datafile-data-explorer/></div>' }
        const DatafileImport = {template: '<div><datafile-import/></div>' }
        const Variables ={props: ['file_id'],template: '<div><variables xv-if="$store.state.variables"/> </div>'}
        const VariableGroups ={template: '<div><variable-groups /> </div>'}
        const ResourcesComp ={props: ['index'],template: '<div><external-resources /></div>'}
        //const FileManager ={props: ['index'],template: '<div><file-manager /></div>'}
        const ResourcesImport ={template: '<div> <external-resources-import /></div>'}
        const ResourcesEditComp ={props: ['index'],template: '<div><external-resources-edit /></div>'}
        const GeoFeatures ={props: ['index'],template: '<div>Geo-features</div>'}
        const GeoFeature ={props: ['feature_name'],template: '<div><geospatial-feature/></div>'}
        const PagePreview ={template: '<div><page-preview/></div>'}
        const GeoGallery ={template: '<div><geospatial-gallery/></div>'}

        //routes
        const routes = [
            { path: '/', component: Home },
            { path: '/page-preview', component: PagePreview },
            { path: '/publish', component: PublishProject },
            { path: '/project-package', component: ProjectPackage },
            { path: '/generate-pdf', component: ProjectPdf },            
            { path: '/configure-catalog', component: ConfigureCatalog },
            { path: '/import', component: ImportOptions },
            { path: '/study/:element_id', component: main, name: 'study',props: true },
            { path: '/datafile/:file_id', component: Datafile, props:true },
            { path: '/data-explorer/:file_id', component: DatafileExplorer, props:true },
            { path: '/datafiles', component: Datafiles },
            { path: '/datafiles/import', component: DatafileImport },
            { path: '/variables/:file_id', component: Variables, props: true },
            { path: '/variable-groups', component: VariableGroups},
            { path: '/external-resources', component: ResourcesComp, props: true},
            { path: '/external-resources/import', component: ResourcesImport},
            { path: '/external-resources/:index', component: ResourcesEditComp, props: true},            
            //{ path: '/files', component: FileManager, props: true},
            { path: '/geospatial-features', component: GeoFeatures, props: true},
            { path: '/geospatial-feature/:feature_name', component: GeoFeature, props: true },
            { path: '/geospatial-gallery', component: GeoGallery, props: true }
        ]

        const router = new VueRouter({
            routes
        })


        router.beforeEach((to, from, next)=>{
            route_path=to.path.replace('/study/','');

            console.log("route path",route_path);
            
            if (!store.state.treeActiveNode){
                console.log("no active node");
                if (store.getters.getTemplateItemByKey(route_path)){
                    store.commit('tree_active_node_path',route_path);
                }
            }

            next();
        })

        var store = new Vuex.Store({
            state: {
                active_section: "not set",
                project_type:project_type,
                idno:project_idno,
                project_id:project_sid,
                formData: project_metadata,
                formTemplate:form_template,
                formTemplateParts:form_template_parts,
                templates:[],//list of templates available                
                treeActiveNode:null,
                treeItems:[],
                active_node: {
                    id: 'table_description.title_statement.table_number'
                },
                external_resources:[],
                data_files:[],
                variable_groups:[],
                variables:{
                    "F1":{}
                },
                project_isloading:false,
                variables_loaded:false,
                variables_isloading:false,
                variables_active_tab:"documentation",                
                variable_documentation_fields:[
                    "variable.var_imputation",
                    "variable.var_derivation",
                    "variable.var_security",
                    "variable.var_respunit",
                    "variable.var_qstn_preqtxt",
                    "variable.var_qstn_qstnlit",
                    "variable.var_qstn_postqtxt",
                    "variable.var_forward",
                    "variable.var_backward",
                    "variable.var_qstn_ivulnstr",
                    "variable.var_universe",
                    "variable.var_txt",
                    "variable.var_codinstr",
                    "variable.var_concept",
                    "variable.var_notes"
                ],
                variable_template_items_enabled:[
                    "variable.var_imputation",
                    "variable.var_derivation",
                    "variable.var_security",
                    "variable.var_respunit",
                    "variable.var_qstn_preqtxt",
                    "variable.var_qstn_qstnlit",
                    "variable.var_qstn_postqtxt",
                    "variable.var_forward",
                    "variable.var_backward",
                    "variable.var_qstn_ivulnstr",
                    "variable.var_universe",
                    "variable.var_txt",
                    "variable.var_codinstr",
                    "variable.var_concept",
                    "variable.var_notes"
                ],                                    
                formTextFieldStyle:
                { 
                    clearable: true,
                    "single-line":true,
                    dense:true,
                    filled:false,
                    outlined:true,                    
                    style:"xborder-top:1px solid gray;"
                }
            },
            getters: {
                getIDNO(state){
                    return state.idno;
                },
                getProjectID(state){
                    return state.project_id;
                },
                getProjectType(state){
                    return state.project_type;
                },
                getProjectTemplate(state){
                    return state.formTemplate;
                },
                getDataFiles(state) {
                    return state.data_files;
                },
                getDataFileById: (state) => (fid) => {
                    for(i=0;i<state.data_files.length;i++){
                        if(state.data_files[i].file_id==fid){
                            return state.data_files[i];
                        }
                    }
                },
                getDataFileNameById: (state) => (fid) => {
                    for(i=0;i<state.data_files.length;i++){
                        if(state.data_files[i].file_id==fid){
                            return state.data_files[i].file_name;
                        }
                    }
                },                
                getVariablesAll(state) {                
                    return state.variables;
                },
                getVariablesByFid: (state) => (fid) => {
                    return state.variables[fid];
                },
                getMaxFileId: function(state){
                    var max_file_id=0;
                    let datafiles=state.data_files;
                    
                    for(i=0;i<datafiles.length;i++){
                        file_id=datafiles[i].file_id;
                        if (parseInt(file_id.substr(1))>max_file_id){
                            max_file_id=file_id.substr(1);
                        }
                    }

                    return parseInt(max_file_id);
                },
                getMaxVariableId: function(state){
                    var max_var=0;
                    let variables=state.variables;
                    let datafile_names=Object.keys(variables);

                    for(k=0;k<datafile_names.length;k++){
                        fid=datafile_names[k];
                        
                        for(i=0;i<variables[fid].length;i++){
                            variable=variables[fid][i];
                            if(parseInt(variable.vid.substr(1))>max_var){
                                max_var=variable.vid.substr(1);
                            }
                        }
                    }
                    return parseInt(max_var);
                },
                getVariablesActiveTab: function(state){
                    return state.variables_active_tab;
                },
                getTreeItems(state){
                    return state.treeItems;
                },
                //find template item by key                
                getTemplateItemByKey: (state) => (key) => {
                    
                    let findTemplateByItemKey= function (items,key){
                        let item=null;
                        let found=false;
                        let i=0;

                        while(!found && i<items.length){
                            if (items[i].key==key){
                                item=items[i];
                                found=true;
                            }else{
                                if (items[i].items){
                                    item=findTemplateByItemKey(items[i].items,key);
                                    if (item){
                                        found=true;
                                    }
                                }
                            }
                            i++;                        
                        }
                        return item;
                    }

                    //search nested formTemplate
                    let items=store.state.formTemplate.template.items;
                    let item=findTemplateByItemKey(items,route_path);

                    return item;
                },
                GetVariableDocumentationFields: function(state){                    
                    return state.variable_documentation_fields;
                },
            },
            actions: {               
                async initData({commit},options) {
                    store.state.variables_isloading=true;
                    store.state.project_isloading=true;
                    await store.dispatch('loadTemplatesList',{});
                    await store.dispatch('loadProject',{dataset_id:options.dataset_id});
                    await store.dispatch('loadDataFiles',{dataset_id:options.dataset_id});
                    await store.dispatch('loadAllVariables',{dataset_id:options.dataset_id});
                    await store.dispatch('loadExternalResources',{dataset_id:options.dataset_id});
                    await store.dispatch('loadVariableGroups',{dataset_id:options.dataset_id});
                    store.state.variables_loaded=true;
                    store.state.variables_isloading=false;
                    store.state.project_isloading=false;
                },
                async initTreeItems({commit},options) {               
                    store.state.treeItems=store.state.formTemplate.template.items;    
                },
                async loadTemplatesList({commit},options) {
                    let url=CI.base_url + '/api/templates/list/'+store.state.project_type;
                    return axios
                    .get(url)
                    .then(function (response) {
                        store.state.templates=response.data.result;
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
                },
                async loadTemplateByUID({commit},options) {                    
                    let url=CI.base_url + '/api/templates/'+options.template_uid;
                    return axios
                    .get(url)
                    .then(function (response) {                        
                        if (response.data.result){
                            store.state.formTemplate=response.data.result;
                        }else{
                            console.log("error load template", response.data);
                            alert("error loading template");
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
                },
                async loadProject({commit},options) {
                    let url=CI.base_url + '/api/editor/'+options.dataset_id;
                    return axios
                    .get(url)
                    .then(function (response) {                        
                        if (response.data.project && response.data.project.metadata){
                            if (response.data.project.metadata.constructor.name == 'Object'){
                                store.state.formData=response.data.project.metadata;                                
                            }else{
                                alert("Error reading project metadata");
                                store.state.formData={};
                            }
                        }                        
                    })
                    .catch(function (error) {
                        console.log("error loading project",error);
                    });
                },
                async loadDataFiles({commit},options) {                    
                    let url=CI.base_url + '/api/datafiles/'+options.dataset_id;
                    return axios
                    .get(url)
                    .then(function (response) {
                        let data_files_=[];
                        if(response.data.datafiles){
                            Object.keys(response.data.datafiles).forEach(function(element, index) { 
                                data_files_.push(response.data.datafiles[element]);
                            })
                            commit('data_files',data_files_);
                        }
                    })
                    .catch(function (error) {
                        console.log("error loading datafiles", error);
                    });                    
                },
                async loadAllVariables({commit,state},options) {
                    i=0;
                    for (let file of state.data_files) {
                        store.dispatch('loadVariables',{dataset_id:options.dataset_id, fid:file.file_id});
                    }
                },
                async loadVariables({commit}, options) {//options {dataset_idno,fid}
                    let url=CI.base_url + '/api/variables/'+options.dataset_id + '/'+ options.fid + '?detailed=1';
                    return axios
                    .get(url)
                    .then(function (response) {
                        if(response.data.variables.length==0){                            
                        }

                        if(response.data.variables.length>0){
                            commit('variables',{
                                'variables':response.data.variables,
                                'fid':options.fid
                            });
                        }
                    })
                    .catch(function (error) {
                        console.log("error loading variables",error);
                    });
                },
                async loadExternalResources({commit}, options) {
                    let url=CI.base_url + '/api/resources/'+options.dataset_id;
                    return axios
                    .get(url)
                    .then(function (response) {
                        if(response.data.resources){
                            commit('external_resources',response.data.resources);
                        }
                    })
                    .catch(function (error) {
                        console.log("external resource loading error",error);
                    });
                },
                async loadVariableGroups({commit},options) {
                    let url=CI.base_url + '/api/variable_groups/'+options.dataset_id + "?variable_groups";
                    return axios
                    .get(url)
                    .then(function (response) {                        
                        if(response.data.variable_groups){                            
                            //#commit('variable_groups',response.data.variable_groups);
                            store.state.variable_groups=response.data.variable_groups;
                        }
                    })
                    .catch(function (error) {
                        console.log("error loading variable groups", error);
                    });                    
                },
                //generate and import data file summary statistics
                async importDataFileSummaryStatistics({commit,getters}, options)
                {
                    let url=CI.base_url + '/api/data/generate_summary_stats/'+getters.getProjectID + '/' + options.file_id;                    
                    let resp = await axios.get(url);
                    return resp;
                },
                async importDataFileSummaryStatisticsQueue({commit,getters}, options)
                {
                    let url=CI.base_url + '/api/data/generate_summary_stats_queue/'+getters.getProjectID + '/' + options.file_id;                    
                    let resp = await axios.get(url);
                    return resp;
                },
                async importDataFileSummaryStatisticsQueueStatusCheck({commit,getters}, options)
                {
                    let url=CI.base_url + '/api/data/summary_stats_queue_status/'+getters.getProjectID + '/' + options.file_id + '/' +  options.job_id;
                    let resp = await axios.get(url);
                    return resp;
                },                 
                //generate and import variable summary statistics for a selected variables
                async importVariableSummaryStatistics({commit,getters}, options)
                {
                    let formData = {
                        "var_names": options.var_names,//this.variableSelectedNames()
                        "weights": options.weights
                    }

                    let url=CI.base_url + '/api/data/generate_summary_stats_variable/'+getters.getProjectID + '/' + options.file_id;
                    let resp = await axios.post(url,formData);
                    return resp;                
                },
                async generateCsvQueue({commit,getters}, options)
                {
                    let url=CI.base_url + '/api/data/generate_csv_queue/'+getters.getProjectID + '/' + options.file_id;
                    let resp = await axios.get(url);
                    return resp;                
                },
                async generateCsvQueueStatusCheck({commit,getters}, options)
                {
                    let url=CI.base_url + '/api/data/generate_csv_job_status/'+getters.getProjectID + '/' + options.file_id + '/' +  options.job_id;
                    let resp = await axios.get(url);
                    return resp;                
                },
                async exportDatafileQueue({commit,getters}, options)
                {
                    let url=CI.base_url + '/api/data/export_datafile_queue/'+getters.getProjectID + '/' + options.file_id;
                    let formData = {
                        "format": options.format                        
                    }

                    let resp = await axios.post(url,formData);
                    return resp;                
                },
                async getJobStatus({commit,getters}, options)
                {
                    let url=CI.base_url + '/api/data/job_status/'+ options.job_id;
                    let resp = await axios.get(url);
                    return resp;                
                }                   
            },
            mutations: VueDeepSet.extendMutation({
                // other mutations
                data_model (state,data) {
                    console.log("value added");
                },
                tree_active_node(state,node){//tobe removed
                    alert("toberemoved");
                    //state.treeActiveNode=state.formTemplateParts[node];
                },
                tree_active_node_path(state,node_key){
                    state.treeActiveNode=store.getters.getTemplateItemByKey(node_key);
                },
                tree_active_node_data(state,node){
                    console.log("active node",node);
                    state.treeActiveNode=node;
                },

                external_resources(state,data){
                    state.external_resources=data;
                },
                external_resources_add(state,newResource){
                    let new_idx=state.external_resources.push(newResource)-1;
                    return new_idx;//index of newly added resource
                },
                data_files(state,data){
                    state.data_files=data;                    
                },
                data_files_add(state,newFile){
                    let new_idx=state.data_files.push(newFile)-1;
                    return new_idx;
                },
                variables(state,data){
                    Vue.set(state.variables, data.fid, data.variables);
                },
                variable_add(state,data){
                    if (state.variables[data.fid]==undefined){
                        Vue.set(state.variables,data.fid,[]);
                        Vue.set(state.variables[data.fid],data.fid,{});
                    }

                    let new_idx=state.variables[data.fid].push(data.variable)-1;
                },
                variable_remove(state,data){
                    if (state.variables[data.fid]==undefined){
                        return;
                    }
                    state.variables[data.fid].splice(data.idx, 1);
                },
                variables_active_tab(state,data){
                    state.variables_active_tab=data;
                }
            })            
        })


        const isUniqueIDNO = (value) => {
                let sid='<?php echo $sid;?>';
                let url='<?php echo site_url('/api/datasets/check_idno/');?>' + value + '/' + sid;
                
                return axios.get(url)
                .then(function (response) {                    
                    if (response.status==200 && response.data.id==sid){
                        return {
                            valid: true,
                            data:{
                                message: 'IDNO is valid'
                            }
                        }
                    }

                    return {
                        valid: response.status==404,
                        data:{
                            message: 'IDNO exists'
                        }
                    }
                })
                .catch(function (error) {                        
                    console.log(error);                      
                    return {
                        valid: error.response.status==404,//valid if statuscode==404
                        data:{
                            message: 'IDNO not found'
                        }
                    }
                });        
        };

  
        VeeValidate.extend('idno', {
            validate: isUniqueIDNO,
            getMessage: (field, params, data) => {
                return data.message;
            },
            message: 'Please enter a unique value.'
        });

        VeeValidate.extend('is_uri', {
            validate(value){
                var pattern = new RegExp('^(https?:\\/\\/)?'+ // protocol
                        '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // domain name
                        '((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
                        '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
                        '(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
                        '(\\#[-a-z\\d_]*)?$','i'); // fragment locator
                return !!pattern.test(value);
            },
            getMessage: (field, params, data) => {
                return data.message;
            },
            message: 'Value must be a URL e.g. http://example.com'
        });

        //ignore validation if a required field is empty ('',null or undefined)
        VeeValidate.extend('required', {
            validate (value) {
                return {
                    required: true,
                    valid: ['', null, undefined].indexOf(value) === -1
                };        
            },
            computesRequired: true
            //message: 'This is a required field'
        });

    Vue.component('ValidationProvider', VeeValidate.ValidationProvider);
    Vue.component('ValidationObserver', VeeValidate.ValidationObserver);

    const { Splitpanes, Pane } = splitpanes;


    Vue.component("pane", Pane);
    Vue.component("splitpanes", Splitpanes);
    //Vue.component("draggable", draggable);
</script>