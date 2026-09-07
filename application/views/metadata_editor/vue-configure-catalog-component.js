// configure-catalog component
Vue.component('configure-catalog', {
    props: {
        value: {},
        showPublishLink: {
            type: Boolean,
            default: true
        }
    },
    data: function () {    
        return {          
            catalog_connections:[],
            catalog:{
                title: '',
                uid: '',
                url: 'https://',
                type: 'nada',
                is_official: false,
                api_key: '',
                has_credential: false,
                created_by: null
            },
            dialog: false,
            editing: false,
            editingId: null,
            loading: false,
            saving: false,
            search: '',
            is_admin: !!(CI && CI.user_info && CI.user_info.is_admin),
            can_manage_official: !!(CI && CI.user_info && (CI.user_info.can_manage_official || CI.user_info.is_admin)),
            current_user_id: CI && CI.user_info && CI.user_info.user_id ? CI.user_info.user_id : null,
            curators_dialog: false,
            curators_catalog: null,
            curators: [],
            curators_loading: false,
            curators_saving: false,
            curator_selected: null,
            curator_search: '',
            curator_user_results: [],
            curator_search_loading: false,
            curator_search_timer: null,
            catalog_was_official: false
        }
    },
    mounted: function(){
        this.loadCatalogConnections();
    },
    computed: {
        tableHeaders() {
            return [
                { text: this.$t('catalog_title'), value: 'title', sortable: true },
                { text: this.$t('id'), value: 'id', sortable: true, width: '80px' },
                { text: this.$t('type'), value: 'is_official', sortable: true, width: '120px' },
                { text: this.$t('your_api_key'), value: 'has_credential', sortable: true, width: '130px' },
                { text: this.$t('actions'), value: 'actions', sortable: false, width: '176px', align: 'end' }
            ];
        },
        catalogTypeItems() {
            return [
                { text: this.$t('catalog_type_nada'), value: 'nada' },
                { text: this.$t('catalog_type_other'), value: 'other' }
            ];
        },
        showAvailabilityRadios() {
            return !!this.can_manage_official;
        },
        isNadaType() {
            return !this.catalog.type || this.catalog.type === 'nada';
        },
        canEditIdentity() {
            if (!this.editing) {
                return true;
            }
            return this.canEditCatalogIdentity(this.catalog);
        },
        isKeyOnlyEdit() {
            return this.editing && !this.canEditIdentity;
        },
        dialogTitle() {
            if (!this.editing) {
                return this.$t('create_new_catalog');
            }
            if (this.isKeyOnlyEdit) {
                return this.catalog.has_credential
                    ? this.$t('update_your_api_key')
                    : this.$t('add_your_api_key');
            }
            return this.$t('edit_catalog');
        },
        saveButtonLabel() {
            if (this.isKeyOnlyEdit) {
                return this.$t('save_api_key');
            }
            return this.editing ? this.$t('update') : this.$t('submit');
        },
        filteredCatalogs() {
            var q = (this.search || '').trim().toLowerCase();
            if (!q) {
                return this.catalog_connections;
            }
            var vm = this;
            return this.catalog_connections.filter(function (item) {
                var kind = vm.catalogOfficialLabel(item);
                return [item.id, item.title, item.uid, item.url, item.type, kind].some(function (val) {
                    return String(val || '').toLowerCase().indexOf(q) !== -1;
                });
            });
        }
    },
    methods:{
        loadCatalogConnections: function() {
            const vm = this;
            const url = CI.site_url + '/api/catalog_connections';
            axios.get(url)
            .then(function (response) {
                if(response.data){
                    vm.catalog_connections=response.data.connections || [];
                    if (typeof response.data.is_admin !== 'undefined') {
                        vm.is_admin = !!response.data.is_admin;
                    }
                    if (typeof response.data.can_manage_official !== 'undefined') {
                        vm.can_manage_official = !!response.data.can_manage_official;
                    }
                    if (response.data.current_user_id) {
                        vm.current_user_id = response.data.current_user_id;
                    }
                }
            })
            .catch(function (error) {
                console.log(error);
            });
        },
        resetCatalogForm: function() {
            this.catalog = {
                title: '',
                uid: '',
                url: 'https://',
                type: 'nada',
                is_official: !!this.can_manage_official,
                api_key: '',
                has_credential: false,
                created_by: this.current_user_id
            };
        },
        OpenCreateDialog: function() {
            this.resetCatalogForm();
            this.editing = false;
            this.editingId = null;
            this.loading = false;
            this.dialog = true;
        },
        EditCatalogConnection: function(connection) {
            this.editing = true;
            this.editingId = connection.id;
            this.catalog.has_credential = !!connection.has_credential;
            this.catalog.is_official = this.isOfficialCatalog(connection);
            this.catalog_was_official = this.isOfficialCatalog(connection);
            this.catalog.created_by = connection.created_by;
            this.dialog = true;
            this.loadCatalogConnectionDetails(connection);
        },
        CreateCatalogConnection: function()
        {            
            if (this.isNadaType) {
                let error = this.validateNadaUrl(this.catalog.url);
                if (error){
                    alert(this.$t('url_validation_failed') + ': ' + this.$t(error));
                    return;
                }
                if (this.catalog.url && this.catalog.url.slice(-1) == "/"){
                    this.catalog.url = this.catalog.url.slice(0, -1);
                }
            }

            let formUrl = this.catalog.url;
            if (!this.isNadaType && (formUrl === 'https://' || formUrl === 'http://')) {
                formUrl = '';
            }
            let formData = {
                title: this.catalog.title,
                type: this.catalog.type || 'nada',
                url: formUrl,
                api_key: this.catalog.api_key
            };
            if (this.can_manage_official) {
                formData.is_official = !!this.catalog.is_official;
            }

            const vm = this;
            vm.saving = true;
            const url = CI.site_url + '/api/catalog_connections';

            axios.post( url, formData)
            .then(function(){
                vm.resetCatalogForm();
                vm.editing = false;
                vm.editingId = null;
                vm.saving = false;
                vm.dialog = false;
                vm.loadCatalogConnections();
            })
            .catch(function(error){
                vm.saving = false;
                alert(vm.apiErrorMessage(error, vm.$t('failed_to_create_catalog_connection')));
            }); 
        },
        loadCatalogConnectionDetails: function(connection) {
            const vm = this;
            const url = CI.site_url + '/api/catalog_connections/single/' + connection.id;
            
            this.loading = true;
            this.resetCatalogForm();
            this.catalog.uid = connection.uid || '';
            this.catalog.has_credential = !!connection.has_credential;
            this.catalog.is_official = this.isOfficialCatalog(connection);
            this.catalog.created_by = connection.created_by;
            
            axios.get(url)
            .then(function (response) {
                if(response.data && response.data.connection) {
                    vm.catalog = {
                        title: response.data.connection.title,
                        uid: response.data.connection.uid || '',
                        url: response.data.connection.url || '',
                        type: response.data.connection.type || 'nada',
                        is_official: vm.isOfficialCatalog(response.data.connection),
                        api_key: '',
                        has_credential: !!response.data.connection.has_credential,
                        created_by: response.data.connection.created_by
                    };
                    vm.catalog_was_official = vm.isOfficialCatalog(response.data.connection);
                }
                vm.loading = false;
            })
            .catch(function (error) {
                console.log("Failed to load catalog connection details", error);
                vm.catalog = {
                    title: connection.title,
                    uid: connection.uid || '',
                    url: connection.url || '',
                    type: connection.type || 'nada',
                    is_official: vm.isOfficialCatalog(connection),
                    api_key: '',
                    has_credential: !!connection.has_credential,
                    created_by: connection.created_by
                };
                vm.loading = false;
            });
        },
        OpenCuratorsDialog: function(item) {
            if (!this.can_manage_official || !this.isOfficialCatalog(item)) {
                return;
            }
            this.curators_catalog = item;
            this.curators = [];
            this.curator_selected = null;
            this.curator_search = '';
            this.curator_user_results = [];
            this.curators_dialog = true;
            this.loadCatalogCurators();
        },
        CloseCuratorsDialog: function() {
            this.curators_dialog = false;
            this.curators_catalog = null;
            this.curators = [];
            this.curator_selected = null;
            this.curator_search = '';
            this.curator_user_results = [];
        },
        loadCatalogCurators: function() {
            if (!this.curators_catalog || !this.curators_catalog.id) {
                return;
            }
            const vm = this;
            vm.curators_loading = true;
            axios.get(CI.site_url + '/api/catalog_connections/curators/' + this.curators_catalog.id)
            .then(function(response) {
                vm.curators = (response.data && response.data.curators) ? response.data.curators : [];
            })
            .catch(function(error) {
                alert(vm.apiErrorMessage(error, vm.$t('failed')));
            })
            .then(function() {
                vm.curators_loading = false;
            });
        },
        searchCuratorUsers: function(val) {
            const vm = this;
            if (this.curator_search_timer) {
                clearTimeout(this.curator_search_timer);
            }
            const keywords = (val || '').trim();
            if (keywords.length < 2) {
                this.curator_user_results = [];
                this.curator_search_loading = false;
                return;
            }
            this.curator_search_loading = true;
            this.curator_search_timer = setTimeout(function() {
                axios.get(CI.site_url + '/api/catalog_connections/search_users', {
                    params: { keywords: keywords }
                })
                .then(function(response) {
                    var users = (response.data && response.data.users) ? response.data.users : [];
                    vm.curator_user_results = users.map(function(user) {
                        user.label = vm.curatorUserLabel(user);
                        return user;
                    });
                })
                .catch(function() {
                    vm.curator_user_results = [];
                })
                .then(function() {
                    vm.curator_search_loading = false;
                });
            }, 300);
        },
        curatorUserLabel: function(user) {
            if (!user) {
                return '';
            }
            const name = [user.first_name, user.last_name].filter(Boolean).join(' ').trim();
            if (name && user.username) {
                return name + ' (' + user.username + ')';
            }
            return name || user.username || user.email || '';
        },
        AddCatalogCurator: function() {
            if (!this.curators_catalog || !this.curator_selected || !this.curator_selected.id) {
                return;
            }
            const vm = this;
            vm.curators_saving = true;
            axios.post(CI.site_url + '/api/catalog_connections/curators/' + this.curators_catalog.id, {
                user_id: this.curator_selected.id
            })
            .then(function() {
                vm.curator_selected = null;
                vm.curator_search = '';
                vm.curator_user_results = [];
                vm.loadCatalogCurators();
            })
            .catch(function(error) {
                alert(vm.apiErrorMessage(error, vm.$t('failed')));
            })
            .then(function() {
                vm.curators_saving = false;
            });
        },
        RemoveCatalogCurator: function(user) {
            if (!this.curators_catalog || !user || !user.user_id) {
                return;
            }
            if (!confirm(this.$t('confirm_remove_catalog_curator'))) {
                return;
            }
            const vm = this;
            vm.curators_saving = true;
            axios.post(CI.site_url + '/api/catalog_connections/remove_curator', {
                catalog_id: this.curators_catalog.id,
                user_id: user.user_id
            })
            .then(function() {
                vm.loadCatalogCurators();
            })
            .catch(function(error) {
                alert(vm.apiErrorMessage(error, vm.$t('failed')));
            })
            .then(function() {
                vm.curators_saving = false;
            });
        },
        UpdateCatalogConnection: function() {

            if (this.canEditIdentity && this.isNadaType) {
                let error = this.validateNadaUrl(this.catalog.url);
                if (error){
                    alert(this.$t('url_validation_failed') + ': ' + this.$t(error));
                    return;
                }
                if (this.catalog.url && this.catalog.url.slice(-1) == "/"){
                    this.catalog.url = this.catalog.url.slice(0, -1);
                }
            }

            if (this.can_manage_official && this.catalog_was_official && !this.catalog.is_official) {
                if (!confirm(this.$t('catalog_unshare_confirm'))) {
                    return;
                }
            }

            let formData = {
                id: this.editingId,
                api_key: this.catalog.api_key
            };
            if (this.canEditIdentity) {
                formData.title = this.catalog.title;
                formData.type = this.catalog.type || 'nada';
                formData.url = this.catalog.url;
            }
            if (this.can_manage_official) {
                formData.is_official = !!this.catalog.is_official;
            }

            const vm = this;
            vm.saving = true;
            const url = CI.site_url + '/api/catalog_connections/update';

            axios.post( url, formData)
            .then(function(){
                vm.resetCatalogForm();
                vm.editing = false;
                vm.editingId = null;
                vm.saving = false;
                vm.dialog = false;
                vm.loadCatalogConnections();
            })
            .catch(function(error){
                vm.saving = false;
                alert(vm.apiErrorMessage(error, vm.$t('failed_to_update_catalog_connection')));
            }); 
        },
        SaveCatalog: function() {
            if (this.editing) {
                this.UpdateCatalogConnection();
            } else {
                this.CreateCatalogConnection();
            }
        },
        CancelEdit: function() {
            this.resetCatalogForm();
            this.editing = false;
            this.editingId = null;
            this.loading = false;
            this.saving = false;
            this.dialog = false;
        },
        DeleteMyApiKey: function(catalog_id)
        {
            if (!confirm(this.$t('confirm_remove_api_key'))){
                return;
            }
            this.postDelete(catalog_id, false);
        },
        DeleteCatalog: function(catalog_id)
        {
            var item = this.catalog_connections.find(function (row) {
                return row.id === catalog_id;
            });
            var official = this.isOfficialCatalog(item);
            if (!confirm(this.$t(official ? 'confirm_delete_catalog' : 'confirm_delete_private_catalog'))){
                return;
            }
            this.postDelete(catalog_id, true);
        },
        postDelete: function(catalog_id, deleteCatalog)
        {
            const formData = new FormData();
            formData.append('catalog_id', catalog_id);
            if (deleteCatalog) {
                formData.append('delete_catalog', '1');
            }

            const vm = this;
            const url = CI.site_url + '/api/catalog_connections/delete';

            axios.post( url, formData)
            .then(function(){
                if (vm.editingId === catalog_id) {
                    vm.CancelEdit();
                }
                vm.loadCatalogConnections();
            })
            .catch(function(error){
                alert(vm.apiErrorMessage(error, vm.$t('failed')));
            }); 
        },
        apiErrorMessage: function(error, fallback) {
            if (error && error.response && error.response.data && error.response.data.message) {
                return error.response.data.message;
            }
            return fallback || this.$t('failed');
        },
        validateNadaUrl: function(url)
        {
            if (!url || (!url.startsWith('https://') && !url.startsWith('http://'))){
                return 'url_must_start_with_http';
            }

            if (url.includes('index.php') || url.includes('/api')){
                return 'url_must_point_to_root';
            }

            try {
                new URL(url);
            } catch (error) {
                return 'url_is_invalid';
            }

            return null;
        },
        copyCatalogId: function(item, event) {
            if (event) {
                event.stopPropagation();
            }
            if (!item || item.id == null) {
                return;
            }
            var value = String(item.id);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value);
                return;
            }
            var input = document.createElement('input');
            input.value = value;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        },
        isOfficialCatalog: function(item) {
            if (!item) {
                return false;
            }
            return item.is_official === true || item.is_official === 1 || item.is_official === '1';
        },
        catalogOfficialLabel: function(item) {
            return this.isOfficialCatalog(item) ? this.$t('catalog_shared_chip') : this.$t('catalog_personal_chip');
        },
        catalogOfficialHint: function(item) {
            return this.isOfficialCatalog(item) ? this.$t('catalog_shared_hint') : this.$t('catalog_personal_hint');
        },
        canEditCatalogIdentity: function(item) {
            if (!item) {
                return false;
            }
            if (this.isOfficialCatalog(item)) {
                return !!this.can_manage_official;
            }
            return item.created_by != null && Number(item.created_by) === Number(this.current_user_id);
        },
        canDeleteCatalog: function(item) {
            return this.canEditCatalogIdentity(item);
        },
        editRowTitle: function(item) {
            if (this.canEditCatalogIdentity(item)) {
                return this.$t('edit');
            }
            return item && item.has_credential
                ? this.$t('update_your_api_key')
                : this.$t('add_your_api_key');
        },
        apiKeyStatusTitle: function(item) {
            if (item && item.has_credential) {
                return this.$t('api_key_saved');
            }
            return this.$t('add_your_api_key');
        }
    },
        template: `
            <div class="configure-catalog-component">
                <div class="mt-5 mb-5">
                    <div class="d-flex">
                        <div class="flex-grow-1 flex-shrink-0 mr-auto">
                            <h3 class="mt-3">{{$t('catalog_connections')}}</h3>
                        </div>
                        <div class="justify-content-end align-self-center">
                            <v-btn small color="primary" @click="OpenCreateDialog">{{$t('create_new_catalog')}}</v-btn>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-3 shadow mt-2">
                    <v-text-field
                        v-model="search"
                        :placeholder="$t('search')"
                        dense
                        solo
                        flat
                        hide-details
                        clearable
                        class="mb-3"
                        prepend-inner-icon="mdi-magnify"
                        style="max-width: 320px;"
                    ></v-text-field>

                    <div class="p-3 border text-center text-danger" v-if="!catalog_connections || catalog_connections.length<1">
                        {{$t('no_catalogs_found')}}
                    </div>

                    <v-data-table
                        v-else
                        :headers="tableHeaders"
                        :items="filteredCatalogs"
                        :items-per-page="-1"
                        class="elevation-0 catalogs-table"
                        hide-default-footer
                        @click:row="EditCatalogConnection"
                    >
                        <template v-slot:item.title="{ item }">
                            <div class="d-flex align-center catalogs-title-cell">
                                <div class="catalogs-row-icon">
                                    <v-icon color="primary" size="40">mdi-web</v-icon>
                                </div>
                                <div>
                                    <div class="font-weight-medium catalogs-title">{{ item.title }}</div>
                                    <div v-if="item.url" class="catalogs-title-meta catalogs-url-sub">{{ item.url }}</div>
                                </div>
                            </div>
                        </template>
                        <template v-slot:item.id="{ item }">
                            <span
                                class="catalogs-id"
                                :title="$t('copy')"
                                @click.stop="copyCatalogId(item, $event)"
                            >{{ item.id }}</span>
                        </template>
                        <template v-slot:item.is_official="{ item }">
                            <v-chip
                                x-small
                                label
                                :color="isOfficialCatalog(item) ? 'indigo lighten-4' : 'grey lighten-3'"
                                class="catalogs-type-chip"
                                :title="catalogOfficialHint(item)"
                            >{{ catalogOfficialLabel(item) }}</v-chip>
                        </template>
                        <template v-slot:item.has_credential="{ item }">
                            <v-icon
                                v-if="item.has_credential"
                                small
                                color="success"
                                :title="apiKeyStatusTitle(item)"
                            >mdi-key</v-icon>
                            <v-icon
                                v-else
                                small
                                color="grey lighten-1"
                                :title="apiKeyStatusTitle(item)"
                            >mdi-key-outline</v-icon>
                        </template>
                        <template v-slot:item.actions="{ item }">
                            <v-btn
                                v-if="can_manage_official && isOfficialCatalog(item)"
                                icon
                                x-small
                                color="primary"
                                @click.stop="OpenCuratorsDialog(item)"
                                :title="$t('manage_catalog_curators')"
                            >
                                <v-icon>mdi-account-multiple-outline</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                x-small
                                color="primary"
                                @click.stop="EditCatalogConnection(item)"
                                :title="editRowTitle(item)"
                            >
                                <v-icon>mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn
                                icon
                                x-small
                                color="warning"
                                @click.stop="DeleteMyApiKey(item.id)"
                                :title="$t('remove_my_api_key')"
                                :disabled="!item.has_credential"
                            >
                                <v-icon>mdi-key-minus</v-icon>
                            </v-btn>
                            <v-btn
                                v-if="canDeleteCatalog(item)"
                                icon
                                x-small
                                color="error"
                                @click.stop="DeleteCatalog(item.id)"
                                :title="$t('delete_catalog')"
                            >
                                <v-icon>mdi-close-circle-outline</v-icon>
                            </v-btn>
                        </template>
                    </v-data-table>
                </div>

                <v-dialog
                    v-model="dialog"
                    max-width="640"
                    persistent
                    content-class="catalog-connection-dialog"
                >
                    <v-card>
                        <v-card-title class="text-h6 d-flex align-center py-3">
                            <span>{{ dialogTitle }}</span>
                            <v-spacer></v-spacer>
                            <v-chip
                                v-if="editing && !isKeyOnlyEdit && catalog.uid"
                                small
                                label
                                color="grey lighten-3"
                            >{{ catalog.uid }}</v-chip>
                        </v-card-title>
                        <v-card-text :class="{ 'catalog-key-dialog-text': isKeyOnlyEdit }">
                            <div v-if="loading" class="text-center py-6">
                                <v-icon class="mr-2">mdi-loading mdi-spin</v-icon>
                                {{$t('loading_catalog_details')}}
                            </div>
                            <div v-else-if="isKeyOnlyEdit">
                                <div class="catalog-readonly-summary mb-4">
                                    <div class="catalog-readonly-title">{{ catalog.title }}</div>
                                    <div v-if="catalog.url" class="catalogs-url-sub mt-1">{{ catalog.url }}</div>
                                </div>

                                <div class="mb-2">
                                    <label class="v-label theme--light">{{ $t('your_api_key') }}</label>
                                    <div class="caption grey--text">
                                        {{ catalog.has_credential ? $t('leave_blank_to_keep_existing_key') : $t('your_api_key_hint') }}
                                    </div>
                                </div>
                                <v-text-field
                                    v-model="catalog.api_key"
                                    outlined
                                    dense
                                    hide-details
                                    type="password"
                                ></v-text-field>
                            </div>
                            <div v-else>
                                <div class="mb-2">
                                    <label class="v-label theme--light">{{ $t('catalog_type') }}</label>
                                </div>
                                <v-select
                                    v-model="catalog.type"
                                    :items="catalogTypeItems"
                                    outlined
                                    dense
                                    hide-details
                                    class="mb-4"
                                ></v-select>

                                <div class="mb-2">
                                    <label class="v-label theme--light">{{ $t('catalog_title') }} *</label>
                                </div>
                                <v-text-field
                                    v-model="catalog.title"
                                    outlined
                                    dense
                                    hide-details
                                    class="mb-4"
                                ></v-text-field>

                                <div class="mb-2">
                                    <label class="v-label theme--light">{{ $t('catalog_url') }} <span v-if="isNadaType">*</span></label>
                                </div>
                                <v-text-field
                                    v-model="catalog.url"
                                    :hint="$t('catalog_url_hint')"
                                    persistent-hint
                                    outlined
                                    dense
                                    class="mb-4"
                                ></v-text-field>

                                <div class="mb-2">
                                    <label class="v-label theme--light">{{ $t('api_key') }}</label>
                                    <div v-if="editing && catalog.has_credential" class="caption grey--text">
                                        {{ $t('leave_blank_to_keep_existing_key') }}
                                    </div>
                                </div>
                                <v-text-field
                                    v-model="catalog.api_key"
                                    outlined
                                    dense
                                    hide-details
                                    class="mb-4"
                                    type="password"
                                ></v-text-field>

                                <div v-if="showAvailabilityRadios">
                                    <v-radio-group v-model="catalog.is_official" hide-details class="mt-0">
                                        <v-radio :value="true" class="mb-3">
                                            <template v-slot:label>
                                                <div>
                                                    <div class="font-weight-bold">{{ $t('catalog_shared') }}</div>
                                                    <div class="catalog-availability-hint">{{ $t('catalog_shared_hint') }}</div>
                                                </div>
                                            </template>
                                        </v-radio>
                                        <v-radio :value="false">
                                            <template v-slot:label>
                                                <div>
                                                    <div class="font-weight-bold">{{ $t('catalog_personal') }}</div>
                                                    <div class="catalog-availability-hint">{{ $t('catalog_personal_hint') }}</div>
                                                </div>
                                            </template>
                                        </v-radio>
                                    </v-radio-group>
                                </div>
                            </div>
                        </v-card-text>
                        <v-card-actions>
                            <v-btn
                                v-if="isKeyOnlyEdit && catalog.has_credential"
                                text
                                color="warning"
                                :disabled="saving"
                                @click="DeleteMyApiKey(editingId)"
                            >{{ $t('remove_my_api_key') }}</v-btn>
                            <v-spacer></v-spacer>
                            <v-btn text :disabled="saving" @click="CancelEdit">{{ $t('cancel') }}</v-btn>
                            <v-btn color="primary" :disabled="loading" :loading="saving" @click="SaveCatalog">
                                {{ saveButtonLabel }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <v-dialog v-model="curators_dialog" max-width="640" scrollable persistent>
                    <v-card>
                        <v-card-title class="text-h6 d-flex align-center">
                            <span>{{ $t('manage_catalog_curators') }}</span>
                            <v-spacer></v-spacer>
                            <v-chip
                                v-if="curators_catalog"
                                small
                                label
                                color="grey lighten-3"
                            >{{ curators_catalog.title }}</v-chip>
                        </v-card-title>
                        <v-card-text>
                            <div class="caption grey--text mb-4">{{ $t('catalog_curators_note') }}</div>
                            <div class="d-flex align-start mb-4">
                                <v-autocomplete
                                    v-model="curator_selected"
                                    :items="curator_user_results"
                                    :loading="curator_search_loading"
                                    :search-input.sync="curator_search"
                                    :placeholder="$t('catalog_curators_search')"
                                    item-text="label"
                                    item-value="id"
                                    return-object
                                    hide-no-data
                                    hide-selected
                                    outlined
                                    dense
                                    hide-details
                                    class="flex-grow-1"
                                    @update:search-input="searchCuratorUsers"
                                ></v-autocomplete>
                                <v-btn
                                    color="primary"
                                    class="ml-2"
                                    :disabled="!curator_selected || curators_saving"
                                    :loading="curators_saving"
                                    @click="AddCatalogCurator"
                                >{{ $t('add_curator') }}</v-btn>
                            </div>
                            <div v-if="curators_loading" class="text-center py-4">
                                <v-icon class="mr-2">mdi-loading mdi-spin</v-icon>
                                {{ $t('loading') }}
                            </div>
                            <div v-else-if="!curators.length" class="caption grey--text">
                                {{ $t('no_catalog_curators') }}
                            </div>
                            <v-list v-else dense>
                                <v-list-item v-for="user in curators" :key="user.user_id">
                                    <v-list-item-content>
                                        <v-list-item-title>{{ curatorUserLabel(user) }}</v-list-item-title>
                                        <v-list-item-subtitle v-if="user.email">{{ user.email }}</v-list-item-subtitle>
                                    </v-list-item-content>
                                    <v-list-item-action>
                                        <v-btn
                                            icon
                                            small
                                            color="error"
                                            :disabled="curators_saving"
                                            :title="$t('remove_curator')"
                                            @click="RemoveCatalogCurator(user)"
                                        >
                                            <v-icon small>mdi-close</v-icon>
                                        </v-btn>
                                    </v-list-item-action>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn text @click="CloseCuratorsDialog">{{ $t('close') }}</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

            </div>
            `    
});
