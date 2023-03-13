Vue.component('vue-collection-share', {
    props: ['value','projects','collections'],
    data() {
        return {
            selected: []
        }
    },
    created:function(){
    },
    methods: {        
        shareWithCollection: function() {
            this.$emit('share-with-collection', 
                {
                    'collection_id':this.selected,
                    'projects':this.projects
                }
            );
            this.selected=[];
            this.dialog=false;
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
        <div class="vue-collection-share">
        <template v-if="projects">
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                width="600px"
                scrollable
                >
                
                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">Add Project(s) to collection</v-card-title>

                    <v-card-text>
                        <v-row>
                             <v-col cols="9">   
                                <v-select
                                    label="Select collection"
                                    class="controls-border-top"
                                    v-model="selected"
                                    :items="collections"
                                    solo
                                    dense
                                    item-text="title"
                                    item-value="id"
                                ></v-select>
                            </v-col>
                            <v-col cols="3">
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
                            </v-col>
                        </v-row>


                        <div class="text-primary m-3">
                            You have <strong>{{projects.length}}</strong> projects selected.
                        </div>



                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn
                        class="ma-2"
                        outlined
                        color="indigo"
                        small
                        @click="selected=[];dialog = false"
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

