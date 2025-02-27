Vue.component('vue-move-collection', {
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
            let url = CI.site_url + '/api/collections/tree_flatten';
            axios.get(url)
            .then(response => {
                vm.collections = response.data.collections;
            })
            .catch(function (error) {
                alert("Error:" + error.response.data.message);
                console.log(error);
            });
        },
        moveCollection: function() {
            let vm=this;
            let url = CI.site_url + '/api/collections/move';
            
            let form_data = {
                'source_id':vm.source_collection_id,                
                'target_id':vm.target_collection_id
            };            

            axios.post(url, form_data)
            .then(response => {
                console.log("move-collection",response);
                vm.loadCollections();
                alert("Collection moved successfully");
                vm.dialog=false;
                //trigger event
                vm.$emit('collection-moved');
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
            if (this.source_collection_id==null || this.target_collection_id==null){
                return true;
            }

            if (this.source_collection_id==this.target_collection_id){
                return true;
            }

        },
        TargetCollections: function() {
            let items=[];
            items.push({id:0,title:"/"});
            
            //add the rest of the collections
            for (let i=0;i<this.collections.length;i++){
                let item=this.collections[i];
                items.push(item);
            }
            return items;
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
                    Move collection                    
                    </v-card-title>
                    

                    <v-card-text> 
                        <div class="text-muted text-small">Select the source and target collections to move the collection</div>

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
                                :items="TargetCollections"
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
                            @click="moveCollection"
                            :disabled="isCopyDisabled"
                        >Move</v-btn>
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

