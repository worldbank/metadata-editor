Vue.component('vue-collection-access-manager', {
    props: ['value'],
    data() {
        return {
            tab: 'collection-acl', // Default to first tab (use string value for Vuetify 2)
            users: [],
            collection_users: [],
            collection_acl_users: [], // Collection ACL users
            collection_id:'',
            collection:{},
            selected_users: [],
            selected_permission:'view',
            is_loading: false,
            is_updating: false,
            search:'',
            user_roles: [
                { value: 'view', text: this.$t('view') },
                { value: 'edit', text: this.$t('edit') },
                { value: 'admin', text: this.$t('admin') }
            ],
            menu_change_user_role: false,
            menu_x: 0,
            menu_y: 0,
            menu_active_id: 0,
            isProjectAccess: true // Track if we're editing project access or collection ACL
        }
    },
    created:function(){        
        this.collection_id=this.$route.params.id;
        this.loadCollection();
        //this.getUsers();
        this.getCollectionAccess();
        this.getCollectionAclUsers();
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
        
        },
      },
    methods: {
        showChangeRoleMenu (e, userId, isProjectAccess = true) {
            e.preventDefault()
            this.menu_change_user_role = false
            this.menu_x = e.clientX
            this.menu_y = e.clientY
            this.menu_active_id = userId
            this.isProjectAccess = isProjectAccess // Store context
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
        changeTab: function(tabName) {
            this.tab = tabName;
            
            // If switching to project access tab, ensure data is loaded
            if (tabName === 'project-access' && (!this.collection_users || this.collection_users.length === 0)) {
                this.getCollectionAccess();
            }
            
            // If switching to collection ACL tab, ensure data is loaded
            if (tabName === 'collection-acl' && (!this.collection_acl_users || this.collection_acl_users.length === 0)) {
                this.getCollectionAclUsers();
            }
        },
        updateAccess: function() {
            this.$emit('update-access', JSON.parse(JSON.stringify(this.title)));
        },
        removeAccess: function(index) {
            if (!confirm(this.$t("are_you_sure_remove_access"))){
                return;
            }

            let vm=this;
            let url = CI.site_url + '/api/collections/remove_user_project_access';
            let collection=this.collection_users[index];
            let form_data = {
                'collection_id':collection.collection_id,
                'user_id':collection.user_id,
                'permissions':collection.permissions
            };

            axios.post(url, form_data)
            .then(response => {
                vm.getCollectionAccess();
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("error") + ": " + error.response.data.message);
            });
        },
        getUsers: function() {
            let vm=this;
            axios.get(CI.site_url + '/api/users')
            .then(response => {
                vm.users = response.data.users;
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
            })
            .catch(function (error) {
                console.log(error);
            })
            .finally(() => (this.is_loading = false));
        },300),
        //get collection users
        getCollectionAccess: function() {
            let vm=this;
            axios.get(CI.site_url + '/api/collections/user_project_access/' + this.collection_id)
            .then(response => {
                vm.collection_users = response.data.users || [];
            })
            .catch(function (error) {
                console.log(error);
                vm.collection_users = [];
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
            let url = CI.site_url + '/api/collections/user_project_access';
            let form_data = {
                'collection_id':collection_id,
                'user_id':user_id,
                'permissions':permissions
            };

            axios.post(url, form_data)
            .then(response => {
                vm.getCollectionAccess();
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("error") + ": " + error.response.data.message);
            });
        },
        ChangeUserRole: async function (permissions){
            if (this.isProjectAccess) {
                await this.AddUserCollectionAccess(this.collection_id, this.menu_active_id, permissions);
                this.getCollectionAccess();
            } else {
                await this.AddUserCollectionAcl(this.collection_id, this.menu_active_id, permissions);
                this.getCollectionAclUsers();
            }
        },
        
        // Collection ACL methods
        getCollectionAclUsers: function() {
            let vm = this;
            axios.get(CI.site_url + '/api/collections/user_acl/' + this.collection_id)
            .then(response => {
                vm.collection_acl_users = response.data.users || [];
            })
            .catch(function (error) {
                console.log(error);
                vm.collection_acl_users = [];
            });
        },
        
        addCollectionAclAccess: async function() {
            this.is_updating = true;
            for (let i = 0; i < this.selected_users.length; i++) {
                let user = this.selected_users[i];
                await this.AddUserCollectionAcl(this.collection_id, user.id, this.selected_permission);
            }
            this.is_updating = false;
            this.selected_users = [];
        },
        
        AddUserCollectionAcl: async function(collection_id, user_id, permissions) {
            let vm = this;
            let url = CI.site_url + '/api/collections/user_acl';
            let form_data = {
                'collection_id': collection_id,
                'user_id': user_id,
                'permissions': permissions
            };

            axios.post(url, form_data)
            .then(response => {
                vm.getCollectionAclUsers();
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("error") + ": " + error.response.data.message);
            });
        },
        
        updateCollectionAclAccess: function(index) {
            let form_data = [];
            let vm = this;

            form_data.push({
                'user_id': this.collection_acl_users[index]['user_id'],
                'permissions': this.collection_acl_users[index]['permissions'],
                'collection_id': this.collection_id
            });

            let url = CI.site_url + '/api/collections/user_acl_update';
            axios.post(url, form_data)
            .then(response => {
                vm.getCollectionAclUsers();
            })
            .catch(function (error) {
                console.log(error);
                alert("Failed: " + vm.errorMessageToText(error));
            });
        },
        
        removeCollectionAclAccess: function(index) {
            if (!confirm("Are you sure you want to remove collection access for this user?")) {
                return;
            }

            let form_data = {
                'collection_id': this.collection_id,
                'user_id': this.collection_acl_users[index]['user_id']
            }

            let vm = this;
            let url = CI.site_url + '/api/collections/user_acl_remove';

            axios.post(url, form_data)
            .then(response => {
                vm.getCollectionAclUsers();
            })
            .catch(function (error) {
                console.log(error);
                alert("Failed: " + vm.errorMessageToText(error));
            });
        },
        
        ChangeCollectionAclRole: async function(permissions) {
            await this.AddUserCollectionAcl(this.collection_id, this.menu_active_id, permissions);
            this.getCollectionAclUsers();
        },
        
        errorMessageToText: function(error){
            let error_text = '';
            if (error.response && error.response.data && error.response.data.errors) {
                for (let key in error.response.data.errors) {
                    error_text += error.response.data.errors[key] + '\n';
                }
            } else if (error.response && error.response.data && error.response.data.message) {
                error_text = error.response.data.message;
            } else {
                error_text = 'An error occurred';
            }
            return error_text;
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
        safeCollectionUsers: function() {
            return this.collection_users || [];
        },
        hasCollectionUsers: function() {
            return this.safeCollectionUsers.length > 0;
        },
        safeCollectionAclUsers: function() {
            return this.collection_acl_users || [];
        },
        hasCollectionAclUsers: function() {
            return this.safeCollectionAclUsers.length > 0;
        }
    },
    template: `
        <div class="vue-edit-collection container">
        
        <div class="mt-5 mb-5">
        <router-link to="/" >{{$t('Return to collections')}}</router-link>
        </div>
        
        <h3>{{$t('Collection')}}: {{collection.title}}</h3>

        <!-- Simple Tab Indicator -->
        <div class="mb-4">
            <v-chip 
                :color="tab === 'collection-acl' ? 'primary' : 'grey'" 
                class="mr-2"
                @click="changeTab('collection-acl')"
                style="cursor: pointer;"
            >
                Collection Access Control
            </v-chip>
            <v-chip 
                :color="tab === 'project-access' ? 'primary' : 'grey'" 
                class="mr-2"
                @click="changeTab('project-access')"
                style="cursor: pointer;"
            >
                Project Access Management
            </v-chip>
        </div>

        <!-- Tab Content -->
        <div v-if="tab === 'collection-acl'" class="mt-4">
            <!-- Tab 1: Collection ACL -->
            <div v-if="!collection_id" class="text-center p-4">
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
                <p class="mt-2">Loading collection information...</p>
            </div>
            <div v-else>
                <div class="border-round border-light p-3 mb-5 shadow bg-light">
                    <h5>{{$t('Manage Collection Access Control')}}</h5>
                    <p class="text-muted">Control which users can access this collection and manage its settings</p>
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
                                :label="$t('Search users')"
                                item-text="username"
                                item-value="id"
                                multiple
                                cache-items
                                return-object
                                no-data-text="$t('type_user_name_or_email')"                        
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
                            <v-btn large @click="addCollectionAclAccess" :loading="is_updating" color="primary">{{$t('add')}}</v-btn>                    
                        </div>
                    </div>
                </div>

                <div class="border-round border-light p-3 mb-5 shadow bg-light">
                    <div v-if="!hasCollectionAclUsers">
                        <p>No collection ACL users found</p>
                    </div>
                    <v-simple-table v-else>
                        <thead>
                        <tr>
                            <th>{{$t('user')}}</th>
                            <th style="width:150px;padding-left:32px;">{{$t('role')}}</th>
                            <th>{{$t('actions')}}</th>
                        </tr>  
                        </thead>
                        <tbody>          
                        <tr v-for="(user, index) in safeCollectionAclUsers">
                            <td>
                                <div>{{user.username}}</div>
                                <div class="small text-muted">{{user.email}}</div>
                            </td>
                            <td>                           
                                <v-btn text @click.stop.prevent="showChangeRoleMenu($event, user.user_id, false)">
                                    {{user.permissions}} <v-icon>mdi-chevron-down</v-icon> 
                                </v-btn>                        
                            </td>
                            <td>
                                <v-btn icon small color="red" @click="removeCollectionAclAccess(index)"><v-icon>mdi-delete-outline</v-icon></v-btn>
                            </td>
                        </tr>
                        </tbody>
                    </v-simple-table>
                </div>
            </div>
        </div>

        <div v-else-if="tab === 'project-access'" class="mt-4">
            <!-- Tab 2: Project Access Management -->
            <div v-if="!collection_id" class="text-center p-4">
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
                <p class="mt-2">Loading collection information...</p>
            </div>
            <div v-else>
                <div class="border-round border-light p-3 mb-5 shadow bg-light">
                    <h5>{{$t('Manage Project Access')}}</h5>
                    <p class="text-muted">Control which users can access projects within this collection</p>
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
                            :label="$t('Search users')"
                            item-text="username"
                            item-value="id"
                            multiple
                            cache-items
                            return-object
                            no-data-text="$t('type_user_name_or_email')"                        
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
                        <v-btn large @click="addCollectionAccess" :loading="is_updating" color="primary">{{$t('add')}}</v-btn>                    
                    </div>
                </div>
            </div>

            <div class="border-round border-light p-3 mb-5 shadow bg-light">
                <div v-if="!hasCollectionUsers">
                    <p>No users found</p>
                </div>
                <v-simple-table v-else>
                    <thead>
                    <tr>
                        <th>{{$t('user')}}</th>
                        <th style="width:150px;padding-left:32px;">{{$t('role')}}</th>
                        <th>{{$t('actions')}}</th>
                    </tr>  
                    </thead>
                    <tbody>          
                    <tr v-for="(user, index) in safeCollectionUsers">
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
            </div>
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

