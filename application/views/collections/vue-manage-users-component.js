Vue.component('vue-manage-users', {
    props: ['value'],
    data() {
        return {
            users: [],
            collection_users: [],
            collection_id:'',
            collection:{},
            selected_users: [],
            selected_permission:'view',
            is_loading: false,
            is_updating: false,
            search:'',
            user_roles: [
                {
                    'value':'view',
                    'text':'View'
                },
                {
                    'value':'edit',
                    'text':'Edit'
                },
                {
                    'value':'admin',
                    'text':'Admin'
                }
            ],
            menu_change_user_role: false,
            menu_x: 0,
            menu_y: 0,
            menu_active_id: 0            
        }
    },
    created:function(){        
        this.collection_id=this.$route.params.id;
        this.loadCollection();
        //this.getUsers();
        this.getCollectionAccess();
    },
    watch: {
        search (val) {
          // Items have already been loaded
          //if (this.users.length > 0) return

          if (!val) return
  
          // Items have already been requested
          if (this.is_loading) return
  
          this.is_loading = true

        let vm=this;
        this.searchUsers(val);
        return;
        axios.get(CI.site_url + '/api/users/search?keywords='+val)
        .then(response => {
            vm.users = response.data.users;
            console.log("users",vm.users);
        })
        .catch(function (error) {
            console.log(error);
        })
        .finally(() => (this.is_loading = false));  
        },
      },
    methods: {
        showChangeRoleMenu (e, userId) {
            e.preventDefault()
            this.menu_change_user_role = false
            this.menu_x = e.clientX
            this.menu_y = e.clientY
            this.menu_active_id = userId          
            this.$nextTick(() => {
              this.menu_change_user_role = true
            })
          },
        removeSelectionItem: function(item) {
            let index = this.selected_users.indexOf(item);
            if (index > -1) {
                this.selected_users.splice(index, 1);
            }
        },
        loadCollection: function() {
            let vm=this;
            let url = CI.site_url + '/api/collections/' + this.collection_id;
            axios.get(url)
            .then(response => {
                vm.collection = response.data.collection;
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        updateAccess: function() {
            this.$emit('update-access', JSON.parse(JSON.stringify(this.title)));
        },
        removeAccess: function(index) {
            if (!confirm("Are you sure you want to remove access?")){
                return;
            }

            let vm=this;
            let url = CI.site_url + '/api/collections/remove_user_access';
            let collection=this.collection_users[index];
            let form_data = {
                'collection_id':collection.collection_id,
                'user_id':collection.user_id,
                'permissions':collection.permissions
            };
            console.log("form data",form_data);

            axios.post(url, form_data)
            .then(response => {
                console.log("remove user",response);
                vm.getCollectionAccess();
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        getUsers: function() {
            let vm=this;
            axios.get(CI.site_url + '/api/users')
            .then(response => {
                vm.users = response.data.users;
                console.log("users",vm.users);
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        searchUsers: _.debounce(function(val) {
            let vm=this;
            axios.get(CI.site_url + '/api/users/search?keywords='+val)
            .then(response => {
                vm.users = response.data.users;
                console.log("users",vm.users);
            })
            .catch(function (error) {
                console.log(error);
            })
            .finally(() => (this.is_loading = false));
        },300),
        //get collection users
        getCollectionAccess: function() {
            let vm=this;
            axios.get(CI.site_url + '/api/collections/user_access/' + this.collection_id)
            .then(response => {
                vm.collection_users = response.data.users;
                console.log("users",vm.users);
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        addCollectionAccess: async function() {
            this.is_updating = true;
            for (let i = 0; i < this.selected_users.length; i++) {
                let user = this.selected_users[i];
                await this.AddUserCollectionAccess(this.collection_id,user.id,this.selected_permission);
            }
            this.is_updating = false;
            this.selected_users=[];
        },
        AddUserCollectionAccess: async function(collection_id,user_id,permissions) {
            let vm=this;
            let url = CI.site_url + '/api/collections/user_access';
            let form_data = {
                'collection_id':collection_id,
                'user_id':user_id,
                'permissions':permissions
            };
            console.log("form data",form_data);

            axios.post(url, form_data)
            .then(response => {
                console.log("add user",response);
                vm.getCollectionAccess();
            })
            .catch(function (error) {
                console.log(error);
                alert("Error:" + error.response.data.message);
            });
        },
        ChangeUserRole: async function (permissions){
            await this.AddUserCollectionAccess(this.collection_id,this.menu_active_id,permissions);
            this.getCollectionAccess();
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
        <div class="vue-edit-collection container">
        
        <div class="mt-5 mb-5">
        <router-link to="/" >Return to collections</router-link>
        </div>
        
        <h3>{{collection.title}}</h3>

        <div class="border-round border-light p-3 mb-5 shadow bg-light">
            <h5>Add user</h5>
            <div class="row">
                <div class="col-6">
                
                    <!--select-->
                    <v-autocomplete
                        v-model="selected_users"
                        :loading="is_loading"
                        :search-input.sync="search"
                        :items="users"
                        solo
                        chips
                        color="blue-grey lighten-2"
                        label="Search users to select"
                        item-text="username"
                        item-value="id"
                        multiple
                        cache-items
                        return-object
                        no-data-text="Type user name or email to search for a user"                        
                    >
                        <template v-slot:selection="data">
                            <v-chip
                                v-bind="data.attrs"
                                :input-value="data.selected"
                                close
                                @click="data.select"
                                @click:close="removeSelectionItem(data.item)"
                            >                                
                                {{ data.item.username }}
                            </v-chip>
                        </template>

                        <template v-slot:item="data">
                            <template v-if="typeof data.item !== 'object'">
                                <v-list-item-content v-text="data.item"></v-list-item-content>
                            </template>
                            <template v-else>
                                <v-list-item-content>
                                <v-list-item-title v-html="data.item.username"></v-list-item-title>
                                <v-list-item-subtitle v-html="data.item.email"></v-list-item-subtitle>
                                </v-list-item-content>
                            </template>
                        </template>
                  </v-autocomplete>
                    <!--end-select-->
                </div>
                <div class="col">
                    <v-select
                        :items="user_roles"
                        v-model="selected_permission"
                        solo
                        item-text="text"
                        item-value="value"
                        label=""
                    ></v-select>                    
                </div>
                <div class="col-auto">
                    <v-btn large @click="addCollectionAccess" :loading="is_updating" color="primary">Add</v-btn>                    
                </div>
            </div>
        </div>

        <div class="border-round border-light p-3 mb-5 shadow bg-light">
            <v-simple-table v-if="collection_users.length>0">
                <thead>
                <tr>
                    <th>User</th>
                    <th style="width:150px;padding-left:32px;">Role</th>
                    <th>Actions</th>
                </tr>  
                </thead>
                <tbody>          
                <tr v-for="(user, index) in collection_users">
                    <td>
                        <div>{{user.username}}</div>
                        <div class="small text-muted">{{user.email}}</div>
                    </td>
                    <td>                           
                        <v-btn text @click.stop.prevent="showChangeRoleMenu($event, user.user_id, true)">
                            {{user.permissions}} <v-icon>mdi-chevron-down</v-icon> 
                        </v-btn>                        
                    </td>
                    <td>
                        <v-btn icon small color="red" @click="removeAccess(index)"><v-icon>mdi-delete-outline</v-icon></v-btn>
                    </td>
                </tr>
                </tbody>
            </v-simple-table>
            <div v-else>
                <p>No users found</p>
            </div>
        </div>

        <!--change user role-->
        <template>
            <v-menu
                v-model="menu_change_user_role"
                :position-x="menu_x-100"
                :position-y="menu_y+20"
                absolute
                offset-y
            >

                <v-list>          
                <v-list-item v-for="permission_ in user_roles">
                        <v-list-item-title @click="ChangeUserRole(permission_.value)"><v-btn text>{{permission_.text}}</v-btn></v-list-item-title>
                </v-list-item>  
                </v-list>
            </v-menu>
        </template>
        <!--end change user role-->
        
    </div>
    `
});

