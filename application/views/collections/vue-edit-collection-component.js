Vue.component('vue-edit-collection', {
    props: ['value','collection'],
    data() {
        return {
            
        }
    },
    created:function(){
    },
    methods: {        
        saveCollection: function() {
            this.$emit('update-collection', JSON.parse(JSON.stringify(this.collection)));
        },
        removeAccess: function(index) {
            this.$emit('remove-access', 
                {
                    'project_id':this.project_id,
                    'user_id':this.shared_users[index]['user_id']
                }
            );
            this.shared_users.splice(index, 1);
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
        <div class="vue-edit-collection">
 
        <template v-if="value">
            <div class="text-center">

                <v-dialog
                v-model="dialog"
                width="600px"
                scrollable
                >                

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    Edit collection
                    </v-card-title>

                    <v-card-text>
                        
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" class="form-control" v-model="collection.title" maxlength="150">
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea v-model="collection.description" class="form-control" maxlength="500"></textarea>
                        </div>

                        <button type="button" class="btn btn-primary" @click="saveCollection">Save</button>
                        

                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    <v-spacer></v-spacer>
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

