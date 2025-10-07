Vue.component('vue-transfer-ownership', {
    props: ['value','projects'],
    data() {
        return {
            users:[],
            selected_user:'',
            is_loading: false,
            search: null,
        }
    },
    mounted: async function(){        
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
        transferOwnership: async function() {
            
            vm=this;
            let url=CI.site_url + '/api/editor/transfer_ownership/';
            let options={
                "projects":vm.projects,
                "owner_id":vm.selected_user.id
            }

            try {
                let response = await axios.post(url, options);
                vm.selected_user=[];
                vm.dialog=false;
                vm.$emit('transfer-ownership','updated');
            } catch (error) {
                console.log("transfer ownership error", error);
                vm.errors=error;
                await vm.$alert(vm.$extractErrorMessage(error), { 
                    title: "Transfer Failed",
                    color: 'error' 
                });
            }
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
        <div class="vue-project-transfer-ownership">
        <template>
            <div class="text-center">
                <v-dialog
                v-model="dialog"                
                scrollable
                max-width="500px"
                >
                
                <v-card>
                    <v-card-title class="text-h5 lighten-2">
                        {{ $t('transfer_ownership') }}
                    </v-card-title>
                    <v-card-subtitle>
                        {{ $t('select_user_to_transfer_ownership') }}
                    </v-card-subtitle>
                    
                    <v-card-text>

                    <v-autocomplete
                        v-model="selected_user"
                        :loading="is_loading"
                        :search-input.sync="search"
                        :items="users"
                        solo
                        chips
                        color="blue-grey lighten-2"
                        :label="$t('search_user')"
                        item-text="username"
                        item-value="id"                        
                        cache-items
                        return-object
                        :no-data-text="$t('type_user_name_or_email')"
                    >
                        <template v-slot:selection="data">
                            <v-chip
                                v-bind="data.attrs"
                                :input-value="data.selected"
                                close
                                @click="data.select"                                
                                @click:close="selected_user=[]"
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
                        
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions>
                    
                        <v-btn
                            :disabled="selected_user.length==0"                            
                            class="mr-3"                            
                            color="primary"
                            small                         
                            @click="transferOwnership"
                        >{{ $t('transfer_ownership') }}
                        </v-btn>
                        <v-btn                            
                            class=""
                            color="grey"
                            small
                            @click="dialog=false"
                        >{{ $t('close') }}
                        </v-btn>
                    
                    </v-card-actions>
                    
                </v-card>
                </v-dialog>
            </div>
        </template>
        
    </div>
    `
});

