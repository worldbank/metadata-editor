Vue.component('vue-template-share', {
    props: ['value','users','template_id'],
    data() {
        return {            
            selected: [],
            user_access: 'view',
            is_loading: false,
            selected_users: [],
            search: null,
            shared_users: [],//users with access to the template
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
        }
    },
    created:function(){
        this.loadTemplateUsers();
    },
    watch:{
        search (val) {
            if (!val) return
            if (this.is_loading) return
            this.is_loading = true
  
          let vm=this;
          this.searchUsers(val);
        }          
    },
    methods: {   
        erorrMessageToText: function(error){
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
        getSelectedUsersList: function(){
            let selected_users = [];
            for (let i = 0; i < this.selected_users.length; i++) {
                selected_users.push(this.selected_users[i].id);
            }
            return selected_users;
        },
        loadTemplateUsers: function(){
            let vm = this;
            let url = CI.site_url + '/api/templates/share/' + this.template_id;
            axios.get(url)
            .then(response => {
                vm.shared_users = response.data.users;
            })
            .catch(function (error) {
                console.log(error);
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
                    'template_uid': this.template_id
                });
            }

            let url = CI.site_url + '/api/templates/share/' + this.template_id;

            axios.post(url,
                form_data
            )
            .then(response => {
                vm.selected_users = [];
                vm.user_access = 'view';
                vm.loadTemplateUsers();
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("failed") + ": " + vm.erorrMessageToText(error));
            })
            .finally(() => (this.is_loading = false));
        },        
        updateAccess: function(index){
            let form_data=[]
            let vm=this;

            form_data.push({
                'user_id': this.shared_users[index]['user_id'],
                'permissions':this.shared_users[index]['permissions'],
                'template_uid':this.template_id
            });

            let url = CI.site_url + '/api/templates/share/' + this.template_id + '?update=1';
            axios.post(url,
                form_data
            )
            .then(response => {
                console.log(response);
                vm.loadTemplateUsers();
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("failed") + ": " + vm.erorrMessageToText(error));
            });
            
        },
        removeAccess: function(index) {
            
            if (!confirm(vm.$t("confirm_remove_user_access"))) {
                return;
            }

            let form_data ={
                'template_uid':this.template_id,
                'user_id':this.shared_users[index]['user_id']
            }

            let user_id = this.shared_users[index]['user_id'];

            let vm=this;
            let url = CI.site_url + '/api/templates/remove_access';

            axios.post(url, form_data)
            .then(response => {
                console.log(response);
                vm.shared_users.splice(index, 1);
            })
            .catch(function (error) {
                console.log(error);
                alert(vm.$t("failed") + ": " + vm.erorrMessageToText(error));
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
        },300)
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
                width="800px"
                scrollable                
                >

                <v-card>
                    <v-card-title class="text-h5 grey lighten-2">
                    {{$t('share_template')}}
                    </v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="6">
                                
                                <!--select-->
                    <v-autocomplete
                        v-model="selected_users"
                        :loading="is_loading"
                        :search-input.sync="search"
                        @input="search=null"
                        :items="users"
                        solo
                        chips
                        color="blue-grey lighten-2"
                        :label="$t('search_users_to_select')"
                        item-text="username"
                        item-value="id"
                        multiple
                        cache-items
                        return-object
                        :no-data-text="$t('type_user_name_or_email_to_search')"                                                
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

                            </v-col>
                            <v-col cols="4">
                                <v-select
                                    :items="user_roles"
                                    v-model="user_access"
                                    solo
                                    item-text="text"
                                    item-value="value"
                                    label=""
                                ></v-select>
                            </v-col>
                            <v-col cols="2">
                                <v-btn
                                    block
                                    class="ma-2 mr-3"
                                    outlined
                                    color="primary"
                                    large
                                    @click="addAccess"
                                >{{$t('share')}}
                                </v-btn>
                            </v-col>

                        </v-row>
                        


                    <div class="table-responsive mt-3" style="max-height:200px;overflow:auto;" v-if="shared_users!=''">
                    
                        <v-simple-table style="font-size:small;">
                            <thead>
                            <tr>
                                <th>{{$t('username')}}</th>
                                <th>{{$t('role')}}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(user,index) in shared_users" :key="index">
                                <td>
                                    <div class="capitalize">{{user.username}}</div>
                                    <div class="text-muted text-secondary">{{user.email}}</div>
                                </td>
                                <td>
                                    <select 
                                        class="form-control form-control-sm" 
                                        v-model="user.permissions" 
                                        @change="updateAccess(index)"
                                    >
                                        <option value="view">{{$t('view')}}</option>
                                        <option value="edit">{{$t('edit')}}</option>
                                        <option value="admin">{{$t('admin')}}</option>
                                    </select>
                                </td>
                                <td>
                                    <v-btn icon small color="red" @click="removeAccess(index)"><v-icon>mdi-delete-outline</v-icon></v-btn>
                                </td>
                            </tr>
                            </tbody>
                        </v-simple-table>
                    </div>
                    <div v-else>
                        <div class="text-center m-3" >{{$t('no_users_have_access_to_template')}}</div>
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
                        {{$t('close')}}
                    </v-btn>
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        
    </div>
    `
});

