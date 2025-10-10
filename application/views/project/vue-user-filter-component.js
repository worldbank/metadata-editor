Vue.component('vue-user-filter', {
    props: ['value'],
    data() {
        return {
            selected_users: [],
            search: null,
            users: [],
            is_loading: false
        }
    },
    watch: {
        search(val) {
            if (!val) return
            if (this.is_loading) return
            this.is_loading = true
            this.searchUsers(val);
        }
    },
    methods: {
        searchUsers: _.debounce(function(val) {
            let vm = this;
            axios.get(CI.site_url + '/api/users/search?keywords=' + val)
            .then(response => {
                vm.users = response.data.users;
            })
            .catch(function (error) {
                console.log(error);
            })
            .finally(() => (this.is_loading = false));
        }, 300),
        removeSelectionItem: function(item) {
            let index = this.selected_users.indexOf(item);
            if (index > -1) {
                this.selected_users.splice(index, 1);
            }
        },
        applyFilter: function() {
            this.$emit('apply', this.selected_users);
            this.selected_users = [];
            this.search = null;
            this.dialog = false;
        },
        cancel: function() {
            this.selected_users = [];
            this.search = null;
            this.dialog = false;
        }
    },
    computed: {
        dialog: {
            get: function() {
                return this.value;
            },
            set: function(newValue) {
                this.$emit('input', newValue);
            }
        }
    },
    template: `
        <v-dialog v-model="dialog" max-width="600px" scrollable>
            <v-card>
                <v-card-title class="text-h6 grey lighten-2">
                    {{$t('filter_by_user')}}
                </v-card-title>
                <v-card-text class="mt-3">
                    <v-autocomplete
                        v-model="selected_users"
                        :loading="is_loading"
                        :search-input.sync="search"
                        @change="search=''"
                        :items="users"
                        solo
                        chips
                        color="primary"
                        :label="$t('search_user')"
                        item-text="username"
                        item-value="id"
                        multiple
                        return-object
                        :no-data-text="$t('type_user_name_or_email')"
                    >
                        <template v-slot:selection="data">
                            <v-chip
                                v-bind="data.attrs"
                                :input-value="data.selected"
                                close
                                @click="data.select"
                                @click:close="removeSelectionItem(data.item)"
                                small
                            >
                                {{ data.item.username }}
                            </v-chip>
                        </template>
                        <template v-slot:item="data">
                            <v-list-item-content>
                                <v-list-item-title>{{ data.item.username }}</v-list-item-title>
                                <v-list-item-subtitle>{{ data.item.email }}</v-list-item-subtitle>
                            </v-list-item-content>
                        </template>
                    </v-autocomplete>
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn text @click="cancel">{{$t('cancel')}}</v-btn>
                    <v-btn color="primary" @click="applyFilter">{{$t('apply')}}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    `
});

