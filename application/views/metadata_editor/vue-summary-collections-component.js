///Collections for the project
Vue.component('vue-summary-collections', {
    props:[],
    data: function () {    
        return {
            project_collections: [],
            dialog_share_collection: true,
            dialog_share_collection_options: {},
        }
    },
    created: function(){    
        this.loadProjectCollections();
    },

    watch:{
        dialog_share_collection: function(){
            if(this.dialog_share_collection == false){
                this.loadProjectCollections();
            }
        }
    },
    
    computed: {
        ProjectID(){
            return this.$store.state.project_id;
        },
    },
    methods:{        
        loadProjectCollections: function(){
            let vm = this;
            let url = CI.base_url + '/api/editor/collections/' + this.ProjectID;

            axios.get(url)
            .then(response => {
                vm.project_collections = response.data.collections;
            })
            .catch(function (error) {
                console.log(error);
            });            
        },
        getCollectionsList: async function() {
            vm = this;
            let url = CI.base_url + '/api/collections/tree';
            let response = await axios.get(url);
  
            if (response.status == 200) {
              return response.data.collections;
            }
  
            return response.data;
        },
        addProjectToCollection: async function() {
            try {
                let collections = await this.getCollectionsList();
                this.dialog_share_collection_options = {
                'collections': collections,
                'projects': [this.ProjectID]
                };
                this.dialog_share_collection = true;
            } catch (e) {
                console.log("shareProject error", e);
                alert("Failed", JSON.stringify(e));
            }
        },  
        onAddProjectsToCollection: async function(obj) {
            try {
              let vm = this;
              console.log("add projects to collection", obj);
  
              let form_data = obj;
              let url = CI.base_url + '/api/collections/add_projects';
  
              let response = await axios.post(url,
                form_data
              );
  
              console.log("completed addprojectstocollections", response);
              this.dialog_share_collection = false;              
              this.loadProjectCollections();
            } catch (e) {
              console.log("addProjectsToCollection error", e);
              alert("Failed", JSON.stringify(e));
            }
          },
          removeFromCollection: async function(collection_id) {
            if (!confirm("Are you sure you want to remove this collection from the project?")) {
              return false;
            }
  
            try {
              let vm = this;
              console.log("remove collection", collection_id);
  
              let form_data = {
                'projects': this.ProjectID,
                'collections': collection_id
              };
              let url = CI.base_url + '/api/collections/remove_projects/';
  
              let response = await axios.post(url,
                form_data
              );
  
              this.loadProjectCollections();
            } catch (e) {
              console.log("removeCollection error", e);
              let message = (e.response.data.message) ? e.response.data.message : JSON.stringify(e.response.data);
              alert("Failed: " + message);
            }
          },
    },    
    template: `
    <div class="project-collections-component">
        
        <div class="component-container">

            <v-card>
                <v-card-title class="d-flex justify-space-between">
                    <h6>{{$t("Collections")}}</h6>
                    <v-btn icon @click="addProjectToCollection">
                        <v-icon>mdi-folder-plus</v-icon>
                    </v-btn>
                </v-card-title>

            <v-card-text>

                <div v-if="project_collections.length==0" class="text-muted text-secondary">
                    {{$t("None")}}
                </div>
                
                <template v-for="collection in project_collections">
                    <v-chip small color="#dce3f7" class="mr-1" close @click:close="removeFromCollection(collection.id)">
                        {{collection.title}}                                      
                    </v-chip>
                </template>


            </v-card-text>
            </v-card>

            

        </div>

        <vue-collection-share v-model="dialog_share_collection" v-bind="dialog_share_collection_options" v-on:share-with-collection="onAddProjectsToCollection"></vue-collection-share>

    </div>          
    `    
});

