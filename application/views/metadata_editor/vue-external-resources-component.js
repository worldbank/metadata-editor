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
        editResource:function(file_id){
            this.page_action="edit";
            this.edit_item=file_id;
        },
        addResource:function(){
            this.page_action="edit";
            this.$store.commit('external_resources_add',{title:'untitled resource'});
            newIdx=this.ExternalResources.length -1;
            router.push('/external-resources/'+newIdx);
        },
        saveFile: function(data)
        {
            console.log("saving file",this.data_files[this.edit_item]);
            this.$set(this.data_files, this.edit_item, data);            
        },
        exitEditMode: function()
        {
            this.page_action="list";
            this.edit_item=null;
        }
    },
    computed: {
        ExternalResources()
        {
          return this.$store.state.external_resources;
        },
        ActiveResourceIndex(){
            return this.$route.params.index;
        }
    },
    template: `
        <div>
            <h1>External resources</h1>            
            activeResourceIndex: - {{ActiveResourceIndex}}
            
            <v-row>
                <v-col md="8"><strong>{{ExternalResources.length}}</strong> resources </v-col>
                <v-col md="4" class="d-flex justify-end">
                    <button type="button" class="btn btn-link" @click="addResource">Add resource</button> | 
                    <button type="button" class="btn btn-link" @click="addResource">Refresh page</button>                    
                </v-col>
            </v-row>

            <external-resources-edit v-if="ActiveResourceIndex"  :index="ActiveResourceIndex"/>
            <div v-else>

                <div v-for="(resource, index) in ExternalResources" class="resource-row">                
                    <div class="media">
                        <div><i class="resource-icon fas fa-file-alt"></i></div>
                        <div class="media-body">
                            <h5 class="mt-0"><router-link class="nav-item" :to="'/external-resources/' + index">{{resource.title}}</router-link></h5>
                            <div>{{resource.dctype}}</div>
                            <div>{{resource.filename}}</div>
                            <div>
                                <span class="badge badge-light mr-2"><i class="fas fa-edit"></i> Edit</span>
                                <span class="badge badge-light"><i class="fas fa-trash-alt"></i> Delete</span>
                            </div>
                        </div>
                    </div>                    
                </div>

            </div>
        </div>
    `
})


