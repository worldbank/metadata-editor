Vue.component('vue-project-share', {
    props: ['value','users','project_id','shared_users'],
    data() {
        return {            
            selected: [],
            user_access: 'view',
            user_selected:''
        }
    },
    created:function(){
    },
    methods: {        
        addAccess: function() {
            this.$emit('share-project', 
                {
                    'project_id':this.project_id,
                    'user_id':this.user_selected,
                    'permissions':this.user_access
                }
            );
            this.user_selected='';
            this.user_access='view';            
        },
        updateAccess: function(index){
            this.$emit('share-project', 
                {
                    'project_id':this.project_id,
                    'user_id':this.shared_users[index]['user_id'],
                    'permissions':this.shared_users[index]['permissions']
                }
            );
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
        <div class="vue-project-share">

        <template>        
            <div class="text-center">
                <v-dialog
                v-model="dialog"
                width="600px"
                scrollable
                >

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    Share project
                    </v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="6">
                                <select class="form-control" v-model="user_selected">
                                    <option value="">Select user</option>
                                    <option v-for="user in users" :value="user.id">{{user.username}}</option>
                                </select>
                            </v-col>
                            <v-col cols="4">
                                <select class="form-control" v-model="user_access">
                                    <option value="">Select access</option>
                                    <option value="view">View</option>
                                    <option value="edit">Edit</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </v-col>
                            <v-col cols="2">
                                <v-btn
                                    block
                                    class="ma-2 mr-3"
                                    outlined
                                    color="indigo"
                                    small
                                    @click="addAccess"
                                >Share
                                </v-btn>
                            </v-col>
                        </v-row>
                        <!--
                        <v-row style="display:none;">
                             <v-col cols="9">   
                                <v-autocomplete
                                    class="controls-border-top"
                                    v-model="selected"
                                    :items="users"
                                    solo
                                    dense
                                    chips
                                    small-chips
                                    item-text="username"
                                    item-value="id"
                                    multiple
                                ></v-autocomplete>
                            </v-col>
                            <v-col cols="3">
                                    <v-btn
                                        :disabled="selected.length==0"
                                        block
                                        class="ma-2 mr-3"
                                        outlined
                                        color="indigo"
                                        small                         
                                        @click="shareProject"
                                    >Share
                                    </v-btn>
                            </v-col>
                        </v-row>
                        -->


                    <div class="table-responsive mt-3" style="max-height:200px;overflow:auto;" v-if="shared_users!=''">
                    
                        <table class="table table-sm table-hover" style="font-size:small;">
                            <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tr v-for="(user,index) in shared_users" :key="index">
                                <td>
                                    <div class="capitalize">{{user.username}}</div>
                                    <div class="text-muted text-secondary">{{user.email}}</div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm" v-model="user.permissions">
                                        <option value="view">View</option>
                                        <option value="edit">Edit</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-xs btn-danger" @click="removeAccess(index)">Remove</button>
                                    <button class="btn btn-sm btn-xs btn-primary" @click="updateAccess(index)">Update</button>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div v-else>
                        <div class="text-center m-3" >No users have access to this project</div>
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

