//external resources
Vue.component('external-resources', {
    props: ['index', 'id'],
    data() {
        return {
        }
    }, 
    created () {
        //this.loadDataFiles();
    },   
    methods: {
        editResource:function(id){
            this.page_action="edit";
            router.push('/external-resources/'+id);
        },
        addResource:function(){

            vm=this;
            let url=CI.base_url + '/api/resources/'+ this.ProjectID;

            formData={
                "title": "untitled",
                "dctype" :"doc/oth"
            }

            axios.post( url,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }
            ).then(function(response){
                vm.$store.dispatch('loadExternalResources',{dataset_id:vm.ProjectID});
                router.push('/external-resources/'+response.data.resource.id);
            })
            .catch(function(response){
                vm.errors=response;
            });
        },
        importResource:function(){
            router.push('/external-resources/import');
        },
        deleteResource:function(id)
        {            
            vm=this;
            let url=CI.base_url + '/api/resources/delete/'+ this.ProjectID + '/'+id;

            axios.post( url
            ).then(function(response){
                vm.$store.dispatch('loadExternalResources',{dataset_id:vm.ProjectID});
            })
            .catch(function(response){
                vm.errors=response;
            });            
        },
    },
    computed: {
        ExternalResources()
        {
          return this.$store.state.external_resources;
        },
        ActiveResourceIndex(){
            return this.$route.params.index;
        },
        ProjectID(){
            return this.$store.state.project_id;
        }
    },
    template: `
        <div class="external resources mt-3">
            <h1>External resources</h1>            
            <v-row>
                <v-col md="8"><strong>{{ExternalResources.length}}</strong> resources </v-col>
                <v-col md="4" class="mb-2">
                    <div class="float-right">
                    <button type="button" class="btn btn-sm btn-outline-primary mr-1" @click="addResource"><i class="fas fa-plus-square"></i> Create resource</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="importResource"><i class="fas fa-file-upload"></i> Import</button> 
                    </div>
                </v-col>
            </v-row>

            <external-resources-edit v-if="ActiveResourceIndex"  :index="ActiveResourceIndex"/>
            <div v-else>

                <table class="table table-striped">
                    <tr v-for="(resource, index) in ExternalResources" class="resource-row">                        
                        <td>
                            <i class="fas fa-file-alt"></i> <router-link class="nav-item" :to="'/external-resources/' + resource.id">{{resource.title}}</router-link>
                            <div class="text-small text-secondary">{{resource.filename}}</div>
                        </td>
                        <td>{{resource.dctype}}</td>
                        
                        <td>
                            <button type="button" class="btn btn-sm btn-default mr-2" @click="editResource(resource.id)"><i class="fas fa-edit"></i> Edit</button>
                            <button type="button" class="btn btn-sm btn-default" @click="deleteResource(resource.id)"><i class="fas fa-trash-alt"></i> Delete</button>
                        </td>
                    </tr>
                </table>

            </div>
        </div>
    `
})


