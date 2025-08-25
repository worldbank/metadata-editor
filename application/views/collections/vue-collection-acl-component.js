Vue.component('vue-collection-acl', {
    props: ['collection_id'],
    data() {
        return {
            selected: [],
            user_access: 'view',
            is_loading: false,
            selected_users: [],
            search: null,
            users: [], // Available users for selection
            shared_users: [], // users with collection-level access
                    user_roles: [
            {
                'value': 'view',
                'text': this.$t('view')
            },
            {
                'value': 'edit',
                'text': this.$t('edit')
            },
            {
                'value': 'admin',
                'text': this.$t('admin')
            }
        ],
        }
    },
    mounted: function() {
        if (this.collection_id) {
            this.loadCollectionAclUsers();
        }
    },
    watch: {
        search(val) {
            if (!val) return
            if (this.is_loading) return
            this.is_loading = true

            let vm = this;
            this.searchUsers(val);
        }
    },
    methods: {
        errorMessageToText: function(error) {
            let error_text = '';
            if (error.response.data.errors) {
                for (let key in error.response.data.errors) {
                    error_text += error.response.data.errors[key] + '\n';
                }
            } else {
                error_text = error.response.data.message;
            }
            return error_text;
        },
        getSelectedUsersList: function() {
            let selected_users = [];
            for (let i = 0; i < this.selected_users.length; i++) {
                selected_users.push(this.selected_users[i].id);
            }
            return selected_users;
        },
        loadCollectionAclUsers: function() {
            let vm = this;
            let url = CI.site_url + '/api/collections/user_acl/' + this.collection_id;
            axios.get(url)
                .then(response => {
                    vm.shared_users = response.data.users;
                })
                .catch(function(error) {
                    console.log('Collection ACL error:', error);
                })
                .finally(() => (this.is_loading = false));
        },
        addAccess: async function() {
            let vm = this;
            let form_data = [];

            for (let i = 0; i < this.selected_users.length; i++) {
                form_data.push({
                    'user_id': this.selected_users[i].id,
                    'permissions': this.user_access,
                    'collection_id': this.collection_id
                });
            }

            let url = CI.site_url + '/api/collections/user_acl';

            axios.post(url, form_data)
                .then(response => {
                    vm.selected_users = [];
                    vm.user_access = 'view';
                    vm.loadCollectionAclUsers();
                })
                .catch(function(error) {
                    console.log(error);
                    alert("Failed: " + vm.errorMessageToText(error));
                })
                .finally(() => (this.is_loading = false));
        },
        updateAccess: function(index) {
            let form_data = []
            let vm = this;

            form_data.push({
                'user_id': this.shared_users[index]['user_id'],
                'permissions': this.shared_users[index]['permissions'],
                'collection_id': this.collection_id
            });

            let url = CI.site_url + '/api/collections/user_acl_update';
            axios.post(url, form_data)
                .then(response => {
                    console.log(response);
                    vm.loadCollectionAclUsers();
                })
                .catch(function(error) {
                    console.log(error);
                    alert("Failed: " + vm.errorMessageToText(error));
                });
        },
        removeAccess: function(index) {
            if (!confirm("Are you sure you want to remove collection access for this user?")) {
                return;
            }

            let form_data = {
                'collection_id': this.collection_id,
                'user_id': this.shared_users[index]['user_id']
            }

            let vm = this;
            let url = CI.site_url + '/api/collections/user_acl_remove';

            axios.post(url, form_data)
                .then(response => {
                    console.log(response);
                    vm.loadCollectionAclUsers();
                })
                .catch(function(error) {
                    console.log(error);
                    alert("Failed: " + vm.errorMessageToText(error));
                });
        },
        searchUsers: _.debounce(function(val) {
            let vm = this;
            axios.get(CI.site_url + '/api/users/search?keywords=' + val)
                .then(response => {
                    vm.users = response.data.users;
                    console.log("users", vm.users);
                })
                .catch(function(error) {
                    console.log(error);
                })
                .finally(() => (this.is_loading = false));
        }, 300),
        removeSelectionItem: function(item) {
            let index = this.selected_users.indexOf(item);
            if (index > -1) {
                this.selected_users.splice(index, 1);
            }
        }
    },

    template: `
        <div class="vue-collection-acl">
            <v-card>
                <v-card-title class="text-h5 grey lighten-2">
                    Collection Access Control
                </v-card-title>
                <v-card-subtitle>
                    Select users who can access this collection and manage its settings
                </v-card-subtitle>
                <v-card-text>
                    <p><strong>Collection ID:</strong> {{collection_id}}</p>
                    <p><strong>Loading State:</strong> {{is_loading}}</p>
                    <p><strong>Shared Users:</strong> {{shared_users.length}}</p>
                    
                    <div v-if="is_loading" class="text-center p-4">
                        <v-progress-circular indeterminate color="primary"></v-progress-circular>
                        <p class="mt-2">Loading collection access information...</p>
                    </div>
                    <div v-else>
                        <p>Component loaded successfully!</p>
                        <p>Ready to manage collection access for collection ID: {{collection_id}}</p>
                    </div>
                </v-card-text>


            </v-card>
        </div>
    `
});
