<script>
        Vue.use(Vuex)
        Vue.use(VueDeepSet)        

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
            
            echo $this->load->view("metadata_editor/vue-form-main-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-form-preview-component.js",null,true);
            
            echo $this->load->view("metadata_editor/vue-external-resources-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-external-resources-edit-component.js",null,true);
            //echo $this->load->view('metadata_editor/vue-form-text-field.js',null,true);
            //echo $this->load->view('metadata_editor/vue-form-part.js',null,true);
            //echo $this->load->view('metadata_editor/editor-main-component.js',null,true);
            echo $this->load->view("metadata_editor/vue-datafiles-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-datafile-edit-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-variables-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-edit-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-variable-categories-component.js",null,true);

            //tree view component
            echo $this->load->view("metadata_editor/vue-form-tree.js",null,true);

            //metadata form component
            //echo $this->load->view("metadata_editor/vue-metadata-form-component.js",null,true); //see v-form

            //metadata grid component
            echo $this->load->view("metadata_editor/vue-grid-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-grid-preview-component.js",null,true);

            //nested
            echo $this->load->view("metadata_editor/vue-nested-section-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-simple-array-component.js",null,true);
            echo $this->load->view("metadata_editor/vue-table-component.js",null,true);

            echo $this->load->view("metadata_editor/vue-geospatial-identification-component.js",null,true);
        ?>

        
        <?php if (empty($metadata)):?>
            var project_metadata={};
        <?php else:?>
            var project_metadata=<?php echo json_encode($metadata);?>;
        <?php endif;?>

        <?php if (empty($survey)):?>
            var project_sid=null;
        <?php else:?>
            var project_sid=<?php echo isset($survey['id']) ? $survey['id'] : 'null';?>;
        <?php endif;?>

        let project_idno='<?php echo isset($survey['idno']) ? $survey['idno'] : '';?>';
        let project_type='<?php echo isset($survey['type']) ? $survey['type'] : '';?>';

        // 1. Define route components.
        const main = {props:['element_id'],template: '<div><form-main/></div>' }
        const Home = { template: '<div>Home -todo </div>' }
        const PublishProject = { template: '<div>Publish options -todo </div>' }
        const _main = {props: ['active_section'],template: '<div><study-metadata/></div>' }
        const Datafiles ={template: '<div><datafiles/></div>'}
        const Variables ={props: ['file_id'],template: '<div><variables /></div>'}
        const ResourcesComp ={props: ['index'],template: '<div><external-resources /></div>'}
        const ResourcesEditComp ={props: ['index'],template: '<div><external-resources-edit /></div>'}

        // 2. Define some routes
        // Each route should map to a component. The "component" can
        // either be an actual component constructor created via
        // `Vue.extend()`, or just a component options object.
        const routes = [
            {path: '/', component: main,
                redirect: '/study/<?php echo isset($metadata_template_arr['items'][0]['key']) ? $metadata_template_arr['items'][0]['key'] : 'study_description' ;?>',
                name: 'home',
            },
            { path: '/publish', component: PublishProject },
            {path: '/study/:element_id', component: main, name: 'study',props: true },
            { path: '/datafiles', component: Datafiles },
            { path: '/variables/:file_id', component: Variables, props: true },
            { path: '/external-resources', component: ResourcesComp, props: true},
            { path: '/external-resources/:index', component: ResourcesEditComp, props: true}
        ]

        // 3. Create the router instance and pass the `routes` option
        const router = new VueRouter({
            routes // short for `routes: routes`
        })

        router.beforeEach((to, from, next)=>{
            console.log("router",to,from);

            route_path=to.path.replace('/study/','');
            //store.commit('tree_active_node',route_path);
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
                formData: project_metadata,
                formTemplate:form_template,
                formTemplateParts:form_template_parts,
                treeActiveNode:null,
                active_node: {
                    id: 'table_description.title_statement.table_number'
                },
                external_resources:[],
                data_files:[]
            },
            mutations: VueDeepSet.extendMutation({
                // other mutations
                data_model (state,data) {
                    //this.$vuexSet('testing',"another value");
                    console.log("value added");                    
                },
                tree_active_node(state,node){
                    state.treeActiveNode=state.formTemplateParts[node];
                    console.log("node",state.treeActiveNode);
                },
                external_resources(state,data){
                    state.external_resources=data;
                },
                external_resources_add(state,newResource){
                    let new_idx=state.external_resources.push(newResource)-1;
                    console.log("external reosurceas add commited",state.external_resources);
                    return new_idx;//index of newly added resource
                }

            })
            /*: {
                data_model (state,data) {
                    state.formData=data;
                }
            }*/
            //mutations: VueDeepSet.extendMutation()
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
</script>