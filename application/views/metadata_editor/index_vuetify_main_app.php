<script>
        Vue.use(Vuex)
        Vue.use(VueDeepSet)     
        Vue.use(VueToast);
   

        window.bus = new Vue();//todo remove?
        const EventBus = new Vue();

        Vue.mixin({
            methods: {
                normalizeClassID: function(class_id){
                    return class_id.replace(/\./g, "-");
                }                
            }
        })
        
        <?php 
            
            echo $this->load->view("metadata_editor/vue-login-component.js",null,true);
            echo $this->load->view("metadata_editor/fields/vue-field-date.js",null,true);

            echo $this->load->view("metadata_editor/vue-spreadmetadata-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-main-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-preview-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-nested-section-preview-component.js",null,true);
            
            echo $this->load->view("metadata_editor/vue-external-resources-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-external-resources-edit-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafiles-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-edit-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-import-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-data-explorer-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-variables-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-edit-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-categories-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-info-edit-component.js",null,true);

            //tree view component
            echo $this->load->view("metadata_editor/vue-form-tree.js",null,true);

            //metadata grid component
            echo $this->load->view("metadata_editor/vue-grid-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-grid-preview-component.js",null,true);

            //nested
            echo $this->load->view("metadata_editor/vue-nested-section-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-simple-array-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-table-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-geospatial-identification-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-import-options-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-publish-options-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-external-resources-import-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-configure-catalog-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-summary-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-summary-files-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-thumbnail-component.js",null,true);
        ?>

        <?php if (empty($metadata)):?>
            var project_metadata={};
        <?php else:?>
            var project_metadata=<?php echo json_encode($metadata);?>;
        <?php endif;?>

        <?php if (empty($sid)):?>
            var project_sid=null;
        <?php else:?>
            var project_sid=<?php echo $sid;?>;
        <?php endif;?>

        let project_idno='<?php echo isset($survey['idno']) ? $survey['idno'] : '';?>';
        let project_type='<?php echo isset($type) ? $type : '';?>';

        // 1. Define route components.
        const main = {props:['element_id'],template: '<div><form-main/></div>' }
        const Home = { template: '<div><summary-component/> </div>' }
        const PublishProject = { template: '<div><publish-options/> </div>' }
        const ConfigureCatalog = { template: '<div><configure-catalog/> </div>' }
        const ImportOptions = { template: '<div><import-options/> </div>' }
        const _main = {props: ['active_section'],template: '<div><study-metadata/></div>' }
        const Datafiles ={template: '<div><datafiles/></div>'}
        const Datafile = {props: ['file_id'],template: '<div><datafile/></div>' }
        const DatafileExplorer = {props: ['file_id'],template: '<div><datafile-data-explorer/></div>' }
        const DatafileImport = {template: '<div><datafile-import/></div>' }
        const Variables ={props: ['file_id'],template: '<div><variables xv-if="$store.state.variables"/> </div>'}
        const ResourcesComp ={props: ['index'],template: '<div><external-resources /></div>'}
        const ResourcesImport ={template: '<div> <external-resources-import /></div>'}
        const ResourcesEditComp ={props: ['index'],template: '<div><external-resources-edit /></div>'}



        // 2. Define some routes
        // Each route should map to a component. The "component" can
        // either be an actual component constructor created via
        // `Vue.extend()`, or just a component options object.
        const routes = [
            /*{path: '/', component: main,
                redirect: '/study/<?php echo isset($metadata_template_arr['items'][0]['key']) ? $metadata_template_arr['items'][0]['key'] : 'study_description' ;?>',
                name: 'home',
            },*/
            { path: '/', component: Home },
            { path: '/publish', component: PublishProject },
            { path: '/configure-catalog', component: ConfigureCatalog },
            { path: '/import', component: ImportOptions },
            { path: '/study/:element_id', component: main, name: 'study',props: true },
            { path: '/datafile/:file_id', component: Datafile, props:true },
            { path: '/data-explorer/:file_id', component: DatafileExplorer, props:true },
            { path: '/datafiles', component: Datafiles },
            { path: '/datafiles/import', component: DatafileImport },
            { path: '/variables/:file_id', component: Variables, props: true },
            { path: '/external-resources', component: ResourcesComp, props: true},
            { path: '/external-resources/import', component: ResourcesImport},
            { path: '/external-resources/:index', component: ResourcesEditComp, props: true}
        ]

        // 3. Create the router instance and pass the `routes` option
        const router = new VueRouter({
            routes // short for `routes: routes`
        })


        router.beforeEach((to, from, next)=>{
            route_path=to.path.replace('/study/','');
            
            if (store.state.formTemplateParts[route_path] !== undefined){
                store.commit('tree_active_node',route_path);
            }

            if (route_path=='/'){}
            //store.commit('tree_active_node','study_desc.title_statement.idno');            
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
                active_node: {
                    id: 'table_description.title_statement.table_number'
                },
                external_resources:[],
                data_files:[],
                variables:{
                    "F1":{}
                },
                variables_loaded:false,
                variables_isloading:false,
                variables_active_tab:"documentation",
                variable_documentation_template:{
                    "title":"Variable",
                    "key":"variable",
                    "items":[
                        {
                            "type": "section",
                            "key": "variable_description",
                            "title": "Description",
                            "expanded": true,
                            "items": [
                                {
                                    "key": "var_txt",
                                    "title": "Definition",
                                    "type": "textarea",
                                    "class": "required",
                                    "required": false,
                                    "help_text": "Definition help text",
                                    "rules":"max:1000",
                                    "enabled":true
                                },
                                {
                                    "key": "var_universe",
                                    "title": "Universe",
                                    "type": "text",
                                    "class": "required",
                                    "required": false,
                                    "help_text": "Universe help text",
                                    "rules":"max:3000",
                                    "enabled": true
                                },                            
                                {
                                    "key": "var_concept",
                                    "title": "Concepts",
                                    "type": "array",
                                    "class": "required",
                                    "enabled":true,
                                    "props": {
                                        "title": {
                                            "key": "title",
                                            "title": "Title",
                                            "type": "text",
                                            "rules":"required",
                                            "name": "Concept title"
                                        },
                                        "vocab": {
                                            "key": "vocab",
                                            "title": "Vocabulary",
                                            "type": "text"
                                        },
                                        "uri": {
                                            "key": "uri",
                                            "title": "Vocabulary URI",
                                            "type": "text"
                                        }
                                    }
                                },
                            ]
                        },
                        {
                            "type": "section",
                            "key": "variable_question",
                            "title": "Question",
                            "expanded": true,
                            "items": [
                                {
                                    "key": "var_qstn_preqtxt",
                                    "title": "Pre-Question text",
                                    "type": "text",
                                    "enabled":true
                                },
                                {
                                    "key": "var_qstn_qstnlit",
                                    "title": "Literal question",
                                    "type": "text",
                                    "enabled":true
                                },
                                {
                                    "key": "var_qstn_postqtxt",
                                    "title": "Post-Question text",
                                    "type": "text",
                                    "enabled":true
                                },
                                {
                                    "key": "var_qstn_ivuinstr",
                                    "title": "Interviewer instructions",
                                    "type": "text",
                                    "enabled":true
                                }
                            ]
                        },
                        {
                            "type": "section",
                            "key": "variable_imputation",
                            "title": "Imputation and derivation",
                            "expanded": true,
                            "items": [
                                {
                                    "key": "var_resp_unit",
                                    "title": "Source of information",
                                    "type": "textarea",
                                    "enabled":true
                                },                            
                                {
                                    "key": "var_imputation",
                                    "title": "Imputation",
                                    "type": "text",
                                    "enabled":true
                                },
                                {
                                    "key": "var_codinstr",
                                    "title": "Recoding and derivation",
                                    "type": "text",
                                    "enabled":true
                                }
                            ]
                        }                    
                    ]
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
                getDataFiles(state) {
                    return state.data_files;
                },
                getDataFileById: (state) => (fid) => {
                    for(i=0;i<state.data_files.length;i++){
                        if(state.data_files[i].file_id==fid){
                            console.log("file found",state.data_files[i]);
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
                getVariableDocumentationTemplate(state){
                    return state.variable_documentation_template;
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
                }
            },
            actions: {               
                async initData({commit},options) {
                    store.state.variables_isloading=true;
                    await store.dispatch('loadTemplatesList',{});
                    await store.dispatch('loadDataFiles',{dataset_id:options.dataset_id});
                    await store.dispatch('loadAllVariables',{dataset_id:options.dataset_id});
                    await store.dispatch('loadExternalResources',{dataset_id:options.dataset_id});
                    store.state.variables_loaded=true;
                    store.state.variables_isloading=false;
                },
                async loadTemplatesList({commit},options) {
                    let url=CI.base_url + '/api/templates/list/'+store.state.project_type;;
                    return axios
                    .get(url)
                    .then(function (response) {
                        store.state.templates=response.data.result;
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
                        console.log(response);
                        store.state.formData=response.data.project.metadata;                        
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
                },
                async loadDataFiles({commit},options) {
                    let url=CI.base_url + '/api/editor/datafiles/'+options.dataset_id;
                    return axios
                    .get(url)
                    .then(function (response) {
                        console.log(response);
                        let data_files_=[];
                        if(response.data.datafiles){
                            Object.keys(response.data.datafiles).forEach(function(element, index) { 
                                data_files_.push(response.data.datafiles[element]);
                            })
                            commit('data_files',data_files_);
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                    })
                    .then(function () {
                        console.log("vuex request completed");
                    });                    
                },
                async loadAllVariables({commit,state},options) {
                    i=0;
                    for (let file of state.data_files) {
                        store.dispatch('loadVariables',{dataset_id:options.dataset_id, fid:file.file_id});
                    }
                },
                async loadVariables({commit}, options) {//options {dataset_idno,fid}
                    let url=CI.base_url + '/api/editor/variables/'+options.dataset_id + '/'+ options.fid + '?detailed=1';
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
                        console.log(error);
                    });
                },
                async loadExternalResources({commit}, options) {
                    let url=CI.base_url + '/api/editor/resources/'+options.dataset_id;
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
            },
            mutations: VueDeepSet.extendMutation({
                // other mutations
                data_model (state,data) {
                    console.log("value added");
                },
                tree_active_node(state,node){
                    state.treeActiveNode=state.formTemplateParts[node];
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
                    console.log(response);
                    
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