Vue.component('vue-collection-remove-dialog', {
    props: ['value', 'collections', 'project_id'],
    data() {
        return {        
            is_processing: false
        }
    },
    created:function(){
        
    },
    methods: {            
        removeCollectionFromList: function(collection_id){
            let index = this.collections.findIndex(x => x.id === collection_id);
            if (index > -1) {
                Vue.delete(this.collections, index);
            }
        },
        removeFromCollection: async function(project_id, collection_id) {
            if (!confirm($t("Are you sure you want to remove this collection from the project?"))) {
              return false;
            }
  
            try {
              let vm = this;
              console.log("remove collection", project_id, collection_id);
  
              let form_data = {
                'projects': project_id,
                'collections': collection_id
              };
              let url = CI.site_url + '/api/collections/remove_projects/';
  
              let response = await axios.post(url,
                form_data
              );
              
              vm.$emit('collection-removed', 1);
              vm.removeCollectionFromList(collection_id);                              
            } catch (e) {
              console.log("removeCollection error", e);
              let message = (e.response.data.message) ? e.response.data.message : JSON.stringify(e.response.data);
              alert("Failed: " + message);
            }
          },
        errorResponseMessage: function(error) {
            if (error.response.data.error) {
                return error.response.data.error;
            }
    
            if (error.response){
                return JSON.stringify(error.response.data);
            }
    
            return JSON.stringify(error);
        }
    },
    computed:{
        dialog: {
            get: function () {
                return this.value;
            },
            set: function (newValue) {
                this.$emit('input', newValue);               
            }
       },
    },
    template: `
        <div class="vue-collection-remove-dialog">

        <v-app>
        <template>        
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                width="600px"
                scrollable
                >

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                        {{$('Collections')}}
                    </v-card-title>
                    <v-card-text>
                        
                        <template v-for="collection in collections">
                            <v-chip small color="#dce3f7" class="m-2" close @click:close="removeFromCollection(collection.sid,collection.id)">
                            {{collection.title}}                                      
                            </v-chip>
                        </template>

                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>

                    <v-spacer></v-spacer>

                    
                    <v-btn
                        class="ma-2"
                        outlined
                        color="indigo"
                        small
                        v-if="!is_processing"
                        @click="selected=[];dialog = false"
                    >
                        {{$('Close')}}
                    </v-btn>
                    
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        </v-app>
        
    </div>
    `
});

