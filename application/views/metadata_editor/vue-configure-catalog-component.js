// configure-catalog component
Vue.component('configure-catalog', {
    props:['value'],
    data: function () {    
        return {          
            catalog_connections:[],
            catalog:{
                url:'https://'
            },
            editing: false,
            editingId: null,
            loading: false,
            tableHeaders: [
                {
                    text: this.$t('catalog_title'),
                    value: 'info',
                    sortable: true
                },
                {
                    text: this.$t('actions'),
                    value: 'actions',
                    sortable: false,
                    width: '120px'
                }
            ]
        }
    },
    mounted: function(){
        this.loadCatalogConnections();
    },
    methods:{
        loadCatalogConnections: function() {
            const vm = this;
            const url = CI.site_url + '/api/catalog_connections';
            axios.get(url)
            .then(function (response) {
                if(response.data){
                    vm.catalog_connections=response.data.connections;
                }
            })
            .catch(function (error) {
                console.log(error);
            })
            .then(function () {
                console.log("request completed");
            });
        },
        CreateCatalogConnection: function()
        {            
            let error = this.validateNadaUrl(this.catalog.url);
            if (error){
                alert(this.$t('url_validation_failed') + ': ' + this.$t(error));
                return;
            }

            //remove trailing slash
            if (this.catalog.url.slice(-1) == "/"){
                this.catalog.url = this.catalog.url.slice(0, -1);
            }

            let formData = this.catalog;
            console.log("form data", formData);

            const vm = this;
            const url = CI.site_url + '/api/catalog_connections';

            axios.post( url,
                formData,
                {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
            ).then(function(response){
                alert(vm.$t('created'));
                vm.catalog={};
                vm.editing = false;
                vm.editingId = null;
                vm.loading = false;
                vm.loadCatalogConnections();
            })
            .catch(function(response){
                alert(vm.$t('failed'));
                console.log(vm.$t('failed_to_create_catalog_connection'),response);
            }); 
        },
        EditCatalogConnection: function(connection) {
            this.editing = true;
            this.editingId = connection.id;
            this.loadCatalogConnectionDetails(connection);
        },
        
        loadCatalogConnectionDetails: function(connection) {
            const vm = this;
            const url = CI.site_url + '/api/catalog_connections/single/' + connection.id;
            
            this.loading = true;
            this.catalog = { url: 'https://' }; // Reset form
            
            axios.get(url)
            .then(function (response) {
                if(response.data && response.data.connection) {
                    vm.catalog = {
                        title: response.data.connection.title,
                        url: response.data.connection.url,
                        api_key: response.data.connection.api_key || ''
                    };
                }
                vm.loading = false;
            })
            .catch(function (error) {
                console.log("Failed to load catalog connection details", error);
                // Fallback to existing data if API fails
                vm.catalog = {
                    title: connection.title,
                    url: connection.url,
                    api_key: connection.api_key || ''
                };
                vm.loading = false;
            });
        },
        UpdateCatalogConnection: function() {

            let error = this.validateNadaUrl(this.catalog.url);
            if (error){
                alert(this.$t('url_validation_failed') + ': ' + this.$t(error));
                return;
            }

            //remove trailing slash
            if (this.catalog.url.slice(-1) == "/"){
                this.catalog.url = this.catalog.url.slice(0, -1);
            }

            let formData = this.catalog;
            formData.id = this.editingId;
            console.log("update form data", formData);

            const vm = this;
            const url = CI.site_url + '/api/catalog_connections/update';

            axios.post( url,
                formData,
                {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
            ).then(function(response){
                alert(vm.$t('updated'));
                vm.catalog = { url: 'https://' };
                vm.editing = false;
                vm.editingId = null;
                vm.loading = false;
                vm.loadCatalogConnections();
            })
            .catch(function(response){
                alert(vm.$t('failed'));
                console.log(vm.$t('failed_to_update_catalog_connection'),response);
            }); 
        },
        CancelEdit: function() {
            this.catalog = { url: 'https://' };
            this.editing = false;
            this.editingId = null;
            this.loading = false;
        },
        DeleteCatalogConnection: function(catalog_id)
        {
            if (!confirm(this.$t('confirm_delete_catalog_connection'))){
                return;
            }

            const formData = new FormData();
            formData.append('catalog_id', catalog_id);

            const vm = this;
            const url = CI.site_url + '/api/catalog_connections/delete';

            axios.post( url,
                formData,
                {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
            ).then(function(response){
                vm.catalog={};
                vm.loadCatalogConnections();
            })
            .catch(function(response){
                alert("failed");
                console.log("failed to delete catalog connection",response);
            }); 
        },
        validateNadaUrl: function(url)
        {
            if (!url.startsWith('https://') && !url.startsWith('http://')){
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
        }
    },
    
    computed: {
        tableHeaders() {
            return [
                { text: this.$t('catalog_info'), value: 'info', sortable: true },
                { text: this.$t('actions'), value: 'actions', sortable: false, width: '120px' }
            ];
        }
    },  
        template: `
            <div class="configure-catalog-component">

                <v-container fluid class="pt-5" style="max-width: 100%;">

                    <v-row>                        
                        <v-col cols="6">
                            <v-card>
                                <v-card-title>{{$t('catalog_connections')}}</v-card-title>
                                <v-card-text>
                                    <v-data-table
                                        :headers="tableHeaders"
                                        :items="catalog_connections"
                                        :items-per-page="-1"
                                        class="elevation-1"
                                        hide-default-footer
                                        @click:row="EditCatalogConnection"
                                        :single-select="false"
                                    >
                                        <template v-slot:item.info="{ item }">
                                            <div class="text-link">
                                                <div class="font-weight-medium">{{ item.title }}</div>
                                                <div class="caption grey--text">{{ item.url }}</div>
                                            </div>
                                        </template>
                                        <template v-slot:item.actions="{ item }">
                                            <v-btn
                                                icon
                                                x-small
                                                color="primary"
                                                @click.stop="EditCatalogConnection(item)"
                                                :title="$t('edit')"
                                            >
                                                <v-icon>mdi-pencil</v-icon>
                                            </v-btn>
                                            <v-btn
                                                icon
                                                x-small
                                                color="error"
                                                @click.stop="DeleteCatalogConnection(item.id)"
                                                :title="$t('delete')"
                                            >
                                                <v-icon>mdi-close-circle-outline</v-icon>
                                            </v-btn>
                                        </template>
                                    </v-data-table>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="6">

                            <v-card>
                                <v-card-title>{{ editing ? $t('edit_catalog') : $t('configure_new_catalog') }}</v-card-title>
                                <v-card-text>

                                <div v-if="loading" class="text-center py-3">
                                    <v-icon class="mr-2">mdi-loading mdi-spin</v-icon>
                                    {{$t('loading_catalog_details')}}
                                </div>
                                
                                <div v-else>
                                    <div class="mb-2">
                                        <label class="v-label theme--light">{{ $t('catalog_title') }} *</label>
                                    </div>
                                    <v-text-field
                                        v-model="catalog.title"
                                        outlined
                                        dense
                                        required
                                        :rules="[v => !!v || $t('title_required')]"
                                        class="mb-4"
                                    ></v-text-field>

                                    <div class="mb-2">
                                        <label class="v-label theme--light">{{ $t('catalog_url') }} *</label>
                                    </div>
                                    <v-text-field
                                        v-model="catalog.url"
                                        outlined
                                        dense
                                        required
                                        :rules="[v => !!v || $t('url_required')]"
                                        :hint="$t('catalog_url_hint')"
                                        persistent-hint
                                        class="mb-4"
                                    ></v-text-field>

                                    <div class="mb-2">
                                        <label class="v-label theme--light">{{ $t('api_key') }}</label>
                                        <div class="caption grey--text" v-if="editing">({{ $t('leave_blank_to_keep_existing_key') }})</div>                                        
                                    </div>
                                    <v-text-field
                                        v-model="catalog.api_key"
                                        outlined
                                        dense
                                        type="password"
                                        class="mb-4"
                                    ></v-text-field>
                                </div>

                                <div class="mt-4">
                                    <v-btn 
                                        color="primary" 
                                        @click="editing ? UpdateCatalogConnection() : CreateCatalogConnection()" 
                                        class="mr-2" 
                                        :disabled="loading"
                                        :loading="loading"
                                    >
                                        {{ editing ? $t('update') : $t('submit') }}
                                    </v-btn>
                                    <v-btn 
                                        color="secondary" 
                                        @click="CancelEdit" 
                                        v-if="editing" 
                                        :disabled="loading"
                                    >
                                        {{ $t('cancel') }}
                                    </v-btn>
                                </div>
                                </v-card-text>
                            </v-card>

                        </v-col>
                    </v-row>


                </v-container>


            </div>          
            `    
});

