Vue.component('vue-collections-component', {
    props: ['value'],
    data() {
        return {     
            collections: [],
            collection_permissions: {}, // Store user permissions for collections
            user_permissions: null, // Store overall user permission data
            edit_collection: {},
            dialog_edit: false,
            dialog_copy_collection: false,
            dialog_move_collection: false,
            active_tab:1,
            site_base_url: CI.site_url,
            action_menu: false,        
            action_menu_x: 0,
            action_menu_y: 0,
            action_menu_id: 0
        }
    },
    
    created: function() {
        this.loadUserPermissions();
        this.loadCollections();
    },
    computed: {
        Collections() {
            console.log("Collections computed property called, collections:", this.collections);
            return this.collections;
        },
        canCreateCollections() {
            // Only allow creation if user has admin permissions
            if (this.user_permissions && this.user_permissions.admin_type === 'global') {
                return true;
            }
            if (this.user_permissions && this.user_permissions.admin_type === 'collection' && this.user_permissions.global_permission === 'admin') {
                return true;
            }
            return false;
        }
    },
    methods: {
        pageLink: function(page) {
            window.location.href = CI.site_url + '/' + page;
        },
        showMenu: function(data) {
            console.log("showMenu", data);
            let e=data.e;
            e.preventDefault()
            this.action_menu = false
            this.action_menu_id = data.id
            this.action_menu_x = e.clientX
            this.action_menu_y = e.clientY
            this.$nextTick(() => {
                this.action_menu = true
            })            
        },
        momentDate(date) {
            return moment.utc(date).format("MMM d, YYYY")
        },
        refreshCollectionsTree: function() {
            let url = CI.site_url + '/api/collections/tree_refresh/';

            axios.get(url)
                .then((response) => {
                    this.loadCollections();
                })
                .catch((error) => {
                    console.log("error", error);
                    alert(this.$t("failed") + ": " +  this.errorResponseMessage(error));
                });
        },
        loadUserPermissions: function() {
            let url = CI.site_url + '/api/collections/permissions';
            console.log("loading user permissions from:", url);
            return axios
                .get(url)
                .then((response) => {
                    this.user_permissions = response.data;
                    
                    // Convert array to object for easy lookup by collection ID
                    this.collection_permissions = {};
                    if (response.data.collections) {
                        response.data.collections.forEach((collection) => {
                            this.collection_permissions[collection.id] = {
                                permissions: collection.permissions
                            };
                        });
                    }
                    
                    console.log("user permissions loaded", this.user_permissions);
                    console.log("collection permissions mapped:", this.collection_permissions);
                })
                .catch((error) => {
                    console.log("error loading permissions", error);
                    // Don't show alert for permissions - just log error
                    this.collection_permissions = {};
                });
        },
        loadCollections: function() {
            let url = CI.site_url + '/api/collections/tree';
            console.log("loading collections");
            return axios
                .get(url)
                .then((response) => {
                    this.collections = response.data.collections;
                    console.log("loading collections", this.collections);
                })
                .catch((error) => {
                    console.log("error", error);
                    alert(this.$t("failed") + ": " +  this.errorResponseMessage(error));
                });
        },
        findByCollectionId: function(id) {
            //recursively search for collection by id
            let collection = null;
            let search = function(items) {
                for (let i = 0; i < items.length; i++) {
                    if (items[i].id == id) {
                        collection = items[i];
                        break;
                    }
                    if (items[i].items) {
                        search(items[i].items);
                    }
                }
            }
            search(this.collections);
            return collection;
        },
        getCollectionPermissions: function(collection_id) {
            // If user is global admin, grant all permissions to all collections
            if (this.user_permissions && this.user_permissions.admin_type === 'global') {
                return {
                    permissions: 'admin',
                    can_edit: true,
                    can_admin: true,
                    can_delete: true,
                    can_manage_access: true
                };
            }
            
            // If user has global collection admin role, grant admin permissions to all collections
            if (this.user_permissions && this.user_permissions.admin_type === 'collection' && this.user_permissions.global_permission === 'admin') {
                return {
                    permissions: 'admin',
                    can_edit: true,
                    can_admin: true,
                    can_delete: true,
                    can_manage_access: true
                };
            }
            
            // If user has global collection edit role, grant edit permissions to all collections
            if (this.user_permissions && this.user_permissions.admin_type === 'collection' && this.user_permissions.global_permission === 'edit') {
                return {
                    permissions: 'edit',
                    can_edit: true,
                    can_admin: false,
                    can_delete: false,
                    can_manage_access: false
                };
            }
            
            // For collection-specific admins or regular users, derive permissions from permissions
            const collection_perms = this.collection_permissions[collection_id] || { permissions: 'view' };
            
            return {
                permissions: collection_perms.permissions,
                can_edit: ['edit', 'admin'].includes(collection_perms.permissions),
                can_admin: collection_perms.permissions === 'admin',
                can_delete: collection_perms.permissions === 'admin',
                can_manage_access: collection_perms.permissions === 'admin'
            };
        },


        editCollectionById: function(id) {
            let collection = this.findByCollectionId(id);
            if (collection) {
                //this.edit_collection = JSON.parse(JSON.stringify(collection));
                this.edit_collection = {
                    id: collection.id,
                    title: collection.title,
                    description: collection.description,
                    pid: collection.pid
                }
                this.dialog_edit = true;
            }
        },
        addChildCollectionById: function(pid) {
            let collection = this.findByCollectionId(pid);
            if (collection) {
                this.edit_collection = {
                    pid: pid
                };
                this.dialog_edit = true;
            }
        },
        createCollection: function() {
            this.edit_collection = {};
            this.dialog_edit = true;
        },
        updateCollection: function(collection) {            
            let url = CI.site_url + '/api/collections';

            if (collection.id) {
                url = CI.site_url + '/api/collections/update/' + collection.id;
            }

            let form_data = collection;

            axios.post(url,
                    form_data
                )
                .then((response) => {
                    console.log(response);
                    this.loadCollections();
                    this.dialog_edit = false;
                })
                .catch((error) => {
                    console.log("error", error);
                    alert(this.$t("failed") + ": " +  this.errorResponseMessage(error));
                });
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
        DeleteCollection: function(id) {
            if (!confirm(this.$t("are_you_sure_delete_collection"))) {
                return false;
            }

            let url = CI.site_url + '/api/collections/delete/' + id;

            axios.post(url)
                .then((response) => {
                    this.loadCollections();
                })
                .catch((error) => {
                    console.log("error", error);
                    alert(this.$t("failed") + ": " +  this.errorResponseMessage(error));
                });
        },
        ManageCollectionAccess: function(id) {
            this.$router.push('/manage-users/' + id);
        }
    },
    template: `
    <div class="vue-collection-component">
        
        <section class="container">

                    <div class="row">

                        <div class="projects col " >

                            <v-tabs background-color="transparent" v-model="active_tab">
                                <v-tab @click="pageLink('projects')"><v-icon>mdi-text-box</v-icon> <a :href="site_base_url + '/editor'">{{$t("projects")}}</a></v-tab>
                                <v-tab @click="pageLink('collections')" active><v-icon>mdi-folder-text</v-icon> <a :href="site_base_url + '/collections'">{{$t("collections")}}</a> </v-tab>
                                <v-tab @click="pageLink('templates')"><v-icon>mdi-alpha-t-box</v-icon> <a :href="site_base_url + '/templates'">{{$t("templates")}}</a></v-tab>                                    
                            </v-tabs>

                            <div class="d-flex">                            
                                <div class="flex-grow-1 flex-shrink-0 mr-auto">
                                    <h3 class="mt-3 mb-1">{{$t('Collections')}}</h3>                                
                                </div>
                                <div class="justify-content-end">
                                    <v-btn color="primary" v-if="canCreateCollections" @click="createCollection">{{$t('Create new collection')}}</v-btn>
                                </div>
                            </div>

                            <div class="bg-light p-3 shadow mt-2" >
                                <div class="p-3 border text-center text-danger" v-if="!collections || collections.length<1"> {{$t('No collections found')}}!</div>





                                <template v-if="collections && collections.length>0">
                                <div class="row border-bottom">
                                    <div class="col-1">
                                        #
                                    </div>
                                    <div class="col">
                                        <strong>{{$t('Collection')}}</strong>
                                    </div>
                                    <div class="col-1"><strong>{{$t('Users')}}</strong></div>
                                    <div class="col-1"><strong>{{$t('Projects')}}</strong></div>
                                    <div class="col-1">
                                    <!-- collection actions -->
                                        <v-menu offset-y>
                                            <template v-slot:activator="{ on, attrs }">
                                                <v-btn
                                                color="primary"
                                                dark
                                                v-bind="attrs"
                                                v-on="on"
                                                icon
                                                >
                                                <v-icon>mdi-dots-vertical</v-icon>
                                                </v-btn>
                                            </template>
                                            <v-list>
                                                <v-list-item>
                                                    <v-list-item-title @click="dialog_copy_collection=true"><v-btn text>{{$t('Copy collection')}}</v-btn></v-list-item-title>
                                                </v-list-item>
                                                <v-list-item>
                                                    <v-list-item-title @click="dialog_move_collection=true"><v-btn text>{{$t('Move collection')}}</v-btn></v-list-item-title>
                                                </v-list-item>
                                                <v-list-item>
                                                    <v-list-item-title @click="refreshCollectionsTree"><v-btn text>{{$t('Refresh tree')}}</v-btn></v-list-item-title>
                                                </v-list-item>
                                            </v-list>
                                        </v-menu>
                                    <!-- end collection actions -->                                    
                                    </div>
                                </div>


                                <div v-for="(collection,index) in collections" >
                                    <vue-tree-list 
                                        :value="collection" 
                                        :permissions="getCollectionPermissions(collection.id)"
                                        :getPermissionsFunction="getCollectionPermissions"
                                        v-on:show-menu="showMenu"
                                    ></vue-tree-list>
                                </div>
                                </template>

                            </div>

                        </div>

                    </div>
            </section>

            <vue-edit-collection v-model="dialog_edit" :collection="edit_collection" v-on:update-collection="updateCollection" vonremove-access="UnshareProjectWithUser"></vue-edit-collection>
            <vue-copy-collection v-model="dialog_copy_collection" v-on:collection-copied="loadCollections"></vue-copy-collection>
            <vue-move-collection v-model="dialog_move_collection" v-on:collection-moved="loadCollections"></vue-move-collection>

            <template>
                <v-menu
                    v-model="action_menu"
                    :position-x="action_menu_x"
                    :position-y="action_menu_y"
                    absolute
                    offset-y
                >
                    <v-list>
                        <v-list-item v-if="getCollectionPermissions(action_menu_id).can_edit">
                            <v-list-item-title @click="editCollectionById(action_menu_id)"><v-btn text>{{$t('edit')}}</v-btn></v-list-item-title>
                        </v-list-item>
                        <v-list-item v-if="getCollectionPermissions(action_menu_id).can_admin">    
                            <v-list-item-title @click="addChildCollectionById(action_menu_id)"><v-btn text>{{$t('Add sub-collection')}}</v-btn></v-list-item-title>
                        </v-list-item>
                        <v-list-item v-if="getCollectionPermissions(action_menu_id).can_manage_access">
                            <v-list-item-title @click="ManageCollectionAccess(action_menu_id)"><v-btn text>{{$t('Manage access')}}</v-btn></v-list-item-title>
                        </v-list-item>
                        <v-list-item v-if="getCollectionPermissions(action_menu_id).can_delete">
                            <v-list-item-title @click="DeleteCollection(action_menu_id)"><v-btn text>{{$t('delete')}}</v-btn></v-list-item-title>
                        </v-list-item>
          
                    </v-list>
                </v-menu>
            </template>

            <!-- Collections count at bottom -->
            <div v-if="collections && collections.length > 0" class="mt-4 p-3 text-center text-muted">
                <small>{{collections.length}} {{$t('collections')}}</small>
            </div>

        </div>
                
    </div>
    `
});

