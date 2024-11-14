//external resources
const VueExternalResources = Vue.component('external-resources', {
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
            router.push('/external-resources/create');
        },
        importResource:function(){
            router.push('/external-resources/import');
        },
        deleteResource:function(id)
        {
            if (!confirm(this.$t("confirm_delete"))){
                return;
            }

            vm=this;
            let url=CI.base_url + '/api/resources/delete/'+ this.ProjectID + '/'+id;

            axios.post( url
            ).then(function(response){
                vm.$store.dispatch('loadExternalResources',{dataset_id:vm.ProjectID});
            })
            .catch(function(response){
                vm.errors=response;
                alert("Failed: " + vm.erorrMessageToText(response));
            });            
        },
        erorrMessageToText: function(error){
            let error_text = '';
            if (error.response.data.errors) {
                for (let key in error.response.data.errors) {
                    error_text += error.response.data.errors[key] + '\n';
                }
            } else {
                error_text = error.response.data.message;
            }
            return error_text;
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
        },
        isProjectEditable(){
            return this.$store.getters.getUserHasEditAccess;
        }            
    },
    template: `
        <div class="external-resources container-fluid pt-5 mt-5">

            <v-card>
                <v-card-title>External resources</v-card-title>
                <v-card-subtitle>
                    <v-row>
                        <v-col md="8"><strong>{{ExternalResources.length}}</strong> resources </v-col>
                        <v-col md="4" class="mb-2">
                            <div class="float-right">
                            <v-btn color="primary" outlined small @click="addResource"><i class="fas fa-plus-square"></i> Create resource</v-btn>
                            <v-btn color="primary" outlined small @click="importResource"><i class="fas fa-file-upload"></i> Import</v-btn> 
                            </div>
                        </v-col>
                    </v-row>
                </v-card-subtitle>
            <v-card-text>
            
            <external-resources-edit v-if="ActiveResourceIndex"  :index="ActiveResourceIndex"/>
            <div v-else>

                <table class="table table-striped">
                    <tr v-for="(resource, index) in ExternalResources" class="resource-row">                        
                        <td>
                            <i class="fas fa-file-alt"></i> <router-link :key="resource.id" class="nav-item" :to="'/external-resources/' + resource.id">{{resource.title}}</router-link>
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

            </v-card-text>
            </v-card>
        </div>
    `
})


