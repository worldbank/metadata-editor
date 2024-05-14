Vue.component('vue-collection-share', {
    props: ['value','projects','collections'],
    data() {
        return {
            selected: [],
        }
    },
    mounted: async function(){        
    },
    methods: {        
        shareWithCollection: function() {
            this.$emit('share-with-collection', 
                {
                    'collections':this.selected,
                    'projects':this.projects
                }
            );
            this.selected=[];
            this.dialog=false;
        },        
        errorResponseMessage: function(error) {
        if (error.response.data.error) {
            return error.response.data.error;
        }

        if (error.response){
            return JSON.stringify(error.response.data);
        }

        return JSON.stringify(error);
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
    },
    template: `
        <div class="vue-collection-share">
        <template v-if="projects">
            <div class="text-center">
                <v-dialog
                v-model="dialog"                
                scrollable
                max-width="500px"
                >
                
                <v-card>
                    <v-card-title class="text-h5 lighten-2">
                        Add to collection 
                        <v-chip v-if="projects.length>0" color="indigo" text-color="white" class="ml-2">{{projects.length}} project(s)</v-chip>
                    </v-card-title>

                    <v-card-text style="height: 300px;">
                        <v-treeview
                            :items="collections"
                            item-children="items"
                            activatable
                            item-key="id"
                            item-text="title"                            
                            v-model="selected"
                            selectable
                            selection-type="independent"
                            >                                                                
                        </v-treeview>
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-btn
                        :disabled="selected.length==0"
                        block
                        class="ma-2 mr-3"
                        outlined
                        color="indigo"
                        small                         
                        @click="shareWithCollection"
                    >Share
                    </v-btn>
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        
    </div>
    `
});

