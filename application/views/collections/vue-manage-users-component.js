Vue.component('vue-manage-users', {
    props: ['value','collection'],
    data() {
        return {
            users: [],
            collection_users: [],
            collection_id:'',
            new_user: {
                'user_id':'',
                'permissions':'',
            }
        }
    },
    created:function(){        
        this.collection_id=this.$route.params.id;
        this.getUsers();
        this.getCollectionAccess();
    },
    methods: {        
        updateAccess: function() {
            this.$emit('update-access', JSON.parse(JSON.stringify(this.title)));
        },
        removeAccess: function(index) {
            let vm=this;
            let url = CI.base_url + '/api/collections/remove_user_access';
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
            axios.get(CI.base_url + '/api/users')
            .then(response => {
                vm.users = response.data.users;
                console.log("users",vm.users);
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        //get collection users
        getCollectionAccess: function() {
            let vm=this;
            axios.get(CI.base_url + '/api/collections/user_access/' + this.collection_id)
            .then(response => {
                vm.collection_users = response.data.users;
                console.log("users",vm.users);
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        addCollectionAccess: function() {
            let vm=this;
            let url = CI.base_url + '/api/collections/user_access';
            let form_data = {
                'collection_id':this.collection_id,
                'user_id':this.new_user.user_id,
                'permissions':this.new_user.permissions
            };
            console.log("form data",form_data);

            axios.post(url, form_data)
            .then(response => {
                console.log("add user",response);
                vm.new_user={};
                vm.getCollectionAccess();
            })
            .catch(function (error) {
                console.log(error);
                alert("Error:" + error.response.data.message);
            });
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
        
        <router-link to="/">Return to collections</router-link>
        
        <h3>Manage users by collection</h3>
        <p></p>

        <div class="border-round border-light p-3 mb-5 shadow">
            <h5>Add user</h5>
            <div class="row">
                <div class="col-6">                    
                    <div class="form-group">
                        <label>User</label>
                        <select class="form-control" v-model="new_user.user_id">
                            <option v-for="user in users" :value="user.id">{{user.username}}</option>
                        </select>
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label>Role</label>
                        <select class="form-control" v-model="new_user.permissions">
                            <option value="view">View</option>
                            <option value="edit">Edit</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="col-auto">
                    <label>&nbsp;</label>
                    <div class="form-group">                        
                        <button type="button" class="btn btn-primary" @click="addCollectionAccess()">Add</button>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-sm" v-if="collection_users.length>0">
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>            
            <tr v-for="(user, index) in collection_users">
                <td>
                    <div>{{user.username}}</div>
                    <div class="small text-muted">{{user.email}}</div>
                </td>
                <td>{{user.permissions}}</td>
                <td>
                    <button class="btn btn-link" @click="removeAccess(index)"><v-icon>mdi-delete-outline</v-icon></button>
                </td>
            </tr>
        </table>
        <div v-else>
            <p>No users found</p>
        </div>
        
    </div>
    `
});

