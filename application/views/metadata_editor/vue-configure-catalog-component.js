/// configure new catalog for publishing
Vue.component('configure-catalog', {
    props:['value'],
    data: function () {    
        return {          
            catalog_connections:[],
            catalog:{
                url:'https://'
            }
        }
    },
    created: function(){
        this.loadCatalogConnections();
    },
    methods:{
        loadCatalogConnections: function() {
            vm=this;
            let url=CI.base_url + '/api/editor/catalog_connections';
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
            //remove trailing slash
            if (this.catalog.url.slice(-1) == "/"){
                this.catalog.url = this.catalog.url.slice(0, -1);
            }

            let formData = this.catalog;//new FormData();
            //formData.append('user_id', this.LoggedInUserID);
            //formData.user_id=this.LoggedInUserID;

            console.log("form data",formData);

            vm=this;
            let url=CI.base_url + '/api/editor/catalog_connections';

            axios.post( url,
                formData,
                {
                    /*headers: {
                        'Content-Type': 'multipart/form-data'
                    }*/
                }
            ).then(function(response){
                alert("Created");
                vm.catalog={};
                vm.loadCatalogConnections();
            })
            .catch(function(response){
                alert("failed");
                console.log("failed to create catalog connection",response);
            }); 
        },
        DeleteCatalogConnection: function(catalog_id)
        {
            if (!confirm("Are you sure you want to delete this catalog connection?")){
                return;
            }

            let formData = new FormData();
            formData.append('catalog_id', catalog_id);

            vm=this;
            let url=CI.base_url + '/api/editor/catalog_connections_delete';

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
    },
    
    computed: {

        LoggedInUserID(){
            return 1;
        },
        
    },  
    template: `
            <div class="configure-catalog-component">
                
                <div class="container-fluid p-5">

                    <div class="row">                        
                        <div class="col-6">
                            <v-card>
                                <v-card-title>Catalogs</v-card-title>
                                <v-card-text>
                                    <table class="table table-sm table-bordered table-striped">
                                        <tr>
                                            <th>Title</th>
                                            <th>Catalog URL</th>
                                        </tr>
                                        <tr v-for="connection in catalog_connections">
                                            <td>{{connection.title}}</td>
                                            <td>{{connection.url}}</td>
                                            <td><button type="button" class="btn btn-sm" @click="DeleteCatalogConnection(connection.id)">
                                            <v-icon color="red">mdi-close-circle-outline</v-icon>
                                            </button></td>
                                        </tr>
                                    </table>
                                </v-card-text>
                            </v-card>
                        </div>

                        <div class="col-6">

                            <v-card>
                                <v-card-title>Configure new catalog</v-card-title>
                                <v-card-text>

                                <div class="form-group form-field">
                                    <label for="titlte">Catalog title</label> 
                                    <span><input type="text" id="title" class="form-control" v-model="catalog.title"/></span> 
                                </div>

                                <div class="form-group form-field">
                                    <label for="url">Catalog URL</label> 
                                    <span><input type="text" id="url" class="form-control" v-model="catalog.url"/></span>                                 
                                </div>

                                <div class="form-group form-field">
                                    <label for="api_key">API Key</label> 
                                    <span><input type="password" id="api_key" class="form-control" v-model="catalog.api_key"/></span>
                                </div>

                                <v-btn small color="primary"  @click="CreateCatalogConnection">Submit</v-btn>
                                </v-card-text>
                            </v-card>

                        </div>
                    </div>


                </div>


            </div>          
            `    
});

