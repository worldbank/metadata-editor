Vue.component('vue-copy-collection', {
    props: ['value'],
    data() {
        return {
            is_loading: false,
            is_updating: false,
            collections: [],
            source_collection_id: null,
            target_collection_id: null,                   
        }
    },
    created:function(){        
        this.loadCollections();
    },   
    methods: {        
        loadCollections: function() {
            let vm=this;
            let url = CI.base_url + '/api/collections/tree_flatten';
            axios.get(url)
            .then(response => {
                vm.collections = response.data.collections;
            })
            .catch(function (error) {
                alert("Error:" + error.response.data.message);
                console.log(error);
            });
        },
        copyCollection: function() {
            let vm=this;
            let url = CI.base_url + '/api/collections/copy';
            
            let form_data = {
                'source_id':vm.source_collection_id,                
                'target_id':vm.target_collection_id
            };            

            axios.post(url, form_data)
            .then(response => {
                console.log("copy-collection",response);
                vm.loadCollections();
                alert("Collection copied successfully");
                vm.dialog=false;
            })
            .catch(function (error) {
                alert("Error:" + error.response.data.message);
                console.log(error);
            });
        },
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
       isCopyDisabled: function() {
              return this.source_collection_id==null || this.target_collection_id==null;
         }
    },
    template: `
        <div class="vue-copy-collection container">
        
        <template v-if="value">
            
            <div class="text-center">

                <v-dialog
                    v-model="dialog"
                    width="600px"
                    scrollable
                    persistent
                >                

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    Copy collection                    
                    </v-card-title>
                    

                    <v-card-text> 
                        <div class="text-muted text-small">Copy projects and users from one collection to another</div>                        

                        <div class="form-group mt-3">
                            <label>Source</label>
                            <v-select
                                :items="collections"
                                item-text="title"
                                item-value="id"
                                v-model="source_collection_id"
                                label=""
                                outlined
                                dense
                            ></v-select>
                        </div>

                        <div class="form-group">
                            <label>Target</label>                            
                            <v-select
                                :items="collections"
                                item-text="title"
                                item-value="id"
                                v-model="target_collection_id"
                                label=""
                                outlined
                                dense
                            ></v-select>
                        </div>
                        
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    
                        <v-spacer></v-spacer>
                        <v-btn 
                            class="ma-2 mr-1"                                                    
                            color="primary"
                            small
                            @click="copyCollection"
                            :disabled="isCopyDisabled"
                        >Copy</v-btn>
                        <v-btn
                            class="ma-2"
                            outlined
                            color="indigo"
                            small
                            @click="dialog = false"
                        >
                            Close
                        </v-btn>
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        
        </div>
        `
});

